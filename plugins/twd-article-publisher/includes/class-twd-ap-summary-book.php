<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage and sanitising for the curated Summary Book -- a small, deliberately
 * separate thing from the swipe book (class-twd-ap-swipebook.php), which
 * stays a live view auto-split from the article every time it's opened. A
 * Summary Book is the opposite on purpose: typed cards (text/quote/question)
 * baked into Article Assist's JSON as a draft, reviewed and edited by a
 * therapist, and only ever shown publicly once explicitly published -- never
 * auto-published from AI output, and never silently tracks later article
 * edits the way the swipe book does.
 *
 * Draft and published are two separate post meta rows, not one row with a
 * status flag: publishing copies the draft into the published slot rather
 * than flipping a flag on it, so a later edited-and-saved draft never
 * accidentally goes live just because something else about the article was
 * saved, and a live Summary Book is never silently replaced by a half-edited
 * draft. "Generate Summary Book" / the review screen is the only path that
 * moves a draft to published.
 *
 * Public rendering (added once storage/editing shipped) reuses
 * TWD_AP_Swipebook's shared header fields and standalone-page shell, so a
 * Summary Book looks and behaves like a swipe book -- standalone page at
 * ?twd_ap_summary_book=1, in-page overlay fed by /articles/{id}/summary-book
 * -- just with published cards as its slides instead of a live split of the
 * article. Each book links to the other (companionUrl/companionLabel/
 * companionPostId/companionBookVariant in get_payload()), reusing the same
 * close-current/open-other mechanism the swipe book's own "More Articles"
 * slide already uses.
 */
class TWD_AP_Summary_Book {

	const META_DRAFT     = '_twd_ap_summary_book_draft';
	const META_PUBLISHED = '_twd_ap_summary_book_published';

