<?php
// Tests for Remix (1.54.0): TWD_AP_Remix (the prompt, reading a pasted reply, the report, the picture and
// link checks) and the /remix-check REST handler. Real TWD_AP_Remix and TWD_AP_Sanitizer; only a few
// WordPress functions are faked. Every article and reply here is made up.
//   php tests/twd-article-publisher/remix-test.php
// With TWD_REMIX_DUMP=<file> it also writes the prompt data, a made-up reply and the real check() result
// for remix-browser-test.js.
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

class WP_Error { public $c; public $m; public $d; function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; $this->d = $d; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
function _n( $single, $plural, $n ) { return 1 === (int) $n ? $single : $plural; }
function add_filter() {}
function add_action() {}
function apply_filters( $t, $v ) { return $v; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $s ) { return (string) $s; }
function rest_ensure_response( $x ) { return $x; }
// A stand-in for wp_kses(): keep only the tags in the allow list (core also filters attributes).
function wp_kses( $html, $allowed ) { return strip_tags( $html, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
$GLOBALS['bloginfo'] = array( 'name' => 'Example Antiques', 'description' => 'Antique jewellery and its history' );
function get_bloginfo( $k ) { return $GLOBALS['bloginfo'][ $k ] ?? ''; }

require $plugin . 'includes/class-twd-ap-sanitizer.php';
require $plugin . 'includes/class-twd-ap-remix.php';
class TWD_AP_Seo { public static $context = ''; static function site_context() { return '' !== self::$context ? self::$context : get_bloginfo( 'description' ); } static function keys() { return array(); } }
class TWD_AP_Frontend { static function current_user_allowed() { return true; } }
class TWD_AP_AI_Generate { const MAX_INPUT_CHARS = 25000; }
class TWD_AP_Settings {}
class TWD_AP_Grid_Settings {}
class TWD_AP_Swipebook {}
class TWD_AP_Book_Import {}
require $plugin . 'includes/class-twd-ap-rest.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
$EM = "\u{2014}"; $EN = "\u{2013}";

// ---- The prompt ----
$tpl = TWD_AP_Remix::template();
t( 'template: no em or en dash anywhere in it', false === strpos( $tpl, $EM ) && false === strpos( $tpl, $EN ) );
t( 'template: asks for a web search check first', false !== stripos( $tpl, 'CHECK THE FACTS WITH A WEB SEARCH' ) && strpos( $tpl, 'JOB 1' ) < strpos( $tpl, 'JOB 2' ) );
t( 'template: the three results are named', false !== strpos( $tpl, '"confirmed"' ) && false !== strpos( $tpl, '"corrected"' ) && false !== strpos( $tpl, '"not confirmed"' ) );
t( 'template: never guess, and an unconfirmed claim is not stated as fact', false !== strpos( $tpl, 'Never guess' ) && false !== strpos( $tpl, 'do not state it as fact' ) );
t( 'template: no new facts, no new links', false !== strpos( $tpl, 'Do not add new facts' ) && false !== strpos( $tpl, 'Do not add new links' ) );
t( 'template: pictures and videos copied unchanged', false !== strpos( $tpl, 'copy each img tag and each figure tag unchanged' ) );
t( 'template: one code block of JSON with html and fact_check', false !== strpos( $tpl, 'ONE code block' ) && false !== strpos( $tpl, '"fact_check"' ) && false !== strpos( $tpl, '"html"' ) );
t( 'template: generic, no therapy wording', false === stripos( $tpl, 'therap' ) && false === stripos( $tpl, 'client' ) );
t( 'template: the four placeholders are there once each', 1 === substr_count( $tpl, '{SITE}' ) && 1 === substr_count( $tpl, '{LENGTH}' ) && 1 === substr_count( $tpl, '{TITLE}' ) && 1 === substr_count( $tpl, '{HTML}' ) );

$p = TWD_AP_Remix::prompt( 'The Title', '<p>Body with {TITLE} and {LENGTH} in it.</p>', 'half', 'SITE-X' );
t( 'prompt: site, length, title and text are filled in', false !== strpos( $p, 'website SITE-X improve' ) && false !== strpos( $p, 'about half of its current length' ) && false !== strpos( $p, 'Title: The Title' ) && false !== strpos( $p, '<p>Body with' ) );
t( 'prompt: one pass, so placeholder-like text inside the article is left alone', false !== strpos( $p, 'Body with {TITLE} and {LENGTH} in it.' ) );
t( 'prompt: an unknown length falls back to two thirds', false !== strpos( TWD_AP_Remix::prompt( 'T', '<p>x</p>', 'tiny' ), 'about two thirds' ) );
t( 'prompt: same length keeps every point', false !== strpos( TWD_AP_Remix::prompt( 'T', '<p>x</p>', 'same' ), 'keep every point' ) );
t( 'lengths: two thirds first (the default), then half, then same', array( 'two-thirds', 'half', 'same' ) === array_keys( TWD_AP_Remix::lengths() ) );
t( 'site line: name and tagline', '"Example Antiques" (Antique jewellery and its history)' === TWD_AP_Remix::site_line(), TWD_AP_Remix::site_line() );
TWD_AP_Seo::$context = 'A shop ' . $EM . ' and a blog';
t( 'site line: the Site context setting wins, dashes taken out', '"Example Antiques" (A shop, and a blog)' === TWD_AP_Remix::site_line(), TWD_AP_Remix::site_line() );
TWD_AP_Seo::$context = '';
$GLOBALS['bloginfo'] = array( 'name' => '', 'description' => '' );
t( 'site line: nothing set gives a plain description', 'a small business website' === TWD_AP_Remix::site_line() );
$GLOBALS['bloginfo'] = array( 'name' => 'Example Antiques', 'description' => 'Antique jewellery and its history' );

// ---- Reading the reply ----
t( 'decode: plain JSON', array( 'a' => 1 ) === TWD_AP_Remix::decode( '{"a":1}' ) );
t( 'decode: in a json code block with words around it', array( 'a' => 1 ) === TWD_AP_Remix::decode( "Here you are:\n```json\n{\"a\":1}\n```\nHope that helps." ) );
t( 'decode: in a plain code block', array( 'a' => 1 ) === TWD_AP_Remix::decode( "```\n{\"a\":1}\n```" ) );
t( 'decode: words before and after, no code block', array( 'a' => 1 ) === TWD_AP_Remix::decode( 'Sure. {"a":1} Done.' ) );
t( 'decode: a byte order mark is ignored', array( 'a' => 1 ) === TWD_AP_Remix::decode( "\xEF\xBB\xBF{\"a\":1}" ) );
t( 'decode: a cut-off reply is not read', null === TWD_AP_Remix::decode( '{"html": "<p>half an arti' ) );
t( 'decode: plain words are not read', null === TWD_AP_Remix::decode( 'I could not do that.' ) );

$e = TWD_AP_Remix::check( '', '<p>x</p>' );
t( 'check: empty paste refused with a plain message', is_wp_error( $e ) && 'twd_ap_remix_empty' === $e->c && 400 === $e->d['status'] );
$e = TWD_AP_Remix::check( str_repeat( 'a', TWD_AP_Remix::MAX_REPLY_CHARS + 1 ), '' );
t( 'check: a huge paste refused', is_wp_error( $e ) && 'twd_ap_remix_too_long' === $e->c );
$e = TWD_AP_Remix::check( '{"html": "<p>cut off', '' );
t( 'check: not JSON refused, and says what to ask for', is_wp_error( $e ) && 'twd_ap_remix_not_json' === $e->c && false !== strpos( $e->m, 'JSON only, in one code block' ) );
$e = TWD_AP_Remix::check( '{"title":"x","html":"  "}', '' );
t( 'check: no article text refused', is_wp_error( $e ) && 'twd_ap_remix_no_html' === $e->c );
$e = TWD_AP_Remix::check( '{"html":"<p></p><br><hr>"}', '' );
t( 'check: text with no words in it is refused', is_wp_error( $e ) && 'twd_ap_remix_no_html' === $e->c );
$e = TWD_AP_Remix::check( '["html"]', '' );
t( 'check: a JSON list is not an article', is_wp_error( $e ) );

$original = '<h2>History</h2><p>The riviere style appeared in the 1700s. It was worn by everyone at court.</p>'
	. '<p><img src="https://pics.example.org/necklace.jpg" alt="A necklace" width="600" height="400"></p>'
	. '<figure class="twd-ap-youtube" data-youtube-id="abc123XYZ00"><figcaption>A short film</figcaption></figure>'
	. '<p>See <a href="https://museum.example.org/collection">the museum</a>.</p>'
	. '<p><img src="https://pics.example.org/clasp.jpg" alt="The clasp"></p>';

$reply_data = array(
	'title'            => 'The Riviere Necklace ' . $EM . ' a short history',
	'meta_description' => 'A made-up summary' . $EN . 'for testing.',
	'html'             => '<h1>History</h1><p>The riviere style became popular in the 18th century ' . $EM . ' sources agree.</p>'
		. '<script>alert(1)</script>'
		. '<p><img src="https://pics.example.org/necklace.jpg" alt="A necklace" width="600" height="400"></p>'
		. '<p><img src="https://invented.example.net/fake.jpg" alt="Invented"></p>'
		. '<figure class="twd-ap-youtube" data-youtube-id="abc123XYZ00"><figcaption>A short film</figcaption></figure>'
		. '<figure class="twd-ap-youtube" data-youtube-id="NEWvideo999"><figcaption>Not in the original</figcaption></figure>'
		. '<p>See <a href="https://museum.example.org/collection">the museum</a> and <a href="https://new.example.com/page">a new page</a>.</p>',
	'fact_check'       => array(
		array( 'claim' => 'Worn by everyone at court', 'result' => 'Not Confirmed', 'note' => 'No source says everyone.', 'sources' => array() ),
		array( 'claim' => 'Appeared in the 1700s', 'result' => 'confirmed', 'note' => 'Museum catalogues agree.', 'sources' => array( 'https://museum.example.org/riviere', array( 'url' => 'http://ref.example.org/a', 'title' => 'A reference ' . $EM . ' work' ), 'javascript:alert(1)', 'ftp://files.example.org/x', 'https://bad.example.org/a b', 'not a url' ) ),
		array( 'claim' => 'Made only in London', 'result' => 'incorrect', 'note' => 'Also made in Paris.', 'sources' => 'https://history.example.org/paris' ),
		array( 'claim' => '<img src=x onerror=alert(1)>Graded by colour', 'result' => 'maybe', 'note' => '' ),
		array( 'claim' => '', 'result' => 'confirmed' ),
		'not an item',
		array( 'claim' => 'Second confirmed', 'result' => 'Verified', 'sources' => array( 'https://a.example.org/1', 'https://a.example.org/2', 'https://a.example.org/3', 'https://a.example.org/4', 'https://a.example.org/5', 'https://a.example.org/6', 'https://a.example.org/7' ) ),
	),
	'changes'          => 'Cut the repeated paragraph ' . $EM . ' and fixed the place.',
);
$reply = "Here is the remix.\n\n```json\n" . json_encode( $reply_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n```\n";
$r = TWD_AP_Remix::check( $reply, $original );
t( 'check: a good reply is read', is_array( $r ), is_wp_error( $r ) ? $r->m : '' );
t( 'check: only the fields the popup uses come back', array( 'title', 'meta_description', 'html', 'fact_check', 'counts', 'changes', 'words_before', 'words_after', 'missing_media', 'new_links', 'notes' ) === array_keys( $r ) );
$h = $r['html'];
t( 'html: cleaned by the sanitiser (no script, no h1)', false === stripos( $h, '<script' ) && false === stripos( $h, '<h1' ) && false !== strpos( $h, '<h2>History</h2>' ) );
t( 'html: no em or en dash left', false === strpos( $h, $EM ) && false === strpos( $h, $EN ) );
t( 'html: the original picture is kept', false !== strpos( $h, 'https://pics.example.org/necklace.jpg' ) );
t( 'html: an invented picture is taken out', false === strpos( $h, 'invented.example.net' ) );
t( 'html: the paragraph it sat in goes too', false === strpos( $h, '<p></p>' ) );
t( 'html: the original video is kept', false !== strpos( $h, 'abc123XYZ00' ) );
t( 'html: a video that was not in the article is taken out', false === strpos( $h, 'NEWvideo999' ) && false === strpos( $h, 'Not in the original' ) );
t( 'notes: the two left-out items are counted', array( '2 pictures or videos that were not in your article were left out.' ) === $r['notes'], json_encode( $r['notes'] ) );
t( 'missing: the picture the remix lost is named', 1 === count( $r['missing_media'] ) && 'image' === $r['missing_media'][0]['type'] && 'https://pics.example.org/clasp.jpg' === $r['missing_media'][0]['src'], json_encode( $r['missing_media'] ) );
t( 'links: only the new link is listed', array( 'https://new.example.com/page' ) === $r['new_links'], json_encode( $r['new_links'] ) );
t( 'title: dashes taken out', 'The Riviere Necklace, a short history' === $r['title'], $r['title'] );
t( 'description: dashes taken out', false === strpos( $r['meta_description'], $EN ), $r['meta_description'] );
t( 'changes: kept, dashes taken out', 'Cut the repeated paragraph, and fixed the place.' === $r['changes'], $r['changes'] );
t( 'words: counted before and after', $r['words_before'] > 15 && $r['words_after'] > 10 && $r['words_before'] !== $r['words_after'], $r['words_before'] . ' / ' . $r['words_after'] );

$fc = $r['fact_check'];
t( 'claims: the empty claim and the non-item are dropped', 5 === count( $fc ), count( $fc ) );
t( 'claims: not confirmed first, then corrected, then confirmed', array( 'not_confirmed', 'not_confirmed', 'corrected', 'confirmed', 'confirmed' ) === array_column( $fc, 'result' ), json_encode( array_column( $fc, 'result' ) ) );
t( 'claims: order inside a group is kept', 'Worn by everyone at court' === $fc[0]['claim'] && 'Appeared in the 1700s' === $fc[3]['claim'] && 'Second confirmed' === $fc[4]['claim'] );
t( 'claims: an unknown result counts as not confirmed', 'not_confirmed' === $fc[1]['result'] );
t( 'claims: tags in a claim are stripped', false === strpos( $fc[1]['claim'], '<' ) && false !== strpos( $fc[1]['claim'], 'Graded by colour' ), $fc[1]['claim'] );
t( 'counts: match the list', array( 'not_confirmed' => 2, 'corrected' => 1, 'confirmed' => 2 ) === $r['counts'] );
$src = $fc[3]['sources'];
t( 'sources: http and https kept, javascript, ftp, spaces and words dropped', array( 'https://museum.example.org/riviere', 'http://ref.example.org/a' ) === array_column( $src, 'url' ), json_encode( $src ) );
t( 'sources: a title becomes the label (dashes out), otherwise the host', 'museum.example.org' === $src[0]['label'] && 'A reference, work' === $src[1]['label'], json_encode( $src ) );
t( 'sources: a single address as a string is read', 'https://history.example.org/paris' === $fc[2]['sources'][0]['url'] );
t( 'sources: at most six per claim', TWD_AP_Remix::MAX_SOURCES === count( $fc[4]['sources'] ) );

$many = array();
for ( $i = 0; $i < 80; $i++ ) { $many[] = array( 'claim' => 'Claim ' . $i, 'result' => 'confirmed' ); }
t( 'claims: at most sixty', TWD_AP_Remix::MAX_CLAIMS === count( TWD_AP_Remix::clean_claims( $many ) ) );
t( 'claims: not a list gives an empty list', array() === TWD_AP_Remix::clean_claims( 'confirmed' ) && array() === TWD_AP_Remix::clean_claims( null ) );
foreach ( array( 'confirmed' => 'confirmed', 'CONFIRMED' => 'confirmed', 'verified' => 'confirmed', 'corrected' => 'corrected', 'Incorrect' => 'corrected', 'not confirmed' => 'not_confirmed', 'not_confirmed' => 'not_confirmed', 'unconfirmed' => 'not_confirmed', 'could not confirm' => 'not_confirmed', '' => 'not_confirmed' ) as $in => $out ) {
	t( 'result: "' . $in . '" is ' . $out, $out === TWD_AP_Remix::result( $in ) );
}
t( 'result: not a string is not confirmed', 'not_confirmed' === TWD_AP_Remix::result( array( 'confirmed' ) ) );

$r2 = TWD_AP_Remix::check( '{"html":"<p>Short text only.</p>"}', $original );
t( 'check: a reply with no fact check still reads, with an empty list', is_array( $r2 ) && array() === $r2['fact_check'] && 0 === $r2['counts']['confirmed'] );
$vids = array_values( array_filter( $r2['missing_media'], function ( $m ) { return 'video' === $m['type']; } ) );
t( 'check: every picture and video the remix lost is named', 3 === count( $r2['missing_media'] ) && 1 === count( $vids ) && false !== strpos( $vids[0]['src'], 'abc123XYZ00' ), json_encode( $r2['missing_media'] ) );
t( 'check: no title or description given stays empty (the popup keeps the old ones)', '' === $r2['title'] && '' === $r2['meta_description'] );
$r3 = TWD_AP_Remix::check( '{"html":"<p><img alt=\"no address\"></p><p>Text.</p>"}', '' );
t( 'check: a picture with no address is taken out', is_array( $r3 ) && false === strpos( $r3['html'], '<img' ) && 1 === count( $r3['notes'] ) );
$r4 = TWD_AP_Remix::check( '{"html":"<p><img src=\"https://pics.example.org/a.jpg?x=1&amp;y=2\"></p><p>Text.</p>"}', '<p><img src="https://pics.example.org/a.jpg?x=1&amp;y=2"></p>' );
t( 'check: a picture address with an encoded ampersand still matches', is_array( $r4 ) && false !== strpos( $r4['html'], 'a.jpg?x=1' ) && array() === $r4['missing_media'] && array() === $r4['notes'] );

t( 'word count: letters and numbers, any language, entities read', 4 === TWD_AP_Remix::word_count( '<p>Caf&eacute; in 1790 &amp; Z&uuml;rich</p>' ) );
t( 'word count: an apostrophe keeps one word', 2 === TWD_AP_Remix::word_count( "<p>It's here</p>" ) );

// ---- The REST handler ----
class Fake_Request { public $p; function __construct( $p ) { $this->p = $p; } function get_json_params() { return $this->p; } }
$rest = ( new ReflectionClass( 'TWD_AP_REST' ) )->newInstanceWithoutConstructor();
$out  = $rest->remix_check( new Fake_Request( array( 'reply' => $reply, 'original_html' => $original ) ) );
t( 'route: a good reply returns the same as check()', is_array( $out ) && $out === $r );
$out = $rest->remix_check( new Fake_Request( array( 'reply' => array( 'not' => 'a string' ) ) ) );
t( 'route: a reply that is not text is refused', is_wp_error( $out ) && 'twd_ap_remix_empty' === $out->c );
$out = $rest->remix_check( new Fake_Request( null ) );
t( 'route: no body is refused, not an error page', is_wp_error( $out ) );

if ( getenv( 'TWD_REMIX_DUMP' ) ) {
	file_put_contents(
		getenv( 'TWD_REMIX_DUMP' ),
		json_encode(
			array(
				'prompt'   => str_replace( '{SITE}', TWD_AP_Remix::site_line(), TWD_AP_Remix::template() ),
				'lengths'  => TWD_AP_Remix::lengths(),
				'original' => $original,
				'reply'    => $reply,
				'result'   => $r,
				'expected_prompt_half' => TWD_AP_Remix::prompt( 'Existing article', $original, 'half', TWD_AP_Remix::site_line() ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		)
	);
}

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
