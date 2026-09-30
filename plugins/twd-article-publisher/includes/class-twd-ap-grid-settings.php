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
			'read_more_color'    => '#1d4ed8',
			'accent_color'       => '#2563eb',
			'swipebook_color'    => '#5b8a72',
			'swipebook_heading'  => '',
			'swipebook_label'    => 'Summary Book',
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

	public static function sanitize( $input ) {
		$current = self::get();

		$output = array(
			'category_order'     => $current['category_order'],
			'categories_count'   => $current['categories_count'],
			'posts_per_category' => $current['posts_per_category'],
			'show_thumbnails'    => $current['show_thumbnails'],
			'read_more_color'    => $current['read_more_color'],
			'accent_color'       => $current['accent_color'],
			'swipebook_color'    => $current['swipebook_color'],
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
			$output['swipebook_label'] = '' !== $label ? $label : 'Summary Book';
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
