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
	public static function sections() {
		return array(
			'waiting'   => __( 'Waiting', 'editorial-scout' ),
			'drafts'    => __( 'Drafts', 'editorial-scout' ),
			'published' => __( 'Published', 'editorial-scout' ),
			'history'   => __( 'History', 'editorial-scout' ),
		);
	}
	private static function section_statuses( $section ) {
		$keys = array(
			'waiting'   => array( 'waiting', 'ready', 'incomplete', 'failed', 'creating', 'sending' ),
			'drafts'    => array( 'draft', 'pending', 'future' ),
			'published' => array( 'publish' ),
			'history'   => array( 'held', 'dismissed', 'paused', 'private', 'trash' ),
		);
		return array_intersect_key( array_merge( self::statuses(), self::statuses( 'posts' ) ), array_flip( $keys[ $section ] ) );
	}

	public static function workspace_filters( $input ) {
		$section = is_string( $input['section'] ?? null ) && isset( self::sections()[ $input['section'] ] ) ? $input['section'] : ( 'posts' === ( $input['view'] ?? '' ) ? 'drafts' : 'waiting' );
		return array(
			'section' => $section,
			'status'  => is_string( $input['status'] ?? null ) && isset( self::section_statuses( $section )[ $input['status'] ] ) ? $input['status'] : '',
			'paged'   => is_scalar( $input['paged'] ?? null ) ? max( 1, min( 1000, absint( $input['paged'] ) ) ) : 1,
		);
	}
	/** Native rows are distinct posts. Commissions appear only without a verified native link. */
	private static function workspace_sql( $native_allowed = true ) {
		global $wpdb;
		$origin = $wpdb->prepare( 'EXISTS (SELECT 1 FROM %i origin WHERE origin.post_id=p.ID AND origin.meta_key=%s AND BINARY origin.meta_key=%s AND BINARY origin.meta_value=%s)', $wpdb->postmeta, self::ORIGIN_KEY, self::ORIGIN_KEY, self::ORIGIN );
		$anchor = $wpdb->prepare( 'EXISTS (SELECT 1 FROM %i anchor WHERE anchor.post_id=p.ID AND anchor.meta_key=%s AND BINARY anchor.meta_key=%s AND BINARY anchor.meta_value=w.work_key)', $wpdb->postmeta, Editorial_Scout_Drafting::KEY, Editorial_Scout_Drafting::KEY );
		$joins  = $wpdb->prepare( 'FROM %i c LEFT JOIN %i w ON w.commission_id=c.id LEFT JOIN %i p ON p.ID=w.post_id AND p.post_type=%s', Editorial_Scout_Store::table( 'commissions' ), Editorial_Scout_Store::table( 'writing' ), $wpdb->posts, 'post' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fragments above contain only prepared identifiers/literals; aliases and SQL below are fixed.
		$commissions = "SELECT 'commission' AS row_type,c.id AS work_id,0 AS native_id,
		(SELECT i.title FROM " . $wpdb->prepare( '%i', Editorial_Scout_Store::table( 'items' ) ) . " i WHERE i.story_hash=c.story_hash ORDER BY i.id LIMIT 1) AS title,
		c.profile_name,c.created_at,c.updated_at AS sort_at,c.story_hash,c.status AS commission_status,w.state AS writing_state,
		CASE WHEN w.state IN ('ready','incomplete','failed','creating','sending') THEN w.state WHEN w.post_id IS NOT NULL THEN 'creating' ELSE c.status END AS state,
		CASE WHEN c.status='waiting' OR w.post_id IS NOT NULL OR w.state IN ('sending','ready','creating','linked') THEN 'waiting' ELSE 'history' END AS section
		$joins WHERE NOT (p.ID IS NOT NULL AND $origin AND $anchor)";
		$native      = $wpdb->prepare(
			"SELECT 'post' AS row_type,c.id AS work_id,p.ID AS native_id,p.post_title AS title,c.profile_name,p.post_date_gmt AS created_at,p.post_modified_gmt AS sort_at,c.story_hash,c.status AS commission_status,w.state AS writing_state,p.post_status AS state,
		CASE WHEN p.post_status IN ('draft','pending','future') THEN 'drafts' WHEN p.post_status='publish' THEN 'published' ELSE 'history' END AS section
		FROM %i p LEFT JOIN %i w ON w.post_id=p.ID AND $anchor LEFT JOIN %i c ON c.id=w.commission_id
		WHERE p.post_type='post' AND $origin",
			$wpdb->posts,
			Editorial_Scout_Store::table( 'writing' ),
			Editorial_Scout_Store::table( 'commissions' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $native_allowed ? $commissions . ' UNION ALL ' . $native : $commissions;
	}
	/** Fresh SQL is intentional: native status/title changes must be reflected immediately. */
	public static function workspace( $input = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden' );
		}
		global $wpdb;
		$filters        = self::workspace_filters( $input );
		$native_allowed = current_user_can( 'edit_others_posts' ) && current_user_can( 'read_private_posts' );
		if ( ! $native_allowed && in_array( $filters['section'], array( 'drafts', 'published' ), true ) ) {
			return new WP_Error( 'forbidden' ); }
		$sql = self::workspace_sql( $native_allowed );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Union is built from fixed SQL and prepared fragments above; filters remain placeholders.
		$where  = $wpdb->prepare( " WHERE section=%s AND (%s='' OR state=%s)", $filters['section'], $filters['status'], $filters['status'] );
		$counts = $wpdb->get_results( "SELECT section,COUNT(*) AS total FROM ($sql) workspace GROUP BY section" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fully prepared fragments.
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) workspace$where" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fully prepared fragments.
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ($sql) workspace$where ORDER BY sort_at DESC,native_id DESC,work_id DESC LIMIT 21 OFFSET %d", ( $filters['paged'] - 1 ) * self::PAGE_SIZE ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->last_error ) {
			_prime_post_caches( array_filter( array_map( 'intval', wp_list_pluck( $rows, 'native_id' ) ) ), false, false );
			foreach ( $rows as $row ) {
				if ( $row->native_id && ! current_user_can( 'read_post', (int) $row->native_id ) ) {
					return new WP_Error( 'forbidden' ); }
			}
		}
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : array(
			'native_allowed' => $native_allowed,
			'rows'           => array_slice( $rows, 0, self::PAGE_SIZE ),
			'total'          => (int) $total,
			'counts'         => array_merge( array_fill_keys( array_keys( self::sections() ), 0 ), array_map( 'intval', wp_list_pluck( $counts, 'total', 'section' ) ) ),
		);
	}
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view editorial work.', 'editorial-scout' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Bounded read-only navigation.
		$prepare = isset( $_GET['prepare'] ) && is_scalar( $_GET['prepare'] ) ? absint( $_GET['prepare'] ) : 0;
		if ( $prepare ) {
			Editorial_Scout_Drafting_Screen::render( $prepare );
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filters are normalized to bounded enums and integers.
		$filters = self::workspace_filters( wp_unslash( $_GET ) );
		$data    = self::workspace( $filters );
		?>
		<h2 class="scout-workspace-heading"><?php esc_html_e( 'Articles', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Choose work to continue, or open an article in WordPress.', 'editorial-scout' ); ?></p>
		<?php
		if ( is_wp_error( $data ) ) {
			echo '<p role="status">' . esc_html__( 'Article history is unavailable. Check administrator and native post permissions, or ask your website administrator to review database access.', 'editorial-scout' ) . '</p>';
			return;
		}
		?>
		<nav class="scout-workspace-tabs" aria-label="<?php esc_attr_e( 'Article sections; totals across all statuses', 'editorial-scout' ); ?>">
		<?php foreach ( self::sections() as $key => $label ) : ?>
		<a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles', array( 'section' => $key ) ) ); ?>"
			<?php
			if ( $key === $filters['section'] ) {
				echo 'aria-current="page"'; }
			?>
		><?php echo esc_html( $label ); ?> <span>(<?php echo esc_html( ! $data['native_allowed'] && in_array( $key, array( 'drafts', 'published' ), true ) ? __( 'Unavailable', 'editorial-scout' ) : number_format_i18n( $data['counts'][ $key ] ) ); ?>)</span></a>
		<?php endforeach; ?></nav>
		<details class="scout-work-filters"
		<?php
		if ( $filters['status'] ) {
			echo 'open'; }
		?>
		><summary><?php esc_html_e( 'Filter articles', 'editorial-scout' ); ?></summary>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="editorial-scout-filters">
		<input type="hidden" name="page" value="editorial-scout"><input type="hidden" name="tab" value="articles"><input type="hidden" name="section" value="<?php echo esc_attr( $filters['section'] ); ?>">
		<label for="scout-work-status"><?php esc_html_e( 'Status', 'editorial-scout' ); ?></label><select id="scout-work-status" name="status"><option value=""><?php esc_html_e( 'All statuses in this section', 'editorial-scout' ); ?></option>
		<?php foreach ( self::section_statuses( $filters['section'] ) as $key => $label ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filters['status'] ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?></select><button class="button" type="submit"><?php esc_html_e( 'Filter work', 'editorial-scout' ); ?></button>
		<p class="description"><?php esc_html_e( 'Section totals include all statuses. The results below reflect the selected status.', 'editorial-scout' ); ?></p></form></details>
		<?php Editorial_Scout_Housekeeping::render( 'articles' ); ?>
		<div class="editorial-scout-table" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Editorial workspace', 'editorial-scout' ); ?>"><table class="widefat striped scout-article-table"><thead><tr>
		<?php
		foreach ( array( __( 'Article', 'editorial-scout' ), __( 'Profile', 'editorial-scout' ), __( 'Status', 'editorial-scout' ), __( 'Next step', 'editorial-scout' ) ) as $label ) :
			?>
			<th scope="col"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
		</tr></thead><tbody>
		<?php foreach ( $data['rows'] as $row ) : ?>
			<?php
			if ( $row->native_id && ! current_user_can( 'read_post', (int) $row->native_id ) ) {
				continue; }
			?>
		<tr><th scope="row"><?php echo esc_html( $row->title ? $row->title : __( '(Untitled article)', 'editorial-scout' ) ); ?></th>
		<td><span class="scout-mobile-label" aria-hidden="true"><?php esc_html_e( 'Profile:', 'editorial-scout' ); ?></span> <?php echo esc_html( $row->profile_name ? $row->profile_name : __( 'Not recorded', 'editorial-scout' ) ); ?></td>
		<td><span class="scout-mobile-label" aria-hidden="true"><?php esc_html_e( 'Status:', 'editorial-scout' ); ?></span> <?php echo esc_html( ( $row->native_id ? self::statuses( 'posts' ) : self::statuses() )[ $row->state ] ?? __( 'Review needed', 'editorial-scout' ) ); ?></td>
		<td><?php self::workspace_actions( $row ); ?></td></tr>
		<?php endforeach; ?>
		<?php
		if ( ! $data['rows'] ) :
			?>
			<tr><td colspan="4"><?php esc_html_e( 'No articles match this view.', 'editorial-scout' ); ?></td></tr><?php endif; ?>
		</tbody></table></div>
		<p><?php /* translators: %d: matching work items in this section and status filter. */ echo esc_html( sprintf( __( '%d items match this view.', 'editorial-scout' ), $data['total'] ) ); ?></p>
		<nav class="tablenav" aria-label="<?php esc_attr_e( 'Editorial work pages', 'editorial-scout' ); ?>">
		<?php
		foreach ( array(
			-1 => __( 'Previous work', 'editorial-scout' ),
			1  => __( 'Next work', 'editorial-scout' ),
		) as $offset => $label ) :
			?>
												<?php if ( ( -1 === $offset && $filters['paged'] > 1 ) || ( 1 === $offset && $filters['paged'] * self::PAGE_SIZE < $data['total'] && $filters['paged'] < 1000 ) ) : ?>
		<a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles', array_merge( $filters, array( 'paged' => $filters['paged'] + $offset ) ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endif; ?><?php endforeach; ?></nav>
		<details><summary><?php esc_html_e( 'About this workspace', 'editorial-scout' ); ?></summary><p><?php esc_html_e( 'Commissioning creates an order, not a WordPress post. Paid writing and saving a native draft require separate approval. WordPress owns finished articles and their current titles and statuses. Pending and scheduled work appears in Drafts; private and trashed posts and cancelled or paused commissions appear in History. Missing or changed native links remain available for safe recovery in Waiting. Nothing is published or deleted here.', 'editorial-scout' ); ?></p></details>
		<?php
	}
	private static function workspace_actions( $row ) {
		if ( $row->native_id && current_user_can( 'edit_post', (int) $row->native_id ) ) {
			$url = 'trash' === $row->state ? admin_url( 'edit.php?post_type=post&post_status=trash' ) : get_edit_post_link( (int) $row->native_id );
			echo '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open in WordPress', 'editorial-scout' ) . '</a>';
		} elseif ( ! $row->native_id && 'waiting' === $row->section && Editorial_Scout_Drafting::allowed() ) {
			$label = in_array( $row->writing_state, array( 'sending', 'creating', 'linked' ), true ) ? __( 'Review recovery', 'editorial-scout' ) : ( 'ready' === $row->writing_state ? __( 'Review written article', 'editorial-scout' ) : __( 'Prepare article draft', 'editorial-scout' ) );
			echo '<a class="button button-primary" href="' . esc_url( Editorial_Scout_Dashboard::url( 'articles', array( 'prepare' => $row->work_id ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		if ( ! $row->native_id && 'history' === $row->section && 'dismissed' !== $row->commission_status ) {
			echo '<a class="button button-primary" href="' . esc_url( Editorial_Scout_Dashboard::url( 'newsroom', array( 'status' => 'all' ) ) ) . '">' . esc_html__( 'Review in Newsroom', 'editorial-scout' ) . '</a>';
		}
		if ( ! $row->native_id && ! $row->writing_state && 'dismissed' === $row->commission_status ) {
			self::commission_action( $row );
		}

		if ( 'publish' === $row->state && $row->native_id ) {
			echo ' <a href="' . esc_url( get_permalink( (int) $row->native_id ) ) . '">' . esc_html__( 'View article', 'editorial-scout' ) . '</a>';
		}
		?>
		<details><summary><?php esc_html_e( 'Details and history', 'editorial-scout' ); ?></summary>
		<p><?php esc_html_e( 'Created (UTC):', 'editorial-scout' ); ?> <?php echo esc_html( $row->created_at && '0000-00-00 00:00:00' !== $row->created_at ? $row->created_at : __( 'Not recorded', 'editorial-scout' ) ); ?></p>
		<?php if ( $row->work_id ) : ?>
		<p><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles', array( 'prepare' => $row->work_id ) ) ); ?>"><?php esc_html_e( 'Writing request, notes and recovery', 'editorial-scout' ); ?></a></p>
		<?php endif; ?>
		<?php if ( $row->story_hash ) : ?>
		<p><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'discoveries', array( 'story' => $row->story_hash ) ) . '#source-coverage' ); ?>"><?php esc_html_e( 'Original discovery and source evidence', 'editorial-scout' ); ?></a></p>
		<?php endif; ?>
		<?php if ( ! $row->native_id && ! $row->writing_state && 'dismissed' !== $row->commission_status ) : ?>
			<?php self::commission_action( $row ); ?>
		<?php elseif ( ! $row->native_id && $row->writing_state ) : ?>
		<p><?php esc_html_e( 'Cancellation is unavailable: saved writing or a recovery record is protected.', 'editorial-scout' ); ?></p>
		<?php endif; ?></details>
		<?php
	}
	private static function commission_action( $row ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="editorial_scout_housekeeping"><input type="hidden" name="tab" value="articles"><input type="hidden" name="mode" value="<?php echo 'dismissed' === $row->commission_status ? 'restore' : 'cancel'; ?>"><input type="hidden" name="stories[]" value="<?php echo esc_attr( $row->story_hash ); ?>"><input type="hidden" name="_scout_tidy_nonce" value="<?php echo esc_attr( wp_create_nonce( 'editorial_scout_housekeeping' ) ); ?>"><button class="button button-primary" type="submit"><?php echo 'dismissed' === $row->commission_status ? esc_html__( 'Restore commission', 'editorial-scout' ) : esc_html__( 'Cancel commission', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $row->title ); ?></span></button></form>
		<?php
	}
}
