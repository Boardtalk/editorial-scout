<?php
/**
 * Minimal administrator-only interface.
 *
 * @package EditorialScout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Editorial_Scout_Admin {
	/**
	 * Register administrative hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register the Editorial Scout menu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Editorial Scout', 'editorial-scout' ),
			__( 'Editorial Scout', 'editorial-scout' ),
			'manage_options',
			'editorial-scout',
			array( __CLASS__, 'render_page' ),
			'dashicons-media-document',
			65
		);
	}

	/**
	 * Render the read-only project landing page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'editorial-scout' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Editorial Scout', 'editorial-scout' ); ?></h1>
			<p><?php echo esc_html__( 'Foundation build: source monitoring, AI drafting, and image selection are not enabled yet.', 'editorial-scout' ); ?></p>
			<p><?php echo esc_html__( 'No posts are created or published by this version.', 'editorial-scout' ); ?></p>
		</div>
		<?php
	}
}
