<?php
/**
 * Read-only editorial work and native WordPress article history.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Articles {
	const ORIGIN_KEY = '_editorial_scout_origin';
	const ORIGIN     = 'editorial-scout/v1';
	const PAGE_SIZE  = 20;

	public static function statuses( $view = 'commissions' ) {
		if ( 'posts' === $view ) {
			return array(
				'draft'   => __( 'WordPress Draft', 'editorial-scout' ),
				'pending' => __( 'Pending Review', 'editorial-scout' ),
				'future'  => __( 'Scheduled', 'editorial-scout' ),
				'publish' => __( 'Published', 'editorial-scout' ),
				'private' => __( 'Private', 'editorial-scout' ),
				'trash'   => __( 'Trash', 'editorial-scout' ),
			);
		}
		return array(
			'linked'     => __( 'Linked to WordPress', 'editorial-scout' ),
			'ready'      => __( 'Written — awaiting draft approval', 'editorial-scout' ),
			'incomplete' => __( 'Research incomplete', 'editorial-scout' ),
			'failed'     => __( 'Writing request failed', 'editorial-scout' ),
			'creating'   => __( 'Draft recovery needed', 'editorial-scout' ),
			'sending'    => __( 'Writing started — awaiting confirmation', 'editorial-scout' ),
			'waiting'    => __( 'Waiting for Writing', 'editorial-scout' ),
			'held'       => __( 'Held for Later', 'editorial-scout' ),
			'dismissed'  => __( 'Cancelled — not interested', 'editorial-scout' ),
			'paused'     => __( 'Paused — returned to New', 'editorial-scout' ),
		);
	}
	public static function filters( $input ) {
		$view = 'posts' === ( $input['view'] ?? '' ) ? 'posts' : 'commissions';
		return array(
			'view'   => $view,
			'status' => is_string( $input['status'] ?? null ) && isset( self::statuses( $view )[ $input['status'] ] ) ? $input['status'] : '',
			'paged'  => is_scalar( $input['paged'] ?? null ) ? max( 1, min( 1000, absint( $input['paged'] ) ) ) : 1,
		);
	}
	/** Indexed status/date pagination; one representative observation per commission. */
	public static function commissions( $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden' );
		}
		global $wpdb;
		$filters = self::filters( $input );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, i.title,s.name AS source_name,w.commission_id AS writing_id,w.state AS writing_state,w.post_id,w.work_key,CASE WHEN w.post_id IS NOT NULL THEN 'linked' WHEN w.state IN ('ready','incomplete','failed','creating','sending') THEN w.state ELSE c.status END AS work_status FROM %i c LEFT JOIN %i i ON i.id =
				(SELECT MIN(e.id) FROM %i e WHERE e.story_hash = c.story_hash)
				LEFT JOIN %i s ON s.id = i.source_id LEFT JOIN %i w ON w.commission_id = c.id
				WHERE (%s = '' OR CASE WHEN w.post_id IS NOT NULL THEN 'linked' WHEN w.state IN ('ready','incomplete','failed','creating','sending') THEN w.state ELSE c.status END = %s) ORDER BY c.updated_at DESC,c.id DESC LIMIT 21 OFFSET %d",
				Editorial_Scout_Store::table( 'commissions' ),
				Editorial_Scout_Store::table( 'items' ),
				Editorial_Scout_Store::table( 'items' ),
				Editorial_Scout_Store::table( 'sources' ),
				Editorial_Scout_Store::table( 'writing' ),
				$filters['status'],
				$filters['status'],
				( $filters['paged'] - 1 ) * self::PAGE_SIZE
			)
		);
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : $rows;
	}
	public static function counts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden' );
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT CASE WHEN w.post_id IS NOT NULL THEN 'linked' WHEN w.state IN ('ready','incomplete','failed','creating','sending') THEN w.state ELSE c.status END AS work_status,COUNT(*) AS total FROM %i c LEFT JOIN %i w ON w.commission_id = c.id GROUP BY work_status", Editorial_Scout_Store::table( 'commissions' ), Editorial_Scout_Store::table( 'writing' ) ) );
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : array_map( 'intval', wp_list_pluck( $rows, 'total', 'work_status' ) );
	}
	/** Exact versioned provenance marker only: no title/author/URL guesses or writes. */
	public static function posts( $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'read_private_posts' ) ) {
			return new WP_Error( 'forbidden' );
		}
		$filters = self::filters( array_merge( $input, array( 'view' => 'posts' ) ) );
		global $wpdb;
		// WP_Meta_Query uses the database's case-insensitive collation for meta_key.
		// Keep its indexable predicate, and require a byte-exact marker on this
		// one query without relying on the generated join alias or regex behavior.
		$exact_key = static function ( $where, $query ) use ( $wpdb ) {
			if ( true === $query->get( 'editorial_scout_history' ) ) {
				$where .= $wpdb->prepare( ' AND EXISTS (SELECT 1 FROM %i scout_origin WHERE scout_origin.post_id = %i.ID AND BINARY scout_origin.meta_key = %s AND BINARY scout_origin.meta_value = %s)', $wpdb->postmeta, $wpdb->posts, self::ORIGIN_KEY, self::ORIGIN );
			}
			return $where;
		};
		add_filter( 'posts_where', $exact_key, 10, 2 );
		try {
			$query = new WP_Query(
				array(
					'editorial_scout_history' => true,
					'post_type'               => 'post',
					'post_status'             => $filters['status'] ? array( $filters['status'] ) : array_keys( self::statuses( 'posts' ) ),
					'posts_per_page'          => self::PAGE_SIZE,
					'paged'                   => $filters['paged'],
					'orderby'                 => array(
						'date' => 'DESC',
						'ID'   => 'DESC',
					),
					'ignore_sticky_posts'     => true,
					'perm'                    => 'readable',
					'update_post_meta_cache'  => false,
					'update_post_term_cache'  => false,
					'meta_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Exact namespaced marker; native meta_key index, paged; no speculative backfill.
						array(
							'key'     => self::ORIGIN_KEY,
							'value'   => self::ORIGIN,
							'compare' => '=',
							'type'    => 'BINARY',
						),
					),
				)
			);
		} finally {
			remove_filter( 'posts_where', $exact_key, 10 );
		}
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : $query;
	}
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view editorial work.', 'editorial-scout' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation; scalar bounded native ID.
		$prepare = isset( $_GET['prepare'] ) && is_scalar( $_GET['prepare'] ) ? absint( $_GET['prepare'] ) : 0;
		if ( $prepare ) {
			Editorial_Scout_Drafting_Screen::render( $prepare );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only enum/type/ID filters normalized below.
		$filters = self::filters( wp_unslash( $_GET ) );
		$counts  = self::counts();
		Editorial_Scout_Housekeeping::render( 'articles' );
		?>
		<h2><?php esc_html_e( 'Articles and editorial work', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Commissioned means ordered for future writing. Draft means a real WordPress draft. Published means a real WordPress post. Prepare article draft starts an explicit evidence and writing review. Only a second approval creates a native WordPress draft; nothing is published automatically.', 'editorial-scout' ); ?></p>
		<p><?php esc_html_e( 'WordPress owns the newspaper. Removing Editorial Scout preserves native posts, drafts, media, taxonomies and post metadata. Source evidence remains unverified.', 'editorial-scout' ); ?></p>
		<?php if ( ! is_wp_error( $counts ) ) : ?>
		<dl class="scout-status-strip" aria-label="<?php esc_attr_e( 'Commission totals across all filters', 'editorial-scout' ); ?>">
			<?php foreach ( self::statuses() as $key => $label ) : ?>
		<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( number_format_i18n( $counts[ $key ] ?? 0 ) ); ?></dd></div>
		<?php endforeach; ?></dl>
		<?php else : ?>
		<p role="status"><?php echo esc_html( Editorial_Scout_Dashboard::explanation( 'storage_failed' ) ); ?></p>
		<?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles' ) ); ?>" 
		<?php
		if ( 'commissions' === $filters['view'] ) {
			echo 'aria-current="page"'; }
		?>
		><?php esc_html_e( 'Commissions', 'editorial-scout' ); ?></a>
		<a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles', array( 'view' => 'posts' ) ) ); ?>" 
		<?php
		if ( 'posts' === $filters['view'] ) {
			echo 'aria-current="page"'; }
		?>
		><?php esc_html_e( 'WordPress articles', 'editorial-scout' ); ?></a></p>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="editorial-scout-filters">
		<input type="hidden" name="page" value="editorial-scout"><input type="hidden" name="tab" value="articles"><input type="hidden" name="view" value="<?php echo esc_attr( $filters['view'] ); ?>">
		<label for="scout-work-status"><?php esc_html_e( 'Work status', 'editorial-scout' ); ?></label>
		<select id="scout-work-status" name="status"><option value=""><?php esc_html_e( 'All statuses', 'editorial-scout' ); ?></option>
		<?php foreach ( self::statuses( $filters['view'] ) as $key => $label ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filters['status'] ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?></select><button class="button" type="submit"><?php esc_html_e( 'Filter work', 'editorial-scout' ); ?></button></form>
		<?php
		if ( 'posts' === $filters['view'] ) {
			$next = self::native_table( $filters );
		} else {
			$next = self::commission_table( $filters );
		}
		?>
		<nav class="tablenav" aria-label="<?php esc_attr_e( 'Editorial work pages', 'editorial-scout' ); ?>">
		<?php
		foreach ( array(
			-1 => __( 'Previous work', 'editorial-scout' ),
			1  => __( 'Next work', 'editorial-scout' ),
		) as $offset => $label ) :
			?>
												<?php if ( ( -1 === $offset && $filters['paged'] > 1 ) || ( 1 === $offset && $next && $filters['paged'] < 1000 ) ) : ?>
		<a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles', array_merge( $filters, array( 'paged' => $filters['paged'] + $offset ) ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endif; ?>
		<?php endforeach; ?></nav>
		<?php
	}
	private static function commission_table( $filters ) {
		$rows = self::commissions( $filters );
		if ( is_wp_error( $rows ) ) {
			echo '<p role="status">' . esc_html( Editorial_Scout_Dashboard::explanation( 'storage_failed' ) ) . '</p>';
			return false;
		}
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No commissions match this view. Commission an offer in the Newsroom to order future work.', 'editorial-scout' ) . '</p>';
			return false;
		}
		?>
		<div class="editorial-scout-table" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Commission queue', 'editorial-scout' ); ?>"><table class="widefat striped"><thead><tr>
		<?php foreach ( array( __( 'Commission', 'editorial-scout' ), __( 'Profile at commissioning', 'editorial-scout' ), __( 'Created (UTC)', 'editorial-scout' ), __( 'Current status', 'editorial-scout' ), __( 'Evidence and actions', 'editorial-scout' ) ) as $label ) : ?>
		<th scope="col"><?php echo esc_html( $label ); ?></th>
		<?php endforeach; ?></tr></thead><tbody>
		<?php foreach ( array_slice( $rows, 0, self::PAGE_SIZE ) as $row ) : ?>
			<?php
			$writing = $row->writing_state ? array(
				'post_id'  => $row->post_id,
				'work_key' => $row->work_key,
			) : null;
			$native  = $writing ? Editorial_Scout_Drafting::linked( $writing ) : null;
			?>
		<tr><th scope="row"><?php echo esc_html( $row->title ? $row->title : __( 'Original discovery unavailable', 'editorial-scout' ) ); ?><span class="description editorial-scout-url"><?php /* translators: %d: durable commission ID. */ echo esc_html( sprintf( __( 'Commission #%d', 'editorial-scout' ), $row->id ) ); ?></span></th>
		<td><?php echo esc_html( $row->profile_name ? $row->profile_name : __( 'Not recorded', 'editorial-scout' ) ); ?></td>
		<td><?php echo esc_html( $row->created_at ); ?></td><td><?php echo esc_html( $native ? ( self::statuses( 'posts' )[ $native->post_status ] ?? __( 'WordPress article', 'editorial-scout' ) ) : ( self::statuses()[ $row->work_status ] ?? __( 'Unknown workflow state', 'editorial-scout' ) ) ); ?></td>
		<td><p><?php esc_html_e( 'Original source:', 'editorial-scout' ); ?> <?php echo esc_html( $row->source_name ? $row->source_name : __( 'Source details unavailable', 'editorial-scout' ) ); ?></p><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'discoveries', array( 'story' => $row->story_hash ) ) . '#source-coverage' ); ?>"><?php esc_html_e( 'Original discovery and source evidence', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $row->title ); ?></span></a>
			<?php if ( ! $row->writing_id && ! $row->post_id ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="editorial_scout_housekeeping"><input type="hidden" name="tab" value="articles"><input type="hidden" name="mode" value="<?php echo 'dismissed' === $row->status ? 'restore' : 'cancel'; ?>"><input type="hidden" name="stories[]" value="<?php echo esc_attr( $row->story_hash ); ?>">
		<input type="hidden" name="_scout_tidy_nonce" value="<?php echo esc_attr( wp_create_nonce( 'editorial_scout_housekeeping' ) ); ?>">
		<button class="button" type="submit"><?php echo 'dismissed' === $row->status ? esc_html__( 'Restore commission', 'editorial-scout' ) : esc_html__( 'Cancel commission', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $row->title ); ?></span></button></form>
			<?php else : ?>
		<p class="description"><?php esc_html_e( 'Cancellation is unavailable: saved writing or a recovery record is protected. Review the writing workflow; WordPress drafts and articles remain untouched.', 'editorial-scout' ); ?></p>
			<?php endif; ?>
			<?php if ( $native && current_user_can( 'read_post', $native->ID ) && current_user_can( 'edit_post', $native->ID ) ) : ?>
		<p><a class="button" href="<?php echo esc_url( get_edit_post_link( $native->ID ) ); ?>"><?php echo 'draft' === $native->post_status ? esc_html__( 'Open WordPress draft', 'editorial-scout' ) : esc_html__( 'Open WordPress article', 'editorial-scout' ); ?></a></p>
		<?php elseif ( Editorial_Scout_Drafting::allowed() && ( 'waiting' === $row->status || $writing ) ) : ?>
		<p><a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles', array( 'prepare' => $row->id ) ) ); ?>"><?php esc_html_e( 'Prepare article draft', 'editorial-scout' ); ?></a></p>
		<?php endif; ?>

		<p><a href="
			<?php
			echo esc_url(
				Editorial_Scout_Dashboard::url(
					'newsroom',
					array(
						'status' => array(
							'waiting'   => 'commissioned',
							'held'      => 'shortlisted',
							'dismissed' => 'dismissed',
							'paused'    => 'new',
						)[ $row->status ] ?? 'all',
					)
				)
			);
			?>
			"><?php esc_html_e( 'Review decisions in Newsroom', 'editorial-scout' ); ?></a></p></td></tr>
		<?php endforeach; ?></tbody></table></div>
		<p class="description"><?php esc_html_e( 'Commissions are orders. A linked WordPress status means a native article exists. Profile names are snapshots from first commissioning, where recorded; older commissions have no inferred profile. Recommissioning preserves the original work ID. Held, cancelled and paused work retains its evidence. Writing notes remain separate from native article content.', 'editorial-scout' ); ?></p>
		<?php
		return count( $rows ) > self::PAGE_SIZE;
	}
	private static function native_table( $filters ) {
		$query = self::posts( $filters );
		if ( is_wp_error( $query ) ) {
			echo '<p role="status">' . esc_html__( 'Article history is unavailable. You need permission to edit others’ posts and read private posts, and database access must be working.', 'editorial-scout' ) . '</p>';
			return false;
		}
		?>
		<p><?php esc_html_e( 'Only native standard posts with the exact Editorial Scout provenance marker are listed. Titles, authors and similar subjects do not establish attribution. WordPress status is read live; plugin removal does not delete these articles.', 'editorial-scout' ); ?></p>
		<div class="editorial-scout-table" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Attributed WordPress articles', 'editorial-scout' ); ?>"><table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Article', 'editorial-scout' ); ?></th><th scope="col"><?php esc_html_e( 'WordPress status', 'editorial-scout' ); ?></th><th scope="col"><?php esc_html_e( 'Date (UTC)', 'editorial-scout' ); ?></th><th scope="col"><?php esc_html_e( 'Actions', 'editorial-scout' ); ?></th></tr></thead><tbody>
		<?php $visible = 0; ?>
		<?php foreach ( $query->posts as $post ) : ?>
			<?php
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue; }
			?>
			<?php ++$visible; ?>
		<tr><th scope="row"><?php echo esc_html( $post->post_title ? $post->post_title : __( '(Untitled article)', 'editorial-scout' ) ); ?></th><td><?php echo esc_html( self::statuses( 'posts' )[ $post->post_status ] ?? __( 'Unknown WordPress status', 'editorial-scout' ) ); ?></td><td><?php echo esc_html( '0000-00-00 00:00:00' === $post->post_date_gmt ? __( 'Not set', 'editorial-scout' ) : $post->post_date_gmt ); ?></td><td>
			<?php if ( current_user_can( 'edit_post', $post->ID ) && 'trash' !== $post->post_status ) : ?>
		<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Open WordPress editor', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $post->post_title ); ?></span></a>
			<?php endif; ?>
			<?php if ( 'publish' === $post->post_status ) : ?>
		<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"><?php esc_html_e( 'View article', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $post->post_title ); ?></span></a>
			<?php endif; ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $visible ) : ?>
		<tr><td colspan="4"><?php esc_html_e( 'No attributable WordPress articles match this view. Commissioning alone creates no article.', 'editorial-scout' ); ?></td></tr>
		<?php endif; ?></tbody></table></div>
		<?php
		return $query->max_num_pages > $filters['paged'];
	}
}
