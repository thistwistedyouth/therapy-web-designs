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
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_url( $s ) { return htmlspecialchars( $s ); }
function esc_attr( $s ) { return htmlspecialchars( $s ); }
function add_filter( $tag = '', $cb = null, $prio = 10 ) { $GLOBALS['registered'][] = array( $tag, is_array( $cb ) ? $cb[1] : $cb, $prio ); }
function add_action() {}
function add_shortcode( $tag, $cb ) { $GLOBALS['shortcodes'][ $tag ] = $cb; }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_localize_script() {}
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function esc_url_raw( $s ) { return $s; }
define( 'TWD_AP_URL', 'https://example.test/plugin/' );
define( 'TWD_AP_VERSION', 'test' );
$GLOBALS['shortcodes'] = array(); $GLOBALS['enqueued'] = array();
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
class TWD_AP_Grid_Settings { static $pos = 'both'; static $top = false; static function get() { return array( 'swipebook_color' => '#5b8a72', 'swipebook_button_position' => self::$pos, 'swipebook_top_shortcode' => self::$top ); } }
class TWD_AP_Shortcode { static $on = false; static function rendered_on_this_page() { return self::$on; } }
class TWD_AP_Sanitizer { static function strip_dashes( $s ) { return $s; } }
require $plugin . 'includes/class-twd-ap-settings.php';
require $plugin . 'includes/class-twd-ap-article-style.php';
require $plugin . 'includes/class-twd-ap-swipebook.php';
require $plugin . 'includes/class-twd-ap-summary-book.php';
require $plugin . 'includes/class-twd-ap-cards.php';
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
t( 'swipe button: shown at the end for an article that is on, worded as a share', false !== strpos( $on, 'Share as a swipe book' ) && false === strpos( $on, 'View as a swipe book' ) );
$GLOBALS['current'] = 2;
t( 'swipe button: hidden for an article that is off', '<p>body</p>' === TWD_AP_Swipebook::instance()->append_button( '<p>body</p>' ) );
$GLOBALS['meta'][1]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
$GLOBALS['meta'][2]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
$GLOBALS['current'] = 1;
t( 'summary book button: shown when published and switch on', false !== strpos( TWD_AP_Summary_Book::instance()->append_button( '' ), 'View Summary Book' ) );
$GLOBALS['current'] = 2;
t( 'summary book button: hidden when published but switch off', '' === TWD_AP_Summary_Book::instance()->append_button( '' ) );

