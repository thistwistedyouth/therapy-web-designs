<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_AP_Settings {

	private static $instance = null;
	const OPTION_KEY = 'twd_ap_settings';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function get_settings() {
		$defaults = array(
			'allowed_roles'    => array( 'administrator', 'editor', 'author' ),
			'default_category' => (int) get_option( 'default_category' ),
			'show_everywhere'  => 1,
		);
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $defaults );
	}

	public function add_menu() {
		add_options_page(
			__( 'Article Publisher', 'twd-article-publisher' ),
			__( 'Article Publisher', 'twd-article-publisher' ),
			'manage_options',
			'twd-article-publisher',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( 'twd_ap_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ) {
		$output      = array();
		$valid_roles = array( 'administrator', 'editor', 'author', 'contributor' );

		$output['allowed_roles'] = array();
		if ( ! empty( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
			foreach ( $input['allowed_roles'] as $role ) {
				if ( in_array( $role, $valid_roles, true ) ) {
					$output['allowed_roles'][] = $role;
				}
			}
		}
		if ( empty( $output['allowed_roles'] ) ) {
			$output['allowed_roles'] = array( 'administrator' );
		}

		$output['default_category'] = isset( $input['default_category'] ) ? absint( $input['default_category'] ) : (int) get_option( 'default_category' );
		$output['show_everywhere']  = ! empty( $input['show_everywhere'] ) ? 1 : 0;

		return $output;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = self::get_settings();
		$roles      = array(
			'administrator' => __( 'Administrator', 'twd-article-publisher' ),
			'editor'        => __( 'Editor', 'twd-article-publisher' ),
			'author'        => __( 'Author', 'twd-article-publisher' ),
			'contributor'   => __( 'Contributor', 'twd-article-publisher' ),
		);
		$categories = get_categories( array( 'hide_empty' => false ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Article Publisher Settings', 'twd-article-publisher' ); ?></h1>
			<p><?php esc_html_e( 'Controls who can use the on-site article popup to publish and edit blog posts, and what happens by default.', 'twd-article-publisher' ); ?></p>

			<?php if ( class_exists( 'TWD_AP_Updater' ) ) : ?>
				<?php TWD_AP_Updater::instance()->render_checked_notice(); ?>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'twd_ap_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Who can publish articles', 'twd-article-publisher' ); ?></th>
						<td>
							<?php foreach ( $roles as $role_key => $role_label ) : ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox" name="twd_ap_settings[allowed_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $settings['allowed_roles'], true ) ); ?> />
									<?php echo esc_html( $role_label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Administrators always have access. Choose which other roles can see the article buttons and use the popup.', 'twd-article-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default category', 'twd-article-publisher' ); ?></th>
						<td>
							<select name="twd_ap_settings[default_category]">
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $settings['default_category'], $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Used only if no category is ticked when an article is published.', 'twd-article-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '"New Article" button', 'twd-article-publisher' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="twd_ap_settings[show_everywhere]" value="1" <?php checked( ! empty( $settings['show_everywhere'] ) ); ?> />
								<?php esc_html_e( 'Show it on every page of the site for allowed users (recommended)', 'twd-article-publisher' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'The "Edit This Article" button always shows only on the article being viewed, to people allowed to edit it, regardless of this setting.', 'twd-article-publisher' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Plugin version', 'twd-article-publisher' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: currently installed plugin version, e.g. 1.8.0 */
					esc_html__( 'Currently running version %s.', 'twd-article-publisher' ),
					'<strong>' . esc_html( TWD_AP_VERSION ) . '</strong>'
				);
				?>
				<?php if ( class_exists( 'TWD_AP_Updater' ) ) : ?>
					<a href="<?php echo esc_url( TWD_AP_Updater::instance()->check_url() ); ?>" class="button"><?php esc_html_e( 'Check for updates', 'twd-article-publisher' ); ?></a>
				<?php endif; ?>
			</p>
			<p class="description"><?php esc_html_e( 'Updates are self-hosted (not on WordPress.org) and normally checked automatically every 12 hours. Use this to check right now instead of waiting.', 'twd-article-publisher' ); ?></p>
		</div>
		<?php
	}
}
