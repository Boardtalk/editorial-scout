<?php
/** Editorial workflow and ownership contract, exclusively in the disposable site. */
if (!isset($checks)||'cli'!==PHP_SAPI) { exit(1); }
wp_set_current_user(1);$work=Editorial_Scout_Articles::class;$nr=Editorial_Scout_Newsroom::class;
$before_history=$wpdb->get_results("SELECT id,story_hash,status,created_at,updated_at FROM $commissions ORDER BY id",ARRAY_A);
$wpdb->query("ALTER TABLE $commissions DROP COLUMN profile_id,DROP COLUMN profile_name");update_option('editorial_scout_schema','4',false);
$fail_profile=static function($sql)use($commissions){return str_contains($sql,'ALTER TABLE '.$commissions)&&str_contains($sql,'profile_')?'INTENTIONAL PROFILE MIGRATION FAILURE':$sql;};
$wpdb->suppress_errors(true);add_filter('query',$fail_profile);$migration=Editorial_Scout_Store::install();remove_filter('query',$fail_profile);$wpdb->suppress_errors(false);
scout_assert(!$migration&&'5'!==get_option('editorial_scout_schema'),'Schema-4 failed profile migration never advances its marker');
scout_assert(Editorial_Scout_Store::install()&&Editorial_Scout_Store::schema_ready()&&'5'===get_option('editorial_scout_schema'),'Schema-4 to 5 upgrade restarts and validates additive profile columns');
scout_assert($before_history===$wpdb->get_results("SELECT id,story_hash,status,created_at,updated_at FROM $commissions ORDER BY id",ARRAY_A),'Profile migration retains all commission IDs, states and timestamps');
scout_assert(0===(int)$wpdb->get_var("SELECT COUNT(*) FROM $commissions WHERE profile_id<>'' OR profile_name<>''"),'Old commissions have no guessed profile');
scout_assert(Editorial_Scout_Store::install(),'Schema-5 migration is repeatable');
$work_network=0;$block_work_http=static function()use(&$work_network){$work_network++;return new WP_Error('work_http_forbidden');};add_filter('pre_http_request',$block_work_http);
$work_before_posts=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts");
Editorial_Scout_Store::save(0,'Work fixture <script>unsafe()</script>','https://feeds.example.org/editorial-work',true);$work_source=Editorial_Scout_Store::sources()[0];
$work_profile=Editorial_Scout_Intelligence::save('',array('name'=>'Workflow profile','topics'=>'workflow'));
$work_hashes=array();
for($i=0;$i<26;$i++) {
 $url='https://feeds.example.org/workflow-'.$i;$work_hashes[]=Editorial_Scout_Store::story_hash($url);
 Editorial_Scout_Store::ingest($work_source->id,array(array('identity_hash'=>hash('sha256',$url),'url'=>$url,'title'=>'Workflow '.($i===0?'<script>unsafe()</script>':$i),'published_at'=>null)));
}
$work_filters=$nr::filters(array('source'=>$work_source->id,'profile'=>$work_profile));$work_rows=$nr::offers($work_filters);$work_token=$nr::ticket($work_rows,$work_filters);$work_lock=Editorial_Scout_Runner::lock();
$work_report=$nr::submit($work_token,array($work_rows[0]->story_hash=>'commissioned',$work_rows[1]->story_hash=>'shortlisted',$work_rows[2]->story_hash=>'dismissed'),$work_lock);Editorial_Scout_Runner::unlock($work_lock);
scout_assert(array('commissioned'=>1,'shortlisted'=>1,'dismissed'=>1,'unchanged'=>7,'failed'=>0)===$nr::summary($work_report),'Receipt counts report exactly three decisions and seven unchanged stories');
$work_commission=$wpdb->get_row($wpdb->prepare("SELECT * FROM $commissions WHERE story_hash=%s",$work_rows[0]->story_hash));
scout_assert($work_profile===$work_commission->profile_id&&'Workflow profile'===$work_commission->profile_name,'First commission records its selected saved profile');
Editorial_Scout_Intelligence::save($work_profile,array('name'=>'Renamed profile'));Editorial_Scout_Store::decide($work_rows[0]->story_hash,'shortlisted');Editorial_Scout_Store::decide($work_rows[0]->story_hash,'commissioned',false,$work_profile);
scout_assert('Workflow profile'===$wpdb->get_var($wpdb->prepare("SELECT profile_name FROM $commissions WHERE id=%d",$work_commission->id)),'Recommissioning or profile editing does not rewrite historic profile snapshot');
$work_same=$nr::offers(array_merge($work_filters,array('status'=>'commissioned')));$work_token=$nr::ticket($work_same,$work_filters);$work_lock=Editorial_Scout_Runner::lock();$same_report=$nr::submit($work_token,array($work_same[0]->story_hash=>'commissioned'),$work_lock);Editorial_Scout_Runner::unlock($work_lock);
scout_assert(0===$nr::summary($same_report)['commissioned']&&1===$nr::summary($same_report)['unchanged'],'Selecting the current state is counted as unchanged, not a new commission');
$work_token=$nr::ticket($work_rows,$work_filters);$work_lock=Editorial_Scout_Runner::lock();$mixed_report=$nr::submit($work_token,array($work_rows[0]->story_hash=>'dismissed',$work_rows[3]->story_hash=>'shortlisted'),$work_lock);Editorial_Scout_Runner::unlock($work_lock);
scout_assert(1===$nr::summary($mixed_report)['failed']&&1===$nr::summary($mixed_report)['shortlisted']&&8===$nr::summary($mixed_report)['unchanged'],'Stale outcome is failed while other valid choices and unchanged items are accurately counted');
// Successful PRG rendering is a receipt, never a fresh batch of the same completed choices.
delete_transient('editorial_scout_newsroom_error_1');update_user_meta(1,$nr::REPORT,$work_report);$saved_get=$_GET;$_GET=array_merge($work_filters,array('receipt'=>$work_report['token']));$ticket_before=get_user_meta(1,$nr::TICKET,true);
ob_start();Editorial_Scout_Newsroom_Screen::render();$confirmation=ob_get_clean();
scout_assert(str_contains($confirmation,'Decisions submitted')&&str_contains($confirmation,'Commissioned: 1')&&str_contains($confirmation,'Held: 1')&&str_contains($confirmation,'Unchanged: 7'),'Prominent success summary has correct counts near submission anchor');
scout_assert(!str_contains($confirmation,'>Submit Decisions</button>')&&get_user_meta(1,$nr::TICKET,true)===$ticket_before,'Refreshing completed receipt never offers a resubmittable batch or mints a manifest');
scout_assert(str_contains($confirmation,'<details><summary>Detailed submission receipt')&&str_contains($confirmation,'View Commissions')&&str_contains($confirmation,'Continue Reviewing'),'Detailed receipt is collapsed and clear next actions are available');
$old_report=$work_report;$old_report['created']=time()-2*DAY_IN_SECONDS;update_user_meta(1,$nr::REPORT,$old_report);
scout_assert($nr::report()===$old_report,'Latest detailed receipt persists beyond the form expiry for audit');
$_GET=array_merge($work_filters,array('receipt'=>str_repeat('x',36)));ob_start();Editorial_Scout_Newsroom_Screen::render();$untrusted_receipt=ob_get_clean();
scout_assert(!str_contains($untrusted_receipt,'>Decisions submitted</h3>'),'Forged receipt token cannot claim a current successful submission');$_GET=$saved_get;
foreach($work_hashes as$i=>$hash){Editorial_Scout_Store::decide($hash,'commissioned',false,$work_profile);if(0===$i%4){Editorial_Scout_Store::decide($hash,'shortlisted');}elseif(1===$i%4){Editorial_Scout_Store::decide($hash,'dismissed');}elseif(2===$i%4){Editorial_Scout_Store::decide($hash,'new');}}
foreach($work::statuses()as$status=>$label){$rows=$work::commissions(array('status'=>$status));scout_assert(count($rows)>0&&!array_filter($rows,static function($r)use($status){return$r->status!==$status;}),'Commission status filter reflects live state: '.$status);}
$first=$work::commissions(array());$second=$work::commissions(array('paged'=>2));
scout_assert(21===count($first)&&count($second)>0&&!array_intersect(array_column(array_slice($first,0,20),'id'),array_column($second,'id')),'Commission history uses stable bounded twenty-row pages');
$real_counts=array_map('intval',wp_list_pluck($wpdb->get_results("SELECT status,COUNT(*) AS total FROM $commissions GROUP BY status"),'total','status'));
scout_assert($real_counts===$work::counts(),'Commission counts equal durable storage after all state changes');
scout_assert(is_array($work::commissions(array('view'=>array('x'),'status'=>array('x'),'paged'=>array('x')))),'Nested commission query parameters fail safely to allowlisted defaults');
$q_before=$wpdb->num_queries;$work::commissions(array());$work::counts();scout_assert($wpdb->num_queries-$q_before<=2,'Commission list and totals require at most two data queries');
$_GET=array('tab'=>'articles','paged'=>2);ob_start();$work::render();$work_screen=ob_get_clean();$_GET=$saved_get;
scout_assert(!str_contains($work_screen,'<script>unsafe()')&&str_contains($work_screen,'Original discovery and source evidence')&&str_contains($work_screen,'Not recorded'),'Commission history escapes untrusted titles and links real evidence without inferred profile');
scout_assert($work_before_posts===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts")&&0===$work_network,'Commission workflow, feedback and history create no posts or network calls');
// Explicit test fixtures in this isolated site are not production writing functionality.
$native_ids=array();
foreach(array('draft','pending','future','publish','private','trash')as$status){
 $id=wp_insert_post(array('post_type'=>'post','post_status'=>$status,'post_title'=>'Ownership fixture '.$status,'post_content'=>'Native article body survives plugin removal.','post_excerpt'=>'Native excerpt','post_author'=>1,'post_date'=>('future'===$status?'2030-01-01 12:00:00':gmdate('Y-m-d H:i:s')),'post_date_gmt'=>('future'===$status?'2030-01-01 12:00:00':gmdate('Y-m-d H:i:s'))));
 update_post_meta($id,$work::ORIGIN_KEY,$work::ORIGIN);$native_ids[$status]=$id;
}
$unmarked=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'Ownership fixture unmarked'));$native_ids['unmarked']=$unmarked;
$wrong=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'Wrong marker'));update_post_meta($wrong,$work::ORIGIN_KEY,'EDITORIAL-SCOUT/V1');$native_ids['wrong']=$wrong;
$page_id=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>'Attributed page excluded'));update_post_meta($page_id,$work::ORIGIN_KEY,$work::ORIGIN);$native_ids['page']=$page_id;
$case_key=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'Wrong key case'));update_post_meta($case_key,'_EDITORIAL_SCOUT_ORIGIN',$work::ORIGIN);$native_ids['case_key']=$case_key;
$prefix=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'Wrong marker suffix'));update_post_meta($prefix,$work::ORIGIN_KEY,$work::ORIGIN.'/wrong');$native_ids['prefix']=$prefix;
add_post_meta($native_ids['draft'],$work::ORIGIN_KEY,$work::ORIGIN);
$native=$work::posts(array());scout_assert($native instanceof WP_Query&&6===count($native->posts)&&6===(int)$native->found_posts,'Native attribution is exact in key and value, unique per post and restricted to standard posts');
foreach(array('draft','pending','future','publish','private','trash')as$status){$query=$work::posts(array('status'=>$status));scout_assert(1===count($query->posts)&&$query->posts[0]->ID===$native_ids[$status],'Native status filter reads actual WordPress status: '.$status);}
wp_update_post(array('ID'=>$native_ids['draft'],'post_status'=>'pending'));
scout_assert(0===$work::posts(array('status'=>'draft'))->post_count&&2===$work::posts(array('status'=>'pending'))->post_count,'Native status changes appear on refresh, independently of commission states');
for($i=0;$i<21;$i++){$id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'Ownership fixture pagination '.$i));update_post_meta($id,$work::ORIGIN_KEY,$work::ORIGIN);$native_ids['pagination-'.$i]=$id;}
$native_first=$work::posts(array('status'=>'draft'));$native_next=$work::posts(array('status'=>'draft','paged'=>2));
scout_assert(20===$native_first->post_count&&1===$native_next->post_count&&!array_intersect(wp_list_pluck($native_first->posts,'ID'),wp_list_pluck($native_next->posts,'ID')),'Native history paginates twenty real attributable posts without overlaps');
scout_assert(!$work::posts(array('status'=>array('draft'),'paged'=>array('x')))->get('suppress_filters')&&is_array($work::posts(array('status'=>array('draft')))->posts),'Malformed native filters remain bounded and byte-exact query filter is removed afterward');
$deny_native=static function($caps,$cap,$uid,$args)use($native_ids){return 'read_post'===$cap&&($args[0]??0)===$native_ids['publish']?array('do_not_allow'):$caps;};add_filter('map_meta_cap',$deny_native,10,4);
$_GET=array('view'=>'posts');ob_start();$work::render();$native_screen=ob_get_clean();$_GET=$saved_get;remove_filter('map_meta_cap',$deny_native);
scout_assert(!str_contains($native_screen,'Ownership fixture publish')&&str_contains($native_screen,'Open WordPress editor'),'Native history rechecks per-post read permissions and offers only authorized editor links');
foreach(array(0,get_user_by('login','scout-test-editor')->ID)as$uid){wp_set_current_user($uid);scout_assert(scout_error($work::commissions(array()),'forbidden')&&scout_error($work::counts(),'forbidden')&&scout_error($work::posts(array()),'forbidden'),'Editorial work APIs reject anonymous/nonadministrator readers: '.$uid);}
wp_set_current_user(1);
$p=proc_open(array(PHP_BINARY,__DIR__.'/editorial-work-endpoint.php'),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);scout_assert(0===proc_close($p)&&''===$err&&!str_contains($out,'Ownership fixture')&&str_contains($out,'not allowed'),'Unauthorized Articles rendering is rejected before data exposure');
$restrict_history=static function($caps,$cap){return 'edit_others_posts'===$cap?array('do_not_allow'):$caps;};add_filter('map_meta_cap',$restrict_history,10,2);
scout_assert(scout_error($work::posts(array()),'forbidden')&&is_array($work::commissions(array())),'Restricted administrators can review commissions without bypassing native article permissions');remove_filter('map_meta_cap',$restrict_history);
$hooks_before=isset($wp_filter['posts_where'])?count($wp_filter['posts_where']->callbacks,COUNT_RECURSIVE):0;
$break_native=static function($sql)use($wpdb){return str_contains($sql,'scout_origin')&&str_contains($sql,$wpdb->posts)?'INTENTIONAL NATIVE HISTORY FAILURE':$sql;};$wpdb->suppress_errors(true);add_filter('query',$break_native);$broken_history=$work::posts(array('paged'=>999));remove_filter('query',$break_native);$wpdb->suppress_errors(false);
$hooks_after=isset($wp_filter['posts_where'])?count($wp_filter['posts_where']->callbacks,COUNT_RECURSIVE):0;
scout_assert(scout_error($broken_history,'storage_failed')&&$hooks_before===$hooks_after,'Native history database failures fail safely and scoped exact-marker filter is removed');
// Content, media file, thumbnail, terms and ordinary/provenance metadata survive every lifecycle path.
$upload=wp_upload_bits('editorial-ownership-fixture.png',null,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
$attachment=wp_insert_attachment(array('post_title'=>'Owned image fixture','post_mime_type'=>'image/png','post_status'=>'inherit'),$upload['file'],$native_ids['publish']);$native_ids['attachment']=$attachment;
set_post_thumbnail($native_ids['publish'],$attachment);update_post_meta($native_ids['publish'],'normal_article_metadata','retained');wp_set_post_terms($native_ids['publish'],array($approved_category),'category');wp_set_post_terms($native_ids['publish'],array('Owned fixture tag'),'post_tag');
$ownership_snapshot=static function()use($native_ids){$result=array();foreach($native_ids as$id){$result[$id]=array(get_post($id,ARRAY_A),get_post_meta($id),wp_get_object_terms($id,array('category','post_tag'),array('fields'=>'tt_ids')));}return$result;};
update_option('scout_test_native_ids',array_values($native_ids),false);
$ownership_before=$ownership_snapshot();$ownership_file=get_attached_file($attachment);$ownership_file_hash=hash_file('sha256',$ownership_file);
$assert_ownership=static function($stage)use($ownership_snapshot,$ownership_before,$ownership_file,$ownership_file_hash){scout_assert($ownership_before===$ownership_snapshot()&&is_file($ownership_file)&&$ownership_file_hash===hash_file('sha256',$ownership_file),'Native posts, drafts, media files, taxonomies and metadata survive '.$stage);};
deactivate_plugins('editorial-scout/editorial-scout.php');$assert_ownership('deactivation');activate_plugin('editorial-scout/editorial-scout.php');$assert_ownership('reactivation');
scout_assert($work::posts(array('status'=>'publish'))->posts[0]->ID===$native_ids['publish'],'Provenance remains discoverable after reactivation');
Editorial_Scout_Intelligence::delete($work_profile);remove_filter('pre_http_request',$block_work_http);scout_assert(0===$work_network,'Entire editorial work suite makes no HTTP or paid requests');
