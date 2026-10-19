<?php
/**
 * Feed trust boundary.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Feed {
	private static $addresses = array();
	const MAX_BYTES           = 1048576;

	public static function url( $url, $resolve = true ) {
		if ( ! is_string( $url ) || strlen( $url ) > 2048 || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
			return new WP_Error( 'invalid_url', __( 'Use a public HTTPS feed URL.', 'editorial-scout' ) );
		}
		$parts = wp_parse_url( $url );
		if ( ! $parts || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) ) {
			return new WP_Error( 'invalid_url', __( 'HTTPS on port 443 without credentials or fragments is required.', 'editorial-scout' ) );
		}
		$host = strtolower( $parts['host'] );
		if ( ! preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])$/D', $host ) || preg_match( '/\.(?:localhost|local|internal|test|invalid|example)$/D', $host ) ) {
			return new WP_Error( 'unsafe_host', __( 'A public DNS hostname is required.', 'editorial-scout' ) );
		}
		$url = 'https://' . $host . ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		if ( $resolve ) {
			$records   = dns_get_record( $host, DNS_A | DNS_AAAA );
			$found     = false;
			$addresses = array();
			foreach ( (array) $records as $record ) {
				$ip = $record['ip'] ?? $record['ipv6'] ?? null;
				if ( null === $ip ) {
					continue;
				}
				$found       = true;
				$addresses[] = $ip;
				if ( ! self::public_ip( $ip ) ) {
					return new WP_Error( 'unsafe_host', __( 'The hostname must resolve only to public addresses.', 'editorial-scout' ) );
				}
			}
			if ( ! $found || ! wp_http_validate_url( $url ) ) {
				return new WP_Error( 'unsafe_host', __( 'The hostname could not be validated.', 'editorial-scout' ) );
			}
			// Prefer IPv4 consistently with WordPress's validator, regardless of DNS answer order.
			usort(
				$addresses,
				static function ( $left, $right ) {
					return (int) str_contains( $left, ':' ) <=> (int) str_contains( $right, ':' );
				}
			);
			self::$addresses[ $host ] = $addresses;
		}
		return $url;
	}

	public static function public_ip( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
		if ( str_contains( $ip, ':' ) ) {
			$packed = inet_pton( $ip );
			return ! str_starts_with( bin2hex( $packed ), '20010db8' ) && 0x20 === ( ord( $packed[0] ) & 0xe0 ) && ! str_starts_with( strtolower( $ip ), '2002:' ) && ! ( 0x20 === ord( $packed[0] ) && 0x01 === ord( $packed[1] ) && ord( $packed[2] ) < 2 ) && ( ord( $packed[0] ) !== 0x3f || ord( $packed[1] ) !== 0xff || ( ord( $packed[2] ) & 0xf0 ) !== 0 );
		}
		$number = ip2long( $ip );
		foreach ( array( '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4' ) as $range ) {
			list( $network, $bits ) = explode( '/', $range );
			$mask                   = -1 << ( 32 - (int) $bits );
			if ( ( $number & $mask ) === ( ip2long( $network ) & $mask ) ) {
				return false;
			}
		}
		return true;
	}

	public static function fetch( $url ) {
		$valid = self::url( $url );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! extension_loaded( 'curl' ) ) {
			return new WP_Error( 'missing_curl', __( 'cURL is required for pinned public-address requests.', 'editorial-scout' ) );
		}
		$proxy = new WP_HTTP_Proxy();
		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $valid ) ) {
			return new WP_Error( 'proxy_unsupported', __( 'Feed fetching requires a direct connection for address pinning.', 'editorial-scout' ) );
		}
		$host = strtolower( wp_parse_url( $valid, PHP_URL_HOST ) );
		$ip   = self::$addresses[ $host ][0];
		// Force Requests' cURL transport and pin DNS without changing TLS hostname validation.
		$transport = static function ( &$request_url, &$headers, &$data, &$type, &$options ) use ( $valid ) {
			if ( $request_url === $valid ) {
				$options['transport'] = new \WpOrg\Requests\Transport\Curl();
			}
		};
		$pin       = static function ( $handle, $args, $request_url ) use ( $valid, $host, $ip ) {
			if ( $request_url === $valid ) {
				$address = str_contains( $ip, ':' ) ? '[' . $ip . ']' : $ip;
				// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- Pin the WordPress HTTP transport; not a separate HTTP client.
				$pinned = curl_setopt_array(
					$handle,
					array(
						CURLOPT_RESOLVE           => array( $host . ':443:' . $address ),
						CURLOPT_PROXY             => '',
						CURLOPT_NOPROXY           => '*',
						CURLOPT_PROTOCOLS         => CURLPROTO_HTTPS,
						CURLOPT_CONNECTTIMEOUT_MS => 10000,
						CURLOPT_TIMEOUT_MS        => 10000,
						CURLOPT_FOLLOWLOCATION    => false,
						CURLOPT_SSL_VERIFYPEER    => true,
						CURLOPT_SSL_VERIFYHOST    => 2,
					)
				);
				if ( ! $pinned ) {
					throw new \WpOrg\Requests\Exception( 'Feed address pinning failed.', 'editorial_scout_pin' );
				}
			}
		};
		add_action( 'requests-requests.before_request', $transport, PHP_INT_MAX, 5 );
		add_action( 'http_api_curl', $pin, PHP_INT_MAX, 3 );
		try {
			$response = wp_safe_remote_get(
				$valid,
				array(
					'timeout'             => 10,
					'redirection'         => 0,
					'limit_response_size' => self::MAX_BYTES + 1,
					'decompress'          => false,
					'cookies'             => array(),
					'sslverify'           => true,
					'headers'             => array(
						'Accept'          => 'application/rss+xml, application/atom+xml, application/xml, text/xml',
						'Accept-Encoding' => 'identity',
					),
				)
			);
		} finally {
			remove_action( 'requests-requests.before_request', $transport, PHP_INT_MAX );
			remove_action( 'http_api_curl', $pin, PHP_INT_MAX );
		}
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_failed', __( 'Feed request failed.', 'editorial-scout' ) );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'http_status', __( 'A direct HTTP 200 response is required; redirects are rejected.', 'editorial-scout' ) );
		}
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$type         = is_string( $content_type ) ? strtolower( trim( explode( ';', $content_type )[0] ) ) : '';
		if ( ! in_array( $type, array( 'application/rss+xml', 'application/atom+xml', 'application/xml', 'text/xml', 'application/rdf+xml' ), true ) || wp_remote_retrieve_header( $response, 'content-encoding' ) ) {
			return new WP_Error( 'content_type', __( 'An uncompressed XML feed response is required.', 'editorial-scout' ) );
		}
		return self::parse( wp_remote_retrieve_body( $response ) );
	}

	public static function parse( $body ) {
		if ( ! is_string( $body ) || '' === $body || strlen( $body ) > self::MAX_BYTES || preg_match( '/<!\s*(?:DOCTYPE|ENTITY)/i', $body ) || str_contains( $body, "\0" ) ) {
			return new WP_Error( 'invalid_xml', __( 'Feed XML is empty, oversized, or contains forbidden declarations.', 'editorial-scout' ) );
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'missing_dom', __( 'The PHP DOM extension is required.', 'editorial-scout' ) );
		}
		$previous = libxml_use_internal_errors( true );
		$document = new DOMDocument();
		$loaded   = $document->loadXML( $body, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API properties.
		if ( ! $loaded || $document->doctype || ! $document->documentElement ) {
			return new WP_Error( 'invalid_xml', __( 'Malformed feed XML.', 'editorial-scout' ) );
		}
		$root = $document->documentElement;
		if ( ! ( ( 'rss' === $root->localName && ! $root->namespaceURI ) || ( 'feed' === $root->localName && 'http://www.w3.org/2005/Atom' === $root->namespaceURI ) || ( 'RDF' === $root->localName && 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' === $root->namespaceURI ) ) ) {
			return new WP_Error( 'invalid_feed', __( 'RSS or Atom XML is required.', 'editorial-scout' ) );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		require_once ABSPATH . WPINC . '/class-simplepie.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php';
		$feed = class_exists( 'SimplePie\\SimplePie' ) ? new \SimplePie\SimplePie() : new SimplePie();
		$feed->enable_cache( false );
		$feed->enable_order_by_date( false );
		$feed->set_raw_data( $body );
		$feed->set_autodiscovery_level( 0 );
		if ( class_exists( 'SimplePie\\Sanitize' ) ) {
			$feed->get_registry()->register( \SimplePie\Sanitize::class, 'WP_SimplePie_Sanitize_KSES', true );
			$feed->sanitize = new WP_SimplePie_Sanitize_KSES();
		} else {
			$feed->set_sanitize_class( 'WP_SimplePie_Sanitize_KSES' );
		}
		if ( ! $feed->init() || $feed->error() ) {
			return new WP_Error( 'invalid_feed', __( 'The feed could not be parsed.', 'editorial-scout' ) );
		}
		$items = array();
		foreach ( $feed->get_items( 0, 50 ) as $item ) {
			$link = self::url( $item->get_permalink(), false );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$title   = mb_substr( sanitize_text_field( html_entity_decode( wp_strip_all_tags( (string) $item->get_title() ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ), 0, 500 );
			$date    = $item->get_date( 'U' );
			$items[] = array(
				'identity_hash' => hash( 'sha256', $link ),
				'title'         => $title,
				'url'           => esc_url_raw( $link, array( 'https' ) ),
				'published_at'  => $date && $date > 0 && $date <= time() + DAY_IN_SECONDS ? gmdate( 'Y-m-d H:i:s', (int) $date ) : null,
			);
		}
		return $items;
	}
}
