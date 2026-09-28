<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleans HTML pasted or written into the article popup before it is saved.
 * Keeps common article formatting, strips scripts/styles/inline styles and
 * anything unsafe, and demotes H1s to H2s so the page's own dynamic post
 * title stays the only H1 on the page.
 */
class TWD_AP_Sanitizer {

	public static function clean( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}

		// Demote H1s so the page's own dynamic post title stays the only H1.
		$html = preg_replace( '/<h1(\s[^>]*)?>/i', '<h2$1>', $html );
		$html = preg_replace( '/<\/h1>/i', '</h2>', $html );

		$allowed = array(
			'p'          => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'blockquote' => array(),
			'hr'         => array(),
			'figure'     => array(
				'class'            => array(),
				'data-youtube-id'  => array(),
			),
			'figcaption' => array(),
			'a'          => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'img'        => array(
				'src'    => array(),
				'alt'    => array(),
				'width'  => array(),
				'height' => array(),
				'class'  => array(),
			),
		);

		/**
		 * Filter the tags allowed in an article body published through the
		 * popup, in case a particular site needs to allow more (or fewer).
		 */
		$allowed = apply_filters( 'twd_ap_allowed_html', $allowed );

		$clean = wp_kses( $html, $allowed );

		// Force a safe rel on any link that opens in a new tab.
		$clean = preg_replace_callback(
			'/<a\s[^>]*target=["\']_blank["\'][^>]*>/i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false === stripos( $tag, 'rel=' ) ) {
					$tag = str_ireplace( '<a ', '<a rel="noopener noreferrer" ', $tag );
				}
				return $tag;
			},
			$clean
		);

		return $clean;
	}
}
