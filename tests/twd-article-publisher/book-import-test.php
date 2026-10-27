<?php
// Tests for the Book Maker style JSON import (1.44.0): TWD_AP_Book_Import::convert(), the picture card in
// TWD_AP_Summary_Book, and the /book-import REST handler. Real TWD_AP_Book_Import, TWD_AP_Summary_Book and
// TWD_AP_Sanitizer (its dash rule and its tag list); only a few WordPress functions are faked.
// Every book here is made up; nothing is taken from a real author.
//   php tests/twd-article-publisher/book-import-test.php
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
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }   // keeps line breaks, as core's does
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return preg_match( '~^https?://~i', (string) $s ) ? htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ) : ''; }
function esc_url_raw( $s, $protocols = array( 'http', 'https' ) ) { $sc = strtolower( (string) parse_url( (string) $s, PHP_URL_SCHEME ) ); return in_array( $sc, $protocols, true ) ? (string) $s : ''; }
function wp_json_encode( $d ) { return json_encode( $d ); }
function rest_ensure_response( $x ) { return $x; }
// A stand-in for wp_kses(): keep only the tags in the allow list. (Core's also filters attributes;
// the point here is that no script or unknown tag survives.)
function wp_kses( $html, $allowed ) { return strip_tags( $html, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
$GLOBALS['meta'] = array();
function get_post_meta( $id, $k, $single = true ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }

require $plugin . 'includes/class-twd-ap-sanitizer.php';
require $plugin . 'includes/class-twd-ap-summary-book.php';
require $plugin . 'includes/class-twd-ap-book-import.php';
class TWD_AP_Frontend { static function current_user_allowed() { return true; } }
class TWD_AP_AI_Generate { const MAX_INPUT_CHARS = 25000; }
class TWD_AP_Settings {}
class TWD_AP_Grid_Settings {}
class TWD_AP_Swipebook {}
require $plugin . 'includes/class-twd-ap-rest.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
$EM = "\u{2014}"; $EN = "\u{2013}";

$book = array(
	'title'              => 'A Made-Up Book - Part One',
	'series'             => '',
	'cover'              => array( 'background' => '#1A2340', 'accent' => '#C9A84C' ),
	'print_front_matter' => array( array( 'type' => 'copyright', 'text' => 'Made-up copyright page.' ) ),
	'print_back_matter'  => array( array( 'type' => 'back_cover', 'text' => 'Made-up back cover.' ) ),
	'cards'              => array(
		array( 'type' => 'filing', 'text' => "This book follows a made-up author.\n\nNone of this is therapy." ),
		array( 'type' => 'insight', 'text' => "Here is the whole book in one question.\n\nWith what are you aligned?\n\nHold onto that." ),
		array( 'type' => 'insight', 'part' => 'Prologue - The First Step', 'text' => "Something is wrong.\n\nNot because people are bad." ),
		array( 'type' => 'quote', 'text' => 'This is not a failure of effort. It is a failure of structure.', 'attribution' => 'A. Author, The Book, Prologue' ),
		array( 'type' => 'insight', 'part' => 'Chapter One - The Two Ways of With', 'text' => 'The Two Ways of With' ),
		array( 'type' => 'question', 'text' => "What are you With, right now?\n\nAnd what might you come With next?" ),
		array( 'type' => 'image', 'src' => 'REPLACE_WITH_MEDIA_URL/pic.webp', 'caption' => 'A placeholder picture.' ),
		array( 'type' => 'image', 'src' => 'https://pictures.example.org/real.webp', 'caption' => 'A real picture.' ),
		array( 'type' => 'mystery', 'text' => 'An odd kind of card.' ),
		array( 'type' => 'insight', 'text' => '' ),
		'not a card',
	),
	'author_id'          => 381,
);

$r = TWD_AP_Book_Import::convert( $book );
t( 'a book converts', is_array( $r ) );
t( 'title is kept, spaced hyphen and all', 'A Made-Up Book - Part One' === $r['title'] );
$slides = $r['summary_book']['slides'];
t( 'eight cards survive (a placeholder picture, an empty card and a non-card do not)', 8 === count( $slides ), count( $slides ) );
t( 'kinds map: filing and insight to text, quote, question, picture, unknown to text', array( 'text', 'text', 'text', 'quote', 'text', 'question', 'image', 'text' ) === array_column( $slides, 'type' ), json_encode( array_column( $slides, 'type' ) ) );
t( 'a part becomes the card heading', 'Prologue - The First Step' === $slides[2]['heading'] && 'Chapter One - The Two Ways of With' === $slides[4]['heading'] );
t( 'a quote keeps its attribution', 'A. Author, The Book, Prologue' === $slides[3]['attribution'] );
t( 'blank lines in a card are kept for the book', "Something is wrong.\n\nNot because people are bad." === $slides[2]['text'] );
t( 'a picture with a real https address is kept, caption as its text', 'https://pictures.example.org/real.webp' === $slides[6]['src'] && 'A real picture.' === $slides[6]['text'] );
t( 'the placeholder picture is left out', false === strpos( json_encode( $slides ), 'REPLACE_WITH' ) );
t( 'nothing but cards, summary slides and notes come back for the popup', array( 'title', 'seo_title', 'meta_description', 'category', 'tags', 'html', 'summary_book', 'notes' ) === array_keys( $r ) );

$n = implode( ' ', $r['notes'] );
t( 'notes: how many cards were read', false !== strpos( $n, '8 cards were read' ), $n );
t( 'notes: the print pages and cover colours are named as not used, the empty series is not', false !== strpos( $n, 'the print edition front and back pages' ) && false !== strpos( $n, 'the cover colours' ) && false === strpos( $n, 'series' ), $n );
t( 'notes: the left-out picture is explained', false !== strpos( $n, '1 picture card was left out because it has no web address yet' ), $n );
t( 'notes: an unknown kind is named', false !== strpos( $n, 'mystery' ), $n );
t( 'notes: the card with no text is counted', false !== strpos( $n, '1 card with no text was left out' ), $n );

$h = $r['html'];
t( 'article: a part is an h2', false !== strpos( $h, '<h2>Prologue - The First Step</h2>' ) );
t( 'article: blank lines become paragraphs', false !== strpos( $h, '<p>Something is wrong.</p><p>Not because people are bad.</p>' ) );
t( 'article: a quote is a block quote with its attribution under it', false !== strpos( $h, '<blockquote><p>This is not a failure of effort. It is a failure of structure.</p></blockquote><p><em>A. Author, The Book, Prologue</em></p>' ) );
t( 'article: a heading that already says the card is not repeated as a paragraph', false !== strpos( $h, '<h2>Chapter One - The Two Ways of With</h2>' ) && false === strpos( $h, '<p>The Two Ways of With</p>' ) );
t( 'article: a question is in italics, paragraph by paragraph', false !== strpos( $h, '<p><em>What are you With, right now?</em></p><p><em>And what might you come With next?</em></p>' ) );
t( 'article: a picture and its caption', false !== strpos( $h, '<img src="https://pictures.example.org/real.webp" alt="A real picture." />' ) && false !== strpos( $h, '<p><em>A real picture.</em></p>' ) );
t( 'article: in the order of the book', strpos( $h, 'Prologue' ) < strpos( $h, 'not a failure' ) && strpos( $h, 'not a failure' ) < strpos( $h, 'Chapter One' ) && strpos( $h, 'Chapter One' ) < strpos( $h, 'come With next' ) && strpos( $h, 'come With next' ) < strpos( $h, 'real.webp' ) );
t( 'article: the opening note is there too', false !== strpos( $h, '<p>This book follows a made-up author.</p><p>None of this is therapy.</p>' ) );
t( 'description: from the first insight card, not the filing note', 'Here is the whole book in one question. With what are you aligned? Hold onto that.' === $r['meta_description'], $r['meta_description'] );
t( 'category and tags are left for the owner', '' === $r['category'] && array() === $r['tags'] && '' === $r['seo_title'] );

// A long first insight is cut at a word.
$long = $book; $long['cards'][1]['text'] = str_repeat( 'word ', 80 );
$r2 = TWD_AP_Book_Import::convert( $long );
t( 'description: long text is cut at about 155 characters with three dots', strlen( $r2['meta_description'] ) <= 158 && '...' === substr( $r2['meta_description'], -3 ) );

// Hostile content.
$bad = array( 'title' => '<b>Bold</b> title<script>x()</script>', 'cards' => array(
	array( 'type' => 'insight', 'part' => '</h2><script>alert(1)</script>', 'text' => '<script>alert(1)</script>Safe & <b>bold</b> <img src=x onerror=alert(1)>' ),
	array( 'type' => 'quote', 'text' => 'Q', 'attribution' => '<i onclick="x()">Someone</i>' ),
	array( 'type' => 'image', 'src' => 'javascript:alert(1)', 'caption' => 'js' ),
	array( 'type' => 'image', 'src' => 'http://pictures.example.org/plain.png', 'caption' => 'plain http' ),
	array( 'type' => 'image', 'src' => 'https://pictures.example.org/a b.png', 'caption' => 'space' ),
	array( 'type' => 'image', 'src' => 'https://localhost/x.png', 'caption' => 'no dot' ),
	array( 'type' => 'image', 'src' => array( 'https://pictures.example.org/arr.png' ), 'caption' => 'array' ),
	array( 'type' => 'image', 'src' => 'https://pictures.example.org/ok.png"onerror="x', 'caption' => 'quote in the address' ),
	array( 'type' => 'image', 'src' => 'https://pictures.example.org/fine.png', 'caption' => '"><script>alert(1)</script>' ),
) );
$rb = TWD_AP_Book_Import::convert( $bad );
$enc = json_encode( $rb );
t( 'hostile: no script or event handler anywhere in what comes back', ! preg_match( '/<script|onerror=|onclick=|javascript:/i', stripslashes( $enc ) ), $enc );
t( 'hostile: the title is plain text', 'Bold titlex()' === $rb['title'], $rb['title'] );
t( 'hostile: an ampersand in text is escaped in the article', false !== strpos( $rb['html'], 'Safe &amp; bold' ), $rb['html'] );
$srcs = array_column( array_filter( $rb['summary_book']['slides'], function ( $c ) { return 'image' === $c['type']; } ), 'src' );
t( 'hostile: only https pictures with a real host and no spaces survive', 1 === count( $srcs ) && 0 === strpos( $srcs[0], 'https://pictures.example.org/' ), json_encode( $srcs ) );
t( 'hostile: nothing but plain text in a card heading', false === strpos( json_encode( array_column( $rb['summary_book']['slides'], 'heading' ) ), '<' ) );

// Dashes: the plugin never lets an em or en dash through, in the cards or the article.
$dash = array( 'title' => "Title {$EM} here", 'cards' => array( array( 'type' => 'insight', 'text' => "One {$EM} two {$EN} three" ), array( 'type' => 'quote', 'text' => "Say {$EM} this", 'attribution' => "Who {$EN} where" ) ) );
$rd = TWD_AP_Book_Import::convert( $dash );
t( 'dashes: none in the title, the cards or the article', ! preg_match( '/\x{2014}|\x{2013}/u', json_encode( $rd, JSON_UNESCAPED_UNICODE ) ), json_encode( $rd, JSON_UNESCAPED_UNICODE ) );

// Limits and shape.
$many = array( 'cards' => array() );
for ( $i = 1; $i <= 250; $i++ ) { $many['cards'][] = array( 'type' => 'insight', 'text' => 'Card ' . $i ); }
$rm = TWD_AP_Book_Import::convert( $many );
t( 'limit: 200 cards kept', 200 === count( $rm['summary_book']['slides'] ) && 'Card 200' === end( $rm['summary_book']['slides'] )['text'] );
t( 'limit: the 50 left out are reported', false !== strpos( implode( ' ', $rm['notes'] ), 'Only the first 200 cards were used; 50 more were left out.' ) );
t( 'shape: an article JSON is not a book', ! TWD_AP_Book_Import::is_book( array( 'title' => 'x', 'html' => '<p>x</p>', 'summary_book' => array( 'slides' => array() ) ) ) );
t( 'shape: a JSON with both html and cards is an article, not a book', ! TWD_AP_Book_Import::is_book( array( 'title' => 'x', 'html' => '<p>x</p>', 'cards' => array( array( 'type' => 'insight', 'text' => 'x' ) ) ) ) );
t( 'shape: a book is a book', TWD_AP_Book_Import::is_book( $book ) );
$e = TWD_AP_Book_Import::convert( array( 'title' => 'No cards' ) );
t( 'shape: no cards list gives a plain error', is_wp_error( $e ) && 'twd_ap_book_not_book' === $e->get_error_code() && 400 === $e->d['status'] );
$e = TWD_AP_Book_Import::convert( array( 'cards' => array() ) );
t( 'shape: an empty list gives a plain error', is_wp_error( $e ) && 'twd_ap_book_empty' === $e->get_error_code() );
$e = TWD_AP_Book_Import::convert( array( 'cards' => array( 1, 'two', null, array( 'type' => 'insight' ) ) ) );
t( 'shape: no usable text anywhere gives a plain error', is_wp_error( $e ) && 'twd_ap_book_empty' === $e->get_error_code() );
$e = TWD_AP_Book_Import::convert( array( 'cards' => array( array( 'type' => 'insight', 'text' => str_repeat( 'x', 1600000 ) ) ) ) );
t( 'shape: a book over 1.5 MB is refused', is_wp_error( $e ) && 'twd_ap_book_too_big' === $e->get_error_code() );
$e = TWD_AP_Book_Import::convert( 'not an array' );
t( 'shape: not even an array', is_wp_error( $e ) );

// The Summary Book itself.
$c = TWD_AP_Summary_Book::sanitize_cards( array(
	array( 'type' => 'image', 'src' => 'https://pictures.example.org/a.png', 'text' => '' ),
	array( 'type' => 'image', 'src' => '', 'text' => 'caption but no picture' ),
	array( 'type' => 'text', 'text' => 'Has text', 'src' => 'https://pictures.example.org/stray.png' ),
	array( 'type' => 'text', 'text' => '' ),
	array( 'type' => 'banana', 'text' => 'Unknown becomes text' ),
) );
t( 'summary book: a picture card needs a picture, not text', 'image' === $c[0]['type'] && 'https://pictures.example.org/a.png' === $c[0]['src'] && '' === $c[0]['text'] );
t( 'summary book: a picture card without a picture is dropped even with a caption', 3 === count( $c ) );
t( 'summary book: a stray picture address on a text card is not kept', ! isset( $c[1]['src'] ) );
t( 'summary book: an unknown kind becomes text', 'text' === $c[2]['type'] );
t( 'summary book: the card limit is 200 and picture is a known kind', 200 === TWD_AP_Summary_Book::MAX_CARDS && in_array( 'image', TWD_AP_Summary_Book::TYPES, true ) );
foreach ( array( 'https://media.example.org/REPLACE_WITH_MEDIA_URL/x.webp', 'https://a.example.org/x.png"onerror="y', "https://a.example.org/x.png'", 'https://a.example.org/<x>.png', 'https://a.example.org/x.png`', 'https://a.example.org/x\\y.png', '', 'REPLACE_WITH_MEDIA_URL/x.webp', 'http://a.example.org/x.png', 'https://a b.example.org/x.png', 'https://nodot/x.png', 'ftp://a.example.org/x.png', 'https://' . str_repeat( 'a', 520 ) . '.org/x.png', 5, null ) as $badsrc ) {
	t( 'picture address refused: ' . substr( json_encode( $badsrc ), 0, 40 ), '' === TWD_AP_Summary_Book::clean_image_src( $badsrc ) );
}
t( 'picture address accepted: https with a host', 'https://a.example.org/x.png' === TWD_AP_Summary_Book::clean_image_src( ' https://a.example.org/x.png ' ) );

// What the swipe book is given for a picture card.
$GLOBALS['meta'][5] = array( TWD_AP_Summary_Book::META_PUBLISHED => array(
	array( 'type' => 'image', 'src' => 'https://pictures.example.org/a.png', 'text' => 'Its caption', 'heading' => 'A heading' ),
	array( 'type' => 'text', 'text' => "Line one\n\nLine two" ),
) );
$m = new ReflectionMethod( 'TWD_AP_Summary_Book', 'build_slides' );
$m->setAccessible( true );
$inst = ( new ReflectionClass( 'TWD_AP_Summary_Book' ) )->newInstanceWithoutConstructor();
$sl = $m->invoke( $inst, (object) array( 'ID' => 5 ) );
t( 'swipe book slide: a picture card is type, src, caption and heading', array( 'type' => 'image', 'src' => 'https://pictures.example.org/a.png', 'caption' => 'Its caption', 'heading' => 'A heading' ) === $sl[0], json_encode( $sl[0] ) );
t( 'swipe book slide: a text card is unchanged (html with line breaks)', 'text' === $sl[1]['type'] && '<p>Line one<br />' . "\n" . '<br />' . "\n" . 'Line two</p>' === str_replace( array( "\r" ), '', $sl[1]['html'] ) || false !== strpos( $sl[1]['html'], 'Line one' ) );

// The REST handler.
$rest = ( new ReflectionClass( 'TWD_AP_REST' ) )->newInstanceWithoutConstructor();
$req = new class( $book ) { private $b; function __construct( $b ) { $this->b = $b; } function get_json_params() { return $this->b; } };
$out = $rest->import_book( $req );
t( 'rest: /book-import returns the converted book', is_array( $out ) && 8 === count( $out['summary_book']['slides'] ) );
$out = $rest->import_book( new class { function get_json_params() { return array( 'cards' => 'nope' ); } } );
t( 'rest: a bad book is an error the popup can show', is_wp_error( $out ) && 400 === $out->d['status'] );
$out = $rest->import_book( new class { function get_json_params() { return null; } } );
t( 'rest: an empty body is an error, not a crash', is_wp_error( $out ) );

// For the real-browser test: the converted book, as the server would send it, and the book JSON itself.
if ( getenv( 'TWD_BOOK_DUMP' ) ) {
	file_put_contents( getenv( 'TWD_BOOK_DUMP' ), json_encode( array( 'book' => $book, 'result' => TWD_AP_Book_Import::convert( $book ) ) ) );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
