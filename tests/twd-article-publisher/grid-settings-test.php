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
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
