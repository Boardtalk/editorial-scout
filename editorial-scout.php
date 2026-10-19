<?php
/**
 * Plugin Name: Editorial Scout
 * Description: Source-led editorial research and human-reviewed draft preparation.
 * Version: 0.2.0-dev
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Editorial Scout Contributors
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI: false
 * Text Domain: editorial-scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EDITORIAL_SCOUT_VERSION', '0.2.0-dev' );
define( 'EDITORIAL_SCOUT_PLUGIN_FILE', __FILE__ );

foreach ( array( 'store', 'feed', 'runner', 'admin' ) as $component ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-editorial-scout-' . $component . '.php';
}

// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Custom interval is 300 seconds.
add_filter( 'cron_schedules', array( 'Editorial_Scout_Runner', 'schedules' ) );
add_action( Editorial_Scout_Runner::HOOK, array( 'Editorial_Scout_Runner', 'run' ) );
register_activation_hook( __FILE__, 'editorial_scout_activate' );
register_deactivation_hook( __FILE__, 'editorial_scout_deactivate' );

/** Activate only on supported single-site installations. */
function editorial_scout_activate() {
	if ( is_multisite() || ! extension_loaded( 'dom' ) || ! extension_loaded( 'mbstring' ) || ! extension_loaded( 'curl' ) ) {
		wp_die( esc_html__( 'Editorial Scout requires single-site WordPress and PHP DOM, mbstring and cURL.', 'editorial-scout' ) );
	}
	Editorial_Scout_Store::install();
	if ( Editorial_Scout_Store::SCHEMA !== get_option( 'editorial_scout_schema' ) ) {
		wp_die( esc_html__( 'Editorial Scout could not create its database tables.', 'editorial-scout' ) );
	}
	Editorial_Scout_Runner::schedule();
}

/** Stop background work; retain source and discovery data. */
function editorial_scout_deactivate() {
	wp_clear_scheduled_hook( Editorial_Scout_Runner::HOOK );
}


add_action(
	'plugins_loaded',
	static function () {
		if ( is_multisite() ) {
			return;
		}
		if ( Editorial_Scout_Store::SCHEMA !== get_option( 'editorial_scout_schema' ) ) {
			Editorial_Scout_Store::install();
		}
		Editorial_Scout_Runner::schedule();
		if ( is_admin() ) {
			Editorial_Scout_Admin::init();
		}
	}
);
