<?php
// Tests for the plugin's own category and tag page: when it takes over, and when it steps aside.
// Real TWD_AP_Archive and TWD_AP_Grid_Settings classes and the real templates/archive-grid.php;
// WordPress, Elementor Pro and the theme are faked.
//   php tests/twd-article-publisher/archive-test.php
define( 'ABSPATH', '/' );
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';
define( 'TWD_AP_PATH', $plugin );
$GLOBALS['opt'] = array();
$GLOBALS['ctx'] = array( 'admin' => false, 'main' => true, 'cat' => true, 'tag' => false, 'block' => false, 'own_templates' => array(), 'filter' => true );
$GLOBALS['term'] = (object) array( 'term_id' => 3, 'slug' => 'anxiety', 'name' => 'Tom &amp; Jerry', 'taxonomy' => 'category' );
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function add_filter() {}
function add_action() {}
function apply_filters( $tag, $v ) { return 'twd_ap_archive_takeover' === $tag ? $GLOBALS['ctx']['filter'] : $v; }
function is_admin() { return $GLOBALS['ctx']['admin']; }
function is_main_query() { return $GLOBALS['ctx']['main']; }
function is_category() { return $GLOBALS['ctx']['cat']; }
function is_tag() { return $GLOBALS['ctx']['tag']; }
function wp_is_block_theme() { return $GLOBALS['ctx']['block']; }
function get_queried_object() { return $GLOBALS['term']; }
function locate_template( $names ) { foreach ( (array) $names as $n ) { if ( in_array( $n, $GLOBALS['ctx']['own_templates'], true ) ) { return '/theme/' . $n; } } return ''; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function single_term_title( $p = '', $echo = true ) { return $GLOBALS['term']->name; }
function get_header() { echo "[HEADER]\n"; }
function get_footer() { echo "[FOOTER]\n"; }
function do_shortcode( $s ) { $GLOBALS['shortcodes'][] = $s; return '[GRID OUTPUT]'; }
require $plugin . 'includes/class-twd-ap-grid-settings.php';
require $plugin . 'includes/class-twd-ap-archive.php';

$pass = 0; $fail = 0;
function t( $name, $ok, $extra = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }
function ctx( $changes = array() ) { $GLOBALS['ctx'] = array_merge( array( 'admin' => false, 'main' => true, 'cat' => true, 'tag' => false, 'block' => false, 'own_templates' => array(), 'filter' => true ), $changes ); }
function take() { return TWD_AP_Archive::should_take_over(); }
$arch = ( new ReflectionClass( 'TWD_AP_Archive' ) )->newInstanceWithoutConstructor();

// ---------- it works out of the box ----------
t( 'default: on for a category page with a plain theme (no setup, no template)', 'on' === ( TWD_AP_Grid_Settings::get()['archive_takeover'] ? 'on' : 'off' ) && take() );
t( 'it hands back the plugin template instead of the theme archive', $plugin . 'templates/archive-grid.php' === $arch->maybe_take_over( '/theme/archive.php' ) );
ctx( array( 'cat' => false, 'tag' => true ) );
t( 'a tag page too', take() );

// ---------- when it steps aside ----------
ctx( array( 'cat' => false ) );
t( 'not on pages that are neither a category nor a tag (home, search, author)', ! take() && '/theme/index.php' === $arch->maybe_take_over( '/theme/index.php' ) );
ctx( array( 'admin' => true ) );
t( 'not in wp-admin', ! take() );
ctx( array( 'main' => false ) );
t( 'not for a secondary query', ! take() );
$GLOBALS['opt']['twd_ap_grid_settings'] = array( 'archive_takeover' => false );
ctx();
t( 'the owner can switch it off (Customise this grid)', ! take() && '/theme/archive.php' === $arch->maybe_take_over( '/theme/archive.php' ) );
$GLOBALS['opt'] = array();
ctx( array( 'block' => true ) );
t( 'not on a block (full site editing) theme, where header and footer are template parts', ! take() );
ctx( array( 'own_templates' => array( 'category.php' ) ) );
t( 'not when the theme ships its own category.php', ! take() );
ctx( array( 'own_templates' => array( 'category-anxiety.php' ) ) );
t( 'not when the theme has a template for this exact category (slug)', ! take() );
ctx( array( 'own_templates' => array( 'category-3.php' ) ) );
t( 'not when the theme has a template for this exact category (id)', ! take() );
ctx( array( 'own_templates' => array( 'tag.php' ) ) );
t( 'a tag.php does not stop a category page being taken over', take() );
ctx( array( 'cat' => false, 'tag' => true, 'own_templates' => array( 'tag.php' ) ) );
t( 'but a tag page with the theme\'s own tag.php is left alone', ! take() );
ctx( array( 'own_templates' => array( 'archive.php', 'index.php' ) ) );
t( 'the generic archive.php and index.php do not count as the theme\'s own design', take() );
ctx( array( 'filter' => false ) );
t( 'the twd_ap_archive_takeover filter can switch it off for a site', ! take() );

// ---------- Elementor Pro Theme Builder ----------
ctx();
t( 'no Elementor Pro: the page builder check says no and it takes over', take() );
class ElementorPro_Conditions { public $docs; function __construct( $d ) { $this->docs = $d; } function get_documents_for_location( $loc ) { $GLOBALS['asked_location'] = $loc; return $this->docs; } }
class ElementorPro_Module { public $c; function __construct( $c ) { $this->c = $c; } function get_conditions_manager() { return $this->c; } }
class ElementorPro_Modules { public $m; function __construct( $m ) { $this->m = $m; } function get_modules( $name ) { return 'theme-builder' === $name ? $this->m : null; } }
eval( 'namespace ElementorPro; class Plugin { public static $inst; public $modules_manager; static function instance() { return self::$inst; } }' );
\ElementorPro\Plugin::$inst = new \ElementorPro\Plugin();
\ElementorPro\Plugin::$inst->modules_manager = new ElementorPro_Modules( new ElementorPro_Module( new ElementorPro_Conditions( array() ) ) );
t( 'Elementor Pro is active but has no archive template for this page: it takes over', take() && 'archive' === $GLOBALS['asked_location'] );
\ElementorPro\Plugin::$inst->modules_manager = new ElementorPro_Modules( new ElementorPro_Module( new ElementorPro_Conditions( array( 123 => 'doc' ) ) ) );
t( 'an Elementor Pro archive template applies to this page: it steps aside', ! take() );
\ElementorPro\Plugin::$inst->modules_manager = new ElementorPro_Modules( null );
t( 'Elementor Pro without a Theme Builder module (other version): it does not break, it takes over', take() );
\ElementorPro\Plugin::$inst->modules_manager = new class { function get_modules() { throw new Exception( 'boom' ); } };
t( 'Elementor Pro throwing an error: it does not break the page, it takes over', take() );

// ---------- the template ----------
ctx();
$GLOBALS['shortcodes'] = array();
ob_start(); include $plugin . 'templates/archive-grid.php'; $out = ob_get_clean();
t( 'template: theme header, then the category name as the only H1, then the grid, then the theme footer', 1 === preg_match( '/^\[HEADER\]\n<main id="content" class="twd-ap-archive site-main">\s*<h1 class="twd-ap-archive-title">Tom &amp; Jerry<\/h1>\s*\[GRID OUTPUT\]\s*<\/main>\n\[FOOTER\]\n$/', $out ) && 1 === substr_count( $out, '<h1' ) );
t( 'template: the name has no "Category:" prefix and is not double-encoded', false === strpos( $out, 'Category:' ) && false === strpos( $out, '&amp;amp;' ) );
t( 'template: the grid is the plain shortcode with the intro forced on', array( '[twd_articles intro="force"]' ) === $GLOBALS['shortcodes'] );
$GLOBALS['term']->name = 'Esteem <script>x</script>';
ob_start(); include $plugin . 'templates/archive-grid.php'; $out = ob_get_clean();
t( 'template: a category name with markup in it is escaped', false === strpos( $out, '<script' ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
