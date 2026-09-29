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
		add_filter( 'plugin_action_links_' . $this->plugin_basename(), array( $this, 'action_links' ) );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_cache' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ) );
		add_action( 'admin_post_twd_ap_check_updates', array( $this, 'handle_manual_check' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_plugins_screen_notice' ) );
	}

	/**
	 * Same "Check for updates" result notice Settings > Article Publisher
	 * shows, but on the Plugins list screen instead -- only when the check
	 * was actually triggered from there (?twd_ap_return=plugins), so it
	 * never shows on an unrelated plugins.php load.
	 */
	public function maybe_render_plugins_screen_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		$this->render_checked_notice();
	}

	/**
	 * The "Check for updates" button on Settings > Article Publisher. Clears
	 * both our own cache and core's update_plugins transient, forces an
	 * immediate synchronous recheck (wp_update_plugins(), the same core
	 * function cron uses) so the redirect reflects the current state right
	 * away rather than waiting for the next page load or 12-hour cache, then
	 * sends the admin back to Settings with the result in the URL.
	 */
	public function handle_manual_check() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'twd_ap_check_updates' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'twd-article-publisher' ) );
		}

		$this->clear_cache();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$remote = $this->get_remote_info();
		$latest = $remote && ! empty( $remote->version ) ? $remote->version : '';

		if ( isset( $_GET['twd_ap_return'] ) && 'plugins' === sanitize_key( wp_unslash( $_GET['twd_ap_return'] ) ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'twd_ap_checked' => 1,
						'twd_ap_latest'  => rawurlencode( $latest ),
					),
					admin_url( 'plugins.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'twd-article-publisher',
					'twd_ap_checked' => 1,
					'twd_ap_latest'  => rawurlencode( $latest ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public function check_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=twd_ap_check_updates' ),
			'twd_ap_check_updates'
		);
	}

	private function plugin_basename() {
		return plugin_basename( TWD_AP_PATH . 'twd-article-publisher.php' );
	}

	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::CACHE_KEY . '_error' );
	}

	/**
	 * Records exactly why the last fetch failed (blocked outbound request,
	 * DNS failure, a non-200 from GitHub, malformed JSON), since "could not
	 * reach the update server" alone gives nothing to act on when a site's
	 * own host or firewall is the actual cause. Read back by
	 * render_checked_notice() only when a fetch just failed.
	 */
	private function set_last_error( $message ) {
		set_transient( self::CACHE_KEY . '_error', $message, DAY_IN_SECONDS );
	}

	private function get_remote_info() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( self::UPDATE_JSON_URL, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			$this->set_last_error( $response->get_error_message() );
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->set_last_error( 'GitHub returned HTTP ' . $code . '.' );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $data || empty( $data->version ) || empty( $data->download_url ) ) {
			$this->set_last_error( 'The update file did not contain a valid version.' );
			return false;
		}

		delete_transient( self::CACHE_KEY . '_error' );
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

	/**
	 * "Check for updates" action link on the plugin's own row on the Plugins
	 * list screen, alongside Deactivate -- same nonce'd admin-post handler
	 * the Settings > Article Publisher button already uses, so both trigger
	 * the same immediate synchronous recheck rather than waiting on the
	 * 12-hour cache. Redirects back to the Plugins list instead of Settings
	 * when triggered from here, with the result appended so a notice can
	 * show without needing to leave this screen.
	 */
	public function action_links( $links ) {
		$url = wp_nonce_url(
			add_query_arg( 'twd_ap_return', 'plugins', admin_url( 'admin-post.php?action=twd_ap_check_updates' ) ),
			'twd_ap_check_updates'
		);
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'twd-article-publisher' ) . '</a>';
		return $links;
	}

	/**
	 * Shared result notice for a "Check for updates" click, used on both
	 * Settings > Article Publisher and the Plugins list screen so the
	 * message and logic only live in one place.
	 */
	public function render_checked_notice() {
		if ( empty( $_GET['twd_ap_checked'] ) ) {
			return;
		}
		$latest = isset( $_GET['twd_ap_latest'] ) ? sanitize_text_field( wp_unslash( $_GET['twd_ap_latest'] ) ) : '';

		if ( '' === $latest ) {
			$detail = get_transient( self::CACHE_KEY . '_error' );
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Could not reach the update server just now.', 'twd-article-publisher' ) . ( $detail ? ' <code>' . esc_html( $detail ) . '</code>' : '' ) . '</p></div>';
			return;
		}

		if ( version_compare( $latest, TWD_AP_VERSION, '>' ) ) {
			$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$message = ( $screen && 'plugins' === $screen->id )
				/* translators: 1: currently installed version, 2: newer version available */
				? esc_html__( 'A newer version is available: %2$s (you have %1$s). See the update below.', 'twd-article-publisher' )
				/* translators: 1: currently installed version, 2: newer version available */
				: esc_html__( 'A newer version is available: %2$s (you have %1$s). Go to Plugins to update.', 'twd-article-publisher' );
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				sprintf( $message, esc_html( TWD_AP_VERSION ), esc_html( $latest ) )
			);
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( "You're on the latest version.", 'twd-article-publisher' ) . '</p></div>';
		}
	}

	public function row_meta( $links, $file ) {
		if ( $file === $this->plugin_basename() ) {
			$links[] = '<a href="https://github.com/thistwistedyouth/therapy-web-designs" target="_blank" rel="noopener">' . esc_html__( 'View source', 'twd-article-publisher' ) . '</a>';
		}
		return $links;
	}
}
