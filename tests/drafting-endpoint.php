<?php
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);$case=$argv[1];
$job=Editorial_Scout_Drafting::job((int)$argv[2]);$_SERVER['REQUEST_METHOD']='POST';$_POST=array('mode'=>$argv[3]??'write','commission_id'=>$argv[2],'token'=>$job['context']['token']??'invalid','approve'=>'1','consent'=>'1','_wpnonce'=>wp_create_nonce('editorial_scout_drafting'));
if('editor'===$case){wp_set_current_user(get_user_by('login','scout-test-editor')->ID);$_POST['_wpnonce']=wp_create_nonce('editorial_scout_drafting');}if('anonymous'===$case){wp_set_current_user(0);}if('nonce'===$case){$_POST['_wpnonce']='bad';}if('get'===$case){$_SERVER['REQUEST_METHOD']='GET';}if('lock'===$case){$lease=Editorial_Scout_Runner::lock();}
$_REQUEST=$_POST;$calls=0;add_filter('pre_http_request',static function()use(&$calls){$calls++;return new WP_Error('blocked');});register_shutdown_function(static function()use(&$calls,&$lease){if($lease){Editorial_Scout_Runner::unlock($lease);}echo '|calls:'.$calls.'|';});add_filter('wp_redirect',static function(){return false;});Editorial_Scout_Admin::handle_drafting();
