<?php
/**
 * Provider contract and fail-closed boundary. No external adapter ships in this build.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
interface Editorial_Scout_Provider {
	public function id();
	public function external();
	public function assess( $payload );
}
