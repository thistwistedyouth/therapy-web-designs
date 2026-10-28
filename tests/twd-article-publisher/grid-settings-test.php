<?php
// Tests for the grid settings that choose which categories the "Browse by topic" row lists.
// Real TWD_AP_Grid_Settings, WordPress option functions faked.
//   php tests/twd-article-publisher/grid-settings-test.php
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
require $plugin . 'includes/class-twd-ap-grid-settings.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }

t( 'default: the topic row lists every category', 'all' === TWD_AP_Grid_Settings::get()['topic_category_ids'] );
TWD_AP_Grid_Settings::save( array( 'topic_category_ids' => array( '3', 5, '3', 'x', 0 ) ) );
t( 'saving a list keeps whole, unique, non-zero category ids', array( 3, 5 ) === TWD_AP_Grid_Settings::get()['topic_category_ids'], json_encode( TWD_AP_Grid_Settings::get()['topic_category_ids'] ) );
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#7a9b76' ) );
t( 'saving something else leaves the topic choice alone', array( 3, 5 ) === TWD_AP_Grid_Settings::get()['topic_category_ids'] && '#7a9b76' === TWD_AP_Grid_Settings::get()['accent_color'] );
TWD_AP_Grid_Settings::save( array( 'topic_category_ids' => array() ) );
t( 'an empty list is kept (the row is hidden)', array() === TWD_AP_Grid_Settings::get()['topic_category_ids'] );
TWD_AP_Grid_Settings::save( array( 'topic_category_ids' => 'all' ) );
t( 'all goes back to every category', 'all' === TWD_AP_Grid_Settings::get()['topic_category_ids'] );
TWD_AP_Grid_Settings::save( array( 'topic_category_ids' => '<script>' ) );
t( 'junk is ignored, not stored', 'all' === TWD_AP_Grid_Settings::get()['topic_category_ids'] );

// Card image shape (1.42.0).
t( 'shape: the default is the original wide strip', 'wide' === TWD_AP_Grid_Settings::get()['thumb_shape'] );
TWD_AP_Grid_Settings::save( array( 'thumb_shape' => '4-3' ) );
t( 'shape: 4:3 is saved', '4-3' === TWD_AP_Grid_Settings::get()['thumb_shape'] );
TWD_AP_Grid_Settings::save( array( 'thumb_shape' => 'square' ) );
t( 'shape: square is saved', 'square' === TWD_AP_Grid_Settings::get()['thumb_shape'] );
foreach ( array( '16-9', 'Square', 'SQUARE', ' square', '4:3', '<script>', '', 0, true, array( 'square' ) ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'thumb_shape' => $bad ) );
	t( 'shape: ' . json_encode( $bad ) . ' is ignored, the saved shape stays', 'square' === TWD_AP_Grid_Settings::get()['thumb_shape'] );
}
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#112233' ) );
t( 'shape: saving something else leaves it alone', 'square' === TWD_AP_Grid_Settings::get()['thumb_shape'] );
TWD_AP_Grid_Settings::save( array( 'thumb_shape' => 'wide' ) );
t( 'shape: wide can be chosen again', 'wide' === TWD_AP_Grid_Settings::get()['thumb_shape'] );
t( 'shape class: wide prints none', '' === TWD_AP_Grid_Settings::thumb_shape_class( 'wide' ) );
t( 'shape class: 4:3', 'twd-ap-thumbs-4-3' === TWD_AP_Grid_Settings::thumb_shape_class( '4-3' ) );
t( 'shape class: square', 'twd-ap-thumbs-square' === TWD_AP_Grid_Settings::thumb_shape_class( 'square' ) );
t( 'shape class: anything else prints none', '' === TWD_AP_Grid_Settings::thumb_shape_class( 'huge' ) && '' === TWD_AP_Grid_Settings::thumb_shape_class( null ) && '' === TWD_AP_Grid_Settings::thumb_shape_class( array() ) );

// Shortcode placement and line endings (1.46.0).
$d = TWD_AP_Grid_Settings::get();
t( 'defaults: related block automatic, top button automatic, line endings tidied', 'auto' === $d['related_place'] && false === $d['swipebook_top_shortcode'] && true === $d['look_tidy_lines'] );
foreach ( array( 'shortcode', 'off', 'auto' ) as $place ) {
	TWD_AP_Grid_Settings::save( array( 'related_place' => $place ) );
	t( 'related place: ' . $place . ' is saved', $place === TWD_AP_Grid_Settings::get()['related_place'] );
}
TWD_AP_Grid_Settings::save( array( 'related_place' => 'shortcode' ) );
foreach ( array( 'Shortcode', 'everywhere', '', 0, true, array( 'off' ), '<script>' ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'related_place' => $bad ) );
	t( 'related place: ' . json_encode( $bad ) . ' is ignored, the saved place stays', 'shortcode' === TWD_AP_Grid_Settings::get()['related_place'] );
}
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#445566' ) );
t( 'related place: saving something else leaves it alone', 'shortcode' === TWD_AP_Grid_Settings::get()['related_place'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_top_shortcode' => true ) );
t( 'top button by shortcode: can be ticked', true === TWD_AP_Grid_Settings::get()['swipebook_top_shortcode'] );
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#112233' ) );
t( 'top button by shortcode: saving something else leaves it ticked', true === TWD_AP_Grid_Settings::get()['swipebook_top_shortcode'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_top_shortcode' => false ) );
t( 'top button by shortcode: can be unticked', false === TWD_AP_Grid_Settings::get()['swipebook_top_shortcode'] );
TWD_AP_Grid_Settings::save( array( 'look_tidy_lines' => false ) );
t( 'tidy line endings: can be switched off', false === TWD_AP_Grid_Settings::get()['look_tidy_lines'] );
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#334455' ) );
t( 'tidy line endings: saving something else leaves it off', false === TWD_AP_Grid_Settings::get()['look_tidy_lines'] );
TWD_AP_Grid_Settings::save( array( 'look_tidy_lines' => true ) );
t( 'tidy line endings: can be switched on again', true === TWD_AP_Grid_Settings::get()['look_tidy_lines'] );

// Swipe book colours (1.47.0).
t( 'swipe book colours: the default is dark, the original look', 'dark' === TWD_AP_Grid_Settings::get()['swipebook_theme'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_theme' => 'light' ) );
t( 'swipe book colours: light is saved', 'light' === TWD_AP_Grid_Settings::get()['swipebook_theme'] );
foreach ( array( 'Light', 'LIGHT', ' light', 'blue', '', 0, true, null, array( 'dark' ), '<script>' ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'swipebook_theme' => $bad ) );
	t( 'swipe book colours: ' . json_encode( $bad ) . ' is ignored, light stays', 'light' === TWD_AP_Grid_Settings::get()['swipebook_theme'] );
}
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#223344' ) );
t( 'swipe book colours: saving something else leaves it on light', 'light' === TWD_AP_Grid_Settings::get()['swipebook_theme'] );
TWD_AP_Grid_Settings::save( array( 'swipebook_theme' => 'dark' ) );
t( 'swipe book colours: dark can be chosen again', 'dark' === TWD_AP_Grid_Settings::get()['swipebook_theme'] );
t( 'swipe book colours: only dark and light exist', array( 'dark', 'light' ) === TWD_AP_Grid_Settings::SWIPEBOOK_THEMES );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
