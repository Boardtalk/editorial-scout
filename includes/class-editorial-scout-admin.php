<?php
/**
 * Administrator-only source management and read-only discoveries.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Admin {
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_editorial_scout_decision', array( __CLASS__, 'handle_decision' ) );
		add_action( 'admin_post_editorial_scout_category', array( __CLASS__, 'handle_category' ) );
		add_action( 'admin_post_editorial_scout_source', array( __CLASS__, 'handle_source' ) );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_editorial-scout' === $hook ) {
			wp_enqueue_style( 'editorial-scout-admin', plugins_url( 'assets/admin.css', EDITORIAL_SCOUT_PLUGIN_FILE ), array(), EDITORIAL_SCOUT_VERSION );
		}
	}

	public static function register_menu() {
		add_menu_page( __( 'Editorial Scout', 'editorial-scout' ), __( 'Editorial Scout', 'editorial-scout' ), 'manage_options', 'editorial-scout', array( __CLASS__, 'render_page' ), 'dashicons-media-document', 65 );
	}

	public static function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'editorial-scout' ), '', array( 'response' => 403 ) );
		}
	}

	public static function handle_source() {
		self::authorize();
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'POST is required.', 'editorial-scout' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'editorial_scout_source' );
		$id   = isset( $_POST['source_id'] ) && is_scalar( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( 'check' === $mode ) {
			$result = Editorial_Scout_Runner::check_now( $id );
			self::finish( 'sources', is_wp_error( $result ) ? $result->get_error_code() : 'checked', is_int( $result ) ? $result : 0 );
		}
		$ok   = false;
		$lock = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				$source = $id ? Editorial_Scout_Store::source( $id ) : null;
				if ( $source && $source->archived ) {
					$source = null;
				}
				if ( 'delete' === $mode && $source ) {
					$ok = Editorial_Scout_Store::delete( $id );
				} elseif ( 'toggle' === $mode && $source ) {
					global $wpdb;
					$ok = false !== $wpdb->update( Editorial_Scout_Store::table( 'sources' ), array( 'enabled' => (int) ! $source->enabled ), array( 'id' => $id ) );
				} elseif ( 'save' === $mode ) {
					$name = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validate the original URL below; sanitizing first can conceal unsafe input.
					$url     = isset( $_POST['url'] ) && is_string( $_POST['url'] ) ? trim( wp_unslash( $_POST['url'] ) ) : '';
					$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
					$valid   = Editorial_Scout_Feed::url( $url );
					if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
						$valid = new WP_Error( 'lease_expired' );
					}
					if ( '' !== $name && mb_strlen( $name ) <= 200 && ! is_wp_error( $valid ) ) {
						$ok = Editorial_Scout_Store::save( $id, $name, $valid, $enabled, absint( self::post_text( 'category_id' ) ) );
					}
				}
			} finally {
				Editorial_Scout_Runner::unlock( $lock );
			}
		}
		self::finish( 'sources', $ok ? 'saved' : 'failed' );
	}

	public static function post_gate( $nonce ) {
		self::authorize();
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'POST is required.', 'editorial-scout' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( $nonce );
	}

	public static function post_text( $key ) {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only called after post_gate or the source nonce check.
	}

	public static function finish( $tab, $code, $count = 0 ) {
		set_transient(
			'editorial_scout_notice_' . get_current_user_id(),
			array(
				'code'  => $code,
				'count' => (int) $count,
			),
			60
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'editorial-scout',
					'tab'    => $tab,
					'result' => in_array( $code, array( 'saved', 'checked' ), true ) ? 'saved' : 'failed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_decision() {
		self::post_gate( 'editorial_scout_decision' );
		$result = new WP_Error( 'busy' );
		$lock   = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				$result = Editorial_Scout_Store::decide( self::post_text( 'story_hash' ), self::post_text( 'status' ) );
			} finally {
				Editorial_Scout_Runner::unlock( $lock );
			}
		}
		self::finish( 'discoveries', is_wp_error( $result ) ? $result->get_error_code() : ( $result ? 'saved' : 'storage_failed' ) );
	}

	public static function handle_category() {
		self::post_gate( 'editorial_scout_category' );
		$result = false;
		$lock   = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				$id = absint( self::post_text( 'category_id' ) );
				if ( 'delete' === self::post_text( 'mode' ) ) {
					$result = Editorial_Scout_Store::delete_category( $id );
				} elseif ( 'save' === self::post_text( 'mode' ) ) {
					$result = Editorial_Scout_Store::save_category( $id, self::post_text( 'name' ) );
				}
			} finally {
				Editorial_Scout_Runner::unlock( $lock );
			}
		}
		self::finish( 'settings', $result ? 'saved' : 'failed' );
	}

	public static function render_page() {
		self::authorize();
		Editorial_Scout_Dashboard::render();
	}
}
