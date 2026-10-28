<?php
// Tests for the owner's own swipe book colours (1.49.0): a frame colour and an accent colour, used with
// either book. Real TWD_AP_Swipebook_Colours and TWD_AP_Grid_Settings classes, WordPress functions faked.
//   php tests/twd-article-publisher/swipe-colours-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['opt'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : ''; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $s ) { return (string) $s; }
function get_bloginfo() { return 'Example Site'; }
function get_theme_mod() { return 0; }
function wp_get_attachment_image_url() { return ''; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function nocache_headers() {}
function wp_count_posts() { return (object) array( 'publish' => 3 ); }
function get_categories() { return array(); }
function wp_list_pluck( $list, $field ) { return array_map( function ( $i ) use ( $field ) { return $i[ $field ]; }, (array) $list ); }
function rest_ensure_response( $x ) { return $x; }
class TWD_AP_Cards { static function uncategorized_id() { return 1; } }
class WP_Error {}
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; const EDITABLE = 'PUT'; const DELETABLE = 'DELETE'; }
function register_rest_route() {}
function __( $s ) { return $s; }
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-swipebook-colours.php';
require $plugin . 'includes/class-twd-ap-swipebook.php';
require $plugin . 'includes/class-twd-ap-rest.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function C() { return 'TWD_AP_Swipebook_Colours'; }
function vars( $style ) { $o = array(); foreach ( array_filter( explode( ';', $style ) ) as $d ) { $p = strpos( $d, ':' ); $o[ substr( $d, 0, $p ) ] = substr( $d, $p + 1 ); } return $o; }
function first_hex( $v ) { preg_match( '/#[0-9a-f]{6}/i', $v, $m ); return $m[0] ?? ''; }
// The colour under a control's writing: the frame with the control's own tint laid over it ("rgba(r, g, b, a)").
function tint_bg( $frame, $v ) { if ( ! preg_match( '/^rgba\((\d+), (\d+), (\d+), ([0-9.]+)\)$/', $v, $m ) ) { return $frame; } return C()::mix( $frame, sprintf( '#%02x%02x%02x', $m[1], $m[2], $m[3] ), (float) $m[4] ); }
function rgba_hex( $v ) { if ( 1 === preg_match( '/^#[0-9a-f]{6}$/', $v ) ) { return $v; } if ( preg_match( '/^rgba\((\d+), (\d+), (\d+), /', $v, $m ) ) { return sprintf( '#%02x%02x%02x', $m[1], $m[2], $m[3] ); } return ''; }

// ---------- nothing chosen, nothing printed ----------
t( 'nothing chosen prints nothing, in both books', '' === C()::style( 'dark', '', '' ) && '' === C()::style( 'light', '', '' ) );
t( 'junk is no colour', '' === C()::style( 'light', "#abc\n", 'red' ) && '' === C()::style( 'dark', '#12345', array() ) && '' === C()::style( 'dark', "#ff0000;} body{x:y", '<script>' ) && '' === C()::style( 'light', null, 5 ) );
t( 'clean_hex: three digits expand, case folds, junk is empty', '#ffcc00' === C()::clean_hex( '#FC0' ) && '#8a5a00' === C()::clean_hex( '#8A5A00' ) && '' === C()::clean_hex( "#8a5a00\n" ) && '' === C()::clean_hex( '8a5a00' ) && '' === C()::clean_hex( '#8a5a0' ) );

// ---------- a pastel green frame in the light book ----------
$s = C()::style( 'light', '#cfe6d2', '' );
$v = vars( $s );
t( 'frame only: the frame gradient, the shadow, the veil, the saved picture and the controls are set, the accent is not', isset( $v['--twd-sb-frame-bg'], $v['--twd-sb-frame-shadow'], $v['--twd-sb-veil'], $v['--twd-sb-export-bg'], $v['--twd-sb-ctl'] ) && ! isset( $v['--twd-sb-gold'] ) && ! isset( $v['--twd-sb-primary'] ), $s );
t( 'frame only: the frame gradient uses the chosen colour', false !== strpos( $v['--twd-sb-frame-bg'], '#cfe6d2' ), $v['--twd-sb-frame-bg'] );
t( 'frame only: the saved picture stays the card\'s own colour (a light book, so a light colour), only a little tinted', C()::luminance( $v['--twd-sb-export-bg'] ) > 0.85, $v['--twd-sb-export-bg'] );
t( 'frame only: the controls on the frame read on it (4.5:1)', C()::contrast( $v['--twd-sb-ctl'], '#cfe6d2' ) >= 4.5, $v['--twd-sb-ctl'] );
t( 'frame only: the output has only numbers, colours and fixed words', 1 === preg_match( '/^[-a-z0-9:;#%.,() ]*$/', $s ), $s );

