<?php
namespace BU\Webrouter;

$remove = $argv[1];

$contents = file_get_contents( dirname(__DIR__) . '/maps/redirects.map' );
$contents = preg_replace( '~^' . preg_quote( $remove, '~' ) . '.*$~m', '', $contents );
file_put_contents( dirname(__DIR__) . '/maps/redirects.map', $contents );

$contents = file_get_contents( dirname(__DIR__) . '/maps/sites.map' );
$contents = preg_replace( '~^' . preg_quote( $remove, '~' ) . '.*$~m', '', $contents );
file_put_contents( dirname(__DIR__) . '/maps/sites.map', $contents );

