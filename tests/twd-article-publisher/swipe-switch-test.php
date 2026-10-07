<?php
// Tests for the per-article swipe book switch, the "New Article only on shortcode pages" default,
// and where the switch is enforced. Real plugin classes, WordPress functions faked.
//   php tests/twd-article-publisher/swipe-switch-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

class WP_Error { public $c; public $m; public $d; function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; $this->d = $d; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s ); }
function esc_url( $s ) { return htmlspecialchars( $s ); }
function esc_attr( $s ) { return htmlspecialchars( $s ); }
function add_filter() {}
function add_action() {}
function apply_filters( $t, $v ) { return $v; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_unslash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function get_permalink( $p ) { return 'https://example.test/article-' . ( is_object( $p ) ? $p->ID : $p ) . '/'; }
function get_the_title( $p ) { return 'Title ' . $p->ID; }
function is_admin() { return false; }
function is_singular() { return true; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_query_var( $k ) { return $GLOBALS['qv'][ $k ] ?? ''; }
function get_queried_object() { return $GLOBALS['posts'][ $GLOBALS['queried'] ]; }
$GLOBALS['qv'] = array(); $GLOBALS['queried'] = 0; $GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['opt'] = array(); $GLOBALS['q'] = array();
function get_post( $id = null ) { if ( null === $id ) { $id = $GLOBALS['current']; } return $GLOBALS['posts'][ $id ] ?? null; }
function get_post_meta( $id, $k, $single = true ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function get_posts( $args ) { $GLOBALS['q'] = $args; return array_values( array_filter( $GLOBALS['posts'], function ( $p ) use ( $args ) { return 'publish' === $p->post_status && ! in_array( $p->ID, $args['post__not_in'], true ) && ! ( isset( $GLOBALS['meta'][ $p->ID ]['_twd_ap_swipebook_off'] ) ); } ) ); }
function rest_ensure_response( $x ) { return $x; }
class TWD_AP_Grid_Settings { static function get() { return array( 'swipebook_color' => '#5b8a72' ); } }
class TWD_AP_Shortcode { static $on = false; static function rendered_on_this_page() { return self::$on; } }
class TWD_AP_Sanitizer { static function strip_dashes( $s ) { return $s; } }
require $plugin . 'includes/class-twd-ap-settings.php';
require $plugin . 'includes/class-twd-ap-swipebook.php';
require $plugin . 'includes/class-twd-ap-summary-book.php';
require $plugin . 'includes/class-twd-ap-rest.php';
require $plugin . 'includes/class-twd-ap-frontend.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function mkpost( $id, $status = 'publish' ) { $GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => 'post', 'post_status' => $status, 'post_content' => '<p>x</p>', 'post_title' => 'T', 'post_excerpt' => '', 'post_date_gmt' => '2026-01-01 00:00:00' ); }

// ---------- the switch itself ----------
mkpost( 1 ); mkpost( 2 ); mkpost( 3 );
t( 'is_enabled: an article with no meta (existing articles) is ON', TWD_AP_Swipebook::is_enabled( 1 ) );
$GLOBALS['meta'][2]['_twd_ap_swipebook_off'] = 1;
t( 'is_enabled: off when the off meta is set', ! TWD_AP_Swipebook::is_enabled( 2 ) );
t( 'is_enabled: only that article is off', TWD_AP_Swipebook::is_enabled( 1 ) && TWD_AP_Swipebook::is_enabled( 3 ) );

// ---------- the buttons ----------
$GLOBALS['current'] = 1;
$on = TWD_AP_Swipebook::instance()->append_button( '<p>body</p>' );
t( 'swipe button: shown for an article that is on', false !== strpos( $on, 'View as a swipe book' ) );
$GLOBALS['current'] = 2;
t( 'swipe button: hidden for an article that is off', '<p>body</p>' === TWD_AP_Swipebook::instance()->append_button( '<p>body</p>' ) );
$GLOBALS['meta'][1]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
$GLOBALS['meta'][2]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
$GLOBALS['current'] = 1;
t( 'summary book button: shown when published and switch on', false !== strpos( TWD_AP_Summary_Book::instance()->append_button( '' ), 'View Summary Book' ) );
$GLOBALS['current'] = 2;
t( 'summary book button: hidden when published but switch off', '' === TWD_AP_Summary_Book::instance()->append_button( '' ) );

// ---------- direct links: the standalone pages must not render ----------
// maybe_render() ends in exit once it renders, so only the switched-off case can be run here:
// it must simply return (a normal article page), not render the book and not fatal.
$ref = function ( $class ) { return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor(); };
$sb = $ref( 'TWD_AP_Swipebook' );
$GLOBALS['queried'] = 2; $GLOBALS['qv'] = array( 'twd_ap_swipebook' => '1' );
ob_start(); $r = $sb->maybe_render(); $out = ob_get_clean();
t( '?twd_ap_swipebook=1 on a switched-off article: not rendered, normal page', null === $r && '' === $out );
$GLOBALS['qv'] = array( 'twd_ap_summary_book' => '1' );
ob_start(); $r = $ref( 'TWD_AP_Summary_Book' )->maybe_render(); $out = ob_get_clean();
t( '?twd_ap_summary_book=1 on a switched-off article: not rendered, even though published', null === $r && '' === $out );
$GLOBALS['qv'] = array();

// ---------- public data feeds ----------
$rest = $ref( 'TWD_AP_REST' );
$e = $rest->get_swipebook( array( 'id' => 2 ) );
t( 'REST /articles/{id}/swipebook: 404 when switched off', is_wp_error( $e ) && 404 === $e->d['status'] );
$e = $rest->get_summary_book_public( array( 'id' => 2 ) );
t( 'REST /articles/{id}/summary-book: 404 when switched off, even if published', is_wp_error( $e ) && 404 === $e->d['status'] && false !== strpos( $e->m, 'could not be found' ) );

// ---------- saving the switch ----------
$m = new ReflectionMethod( 'TWD_AP_REST', 'after_save' ); $m->setAccessible( true );
$m->invoke( $rest, 3, array( 'swipebook_enabled' => false ) );
t( 'save: unticked stores the off flag', ! TWD_AP_Swipebook::is_enabled( 3 ) && 1 === $GLOBALS['meta'][3]['_twd_ap_swipebook_off'] );
$m->invoke( $rest, 3, array( 'swipebook_enabled' => true ) );
t( 'save: ticked removes it again', TWD_AP_Swipebook::is_enabled( 3 ) && ! isset( $GLOBALS['meta'][3]['_twd_ap_swipebook_off'] ) );
$m->invoke( $rest, 2, array( 'title' => 'unrelated edit' ) );
t( 'save: a save that does not carry the switch leaves it alone', ! TWD_AP_Swipebook::is_enabled( 2 ) );
$m->invoke( $rest, 1, array( 'swipebook_enabled' => '' ) );
t( 'save: false-y string also means off', ! TWD_AP_Swipebook::is_enabled( 1 ) );
$m->invoke( $rest, 1, array( 'swipebook_enabled' => true ) );

// ---------- loading the switch into the edit popup ----------
function wp_get_post_categories() { return array(); }
function get_post_thumbnail_id() { return 0; }
function wp_get_attachment_image_url() { return ''; }
function wp_get_post_tags() { return array(); }
$fp = new ReflectionMethod( 'TWD_AP_REST', 'format_post' ); $fp->setAccessible( true );
t( 'edit data: swipebook_enabled true for an article that is on', true === $fp->invoke( $rest, 1, true )['swipebook_enabled'] );
t( 'edit data: swipebook_enabled false for an article that is off', false === $fp->invoke( $rest, 2, true )['swipebook_enabled'] );

// ---------- grid cards: the edit pencil permission and the per-article edit link ----------
function get_the_category() { return array( (object) array( 'name' => 'Anxiety' ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_trim_words( $s ) { return $s; }
function get_the_date() { return '1 Oct 2026'; }
function get_edit_post_link( $id ) { return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
$GLOBALS['can_edit'] = array( 1 => true, 3 => false );
function current_user_can( $cap, $id = 0 ) { return 'edit_post' === $cap && ! empty( $GLOBALS['can_edit'][ $id ] ); }
$card = new ReflectionMethod( 'TWD_AP_REST', 'format_article_card' ); $card->setAccessible( true );
t( 'grid card: can_edit true for an article this person can edit', true === $card->invoke( $rest, 1 )['can_edit'] );
t( 'grid card: can_edit false for one they cannot edit', false === $card->invoke( $rest, 3 )['can_edit'] );
t( 'grid card: can_edit is a real boolean (never leaks anything else)', is_bool( $card->invoke( $rest, 1 )['can_edit'] ) && is_bool( $card->invoke( $rest, 3 )['can_edit'] ) );
$GLOBALS['can_edit'] = array();
t( 'grid card: a logged-out visitor gets can_edit false on every card', false === $card->invoke( $rest, 1 )['can_edit'] );
t( 'edit data: carries the edit link for THAT article', 'https://example.test/wp-admin/post.php?post=2&action=edit' === $fp->invoke( $rest, 2, true )['edit_url'] );
t( 'public (non-edit) data does not carry the edit link', ! isset( $fp->invoke( $rest, 2, false )['edit_url'] ) );

// ---------- "More Articles" slide skips switched-off articles ----------
$rel = new ReflectionMethod( 'TWD_AP_Swipebook', 'build_related_slide' ); $rel->setAccessible( true );
mkpost( 10 ); mkpost( 11 ); mkpost( 12 );
$GLOBALS['meta'][11]['_twd_ap_swipebook_off'] = 1;
$slide = $rel->invoke( $sb, $GLOBALS['posts'][10] );
$ids = array_map( function ( $i ) { return $i['id']; }, $slide['items'] );
t( 'related slide (automatic): switched-off article is not listed', ! in_array( 11, $ids, true ) && in_array( 12, $ids, true ) );
t( 'related slide (automatic): query asks for NOT EXISTS on the off flag', 'NOT EXISTS' === $GLOBALS['q']['meta_query'][0]['compare'] && '_twd_ap_swipebook_off' === $GLOBALS['q']['meta_query'][0]['key'] );
$GLOBALS['meta'][10]['_twd_ap_related_ids'] = array( 11, 12 );
$slide = $rel->invoke( $sb, $GLOBALS['posts'][10] );
$ids = array_map( function ( $i ) { return $i['id']; }, $slide['items'] );
t( 'related slide (hand-picked): switched-off article is skipped', array( 12 ) === $ids );

// ---------- New Article button: shortcode pages only by default ----------
$ft = $ref( 'TWD_AP_Frontend' );
$sn = new ReflectionMethod( 'TWD_AP_Frontend', 'should_show_new_button' ); $sn->setAccessible( true );
$GLOBALS['opt'] = array(); TWD_AP_Shortcode::$on = false;
t( 'default setting is shortcode_page', 'shortcode_page' === TWD_AP_Settings::get_settings()['show_everywhere'] );
t( 'default: no New Article button on a page without the shortcode', false === $sn->invoke( $ft ) );
TWD_AP_Shortcode::$on = true;
t( 'default: New Article button shows on a page WITH the shortcode', true === $sn->invoke( $ft ) );
$GLOBALS['opt']['twd_ap_settings'] = array( 'show_everywhere' => 'everywhere' ); TWD_AP_Shortcode::$on = false;
t( 'existing site saved as "everywhere": left alone, still everywhere', 'everywhere' === TWD_AP_Settings::get_settings()['show_everywhere'] && true === $sn->invoke( $ft ) );
$GLOBALS['opt']['twd_ap_settings'] = array( 'show_everywhere' => 1 );
t( 'old pre-1.15 saved value 1 still means everywhere', 'everywhere' === TWD_AP_Settings::get_settings()['show_everywhere'] );
$GLOBALS['opt']['twd_ap_settings'] = array( 'show_everywhere' => 0 );
t( 'old pre-1.15 saved value 0 still means nowhere', 'nowhere' === TWD_AP_Settings::get_settings()['show_everywhere'] );
$GLOBALS['opt']['twd_ap_settings'] = array( 'show_everywhere' => 'nowhere' ); TWD_AP_Shortcode::$on = true;
t( 'nowhere: never shows, even with the shortcode', false === $sn->invoke( $ft ) );
$GLOBALS['opt']['twd_ap_settings'] = array( 'allowed_roles' => array( 'editor' ) );
t( 'a site that saved other settings but never this one gets the new default', 'shortcode_page' === TWD_AP_Settings::get_settings()['show_everywhere'] );
$main = file_get_contents( $plugin . 'twd-article-publisher.php' );
t( 'fresh installs: activation stores shortcode_page, not 1', false !== strpos( $main, "'show_everywhere'  => 'shortcode_page'" ) && false === strpos( $main, "'show_everywhere'  => 1" ) );
$sv = $ref( 'TWD_AP_Settings' ); $san = new ReflectionMethod( 'TWD_AP_Settings', 'sanitize' );
$GLOBALS['opt'] = array();
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $s ) ); }
function sanitize_text_field( $s ) { return $s; }
function sanitize_textarea_field( $s ) { return $s; }
t( 'settings save: missing or invalid scope falls back to shortcode_page', 'shortcode_page' === $san->invoke( $sv, array() )['show_everywhere'] && 'shortcode_page' === $san->invoke( $sv, array( 'show_everywhere' => 'bogus' ) )['show_everywhere'] );
t( 'settings save: a chosen scope is kept', 'everywhere' === $san->invoke( $sv, array( 'show_everywhere' => 'everywhere' ) )['show_everywhere'] );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
