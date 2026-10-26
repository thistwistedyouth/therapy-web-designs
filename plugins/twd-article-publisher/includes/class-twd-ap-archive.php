<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes category and tag pages work out of the box: the plugin's own template
 * (the page's title, the category's intro, the grid, the call to action) is
 * used instead of the theme's plain archive, so nobody has to build a
 * template in a page builder first. It steps aside whenever the site has made
 * a deliberate choice of its own:
 *
 *  - the owner switched it off (Customise this grid > Category pages),
 *  - a page builder (Elementor Pro Theme Builder) has an archive template
 *    that applies to this page,
 *  - the theme ships its own category or tag template (category.php,
 *    category-{slug}.php, tag.php and so on),
 *  - the theme is a block theme (header and footer are template parts there),
 *  - or the twd_ap_archive_takeover filter returns false.
 */
class TWD_AP_Archive {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Late, so a theme or page builder that picks a template for a
		// reason of its own has already had its say; should_take_over()
		// checks for the cases it knows about.
		add_filter( 'template_include', array( $this, 'maybe_take_over' ), 99 );
	}

	public function maybe_take_over( $template ) {
		if ( ! self::should_take_over() ) {
			return $template;
		}
		// WordPress's own /category/x/page/2/ would print this same grid again
		// (the grid pages itself with ?twd_page=N and Load more), a duplicate
		// page for search engines. Send those addresses to the category page.
		if ( is_paged() ) {
			$url = get_term_link( get_queried_object() );
			if ( ! is_wp_error( $url ) && '' !== $url ) {
				wp_safe_redirect( $url, 301 );
				exit;
			}
		}
		return TWD_AP_PATH . 'templates/archive-grid.php';
	}

	public static function should_take_over() {
		if ( is_admin() || ! is_main_query() || ( ! is_category() && ! is_tag() ) ) {
			return false;
		}
		$settings = TWD_AP_Grid_Settings::get();
		if ( empty( $settings['archive_takeover'] ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return false;
		}
		if ( self::theme_has_own_template() || self::page_builder_template_applies() ) {
			return false;
		}
		return (bool) apply_filters( 'twd_ap_archive_takeover', true );
	}

	/**
	 * A dedicated category or tag template in the active theme (or its
	 * parent) is the theme author's own design, so it is left alone. The
	 * generic archive.php and index.php do not count: those are what this
	 * replaces.
	 */
	private static function theme_has_own_template() {
		$term = get_queried_object();
		if ( ! $term || empty( $term->term_id ) ) {
			return false;
		}
		$names = array();
		if ( is_category() ) {
			$names = array( 'category-' . $term->slug . '.php', 'category-' . $term->term_id . '.php', 'category.php' );
		} elseif ( is_tag() ) {
			$names = array( 'tag-' . $term->slug . '.php', 'tag-' . $term->term_id . '.php', 'tag.php' );
		}
		return '' !== locate_template( $names );
	}

	/**
	 * True when Elementor Pro's Theme Builder has an archive template that
	 * applies to this page. Written defensively: if the class or method is
	 * missing or throws (another Elementor version), this says no and the
	 * takeover is skipped only when it can be sure a template applies.
	 */
	private static function page_builder_template_applies() {
		if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
			return false;
		}
		try {
			$module = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
			if ( ! $module || ! method_exists( $module, 'get_conditions_manager' ) ) {
				return false;
			}
			$docs = $module->get_conditions_manager()->get_documents_for_location( 'archive' );
			return ! empty( $docs );
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
