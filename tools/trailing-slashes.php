<?php
namespace BU\Webrouter;

$lines = file( dirname(__DIR__) . '/maps/redirects.map' );
foreach( $lines as &$line ) {
	if ( ! preg_match( '/people\.bu\.edu/', $line ) )
		continue;

	if ( preg_match( '/\?/', $line ) )
		continue;
	 
	if ( preg_match( '/\#/', $line ) )
		continue;

	if ( preg_match( '~/ ;~', $line ) )
		continue;

	if ( preg_match( '/\.(s?html?|php|pl|pdf)/', $line ) )
		continue;

	echo $line;
	$line = str_replace( ' ;', '/ ;', $line );
}
// file_put_contents( dirname(__DIR__) . '/maps/redirects.map', implode("", $lines) );