<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates an article directly on this site, using the client's own
 * Anthropic API key (Settings > Article Publisher), instead of the
 * copy/paste round trip through Article Assist on Therapy Resource
 * Directory. Deliberately mirrors that tool's own prompt and JSON shape
 * (trd_aa_shared_rules() and friends, in therapy-resource-directory's own
 * "15 TRD Article Assist.php") so the two stay interchangeable from the
 * popup's point of view -- fillFromJson()/applyGeneratedData() don't know
 * or care which one produced the data they're filling fields from. Keep
 * this prompt in sync with that file if either one changes; there is no
 * shared code between the two repos to do that automatically.
 *
 * No instance(); a pure static helper, same shape as TWD_AP_Sanitizer.
 * The one exception is init(), which registers the two filters this
 * plugin offers to other plugins (twd_ai_is_configured, twd_ai_complete).
 * They let TWD Site Kit use this site's saved key without ever seeing it.
 */
class TWD_AP_AI_Generate {

	const MODEL      = 'claude-sonnet-5-5';
	const MAX_TOKENS = 5000;

	// Bounds for the twd_ai_complete filter only.
	const COMPLETE_MIN_TOKENS = 256;
	const COMPLETE_MAX_TOKENS = 8000;

	// 'improve' and 'format' hand back a whole existing article (plus the
	// SEO fields and summary book draft), so they get a larger reply
	// allowance than 'idea', which writes 600 to 900 words.
	const LONG_MODE_TOKENS = 8000;

	// The most text accepted in the start screen box. Longer is refused
	// with a clear message, never silently cut.
	const MAX_INPUT_CHARS = 20000;

	// A safety backstop, not a real constraint -- this runs on the client's
	// own key and their own bill, so there is no cost reason to limit it
	// the way Article Assist limits shared TRD usage. Only here in case a
	// bug or a bad actor with edit access loops the endpoint.
	const RATE_LIMIT = 30;

	/**
	 * Registers the filters offered to other plugins. Server-side only:
	 * no REST route, no JavaScript. The key is read inside this class and
	 * is never passed to, or returned from, either filter.
	 */
	public static function init() {
		add_filter( 'twd_ai_is_configured', array( __CLASS__, 'filter_is_configured' ), 10, 1 );
		add_filter( 'twd_ai_complete', array( __CLASS__, 'filter_complete' ), 10, 4 );
	}

	/**
	 * apply_filters( 'twd_ai_is_configured', false ): true only when a key
	 * is saved, otherwise the value it was given.
	 */
	public static function filter_is_configured( $value ) {
		return self::is_configured() ? true : $value;
	}

	/**
	 * apply_filters( 'twd_ai_complete', null, $system, $message, $max_tokens ):
	 * returns null when no key is saved (nothing answered), otherwise the
	 * reply text or a WP_Error from complete().
	 */
	public static function filter_complete( $result, $system = '', $message = '', $max_tokens = self::MAX_TOKENS ) {
		if ( null !== $result || ! self::is_configured() ) {
			return $result;
		}
		return self::complete( $system, $message, $max_tokens );
	}

	/**
	 * One plain system + user message round trip using this site's own
	 * key. Reuses call_anthropic() unchanged apart from max_tokens, so the
	 * endpoint, model, headers, timeout and error messages are identical to
	 * generate(). Not counted against the daily article limit: that count
	 * lives in the REST layer, and callers (TWD Site Kit) have their own
	 * rate limit and permission checks.
	 *
	 * Returns the reply text, a WP_Error, or null when no key is saved.
	 */
	public static function complete( $system, $message, $max_tokens = self::MAX_TOKENS ) {
		$key = self::get_key();
		if ( '' === $key ) {
			return null;
		}

		if ( ! is_string( $system ) || ! is_string( $message ) || '' === trim( $message ) ) {
			return new WP_Error( 'twd_ap_ai_bad_request', __( 'The AI request was empty or in the wrong form.', 'twd-article-publisher' ) );
		}

		$reply = self::call_anthropic( $key, $system, $message, self::clamp_max_tokens( $max_tokens ) );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		return $reply;
	}

