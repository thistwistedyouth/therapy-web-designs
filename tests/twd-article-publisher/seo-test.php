<?php
// Tests for where an article's search title and description live (1.50.0) and for the
// suggestion's length handling. Real TWD_AP_Seo, WordPress functions faked.
//   php tests/twd-article-publisher/seo-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
$GLOBALS['filter_result'] = null;
function apply_filters( $tag, $value ) { return $GLOBALS['filter_result'] ?? $value; }
require $plugin . 'includes/class-twd-ap-seo.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }

// No SEO plugin at all (the usual case on a new site): the plugin keeps the fields itself.
t( 'no SEO plugin: the plugin\'s own two fields', array( '_twd_ap_seo_title', '_twd_ap_seo_desc' ) === TWD_AP_Seo::keys() );

// Another SEO plugin: nothing is saved, the popup hides the block.
define( 'RANK_MATH_VERSION', '1' );
t( 'another SEO plugin active: no fields (the author edits them there)', null === TWD_AP_Seo::keys() );

// A site can override.
$GLOBALS['filter_result'] = array( '_my_title', '_my_desc' );
t( 'the twd_ap_seo_keys filter can set the fields', array( '_my_title', '_my_desc' ) === TWD_AP_Seo::keys() );
$GLOBALS['filter_result'] = array( 'only_one' );
t( 'a filter answer that is not a pair is ignored', null === TWD_AP_Seo::keys() );
$GLOBALS['filter_result'] = null;

// Length handling: cut at a word, never mid-word, never over the limit.
$fit = new ReflectionMethod( 'TWD_AP_Seo', 'fit' );
$fit->setAccessible( true );
$long = 'Understanding trauma and how it is treated in adults and children with EMDR and TF-CBT';
$out  = $fit->invoke( null, $long, 60 );
t( 'fit: never longer than the limit', strlen( $out ) <= 60, $out );
t( 'fit: ends on a whole word', false !== strpos( $long, $out ) && ' ' === substr( $long, strlen( $out ), 1 ), $out );
t( 'fit: a short text is returned as it is', 'Short title' === $fit->invoke( null, '  Short title ', 60 ) );

// Yoast wins over everything when it is active (checked last: the constant cannot be undefined).
define( 'WPSEO_VERSION', '1' );
t( 'Yoast active: Yoast\'s fields, even with another plugin named', array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ) === TWD_AP_Seo::keys() );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
