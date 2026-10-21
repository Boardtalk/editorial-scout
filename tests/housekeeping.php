<?php
/** Non-destructive housekeeping in real InnoDB transactions. No real provider calls. */
if(!isset($checks)||'cli'!==PHP_SAPI){exit(1);}
wp_set_current_user(1);$tidy=Editorial_Scout_Housekeeping::class;
$tidy_tables=array();foreach(array('items','commissions','writing')as$t){$tidy_tables[$t]=Editorial_Scout_Store::table($t);}
$tidy_posts=$wpdb->get_results("SELECT * FROM $wpdb->posts ORDER BY ID",ARRAY_A);$tidy_meta=$wpdb->get_results("SELECT * FROM $wpdb->postmeta ORDER BY meta_id",ARRAY_A);
$tidy_journals=$wpdb->get_results("SELECT * FROM {$tidy_tables['writing']} ORDER BY commission_id",ARRAY_A);
$tidy_usage=Editorial_Scout_OpenAI::usage();$tidy_schedule=Editorial_Scout_Scheduling::state();
$tidy_http=0;$tidy_deny=static function()use(&$tidy_http){$tidy_http++;return new WP_Error('no_housekeeping_network');};add_filter('pre_http_request',$tidy_deny);
Editorial_Scout_Store::save(0,'Tidy active','https://feeds.example.org/tidy-active',true);$ta=Editorial_Scout_Store::sources()[0];
Editorial_Scout_Store::save(0,'Tidy disabled','https://feeds.example.org/tidy-disabled',false);$td=Editorial_Scout_Store::sources()[0];
$tidy_hashes=array();for($i=0;$i<25;$i++){$url='https://feeds.example.org/tidy-story-'.$i;$hash=Editorial_Scout_Store::story_hash($url);$tidy_hashes[]=$hash;Editorial_Scout_Store::ingest($td->id,array(array('url'=>$url,'identity_hash'=>hash('sha256',$url),'title'=>'Housekeeping story '.$i,'published_at'=>null)));}
$tidy_filter=array('source'=>$td->id,'profile'=>'');
scout_assert(array()===Editorial_Scout_Newsroom::offers($tidy_filter),'Disabled-only New Stories are hidden by default');
scout_assert(11===count(Editorial_Scout_Newsroom::offers($tidy_filter+array('include_disabled'=>'1'))),'Administrator can include disabled-source stories with bounded pagination');
scout_assert(0===(Editorial_Scout_Newsroom::counts($tidy_filter)['new']??0)&&25===(Editorial_Scout_Newsroom::counts($tidy_filter+array('include_disabled'=>'1'))['new']??0),'Filtered totals match disabled-source visibility before pagination');
$url='https://feeds.example.org/tidy-story-0?utm_source=second';Editorial_Scout_Store::ingest($ta->id,array(array('url'=>$url,'identity_hash'=>hash('sha256',$url),'title'=>'Mixed source story','published_at'=>null)));
$visible=Editorial_Scout_Newsroom::offers($tidy_filter);scout_assert(1===count($visible)&&$tidy_hashes[0]===$visible[0]->story_hash&&2===(int)$visible[0]->observations,'One active source keeps mixed-source story visible, preserving both observations');
Editorial_Scout_Store::save($ta->id,$ta->name,$ta->url,false);scout_assert(array()===Editorial_Scout_Newsroom::offers($tidy_filter),'Disabling the last active source hides the mixed story without deleting evidence');
Editorial_Scout_Store::save($ta->id,$ta->name,$ta->url,true);scout_assert(1===count(Editorial_Scout_Newsroom::offers($tidy_filter)),'Re-enabling a source restores New Stories visibility');
Editorial_Scout_Store::decide($tidy_hashes[1],'commissioned');Editorial_Scout_Store::decide($tidy_hashes[2],'shortlisted');
scout_assert(1===(Editorial_Scout_Newsroom::counts($tidy_filter)['commissioned']??0)&&1===(Editorial_Scout_Newsroom::counts($tidy_filter)['shortlisted']??0),'Historic editorial choices stay accessible with disabled sources and have accurate filtered counters');
$tidy_commission=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$tidy_tables['commissions']} WHERE story_hash=%s",$tidy_hashes[1]),ARRAY_A);
$tl=Editorial_Scout_Runner::lock();$pv=$tidy::preview('cancel',array($tidy_hashes[1]),$tl);
scout_assert('waiting'===$wpdb->get_var($wpdb->prepare("SELECT status FROM {$tidy_tables['commissions']} WHERE story_hash=%s",$tidy_hashes[1])),'Cancellation preview does not change a commission');
scout_assert(scout_error($tidy::confirm($pv['token'],false,$tl),'forbidden'),'Explicit confirmation is required');
$receipt=$tidy::confirm($pv['token'],true,$tl);scout_assert('saved'===$receipt['rows'][$tidy_hashes[1]]['code'],'Eligible commission cancels after explicit confirmation');
scout_assert(scout_error($tidy::confirm($pv['token'],true,$tl),'stale_batch'),'Double confirmation cannot replay a cancellation');
$cancelled=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$tidy_tables['commissions']} WHERE story_hash=%s",$tidy_hashes[1]),ARRAY_A);
scout_assert('dismissed'===$cancelled['status']&&$tidy_commission['id']===$cancelled['id']&&$tidy_commission['created_at']===$cancelled['created_at']&&$tidy_commission['profile_id']===$cancelled['profile_id'],'Cancellation keeps commission identity, date and profile history');
$pv=$tidy::preview('restore',array($tidy_hashes[1]),$tl);$receipt=$tidy::confirm($pv['token'],true,$tl);scout_assert('saved'===$receipt['rows'][$tidy_hashes[1]]['code']&&'waiting'===$wpdb->get_var($wpdb->prepare("SELECT status FROM {$tidy_tables['commissions']} WHERE id=%d",$tidy_commission['id'])),'Cancelled commission can be restored safely');
$pv=$tidy::preview('cancel',array($tidy_hashes[1]),$tl);Editorial_Scout_Store::decide($tidy_hashes[1],'shortlisted');Editorial_Scout_Store::decide($tidy_hashes[1],'commissioned');$receipt=$tidy::confirm($pv['token'],true,$tl);
scout_assert('stale_offer'===$receipt['rows'][$tidy_hashes[1]]['code'],'Same-second ABA editorial changes make a cancellation preview stale');
scout_assert(scout_error($tidy::preview('selected',array(array('bad')),$tl),'invalid_batch')&&scout_error($tidy::preview('selected',array_fill(0,21,$tidy_hashes[3]),$tl),'invalid_batch'),'Nested and oversized bulk inputs are rejected');
$pv=$tidy::preview('selected',array($tidy_hashes[1],$tidy_hashes[2],$tidy_hashes[3]),$tl);$receipt=$tidy::confirm($pv['token'],true,$tl);
scout_assert('tidy_protected'===$receipt['rows'][$tidy_hashes[1]]['code']&&'saved'===$receipt['rows'][$tidy_hashes[2]]['code']&&'saved'===$receipt['rows'][$tidy_hashes[3]]['code'],'Selected bulk dismissal preserves commissions and dismisses only eligible uncommissioned stories');
Editorial_Scout_Store::decide($tidy_hashes[2],'shortlisted');scout_assert('shortlisted'===$tidy::snapshot($tidy_hashes[2])['status'],'Dismissed story can be recovered to Hold for Later');
$queue=$tidy::disabled_stories();$pv=$tidy::preview('disabled',array(),$tl);scout_assert($queue['total']>=21&&20===count($queue['hashes'])&&$queue['total']===$pv['total']&&20===count($pv['rows']),'Disabled-source preview has an exact overall eligible count and a twenty-story bound');
$evidence_before=$wpdb->get_results("SELECT * FROM {$tidy_tables['items']} ORDER BY id",ARRAY_A);
Editorial_Scout_Store::save($td->id,$td->name,$td->url,true);$receipt=$tidy::confirm($pv['token'],true,$tl);scout_assert(count(array_intersect(array_keys($receipt['rows']),$tidy_hashes))>0&&!array_filter(array_intersect_key($receipt['rows'],array_flip($tidy_hashes)),static function($r){return 'saved'===$r['code'];}),'Re-enabling a source after preview blocks stale cleanup for every story it supports');
Editorial_Scout_Store::save($td->id,$td->name,$td->url,false);$queue=$tidy::disabled_stories();$pv=$tidy::preview('disabled',array(),$tl);$receipt=$tidy::confirm($pv['token'],true,$tl);$saved_tidy=count(array_filter($receipt['rows'],static function($r){return 'saved'===$r['code'];}));
scout_assert(20===$saved_tidy&&$queue['total']-20===$tidy::disabled_stories()['total'],'Cleanup changes exactly the previewed eligible batch and leaves a resumable remainder');
scout_assert($evidence_before===$wpdb->get_results("SELECT * FROM {$tidy_tables['items']} ORDER BY id",ARRAY_A),'Cleanup preserves every source observation, title and URL');
$pv=$tidy::preview('selected',array($tidy_hashes[4]),$tl);$old=$pv;$old['created']=time()-HOUR_IN_SECONDS-1;update_user_meta(1,$tidy::META,$old);scout_assert(scout_error($tidy::confirm($pv['token'],true,$tl),'stale_batch'),'Expired preview cannot mutate data');
$pv=$tidy::preview('selected',array($tidy_hashes[4]),$tl);
$other_tidy_user=get_user_by('login','scout-test-editor');$other_tidy_user->add_cap('manage_options');wp_set_current_user($other_tidy_user->ID);
scout_assert(scout_error($tidy::confirm($pv['token'],true,$tl),'stale_batch'),'Even another administrator cannot confirm the initiating administrator preview');
$other_tidy_user->remove_cap('manage_options');wp_set_current_user(1);
$pv=$tidy::preview('selected',array($tidy_hashes[4]),$tl);$pv2=$tidy::preview('selected',array($tidy_hashes[5]),$tl);scout_assert(scout_error($tidy::confirm($pv['token'],true,$tl),'stale_batch'),'A replacement preview invalidates an earlier tab');
Editorial_Scout_Runner::unlock($tl);
$fail_tidy_count=static function($sql){return str_starts_with($sql,'SELECT COUNT(*) FROM (SELECT i.story_hash')?'INTENTIONAL HOUSEKEEPING COUNT FAILURE':$sql;};
$wpdb->suppress_errors(true);add_filter('query',$fail_tidy_count);$count_failure=$tidy::disabled_stories();remove_filter('query',$fail_tidy_count);$wpdb->suppress_errors(false);
scout_assert(scout_error($count_failure,'storage_failed'),'A failed cleanup count cannot be masked by a successful subsequent query');
// Independent HTTP-handler processes contend on the real shared lease.
Editorial_Scout_Store::decide($tidy_hashes[4],'new');Editorial_Scout_Store::decide($tidy_hashes[5],'new');
$tl=Editorial_Scout_Runner::lock();$pv=$tidy::preview('selected',array($tidy_hashes[4],$tidy_hashes[5]),$tl);Editorial_Scout_Runner::unlock($tl);
$tidy_processes=array();for($i=0;$i<2;$i++){$p=proc_open(array(PHP_BINARY,__DIR__.'/housekeeping-endpoint.php','admin',$pv['token']),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$tidy_processes[]=array($p,$pipes);}
foreach($tidy_processes as$entry){[$p,$pipes]=$entry;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);scout_assert(0===proc_close($p)&&''===$err&&str_contains($out,'redirected'),'Concurrent confirmation returns safely through the authorized handler');}
wp_cache_delete(1,'user_meta');$concurrent_receipt=get_user_meta(1,$tidy::META,true);scout_assert('complete'===$concurrent_receipt['state']&&2===count(array_filter($concurrent_receipt['rows'],static function($r){return 'saved'===$r['code'];})),'Concurrent double submit commits exactly one two-story receipt');
$tl=Editorial_Scout_Runner::lock();scout_assert(scout_error($tidy::confirm($pv['token'],true,$tl),'stale_batch'),'A third request cannot replay a committed concurrent batch');Editorial_Scout_Runner::unlock($tl);
// A failed receipt rolls back decisions too and the consumed token cannot replay.
Editorial_Scout_Store::decide($tidy_hashes[4],'new');Editorial_Scout_Store::decide($tidy_hashes[5],'new');$tl=Editorial_Scout_Runner::lock();$pv=$tidy::preview('selected',array($tidy_hashes[4],$tidy_hashes[5]),$tl);
$deny_tidy_receipt=static function($sql){return str_starts_with($sql,'UPDATE')&&str_contains($sql,"'editorial_scout_housekeeping'")&&str_contains($sql,'complete')?'INTENTIONAL HOUSEKEEPING RECEIPT FAILURE':$sql;};
$wpdb->suppress_errors(true);add_filter('query',$deny_tidy_receipt);$failed_tidy=$tidy::confirm($pv['token'],true,$tl);remove_filter('query',$deny_tidy_receipt);$wpdb->suppress_errors(false);Editorial_Scout_Runner::unlock($tl);
scout_assert(scout_error($failed_tidy,'newsroom_rollback')&&'new'===$tidy::snapshot($tidy_hashes[4])['status']&&'new'===$tidy::snapshot($tidy_hashes[5])['status'],'Receipt failure atomically rolls back all housekeeping decisions');
scout_assert('processing'===get_user_meta(1,$tidy::META,true)['state'],'Interrupted housekeeping remains consumed with an uncertain-outcome explanation');
$tl=Editorial_Scout_Runner::lock();scout_assert((bool)$tl,'Rolled-back housekeeping does not leak its shared lock');Editorial_Scout_Runner::unlock($tl);
foreach(array('prepared','sending','failed','incomplete','ready','creating','linked','unknown')as$writing_state){
 $hash=$tidy_hashes[24];Editorial_Scout_Store::decide($hash,'commissioned');$cid=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$tidy_tables['commissions']} WHERE story_hash=%s",$hash));
 $wpdb->replace($tidy_tables['writing'],array('commission_id'=>$cid,'work_key'=>wp_generate_uuid4(),'state'=>$writing_state,'data'=>wp_json_encode(array('notes'=>'Valuable retained research'))));
 $tl=Editorial_Scout_Runner::lock();$pv=$tidy::preview('cancel',array($hash),$tl);$receipt=$tidy::confirm($pv['token'],true,$tl);Editorial_Scout_Runner::unlock($tl);
 scout_assert('protected_writing'===$receipt['rows'][$hash]['code']&&scout_error(Editorial_Scout_Store::decide($hash,'dismissed'),'protected_writing')&&scout_error(Editorial_Scout_Store::decide($hash,'new'),'protected_writing'),'Journal protection includes '.$writing_state.' and legacy decision routes');
 $wpdb->update($tidy_tables['commissions'],array('status'=>'dismissed'),array('id'=>$cid));$wpdb->update($decisions,array('status'=>'dismissed'),array('identity_hash'=>$hash));
 $tl=Editorial_Scout_Runner::lock();$pv=$tidy::preview('restore',array($hash),$tl);$receipt=$tidy::confirm($pv['token'],true,$tl);Editorial_Scout_Runner::unlock($tl);
 scout_assert('protected_writing'===$receipt['rows'][$hash]['code']&&scout_error(Editorial_Scout_Store::decide($hash,'commissioned'),'protected_writing'),'Restore also protects legacy cancelled journals in state '.$writing_state);
 $wpdb->update($tidy_tables['commissions'],array('status'=>'waiting'),array('id'=>$cid));$wpdb->update($decisions,array('status'=>'commissioned'),array('identity_hash'=>$hash));
 $wpdb->delete($tidy_tables['writing'],array('commission_id'=>$cid));
}
foreach(array(0,get_user_by('login','scout-test-editor')->ID)as$uid){wp_set_current_user($uid);$tl=false;scout_assert(scout_error($tidy::snapshot($tidy_hashes[0]),'forbidden')&&scout_error($tidy::disabled_stories(),'forbidden')&&scout_error($tidy::preview('disabled',array(),$tl),'invalid_batch')&&scout_error($tidy::confirm('x',true,$tl),'forbidden'),'Housekeeping service rejects unauthorized user '.$uid);}
wp_set_current_user(1);
foreach(array('anonymous','editor','nonce','get')as$case){$p=proc_open(array(PHP_BINARY,__DIR__.'/housekeeping-endpoint.php',$case),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);scout_assert(0===proc_close($p)&&''===$err&&!str_contains($out,'redirected'),'Housekeeping POST gate rejects '.$case);}
scout_assert($tidy_journals===$wpdb->get_results("SELECT * FROM {$tidy_tables['writing']} ORDER BY commission_id",ARRAY_A),'All pre-existing writing journals and research data are byte-for-byte preserved');
scout_assert($tidy_posts===$wpdb->get_results("SELECT * FROM $wpdb->posts ORDER BY ID",ARRAY_A)&&$tidy_meta===$wpdb->get_results("SELECT * FROM $wpdb->postmeta ORDER BY meta_id",ARRAY_A),'Housekeeping never changes any native post, draft, published content or post metadata');
scout_assert(0===$tidy_http&&$tidy_usage===Editorial_Scout_OpenAI::usage()&&$tidy_schedule===Editorial_Scout_Scheduling::state(),'Housekeeping makes no HTTP requests and preserves budgets and scheduling');
remove_filter('pre_http_request',$tidy_deny);
