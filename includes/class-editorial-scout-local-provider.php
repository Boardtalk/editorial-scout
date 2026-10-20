<?php
/**
 * Provider contract and fail-closed boundary. No external adapter ships in this build.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Local_Provider implements Editorial_Scout_Provider {
	public function id() {
		return 'local-terms-v1';
	}
	public function external() {
		return false;
	}
	public function assess( $payload ) {
		$found    = array();
		$excluded = array();
		foreach ( array( 'topics', 'excluded' ) as $field ) {
			foreach ( preg_split( '/[,;\r\n]+/u', $payload['profile'][ $field ] ) as $term ) {
				$term = trim( $term );
				if ( mb_strlen( $term ) >= 2 && mb_strlen( $term ) <= 100 && preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $term, '/' ) . '(?![\p{L}\p{N}])/iu', $payload['title'] ) ) {
					if ( 'excluded' === $field ) {
						$excluded[] = $term;
					} else {
						$found[] = $term;
					}
				}
			}
		}
		$recommendation = $excluded && ! $found ? 'irrelevant' : ( $found && ! $excluded ? 'relevant' : 'uncertain' );
		return array(
			'recommendation' => $recommendation,
			'explanation'    => __( 'Local title-term matching only. Matching and excluded terms can conflict; no factual verification or semantic AI analysis was performed. Absence of a match is uncertain.', 'editorial-scout' ),
			'evidence'       => array_slice( array_merge( $excluded, $found ), 0, 5 ),
		);
	}
}
