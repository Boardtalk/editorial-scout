<?php
/**
 * Plain-English writing review with separate provider and native-draft approvals.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Drafting_Screen {
	public static function message( $code ) {
		$messages = array(
			'writing_prepared'    => __( 'Sources prepared. Review exactly what may be sent, then give consent for one writing request.', 'editorial-scout' ),
			'writing_ready'       => __( 'Writing complete. Review the article and fact-checking notes below. No WordPress post has been created yet.', 'editorial-scout' ),
			'writing_created'     => __( 'The WordPress article is linked. Editorial Scout creates drafts only; later edits and status are managed in WordPress. Existing changes are preserved.', 'editorial-scout' ),
			'writing_inadequate'  => __( 'Research incomplete. A headline alone is not enough. Add short material you have permission to use, or research this story manually. No WordPress draft was created.', 'editorial-scout' ),
			'writing_material'    => __( 'Choose a saved profile, permitted author and existing category. Confirm permission for the source material and keep each excerpt within 600 characters. Secrets must never be included.', 'editorial-scout' ),
			'writing_permission'  => __( 'You need administrator and native post permissions, the original request owner, and explicit approval. Check the author and category permissions.', 'editorial-scout' ),
			'writing_stale'       => __( 'This writing preview expired, was used, or its profile, sources or decision changed. Review current work before preparing a fresh request.', 'editorial-scout' ),
			'writing_state'       => __( 'This commission is not waiting for writing, or work is already prepared, running or linked. Review the existing result below; a running request will not be repeated.', 'editorial-scout' ),
			'writing_interrupted' => __( 'This operation could not be confirmed. Do not repeat a paid request. Reopen this work: saved results remain available, and Recover WordPress draft searches for an existing post without inserting another.', 'editorial-scout' ),
			'writing_integrity'   => __( 'The article link needs manual review. No additional post was created. Ask your website administrator to reconcile the saved work with WordPress posts; do not delete its journal or retry insertion blindly.', 'editorial-scout' ),
		);
		return $messages[ $code ] ?? Editorial_Scout_Dashboard::explanation( $code );
	}
	private static function form( $id, $mode, $token = '' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="editorial_scout_drafting"><input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>"><input type="hidden" name="commission_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
		<?php
		wp_nonce_field( 'editorial_scout_drafting' );
	}
	public static function render( $id ) {
		if ( ! Editorial_Scout_Drafting::allowed() ) {
			wp_die( esc_html__( 'You are not allowed to prepare articles.', 'editorial-scout' ), '', array( 'response' => 403 ) );
		}
		$c   = Editorial_Scout_Drafting::commission( $id );
		$job = Editorial_Scout_Drafting::job( $id );
		?>
		<div id="scout-writing" tabindex="-1">
		<?php
		$notice = get_transient( 'editorial_scout_writing_notice_' . get_current_user_id() );
		delete_transient( 'editorial_scout_writing_notice_' . get_current_user_id() );
		if ( is_string( $notice ) ) {
			echo '<div class="notice notice-info inline" role="status"><p>' . esc_html( self::message( $notice ) ) . '</p></div>';
		}
		?>
		<h2>
		<?php
		esc_html_e( 'Prepare article draft', 'editorial-scout' );
		?>
</h2>
		<p><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'articles' ) ); ?>">
		<?php
		esc_html_e( 'Back to Articles', 'editorial-scout' );
		?>
</a></p>
		<?php
		if ( ! $c ) {
			echo '<p>' . esc_html__( 'This commission is unavailable.', 'editorial-scout' ) . '</p></div>';
			return;
		}
		?>
		<h3>
		<?php
		echo esc_html( $c->title ? $c->title : __( 'Original title unavailable', 'editorial-scout' ) );
		?>
</h3>
		<p>
		<?php
		esc_html_e( 'A human editor must check and approve the final article. Feed claims and source material are unverified. No automatic publication, research, image download or background writing occurs.', 'editorial-scout' );
		?>
</p>
		<p><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'discoveries', array( 'story' => $c->story_hash ) ) . '#source-coverage' ); ?>">
		<?php
		esc_html_e( 'Original source evidence', 'editorial-scout' );
		?>
</a></p>
		<?php
		if ( $job && 'linked' === $job['state'] ) {
			$post = Editorial_Scout_Drafting::linked( $job );
			if ( $post && current_user_can( 'edit_post', $post->ID ) ) {
				echo '<p><a class="button button-primary" href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . ( 'draft' === $post->post_status ? esc_html__( 'Open WordPress draft', 'editorial-scout' ) : esc_html__( 'Open WordPress article', 'editorial-scout' ) ) . '</a></p>';
				echo '<p>' . esc_html__( 'WordPress owns this article. Its current status and edits are managed in WordPress; this workflow never overwrites them.', 'editorial-scout' ) . '</p>';
			} else {
				echo '<p role="status">' . esc_html( self::message( 'writing_integrity' ) ) . '</p>';
			}
		}
		if ( $job && isset( $job['context']['result'] ) ) {
			self::result( $job );
		}
		if ( $job && get_current_user_id() !== (int) $job['context']['user'] ) {
			echo '<p>' . esc_html__( 'Another administrator owns this writing request. Contact them to continue; their consent cannot be reused by another account.', 'editorial-scout' ) . '</p></div>';
			return;
		}
		if ( $job && 'failed' === $job['state'] ) {
			echo '<p role="status">' . esc_html( self::message( $job['context']['error'] ?? 'ai_request' ) ) . '</p>'; }
		if ( $job && 'sending' === $job['state'] ) {
			echo '<p role="status">' . esc_html__( 'The writing request was started, but no result has been confirmed. Refresh to check. An interrupted request is not retried automatically; contact your website administrator. The reserved allowance is retained.', 'editorial-scout' ) . '</p>';
		}
		if ( $job && 'waiting' === $c->status && in_array( $job['state'], array( 'ready', 'creating' ), true ) ) {
			self::form( $id, 'create', $job['context']['token'] );
			?>
		<p><label><input type="checkbox" name="approve" value="1" required>
			<?php
			esc_html_e( 'I reviewed the generated article and notes. Create or recover one native WordPress draft; do not publish it.', 'editorial-scout' );
			?>
</label></p>
		<button class="button button-primary" type="submit">
			<?php
			echo 'creating' === $job['state'] ? esc_html__( 'Recover WordPress draft', 'editorial-scout' ) : esc_html__( 'Create WordPress draft', 'editorial-scout' );
			?>
</button></form>
			<?php
		}
		if ( $job && 'waiting' !== $c->status && 'linked' !== $job['state'] ) {
			echo '<p role="status">' . esc_html__( 'Writing is paused by the current editorial decision. Review and recommission this story in Newsroom to continue. Existing articles are never removed.', 'editorial-scout' ) . '</p>'; }

		if ( $job && 'prepared' === $job['state'] ) {
			$estimate = Editorial_Scout_OpenAI::estimate( $job['context']['payload'] );
			$settings = Editorial_Scout_OpenAI::settings();
			?>
		<h3>
			<?php
			esc_html_e( 'Review sources and writing instructions', 'editorial-scout' );
			?>
</h3>
		<p>
			<?php
			esc_html_e( 'Only the displayed story, profile, selected source names/links/material and chosen category are sent. No author identity, existing article bodies, private posts or media are sent.', 'editorial-scout' );
			?>
</p>
		<h4><?php echo esc_html( $job['context']['payload']['profile']['name'] ); ?></h4>
		<dl><?php foreach ( Editorial_Scout_Intelligence::fields() as $key => $label ) : ?>
		<dt><strong><?php echo esc_html( $label ); ?></strong></dt><dd><?php echo esc_html( $job['context']['payload']['profile'][ $key ] ); ?></dd>
		<?php endforeach; ?></dl>
		<p><?php esc_html_e( 'Selected WordPress category:', 'editorial-scout' ); ?> <?php echo esc_html( $job['context']['payload']['category']['name'] ); ?></p>
			<?php foreach ( $job['context']['payload']['sources'] as $source ) : ?>
		<h4><?php echo esc_html( $source['name'] ); ?></h4><p><?php echo esc_html( $source['url'] ); ?></p><p><?php echo esc_html( $source['material'] ); ?></p>
		<?php endforeach; ?>
		<details><summary><?php esc_html_e( 'Exact request data (advanced)', 'editorial-scout' ); ?></summary>
		<pre class="scout-writing-material" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Exact writing material', 'editorial-scout' ); ?>"><?php echo esc_html( wp_json_encode( $job['context']['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
		<details><summary>
			<?php
			esc_html_e( 'Writing rules and request allowance', 'editorial-scout' );
			?>
</summary><p>
			<?php
			echo esc_html( Editorial_Scout_Drafting::instructions() );
			?>
</p>
		<p>
			<?php
			/* translators: 1: reserved tokens, 2: estimated USD, 3: pricing date. */ echo esc_html( sprintf( __( 'Conservative allowance: %1$d tokens, approximately USD %2$s using assumptions dated %3$s. This is not a guaranteed billing cap. Failed or interrupted requests keep their reservation.', 'editorial-scout' ), $estimate['reserved'], number_format_i18n( $estimate['cost'], 4 ), $settings['price_date'] ) );
			?>
