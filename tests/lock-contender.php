<?php
/** Independent database connection with a deliberately stale object cache. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
wp_cache_set( 'notoptions', array( 'editorial_scout_lock' => true ), 'options' );
$lock = Editorial_Scout_Runner::lock();
if ( $lock ) {
	echo "acquired\n";
	fflush( STDOUT );
	// Hold concurrently until the parent has observed all attempts.
	$end = microtime( true ) + 20;
	while ( ! file_exists( '/tmp/scout-release-contenders' ) && microtime( true ) < $end ) { usleep( 10000 ); }
	Editorial_Scout_Runner::unlock( $lock );
} else { echo "busy\n"; }
