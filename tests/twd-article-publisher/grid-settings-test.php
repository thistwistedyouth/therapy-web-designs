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

// Look colours: a trailing newline passes WordPress's own hex check and must not be stored (1.47.0 review).
TWD_AP_Grid_Settings::save( array( 'look_quote_color' => '#aabbcc' ) );
t( 'look colour: a plain hex is saved', '#aabbcc' === TWD_AP_Grid_Settings::get()['look_quote_color'] );
foreach ( array( "#112233\n", "#112233 ", "\n#112233", array( '#112233' ), 5, true ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'look_quote_color' => $bad ) );
	t( 'look colour: ' . json_encode( $bad ) . ' is not stored, the saved colour stays', '#aabbcc' === TWD_AP_Grid_Settings::get()['look_quote_color'] );
}
TWD_AP_Grid_Settings::save( array( 'look_quote_color' => '' ) );
t( 'look colour: an empty value still clears it', '' === TWD_AP_Grid_Settings::get()['look_quote_color'] );

// 1.48.0: heading sizes, topics alignment, related layout, bio page in every swipe book.
$d = TWD_AP_Grid_Settings::get();
t( 'defaults: no heading sizes set, topics left, related as a list, bio page not in every book', 0 === $d['look_h2_size'] && 0 === $d['look_h3_size'] && 0 === $d['look_h4_size'] && 'left' === $d['topics_align'] && 'list' === $d['related_layout'] && false === $d['profile_in_all_books'] );
TWD_AP_Grid_Settings::save( array( 'look_h2_size' => '32', 'look_h3_size' => 24, 'look_h4_size' => '19.4' ) );
$g = TWD_AP_Grid_Settings::get();
t( 'heading sizes: whole pixels are saved (a decimal is rounded)', 32 === $g['look_h2_size'] && 24 === $g['look_h3_size'] && 19 === $g['look_h4_size'], json_encode( array( $g['look_h2_size'], $g['look_h3_size'], $g['look_h4_size'] ) ) );
TWD_AP_Grid_Settings::save( array( 'look_h2_size' => '5', 'look_h3_size' => '400' ) );
$g = TWD_AP_Grid_Settings::get();
t( 'heading sizes: too small or too large is brought to the limit (14 and 64)', 14 === $g['look_h2_size'] && 64 === $g['look_h3_size'], json_encode( array( $g['look_h2_size'], $g['look_h3_size'] ) ) );
foreach ( array( 'big', '12px', array( 30 ), true, '--1px' ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'look_h4_size' => $bad ) );
	t( 'heading sizes: ' . json_encode( $bad ) . ' is ignored, the saved size stays', 19 === TWD_AP_Grid_Settings::get()['look_h4_size'] );
}
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#334455' ) );
t( 'heading sizes: saving something else leaves them alone', 14 === TWD_AP_Grid_Settings::get()['look_h2_size'] && 64 === TWD_AP_Grid_Settings::get()['look_h3_size'] && 19 === TWD_AP_Grid_Settings::get()['look_h4_size'] );
foreach ( array( PHP_INT_MAX, '9223372036854775807', 9.2e18, 1e30, '1e30', INF ) as $huge ) {
	TWD_AP_Grid_Settings::save( array( 'look_h3_size' => 22 ) );
	TWD_AP_Grid_Settings::save( array( 'look_h3_size' => $huge ) );
	t( 'heading sizes: a huge number (' . json_encode( is_float( $huge ) && is_infinite( $huge ) ? 'INF' : $huge ) . ') is brought to 64, not wrapped round to 14', 64 === TWD_AP_Grid_Settings::get()['look_h3_size'], json_encode( TWD_AP_Grid_Settings::get()['look_h3_size'] ) );
}
foreach ( array( '', 0, '0' ) as $clear ) {
	TWD_AP_Grid_Settings::save( array( 'look_h2_size' => 30 ) );
	TWD_AP_Grid_Settings::save( array( 'look_h2_size' => $clear ) );
	t( 'heading sizes: ' . json_encode( $clear ) . ' goes back to the plugin size', 0 === TWD_AP_Grid_Settings::get()['look_h2_size'] );
}
foreach ( array( 'center', 'left' ) as $a ) {
	TWD_AP_Grid_Settings::save( array( 'topics_align' => $a ) );
	t( 'topics row: ' . $a . ' is saved', $a === TWD_AP_Grid_Settings::get()['topics_align'] );
}
TWD_AP_Grid_Settings::save( array( 'topics_align' => 'center' ) );
foreach ( array( 'Center', 'centre', 'right', '', 0, true, array( 'left' ), '<script>' ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'topics_align' => $bad ) );
	t( 'topics row: ' . json_encode( $bad ) . ' is ignored, centred stays', 'center' === TWD_AP_Grid_Settings::get()['topics_align'] );
}
foreach ( array( 'grid', 'list' ) as $l ) {
	TWD_AP_Grid_Settings::save( array( 'related_layout' => $l ) );
	t( 'related layout: ' . $l . ' is saved', $l === TWD_AP_Grid_Settings::get()['related_layout'] );
}
TWD_AP_Grid_Settings::save( array( 'related_layout' => 'grid' ) );
foreach ( array( 'Grid', 'cards', '', 0, true, array( 'list' ), '<script>' ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'related_layout' => $bad ) );
	t( 'related layout: ' . json_encode( $bad ) . ' is ignored, grid stays', 'grid' === TWD_AP_Grid_Settings::get()['related_layout'] );
}
TWD_AP_Grid_Settings::save( array( 'profile_in_all_books' => true ) );
t( 'bio in every book: can be ticked', true === TWD_AP_Grid_Settings::get()['profile_in_all_books'] );
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#556677' ) );
t( 'bio in every book: and, grid layout and centred row, all stay when something else is saved', true === TWD_AP_Grid_Settings::get()['profile_in_all_books'] && 'grid' === TWD_AP_Grid_Settings::get()['related_layout'] && 'center' === TWD_AP_Grid_Settings::get()['topics_align'] );
TWD_AP_Grid_Settings::save( array( 'profile_in_all_books' => false ) );
t( 'bio in every book: can be unticked', false === TWD_AP_Grid_Settings::get()['profile_in_all_books'] );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
