<?php
// Tests for the call to action box: hidden until filled in, shown only where ticked, escaped, safe links.
// Real TWD_AP_Grid_Settings and TWD_AP_Cta classes, WordPress functions faked.
//   php tests/twd-article-publisher/cta-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['opt'] = array();
$GLOBALS['single'] = true;
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : ''; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
// Like the real esc_url_raw( $url, $protocols ): anything with a protocol not on the list becomes empty.
function esc_url_raw( $u, $protocols = array( 'http', 'https' ) ) {
	$u = trim( (string) $u );
	if ( '' === $u ) { return ''; }
	if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $u, $m ) ) { return in_array( strtolower( $m[1] ), $protocols, true ) ? $u : ''; }
	return 'http://' . ltrim( $u, '/' );
}
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function add_action() {}
function add_filter() {}
function is_admin() { return false; }
function is_singular() { return $GLOBALS['single']; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
require $plugin . 'includes/class-twd-ap-sanitizer.php';
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-cta.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function reset_cta() { $GLOBALS['opt'] = array(); $GLOBALS['single'] = true; $p = new ReflectionProperty( 'TWD_AP_Cta', 'appended' ); $p->setAccessible( true ); $p->setValue( null, false ); }
function cta_object() { return ( new ReflectionClass( 'TWD_AP_Cta' ) )->newInstanceWithoutConstructor(); }
$dump = array();

// ---------- nothing until it is filled in and ticked ----------
reset_cta();
t( 'default: nothing is set and nothing shows anywhere', '' === TWD_AP_Cta::html( 'grid' ) . TWD_AP_Cta::html( 'category' ) . TWD_AP_Cta::html( 'index' ) . TWD_AP_Cta::html( 'article' ) );
TWD_AP_Grid_Settings::save( array( 'cta_heading' => 'Ready to talk?', 'cta_text' => "A short chat is free.\nNo obligation.", 'cta_button_label' => 'Book a call', 'cta_button_url' => 'https://example.test/book' ) );
t( 'filled in but not ticked anywhere: still nothing shows', '' === TWD_AP_Cta::html( 'grid' ) . TWD_AP_Cta::html( 'article' ) );
TWD_AP_Grid_Settings::save( array( 'cta_show_grid' => true ) );
t( 'ticking the grid shows it on the grid only', '' !== TWD_AP_Cta::html( 'grid' ) && '' === TWD_AP_Cta::html( 'category' ) && '' === TWD_AP_Cta::html( 'index' ) && '' === TWD_AP_Cta::html( 'article' ) );
TWD_AP_Grid_Settings::save( array( 'cta_show_categories' => true, 'cta_show_index' => true, 'cta_show_articles' => true ) );
t( 'each place has its own switch', '' !== TWD_AP_Cta::html( 'category' ) && '' !== TWD_AP_Cta::html( 'index' ) && '' !== TWD_AP_Cta::html( 'article' ) );
t( 'an unknown place shows nothing', '' === TWD_AP_Cta::html( 'sidebar' ) );

// ---------- the markup ----------
$html = TWD_AP_Cta::html( 'grid' );
$dump['full'] = $html;
t( 'markup: heading, text with a line break, and the button', false !== strpos( $html, '<p class="twd-ap-cta-heading">Ready to talk?</p>' ) && false !== strpos( $html, 'A short chat is free.<br />' ) && 1 === preg_match( '/<a class="twd-ap-cta-button" href="https:\/\/example.test\/book" rel="noopener">Book a call<\/a>/', $html ) );
t( 'markup: it carries the saved accent colour', false !== strpos( $html, 'style="--twd-ap-accent-color:#2563eb;"' ) );
t( 'markup: it names its place for styling', false !== strpos( $html, 'twd-ap-cta twd-ap-cta-grid' ) && false !== strpos( TWD_AP_Cta::html( 'article' ), 'twd-ap-cta-article' ) );
t( 'markup: the heading is a paragraph, not a heading, so the page outline is untouched', false === strpos( $html, '<h1' ) && false === strpos( $html, '<h2' ) && false === strpos( $html, '<h3' ) );

// ---------- partly filled in ----------
TWD_AP_Grid_Settings::save( array( 'cta_button_url' => '' ) );
$html = TWD_AP_Cta::html( 'grid' );
t( 'a button label without a link: no button', false === strpos( $html, 'twd-ap-cta-button' ) && false !== strpos( $html, 'Ready to talk?' ) );
TWD_AP_Grid_Settings::save( array( 'cta_heading' => '', 'cta_text' => '' ) );
t( 'nothing but a label: nothing shows at all', '' === TWD_AP_Cta::html( 'grid' ) );
TWD_AP_Grid_Settings::save( array( 'cta_button_url' => 'tel:+441234567890', 'cta_button_label' => 'Call me' ) );
$dump['button'] = TWD_AP_Cta::html( 'grid' );
t( 'only a button and a link: just the button shows', false !== strpos( TWD_AP_Cta::html( 'grid' ), 'href="tel:+441234567890"' ) && false === strpos( TWD_AP_Cta::html( 'grid' ), 'twd-ap-cta-heading' ) );

// ---------- no em or en dashes in site copy ----------
reset_cta();
TWD_AP_Grid_Settings::save( array( 'cta_heading' => "Talk \u{2014} today", 'cta_text' => "Free \u{2013} no obligation", 'cta_button_label' => "Book \u{2014} now" ) );
$s = TWD_AP_Grid_Settings::get();
t( 'dashes: heading, text and button text have em and en dashes turned into commas', 'Talk, today' === $s['cta_heading'] && 'Free, no obligation' === $s['cta_text'] && 'Book, now' === $s['cta_button_label'], json_encode( $s ) );

// ---------- safe input ----------
reset_cta();
TWD_AP_Grid_Settings::save( array( 'cta_heading' => '<script>alert(1)</script>Hello "you" & me', 'cta_text' => '<b>bold</b> <img src=x onerror=alert(1)> text', 'cta_button_label' => str_repeat( 'x', 80 ), 'cta_button_url' => 'javascript:alert(1)', 'cta_show_grid' => true ) );
$s = TWD_AP_Grid_Settings::get();
t( 'saving: a script link is rejected', '' === $s['cta_button_url'] );
t( 'saving: tags are stripped from the heading and text', false === strpos( $s['cta_heading'], '<script' ) && false === strpos( $s['cta_text'], '<b>' ) && false === strpos( $s['cta_text'], '<img' ) );
t( 'saving: long values are cut to their limits', 40 === strlen( $s['cta_button_label'] ) );
TWD_AP_Grid_Settings::save( array( 'cta_button_url' => 'mailto:hello@example.test', 'cta_button_label' => 'Email me' ) );
t( 'saving: mailto links are allowed', 'mailto:hello@example.test' === TWD_AP_Grid_Settings::get()['cta_button_url'] );
TWD_AP_Grid_Settings::save( array( 'cta_button_url' => 'ftp://example.test/x' ) );
t( 'saving: other protocols are rejected', '' === TWD_AP_Grid_Settings::get()['cta_button_url'] );
$GLOBALS['opt']['twd_ap_grid_settings']['cta_heading'] = 'Hi <script>alert(1)</script> "x"';
$html = TWD_AP_Cta::html( 'grid' );
t( 'output: a value that got into the option unescaped is still escaped on the page', false === strpos( $html, '<script' ) && false !== strpos( $html, '&lt;script&gt;' ) );
$GLOBALS['opt']['twd_ap_grid_settings']['cta_button_label'] = 'Go';
$GLOBALS['opt']['twd_ap_grid_settings']['cta_button_url'] = 'https://example.test/?a=1&b="2"';
t( 'output: the link is escaped inside the attribute', false !== strpos( TWD_AP_Cta::html( 'grid' ), 'href="https://example.test/?a=1&amp;b=&quot;2&quot;"' ) );

// ---------- the end of every article ----------
reset_cta();
TWD_AP_Grid_Settings::save( array( 'cta_heading' => 'Ready?', 'cta_show_articles' => true ) );
$out = cta_object()->append_to_article( '<div class="twd-ap-article-content"><p>Body</p></div>' );
t( 'article: the box comes after the article wrapper, not inside it', 1 === preg_match( '/^<div class="twd-ap-article-content"><p>Body<\/p><\/div><aside class="twd-ap-cta twd-ap-cta-article"/', $out ) );
t( 'article: added once even if the content filter runs again', '<p>x</p>' === cta_object()->append_to_article( '<p>x</p>' ) );
reset_cta();
$GLOBALS['single'] = false;
t( 'article: not added on a page that is not a single article', '<p>x</p>' === cta_object()->append_to_article( '<p>x</p>' ) );
reset_cta();
TWD_AP_Grid_Settings::save( array( 'cta_show_articles' => false ) );
t( 'article: not added when that place is switched off', '<p>x</p>' === cta_object()->append_to_article( '<p>x</p>' ) );

if ( getenv( 'TWD_CTA_DUMP' ) ) { file_put_contents( getenv( 'TWD_CTA_DUMP' ), json_encode( $dump ) ); }
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
