<?php
// Prints the swipe book colour styles (TWD_AP_Swipebook_Colours::style) for a list of frame and accent colours
// as JSON, for the browser test. usage: php swipe-colours-dump.php <out.json>
define( 'ABSPATH', '/' );
require dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/includes/class-twd-ap-swipebook-colours.php';
$combos = array(
	'pastel'  => array( '#cfe6d2', '' ),
	'pastel2' => array( '#cfe6d2', '#3f7d4a' ),
	'pink'    => array( '#ef0391', '' ),
	'forest'  => array( '#14301f', '' ),
	'forest2' => array( '#14301f', '#a8e6b4' ),
	'white'   => array( '#ffffff', '#ffffff' ),
	'black'   => array( '#000000', '#000000' ),
	'accent'  => array( '', '#5fa36a' ),
	'mid'     => array( '#8a9dc0', '#b05e6f' ),
	'yellow'  => array( '#ffe27a', '#ffd166' ),
);
$out = array();
foreach ( $combos as $key => $c ) {
	foreach ( array( 'light', 'dark' ) as $theme ) {
		$out[ $key . '-' . $theme ] = array( 'theme' => $theme, 'frame' => $c[0], 'accent' => $c[1], 'style' => TWD_AP_Swipebook_Colours::style( $theme, $c[0], $c[1] ) );
	}
}
file_put_contents( $argv[1], json_encode( $out ) );
echo "ok\n";