// ---------- accent ----------
$s = C()::style( 'light', '', '#5fa36a' );
$v = vars( $s );
t( 'accent only: headings and links read on the card (4.5:1) though the pick was pale, buttons use the pick as chosen', C()::contrast( $v['--twd-sb-gold'], C()::CARD_LIGHT ) >= 4.5 && '#5fa36a' === $v['--twd-sb-primary'], $s );
t( 'accent only: the filled button writing is white or dark, whichever reads better, at 4.5:1 or more', C()::contrast( $v['--twd-sb-primary-ink'], '#5fa36a' ) >= 4.5, $v['--twd-sb-primary-ink'] );
t( 'accent only: the frame is not touched, the controls follow the accent', ! isset( $v['--twd-sb-frame-bg'] ) && isset( $v['--twd-sb-ctl'] ), $s );
$s = C()::style( 'dark', '', '#2d6a4f' );
$v = vars( $s );
t( 'a dark accent in the dark book is lightened until it reads on the dark card', C()::contrast( $v['--twd-sb-gold'], C()::CARD_DARK ) >= 4.5, $v['--twd-sb-gold'] );

// ---------- 3000 random colours, both books: nothing can be made unreadable ----------
mt_srand( 149 );
$bad = array();
for ( $i = 0; $i < 3000; $i++ ) {
	$f = sprintf( '#%02x%02x%02x', mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) );
	$a = sprintf( '#%02x%02x%02x', mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) );
	foreach ( array( 'light', 'dark' ) as $theme ) {
		$card = 'light' === $theme ? C()::CARD_LIGHT : C()::CARD_DARK;
		$v    = vars( C()::style( $theme, $f, $a ) );
		if ( C()::contrast( $v['--twd-sb-gold'], $card ) < 4.5 ) { $bad[] = "$theme gold $f $a " . $v['--twd-sb-gold']; }
		if ( C()::contrast( rgba_hex( $v['--twd-sb-gold-soft'] ), $card ) < 4.4 ) { $bad[] = "$theme gold-soft $f $a"; }
		if ( C()::contrast( $v['--twd-sb-ctl'], $f ) < 4.5 ) { $bad[] = "$theme ctl $f $a " . $v['--twd-sb-ctl']; }
		if ( C()::contrast( $v['--twd-sb-primary-ink'], $v['--twd-sb-primary'] ) < 4.5 ) { $bad[] = "$theme ink $a"; }
		if ( C()::contrast( $v['--twd-sb-primary-ink'], $v['--twd-sb-primary-hover'] ) < 4.0 ) { $bad[] = "$theme ink-hover $a " . $v['--twd-sb-primary-hover']; }
		// the saved picture's colour must carry the book's own writing: the light book's dark ink, the dark book's cream
		$ink = 'light' === $theme ? '#2b2540' : '#fff8ec';
		if ( C()::contrast( $ink, $v['--twd-sb-export-bg'] ) < 7 ) { $bad[] = "$theme export $f " . $v['--twd-sb-export-bg']; }
		// the writing on its own tint: small writing (Share, Save, Read the full article) 4.5:1, the 20px arrows 3:1
		foreach ( array( '--twd-sb-ctl-chip', '--twd-sb-ctl-chip-hover' ) as $k ) { if ( C()::contrast( $v['--twd-sb-ctl'], tint_bg( $f, $v[ $k ] ) ) < 4.5 ) { $bad[] = "$theme $k $f $a"; } }
		foreach ( array( '--twd-sb-ctl-rest', '--twd-sb-ctl-hover' ) as $k ) { if ( C()::contrast( $v['--twd-sb-ctl'], tint_bg( $f, $v[ $k ] ) ) < 3 ) { $bad[] = "$theme $k $f $a"; } }
		// read-link writing on the frame
		if ( C()::contrast( rgba_hex( $v['--twd-sb-read'] ), $f ) < 4.5 ) { $bad[] = "$theme read $f"; }
		if ( C()::contrast( rgba_hex( $v['--twd-sb-read-hover'] ), $f ) < 4.5 ) { $bad[] = "$theme read-hover $f"; }
		if ( 1 !== preg_match( '/^[-a-z0-9:;#%.,() ]*$/', C()::style( $theme, $f, $a ) ) ) { $bad[] = 'chars'; }
	}
}
t( '3000 random frame and accent colours, both books: headings, controls, buttons, the read link and the saved picture always read', 0 === count( $bad ), implode( ' | ', array_slice( $bad, 0, 5 ) ) . ' (' . count( $bad ) . ')' );

