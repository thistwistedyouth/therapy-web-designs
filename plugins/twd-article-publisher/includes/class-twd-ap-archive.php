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
		// Redirects belong on template_redirect (core's own hook for them); it
		// also runs for HEAD requests, which never reach template_include.
		add_action( 'template_redirect', array( $this, 'maybe_redirect_paged' ) );
	}

	/**
	 * WordPress's own /category/x/page/2/ would print this same grid again
	 * (the grid pages itself with ?twd_page=N and Load more), a duplicate
	 * page for search engines. Send those addresses to the category page,
	 * permanently, so any that are already indexed fold into it.
	 */
	public function maybe_redirect_paged() {
		if ( ! is_paged() || ! self::should_take_over() ) {
			return;
		}
		$url = get_term_link( get_queried_object() );
		if ( ! is_wp_error( $url ) && '' !== $url ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}

	public function maybe_take_over( $template ) {
		if ( ! self::should_take_over() ) {
			return $template;
		}
		// Belt and braces for page builders: a template that something other
		// than the theme chose (Elementor Pro's Theme Builder, another
		// builder or plugin) always wins, whatever the detection above says.
		if ( ! self::is_theme_template( $template ) ) {
			return $template;
		}
		// The main query still counts the theme's own pages (/page/2/ ...), and
		// SEO plugins print a "next page" link from that count. This page
		// pages itself, so tell them there is only one.
		global $wp_query;
		if ( is_object( $wp_query ) ) {
			$wp_query->max_num_pages = 1;
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
	 * True when the template WordPress chose lives in the active theme (or its
	 * parent), as the generic archive.php or index.php does.
	 */
	private static function is_theme_template( $template ) {
		$template = wp_normalize_path( (string) $template );
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $dir ) {
			$dir = trailingslashit( wp_normalize_path( $dir ) );
			if ( 0 === strpos( $template, $dir ) ) {
				return true;
			}
		}
		return false;
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
		// Same names WordPress itself tries, including the decoded slug.
		$slugs = array_unique( array( $term->slug, urldecode( $term->slug ) ) );
		$base  = is_category() ? 'category' : ( is_tag() ? 'tag' : '' );
		if ( '' !== $base ) {
			foreach ( $slugs as $slug ) {
				$names[] = $base . '-' . $slug . '.php';
			}
			$names[] = $base . '-' . $term->term_id . '.php';
			$names[] = $base . '.php';
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