	const MAX_CARDS = 20;
	const TYPES     = array( 'text', 'quote', 'question' );

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		// Priority 16: right after TWD_AP_Swipebook's own append_button() (15),
		// so when both buttons show they land together in a consistent order,
		// before TWD_AP_Article_Style::wrap_content() (20) wraps them.
		add_filter( 'the_content', array( $this, 'append_button' ), 16 );
	}

	public function register_query_var( $vars ) {
		$vars[] = 'twd_ap_summary_book';
		return $vars;
	}

	public static function url_for( $post ) {
		return add_query_arg( 'twd_ap_summary_book', '1', get_permalink( $post ) );
	}

	public function maybe_render() {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		if ( '1' !== (string) get_query_var( 'twd_ap_summary_book' ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! self::is_published( $post->ID ) || ! TWD_AP_Swipebook::is_enabled( $post->ID ) ) {
			return;
		}

		TWD_AP_Swipebook::render_standalone_page( $post, $this->get_payload( $post ) );
		exit;
	}

	/**
	 * A "View Summary Book" pill below the article's content, only shown
	 * once a Summary Book has actually been published -- sits alongside
	 * TWD_AP_Swipebook's own "View as a swipe book" pill (its append_button()
	 * runs at priority 15; this one at 16 lands right after it).
	 */
	public function append_button( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( '1' === (string) get_query_var( 'twd_ap_summary_book' ) || '1' === (string) get_query_var( 'twd_ap_swipebook' ) ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || 'publish' !== $post->post_status || ! self::is_published( $post->ID ) || ! TWD_AP_Swipebook::is_enabled( $post->ID ) ) {
			return $content;
		}

		$url    = esc_url( self::url_for( $post ) );
		$button = '<p class="twd-ap-swipebook-row twd-ap-summary-book-row"><a class="twd-ap-swipebook-btn twd-ap-summary-book-btn" href="' . $url . '" data-twd-ap-post-id="' . (int) $post->ID . '" data-twd-ap-book-variant="summary-book">'
			. esc_html__( 'View Summary Book', 'twd-article-publisher' ) . ' &#8599;</a></p>';

		return $content . $button;
	}

	/**
	 * Same payload shape TWD_AP_Swipebook::get_payload() builds, so
	 * TWD_AP_Swipebook::render_standalone_page() and swipebook.js's
	 * buildMarkup() both work on it unmodified. The header label is always
	 * "Summary Book" (hardcoded, not a setting -- unlike the swipe book's own
	 * label this one carries no site-level branding). The companion* fields
	 * point back to the swipe book, mirroring the forward link
	 * TWD_AP_Swipebook::get_payload() adds toward this one when published.
	 */
	public function get_payload( $post ) {
		$title  = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$header = TWD_AP_Swipebook::get_header_fields( __( 'Summary Book', 'twd-article-publisher' ) );

		return array_merge(
			$header,
			array(
				'title'      => $title,
				'articleUrl' => get_permalink( $post ),
				'shareUrl'   => self::url_for( $post ),
				'slides'     => $this->build_slides( $post ),

				'companionUrl'         => TWD_AP_Swipebook::url_for( $post ),
				'companionLabel'       => __( 'Back to swipe book', 'twd-article-publisher' ),
				'companionPostId'      => $post->ID,
				'companionBookVariant' => 'swipebook',
			)
		);
	}

	/**
	 * Maps published cards onto the slide shape swipebook.js's buildMarkup()
	 * understands (type/heading/html, plus attribution for a quote card).
	 * Card text is already sanitised on save, so wrapping it for display is
	 * the only HTML-building this needs.
	 */
	private function build_slides( $post ) {
		$slides = array();
		foreach ( self::get_published( $post->ID ) as $card ) {
			$slide = array(
				'type' => $card['type'],
				'html' => '<p>' . nl2br( esc_html( $card['text'] ) ) . '</p>',
			);
			if ( ! empty( $card['heading'] ) ) {
				$slide['heading'] = $card['heading'];
			}
			if ( 'quote' === $card['type'] && ! empty( $card['attribution'] ) ) {
				$slide['attribution'] = $card['attribution'];
			}
			$slides[] = $slide;
		}
		return $slides;
	}

	/**
	 * Cleans an array of cards from any source (JSON paste, the review
	 * screen's own save) down to a safe, consistent shape. Anything not
	 * shaped like a card, or beyond MAX_CARDS, is silently dropped rather
	 * than erroring -- a therapist editing the review screen shouldn't be
	 * able to break the save by a stray field, and Article Assist's JSON
	 * is treated as untrusted input like everything else pasted into this
	 * popup.
	 */
	public static function sanitize_cards( $cards ) {
		if ( ! is_array( $cards ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_slice( $cards, 0, self::MAX_CARDS ) as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}

			$type = isset( $card['type'] ) ? sanitize_key( $card['type'] ) : 'text';
			if ( ! in_array( $type, self::TYPES, true ) ) {
				$type = 'text';
			}

			$text = isset( $card['text'] ) ? TWD_AP_Sanitizer::strip_dashes( sanitize_textarea_field( (string) $card['text'] ) ) : '';
			if ( '' === trim( $text ) ) {
				// A card with nothing to actually show isn't worth keeping --
				// same reasoning as the swipe book never showing an empty
				// "more articles" slide rather than a blank one.
				continue;
			}

			$entry = array(
				'type' => $type,
				'text' => $text,
			);

			if ( isset( $card['heading'] ) ) {
				$heading = TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( (string) $card['heading'] ) );
				if ( '' !== $heading ) {
					$entry['heading'] = $heading;
				}
			}
			if ( 'quote' === $type && isset( $card['attribution'] ) ) {
				$attribution = TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( (string) $card['attribution'] ) );
				if ( '' !== $attribution ) {
					$entry['attribution'] = $attribution;
				}
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	public static function get_draft( $post_id ) {
		$cards = get_post_meta( (int) $post_id, self::META_DRAFT, true );
		return is_array( $cards ) ? $cards : array();
	}

	public static function save_draft( $post_id, $cards ) {
		$clean = self::sanitize_cards( $cards );
		if ( empty( $clean ) ) {
			delete_post_meta( (int) $post_id, self::META_DRAFT );
		} else {
			update_post_meta( (int) $post_id, self::META_DRAFT, $clean );
		}
		return $clean;
	}

	public static function get_published( $post_id ) {
		$cards = get_post_meta( (int) $post_id, self::META_PUBLISHED, true );
		return is_array( $cards ) ? $cards : array();
	}

	public static function is_published( $post_id ) {
		return ! empty( self::get_published( $post_id ) );
	}

	/**
	 * Publishing always re-sanitises and re-saves the given cards as the
	 * draft too (not just the published copy), so the review screen's own
	 * "save my edits, then publish" is one call, and the draft never lags
	 * behind what a therapist just approved.
	 */
	public static function publish( $post_id, $cards ) {
		$clean = self::save_draft( $post_id, $cards );
		if ( empty( $clean ) ) {
			return new WP_Error( 'twd_ap_summary_book_empty', __( 'Add at least one card before publishing.', 'twd-article-publisher' ) );
		}
		update_post_meta( (int) $post_id, self::META_PUBLISHED, $clean );
		return $clean;
	}

	public static function unpublish( $post_id ) {
		delete_post_meta( (int) $post_id, self::META_PUBLISHED );
	}
}
