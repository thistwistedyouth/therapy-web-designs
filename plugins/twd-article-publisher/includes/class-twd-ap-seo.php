<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where an article's search title and description live, and the "Generate from
 * article" suggestion (v1.50.0).
 *
 * Yoast SEO active: its own fields. Another SEO plugin active: none (the author
 * edits those in that plugin, and the popup hides the block). No SEO plugin at all
 * (the usual case on a new site): this plugin keeps the two fields itself and prints
 * the title, description and share tags for single articles. (TWD Site Kit prints
 * search details for pages only, not posts, so it does not cover articles.) A site can
 * override with the twd_ap_seo_keys filter (array( title_key, description_key ) or null).
 */
class TWD_AP_Seo {

	const OWN_TITLE = '_twd_ap_seo_title';
	const OWN_DESC  = '_twd_ap_seo_desc';
	const MAX_TITLE = 60;
	const MAX_DESC  = 155;

	/** @return array|null array( title_meta_key, description_meta_key ) */
	public static function keys() {
		$keys = null;
		if ( defined( 'WPSEO_VERSION' ) ) {
			$keys = array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' );
		} else {
			$other = false;
			foreach ( array( 'RANK_MATH_VERSION', 'AIOSEO_VERSION', 'SEOPRESS_VERSION', 'THE_SEO_FRAMEWORK_VERSION' ) as $const ) {
				if ( defined( $const ) ) {
					$other = true;
					break;
				}
			}
			if ( ! $other ) {
				$keys = array( self::OWN_TITLE, self::OWN_DESC );
			}
		}
		$keys = apply_filters( 'twd_ap_seo_keys', $keys );
		return ( is_array( $keys ) && 2 === count( $keys ) ) ? array_values( $keys ) : null;
	}

	/** The site tagline, used when no site context has been typed. */
	public static function site_context_default() {
		return trim( wp_strip_all_tags( (string) get_bloginfo( 'description' ) ) );
	}

	/** What the owner typed, else the tagline. */
	public static function site_context() {
		$typed = trim( (string) ( TWD_AP_Grid_Settings::get()['site_context'] ?? '' ) );
		return '' !== $typed ? $typed : self::site_context_default();
	}

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 1 );
	}

	/** True when this plugin is the one printing an article's search details. */
	private static function printing() {
		$keys = self::keys();
		return is_singular( 'post' ) && $keys && self::OWN_TITLE === $keys[0];
	}

	public static function filter_title( $title ) {
		if ( ! self::printing() ) {
			return $title;
		}
		$own = trim( (string) get_post_meta( get_queried_object_id(), self::OWN_TITLE, true ) );
		if ( '' === $own ) {
			return $title;
		}
		$sep = apply_filters( 'document_title_separator', '-' );
		return $own . ' ' . $sep . ' ' . get_bloginfo( 'name' );
	}

	public static function print_head() {
		if ( ! self::printing() ) {
			return;
		}
		$id    = get_queried_object_id();
		$post  = get_post( $id );
		if ( ! $post ) {
			return;
		}
		// The theme's own description tag (Hello Elementor) would be a second one.
		remove_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );
		$title = trim( (string) get_post_meta( $id, self::OWN_TITLE, true ) );
		if ( '' === $title ) {
			$title = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		}
		$desc = trim( (string) get_post_meta( $id, self::OWN_DESC, true ) );
		if ( '' === $desc && has_excerpt( $post ) ) {
			$desc = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
		}
		$out = array();
		if ( '' !== $desc ) {
			$out[] = '<meta name="description" content="' . esc_attr( $desc ) . '">';
		}
		$out[] = '<meta property="og:type" content="article">';
		$out[] = '<meta property="og:title" content="' . esc_attr( $title ) . '">';
		if ( '' !== $desc ) {
			$out[] = '<meta property="og:description" content="' . esc_attr( $desc ) . '">';
		}
		$out[] = '<meta property="og:url" content="' . esc_url( get_permalink( $post ) ) . '">';
		$out[] = '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
		$img   = get_the_post_thumbnail_url( $post, 'large' );
		if ( $img ) {
			$out[] = '<meta property="og:image" content="' . esc_url( $img ) . '">';
		}
		$out[] = '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">';
		echo "
" . implode( "
", $out ) . "
"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * A suggested SEO title and meta description for an article, from its own
	 * text only. Fills the popup's two boxes for the author to read and edit; it
	 * saves nothing. Returns array( seo_title, meta_description ) or WP_Error.
	 */
	public static function suggest( $title, $html ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $html ) ) );
		if ( '' === $text && '' === trim( (string) $title ) ) {
			return new WP_Error( 'twd_ap_seo_empty', __( 'Add a title and some text to the article first.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}
		if ( function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, 6000 );
		} else {
			$text = substr( $text, 0, 6000 );
		}
		$system  = "You write the search listing for one article on a therapist's website.\n" .
			"Use only what the article itself says. Never add facts, qualifications, places, prices, statistics or claims that are not in the text. Never promise a cure or a result.\n" .
			"Write plain British English, in the voice of the article, no clickbait, no hype, no all capitals, no emoji.\n" .
			"Never use an em dash or an en dash anywhere. Use a comma or a full stop.\n" .
			"seo_title: " . self::MAX_TITLE . " characters or fewer, says what the article is about, does not add the site name.\n" .
			"meta_description: " . self::MAX_DESC . " characters or fewer, one or two plain sentences that tell a reader what they will learn.\n" .
			'Reply with strict JSON only, no code fences: {"seo_title":"...","meta_description":"..."}';
		$context = self::site_context();
		if ( '' !== $context ) {
			$system .= "
About the site (from its owner): " . $context . ". Use these words, such as the profession or the place, only where they genuinely fit what this article is about and read naturally. Never force them in, never repeat them, and never add anything about the site that is not in this line or in the article.";
		}
		$message = "Article title: " . trim( (string) $title ) . "\n\nArticle text:\n" . $text;
		$reply   = TWD_AP_AI_Generate::complete( $system, $message, 600 );
		if ( null === $reply ) {
			return new WP_Error( 'twd_ap_ai_not_configured', __( 'No API key is set up for this site yet.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		$start = strpos( $reply, '{' );
		$end   = strrpos( $reply, '}' );
		$data  = ( false !== $start && false !== $end ) ? json_decode( substr( $reply, $start, $end - $start + 1 ), true ) : null;
		if ( ! is_array( $data ) || empty( $data['seo_title'] ) || empty( $data['meta_description'] ) ) {
			return new WP_Error( 'twd_ap_seo_bad_result', __( 'The suggestion did not come back in a usable form. Please try again.', 'twd-article-publisher' ), array( 'status' => 502 ) );
		}
		return array(
			'seo_title'        => self::fit( TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( (string) $data['seo_title'] ) ), self::MAX_TITLE ),
			'meta_description' => self::fit( TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( (string) $data['meta_description'] ) ), self::MAX_DESC ),
		);
	}

	/** Cut at a word boundary, never mid-word. */
	private static function fit( $text, $max ) {
		$text = trim( $text );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $len <= $max ) {
			return $text;
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		$sp  = strrpos( $cut, ' ' );
		return rtrim( false !== $sp && $sp > $max * 0.6 ? substr( $cut, 0, $sp ) : $cut, " ,;:" );
	}
}
