<?php
/** Local HTTPS fixture with a test-only trusted certificate. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$context = stream_context_create( array( 'ssl' => array( 'local_cert' => '/tmp/scout-cert.pem', 'local_pk' => '/tmp/scout-key.pem' ) ) );
$server = stream_socket_server( 'tls://93.184.216.34:443', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context );
if ( ! $server ) { throw new RuntimeException( $error ); }
while ( true ) {
	$client = @stream_socket_accept( $server, 30 );
	if ( ! $client ) { continue; }
	stream_set_timeout( $client, 5 );
	$line = fgets( $client, 4096 );
	while ( ( $header = fgets( $client, 4096 ) ) && "\r\n" !== $header ) {}
	if ( str_contains( $line, '/redirect' ) ) {
		fwrite( $client, "HTTP/1.1 302 Found\r\nLocation: https://127.0.0.1/\r\nContent-Length: 0\r\nConnection: close\r\n\r\n" );
	} else {
		$body = '<rss version="2.0"><channel><title>Fixture</title><link>https://feeds.example.org/</link><description>Fixture</description><item><title>Pinned TLS feed</title><link>https://feeds.example.org/article</link></item></channel></rss>';
		fwrite( $client, "HTTP/1.1 200 OK\r\nContent-Type: application/rss+xml\r\nContent-Length: " . strlen( $body ) . "\r\nConnection: close\r\n\r\n" . $body );
	}
	fclose( $client );
}
