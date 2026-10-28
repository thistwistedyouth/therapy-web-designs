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
				'aiLimit'         => class_exists( 'TWD_AP_AI_Generate' ) ? TWD_AP_AI_Generate::RATE_LIMIT : 0,
				'aiRemaining'     => ( class_exists( 'TWD_AP_AI_Generate' ) && TWD_AP_AI_Generate::is_configured() ) ? TWD_AP_AI_Generate::remaining() : 0,
				'aiMaxChars'      => class_exists( 'TWD_AP_AI_Generate' ) ? TWD_AP_AI_Generate::MAX_INPUT_CHARS : 0,
				// 1.48.0: a new article's "Include bio page" tick starts as the site-wide tick says.
				'profileInAllBooks' => ! empty( TWD_AP_Grid_Settings::get()['profile_in_all_books'] ) ? 1 : 0,
				'i18n'          => array(
					'saving'       => __( 'Saving…', 'twd-article-publisher' ),
					'error'        => __( 'Something went wrong. Please try again.', 'twd-article-publisher' ),
					'confirmClose' => __( 'Discard unsaved changes?', 'twd-article-publisher' ),
					'confirmDelete' => __( 'Delete this article? It will be moved to the Trash.', 'twd-article-publisher' ),
				),
			)
		);
	}

	/**
	 * Inline custom properties that give the floating New Article button the
	 * colour of the quote bar in an article (the owner's quote colour from
	 * Article look, else their link colour, which the bar falls back to), with
	 * a hover colour and writing that reads on it. The writing is white or dark
	 * ink, whichever has the better contrast (WCAG) on that colour, and the
	 * hover moves the way that keeps the contrast: darker under white, lighter
	 * under dark ink. '' when neither colour is set, so a site that never chose
	 * colours keeps the original sage button.
	 */
	public static function new_button_style( $quote_color, $link_color ) {
		$h = '';
		foreach ( array( $quote_color, $link_color ) as $candidate ) {
			// Three or six hex digits and nothing else (sanitize_hex_color alone lets
			// a trailing newline through).
			if ( is_string( $candidate ) && sanitize_hex_color( $candidate ) && preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/iD', $candidate, $m ) ) {
				$h = $m[1];
				break;
			}
		}
		if ( '' === $h ) {
			return '';
		}
		if ( 3 === strlen( $h ) ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		$r = hexdec( substr( $h, 0, 2 ) );
		$g = hexdec( substr( $h, 2, 2 ) );
		$b = hexdec( substr( $h, 4, 2 ) );

		$dark_ink = array( 31, 29, 43 ); // #1f1d2b
		$lum      = self::luminance( $r, $g, $b );
		$white_on = 1.05 / ( $lum + 0.05 );
		$dark_on  = ( $lum + 0.05 ) / ( self::luminance( $dark_ink[0], $dark_ink[1], $dark_ink[2] ) + 0.05 );
		$use_dark = $dark_on > $white_on;

		if ( $use_dark ) {
			$hover = sprintf( '#%02x%02x%02x', (int) round( $r + ( 255 - $r ) * 0.18 ), (int) round( $g + ( 255 - $g ) * 0.18 ), (int) round( $b + ( 255 - $b ) * 0.18 ) );
		} else {
			$hover = sprintf( '#%02x%02x%02x', (int) round( $r * 0.82 ), (int) round( $g * 0.82 ), (int) round( $b * 0.82 ) );
		}
		return '--twd-ap-primary:#' . $h . ';--twd-ap-primary-dark:' . $hover . ';--twd-ap-primary-ink:' . ( $use_dark ? '#1f1d2b' : '#ffffff' ) . ';';
	}

	/** WCAG relative luminance of an sRGB colour given as 0 to 255 channels. */
	private static function luminance( $r, $g, $b ) {
		$lin = function ( $v ) {
			$v = $v / 255;
			return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * $lin( $r ) + 0.7152 * $lin( $g ) + 0.0722 * $lin( $b );
	}

	/**
	 * Whether this person may start a new article here at all: an allowed publisher, and the site has not
	 * chosen "nowhere" for the New Article button. The grid's own New article circle is shown on this test.
	 */
	public static function new_article_allowed() {
		if ( ! self::current_user_allowed() ) {
			return false;
		}
		$settings = TWD_AP_Settings::get_settings();
		return 'nowhere' !== $settings['show_everywhere'];
	}

	public function render_buttons_and_modal() {
		if ( is_admin() ) {
			return;
		}
		if ( ! self::current_user_allowed() ) {
			return;
		}

		// A grid on this page that printed its own New article circle takes the place of the floating button
		// (the popup itself is still needed, for the circle).
		$grid_new  = TWD_AP_Shortcode::new_circle_rendered();
		$show_new  = $this->should_show_new_button() && ! $grid_new;
		$show_edit = $this->should_show_edit_button();
		$look      = TWD_AP_Grid_Settings::get();
		// The New Article button wears the colour of the article's quote bar.
		$new_btn_style = self::new_button_style( $look['look_quote_color'], $look['look_link_color'] );

		if ( ! $show_new && ! $show_edit && ! $grid_new ) {
			return;
		}

		include TWD_AP_PATH . 'templates/buttons-and-modal.php';
	}
}
