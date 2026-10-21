<?php
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);$fixture=get_option('scout_test_preparation_fixture');
$_SERVER['REQUEST_METHOD']='POST';$_POST=$fixture['input']+array('action'=>'editorial_scout_drafting','mode'=>'prepare','commission_id'=>(string)$fixture['id'],'_wpnonce'=>wp_create_nonce('editorial_scout_drafting'));
if('missing-permission'===($argv[1]??'')){unset($_POST['permitted']);}if('nonce'===($argv[1]??'')){$_POST['_wpnonce']='invalid';}
$_REQUEST=$_POST;$calls=0;add_filter('pre_http_request',static function()use(&$calls){$calls++;return new WP_Error('fixture_no_network');},99);register_shutdown_function(static function()use(&$calls){echo '|calls:'.$calls.'|';});add_filter('wp_redirect',static function($url){echo str_ends_with($url,'#scout-writing')?'|redirect:anchored|':'|redirect:wrong|';return false;});Editorial_Scout_Admin::handle_drafting();
