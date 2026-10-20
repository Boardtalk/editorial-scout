<?php
/** Disposable browser-only housekeeping fixtures. */
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);global $wpdb;
$tables=array();foreach(array('items','writing')as$t){$tables[$t]=Editorial_Scout_Store::table($t);}
if('verify'===($argv[1]??'')){
 echo wp_json_encode(array('posts'=>hash('sha256',serialize($wpdb->get_results("SELECT * FROM $wpdb->posts ORDER BY ID",ARRAY_A))),'journals'=>hash('sha256',serialize($wpdb->get_results("SELECT * FROM {$tables['writing']} ORDER BY commission_id",ARRAY_A)))));exit;
}
Editorial_Scout_Store::save(0,'Housekeeping live feed','https://feeds.example.org/browser-tidy-live',true);$active=Editorial_Scout_Store::sources()[0];
Editorial_Scout_Store::save(0,'Housekeeping old feed','https://feeds.example.org/browser-tidy-old',false);$disabled=Editorial_Scout_Store::sources()[0];
$hashes=array();foreach(array('mixed coverage','old offer','cancellable work','protected writing','unwanted one','unwanted two')as$i=>$label){
 $url='https://feeds.example.org/browser-tidy-'.$i;$hashes[$i]=Editorial_Scout_Store::story_hash($url);
 foreach((0===$i?array($active->id,$disabled->id):array(1===$i?$disabled->id:$active->id))as$sid){Editorial_Scout_Store::ingest($sid,array(array('url'=>$url,'identity_hash'=>hash('sha256',$url),'title'=>'Housekeeping '.$label,'published_at'=>null)));}
 if(in_array($i,array(2,3),true)){Editorial_Scout_Store::decide($hashes[$i],'commissioned');}
 if(3===$i){$cid=$wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE story_hash=%s',Editorial_Scout_Store::table('commissions'),$hashes[$i]));$wpdb->insert($tables['writing'],array('commission_id'=>$cid,'work_key'=>wp_generate_uuid4(),'state'=>'sending','data'=>wp_json_encode(array('notes'=>'Retain these research notes.'))));}
}
echo wp_json_encode(array('active'=>$active->id,'disabled'=>$disabled->id,'hashes'=>$hashes,'posts'=>hash('sha256',serialize($wpdb->get_results("SELECT * FROM $wpdb->posts ORDER BY ID",ARRAY_A))),'journals'=>hash('sha256',serialize($wpdb->get_results("SELECT * FROM {$tables['writing']} ORDER BY commission_id",ARRAY_A)))));
