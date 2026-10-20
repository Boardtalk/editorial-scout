<?php
/**
 * Optional provider connection and per-analysis privacy confirmation.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_OpenAI_Screen {
	private static function begin( $mode ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="editorial_scout_intelligence"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '">';
		wp_nonce_field( 'editorial_scout_intelligence' );
	}
	public static function mutate( $mode, &$lock ) {
		if ( 'ai_configure' === $mode ) {
			return Editorial_Scout_OpenAI::configure( '1' === Editorial_Scout_Admin::post_text( 'enabled' ), Editorial_Scout_Admin::post_text( 'daily' ), Editorial_Scout_Admin::post_text( 'monthly' ), Editorial_Scout_Admin::post_text( 'input_price' ), Editorial_Scout_Admin::post_text( 'output_price' ), Editorial_Scout_Admin::post_text( 'price_date' ) );
		}
		if ( 'ai_disconnect' === $mode ) {
			return Editorial_Scout_OpenAI::disconnect();
		}
		if ( 'ai_test' === $mode ) {
			return Editorial_Scout_OpenAI::test_connection( '1' === Editorial_Scout_Admin::post_text( 'test_consent' ), $lock );
		}
		if ( 'ai_prepare' === $mode ) {
			return Editorial_Scout_OpenAI::prepare();
		}
		if ( 'ai_analyze' === $mode ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_gate ran; scalars converted to integers, membership validated server-side.
			$ids     = isset( $_POST['sample_posts'] ) && is_array( $_POST['sample_posts'] ) ? array_map( 'absint', array_filter( wp_unslash( $_POST['sample_posts'] ), 'is_scalar' ) ) : array();
			$consent = '1' === Editorial_Scout_Admin::post_text( 'external_consent' );
			return Editorial_Scout_OpenAI::analyze( Editorial_Scout_Admin::post_text( 'preview_token' ), $ids, $consent, $lock );
		}
		return new WP_Error( 'ai_settings' );
	}
	public static function render() {
		$settings = Editorial_Scout_OpenAI::settings();
		$usage    = Editorial_Scout_OpenAI::usage();
		?>
		<h2><?php esc_html_e( 'AI website understanding', 'editorial-scout' ); ?></h2>
		<p><?php esc_html_e( 'Optional OpenAI semantic analysis proposes subjects, sections, tone and grounded readership or geography. It can make mistakes. You review and edit every proposal before saving a new profile. Local keyword analysis remains available; configuring a key grants no content-transmission consent.', 'editorial-scout' ); ?></p>
		<p><?php echo esc_html( $settings['enabled'] ? __( 'External execution is enabled for explicit administrator requests only.', 'editorial-scout' ) : __( 'External execution is off. No website content is transmitted.', 'editorial-scout' ) ); ?></p>
		<details><summary><?php esc_html_e( 'Provider connection and budget settings', 'editorial-scout' ); ?></summary>
		<p><?php esc_html_e( 'Ask your website administrator to define EDITORIAL_SCOUT_OPENAI_API_KEY in wp-config.php using a protected server environment secret. Do not paste a key into this screen or store it in WordPress options. Remove the server secret to revoke it completely. Disconnection disables this plugin but does not revoke the key at OpenAI.', 'editorial-scout' ); ?></p>
		<p><?php echo esc_html( Editorial_Scout_OpenAI::configured() ? __( 'A server-configured credential is available.', 'editorial-scout' ) : __( 'No valid server-configured credential is available.', 'editorial-scout' ) ); ?> <?php echo esc_html( Editorial_Scout_OpenAI::MODEL ); ?></p>
		<?php self::begin( 'ai_configure' ); ?>
		<p><label><input type="checkbox" name="enabled" value="1" <?php checked( (bool) $settings['enabled'] ); ?>><?php esc_html_e( 'Enable optional external execution', 'editorial-scout' ); ?></label></p>
		<?php
		foreach ( array(
			'daily'        => __( 'Daily reserved-token ceiling (UTC)', 'editorial-scout' ),
			'monthly'      => __( 'Monthly reserved-token ceiling (UTC)', 'editorial-scout' ),
			'input_price'  => __( 'Estimated USD per million input tokens', 'editorial-scout' ),
			'output_price' => __( 'Estimated USD per million output tokens', 'editorial-scout' ),
			'price_date'   => __( 'Pricing assumption date', 'editorial-scout' ),
		) as $key => $label ) :
			?>
		<p><label for="ai-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label> <input class="regular-text" id="ai-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" required <?php echo 'price_date' === $key ? 'type="date"' : 'type="number" min="0" step="any"'; ?>></p>
		<?php endforeach; ?>
		<p><?php esc_html_e( 'Prices are editable assumptions, not live quotes. Check OpenAI pricing and set provider-side project budgets too. Token reservations are conservative and not refunded after failed or interrupted requests. Billing may differ: these controls cannot guarantee zero overrun or limit other software using the key.', 'editorial-scout' ); ?></p>
		<?php submit_button( __( 'Save AI connection settings', 'editorial-scout' ), 'secondary' ); ?></form>
		<?php self::begin( 'ai_test' ); ?>
		<p><label><input type="checkbox" name="test_consent" value="1" required><?php esc_html_e( 'Send a fixed connection-test message to OpenAI with no website content. This is a billable API request.', 'editorial-scout' ); ?></label></p>
		<?php $estimate = Editorial_Scout_OpenAI::estimate( array() ); ?>
		<p><?php /* translators: 1: token allowance, 2: estimated USD cost. */ echo esc_html( sprintf( __( 'Connection-test reservation: %1$d tokens; estimated upper allowance USD %2$s under the pricing assumptions above.', 'editorial-scout' ), $estimate['reserved'], number_format( $estimate['cost'], 4 ) ) ); ?></p>
		<?php submit_button( __( 'Test OpenAI connection', 'editorial-scout' ), 'secondary' ); ?></form>
		<?php
		self::begin( 'ai_disconnect' );
		submit_button( __( 'Disconnect OpenAI', 'editorial-scout' ), 'secondary' );
		?>
		</form>
		</details>
		<p><?php /* translators: 1: daily reservations, 2: monthly reservations, 3: actual provider-reported tokens. */ echo esc_html( sprintf( __( 'Reserved tokens today: %1$d; this month: %2$d. Provider-reported successful response tokens this month: %3$d.', 'editorial-scout' ), $usage['daily'], $usage['monthly'], $usage['actual'] ) ); ?></p>
		<p><?php esc_html_e( 'Last request status:', 'editorial-scout' ); ?>
		<?php
		echo esc_html(
			array(
				'reserved'       => __( 'Reserved; completion unconfirmed', 'editorial-scout' ),
				'failed'         => __( 'Failed; allowance retained', 'editorial-scout' ),
				'invalid_output' => __( 'Invalid model output; allowance retained', 'editorial-scout' ),
				'completed'      => __( 'Completed', 'editorial-scout' ),
			)[ $usage['status'] ?? '' ] ?? __( 'No request recorded', 'editorial-scout' )
		);
		?>
			</p>
		<?php
		if ( Editorial_Scout_Intelligence::proposal() ) {
			self::begin( 'ai_prepare' );
			echo '<p>' . esc_html__( 'Use the local sample above as a starting point. Previewing sends nothing. Adjust dates or excluded categories by running local analysis again.', 'editorial-scout' ) . '</p>';
			submit_button( __( 'Preview sample for AI analysis', 'editorial-scout' ), 'secondary' );
			echo '</form>';
		}
		$preview = Editorial_Scout_OpenAI::preview();
		if ( ! $preview ) {
			return;
		}
		$estimate = Editorial_Scout_OpenAI::estimate( $preview['payload'] );
		self::begin( 'ai_analyze' );
		?>
		<h3><?php esc_html_e( 'Review exactly what will be sent', 'editorial-scout' ); ?></h3>
		<p><?php esc_html_e( 'Recipient: OpenAI, via its HTTPS Responses API. OpenAI also receives your server IP and a generic plugin user agent. Selected public post IDs, titles, publication dates, category IDs and the excerpts shown below will be sent, together with the native category structure listed below. No author names, URLs, full bodies, drafts, private posts, protected posts or feed articles are included. Public text can still contain sensitive personal information. Inspect every excerpt and uncheck any unsuitable post. Automated personal-data removal is not provided.', 'editorial-scout' ); ?></p>
		<p><a href="https://openai.com/policies/privacy-policy/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'OpenAI privacy policy (opens in a new tab)', 'editorial-scout' ); ?></a> <?php esc_html_e( 'store:false is requested; provider abuse-monitoring retention and account policies may still apply. This is not a zero-retention guarantee.', 'editorial-scout' ); ?></p>
		<input type="hidden" name="preview_token" value="<?php echo esc_attr( $preview['token'] ); ?>">
		<fieldset><legend><?php esc_html_e( 'Approved sample posts: uncheck to exclude', 'editorial-scout' ); ?></legend>
		<?php foreach ( $preview['payload']['posts'] as $post ) : ?>
		<p><label><input type="checkbox" name="sample_posts[]" value="<?php echo esc_attr( $post['id'] ); ?>" checked> <?php echo esc_html( $post['title'] ); ?></label></p><details><summary><?php esc_html_e( 'Preview excerpt and post metadata', 'editorial-scout' ); ?>: <?php echo esc_html( $post['title'] ); ?></summary><p><?php echo esc_html( $post['date'] . ' UTC; ' . implode( ', ', $post['categories'] ) ); ?></p><p><?php echo esc_html( $post['excerpt'] ); ?></p><a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'post'   => $post['id'],
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				)
			);
			?>
																		"><?php esc_html_e( 'Edit original post', 'editorial-scout' ); ?></a></details>
		<?php endforeach; ?></fieldset>
		<details><summary><?php esc_html_e( 'Category structure included in transmission', 'editorial-scout' ); ?></summary><ul>
		<?php foreach ( $preview['payload']['categories'] as $category ) : ?>
		<li><?php echo esc_html( $category['id'] . ': ' . $category['name'] . ' (' . $category['parent'] . ')' ); ?></li>
		<?php endforeach; ?></ul></details>
		<p><?php /* translators: 1: input estimate, 2: maximum output, 3: cost estimate, 4: pricing date. */ echo esc_html( sprintf( __( 'Conservative allowance: %1$d input tokens plus up to %2$d output tokens; approximately USD %3$s using pricing assumptions dated %4$s. Excluding posts lowers the reservation. Actual tokenization and billing may differ.', 'editorial-scout' ), $estimate['input'], $estimate['output'], number_format( $estimate['cost'], 4 ), $settings['price_date'] ) ); ?></p>
		<p><label><input type="checkbox" name="external_consent" value="1" required><?php esc_html_e( 'I reviewed this material and consent to this one billable OpenAI analysis of the checked posts and displayed category structure.', 'editorial-scout' ); ?></label></p>
		<?php submit_button( __( 'Analyze approved sample with OpenAI', 'editorial-scout' ) ); ?></form>
		<?php
	}
}
