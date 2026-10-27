<?php
// Tests for "More in {category}" at the end of every article. Real TWD_AP_Related and TWD_AP_Cards classes,
// WordPress functions and WP_Query faked (the fake sorts featured first, then newest, as the real query is written to).
//   php tests/twd-article-publisher/related-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['filters'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['single'] = true; $GLOBALS['loop'] = true; $GLOBALS['current_id'] = 1;
function add_filter( $tag, $cb, $prio = 10 ) { $GLOBALS['filters'][] = array( $tag, $prio ); }
function is_wp_error( $x ) { return false; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function get_option( $k, $d = false ) { return 'default_category' === $k ? 1 : $d; }
function is_admin() { return false; }
function is_singular() { return $GLOBALS['single']; }
function in_the_loop() { return $GLOBALS['loop']; }
function is_main_query() { return true; }
function get_the_ID() { return $GLOBALS['current_id']; }
function get_post_meta( $id, $k ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function get_the_category( $id ) { return array_map( function ( $c ) { return $GLOBALS['terms'][ $c ]; }, $GLOBALS['posts'][ $id ]->cats ); }
function get_the_title( $p ) { return str_replace( "'", '&#8217;', $p->post_title ); }
function get_permalink( $p ) { return 'https://example.test/' . $p->slug . '/'; }
function get_the_date( $f, $p ) { return date( 'j F Y', strtotime( $p->post_date ) ); }
function get_category_link( $id ) { return 'https://example.test/category/' . $GLOBALS['terms'][ $id ]->slug . '/'; }
class WP_Query {
	public $posts = array();
	function __construct( $a ) {
		$GLOBALS['last_args'] = $a;
		$p = array_filter( $GLOBALS['posts'], function ( $x ) { return 'publish' === $x->post_status; } );
		if ( ! empty( $a['category_name'] ) ) {
			$p = array_filter( $p, function ( $x ) use ( $a ) { foreach ( $x->cats as $c ) { if ( $GLOBALS['terms'][ $c ]->slug === $a['category_name'] ) { return true; } } return false; } );
		}
		usort( $p, function ( $x, $y ) {
			$fx = ! empty( $GLOBALS['meta'][ $x->ID ]['_twd_ap_featured'] ) ? 1 : 0; $fy = ! empty( $GLOBALS['meta'][ $y->ID ]['_twd_ap_featured'] ) ? 1 : 0;
			return $fy <=> $fx ?: strcmp( $y->post_date, $x->post_date );
		} );
		$this->posts = array_slice( $p, $a['offset'] ?? 0, $a['posts_per_page'] );
	}
}
class TWD_AP_Grid_Settings { static $s = array( 'related_show' => true, 'related_count' => 4, 'accent_color' => '#2563eb' ); static function get() { return self::$s; } }
require $plugin . 'includes/class-twd-ap-cards.php';
require $plugin . 'includes/class-twd-ap-related.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function reset_site() {
	$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['single'] = true; $GLOBALS['loop'] = true; $GLOBALS['current_id'] = 1;
	TWD_AP_Grid_Settings::$s = array( 'related_show' => true, 'related_count' => 4, 'accent_color' => '#2563eb' );
	$GLOBALS['terms'] = array(
		1 => (object) array( 'term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized' ),
		2 => (object) array( 'term_id' => 2, 'name' => 'Anxiety', 'slug' => 'anxiety' ),
		3 => (object) array( 'term_id' => 3, 'name' => 'Sleep', 'slug' => 'sleep' ),
		4 => (object) array( 'term_id' => 4, 'name' => 'Featured Articles', 'slug' => 'featured-articles' ),
	);
}
function add_post( $id, $title, $cats, $date, $status = 'publish' ) { $GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'slug' => 'post-' . $id, 'post_title' => $title, 'post_status' => $status, 'post_date' => $date, 'cats' => $cats ); }
function links( $html ) { preg_match_all( '/<li><a href="([^"]+)">/', $html, $m ); return $m[1]; }
function inst() { $p = new ReflectionProperty( 'TWD_AP_Related', 'appended' ); $p->setAccessible( true ); $p->setValue( null, false ); return ( new ReflectionClass( 'TWD_AP_Related' ) )->newInstanceWithoutConstructor(); }

