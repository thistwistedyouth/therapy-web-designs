<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One call-to-action box the owner writes once (Customise this grid > Call
 * to action) and chooses where to show: under the resources grid, under a
 * category page's grid, under the article index, and at the end of every
 * article. Nothing shows until something is filled in. Plain text only (no
 * HTML), and the button link is limited to http, https, mailto and tel.
 */
class TWD_AP_Cta {

	// Place => the setting that switches the box on there.
	const SWITCHES = array(
		'grid'     => 'cta_show_grid',
		'category' => 'cta_show_categories',
		'index'    => 'cta_show_index',
		'article'  => 'cta_show_articles',
	);

	private static $instance = null;
	private static $appended = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// After TWD_AP_Article_Style::wrap_content() (20), so the box sits
		// below the article rather than inside its narrow column.
		add_filter( 'the_content', array( $this, 'append_to_article' ), 25 );
	}

	/**
	 * The HTML for one place, or '' when the box is empty or switched off
	 * for that place.
	 */
	public static function html( $context ) {
		if ( ! isset( self::SWITCHES[ $context ] ) ) {
			return '';
		}
		$s = TWD_AP_Grid_Settings::get();
		if ( empty( $s[ self::SWITCHES[ $context ] ] ) ) {
			return '';
		}
		$heading = trim( (string) $s['cta_heading'] );
		$text    = trim( (string) $s['cta_text'] );
		$label   = trim( (string) $s['cta_button_label'] );
		$url     = trim( (string) $s['cta_button_url'] );
		$button  = '' !== $label && '' !== $url;
		if ( '' === $heading && '' === $text && ! $button ) {
			return '';
		}

		$html = '<aside class="twd-ap-cta twd-ap-cta-' . esc_attr( $context ) . '" style="' . esc_attr( '--twd-ap-accent-color:' . $s['accent_color'] . ';' ) . '"><div class="twd-ap-cta-inner">';
		if ( '' !== $heading ) {
			$html .= '<p class="twd-ap-cta-heading">' . esc_html( $heading ) . '</p>';
		}
		if ( '' !== $text ) {
			$html .= '<p class="twd-ap-cta-text">' . nl2br( esc_html( $text ) ) . '</p>';
		}
		if ( $button ) {
			$html .= '<a class="twd-ap-cta-button" href="' . esc_url( $url ) . '" rel="noopener">' . esc_html( $label ) . '</a>';
		}
		return $html . '</div></aside>';
	}

	/**
	 * Adds the box after the body of a single article, once.
	 */
	public function append_to_article( $content ) {
		if ( self::$appended || is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$box = self::html( 'article' );
		if ( '' === $box ) {
			return $content;
		}
		self::$appended = true;
		return $content . $box;
	}
}
