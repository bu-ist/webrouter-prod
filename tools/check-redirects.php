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
$checks = [];

$start = time();

$i = 0;

$estimate = intval( count($redirects) / 2 / 60 ) . ' to ' . intval( count($redirects ) / 60 ) . ' minutes';

echo 'Checking all URLs. This will take about ' . $estimate . '. No changes are made during these checks. Redirects to Check: ' . count($redirects) . "\n";
foreach( $redirects as $key => $url ) {
	$checks[ $key ] = check_url( $url );
	if ( $i++ % 20 == 19 )
		echo "  " . $i . " done (" . (time() - $start) . " seconds)\n";
}


$i = 0;

// Note: we do not iterate over sites. This reporting is explicitly for redirects.
foreach( $redirects as $key => $url ) {

	$i++;
	$counter = '[' . str_pad( $i, 3, ' ', STR_PAD_LEFT ) . '/' . str_pad( count($redirects), 3, ' ', STR_PAD_LEFT ) . '] ';

	if ( !isset( $sites[ $key ] ) ) {
		echo $counter . $key . "\n";
		echo "    Found in redirects.map but not in sites.map\n";
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
		continue;
	}

	if ( $sites[$key] !== 'redirect' && $sites[$key] !== 'redirect_asis' ) {
		echo $counter . "$key - Found in redirects.map, but sites.map lists it as " . $sites[$key] . "\n";
		continue;
	}

	$check = $checks[ $key ];
	if ( $check ) {
		if ( preg_match( '/Second order redirect: (.*)$/', $check, $match ) ) {
			if ( $match[1] == $url . '/' || str_replace( 'http://', 'https://', $url ) == $match[1] ) {
				// Automatically replace when it's just about a trailing slash or HTTPS
				replace_redirect( $key, $match[1] );
			} else {
				echo $counter . $key . "\n";
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
						system( 'open "https://' . str_replace( '_/', 'www.bu.edu/', $key ) . '"' );
				} while ( 'o' === $replace );
			}

		} else if ( preg_match( '/Error: (.*)$/', $check, $match ) ) {
			if ( '404' == $match[1] ) {
				echo $counter . $key . "\n";
				echo "    Target:        " . $url . "\n";
				echo "    Error:         404\n";

				do {
					echo "    Remove? (y/N) or [o]pen in browser";
					$replace = strtolower( readline( " > " ) );
					if ( 'y' === $replace )
						remove_redirect( $key );
					if ( 'd' === $replace )
						remove_redirect( $key );
					if ( 'o' === $replace )
						system( 'open "https://' . str_replace( '_/', 'www.bu.edu/', $key ) . '"' );
				} while ( 'o' === $replace );

			}
		} else {
			echo $counter . $key . "\n";
			echo "    " . $check . "\n";
			echo "    Must be Resolved Manually\n";
		}
	}
}

function replace_redirect( $key, $target ) {
	$contents = file_get_contents( dirname(__DIR__) . '/maps/redirects.map' );
	$contents = preg_replace( '~^' . preg_quote( $key, '~' ) . ' .*$~m', $key . ' ' . $target . ' ;', $contents );
	file_put_contents( dirname(__DIR__) . '/maps/redirects.map', $contents );
}


function remove_redirect( $key ) {
	$contents = file_get_contents( dirname(__DIR__) . '/maps/redirects.map' );
	$contents = preg_replace( '~^' . preg_quote( $key, '~' ) . ' .*?$.~sm', '', $contents );
	file_put_contents( dirname(__DIR__) . '/maps/redirects.map', $contents );

	$contents = file_get_contents( dirname(__DIR__) . '/maps/sites.map' );
	$contents = preg_replace( '~^' . preg_quote( $key, '~' ) . ' .*?$.~sm', '', $contents );
	file_put_contents( dirname(__DIR__) . '/maps/sites.map', $contents );
}


function check_url( $url ) {
	$curl = curl_init();
	curl_setopt( $curl, CURLOPT_URL, $url );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl, CURLOPT_TIMEOUT, 2 );
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
		if ( stristr( $redirect, '/wp-app/shibboleth/' ) )
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