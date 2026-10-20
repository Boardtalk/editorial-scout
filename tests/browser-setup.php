<?php
/** Browser fixture and ephemeral cookies: values are consumed in memory, never logged. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$base = getenv( 'SCOUT_BROWSER_URL' ) ?: 'http://127.0.0.1:8099';
$_SERVER['HTTP_HOST'] = parse_url( $base, PHP_URL_HOST );
define( 'COOKIEHASH', md5( $base ) );
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
activate_plugin( 'editorial-scout/editorial-scout.php' );
Editorial_Scout_Scheduling::configure( 'hourly' );
delete_user_meta( 1, Editorial_Scout_Scheduling::JOB );
update_option( 'siteurl', $base );
update_option( 'home', $base );
global $wpdb;
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Editorial_Scout_Store::table( 'items' ) ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Editorial_Scout_Store::table( 'sources' ) ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Editorial_Scout_Store::table( 'decisions' ) ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Editorial_Scout_Store::table( 'categories' ) ) );
Editorial_Scout_Store::save( 0, 'Browser source', 'https://feeds.example.org/rss?' . str_repeat( 'long', 200 ), true );
$source = Editorial_Scout_Store::sources()[0];
global $wpdb;
$wpdb->insert( Editorial_Scout_Store::table( 'items' ), array( 'source_id' => $source->id, 'identity_hash' => hash( 'sha256', 'browser' ), 'story_hash' => Editorial_Scout_Store::story_hash( 'https://feeds.example.org/article' ), 'title' => '<script>unsafe()</script> Original research', 'url' => 'https://feeds.example.org/article', 'discovered_at' => gmdate( 'Y-m-d H:i:s' ) ) );
Editorial_Scout_Store::save( 0, 'Browser second source', 'https://feeds.example.org/rss', true );
$mu = ABSPATH . 'wp-content/mu-plugins';
wp_mkdir_p( $mu );
file_put_contents( $mu . '/scout-fixture-trust.php', <<<'TRUST'
<?php
if ( '1' === getenv( 'EDITORIAL_SCOUT_TESTS' ) ) {
 add_filter( 'http_request_args', static function( $args, $url ) {
  if ( str_starts_with( $url, 'https://feeds.example.org/' ) ) { $args['sslcertificates'] = '/tmp/scout-cert.pem'; }
  return $args;
 }, 10, 2 );
}
TRUST
);
file_put_contents( $mu . '/scout-ai-functions.php', file_get_contents( __DIR__ . '/openai-fixture.php' ) );
file_put_contents( $mu . '/scout-ai-fixture.php', <<<'MOCK'
<?php
if ( '1' === getenv( 'EDITORIAL_SCOUT_TESTS' ) ) {
 define('EDITORIAL_SCOUT_OPENAI_API_KEY','sk-fixture-'.str_repeat('x',60));
 require_once __DIR__ . '/scout-ai-functions.php';
 add_filter('pre_http_request', static function($pre,$args,$url){
  if($url!=='https://api.openai.com/v1/responses'){return $pre;}
  $body=json_decode($args['body'],true);$payload=json_decode($body['input'],true);
  return scout_semantic_response($payload,is_null($payload));
 },10,3);
}
MOCK
);
delete_option( Editorial_Scout_OpenAI::OPTION );
delete_option( Editorial_Scout_OpenAI::USAGE );
delete_user_meta( 1, Editorial_Scout_OpenAI::PREVIEW );
delete_option( Editorial_Scout_Intelligence::OPTION );
delete_option( Editorial_Scout_Intelligence::RESULTS );
delete_user_meta( 1, Editorial_Scout_Intelligence::DRAFT );
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'title' => 'Public research browser reference', 'numberposts' => 50, 'fields' => 'ids' ) ) as $old_fixture ) { wp_delete_post( $old_fixture, true ); }
wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'Public research browser reference', 'post_content' => 'research evidence community public information astronomy exoplanets', 'post_date' => gmdate( 'Y-m-d H:i:s' ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s' ) ) );
$cookies = array();
foreach ( array( AUTH_COOKIE => 'auth', LOGGED_IN_COOKIE => 'logged_in' ) as $name => $scheme ) {
	$cookies[] = array( 'name' => $name, 'value' => wp_generate_auth_cookie( 1, time() + 1800, $scheme ), 'url' => $base, 'httpOnly' => true, 'sameSite' => 'Lax' );
}
echo wp_json_encode( $cookies );
