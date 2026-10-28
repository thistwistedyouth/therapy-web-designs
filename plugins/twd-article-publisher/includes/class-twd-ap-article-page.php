<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The article page on a plain theme (1.50.0), for a site that has no page-builder
 * template for single articles (Hello Elementor on its own, for example). Two
 * optional things, both off until the owner ticks them in Customise this grid >
 * Article page, so a site that never touches them is byte for byte as before:
 *
 *  - the featured image under the title, inside the article's column, in the card
 *    image shape the site already chose (wide, 4:3 or square);
 *  - the theme's own "Tagged ..." line under the article taken out of the page
 *    (the tags themselves are kept; tag pages still work).
 *
 * The Back link and the end-of-article row are in TWD_AP_Swipebook::end_row_html();
 * the Browse by topic row and its shortcode are in TWD_AP_Related.
 */
class TWD_AP_Article_Page {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// 12: after the swipe book buttons were put first in the text (11), so the
		// picture lands above them, and before the article wrapper (20), so it sits
		// inside the article's column.
		add_filter( 'the_content', array( $this, 'prepend_image' ), 12 );
		add_filter( 'the_tags', array( $this, 'drop_theme_tags' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/** True on a normal view of a single article (not the swipe book page, not the editor, not a widget). */
	private static function is_article_view() {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}
		return '1' !== (string) get_query_var( 'twd_ap_swipebook' );
	}

	/** The picture's shape class: the card image shape, with 'wide' for anything unrecognised. */
	public static function shape_class( $shape ) {
		return 'twd-ap-featured-' . ( in_array( $shape, array( '4-3', 'square' ), true ) ? $shape : 'wide' );
	}

	/**
	 * True when the article's own text already opens with this picture (the editor
	 * put it there, or the article was converted from a page builder), so it is not
	 * printed a second time. The swipe book buttons that were put first are set aside
	 * before looking.
	 */
	public static function text_opens_with_image( $content, $attachment_id ) {
		$content = preg_replace( '#^\s*<div class="twd-ap-swipebook-top">.*?</div>\s*#s', '', (string) $content, 1 );
		$head    = substr( $content, 0, 600 );
		return (bool) preg_match( '/<img[^>]*\bwp-image-' . (int) $attachment_id . '\b/i', $head );
	}

	public function prepend_image( $content ) {
		if ( empty( TWD_AP_Grid_Settings::get()['featured_image_show'] ) || ! self::is_article_view() ) {
			return $content;
		}
		$id = (int) get_post_thumbnail_id();
		if ( ! $id || self::text_opens_with_image( $content, $id ) ) {
			return $content;
		}
		// Loaded at once (it is the first thing on the page) and at the size that
		// fits the 720px column; the alt text is the picture's own from the Media
		// Library, empty when it has none.
		$img = wp_get_attachment_image(
			$id,
			'large',
			false,
			array(
				'class'         => 'twd-ap-featured-img',
				'sizes'         => '(max-width: 760px) 100vw, 720px',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
		if ( '' === $img ) {
			return $content;
		}
		TWD_AP_Article_Style::enqueue();
		$shape = TWD_AP_Grid_Settings::get()['thumb_shape'];
		return '<figure class="twd-ap-featured-figure ' . esc_attr( self::shape_class( $shape ) ) . '">' . $img . '</figure>' . $content;
	}

	/** True while the theme is printing the tags of the article being shown. */
	private static function hiding_tags() {
		if ( empty( TWD_AP_Grid_Settings::get()['hide_theme_tags'] ) || is_admin() || ! is_singular( 'post' ) ) {
			return false;
		}
		return (int) get_the_ID() === (int) get_queried_object_id();
	}

	/**
	 * Hello Elementor (and many other themes) print the tag links with the_tags(),
	 * which asks this filter for the finished list; an empty answer prints nothing.
	 */
	public function drop_theme_tags( $list ) {
		return self::hiding_tags() ? '' : $list;
	}

	/** A class on the page so the stylesheet can also hide the theme's own empty tag box. */
	public function body_class( $classes ) {
		if ( ! empty( TWD_AP_Grid_Settings::get()['hide_theme_tags'] ) && is_singular( 'post' ) ) {
			$classes[] = 'twd-ap-no-theme-tags';
		}
		return $classes;
	}
}
