<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-hosted update checker. Not on WordPress.org, so this reads a small
 * update.json from the plugin's own GitHub repo (must be public, since
 * client sites fetch it with no credentials) and, when its version is newer
 * than what's installed, wires it into wp-admin's normal Plugins update UI:
 * "Update available" row, one-click update, and the "View details" popup.
 */
class TWD_AP_Updater {

	private static $instance = null;
	const UPDATE_JSON_URL = 'https://raw.githubusercontent.com/thistwistedyouth/therapy-web-designs/main/dist/twd-article-publisher-update.json';
	const CACHE_KEY = 'twd_ap_update_check';
	const SLUG = 'twd-article-publisher';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_cache' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ) );
	}

	private function plugin_basename() {
		return plugin_basename( TWD_AP_PATH . 'twd-article-publisher.php' );
	}

	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	private function get_remote_info() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( self::UPDATE_JSON_URL, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $data || empty( $data->version ) || empty( $data->download_url ) ) {
			return false;
		}

		set_transient( self::CACHE_KEY, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->get_remote_info();
		if ( ! $remote ) {
			return $transient;
		}

		$basename = $this->plugin_basename();

		if ( version_compare( $remote->version, TWD_AP_VERSION, '>' ) ) {
			$item               = new stdClass();
			$item->id           = self::SLUG;
			$item->slug         = self::SLUG;
			$item->plugin       = $basename;
			$item->new_version  = $remote->version;
			$item->url          = isset( $remote->homepage ) ? $remote->homepage : '';
			$item->package      = $remote->download_url;
			$item->tested       = isset( $remote->tested ) ? $remote->tested : '';
			$item->requires     = isset( $remote->requires ) ? $remote->requires : '';
			$item->requires_php = isset( $remote->requires_php ) ? $remote->requires_php : '';

			$transient->response[ $basename ] = $item;
			unset( $transient->no_update[ $basename ] );
		} else {
			unset( $transient->response[ $basename ] );
		}

		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$remote = $this->get_remote_info();
		if ( ! $remote ) {
			return $result;
		}

		$info               = new stdClass();
		$info->name         = 'TWD Article Publisher';
		$info->slug         = self::SLUG;
		$info->version      = $remote->version;
		$info->author       = '<a href="https://therapywebdesigns.co.uk/">Therapy Web Designs</a>';
		$info->homepage     = isset( $remote->homepage ) ? $remote->homepage : '';
		$info->requires     = isset( $remote->requires ) ? $remote->requires : '';
		$info->tested       = isset( $remote->tested ) ? $remote->tested : '';
		$info->requires_php = isset( $remote->requires_php ) ? $remote->requires_php : '';
		$info->download_link = $remote->download_url;
		$info->last_updated = isset( $remote->last_updated ) ? $remote->last_updated : '';
		$info->sections     = array(
			'description' => isset( $remote->description ) ? $remote->description : '',
			'changelog'   => isset( $remote->changelog ) ? $remote->changelog : '',
		);

		return $info;
	}

	public function row_meta( $links, $file ) {
		if ( $file === $this->plugin_basename() ) {
			$links[] = '<a href="https://github.com/thistwistedyouth/therapy-web-designs" target="_blank" rel="noopener">' . esc_html__( 'View source', 'twd-article-publisher' ) . '</a>';
		}
		return $links;
	}
}
