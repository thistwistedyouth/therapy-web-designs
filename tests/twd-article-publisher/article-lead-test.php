<?php
// 1.52.2: the per-article tick "Do not show the introduction line under the title" (post meta _twd_ap_hide_lead).
// Real TWD_AP_Article_Style, WordPress functions faked. Checks that with the tick off the article header prints
// exactly the same bytes as 1.52.1 did, that the tick hides only the lead line, that the excerpt stays in
// every other place, and that the popup, the REST save and the REST read are wired the same way as hide_hero.
//   php tests/twd-article-publisher/article-lead-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['opt']  = array();
$GLOBALS['meta'] = array();
$GLOBALS['post'] = null;
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : ''; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function add_action() {}
function add_filter() {}
function is_admin() { return false; }
function is_singular() { return true; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_post( $p = null ) { if ( is_object( $p ) ) { return $p; } return $GLOBALS['post']; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function has_excerpt( $post ) { return '' !== trim( (string) $post->post_excerpt ); }
function get_the_excerpt( $post ) { return (string) $post->post_excerpt; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function strip_shortcodes( $s ) { return (string) $s; }
function get_the_date( $f, $post ) { return '4 November 2026'; }
function wp_attachment_is_image( $id ) { return false; }
function get_post_thumbnail_id( $post ) { return 0; }
function wp_get_attachment_image() { return ''; }
define( 'TWD_AP_URL', 'https://example.test/' );
define( 'TWD_AP_VERSION', 'test' );
class TWD_AP_Related {
	public static function topic_for( $id ) { $o = new stdClass(); $o->name = 'Anxiety &amp; Sleep'; return $o; }
}
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-article-style.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function make_post( $id, $excerpt ) {
	$p = new stdClass();
	$p->ID = $id;
	$p->post_excerpt = $excerpt;
	$p->post_content = '<p>' . str_repeat( 'word ', 450 ) . '</p>';
	$GLOBALS['post'] = $p;
	return $p;
}

// ---------- 1.52.1 output, recorded from the code before the option existed ----------
$golden_with_lead = '<div class="twd-ap-article-header"><p class="twd-ap-article-lead">A short introduction line, with &quot;quotes&quot; &amp; more.</p><p class="twd-ap-article-meta"><span class="twd-ap-article-topic">Anxiety &amp; Sleep</span><span class="twd-ap-article-meta-dot" aria-hidden="true">&middot;</span><span>4 November 2026</span><span class="twd-ap-article-meta-dot" aria-hidden="true">&middot;</span><span>3 min read</span></p></div>';
$golden_no_lead   = '<div class="twd-ap-article-header"><p class="twd-ap-article-meta"><span class="twd-ap-article-topic">Anxiety &amp; Sleep</span><span class="twd-ap-article-meta-dot" aria-hidden="true">&middot;</span><span>4 November 2026</span><span class="twd-ap-article-meta-dot" aria-hidden="true">&middot;</span><span>3 min read</span></p></div>';
$lead_para        = '<p class="twd-ap-article-lead">A short introduction line, with &quot;quotes&quot; &amp; more.</p>';
$excerpt_text     = 'A short <b>introduction</b> line, with "quotes" & more.';

$post = make_post( 7, $excerpt_text );
$out  = TWD_AP_Article_Style::header_html( $post );
t( 'tick off, excerpt written: the header is byte for byte what 1.52.1 printed', $golden_with_lead === $out, "\n" . $out );
t( 'tick off: the lead line is there', false !== strpos( $out, 'class="twd-ap-article-lead"' ) );

$out2 = TWD_AP_Article_Style::header_html( make_post( 8, '' ) );
t( 'tick off, no excerpt: the header is byte for byte what 1.52.1 printed', $golden_no_lead === $out2, "\n" . $out2 );

// ---------- the tick on ----------
$GLOBALS['meta'][7]['_twd_ap_hide_lead'] = 1;
$on = TWD_AP_Article_Style::header_html( make_post( 7, $excerpt_text ) );
t( 'tick on: no lead line is printed', false === strpos( $on, 'twd-ap-article-lead' ) && false === strpos( $on, 'introduction' ) );
t( 'tick on: the details line is still there', false !== strpos( $on, 'class="twd-ap-article-meta"' ) && false !== strpos( $on, '4 November 2026' ) && false !== strpos( $on, 'min read' ) && false !== strpos( $on, 'Anxiety' ) );
t( 'tick on: the only difference from the tick-off output is the lead paragraph', str_replace( $lead_para, '', $golden_with_lead ) === $on, "\n" . $on );
t( 'tick on, and no excerpt anyway: same as tick off', $golden_no_lead === TWD_AP_Article_Style::header_html( make_post( 7, '' ) ) );

// ---------- other values of the meta ----------
foreach ( array( '0', '', 0 ) as $v ) {
	$GLOBALS['meta'][7]['_twd_ap_hide_lead'] = $v;
	t( 'meta value ' . var_export( $v, true ) . ' counts as off', $golden_with_lead === TWD_AP_Article_Style::header_html( make_post( 7, $excerpt_text ) ) );
}
$GLOBALS['meta'][7]['_twd_ap_hide_lead'] = 1;
$GLOBALS['meta'][7]['_twd_ap_hide_hero'] = 1;
t( 'both ticks on: no lead line, details still there', false === strpos( TWD_AP_Article_Style::header_html( make_post( 7, 'Hello there' ) ), 'twd-ap-article-lead' ) && false !== strpos( TWD_AP_Article_Style::header_html( make_post( 7, 'Hello there' ) ), 'twd-ap-article-meta' ) );
unset( $GLOBALS['meta'][7]['_twd_ap_hide_lead'] );
t( 'picture tick on, lead tick off: the lead line stays', false !== strpos( TWD_AP_Article_Style::header_html( make_post( 7, 'Hello there' ) ), 'twd-ap-article-lead' ) );

// ---------- the excerpt is used everywhere else: only the article header reads the tick ----------
$hits = array();
foreach ( glob( $plugin . '{includes,templates}/*.php', GLOB_BRACE ) as $f ) {
	if ( false !== strpos( file_get_contents( $f ), '_twd_ap_hide_lead' ) ) { $hits[] = basename( $f ); }
}
sort( $hits );
t( 'the new meta key appears only in the article header class and the REST class', array( 'class-twd-ap-article-style.php', 'class-twd-ap-rest.php' ) === $hits, implode( ',', $hits ) );
foreach ( array( 'class-twd-ap-cards.php', 'class-twd-ap-seo.php', 'class-twd-ap-swipebook.php', 'class-twd-ap-summary-book.php', 'class-twd-ap-related.php', 'class-twd-ap-index.php' ) as $f ) {
	t( "$f does not look at the tick (the excerpt still shows there)", false === strpos( file_get_contents( $plugin . 'includes/' . $f ), 'hide_lead' ) );
}

// ---------- REST: set and cleared exactly the way hide_hero is, and read back ----------
$rest = file_get_contents( $plugin . 'includes/class-twd-ap-rest.php' );
t( 'REST save: sets the meta to 1 when ticked, deletes it when not', 1 === preg_match( '/isset\( \$request\[\'hide_lead\'\] \) \) \{\s*if \( \$request\[\'hide_lead\'\] \) \{\s*update_post_meta\( \$post_id, \'_twd_ap_hide_lead\', 1 \);\s*\} else \{\s*delete_post_meta\( \$post_id, \'_twd_ap_hide_lead\' \);/', $rest ) );
t( 'REST save: sits next to the picture tick, in the same shared save path (drafts included)', strpos( $rest, "'_twd_ap_hide_hero', 1" ) < strpos( $rest, "'_twd_ap_hide_lead', 1" ) && strpos( $rest, "'_twd_ap_hide_lead', 1" ) < strpos( $rest, "\$request['top_media']" ) );
t( 'REST read: the edit payload carries hide_lead as a boolean', false !== strpos( $rest, "\$data['hide_lead']" ) && false !== strpos( $rest, "(bool) get_post_meta( \$post_id, '_twd_ap_hide_lead', true )" ) );

// ---------- popup: the checkbox, loaded, reset, sent ----------
$tpl = file_get_contents( $plugin . 'templates/buttons-and-modal.php' );
$js  = file_get_contents( $plugin . 'assets/publisher.js' );
t( 'popup: the checkbox is in the template, after the picture tick', false !== strpos( $tpl, 'id="twd-ap-hide-lead"' ) && strpos( $tpl, 'id="twd-ap-hide-hero"' ) < strpos( $tpl, 'id="twd-ap-hide-lead"' ) );
t( 'popup: the words are plain and say the excerpt still shows elsewhere', false !== strpos( $tpl, 'Do not show the introduction line under the title (the excerpt still shows on the resources grid and in search results)' ) );
t( 'popup: JS finds the box, marks the form changed, clears it for a new article, loads it, sends it',
	false !== strpos( $js, "els.hideLead = document.getElementById('twd-ap-hide-lead');" )
	&& false !== strpos( $js, "els.hideLead.addEventListener('change', markDirty);" )
	&& false !== strpos( $js, 'els.hideLead.checked = false;' )
	&& false !== strpos( $js, 'els.hideLead.checked = !!data.hide_lead;' )
	&& false !== strpos( $js, 'hide_lead: !!(els.hideLead && els.hideLead.checked),' ) );

// ---------- house rules: no em or en dash anywhere in what this change touches ----------
foreach ( array( $plugin . 'includes/class-twd-ap-article-style.php', $plugin . 'includes/class-twd-ap-rest.php', $plugin . 'templates/buttons-and-modal.php', $plugin . 'assets/publisher.js', __FILE__ ) as $f ) {
	t( basename( $f ) . ': no em or en dash', 1 !== preg_match( '/\x{2014}|\x{2013}/u', file_get_contents( $f ) ) );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
