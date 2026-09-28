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
	}

	public function wrap_content( $content ) {
		if ( self::$wrapped || is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		self::$wrapped = true;
		return '<div class="twd-ap-article-content">' . $content . '</div>';
	}
}
