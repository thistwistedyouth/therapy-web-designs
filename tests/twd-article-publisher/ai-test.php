<?php
// Tests for TWD_AP_AI_Generate (modes, reply sizes, filters) and the /generate REST handler.
// Pure PHP, no WordPress needed: WordPress functions and the Anthropic HTTP call are faked.
//   php tests/twd-article-publisher/ai-test.php
define( 'ABSPATH', '/' );
define( 'DAY_IN_SECONDS', 86400 );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

class WP_Error { public $c; public $m; public $d; function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; $this->d = $d; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
$GLOBALS['f'] = array();
function add_filter( $t, $cb, $p = 10, $n = 1 ) { $GLOBALS['f'][ $t ][] = array( $cb, $n ); }
function apply_filters( $t, $v ) { $a = func_get_args(); array_shift( $a ); foreach ( ( $GLOBALS['f'][ $t ] ?? array() ) as $h ) { $v = call_user_func_array( $h[0], array_slice( $a, 0, $h[1] ) ); $a[0] = $v; } return $v; }
function get_current_user_id() { return 7; }
function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['tr'][ $k ] = $v; }
function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( $s ) ); }
function wp_unslash( $s ) { return $s; }
function number_format_i18n( $n ) { return number_format( $n ); }
function rest_ensure_response( $x ) { return $x; }
function get_categories() { return array( (object) array( 'name' => 'Anxiety' ), (object) array( 'name' => 'Uncategorized' ) ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
$GLOBALS['sent'] = array();
function wp_remote_post( $url, $args ) { $GLOBALS['sent'][] = array( $url, $args ); return $GLOBALS['resp']; }
class TWD_AP_Settings { static $key = ''; static $model = ''; static function get_settings() { return array( 'anthropic_api_key' => self::$key, 'ai_model' => self::$model ); } }
class TWD_AP_Sanitizer { static function strip_dashes( $s ) { return $s; } static function clean( $s ) { return $s; } }
class TWD_AP_Summary_Book { static function sanitize_cards( $c ) { return $c; } }
require $plugin . 'includes/class-twd-ap-ai-generate.php';
require $plugin . 'includes/class-twd-ap-seo.php';
require $plugin . 'includes/class-twd-ap-rest.php';
TWD_AP_AI_Generate::init();

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function ok_resp( $text ) { return array( 'code' => 200, 'body' => json_encode( array( 'stop_reason' => 'end_turn', 'content' => array( array( 'type' => 'text', 'text' => $text ) ) ) ) ); }
function body() { return json_decode( end( $GLOBALS['sent'] )[1]['body'], true ); }
$SECRET = 'sk-ant-SECRET-1234567890';
$gen = json_encode( array( 'title' => 'T', 'html' => '<p>Hi</p>', 'tags' => array( 'a' ), 'category' => 'Anxiety', 'seo_title' => 's', 'meta_description' => 'm', 'summary_book' => array( 'slides' => array( array( 'type' => 'text', 'text' => 'x' ) ) ) ) );

// ---------- generate(): three modes ----------
TWD_AP_Settings::$key = $SECRET;
$cases = array(
	'idea'    => array( 5000, 'from a topic or idea they give you', 'The idea or topic to write about' ),
	'improve' => array( 12000, 'draft article a therapist has written themselves', 'Here is the draft article to improve and tag' ),
	'format'  => array( 12000, 'article a therapist has already written themselves', 'Here is the article text to restructure and tag' ),
	'bogus'   => array( 5000, 'from a topic or idea they give you', 'The idea or topic to write about' ),
);
foreach ( $cases as $mode => $c ) {
	$GLOBALS['resp'] = ok_resp( $gen );
	$r = TWD_AP_AI_Generate::generate( $mode, 'my text' );
	$b = body();
	t( "generate($mode): succeeds", is_array( $r ) && 'T' === $r['title'] );
	t( "generate($mode): reply size {$c[0]}", $b['max_tokens'] === $c[0], 'got ' . $b['max_tokens'] );
	t( "generate($mode): right prompt", false !== strpos( $b['system'][0]['text'], $c[1] ) );
	t( "generate($mode): right message prefix and text", 0 === strpos( $b['messages'][0]['content'], $c[2] ) && substr( $b['messages'][0]['content'], -7 ) === 'my text' );
	t( "generate($mode): shared rules (no dashes, confidentiality, tags) present", false !== strpos( $b['system'][0]['text'], 'Confidentiality is not optional' ) && false !== strpos( $b['system'][0]['text'], 'Never use an em dash' ) && false !== strpos( $b['system'][0]['text'], 'Anxiety, Uncategorized' ) );
	t( "generate($mode): same endpoint, model, timeout", 'https://api.anthropic.com/v1/messages' === end( $GLOBALS['sent'] )[0] && 'claude-sonnet-5-5' === $b['model'] );
	t( "generate($mode): wait " . ( in_array( $mode, array( 'improve', 'format' ), true ) ? '240' : '120' ) . ' seconds', ( in_array( $mode, array( 'improve', 'format' ), true ) ? 240 : 120 ) === end( $GLOBALS['sent'] )[1]['timeout'], 'got ' . end( $GLOBALS['sent'] )[1]['timeout'] );
	t( "generate($mode): result shape unchanged", array_keys( $r ) === array( 'title', 'seo_title', 'meta_description', 'category', 'tags', 'html', 'summary_book' ) );
}
$GLOBALS['resp'] = ok_resp( $gen ); TWD_AP_AI_Generate::generate( 'improve', 'x' ); $sys = body()['system'][0]['text'];
t( 'improve prompt: keeps their voice, adds nothing of its own', false !== strpos( $sys, 'Do not add facts' ) && false !== strpos( $sys, 'their own spelling conventions' ) );
$GLOBALS['resp'] = array( 'code' => 200, 'body' => json_encode( array( 'stop_reason' => 'max_tokens', 'content' => array( array( 'type' => 'text', 'text' => 'x' ) ) ) ) );
t( 'generate(improve): cut-off still reported as before', 'twd_ap_ai_cut_off' === TWD_AP_AI_Generate::generate( 'improve', 'x' )->c );
TWD_AP_Settings::$key = '';
t( 'generate(): no key gives the same error as before', 'twd_ap_ai_not_configured' === TWD_AP_AI_Generate::generate( 'improve', 'x' )->c );
TWD_AP_Settings::$key = $SECRET;

// ---------- remaining() ----------
$GLOBALS['tr'] = array();
t( 'remaining(): 30 at the start', 30 === TWD_AP_AI_Generate::remaining() );
TWD_AP_AI_Generate::increment(); TWD_AP_AI_Generate::increment();
t( 'remaining(): 28 after two', 28 === TWD_AP_AI_Generate::remaining() );
for ( $i = 0; $i < 40; $i++ ) { TWD_AP_AI_Generate::increment(); }
t( 'remaining(): never below zero', 0 === TWD_AP_AI_Generate::remaining() );
$GLOBALS['tr'] = array();

// ---------- REST /generate ----------
$rest = ( new ReflectionClass( 'TWD_AP_REST' ) )->newInstanceWithoutConstructor();
function req( $mode, $input ) { return array( 'mode' => $mode, 'input' => $input ); }
$GLOBALS['resp'] = ok_resp( $gen );
$r = $rest->generate_article( req( 'improve', 'a draft' ) );
t( 'rest: improve accepted and sent through', is_array( $r ) && 'improve' === ( json_decode( end( $GLOBALS['sent'] )[1]['body'], true )['max_tokens'] === 12000 ? 'improve' : '' ) );
t( 'rest: response carries ai_remaining (29)', 29 === $r['ai_remaining'] );
$r = $rest->generate_article( req( 'idea', 'an idea' ) );
t( 'rest: count goes down again (28)', 28 === $r['ai_remaining'] );
$rest->generate_article( req( 'nonsense', 'text' ) );
t( 'rest: unknown mode treated as idea (5000)', 5000 === body()['max_tokens'] );
foreach ( array( 'idea' => 'idea or topic', 'improve' => 'article text', 'format' => 'article text' ) as $mode => $word ) {
	$e = $rest->generate_article( req( $mode, '   ' ) );
	t( "rest: empty $mode input refused with a clear message", is_wp_error( $e ) && 'twd_ap_ai_empty_input' === $e->c && false !== strpos( $e->m, $word ) );
}
$before = count( $GLOBALS['sent'] );
$e = $rest->generate_article( req( 'format', str_repeat( 'x', 25001 ) ) );
t( 'rest: 25,001 characters refused, not cut', is_wp_error( $e ) && 'twd_ap_ai_too_long' === $e->c && false !== strpos( $e->m, '25,001' ) && false !== strpos( $e->m, '25,000' ) );
t( 'rest: too-long text never sent to the AI', count( $GLOBALS['sent'] ) === $before );
$e = $rest->generate_article( req( 'format', str_repeat( 'x', 20001 ) ) );
t( 'rest: 20,001 characters is now accepted (the old limit)', is_array( $e ) );
$e = $rest->generate_article( req( 'format', str_repeat( 'x', 25000 ) ) );
$prefix = "Here is the article text to restructure and tag:\n\n";
t( 'rest: exactly 25,000 characters accepted, sent whole', is_array( $e ) && $prefix . str_repeat( 'x', 25000 ) === body()['messages'][0]['content'] );
$e = $rest->generate_article( req( 'format', str_repeat( "é", 25000 ) ) );
t( 'rest: multibyte text counted in characters, not bytes', is_array( $e ) );
$GLOBALS['tr'] = array(); for ( $i = 0; $i < 30; $i++ ) { TWD_AP_AI_Generate::increment(); }
$before = count( $GLOBALS['sent'] );
$e = $rest->generate_article( req( 'idea', 'hello' ) );
t( 'rest: at 30 a clear limit error, nothing sent', is_wp_error( $e ) && 'twd_ap_ai_rate_limited' === $e->c && count( $GLOBALS['sent'] ) === $before );
$GLOBALS['tr'] = array(); TWD_AP_Settings::$key = '';
$e = $rest->generate_article( req( 'idea', 'hello' ) );
t( 'rest: no key gives the not-configured error', is_wp_error( $e ) && 'twd_ap_ai_not_configured' === $e->c );
TWD_AP_Settings::$key = $SECRET;
$GLOBALS['resp'] = array( 'code' => 500, 'body' => '{}' ); $GLOBALS['tr'] = array();
$e = $rest->generate_article( req( 'improve', 'hello' ) );
t( 'rest: a failed generation does not use up one of the 30', is_wp_error( $e ) && 30 === TWD_AP_AI_Generate::remaining() );

// ---------- the 1.34.0 filters (re-tested here, now committed) ----------
t( 'filter is_configured: true with key', true === apply_filters( 'twd_ai_is_configured', false ) );
TWD_AP_Settings::$key = '   ';
t( 'filter is_configured: false with a blank key', false === apply_filters( 'twd_ai_is_configured', false ) );
TWD_AP_Settings::$key = '';
$before = count( $GLOBALS['sent'] );
t( 'filter complete: null with no key, nothing sent', null === apply_filters( 'twd_ai_complete', null, 's', 'm', 1000 ) && count( $GLOBALS['sent'] ) === $before );
TWD_AP_Settings::$key = $SECRET; $GLOBALS['resp'] = ok_resp( '  Hello world  ' );
t( 'filter complete: string on success', 'Hello world' === apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000 ) );
foreach ( array( array( 100, 256 ), array( 8001, 8000 ), array( 'abc', 5000 ), array( '2500', 2500 ), array( -5, 256 ) ) as $c ) {
	apply_filters( 'twd_ai_complete', null, 's', 'm', $c[0] );
	t( 'filter complete: max_tokens ' . json_encode( $c[0] ) . ' -> ' . $c[1], body()['max_tokens'] === $c[1] );
}
$GLOBALS['resp'] = array( 'code' => 401, 'body' => '{}' );
$e = apply_filters( 'twd_ai_complete', null, 's', 'm', 1000 );
t( 'filter complete: WP_Error passed through, key not in it', is_wp_error( $e ) && 'twd_ap_ai_bad_key' === $e->c && false === strpos( serialize( $e ), $SECRET ) );
$GLOBALS['resp'] = ok_resp( 'ok' );
t( 'filter complete: key never in a return value or request body', false === strpos( serialize( array( apply_filters( 'twd_ai_is_configured', false ), apply_filters( 'twd_ai_complete', null, 's', 'm', 1000 ) ) ), $SECRET ) && false === strpos( end( $GLOBALS['sent'] )[1]['body'], $SECRET ) );
$GLOBALS['tr'] = array(); apply_filters( 'twd_ai_complete', null, 's', 'm', 1000 );
t( 'filter complete: still waits only 120 seconds (the contract Site Kit depends on)', 120 === end( $GLOBALS['sent'] )[1]['timeout'], 'got ' . end( $GLOBALS['sent'] )[1]['timeout'] );
t( 'filter complete: does not use up the daily 30', 30 === TWD_AP_AI_Generate::remaining() );

// ---------- 1.53.0: model choice, effort, cached system block ----------
TWD_AP_Settings::$key = $SECRET; TWD_AP_Settings::$model = ''; $GLOBALS['resp'] = ok_resp( 'ok' );
t( 'models(): the two allowed ids, Sonnet first', array( 'claude-sonnet-5-5', 'claude-opus-5-5' ) === array_keys( TWD_AP_AI_Generate::models() ) );
t( 'model(): the default when nothing is saved', 'claude-sonnet-5-5' === TWD_AP_AI_Generate::model() );
TWD_AP_Settings::$model = 'claude-opus-5-5';
t( 'model(): follows the setting', 'claude-opus-5-5' === TWD_AP_AI_Generate::model() );
TWD_AP_Settings::$model = 'claude-nonsense';
t( 'model(): an unknown saved value falls back to the default', 'claude-sonnet-5-5' === TWD_AP_AI_Generate::model() );
TWD_AP_Settings::$model = '';

// generate() follows the setting
TWD_AP_AI_Generate::generate( 'idea', 'x' ); $b = body();
t( 'generate(): default model and medium effort', 'claude-sonnet-5-5' === $b['model'] && array( 'effort' => 'medium' ) === $b['output_config'] );
t( 'generate(): system is one text block marked for caching', is_array( $b['system'] ) && 1 === count( $b['system'] ) && 'text' === $b['system'][0]['type'] && array( 'type' => 'ephemeral' ) === $b['system'][0]['cache_control'] && false !== strpos( $b['system'][0]['text'], 'Confidentiality is not optional' ) );
t( 'generate(): no thinking or temperature parameter', ! isset( $b['thinking'] ) && ! isset( $b['temperature'] ) );
t( 'generate(): fallbacks and the beta header unchanged', 'default' === $b['fallbacks'] && 'server-side-fallback-2026-07-01' === end( $GLOBALS['sent'] )[1]['headers']['anthropic-beta'] );
TWD_AP_Settings::$model = 'claude-opus-5-5';
TWD_AP_AI_Generate::generate( 'idea', 'x' );
t( 'generate(): Opus when the setting says so', 'claude-opus-5-5' === body()['model'] );

// the filter with four arguments (what Site Kit sends) is unchanged
TWD_AP_Settings::$model = '';
$r = apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000 ); $b = body();
t( 'filter, four arguments: text back, default model, medium effort', 'ok' === $r && 'claude-sonnet-5-5' === $b['model'] && 'medium' === $b['output_config']['effort'] );
t( 'filter, four arguments: system text sent as one cached block', array( array( 'type' => 'text', 'text' => 'sys', 'cache_control' => array( 'type' => 'ephemeral' ) ) ) === $b['system'] );
t( 'filter, four arguments: no thinking or temperature parameter', ! isset( $b['thinking'] ) && ! isset( $b['temperature'] ) );
TWD_AP_Settings::$model = 'claude-opus-5-5';
apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000 );
t( 'filter, four arguments: follows the setting (Opus)', 'claude-opus-5-5' === body()['model'] );
TWD_AP_Settings::$model = '';

