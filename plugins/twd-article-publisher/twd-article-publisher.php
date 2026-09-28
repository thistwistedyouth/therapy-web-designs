<?php
/**
 * Plugin Name: TWD Article Publisher
 * Plugin URI: https://therapywebdesigns.co.uk/
 * Description: Lets approved users publish and edit blog articles from the live site in a popup, without going into wp-admin. Paste AI-formatted HTML or write visually, tag categories, set a featured image, and publish.
 * Version: 1.4.0
 * Author: Therapy Web Designs
 * Author URI: https://therapywebdesigns.co.uk/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: twd-article-publisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TWD_AP_VERSION', '1.4.0' );
define( 'TWD_AP_PATH', plugin_dir_path( __FILE__ ) );
define( 'TWD_AP_URL', plugin_dir_url( __FILE__ ) );

require_once TWD_AP_PATH . 'includes/class-twd-ap-sanitizer.php';
require_once TWD_AP_PATH . 'includes/class-twd-ap-settings.php';
require_once TWD_AP_PATH . 'includes/class-twd-ap-rest.php';
require_once TWD_AP_PATH . 'includes/class-twd-ap-frontend.php';
require_once TWD_AP_PATH . 'includes/class-twd-ap-shortcode.php';
require_once TWD_AP_PATH . 'includes/class-twd-ap-article-style.php';

final class TWD_Article_Publisher {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		TWD_AP_Settings::instance();
		TWD_AP_REST::instance();
		TWD_AP_Frontend::instance();
		TWD_AP_Shortcode::instance();
		TWD_AP_Article_Style::instance();
	}

	public static function activate() {
		$defaults = array(
			'allowed_roles'    => array( 'administrator', 'editor', 'author' ),
			'default_category' => (int) get_option( 'default_category' ),
			'show_everywhere'  => 1,
		);
		if ( false === get_option( 'twd_ap_settings' ) ) {
			add_option( 'twd_ap_settings', $defaults );
		}
	}
}

register_activation_hook( __FILE__, array( 'TWD_Article_Publisher', 'activate' ) );

TWD_Article_Publisher::instance();
