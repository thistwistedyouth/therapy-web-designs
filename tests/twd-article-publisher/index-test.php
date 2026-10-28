<?php
// Tests for the alphabetical article index ([twd_articles_index]) and the "Article index" page the plugin
// creates for it. Real TWD_AP_Index and TWD_AP_Cards classes, WordPress functions faked.
//   php tests/twd-article-publisher/index-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

class WP_Error { }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function is_singular() { return false; }
function in_the_loop() { return false; }
function is_main_query() { return false; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function add_filter() {}
function add_action() {}
function add_shortcode() {}
function wp_unslash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function current_user_can() { return $GLOBALS['is_admin_user']; }
function get_option( $k, $d = false ) { if ( 'default_category' === $k ) { return 1; } return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]->post_status ?? false; }
function get_post_meta( $id, $k ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function get_the_title( $p ) { return str_replace( "'", '&#8217;', $p->post_title ); }
function get_permalink( $p = null ) { if ( null === $p ) { return 'https://example.test/article-index/'; } return 'https://example.test/' . ( is_object( $p ) ? $p->slug : 'page-' . $p ) . '/'; }
function get_the_date( $f, $p ) { return date( 'j F Y', strtotime( $p->post_date ) ); }
function wp_get_post_categories( $id ) { return $GLOBALS['posts'][ $id ]->cats; }
function get_category( $id ) { return $GLOBALS['terms'][ $id ] ?? null; }
function get_category_link( $id ) { return 'https://example.test/category/' . $GLOBALS['terms'][ $id ]->slug . '/'; }
function get_posts( $a ) {
	$GLOBALS['last_get_posts'] = $a;
	$want = isset( $a['post_type'] ) ? $a['post_type'] : 'post';
	$out = array_filter( $GLOBALS['posts'], function ( $p ) use ( $a, $want ) {
		if ( $p->post_type !== $want || 'publish' !== $p->post_status ) { return false; }
		if ( isset( $a['s'] ) && false === strpos( $p->post_content, $a['s'] ) ) { return false; }
		return true;
	} );
	if ( 'post' === $want ) { usort( $out, function ( $x, $y ) { return strcmp( $x->post_title, $y->post_title ); } ); }
	$out = array_values( $out );
	return isset( $a['fields'] ) && 'ids' === $a['fields'] ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
}
$next_page_id = 500;
function wp_insert_post( $a ) {
	global $next_page_id;
	$id = ++$next_page_id;
	$GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'slug' => $a['post_name'], 'post_title' => $a['post_title'], 'post_status' => $a['post_status'], 'post_type' => $a['post_type'], 'post_content' => $a['post_content'], 'cats' => array(), 'post_date' => '2026-10-26' );
	$GLOBALS['inserted'][] = $id;
	return $id;
}

class TWD_AP_Grid_Settings { static $s = array( 'accent_color' => '#2563eb', 'topic_category_ids' => 'all', 'cta_heading' => '', 'cta_text' => '', 'cta_button_label' => '', 'cta_button_url' => '', 'cta_show_grid' => false, 'cta_show_categories' => false, 'cta_show_index' => false, 'cta_show_articles' => false ); static function get() { return self::$s; } }
require $plugin . 'includes/class-twd-ap-swipebook-colours.php';
require $plugin . 'includes/class-twd-ap-cards.php';
require $plugin . 'includes/class-twd-ap-cta.php';
require $plugin . 'includes/class-twd-ap-index.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function reset_site() {
	$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['inserted'] = array(); $GLOBALS['is_admin_user'] = true; $_GET = array();
	$GLOBALS['terms'] = array(
		1 => (object) array( 'term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized' ),
		2 => (object) array( 'term_id' => 2, 'name' => 'Sleep', 'slug' => 'sleep' ),
		3 => (object) array( 'term_id' => 3, 'name' => 'anxiety', 'slug' => 'anxiety' ),
		4 => (object) array( 'term_id' => 4, 'name' => 'Featured Articles', 'slug' => 'featured-articles' ),
		5 => (object) array( 'term_id' => 5, 'name' => 'Boundaries', 'slug' => 'boundaries' ),
	);
}
function add_post( $id, $title, $cats, $date = '2026-02-01', $status = 'publish' ) {
	$GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'slug' => 'post-' . $id, 'post_title' => $title, 'post_status' => $status, 'post_type' => 'post', 'post_content' => '<p>x</p>', 'cats' => $cats, 'post_date' => $date );
}
function render_index() { return TWD_AP_Index::instance()->render(); }
function hrefs( $html ) { preg_match_all( '/<li><a href="(https:[^"]+\/post-[0-9]+\/)">/', $html, $m ); return $m[1]; }

