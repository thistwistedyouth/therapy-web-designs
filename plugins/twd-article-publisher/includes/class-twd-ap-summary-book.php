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
 */
class TWD_AP_Summary_Book {

	const META_DRAFT     = '_twd_ap_summary_book_draft';
	const META_PUBLISHED = '_twd_ap_summary_book_published';

	const MAX_CARDS = 20;
	const TYPES     = array( 'text', 'quote', 'question' );

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