	/**
	 * Below 256 becomes 256, above 8000 becomes 8000, non-numeric becomes
	 * the default of 5000.
	 */
	public static function clamp_max_tokens( $value ) {
		if ( ! is_numeric( $value ) ) {
			return self::MAX_TOKENS;
		}
		return (int) max( self::COMPLETE_MIN_TOKENS, min( self::COMPLETE_MAX_TOKENS, (int) $value ) );
	}

	public static function is_configured() {
		return '' !== self::get_key();
	}

	private static function get_key() {
		$settings = TWD_AP_Settings::get_settings();
		return trim( (string) ( $settings['anthropic_api_key'] ?? '' ) );
	}

	private static function rate_key() {
		return 'twd_ap_ai_' . get_current_user_id() . '_' . gmdate( 'Y-m-d' );
	}

	public static function count() {
		return (int) get_transient( self::rate_key() );
	}

	public static function remaining() {
		return max( 0, self::RATE_LIMIT - self::count() );
	}

	public static function increment() {
		$key = self::rate_key();
		$n   = (int) get_transient( $key );
		set_transient( $key, $n + 1, DAY_IN_SECONDS );
	}

	private static function category_names() {
		$cats = get_categories( array( 'hide_empty' => false ) );
		$out  = array();
		foreach ( $cats as $cat ) {
			$out[] = $cat->name;
		}
		if ( empty( $out ) ) {
			$out[] = 'Uncategorized';
		}
		return $out;
	}

	private static function shared_rules( $category_names ) {
		$category_list = implode( ', ', $category_names );
		return <<<PROMPT
Confidentiality is not optional, in either mode. If anything reads like a
specific identifiable real person, a real name, a very specific unique
situation, anything that narrows down to one individual, generalise it
into a composite. Never invent identifying specifics of your own either.

Rules for the HTML body:
- Do not include an h1 tag. Start with h2 for the first heading, h3 for
  subheadings.
- Only use these tags: p, h2, h3, h4, ul, ol, li, blockquote, strong, em,
  a href, hr. No other tags, no inline styles, no class attributes.
- Do not include img tags. The therapist adds their own images afterward.
- Never use an em dash or an en dash anywhere in the output. Use a comma,
  a full stop, or rephrase instead.

Also suggest a category: pick the single closest match from this list of
the site's existing categories, exactly as spelled: {$category_list}.

Also draft a "summary book": 5 to 8 short cards distilling the article
into a quick, swipeable read, for someone who wants the gist before (or
instead of) reading the full piece. This is a draft only, reviewed and
edited by the therapist before anything is published from it, so favour
clarity over cleverness. Each card is one of three types:
- "text": one key point in 1 to 3 short sentences, plain and concrete.
- "quote": one resonant line lifted or lightly tightened from the
  article's own body text, never invented, never attributed to a real
  person. Omit "attribution" entirely rather than inventing one.
- "question": one short reflective question addressed directly to the
  reader, inviting them to pause and think, not answered in the card
  itself.
Open with a "text" card that states the article's core idea in one line,
close with a "question" card, and use at least one "quote" card somewhere
in between. Every card needs "type" and "text"; a "text" or "quote" card
may also carry a short "heading" (a few words, optional). Never use an em
dash or an en dash in any card, same rule as the HTML body above.

Reply with strict JSON only, no code fences, no commentary outside the
JSON, in exactly this shape:
{
  "title": "the article title, not in the HTML, a plain string",
  "seo_title": "an SEO title, 60 characters or fewer",
  "meta_description": "a meta description, 155 characters or fewer",
  "category": "one of the listed category names",
  "tags": ["3 to 5 short tags"],
  "html": "the article body as HTML, following the rules above",
  "summary_book": {
    "slides": [
      { "type": "text", "text": "...", "heading": "optional" },
      { "type": "quote", "text": "..." },
      { "type": "question", "text": "..." }
    ]
  }
}
PROMPT;
	}

