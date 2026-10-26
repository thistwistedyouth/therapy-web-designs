<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The short intro printed above a category (or tag) page's grid: the term's
 * own Description from Posts > Categories. People who can edit that term get
 * a small "Edit intro" button on the page that opens a popup and saves the
 * description without going into wp-admin.
 */
class TWD_AP_Intro {

	const TAXONOMIES = array( 'category', 'post_tag' );

	/**
	 * The description as safe HTML (paragraphs added, only post-content tags
	 * allowed), or '' when there is none.
	 */
	public static function description_html( $term ) {
		$raw = trim( (string) $term->description );
		if ( '' === $raw ) {
			return '';
		}
		return trim( wpautop( wp_kses_post( $raw ) ) );
	}

	/**
	 * Can this person edit this term's description from the page?
	 */
	public static function can_edit( $term ) {
		return TWD_AP_Frontend::current_user_allowed() && current_user_can( 'edit_term', $term->term_id );
	}

	/**
	 * The intro block for a category or tag archive, or '' when there is
	 * nothing to show (no description, and the viewer cannot add one).
	 */
	public static function html( $term ) {
		if ( ! $term || ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, self::TAXONOMIES, true ) ) {
			return '';
		}
		$text     = self::description_html( $term );
		$editable = self::can_edit( $term );
		if ( '' === $text && ! $editable ) {
			return '';
		}
		$edit = '';
		if ( $editable ) {
			$edit = '<button type="button" class="twd-ap-intro-edit" data-term-id="' . (int) $term->term_id . '" data-taxonomy="' . esc_attr( $term->taxonomy ) . '" data-name="' . esc_attr( $term->name ) . '" data-raw="' . esc_attr( (string) $term->description ) . '">' .
				'<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> ' .
				esc_html( '' === $text ? __( 'Add an intro', 'twd-article-publisher' ) : __( 'Edit intro', 'twd-article-publisher' ) ) .
			'</button>';
		}
		return '<div class="twd-ap-articles-intro"><div class="twd-ap-articles-intro-text">' . $text . '</div>' . $edit . '</div>';
	}

	/**
	 * Saves a new description. Returns array( 'html' => rendered text ) or a
	 * WP_Error. Only categories and tags, only by someone who may edit that term.
	 */
	public static function save( $term_id, $taxonomy, $description ) {
		$term_id = absint( $term_id );
		if ( ! in_array( $taxonomy, self::TAXONOMIES, true ) || ! $term_id || ! get_term( $term_id, $taxonomy ) || is_wp_error( get_term( $term_id, $taxonomy ) ) ) {
			return new WP_Error( 'twd_ap_bad_term', __( 'That category could not be found.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return new WP_Error( 'twd_ap_forbidden', __( 'You do not have permission to edit this category.', 'twd-article-publisher' ), array( 'status' => 403 ) );
		}
		$clean   = trim( wp_kses_post( (string) $description ) );
		$updated = wp_update_term( $term_id, $taxonomy, array( 'description' => $clean ) );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		$term = get_term( $term_id, $taxonomy );
		return array(
			'html' => self::description_html( $term ),
			'raw'  => (string) $term->description,
		);
	}
}
