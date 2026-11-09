<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remix (1.54.0): fact-check an existing article with a web search and
 * rewrite it more concisely, by copy and paste. Nothing here calls an AI
 * service. The popup builds a prompt from template() and the article as it
 * stands in the editor; the person pastes it into an assistant that can
 * search the web (Claude, for example), pastes the reply back, and check()
 * reads that reply: it cleans the article text with TWD_AP_Sanitizer::clean()
 * (the one path article HTML takes), tidies the fact-check report, compares
 * pictures, videos and links with the original, and counts words. It saves
 * nothing. The person reviews the result in the editor and clicks Update.
 */
class TWD_AP_Remix {

	/** A pasted reply longer than this is refused (a whole article plus its report is far smaller). */
	const MAX_REPLY_CHARS = 200000;

	/** At most this many fact-check items are kept. */
	const MAX_CLAIMS = 60;

	/** At most this many sources per item. */
	const MAX_SOURCES = 6;

	const RESULTS = array( 'not_confirmed', 'corrected', 'confirmed' );

	/**
	 * The length choices offered in the popup, key => the words put into the
	 * prompt. The first is the default.
	 */
	public static function lengths() {
		return array(
			'two-thirds' => 'about two thirds of its current length',
			'half'       => 'about half of its current length',
			'same'       => 'about the same length (tighten the wording, keep every point)',
		);
	}

	/**
	 * A short description of the website for the prompt: its name, plus the
	 * "Site context" setting or, when that is empty, the tagline.
	 */
	public static function site_line() {
		$name    = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
		$context = class_exists( 'TWD_AP_Seo' ) ? trim( (string) TWD_AP_Seo::site_context() ) : '';
		$line    = '' !== $name ? '"' . $name . '"' : 'a small business website';
		if ( '' !== $context ) {
			$line .= ' (' . $context . ')';
		}
		return TWD_AP_Sanitizer::strip_dashes( $line );
	}

	/**
	 * The prompt, with four placeholders the popup fills: {SITE}, {LENGTH},
	 * {TITLE} and {HTML}. Generic on purpose (every kind of site uses it).
	 */
	public static function template() {
		return <<<PROMPT
You are helping the owner of the website {SITE} improve one of their published articles. Do two jobs, in this order.

JOB 1: CHECK THE FACTS WITH A WEB SEARCH.
Search the web to check every factual claim in the article below: dates and periods, names, places, materials, techniques, numbers, definitions, history, and anything about health, law, money or safety. Prefer authoritative sources (museums, universities, professional bodies, government sites, established reference works) over shops, blogs and forums. For each claim decide one result:
- "confirmed": reliable sources agree with the article.
- "corrected": reliable sources say something different. Say what they say.
- "not confirmed": you could not find a reliable source either way.
Never guess. If a claim is not confirmed, do not state it as fact in the rewrite: leave it out, or word it clearly as the author's own view or experience. Do not add new facts, figures, quotes or claims that you have not checked.

JOB 2: REWRITE THE ARTICLE MORE CONCISELY.
Aim for {LENGTH}. Keep the author's own voice, opinions and experience, British spelling, the subheadings that help a reader, and the links that still matter. Remove repetition, padding, and anything the check found to be wrong. Do not add new links.
Keep every picture and video exactly as it is: copy each img tag and each figure tag unchanged, with all of its attributes, and put it next to the text it belongs with.

RULES FOR THE HTML:
- No h1. Use h2 for headings and h3 for subheadings.
- Only these tags: p, h2, h3, h4, ul, ol, li, blockquote, strong, em, a href, hr, plus the img and figure tags already in the article.
- No inline styles, no class attributes (except on the figure tags you copy unchanged), no scripts.
- Never use an em dash or an en dash anywhere. Use a comma, a full stop, or rephrase.

HOW TO REPLY:
Reply with ONE code block that contains only JSON, nothing before or after it, in exactly this shape:
{
  "title": "the article title (keep it unless it is wrong or much too long)",
  "meta_description": "a summary for search results, 155 characters or fewer",
  "html": "the rewritten article as HTML, following the rules above",
  "fact_check": [
    { "claim": "the claim as the article made it", "result": "confirmed", "note": "what the sources say", "sources": ["https://..."] },
    { "claim": "...", "result": "corrected", "note": "what the sources say instead", "sources": ["https://..."] },
    { "claim": "...", "result": "not confirmed", "note": "what you looked for", "sources": [] }
  ],
  "changes": "two or three sentences on what you cut or changed and why"
}

THE ARTICLE:
Title: {TITLE}

{HTML}
PROMPT;
	}

