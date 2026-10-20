<?php
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);
$case=$argv[1];$hash=$argv[2]??str_repeat('a',64);$ticket=get_user_meta(1,Editorial_Scout_Newsroom::TICKET,true);
$_SERVER['REQUEST_METHOD']='POST';$_POST=array('ticket'=>$ticket['token']??'missing','choices'=>array($hash=>'commissioned'),'_wpnonce'=>wp_create_nonce('editorial_scout_newsroom'));
if('editor'===$case){wp_set_current_user(get_user_by('login','scout-test-editor')->ID);$_POST['_wpnonce']=wp_create_nonce('editorial_scout_newsroom');}
if('anonymous'===$case){wp_set_current_user(0);}if('nonce'===$case){$_POST['_wpnonce']='bad';}if('get'===$case){$_SERVER['REQUEST_METHOD']='GET';}
$_REQUEST=$_POST;$calls=0;add_filter('pre_http_request',static function()use(&$calls){++$calls;return new WP_Error('blocked');});
register_shutdown_function(static function()use(&$calls){echo '|calls:'.$calls.'|';});
add_filter('wp_redirect',static function($url){echo '|redirect:'.(str_contains($url,'#newsroom-decisions')?'anchored':'wrong').'|';echo '|error:'.get_transient('editorial_scout_newsroom_error_'.get_current_user_id()).'|';return false;});
Editorial_Scout_Admin::handle_newsroom();
