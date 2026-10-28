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
t( 'white gets dark writing, black gets white writing', false !== strpos( style( '#ffffff', '' ), '#1f1d2b;' ) && false !== strpos( style( '#000000', '' ), '--twd-ap-primary-ink:#ffffff;' ) );
t( 'black hover stays valid', false !== strpos( style( '#000000', '' ), '--twd-ap-primary-dark:#000000;' ) );
t( 'upper case hex is accepted and the properties stay well formed', 1 === preg_match( '/^(--[a-z-]+:#[0-9a-fA-F]{6};){3}$/', style( '#AABBCC', '' ) ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
