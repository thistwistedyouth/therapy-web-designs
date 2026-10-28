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
t( 'frame only: the frame gradient, the shadow, the veil, the solid card, the saved picture and the controls are set, the button colour is not', isset( $v['--twd-sb-frame-bg'], $v['--twd-sb-frame-shadow'], $v['--twd-sb-veil'], $v['--twd-sb-export-bg'], $v['--twd-sb-ctl'], $v['--twd-sb-card-bg'] ) && ! isset( $v['--twd-sb-primary'] ), $s );
t( 'frame only: the frame gradient uses the chosen colour', false !== strpos( $v['--twd-sb-frame-bg'], '#cfe6d2' ), $v['--twd-sb-frame-bg'] );
t( 'frame only: the saved picture stays the card\'s own colour (a light book, so a light colour), only a little tinted', C()::luminance( $v['--twd-sb-export-bg'] ) > 0.85, $v['--twd-sb-export-bg'] );
t( 'frame only: the controls on the frame read on it (4.5:1)', C()::min_contrast( $v['--twd-sb-ctl'], array( '#cfe6d2' ) ) >= 4.5, $v['--twd-sb-ctl'] );
t( 'frame only: the card is solid and the saved picture is that same colour', 1 === preg_match( '/^#[0-9a-f]{6}$/', $v['--twd-sb-card-bg'] ) && $v['--twd-sb-card-bg'] === $v['--twd-sb-export-bg'], $v['--twd-sb-card-bg'] . ' ' . $v['--twd-sb-export-bg'] );
t( 'frame only: the output has only numbers, colours and fixed words', 1 === preg_match( '/^[-a-z0-9:;#%.,() ]*$/', $s ), $s );

// ---------- accent ----------
$s = C()::style( 'light', '', '#5fa36a' );
$v = vars( $s );
t( 'accent only: headings and links read on the card (4.5:1) though the pick was pale, buttons use the pick as chosen', C()::contrast( $v['--twd-sb-gold'], $v['--twd-sb-card-bg'] ) >= 4.5 && '#5fa36a' === $v['--twd-sb-primary'], $s );
t( 'accent only: the filled button writing is white or dark, whichever reads better, at 4.5:1 or more', C()::contrast( $v['--twd-sb-primary-ink'], '#5fa36a' ) >= 4.5, $v['--twd-sb-primary-ink'] );
t( 'accent only: the frame is not touched, the controls follow the accent', ! isset( $v['--twd-sb-frame-bg'] ) && isset( $v['--twd-sb-ctl'] ), $s );
$s = C()::style( 'dark', '', '#2d6a4f' );
$v = vars( $s );
t( 'a dark accent in the dark book is lightened until it reads on the dark card', C()::contrast( $v['--twd-sb-gold'], $v['--twd-sb-card-bg'] ) >= 4.5, $v['--twd-sb-gold'] );

