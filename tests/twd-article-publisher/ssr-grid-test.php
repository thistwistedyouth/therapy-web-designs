<?php
// Tests for the server-rendered [twd_articles] grid: the first batch of cards is printed into the page
// HTML (real links for crawlers), shares one query and one card formatter with the REST route, and
// escapes everything. Real plugin classes (TWD_AP_Cards, TWD_AP_Shortcode, TWD_AP_REST), WordPress
// functions and WP_Query faked. The fake WP_Query sorts the way the real query is written to
// (newest first; the featured-first order is done in PHP by TWD_AP_Cards), so these checks prove both callers use the same arguments and the same
// card code; the real database ordering is checked on a live site.
//   php tests/twd-article-publisher/ssr-grid-test.php
define( 'ABSPATH', '/' );
define( 'TWD_AP_PATH', dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

class WP_Error { }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $s ) { return (string) $s; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function nocache_headers() {}
function absint( $n ) { return abs( (int) $n ); }
function remove_query_arg( $k ) { return 'https://example.test/category/anxiety/'; }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function get_bloginfo() { return 'Example Site'; }
function rest_ensure_response( $x ) { return $x; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function wp_create_nonce() { return 'nonce123'; }
function wp_json_encode( $x ) { return json_encode( $x ); }
function wp_unslash( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, (array) $atts ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_trim_words( $s, $n ) { $w = preg_split( '/\s+/', trim( $s ) ); return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . '&hellip;' : implode( ' ', $w ); }
function get_option( $k, $d = false ) { if ( 'default_category' === $k ) { return 1; } return $GLOBALS['opts'][ $k ] ?? $d; }
function get_post_status( $id ) { return $GLOBALS['page_status'][ $id ] ?? false; }
function current_user_can( $cap = '' ) { return ! empty( $GLOBALS['caps'][ $cap ] ); }
function wpautop( $t ) { $parts = preg_split( '/\n\s*\n/', trim( $t ) ); return implode( "\n", array_map( function ( $p ) { return '<p>' . $p . '</p>'; }, $parts ) ); }
function wp_kses_post( $t ) { $t = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', $t ); $t = preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $t ); return strip_tags( $t, '<p><a><strong><em><br><ul><ol><li>' ); }
function post_password_required( $p ) { return ! empty( $p->protected ); }
function get_post( $id ) { return is_object( $id ) ? $id : ( $GLOBALS['posts'][ $id ] ?? null ); }
function get_post_meta( $id, $k ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function get_post_thumbnail_id( $p ) { return $p->thumb ? $p->ID : 0; }
function wp_get_attachment_image_url( $id ) { return $GLOBALS['posts'][ $id ]->thumb; }
// Like the real get_the_title(): wptexturize turns an apostrophe into entity text.
function get_the_title( $p ) { return str_replace( "'", '&#8217;', $p->post_title ); }
function get_permalink( $p ) { return is_object( $p ) ? 'https://example.test/' . $p->slug . '/' : 'https://example.test/page-' . $p . '/'; }
function get_the_date( $f, $p ) { return date( 'j F Y', strtotime( $p->post_date ) ); }
function get_the_category( $id ) { return array_map( function ( $c ) { return $GLOBALS['terms'][ $c ]; }, $GLOBALS['posts'][ $id ]->cats ); }
function wp_get_post_categories( $id ) { return $GLOBALS['posts'][ $id ]->cats; }
function get_category( $id ) { return $GLOBALS['terms'][ $id ] ?? null; }
function get_category_link( $id ) { return 'https://example.test/category/' . $GLOBALS['terms'][ $id ]->slug . '/'; }
function live_posts() { return array_filter( $GLOBALS['posts'], function ( $p ) { return 'publish' === $p->post_status; } ); }
function get_categories() {
	$out = array();
	foreach ( $GLOBALS['terms'] as $t ) {
		$n = 0;
		foreach ( live_posts() as $p ) { if ( in_array( $t->term_id, $p->cats, true ) ) { $n++; } }
		if ( $n > 0 ) { $t->count = $n; $out[] = $t; }
	}
	return $out;
}
function get_posts( $args ) {
	$p = live_posts();
	usort( $p, function ( $a, $b ) { return strcmp( $b->post_date, $a->post_date ); } );
	return array_map( function ( $x ) { return $x->ID; }, $p );
}
function wp_count_posts() { return (object) array( 'publish' => count( live_posts() ) ); }
function is_category() { return ! empty( $GLOBALS['archive'] ); }
function is_tag() { return false; }
function get_queried_object() { return $GLOBALS['terms'][ $GLOBALS['archive'] ]; }

// The fake query: category/tag filter, featured first then newest, offset and limit, as the real args ask.
class WP_Query {
	public $posts = array();
	public $found_posts = 0;
	function __construct( $a ) {
		$GLOBALS['last_args'] = $a;
		$p = live_posts();
		if ( ! empty( $a['category_name'] ) ) {
			$p = array_filter( $p, function ( $x ) use ( $a ) {
				foreach ( $x->cats as $c ) { if ( $GLOBALS['terms'][ $c ]->slug === $a['category_name'] ) { return true; } }
				return false;
			} );
		}
		if ( ! empty( $a['post__not_in'] ) ) {
			$p = array_filter( $p, function ( $x ) use ( $a ) { return ! in_array( $x->ID, $a['post__not_in'], true ); } );
		}
		// A plain "has this key" meta query (what the real WordPress does with compare EXISTS).
		foreach ( $a['meta_query'] ?? array() as $mq ) {
			if ( isset( $mq['key'], $mq['compare'] ) && 'EXISTS' === $mq['compare'] ) {
				$p = array_filter( $p, function ( $x ) use ( $mq ) { return array_key_exists( $mq['key'], $GLOBALS['meta'][ $x->ID ] ?? array() ); } );
			}
		}
		// Newest first, as asked. Featured first is NOT done by the database any more (PHP does it in TWD_AP_Cards).
		usort( $p, function ( $x, $y ) { return strcmp( $y->post_date, $x->post_date ); } );
		$this->found_posts = count( $p );
		$this->posts = array_slice( $p, $a['offset'] ?? 0, $a['posts_per_page'] );
	}
}
class TWD_AP_Grid_Settings {
	static $s = array( 'intro_show' => false, 'cta_heading' => '', 'cta_text' => '', 'cta_button_label' => '', 'cta_button_url' => '', 'cta_show_grid' => false, 'cta_show_categories' => false, 'cta_show_index' => false, 'cta_show_articles' => false, 'topic_category_ids' => 'all', 'category_order' => array(), 'categories_count' => 4, 'posts_per_category' => 6, 'show_thumbnails' => true, 'thumb_shape' => 'wide', 'read_more_color' => '#1d4ed8', 'accent_color' => '#2563eb' );
	static function get() { return self::$s; }
	// Same mapping as the real class (tested against the real one in grid-settings-test.php).
	static function thumb_shape_class( $shape ) { return '4-3' === $shape ? 'twd-ap-thumbs-4-3' : ( 'square' === $shape ? 'twd-ap-thumbs-square' : '' ); }
}
class TWD_AP_Frontend { static function current_user_allowed() { return ! empty( $GLOBALS['allowed'] ); } }

require $plugin . 'includes/class-twd-ap-cards.php';
require $plugin . 'includes/class-twd-ap-cta.php';
require $plugin . 'includes/class-twd-ap-intro.php';
require $plugin . 'includes/class-twd-ap-index.php';
require $plugin . 'includes/class-twd-ap-shortcode.php';
require $plugin . 'includes/class-twd-ap-rest.php';

$dump = array(); // filled for the browser test when TWD_SSR_DUMP is set
function snap( $name ) {
	global $dump;
	$rest = TWD_AP_REST::instance();
	$dump[ $name ] = array(
		'html'    => grid(),
		'list'    => $rest->list_articles( new ArrayObject( array( 'per_page' => 9, 'offset' => 0 ) ) ),
		'grouped' => $rest->list_articles_grouped(),
		'total'   => count( live_posts() ),
	);
}
$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function reset_site() {
	$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['archive'] = 0; $GLOBALS['caps'] = array(); $GLOBALS['allowed'] = false;
	$GLOBALS['terms'] = array(
		1 => (object) array( 'term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized', 'count' => 0, 'taxonomy' => 'category', 'description' => '' ),
		2 => (object) array( 'term_id' => 2, 'name' => 'Anxiety', 'slug' => 'anxiety', 'count' => 0, 'taxonomy' => 'category', 'description' => '' ),
		3 => (object) array( 'term_id' => 3, 'name' => 'Sleep', 'slug' => 'sleep', 'count' => 0, 'taxonomy' => 'category', 'description' => '' ),
		4 => (object) array( 'term_id' => 4, 'name' => 'Empty topic', 'slug' => 'empty-topic', 'count' => 0, 'taxonomy' => 'category', 'description' => '' ),
	);
	TWD_AP_Grid_Settings::$s['show_thumbnails'] = true;
	TWD_AP_Grid_Settings::$s['thumb_shape'] = 'wide';
	TWD_AP_Grid_Settings::$s['posts_per_category'] = 6;
	TWD_AP_Grid_Settings::$s['topic_category_ids'] = 'all';
	TWD_AP_Grid_Settings::$s['intro_show'] = false;
}
function add_post( $id, $title, $cats, $date, $extra = array() ) {
	$GLOBALS['posts'][ $id ] = (object) array_merge( array(
		'ID' => $id, 'slug' => 'post-' . $id, 'post_title' => $title, 'post_status' => 'publish', 'post_date' => $date,
		'post_content' => '<p>Short made-up body number ' . $id . ' about calm breathing.</p>', 'post_excerpt' => '',
		'cats' => $cats, 'thumb' => '', 'protected' => false,
	), $extra );
}
function grid( $atts = array() ) { return TWD_AP_Shortcode::instance()->render( $atts ); }
function card_links( $html ) { preg_match_all( '/<a class="twd-ap-article-card" href="([^"]+)"/', $html, $m ); return $m[1]; }
function rest_links( $params ) {
	$r = TWD_AP_REST::instance()->list_articles( new ArrayObject( $params ) );
	return array_column( $r['items'], 'link' );
}

// ---------- a small site (5 articles, so one flat list of all of them) ----------
reset_site();
add_post( 10, 'Older featured piece', array( 2 ), '2026-01-05' );
$GLOBALS['meta'][10]['_twd_ap_featured'] = 1;
add_post( 11, 'Newest piece', array( 2 ), '2026-03-09' );
add_post( 12, 'Middle piece', array( 3 ), '2026-02-10' );
add_post( 13, 'Only in the default category', array( 1 ), '2026-02-01' );
add_post( 14, "Tom's calm & <b>quiet</b> \"day\"", array( 3, 2 ), '2026-01-20' );
snap( 'small' );
$html  = grid();
$links = card_links( $html );
t( 'small site: every article has a real link in the server HTML', 5 === count( $links ) );
foreach ( array( 10, 11, 12, 13, 14 ) as $id ) {
	t( "small site: link to article $id is in the HTML", in_array( 'https://example.test/post-' . $id . '/', $links, true ) );
}
t( 'small site: titles are in the HTML', false !== strpos( $html, 'Older featured piece' ) && false !== strpos( $html, 'Newest piece' ) );
t( 'order: featured first, then newest', 'https://example.test/post-10/' === $links[0] && 'https://example.test/post-11/' === $links[1] );
t( 'order: identical to the REST route', $links === rest_links( array( 'per_page' => 9, 'offset' => 0 ) ), json_encode( $links ) );
t( 'order: both callers build the same query arguments', TWD_AP_Cards::query_args( 9, 0 ) === TWD_AP_Cards::query_args( 9, 0, '', '', '' ) );
t( 'uncategorized is never shown as a category', false === stripos( $html, 'Uncategorized' ) );
t( 'a post in a real category shows it as the eyebrow', false !== strpos( $html, 'Anxiety &middot; 5 January 2026' ) );
t( 'escaping: a tag in a title is not output raw', false === strpos( $html, '<b>quiet</b>' ) && false !== strpos( $html, '&lt;b&gt;quiet&lt;/b&gt;' ) );
t( 'escaping: quotes and ampersands are escaped', false !== strpos( $html, '&amp;' ) && false !== strpos( $html, '&quot;day&quot;' ) );
t( 'entities: the curly apostrophe is a real character, not double-encoded', false !== strpos( $html, 'Tom’s' ) && false === strpos( $html, '&amp;#8217;' ) && false === strpos( $html, '&#8217;' ) );
t( 'filter-empty words are never in the server HTML', false === strpos( $html, 'No articles match' ) );
t( 'friendly zero line is present but hidden when there are articles', 1 === preg_match( '/<p class="twd-ap-articles-none" hidden>/', $html ) );
t( 'the grid element is not hidden and has no loading text', false === strpos( $html, 'Loading articles' ) && 1 === preg_match( '/<div class="twd-ap-articles-grid"[^>]*><a class="twd-ap-article-card"/', $html ) );
t( 'the JS can tell the HTML is server-rendered', false !== strpos( $html, 'data-ssr="1"' ) );
t( 'the saved colours are on the root from the first paint', false !== strpos( $html, '--twd-ap-readmore-color:#1d4ed8' ) && false !== strpos( $html, '--twd-ap-accent-color:#2563eb' ) );
t( 'the config count covers every article on a small site', false !== strpos( html_entity_decode( $html ), '"count":9' ) );
$cfg = json_decode( html_entity_decode( preg_replace( '/^.*data-config="([^"]+)".*$/s', '$1', $html ) ), true );
t( 'the config says a small site is not "many"', is_array( $cfg ) && false === $cfg['manyArticles'] );
t( 'category archive links are in the HTML', false !== strpos( $html, 'href="https://example.test/category/anxiety/"' ) && false !== strpos( $html, 'href="https://example.test/category/sleep/"' ) );
t( 'category archive links skip uncategorized and empty categories', false === strpos( $html, '/category/uncategorized/' ) && false === strpos( $html, '/category/empty-topic/' ) );

// ---------- thumbnails ----------
add_post( 15, 'Has a picture', array( 2 ), '2026-03-10', array( 'thumb' => "https://example.test/img/it's.jpg" ) );
$html = grid();
t( 'thumbnails on: the picture is a background with the quote escaped', false !== strpos( $html, "background-image:url(&#039;https://example.test/img/it%27s.jpg&#039;)" ) );
t( 'thumbnails on: a post with no picture gets the placeholder', false !== strpos( $html, 'twd-ap-article-thumb-placeholder' ) );
TWD_AP_Grid_Settings::$s['show_thumbnails'] = false;
$html = grid();
t( 'thumbnails off: no thumbnail markup at all', false === strpos( $html, 'twd-ap-article-thumb' ) );
t( 'thumbnails off: the root carries the no-thumbnails class', false !== strpos( $html, 'class="twd-ap-articles twd-ap-no-thumbnails"' ) );
TWD_AP_Grid_Settings::$s['show_thumbnails'] = true;

// ---------- card image shape ----------
$html = grid();
t( 'shape: the default prints no shape class, so the page is as it was', false !== strpos( $html, 'class="twd-ap-articles"' ) && false === strpos( $html, 'twd-ap-thumbs-' ) );
TWD_AP_Grid_Settings::$s['thumb_shape'] = '4-3';
t( 'shape: 4:3 puts its class on the root', false !== strpos( grid(), 'class="twd-ap-articles twd-ap-thumbs-4-3"' ) );
snap( 'shape43' );
// Several cards and the owner's gear, built while 4:3 is saved, so the popup test can tell a live change from a page that already had the shape.
$GLOBALS['allowed'] = true; snap( 'adminmulti' ); $GLOBALS['allowed'] = false;
TWD_AP_Grid_Settings::$s['thumb_shape'] = 'square';
t( 'shape: square puts its class on the root', false !== strpos( grid(), 'class="twd-ap-articles twd-ap-thumbs-square"' ) );
snap( 'shapesq' );
// A site with a single article keeps its image-beside-text card on a wide screen.
$keep_posts = $GLOBALS['posts']; $keep_meta = $GLOBALS['meta'];
$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array();
add_post( 90, 'The only article', array( 2 ), '2026-03-12', array( 'thumb' => 'https://example.test/img/lone.jpg' ) );
snap( 'singlesq' );
TWD_AP_Grid_Settings::$s['thumb_shape'] = '4-3';
snap( 'single43' );
$GLOBALS['posts'] = $keep_posts; $GLOBALS['meta'] = $keep_meta;
TWD_AP_Grid_Settings::$s['thumb_shape'] = 'square';
TWD_AP_Grid_Settings::$s['show_thumbnails'] = false;
t( 'shape: with thumbnails off the root keeps both classes', false !== strpos( grid(), 'class="twd-ap-articles twd-ap-no-thumbnails twd-ap-thumbs-square"' ) );
TWD_AP_Grid_Settings::$s['show_thumbnails'] = true;
TWD_AP_Grid_Settings::$s['thumb_shape'] = '16-9"><script>';
$html = grid();
t( 'shape: a stored value that is not a known shape prints nothing', false === strpos( $html, 'twd-ap-thumbs-' ) && false === strpos( $html, '<script>' ) );
TWD_AP_Grid_Settings::$s['thumb_shape'] = 'wide';

// ---------- password-protected articles ----------
add_post( 16, 'Private piece', array( 2 ), '2026-03-11', array( 'protected' => true, 'post_content' => '<p>secret words nobody should read</p>' ) );
t( 'a password-protected article leaks no text into the HTML', false === strpos( grid(), 'secret words' ) );
unset( $GLOBALS['posts'][16], $GLOBALS['posts'][15] );

// ---------- the count attribute and curated lists on a larger site ----------
reset_site();
for ( $i = 1; $i <= 14; $i++ ) {
	add_post( $i, 'Piece ' . $i, array( $i <= 8 ? 2 : ( $i <= 12 ? 3 : 1 ) ), sprintf( '2026-02-%02d', $i ) );
}
$html = grid( array( 'category' => 'anxiety', 'count' => 3 ) );
t( 'category attribute with count=3: exactly 3 cards', 3 === count( card_links( $html ) ) );
t( 'category attribute: newest first, only that category', array( 'https://example.test/post-8/', 'https://example.test/post-7/', 'https://example.test/post-6/' ) === card_links( $html ) );
t( 'category attribute: same links as the REST route', card_links( $html ) === rest_links( array( 'per_page' => 3, 'category' => 'anxiety' ) ) );
t( 'category attribute: no topic links on a curated list', false === strpos( $html, 'twd-ap-articles-topics' ) );
$GLOBALS['archive'] = 3;
$html = grid( array( 'count' => 2 ) );
t( 'auto-detected category archive: only that category, count respected', array( 'https://example.test/post-12/', 'https://example.test/post-11/' ) === card_links( $html ) );
$GLOBALS['archive'] = 0;

// ---------- a large site on the bare shortcode: grouped by category ----------
snap( 'large' );
TWD_AP_Grid_Settings::$s['thumb_shape'] = 'square'; snap( 'largesq' ); TWD_AP_Grid_Settings::$s['thumb_shape'] = 'wide';
$html = grid();
t( 'large site: starts grouped, with a heading per category', 1 === preg_match( '/<h2 class="twd-ap-articles-group-title">Anxiety<\/h2>.*<h2 class="twd-ap-articles-group-title">Sleep<\/h2>/s', $html ) );
t( 'large site: the main flat grid is hidden', 1 === preg_match( '/<div class="twd-ap-articles-grid" style="[^"]*" hidden><\/div>/', $html ) );
t( 'large site: See all is a real link to the category archive when there are more', false !== strpos( $html, '<a class="twd-ap-articles-group-more" href="https://example.test/category/anxiety/"' ) );
t( 'large site: no See all where the section already shows everything', false === strpos( $html, 'href="https://example.test/category/sleep/" data-slug' ) );
t( 'large site: 6 of 8 Anxiety cards, all 4 Sleep cards, none from the default category', 10 === count( card_links( $html ) ) && false === strpos( $html, 'Piece 13' ) && false === strpos( $html, 'Piece 14' ) );
$rest = TWD_AP_REST::instance()->list_articles_grouped();
$rest_links = array();
foreach ( $rest['groups'] as $g ) { foreach ( $g['posts'] as $p ) { $rest_links[] = $p['link']; } }
t( 'large site: the grouped cards match the REST grouped route, in order', card_links( $html ) === $rest_links );
t( 'large site: the config says many articles', false !== strpos( html_entity_decode( $html ), '"manyArticles":true' ) );
t( 'large site: topic links are in the HTML', false !== strpos( $html, 'class="twd-ap-articles-topics"' ) && false !== strpos( $html, '/category/sleep/' ) );

// ---------- featured articles and grid numbers (1.45.0) ----------
reset_site();
TWD_AP_Grid_Settings::$s['posts_per_category'] = 6;
add_post( 20, 'Anx newest', array( 2 ), '2026-03-20' );
add_post( 21, 'Anx featured three', array( 2 ), '2026-01-10' );
add_post( 22, 'Anx featured no number older', array( 2 ), '2026-01-02' );
add_post( 23, 'Anx old', array( 2 ), '2026-01-01' );
add_post( 24, 'Sleep featured one', array( 3 ), '2026-02-01' );
add_post( 25, 'Sleep newest', array( 3 ), '2026-03-01' );
add_post( 26, 'Sleep featured two', array( 3 ), '2026-02-15' );
add_post( 27, 'Sleep featured no number newer', array( 3 ), '2026-03-05' );
add_post( 28, 'Loose featured', array( 1 ), '2026-02-20' );
add_post( 29, 'Loose plain', array( 1 ), '2026-02-21' );
add_post( 30, 'Only featured in its topic', array( 4 ), '2026-01-15' );
foreach ( array( 21 => 3, 22 => 0, 24 => 1, 26 => 2, 27 => 0, 28 => 4, 30 => 5 ) as $fid => $gn ) {
	$GLOBALS['meta'][ $fid ]['_twd_ap_featured'] = 1;
	if ( $gn ) { $GLOBALS['meta'][ $fid ]['_twd_ap_grid_order'] = $gn; }
}
$ids = function ( $r ) { return array_map( function ( $p ) { return $p->ID; }, $r['posts'] ); };
$want = array( 24, 26, 21, 28, 30, 27, 22, 20, 25, 29, 23 );
$all = TWD_AP_Cards::fetch( 24, 0 );
t( 'featured order: grid number first (1, 2, 3...), then featured with no number newest first, then the rest newest first', $want === $ids( $all ), json_encode( $ids( $all ) ) );
t( 'featured order: nothing repeated, nothing missing', 11 === count( array_unique( $ids( $all ) ) ) );
$walk = array();
for ( $off = 0, $more = true; $more && $off < 30; $off += 3 ) { $r = TWD_AP_Cards::fetch( 3, $off ); $walk = array_merge( $walk, $ids( $r ) ); $more = $r['has_more']; }
t( 'paging by 3: every page lines up, the whole list comes back in the same order', $want === $walk, json_encode( $walk ) );
$r = TWD_AP_Cards::fetch( 4, 4 );
t( 'paging: a page that starts among the featured and ends among the others', array( 30, 27, 22, 20 ) === $ids( $r ) && true === $r['has_more'], json_encode( $ids( $r ) ) );
$r = TWD_AP_Cards::fetch( 5, 6 );
t( 'paging: the last page has no next page', array( 22, 20, 25, 29, 23 ) === $ids( $r ) && false === $r['has_more'], json_encode( $ids( $r ) ) );
$r = TWD_AP_Cards::fetch( 5, 11 );
t( 'paging: past the end is empty', array() === $ids( $r ) && false === $r['has_more'] );
$r = TWD_AP_Cards::fetch( 24, 0, '', 'anxiety' );
t( 'category list: its own featured first (by number), then the rest', array( 21, 22, 20, 23 ) === $ids( $r ), json_encode( $ids( $r ) ) );
$GLOBALS['meta'][26]['_twd_ap_grid_order'] = 1;
$r = TWD_AP_Cards::fetch( 24, 0, '', 'sleep' );
t( 'same grid number on two articles: the newer one first', array( 26, 24, 27, 25 ) === $ids( $r ), json_encode( $ids( $r ) ) );
$GLOBALS['meta'][26]['_twd_ap_grid_order'] = 2;
$GLOBALS['meta'][25]['_twd_ap_grid_order'] = 1; // a number on an article that is not featured does nothing
$r = TWD_AP_Cards::fetch( 24, 0 );
t( 'a grid number on an article that is not featured changes nothing', $want === $ids( $r ) );
unset( $GLOBALS['meta'][25]['_twd_ap_grid_order'] );
$GLOBALS['meta'][27]['_twd_ap_featured'] = 0; // a stray falsy value does not make it featured
$r = TWD_AP_Cards::fetch( 24, 0 );
t( 'a featured key with a false value is not featured (same rule as everywhere else)', array( 24, 26, 21, 28, 30, 22, 20, 27, 25, 29, 23 ) === $ids( $r ), json_encode( $ids( $r ) ) );
$GLOBALS['meta'][27]['_twd_ap_featured'] = 1;
$html  = grid();
$links = card_links( $html );
t( 'the page HTML shows the same order as the REST route', $links === rest_links( array( 'per_page' => 24, 'offset' => 0 ) ) && 'https://example.test/post-24/' === $links[0] && 'https://example.test/post-23/' === $links[10], json_encode( $links ) );

// The grouped landing view: a Featured row first, each article once.
$g     = TWD_AP_Cards::grouped();
$names = array_map( function ( $x ) { return $x['category']['name']; }, $g['groups'] );
$gids  = function ( $grp ) { return array_map( function ( $c ) { return $c['id']; }, $grp['posts'] ); };
t( 'Featured row: the first group is "Featured", with no category link', 'Featured' === $names[0] && '' === $g['groups'][0]['category']['link'] && ! empty( $g['groups'][0]['featured'] ), json_encode( $names ) );
t( 'Featured row: the six first in featured order, whatever their category', array( 24, 26, 21, 28, 30, 27 ) === $gids( $g['groups'][0] ), json_encode( $gids( $g['groups'][0] ) ) );
t( 'Featured row: no "See all" for it (its total is what it shows)', $g['groups'][0]['total'] === count( $g['groups'][0]['posts'] ) );
t( 'sections follow: Anxiety then Sleep, a topic whose only article is in the row is skipped', array( 'Featured', 'Anxiety', 'Sleep' ) === $names, json_encode( $names ) );
t( 'sections: a featured article past the row is first in its own section', array( 22, 20, 23 ) === $gids( $g['groups'][1] ) && array( 25 ) === $gids( $g['groups'][2] ), json_encode( array( $gids( $g['groups'][1] ), $gids( $g['groups'][2] ) ) ) );
$every = array();
foreach ( $g['groups'] as $grp ) { $every = array_merge( $every, $gids( $grp ) ); }
t( 'every article appears once on the page (the plain Uncategorized one is not on the grouped view)', 10 === count( $every ) && 10 === count( array_unique( $every ) ) && ! in_array( 29, $every, true ) );
t( 'sections keep their own full count for See all', 4 === $g['groups'][1]['total'] );
TWD_AP_Grid_Settings::$s['posts_per_category'] = 3;
$g = TWD_AP_Cards::grouped();
t( 'a smaller section size makes a smaller Featured row; the rest return to their sections', array( 24, 26, 21 ) === $gids( $g['groups'][0] ) && array( 22, 20, 23 ) === $gids( $g['groups'][1] ) && array( 27, 25 ) === $gids( $g['groups'][2] ), json_encode( array_map( $gids, $g['groups'] ) ) );
// Two more featured articles past the row, in one section: ordered by grid number, not by date.
add_post( 40, 'Sleep featured nine', array( 3 ), '2026-03-10' );
add_post( 41, 'Sleep featured eight', array( 3 ), '2026-01-03' );
$GLOBALS['meta'][40] = array( '_twd_ap_featured' => 1, '_twd_ap_grid_order' => 9 );
$GLOBALS['meta'][41] = array( '_twd_ap_featured' => 1, '_twd_ap_grid_order' => 8 );
$g = TWD_AP_Cards::grouped();
$sleep = null;
foreach ( $g['groups'] as $grp ) { if ( 'Sleep' === $grp['category']['name'] ) { $sleep = $grp; } }
t( 'inside a section the featured ones past the row follow their grid numbers, then the unnumbered one, then the rest', null !== $sleep && array( 41, 40, 27 ) === $gids( $sleep ), json_encode( $sleep ? $gids( $sleep ) : null ) );
unset( $GLOBALS['posts'][40], $GLOBALS['posts'][41], $GLOBALS['meta'][40], $GLOBALS['meta'][41] );
TWD_AP_Grid_Settings::$s['posts_per_category'] = 12;
$g = TWD_AP_Cards::grouped();
$names = array_map( function ( $x ) { return $x['category']['name']; }, $g['groups'] );
t( 'every featured article in the row: the sections carry only the others', array( 24, 26, 21, 28, 30, 27, 22 ) === $gids( $g['groups'][0] ) && array( 20, 23 ) === $gids( $g['groups'][1] ) && array( 'Featured', 'Anxiety', 'Sleep' ) === $names, json_encode( array_map( $gids, $g['groups'] ) ) );
TWD_AP_Grid_Settings::$s['posts_per_category'] = 6;
$html = TWD_AP_Cards::groups_html( TWD_AP_Cards::grouped()['groups'], true );
t( 'Featured row markup: the first heading, no See all link on it', 1 === preg_match( '/^<div class="twd-ap-articles-group"><div class="twd-ap-articles-group-head"><h2 class="twd-ap-articles-group-title">Featured<\/h2><\/div>/', $html ) );
foreach ( array( 21, 22, 24, 26, 27, 28, 30 ) as $fid ) { unset( $GLOBALS['meta'][ $fid ]['_twd_ap_featured'] ); }
$g = TWD_AP_Cards::grouped();
$names = array_map( function ( $x ) { return $x['category']['name']; }, $g['groups'] );
t( 'no featured articles: no Featured row at all', array( 'Anxiety', 'Sleep' ) === array_slice( $names, 0, 2 ) && ! in_array( 'Featured', $names, true ), json_encode( $names ) );
// The browser test reads this one: the Featured row must draw the same from the server and from the JS.
foreach ( array( 21 => 3, 22 => 0, 24 => 1, 26 => 2, 27 => 0, 28 => 4, 30 => 5 ) as $fid => $gn ) { $GLOBALS['meta'][ $fid ]['_twd_ap_featured'] = 1; }
for ( $i = 31; $i <= 36; $i++ ) { add_post( $i, 'Filler ' . $i, array( 2 ), sprintf( '2025-12-%02d', $i - 10 ) ); }
snap( 'featuredrow' );
$html = grid();
t( 'large site with featured articles: the Featured row heads the page HTML', 1 === preg_match( '/<h2 class="twd-ap-articles-group-title">Featured<\/h2>.*<h2 class="twd-ap-articles-group-title">Anxiety<\/h2>/s', $html ) );

// ---------- an empty site ----------
reset_site();
snap( 'empty' );
$html = grid();
t( 'empty site: the friendly line shows, not hidden', 1 === preg_match( '/<p class="twd-ap-articles-none">No articles yet\. Please check back soon\.<\/p>/', $html ) );
t( 'empty site: the filter-empty words are not in the HTML', false === strpos( $html, 'No articles match' ) );
t( 'empty site: no card links and the grid is hidden', 0 === count( card_links( $html ) ) && 1 === preg_match( '/<div class="twd-ap-articles-grid"[^>]*hidden><\/div>/', $html ) );
t( 'empty site: no loading text left behind', false === strpos( $html, 'Loading articles' ) );
$html = grid( array( 'category' => 'anxiety' ) );
t( 'a curated category with nothing in it also shows the friendly line', false !== strpos( $html, 'No articles yet' ) && 0 === count( card_links( $html ) ) );

// ---------- the automatic link to the full index page ----------
reset_site();
add_post( 1, 'Some piece', array( 2 ), '2026-02-01' );
t( 'index link: absent when no index page exists yet', false === strpos( grid(), 'twd-ap-articles-index-link' ) && false === strpos( grid(), 'twd-ap-topics-all' ) );
$GLOBALS['opts']['twd_ap_index_page_id'] = 77; $GLOBALS['page_status'][77] = 'publish';
snap( 'indexlink' );
$html = grid();
preg_match( '/<nav class="twd-ap-articles-topics".*?<\/nav>/s', $html, $nav );
t( 'index link: on the default view it is a quiet last item in the Browse by topic row, a real link', ! empty( $nav ) && false !== strpos( $nav[0], '<li class="twd-ap-topics-all"><a href="https://example.test/page-77/">All articles</a></li>' ) && 1 === preg_match( '/<\/li><li class="twd-ap-topics-all">/', $nav[0] ) );
t( 'index link: no big button any more', false === strpos( $html, 'index-pill' ) && false === strpos( $html, 'twd-ap-articles-index-link' ) );
$cur = grid( array( 'category' => 'anxiety' ) );
t( 'index link: a curated list has no topics row, so it gets the same quiet link on its own', false !== strpos( $cur, '<p class="twd-ap-articles-index-link"><a href="https://example.test/page-77/">Browse all articles</a></p>' ) && false === strpos( $cur, 'twd-ap-topics-all' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = array();
$none = grid();
t( 'index link: with the topics row hidden by the owner the quiet link still shows', false !== strpos( $none, 'twd-ap-articles-index-link' ) && false === strpos( $none, 'twd-ap-articles-topics' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = 'all';
$GLOBALS['page_status'][77] = 'trash';
t( 'index link: gone when the page is trashed', false === strpos( grid(), 'twd-ap-articles-index-link' ) );
$GLOBALS['page_status'][77] = 'draft';
t( 'index link: gone when the page is not published', false === strpos( grid(), 'twd-ap-articles-index-link' ) );
$GLOBALS['opts'] = array(); $GLOBALS['page_status'] = array();

// ---------- the call to action under the grid ----------
reset_site();
add_post( 1, 'Some piece', array( 2 ), '2026-02-01' ); add_post( 2, 'Another', array( 3 ), '2026-02-02' );
t( 'cta: nothing under the grid by default', false === strpos( grid(), 'twd-ap-cta' ) );
TWD_AP_Grid_Settings::$s = array_merge( TWD_AP_Grid_Settings::$s, array( 'cta_heading' => 'Ready to talk?', 'cta_text' => 'A short chat is free.', 'cta_button_label' => 'Book a call', 'cta_button_url' => 'https://example.test/book', 'cta_show_grid' => true ) );
$html = grid();
t( 'cta: shown under the grid once ticked, after the topics row and the index pill', 1 === preg_match( '/twd-ap-articles-topics.*twd-ap-cta twd-ap-cta-grid.*<\/div>\s*$/s', $html ) );
snap( 'cta' );
t( 'cta: on a category page it follows the category switch, not the grid one', ( $GLOBALS['archive'] = 3 ) && false === strpos( grid(), 'twd-ap-cta' ) );
TWD_AP_Grid_Settings::$s['cta_show_categories'] = true;
t( 'cta: ticked for category pages, it shows there with its own class', false !== strpos( grid(), 'twd-ap-cta twd-ap-cta-category' ) && false === strpos( grid(), 'twd-ap-cta-grid' ) );
$GLOBALS['archive'] = 0;
TWD_AP_Grid_Settings::$s['cta_show_grid'] = false;
t( 'cta: unticked for the grid, a normal page shows none', false === strpos( grid(), 'twd-ap-cta' ) );
TWD_AP_Grid_Settings::$s = array_merge( TWD_AP_Grid_Settings::$s, array( 'cta_heading' => '', 'cta_text' => '', 'cta_button_label' => '', 'cta_button_url' => '', 'cta_show_categories' => false ) );

// ---------- the plugin's own category page (template + shortcode together) ----------
function get_template() { return 'astra'; }
function get_header() { echo '<header class="theme-header">Site header</header>'; }
function get_footer() { echo '<footer class="theme-footer">Site footer</footer>'; }
function do_shortcode( $s ) { return grid( 'intro="force"' === substr( $s, -14, -1 ) || false !== strpos( $s, 'intro="force"' ) ? array( 'intro' => 'force' ) : array() ); }
function render_archive_page() { ob_start(); include TWD_AP_PATH . 'templates/archive-grid.php'; return ob_get_clean(); }
reset_site();
add_post( 1, 'Some piece', array( 2 ), '2026-02-01' );
add_post( 2, 'Another piece', array( 2 ), '2026-02-02' );
add_post( 3, 'Third piece', array( 2 ), '2026-02-03' );
add_post( 4, 'A sleep piece', array( 3 ), '2026-02-04' );
$GLOBALS['archive'] = 2;
$GLOBALS['terms'][2]->description = "Anxiety can feel huge.\n\nThese pieces help.";
$_GET = array();
$page_html = render_archive_page();
$dump['archivepage'] = array( 'html' => $page_html, 'list' => array( 'items' => array(), 'has_more' => false ), 'grouped' => array( 'groups' => array() ), 'total' => 3 );
t( 'archive page: theme header, the category name as H1, the intro (forced on although the setting is off), the cards, then the footer', 1 === preg_match( '/theme-header.*<h1 class="twd-ap-archive-title">Anxiety<\/h1>.*twd-ap-articles-intro-text">\s*<p>Anxiety can feel huge\..*twd-ap-article-card.*theme-footer/s', $page_html ) && 3 === count( card_links( $page_html ) ) );
preg_match( '/<nav class="twd-ap-articles-topics".*?<\/nav>/s', $page_html, $nav );
t( 'archive page: the Browse by topic row closes the page, like the resources page', ! empty( $nav ) && strpos( $page_html, 'twd-ap-article-card' ) < strpos( $page_html, 'twd-ap-articles-topics' ) );
t( 'archive page: this category is shown but not linked, the other topic is a link', ! empty( $nav ) && false !== strpos( $nav[0], '<li class="twd-ap-topics-current"><span aria-current="page">Anxiety</span></li>' ) && false === strpos( $nav[0], '/category/anxiety/' ) && false !== strpos( $nav[0], '<a href="https://example.test/category/sleep/">Sleep</a>' ) );
t( 'archive page: the All articles link is not there while no index page exists', false === strpos( $page_html, 'twd-ap-topics-all' ) );
$GLOBALS['opts']['twd_ap_index_page_id'] = 77; $GLOBALS['page_status'][77] = 'publish';
$with_index = render_archive_page();
t( 'archive page: with an index page the row ends with the quiet All articles link', false !== strpos( $with_index, '<li class="twd-ap-topics-all"><a href="https://example.test/page-77/">All articles</a></li>' ) );
$GLOBALS['opts'] = array(); $GLOBALS['page_status'] = array();
TWD_AP_Grid_Settings::$s['topic_category_ids'] = array( 3 );
$chosen = render_archive_page();
preg_match( '/<nav class="twd-ap-articles-topics".*?<\/nav>/s', $chosen, $nav2 );
t( 'archive page: the row follows the categories the owner chose (this category is not in the choice, so it is simply not listed)', ! empty( $nav2 ) && false === strpos( $nav2[0], 'twd-ap-topics-current' ) && false !== strpos( $nav2[0], '>Sleep<' ) && false === strpos( $nav2[0], '>Anxiety<' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = array();
t( 'archive page: with the row switched off by the owner there is none', false === strpos( render_archive_page(), 'twd-ap-articles-topics' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = 'all';
$GLOBALS['archive'] = 0;
t( 'a curated list on an ordinary page still has no topics row (it is one topic on its own)', false === strpos( grid( array( 'category' => 'anxiety' ) ), 'twd-ap-articles-topics' ) );
$GLOBALS['archive'] = 2;
t( 'archive page: one H1 only (the shortcode adds none)', 1 === substr_count( $page_html, '<h1' ) );
t( 'archive page: no filter toolbar on a category page', false === strpos( $page_html, 'twd-ap-articles-toolbar' ) );
t( 'archive page: a visitor sees no settings gear', false === strpos( $page_html, 'twd-ap-articles-settings-btn' ) && false === strpos( $page_html, 'twd-ap-grid-settings-overlay' ) );
$GLOBALS['allowed'] = true;
$admin_page = render_archive_page();
$dump['archiveadmin'] = array( 'html' => $admin_page, 'list' => array( 'items' => array(), 'has_more' => false ), 'grouped' => array( 'groups' => array() ), 'total' => 3 );
t( 'archive page: someone allowed to publish gets the gear, so the owner can always reach the switch for this page', false !== strpos( $admin_page, 'twd-ap-articles-settings-btn' ) && false !== strpos( $admin_page, 'twd-ap-grid-settings-overlay' ) && false !== strpos( $admin_page, 'id="twd-ap-gs-archive-takeover"' ) );
t( 'archive page: the gear is not the full filter toolbar (no search box or dropdown on a category page)', false === strpos( $admin_page, 'twd-ap-articles-search' ) && false === strpos( $admin_page, 'twd-ap-articles-category-select' ) );
$GLOBALS['allowed'] = false;
$GLOBALS['archive'] = 0;
reset_site();

// ---------- real page links under a long list ----------
reset_site();
for ( $i = 1; $i <= 30; $i++ ) { add_post( $i, 'Piece ' . $i, array( 2 ), sprintf( '2026-02-%02d', $i ) ); }
$page_cfg = function ( $html ) { $c = json_decode( html_entity_decode( preg_replace( '/^.*data-config="([^"]+)".*$/s', '$1', $html ) ), true ); return $c; };
$_GET = array();
$html = grid( array( 'category' => 'anxiety', 'count' => 9 ) );
t( 'paging: a category with more than count articles gets page links', false !== strpos( $html, 'class="twd-ap-articles-pager"' ) && 4 === preg_match_all( '/<li><(?:a|span)[^>]*>\d<\/(?:a|span)><\/li>/', $html ) );
t( 'paging: page 1 shows the first 9 and is marked current', 9 === count( card_links( $html ) ) && false !== strpos( $html, '<span aria-current="page">1</span>' ) );
t( 'paging: page links are real links with rel next, and page 1 has no ?twd_page', false !== strpos( $html, '<a rel="next" href="https://example.test/category/anxiety/?twd_page=2">2</a>' ) && false !== strpos( $html, 'Next &rarr;' ) && false === strpos( $html, 'twd_page=1' ) );
t( 'paging: no Previous link on page 1', false === strpos( $html, 'twd-ap-pager-prev' ) );
t( 'paging: the config starts at offset 0', 0 === $page_cfg( $html )['startOffset'] );
$_GET['twd_page'] = '2';
$html = grid( array( 'category' => 'anxiety', 'count' => 9 ) );
t( 'paging: ?twd_page=2 shows the next 9, matching the REST route at that offset', card_links( $html ) === rest_links( array( 'per_page' => 9, 'offset' => 9, 'category' => 'anxiety' ) ) && 9 === count( card_links( $html ) ) );
t( 'paging: page 2 has Previous and Next, and links back to page 1 without the parameter', false !== strpos( $html, 'twd-ap-pager-prev"><a rel="prev" href="https://example.test/category/anxiety/">' ) && false !== strpos( $html, 'twd-ap-pager-next' ) );
t( 'paging: the config tells the JS to start at offset 9', 9 === $page_cfg( $html )['startOffset'] );
$_GET['twd_page'] = '4';
$html = grid( array( 'category' => 'anxiety', 'count' => 9 ) );
t( 'paging: the last page shows the remaining 3 and no Next', 3 === count( card_links( $html ) ) && false === strpos( $html, 'twd-ap-pager-next' ) );
$_GET['twd_page'] = '99';
$html = grid( array( 'category' => 'anxiety', 'count' => 9 ) );
t( 'paging: a page past the end shows the last page, not an empty grid', 3 === count( card_links( $html ) ) && false !== strpos( $html, '<span aria-current="page">4</span>' ) && 27 === $page_cfg( $html )['startOffset'] );
foreach ( array( 'abc', '0', '' ) as $junk ) {
	$_GET['twd_page'] = $junk;
	t( "paging: a junk page number ('$junk') shows page 1", 9 === count( card_links( grid( array( 'category' => 'anxiety', 'count' => 9 ) ) ) ) && false !== strpos( grid( array( 'category' => 'anxiety', 'count' => 9 ) ), '<span aria-current="page">1</span>' ) );
}
// Page snapshots for the browser test: page 1 and page 2 of a 30 article category, 9 to a page.
foreach ( array( 1 => 0, 2 => 9 ) as $pg => $off ) {
	$_GET['twd_page'] = (string) $pg;
	$dump[ 'paged' . $pg ] = array(
		'html'    => grid( array( 'category' => 'anxiety', 'count' => 9 ) ),
		'list'    => TWD_AP_REST::instance()->list_articles( new ArrayObject( array( 'per_page' => 9, 'offset' => $off, 'category' => 'anxiety' ) ) ),
		'grouped' => array( 'groups' => array() ),
		'total'   => 30,
	);
}
$_GET = array();
$html = grid( array( 'category' => 'anxiety', 'count' => 9 ) );
t( 'paging: an inline script right after the links marks them JavaScript-on before first paint', 1 === preg_match( '/<\/nav><script>document\.currentScript\.previousElementSibling\.setAttribute\("data-js","1"\);<\/script>/', $html ) );
t( 'paging: page 1 is not marked deep, page 2 is', false === strpos( $html, 'data-deep' ) && ( $_GET['twd_page'] = '2' ) && false !== strpos( grid( array( 'category' => 'anxiety', 'count' => 9 ) ), 'data-deep="1"' ) );
$_GET = array();
// count above the 24 the REST route returns is clamped, so the page links match the cards
$html = grid( array( 'category' => 'anxiety', 'count' => 30 ) );
t( 'count: count="30" is clamped to 24, so 24 cards and a pager for the other 6 (nothing unreachable)', 24 === count( card_links( $html ) ) && false !== strpos( $html, 'class="twd-ap-articles-pager"' ) && 24 === $page_cfg( $html )['count'] );
$_GET['twd_page'] = '2';
t( 'count: page 2 of the clamped list holds the last 6', 6 === count( card_links( grid( array( 'category' => 'anxiety', 'count' => 30 ) ) ) ) );
$_GET = array();
$html = grid( array( 'category' => 'anxiety', 'count' => 24 ) );
t( 'paging: with 30 articles 24 to a page still needs a pager (6 left)', false !== strpos( $html, 'twd-ap-articles-pager' ) );
$all = array();
for ( $pg = 1; $pg <= 4; $pg++ ) { $_GET['twd_page'] = (string) $pg; $all = array_merge( $all, card_links( grid( array( 'category' => 'anxiety', 'count' => 9 ) ) ) ); }
$_GET = array();
t( 'paging: pages 1 to 4 together list all 30 articles once', 30 === count( $all ) && 30 === count( array_unique( $all ) ) );
t( 'paging: the large-site grouped view has no page links (its sections link to the category pages)', ( function () { for ( $i = 31; $i <= 40; $i++ ) { add_post( $i, 'Extra ' . $i, array( 3 ), sprintf( '2026-03-%02d', $i - 30 ) ); } return false === strpos( grid(), 'twd-ap-articles-pager' ); } )() );
reset_site();
for ( $i = 1; $i <= 120; $i++ ) { add_post( $i, 'Item ' . $i, array( 2 ), '2026-01-01' ); }
$_GET['twd_page'] = '12';
$html = grid( array( 'category' => 'anxiety', 'count' => 5 ) );
preg_match( '/<nav class="twd-ap-articles-pager".*?<\/nav>/s', $html, $nav );
t( 'paging: 24 pages are shortened to the first, last and the pages around the current one', ! empty( $nav ) && 1 === preg_match( '/>1<.*gap.*>11<.*aria-current="page">12<.*>13<.*gap.*>24</s', $nav[0] ) && false === strpos( $nav[0], '>7<' ) );
$_GET = array();
reset_site();

// ---------- the category intro above the grid ----------
reset_site();
add_post( 1, 'Some piece', array( 2 ), '2026-02-01' );
$GLOBALS['archive'] = 2;
t( 'intro: a category with no description shows nothing to a visitor', false === strpos( grid(), 'twd-ap-articles-intro' ) );
TWD_AP_Grid_Settings::$s['intro_show'] = true;
t( 'intro: off by default, so a theme that already prints the description is not doubled', ( $GLOBALS['terms'][2]->description = 'Words' ) && ( TWD_AP_Grid_Settings::$s['intro_show'] = false ) === false && false === strpos( grid(), 'twd-ap-articles-intro' ) );
t( 'intro: intro="force" shows it even when the setting is off (the plugin\'s own category page uses this)', false !== strpos( grid( array( 'intro' => 'force' ) ), 'twd-ap-articles-intro-text' ) );
TWD_AP_Grid_Settings::$s['intro_show'] = true;
$GLOBALS['terms'][2]->description = "Anxiety can feel <strong>huge</strong>.\n\nThese pieces help. <script>alert(1)</script><a href=\"https://example.test/x\" onclick=\"evil()\">More</a>";
$html = grid();
t( 'intro: the category description is printed above the grid, in paragraphs', 1 === preg_match( '/twd-ap-articles-intro-text"><p>Anxiety can feel <strong>huge<\/strong>\.<\/p>\s*<p>These pieces help\./', $html ) && strpos( $html, 'twd-ap-articles-intro' ) < strpos( $html, 'twd-ap-articles-grid' ) );
t( 'intro: scripts and event handlers in the description are removed', false === strpos( $html, '<script' ) && false === strpos( $html, 'onclick' ) && false !== strpos( $html, 'href="https://example.test/x"' ) );
t( 'intro: a visitor gets no edit button', false === strpos( $html, 'twd-ap-intro-edit' ) );
snap( 'intro' );
t( 'intro: intro="no" switches it off', false === strpos( grid( array( 'intro' => 'no' ) ), 'twd-ap-articles-intro' ) );
TWD_AP_Grid_Settings::$s['intro_show'] = false;
t( 'intro: untick in Customise this grid switches it off everywhere', false === strpos( grid(), 'twd-ap-articles-intro' ) );
TWD_AP_Grid_Settings::$s['intro_show'] = true;
$GLOBALS['archive'] = 0;
t( 'intro: not shown on an ordinary page, only on a category page', false === strpos( grid(), 'twd-ap-articles-intro' ) );
$GLOBALS['archive'] = 2; $GLOBALS['allowed'] = true; $GLOBALS['caps']['edit_term'] = true;
$html = grid();
t( 'intro: someone who can edit the category gets an Edit intro button carrying the id, taxonomy and current text', 1 === preg_match( '/<button type="button" class="twd-ap-intro-edit" data-term-id="2" data-taxonomy="category" data-name="Anxiety" data-raw="[^"]*huge/', $html ) && false !== strpos( $html, 'Edit intro' ) );
t( 'intro: the raw text in the button is escaped inside the attribute', false === strpos( $html, 'data-raw="Anxiety can feel <strong>' ) && false !== strpos( $html, 'data-raw="Anxiety can feel &lt;strong&gt;' ) );
snap( 'introedit' );
$GLOBALS['terms'][2]->description = '';
$html = grid();
t( 'intro: with no description an editor sees an Add an intro button and an empty text area', false !== strpos( $html, 'Add an intro' ) && false !== strpos( $html, 'twd-ap-articles-intro-text"></div>' ) );
$GLOBALS['caps']['edit_term'] = false;
t( 'intro: an allowed publisher who may not edit that category sees nothing', false === strpos( grid(), 'twd-ap-articles-intro' ) );
$GLOBALS['archive'] = 0; $GLOBALS['allowed'] = false; $GLOBALS['caps'] = array();

$GLOBALS['allowed'] = true; snap( 'admin' ); $GLOBALS['allowed'] = false;
// ---------- the filter dropdown starts with All; the topic row follows the setting ----------
reset_site();
add_post( 1, 'Some piece', array( 2 ), '2026-02-01' ); add_post( 2, 'Another', array( 3 ), '2026-02-02' );
$html = grid();
t( 'filter: the dropdown starts with All (no Loading text, no Show all at the end)', 1 === preg_match( '/<select class="twd-ap-articles-category-select"[^>]*>\s*<option value="">All<\/option>/', $html ) && false === strpos( $html, 'Show all' ) );
t( 'topics: every real category is listed by default', false !== strpos( $html, '/category/anxiety/' ) && false !== strpos( $html, '/category/sleep/' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = array( 3 );
$html = grid();
preg_match( '/<nav class="twd-ap-articles-topics".*?<\/nav>/s', $html, $nav );
t( 'topics: only the chosen category is in the row', ! empty( $nav ) && false !== strpos( $nav[0], '/category/sleep/' ) && false === strpos( $nav[0], '/category/anxiety/' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = array();
t( 'topics: unticking every category hides the row', false === strpos( grid(), 'twd-ap-articles-topics' ) );
TWD_AP_Grid_Settings::$s['topic_category_ids'] = 'all';

if ( getenv( 'TWD_SSR_DUMP' ) ) { file_put_contents( getenv( 'TWD_SSR_DUMP' ), json_encode( $dump ) ); }
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
