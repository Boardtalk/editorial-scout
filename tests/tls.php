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
	$path = explode( ' ', $line )[1] ?? '/';
	file_put_contents( '/tmp/scout-tls-requests', $path . "\n", FILE_APPEND );
	$body = '<rss version="2.0"><channel><title>Fixture</title><link>https://feeds.example.org/</link><description>Fixture</description><item><title>Pinned TLS feed</title><link>https://feeds.example.org/article</link></item></channel></rss>';
	if ( str_contains( $path, '/redirect' ) ) {
		$location = '/redirect-public' === $path ? 'https://feeds.example.org/sink' : 'https://127.0.0.1/sink';
		fwrite( $client, "HTTP/1.1 302 Found\r\nLocation: $location\r\nContent-Length: 0\r\nConnection: close\r\n\r\n" );
	} elseif ( '/chunked' === $path ) {
		fwrite( $client, "HTTP/1.1 200 OK\r\nContent-Type: application/rss+xml\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n" );
		for ( $i = 0; $i < 130; ++$i ) { @fwrite( $client, "2000\r\n" . str_repeat( 'x', 8192 ) . "\r\n" ); }
		@fwrite( $client, "0\r\n\r\n" );
	} else {
		$extra = '';
		$type = 'application/rss+xml';
		if ( '/oversized' === $path ) { $body .= str_repeat( ' ', 1048577 ); }
		if ( '/compressed' === $path ) { $body = gzencode( $body ); $extra = "Content-Encoding: gzip\r\n"; }
		if ( '/html' === $path ) { $type = 'text/html'; }
		if ( '/slow' === $path ) { sleep( 12 ); }
		@fwrite( $client, "HTTP/1.1 200 OK\r\nContent-Type: $type\r\n" . $extra . 'Content-Length: ' . strlen( $body ) . "\r\nConnection: close\r\n\r\n" . $body );
	}
	fclose( $client );
}
