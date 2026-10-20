<?php
/**
 * Native WordPress editorial screens; no remote scripts or rendering dependencies.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Dashboard {
	private static function get( $key, $fallback = '' ) {
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $fallback; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filters.
	}

	public static function url( $tab, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'editorial-scout',
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	public static function explanation( $code ) {
		$messages = array(
			'consent_required'     => __( 'Confirm the described local analysis before proceeding.', 'editorial-scout' ),
			'ai_settings'          => __( 'Enter valid token ceilings, pricing and a real pricing date. Enabling requires a server-configured credential. Daily ceiling: 5,000–1,000,000; monthly: daily ceiling–10,000,000.', 'editorial-scout' ),
			'ai_disabled'          => __( 'AI execution is off, a server credential is unavailable, or the work lock expired. Review the connection settings.', 'editorial-scout' ),
			'ai_consent'           => __( 'Confirm consent for this specific request and select at least one approved post. Nothing was sent.', 'editorial-scout' ),
			'ai_stale'             => __( 'This sample expired or its posts or categories changed. Preview the sample again before confirming.', 'editorial-scout' ),
			'ai_budget'            => __( 'The conservative daily or monthly token ceiling would be exceeded. Nothing was sent. Review usage and provider budgets.', 'editorial-scout' ),
			'ai_cooldown'          => __( 'Wait at least one minute between AI requests, then prepare a fresh preview. No new request was sent.', 'editorial-scout' ),
			'ai_transport'         => __( 'This connection is disabled when a WordPress HTTP proxy is configured. Ask your website administrator about a direct HTTPS connection.', 'editorial-scout' ),
			'ai_size'              => __( 'This sample exceeds the bounded request allowance. Reduce the sample size and preview again.', 'editorial-scout' ),
			'ai_output'            => __( 'OpenAI returned a proposal that could not be validated. The existing profile is unchanged; use local analysis or prepare a new sample.', 'editorial-scout' ),
			'ai_request'           => __( 'The OpenAI request failed or returned an unsupported response. Check the provider account and connection with your website administrator. No automatic retry occurs; the reserved allowance is retained.', 'editorial-scout' ),
			'profile_limit'        => __( 'Choose an existing profile or remove an unused profile. At most ten profiles are supported.', 'editorial-scout' ),
			'invalid_profile'      => __( 'Enter a profile name and keep fields within their displayed limits.', 'editorial-scout' ),
			'invalid_sample'       => __( 'Choose valid UTC dates and a sample size from 1 to 32.', 'editorial-scout' ),
			'empty_sample'         => __( 'No eligible public posts matched the bounded sample. Try another range or fewer exclusions.', 'editorial-scout' ),
			'assessment_limit'     => __( 'Select one to five discoveries and an existing editorial profile.', 'editorial-scout' ),
			'invalid_assessment'   => __( 'The recommendation could not be validated. Select existing discoveries and retry.', 'editorial-scout' ),
			'stale_proposal'       => __( 'The proposal expired or its evidence changed. Run Learn My Website again.', 'editorial-scout' ),
			'category_conflict'    => __( 'Use an existing similar category. Category creation is unavailable when the bounded catalog is incomplete.', 'editorial-scout' ),
			'provider_unavailable' => __( 'External discovery relevance analysis is not enabled. Use local relevance assessment.', 'editorial-scout' ),
			'invalid_schedule'     => __( 'Choose one of the listed feed checking schedules.', 'editorial-scout' ),
			'schedule_failed'      => __( 'The feed schedule was saved but the worker could not be scheduled. Open this page to retry; contact your hosting provider or website administrator if this continues.', 'editorial-scout' ),
			'batch_pending'        => __( 'A manual check is already in progress. Use Continue checking sources below.', 'editorial-scout' ),
			'batch_expired'        => __( 'This manual batch expired or was replaced. Start a new check; recent sources still have a cooldown.', 'editorial-scout' ),
			'batch_updated'        => __( 'Manual batch updated. Review its progress below; use Continue to check the next source.', 'editorial-scout' ),
			'upgrade_required'     => __( 'Open an administrator page to complete the database upgrade before checking feeds.', 'editorial-scout' ),
			'storage_failed'       => __( 'The results could not be saved. Retry shortly; if this persists, ask your administrator to check database availability.', 'editorial-scout' ),
			'saved'                => __( 'Changes saved.', 'editorial-scout' ),
			'ok'                   => __( 'Feed checked successfully.', 'editorial-scout' ),
			'pending'              => __( 'Waiting for a feed check.', 'editorial-scout' ),
			'busy'                 => __( 'Another check or update is running. Try again shortly.', 'editorial-scout' ),
			'cooldown'             => __( 'This source was checked recently. Wait five minutes between checks.', 'editorial-scout' ),
			'disabled'             => __( 'Enable this source before checking it.', 'editorial-scout' ),
			'not_found'            => __( 'This source or story is no longer available.', 'editorial-scout' ),
			'invalid_url'          => __( 'Use a direct public HTTPS URL on port 443 without credentials or a fragment.', 'editorial-scout' ),
			'unsafe_host'          => __( 'The feed hostname could not be verified as public. Check the URL and DNS configuration.', 'editorial-scout' ),
			'fetch_failed'         => __( 'The feed could not be reached or its secure connection could not be verified.', 'editorial-scout' ),
			'http_status'          => __( 'The source did not return a direct successful response. Redirects are not followed.', 'editorial-scout' ),
			'content_type'         => __( 'The source must return uncompressed RSS or Atom XML, not a web page.', 'editorial-scout' ),
			'invalid_xml'          => __( 'The feed XML was malformed, oversized or contained unsupported declarations.', 'editorial-scout' ),
			'invalid_feed'         => __( 'The response could not be read as an RSS or Atom feed.', 'editorial-scout' ),
			'proxy_unsupported'    => __( 'Feed checking requires a direct connection; this hosting proxy configuration is unsupported.', 'editorial-scout' ),
			'decision_limit'       => __( 'The 1,000-story editorial limit has been reached. Return unneeded decisions to New before retaining more stories.', 'editorial-scout' ),
			'evidence_limit'       => __( 'The 10,000-observation editorial limit has been reached. Existing evidence is retained. Return unneeded decisions to New to release space.', 'editorial-scout' ),
			'lease_lost'           => __( 'The check was interrupted by another update or deactivation. Its results were not saved.', 'editorial-scout' ),
			'failed'               => __( 'Could not save changes. Check required fields, uniqueness and limits, or retry after the current check finishes.', 'editorial-scout' ),
		);
		return $messages[ $code ] ?? __( 'The operation could not complete. Try again; if it persists, ask your site administrator to check server configuration.', 'editorial-scout' );
	}

	public static function render() {
		$tab = self::get( 'tab', 'discoveries' );
		if ( ! in_array( $tab, array( 'sources', 'discoveries', 'settings', 'profiles' ), true ) ) {
			$tab = 'discoveries';
		}
		$notice = get_transient( 'editorial_scout_notice_' . get_current_user_id() );
		delete_transient( 'editorial_scout_notice_' . get_current_user_id() );
		?>
		<div class="wrap editorial-scout">
		<h1><?php esc_html_e( 'Editorial Scout', 'editorial-scout' ); ?></h1>
		<p><?php esc_html_e( 'Discover and review source material. No posts are created or published. Source claims are unverified.', 'editorial-scout' ); ?></p>
		<?php if ( is_array( $notice ) && isset( $notice['code'] ) ) : ?>
			<div class="notice <?php echo in_array( $notice['code'], array( 'saved', 'checked' ), true ) ? 'notice-success' : 'notice-warning'; ?>" role="status"><p>
			<?php
			if ( 'checked' === $notice['code'] ) {
				/* translators: %d: number of newly recorded source observations. */
				echo esc_html( sprintf( _n( 'Check complete: %d new source observation.', 'Check complete: %d new source observations.', $notice['count'], 'editorial-scout' ), $notice['count'] ) );
			} else {
				echo esc_html( self::explanation( $notice['code'] ) );
			}
			?>
			</p></div>
		<?php endif; ?>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Editorial Scout sections', 'editorial-scout' ); ?>">
		<?php
		foreach ( array(
			'discoveries' => __( 'Discoveries', 'editorial-scout' ),
			'sources'     => __( 'Sources', 'editorial-scout' ),
			'settings'    => __( 'Settings', 'editorial-scout' ),
			'profiles'    => __( 'Editorial Profiles', 'editorial-scout' ),
		) as $key => $label ) :
			?>
			<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"
			<?php
			if ( $tab === $key ) {
				echo 'aria-current="page"'; }
			?>
				href="<?php echo esc_url( self::url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
		</nav>
		<?php
		if ( 'sources' === $tab ) {
			self::sources();
		} elseif ( 'profiles' === $tab ) {
			Editorial_Scout_Intelligence_Screen::render();
		} elseif ( 'settings' === $tab ) {
			self::settings();
		} else {
			self::discoveries();
		}
		?>
		</div>
		<?php
	}

	private static function hidden( $name, $value ) {
		?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<?php
	}

	private static function source_action( $source, $mode, $label ) {
		?>
		<form class="editorial-scout-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		self::hidden( 'action', 'editorial_scout_source' );
		self::hidden( 'mode', $mode );
		self::hidden( 'source_id', $source->id );
		self::hidden( '_wpnonce', wp_create_nonce( 'editorial_scout_source' ) );
		?>
		<button class="button" type="submit" aria-label="<?php echo esc_attr( $label . ': ' . $source->name ); ?>"><?php echo esc_html( $label ); ?></button></form>
		<?php
	}

	private static function category_select( $id, $selected = 0, $all = false ) {
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo 'source-category' === $id ? 'category_id' : 'category'; ?>"><option value="0"><?php echo $all ? esc_html__( 'All categories', 'editorial-scout' ) : esc_html__( 'Uncategorized', 'editorial-scout' ); ?></option>
		<?php
		if ( $all ) {
			?>
			<option value="uncategorized" <?php selected( $selected, 'uncategorized' ); ?>><?php esc_html_e( 'Uncategorized', 'editorial-scout' ); ?></option>
			<?php
		}
		foreach ( Editorial_Scout_Store::categories() as $category ) {
			?>
			<option value="<?php echo esc_attr( $category->id ); ?>" <?php selected( $selected, $category->id ); ?>><?php echo esc_html( $category->name ); ?></option>
			<?php
		}
		?>
		</select>
		<?php
	}

	private static function sources() {
		$edit = Editorial_Scout_Store::source( absint( self::get( 'edit' ) ) );
		if ( $edit && $edit->archived ) {
			$edit = null;
		}
		$categories = wp_list_pluck( Editorial_Scout_Store::categories(), 'name', 'id' );
		?>
		<h2><?php esc_html_e( 'Source Manager', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Automatic checks follow the feed schedule in Settings. Check Now works independently of cron and is limited to once per source every five minutes. Times are UTC.', 'editorial-scout' ); ?></p>
		<p><?php esc_html_e( 'Removing a source or changing its feed URL preserves shortlisted and dismissed evidence; other history is removed.', 'editorial-scout' ); ?></p>
		<?php self::batch(); ?>
		<div class="editorial-scout-table" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Sources', 'editorial-scout' ); ?>"><table class="widefat striped"><thead><tr>
		<?php foreach ( array( __( 'Source', 'editorial-scout' ), __( 'Category', 'editorial-scout' ), __( 'Last check', 'editorial-scout' ), __( 'Next check', 'editorial-scout' ), __( 'Outcome', 'editorial-scout' ), __( 'Actions', 'editorial-scout' ) ) as $label ) : ?>
		<th scope="col"><?php echo esc_html( $label ); ?></th>
		<?php endforeach; ?>
		</tr></thead><tbody>
		<?php foreach ( Editorial_Scout_Store::sources() as $source ) : ?>
		<tr><th scope="row"><?php echo esc_html( $source->name ); ?><span class="description editorial-scout-url"><?php echo esc_html( $source->url ); ?></span>
			<?php
			if ( ! $source->enabled ) {
				echo '<strong>' . esc_html__( 'Disabled', 'editorial-scout' ) . '</strong>'; }
			?>
			</th>
		<td><?php echo esc_html( $categories[ $source->category_id ] ?? __( 'Uncategorized', 'editorial-scout' ) ); ?></td>
		<td><?php echo esc_html( $source->last_checked ?? '—' ); ?></td>
		<td><?php echo esc_html( $source->enabled && 'manual' !== Editorial_Scout_Scheduling::mode() ? $source->next_check : __( 'Not scheduled', 'editorial-scout' ) ); ?></td>
		<td><?php echo esc_html( self::explanation( $source->status ) ); ?>
			<?php
			if ( $source->failures ) :
				?>
			<p><?php esc_html_e( 'Retries are delayed automatically after failures.', 'editorial-scout' ); ?></p><?php endif; ?></td>
		<td><a class="button" href="<?php echo esc_url( self::url( 'sources', array( 'edit' => $source->id ) ) ); ?>"><?php esc_html_e( 'Edit', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $source->name ); ?></span></a>
			<?php
			if ( $source->enabled ) {
				self::source_action( $source, 'check', __( 'Check Now', 'editorial-scout' ) );
			}
			self::source_action( $source, 'toggle', $source->enabled ? __( 'Disable', 'editorial-scout' ) : __( 'Enable', 'editorial-scout' ) );
			self::source_action( $source, 'delete', __( 'Remove source', 'editorial-scout' ) );
			?>
		</td></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<h2><?php echo $edit ? esc_html__( 'Edit source', 'editorial-scout' ) : esc_html__( 'Add source', 'editorial-scout' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		self::hidden( 'action', 'editorial_scout_source' );
		self::hidden( 'mode', 'save' );
		self::hidden( 'source_id', $edit->id ?? 0 );
		self::hidden( '_wpnonce', wp_create_nonce( 'editorial_scout_source' ) );
		?>
		<table class="form-table" role="presentation">
		<tr><th scope="row"><label for="scout-name"><?php esc_html_e( 'Name', 'editorial-scout' ); ?></label></th><td><input class="regular-text" id="scout-name" name="name" maxlength="200" required value="<?php echo esc_attr( $edit->name ?? '' ); ?>"></td></tr>
		<tr><th scope="row"><label for="scout-url"><?php esc_html_e( 'Feed URL (HTTPS)', 'editorial-scout' ); ?></label></th><td><input class="large-text" id="scout-url" type="url" name="url" maxlength="2048" required value="<?php echo esc_attr( $edit->url ?? '' ); ?>"><p class="description"><?php esc_html_e( 'Use a final public RSS or Atom URL. Redirects and authenticated feeds are unsupported.', 'editorial-scout' ); ?></p></td></tr>
		<tr><th scope="row"><label for="source-category"><?php esc_html_e( 'Category', 'editorial-scout' ); ?></label></th><td><?php self::category_select( 'source-category', $edit->category_id ?? 0 ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Monitoring', 'editorial-scout' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! $edit || $edit->enabled ); ?>><?php esc_html_e( 'Enabled', 'editorial-scout' ); ?></label></td></tr>
		</table><?php submit_button( __( 'Save source', 'editorial-scout' ) ); ?></form>
		<?php
	}

	private static function discoveries() {
		$page    = max( 1, min( 300, absint( self::get( 'paged', '1' ) ) ) );
		$filters = array(
			'source'   => absint( self::get( 'source' ) ),
			'category' => 'uncategorized' === self::get( 'category' ) ? 'uncategorized' : absint( self::get( 'category' ) ),
			'status'   => self::get( 'status' ),
			'from'     => self::get( 'from' ),
			'to'       => self::get( 'to' ),
		);
		$queue   = Editorial_Scout_Store::queue( $page, $filters );
		?>
		<h2><?php esc_html_e( 'Discovery queue', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Matching article URLs are grouped; coverage preserves each source observation. Shortlisted and dismissed stories survive routine pruning. Returning a story to New releases that protection.', 'editorial-scout' ); ?></p>
		<form class="editorial-scout-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<?php
		self::hidden( 'page', 'editorial-scout' );
		self::hidden( 'tab', 'discoveries' );
		?>
		<label for="filter-source"><?php esc_html_e( 'Source', 'editorial-scout' ); ?></label><select name="source" id="filter-source"><option value="0"><?php esc_html_e( 'All sources', 'editorial-scout' ); ?></option>
		<?php
		$sources  = Editorial_Scout_Store::sources( true );
		$selected = $filters['source'] ? Editorial_Scout_Store::source( $filters['source'] ) : null;
		if ( $selected && ! in_array( $selected->id, wp_list_pluck( $sources, 'id' ), true ) ) {
			$sources[] = $selected;
		}
		foreach ( $sources as $source ) :
			?>
		<option value="<?php echo esc_attr( $source->id ); ?>" <?php selected( $source->id, $filters['source'] ); ?>><?php echo esc_html( $source->name . ( $source->archived ? ' (' . __( 'Archived', 'editorial-scout' ) . ')' : '' ) ); ?></option>
		<?php endforeach; ?></select>
		<label for="filter-category"><?php esc_html_e( 'Category', 'editorial-scout' ); ?></label><?php self::category_select( 'filter-category', $filters['category'], true ); ?>
		<label for="filter-status"><?php esc_html_e( 'Status', 'editorial-scout' ); ?></label><select name="status" id="filter-status"><option value=""><?php esc_html_e( 'All statuses', 'editorial-scout' ); ?></option>
		<?php
		foreach ( self::statuses() as $value => $label ) :
			?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
		<label for="filter-from"><?php esc_html_e( 'Discovered from (UTC)', 'editorial-scout' ); ?></label><input id="filter-from" name="from" type="date" value="<?php echo esc_attr( $filters['from'] ); ?>">
		<label for="filter-to"><?php esc_html_e( 'Discovered to (UTC)', 'editorial-scout' ); ?></label><input id="filter-to" name="to" type="date" value="<?php echo esc_attr( $filters['to'] ); ?>">
		<button class="button" type="submit"><?php esc_html_e( 'Filter', 'editorial-scout' ); ?></button><a href="<?php echo esc_url( self::url( 'discoveries' ) ); ?>"><?php esc_html_e( 'Clear filters', 'editorial-scout' ); ?></a></form>
		<div class="editorial-scout-table" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Discovered articles', 'editorial-scout' ); ?>"><table class="widefat striped"><thead><tr>
		<?php
		foreach ( array( __( 'Story', 'editorial-scout' ), __( 'Source coverage', 'editorial-scout' ), __( 'Discovered (UTC)', 'editorial-scout' ), __( 'Editorial status', 'editorial-scout' ) ) as $label ) :
			?>
			<th scope="col"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
		</tr></thead><tbody>
		<?php foreach ( array_slice( $queue, 0, 50 ) as $item ) : ?>
		<tr><th scope="row"><a href="<?php echo esc_url( $item->url, array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer external"><?php echo esc_html( $item->title ? $item->title : __( '(Untitled)', 'editorial-scout' ) ); ?></a><span class="description editorial-scout-url"><?php esc_html_e( 'Published (UTC):', 'editorial-scout' ); ?> <?php echo esc_html( $item->published_at ?? '—' ); ?></span></th>
		<td><?php echo esc_html( $item->source_name ); ?><p><?php /* translators: %d: matching source records. */ echo esc_html( sprintf( _n( '%d matching source', '%d matching sources', $item->coverage, 'editorial-scout' ), $item->coverage ) ); ?></p> <a href="<?php echo esc_url( self::url( 'discoveries', array( 'story' => $item->story_hash ) ) . '#source-coverage' ); ?>"><?php esc_html_e( 'View coverage', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $item->title ); ?></span></a></td><td><?php echo esc_html( $item->latest ); ?></td>
		<td><?php echo esc_html( self::statuses()[ $item->editorial_status ] ?? __( 'New', 'editorial-scout' ) ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			self::hidden( 'action', 'editorial_scout_decision' );
			self::hidden( 'story_hash', $item->story_hash );
			self::hidden( '_wpnonce', wp_create_nonce( 'editorial_scout_decision' ) );
			?>
		<label class="screen-reader-text" for="decision-<?php echo esc_attr( $item->id ); ?>"><?php /* translators: %s: story title. */ echo esc_html( sprintf( __( 'Status for %s', 'editorial-scout' ), $item->title ) ); ?></label>
		<select id="decision-<?php echo esc_attr( $item->id ); ?>" name="status">
			<?php
			foreach ( self::statuses() as $value => $label ) :
				?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $item->editorial_status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
		<button class="button" type="submit"><?php esc_html_e( 'Update status', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $item->title ); ?></span></button></form></td></tr>
		<?php endforeach; ?>
		<?php
		if ( ! $queue ) :
			?>
			<tr><td colspan="4"><?php esc_html_e( 'No discoveries match these filters.', 'editorial-scout' ); ?></td></tr><?php endif; ?>
		</tbody></table></div>
		<?php
		self::pagination( $page, count( $queue ) > 50, $filters );
		self::coverage();
		Editorial_Scout_Intelligence_Screen::assessment_form( $queue );
	}

	private static function statuses() {
		return array(
			'new'         => __( 'New', 'editorial-scout' ),
			'shortlisted' => __( 'Shortlisted', 'editorial-scout' ),
			'dismissed'   => __( 'Dismissed', 'editorial-scout' ),
		);
	}

	private static function pagination( $page, $more, $args ) {
		?>
		<nav class="tablenav" aria-label="<?php esc_attr_e( 'Discovery pages', 'editorial-scout' ); ?>">
		<?php
		foreach ( array(
			-1 => __( 'Previous', 'editorial-scout' ),
			1  => __( 'Next', 'editorial-scout' ),
		) as $offset => $label ) {
			if ( ( -1 === $offset && $page > 1 ) || ( 1 === $offset && $more && $page < 300 ) ) {
				?>
				<a class="button" href="<?php echo esc_url( self::url( 'discoveries', array_merge( $args, array( 'paged' => $page + $offset ) ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php
			}
		}
		?>
		</nav>
		<?php
	}

	private static function coverage() {
		$hash = self::get( 'story' );
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
			return;
		}
		$page = max( 1, min( 200, absint( self::get( 'coverage_page', '1' ) ) ) );
		$rows = Editorial_Scout_Store::coverage( $hash, $page );
		?>
		<h2 id="source-coverage"><?php esc_html_e( 'Source coverage', 'editorial-scout' ); ?></h2><ul>
		<?php
		foreach ( array_slice( $rows, 0, 50 ) as $row ) {
			?>
			<li><?php echo esc_html( $row->source_name . ( $row->archived ? ' (' . __( 'Archived', 'editorial-scout' ) . ')' : '' ) ); ?></a> — <a href="<?php echo esc_url( $row->url, array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->title ? $row->title : __( '(Untitled)', 'editorial-scout' ) ); ?></a> — <?php echo esc_html( $row->discovered_at ); ?></li>
			<?php
		}
		?>
		</ul>
		<?php
		foreach ( array(
			-1 => __( 'Previous coverage', 'editorial-scout' ),
			1  => __( 'Next coverage', 'editorial-scout' ),
		) as $offset => $label ) {
			if ( ( -1 === $offset && $page > 1 ) || ( 1 === $offset && count( $rows ) > 50 && $page < 200 ) ) {
				?>
				<a class="button" href="
				<?php
				echo esc_url(
					self::url(
						'discoveries',
						array(
							'story'         => $hash,
							'coverage_page' => $page + $offset,
						)
					)
				);
				?>
										"><?php echo esc_html( $label ); ?></a>
				<?php
			}
		}
	}

	private static function batch() {
		$job     = Editorial_Scout_Scheduling::job();
		$pending = $job && ( $job['cursor'] < count( $job['ids'] ) || ! empty( $job['inflight'] ) );
		?>
		<h2><?php esc_html_e( 'Manual source checks', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Checks one enabled source per request. Continue explicitly until finished. Closing this page pauses processing; progress expires after one hour. Cooldowns, disabled and removed sources are skipped. No cron is needed.', 'editorial-scout' ); ?></p>
		<?php if ( $job ) : ?>
		<p role="status">
			<?php
			/* translators: 1: completed attempts, 2: total sources, 3: successful checks, 4: failed or interrupted, 5: skipped, 6: new observations. */
			echo esc_html( sprintf( __( 'Attempted %1$d of %2$d. Successful: %3$d. Failed/interrupted: %4$d. Skipped: %5$d. New observations: %6$d.', 'editorial-scout' ), $job['cursor'], count( $job['ids'] ), $job['checked'], $job['failed'], $job['skipped'], $job['added'] ) );
			?>
		</p>
			<?php
			if ( ! empty( $job['last'] ) ) :
				?>
				<p><?php echo esc_html( self::explanation( $job['last'] ) ); ?></p><?php endif; ?>
			<?php
			if ( ! empty( $job['inflight'] ) ) :
				?>
				<p><?php esc_html_e( 'The last attempt may have been interrupted. Continue to record it and proceed; its request will not be silently replayed.', 'editorial-scout' ); ?></p><?php endif; ?>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		self::hidden( 'action', 'editorial_scout_batch' );
		self::hidden( '_wpnonce', wp_create_nonce( 'editorial_scout_batch' ) );
		self::hidden( 'batch_token', $pending ? $job['token'] : '' );
		?>
		<button class="button" type="submit"><?php echo $pending ? esc_html__( 'Continue checking sources', 'editorial-scout' ) : esc_html__( 'Check All Sources Now', 'editorial-scout' ); ?></button></form>
		<?php
	}

	private static function scheduling() {
		$health   = Editorial_Scout_Scheduling::health();
		$messages = array(
			'manual'         => __( 'Manual Only: automatic feed checks are off. Use Check Now or Check All Sources Now; no cron is required.', 'editorial-scout' ),
			'idle'           => __( 'No enabled sources need monitoring. Add or enable a source to begin.', 'editorial-scout' ),
			'grace'          => __( 'Waiting for the first automatic run. Allow up to one hour for low-traffic sites, or ask your administrator to service WordPress cron.', 'editorial-scout' ),
			'missing'        => __( 'The feed worker is not scheduled. Reload this page to retry scheduling. Contact your hosting provider or website administrator if this persists.', 'editorial-scout' ),
			'interrupted'    => __( 'An automatic run appears interrupted. Its lease expires after fifteen minutes. Retry cron and contact your hosting provider or website administrator if this repeats.', 'editorial-scout' ),
			'overdue'        => __( 'Automatic feed checks are overdue beyond the one-hour traffic allowance. Ask your hosting provider or website administrator to verify WordPress cron or the external cron task. Manual checks are available meanwhile.', 'editorial-scout' ),
			'backlog'        => __( 'The worker is running but some sources remain overdue. Allow further five-minute batches; contact your hosting provider or website administrator if the backlog persists.', 'editorial-scout' ),
			'feed_failures'  => __( 'Some feeds report failures. Review source outcomes and direct feed URLs. Contact your hosting provider or website administrator if secure connections repeatedly fail.', 'editorial-scout' ),
			'storage_failed' => __( 'Automatic checks could not confirm database access. Contact your hosting provider or website administrator to check database availability.', 'editorial-scout' ),
			'healthy'        => __( 'No overdue feed work detected. This is execution evidence, not a guarantee of complete feed coverage.', 'editorial-scout' ),
		);
		?>
		<h2><?php esc_html_e( 'Feed checking schedule', 'editorial-scout' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		self::hidden( 'action', 'editorial_scout_schedule' );
		self::hidden( '_wpnonce', wp_create_nonce( 'editorial_scout_schedule' ) );
		?>
		<p><label for="scout-schedule"><?php esc_html_e( 'Automatic feed checks', 'editorial-scout' ); ?></label>
		<select id="scout-schedule" name="schedule" aria-describedby="scout-coverage-warning">
		<?php foreach ( Editorial_Scout_Scheduling::modes() as $mode => $label ) : ?>
		<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $mode, Editorial_Scout_Scheduling::mode() ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?></select></p>
		<p id="scout-coverage-warning"><?php esc_html_e( 'Weekly and Twice Weekly checks can miss stories: RSS feeds may discard older items between checks. Complete coverage is never guaranteed. Twice Weekly means every 84 hours, not fixed weekdays.', 'editorial-scout' ); ?></p>
		<p><?php esc_html_e( 'This controls feed discovery only. Future research, draft generation and publishing schedules are separate; none are enabled here.', 'editorial-scout' ); ?></p>
		<?php submit_button( __( 'Save feed schedule', 'editorial-scout' ) ); ?></form>
		<h2><?php esc_html_e( 'Cron Health Monitor', 'editorial-scout' ); ?></h2>
		<p role="status"><?php echo esc_html( $messages[ $health['code'] ] ); ?></p>
		<p><?php esc_html_e( 'Last automatic completion (UTC):', 'editorial-scout' ); ?> <?php echo esc_html( $health['finished'] ? gmdate( 'Y-m-d H:i:s', $health['finished'] ) : __( 'Not yet observed', 'editorial-scout' ) ); ?></p>
		<p><?php esc_html_e( 'External cron is supported. DISABLE_WP_CRON alone is not an error. Health is based on actual automatic runs and due sources, with a one-hour first-run and low-traffic allowance. Manual checks do not create an automatic heartbeat.', 'editorial-scout' ); ?></p>
		<p><label for="scout-diagnostic"><?php esc_html_e( 'Sanitized diagnostic report', 'editorial-scout' ); ?></label></p>
		<textarea id="scout-diagnostic" class="large-text" rows="12" readonly><?php echo esc_textarea( Editorial_Scout_Scheduling::diagnostic() ); ?></textarea>
		<p><button id="scout-copy-diagnostic" class="button" type="button"><?php esc_html_e( 'Copy Diagnostic Report', 'editorial-scout' ); ?></button></p>
		<p><?php esc_html_e( 'The button selects the report and copies it when your browser permits. Otherwise press Ctrl+C or Command+C. Share only with a trusted support contact.', 'editorial-scout' ); ?></p>
		<?php
	}

	private static function settings() {
		self::scheduling();
		?>
		<h2><?php esc_html_e( 'Source categories', 'editorial-scout' ); ?></h2><p><?php esc_html_e( 'Configure up to 20 categories. Removing a category returns its sources to Uncategorized; editorial decisions are preserved.', 'editorial-scout' ); ?></p>
		<?php
		foreach ( array_merge(
			Editorial_Scout_Store::categories(),
			array(
				(object) array(
					'id'   => 0,
					'name' => '',
				),
			)
		) as $category ) :
			?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php
				self::hidden( 'action', 'editorial_scout_category' );
				self::hidden( 'category_id', $category->id );
				self::hidden( '_wpnonce', wp_create_nonce( 'editorial_scout_category' ) );
				?>
		<p><label for="category-<?php echo esc_attr( $category->id ); ?>"><?php echo $category->id ? esc_html__( 'Category name', 'editorial-scout' ) : esc_html__( 'New category', 'editorial-scout' ); ?></label>
		<input id="category-<?php echo esc_attr( $category->id ); ?>" name="name" maxlength="80" value="<?php echo esc_attr( $category->name ); ?>" required>
		<button class="button" name="mode" value="save" type="submit"><?php esc_html_e( 'Save category', 'editorial-scout' ); ?></button>
				<?php
				if ( $category->id ) :
					?>
					<button class="button" name="mode" value="delete" type="submit" formnovalidate><?php esc_html_e( 'Remove category', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $category->name ); ?></span></button><?php endif; ?></p></form>
		<?php endforeach; ?>
		<h2><?php esc_html_e( 'Checking and retention', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'The server cron may continue triggering WordPress cron every five minutes. The worker checks up to five due sources per callback, following the selected interval with failure backoff. Manual Only disables automatic feed work. Manual requests never schedule background work.', 'editorial-scout' ); ?></p>
		<p><?php esc_html_e( 'Unreviewed observations are pruned to 30 days and 5,000 records. Up to 1,000 shortlisted or dismissed stories and 10,000 associated source observations are retained until you return those stories to New. Existing evidence is never evicted to make room.', 'editorial-scout' ); ?></p>
		<p><?php esc_html_e( 'Requests are HTTPS-only, limited to 10 transport seconds and 1 MiB. No redirects, public post creation, AI services or image services are enabled.', 'editorial-scout' ); ?></p>
		<?php
	}
}
