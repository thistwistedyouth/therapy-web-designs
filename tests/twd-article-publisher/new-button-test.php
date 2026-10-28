<?php
// Tests for the colour of the floating New Article button (1.47.0): it takes the
// colour of the quote bar in an article. Real TWD_AP_Frontend::new_button_style(),
// WordPress functions faked.
//   php tests/twd-article-publisher/new-button-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
function sanitize_hex_color( $c ) {
	if ( ! is_string( $c ) ) { return ''; }
	if ( preg_match( '/^#([0-9a-f]{3}){1,2}$/i', $c ) ) { return $c; }
	return '';
}
require $plugin . 'includes/class-twd-ap-frontend.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function style( $q, $l ) { return TWD_AP_Frontend::new_button_style( $q, $l ); }

t( 'neither colour set: no style, the button keeps its original sage', '' === style( '', '' ) );
t( 'null and junk are no colour', '' === style( null, null ) && '' === style( 'red', 'blue' ) && '' === style( array(), 5 ) && '' === style( '#12', '#12345' ) && '' === style( 'javascript:alert(1)', '<script>' ) );

$s = style( '#8a5a00', '' );
t( 'quote colour becomes the button colour', 0 === strpos( $s, '--twd-ap-primary:#8a5a00;' ), $s );
t( 'hover colour is a darker one', false !== strpos( $s, '--twd-ap-primary-dark:#714a00;' ), $s );
t( 'a dark colour gets white writing', false !== strpos( $s, '--twd-ap-primary-ink:#ffffff;' ), $s );
t( 'the style is custom properties only, ending in a semicolon', 1 === preg_match( '/^(--[a-z-]+:#[0-9a-f]{6};){3}$/', $s ), $s );

$s = style( '', '#2255aa' );
t( 'no quote colour: the link colour is used (the quote bar falls back to it)', 0 === strpos( $s, '--twd-ap-primary:#2255aa;' ), $s );
$s = style( '#8a5a00', '#2255aa' );
t( 'both set: the quote colour wins', 0 === strpos( $s, '--twd-ap-primary:#8a5a00;' ), $s );
$s = style( 'junk', '#2255aa' );
t( 'a bad quote colour falls through to the link colour', 0 === strpos( $s, '--twd-ap-primary:#2255aa;' ), $s );

$s = style( '#fc0', '' );
t( 'short hex is expanded', 0 === strpos( $s, '--twd-ap-primary:#ffcc00;' ), $s );
t( 'a light colour gets dark writing', false !== strpos( $s, '--twd-ap-primary-ink:#1f1d2b;' ), $s );
t( 'under dark writing the hover is a lighter colour, so the label keeps its contrast', false !== strpos( $s, '--twd-ap-primary-dark:#ffd52e;' ), $s );
t( 'white gets dark writing, black gets white writing', false !== strpos( style( '#ffffff', '' ), '#1f1d2b;' ) && false !== strpos( style( '#000000', '' ), '--twd-ap-primary-ink:#ffffff;' ) );
t( 'black hover stays valid', false !== strpos( style( '#000000', '' ), '--twd-ap-primary-dark:#000000;' ) );
t( 'upper case hex is accepted and the properties stay well formed', 1 === preg_match( '/^(--[a-z-]+:#[0-9a-fA-F]{6};){3}$/', style( '#AABBCC', '' ) ) );

// Mid-tones: the writing is whichever of white or dark ink reads better, not a luma cut-off.
function lum( $hex ) {
	$h = ltrim( $hex, '#' );
	if ( 3 === strlen( $h ) ) { $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]; }
	$c = array_map( function ( $v ) { $v = hexdec( $v ) / 255; return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ); }, str_split( $h, 2 ) );
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function ratio( $a, $b ) { $x = lum( $a ); $y = lum( $b ); return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 ); }
function parts( $s ) { preg_match( '/primary:(#[0-9a-f]{6});--twd-ap-primary-dark:(#[0-9a-f]{6});--twd-ap-primary-ink:(#[0-9a-f]{6});/', $s, $m ); return $m; }
foreach ( array( '#8a9dc0' => 'dusty blue', '#8a9a5b' => 'olive', '#c47a5a' => 'terracotta', '#999999' => 'grey', '#2a9d8f' => 'teal' ) as $hex => $name ) {
	$m = parts( style( $hex, '' ) );
	t( 'mid-tone ' . $name . ' (' . $hex . ') gets dark writing, which reads better there than white', '#1f1d2b' === $m[3] && ratio( $hex, '#1f1d2b' ) > ratio( $hex, '#ffffff' ), json_encode( $m ) );
}
mt_srand( 147 );
$worse = 0; $hover_worse = 0; $below = 0;
for ( $i = 0; $i < 3000; $i++ ) {
	$hex = sprintf( '#%02x%02x%02x', mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) );
	$m   = parts( style( $hex, '' ) );
	$other = '#ffffff' === $m[3] ? '#1f1d2b' : '#ffffff';
	if ( ratio( $hex, $m[3] ) + 0.0001 < ratio( $hex, $other ) ) { $worse++; }
	if ( ratio( $m[2], $m[3] ) + 0.0001 < ratio( $hex, $m[3] ) ) { $hover_worse++; }
	if ( ratio( $hex, $m[3] ) < 4.5 ) { $below++; }
}
t( '3000 random colours: the writing chosen is never the worse of the two', 0 === $worse, $worse . ' worse' );
t( '3000 random colours: the hover never has less contrast than the button itself', 0 === $hover_worse, $hover_worse . ' worse' );
t( '3000 random colours: the worst case is far fewer than the 34% a luma cut-off left below 4.5:1', $below < 3000 * 0.2, $below . ' of 3000 below 4.5:1' );

// A hex with a trailing newline passes WordPress's own check; it must not reach the style.
t( 'a colour with a trailing newline is no colour', '' === style( "#aabbcc\n", '' ) && '' === style( "#abc\n", '' ) );
t( 'and the next colour is used instead', 0 === strpos( style( "#abc\n", '#2255aa' ), '--twd-ap-primary:#2255aa;' ) );
t( 'the style never contains a line break', false === strpos( style( "#aabbcc\n", '#2255aa' ), "\n" ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