	private static function system_prompt_idea( $category_names ) {
		return <<<PROMPT
You help a therapist write an article for their own website, starting
from a topic or idea they give you, not from session notes or client
material. Write a warm, plain English article on the idea given, in
short paragraphs (two to four sentences), with a subheading every two to
three paragraphs. If a scenario would help illustrate the idea, invent a
clearly composite one, never presented as a real client. End with one
small, doable next step the reader can try, not a hard sell for booking
a session. Length: 600 to 900 words.

PROMPT . self::shared_rules( $category_names );
	}

	/**
	 * Draft article, improve. Has no counterpart in Article Assist (which
	 * only writes from an idea or formats), so it is the one prompt in this
	 * file that is not kept in step with that tool.
	 */
	private static function system_prompt_improve( $category_names ) {
		return <<<PROMPT
You are given a draft article a therapist has written themselves, in
their own words and voice. Improve it without changing what they mean or
who they sound like. Tighten and clarify sentences, fix grammar,
spelling and punctuation, smooth the flow between paragraphs, remove
repetition, and add clear subheadings and short paragraphs where the
structure needs them. Keep their meaning, their examples, their warmth
and their own spelling conventions. Do not add facts, claims,
statistics, examples, advice or anecdotes of your own, and do not make
the piece longer than it needs to be. If the draft is only rough notes,
turn them into a clear article using only what the notes say. If a
title isn't obviously present in the text, suggest one that reflects
what is actually there.

PROMPT . self::shared_rules( $category_names );
	}

	private static function system_prompt_format( $category_names ) {
		return <<<PROMPT
You are given an article a therapist has already written themselves, in
their own words. Do not rewrite their content or their voice, and do not
add material of your own. Restructure it into clean HTML using only the
allowed tags below, correcting only what is needed for structure
(headings, paragraph breaks, lists), and otherwise preserve their
wording as closely as possible. If a title isn't obviously present in
the text, suggest one that reflects what's actually there.

PROMPT . self::shared_rules( $category_names );
	}

