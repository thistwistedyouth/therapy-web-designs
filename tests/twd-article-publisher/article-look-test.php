<?php
// Tests for "Article look": the settings, and the style attribute printed on the article wrapper. Real
// TWD_AP_Grid_Settings and TWD_AP_Article_Style classes, WordPress functions faked.
//   php tests/twd-article-publisher/article-look-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['opt'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : ''; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function add_action() {}
function add_filter() {}
function is_admin() { return false; }
function is_singular() { return true; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
define( 'TWD_AP_URL', 'https://example.test/' );
define( 'TWD_AP_VERSION', 'test' );
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-article-style.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function wrap( $html = '<p>Body</p>' ) {
	// wrap_content() only wraps once per request; reset that for each check.
	$p = new ReflectionProperty( 'TWD_AP_Article_Style', 'wrapped' ); $p->setAccessible( true ); $p->setValue( null, false );
	$r = new ReflectionClass( 'TWD_AP_Article_Style' );
	return $r->newInstanceWithoutConstructor()->wrap_content( $html );
}
$dump = array();

// ---------- defaults: nothing printed, so existing sites look exactly as before ----------
$s = TWD_AP_Grid_Settings::get();
t( 'defaults: no colour is set and the choices are the plugin defaults', '' === $s['look_text_color'] && '' === $s['look_heading_color'] && '' === $s['look_link_color'] && '' === $s['look_quote_color'] && 'system' === $s['look_font'] && 'medium' === $s['look_size'] && 'normal' === $s['look_spacing'] );
t( 'defaults: no style variables at all', '' === TWD_AP_Article_Style::look_style() );
t( 'defaults: the wrapper has no style attribute', '<div class="twd-ap-article-content"><p>Body</p></div>' === wrap() );
$dump['default'] = wrap();

// ---------- saving ----------
TWD_AP_Grid_Settings::save( array( 'look_text_color' => '#112233', 'look_heading_color' => '#445566', 'look_link_color' => '#7a9b76', 'look_quote_color' => '#abc' ) );
$s = TWD_AP_Grid_Settings::get();
t( 'saving: valid hex colours are kept', '#112233' === $s['look_text_color'] && '#445566' === $s['look_heading_color'] && '#7a9b76' === $s['look_link_color'] && '#abc' === $s['look_quote_color'] );
TWD_AP_Grid_Settings::save( array( 'look_text_color' => 'red; background:url(x)', 'look_link_color' => 'javascript:1' ) );
$s = TWD_AP_Grid_Settings::get();
t( 'saving: a colour that is not a hex value is ignored, the old one stays', '#112233' === $s['look_text_color'] && '#7a9b76' === $s['look_link_color'] );
TWD_AP_Grid_Settings::save( array( 'look_heading_color' => '' ) );
t( 'saving: an empty colour clears it back to the default', '' === TWD_AP_Grid_Settings::get()['look_heading_color'] );
TWD_AP_Grid_Settings::save( array( 'look_font' => 'serif', 'look_size' => 'large', 'look_spacing' => 'relaxed' ) );
$s = TWD_AP_Grid_Settings::get();
t( 'saving: font, size and spacing choices are kept', 'serif' === $s['look_font'] && 'large' === $s['look_size'] && 'relaxed' === $s['look_spacing'] );
TWD_AP_Grid_Settings::save( array( 'look_font' => 'Comic Sans; x', 'look_size' => 'huge', 'look_spacing' => '0' ) );
$s = TWD_AP_Grid_Settings::get();
t( 'saving: a choice that is not on the list is ignored', 'serif' === $s['look_font'] && 'large' === $s['look_size'] && 'relaxed' === $s['look_spacing'] );
TWD_AP_Grid_Settings::save( array( 'accent_color' => '#336699' ) );
t( 'saving: changing something else leaves the look alone', '#112233' === TWD_AP_Grid_Settings::get()['look_text_color'] && 'serif' === TWD_AP_Grid_Settings::get()['look_font'] );

// ---------- the style on the article wrapper ----------
$css = TWD_AP_Article_Style::look_style();
t( 'style: text, link and quote colours become the matching variables', false !== strpos( $css, '--twd-ap-content-text:#112233;' ) && false !== strpos( $css, '--twd-ap-content-link:#7a9b76;' ) && false !== strpos( $css, '--twd-ap-content-quote:#abc;' ) );
t( 'style: a cleared heading colour prints no heading variable (headings follow the text)', false === strpos( $css, '--twd-ap-content-heading' ) );
t( 'style: serif font, large size and relaxed spacing', false !== strpos( $css, '--twd-ap-content-font:Georgia' ) && false !== strpos( $css, '--twd-ap-content-size:19px;' ) && false !== strpos( $css, '--twd-ap-content-size-m:17px;' ) && false !== strpos( $css, '--twd-ap-content-leading:1.95;' ) );
TWD_AP_Grid_Settings::save( array( 'look_font' => 'theme', 'look_spacing' => 'compact', 'look_size' => 'medium', 'look_heading_color' => '#445566' ) );
$css = TWD_AP_Article_Style::look_style();
t( 'style: the theme font is inherit, compact spacing, medium size prints nothing', false !== strpos( $css, 'font-family:inherit !important;' ) && false !== strpos( $css, '--twd-ap-content-leading:1.55;' ) && false === strpos( $css, 'content-size' ) && false !== strpos( $css, '--twd-ap-content-heading:#445566;' ) );
$html = wrap();
t( 'wrapper: the style attribute is on the article wrapper and the body is unchanged', 1 === preg_match( '/^<div class="twd-ap-article-content" style="[^"]+"><p>Body<\/p><\/div>$/', $html ) );
$dump['custom'] = $html;
// A value that somehow got into the option directly still cannot break out of the attribute.
$GLOBALS['opt']['twd_ap_grid_settings']['look_text_color'] = '#fff"><script>alert(1)</script>';
t( 'wrapper: a bad stored colour is dropped, never printed', false === strpos( wrap(), 'script' ) && false === strpos( TWD_AP_Article_Style::look_style(), 'alert' ) );
$GLOBALS['opt']['twd_ap_grid_settings']['look_font'] = 'x; background:red';
t( 'wrapper: a bad stored font is dropped, never printed', false === strpos( TWD_AP_Article_Style::look_style(), 'background' ) );

// ---------- the stylesheet reads the variables and keeps today's values as fallbacks ----------
$c = file_get_contents( $plugin . 'assets/article-content.css' );
t( 'css: headings use the heading variable and fall back to the text colour', 3 === substr_count( $c, 'color: var(--twd-ap-content-heading, var(--twd-ap-content-text)) !important;' ) );
t( 'css: the quote uses the quote variable and falls back to the old link bar and muted text', false !== strpos( $c, 'border-left: 3px solid var(--twd-ap-content-quote, var(--twd-ap-content-link)) !important;' ) && false !== strpos( $c, 'color: var(--twd-ap-content-quote, var(--twd-ap-content-muted)) !important;' ) );
t( 'css: font, size and line spacing read their variables with the old values as fallbacks', false !== strpos( $c, 'font-size: var(--twd-ap-content-size, 17px) !important;' ) && false !== strpos( $c, 'line-height: var(--twd-ap-content-leading, 1.75) !important;' ) && false !== strpos( $c, 'font-family: var(--twd-ap-content-font, -apple-system' ) && false !== strpos( $c, 'font-size: var(--twd-ap-content-size-m, 16px) !important;' ) );

if ( getenv( 'TWD_LOOK_DUMP' ) ) { file_put_contents( getenv( 'TWD_LOOK_DUMP' ), json_encode( $dump ) ); }
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
