<?php
/**
 * Local editorial assistance screens and authorized mutation routing.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Intelligence_Screen {
	private static function hidden( $key, $value ) {
		echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
	}
	private static function begin( $mode ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::hidden( 'action', 'editorial_scout_intelligence' );
		self::hidden( 'mode', $mode );
		wp_nonce_field( 'editorial_scout_intelligence' );
	}
	private static function posted_ids( $key ) {
		return isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? array_map( 'absint', array_filter( wp_unslash( $_POST[ $key ] ), 'is_scalar' ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Called only after post_gate.
	}
	public static function mutate( &$lock ) {
		$mode = Editorial_Scout_Admin::post_text( 'mode' );
		$id   = Editorial_Scout_Admin::post_text( 'profile_id' );
		if ( str_starts_with( $mode, 'ai_' ) ) {
			return Editorial_Scout_OpenAI_Screen::mutate( $mode, $lock );
		}
		if ( 'save_profile' === $mode ) {
			$token = Editorial_Scout_Admin::post_text( 'learn_token' );
			if ( $token ) {
				$draft = Editorial_Scout_Intelligence::proposal();
				if ( ! $draft || ! hash_equals( $draft['token'], $token ) ) {
					return new WP_Error( 'stale_proposal' );
				}
			}
			$data = array();
			foreach ( Editorial_Scout_Intelligence::fields() as $key => $label ) {
				$data[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Called only after post_gate.
			}
			$data['categories']       = self::posted_ids( 'categories' );
			$data['default_category'] = Editorial_Scout_Admin::post_text( 'default_category' );
			$make_default             = '1' === Editorial_Scout_Admin::post_text( 'is_default' );
			$result                   = Editorial_Scout_Intelligence::save( $id, $data, $make_default );
			if ( ! is_wp_error( $result ) && $token ) {
				delete_user_meta( get_current_user_id(), Editorial_Scout_Intelligence::DRAFT );
			}
			return $result;
		}
		if ( 'delete_profile' === $mode ) {
			return Editorial_Scout_Intelligence::delete( $id );
		}
		if ( 'learn' === $mode ) {
			if ( '1' !== Editorial_Scout_Admin::post_text( 'local_consent' ) ) {
				return new WP_Error( 'consent_required' );
			}
			return Editorial_Scout_Intelligence::learn( Editorial_Scout_Admin::post_text( 'from' ), Editorial_Scout_Admin::post_text( 'to' ), absint( Editorial_Scout_Admin::post_text( 'size' ) ), self::posted_ids( 'excluded_categories' ), $lock );
		}
		if ( 'assess' === $mode ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict hash validation in assess; post_gate ran first.
			$hashes = isset( $_POST['stories'] ) && is_array( $_POST['stories'] ) ? wp_unslash( $_POST['stories'] ) : array();
			return Editorial_Scout_Intelligence::assess( $hashes, $id, $lock );
		}
		if ( 'approve_category' === $mode && current_user_can( 'manage_categories' ) ) {
			return Editorial_Scout_Intelligence::approve_category( Editorial_Scout_Admin::post_text( 'assessment_key' ), Editorial_Scout_Admin::post_text( 'category_name' ), Editorial_Scout_Admin::post_text( 'category_slug' ), Editorial_Scout_Admin::post_text( 'category_description' ), absint( Editorial_Scout_Admin::post_text( 'category_parent' ) ) );
		}
		return new WP_Error( 'invalid_profile' );
	}
	private static function category_select( $key, $selected, $multiple = false ) {
		echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key . ( $multiple ? '[]' : '' ) ) . '" ' . ( $multiple ? 'multiple size="4"' : '' ) . '>';
		if ( ! $multiple ) {
			echo '<option value="0">' . esc_html__( 'None', 'editorial-scout' ) . '</option>';
		}
		foreach ( array_slice( Editorial_Scout_Intelligence::terms(), 0, 200 ) as $term ) {
			echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( in_array( $term->term_id, array_map( 'intval', (array) $selected ), true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
	}
	private static function starters() {
		return array(
			'briefing'  => array(
				'audience'     => __( 'Readers seeking concise, well-sourced updates', 'editorial-scout' ),
				'tone'         => __( 'Clear, respectful and factual', 'editorial-scout' ),
				'instructions' => __( 'Verify claims, link evidence, state uncertainty and require human review.', 'editorial-scout' ),
			),
			'explainer' => array(
				'audience'     => __( 'Readers seeking context and accessible explanations', 'editorial-scout' ),
				'tone'         => __( 'Patient, plain-language and balanced', 'editorial-scout' ),
				'instructions' => __( 'Define unfamiliar terms, distinguish evidence from opinion and explain limitations.', 'editorial-scout' ),
			),
			'community' => array(
				'audience'     => __( 'Readers seeking practical information affecting their community', 'editorial-scout' ),
				'tone'         => __( 'Helpful, inclusive and precise', 'editorial-scout' ),
				'instructions' => __( 'Check local relevance, verify dates and eligibility, avoid assumptions about readers.', 'editorial-scout' ),
			),
		);
	}

	public static function render() {
		$state           = Editorial_Scout_Intelligence::state();
		$profiles        = $state['profiles'] ?? array();
		$default_profile = $state['default'] ?? '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$id              = isset( $_GET['profile'] ) && is_string( $_GET['profile'] ) ? sanitize_text_field( wp_unslash( $_GET['profile'] ) ) : '';
		$data            = $profiles[ $id ] ?? array();
		$draft           = Editorial_Scout_Intelligence::proposal();
		$evidence_titles = wp_list_pluck( $draft['evidence'] ?? array(), 'title', 'id' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Selecting a local proposal is read-only; save is separately gated.
		$review = isset( $_GET['review'] ) && '1' === $_GET['review'] && $draft;
		if ( $review ) {
			$id   = '';
			$data = $draft['profile'];
		}
		?>
		<h2><?php esc_html_e( 'Editorial profiles', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'An editorial profile describes what your website covers and helps you review relevant stories. Start with local keyword analysis or optionally request AI website understanding, then edit and save a new profile. Up to ten shared profiles. Native WordPress article categories below are separate from source-monitoring categories. Audience, geography, tone and instructions describe future editorial work; local matching uses only relevant/excluded topic phrases.', 'editorial-scout' ); ?></p>
		<ul>
		<?php
		foreach ( $profiles as $key => $profile ) :
			?>
			<li><a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'profiles', array( 'profile' => $key ) ) ); ?>"><?php echo esc_html( $profile['name'] ); ?></a> <?php echo ( $state['default'] ?? '' ) === $key ? esc_html__( '(default)', 'editorial-scout' ) : ''; ?></li><?php endforeach; ?></ul>
		<p><a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'profiles', array( 'new_profile' => 1 ) ) ); ?>"><?php esc_html_e( 'New profile', 'editorial-scout' ); ?></a></p>
		<?php
		if ( ! $data ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template selection.
			$template = isset( $_GET['starter'] ) && is_string( $_GET['starter'] ) ? sanitize_key( wp_unslash( $_GET['starter'] ) ) : 'briefing';
			$data     = self::starters()[ $template ] ?? self::starters()['briefing'];
		}
		?>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation only.
		$editing = $id || $review || ! $profiles || isset( $_GET['starter'] ) || isset( $_GET['new_profile'] );
		?>
		<details <?php echo $editing ? 'open' : ''; ?>><summary><?php esc_html_e( 'Create or edit a profile', 'editorial-scout' ); ?></summary>
		<p><?php esc_html_e( 'General-purpose starters; replace the audience and supply your own topics:', 'editorial-scout' ); ?>
		<?php
		foreach ( array(
			'briefing'  => __( 'Briefing starter', 'editorial-scout' ),
			'explainer' => __( 'Explainer starter', 'editorial-scout' ),
			'community' => __( 'Community starter', 'editorial-scout' ),
		) as $starter => $label ) :
			?>
		<a href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'profiles', array( 'starter' => $starter ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?></p>
		<?php
		self::begin( 'save_profile' );
		self::hidden( 'profile_id', $id );
		self::hidden( 'learn_token', $review ? $draft['token'] : '' );
		?>
		<?php
		if ( $review ) :
			?>
			<p><strong><?php esc_html_e( 'Reviewing an inactive learning proposal. Saving is explicit approval; edit every inferred field first.', 'editorial-scout' ); ?></strong></p><?php endif; ?>
		<?php foreach ( Editorial_Scout_Intelligence::fields() as $key => $label ) : ?>
		<p><label for="profile-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></p>
		<textarea class="large-text" rows="2" id="profile-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" maxlength="
			<?php
			echo esc_attr(
				array(
					'name'         => 80,
					'audience'     => 500,
					'topics'       => 1000,
					'excluded'     => 1000,
					'geography'    => 200,
					'tone'         => 200,
					'instructions' => 2000,
				)[ $key ]
			);
			?>
															" <?php echo 'name' === $key ? 'required' : ''; ?>><?php echo esc_textarea( $data[ $key ] ?? '' ); ?></textarea>
		<?php endforeach; ?>
		<p><label for="categories"><?php esc_html_e( 'Preferred WordPress categories (up to ten)', 'editorial-scout' ); ?></label></p><?php self::category_select( 'categories', $data['categories'] ?? array(), true ); ?>
		<p><label for="default_category"><?php esc_html_e( 'Default WordPress category', 'editorial-scout' ); ?></label></p><?php self::category_select( 'default_category', array( $data['default_category'] ?? 0 ) ); ?>
		<p><label><input type="checkbox" name="is_default" value="1" <?php checked( $id && $id === $default_profile ); ?>><?php esc_html_e( 'Use as default editorial profile', 'editorial-scout' ); ?></label></p>
		<?php submit_button( __( 'Save reviewed profile', 'editorial-scout' ) ); ?></form>
		<?php
		if ( $id ) {
			self::begin( 'delete_profile' );
			self::hidden( 'profile_id', $id );
			submit_button( __( 'Delete profile', 'editorial-scout' ), 'secondary' );
			echo '</form>'; }
		?>
		</details>
		<h2><?php esc_html_e( 'Learn My Website: Local keyword analysis', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Local analysis only. Samples published, non-password-protected standard posts across eight UTC date windows and up to four category strata. At most 32 posts, 600-character excerpts each, and 42 ID queries. Sparse windows are filled from both ends of the approved date range. Sample size is a ceiling; fewer eligible posts may exist. No rendering, external transmission or post edits occur.', 'editorial-scout' ); ?></p>
		<?php self::begin( 'learn' ); ?>
		<details><summary><?php esc_html_e( 'Advanced sample controls: dates, size and excluded categories', 'editorial-scout' ); ?></summary>
		<p><label for="learn-from"><?php esc_html_e( 'Published from (UTC)', 'editorial-scout' ); ?></label> <input id="learn-from" type="date" name="from" required value="<?php echo esc_attr( gmdate( 'Y-m-d', time() - YEAR_IN_SECONDS ) ); ?>"></p>
		<p><label for="learn-to"><?php esc_html_e( 'Published to (UTC)', 'editorial-scout' ); ?></label> <input id="learn-to" type="date" name="to" required value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></p>
		<p><label for="learn-size"><?php esc_html_e( 'Maximum sample size', 'editorial-scout' ); ?></label> <input id="learn-size" type="number" name="size" min="1" max="32" value="16" required></p>
		<p><label for="excluded_categories"><?php esc_html_e( 'Exclude WordPress categories and descendants (up to ten selections)', 'editorial-scout' ); ?></label></p><?php self::category_select( 'excluded_categories', array(), true ); ?></details>
		<p><label><input name="local_consent" value="1" type="checkbox" required><?php esc_html_e( 'Analyze eligible public posts locally and keep a reviewable proposal for one hour. No excerpts leave this website.', 'editorial-scout' ); ?></label></p>
		<?php submit_button( __( 'Analyze published posts locally', 'editorial-scout' ) ); ?></form>
		<?php if ( $draft ) : ?>
		<h3><?php esc_html_e( 'Inactive proposal and evidence', 'editorial-scout' ); ?></h3>
		<p><?php echo esc_html( $draft['profile']['topics'] ); ?></p>
			<?php if ( ! empty( $draft['claims'] ) ) : ?>
		<p><?php esc_html_e( 'AI proposals are unverified. Evidence links show the sample supporting each claim; they do not prove it. Audience and geography should be blank when unsupported. Review every field before saving.', 'editorial-scout' ); ?></p>
				<?php foreach ( $draft['claims'] as $field => $claim ) : ?>
		<details><summary><?php echo esc_html( Editorial_Scout_Intelligence::fields()[ $field ] ?? __( 'Editorial sections', 'editorial-scout' ) ); ?></summary><p><?php echo esc_html( $claim['text'] ); ?></p><p><?php echo esc_html( $claim['uncertainty'] ); ?></p><ul>
					<?php foreach ( $claim['evidence'] as $post_id ) : ?>
		<li><a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'post'   => $post_id,
									'action' => 'edit',
								),
								admin_url( 'post.php' )
							)
						);
						?>
						"><?php echo esc_html( $evidence_titles[ $post_id ] ?? '' ); ?></a></li>
		<?php endforeach; ?></ul></details>
					<?php
		endforeach; else :
			?>
			<p><?php esc_html_e( 'Confidence: limited, heuristic and not a calibrated probability. These are frequent sample words, not inferred audience facts. Review language, coverage and missing sections. Private or changed evidence invalidates the proposal.', 'editorial-scout' ); ?></p><?php endif; ?>
		<ul>
			<?php
			foreach ( $draft['evidence'] as $evidence ) :
				?>
			<li><a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'post'   => $evidence['id'],
							'action' => 'edit',
						),
						admin_url( 'post.php' )
					)
				);
				?>
			"><?php echo esc_html( $evidence['title'] ); ?></a> <?php echo esc_html( $evidence['date'] ); ?> UTC</li><?php endforeach; ?></ul>
		<p><a class="button" href="<?php echo esc_url( Editorial_Scout_Dashboard::url( 'profiles', array( 'review' => 1 ) ) ); ?>"><?php esc_html_e( 'Review proposed profile', 'editorial-scout' ); ?></a></p>
		<?php endif; ?>
		<?php Editorial_Scout_OpenAI_Screen::render(); ?>
		<p><?php esc_html_e( 'Native WordPress tags available for future assignments:', 'editorial-scout' ); ?> <?php echo esc_html( implode( ', ', array_slice( wp_list_pluck( Editorial_Scout_Intelligence::terms( 'post_tag' ), 'name' ), 0, 20 ) ) ); ?></p>
		<?php self::results(); ?>
		<?php
	}
	public static function assessment_form( $queue ) {
		$state = Editorial_Scout_Intelligence::state();
		if ( empty( $state['profiles'] ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Assess relevance locally', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Select up to five stories. Recommendations use title phrases only; source text is untrusted data, never instructions. Human shortlist/dismiss choices remain unchanged. Results appear under Editorial Profiles.', 'editorial-scout' ); ?></p>
		<?php self::begin( 'assess' ); ?>
		<p><label for="assessment-profile"><?php esc_html_e( 'Editorial profile', 'editorial-scout' ); ?></label> <select id="assessment-profile" name="profile_id">
		<?php
		foreach ( $state['profiles'] as $id => $profile ) :
			?>
			<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $state['default'] ?? '', $id ); ?>><?php echo esc_html( $profile['name'] ); ?></option><?php endforeach; ?></select></p>
		<fieldset><legend><?php esc_html_e( 'Stories to assess (maximum five)', 'editorial-scout' ); ?></legend>
		<?php
		foreach ( array_slice( $queue, 0, 50 ) as $item ) :
			?>
			<p><label><input type="checkbox" name="stories[]" value="<?php echo esc_attr( $item->story_hash ); ?>"> <?php echo esc_html( $item->title ); ?></label></p><?php endforeach; ?></fieldset>
		<?php submit_button( __( 'Assess selected stories locally', 'editorial-scout' ) ); ?></form>
		<?php
	}
	private static function results() {
		$proposed = array();
		?>
		<h2><?php esc_html_e( 'Relevance recommendations', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Local, unverified recommendations; no model probability. Saved results are bounded to 200 entries and 30 days. Stale/deleted-profile results are hidden. Category order is primary then optional secondary; these are suggestions only, never post assignments.', 'editorial-scout' ); ?></p>
		<?php foreach ( array_reverse( array_slice( Editorial_Scout_Intelligence::results(), -10, null, true ), true ) as $key => $row ) : ?>
			<?php
			if ( ! Editorial_Scout_Intelligence::current_result( $row ) ) {
				continue; }
			?>
		<div class="card"><h3><?php echo esc_html( $row['title'] ); ?></h3><p><?php echo esc_html( Editorial_Scout_Intelligence::state()['profiles'][ $row['profile_id'] ]['name'] ); ?>:
			<?php
			echo esc_html(
				array(
					'relevant'   => __( 'Relevant', 'editorial-scout' ),
					'uncertain'  => __( 'Uncertain', 'editorial-scout' ),
					'irrelevant' => __( 'Irrelevant', 'editorial-scout' ),
				)[ $row['recommendation'] ]
			);
			?>
								</p><p><?php echo esc_html( $row['explanation'] ); ?></p><p><?php echo esc_html( implode( ', ', $row['evidence'] ) ); ?></p>
		<p>
			<?php
			foreach ( $row['categories'] as $id ) {
				$term = get_term( $id, 'category' );
				if ( $term instanceof WP_Term ) {
					echo esc_html( $term->name ) . ' '; }
			}
			?>
		</p>
			<?php
			$candidate            = Editorial_Scout_Intelligence::candidate( $row );
			$normalized_candidate = str_replace( '-', '', sanitize_title( $candidate ) );
			foreach ( $proposed as $previous ) {
				if ( $normalized_candidate === $previous || ( strlen( $normalized_candidate ) >= 4 && levenshtein( $normalized_candidate, $previous ) <= 1 ) ) {
					$candidate = '';
				}
			}
			if ( $candidate ) {
				$proposed[] = $normalized_candidate;
			} if ( $candidate ) :
				?>
					<?php
					self::begin( 'approve_category' );
					self::hidden( 'assessment_key', $key );
					?>
		<p><?php esc_html_e( 'Optional new category proposal, justified only by the matched phrase. Check existing categories first. This button explicitly creates a native WordPress category; it creates no post.', 'editorial-scout' ); ?></p>
				<?php
				foreach ( array(
					'category_name'        => __( 'Proposed category name', 'editorial-scout' ),
					'category_slug'        => __( 'Proposed category slug', 'editorial-scout' ),
					'category_description' => __( 'Proposed category description', 'editorial-scout' ),
				) as $field => $label ) :
					?>
		<p><label for="<?php echo esc_attr( $field . $key ); ?>"><?php echo esc_html( $label ); ?></label><input class="regular-text" id="<?php echo esc_attr( $field . $key ); ?>" name="<?php echo esc_attr( $field ); ?>" maxlength="200" value="<?php echo esc_attr( 'category_name' === $field ? $candidate : ( 'category_slug' === $field ? sanitize_title( $candidate ) : sprintf( /* translators: %s: matched editorial topic. */ __( 'Articles matching the reviewed topic: %s', 'editorial-scout' ), $candidate ) ) ); ?>"></p>
		<?php endforeach; ?>
		<p><label for="category_parent<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Optional parent category ID', 'editorial-scout' ); ?></label><input id="category_parent<?php echo esc_attr( $key ); ?>" type="number" name="category_parent" min="0" value="0"></p>
					<?php submit_button( __( 'Approve and create category', 'editorial-scout' ), 'secondary' ); ?></form>
			<?php endif; ?></div>
		<?php endforeach; ?>
		<?php
	}
}
