<?php
/** Standalone safety checks: php tests/diagnostic-extractor.php (no WordPress/network). */
if ( 'cli' !== PHP_SAPI || defined( 'ABSPATH' ) ) {
	exit( 1 );
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'WP_CLI', true );
$scout_authorized = true;
function current_user_can( $capability ) {
	global $scout_authorized;
	return $scout_authorized && in_array( $capability, array( 'manage_options', 'edit_posts' ), true );
}
class WP_Error {
	private $data;
	public function __construct( $code, $message, $data ) {
		$this->data = $data;
	}
	public function get_error_data() {
		return $this->data;
	}
}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
class WP_CLI {
	public static $output = array();
	public static function line( $line ) {
		self::$output[] = $line;
	}
	public static function error( $message ) {
		throw new RuntimeException( $message );
	}
}
class Editorial_Scout_Store {
	public static function table( $kind ) {
		if ( 'writing' !== $kind ) {
			throw new RuntimeException( 'Unexpected table.' );
		}
		return 'test_writing';
	}
}
class Scout_Diagnostic_Read_Only_DB {
	public $row;
	public $reads = 0;
	public function prepare( $query, $table, $id ) {
		if ( 'SELECT * FROM %i WHERE commission_id = %d' !== $query || 'test_writing' !== $table || 4 !== $id ) {
			throw new RuntimeException( 'Unexpected query.' );
		}
		return 'safe_select';
	}
	public function get_row( $query, $format ) {
		if ( 'safe_select' !== $query || ARRAY_A !== $format ) {
			throw new RuntimeException( 'Unexpected database read.' );
		}
		++$this->reads;
		return $this->row;
	}
	public function __call( $name, $args ) {
		throw new RuntimeException( 'Unexpected database operation.' );
	}
}
require dirname( __DIR__ ) . '/includes/class-editorial-scout-drafting.php';
$wpdb = new Scout_Diagnostic_Read_Only_DB();
foreach ( array( 'claims', 'field_text', 'unsupported_number', 'claim_coverage', 'CANARY_PRIVATE', null, array( 'CANARY_PRIVATE' ) ) as $stage ) {
	$wpdb->row = array( 'data' => json_encode( array( 'error_stage' => $stage, 'private_notes' => 'CANARY_PRIVATE' ) ) );
	WP_CLI::$output = array();
	$args = array( '4' );
	require dirname( __DIR__ ) . '/tools/diagnose-writing-stage.php';
	$expected = is_string( $stage ) && 'CANARY_PRIVATE' !== $stage ? $stage : ( null === $stage ? 'not_recorded' : 'unrecognized' );
	if ( array( $expected ) !== WP_CLI::$output ) {
		throw new RuntimeException( 'Unsafe diagnostic output.' );
	}
}
$wpdb->row = null;
WP_CLI::$output = array();
require dirname( __DIR__ ) . '/tools/diagnose-writing-stage.php';
if ( array( 'not_recorded' ) !== WP_CLI::$output ) {
	throw new RuntimeException( 'Missing journal output failed.' );
}
$scout_authorized = false;
$reads_before = $wpdb->reads;
WP_CLI::$output = array();
try {
	require dirname( __DIR__ ) . '/tools/diagnose-writing-stage.php';
	throw new LogicException( 'Unauthorized access accepted.' );
} catch ( RuntimeException $error ) {
	if ( WP_CLI::$output || $reads_before !== $wpdb->reads ) {
		throw new LogicException( 'Unauthorized read or output.' );
	}
}
echo "PASS: eight allowlist/absence cases; unauthorized access denied before database read.\n";
