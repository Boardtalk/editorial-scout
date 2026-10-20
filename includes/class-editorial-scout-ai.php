<?php
/**
 * Provider contract and fail-closed boundary. No external adapter ships in this build.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_AI {
	/** External execution is deliberately unavailable, even if consent is supplied. */
	public static function assess( Editorial_Scout_Provider $provider, $payload, $consent = false ) {
		try {
			$external = $provider->external();
		} catch ( Throwable $error ) {
			return new WP_Error( 'assessment_failed' );
		}
		if ( $external ) {
			return new WP_Error( $consent ? 'provider_unavailable' : 'consent_required' );
		}
		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > 16000 ) {
			return new WP_Error( 'assessment_limit' );
		}
		if ( ! is_array( $payload ) || ! is_string( $payload['title'] ?? null ) || mb_strlen( $payload['title'] ) > 400 || ! is_array( $payload['profile'] ?? null ) || ! is_string( $payload['profile']['topics'] ?? null ) || ! is_string( $payload['profile']['excluded'] ?? null ) ) {
			return new WP_Error( 'invalid_assessment' );
		}
		try {
			$result = $provider->assess( $payload );
		} catch ( Throwable $error ) {
			return new WP_Error( 'assessment_failed' );
		}
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'assessment_failed' );
		}
		$json = wp_json_encode( $result );
		if ( ! is_array( $result ) || ! is_string( $json ) || strlen( $json ) > 4096 || ! in_array( $result['recommendation'] ?? '', array( 'relevant', 'uncertain', 'irrelevant' ), true ) || ! is_string( $result['explanation'] ?? null ) || ! is_array( $result['evidence'] ?? null ) || count( $result['evidence'] ) > 5 ) {
			return new WP_Error( 'invalid_assessment' );
		}
		$evidence = array();
		foreach ( $result['evidence'] as $phrase ) {
			if ( ! is_string( $phrase ) || '' === trim( $phrase ) || mb_strlen( $phrase ) > 100 || false === mb_stripos( $payload['title'] ?? '', $phrase ) ) {
				return new WP_Error( 'invalid_assessment' );
			}
			$evidence[] = sanitize_text_field( $phrase );
		}
		return array(
			'recommendation' => $result['recommendation'],
			'explanation'    => mb_substr( sanitize_text_field( $result['explanation'] ), 0, 500 ),
			'evidence'       => $evidence,
		);
	}
}
