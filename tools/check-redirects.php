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

$redirects = parse( file( dirname(__DIR__) . '/landscape/prod/maps/redirects.map' ) );
$sites = parse( file( dirname(__DIR__) . '/landscape/prod/maps/sites.map' ) );


// Note: we do not iterate over sites. This reporting is explicitly for redirects.
foreach( $redirects as $key => $url ) {

	if ( !isset( $sites[ $key ] ) ) {
		echo "$key - Found in redirects.map but not in sites.map\n";
		continue;
	}

	if ( $sites[$key] !== 'redirect' && $sites[$key] !== 'redirect_asis' ) {
		echo "$key - Found in redirects.map, but sites.map lists it as " . $sites[$key] . "\n";
		continue;
	}

	$check = check_url( $url );
	if ( $check )
		echo "$key - " . $check . "\n";
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
		return 'Second order redirect: ' . $url . ' to ' . $redirect;
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