// ---------- order ----------
reset_site();
add_post( 1, 'zebra calm', array( 2 ) );
add_post( 2, 'apple routine', array( 2 ) );
add_post( 3, 'Banana habits', array( 2 ) );
add_post( 4, 'Worry notes', array( 3 ) );
add_post( 5, 'Loose piece', array( 1 ) );
add_post( 6, 'No category at all', array() );
add_post( 7, 'Saying no kindly', array( 5 ) );
$html = render_index();
if ( getenv( 'TWD_INDEX_DUMP' ) ) { file_put_contents( getenv( 'TWD_INDEX_DUMP' ), $html ); }
t( 'titles are alphabetical within a category, ignoring case', 1 === preg_match( '/apple routine.*Banana habits.*zebra calm/s', $html ) );
t( 'categories are alphabetical, ignoring case, with Other articles last', 1 === preg_match( '/>anxiety<.*>Boundaries<.*>Sleep<.*Other articles/s', $html ) );
t( 'an article in no category or only the default category goes under Other articles', 1 === preg_match( '/Other articles.*Loose piece.*No category at all/s', $html ) );
t( 'Uncategorized is never a heading', false === stripos( $html, '>Uncategorized<' ) );
t( 'every article has exactly one link', 7 === count( hrefs( $html ) ) && 7 === count( array_unique( hrefs( $html ) ) ) );
t( 'article links are the real permalinks', in_array( 'https://example.test/post-4/', hrefs( $html ), true ) );
t( 'headings link to the category archive', false !== strpos( $html, '<a href="https://example.test/category/sleep/">Sleep</a>' ) );
t( 'Other articles has no archive link', false !== strpos( $html, '<h2 class="twd-ap-index-title">Other articles ' ) );
t( 'each article shows its date', false !== strpos( $html, '<span class="twd-ap-index-date">1 February 2026</span>' ) );
t( 'a count is shown per category', false !== strpos( $html, '(3)' ) );
t( 'the jump list links to each section on the page', false !== strpos( $html, 'href="#twd-ap-index-sleep"' ) && false !== strpos( $html, 'id="twd-ap-index-sleep"' ) );
t( 'the index needs no JavaScript or script tag', false === stripos( $html, '<script' ) );

// ---------- which category an article is listed under ----------
reset_site();
add_post( 1, 'Both topics', array( 2, 3 ) );
$GLOBALS['meta'][1]['_twd_ap_primary_category'] = 3;
t( 'the primary category the grid uses decides the section', 1 === preg_match( '/>anxiety<.*Both topics/s', render_index() ) && false === strpos( render_index(), '>Sleep<' ) );
reset_site();
add_post( 1, 'Featured and a topic', array( 4, 2 ) );
$GLOBALS['meta'][1]['_twd_ap_primary_category'] = 4;
t( 'a Featured Articles primary yields to a real topic', false !== strpos( render_index(), '>Sleep<' ) && false === strpos( render_index(), 'Featured Articles' ) );
reset_site();
add_post( 1, 'Only featured', array( 4 ) );
t( 'an article only in Featured Articles still appears (under it)', false !== strpos( render_index(), 'Featured Articles' ) );

