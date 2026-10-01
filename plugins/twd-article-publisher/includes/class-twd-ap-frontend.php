<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_AP_Frontend {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_buttons_and_modal' ) );
	}

	public static function current_user_allowed() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}
		$settings = TWD_AP_Settings::get_settings();
		foreach ( (array) $user->roles as $role ) {
			if ( in_array( $role, $settings['allowed_roles'], true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * "shortcode_page" relies on TWD_AP_Shortcode::rendered_on_this_page(),
	 * only reliable by the time wp_footer fires (this method is only ever
	 * called from there and from render_buttons_and_modal(), never from the
	 * earlier maybe_enqueue(), which enqueues unconditionally for any
	 * allowed user regardless of button visibility) -- wp_footer always runs
	 * after the page's own content, and so this shortcode if present, has
	 * already rendered.
	 */
	private function should_show_new_button() {
		$settings = TWD_AP_Settings::get_settings();
		$scope    = $settings['show_everywhere'];

		if ( 'nowhere' === $scope ) {
			return false;
		}
		if ( 'shortcode_page' === $scope ) {
			return TWD_AP_Shortcode::rendered_on_this_page();
		}
		return true;
	}

	private function should_show_edit_button() {
		if ( ! is_singular( 'post' ) ) {
			return false;
		}
		return current_user_can( 'edit_post', get_the_ID() );
	}

	public function maybe_enqueue() {
		if ( is_admin() ) {
			return;
		}
		if ( ! self::current_user_allowed() ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'twd-ap-publisher',
			TWD_AP_URL . 'assets/publisher.css',
			array(),
			TWD_AP_VERSION
		);

		wp_enqueue_script(
			'twd-ap-publisher',
			TWD_AP_URL . 'assets/publisher.js',
			array(),
			TWD_AP_VERSION,
			true
		);

		wp_localize_script(
			'twd-ap-publisher',
			'TWD_AP',
			array(
				'restUrl'       => esc_url_raw( rest_url( 'twd-publisher/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'uncategorizedId' => (int) get_option( 'default_category' ),
				'yoastEnabled'  => defined( 'WPSEO_VERSION' ) ? 1 : 0,
				'currentPostId' => is_singular( 'post' ) ? get_the_ID() : 0,
				'canEditThis'   => $this->should_show_edit_button() ? 1 : 0,
				'adminEditUrl'  => $this->should_show_edit_button() ? get_edit_post_link( get_the_ID(), '' ) : '',
				'aiKeyConfigured' => class_exists( 'TWD_AP_AI_Generate' ) && TWD_AP_AI_Generate::is_configured() ? 1 : 0,
				'i18n'          => array(
					'saving'       => __( 'Saving…', 'twd-article-publisher' ),
					'error'        => __( 'Something went wrong. Please try again.', 'twd-article-publisher' ),
					'confirmClose' => __( 'Discard unsaved changes?', 'twd-article-publisher' ),
					'confirmDelete' => __( 'Delete this article? It will be moved to the Trash.', 'twd-article-publisher' ),
				),
			)
		);
	}

	public function render_buttons_and_modal() {
		if ( is_admin() ) {
			return;
		}
		if ( ! self::current_user_allowed() ) {
			return;
		}

		$show_new  = $this->should_show_new_button();
		$show_edit = $this->should_show_edit_button();

		if ( ! $show_new && ! $show_edit ) {
			return;
		}

		include TWD_AP_PATH . 'templates/buttons-and-modal.php';
	}
}