	/**
	 * The whole prompt, the same way the popup builds it (publisher.js,
	 * buildRemixPrompt()). Used by the tests; keep the two in step.
	 */
	public static function prompt( $title, $html, $length = 'two-thirds', $site = '' ) {
		$lengths = self::lengths();
		$words   = isset( $lengths[ $length ] ) ? $lengths[ $length ] : reset( $lengths );
		return strtr(
			self::template(),
			array(
				'{SITE}'   => (string) $site,
				'{LENGTH}' => $words,
				'{TITLE}'  => (string) $title,
				'{HTML}'   => (string) $html,
			)
		);
	}

	/**
	 * Reads a pasted reply. Returns the fields for the popup, or a WP_Error
	 * with a plain message saying what to do.
	 *
	 * @param string $reply         What was pasted.
	 * @param string $original_html The article text in the editor before the remix.
	 */
	public static function check( $reply, $original_html ) {
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return self::error( 'twd_ap_remix_empty', __( 'Paste the reply from the AI assistant first.', 'twd-article-publisher' ) );
		}
		if ( strlen( $reply ) > self::MAX_REPLY_CHARS ) {
			return self::error( 'twd_ap_remix_too_long', __( 'That reply is far longer than an article. Paste only the code block with the JSON.', 'twd-article-publisher' ) );
		}

		$data = self::decode( $reply );
		if ( ! is_array( $data ) ) {
			return self::error( 'twd_ap_remix_not_json', __( 'That reply is not the JSON the prompt asks for, or it was cut off. Ask the assistant: "Please reply again with the JSON only, in one code block", then paste that.', 'twd-article-publisher' ) );
		}