// ---------- the settings ----------
$d = TWD_AP_Grid_Settings::get();
t( 'settings: the default is no colour', '' === $d['swipebook_frame_color'] && '' === $d['swipebook_accent_color'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_frame_color' => '#CFE6D2', 'swipebook_accent_color' => '#5FA36A' ) );
t( 'settings: colours are saved in lower case', '#cfe6d2' === TWD_AP_Grid_Settings::get()['swipebook_frame_color'] && '#5fa36a' === TWD_AP_Grid_Settings::get()['swipebook_accent_color'] );
foreach ( array( "#aabbcc\n", 'green', '#12', '', 5, true, array( '#aabbcc' ), '#aabbcc;x', 'url(x)' ) as $bad_value ) {
	TWD_AP_Grid_Settings::save( array( 'swipebook_frame_color' => '' ) );
	TWD_AP_Grid_Settings::save( array( 'swipebook_frame_color' => '#cfe6d2' ) );
	TWD_AP_Grid_Settings::save( array( 'swipebook_frame_color' => $bad_value ) );
	$kept = TWD_AP_Grid_Settings::get()['swipebook_frame_color'];
	t( 'settings: ' . json_encode( $bad_value ) . ( '' === $bad_value ? ' clears the colour' : ' is ignored, the saved colour stays' ), '' === $bad_value ? '' === $kept : '#cfe6d2' === $kept, $kept );
}
TWD_AP_Grid_Settings::save( array( 'swipebook_frame_color' => '#cfe6d2' ) );
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#223344' ) );
t( 'settings: saving something else leaves both colours alone', '#cfe6d2' === TWD_AP_Grid_Settings::get()['swipebook_frame_color'] && '#5fa36a' === TWD_AP_Grid_Settings::get()['swipebook_accent_color'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_accent_color' => '' ) );
t( 'settings: an empty accent clears it and leaves the frame', '' === TWD_AP_Grid_Settings::get()['swipebook_accent_color'] && '#cfe6d2' === TWD_AP_Grid_Settings::get()['swipebook_frame_color'] );

// ---------- they reach the book and the popup ----------
$f = TWD_AP_Swipebook::get_header_fields( 'Swipe Book' );
t( 'the header fields carry the style, made for the chosen book', false !== strpos( $f['style'], '--twd-sb-frame-bg:' ) && false !== strpos( $f['style'], '#cfe6d2' ), $f['style'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_frame_color' => '', 'swipebook_accent_color' => '' ) );
t( 'with no colour the style is empty (the book is exactly as before)', '' === TWD_AP_Swipebook::get_header_fields( 'x' )['style'] );
$GLOBALS['opt'][ TWD_AP_Grid_Settings::OPTION_KEY ] = array( 'swipebook_frame_color' => "#cfe6d2\"><script>", 'swipebook_accent_color' => 'javascript:1' );
t( 'a bad value that got into the option directly never reaches the page', '' === TWD_AP_Swipebook::get_header_fields( 'x' )['style'] );
unset( $GLOBALS['opt'][ TWD_AP_Grid_Settings::OPTION_KEY ] );
TWD_AP_Grid_Settings::save( array( 'swipebook_frame_color' => '#cfe6d2', 'swipebook_accent_color' => '#5fa36a' ) );
$rest = ( new ReflectionClass( 'TWD_AP_REST' ) )->newInstanceWithoutConstructor();
$r = $rest->get_grid_settings();
t( '/grid-settings reports both colours', '#cfe6d2' === $r['swipebook_frame_color'] && '#5fa36a' === $r['swipebook_accent_color'] );
$GLOBALS['opt'][ TWD_AP_Grid_Settings::OPTION_KEY ] = array( 'swipebook_frame_color' => 'nonsense' );
t( '/grid-settings reports a bad stored colour as none', '' === $rest->get_grid_settings()['swipebook_frame_color'] );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