// ---------- what the writing is painted on, read back from the printed variables ----------
// The frame is a gradient with a glow at the top: the controls have to read on every colour of it, not just its middle.
function own_stops( $theme ) { return 'light' === $theme ? array( C()::mix( '#fffdf8', '#d69e2e', 0.18 ), '#fffdf8', '#f8f1e3', '#efe5d1' ) : array( C()::mix( '#1b1836', '#ffd166', 0.06 ), '#1b1836', '#100e20', '#0a0912' ); }
function frame_stops( $v, $theme ) {
	if ( ! isset( $v['--twd-sb-frame-bg'] ) ) { return own_stops( $theme ); }
	if ( ! preg_match( '/rgba\(255, 255, 255, ([0-9.]+)\) 0%.*linear-gradient\(180deg, (#[0-9a-f]{6}) 0%, (#[0-9a-f]{6}) 58%, (#[0-9a-f]{6}) 100%\)/', $v['--twd-sb-frame-bg'], $m ) ) { return array(); }
	return array( C()::mix( $m[2], '#ffffff', (float) $m[1] ), $m[2], $m[3], $m[4] );
}
function over_card( $css, $card ) { return preg_match( '/^rgba\((\d+), (\d+), (\d+), ([0-9.]+)\)$/', $css, $m ) ? C()::mix( $card, sprintf( '#%02x%02x%02x', $m[1], $m[2], $m[3] ), (float) $m[4] ) : $css; }
/** Every contrast the book promises, for one printed style: a list of the ones that fail. */
function failures( $theme, $style, $label ) {
	$bad = array();
	$v   = vars( $style );
	$light = 'light' === $theme;
	$stops = frame_stops( $v, $theme );
	$card  = $v['--twd-sb-card-bg'] ?? '';
	if ( ! $stops || 1 !== preg_match( '/^#[0-9a-f]{6}$/', $card ) ) { return array( "$label stops or card unreadable" ); }
	$gold = $v['--twd-sb-gold'];
	// on the card: headings and links, on their own pale tint, and the softer variants
	if ( C()::contrast( $gold, $card ) < 4.5 ) { $bad[] = "$label gold on card"; }
	foreach ( array( '--twd-sb-chip', '--twd-sb-chip-hover', '--twd-sb-chip-soft', '--twd-sb-btn-rest', '--twd-sb-btn-hover' ) as $k ) { if ( C()::contrast( $gold, over_card( $v[ $k ], $card ) ) < 4.5 && '--twd-sb-btn-hover' !== $k ) { $bad[] = "$label gold on $k"; } }
	if ( C()::contrast( $gold, over_card( $v['--twd-sb-btn-hover'], $card ) ) < 3 ) { $bad[] = "$label gold on btn-hover"; }
	if ( C()::contrast( over_card( $v['--twd-sb-gold-soft'], $card ), $card ) < 4.5 ) { $bad[] = "$label gold-soft on card"; }
	// the book's writing on the card (the book's own colours unless they would not read)
	$cream = $v['--twd-sb-cream'] ?? ( $light ? '#2b2540' : 'rgba(255, 248, 236, 0.92)' );
	$muted = $v['--twd-sb-cream-muted'] ?? ( $light ? 'rgba(43, 37, 64, 0.68)' : 'rgba(255, 248, 236, 0.62)' );
	if ( C()::contrast( over_card( $cream, $card ), $card ) < 4.5 ) { $bad[] = "$label cream on card"; }
	if ( C()::contrast( over_card( $muted, $card ), $card ) < 4.5 ) { $bad[] = "$label cream-muted on card"; }
	// the filled button
	if ( isset( $v['--twd-sb-primary'] ) ) {
		if ( C()::contrast( $v['--twd-sb-primary-ink'], $v['--twd-sb-primary'] ) < 4.5 ) { $bad[] = "$label primary ink"; }
		if ( C()::contrast( $v['--twd-sb-primary-ink'], $v['--twd-sb-primary-hover'] ) < 4.0 ) { $bad[] = "$label primary ink on hover"; }
	}
	// the saved picture carries the book's own writing
	if ( isset( $v['--twd-sb-export-bg'] ) ) {
		if ( $v['--twd-sb-export-bg'] !== $card ) { $bad[] = "$label export is not the card"; }
		if ( C()::contrast( $light ? '#2b2540' : '#fff8ec', $v['--twd-sb-export-bg'] ) < 7 ) { $bad[] = "$label export"; }
	}
	// on the frame: against EVERY colour of it
	$ctl = $v['--twd-sb-ctl']; $ink = $v['--twd-sb-read']; $ink_h = $v['--twd-sb-read-hover'];
	foreach ( $stops as $n => $stop ) {
		if ( C()::contrast( $ctl, $stop ) < 4.5 ) { $bad[] = "$label ctl on frame stop $n"; }
		if ( C()::contrast( $ink, $stop ) < 4.5 ) { $bad[] = "$label read ink on frame stop $n"; }
		if ( C()::contrast( $ink_h, $stop ) < 4.5 ) { $bad[] = "$label read hover on frame stop $n"; }
		// Share, Save, the Read link: small writing on the pale tint of the control colour
		foreach ( array( '--twd-sb-ctl-chip', '--twd-sb-ctl-chip-hover' ) as $k ) {
			$bg = over_card( $v[ $k ], $stop );
			if ( C()::contrast( $ctl, $bg ) < 4.5 ) { $bad[] = "$label ctl on $k at stop $n"; }
			if ( C()::contrast( $ink, $bg ) < 4.5 ) { $bad[] = "$label read ink on $k at stop $n"; }
		}
		// the 20px arrows
		foreach ( array( '--twd-sb-ctl-rest', '--twd-sb-ctl-hover' ) as $k ) { if ( C()::contrast( $ctl, over_card( $v[ $k ], $stop ) ) < 3 ) { $bad[] = "$label arrow on $k at stop $n"; } }
	}
	if ( 1 !== preg_match( '/^[-a-z0-9:;#%.,() ]*$/', $style ) ) { $bad[] = "$label characters"; }
	return $bad;
}

