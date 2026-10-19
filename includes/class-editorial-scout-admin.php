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
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_editorial_scout_source', array( __CLASS__, 'handle_source' ) );
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
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'POST is required.', 'editorial-scout' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'editorial_scout_source' );
		$id   = isset( $_POST['source_id'] ) && is_scalar( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$ok   = false;
		$lock = Editorial_Scout_Runner::lock();
		if ( $lock ) {
			try {
				$source = $id ? Editorial_Scout_Store::source( $id ) : null;
				if ( 'delete' === $mode && $source ) {
					Editorial_Scout_Store::delete( $id );
					$ok = true;
				} elseif ( 'toggle' === $mode && $source ) {
					global $wpdb;
					$ok = false !== $wpdb->update( Editorial_Scout_Store::table( 'sources' ), array( 'enabled' => (int) ! $source->enabled ), array( 'id' => $id ) );
				} elseif ( 'save' === $mode ) {
					$name    = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
					$url     = isset( $_POST['url'] ) && is_string( $_POST['url'] ) ? trim( wp_unslash( $_POST['url'] ) ) : '';
					$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
					$valid   = Editorial_Scout_Feed::url( $url );
					if ( '' !== $name && mb_strlen( $name ) <= 200 && ! is_wp_error( $valid ) ) {
						$ok = Editorial_Scout_Store::save( $id, $name, $valid, $enabled );
					}
				}
			} finally {
				Editorial_Scout_Runner::unlock( $lock );
			}
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'editorial-scout',
					'result' => $ok ? 'saved' : 'failed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function action_form( $source, $mode, $label ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
			<input type="hidden" name="action" value="editorial_scout_source">
			<input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>">
			<input type="hidden" name="source_id" value="<?php echo esc_attr( $source->id ); ?>">
			<?php wp_nonce_field( 'editorial_scout_source' ); ?>
			<button class="button" type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	public static function render_page() {
		self::authorize();
		// Read-only navigation does not mutate state and needs no nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$page    = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? max( 1, min( 100, absint( $_GET['paged'] ) ) ) : 1;
		$result  = isset( $_GET['result'] ) && is_string( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$edit  = $edit_id ? Editorial_Scout_Store::source( $edit_id ) : null;
		$queue = Editorial_Scout_Store::queue( $page );
		?>
		<div class="wrap">
		<h1><?php esc_html_e( 'Editorial Scout', 'editorial-scout' ); ?></h1>
		<p><?php esc_html_e( 'Feed discovery only. No posts are created or published. Discovered links and titles are unverified third-party information.', 'editorial-scout' ); ?></p>
		<?php if ( 'saved' === $result ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Source updated.', 'editorial-scout' ); ?></p></div>
		<?php elseif ( 'failed' === $result ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Could not update the source. Check the public HTTPS URL, unique URL, name (1–200 characters), and 50-source limit. A feed check may be running; retry shortly.', 'editorial-scout' ); ?></p></div>
		<?php endif; ?>
		<h2><?php esc_html_e( 'Source Manager', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Checks run hourly when WP-Cron executes. Use the final feed URL: redirects are rejected. Deleting a source or changing its URL removes its discovery history.', 'editorial-scout' ); ?></p>
		<table class="widefat striped"><thead><tr>
		<?php foreach ( array( __( 'Name', 'editorial-scout' ), __( 'Feed URL', 'editorial-scout' ), __( 'Enabled', 'editorial-scout' ), __( 'Last checked (UTC)', 'editorial-scout' ), __( 'Status', 'editorial-scout' ), __( 'Actions', 'editorial-scout' ) ) as $label ) : ?>
			<th scope="col"><?php echo esc_html( $label ); ?></th>
		<?php endforeach; ?>
		</tr></thead><tbody>
		<?php foreach ( Editorial_Scout_Store::sources() as $source ) : ?>
			<tr><td><?php echo esc_html( $source->name ); ?></td><td><?php echo esc_html( $source->url ); ?></td>
			<td><?php echo $source->enabled ? esc_html__( 'Yes', 'editorial-scout' ) : esc_html__( 'No', 'editorial-scout' ); ?></td>
			<td><?php echo esc_html( $source->last_checked ?? '—' ); ?></td><td><?php echo esc_html( $source->status ); ?></td>
			<td><a class="button" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'editorial-scout',
						'edit' => $source->id,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
										"><?php esc_html_e( 'Edit', 'editorial-scout' ); ?></a>
			<?php self::action_form( $source, 'toggle', $source->enabled ? __( 'Disable', 'editorial-scout' ) : __( 'Enable', 'editorial-scout' ) ); ?>
			<?php self::action_form( $source, 'delete', __( 'Delete source and history', 'editorial-scout' ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<h3><?php echo $edit ? esc_html__( 'Edit source', 'editorial-scout' ) : esc_html__( 'Add source', 'editorial-scout' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="editorial_scout_source"><input type="hidden" name="mode" value="save">
			<input type="hidden" name="source_id" value="<?php echo esc_attr( $edit->id ?? 0 ); ?>">
			<?php wp_nonce_field( 'editorial_scout_source' ); ?>
			<p><label for="scout-name"><?php esc_html_e( 'Name', 'editorial-scout' ); ?></label><br><input id="scout-name" name="name" maxlength="200" required value="<?php echo esc_attr( $edit->name ?? '' ); ?>"></p>
			<p><label for="scout-url"><?php esc_html_e( 'Feed URL (HTTPS)', 'editorial-scout' ); ?></label><br><input class="large-text" id="scout-url" type="url" name="url" maxlength="2048" required value="<?php echo esc_attr( $edit->url ?? '' ); ?>"></p>
			<p><label><input type="checkbox" name="enabled" value="1" <?php checked( ! $edit || $edit->enabled ); ?>><?php esc_html_e( 'Enabled', 'editorial-scout' ); ?></label></p>
			<?php submit_button( __( 'Save source', 'editorial-scout' ) ); ?>
		</form>
		<h2><?php esc_html_e( 'Discovery queue', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Read-only metadata; retained for up to 30 days and 5,000 items. Publication dates are supplied by the source. All times are UTC.', 'editorial-scout' ); ?></p>
		<table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Article', 'editorial-scout' ); ?></th><th scope="col"><?php esc_html_e( 'Source', 'editorial-scout' ); ?></th><th scope="col"><?php esc_html_e( 'Published', 'editorial-scout' ); ?></th><th scope="col"><?php esc_html_e( 'Discovered', 'editorial-scout' ); ?></th></tr></thead><tbody>
		<?php foreach ( array_slice( $queue, 0, 50 ) as $item ) : ?>
			<tr><td><a href="<?php echo esc_url( $item->url, array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer external"><?php echo esc_html( $item->title ? $item->title : __( '(Untitled)', 'editorial-scout' ) ); ?></a></td><td><?php echo esc_html( $item->source_name ); ?></td><td><?php echo esc_html( $item->published_at ?? '—' ); ?></td><td><?php echo esc_html( $item->discovered_at ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $queue ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No discoveries on this page.', 'editorial-scout' ); ?></td></tr>
		<?php endif; ?>
		</tbody></table>
		<?php
		foreach ( array(
			-1 => __( 'Previous', 'editorial-scout' ),
			1  => __( 'Next', 'editorial-scout' ),
		) as $offset => $label ) :
			?>
			<?php if ( ( -1 === $offset && $page > 1 ) || ( 1 === $offset && count( $queue ) > 50 && $page < 100 ) ) : ?>
				<a class="button" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'  => 'editorial-scout',
							'paged' => $page + $offset,
						),
						admin_url( 'admin.php' )
					)
				);
				?>
										"><?php echo esc_html( $label ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
		</div>
		<?php
	}
}
