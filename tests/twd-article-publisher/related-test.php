<?php
// Tests for "More in {category}" at the end of every article. Real TWD_AP_Related and TWD_AP_Cards classes,
// WordPress functions and WP_Query faked (the fake sorts featured first, then newest, as the real query is written to).
//   php tests/twd-article-publisher/related-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['filters'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['single'] = true; $GLOBALS['loop'] = true; $GLOBALS['current_id'] = 1;
function add_filter( $tag, $cb, $prio = 10 ) { $GLOBALS['filters'][] = array( $tag, $prio ); }
function add_shortcode( $tag, $cb ) { $GLOBALS['shortcodes'][ $tag ] = $cb; }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function absint( $n ) { return abs( (int) $n ); }
$GLOBALS['shortcodes'] = array(); $GLOBALS['enqueued'] = 0;
class TWD_AP_Article_Style { static function enqueue() { $GLOBALS['enqueued']++; } }
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
		if ( ! empty( $a['post__not_in'] ) ) { $p = array_filter( $p, function ( $x ) use ( $a ) { return ! in_array( $x->ID, $a['post__not_in'], true ); } ); }
		foreach ( $a['meta_query'] ?? array() as $mq ) {
			if ( isset( $mq['key'], $mq['compare'] ) && 'EXISTS' === $mq['compare'] ) { $p = array_filter( $p, function ( $x ) use ( $mq ) { return array_key_exists( $mq['key'], $GLOBALS['meta'][ $x->ID ] ?? array() ); } ); }
		}
		// Honour the ordering the code asks for (a string or an array of field => direction), so a lost or wrong
		// orderby is caught; WordPress's default when none is given is date, newest first.
		$ob   = $a['orderby'] ?? 'date';
		$dirs = is_array( $ob ) ? $ob : array( $ob => $a['order'] ?? 'DESC' );
		usort( $p, function ( $x, $y ) use ( $dirs ) {
			foreach ( $dirs as $k => $dir ) {
				$f = 'ID' === $k ? 'ID' : ( 'date' === $k ? 'post_date' : 'post_title' );
				$c = $x->$f <=> $y->$f;
				if ( 0 !== $c ) { return 'ASC' === strtoupper( $dir ) ? $c : -$c; }
			}
			return 0;
		} );
		$this->posts = array_slice( $p, $a['offset'] ?? 0, $a['posts_per_page'] );
	}
}
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function get_categories() { $out = array(); foreach ( $GLOBALS['terms'] as $t ) { foreach ( $GLOBALS['posts'] as $p ) { if ( 'publish' === $p->post_status && in_array( $t->term_id, $p->cats, true ) ) { $out[] = $t; break; } } } return $out; }
class TWD_AP_Index { static function index_url() { return $GLOBALS['index_url']; } }
$GLOBALS['index_url'] = '';
class TWD_AP_Grid_Settings { const RELATED_PLACES = array( 'auto', 'shortcode', 'off' ); static $s = array( 'related_show' => true, 'related_count' => 4, 'related_place' => 'auto', 'related_topics' => false, 'topic_category_ids' => 'all', 'accent_color' => '#2563eb' ); static function get() { return self::$s; } }
require $plugin . 'includes/class-twd-ap-cards.php';
require $plugin . 'includes/class-twd-ap-related.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function reset_site() {
	$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['single'] = true; $GLOBALS['loop'] = true; $GLOBALS['current_id'] = 1;
	TWD_AP_Grid_Settings::$s = array( 'related_show' => true, 'related_count' => 4, 'related_place' => 'auto', 'related_topics' => false, 'topic_category_ids' => 'all', 'accent_color' => '#2563eb' );
	$GLOBALS['index_url'] = '';
	$GLOBALS['terms'] = array(
		1 => (object) array( 'term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized' ),
		2 => (object) array( 'term_id' => 2, 'name' => 'Anxiety', 'slug' => 'anxiety' ),
		3 => (object) array( 'term_id' => 3, 'name' => 'Sleep', 'slug' => 'sleep' ),
		4 => (object) array( 'term_id' => 4, 'name' => 'Featured Articles', 'slug' => 'featured-articles' ),
	);
}
function add_post( $id, $title, $cats, $date, $status = 'publish' ) { $GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'slug' => 'post-' . $id, 'post_title' => $title, 'post_status' => $status, 'post_date' => $date, 'cats' => $cats ); }
function links( $html ) { preg_match_all( '/<li><a href="([^"]+)">/', $html, $m ); return $m[1]; }
function inst() { foreach ( array( 'appended', 'placed' ) as $flag ) { $p = new ReflectionProperty( 'TWD_AP_Related', $flag ); $p->setAccessible( true ); $p->setValue( null, false ); } return ( new ReflectionClass( 'TWD_AP_Related' ) )->newInstanceWithoutConstructor(); }

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

