<?php
if ( 'cli' !== PHP_SAPI || '1' !== getenv('EDITORIAL_SCOUT_TESTS') ) { exit(1); }
$_SERVER['HTTP_HOST']='localhost';
require '/var/www/html/wp-load.php';
wp_set_current_user(1);
$_SERVER['REQUEST_METHOD']='POST';
$case=$argv[1];
$_POST=array('mode'=>$argv[2], 'name'=>'Endpoint intelligence', 'topics'=>'astronomy', 'profile_id'=>'', '_wpnonce'=>wp_create_nonce('editorial_scout_intelligence'));
if('editor'===$case){wp_set_current_user(get_user_by('login','scout-test-editor')->ID);$_POST['_wpnonce']=wp_create_nonce('editorial_scout_intelligence');}
if('anonymous'===$case){wp_set_current_user(0);}
if('nonce'===$case){$_POST['_wpnonce']='invalid';}
if('get'===$case){$_SERVER['REQUEST_METHOD']='GET';}
$_REQUEST=$_POST;
$network=false;
add_filter('pre_http_request',static function()use(&$network){$network=true;return new WP_Error('no_network');});
register_shutdown_function(static function()use(&$network){echo $network?'|network':'|local-only';});
add_filter('wp_redirect',static function(){echo '|notice:'.(get_transient('editorial_scout_notice_'.get_current_user_id())['code']??'').'|';return false;});
Editorial_Scout_Admin::handle_intelligence();
