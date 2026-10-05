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
			'allowed_roles'     => array( 'administrator', 'editor', 'author' ),
			'default_category'  => (int) get_option( 'default_category' ),
			'show_everywhere'   => 'everywhere',
			'anthropic_api_key' => '',
			'converter_enabled' => 0,
			'converter_rules'   => class_exists( 'TWD_AP_Converter' ) ? TWD_AP_Converter::default_rules() : '',
		);
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		// Pre-1.15 sites stored show_everywhere as 1/0. Map that to the new
		// three-way scope so an already-configured site keeps behaving the
		// same way after updating, instead of silently reverting to the
		// default until someone happens to resave the settings screen.
		if ( isset( $saved['show_everywhere'] ) && ! is_string( $saved['show_everywhere'] ) ) {
			$saved['show_everywhere'] = $saved['show_everywhere'] ? 'everywhere' : 'nowhere';
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

		$valid_scopes = array( 'everywhere', 'shortcode_page', 'nowhere' );
		$scope        = isset( $input['show_everywhere'] ) ? sanitize_key( $input['show_everywhere'] ) : 'everywhere';
		$output['show_everywhere'] = in_array( $scope, $valid_scopes, true ) ? $scope : 'everywhere';

		// The key field always posts blank (render_page() never echoes the
		// saved value back into it, see there for why) -- so blank means
		// "left alone," not "clear it." Clearing only ever happens via the
		// explicit checkbox, never by submitting the form with nothing
		// typed into a field that was never meant to show the real value.
		$existing = self::get_settings();
		if ( ! empty( $input['anthropic_api_key_clear'] ) ) {
			$output['anthropic_api_key'] = '';
		} elseif ( ! empty( $input['anthropic_api_key'] ) ) {
			$output['anthropic_api_key'] = sanitize_text_field( wp_unslash( $input['anthropic_api_key'] ) );
		} else {
			$output['anthropic_api_key'] = $existing['anthropic_api_key'];
		}

		// Elementor converter: off unless ticked. The rules box is only
		// replaced when it was actually posted, so saving the screen from
		// a page that never showed it cannot wipe the rules.
		$output['converter_enabled'] = ! empty( $input['converter_enabled'] ) ? 1 : 0;
		if ( isset( $input['converter_rules'] ) ) {
			$output['converter_rules'] = sanitize_textarea_field( wp_unslash( $input['converter_rules'] ) );
		} else {
			$output['converter_rules'] = $existing['converter_rules'];
		}

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
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="twd_ap_settings[show_everywhere]" value="everywhere" <?php checked( $settings['show_everywhere'], 'everywhere' ); ?> />
								<?php esc_html_e( 'Show it on every page of the site for allowed users (recommended)', 'twd-article-publisher' ); ?>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="twd_ap_settings[show_everywhere]" value="shortcode_page" <?php checked( $settings['show_everywhere'], 'shortcode_page' ); ?> />
								<?php esc_html_e( 'Only on pages with the [twd_articles] shortcode', 'twd-article-publisher' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="twd_ap_settings[show_everywhere]" value="nowhere" <?php checked( $settings['show_everywhere'], 'nowhere' ); ?> />
								<?php esc_html_e( 'Do not show it (rely on "Edit This Article" only)', 'twd-article-publisher' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'The "Edit This Article" button always shows only on the article being viewed, to people allowed to edit it, regardless of this setting.', 'twd-article-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'AI article generation', 'twd-article-publisher' ); ?></th>
						<td>
							<?php if ( '' !== $settings['anthropic_api_key'] ) : ?>
								<p class="description" style="margin:0 0 8px;">
									<?php esc_html_e( 'A key is already saved for this site.', 'twd-article-publisher' ); ?>
								</p>
							<?php endif; ?>
							<input type="password" name="twd_ap_settings[anthropic_api_key]" class="regular-text" autocomplete="off" placeholder="<?php echo '' !== $settings['anthropic_api_key'] ? esc_attr__( 'Enter a new key to replace it', 'twd-article-publisher' ) : esc_attr__( 'sk-ant-...', 'twd-article-publisher' ); ?>" />
							<?php if ( '' !== $settings['anthropic_api_key'] ) : ?>
								<label style="display:block;margin-top:8px;">
									<input type="checkbox" name="twd_ap_settings[anthropic_api_key_clear]" value="1" />
									<?php esc_html_e( 'Remove the saved key', 'twd-article-publisher' ); ?>
								</label>
							<?php endif; ?>
							<p class="description">
								<?php esc_html_e( 'Add your own Anthropic API key to generate articles directly on this site (idea or topic in, a drafted article out), instead of copying JSON in from Article Assist on Therapy Resource Directory. Leave blank to keep the article popup exactly as it is now. The key is never shown back in this field once saved, and is only ever used server-side, never sent to the browser.', 'twd-article-publisher' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Elementor converter', 'twd-article-publisher' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px;">
								<input type="checkbox" name="twd_ap_settings[converter_enabled]" value="1" <?php checked( ! empty( $settings['converter_enabled'] ) ); ?> />
								<?php esc_html_e( 'Turn on the tool that converts old Elementor posts (advanced, off by default)', 'twd-article-publisher' ); ?>
							</label>
							<p class="description" style="margin-bottom:8px;">
								<?php esc_html_e( 'Adds Tools > Convert Elementor posts, for administrators only. It converts one post at a time, saves a backup first, and every conversion can be undone. Take a full site backup before using it.', 'twd-article-publisher' ); ?>
								<?php if ( ! empty( $settings['converter_enabled'] ) && class_exists( 'TWD_AP_Converter' ) ) : ?>
									<a href="<?php echo esc_url( TWD_AP_Converter::tool_url() ); ?>"><?php esc_html_e( 'Open the converter', 'twd-article-publisher' ); ?></a>
								<?php endif; ?>
							</p>
							<?php if ( ! empty( $settings['converter_enabled'] ) ) : ?>
								<textarea name="twd_ap_settings[converter_rules]" rows="8" class="large-text code"><?php echo esc_textarea( (string) $settings['converter_rules'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Rules for turning this site\'s old class names into real headings, quotes and paragraphs when converting. One per line: part of an old class name = h2, h3, h4, p, blockquote, strong, em, unwrap or remove. Lines starting with # are notes.', 'twd-article-publisher' ); ?></p>
							<?php endif; ?>
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
