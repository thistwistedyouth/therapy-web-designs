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

	/**
	 * Em and en dashes read as a tell that a page was AI-written, so they are
	 * never allowed through, regardless of whether the text was typed, pasted
	 * from an AI assistant, or came back from a future in-house AI tool.
	 * Handles both the real character and its common HTML-entity forms,
	 * since pasted content can carry either. A dash used as separating
	 * punctuation becomes a comma; one already touching punctuation is just
	 * dropped rather than doubled up.
	 */
	public static function strip_dashes( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		$em = '(?:\x{2014}|&mdash;|&#0*8212;|&#[xX]0*2014;)';
		$en = '(?:\x{2013}|&ndash;|&#0*8211;|&#[xX]0*2013;)';
		$dash = '(?:' . $em . '|' . $en . ')';

		// A dash immediately before closing punctuation: just drop it.
		$text = preg_replace( '/\s*' . $dash . '\s*(?=[.,;:!?])/u', '', $text );
		// Otherwise: a comma, single-spaced.
		$text = preg_replace( '/\s*' . $dash . '\s*/u', ', ', $text );
		// Tidy up any double commas the above can produce.
		$text = preg_replace( '/,\s*,/u', ',', $text );

		return $text;
	}

	public static function clean( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}

		$html = self::strip_dashes( $html );

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