// ---------- 3000 random colours and every three-digit colour, both books: nothing can be made unreadable ----------
mt_srand( 149 );
$bad = array();
$n   = 0;
for ( $i = 0; $i < 3000; $i++ ) {
	$f = sprintf( '#%02x%02x%02x', mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) );
	$a = sprintf( '#%02x%02x%02x', mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) );
	foreach ( array( 'light', 'dark' ) as $theme ) {
		foreach ( array( array( $f, $a ), array( $f, '' ), array( '', $a ) ) as $pair ) { $n++; $bad = array_merge( $bad, failures( $theme, C()::style( $theme, $pair[0], $pair[1] ), "$theme frame={$pair[0]} accent={$pair[1]}" ) ); }
	}
}
t( "3000 random frame and accent colours (frame and accent, frame only, accent only), both books, $n styles: every piece of writing reads on what is really behind it", 0 === count( $bad ), implode( ' | ', array_slice( $bad, 0, 5 ) ) . ' (' . count( $bad ) . ' failures)' );
$bad = array();
$n   = 0;
for ( $r = 0; $r < 16; $r++ ) { for ( $g = 0; $g < 16; $g++ ) { for ( $b = 0; $b < 16; $b++ ) {
	$f = sprintf( '#%x%x%x', $r, $g, $b );
	foreach ( array( 'light', 'dark' ) as $theme ) { $n++; $bad = array_merge( $bad, failures( $theme, C()::style( $theme, $f, '' ), "$theme frame=$f" ) ); }
} } }
t( "every three-digit frame colour with no accent, both books, $n styles: every piece of writing reads on what is really behind it", 0 === count( $bad ), implode( ' | ', array_slice( $bad, 0, 5 ) ) . ' (' . count( $bad ) . ' failures)' );
$bad = array();
foreach ( array( '#000', '#fff', '#777', '#888', '#f00', '#0f0', '#00f', '#ff0', '#f0f', '#0ff', '#ffd166', '#8a5a00' ) as $f ) { foreach ( array( '#000', '#fff', '#777', '#f00', '#0f0', '#00f', '#ff0', '#ffd166', '#8a5a00', '#5fa36a' ) as $a ) { foreach ( array( 'light', 'dark' ) as $theme ) { $bad = array_merge( $bad, failures( $theme, C()::style( $theme, $f, $a ), "$theme frame=$f accent=$a" ) ); } } }
t( 'extreme and mid-tone pairs (black, white, grey, pure colours), both books: every piece of writing reads', 0 === count( $bad ), implode( ' | ', array_slice( $bad, 0, 5 ) ) . ' (' . count( $bad ) . ' failures)' );
t( 'a mid-grey frame is flat (no gradient that no writing could read on)', 1 === preg_match( '/linear-gradient\(180deg, (#[0-9a-f]{6}) 0%, \1 58%, \1 100%\)/', vars( C()::style( 'dark', '#777777', '' ) )['--twd-sb-frame-bg'] ) );
t( 'a plain frame keeps its gradient', 1 === preg_match( '/linear-gradient\(180deg, (#[0-9a-f]{6}) 0%, (#[0-9a-f]{6}) 58%, (#[0-9a-f]{6}) 100%\)/', vars( C()::style( 'light', '#cfe6d2', '' ) )['--twd-sb-frame-bg'], $mm ) && $mm[1] !== $mm[3] );

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