// ---------- where the button goes: under the title, at the end, or both ----------
$sbi = TWD_AP_Swipebook::instance(); $smi = TWD_AP_Summary_Book::instance();
$GLOBALS['current'] = 1; $GLOBALS['qv'] = array();
$GLOBALS['meta'][1]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
TWD_AP_Grid_Settings::$pos = 'both';
$top = $sbi->prepend_buttons( '<p>body</p>' );
t( 'position both: the button is the first thing in the article text, inside a top wrapper', 1 === preg_match( '/^<div class="twd-ap-swipebook-top"><p class="twd-ap-swipebook-row"><a class="twd-ap-swipebook-btn"/', $top ) && '</div><p>body</p>' === substr( $top, -17 ) );
t( 'position both: the Summary Book button is beside it at the top when the article has one', false !== strpos( $top, 'twd-ap-summary-book-btn' ) && strpos( $top, 'View as a swipe book' ) < strpos( $top, 'View Summary Book' ) );
t( 'position both: the bottom buttons are still added after the text', false !== strpos( $sbi->append_button( '<p>body</p>' ), '<p>body</p><p class="twd-ap-swipebook-row twd-ap-swipebook-row-end">' ) && false !== strpos( $smi->append_button( '<p>body</p>' ), 'View Summary Book' ) );
TWD_AP_Grid_Settings::$pos = 'top';
t( 'position top: the buttons are at the top and not repeated at the end', false !== strpos( $sbi->prepend_buttons( '<p>b</p>' ), 'twd-ap-swipebook-btn' ) && '<p>b</p>' === $sbi->append_button( '<p>b</p>' ) && '<p>b</p>' === $smi->append_button( '<p>b</p>' ) );
TWD_AP_Grid_Settings::$pos = 'bottom';
t( 'position bottom: nothing at the top, the buttons at the end as before this version', '<p>b</p>' === $sbi->prepend_buttons( '<p>b</p>' ) && false !== strpos( $sbi->append_button( '<p>b</p>' ), 'twd-ap-swipebook-btn' ) );
TWD_AP_Grid_Settings::$pos = 'sideways';
t( 'an unknown position value falls back to both', 'both' === TWD_AP_Swipebook::button_position() && false !== strpos( $sbi->prepend_buttons( '<p>b</p>' ), 'twd-ap-swipebook-btn' ) );
TWD_AP_Grid_Settings::$pos = 'both';
$GLOBALS['meta'][1]['_twd_ap_summary_book_published'] = '';
t( 'top: no Summary Book button when the article has no published deck', false === strpos( $sbi->prepend_buttons( '<p>b</p>' ), 'View Summary Book' ) );
$GLOBALS['current'] = 2;
$GLOBALS['meta'][2]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
t( 'top: an article with the swipe book switched off gets neither button at the top', '<p>b</p>' === $sbi->prepend_buttons( '<p>b</p>' ) );
$GLOBALS['current'] = 1;
$GLOBALS['qv'] = array( 'twd_ap_swipebook' => '1' );
t( 'top: not added when the swipe book page itself is being shown', '<p>b</p>' === $sbi->prepend_buttons( '<p>b</p>' ) );
$GLOBALS['qv'] = array();