// ---------- which topic an article belongs to ----------
reset_site();
add_post( 1, 'Self', array( 1, 3, 2 ), '2026-01-01' );
t( 'topic: the default category is ignored, the first real category is used', 3 === TWD_AP_Related::topic_for( 1 )->term_id );
$GLOBALS['meta'][1]['_twd_ap_primary_category'] = 2;
t( 'topic: the primary category the grid uses wins', 2 === TWD_AP_Related::topic_for( 1 )->term_id );
add_post( 2, 'Featured first', array( 4, 3 ), '2026-01-02' );
$GLOBALS['meta'][2]['_twd_ap_primary_category'] = 4;
t( 'topic: a Featured Articles primary yields to a real topic', 3 === TWD_AP_Related::topic_for( 2 )->term_id );
add_post( 3, 'Only featured', array( 4 ), '2026-01-03' );
t( 'topic: an article only in Featured Articles uses it', 4 === TWD_AP_Related::topic_for( 3 )->term_id );
add_post( 4, 'Uncategorised', array( 1 ), '2026-01-04' );
t( 'topic: an article only in the default category has none', null === TWD_AP_Related::topic_for( 4 ) );

// ---------- the block ----------
reset_site();
add_post( 1, 'This article', array( 2 ), '2026-03-01' );
for ( $i = 2; $i <= 8; $i++ ) { add_post( $i, 'Anxiety piece ' . $i, array( 2 ), sprintf( '2026-02-%02d', $i ) ); }
add_post( 20, 'A sleep piece', array( 3 ), '2026-03-05' );
add_post( 21, 'A draft piece', array( 2 ), '2026-03-06', 'draft' );
$html = TWD_AP_Related::html( 1 );
t( 'block: shows 4 other articles from the same category, newest first', array( 'https://example.test/post-8/', 'https://example.test/post-7/', 'https://example.test/post-6/', 'https://example.test/post-5/' ) === links( $html ), json_encode( links( $html ) ) );
t( 'block: never lists the article itself', false === strpos( $html, 'post-1/' ) );
t( 'block: nothing from another category and no drafts', false === strpos( $html, 'post-20/' ) && false === strpos( $html, 'post-21/' ) );
t( 'block: the heading is "More in" plus a link to the category page', false !== strpos( $html, '<h2 class="twd-ap-related-title">More in <a href="https://example.test/category/anxiety/">Anxiety</a></h2>' ) );
t( 'block: each link carries the date', false !== strpos( $html, '<span class="twd-ap-related-date">8 February 2026</span>' ) );
t( 'block: it carries the saved accent colour', false !== strpos( $html, 'style="--twd-ap-accent-color:#2563eb;"' ) );
$GLOBALS['meta'][3]['_twd_ap_featured'] = 1;
t( 'block: a featured article in the category comes first, like the grid', 'https://example.test/post-3/' === links( TWD_AP_Related::html( 1 ) )[0] );
$GLOBALS['meta'] = array();
TWD_AP_Grid_Settings::$s['related_count'] = 2;
t( 'block: the owner\'s count is honoured', 2 === count( links( TWD_AP_Related::html( 1 ) ) ) );
TWD_AP_Grid_Settings::$s['related_count'] = 50;
t( 'block: a count above 8 is held at 8', 7 === count( links( TWD_AP_Related::html( 1 ) ) ) && 10 === $GLOBALS['last_args']['posts_per_page'] );
TWD_AP_Grid_Settings::$s['related_count'] = 0;
t( 'block: a count of 0 still shows at least 1', 1 === count( links( TWD_AP_Related::html( 1 ) ) ) );
TWD_AP_Grid_Settings::$s['related_count'] = 4;
TWD_AP_Grid_Settings::$s['related_show'] = false;
t( 'block: switched off by the owner, nothing is printed', '' === TWD_AP_Related::html( 1 ) );
TWD_AP_Grid_Settings::$s['related_show'] = true;