// ---------- the accent colour and the topic dropdown ----------
reset_site();
add_post( 1, 'A one', array( 2 ) ); add_post( 2, 'B two', array( 3 ) ); add_post( 3, 'C three', array( 5 ) );
TWD_AP_Grid_Settings::$s['accent_color'] = '#7a9b76';
$html = render_index();
t( 'accent: the index page carries the saved accent colour', false !== strpos( $html, 'style="--twd-ap-accent-color:#7a9b76;"' ) );
TWD_AP_Grid_Settings::$s['accent_color'] = '#2563eb';
t( 'dropdown: printed hidden, with All topics first then each topic and its count', 1 === preg_match( '/<div class="twd-ap-index-filter" hidden><select[^>]*><option value="">All topics<\/option><option value="twd-ap-index-anxiety">anxiety \(1\)<\/option><option value="twd-ap-index-boundaries">Boundaries \(1\)<\/option><option value="twd-ap-index-sleep">Sleep \(1\)<\/option>/', $html ) );
t( 'dropdown: the plain jump links stay in the HTML for crawlers', false !== strpos( $html, 'class="twd-ap-index-jump"' ) );
t( 'dropdown: every section is still in the HTML, none hidden', 3 === substr_count( $html, '<section class="twd-ap-index-section"' ) );
reset_site();
add_post( 1, 'A one', array( 2 ) ); add_post( 2, 'B two', array( 3 ) );
t( 'dropdown: not shown for a site with only two topics', false === strpos( render_index(), 'twd-ap-index-filter' ) );

// ---------- the call to action under the index ----------
reset_site();
add_post( 1, 'A one', array( 2 ), '2026-02-01' );
t( 'cta: nothing under the index by default', false === strpos( render_index(), 'twd-ap-cta' ) );
TWD_AP_Grid_Settings::$s = array_merge( TWD_AP_Grid_Settings::$s, array( 'cta_heading' => 'Ready?', 'cta_show_index' => true ) );
$html = render_index();
t( 'cta: shown inside the index, after the last section, when ticked', 1 === preg_match( '/<\/section><aside class="twd-ap-cta twd-ap-cta-index".*<\/aside><\/div>$/s', $html ) );
TWD_AP_Grid_Settings::$s = array_merge( TWD_AP_Grid_Settings::$s, array( 'cta_heading' => '', 'cta_show_index' => false ) );

// ---------- escaping and entities ----------
reset_site();
add_post( 1, "Tom's calm & <b>quiet</b> \"day\"", array( 2 ) );
$html = render_index();
t( 'escaping: a tag in a title is not output raw', false === strpos( $html, '<b>quiet</b>' ) && false !== strpos( $html, '&lt;b&gt;quiet&lt;/b&gt;' ) );
t( 'entities: the curly apostrophe is a real character, once', false !== strpos( $html, 'Tom’s' ) && false === strpos( $html, '&#8217;' ) );

// ---------- only published articles ----------
reset_site();
add_post( 1, 'Live one', array( 2 ) );
add_post( 2, 'Still a draft', array( 2 ), '2026-02-01', 'draft' );
add_post( 3, 'Private one', array( 2 ), '2026-02-01', 'private' );
$html = render_index();
t( 'drafts and private articles are not listed', false === strpos( $html, 'Still a draft' ) && false === strpos( $html, 'Private one' ) && 1 === count( hrefs( $html ) ) );
t( 'the query asks for published posts only', 'publish' === $GLOBALS['last_get_posts']['post_status'] );

// ---------- empty site ----------
reset_site();
$html = render_index();
t( 'empty site: a friendly line, no headings', false !== strpos( $html, 'No articles yet. Please check back soon.' ) && false === strpos( $html, '<h2' ) );

