<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a Book Maker style JSON (a title and a list of typed "cards": insight,
 * filing, quote, question, image, with an optional "part" on a card that opens
 * a chapter) into what the New Article popup already understands: an article
 * (title, short description and readable text) and a Summary Book draft.
 *
 * Nothing here saves or publishes anything. The popup puts the result in its
 * fields like any other pasted JSON; the article goes through
 * TWD_AP_Sanitizer on save, and the cards ride along as the Summary Book
 * DRAFT, which stays private until the owner reviews and publishes it (the
 * Summary Book's own rule). The JSON is treated as untrusted: every card goes
 * through TWD_AP_Summary_Book::sanitize_cards() and every piece of text is
 * escaped when the article text is built.
 *
 * Left out on purpose, and said so in the notes: the print edition's front and
 * back pages (`print_front_matter`, `print_back_matter`), the cover colours,
 * the series name and the author id. Picture cards still holding a placeholder
 * address (REPLACE_WITH_MEDIA_URL) are left out too, since there is no picture
 * to show yet.
 */
class TWD_AP_Book_Import {

	/** The most JSON accepted, once decoded and encoded again. */
	const MAX_BYTES = 1500000;

	/** Book Maker's card kinds, and the Summary Book card each becomes. */
	const KINDS = array(
		'insight'  => 'text',
		'filing'   => 'text',
		'text'     => 'text',
		'part'     => 'text',
		'quote'    => 'quote',
		'question' => 'question',
		'image'    => 'image',
	);

	/** True for a decoded JSON that looks like a Book Maker book rather than an article. */
	public static function is_book( $data ) {
		return is_array( $data ) && isset( $data['cards'] ) && is_array( $data['cards'] ) && ( ! isset( $data['html'] ) || '' === $data['html'] );
	}

	/**
	 * @return array|WP_Error title, seo_title, meta_description, category, tags,
	 *                        html, summary_book { slides }, notes (list of plain
	 *                        sentences for the owner).
	 */
	public static function convert( $data ) {
		if ( ! self::is_book( $data ) ) {
			return new WP_Error( 'twd_ap_book_not_book', __( 'That JSON has no list of cards, so it does not look like a book.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}
		if ( strlen( (string) wp_json_encode( $data ) ) > self::MAX_BYTES ) {
			return new WP_Error( 'twd_ap_book_too_big', __( 'That book is too big to read in one go.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}

		$mapped        = array();
		$unknown_kinds = array();
		$dropped_pics  = 0;
		$over_cap      = 0;
		$cap           = TWD_AP_Summary_Book::MAX_CARDS;
		$intro_text    = '';

		foreach ( $data['cards'] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			if ( count( $mapped ) >= $cap ) {
				$over_cap++;
				continue;
			}

			$kind = ( isset( $raw['type'] ) && is_string( $raw['type'] ) ) ? strtolower( trim( $raw['type'] ) ) : 'insight';
			if ( isset( self::KINDS[ $kind ] ) ) {
				$type = self::KINDS[ $kind ];
			} else {
				$type = 'text';
				$k    = substr( preg_replace( '/[^a-z0-9_-]/', '', $kind ), 0, 24 );
				if ( '' !== $k && ! in_array( $k, $unknown_kinds, true ) ) {
					$unknown_kinds[] = $k;
				}
			}

			$text = ( isset( $raw['text'] ) && is_string( $raw['text'] ) ) ? str_replace( array( "\r\n", "\r" ), "\n", $raw['text'] ) : '';
			$card = array( 'type' => $type, 'text' => $text );

			if ( isset( $raw['part'] ) && is_string( $raw['part'] ) && '' !== trim( $raw['part'] ) ) {
				$card['heading'] = $raw['part'];
			}
			if ( 'quote' === $type && isset( $raw['attribution'] ) && is_string( $raw['attribution'] ) ) {
				$card['attribution'] = $raw['attribution'];
			}
			if ( 'image' === $type ) {
				$src = TWD_AP_Summary_Book::clean_image_src( isset( $raw['src'] ) ? $raw['src'] : '' );
				if ( '' === $src ) {
					$dropped_pics++;
					continue;
				}
				$card['src']  = $src;
				$card['text'] = ( isset( $raw['caption'] ) && is_string( $raw['caption'] ) ) ? $raw['caption'] : '';
			}

			if ( '' === $intro_text && 'insight' === $kind && '' !== trim( $text ) ) {
				$intro_text = $text;
			}
			$mapped[] = $card;
		}

		$cards = TWD_AP_Summary_Book::sanitize_cards( $mapped );
		if ( empty( $cards ) ) {
			return new WP_Error( 'twd_ap_book_empty', __( 'No cards with any text in them were found in that JSON.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}

		$title = '';
		if ( isset( $data['title'] ) && is_string( $data['title'] ) ) {
			$title = TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( $data['title'] ) );
		}

		$notes = array();
		/* translators: %d: number of cards */
		$notes[] = sprintf( _n( '%d card was read.', '%d cards were read.', count( $cards ), 'twd-article-publisher' ), count( $cards ) );

		$left_out = array();
		if ( ! empty( $data['print_front_matter'] ) || ! empty( $data['print_back_matter'] ) ) {
			$left_out[] = __( 'the print edition front and back pages', 'twd-article-publisher' );
		}
		if ( ! empty( $data['cover'] ) ) {
			$left_out[] = __( 'the cover colours', 'twd-article-publisher' );
		}
		if ( ! empty( $data['series'] ) ) {
			$left_out[] = __( 'the series name', 'twd-article-publisher' );
		}
		if ( ! empty( $data['author_id'] ) ) {
			$left_out[] = __( 'the author', 'twd-article-publisher' );
		}
		if ( $left_out ) {
			/* translators: %s: a list such as "the cover colours and the series name" */
			$notes[] = sprintf( __( 'Not used: %s.', 'twd-article-publisher' ), implode( ', ', $left_out ) );
		}
		if ( $dropped_pics > 0 ) {
			$notes[] = sprintf(
				/* translators: %d: number of picture cards */
				_n( '%d picture card was left out because it has no web address yet. Upload the picture to this site, then add it back in the Summary Book screen.', '%d picture cards were left out because they have no web address yet. Upload the pictures to this site, then add them back in the Summary Book screen.', $dropped_pics, 'twd-article-publisher' ),
				$dropped_pics
			);
		}
		if ( $over_cap > 0 ) {
			/* translators: 1: the card limit, 2: how many were left out */
			$notes[] = sprintf( __( 'Only the first %1$d cards were used; %2$d more were left out.', 'twd-article-publisher' ), $cap, $over_cap );
		}
		if ( $unknown_kinds ) {
			/* translators: %s: a list of card kinds */
			$notes[] = sprintf( __( 'These kinds of card were not recognised and were treated as plain text: %s.', 'twd-article-publisher' ), implode( ', ', $unknown_kinds ) );
		}
		$empty = count( $mapped ) - count( $cards );
		if ( $empty > 0 ) {
			/* translators: %d: number of cards */
			$notes[] = sprintf( _n( '%d card with no text was left out.', '%d cards with no text were left out.', $empty, 'twd-article-publisher' ), $empty );
		}

		return array(
			'title'            => $title,
			'seo_title'        => '',
			'meta_description' => self::description( $intro_text ),
			'category'         => '',
			'tags'             => array(),
			'html'             => TWD_AP_Sanitizer::clean( self::article_html( $cards ) ),
			'summary_book'     => array( 'slides' => $cards ),
			'notes'            => $notes,
		);
	}

	/** A short description (about 155 characters, cut at a word) from the first insight card. */
	private static function description( $text ) {
		$flat = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( TWD_AP_Sanitizer::strip_dashes( (string) $text ) ) ) );
		if ( '' === $flat ) {
			return '';
		}
		if ( self::length( $flat ) <= 155 ) {
			return $flat;
		}
		$cut  = function_exists( 'mb_substr' ) ? mb_substr( $flat, 0, 152 ) : substr( $flat, 0, 152 );
		$last = strrpos( $cut, ' ' );
		if ( false !== $last && $last > 80 ) {
			$cut = substr( $cut, 0, $last );
		}
		return rtrim( $cut, " ,;:.-" ) . '...';
	}

	private static function length( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	}

	/**
	 * The cards as readable article text, in order: a chapter heading as an
	 * h2, text as paragraphs, a quote as a block quote with its attribution
	 * under it, a question in italics, a picture with its caption. Built from
	 * the already-cleaned cards and escaped here as well.
	 */
	private static function article_html( $cards ) {
		$html = '';
		foreach ( $cards as $card ) {
			$text = isset( $card['text'] ) ? trim( $card['text'] ) : '';
			if ( ! empty( $card['heading'] ) ) {
				$html .= '<h2>' . esc_html( $card['heading'] ) . '</h2>';
				// "Chapter One - The Two Ways of With" over a card that only repeats
				// "The Two Ways of With": the heading already says it.
				$after = preg_replace( '/^.*?\s-\s/u', '', $card['heading'], 1 );
				if ( 'text' === $card['type'] && is_string( $after ) && '' !== $text && 0 === strcasecmp( $text, trim( $after ) ) ) {
					continue;
				}
			}
			switch ( $card['type'] ) {
				case 'quote':
					$html .= '<blockquote>' . self::paragraphs( $text, false ) . '</blockquote>';
					if ( ! empty( $card['attribution'] ) ) {
						$html .= '<p><em>' . esc_html( $card['attribution'] ) . '</em></p>';
					}
					break;
				case 'question':
					$html .= self::paragraphs( $text, true );
					break;
				case 'image':
					$html .= '<p><img src="' . esc_url( $card['src'] ) . '" alt="' . esc_attr( $text ) . '" /></p>';
					if ( '' !== $text ) {
						$html .= '<p><em>' . esc_html( $text ) . '</em></p>';
					}
					break;
				default:
					$html .= self::paragraphs( $text, false );
			}
		}
		return $html;
	}

	/** Blank lines make paragraphs; a single line break stays a line break. */
	private static function paragraphs( $text, $italic ) {
		$out = '';
		foreach ( preg_split( '/\n\s*\n/', trim( (string) $text ) ) as $para ) {
			$para = trim( $para );
			if ( '' === $para ) {
				continue;
			}
			$inner = nl2br( esc_html( $para ), false );
			$out  .= '<p>' . ( $italic ? '<em>' . $inner . '</em>' : $inner ) . '</p>';
		}
		return $out;
	}
}
