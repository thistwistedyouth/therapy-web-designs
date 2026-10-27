<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display settings for the [twd_articles] grouped-by-category landing view:
 * how many category sections show, how many posts in each, their order, and
 * whether thumbnails show at all. A separate option and class from
 * TWD_AP_Settings on purpose, since that one is about who can use the editor
 * popup, a different concern from how the public grid displays.
 */
class TWD_AP_Grid_Settings {

	const OPTION_KEY = 'twd_ap_grid_settings';

	public static function get() {
		$defaults = array(
			'category_order'     => array(),
			'categories_count'   => 4,
			'posts_per_category' => 6,
			'show_thumbnails'    => true,
			// Card image shape (1.42.0): 'wide' (the original full-width strip, 170px
			// tall), '4-3' or 'square'. 'wide' prints nothing extra, so a site that
			// never touches it is unchanged.
			'thumb_shape'        => 'wide',
			'read_more_color'    => '#1d4ed8',
			'accent_color'       => '#2563eb',
			// Which categories the "Browse by topic" row under the grid lists:
			// 'all' (every real, non-empty category, the default, so new
			// categories appear on their own) or an array of category IDs.
			'topic_category_ids' => 'all',
			// Article look (1.38.0): how a published article reads. Empty
			// colours and the 'system' / 'medium' / 'normal' choices mean "the
			// plugin's own typography", and then nothing is printed at all,
			// so a site that never touches these looks exactly as before.
			// Call to action (1.38.0): empty and switched off everywhere until
			// the owner fills it in and ticks where it should show.
			// Category intro (1.38.0): print a category page's own Description above
			// its grid. Off until the owner ticks it, because many themes already
			// print the description themselves and it would show twice.
			'intro_show'         => false,
			// Category pages (1.39.0): use the plugin's own category and tag page
			// (title, intro, grid, call to action) so no page-builder template is
			// needed. On by default; it steps aside for a theme or page-builder
			// template that already applies (see TWD_AP_Archive).
			'archive_takeover'   => true,
			// Related articles (1.40.0): "More in {category}" links at the end of
			// every article. On by default; the owner can switch it off or change
			// how many links.
			'related_show'       => true,
			'related_count'      => 4,
			// 1.46.0: where the related block goes: 'auto' (at the end of every
			// article, as before), 'shortcode' (only where [twd_related] is
			// placed) or 'off'.
			'related_place'      => 'auto',
			// Also close every article with the Browse by topic links under the
			// related list.
			'related_topics'     => true,
			'cta_heading'        => '',
			'cta_text'           => '',
			'cta_button_label'   => '',
			'cta_button_url'     => '',
			'cta_show_grid'      => false,
			'cta_show_categories' => false,
			'cta_show_index'     => false,
			'cta_show_articles'  => false,
			'look_text_color'    => '',
			'look_heading_color' => '',
			'look_link_color'    => '',
			'look_quote_color'   => '',
			'look_font'          => 'system',
			'look_size'          => 'medium',
			'look_spacing'       => 'normal',
			// 1.46.0: keep one and two letter words with the word after them, so a
			// line never ends on "so" or "we".
			'look_tidy_lines'    => true,
			'swipebook_color'    => '#5b8a72',
			// Where the swipe book button goes on an article (1.41.0): 'top' (under
			// the title), 'bottom' (after the text) or 'both'.
			'swipebook_button_position' => 'both',
			// 1.46.0: put the button under the title with the [twd_swipebook_button]
			// shortcode (in a template or a page) instead of the automatic one.
			'swipebook_top_shortcode' => false,
			'swipebook_heading'  => '',
			'swipebook_label'    => 'Swipe Book',
			'swipebook_logo_id'  => 0,
			'swipebook_logo_bg'  => 'light',
			'swipebook_export_branding' => true,
			'profile_photo_id'   => 0,
			'profile_name'       => '',
			'profile_bio'        => '',
			'profile_link_url'   => '',
			'profile_link_label' => '',
		);
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $defaults );
	}

	/** The choices for the card image shape, in the order the popup lists them. */
	const THUMB_SHAPES = array( 'wide', '4-3', 'square' );

	/** Where the related block goes (1.46.0). */
	const RELATED_PLACES = array( 'auto', 'shortcode', 'off' );

	/**
	 * The class the grid's root element carries for a card image shape: none for
	 * the original wide strip (and for anything unrecognised), so the page HTML
	 * of a site that never chose a shape is byte for byte what it was.
	 */
	public static function thumb_shape_class( $shape ) {
		if ( '4-3' === $shape ) {
			return 'twd-ap-thumbs-4-3';
		}
		if ( 'square' === $shape ) {
			return 'twd-ap-thumbs-square';
		}
		return '';
	}

	public static function sanitize( $input ) {
		$current = self::get();

		$output = array(
			'category_order'     => $current['category_order'],
			'categories_count'   => $current['categories_count'],
			'posts_per_category' => $current['posts_per_category'],
			'show_thumbnails'    => $current['show_thumbnails'],
			'thumb_shape'        => $current['thumb_shape'],
			'read_more_color'    => $current['read_more_color'],
			'accent_color'       => $current['accent_color'],
			'topic_category_ids' => $current['topic_category_ids'],
			'intro_show'         => $current['intro_show'],
			'archive_takeover'   => $current['archive_takeover'],
			'related_show'       => $current['related_show'],
			'related_count'      => $current['related_count'],
			'related_place'      => $current['related_place'],
			'cta_heading'        => $current['cta_heading'],
			'cta_text'           => $current['cta_text'],
			'cta_button_label'   => $current['cta_button_label'],
			'cta_button_url'     => $current['cta_button_url'],
			'cta_show_grid'      => $current['cta_show_grid'],
			'cta_show_categories' => $current['cta_show_categories'],
			'cta_show_index'     => $current['cta_show_index'],
			'cta_show_articles'  => $current['cta_show_articles'],
			'look_text_color'    => $current['look_text_color'],
			'look_heading_color' => $current['look_heading_color'],
			'look_link_color'    => $current['look_link_color'],
			'look_quote_color'   => $current['look_quote_color'],
			'look_font'          => $current['look_font'],
			'look_size'          => $current['look_size'],
			'look_spacing'       => $current['look_spacing'],
			'look_tidy_lines'    => $current['look_tidy_lines'],
			'swipebook_color'    => $current['swipebook_color'],
			'swipebook_button_position' => $current['swipebook_button_position'],
			'swipebook_top_shortcode' => $current['swipebook_top_shortcode'],
			'related_topics'     => $current['related_topics'],
			'swipebook_heading'  => $current['swipebook_heading'],
			'swipebook_label'    => $current['swipebook_label'],
			'swipebook_logo_id'  => $current['swipebook_logo_id'],
			'swipebook_logo_bg'  => $current['swipebook_logo_bg'],
			'swipebook_export_branding' => $current['swipebook_export_branding'],
			'profile_photo_id'   => $current['profile_photo_id'],
			'profile_name'       => $current['profile_name'],
			'profile_bio'        => $current['profile_bio'],
			'profile_link_url'   => $current['profile_link_url'],
			'profile_link_label' => $current['profile_link_label'],
		);

		if ( isset( $input['category_order'] ) && is_array( $input['category_order'] ) ) {
			$output['category_order'] = array_values( array_filter( array_map( 'absint', $input['category_order'] ) ) );
		}
		if ( isset( $input['categories_count'] ) ) {
			$output['categories_count'] = max( 1, min( 12, absint( $input['categories_count'] ) ) );
		}
		if ( isset( $input['posts_per_category'] ) ) {
			$output['posts_per_category'] = max( 1, min( 24, absint( $input['posts_per_category'] ) ) );
		}
		if ( isset( $input['show_thumbnails'] ) ) {
			$output['show_thumbnails'] = (bool) $input['show_thumbnails'];
		}
		if ( isset( $input['thumb_shape'] ) && in_array( $input['thumb_shape'], self::THUMB_SHAPES, true ) ) {
			$output['thumb_shape'] = $input['thumb_shape'];
		}
		if ( isset( $input['read_more_color'] ) ) {
			$color = sanitize_hex_color( $input['read_more_color'] );
			if ( $color ) {
				$output['read_more_color'] = $color;
			}
		}
		if ( isset( $input['accent_color'] ) ) {
			$color = sanitize_hex_color( $input['accent_color'] );
			if ( $color ) {
				$output['accent_color'] = $color;
			}
		}
		if ( isset( $input['topic_category_ids'] ) ) {
			if ( 'all' === $input['topic_category_ids'] ) {
				$output['topic_category_ids'] = 'all';
			} elseif ( is_array( $input['topic_category_ids'] ) ) {
				$output['topic_category_ids'] = array_values( array_unique( array_filter( array_map( 'absint', $input['topic_category_ids'] ) ) ) );
			}
		}
		foreach ( array( 'look_text_color', 'look_heading_color', 'look_link_color', 'look_quote_color' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				// An empty value clears the colour back to the plugin default.
				$color            = '' === $input[ $key ] ? '' : sanitize_hex_color( $input[ $key ] );
				$output[ $key ]   = $color ? $color : ( '' === $input[ $key ] ? '' : $output[ $key ] );
			}
		}
		if ( isset( $input['swipebook_button_position'] ) && in_array( $input['swipebook_button_position'], array( 'top', 'bottom', 'both' ), true ) ) {
			$output['swipebook_button_position'] = $input['swipebook_button_position'];
		}
		if ( isset( $input['swipebook_top_shortcode'] ) ) {
			$output['swipebook_top_shortcode'] = (bool) $input['swipebook_top_shortcode'];
		}
		if ( isset( $input['look_tidy_lines'] ) ) {
			$output['look_tidy_lines'] = (bool) $input['look_tidy_lines'];
		}
		if ( isset( $input['related_place'] ) && in_array( $input['related_place'], self::RELATED_PLACES, true ) ) {
			$output['related_place'] = $input['related_place'];
		}
		if ( isset( $input['related_topics'] ) ) {
			$output['related_topics'] = (bool) $input['related_topics'];
		}
		if ( isset( $input['related_show'] ) ) {
			$output['related_show'] = (bool) $input['related_show'];
		}
		if ( isset( $input['related_count'] ) ) {
			$output['related_count'] = max( 1, min( 8, absint( $input['related_count'] ) ) );
		}
		if ( isset( $input['archive_takeover'] ) ) {
			$output['archive_takeover'] = (bool) $input['archive_takeover'];
		}
		if ( isset( $input['intro_show'] ) ) {
			$output['intro_show'] = (bool) $input['intro_show'];
		}
		if ( isset( $input['cta_heading'] ) ) {
			$output['cta_heading'] = mb_substr( TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( $input['cta_heading'] ) ), 0, 120 );
		}
		if ( isset( $input['cta_text'] ) ) {
			$output['cta_text'] = mb_substr( TWD_AP_Sanitizer::strip_dashes( sanitize_textarea_field( $input['cta_text'] ) ), 0, 500 );
		}
		if ( isset( $input['cta_button_label'] ) ) {
			$output['cta_button_label'] = mb_substr( TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( $input['cta_button_label'] ) ), 0, 40 );
		}
		if ( isset( $input['cta_button_url'] ) ) {
			// http, https, mailto and tel only; anything else becomes empty.
			$output['cta_button_url'] = esc_url_raw( trim( (string) $input['cta_button_url'] ), array( 'http', 'https', 'mailto', 'tel' ) );
		}
		foreach ( array( 'cta_show_grid', 'cta_show_categories', 'cta_show_index', 'cta_show_articles' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = (bool) $input[ $key ];
			}
		}
		$choices = array(
			'look_font'    => array( 'system', 'serif', 'theme' ),
			'look_size'    => array( 'medium', 'large' ),
			'look_spacing' => array( 'compact', 'normal', 'relaxed' ),
		);
		foreach ( $choices as $key => $allowed ) {
			if ( isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ) {
				$output[ $key ] = $input[ $key ];
			}
		}
		if ( isset( $input['swipebook_color'] ) ) {
			$color = sanitize_hex_color( $input['swipebook_color'] );
			if ( $color ) {
				$output['swipebook_color'] = $color;
			}
		}
		if ( isset( $input['swipebook_heading'] ) ) {
			$output['swipebook_heading'] = sanitize_text_field( $input['swipebook_heading'] );
		}
		if ( isset( $input['swipebook_label'] ) ) {
			$label = sanitize_text_field( $input['swipebook_label'] );
			$output['swipebook_label'] = '' !== $label ? $label : 'Swipe Book';
		}
		if ( isset( $input['swipebook_logo_id'] ) ) {
			$output['swipebook_logo_id'] = absint( $input['swipebook_logo_id'] );
		}
		if ( isset( $input['swipebook_logo_bg'] ) && in_array( $input['swipebook_logo_bg'], array( 'none', 'light', 'dark' ), true ) ) {
			$output['swipebook_logo_bg'] = $input['swipebook_logo_bg'];
		}
		if ( isset( $input['swipebook_export_branding'] ) ) {
			$output['swipebook_export_branding'] = (bool) $input['swipebook_export_branding'];
		}
		if ( isset( $input['profile_photo_id'] ) ) {
			$output['profile_photo_id'] = absint( $input['profile_photo_id'] );
		}
		if ( isset( $input['profile_name'] ) ) {
			$output['profile_name'] = sanitize_text_field( $input['profile_name'] );
		}
		if ( isset( $input['profile_bio'] ) ) {
			$output['profile_bio'] = sanitize_textarea_field( $input['profile_bio'] );
		}
		if ( isset( $input['profile_link_url'] ) ) {
			$output['profile_link_url'] = esc_url_raw( $input['profile_link_url'] );
		}
		if ( isset( $input['profile_link_label'] ) ) {
			$output['profile_link_label'] = sanitize_text_field( $input['profile_link_label'] );
		}

		return $output;
	}

	public static function save( $input ) {
		update_option( self::OPTION_KEY, self::sanitize( $input ) );
	}
}
