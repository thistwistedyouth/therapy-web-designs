<?php
// Tests for "Tidy line endings" (1.46.0): a non-breaking space after every one or two letter word that is
// followed by a space, in the text of an article shown on its own page, so a line never ends on "so" or "we".
// Real TWD_AP_Article_Style, the few WordPress functions it needs faked.
//   php tests/twd-article-publisher/line-endings-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

$GLOBALS['admin'] = false; $GLOBALS['single'] = true; $GLOBALS['loop'] = true; $GLOBALS['tidy'] = true;
function add_action() {}
function add_filter( $tag, $cb, $prio = 10 ) { $GLOBALS['registered'][] = array( $tag, is_array( $cb ) ? $cb[1] : $cb, $prio ); }
function is_admin() { return $GLOBALS['admin']; }
function is_singular() { return $GLOBALS['single']; }
function in_the_loop() { return $GLOBALS['loop']; }
function is_main_query() { return true; }
class TWD_AP_Grid_Settings { static function get() { return array( 'look_tidy_lines' => $GLOBALS['tidy'] ); } }
require $plugin . 'includes/class-twd-ap-article-style.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function tidy( $s ) { return TWD_AP_Article_Style::tidy_lines( $s ); }
$N = '&nbsp;';

// ---------- what it does ----------
t( 'a two letter word before another word: kept with it', 'relationships we' . $N . 'ever have' === tidy( 'relationships we ever have' ) );
t( 'the heading case from a real article: "so" stays with "hard?"', 'Why are family relationships so' . $N . 'hard?' === tidy( 'Why are family relationships so hard?' ) );
t( 'a one letter word: kept with the next', 'There is' . $N . 'a' . $N . 'quiet way' === tidy( 'There is a quiet way' ) );
t( 'a short word at the start of the text', 'So' . $N . 'we' . $N . 'begin' === tidy( 'So we begin' ) );
t( 'every short word in a sentence', 'It' . $N . 'is' . $N . 'an' . $N . 'ox' . $N . 'of' . $N . 'us' . $N . 'all' === tidy( 'It is an ox of us all' ) );
t( 'three letter words are left alone', 'the first one was not' === tidy( 'the first one was not' ) );
t( 'a short word at the end of a sentence (followed by a full stop) is left alone', 'Come with us. Then go.' === tidy( 'Come with us. Then go.' ) );
t( 'a capital abbreviation counts like any short word', 'in' . $N . 'the UK' . $N . 'and the EU' . $N . 'too' === tidy( 'in the UK and the EU too' ) );
t( 'idempotent: running it twice changes nothing more', tidy( tidy( 'I think we are so close' ) ) === tidy( 'I think we are so close' ) );
t( 'a short word at the end of a piece of text before a tag still keeps with what follows', 'we' . $N . 'go' . $N . '<em>now</em>' === tidy( 'we go <em>now</em>' ) );

// ---------- what it must leave alone ----------
t( 'the end of a longer word is not a short word ("Plato", "ago", "banana")', 'Plato ago banana' === tidy( 'Plato ago banana' ) );
t( 'after an apostrophe: don\'t, I\'m, it\'s', 'I\'m so' . $N . 'tired and it\'s late' === tidy( 'I\'m so tired and it\'s late' ) );
t( 'after a curly apostrophe', "it\u{2019}s a" . $N . 'day' === tidy( "it\u{2019}s a day" ) );
t( 'after a hyphen: re-do it', 're-do it' . $N . 'now' === tidy( 're-do it now' ) );
t( 'digits are not words, the word after them is', '1 in' . $N . '3 people' === tidy( '1 in 3 people' ) );
t( 'a letter after a digit is not a short word on its own', '4a b' === tidy( '4a b' ) );
t( 'a word followed by a comma is left alone', 'yes, no, or' . $N . 'so, we' . $N . 'go' . $N . 'on' === tidy( 'yes, no, or so, we go on' ) );
t( 'text with no short words is returned as it was', 'Nothing here needs tidying whatever.' === tidy( 'Nothing here needs tidying whatever.' ) );
t( 'empty text', '' === tidy( '' ) );

