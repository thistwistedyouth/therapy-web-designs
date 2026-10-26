<?php
// Tests for the server-rendered [twd_articles] grid: the first batch of cards is printed into the page
// HTML (real links for crawlers), shares one query and one card formatter with the REST route, and
// escapes everything. Real plugin classes (TWD_AP_Cards, TWD_AP_Shortcode, TWD_AP_REST), WordPress
// functions and WP_Query faked. The fake WP_Query sorts the way the real query is written to
// (featured first, then newest), so these checks prove both callers use the same arguments and the same
// card code; the real database ordering is checked on a live site.
//   php tests/twd-article-publisher/ssr-grid-test.php
define( 'ABSPATH', '/' );
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
function current_user_can() { return false; }
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
	function __construct( $a ) {
		$GLOBALS['last_args'] = $a;
		$p = live_posts();
		if ( ! empty( $a['category_name'] ) ) {
			$p = array_filter( $p, function ( $x ) use ( $a ) {
				foreach ( $x->cats as $c ) { if ( $GLOBALS['terms'][ $c ]->slug === $a['category_name'] ) { return true; } }
				return false;
			} );
		}
		usort( $p, function ( $x, $y ) {
			$fx = ! empty( $GLOBALS['meta'][ $x->ID ]['_twd_ap_featured'] ) ? 1 : 0;
			$fy = ! empty( $GLOBALS['meta'][ $y->ID ]['_twd_ap_featured'] ) ? 1 : 0;
			return $fy <=> $fx ?: strcmp( $y->post_date, $x->post_date );
		} );
		$this->posts = array_slice( $p, $a['offset'], $a['posts_per_page'] );
	}
}
class TWD_AP_Grid_Settings {
	static $s = array( 'cta_heading' => '', 'cta_text' => '', 'cta_button_label' => '', 'cta_button_url' => '', 'cta_show_grid' => false, 'cta_show_categories' => false, 'cta_show_index' => false, 'cta_show_articles' => false, 'topic_category_ids' => 'all', 'category_order' => array(), 'categories_count' => 4, 'posts_per_category' => 6, 'show_thumbnails' => true, 'read_more_color' => '#1d4ed8', 'accent_color' => '#2563eb' );
	static function get() { return self::$s; }
}
class TWD_AP_Frontend { static function current_user_allowed() { return ! empty( $GLOBALS['allowed'] ); } }

require $plugin . 'includes/class-twd-ap-cards.php';
require $plugin . 'includes/class-twd-ap-cta.php';
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
	$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['archive'] = 0;
	$GLOBALS['terms'] = array(
		1 => (object) array( 'term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized', 'count' => 0 ),
		2 => (object) array( 'term_id' => 2, 'name' => 'Anxiety', 'slug' => 'anxiety', 'count' => 0 ),
		3 => (object) array( 'term_id' => 3, 'name' => 'Sleep', 'slug' => 'sleep', 'count' => 0 ),
		4 => (object) array( 'term_id' => 4, 'name' => 'Empty topic', 'slug' => 'empty-topic', 'count' => 0 ),
	);
	TWD_AP_Grid_Settings::$s['show_thumbnails'] = true;
	TWD_AP_Grid_Settings::$s['posts_per_category'] = 6;
	TWD_AP_Grid_Settings::$s['topic_category_ids'] = 'all';
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
t( 'index link: absent when no index page exists yet', false === strpos( grid(), 'twd-ap-articles-index-link' ) );
$GLOBALS['opts']['twd_ap_index_page_id'] = 77; $GLOBALS['page_status'][77] = 'publish';
snap( 'indexlink' );
$html = grid();
t( 'index link: a pill button on the grid once the index page is published', false !== strpos( $html, '<a class="twd-ap-articles-index-pill" href="https://example.test/page-77/">Browse all articles' ) );
t( 'index link: also on a curated category list', false !== strpos( grid( array( 'category' => 'anxiety' ) ), 'twd-ap-articles-index-link' ) );
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
