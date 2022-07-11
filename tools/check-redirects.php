<?php
namespace BU\Webrouter;

/**
 * This script checks the validity of redirects.
 * 
 * We're looking for three types of possible errors:
 * 
 * 1. Redirects defined in redirects.map that don't have a
 *    corresponding entry in sites.map
 * 
 * 2. Redirects that point ot broken URLs
 * 
 * 3. Redirects that point to URLs that themselves then redirect again
 *    These are not necessarily errors. For example, our target site
 *    might be redirecting to a login screen.
 */

$redirects = parse( file( dirname(__DIR__) . '/maps/redirects.map' ) );
$sites = parse( file( dirname(__DIR__) . '/maps/sites.map' ) );


// Note: we do not iterate over sites. This reporting is explicitly for redirects.
foreach( $redirects as $key => $url ) {

	if ( !isset( $sites[ $key ] ) ) {
		echo $key . "\n";
		echo "    Found in redirects.map but not in sites.map\n";
		do {
			echo "    Remove? (y/N) or [o]pen in browser";
			$replace = strtolower( readline( " > " ) );
			if ( 'y' === $replace )
				remove_redirect( $key );
			if ( 'o' === $replace )
				system( 'open "' . str_replace( '_/', 'https://www.bu.edu/', $key ) . '"' );
		} while ( 'o' === $replace );
		continue;
	}

	if ( $sites[$key] !== 'redirect' && $sites[$key] !== 'redirect_asis' ) {
		echo "$key - Found in redirects.map, but sites.map lists it as " . $sites[$key] . "\n";
		continue;
	}

	$check = check_url( $url );
	if ( $check ) {
		if ( preg_match( '/Second order redirect: (.*)$/', $check, $match ) ) {
			echo "$key\n";
			echo "    Target:        " . $url . "\n";
			echo "    Redirected To: " . $match[1] . "\n\n";

			do {
				echo "    Replace? (y/N) or [o]pen in browser or [d]elete";
				$replace = strtolower( readline( " > " ) );
				if ( 'y' === $replace )
					replace_redirect( $key, $match[1] );
				if ( 'd' === $replace )
					remove_redirect( $key, $match[1] );
				if ( 'o' === $replace )
					system( 'open "' . str_replace( '_/', 'https://www.bu.edu/', $key ) . '"' );
			} while ( 'o' === $replace );

		} else if ( preg_match( '/Error: (.*)$/', $check, $match ) ) {
			if ( '404' == $match[1] ) {
				echo $key . "\n";
				echo "    Error: 404\n";

				do {
					echo "    Remove? (y/N) or [o]pen in browser";
					$replace = strtolower( readline( " > " ) );
					if ( 'y' === $replace )
						remove_redirect( $key );
					if ( 'd' === $replace )
						remove_redirect( $key );
					if ( 'o' === $replace )
						system( 'open "' . str_replace( '_/', 'https://www.bu.edu/', $key ) . '"' );
				} while ( 'o' === $replace );

			}
		} else {
			echo $key . "\n";
			echo "    " . $check . "\n";
			echo "    Must be Resolved Manually\n";
		}
	}
}

function replace_redirect( $key, $target ) {
	$contents = file_get_contents( dirname(__DIR__) . '/maps/redirects.map' );
	$contents = preg_replace( '~^' . preg_quote( $key, '~' ) . '.*$~m', $key . ' ' . $target . ' ;', $contents );
	file_put_contents( dirname(__DIR__) . '/maps/redirects.map', $contents );
}


function remove_redirect( $key ) {
	$contents = file_get_contents( dirname(__DIR__) . '/maps/redirects.map' );
	$contents = preg_replace( '~^' . preg_quote( $key, '~' ) . '.*?$.~sm', '', $contents );
	file_put_contents( dirname(__DIR__) . '/maps/redirects.map', $contents );

	$contents = file_get_contents( dirname(__DIR__) . '/maps/sites.map' );
	$contents = preg_replace( '~^' . preg_quote( $key, '~' ) . '.*?$.~sm', '', $contents );
	file_put_contents( dirname(__DIR__) . '/maps/sites.map', $contents );
}


function check_url( $url ) {
	$curl = curl_init();
	curl_setopt( $curl, CURLOPT_URL, $url );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_exec( $curl );

	// HTTP response code:
	$status = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	
	// This is the ONLY condition for "success". We requested one URL and we got a valid response.
	if ( $status == 200 )
		return null;

	$redirect = curl_getinfo( $curl, CURLINFO_REDIRECT_URL );
	if ( $redirect ) {
		if ( stristr( $url, 'bostonu.imodules.com' ) )
			return null; // Second-order redirects are expected for these URLs.
		if ( stristr( $redirect, 'bostonu.imodules.com' ) )
			return null; // Second-order redirects are expected for these URLs.
		if ( stristr( $url, 'trusted.bu.edu' ) )
			return null; // Second-order redirects are expected for these URLs.
		if ( stristr( $redirect, 'weblogin.bu.edu' ) )
			return null; // Second-order redirects are expected for these URLs.
		if ( stristr( $redirect, 'shib.bu.edu' ) )
			return null; // Second-order redirects are expected for these URLs.

		return 'Second order redirect: ' . $redirect;
	}

	return 'Error: ' . $status;
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