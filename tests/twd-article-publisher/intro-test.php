<?php
// Tests for saving a category's intro from the "Edit intro" popup: who may, what is kept, what comes back.
// Real TWD_AP_Intro and TWD_AP_REST (the save route's handler); WordPress functions faked.
//   php tests/twd-article-publisher/intro-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
class WP_Error { public $code; public $data; function __construct( $c = '', $m = '', $d = array() ) { $this->code = $c; $this->data = $d; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function add_action() {}
function add_filter() {}
function rest_ensure_response( $x ) { return $x; }
function wpautop( $t ) { return '<p>' . str_replace( "\n\n", "</p>\n<p>", trim( $t ) ) . '</p>'; }
function wp_kses_post( $t ) { $t = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', $t ); $t = preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $t ); return strip_tags( $t, '<p><a><strong><em><br><ul><ol><li>' ); }
function current_user_can( $cap, $id = 0 ) { return 'edit_term' === $cap && in_array( $id, $GLOBALS['may_edit'], true ); }
function get_term( $id, $tax ) { $t = $GLOBALS['terms'][ $id ] ?? null; return $t && $t->taxonomy === $tax ? $t : new WP_Error(); }
function wp_update_term( $id, $tax, $args ) { $GLOBALS['updates'][] = array( $id, $tax, $args ); if ( ! empty( $GLOBALS['update_fails'] ) ) { return new WP_Error( 'x' ); } $GLOBALS['terms'][ $id ]->description = $args['description']; return array( 'term_id' => $id ); }
class TWD_AP_Frontend { static function current_user_allowed() { return ! empty( $GLOBALS['allowed'] ); } }
require $plugin . 'includes/class-twd-ap-intro.php';
require $plugin . 'includes/class-twd-ap-rest.php';

class Req { public $p; function __construct( $p ) { $this->p = $p; } function get_json_params() { return $this->p; } }
$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
$GLOBALS['terms'] = array(
	2 => (object) array( 'term_id' => 2, 'name' => 'Anxiety', 'taxonomy' => 'category', 'description' => 'Old text' ),
	3 => (object) array( 'term_id' => 3, 'name' => 'Sleep', 'taxonomy' => 'category', 'description' => '' ),
	9 => (object) array( 'term_id' => 9, 'name' => 'calm', 'taxonomy' => 'post_tag', 'description' => '' ),
);
$GLOBALS['may_edit'] = array( 2, 9 ); $GLOBALS['updates'] = array(); $GLOBALS['allowed'] = true;
$rest = TWD_AP_REST::instance();

$r = $rest->save_term_intro( new Req( array( 'term_id' => 2, 'taxonomy' => 'category', 'description' => "Hello <strong>there</strong>.\n\nSecond <script>alert(1)</script>paragraph." ) ) );
t( 'saving: the description is stored with scripts removed', ! is_wp_error( $r ) && false === strpos( $GLOBALS['terms'][2]->description, '<script' ) && false !== strpos( $GLOBALS['terms'][2]->description, '<strong>there</strong>' ) );
t( 'saving: the reply carries the cleaned, formatted text ready to show', ! is_wp_error( $r ) && 1 === preg_match( '/^<p>Hello <strong>there<\/strong>\.<\/p>\n<p>Second paragraph\.<\/p>$/', $r['html'] ) && $r['raw'] === $GLOBALS['terms'][2]->description );
$r = $rest->save_term_intro( new Req( array( 'term_id' => 2, 'taxonomy' => 'category', 'description' => '' ) ) );
t( 'saving: an empty text clears the intro', ! is_wp_error( $r ) && '' === $r['html'] && '' === $GLOBALS['terms'][2]->description );
$r = $rest->save_term_intro( new Req( array( 'term_id' => 9, 'taxonomy' => 'post_tag', 'description' => 'About calm' ) ) );
t( 'saving: a tag works too', ! is_wp_error( $r ) && 'About calm' === $GLOBALS['terms'][9]->description );

$before = count( $GLOBALS['updates'] );
$r = $rest->save_term_intro( new Req( array( 'term_id' => 3, 'taxonomy' => 'category', 'description' => 'Not allowed' ) ) );
t( 'permission: someone who may not edit that category is refused and nothing is saved', is_wp_error( $r ) && 403 === $r->data['status'] && count( $GLOBALS['updates'] ) === $before && '' === $GLOBALS['terms'][3]->description );
$r = $rest->save_term_intro( new Req( array( 'term_id' => 2, 'taxonomy' => 'nav_menu', 'description' => 'x' ) ) );
t( 'taxonomy: only categories and tags can be changed', is_wp_error( $r ) && count( $GLOBALS['updates'] ) === $before );
$r = $rest->save_term_intro( new Req( array( 'term_id' => 2, 'taxonomy' => 'post_tag', 'description' => 'x' ) ) );
t( 'taxonomy: an id from the wrong taxonomy is not found', is_wp_error( $r ) && 404 === $r->data['status'] );
$r = $rest->save_term_intro( new Req( array( 'term_id' => 404, 'taxonomy' => 'category', 'description' => 'x' ) ) );
t( 'a category that does not exist is not found', is_wp_error( $r ) && 404 === $r->data['status'] );
$r = $rest->save_term_intro( new Req( null ) );
t( 'an empty request is refused rather than breaking', is_wp_error( $r ) );
$GLOBALS['update_fails'] = true;
$r = $rest->save_term_intro( new Req( array( 'term_id' => 2, 'taxonomy' => 'category', 'description' => 'x' ) ) );
t( 'a failed save is passed on as an error', is_wp_error( $r ) );
$GLOBALS['update_fails'] = false;

// ---------- who sees the button ----------
$term = $GLOBALS['terms'][2]; $term->description = 'Some words';
$GLOBALS['allowed'] = false;
t( 'button: not for a visitor', false === strpos( TWD_AP_Intro::html( $term ), 'twd-ap-intro-edit' ) );
$GLOBALS['allowed'] = true;
t( 'button: for a publisher who may edit this category', false !== strpos( TWD_AP_Intro::html( $term ), 'twd-ap-intro-edit' ) );
t( 'button: not for a publisher who may not edit this category', false === strpos( TWD_AP_Intro::html( $GLOBALS['terms'][3] ), 'twd-ap-intro-edit' ) && '' === TWD_AP_Intro::html( $GLOBALS['terms'][3] ) );
$bad = (object) array( 'term_id' => 5, 'name' => 'x', 'taxonomy' => 'nav_menu', 'description' => 'text' );
t( 'a term from another taxonomy gets no intro', '' === TWD_AP_Intro::html( $bad ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
