<?php
// Tests that the swipe book colours choice (1.47.0) reaches the book and the settings popup: the header fields
// both books share carry 'theme', 'light' only when the owner chose it, and /grid-settings reports it. Real TWD_AP_Swipebook::get_header_fields()
// and TWD_AP_Grid_Settings, WordPress functions faked.
//   php tests/twd-article-publisher/swipe-theme-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['opt'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $c ) ? $c : ''; }
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
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-swipebook.php';
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
require $plugin . 'includes/class-twd-ap-rest.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }

$f = TWD_AP_Swipebook::get_header_fields( 'Swipe Book' );
t( 'untouched settings: the book is dark', 'dark' === $f['theme'], json_encode( $f ) );
TWD_AP_Grid_Settings::save( array( 'swipebook_theme' => 'light' ) );
$f = TWD_AP_Swipebook::get_header_fields( 'Swipe Book' );
t( 'light chosen: the header fields say light', 'light' === $f['theme'] );
t( 'the other header fields are unchanged', 'Example Site' === $f['siteName'] && 'Swipe Book' === $f['label'] && true === $f['includeBranding'] );
// A bad stored value (hand-edited option) can never reach the page as anything but dark or light.
$GLOBALS['opt'][ TWD_AP_Grid_Settings::OPTION_KEY ] = array( 'swipebook_theme' => '"><script>' );
$f = TWD_AP_Swipebook::get_header_fields( 'Swipe Book' );
t( 'a stored value that is neither dark nor light comes out as dark', 'dark' === $f['theme'], json_encode( $f['theme'] ) );

// What the settings popup reads back (/grid-settings).
$rest = ( new ReflectionClass( 'TWD_AP_REST' ) )->newInstanceWithoutConstructor();
unset( $GLOBALS['opt'][ TWD_AP_Grid_Settings::OPTION_KEY ] );
t( 'REST: an untouched site reports dark', 'dark' === $rest->get_grid_settings()['swipebook_theme'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_theme' => 'light' ) );
t( 'REST: light is reported once chosen', 'light' === $rest->get_grid_settings()['swipebook_theme'] );
$GLOBALS['opt'][ TWD_AP_Grid_Settings::OPTION_KEY ] = array( 'swipebook_theme' => '"><script>' );
t( 'REST: a stored value that is neither dark nor light is reported as dark', 'dark' === $rest->get_grid_settings()['swipebook_theme'] );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