</p></details>
		<p>
			<?php
			/* translators: %s: estimated USD. */ echo esc_html( sprintf( __( 'One writing request: estimated allowance USD %s. Review the advanced details for token and pricing assumptions.', 'editorial-scout' ), number_format_i18n( $estimate['cost'], 4 ) ) );
			?>
</p>
		<p>
			<?php
			esc_html_e( 'OpenAI receives this material after consent. Public material can contain personal data; exclude sensitive information. store:false is requested but does not guarantee zero provider retention. There is no automatic retry or refund.', 'editorial-scout' );
			?>
</p>
			<?php
			self::form( $id, 'write', $job['context']['token'] );
			?>
		<p><label><input type="checkbox" name="consent" value="1" required>
			<?php
			esc_html_e( 'I approve sending exactly this material to OpenAI for one billable writing request.', 'editorial-scout' );
			?>
</label></p>
		<button class="button button-primary" type="submit">
			<?php
			esc_html_e( 'Write draft', 'editorial-scout' );
			?>
</button></form>
			<?php
		}
		if ( 'waiting' === $c->status && ( ! $job || in_array( $job['state'], array( 'prepared', 'failed', 'incomplete' ), true ) ) ) {
			self::prepare( $c, $job );
		}
		?>
		</div>
		<?php
	}
	private static function prepare( $c, $job ) {
		$profiles = Editorial_Scout_Intelligence::state()['profiles'] ?? array();
		$selected = $job['context']['profile'] ?? $c->profile_id;
		$profile  = $profiles[ $selected ] ?? array();
		$terms    = Editorial_Scout_Intelligence::terms();
		?>
		<h3>
		<?php
		esc_html_e( 'Review sources', 'editorial-scout' );
		?>
</h3>
		<p>
		<?php
		esc_html_e( 'This first release does not crawl article pages: permission, robots rules and copyright cannot be inferred from an RSS link. Open the original source yourself. Supply only short excerpts or your own research notes that you are permitted to use (40–600 characters per source). Never paste paywalled, restricted, private or sensitive material. Headlines alone produce research incomplete without a paid request. No claim is independently fact-checked.', 'editorial-scout' );
		?>
</p>
		<?php
		if ( ! $profiles || count( $terms ) > 200 ) {
			echo '<p>' . esc_html__( 'Create a saved editorial profile and ensure the native category catalog contains at most 200 categories before preparing writing.', 'editorial-scout' ) . '</p>';
			return;
		}
		?>
		<?php
		self::form( $c->id, 'prepare' );
		?>
		<p><label for="writing-profile">
		<?php
		esc_html_e( 'Editorial profile', 'editorial-scout' );
		?>
</label> <select id="writing-profile" name="profile" required><option value="">
		<?php
		esc_html_e( 'Choose a profile', 'editorial-scout' );
		?>
</option>
		<?php
		foreach ( $profiles as $key => $value ) :
			?>
			<option value="<?php echo esc_attr( $key ); ?>" 
			<?php
			selected( $selected, $key );
			?>
>
			<?php
			echo esc_html( $value['name'] );
			?>
</option>
			<?php
endforeach;
		?>
</select></p>
		<p>
		<?php
		esc_html_e( 'The saved profile is selected where it still exists. Review its current settings; the transmitted snapshot is shown before consent. Changing profiles requires reviewing the category choice again.', 'editorial-scout' );
		?>
</p>
		<p><label for="writing-author">
		<?php
		esc_html_e( 'WordPress author', 'editorial-scout' );
		?>
</label> <select id="writing-author" name="author">
		<?php
		foreach ( Editorial_Scout_Drafting::authors() as $uid => $name ) :
			?>
			<option value="<?php echo esc_attr( $uid ); ?>">
			<?php
			echo esc_html( $name );
			?>
</option>
			<?php
endforeach;
		?>
</select></p>
		<p><label for="writing-category">
		<?php
		esc_html_e( 'WordPress category', 'editorial-scout' );
		?>
</label> <select id="writing-category" name="category" required><option value="">
		<?php
		esc_html_e( 'Choose an existing category', 'editorial-scout' );
		?>
</option>
		<?php
		foreach ( $terms as $term ) :
			?>
			<option value="<?php echo esc_attr( $term->term_id ); ?>" 
			<?php
			selected( $profile['default_category'] ?? 0, $term->term_id );
			?>
>
			<?php
			echo esc_html( $term->name );
			?>
</option>
			<?php
endforeach;
		?>
</select></p>
		<?php foreach ( Editorial_Scout_Drafting::sources( $c ) as $index => $source ) : ?>
		<p><a href="<?php echo esc_url( $source->article_url, array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer external">
			<?php
			echo esc_html( $source->name ? $source->name : __( 'Original source', 'editorial-scout' ) );
			?>
<span class="screen-reader-text">
			<?php
			esc_html_e( '(opens in a new tab)', 'editorial-scout' );
			?>
</span></a></p>
		<p><label for="writing-note-<?php echo esc_attr( $index ); ?>">
			<?php
			/* translators: %d: source number. */ echo esc_html( sprintf( __( 'Permitted material for source %d', 'editorial-scout' ), $index + 1 ) );
			?>
</label><textarea class="large-text" id="writing-note-<?php echo esc_attr( $index ); ?>" name="notes[<?php echo esc_attr( $index ); ?>]" maxlength="600" rows="3">
			<?php
			echo esc_textarea( array_column( $job['context']['payload']['sources'] ?? array(), 'material', 'id' )[ $index + 1 ] ?? '' );
			?>
</textarea></p>
			<?php
		endforeach;
		?>
		<p><label><input type="checkbox" name="permitted" value="1" required>
		<?php
		esc_html_e( 'I have permission to use these short excerpts or original research notes and have respected source usage and access restrictions.', 'editorial-scout' );
		?>
</label></p>
		<button class="button" type="submit">
		<?php
		esc_html_e( 'Prepare writing request', 'editorial-scout' );
		?>
</button></form>
		<?php
	}
	private static function result( $job ) {
		$result = $job['context']['result'];
		?>
		<h3>
		<?php
		esc_html_e( 'Generated article for review', 'editorial-scout' );
		?>
</h3>
		<p><strong>
		<?php
		esc_html_e( 'Needs fact-checking', 'editorial-scout' );
		?>
</strong> — 
		<?php
		esc_html_e( 'AI output is unverified. Review sources, claims, wording and rights before using it.', 'editorial-scout' );
		?>
</p>
		<?php
		if ( 'research_incomplete' === $result['status'] ) :
			?>
			<p role="status">
			<?php
			echo esc_html( self::message( 'writing_inadequate' ) );
			?>
</p><?php else : ?>
		<article class="scout-offer"><h4>
		<?php
		echo esc_html( $result['headline'] );
		?>
</h4><p>
		<?php
		echo esc_html( $result['introduction'] );
		?>
</p>
			<?php
			foreach ( $result['sections'] as $section ) :
				?>
				<h5>
				<?php
				echo esc_html( $section['heading'] );
				?>
</h5><p>
				<?php
				echo esc_html( $section['text'] );
				?>
</p>
				<?php
endforeach;
			?>
		<p>
		<?php
		esc_html_e( 'Suggested excerpt:', 'editorial-scout' );
		?>
		<?php
			echo esc_html( $result['excerpt'] );
		?>
</p></article>
		<?php
		endif;
	?>
		<details open><summary>
		<?php
		esc_html_e( 'Source references and verification notes (not article content)', 'editorial-scout' );
		?>
</summary><ul>
		<?php
		foreach ( $job['context']['payload']['sources'] as $source ) :
			?>
			<li>
			<?php
			echo esc_html( $source['id'] . ': ' . $source['name'] );
			?>
— <a href="<?php echo esc_url( $source['url'], array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer external">
			<?php
			esc_html_e( 'Source material (opens in a new tab)', 'editorial-scout' );
			?>
</a></li>
			<?php
endforeach;
		?>
</ul><ul>
		<?php
		foreach ( $result['notes'] as $note ) :
			?>
			<li>
			<?php
			echo esc_html( self::kinds()[ $note['kind'] ] . ': ' . $note['text'] . ' (' . implode( ', ', $note['sources'] ) . ')' );
			?>
</li>
			<?php
endforeach;
		?>
</ul>
		<p>
		<?php
		esc_html_e( 'Suggested tags (not applied):', 'editorial-scout' );
		?>
		<?php
		echo esc_html( implode( ', ', $result['tags'] ) );
		?>
</p></details>
		<?php
	}
	private static function kinds() {
		return array(
			'supported'   => __( 'Supported by supplied material, not independently verified', 'editorial-scout' ),
			'verify'      => __( 'Requires independent verification', 'editorial-scout' ),
			'opinion'     => __( 'Opinion or interpretation', 'editorial-scout' ),
			'unavailable' => __( 'Information unavailable', 'editorial-scout' ),
		);
	}
}