// the optional fifth argument
$r = apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000, array( 'model' => 'claude-opus-5-5', 'effort' => 'high' ) ); $b = body();
t( 'filter, five arguments: Opus at high effort', 'ok' === $r && 'claude-opus-5-5' === $b['model'] && 'high' === $b['output_config']['effort'] );
apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000, array( 'effort' => 'low' ) );
t( 'filter, five arguments: low effort, model from the setting', 'claude-sonnet-5-5' === body()['model'] && 'low' === body()['output_config']['effort'] );
apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000, array( 'model' => 'claude-haiku-5-5', 'effort' => 'max' ) );
t( 'filter, five arguments: unknown model and effort fall back to the setting and medium', 'claude-sonnet-5-5' === body()['model'] && 'medium' === body()['output_config']['effort'] );
TWD_AP_Settings::$model = 'claude-opus-5-5';
apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000, array( 'model' => 'claude-sonnet-5-5' ) );
t( 'filter, five arguments: a caller may pick Sonnet while the setting is Opus', 'claude-sonnet-5-5' === body()['model'] );
apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000, array( 'model' => 'bogus' ) );
t( 'filter, five arguments: an unknown model falls back to the setting, not the default', 'claude-opus-5-5' === body()['model'] );
TWD_AP_Settings::$model = '';
$r = apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000, 'not an array' );
t( 'filter, five arguments: a non-array options value is ignored', 'ok' === $r && 'claude-sonnet-5-5' === body()['model'] && 'medium' === body()['output_config']['effort'] );
apply_filters( 'twd_ai_complete', null, 'sys', 'msg', 1000, array( 'effort' => ' HIGH ' ) );
t( 'filter, five arguments: effort is case and space tolerant', 'high' === body()['output_config']['effort'] );
$r = apply_filters( 'twd_ai_complete', null, '', 'msg', 1000 );
t( 'filter: an empty system text sends no system block at all', 'ok' === $r && ! isset( body()['system'] ) );
t( 'filter: still waits 120 seconds and still does not use up the daily 30', 120 === end( $GLOBALS['sent'] )[1]['timeout'] && 30 === TWD_AP_AI_Generate::remaining() );
t( 'filter: the key is still never in the request body', false === strpos( end( $GLOBALS['sent'] )[1]['body'], $SECRET ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
