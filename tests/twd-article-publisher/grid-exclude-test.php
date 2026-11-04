<?php
// Tests for keeping categories out of the main grid (1.52.0): the setting, its cleaning, the
// children of a hidden category, and which lists the rule applies to. Real TWD_AP_Grid_Settings
// and TWD_AP_Cards::query_args(), WordPress functions faked.
//   php tests/twd-article-publisher/grid-exclude-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['opt'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function absint( $n ) { return abs( (int) $n ); }
function get_term_children( $id, $tax ) { return array( 10 => array( 11, 12 ) )[ $id ] ?? array(); }
function sanitize_hex_color( $c ) { return is_string( $c ) && preg_match( '/^#([0-9a-f]{3}){1,2}$/i', $c ) ? $c : ''; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $u ) { return (string) $u; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function get_bloginfo( $k = '' ) { return ''; }
function apply_filters( $t, $v ) { return $v; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
require $plugin . 'includes/class-twd-ap-sanitizer.php';
require $plugin . 'includes/class-twd-ap-swipebook-colours.php';
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-cards.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }

t( 'default: nothing is hidden', array() === TWD_AP_Cards::excluded_category_ids() );
$a = TWD_AP_Cards::query_args( 6, 0 );
t( 'default: the query has no category exclusion (a site that never uses this is unchanged)', ! isset( $a['category__not_in'] ) );

$GLOBALS['opt']['twd_ap_grid_settings'] = array( 'grid_exclude_category_ids' => array( 5, '7', 5, 0, 'x' ) );
$ids = TWD_AP_Cards::excluded_category_ids();
t( 'the hidden ids are whole numbers, once each, junk dropped', array( 5, 7 ) === $ids, json_encode( $ids ) );

$GLOBALS['opt']['twd_ap_grid_settings'] = array( 'grid_exclude_category_ids' => array( 10 ) );
t( 'the children of a hidden category are hidden too', array( 10, 11, 12 ) === TWD_AP_Cards::excluded_category_ids() );

$a = TWD_AP_Cards::query_args( 6, 0 );
t( 'main grid: the query leaves out the hidden categories', array( 10, 11, 12 ) === ( $a['category__not_in'] ?? null ) );
$a = TWD_AP_Cards::query_args( 6, 0, 'anxiety' );
t( 'main grid search: still leaves them out', array( 10, 11, 12 ) === ( $a['category__not_in'] ?? null ) );
$a = TWD_AP_Cards::query_args( 6, 0, '', 'essays' );
t( 'a list for one category shows what it asks for, nothing hidden', ! isset( $a['category__not_in'] ) );
$a = TWD_AP_Cards::query_args( 6, 0, '', '', 'sleep' );
t( 'a list for one tag shows what it asks for, nothing hidden', ! isset( $a['category__not_in'] ) );

// Saving: only an array is accepted, cleaned; a save that does not mention it leaves it alone.
$out = TWD_AP_Grid_Settings::sanitize( array( 'grid_exclude_category_ids' => array( '3', 3, 'a', -4 ) ) );
t( 'saving cleans the list', array( 3, 4 ) === $out['grid_exclude_category_ids'], json_encode( $out['grid_exclude_category_ids'] ) );
$out = TWD_AP_Grid_Settings::sanitize( array( 'grid_exclude_category_ids' => 'all' ) );
t( 'saving a non-list is ignored and the saved list stays', array( 10 ) === $out['grid_exclude_category_ids'], json_encode( $out['grid_exclude_category_ids'] ) );
$out = TWD_AP_Grid_Settings::sanitize( array( 'read_more_color' => '#112233' ) );
t( 'saving something else leaves the list alone', array( 10 ) === $out['grid_exclude_category_ids'] );
$out = TWD_AP_Grid_Settings::sanitize( array( 'grid_exclude_category_ids' => array() ) );
t( 'an empty list clears it', array() === $out['grid_exclude_category_ids'] );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
