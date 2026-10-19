<?php
/** Deterministic DNS fixture. Never install in production. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) {
	exit( 1 );
}
$socket = stream_socket_server( 'udp://127.0.0.1:53', $errno, $error, STREAM_SERVER_BIND );
if ( ! $socket ) {
	throw new RuntimeException( $error );
}
while ( true ) {
	$query = stream_socket_recvfrom( $socket, 4096, 0, $peer );
	if ( strlen( $query ) < 17 ) { continue; }
	$offset = 12;
	$labels = array();
	while ( isset( $query[ $offset ] ) && ord( $query[ $offset ] ) > 0 ) {
		$length = ord( $query[ $offset++ ] );
		$labels[] = substr( $query, $offset, $length );
		$offset += $length;
	}
	$offset++;
	$type = unpack( 'ntype', substr( $query, $offset, 2 ) )['type'];
	$question = substr( $query, 12, $offset + 4 - 12 );
	$host = implode( '.', $labels );
	$addresses = array();
	if ( in_array( $host, array( 'feeds.example.org', 'mixed.example.org' ), true ) && 1 === $type ) {
		$addresses[] = file_exists( '/tmp/scout-dns-private' ) ? '127.0.0.1' : '93.184.216.34';
	}
	if ( 'private.example.org' === $host && 1 === $type ) { $addresses[] = '127.0.0.1'; }
	if ( 'mixed.example.org' === $host && 28 === $type ) { $addresses[] = '::1'; }
	$answer = '';
	foreach ( $addresses as $ip ) {
		$packed = inet_pton( $ip );
		$answer .= "\xc0\x0c" . pack( 'nnNn', $type, 1, 60, strlen( $packed ) ) . $packed;
	}
	$response = substr( $query, 0, 2 ) . pack( 'nnnnn', 0x8180, 1, count( $addresses ), 0, 0 ) . $question . $answer;
	stream_socket_sendto( $socket, $response, 0, $peer );
}
