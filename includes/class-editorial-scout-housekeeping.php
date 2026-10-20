<?php
/**
 * Bounded, non-destructive editorial housekeeping. Never writes native content.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Housekeeping {
	const META  = 'editorial_scout_housekeeping';
	const LIMIT = 20;

	/** A fresh database snapshot includes every source and every writing state. */
	public static function snapshot( $hash ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
			return new WP_Error( 'forbidden' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MIN(i.title) AS title,COALESCE(d.status,'new') AS status,COALESCE(d.revision,'') AS revision,c.id AS commission_id,c.status AS commission_status,c.updated_at,w.commission_id AS journal,
		COUNT(*) AS observations,MAX(CASE WHEN s.enabled=1 AND s.archived=0 THEN 1 ELSE 0 END) AS active_sources,
		SHA2(GROUP_CONCAT(CONCAT(i.id,':',COALESCE(s.enabled,0),':',COALESCE(s.archived,1)) ORDER BY i.id SEPARATOR ','),256) AS coverage
		FROM %i i LEFT JOIN %i s ON s.id=i.source_id LEFT JOIN %i d ON d.identity_hash=i.story_hash
		LEFT JOIN %i c ON c.story_hash=i.story_hash LEFT JOIN %i w ON w.commission_id=c.id
		WHERE i.story_hash=%s GROUP BY i.story_hash,d.status,d.revision,c.id,c.status,c.updated_at,w.commission_id",
				Editorial_Scout_Store::table( 'items' ),
				Editorial_Scout_Store::table( 'sources' ),
				Editorial_Scout_Store::table( 'decisions' ),
				Editorial_Scout_Store::table( 'commissions' ),
				Editorial_Scout_Store::table( 'writing' ),
				$hash
			),
			ARRAY_A
		);
		if ( $wpdb->last_error || ! $row ) {
			return new WP_Error( 'storage_failed' );
		}
		// Visibility eligibility is checked again at confirmation; the digest also
		// catches evidence changes without ever serializing writing data into a form.
		$row['version'] = hash( 'sha256', wp_json_encode( $row ) );
		return $row;
	}

	private static function eligible( $row, $mode ) {
		if ( $row['journal'] ) {
			return 'protected_writing';
		}
		if ( 'restore' === $mode ) {
			return $row['commission_id'] && 'dismissed' === $row['commission_status'] && 'dismissed' === $row['status'] ? '' : 'stale_offer';
		}
		if ( 'cancel' === $mode ) {
			return $row['commission_id'] && in_array( $row['commission_status'], array( 'waiting', 'held', 'paused' ), true ) ? '' : 'stale_offer';
		}
		// Bulk tidying cannot cancel a commission or change another editorial choice.
		if ( $row['commission_id'] || ! in_array( $row['status'], array( 'new', 'shortlisted' ), true ) ) {
			return 'tidy_protected';
		}
		return 'disabled' === $mode && ( $row['active_sources'] || 'new' !== $row['status'] ) ? 'stale_offer' : '';
	}

	/** Count and select on the server, never accept an unbounded client queue. */
	public static function disabled_stories() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden' );
		}
		$sql   = $wpdb->prepare(
			"SELECT i.story_hash FROM %i i LEFT JOIN %i d ON d.identity_hash=i.story_hash LEFT JOIN %i c ON c.story_hash=i.story_hash
		WHERE c.id IS NULL AND (d.status IS NULL OR d.status='new') AND NOT EXISTS(SELECT 1 FROM %i ai INNER JOIN %i s ON s.id=ai.source_id WHERE ai.story_hash=i.story_hash AND s.enabled=1 AND s.archived=0) GROUP BY i.story_hash",
			Editorial_Scout_Store::table( 'items' ),
			Editorial_Scout_Store::table( 'decisions' ),
			Editorial_Scout_Store::table( 'commissions' ),
			Editorial_Scout_Store::table( 'items' ),
			Editorial_Scout_Store::table( 'sources' )
		);
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) eligible" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fully prepared fixed subquery above.
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		$rows = $wpdb->get_col( $sql . ' ORDER BY i.story_hash LIMIT 20' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fully prepared above; fixed bounded ordering.
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : array(
			'total'  => (int) $total,
			'hashes' => $rows,
		);
	}

	/** Caller holds the shared lease. A preview alone changes no editorial data. */
	public static function preview( $mode, $hashes, &$lock ) {
		if ( ! current_user_can( 'manage_options' ) || ! in_array( $mode, array( 'disabled', 'selected', 'cancel', 'restore' ), true ) || ! is_array( $hashes ) || count( $hashes ) > self::LIMIT ) {
			return new WP_Error( 'invalid_batch' );
		}
		if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
			return new WP_Error( 'lease_lost' );
		}
		foreach ( $hashes as $hash ) {
			if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
				return new WP_Error( 'invalid_batch' );
			}
		}
		$hashes = array_values( array_unique( $hashes ) );
		$total  = count( $hashes );
		if ( 'disabled' === $mode ) {
			$queue = self::disabled_stories();
			if ( is_wp_error( $queue ) ) {
				return $queue;
			}
			$total  = $queue['total'];
			$hashes = $queue['hashes'];
		}
		if ( in_array( $mode, array( 'cancel', 'restore' ), true ) && 1 !== count( $hashes ) ) {
			return new WP_Error( 'invalid_batch' );
		}
		$preview = array(
			'token'   => wp_generate_uuid4(),
			'created' => time(),
			'mode'    => $mode,
			'state'   => 'pending',
			'total'   => $total,
			'rows'    => array(),
		);
		foreach ( array_unique( $hashes ) as $hash ) {
			$row = self::snapshot( $hash );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
			$preview['rows'][ $hash ] = array(
				'title'   => $row['title'],
				'version' => $row['version'],
				'code'    => self::eligible( $row, $mode ),
			);
		}
		return update_user_meta( get_current_user_id(), self::META, $preview ) ? $preview : new WP_Error( 'storage_failed' );
	}

	/** Strict single use; a failed/uncertain transaction requires a fresh preview. */
	public static function confirm( $token, $confirmed, &$lock ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || true !== $confirmed || ! is_string( $token ) ) {
			return new WP_Error( 'forbidden' );
		}
		wp_cache_delete( get_current_user_id(), 'user_meta' );
		$preview = get_user_meta( get_current_user_id(), self::META, true );
		if ( ! is_array( $preview ) || ! hash_equals( $preview['token'], $token ) || $preview['created'] < time() - HOUR_IN_SECONDS || 'pending' !== $preview['state'] ) {
			return new WP_Error( 'stale_batch' );
		}
		foreach ( array( $wpdb->usermeta, $wpdb->options ) as $table ) {
			$engine = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name=%s', $table ), ARRAY_A );
			if ( ! $engine || 'InnoDB' !== $engine['Engine'] ) {
				return new WP_Error( 'newsroom_engine' );
			}
		}
		if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
			return new WP_Error( 'lease_lost' );
		}
		$preview['state'] = 'processing';
		if ( ! update_user_meta( get_current_user_id(), self::META, $preview ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'storage_failed' );
		}
		$before    = $lock;
		$committed = false;
		$failure   = 'newsroom_rollback';
		try {
			foreach ( $preview['rows'] as $hash => &$saved ) {
				$row = self::snapshot( $hash );
				if ( is_wp_error( $row ) ) {
					throw new RuntimeException();
				}
				$code = self::eligible( $row, $preview['mode'] );
				if ( ! $saved['code'] && ! $code && hash_equals( $saved['version'], $row['version'] ) ) {
					$result = Editorial_Scout_Store::decide( $hash, 'restore' === $preview['mode'] ? 'commissioned' : 'dismissed', true );
					if ( is_wp_error( $result ) || ! $result ) {
						if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'decision_limit', 'evidence_limit', 'protected_writing' ), true ) ) {
							$failure = $result->get_error_code();
						}
						throw new RuntimeException();
					}
					$saved['code'] = 'saved';
				} else {
					$saved['code'] = $code ? $code : 'stale_offer';
				}
				if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
					throw new RuntimeException();
				}
			}
			unset( $saved );
			$preview['state'] = 'complete';
			// Persist the receipt in the same transaction; invalidate cache after commit.
			if ( false === $wpdb->update(
				$wpdb->usermeta,
				array( 'meta_value' => maybe_serialize( $preview ) ),
				array(
					'user_id'  => get_current_user_id(),
					'meta_key' => self::META,
				)
			) || ! Editorial_Scout_Runner::renew( $lock ) || false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException();
			}
			$committed = true;
			return $preview;
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( $failure );
		} finally {
			if ( ! $committed ) {
				$lock = $before;
			}
			wp_cache_delete( get_current_user_id(), 'user_meta' );
		}
	}

	public static function render( $tab ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$error = get_transient( 'editorial_scout_tidy_error_' . get_current_user_id() );
		delete_transient( 'editorial_scout_tidy_error_' . get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only receipt expansion, not authorization.
		$show = isset( $_GET['tidy'] ) && '1' === $_GET['tidy'];
		$view = get_user_meta( get_current_user_id(), self::META, true );
		?>
		<details id="scout-housekeeping" <?php echo $error || $show || ( is_array( $view ) && 'pending' === $view['state'] ) ? 'open' : ''; ?>><summary><?php esc_html_e( 'Newsroom housekeeping', 'editorial-scout' ); ?></summary>
		<p><?php esc_html_e( 'Previews expire after one hour. Starting another housekeeping preview replaces the previous one.', 'editorial-scout' ); ?></p>
		<p><?php esc_html_e( 'Disabling a source stops checks. It does not dismiss stories or cancel commissions. New Stories normally hides stories without an active source. Dismissal and cancellation preserve evidence; WordPress owns all existing drafts and articles.', 'editorial-scout' ); ?></p>
		<?php if ( $error ) : ?>
		<p role="status"><?php echo esc_html( Editorial_Scout_Newsroom_Screen::message( $error ) ); ?></p>
		<?php endif; ?>
		<?php if ( is_array( $view ) ) : ?>
			<?php
			$affected = count(
				array_filter(
					$view['rows'],
					static function ( $row ) use ( $view ) {
						return 'complete' === $view['state'] ? 'saved' === $row['code'] : '' === $row['code'];
					}
				)
			);
			?>
			<?php if ( 'processing' !== $view['state'] ) : ?>
		<p role="status"><?php /* translators: 1: affected stories, 2: blocked or unchanged stories. */ echo esc_html( sprintf( 'complete' === $view['state'] ? __( 'Changed: %1$d · Not changed: %2$d', 'editorial-scout' ) : __( 'Ready to change: %1$d · Blocked: %2$d', 'editorial-scout' ), $affected, count( $view['rows'] ) - $affected ) ); ?></p>
		<?php endif; ?>
		<h3><?php echo 'complete' === $view['state'] ? esc_html__( 'Housekeeping receipt', 'editorial-scout' ) : esc_html__( 'Review before changing anything', 'editorial-scout' ); ?></h3>
		<p><?php /* translators: 1: eligible stories overall, 2: maximum stories in this batch. */ echo esc_html( sprintf( __( 'Stories in this selection: %1$d. This bounded batch contains %2$d. Blocked or changed stories will not be updated.', 'editorial-scout' ), $view['total'], count( $view['rows'] ) ) ); ?></p>
		<p><?php echo esc_html( 'restore' === $view['mode'] ? __( 'Restore eligible cancelled commissions to Waiting for Writing.', 'editorial-scout' ) : ( 'cancel' === $view['mode'] ? __( 'Cancel this commission, keeping its history and evidence.', 'editorial-scout' ) : __( 'Dismiss eligible stories. No source evidence will be deleted.', 'editorial-scout' ) ) ); ?></p>
		<ul><?php foreach ( $view['rows'] as $row ) : ?>
		<li><?php echo esc_html( $row['title'] ); ?> — <?php echo esc_html( 'saved' === $row['code'] ? __( 'Changed', 'editorial-scout' ) : ( $row['code'] ? ( 'tidy_protected' === $row['code'] ? __( 'Not changed: this story has a commission or another editorial decision. Review it individually in Articles.', 'editorial-scout' ) : Editorial_Scout_Newsroom_Screen::message( $row['code'] ) ) : ( 'processing' === $view['state'] ? __( 'Outcome unconfirmed: review the current decision', 'editorial-scout' ) : __( 'Ready for confirmation', 'editorial-scout' ) ) ) ); ?></li>
		<?php endforeach; ?></ul>
			<?php if ( 'pending' === $view['state'] && $affected ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="editorial_scout_housekeeping"><input type="hidden" name="mode" value="confirm"><input type="hidden" name="token" value="<?php echo esc_attr( $view['token'] ); ?>"><input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
		<input type="hidden" name="_scout_tidy_nonce" value="<?php echo esc_attr( wp_create_nonce( 'editorial_scout_housekeeping' ) ); ?>">
		<label><input type="checkbox" name="confirm" value="1" required><?php esc_html_e( 'I confirm these status changes. Keep all evidence and WordPress content.', 'editorial-scout' ); ?></label>
		<button class="button" type="submit"><?php esc_html_e( 'Confirm housekeeping', 'editorial-scout' ); ?></button></form>
		<?php elseif ( 'processing' === $view['state'] ) : ?>
		<p role="status"><?php esc_html_e( 'This request was interrupted or its outcome could not be confirmed. Review current decisions and start a fresh preview; this request will not replay.', 'editorial-scout' ); ?></p>
		<?php endif; ?>
		<?php endif; ?>
		<p><a href="
		<?php
		echo esc_url(
			Editorial_Scout_Dashboard::url(
				'newsroom',
				array(
					'status'           => 'dismissed',
					'include_disabled' => '1',
				)
			)
		);
		?>
		"><?php esc_html_e( 'Recover dismissed stories', 'editorial-scout' ); ?></a> <?php esc_html_e( 'Choose Hold for Later or Commission Article. Cancelled commissions can be restored in Articles. Re-enabling a source restores visibility of its New Stories, but does not undo dismissal.', 'editorial-scout' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="editorial_scout_housekeeping"><input type="hidden" name="mode" value="disabled"><input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
		<input type="hidden" name="_scout_tidy_nonce" value="<?php echo esc_attr( wp_create_nonce( 'editorial_scout_housekeeping' ) ); ?>">
		<p><?php esc_html_e( 'Clean up New Stories supported only by disabled or archived sources. Held stories, dismissed stories and all commissions are excluded. Preview up to 20 at a time; repeat for the next batch.', 'editorial-scout' ); ?></p>
		<button class="button" type="submit"><?php esc_html_e( 'Preview disabled-source cleanup', 'editorial-scout' ); ?></button></form></details>
		<?php
	}
}