	/**
	 * $mode is 'idea', 'improve' or 'format' (anything else is treated as
	 * 'idea'), $input is the therapist's own text in every case. Returns the same array shape the REST layer hands
	 * straight back to the popup (title/seo_title/meta_description/
	 * category/tags/html/summary_book), already sanitised through
	 * TWD_AP_Sanitizer and TWD_AP_Summary_Book::sanitize_cards() -- or a
	 * WP_Error fit to show the editor popup directly.
	 */
	public static function generate( $mode, $input ) {
		$key = self::get_key();
		if ( '' === $key ) {
			return new WP_Error( 'twd_ap_ai_not_configured', __( 'No API key is set up for this site yet.', 'twd-article-publisher' ) );
		}

		$categories = self::category_names();
		$tokens     = self::LONG_MODE_TOKENS;
		if ( 'format' === $mode ) {
			$system  = self::system_prompt_format( $categories );
			$message = "Here is the article text to restructure and tag:\n\n" . $input;
		} elseif ( 'improve' === $mode ) {
			$system  = self::system_prompt_improve( $categories );
			$message = "Here is the draft article to improve and tag:\n\n" . $input;
		} else {
			$system  = self::system_prompt_idea( $categories );
			$message = "The idea or topic to write about:\n\n" . $input;
			$tokens  = self::MAX_TOKENS;
		}

		$reply = self::call_anthropic( $key, $system, $message, $tokens );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		$data = self::decode_json( $reply );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$title = isset( $data['title'] ) ? trim( TWD_AP_Sanitizer::strip_dashes( (string) $data['title'] ) ) : '';
		$html  = isset( $data['html'] ) ? TWD_AP_Sanitizer::clean( (string) $data['html'] ) : '';
		if ( '' === $title || '' === $html ) {
			return new WP_Error( 'twd_ap_ai_bad_result', __( 'The generator did not return a usable result. Please try again.', 'twd-article-publisher' ) );
		}

		$tags = array();
		if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			foreach ( $data['tags'] as $t ) {
				$t = trim( TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( (string) $t ) ) );
				if ( '' !== $t ) {
					$tags[] = $t;
				}
			}
		}

		$category = isset( $data['category'] ) ? trim( sanitize_text_field( (string) $data['category'] ) ) : '';
		if ( ! in_array( $category, $categories, true ) ) {
			$category = $categories[0];
		}

		$summary_book_slides = class_exists( 'TWD_AP_Summary_Book' )
			? TWD_AP_Summary_Book::sanitize_cards( isset( $data['summary_book']['slides'] ) ? $data['summary_book']['slides'] : array() )
			: array();

		return array(
			'title'            => $title,
			'seo_title'        => isset( $data['seo_title'] ) ? trim( TWD_AP_Sanitizer::strip_dashes( (string) $data['seo_title'] ) ) : '',
			'meta_description' => isset( $data['meta_description'] ) ? trim( TWD_AP_Sanitizer::strip_dashes( (string) $data['meta_description'] ) ) : '',
			'category'         => $category,
			'tags'             => $tags,
			'html'             => $html,
			'summary_book'     => array( 'slides' => $summary_book_slides ),
		);
	}

	private static function call_anthropic( $key, $system, $message, $max_tokens = self::MAX_TOKENS ) {
		$body = array(
			'model'         => self::MODEL,
			'max_tokens'    => (int) $max_tokens,
			'system'        => $system,
			'fallbacks'     => 'default',
			'messages'      => array( array( 'role' => 'user', 'content' => $message ) ),
			'output_config' => array( 'effort' => 'medium' ),
		);

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 150 );
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
					'anthropic-beta'    => 'server-side-fallback-2026-07-01',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'twd_ap_ai_unreachable', __( 'Could not reach the AI service: ', 'twd-article-publisher' ) . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'twd_ap_ai_bad_key', __( 'The API key on this site was rejected. Check it under Settings > Article Publisher.', 'twd-article-publisher' ) );
		}
		if ( 200 !== $code || ! is_array( $data ) ) {
			$why = ( is_array( $data ) && ! empty( $data['error']['message'] ) ) ? $data['error']['message'] : 'no detail given';
			return new WP_Error(
				'twd_ap_ai_http',
				sprintf(
					/* translators: 1: HTTP status code, 2: error detail from the AI service */
					__( 'The AI service returned an error (%1$d, %2$s).', 'twd-article-publisher' ),
					$code,
					$why
				)
			);
		}

		$stop = (string) ( $data['stop_reason'] ?? '' );
		if ( 'refusal' === $stop ) {
			return new WP_Error( 'twd_ap_ai_refused', __( 'The AI declined this request. Try rephrasing the idea.', 'twd-article-publisher' ) );
		}
		if ( 'max_tokens' === $stop ) {
			return new WP_Error( 'twd_ap_ai_cut_off', __( 'The AI ran out of room before finishing. Try a shorter idea.', 'twd-article-publisher' ) );
		}

		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) && isset( $block['text'] ) ) {
				$text .= (string) $block['text'];
			}
		}
		$text = trim( $text );
		if ( '' === $text ) {
			return new WP_Error( 'twd_ap_ai_empty', __( 'The AI sent back nothing usable. Please try again.', 'twd-article-publisher' ) );
		}

		return $text;
	}

	private static function decode_json( $reply ) {
		$raw  = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/', '', trim( $reply ) ) );
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : new WP_Error( 'twd_ap_ai_bad_json', __( 'The AI replied, but not in the form expected. Please try again.', 'twd-article-publisher' ) );
	}
}
