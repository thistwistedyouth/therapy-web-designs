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
			'category_order'    => array(),
			'categories_count'  => 4,
			'posts_per_category' => 6,
			'show_thumbnails'   => true,
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

		return $output;
	}

	public static function save( $input ) {
		update_option( self::OPTION_KEY, self::sanitize( $input ) );
	}
}
