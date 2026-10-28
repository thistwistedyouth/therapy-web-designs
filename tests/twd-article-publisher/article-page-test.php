<?php
// Tests for the article page options added in 1.50.0: the new settings and how bad input is handled, the
// end-of-article row (Share as a swipe book and the Back link), the featured image under the title, and the
// theme's own "Tagged ..." line taken out. Real TWD_AP_Grid_Settings, TWD_AP_Swipebook and TWD_AP_Article_Page;
// WordPress functions faked.
//   php tests/twd-article-publisher/article-page-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

$GLOBALS['opt'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['filters'] = array();
$GLOBALS['single'] = true; $GLOBALS['loop'] = true; $GLOBALS['admin'] = false; $GLOBALS['qv'] = array();
$GLOBALS['current'] = 1; $GLOBALS['queried'] = 1; $GLOBALS['thumb'] = array(); $GLOBALS['imgargs'] = null; $GLOBALS['enqueued'] = 0;
$GLOBALS['index_url'] = '';
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $c ) ? $c : ''; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
// Behaves like WordPress's esc_url_raw(): a bare word gets http:// put in front, //host and /path are kept, and a scheme that is not allowed gives ''.
function esc_url_raw( $s, $protocols = null ) {
	$s = (string) $s;
	if ( false === strpos( $s, ':' ) && ! in_array( substr( $s, 0, 1 ), array( '/', '#', '?' ), true ) ) { $s = 'http://' . $s; }
	if ( preg_match( '#^([a-z][a-z0-9+.-]*):#i', $s, $m ) && null !== $protocols && ! in_array( strtolower( $m[1] ), $protocols, true ) ) { return ''; }
	return $s;
}
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function add_filter( $tag, $cb, $prio = 10 ) { $GLOBALS['filters'][] = array( $tag, is_array( $cb ) ? $cb[1] : $cb, $prio ); }
function add_action() {}
function add_shortcode() {}
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function get_permalink( $p ) { return 'https://example.test/article-' . ( is_object( $p ) ? $p->ID : $p ) . '/'; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function is_admin() { return $GLOBALS['admin']; }
function is_singular() { return $GLOBALS['single']; }
function in_the_loop() { return $GLOBALS['loop']; }
function is_main_query() { return true; }
function get_query_var( $k ) { return $GLOBALS['qv'][ $k ] ?? ''; }
function get_the_ID() { return $GLOBALS['current']; }
function get_queried_object_id() { return $GLOBALS['queried']; }
function get_post( $id = null ) { return $GLOBALS['posts'][ $id ?? $GLOBALS['current'] ] ?? null; }
function get_post_meta( $id, $k, $single = true ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function get_post_thumbnail_id( $p = null ) { return $GLOBALS['thumb'][ $GLOBALS['current'] ] ?? 0; }
function wp_get_attachment_image( $id, $size, $icon, $attr ) { $GLOBALS['imgargs'] = array( $id, $size, $attr ); return '<img src="https://example.test/img-' . $id . '.jpg" class="' . $attr['class'] . '" alt="" loading="' . $attr['loading'] . '" />'; }
class TWD_AP_Article_Style { static function enqueue() { $GLOBALS['enqueued']++; } }
class TWD_AP_Index { static function index_url() { return $GLOBALS['index_url']; } }
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-swipebook.php';
require $plugin . 'includes/class-twd-ap-article-page.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function mkpost( $id, $status = 'publish' ) { $GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => 'post', 'post_status' => $status ); }
function ref( $class ) { return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor(); }
function reset_page() {
	$GLOBALS['opt'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['single'] = true; $GLOBALS['loop'] = true;
	$GLOBALS['admin'] = false; $GLOBALS['qv'] = array(); $GLOBALS['current'] = 1; $GLOBALS['queried'] = 1; $GLOBALS['thumb'] = array();
	$GLOBALS['imgargs'] = null; $GLOBALS['enqueued'] = 0; $GLOBALS['index_url'] = '';
	mkpost( 1 );
}

// ---------- the settings ----------
reset_page();
$d = TWD_AP_Grid_Settings::get();
t( 'defaults: every new option is off or empty, so a site that never touches them is unchanged', 'with_related' === $d['topics_place'] && false === $d['featured_image_show'] && false === $d['hide_theme_tags'] && false === $d['back_link_show'] && '' === $d['back_link_url'] );
foreach ( array( 'with_related', 'auto', 'shortcode', 'off' ) as $place ) {
	TWD_AP_Grid_Settings::save( array( 'topics_place' => $place ) );
	t( 'topics place: ' . $place . ' is saved', $place === TWD_AP_Grid_Settings::get()['topics_place'] );
}
TWD_AP_Grid_Settings::save( array( 'topics_place' => 'shortcode' ) );
foreach ( array( 'Auto', 'everywhere', '', 0, true, array( 'off' ), '<script>' ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'topics_place' => $bad ) );
	t( 'topics place: ' . json_encode( $bad ) . ' is ignored, shortcode stays', 'shortcode' === TWD_AP_Grid_Settings::get()['topics_place'] );
}
foreach ( array( 'featured_image_show', 'hide_theme_tags', 'back_link_show' ) as $key ) {
	TWD_AP_Grid_Settings::save( array( $key => true ) );
	t( $key . ': can be ticked', true === TWD_AP_Grid_Settings::get()[ $key ] );
	TWD_AP_Grid_Settings::save( array( 'accent_color' => '#223344' ) );
	t( $key . ': stays ticked when something else is saved', true === TWD_AP_Grid_Settings::get()[ $key ] );
	TWD_AP_Grid_Settings::save( array( $key => false ) );
	t( $key . ': can be unticked', false === TWD_AP_Grid_Settings::get()[ $key ] );
}
foreach ( array( '/writing', '/writing#twd-sk-more-writing', '/a/b/?x=1#y', 'https://example.com/articles/', 'http://example.com' ) as $good ) {
	TWD_AP_Grid_Settings::save( array( 'back_link_url' => ' ' . $good . ' ' ) );
	t( 'back address: ' . $good . ' is kept (and trimmed)', $good === TWD_AP_Grid_Settings::get()['back_link_url'], TWD_AP_Grid_Settings::get()['back_link_url'] );
}
TWD_AP_Grid_Settings::save( array( 'back_link_url' => '/writing' ) );
t( 'back address: saving something else leaves it alone', ( TWD_AP_Grid_Settings::save( array( 'accent_color' => '#334455' ) ) || true ) && '/writing' === TWD_AP_Grid_Settings::get()['back_link_url'] );
foreach ( array( 'writing', '//evil.example/x', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,x', 'ftp://example.com/x', '/a b', "/a\tb", '/a"b', "/a'b", '/a<b', '/a>b', '/a\\b', '<script>alert(1)</script>', 'mailto:a@b.c', '   ', str_repeat( '/a', 200 ) ) as $bad ) {
	TWD_AP_Grid_Settings::save( array( 'back_link_url' => $bad ) );
	t( 'back address: ' . json_encode( substr( $bad, 0, 30 ) ) . ' is not kept (cleared, never a link that goes elsewhere)', '' === TWD_AP_Grid_Settings::get()['back_link_url'], TWD_AP_Grid_Settings::get()['back_link_url'] );
	TWD_AP_Grid_Settings::save( array( 'back_link_url' => '/writing' ) );
}
TWD_AP_Grid_Settings::save( array( 'back_link_url' => '' ) );
t( 'back address: empty clears it', '' === TWD_AP_Grid_Settings::get()['back_link_url'] );

// ---------- the end of the article ----------
reset_page();
$sb = ref( 'TWD_AP_Swipebook' );
$legacy_row = '<p class="twd-ap-swipebook-row twd-ap-swipebook-row-end"><a class="twd-ap-swipebook-btn" style="' . esc_attr( ( function () { $m = new ReflectionMethod( 'TWD_AP_Swipebook', 'color_custom_properties' ); $m->setAccessible( true ); return $m->invoke( null, '#5b8a72' ); } )() ) . '" href="https://example.test/article-1/?twd_ap_swipebook=1" data-twd-ap-post-id="1">Share as a swipe book &#8599;</a></p>';
t( 'end row, Back link off: exactly the original Share button on the right', $legacy_row === $sb->append_button( '' ), $sb->append_button( '' ) );
t( 'end row, Back link off: no Back link and no centring class', false === strpos( $sb->append_button( '' ), 'back-btn' ) && false === strpos( $sb->append_button( '' ), 'end-centred' ) );
TWD_AP_Grid_Settings::save( array( 'swipebook_button_position' => 'top' ) );
t( 'end row, Back link off, button "under the title only": nothing at the end, as before', '<p>x</p>' === $sb->append_button( '<p>x</p>' ) );
TWD_AP_Grid_Settings::save( array( 'swipebook_button_position' => 'both', 'back_link_show' => true ) );
$row = $sb->append_button( '<p>x</p>' );
t( 'end row, Back link on: one centred row after the text', 0 === strpos( $row, '<p>x</p><p class="twd-ap-swipebook-row twd-ap-swipebook-row-end twd-ap-end-centred">' ) && 1 === substr_count( $row, '<p class="twd-ap-swipebook-row' ), $row );
t( 'end row, Back link on: Share first, then Back', strpos( $row, 'Share as a swipe book' ) < strpos( $row, 'Back to all articles' ) );
t( 'end row, Back link on: the Back link is a plain link with the book colour, and is not the swipe book button (that one opens the overlay)', false !== strpos( $row, '<a class="twd-ap-back-btn" style="--twd-ap-swipebook-color:#5b8a72;' ) && 1 === substr_count( $row, 'twd-ap-swipebook-btn' ) && 1 === substr_count( $row, 'data-twd-ap-post-id' ) );
t( 'end row, Back link on, nothing set: it goes to the home page when there is no Article index page', false !== strpos( $row, 'href="https://example.test/"' ), $row );
$GLOBALS['index_url'] = 'https://example.test/article-index/';
t( 'end row, Back link on, nothing set: it goes to the Article index page when there is one', false !== strpos( $sb->append_button( '' ), 'href="https://example.test/article-index/"' ) );
TWD_AP_Grid_Settings::save( array( 'back_link_url' => '/writing#twd-sk-more-writing' ) );
t( 'end row: a path with a #section is made absolute with this site\'s address and keeps the section', false !== strpos( $sb->append_button( '' ), 'href="https://example.test/writing#twd-sk-more-writing"' ) );
TWD_AP_Grid_Settings::save( array( 'back_link_url' => 'https://other.example/all?a=1&b=2' ) );
t( 'end row: a full address is used as it is, with the & escaped', false !== strpos( $sb->append_button( '' ), 'href="https://other.example/all?a=1&amp;b=2"' ) );
TWD_AP_Grid_Settings::save( array( 'back_link_url' => '' ) );
$GLOBALS['meta'][1]['_twd_ap_swipebook_off'] = '1';
$row = $sb->append_button( '' );
t( 'end row, swipe book switched off for this article: the Back link alone, still centred', false !== strpos( $row, 'Back to all articles' ) && false === strpos( $row, 'Share as a swipe book' ) && false !== strpos( $row, 'end-centred' ), $row );
$GLOBALS['meta'] = array();
TWD_AP_Grid_Settings::save( array( 'swipebook_button_position' => 'top' ) );
$row = $sb->append_button( '' );
t( 'end row, button "under the title only", Back link on: the Back link alone at the end', false !== strpos( $row, 'Back to all articles' ) && false === strpos( $row, 'Share as a swipe book' ), $row );
TWD_AP_Grid_Settings::save( array( 'swipebook_button_position' => 'both' ) );
mkpost( 2, 'draft' ); $GLOBALS['current'] = 2;
t( 'end row: a draft or preview gets no row at all', '' === TWD_AP_Swipebook::end_row_html( get_post() ) );
$GLOBALS['current'] = 1;
$GLOBALS['single'] = false;
t( 'end row: not added on anything that is not a single article', '<p>x</p>' === $sb->append_button( '<p>x</p>' ) );
$GLOBALS['single'] = true; $GLOBALS['qv'] = array( 'twd_ap_swipebook' => '1' );
t( 'end row: not added on the swipe book page itself', '<p>x</p>' === $sb->append_button( '<p>x</p>' ) );
$GLOBALS['qv'] = array();

// ---------- the featured image ----------
reset_page();
$ap = ref( 'TWD_AP_Article_Page' );
$GLOBALS['thumb'][1] = 77;
$body = '<p>The article text.</p>';
t( 'image: off by default, the text is returned untouched', $body === $ap->prepend_image( $body ) );
TWD_AP_Grid_Settings::save( array( 'featured_image_show' => true ) );
$out = $ap->prepend_image( $body );
t( 'image: on, a figure with the picture goes first, inside what the article wrapper will wrap, before the text', 0 === strpos( $out, '<figure class="twd-ap-featured-figure twd-ap-featured-wide"><img ' ) && substr( $out, -strlen( $body ) ) === $body, $out );
t( 'image: it asks for the large size, loads it at once and gives the column width as the size hint', 77 === $GLOBALS['imgargs'][0] && 'large' === $GLOBALS['imgargs'][1] && 'eager' === $GLOBALS['imgargs'][2]['loading'] && 'high' === $GLOBALS['imgargs'][2]['fetchpriority'] && false !== strpos( $GLOBALS['imgargs'][2]['sizes'], '720px' ) && 'twd-ap-featured-img' === $GLOBALS['imgargs'][2]['class'] );
t( 'image: it makes the article stylesheet load', $GLOBALS['enqueued'] > 0 );
foreach ( array( 'wide' => 'wide', '4-3' => '4-3', 'square' => 'square' ) as $shape => $class ) {
	TWD_AP_Grid_Settings::save( array( 'thumb_shape' => $shape ) );
	t( 'image: the card shape "' . $shape . '" gives class ' . $class, false !== strpos( $ap->prepend_image( $body ), 'twd-ap-featured-figure twd-ap-featured-' . $class . '"' ) );
}
t( 'image: an unknown shape value is the wide strip (the setting itself ignores bad input, so this is the class function on its own)', 'twd-ap-featured-wide' === TWD_AP_Article_Page::shape_class( 'huge' ) && 'twd-ap-featured-wide' === TWD_AP_Article_Page::shape_class( '' ) && 'twd-ap-featured-wide' === TWD_AP_Article_Page::shape_class( null ) );
$GLOBALS['thumb'] = array();
t( 'image: an article with no featured image is returned untouched', $body === $ap->prepend_image( $body ) );
$GLOBALS['thumb'][1] = 77;
$buttons = '<div class="twd-ap-swipebook-top"><p class="twd-ap-swipebook-row"><a class="twd-ap-swipebook-btn" href="#">View as a swipe book &#8599;</a></p></div>';
$out = $ap->prepend_image( $buttons . $body );
t( 'image: it lands above the swipe book buttons, which stay above the text', 0 === strpos( $out, '<figure' ) && strpos( $out, '</figure>' ) < strpos( $out, 'twd-ap-swipebook-top' ) && strpos( $out, 'twd-ap-swipebook-top' ) < strpos( $out, 'The article text' ) );
$starts = '<p><img class="alignnone wp-image-77 size-large" src="x.jpg" alt="" /></p><p>Text</p>';
t( 'image: not added when the article text already opens with this picture', $starts === $ap->prepend_image( $starts ) );
t( 'image: nor when the swipe book buttons come first and the picture follows them', $buttons . $starts === $ap->prepend_image( $buttons . $starts ) );
$other = '<p><img class="wp-image-78" src="y.jpg" alt="" /></p><p>Text</p>';
t( 'image: a different picture at the top of the text does not stop the featured image', 0 === strpos( $ap->prepend_image( $other ), '<figure' ) );
$wp770 = '<p><img class="wp-image-770" src="y.jpg" alt="" /></p><p>Text</p>';
t( 'image: picture 770 is not mistaken for picture 77', 0 === strpos( $ap->prepend_image( $wp770 ), '<figure' ) );
$late = '<p>' . str_repeat( 'Words and more words. ', 40 ) . '</p><p><img class="wp-image-77" src="x.jpg" alt="" /></p>';
t( 'image: the same picture far down the text (not an opening picture) does not stop it', 0 === strpos( $ap->prepend_image( $late ), '<figure' ) );
$GLOBALS['single'] = false;
t( 'image: not on a page that is not a single article', $body === $ap->prepend_image( $body ) );
$GLOBALS['single'] = true; $GLOBALS['loop'] = false;
t( 'image: not outside the main loop (a widget or excerpt)', $body === $ap->prepend_image( $body ) );
$GLOBALS['loop'] = true; $GLOBALS['qv'] = array( 'twd_ap_swipebook' => '1' );
t( 'image: not on the swipe book page itself', $body === $ap->prepend_image( $body ) );
$GLOBALS['qv'] = array(); $GLOBALS['admin'] = true;
t( 'image: not in the admin', $body === $ap->prepend_image( $body ) );
$GLOBALS['admin'] = false;

// ---------- the theme's tag line ----------
reset_page();
$ap = ref( 'TWD_AP_Article_Page' );
$list = '<span class="tag-links">Tagged <a href="#">x</a></span>';
t( 'tags: off by default, the theme\'s list is left alone', $list === $ap->drop_theme_tags( $list ) );
TWD_AP_Grid_Settings::save( array( 'hide_theme_tags' => true ) );
t( 'tags: on, the list the theme asks for is empty, so nothing is printed', '' === $ap->drop_theme_tags( $list ) );
$GLOBALS['queried'] = 9;
t( 'tags: on, but the tags of another article (a related post on the page) are left alone', $list === $ap->drop_theme_tags( $list ) );
$GLOBALS['queried'] = 1; $GLOBALS['single'] = false;
t( 'tags: on, but not on a page that is not a single article', $list === $ap->drop_theme_tags( $list ) );
$GLOBALS['single'] = true; $GLOBALS['admin'] = true;
t( 'tags: not changed in the admin', $list === $ap->drop_theme_tags( $list ) );
$GLOBALS['admin'] = false;
t( 'tags: a body class lets the stylesheet hide the theme\'s empty box', array( 'x', 'twd-ap-no-theme-tags' ) === $ap->body_class( array( 'x' ) ) );
TWD_AP_Grid_Settings::save( array( 'hide_theme_tags' => false ) );
t( 'tags: off, no body class', array( 'x' ) === $ap->body_class( array( 'x' ) ) );

// ---------- wiring and stylesheet ----------
$reg = array_map( 'json_encode', $GLOBALS['filters'] );
$reg_all = $GLOBALS['filters'];
ref( 'TWD_AP_Article_Page' );
$ctor = new ReflectionMethod( 'TWD_AP_Article_Page', '__construct' ); $ctor->setAccessible( true ); $ctor->invoke( ref( 'TWD_AP_Article_Page' ) );
$reg = array_map( 'json_encode', $GLOBALS['filters'] );
t( 'registered: the picture is added to the_content at 12 (after the buttons at 11, before the wrapper at 20), and the tag list and the body class are filtered', in_array( json_encode( array( 'the_content', 'prepend_image', 12 ) ), $reg, true ) && in_array( json_encode( array( 'the_tags', 'drop_theme_tags', 10 ) ), $reg, true ) && in_array( json_encode( array( 'body_class', 'body_class', 10 ) ), $reg, true ) );
$css = file_get_contents( $plugin . 'assets/article-content.css' );
t( 'stylesheet: the centred end row, the Back button (same guarded rule as the swipe book button, doubled class and hover), the picture shapes and the empty tag box are all there', false !== strpos( $css, '.twd-ap-swipebook-row.twd-ap-swipebook-row-end.twd-ap-end-centred' ) && false !== strpos( $css, 'a.twd-ap-back-btn.twd-ap-back-btn {' ) && false !== strpos( $css, 'a.twd-ap-back-btn.twd-ap-back-btn:hover' ) && false !== strpos( $css, 'aspect-ratio: 21 / 9' ) && false !== strpos( $css, 'aspect-ratio: 4 / 3' ) && false !== strpos( $css, 'aspect-ratio: 1 / 1' ) && false !== strpos( $css, 'body.twd-ap-no-theme-tags .post-tags' ) );
t( 'stylesheet: every new declaration carries !important (a theme or builder would otherwise win)', 0 === preg_match_all( '/^\s+(?:display|margin|padding|width|max-width|height|overflow|border-radius|line-height|object-fit|aspect-ratio|justify-content|flex-wrap|gap|text-align)\s*:[^;!]*;/m', substr( $css, strpos( $css, '1.50.0: with the Back link' ) ), $m ) || 0 === count( $m[0] ) , json_encode( $m[0] ?? array() ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