$reg = array_map( 'json_encode', $GLOBALS['registered'] ?? array() );
t( 'registered: the top buttons go on the_content at priority 11, the bottom ones at 15 and 16, all before the wrapper (20)', in_array( json_encode( array( 'the_content', 'prepend_buttons', 11 ) ), $reg, true ) && in_array( json_encode( array( 'the_content', 'append_button', 15 ) ), $reg, true ) && in_array( json_encode( array( 'the_content', 'append_button', 16 ) ), $reg, true ), implode( ' ', $reg ) );

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
function get_the_category() { return array( (object) array( 'term_id' => 2, 'name' => 'Anxiety' ) ); }
function post_password_required() { return false; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_trim_words( $s ) { return $s; }
function get_the_date() { return '1 Oct 2026'; }
function get_edit_post_link( $id ) { return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
$GLOBALS['can_edit'] = array( 1 => true, 3 => false );
function current_user_can( $cap, $id = 0 ) { return 'edit_post' === $cap && ! empty( $GLOBALS['can_edit'][ $id ] ); }
t( 'grid card: can_edit true for an article this person can edit', true === TWD_AP_Cards::format( 1 )['can_edit'] );
t( 'grid card: can_edit false for one they cannot edit', false === TWD_AP_Cards::format( 3 )['can_edit'] );
t( 'grid card: can_edit is a real boolean (never leaks anything else)', is_bool( TWD_AP_Cards::format( 1 )['can_edit'] ) && is_bool( TWD_AP_Cards::format( 3 )['can_edit'] ) );
$GLOBALS['can_edit'] = array();
t( 'grid card: a logged-out visitor gets can_edit false on every card', false === TWD_AP_Cards::format( 1 )['can_edit'] );
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

if ( getenv( 'TWD_SWIPE_DUMP' ) ) {
	$GLOBALS['current'] = 1; $GLOBALS['qv'] = array();
	$GLOBALS['meta'][1]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
	$out = array();
	foreach ( array( 'both', 'top', 'bottom' ) as $pos ) {
		TWD_AP_Grid_Settings::$pos = $pos;
		$body = $sbi->prepend_buttons( '<h2>First heading</h2><p>Article body text.</p>' );
		$out[ $pos ] = $smi->append_button( $sbi->append_button( $body ) );
	}
	file_put_contents( getenv( 'TWD_SWIPE_DUMP' ), json_encode( $out ) );
}
// ---------- the end button and the shortcode (1.46.0) ----------
$GLOBALS['current'] = 1; $GLOBALS['qv'] = array(); TWD_AP_Grid_Settings::$pos = 'both'; TWD_AP_Grid_Settings::$top = false;
$GLOBALS['meta'][1]['_twd_ap_summary_book_published'] = array( array( 'type' => 'text', 'text' => 'x' ) );
$endhtml = $sbi->append_button( '' );
t( 'end button: "Share as a swipe book", in a row marked as the end one (the stylesheet puts it on the right)', 1 === preg_match( '/^<p class="twd-ap-swipebook-row twd-ap-swipebook-row-end"><a class="twd-ap-swipebook-btn"[^>]*>Share as a swipe book &#8599;<\/a><\/p>$/', $endhtml ) );
$endsum = $smi->append_button( '' );
t( 'end button: the Summary Book one sits on the right too, its words unchanged', false !== strpos( $endsum, 'twd-ap-swipebook-row-end' ) && false !== strpos( $endsum, 'View Summary Book' ) );
t( 'top button: still "View as a swipe book", not marked as the end row', false !== strpos( $sbi->prepend_buttons( '' ), 'View as a swipe book' ) && false === strpos( $sbi->prepend_buttons( '' ), 'row-end' ) );
// the "place it with a shortcode" tick
TWD_AP_Grid_Settings::$top = true;
t( 'top shortcode tick: no automatic button under the title', '<p>b</p>' === $sbi->prepend_buttons( '<p>b</p>' ) );
t( 'top shortcode tick: the button at the end is still added (position both)', false !== strpos( $sbi->append_button( '<p>b</p>' ), 'Share as a swipe book' ) );
TWD_AP_Grid_Settings::$pos = 'top';
t( 'top shortcode tick with "under the title only": nothing is added automatically at all', '<p>b</p>' === $sbi->prepend_buttons( '<p>b</p>' ) && '<p>b</p>' === $sbi->append_button( '<p>b</p>' ) );
TWD_AP_Grid_Settings::$pos = 'both'; TWD_AP_Grid_Settings::$top = false;
t( 'top shortcode tick off: the automatic button is back', false !== strpos( $sbi->prepend_buttons( '<p>b</p>' ), 'View as a swipe book' ) );
// [twd_swipebook_button]
t( 'shortcode [twd_swipebook_button] is registered', isset( $GLOBALS['shortcodes']['twd_swipebook_button'] ) );
$sc = function ( $atts = array() ) { return $GLOBALS['shortcodes']['twd_swipebook_button']( $atts ); };
$out = $sc();
t( 'shortcode: the top wrapper with the swipe book button and, for a published deck, the Summary Book one', 1 === preg_match( '/^<div class="twd-ap-swipebook-top"><p class="twd-ap-swipebook-row"><a class="twd-ap-swipebook-btn"[^>]*>View as a swipe book/', $out ) && false !== strpos( $out, 'twd-ap-summary-book-btn' ) && '</div>' === substr( $out, -6 ) );
TWD_AP_Grid_Settings::$top = true; TWD_AP_Grid_Settings::$pos = 'bottom';
t( 'shortcode: prints where it is placed whatever the position and tick settings say', $out === $sc() );
TWD_AP_Grid_Settings::$top = false; TWD_AP_Grid_Settings::$pos = 'both';
$GLOBALS['enqueued'] = array(); $sc();
t( 'shortcode: loads the overlay script and the article stylesheet (so it works on a page that is not an article)', in_array( 'twd-ap-swipebook-inline', $GLOBALS['enqueued'], true ) && in_array( 'twd-ap-article-style', $GLOBALS['enqueued'], true ), json_encode( $GLOBALS['enqueued'] ) );
mkpost( 7 );
$o7 = $sc( array( 'id' => '7' ) );
t( 'shortcode: id names another article', false !== strpos( $o7, 'data-twd-ap-post-id="7"' ) && false === strpos( $o7, 'data-twd-ap-post-id="1"' ) );
t( 'shortcode: an article with the swipe book switched off prints nothing', '' === $sc( array( 'id' => '2' ) ) );
mkpost( 8, 'draft' );
t( 'shortcode: an unpublished article prints nothing', '' === $sc( array( 'id' => '8' ) ) );
t( 'shortcode: an unknown id prints nothing', '' === $sc( array( 'id' => '999' ) ) );
$GLOBALS['posts'][9] = (object) array( 'ID' => 9, 'post_type' => 'page', 'post_status' => 'publish' );
t( 'shortcode: a page (not an article) prints nothing', '' === $sc( array( 'id' => '9' ) ) );
$GLOBALS['current'] = 0;
t( 'shortcode: with no current article and no id prints nothing and does not fail', '' === $sc() );
$GLOBALS['current'] = 1;
t( 'shortcode: the Summary Book button is left out when there is no published deck', ( function () use ( $sc ) { $o = $sc( array( 'id' => '7' ) ); return false === strpos( $o, 'summary-book' ); } )() );

// ---------- the grid number (1.45.0): saving and loading ----------
$GN = TWD_AP_Cards::ORDER_META;
$m->invoke( $rest, 3, array( 'featured' => true, 'grid_order' => '2' ) );
t( 'grid number: a number is stored', 2 === $GLOBALS['meta'][3][ $GN ] && 1 === $GLOBALS['meta'][3]['_twd_ap_featured'] );
t( 'grid number: it comes back in the edit data', 2 === $fp->invoke( $rest, 3, true )['grid_order'] );
$m->invoke( $rest, 3, array( 'title' => 'unrelated edit' ) );
t( 'grid number: a save that does not carry it leaves it alone', 2 === $GLOBALS['meta'][3][ $GN ] );
$m->invoke( $rest, 3, array( 'grid_order' => ' 7 ' ) );
t( 'grid number: spaces are fine', 7 === $GLOBALS['meta'][3][ $GN ] );
foreach ( array( '', '0', 'abc', '-3', '2.5', '12345678', array( 1 ) ) as $bad ) {
	$m->invoke( $rest, 3, array( 'grid_order' => '4' ) );
	$m->invoke( $rest, 3, array( 'grid_order' => $bad ) );
	t( 'grid number: ' . json_encode( $bad ) . ' clears it', ! isset( $GLOBALS['meta'][3][ $GN ] ) && 0 === $fp->invoke( $rest, 3, true )['grid_order'] );
}
$m->invoke( $rest, 3, array( 'grid_order' => '4' ) );
$m->invoke( $rest, 3, array( 'grid_order' => null ) );
t( 'grid number: null counts as not sent (the popup sends a blank as an empty string)', 4 === $GLOBALS['meta'][3][ $GN ] );
$m->invoke( $rest, 3, array( 'grid_order' => '99999' ) );
t( 'grid number: held at 9999', 9999 === $GLOBALS['meta'][3][ $GN ] );
$m->invoke( $rest, 3, array( 'featured' => false ) );
t( 'grid number: taking the feature tick off keeps the number (it only matters while featured)', 9999 === $GLOBALS['meta'][3][ $GN ] && ! isset( $GLOBALS['meta'][3]['_twd_ap_featured'] ) );
t( 'grid number: clean_grid_order edge cases', 0 === TWD_AP_Cards::clean_grid_order( '' ) && 1 === TWD_AP_Cards::clean_grid_order( 1 ) && 0 === TWD_AP_Cards::clean_grid_order( 0 ) && 5 === TWD_AP_Cards::clean_grid_order( '005' ) && 0 === TWD_AP_Cards::clean_grid_order( new stdClass() ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