// ---------- tags, attributes and code ----------
$html = '<p class="a b c">so we <a href="https://x.test/a b/c" title="so we go">go on</a> to it</p>';
t( 'tag attributes are never touched, the text between tags is', '<p class="a b c">so' . $N . 'we' . $N . '<a href="https://x.test/a b/c" title="so we go">go' . $N . 'on</a> to' . $N . 'it</p>' === tidy( $html ), tidy( $html ) );
t( 'an html comment is left alone', '<!-- so we go -->' === tidy( '<!-- so we go -->' ) );
t( 'inside pre, code, kbd, samp, script, style and textarea: untouched; after them: tidied again', '<pre>so we go</pre> we' . $N . 'go' === tidy( '<pre>so we go</pre> we go' ) );
t( 'nested pre and code: untouched until both are closed', '<pre><code>if a b</code> so we</pre> to' . $N . 'it' === tidy( '<pre><code>if a b</code> so we</pre> to it' ), tidy( '<pre><code>if a b</code> so we</pre> to it' ) );
foreach ( array( 'code', 'kbd', 'samp', 'script', 'style', 'textarea' ) as $tag ) {
	t( "inside <$tag>: untouched", "<$tag>so we go</$tag>" === tidy( "<$tag>so we go</$tag>" ) );
}
t( 'a self-closing tag does not start a skipped block', '<code/> we' . $N . 'go' === tidy( '<code/> we go' ) );
t( 'an unclosed pre leaves the rest alone (it is code until it closes)', '<pre>so we go on' === tidy( '<pre>so we go on' ) );
t( 'upper case tags are recognised too', '<PRE>so we</PRE> to' . $N . 'it' === tidy( '<PRE>so we</PRE> to it' ) );
t( 'a prefix is not a match: <preview> is an ordinary tag, not pre', '<preview>so' . $N . 'we' . $N . 'go' . $N . 'on</preview>' === tidy( '<preview>so we go on</preview>' ), tidy( '<preview>so we go on</preview>' ) );
t( 'entities are not broken: &amp; &lt; &quot;', '&amp; we' . $N . 'go' . $N . '&lt;b&gt; to' . $N . 'it' . $N . '&quot;so&quot; we' . $N . 'do' === tidy( '&amp; we go &lt;b&gt; to it &quot;so&quot; we do' ), tidy( '&amp; we go &lt;b&gt; to it &quot;so&quot; we do' ) );
t( 'text that is not valid UTF-8 is returned exactly as it was', "so we \xFF go on" === tidy( "so we \xFF go on" ) );
t( 'accented short words count as words', 'à' . $N . 'la' . $N . 'carte, ça' . $N . 'va' === tidy( 'à la carte, ça va' ), tidy( 'à la carte, ça va' ) );

// ---------- when it runs ----------
$st = ( new ReflectionClass( 'TWD_AP_Article_Style' ) )->newInstanceWithoutConstructor();
t( 'article page, setting on: the article text is tidied', 'so' . $N . 'we' . $N . 'go' === $st->tidy_content( 'so we go' ) );
$GLOBALS['tidy'] = false;
t( 'the setting off: nothing changes', 'so we go' === $st->tidy_content( 'so we go' ) );
$GLOBALS['tidy'] = true; $GLOBALS['single'] = false;
t( 'not a single article (an archive, a page): nothing changes', 'so we go' === $st->tidy_content( 'so we go' ) );
$GLOBALS['single'] = true; $GLOBALS['loop'] = false;
t( 'outside the main loop (a widget, an excerpt): nothing changes', 'so we go' === $st->tidy_content( 'so we go' ) );
$GLOBALS['loop'] = true; $GLOBALS['admin'] = true;
t( 'in the admin: nothing changes', 'so we go' === $st->tidy_content( 'so we go' ) );
$GLOBALS['admin'] = false;

// registration order: tidy runs at priority 10, before the swipe book buttons (11 and 15) and the wrapper (20)
$ref = new ReflectionMethod( 'TWD_AP_Article_Style', '__construct' ); $ref->setAccessible( true );
$GLOBALS['registered'] = array();
$ref->invoke( $st );
$reg = array_map( 'json_encode', $GLOBALS['registered'] );
t( 'registered: tidy_content on the_content at priority 10, wrap_content at 20', in_array( json_encode( array( 'the_content', 'tidy_content', 10 ) ), $reg, true ) && in_array( json_encode( array( 'the_content', 'wrap_content', 20 ) ), $reg, true ), implode( ' ', $reg ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