// ---------- the Browse by topic row under the list ----------
reset_site();
add_post( 1, 'This article', array( 2 ), '2026-03-01' );
add_post( 2, 'A sibling', array( 2 ), '2026-03-02' );
add_post( 3, 'A sleep piece', array( 3 ), '2026-03-03' );
t( 'topics: off, no row is added under the list', false === strpos( TWD_AP_Related::html( 1 ), 'twd-ap-articles-topics' ) );
TWD_AP_Grid_Settings::$s['related_topics'] = true;
$html = TWD_AP_Related::html( 1 );
preg_match( '/<nav class="twd-ap-articles-topics".*?<\/nav>/s', $html, $nav );
t( 'topics: on, the row follows the related list inside the same block', ! empty( $nav ) && strpos( $html, 'twd-ap-related-list' ) < strpos( $html, 'twd-ap-articles-topics' ) && 1 === substr_count( $html, '<aside' ) );
t( 'topics: this article\'s own category is shown but not linked, the other category is a link', false !== strpos( $nav[0], '<li class="twd-ap-topics-current"><span aria-current="page">Anxiety</span></li>' ) && false !== strpos( $nav[0], '<a href="https://example.test/category/sleep/">Sleep</a>' ) && false === strpos( $nav[0], '/category/anxiety/' ) );
t( 'topics: no All articles link while there is no index page', false === strpos( $html, 'twd-ap-topics-all' ) );
$GLOBALS['index_url'] = 'https://example.test/article-index/';
t( 'topics: with an index page the row ends with the quiet All articles link', false !== strpos( TWD_AP_Related::html( 1 ), '<li class="twd-ap-topics-all"><a href="https://example.test/article-index/">All articles</a></li>' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = array( 3 );
t( 'topics: it follows the categories the owner chose', false === strpos( TWD_AP_Related::html( 1 ), 'twd-ap-topics-current' ) && false !== strpos( TWD_AP_Related::html( 1 ), '>Sleep<' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = 'all';
TWD_AP_Grid_Settings::$s['related_show'] = false;
$only = TWD_AP_Related::html( 1 );
t( 'topics: with the related list switched off the row still closes the article on its own', false === strpos( $only, 'twd-ap-related-list' ) && false !== strpos( $only, 'twd-ap-articles-topics' ) );
TWD_AP_Grid_Settings::$s['related_topics'] = false;
t( 'topics: both off, nothing at all is printed', '' === TWD_AP_Related::html( 1 ) );
TWD_AP_Grid_Settings::$s['related_show'] = true; TWD_AP_Grid_Settings::$s['related_topics'] = true;
add_post( 4, 'Alone in its category', array( 3 ), '2026-03-04' );
$GLOBALS['posts'][3]->post_status = 'draft';
$alone = TWD_AP_Related::html( 4 );
t( 'topics: an article alone in its category has no list but still gets the row', false === strpos( $alone, 'twd-ap-related-list' ) && false !== strpos( $alone, 'twd-ap-articles-topics' ) );
add_post( 5, 'Uncategorised', array( 1 ), '2026-03-05' );
$none = TWD_AP_Related::html( 5 );
t( 'topics: an article with no real category still gets the row, with nothing marked as current', false !== strpos( $none, 'twd-ap-articles-topics' ) && false === strpos( $none, 'twd-ap-topics-current' ) );

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

// ---------- where it goes, and the [twd_related] shortcode (1.46.0) ----------
reset_site();
add_post( 1, 'This article', array( 2 ), '2026-03-01' );
for ( $i = 2; $i <= 9; $i++ ) { add_post( $i, 'Anxiety piece ' . $i, array( 2 ), sprintf( '2026-02-%02d', $i ) ); }
add_post( 20, 'A sleep piece', array( 3 ), '2026-03-05' );
add_post( 21, 'Another sleep piece', array( 3 ), '2026-03-06' );
$sc = function ( $atts = array() ) { return $GLOBALS['shortcodes']['twd_related']( $atts ); };
t( 'shortcode [twd_related] is registered', isset( $GLOBALS['shortcodes']['twd_related'] ) );
t( 'place: the default is automatically at the end of every article', 'auto' === TWD_AP_Related::place() );
$GLOBALS['current_id'] = 1;
t( 'shortcode: prints the same block as the automatic one, for the article being shown', TWD_AP_Related::html( 1 ) === $sc() && '' !== $sc() );
t( 'shortcode: count changes how many links for that place only', 2 === count( links( $sc( array( 'count' => '2' ) ) ) ) && 4 === count( links( $sc() ) ) );
t( 'shortcode: a count above 8 is held at 8', 8 === count( links( $sc( array( 'count' => '99' ) ) ) ) );
t( 'shortcode: id names another article, with its own topic', false !== strpos( $sc( array( 'id' => '20' ) ), '>Sleep<' ) && 1 === count( links( $sc( array( 'id' => '20' ) ) ) ) );
$GLOBALS['enqueued'] = 0; $sc();
t( 'shortcode: loads the article stylesheet so the block is styled on any page', $GLOBALS['enqueued'] > 0 );
$GLOBALS['current_id'] = 0;
t( 'shortcode: with no article in view and no id prints nothing and does not fail', '' === $sc() );
$GLOBALS['current_id'] = 1;
// automatic: after the text
$i = inst();
t( 'place auto: the block is added after the article text', 0 === strpos( $i->append_to_article( '<p>text</p>' ), '<p>text</p><aside' ) );
// auto, but the shortcode already printed it inside the article's own text
$i = inst();
$sc();
t( 'place auto with the shortcode in the article text: the automatic copy steps aside, so it shows once', '<p>text</p>' === $i->append_to_article( '<p>text</p>' ) );
// shortcode only
TWD_AP_Grid_Settings::$s['related_place'] = 'shortcode';
$i = inst();
t( 'place shortcode: nothing is added automatically', '<p>text</p>' === $i->append_to_article( '<p>text</p>' ) );
t( 'place shortcode: the shortcode still prints', '' !== $sc() );
// off
TWD_AP_Grid_Settings::$s['related_place'] = 'off';
$i = inst();
t( 'place off: nothing is added automatically and the shortcode prints nothing', '<p>text</p>' === $i->append_to_article( '<p>text</p>' ) && '' === $sc() );
TWD_AP_Grid_Settings::$s['related_place'] = 'sideways';
t( 'place: an unknown value is treated as automatic', 'auto' === TWD_AP_Related::place() );
TWD_AP_Grid_Settings::$s['related_place'] = 'auto';
// nothing to show: the shortcode prints nothing and leaves the automatic copy free to print nothing too
reset_site();
add_post( 1, 'Alone', array( 3 ), '2026-03-01' );
TWD_AP_Grid_Settings::$s['related_topics'] = false;
$i = inst();
t( 'shortcode: an article with nothing related prints nothing', '' === $sc() && '<p>t</p>' === $i->append_to_article( '<p>t</p>' ) );
if ( getenv( 'TWD_RELATED_DUMP' ) ) {
	reset_site();
	TWD_AP_Grid_Settings::$s['related_topics'] = true;
	$GLOBALS['index_url'] = 'https://example.test/article-index/';
	add_post( 1, 'This article', array( 2 ), '2026-03-01' );
	add_post( 30, 'A sleep piece', array( 3 ), '2026-03-02' );
	for ( $i = 2; $i <= 6; $i++ ) { add_post( $i, 'Understanding anxiety, part ' . $i, array( 2 ), sprintf( '2026-02-%02d', $i ) ); }
	file_put_contents( getenv( 'TWD_RELATED_DUMP' ), json_encode( array( 'html' => '<div class="twd-ap-article-content"><h2>A heading</h2><p>Article body text here.</p></div>' . TWD_AP_Related::html( 1 ) ) ) );
}
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