// ---------- a very large site is split by whole categories ----------
reset_site();
$GLOBALS['terms'] = array( 1 => $GLOBALS['terms'][1] );
$id = 0;
for ( $c = 10; $c < 18; $c++ ) {
	$GLOBALS['terms'][ $c ] = (object) array( 'term_id' => $c, 'name' => 'Topic ' . chr( 55 + $c ), 'slug' => 'topic-' . $c ); // Topic A..H
	for ( $n = 0; $n < 45; $n++ ) { $id++; add_post( $id, sprintf( 'Article %03d', $id ), array( $c ) ); }
}
$all = array();
$pages_seen = 0;
for ( $pg = 1; $pg <= 6; $pg++ ) {
	$_GET['twd_idx'] = (string) $pg;
	$html = render_index();
	if ( ! preg_match( '/aria-current="page">(\d+)/', $html, $cur ) || (int) $cur[1] !== $pg ) { break; }
	$all = array_merge( $all, hrefs( $html ) );
	$pages_seen++;
	if ( 1 === $pg ) { $first = $html; }
}
t( 'large site: 360 articles are split over several pages', $pages_seen >= 2 && 360 === $id );
$_GET = array(); $first = render_index();
t( 'large site: page 1 is the default and holds about 100 articles (whole categories)', count( hrefs( $first ) ) >= 90 && count( hrefs( $first ) ) <= 135 );
t( 'large site: pages 1 to the last together list every article once', 360 === count( $all ) && 360 === count( array_unique( $all ) ), count( $all ) . ' listed' );
t( 'large site: the pager has real links with rel next on page 1', false !== strpos( $first, 'rel="next"' ) && false !== strpos( $first, 'twd_idx=2' ) && false !== strpos( $first, 'aria-current="page"' ) );
$_GET['twd_idx'] = '2'; $second = render_index();
t( 'large site: page 2 has rel prev and rel next', false !== strpos( $second, 'rel="prev"' ) && false !== strpos( $second, 'rel="next"' ) );
$_GET['twd_idx'] = '9999'; $last = render_index();
t( 'large site: a page number past the end shows the last page, not an error', false !== strpos( $last, 'class="twd-ap-index-section"' ) && false === strpos( $last, 'rel="next"' ) );
$_GET['twd_idx'] = 'abc'; $odd = render_index();
$_GET = array(); $first = render_index();
t( 'large site: a junk page number shows page 1', hrefs( $odd ) === hrefs( $first ) );
$_GET = array();
reset_site();
for ( $i = 1; $i <= 60; $i++ ) { add_post( $i, 'Piece ' . $i, array( 2 ) ); }
t( 'a normal site is one page with no pager', false === strpos( render_index(), 'twd-ap-index-pager' ) && 60 === count( hrefs( render_index() ) ) );

// ---------- the Article index page ----------
reset_site();
add_post( 1, 'Some article', array( 2 ) );
TWD_AP_Index::instance()->maybe_create_page();
t( 'page: created once, published, with the shortcode', 1 === count( $GLOBALS['inserted'] ) && 'publish' === $GLOBALS['posts'][ $GLOBALS['inserted'][0] ]->post_status && false !== strpos( $GLOBALS['posts'][ $GLOBALS['inserted'][0] ]->post_content, '[twd_articles_index]' ) && 'page' === $GLOBALS['posts'][ $GLOBALS['inserted'][0] ]->post_type );
t( 'page: its id is stored', $GLOBALS['inserted'][0] === $GLOBALS['opts']['twd_ap_index_page_id'] );
t( 'page: the slug is article-index', 'article-index' === $GLOBALS['posts'][ $GLOBALS['inserted'][0] ]->slug );
t( 'page: index_url() gives its address while published', 'https://example.test/page-' . $GLOBALS['inserted'][0] . '/' === TWD_AP_Index::index_url() );
TWD_AP_Index::instance()->maybe_create_page();
t( 'page: running again does not create a second one', 1 === count( $GLOBALS['inserted'] ) );
$GLOBALS['posts'][ $GLOBALS['inserted'][0] ]->post_status = 'trash';
TWD_AP_Index::instance()->maybe_create_page();
t( 'page: a trashed page is not brought back', 1 === count( $GLOBALS['inserted'] ) );
t( 'page: index_url() is empty while it is trashed', '' === TWD_AP_Index::index_url() );

reset_site();
$GLOBALS['posts'][900] = (object) array( 'ID' => 900, 'slug' => 'my-own-index', 'post_title' => 'My index', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => 'Intro [twd_articles_index] outro', 'cats' => array(), 'post_date' => '2026-01-01' );
TWD_AP_Index::instance()->maybe_create_page();
t( 'page: an existing page that already has the shortcode is adopted, nothing created', 0 === count( $GLOBALS['inserted'] ) && 900 === $GLOBALS['opts']['twd_ap_index_page_id'] );

reset_site();
$GLOBALS['is_admin_user'] = false;
TWD_AP_Index::instance()->maybe_create_page();
t( 'page: only an administrator triggers creation', 0 === count( $GLOBALS['inserted'] ) && ! array_key_exists( 'twd_ap_index_page_id', $GLOBALS['opts'] ) );
t( 'page: no page set means no link', '' === TWD_AP_Index::index_url() );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
