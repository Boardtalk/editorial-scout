<?php
/** Native fixtures for browser history testing, never a plugin writing endpoint. */
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);
$ids=array();for($i=0;$i<21;$i++){$id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'Browser owned article '.$i,'post_content'=>'Disposable ownership fixture.'));update_post_meta($id,Editorial_Scout_Articles::ORIGIN_KEY,Editorial_Scout_Articles::ORIGIN);$ids[]=$id;}
update_option('scout_test_native_ids',$ids,false);echo wp_json_encode($ids);
