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
		add_action( 'admin_post_editorial_scout_intelligence', array( __CLASS__, 'handle_intelligence' ) );
		add_action( 'admin_post_editorial_scout_drafting', array( __CLASS__, 'handle_drafting' ) );
		add_action( 'admin_post_editorial_scout_newsroom', array( __CLASS__, 'handle_newsroom' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_editorial_scout_schedule', array( __CLASS__, 'handle_schedule' ) );
		add_action( 'admin_post_editorial_scout_batch', array( __CLASS__, 'handle_batch' ) );
		add_action( 'admin_post_editorial_scout_decision', array( __CLASS__, 'handle_decision' ) );
		add_action( 'admin_post_editorial_scout_category', array( __CLASS__, 'handle_category' ) );
		add_action( 'admin_post_editorial_scout_source', array( __CLASS__, 'handle_source' ) );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_editorial-scout' === $hook ) {
			wp_enqueue_script( 'editorial-scout-admin', plugins_url( 'assets/admin.js', EDITORIAL_SCOUT_PLUGIN_FILE ), array(), EDITORIAL_SCOUT_VERSION, true );
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

	public static function handle_newsroom() {
		self::post_gate( 'editorial_scout_newsroom' );
		$result  = new WP_Error( 'busy' );
		$filters = array();
		$lock    = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				wp_cache_delete( get_current_user_id(), 'user_meta' );
				$ticket  = get_user_meta( get_current_user_id(), Editorial_Scout_Newsroom::TICKET, true );
				$filters = is_array( $ticket ) ? Editorial_Scout_Newsroom::filters( $ticket['filters'] ) : array();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_gate ran; submit strictly validates every key and value against the server-side manifest.
				$choices = isset( $_POST['choices'] ) ? wp_unslash( $_POST['choices'] ) : array();
				$result  = Editorial_Scout_Newsroom::submit( self::post_text( 'ticket' ), $choices, $lock );
			} finally {
				Editorial_Scout_Runner::unlock( $lock );
			}
		}
		if ( is_wp_error( $result ) ) {
			set_transient( 'editorial_scout_newsroom_error_' . get_current_user_id(), $result->get_error_code(), 60 );
		} else {
			delete_transient( 'editorial_scout_newsroom_error_' . get_current_user_id() );
		}
		if ( ! is_wp_error( $result ) ) {
			$filters['receipt'] = $result['token'];
		}
		wp_safe_redirect( Editorial_Scout_Dashboard::url( 'newsroom', $filters ) . '#newsroom-decisions' );
		exit;
	}

	public static function handle_drafting() {
		self::post_gate( 'editorial_scout_drafting' );
		$id     = absint( self::post_text( 'commission_id' ) );
		$result = new WP_Error( 'busy' );
		$lock   = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				$mode = self::post_text( 'mode' );
				if ( 'prepare' === $mode ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_gate verified nonce; service validates every field, type and bound.
					$result = Editorial_Scout_Drafting::prepare( $id, wp_unslash( $_POST ), $lock );
				} elseif ( 'write' === $mode ) {
					$result = Editorial_Scout_Drafting::write( $id, self::post_text( 'token' ), '1' === self::post_text( 'consent' ), $lock );
				} elseif ( 'create' === $mode ) {
					$result = Editorial_Scout_Drafting::create( $id, self::post_text( 'token' ), '1' === self::post_text( 'approve' ), $lock );
				}
			} catch ( Throwable $error ) {
				$result = new WP_Error( 'writing_interrupted' );
			} finally {
				Editorial_Scout_Runner::unlock( $lock );
			}
		}
		set_transient( 'editorial_scout_writing_notice_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_code() : $result, 60 );
		wp_safe_redirect( Editorial_Scout_Dashboard::url( 'articles', array( 'prepare' => $id ) ) . '#scout-writing' );
		exit;
	}

	public static function handle_decision() {
		self::post_gate( 'editorial_scout_decision' );
		$result = new WP_Error( 'busy' );
		$lock   = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				$hash    = self::post_text( 'story_hash' );
				$version = Editorial_Scout_Newsroom::decision_version( $hash );
				$result  = is_wp_error( $version ) ? $version : ( hash_equals( $version, self::post_text( 'decision_version' ) ) ? Editorial_Scout_Store::decide( $hash, self::post_text( 'status' ) ) : new WP_Error( 'stale_offer' ) );
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

	public static function handle_schedule() {
		self::post_gate( 'editorial_scout_schedule' );
		$result = Editorial_Scout_Scheduling::configure( self::post_text( 'schedule' ) );
		self::finish( 'settings', is_wp_error( $result ) ? $result->get_error_code() : 'saved' );
	}

	public static function handle_batch() {
		self::post_gate( 'editorial_scout_batch' );
		$result = Editorial_Scout_Scheduling::manual_batch( self::post_text( 'batch_token' ) );
		self::finish( 'sources', is_wp_error( $result ) ? $result->get_error_code() : 'batch_updated' );
	}

	public static function handle_intelligence() {
		self::post_gate( 'editorial_scout_intelligence' );
		$result = new WP_Error( 'busy' );
		$lock   = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				wp_cache_delete( get_current_user_id(), 'user_meta' );
				wp_cache_delete( Editorial_Scout_Intelligence::OPTION, 'options' );
				wp_cache_delete( Editorial_Scout_Intelligence::RESULTS, 'options' );
				wp_cache_delete( Editorial_Scout_OpenAI::OPTION, 'options' );
				wp_cache_delete( Editorial_Scout_OpenAI::USAGE, 'options' );
				$state  = Editorial_Scout_Intelligence::state();
				$result = (int) ( $state['version'] ?? 1 ) > 1 ? new WP_Error( 'upgrade_required' ) : Editorial_Scout_Intelligence_Screen::mutate( $lock );
			} finally {
				Editorial_Scout_Runner::unlock( $lock );
			}
		}
		self::finish( 'profiles', is_wp_error( $result ) ? $result->get_error_code() : 'saved' );
	}

	public static function render_page() {
		self::authorize();
		Editorial_Scout_Dashboard::render();
	}
}
