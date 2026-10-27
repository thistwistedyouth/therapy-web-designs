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
		// After WordPress's own paragraph and quote tidying (also priority 10,
		// registered earlier) and before the swipe book buttons are added (11
		// and 15), so the buttons' own words are left alone.
		add_filter( 'the_content', array( $this, 'tidy_content' ), 10 );
	}

	public function maybe_enqueue() {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		self::enqueue();
	}

	/**
	 * The article stylesheet and script. Also called by the shortcodes that
	 * print article furniture (the related block, the swipe book button), so
	 * they are styled on a page that is not a single article too.
	 */
	public static function enqueue() {
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

	/**
	 * "Tidy line endings" (Article look): keeps a one or two letter word with
	 * the word after it, so a line never ends on "so", "we" or "an". Only the
	 * article being shown on its own page, and only when the owner has left it
	 * ticked (it is, by default). The stored article is never touched.
	 */
	public function tidy_content( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( empty( TWD_AP_Grid_Settings::get()['look_tidy_lines'] ) ) {
			return $content;
		}
		return self::tidy_lines( $content );
	}

	/**
	 * The text of an HTML string with a non-breaking space after every word
	 * of one or two letters that is followed by a space. Tags and their
	 * attributes are left exactly as they are, and so is everything inside
	 * pre, code, kbd, samp, script, style and textarea. A word counts only if
	 * it stands alone (not the end of a longer word, not after an apostrophe
	 * or hyphen). If the text is not valid UTF-8 it is returned unchanged.
	 */
	public static function tidy_lines( $html ) {
		$parts = preg_split( '/(<[^>]*>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		$skip = 0;
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( preg_match( '/^<(\/?)(pre|code|kbd|samp|script|style|textarea)\b/i', $part, $m ) && '/>' !== substr( $part, -2 ) ) {
					$skip = max( 0, $skip + ( '/' === $m[1] ? -1 : 1 ) );
				}
				continue;
			}
			if ( $skip > 0 ) {
				continue;
			}
			// The apostrophe entities too: wptexturize has already turned "it's" into
			// "it&#8217;s" by the time this runs, and the "s" is not a word of its own.
			$done = preg_replace( '/(?<![\p{L}\p{N}\'\x{2019}\-])(?<!&#8217;)(?<!&#8216;)(?<!&#039;)(?<!&#x2019;)(?<!&rsquo;)(?<!&lsquo;)(?<!&apos;)(\p{L}{1,2}) (?=\S|$)/u', '$1&nbsp;', $part );
			if ( null === $done ) {
				return $html; // not valid UTF-8: leave the whole article as it is
			}
			$parts[ $i ] = $done;
		}
		return implode( '', $parts );
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
