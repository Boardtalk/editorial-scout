<?php
/**
 * Plugin Name: Editorial Scout
 * Description: Source-led editorial research and human-reviewed draft preparation.
 * Version: 0.1.0-dev
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Editorial Scout Contributors
 * License: GPL-3.0-only
 * Text Domain: editorial-scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EDITORIAL_SCOUT_VERSION', '0.1.0-dev' );
define( 'EDITORIAL_SCOUT_PLUGIN_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-editorial-scout-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		if ( is_admin() ) {
			Editorial_Scout_Admin::init();
		}
	}
);
