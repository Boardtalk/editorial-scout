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
		$counts  = Editorial_Scout_Newsroom::counts();
		$state   = Editorial_Scout_Intelligence::state();
		$report  = Editorial_Scout_Newsroom::report();
		$error   = get_transient( 'editorial_scout_newsroom_error_' . get_current_user_id() );
		delete_transient( 'editorial_scout_newsroom_error_' . get_current_user_id() );
		?>
		<h2><?php esc_html_e( 'The Newsroom', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Your editorial desk: open an offer, choose what deserves your attention, then submit your decisions together. Leaving a choice blank changes nothing. Commissioning reserves work for future writing; it creates no article or WordPress draft.', 'editorial-scout' ); ?></p>
		<?php if ( ! is_wp_error( $counts ) ) : ?>
		<dl class="scout-status-strip" aria-label="<?php esc_attr_e( 'Newsroom totals across all filters', 'editorial-scout' ); ?>">
			<?php foreach ( array( 'new', 'shortlisted', 'commissioned' ) as $status ) : ?>
		<div><dt><?php echo esc_html( Editorial_Scout_Newsroom::statuses()[ $status ] ); ?></dt><dd><?php echo esc_html( number_format_i18n( $counts[ $status ] ?? 0 ) ); ?></dd></div>
		<?php endforeach; ?></dl>
			<?php
		else :
			?>
			<p role="status"><?php esc_html_e( 'Totals are temporarily unavailable.', 'editorial-scout' ); ?></p><?php endif; ?>
		<p class="description"><?php esc_html_e( 'Article creation is not enabled. Other WordPress articles and author/category totals are not counted here.', 'editorial-scout' ); ?></p>
		<details><summary><?php esc_html_e( 'How offers are ordered', 'editorial-scout' ); ?></summary><p><?php esc_html_e( 'Heuristic ordering, not AI: clear title-phrase matches to the selected saved profile come first, followed by recency (seven days, then thirty days), then latest observation and a stable ID. Up to eight relevant and eight excluded phrases are considered. This can miss related subjects. If publication is unknown, first discovery is the recency signal; future dates gain no priority. Feed counts never increase priority.', 'editorial-scout' ); ?></p><p><?php esc_html_e( 'Matching article URLs retain every original observation. Different feeds can share an owner, copy one another or repeat a story. Feed coverage does not establish independence, factual verification or importance. No summaries are displayed because this release has not stored feed excerpts.', 'editorial-scout' ); ?></p></details>
		<form method="get" class="editorial-scout-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="editorial-scout"><input type="hidden" name="tab" value="newsroom">
		<label for="newsroom-status"><?php esc_html_e( 'Editorial state', 'editorial-scout' ); ?></label><select id="newsroom-status" name="status">
		<?php foreach ( array_merge( array( 'all' => __( 'All decisions', 'editorial-scout' ) ), Editorial_Scout_Newsroom::statuses() ) as $key => $label ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filters['status'] ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?></select>
		<label for="newsroom-profile"><?php esc_html_e( 'Profile for prioritization', 'editorial-scout' ); ?></label><select id="newsroom-profile" name="profile"><option value=""><?php esc_html_e( 'Recency only', 'editorial-scout' ); ?></option>
		<?php foreach ( $state['profiles'] ?? array() as $key => $profile ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filters['profile'] ); ?>><?php echo esc_html( $profile['name'] ); ?></option>
		<?php endforeach; ?></select>
		<label><input type="checkbox" name="title_match" value="1" <?php checked( '1', $filters['title_match'] ); ?>><?php esc_html_e( 'Only clear title matches', 'editorial-scout' ); ?></label>
		<label for="newsroom-source"><?php esc_html_e( 'Source', 'editorial-scout' ); ?></label><select id="newsroom-source" name="source"><option value="0"><?php esc_html_e( 'All sources', 'editorial-scout' ); ?></option>
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
		<label for="newsroom-category"><?php esc_html_e( 'Source category', 'editorial-scout' ); ?></label><select id="newsroom-category" name="category"><option value="0"><?php esc_html_e( 'All source categories', 'editorial-scout' ); ?></option>
		<?php foreach ( Editorial_Scout_Store::categories() as $category ) : ?>
		<option value="<?php echo esc_attr( $category->id ); ?>" <?php selected( $category->id, $filters['category'] ); ?>><?php echo esc_html( $category->name ); ?></option>
		<?php endforeach; ?></select><button class="button" type="submit"><?php esc_html_e( 'Show offers', 'editorial-scout' ); ?></button>
		<a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'newsroom' ) ); ?>"><?php esc_html_e( 'Clear filters', 'editorial-scout' ); ?></a></form>
		<?php
		if ( is_wp_error( $rows ) ) {
			echo '<p role="status">' . esc_html( self::message( 'storage_failed' ) ) . '</p>';
			$rows = array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, per-user receipt token; never authorizes a write.
		$receipt  = isset( $_GET['receipt'] ) && is_string( $_GET['receipt'] ) ? sanitize_text_field( wp_unslash( $_GET['receipt'] ) ) : '';
		$complete = ! $error && $receipt && isset( $report['token'] ) && hash_equals( $report['token'], $receipt );
		$token    = $complete ? '' : Editorial_Scout_Newsroom::ticket( $rows, $filters );
		if ( $complete ) {
			$rows = array();
		}
		if ( ! $rows && ! $complete ) :
			?>
		<p><?php esc_html_e( 'Your desk is clear for this view. Try another editorial state, add a source or check your feeds when you are ready.', 'editorial-scout' ); ?> <a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'sources' ) ); ?>"><?php esc_html_e( 'Open Sources', 'editorial-scout' ); ?></a></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="editorial_scout_newsroom"><input type="hidden" name="ticket" value="<?php echo esc_attr( $token ); ?>">
		<?php wp_nonce_field( 'editorial_scout_newsroom' ); ?>
		<div class="scout-offers">
		<?php foreach ( array_slice( $rows, 0, Editorial_Scout_Newsroom::PAGE_SIZE ) as $row ) : ?>
			<?php $coverage = Editorial_Scout_Newsroom::sources( $row->story_hash ); ?>
		<article class="scout-offer" aria-labelledby="offer-<?php echo esc_attr( $row->id ); ?>">
		<h3 id="offer-<?php echo esc_attr( $row->id ); ?>"><?php echo esc_html( $row->title ? $row->title : __( '(Untitled story)', 'editorial-scout' ) ); ?></h3>
		<p class="description"><?php echo esc_html( Editorial_Scout_Newsroom::statuses()[ $row->editorial_status ] ?? __( 'Unknown editorial state', 'editorial-scout' ) ); ?></p>
		<p><?php esc_html_e( 'Published (UTC):', 'editorial-scout' ); ?> <?php echo esc_html( $row->published_at ?? __( 'Not supplied', 'editorial-scout' ) ); ?></p>
		<p class="description scout-offer-source"><?php esc_html_e( 'From:', 'editorial-scout' ); ?> <?php echo esc_html( ! is_wp_error( $coverage ) && $coverage ? implode( ', ', array_slice( array_unique( array_filter( wp_list_pluck( $coverage, 'name' ) ) ), 0, 2 ) ) : __( 'Source details unavailable', 'editorial-scout' ) ); ?></p>
		<p><?php /* translators: %d: distinct configured feeds, not independent publishers. */ echo esc_html( sprintf( _n( '%d feed', '%d feeds', $row->feeds, 'editorial-scout' ), $row->feeds ) ); ?> · <?php /* translators: %d: stored source observations. */ echo esc_html( sprintf( _n( '%d observation', '%d observations', $row->observations, 'editorial-scout' ), $row->observations ) ); ?>. <?php esc_html_e( 'Independence unknown.', 'editorial-scout' ); ?></p>
		<details><summary><?php esc_html_e( 'Open story offer', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $row->title ); ?></span></summary>
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
		<li><?php echo esc_html( $source->name ? $source->name : __( 'Unnamed source', 'editorial-scout' ) ); ?> <?php echo $source->archived ? esc_html__( '(Archived)', 'editorial-scout' ) : ''; ?> —
					<?php if ( ! is_wp_error( Editorial_Scout_Feed::url( $source->article_url, false ) ) ) : ?>
		<a href="<?php echo esc_url( $source->article_url, array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer external"><?php esc_html_e( 'Read source article (opens in a new tab)', 'editorial-scout' ); ?></a>
						<?php
		else :
			?>
			<?php esc_html_e( 'Source link unavailable', 'editorial-scout' ); ?><?php endif; ?></li>
		<?php endforeach; ?></ul>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'discoveries', array( 'story' => $row->story_hash ) ) . '#source-coverage' ); ?>"><?php esc_html_e( 'All source evidence', 'editorial-scout' ); ?></a> <?php esc_html_e( 'Coverage is not corroboration.', 'editorial-scout' ); ?></p>
		<label for="choice-<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Your decision', 'editorial-scout' ); ?><span class="screen-reader-text">: <?php echo esc_html( $row->title ); ?></span></label>
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
		<?php endif; ?>
		<?php if ( $rows ) : ?>
		<p><?php esc_html_e( 'Submit once for these offers. Opening another Newsroom view replaces this form; it expires after one hour. Hold and Not Interested remain recoverable through the editorial state filter.', 'editorial-scout' ); ?></p>
		<button type="submit" class="button button-primary" <?php disabled( ! $token ); ?>><?php esc_html_e( 'Submit Decisions', 'editorial-scout' ); ?></button>
			<?php
			if ( ! $token ) :
				?>
				<p><?php echo esc_html( self::message( 'busy' ) ); ?></p><?php endif; ?>
		<?php endif; ?></div></form>
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
