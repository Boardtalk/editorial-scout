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
	public static function message( $code, $stage = '' ) {
		$stage    = Editorial_Scout_Drafting::failure_stage( new WP_Error( 'diagnostic', '', array( 'stage' => $stage ) ) );
		$messages = array(
			'ai_consent'          => __( 'Review the prepared article request and explicitly approve sending exactly that material. Nothing was sent by this attempt. A used or changed request needs a fresh preview.', 'editorial-scout' ),
			'ai_output'           => __( 'The previous article-writing response did not pass validation. No WordPress draft was created. Review the research before preparing another request; a new writing request requires fresh consent and may incur a charge.', 'editorial-scout' ),
			'writing_output'      => __( 'The article-writing response did not pass validation. No WordPress draft was created. Review the research before preparing another request; a new writing request requires fresh consent and may incur a charge.', 'editorial-scout' ),
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
		$message  = $messages[ $code ] ?? Editorial_Scout_Dashboard::explanation( $code );
		if ( 'ai_request' === $code ) {
			$transport = array(
				'transport'            => __( 'The connection to OpenAI failed or timed out. Whether OpenAI processed the request is uncertain.', 'editorial-scout' ),
				'rate_limit'           => __( 'OpenAI rejected the request because of a rate or account limit.', 'editorial-scout' ),
				'authentication'       => __( 'OpenAI did not accept the configured account credentials. Ask your website administrator to check the configuration.', 'editorial-scout' ),
				'provider_unavailable' => __( 'OpenAI reported a service error. Whether the request was processed is uncertain.', 'editorial-scout' ),
				'provider_rejected'    => __( 'OpenAI rejected the request. Ask your website administrator to review the integration configuration.', 'editorial-scout' ),
			);
			if ( isset( $transport[ $stage ] ) ) {
				$message = $transport[ $stage ] . ' ' . __( 'No WordPress draft was created. No automatic retry occurs; the reserved allowance is retained.', 'editorial-scout' );
			}
		}
		if ( in_array( $code, array( 'writing_output', 'ai_output' ), true ) ) {
			$stages  = array(
				'response_envelope'  => __( 'OpenAI returned an unsupported response format.', 'editorial-scout' ),
				'json'               => __( 'OpenAI returned article data that could not be read as JSON.', 'editorial-scout' ),
				'secret'             => __( 'The response failed the sensitive-data safety check.', 'editorial-scout' ),
				'structure'          => __( 'OpenAI returned article fields or counts that did not match the required format.', 'editorial-scout' ),
				'field_text'         => __( 'The headline, introduction or excerpt exceeded a limit or contained unsafe text.', 'editorial-scout' ),
				'sections'           => __( 'The response did not include the required article sections.', 'editorial-scout' ),
				'section_text'       => __( 'An article section exceeded a limit or contained unsafe text.', 'editorial-scout' ),
				'claims'             => __( 'OpenAI returned verification notes that did not match the required format.', 'editorial-scout' ),
				'source_reference'   => __( 'The article referred to an unknown source.', 'editorial-scout' ),
				'claim_coverage'     => __( 'The response did not separate supported claims from missing verification adequately.', 'editorial-scout' ),
				'tags'               => __( 'A suggested tag exceeded a limit or contained unsafe text.', 'editorial-scout' ),
				'unsupported_number' => __( 'The article included numerical claims absent from the supplied research.', 'editorial-scout' ),
				'copying'            => __( 'The article repeated too much of the source wording.', 'editorial-scout' ),
				'quotation'          => __( 'A public quotation was incomplete or absent from the supplied research.', 'editorial-scout' ),
				'incomplete'         => __( 'OpenAI did not complete the response. Partial content was not accepted.', 'editorial-scout' ),
				'output_limit'       => __( 'OpenAI reached the output-token limit before completing the article. Shorten the requested article before preparing a new request.', 'editorial-scout' ),
				'refusal'            => __( 'OpenAI declined this request. Review the research and writing requirements.', 'editorial-scout' ),
				'response_usage'     => __( 'OpenAI returned missing or inconsistent usage information. The reserved allowance is retained.', 'editorial-scout' ),
				'provider_status'    => __( 'OpenAI did not return a completed response. No automatic retry will occur.', 'editorial-scout' ),
			);
			$message = isset( $stages[ $stage ] ) ? $stages[ $stage ] . ' ' . __( 'No WordPress draft was created. A new writing request needs fresh consent and may incur a charge.', 'editorial-scout' ) : $message;
		}
		return $message;
	}
	/** A shareable fixed-field summary, never a journal dump or request token. */
	public static function diagnostic_summary( $id ) {
		if ( ! Editorial_Scout_Drafting::allowed() ) {
			return '';
		}
		$job = Editorial_Scout_Drafting::job( $id );
		if ( ! $job || get_current_user_id() !== (int) ( $job['context']['user'] ?? 0 ) || ! in_array( $job['state'], array( 'failed', 'sending' ), true ) ) {
			return '';
		}
		$stage = Editorial_Scout_Drafting::failure_stage( new WP_Error( 'diagnostic', '', array( 'stage' => $job['context']['error_stage'] ?? '' ) ) );
		return 'Editorial Scout: ' . EDITORIAL_SCOUT_VERSION . "\n" .
			'Model: ' . Editorial_Scout_OpenAI::MODEL . "\n" .
			'Writing state: ' . $job['state'] . "\n" .
			'Validation check: ' . ( $stage ? $stage : 'not_recorded' ) . "\n" .
			'Automatic paid retry: disabled';
	}
	private static function diagnostic( $id ) {
		$summary = self::diagnostic_summary( $id );
		if ( ! $summary ) {
			return;
		}
		?>
		<details class="scout-writing-diagnostic"><summary><?php esc_html_e( 'Technical details', 'editorial-scout' ); ?></summary>
		<p><?php esc_html_e( 'This safe summary contains fixed diagnostic labels only. It does not send or retry a request. If a check was not recorded, the earlier cause cannot be recovered here.', 'editorial-scout' ); ?></p>
		<label for="scout-writing-diagnostic"><?php esc_html_e( 'Shareable writing diagnostic', 'editorial-scout' ); ?></label>
		<textarea id="scout-writing-diagnostic" class="large-text code" rows="6" readonly><?php echo esc_textarea( $summary ); ?></textarea>
		<button type="button" class="button" data-scout-copy="scout-writing-diagnostic"><?php esc_html_e( 'Copy diagnostic summary', 'editorial-scout' ); ?></button>
		<p><?php esc_html_e( 'If copying is unavailable, select the summary and copy it manually. Share only this summary with your developer.', 'editorial-scout' ); ?></p>
		</details>
		<?php
	}

	public static function notice_key( $id ) {
		return 'editorial_scout_writing_notice_' . get_current_user_id() . '_' . absint( $id );
	}
	private static function retry_key( $id ) {
		return 'editorial_scout_writing_form_' . get_current_user_id() . '_' . absint( $id );
	}
	/** Ephemeral correction form, never a journal, permission or paid consent. */
	public static function remember( $id, $input, $failed, $code = 'writing_material' ) {
		if ( ! Editorial_Scout_Drafting::allowed() ) {
			return;
		}
		delete_transient( self::retry_key( $id ) );
		$c   = Editorial_Scout_Drafting::commission( $id );
		$job = Editorial_Scout_Drafting::job( $id );
		if ( ! $failed || ! is_array( $input ) || ! $c || 'waiting' !== $c->status || ( $job && ( get_current_user_id() !== (int) $job['context']['user'] || ! in_array( $job['state'], array( 'prepared', 'failed', 'incomplete' ), true ) ) ) ) {
			return;
		}
		$sources  = Editorial_Scout_Drafting::sources( $c );
		$notes    = is_array( $input['notes'] ?? null ) ? $input['notes'] : array();
		$value    = array(
			'sources' => hash( 'sha256', wp_json_encode( $sources ) ),
			'journal' => hash( 'sha256', wp_json_encode( $job ) ),
			'notes'   => array(),
			'error'   => in_array( $code, array( 'writing_material', 'writing_inadequate', 'writing_permission', 'writing_state', 'storage_failed', 'busy', 'writing_interrupted' ), true ) ? $code : 'writing_material',
		);
		$profiles = Editorial_Scout_Intelligence::state()['profiles'] ?? array();
		if ( is_string( $input['profile'] ?? null ) && isset( $profiles[ $input['profile'] ] ) ) {
			$value['profile'] = $input['profile'];
		}
		$author   = is_scalar( $input['author'] ?? null ) ? absint( $input['author'] ) : 0;
		$category = is_scalar( $input['category'] ?? null ) ? absint( $input['category'] ) : 0;
		if ( isset( Editorial_Scout_Drafting::authors()[ $author ] ) ) {
			$value['author'] = $author;
		}
		if ( get_term( $category, 'category' ) instanceof WP_Term ) {
			$value['category'] = $category;
		}
		foreach ( array_slice( $sources, 0, 4 ) as $index => $source ) {
			$text = $notes[ $index ] ?? null;
			if ( is_string( $text ) && mb_strlen( $text ) <= 600 && ! Editorial_Scout_OpenAI::contains_secret( $text ) ) {
				$value['notes'][ $index ] = sanitize_textarea_field( $text );
			}
		}
		set_transient( self::retry_key( $id ), $value, HOUR_IN_SECONDS );
	}
	public static function retry( $id ) {
		if ( ! Editorial_Scout_Drafting::allowed() ) {
			return array();
		}
		$value = get_transient( self::retry_key( $id ) );
		$c     = Editorial_Scout_Drafting::commission( $id );
		$job   = Editorial_Scout_Drafting::job( $id );
		if ( ! is_array( $value ) || ! is_string( $value['journal'] ?? null ) || ! hash_equals( $value['journal'], hash( 'sha256', wp_json_encode( $job ) ) ) || Editorial_Scout_OpenAI::contains_secret( $value ) || ! $c || 'waiting' !== $c->status || ( $job && ( get_current_user_id() !== (int) $job['context']['user'] || ! in_array( $job['state'], array( 'prepared', 'failed', 'incomplete' ), true ) ) ) || ! hash_equals( $value['sources'], hash( 'sha256', wp_json_encode( Editorial_Scout_Drafting::sources( $c ) ) ) ) ) {
			delete_transient( self::retry_key( $id ) );
			return array();
		}
		return $value;
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
		$retry  = self::retry( $id );
		$notice = get_transient( self::notice_key( $id ) );
		delete_transient( self::notice_key( $id ) );
		$stage  = is_array( $notice ) ? ( $notice['stage'] ?? '' ) : '';
		$notice = is_array( $notice ) ? ( $notice['code'] ?? '' ) : '';
		if ( ! $notice && $retry ) {
			$notice = $retry['error'];
		}
		if ( is_string( $notice ) && $notice ) {
			echo '<div class="notice notice-info inline" role="status"><p>' . esc_html( self::message( $notice, $stage ) ) . '</p></div>';
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
		if ( $job && 'failed' === $job['state'] && ! $notice && ! $retry ) {
			echo '<p role="status">' . esc_html( self::message( $job['context']['error'] ?? 'ai_request', $job['context']['error_stage'] ?? '' ) ) . '</p>'; }
		self::diagnostic( $id );
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

		if ( $job && 'prepared' === $job['state'] && Editorial_Scout_Drafting::RULES_VERSION !== ( $job['context']['rules'] ?? '' ) ) {
			echo '<p>' . esc_html__( 'The writing rules changed since this preview was prepared. Prepare and review a fresh request below, then give new consent. Nothing has been sent by opening this page.', 'editorial-scout' ) . '</p>';
		}
		if ( $job && 'prepared' === $job['state'] && Editorial_Scout_Drafting::RULES_VERSION === ( $job['context']['rules'] ?? '' ) && ! $retry ) {
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
		$retry    = self::retry( $c->id );
		$selected = $retry['profile'] ?? ( $job['context']['profile'] ?? $c->profile_id );
		$profile  = $profiles[ $selected ] ?? array();
		$terms    = Editorial_Scout_Intelligence::terms();
		$author   = $retry['author'] ?? ( $job['context']['author'] ?? get_current_user_id() );
		$category = $retry['category'] ?? ( $job['context']['payload']['category']['id'] ?? ( $profile['default_category'] ?? 0 ) );
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
		if ( $retry ) {
			echo '<p>' . esc_html__( 'Safe form entries are retained for one hour for this administrator and commission. Review the entries and confirm source permission again. Any earlier writing preview remains unchanged and cannot be sent from this correction form.', 'editorial-scout' ) . '</p>';
		}
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
</label> <select id="writing-author" name="author" required><option value=""><?php esc_html_e( 'Choose an author', 'editorial-scout' ); ?></option>
		<?php
		foreach ( Editorial_Scout_Drafting::authors() as $uid => $name ) :
			?>
			<option value="<?php echo esc_attr( $uid ); ?>" <?php selected( $author, $uid ); ?>>
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
			selected( $category, $term->term_id );
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
</label><textarea class="large-text" id="writing-note-<?php echo esc_attr( $index ); ?>" name="notes[<?php echo esc_attr( $index ); ?>]" maxlength="600" rows="3"><?php echo esc_textarea( $retry ? ( $retry['notes'][ $index ] ?? '' ) : ( array_column( $job['context']['payload']['sources'] ?? array(), 'material', 'id' )[ $index + 1 ] ?? '' ) ); ?></textarea></p>
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
