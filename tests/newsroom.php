<?php
/** Real transactions in the disposable site; no paid or public network operations. */
if(!isset($checks)||'cli'!==PHP_SAPI){exit(1);}
wp_set_current_user(1);$nr=Editorial_Scout_Newsroom::class;$commissions=Editorial_Scout_Store::table('commissions');
$prior_decisions=$wpdb->get_results("SELECT identity_hash,status,updated_at FROM $decisions ORDER BY identity_hash",ARRAY_A);
$prior_observations=(int)$wpdb->get_var("SELECT COUNT(*) FROM $items");
$wpdb->query("ALTER TABLE $decisions DROP COLUMN revision");$wpdb->query("DROP TABLE $commissions");update_option('editorial_scout_schema','3',false);
$fail_commission_table=static function($sql)use($commissions){return str_starts_with($sql,'CREATE TABLE '.$commissions)?'INTENTIONAL NEWSROOM MIGRATION FAILURE':$sql;};
$wpdb->suppress_errors(true);add_filter('query',$fail_commission_table);
scout_assert(!Editorial_Scout_Store::install()&&'4'!==get_option('editorial_scout_schema'),'Failed additive schema-3 upgrade does not advance marker');
remove_filter('query',$fail_commission_table);$wpdb->suppress_errors(false);
scout_assert(Editorial_Scout_Store::install()&&Editorial_Scout_Store::schema_ready(),'Interrupted newsroom migration restarts and verifies schema 4');
scout_assert($prior_decisions===$wpdb->get_results("SELECT identity_hash,status,updated_at FROM $decisions ORDER BY identity_hash",ARRAY_A)&&$prior_observations===(int)$wpdb->get_var("SELECT COUNT(*) FROM $items"),'Schema-3 migration preserves historical choices, dates and all source evidence');
scout_assert(Editorial_Scout_Store::install(),'Newsroom schema migration is repeatable');
update_option('editorial_scout_schema','5',false);scout_assert(!Editorial_Scout_Store::install(),'Future schema is never downgraded');update_option('editorial_scout_schema','4',false);
$nr_network=0;$nr_deny=static function()use(&$nr_network){$nr_network++;return new WP_Error('no_newsroom_network');};add_filter('pre_http_request',$nr_deny);
$nr_posts=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts");$nr_schedule=Editorial_Scout_Scheduling::state();$nr_usage=Editorial_Scout_OpenAI::usage();
Editorial_Scout_Store::save_category(0,'Newsroom fixtures');$nr_cat=Editorial_Scout_Store::categories();$nr_cat=end($nr_cat)->id;
Editorial_Scout_Store::save(0,'Newsroom Alpha','https://feeds.example.org/newsroom-a',true,$nr_cat);$nr_a=Editorial_Scout_Store::sources()[0];
Editorial_Scout_Store::save(0,'Newsroom Echo','https://feeds.example.org/newsroom-b',true);$nr_b=Editorial_Scout_Store::sources()[0];
$nr_profile=Editorial_Scout_Intelligence::save('',array('name'=>'Newsroom fixture profile','topics'=>'orchard','excluded'=>'gambling'));
$nr_hashes=array();
for($i=0;$i<23;$i++){
 $url='https://feeds.example.org/newsroom-story-'.$i;$h=Editorial_Scout_Store::story_hash($url);$nr_hashes[]=$h;
 $title=0===$i?'ORCHARD research':(1===$i?'Orchard gambling':(2===$i?'<img src=x onerror=unsafe()> Untrusted long '.str_repeat('w',400):'Story '.$i));
 $date=0===$i?'2020-01-01 00:00:00':gmdate('Y-m-d H:i:s');
 Editorial_Scout_Store::ingest($nr_a->id,array(array('url'=>$url,'identity_hash'=>hash('sha256',$url),'title'=>$title,'published_at'=>$date)));
}
$nr_filters=$nr::filters(array('source'=>$nr_a->id,'profile'=>$nr_profile));
$nr_rows=$nr::offers($nr_filters);
scout_assert(is_array($nr_rows)&&11===count($nr_rows)&&$nr_rows[0]->story_hash===$nr_hashes[0]&&1===(int)$nr_rows[0]->profile_fit,'Title matches prioritize the full filtered queue before ten-offer pagination');
scout_assert(0===(int)$nr_rows[0]->freshness,'Old publication date receives no recency bonus');
$next=$nr::offers(array_merge($nr_filters,array('paged'=>2)));$third=$nr::offers(array_merge($nr_filters,array('paged'=>3)));
scout_assert(11===count($next)&&3===count($third)&&!array_intersect(array_column(array_slice($nr_rows,0,10),'id'),array_column(array_slice($next,0,10),'id')),'Deterministic ten-offer pages have no overlaps and preserve access to older offers');
$only=$nr::offers(array_merge($nr_filters,array('title_match'=>'1')));
scout_assert(1===count($only)&&$only[0]->story_hash===$nr_hashes[0],'Profile filter requires inclusion without an exclusion phrase');
$malicious=array('source'=>array('x'),'category'=>"1 OR 1=1",'profile'=>array('x'),'status'=>array('x'),'paged'=>array('x'),'title_match'=>array('x'));
scout_assert(is_array($nr::offers($malicious)),'Malformed filter types cannot inject SQL or trigger PHP warnings');
Editorial_Scout_Intelligence::save($nr_profile,array('name'=>'Percent quote profile','topics'=>"%s, a'b, orchard",'excluded'=>'_never_'));
scout_assert(is_array($nr::offers($nr_filters)),'Prepared LIKE predicates safely handle percent, underscore and quote phrases');
Editorial_Scout_Intelligence::save($nr_profile,array('name'=>'SQL literal fixture','topics'=>"x%') OR 1=1 --"));
scout_assert(array()===$nr::offers(array_merge($nr_filters,array('title_match'=>'1'))),'SQL injection text in profile phrases remains a literal title search, never executable SQL');
Editorial_Scout_Intelligence::save($nr_profile,array('name'=>'Newsroom fixture profile','topics'=>'orchard','excluded'=>'gambling'));
$echo_url='https://feeds.example.org/newsroom-story-0?utm_source=echo';
Editorial_Scout_Store::ingest($nr_b->id,array(array('url'=>$echo_url,'identity_hash'=>hash('sha256',$echo_url),'title'=>'Syndicated orchard','published_at'=>null)));
Editorial_Scout_Store::ingest($nr_a->id,array(array('url'=>$echo_url.'&utm_medium=repeat','identity_hash'=>hash('sha256','repeat'),'title'=>'Repeat orchard','published_at'=>null)));
$nr_rows=$nr::offers($nr_filters);$top=$nr_rows[0];
scout_assert(2===(int)$top->feeds&&3===(int)$top->observations&&2===count($nr::sources($nr_hashes[0])),'Repeated observations on one feed do not inflate distinct feed coverage');
scout_assert(0===(int)$top->freshness&&$top->story_hash===$nr_hashes[0],'Echo coverage neither changes original publication nor adds prioritization');
$before_queries=$wpdb->num_queries;$nr::offers($nr_filters);$nr::counts();foreach(array_slice($nr_rows,0,10)as$row){$nr::sources($row->story_hash);}
scout_assert($wpdb->num_queries-$before_queries<=12,'Ten-offer data queries remain bounded, including attribution and totals');
$saved_get=$_GET;$_GET=array_merge($nr_filters,array('status'=>'new','profile'=>'','paged'=>3));
$unsafe_url='javascript:unsafe()';$unsafe_hash=hash('sha256',$unsafe_url);$wpdb->insert($items,array('source_id'=>$nr_a->id,'identity_hash'=>$unsafe_hash,'story_hash'=>$unsafe_hash,'title'=>'<script>unsafe()</script>','url'=>$unsafe_url,'discovered_at'=>gmdate('Y-m-d H:i:s')));
// Render this item alone via a title-match fixture profile, with no browser/network execution.
$unsafe_profile=Editorial_Scout_Intelligence::save('',array('name'=>'Escaping fixture','topics'=>'unsafe'));$_GET=array('source'=>$nr_a->id,'profile'=>$unsafe_profile,'title_match'=>'1');
ob_start();Editorial_Scout_Newsroom_Screen::render();$screen=ob_get_clean();$_GET=$saved_get;
scout_assert(!str_contains($screen,'<script>unsafe()')&&!str_contains($screen,'href="javascript:')&&str_contains($screen,'Source link unavailable'),'Untrusted card/source text and unsafe link schemes cannot create stored XSS');
$wpdb->delete($items,array('identity_hash'=>$unsafe_hash));Editorial_Scout_Intelligence::delete($unsafe_profile);
$nr_token=$nr::ticket($nr_rows,$nr_filters);$nr_lock=Editorial_Scout_Runner::lock();
scout_assert(scout_error($nr::submit($nr_token,array('unknown'=>'dismissed'),$nr_lock),'invalid_batch')&&scout_error($nr::submit($nr_token,array($nr_hashes[0]=>array('commissioned')),$nr_lock),'invalid_batch'),'Forged IDs and nested choices reject the whole submission before mutation');
$choice=array($nr_hashes[0]=>'commissioned');$submitted=$nr::submit($nr_token,$choice,$nr_lock);
scout_assert(is_array($submitted)&&10===count($submitted['rows']),'One batch returns a per-item receipt including no-choice items');
scout_assert(9===count(array_filter($submitted['rows'],static function($row){return 'unchanged'===$row['code'];})),'Blank or absent choices never dismiss other stories');
$commission=$wpdb->get_row($wpdb->prepare("SELECT * FROM $commissions WHERE story_hash=%s",$nr_hashes[0]));
scout_assert($commission&&'waiting'===$commission->status&&'commissioned'===$wpdb->get_var($wpdb->prepare("SELECT status FROM $decisions WHERE identity_hash=%s",$nr_hashes[0])),'Commission creates a durable work item and a protected decision only');
scout_assert(scout_error($nr::submit($nr_token,$choice,$nr_lock),'used_batch'),'Consumed ticket cannot duplicate commissions or replay choices');
scout_assert(1===(int)$wpdb->get_var("SELECT COUNT(*) FROM $commissions"),'One story has exactly one commission');
Editorial_Scout_Runner::unlock($nr_lock);
$p=proc_open(array(PHP_BINARY,__DIR__.'/workflow-endpoint.php','admin',$nr_hashes[0],'decision',hash('sha256','stale-old-tab')),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$legacy_out=stream_get_contents($pipes[1]);$legacy_err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
scout_assert(0===proc_close($p)&&''===$legacy_err&&str_contains($legacy_out,'|notice:stale_offer|')&&'commissioned'===$wpdb->get_var($wpdb->prepare("SELECT status FROM $decisions WHERE identity_hash=%s",$nr_hashes[0])),'Stale legacy Discoveries forms cannot overwrite a newer Newsroom commission');
$nr_rows=$nr::offers(array_merge($nr_filters,array('status'=>'all')));$nr_token=$nr::ticket($nr_rows,$nr_filters);$nr_lock=Editorial_Scout_Runner::lock();
$held=$nr::submit($nr_token,array($nr_hashes[0]=>'shortlisted'),$nr_lock);Editorial_Scout_Runner::unlock($nr_lock);
scout_assert(is_array($held)&&'held'===$wpdb->get_var($wpdb->prepare("SELECT status FROM $commissions WHERE id=%d",$commission->id)),'Hold pauses an existing commission without deleting the work item');
$nr_rows=$nr::offers(array_merge($nr_filters,array('status'=>'all')));$nr_token=$nr::ticket($nr_rows,$nr_filters);
Editorial_Scout_Store::decide($nr_hashes[0],'dismissed');Editorial_Scout_Store::decide($nr_hashes[0],'shortlisted');
$nr_lock=Editorial_Scout_Runner::lock();$stale=$nr::submit($nr_token,array($nr_hashes[0]=>'commissioned'),$nr_lock);Editorial_Scout_Runner::unlock($nr_lock);
scout_assert('stale_offer'===$stale['rows'][0]['code'],'Revision tokens detect same-second decision ABA changes in stale forms');
$nr_rows=$nr::offers(array_merge($nr_filters,array('status'=>'all')));$nr_token=$nr::ticket($nr_rows,$nr_filters);
$second_hash=$nr_rows[1]->story_hash;Editorial_Scout_Store::decide($second_hash,'dismissed');
$nr_lock=Editorial_Scout_Runner::lock();$mixed=$nr::submit($nr_token,array($nr_hashes[0]=>'commissioned',$second_hash=>'shortlisted'),$nr_lock);Editorial_Scout_Runner::unlock($nr_lock);
scout_assert('saved'===$mixed['rows'][0]['code']&&'stale_offer'===$mixed['rows'][1]['code'],'Valid decisions commit while stale items are explicitly left unchanged');
scout_assert((int)$commission->id===(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $commissions WHERE story_hash=%s",$nr_hashes[0])),'Recommissioning reuses the original durable ID');
$nr_rows=$nr::offers(array_merge($nr_filters,array('status'=>'all')));$nr_token=$nr::ticket($nr_rows,$nr_filters);$third_hash=$nr_rows[2]->story_hash;
$before_decisions=$wpdb->get_results("SELECT * FROM $decisions ORDER BY identity_hash",ARRAY_A);
$break_commission=static function($sql)use($commissions){return str_starts_with($sql,'INSERT INTO `'.$commissions.'`')?'INTENTIONAL COMMISSION WRITE FAILURE':$sql;};
$wpdb->suppress_errors(true);add_filter('query',$break_commission);$nr_lock=Editorial_Scout_Runner::lock();$failed=$nr::submit($nr_token,array($third_hash=>'commissioned'),$nr_lock);Editorial_Scout_Runner::unlock($nr_lock);remove_filter('query',$break_commission);$wpdb->suppress_errors(false);
scout_assert(scout_error($failed,'newsroom_rollback')&&$before_decisions===$wpdb->get_results("SELECT * FROM $decisions ORDER BY identity_hash",ARRAY_A),'Commission failure rolls back every decision and preserves valid prior choices');
scout_assert('processing'===get_user_meta(1,$nr::TICKET,true)['state'],'Interrupted or rolled-back batch stays consumed and explicitly recoverable');
$probe_lock=Editorial_Scout_Runner::lock();scout_assert((bool)$probe_lock,'Rolled-back lease renewals do not leak a busy lock');Editorial_Scout_Runner::unlock($probe_lock);
$nr_rows=$nr::offers(array_merge($nr_filters,array('status'=>'all')));$nr_token=$nr::ticket($nr_rows,$nr_filters);$nr_lock=Editorial_Scout_Runner::lock();
$deny_receipt=static function($sql){return str_starts_with($sql,'UPDATE')&&str_contains($sql,"'editorial_scout_newsroom_report'")?'INTENTIONAL RECEIPT WRITE FAILURE':$sql;};$wpdb->suppress_errors(true);add_filter('query',$deny_receipt);
$failed=$nr::submit($nr_token,array($third_hash=>'commissioned'),$nr_lock);remove_filter('query',$deny_receipt);$wpdb->suppress_errors(false);Editorial_Scout_Runner::unlock($nr_lock);
scout_assert(scout_error($failed,'newsroom_rollback')&&$before_decisions===$wpdb->get_results("SELECT * FROM $decisions ORDER BY identity_hash",ARRAY_A),'Receipt failure atomically rolls back commission and decision writes');
set_transient('editorial_scout_newsroom_error_1','newsroom_rollback',60);ob_start();Editorial_Scout_Newsroom_Screen::render();$failed_screen=ob_get_clean();
scout_assert(str_contains($failed_screen,'not confirmation of the failed submission'),'Earlier confirmed receipt cannot be mistaken for a failed batches outcome');
$nr_rows=$nr::offers(array_merge($nr_filters,array('status'=>'all')));$nr_token=$nr::ticket($nr_rows,$nr_filters);$nr_lock=Editorial_Scout_Runner::lock();
$fake_engine=static function($sql)use($wpdb){return str_starts_with($sql,'SHOW TABLE STATUS')&&str_contains($sql,$wpdb->usermeta)?"SELECT 'MyISAM' AS Engine":$sql;};add_filter('query',$fake_engine);
scout_assert(scout_error($nr::submit($nr_token,array($third_hash=>'commissioned'),$nr_lock),'newsroom_engine'),'Nontransactional metadata table fails closed before any editorial writes');remove_filter('query',$fake_engine);Editorial_Scout_Runner::unlock($nr_lock);
$nr_token=$nr::ticket($nr_rows,$nr_filters);$expired=get_user_meta(1,$nr::TICKET,true);$expired['created']=time()-3601;update_user_meta(1,$nr::TICKET,$expired);$nr_lock=Editorial_Scout_Runner::lock();
scout_assert(scout_error($nr::submit($nr_token,array(),$nr_lock),'stale_batch'),'Expired forms cannot submit decisions');Editorial_Scout_Runner::unlock($nr_lock);
$nr_token=$nr::ticket($nr_rows,$nr_filters);$nr_lock=Editorial_Scout_Runner::lock();
foreach(array('anonymous','editor','nonce','get','admin')as$case){
 $p=proc_open(array(PHP_BINARY,__DIR__.'/newsroom-endpoint.php',$case,$third_hash),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 scout_assert(0===proc_close($p)&&''===$err&&str_contains($out,'|calls:0|')&&('admin'===$case?str_contains($out,'|error:busy|'):!str_contains($out,'|redirect:')),'Newsroom permission, nonce, method or held-lock gate: '.$case);
}
Editorial_Scout_Runner::unlock($nr_lock);
$nr_token=$nr::ticket($nr_rows,$nr_filters);$processes=array();
for($i=0;$i<2;$i++){$pipes=array();$p=proc_open(array(PHP_BINARY,__DIR__.'/newsroom-endpoint.php','admin',$third_hash),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$processes[]=array($p,$pipes);}
foreach($processes as$entry){[$p,$pipes]=$entry;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);scout_assert(0===proc_close($p)&&''===$err&&str_contains($out,'|calls:0|')&&str_contains($out,'|redirect:anchored|'),'Concurrent administrator submit uses PRG anchor with no external work');}
scout_assert(1===(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $commissions WHERE story_hash=%s",$third_hash)),'Simultaneous repeated POST processes cannot duplicate the commission');
wp_cache_delete(1,'user_meta');$nr_counts=$nr::counts();
scout_assert(($nr_counts['commissioned']??0)>=2&&!$nr::offers(array_merge($nr_filters,array('status'=>'shortlisted'))),'Counts and filters return actual editorial state');
// Returning a historic commission to New preserves work-item evidence; no silent deletion.
Editorial_Scout_Store::decide($nr_hashes[0],'new');$wpdb->query($wpdb->prepare("UPDATE $items SET discovered_at='2000-01-01 00:00:00' WHERE story_hash=%s",$nr_hashes[0]));Editorial_Scout_Store::prune();
scout_assert(3===count(Editorial_Scout_Store::coverage($nr_hashes[0]))&&'paused'===$wpdb->get_var($wpdb->prepare("SELECT status FROM $commissions WHERE id=%d",$commission->id)),'Legacy New pauses commission while preserving its historical evidence');
Editorial_Scout_Store::decide($nr_hashes[0],'dismissed');Editorial_Scout_Store::delete($nr_a->id);
scout_assert(Editorial_Scout_Store::source($nr_a->id)->archived&&3===count(Editorial_Scout_Store::coverage($nr_hashes[0])),'Commission dismissal and source removal keep curated source attribution');
$wpdb->query("ALTER TABLE $commissions DROP INDEX story_hash");scout_assert(!Editorial_Scout_Store::schema_ready()&&Editorial_Scout_Store::install(),'Missing commission uniqueness index is detected and repaired restartably');
scout_assert(0===$nr_network&&$nr_posts===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts")&&$nr_schedule===Editorial_Scout_Scheduling::state()&&$nr_usage===Editorial_Scout_OpenAI::usage(),'All Newsroom workflows create no WordPress posts, external calls or schedule/AI usage changes');
remove_filter('pre_http_request',$nr_deny);Editorial_Scout_Intelligence::delete($nr_profile);
