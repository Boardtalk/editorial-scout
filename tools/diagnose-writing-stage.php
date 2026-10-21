<?php
/**
 * Optional, read-only WP-CLI stage extraction. Never dump a writing journal.
 * Usage: wp eval-file /path/to/diagnose-writing-stage.php 4 --user=ADMIN_LOGIN
 *
 * @package EditorialScout
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_posts' ) || ! class_exists( 'Editorial_Scout_Drafting' ) ) {
	WP_CLI::error( 'An authorized administrator and the active plugin are required.' );
}
$scout_id = $args[0] ?? '4';
if ( ! is_string( $scout_id ) || ! preg_match( '/^[1-9][0-9]{0,17}$/D', $scout_id ) ) {
	WP_CLI::error( 'Provide one valid commission number.' );
}
$scout_job   = Editorial_Scout_Drafting::job( (int) $scout_id );
$scout_stage = $scout_job['context']['error_stage'] ?? null;
$scout_safe  = Editorial_Scout_Drafting::failure_stage( new WP_Error( 'diagnostic', '', array( 'stage' => $scout_stage ) ) );
WP_CLI::line( $scout_safe ? $scout_safe : ( null === $scout_stage || '' === $scout_stage ? 'not_recorded' : 'unrecognized' ) );
