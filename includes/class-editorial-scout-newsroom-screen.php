<?php
/**
 * Native, progressively enhanced editorial desk. No remote assets or generated text.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Newsroom_Screen {
	public static function message( $code ) {
		$messages = array(
			'protected_writing' => __( 'This commission has saved writing or a recovery record. Open Articles to review or recover the work. Housekeeping cannot change it; WordPress content is untouched.', 'editorial-scout' ),
			'unchanged'         => __( 'No change to this story.', 'editorial-scout' ),
			'stale_offer'       => __( 'Not changed: another decision or source change occurred. Review the current offer before choosing again.', 'editorial-scout' ),
			'invalid_batch'     => __( 'The submitted choices were not valid. Nothing was changed. Reload the Newsroom.', 'editorial-scout' ),
			'stale_batch'       => __( 'This form expired or was replaced by another Newsroom view. Reload and review current decisions.', 'editorial-scout' ),
			'used_batch'        => __( 'This submission has already been used. No decisions were repeated. Review the receipt below, or refresh offers to confirm current state if processing was interrupted.', 'editorial-scout' ),
			'newsroom_engine'   => __( 'Decisions could not be submitted safely. Ask your website administrator to check InnoDB support for the WordPress options and user metadata tables.', 'editorial-scout' ),
			'newsroom_rollback' => __( 'The batch could not be confirmed. No partial batch is intentionally committed. Refresh and review current decisions before submitting again; this form will not replay.', 'editorial-scout' ),
		);
		return $messages[ $code ] ?? Editorial_Scout_Dashboard::explanation( $code );
	}
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filters; strict types, enums and native IDs normalized below.
		$filters = Editorial_Scout_Newsroom::filters( wp_unslash( $_GET ) );
		$rows    = Editorial_Scout_Newsroom::offers( $filters );
		$counts  = Editorial_Scout_Newsroom::counts( $filters );
		$state   = Editorial_Scout_Intelligence::state();
		$report  = Editorial_Scout_Newsroom::report();
		$error   = get_transient( 'editorial_scout_newsroom_error_' . get_current_user_id() );
		delete_transient( 'editorial_scout_newsroom_error_' . get_current_user_id() );
		?>
		<h2 class="scout-workspace-heading"><?php esc_html_e( 'The Newsroom', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Choose stories for your next article.', 'editorial-scout' ); ?></p>
		<p class="description"><?php esc_html_e( 'Commissioning adds work to Waiting. AI writing and saving a WordPress draft need separate approval. Nothing is published automatically.', 'editorial-scout' ); ?></p>
		<?php if ( ! is_wp_error( $counts ) ) : ?>
		<nav class="scout-workspace-tabs" aria-label="<?php esc_attr_e( 'Newsroom views', 'editorial-scout' ); ?>">
			<?php
			foreach ( array(
				'new'          => __( 'New stories', 'editorial-scout' ),
				'shortlisted'  => __( 'Held for later', 'editorial-scout' ),
				'commissioned' => __( 'Commissioned', 'editorial-scout' ),
			) as $key => $label ) :
				?>
			<a href="
				<?php
				echo esc_url(
					Editorial_Scout_Dashboard::url(
						'newsroom',
						array_merge(
							$filters,
							array(
								'status' => $key,
								'paged'  => 1,
							)
						)
					)
				);
				?>
						"
				<?php
				if ( $key === $filters['status'] ) {
					echo 'aria-current="page"'; }
				?>
><?php echo esc_html( $label ); ?> <span>(<?php echo esc_html( number_format_i18n( $counts[ $key ] ?? 0 ) ); ?>)</span></a>
			<?php endforeach; ?>
		</nav>
		<?php else : ?>
		<p role="status"><?php esc_html_e( 'Totals are temporarily unavailable.', 'editorial-scout' ); ?></p>
		<?php endif; ?>
		<form method="get" class="editorial-scout-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="editorial-scout"><input type="hidden" name="tab" value="newsroom">
		<div class="scout-filter-field"><label for="newsroom-profile"><?php esc_html_e( 'Profile', 'editorial-scout' ); ?></label><select id="newsroom-profile" name="profile"><option value=""><?php esc_html_e( 'Recency only', 'editorial-scout' ); ?></option>
		<?php foreach ( $state['profiles'] ?? array() as $key => $profile ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filters['profile'] ); ?>><?php echo esc_html( $profile['name'] ); ?></option>
		<?php endforeach; ?></select></div>
		<div class="scout-filter-field"><label for="newsroom-source"><?php esc_html_e( 'Source', 'editorial-scout' ); ?></label><select id="newsroom-source" name="source"><option value="0"><?php esc_html_e( 'All sources', 'editorial-scout' ); ?></option>
		<?php
		$sources  = Editorial_Scout_Store::sources( true );
		$selected = $filters['source'] ? Editorial_Scout_Store::source( $filters['source'] ) : null;
		if ( $selected && ! in_array( $selected->id, wp_list_pluck( $sources, 'id' ), true ) ) {
			$sources[] = $selected;
		}
		foreach ( $sources as $source ) :
			?>
		<option value="<?php echo esc_attr( $source->id ); ?>" <?php selected( $source->id, $filters['source'] ); ?>><?php echo esc_html( $source->name . ( $source->archived ? ' (' . __( 'Archived', 'editorial-scout' ) . ')' : '' ) ); ?></option>
		<?php endforeach; ?></select></div><button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'editorial-scout' ); ?></button>
		<details class="scout-advanced-filters"
		<?php
		if ( $filters['include_disabled'] || $filters['title_match'] || $filters['category'] || ! in_array( $filters['status'], array( 'new', 'shortlisted', 'commissioned' ), true ) ) {
			echo 'open'; }
		?>
		>
		<summary><?php esc_html_e( 'More filters', 'editorial-scout' ); ?></summary><div class="editorial-scout-filters">
		<label><input type="checkbox" name="include_disabled" value="1" <?php checked( '1', $filters['include_disabled'] ); ?>><?php esc_html_e( 'Include stories from disabled sources', 'editorial-scout' ); ?></label>
		<label for="newsroom-status"><?php esc_html_e( 'Editorial state', 'editorial-scout' ); ?></label><select id="newsroom-status" name="status">
		<?php foreach ( array_merge( array( 'all' => __( 'All decisions', 'editorial-scout' ) ), Editorial_Scout_Newsroom::statuses() ) as $key => $label ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filters['status'] ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?></select>
		<label><input type="checkbox" name="title_match" value="1" <?php checked( '1', $filters['title_match'] ); ?>><?php esc_html_e( 'Only clear title matches', 'editorial-scout' ); ?></label>
		<label for="newsroom-category"><?php esc_html_e( 'Source category', 'editorial-scout' ); ?></label><select id="newsroom-category" name="category"><option value="0"><?php esc_html_e( 'All source categories', 'editorial-scout' ); ?></option>
		<?php foreach ( Editorial_Scout_Store::categories() as $category ) : ?>
		<option value="<?php echo esc_attr( $category->id ); ?>" <?php selected( $category->id, $filters['category'] ); ?>><?php echo esc_html( $category->name ); ?></option>
		<?php endforeach; ?></select>
		<button class="button" type="submit"><?php esc_html_e( 'Apply filters', 'editorial-scout' ); ?></button>
		<a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'newsroom' ) ); ?>"><?php esc_html_e( 'Clear filters', 'editorial-scout' ); ?></a>
		<p><?php esc_html_e( 'Ordering uses saved profile title phrases and recency, not AI verification. Feed coverage does not establish independence or accuracy. Feed excerpts are not stored, so no invented summaries are shown.', 'editorial-scout' ); ?></p>
		</div></details></form>
		<?php
		if ( is_wp_error( $rows ) ) {
			echo '<p role="status">' . esc_html( self::message( 'storage_failed' ) ) . '</p>';
			$rows = array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, per-user receipt token; never authorizes a write.
		$receipt  = isset( $_GET['receipt'] ) && is_string( $_GET['receipt'] ) ? sanitize_text_field( wp_unslash( $_GET['receipt'] ) ) : '';
		$complete = ! $error && $receipt && isset( $report['token'] ) && hash_equals( $report['token'], $receipt );
		Editorial_Scout_Housekeeping::render( 'newsroom' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presentation flag; never authorizes a decision.
		$inline = isset( $_GET['inline'] ) && '1' === $_GET['inline'];
		$token  = $complete && ! $inline ? '' : Editorial_Scout_Newsroom::ticket( $rows, $filters );
		if ( $complete && ! $inline ) {
			$rows = array();
		}
		if ( $inline && ( $complete || $error ) ) {
			$feedback = $error ? self::message( $error ) : __( 'Story decision saved. Other stories were left unchanged.', 'editorial-scout' );
			if ( ! $error ) {
				foreach ( $report['rows'] as $outcome ) {
					if ( $outcome['choice'] && 'saved' === $outcome['code'] ) {
						$feedback = array(
							'commissioned' => __( 'Article commissioned. Continue in Articles → Waiting. No AI request or WordPress draft was created.', 'editorial-scout' ),
							'shortlisted'  => __( 'Story held for later. Find it in Held for later. Other stories were left unchanged.', 'editorial-scout' ),
							'dismissed'    => __( 'Story marked Not interested. You can recover it through More filters. Other stories were left unchanged.', 'editorial-scout' ),
						)[ $outcome['choice'] ] ?? $feedback;
					}
					if ( $outcome['choice'] && 'saved' !== $outcome['code'] ) {
						$feedback = self::message( $outcome['code'] );
					}
				}
			}
			echo '<div id="scout-story-feedback" tabindex="-1" class="notice notice-info inline"><p role="status">' . esc_html( $feedback ) . '</p></div>';
		}

		if ( ! $rows && ! $complete ) :
			?>
		<p><?php esc_html_e( 'Your desk is clear for this view. Try another editorial state, add a source or check your feeds when you are ready.', 'editorial-scout' ); ?> <a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'sources' ) ); ?>"><?php esc_html_e( 'Open Sources', 'editorial-scout' ); ?></a></p>
		<?php endif; ?>
		<form id="scout-story-decisions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="editorial_scout_newsroom"><input type="hidden" name="ticket" value="<?php echo esc_attr( $token ); ?>">
		<?php wp_nonce_field( 'editorial_scout_newsroom' ); ?>
		<div class="scout-offers">
		<?php foreach ( array_slice( $rows, 0, Editorial_Scout_Newsroom::PAGE_SIZE ) as $row ) : ?>
			<?php $coverage = Editorial_Scout_Newsroom::sources( $row->story_hash ); ?>
		<article class="scout-offer" aria-labelledby="offer-<?php echo esc_attr( $row->id ); ?>">
		<label class="scout-story-selection"><input type="checkbox" form="scout-tidy-selection" name="stories[]" value="<?php echo esc_attr( $row->story_hash ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Select for dismissal:', 'editorial-scout' ); ?> <?php echo esc_html( $row->title ); ?></span></label>
		<h3 id="offer-<?php echo esc_attr( $row->id ); ?>"><?php echo esc_html( $row->title ? $row->title : __( '(Untitled story)', 'editorial-scout' ) ); ?></h3>
		<p class="description"><?php echo esc_html( Editorial_Scout_Newsroom::statuses()[ $row->editorial_status ] ?? __( 'Unknown editorial state', 'editorial-scout' ) ); ?></p>
		<p class="description scout-offer-source"><?php echo esc_html( ! is_wp_error( $coverage ) && $coverage ? implode( ', ', array_slice( array_unique( array_filter( wp_list_pluck( $coverage, 'name' ) ) ), 0, 2 ) ) : __( 'Source details unavailable', 'editorial-scout' ) ); ?> · <?php echo esc_html( $row->published_at ? mysql2date( get_option( 'date_format' ), $row->published_at, false ) : __( 'Publication date unknown', 'editorial-scout' ) ); ?></p>
		<div class="scout-story-actions" role="group" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: story title. */ __( 'Actions for %s', 'editorial-scout' ), $row->title ) ); ?>">
			<?php
			foreach ( array(
				'commissioned' => __( 'Commission Article', 'editorial-scout' ),
				'shortlisted'  => __( 'Hold for later', 'editorial-scout' ),
				'dismissed'    => __( 'Not interested', 'editorial-scout' ),
			) as $choice => $label ) :
				?>
		<button type="submit" name="story_action" value="<?php echo esc_attr( $row->story_hash . ':' . $choice ); ?>" class="button <?php echo 'commissioned' === $choice ? 'button-primary' : ''; ?>" <?php disabled( ! $token ); ?>><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?></div>
		<details><summary><?php esc_html_e( 'Source details', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $row->title ); ?></span></summary>
		<p><?php /* translators: %d: stored source observations. */ echo esc_html( sprintf( __( '%d source observations. Coverage is not independent verification.', 'editorial-scout' ), $row->observations ) ); ?></p>
		<p><?php echo esc_html( $row->profile_fit ? __( 'A relevant topic phrase appears in this title, without an excluded phrase. This is a local heuristic, not semantic verification.', 'editorial-scout' ) : ( $filters['profile'] ? __( 'No clear title match to the selected profile. Context may still make this useful.', 'editorial-scout' ) : __( 'No saved profile selected; this view uses recency only.', 'editorial-scout' ) ) ); ?></p>
		<p><?php echo esc_html( $row->freshness ? ( $row->published_at ? __( 'The supplied publication date falls within the last thirty days.', 'editorial-scout' ) : __( 'First discovered within the last thirty days; publication date is unknown.', 'editorial-scout' ) ) : __( 'No recent date signal. The publication date may be old, missing or in the future.', 'editorial-scout' ) ); ?></p>
			<?php
			if ( is_wp_error( $coverage ) ) :
				?>
		<p><?php esc_html_e( 'Source details are temporarily unavailable. No verification is claimed.', 'editorial-scout' ); ?></p>
				<?php
			else :
				?>
				<ul>
				<?php foreach ( array_slice( $coverage, 0, 6 ) as $source ) : ?>
		<li><?php echo esc_html( $source->name ? $source->name : __( 'Unnamed source', 'editorial-scout' ) ); ?> <?php echo $source->archived ? esc_html__( '(Archived)', 'editorial-scout' ) : ( ! $source->enabled ? esc_html__( '(Disabled)', 'editorial-scout' ) : '' ); ?> —
					<?php if ( ! is_wp_error( Editorial_Scout_Feed::url( $source->article_url, false ) ) ) : ?>
		<a href="<?php echo esc_url( $source->article_url, array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer external"><?php esc_html_e( 'Read source article (opens in a new tab)', 'editorial-scout' ); ?></a>
						<?php
		else :
			?>
			<?php esc_html_e( 'Source link unavailable', 'editorial-scout' ); ?><?php endif; ?></li>
		<?php endforeach; ?></ul>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'discoveries', array( 'story' => $row->story_hash ) ) . '#source-coverage' ); ?>"><?php esc_html_e( 'All source evidence', 'editorial-scout' ); ?></a> <?php esc_html_e( 'Coverage is not corroboration.', 'editorial-scout' ); ?></p>
		<label for="choice-<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Bulk decision', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $row->title ); ?></span></label>
		<select id="choice-<?php echo esc_attr( $row->id ); ?>" name="choices[<?php echo esc_attr( $row->story_hash ); ?>]">
		<option value=""><?php esc_html_e( 'Leave unchanged', 'editorial-scout' ); ?></option><option value="commissioned"><?php esc_html_e( 'Commission Article', 'editorial-scout' ); ?></option><option value="shortlisted"><?php esc_html_e( 'Hold for Later', 'editorial-scout' ); ?></option><option value="dismissed"><?php esc_html_e( 'Not Interested', 'editorial-scout' ); ?></option></select>
		</details></article>
		<?php endforeach; ?></div>
		<div id="newsroom-decisions" tabindex="-1">
		<?php
		if ( is_string( $error ) ) :
			?>
			<p role="status"><?php echo esc_html( self::message( $error ) ); ?></p><?php endif; ?>
		<?php if ( is_array( $report ) && ! empty( $report['rows'] ) ) : ?>
			<?php
			if ( $inline || ! $complete ) :
				?>
				<details><summary><?php esc_html_e( 'Last decision receipt', 'editorial-scout' ); ?></summary><?php endif; ?>
		<section class="scout-confirmation notice <?php echo $error ? 'notice-warning' : 'notice-success'; ?> inline" aria-labelledby="scout-receipt-heading">
		<h3 id="scout-receipt-heading"><?php echo $complete ? esc_html__( 'Decisions submitted', 'editorial-scout' ) : esc_html__( 'Your last submission', 'editorial-scout' ); ?></h3>
			<?php $summary = Editorial_Scout_Newsroom::summary( $report ); ?>
		<p role="status">
			<?php
			/* translators: 1: commissioned count, 2: held count, 3: dismissed count, 4: unchanged count, 5: failed count. */
			echo esc_html( sprintf( __( 'Commissioned: %1$d · Held: %2$d · Dismissed: %3$d · Unchanged: %4$d · Failed: %5$d', 'editorial-scout' ), $summary['commissioned'], $summary['shortlisted'], $summary['dismissed'], $summary['unchanged'], $summary['failed'] ) );
			?>
		</p>
		<p><?php esc_html_e( 'Commissions are waiting for future writing. No WordPress draft or post has been created by this submission.', 'editorial-scout' ); ?></p>
			<?php if ( is_string( $error ) && 'used_batch' !== $error ) : ?>
		<p><?php esc_html_e( 'This is an earlier confirmed receipt, not confirmation of the failed submission. Review current decisions before trying again.', 'editorial-scout' ); ?></p>
		<?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles' ) ); ?>"><?php esc_html_e( 'View Commissions', 'editorial-scout' ); ?></a>
		<a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'newsroom', $filters ) ); ?>"><?php esc_html_e( 'Continue Reviewing', 'editorial-scout' ); ?></a></p>
		<details><summary><?php esc_html_e( 'Detailed submission receipt', 'editorial-scout' ); ?></summary>
		<p><?php esc_html_e( 'Last confirmed receipt (UTC):', 'editorial-scout' ); ?> <?php echo esc_html( gmdate( 'Y-m-d H:i:s', $report['created'] ) ); ?></p><ul>
			<?php foreach ( $report['rows'] as $result ) : ?>
		<li><strong><?php echo esc_html( $result['title'] ); ?></strong>: <?php echo esc_html( 'saved' === $result['code'] ? ( Editorial_Scout_Newsroom::statuses()[ $result['choice'] ] ?? self::message( 'saved' ) ) : self::message( $result['code'] ) ); ?></li>
		<?php endforeach; ?></ul></details></section>
			<?php
			if ( $inline || ! $complete ) :
				?>
				</details><?php endif; ?>
		<?php endif; ?>
		<?php if ( $rows ) : ?>
		<p><?php esc_html_e( 'Bulk decisions: choose a decision inside Source details for each story, then apply once. Blank choices change nothing. Hold and Not interested remain recoverable through More filters. Use the selection checkboxes and Preview selected dismissals for confirmed bulk cleanup.', 'editorial-scout' ); ?></p>
		<button type="submit" class="button button-primary" <?php disabled( ! $token ); ?>><?php esc_html_e( 'Apply decisions', 'editorial-scout' ); ?></button>
			<?php
			if ( ! $token ) :
				?>
				<p><?php echo esc_html( self::message( 'busy' ) ); ?></p><?php endif; ?>
		<?php endif; ?></div></form>
		<form id="scout-tidy-selection" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="editorial_scout_housekeeping"><input type="hidden" name="mode" value="selected">
		<input type="hidden" name="_scout_tidy_nonce" value="<?php echo esc_attr( wp_create_nonce( 'editorial_scout_housekeeping' ) ); ?>">
		<button class="button" type="submit"><?php esc_html_e( 'Preview selected dismissals', 'editorial-scout' ); ?></button></form>
		<nav aria-label="<?php esc_attr_e( 'Newsroom pages', 'editorial-scout' ); ?>" class="tablenav">
		<?php
		foreach ( array(
			-1 => __( 'Previous offers', 'editorial-scout' ),
			1  => __( 'Next offers', 'editorial-scout' ),
		) as $offset => $label ) :
			?>
							<?php if ( ( -1 === $offset && $filters['paged'] > 1 ) || ( 1 === $offset && count( $rows ) > 10 && $filters['paged'] < 1500 ) ) : ?>
		<a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'newsroom', array_merge( $filters, array( 'paged' => $filters['paged'] + $offset ) ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endif; ?>
		<?php endforeach; ?></nav>
		<?php
	}
}