		$html = isset( $data['html'] ) && is_string( $data['html'] ) ? TWD_AP_Sanitizer::clean( $data['html'] ) : '';
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return self::error( 'twd_ap_remix_no_html', __( 'The reply has no article text in it ("html" is missing or empty). Ask the assistant to reply again with the whole JSON.', 'twd-article-publisher' ) );
		}

		$original = TWD_AP_Sanitizer::clean( is_string( $original_html ) ? $original_html : '' );
		$notes    = array();

		// Pictures and videos: anything the original did not have is taken
		// out (an assistant can invent a picture address); anything it had and
		// the remix lost is named so the person can put it back.
		$before_media = self::media( $original );
		$kept         = self::drop_new_media( $html, $before_media );
		$html         = $kept['html'];
		if ( $kept['dropped'] > 0 ) {
			/* translators: %d: number of pictures or videos */
			$notes[] = sprintf( _n( '%d picture or video that was not in your article was left out.', '%d pictures or videos that were not in your article were left out.', $kept['dropped'], 'twd-article-publisher' ), $kept['dropped'] );
		}
		$after_media = self::media( $html );
		$missing     = array();
		foreach ( $before_media as $key => $item ) {
			if ( ! isset( $after_media[ $key ] ) ) {
				$missing[] = $item;
			}
		}

		$before_links = self::links( $original );
		$new_links    = array();
		foreach ( self::links( $html ) as $url ) {
			if ( ! in_array( $url, $before_links, true ) ) {
				$new_links[] = $url;
			}
		}

		$title = isset( $data['title'] ) && is_string( $data['title'] ) ? self::text( $data['title'], 200 ) : '';
		$meta  = isset( $data['meta_description'] ) && is_string( $data['meta_description'] ) ? self::text( $data['meta_description'], 300 ) : '';

		$changes = '';
		if ( isset( $data['changes'] ) && is_string( $data['changes'] ) ) {
			$changes = self::text( $data['changes'], 1500 );
		}

		$claims = self::clean_claims( isset( $data['fact_check'] ) ? $data['fact_check'] : null );

		return array(
			'title'            => $title,
			'meta_description' => $meta,
			'html'             => $html,
			'fact_check'       => $claims,
			'counts'           => array(
				'not_confirmed' => count( array_filter( $claims, function ( $c ) { return 'not_confirmed' === $c['result']; } ) ),
				'corrected'     => count( array_filter( $claims, function ( $c ) { return 'corrected' === $c['result']; } ) ),
				'confirmed'     => count( array_filter( $claims, function ( $c ) { return 'confirmed' === $c['result']; } ) ),
			),
			'changes'          => $changes,
			'words_before'     => self::word_count( $original ),
			'words_after'      => self::word_count( $html ),
			'missing_media'    => array_values( $missing ),
			'new_links'        => array_slice( $new_links, 0, 20 ),
			'notes'            => $notes,
		);
	}

	/**
	 * The JSON object in a reply: code fences and any words around it are
	 * ignored (an assistant often adds a line before or after).
	 */
	public static function decode( $reply ) {
		$text = trim( (string) $reply );
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$data = json_decode( $text, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		if ( preg_match( '/```(?:json|JSON)?\s*(\{.*\})\s*```/s', $text, $m ) ) {
			$data = json_decode( $m[1], true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return null;
	}

	/**
	 * The fact-check list, tidied: each item has claim, result (one of
	 * RESULTS), note and sources (http and https addresses only), sorted
	 * not confirmed first, then corrected, then confirmed. An item with no
	 * claim is dropped. An unknown result counts as not confirmed (the safe
	 * side: it asks the person to look).
	 */
	public static function clean_claims( $list ) {
		if ( ! is_array( $list ) ) {
			return array();
		}
		$out = array();
		foreach ( $list as $item ) {
			if ( count( $out ) >= self::MAX_CLAIMS ) {
				break;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$claim = isset( $item['claim'] ) && is_string( $item['claim'] ) ? self::text( $item['claim'], 600 ) : '';
			if ( '' === $claim ) {
				continue;
			}
			$sources = array();
			$raw     = isset( $item['sources'] ) ? $item['sources'] : array();
			if ( is_string( $raw ) ) {
				$raw = array( $raw );
			}
			if ( is_array( $raw ) ) {
				foreach ( $raw as $source ) {
					if ( count( $sources ) >= self::MAX_SOURCES ) {
						break;
					}
					$url   = is_string( $source ) ? $source : ( is_array( $source ) && isset( $source['url'] ) && is_string( $source['url'] ) ? $source['url'] : '' );
					$label = is_array( $source ) && isset( $source['title'] ) && is_string( $source['title'] ) ? self::text( $source['title'], 120 ) : '';
					$url   = self::clean_url( $url );
					if ( '' !== $url ) {
						$sources[] = array(
							'url'   => $url,
							'label' => '' !== $label ? $label : (string) parse_url( $url, PHP_URL_HOST ),
						);
					}
				}
			}
			$out[] = array(
				'claim'   => $claim,
				'result'  => self::result( isset( $item['result'] ) ? $item['result'] : '' ),
				'note'    => isset( $item['note'] ) && is_string( $item['note'] ) ? self::text( $item['note'], 600 ) : '',
				'sources' => $sources,
			);
		}
		// Stable sort by result order (usort is not stable before PHP 8).
		$sorted = array();
		foreach ( self::RESULTS as $result ) {
			foreach ( $out as $item ) {
				if ( $result === $item['result'] ) {
					$sorted[] = $item;
				}
			}
		}
		return $sorted;
	}

	/** One of RESULTS from whatever words the assistant used. */
	public static function result( $value ) {
		$v = is_string( $value ) ? strtolower( trim( preg_replace( '/[^a-z]+/i', ' ', $value ) ) ) : '';
		if ( in_array( $v, array( 'confirmed', 'verified', 'correct', 'true', 'accurate' ), true ) ) {
			return 'confirmed';
		}
		if ( in_array( $v, array( 'corrected', 'incorrect', 'wrong', 'false', 'fixed', 'inaccurate', 'correction' ), true ) ) {
			return 'corrected';
		}
		return 'not_confirmed';
	}

	/** An http or https address with a host, or ''. */
	public static function clean_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || strlen( $url ) > 500 || preg_match( '/[\s"\'<>`\\\\]/', $url ) ) {
			return '';
		}
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		$host   = (string) parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return '';
		}
		return $url;
	}

	/**
	 * The pictures and YouTube videos in some article HTML, keyed so two
	 * versions can be compared: 'img:' plus the picture address, 'yt:' plus
	 * the video id.
	 */
	public static function media( $html ) {
		$out = array();
		if ( preg_match_all( '/<img\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is', (string) $html, $m ) ) {
			foreach ( $m[2] as $src ) {
				$src = html_entity_decode( $src, ENT_QUOTES, 'UTF-8' );
				$out[ 'img:' . $src ] = array( 'type' => 'image', 'src' => $src );
			}
		}
		if ( preg_match_all( '/<figure\b[^>]*?\bdata-youtube-id\s*=\s*(["\'])(.*?)\1[^>]*>/is', (string) $html, $m ) ) {
			foreach ( $m[2] as $id ) {
				$out[ 'yt:' . $id ] = array( 'type' => 'video', 'src' => 'https://www.youtube.com/watch?v=' . rawurlencode( $id ) );
			}
		}
		return $out;
	}

	/** Takes out pictures and videos that are not in $allowed (a media() list). */
	public static function drop_new_media( $html, $allowed ) {
		$dropped = 0;
		$html    = preg_replace_callback(
			'/<figure\b[^>]*?\bdata-youtube-id\s*=\s*(["\'])(.*?)\1[^>]*>.*?<\/figure>/is',
			function ( $m ) use ( $allowed, &$dropped ) {
				if ( isset( $allowed[ 'yt:' . $m[2] ] ) ) {
					return $m[0];
				}
				$dropped++;
				return '';
			},
			(string) $html
		);
		$html = preg_replace_callback(
			'/<img\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is',
			function ( $m ) use ( $allowed, &$dropped ) {
				if ( isset( $allowed[ 'img:' . html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) ] ) ) {
					return $m[0];
				}
				$dropped++;
				return '';
			},
			$html
		);
		// An img with no src at all is never wanted.
		$html = preg_replace_callback(
			'/<img\b(?![^>]*\bsrc\s*=)[^>]*>/is',
			function () use ( &$dropped ) {
				$dropped++;
				return '';
			},
			$html
		);
		// A paragraph or figure left empty by the above goes too.
		$html = preg_replace( '/<(p|figure)>\s*(<figcaption>.*?<\/figcaption>\s*)?<\/\1>/is', '', $html );
		return array( 'html' => $html, 'dropped' => $dropped );
	}

	/** The link addresses in some article HTML, in order, once each. */
	public static function links( $html ) {
		$out = array();
		if ( preg_match_all( '/<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1/is', (string) $html, $m ) ) {
			foreach ( $m[2] as $href ) {
				$href = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
				if ( ! in_array( $href, $out, true ) ) {
					$out[] = $href;
				}
			}
		}
		return $out;
	}

	/** Words in some article HTML (letters and numbers, any language). */
	public static function word_count( $html ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'\x{2019}-]*/u', $text );
	}

	private static function text( $value, $max ) {
		$value = TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( $value ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}

	private static function error( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