// ---------- small and odd cases ----------
reset_site();
add_post( 1, 'Only one in its category', array( 3 ), '2026-03-01' );
t( 'small: an article alone in its category has no block', '' === TWD_AP_Related::html( 1 ) );
add_post( 2, 'One sibling', array( 3 ), '2026-03-02' );
t( 'small: one sibling is enough to show a block of one', 1 === count( links( TWD_AP_Related::html( 1 ) ) ) );
add_post( 3, 'Uncategorised article', array( 1 ), '2026-03-03' );
t( 'small: an article only in the default category has no block', '' === TWD_AP_Related::html( 3 ) );
reset_site();
add_post( 1, 'Self', array( 2 ), '2026-03-01' );
add_post( 2, "Tom's <b>calm</b> & \"quiet\"", array( 2 ), '2026-03-02' );
$html = TWD_AP_Related::html( 1 );
t( 'escaping: markup in a title is escaped, quotes and ampersands too', false === strpos( $html, '<b>calm</b>' ) && false !== strpos( $html, '&lt;b&gt;calm&lt;/b&gt;' ) && false !== strpos( $html, '&amp;' ) && false !== strpos( $html, '&quot;quiet&quot;' ) );
t( 'entities: the curly apostrophe is a real character, once', false !== strpos( $html, 'Tom’s' ) && false === strpos( $html, '&#8217;' ) && false === strpos( $html, '&amp;#8217;' ) );
$GLOBALS['terms'][2]->name = 'Tom &amp; Jerry';
t( 'entities: a category name with an ampersand is shown once-encoded', false !== strpos( TWD_AP_Related::html( 1 ), '>Tom &amp; Jerry</a>' ) && false === strpos( TWD_AP_Related::html( 1 ), '&amp;amp;' ) );

// ---------- the content filter ----------
reset_site();
add_post( 1, 'Self', array( 2 ), '2026-03-01' ); add_post( 2, 'Other', array( 2 ), '2026-03-02' );
$GLOBALS['filters'] = array();
$ref = new ReflectionClass( 'TWD_AP_Related' ); $ctor = $ref->getConstructor(); $ctor->setAccessible( true ); $ctor->invoke( $ref->newInstanceWithoutConstructor() );
t( 'filter: registered on the_content at priority 22, between the article wrapper (20) and the call to action (25)', array( array( 'the_content', 22 ) ) === $GLOBALS['filters'] );
$out = inst()->append_to_article( '<div class="twd-ap-article-content"><p>Body</p></div>' );
t( 'filter: the block follows the article', 1 === preg_match( '/^<div class="twd-ap-article-content"><p>Body<\/p><\/div><aside class="twd-ap-related"/', $out ) );
t( 'filter: added once even if the content filter runs again', '<p>x</p>' === ( new ReflectionClass( 'TWD_AP_Related' ) )->newInstanceWithoutConstructor()->append_to_article( '<p>x</p>' ) );
$GLOBALS['single'] = false;
t( 'filter: not on a page that is not a single article', '<p>x</p>' === inst()->append_to_article( '<p>x</p>' ) );
$GLOBALS['single'] = true; $GLOBALS['loop'] = false;
t( 'filter: not outside the main loop (widgets, excerpts)', '<p>x</p>' === inst()->append_to_article( '<p>x</p>' ) );

if ( getenv( 'TWD_RELATED_DUMP' ) ) {
	reset_site();
	add_post( 1, 'This article', array( 2 ), '2026-03-01' );
	for ( $i = 2; $i <= 6; $i++ ) { add_post( $i, 'Understanding anxiety, part ' . $i, array( 2 ), sprintf( '2026-02-%02d', $i ) ); }
	file_put_contents( getenv( 'TWD_RELATED_DUMP' ), json_encode( array( 'html' => '<div class="twd-ap-article-content"><h2>A heading</h2><p>Article body text here.</p></div>' . TWD_AP_Related::html( 1 ) ) ) );
}
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
