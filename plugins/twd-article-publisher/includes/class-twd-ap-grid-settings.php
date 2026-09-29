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
