<?php
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);
$_SERVER['REQUEST_METHOD']='POST';$_POST=array('mode'=>'disabled','_scout_tidy_nonce'=>wp_create_nonce('editorial_scout_housekeeping'));
if('admin'===$argv[1]){$_POST['mode']='confirm';$_POST['confirm']='1';$_POST['token']=$argv[2];}
if('anonymous'===$argv[1]){wp_set_current_user(0);}if('editor'===$argv[1]){wp_set_current_user(get_user_by('login','scout-test-editor')->ID);$_POST['_scout_tidy_nonce']=wp_create_nonce('editorial_scout_housekeeping');}
if('nonce'===$argv[1]){$_POST['_scout_tidy_nonce']='bad';}if('get'===$argv[1]){$_SERVER['REQUEST_METHOD']='GET';}$_REQUEST=$_POST;
add_filter('wp_redirect',static function(){echo 'redirected';return false;});Editorial_Scout_Admin::handle_housekeeping();
