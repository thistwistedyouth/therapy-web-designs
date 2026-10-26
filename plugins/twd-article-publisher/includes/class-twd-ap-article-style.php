<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives published articles readable typography on any theme, independent of
 * whether the site has built its own Single Post template. Unlike the popup's
 * own CSS (only loaded for logged-in editors via TWD_AP_Frontend), this loads
 * for every visitor, because they're the ones actually reading the article.
 */
class TWD_AP_Article_Style {

	private static $instance = null;
	private static $wrapped = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_filter( 'the_content', array( $this, 'wrap_content' ), 20 );
	}

	public function maybe_enqueue() {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		wp_enqueue_style(
			'twd-ap-article-style',
			TWD_AP_URL . 'assets/article-content.css',
			array(),
			TWD_AP_VERSION
		);

		wp_enqueue_script(
			'twd-ap-article-style',
			TWD_AP_URL . 'assets/article-content.js',
			array(),
			TWD_AP_VERSION,
			true
		);
	}

	public function wrap_content( $content ) {
		if ( self::$wrapped || is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		self::$wrapped = true;
		$style = self::look_style();
		return '<div class="twd-ap-article-content"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>' . $content . '</div>';
	}

	/**
	 * The owner's "Article look" choices as CSS custom properties for the
	 * article wrapper. Only what the owner has changed is returned, so a
	 * site that never touched the settings gets no style attribute and
	 * keeps the stylesheet's own defaults. Every value is a validated hex
	 * colour or one of a fixed list of font, size and spacing choices.
	 */
	public static function look_style() {
		$s    = TWD_AP_Grid_Settings::get();
		$vars = array();
		$map  = array(
			'look_text_color'    => '--twd-ap-content-text',
			'look_heading_color' => '--twd-ap-content-heading',
			'look_link_color'    => '--twd-ap-content-link',
			'look_quote_color'   => '--twd-ap-content-quote',
		);
		foreach ( $map as $key => $var ) {
			if ( ! empty( $s[ $key ] ) && sanitize_hex_color( $s[ $key ] ) ) {
				$vars[] = $var . ':' . $s[ $key ];
			}
		}
		if ( 'serif' === $s['look_font'] ) {
			$vars[] = '--twd-ap-content-font:Georgia, "Times New Roman", Times, serif';
		} elseif ( 'theme' === $s['look_font'] ) {
			// Not a variable: a CSS-wide keyword such as inherit cannot be the
			// value of a custom property (it would mean "inherit the
			// variable"). Inline !important beats the stylesheet's own.
			$vars[] = 'font-family:inherit !important';
		}
		if ( 'large' === $s['look_size'] ) {
			$vars[] = '--twd-ap-content-size:19px';
			$vars[] = '--twd-ap-content-size-m:17px';
		}
		$leading = array( 'compact' => '1.55', 'relaxed' => '1.95' );
		if ( isset( $leading[ $s['look_spacing'] ] ) ) {
			$vars[] = '--twd-ap-content-leading:' . $leading[ $s['look_spacing'] ];
		}
		return implode( ';', $vars ) . ( $vars ? ';' : '' );
	}
}
