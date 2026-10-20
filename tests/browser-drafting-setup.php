<?php
/** Fake provider setup only in the disposable browser test site. */
if('cli'!==PHP_SAPI||'1'!==getenv('EDITORIAL_SCOUT_TESTS')){exit(1);}
$_SERVER['HTTP_HOST']='localhost';require '/var/www/html/wp-load.php';wp_set_current_user(1);
if(isset($argv[1])){$ids=get_option('scout_test_native_ids',array());$ids[]=(int)$argv[1];update_option('scout_test_native_ids',array_values(array_unique($ids)),false);exit;}
Editorial_Scout_OpenAI::configure(true,1000000,10000000,'0.40','1.60','2025-04-14');$u=Editorial_Scout_OpenAI::usage();$u['last_at']=0;update_option(Editorial_Scout_OpenAI::USAGE,$u,false);
