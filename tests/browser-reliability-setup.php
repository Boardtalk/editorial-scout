<?php
/** Synthetic failed journal for the disposable browser site; never contacts a provider. */
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);global $wpdb;
if(isset($argv[1])){$id=(int)$argv[1];}else{
 Editorial_Scout_Store::save(0,'Reliability browser feed','https://feeds.example.org/browser-reliability',true);$sid=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE name=%s',Editorial_Scout_Store::table('sources'),'Reliability browser feed'));
 $url='https://feeds.example.org/reliability-browser-story';$hash=Editorial_Scout_Store::story_hash($url);Editorial_Scout_Store::ingest($sid,array(array('identity_hash'=>hash('sha256',$url),'url'=>$url,'title'=>'Reliability diagnostic fixture','published_at'=>null)));$profiles=Editorial_Scout_Intelligence::state()['profiles'];$profile=array_key_first($profiles);Editorial_Scout_Store::decide($hash,'commissioned',false,$profile);$id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE story_hash=%s',Editorial_Scout_Store::table('commissions'),$hash));
 $wpdb->insert(Editorial_Scout_Store::table('writing'),array('commission_id'=>$id,'work_key'=>wp_generate_uuid4(),'post_id'=>null,'state'=>'failed','data'=>wp_json_encode(array('user'=>1,'profile'=>$profile,'author'=>1,'error'=>'writing_output','error_stage'=>'claims','token'=>'PRIVATE_DIAGNOSTIC_TOKEN_CANARY','private_notes'=>'PRIVATE_RESEARCH_CANARY'))));
}
echo wp_json_encode(array('id'=>$id,'journal'=>hash('sha256',wp_json_encode(Editorial_Scout_Drafting::job($id))),'posts'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts")));
