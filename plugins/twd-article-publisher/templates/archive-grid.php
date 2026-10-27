<?php
/**
 * The plugin's own category and tag page (see TWD_AP_Archive): the term's
 * name, then [twd_articles], which prints the intro, the grid, the page
 * links and the call to action. Uses the active theme's own header and
 * footer.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$twd_ap_term = get_queried_object();
// Hello Elementor's skip link points at #content and its header has no such
// element, so it is used there. Themes that already open a #content wrapper
// in their header (Astra, GeneratePress, Twenty Twenty-One) would get a second
// one, which is invalid, so this page uses its own id.
$twd_ap_id = ( 'hello-elementor' === get_template() ) ? 'content' : 'twd-ap-archive';
?>
<main id="<?php echo esc_attr( $twd_ap_id ); ?>" class="twd-ap-archive site-main">
	<h1 class="twd-ap-archive-title"><?php echo esc_html( html_entity_decode( $twd_ap_term ? $twd_ap_term->name : single_term_title( '', false ), ENT_QUOTES, 'UTF-8' ) ); ?></h1>
	<?php echo do_shortcode( '[twd_articles intro="force"]' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in TWD_AP_Shortcode. ?>
</main>
<?php
get_footer();
