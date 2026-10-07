<?php
// Renders the popup template to a static page so the real CSS and JS can be tested in a browser.
// usage: php render.php <key-on|key-off> <out.html>
define( 'ABSPATH', '/' );
define( 'TWD_AP_ARTICLE_ASSIST_URL', 'https://example.test/article-assist/' );
$on  = isset( $argv[1] ) && 'key-on' === $argv[1];
$out = $argv[2];

function esc_html_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function __( $s ) { return $s; }
function get_theme_mod() { return ''; }
function wp_get_attachment_image_url() { return ''; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function site_url( $p = '' ) { return 'https://example.test' . $p; }
function get_option( $k, $d = false ) { return $d; }
function wp_kses_post( $s ) { return $s; }
class TWD_AP_AI_Generate { public static $on = false; public static function is_configured() { return self::$on; } }
TWD_AP_AI_Generate::$on = $on;
$show_new  = true;
$show_edit = true;

ob_start();
include dirname( __DIR__, 3 ) . '/plugins/twd-article-publisher/templates/buttons-and-modal.php';
$body = ob_get_clean();
file_put_contents( $out, '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="file://' . dirname( __DIR__, 3 ) . '/plugins/twd-article-publisher/assets/publisher.css"></head><body><p>Page behind the popup</p>' . $body . '</body></html>' );
echo "rendered " . strlen( $body ) . " bytes\n";
