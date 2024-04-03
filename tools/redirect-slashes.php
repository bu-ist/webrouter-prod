<?php

$redirects = parse( file( dirname(__DIR__) . '/maps/redirects.map' ) );
$sites = parse( file( dirname(__DIR__) . '/maps/sites.map' ) );

foreach( $sites as $key => $target ) {
	if ( $target == 'redirect' ) {
		if ( preg_match( '~/$~', $redirects[ $key ] ) ) {
			replace_redirect( $key, substr( $redirects[$key], 0, -1 ) );
		}
	}
}


function replace_redirect( $key, $target ) {
	$contents = file_get_contents( dirname(__DIR__) . '/maps/redirects.map' );
	$contents = preg_replace( '~^' . preg_quote( $key, '~' ) . '.*$~m', $key . ' ' . $target . ' ;', $contents );
	file_put_contents( dirname(__DIR__) . '/maps/redirects.map', $contents );
}

function parse( $lines ) {
	$map = [];
	
	foreach( $lines as $line ) {
		// Remove comments:
		$line = preg_replace( '/#.*$/', '', $line );

		// And whitespace:
		$line = trim( $line );

		if ( $line == '' )
			continue;

		$pieces = preg_split( '/[ 	]+/', $line );

		$map[ $pieces[0] ] = $pieces[1];
	}

	return $map;
}