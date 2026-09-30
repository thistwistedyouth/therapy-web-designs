<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [twd_articles] renders a self-contained, JS-driven grid that fetches from
 * the public /articles REST route (search, category, tag, pagination all
 * happen client-side with no page reload). When no category/tag attribute
 * is given and the shortcode sits on a real category or tag archive page,
 * it auto-detects that term so the same shortcode works unmodified as a
 * per-category "resources" page.
 */
class TWD_AP_Shortcode {

	private static $instance = null;
	private static $instance_count = 0;
	private static $rendered_on_this_page = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * True once [twd_articles] has actually rendered somewhere on the
	 * current page. Set inside render() rather than detected up front with
	 * has_shortcode(), since that can't see a shortcode stored in an
	 * Elementor widget's own _elementor_data JSON rather than post_content
	 * (see the enqueue_assets() note below). Read by TWD_AP_Frontend, hooked
	 * to wp_footer, which always runs after the page's own content (and so
	 * this shortcode, if present) has already rendered.
	 */
	public static function rendered_on_this_page() {
		return self::$rendered_on_this_page;
	}

	private function __construct() {
		add_shortcode( 'twd_articles', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registered unconditionally on every front-end page (not gated on
	 * has_shortcode()) deliberately: a shortcode inside an Elementor
	 * widget lives in _elementor_data JSON, not plain post_content, so
	 * has_shortcode() can't reliably detect it there. The files are tiny,
	 * so loading them site-wide is a fine trade for not missing the
	 * enqueue window (styles queued after wp_head has already fired --
	 * which is when the shortcode itself renders -- don't reliably make
	 * it into <head> on most themes).
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}
		wp_enqueue_style(
			'twd-ap-articles-grid',
			TWD_AP_URL . 'assets/articles-grid.css',
			array(),
			TWD_AP_VERSION
		);
		wp_enqueue_script(
			'twd-ap-articles-grid',
			TWD_AP_URL . 'assets/articles-grid.js',
			array(),
			TWD_AP_VERSION,
			true
		);
	}

	public function render( $atts ) {
		self::$rendered_on_this_page = true;

		$atts = shortcode_atts(
			array(
				'category' => '',
				'tag'      => '',
				'count'    => 9,
				'columns'  => 3,
			),
			$atts,
			'twd_articles'
		);

		if ( '' === $atts['category'] && '' === $atts['tag'] ) {
			if ( is_category() ) {
				$atts['category'] = get_queried_object()->slug;
			} elseif ( is_tag() ) {
				$atts['tag'] = get_queried_object()->slug;
			}
		}

		self::$instance_count++;
		$instance_id = 'twd-ap-grid-' . self::$instance_count;
		$columns     = max( 1, min( 4, (int) $atts['columns'] ) );
		$show_filters = ( '' === $atts['category'] && '' === $atts['tag'] );
		$is_admin_user = TWD_AP_Frontend::current_user_allowed();

		// Baked into the page's own server-rendered HTML, not fetched over
		// REST after load: the "does this site even have enough articles to
		// split into sections / ever need Load more" decision no longer
		// depends on any async request landing correctly, or its response
		// not being stale -- it's simply already true or false the moment
		// the page itself is generated, same page load as everything else.
		$total_articles = (int) wp_count_posts( 'post' )->publish;

		$config = array(
			'restUrl'      => esc_url_raw( rest_url( 'twd-publisher/v1' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'category'     => sanitize_title( $atts['category'] ),
			'tag'          => sanitize_title( $atts['tag'] ),
			'count'        => max( 1, (int) $atts['count'] ),
			'showFilters'  => $show_filters,
			'isAdmin'      => $is_admin_user,
			'manyArticles' => $total_articles > 12,
		);

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="twd-ap-articles" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php if ( $show_filters ) : ?>
				<div class="twd-ap-articles-toolbar">
					<select class="twd-ap-articles-category-select" aria-label="<?php esc_attr_e( 'Jump to a category', 'twd-article-publisher' ); ?>">
						<option value=""><?php esc_html_e( 'Loading…', 'twd-article-publisher' ); ?></option>
					</select>
					<input type="search" class="twd-ap-articles-search" placeholder="<?php esc_attr_e( 'Search articles…', 'twd-article-publisher' ); ?>" aria-label="<?php esc_attr_e( 'Search articles', 'twd-article-publisher' ); ?>" />
					<?php if ( $is_admin_user ) : ?>
						<button type="button" class="twd-ap-articles-settings-btn" aria-label="<?php esc_attr_e( 'Customise this grid', 'twd-article-publisher' ); ?>" title="<?php esc_attr_e( 'Customise this grid', 'twd-article-publisher' ); ?>">&#9881;</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="twd-ap-articles-groups"></div>
			<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: <?php echo esc_attr( $columns ); ?>;">
				<p class="twd-ap-muted"><?php esc_html_e( 'Loading articles…', 'twd-article-publisher' ); ?></p>
			</div>
			<p class="twd-ap-articles-empty" hidden><?php esc_html_e( 'No articles match your filters.', 'twd-article-publisher' ); ?></p>
			<div class="twd-ap-articles-loadmore-row">
				<button type="button" class="twd-ap-articles-loadmore" hidden><?php esc_html_e( 'Load more', 'twd-article-publisher' ); ?></button>
			</div>
		</div>
		<?php if ( $show_filters && $is_admin_user ) : ?>
			<div class="twd-ap-overlay twd-ap-grid-settings-overlay" data-for="<?php echo esc_attr( $instance_id ); ?>" hidden>
				<div class="twd-ap-modal twd-ap-grid-settings-modal">
					<div class="twd-ap-modal-header">
						<p class="twd-ap-modal-title"><?php esc_html_e( 'Customise this grid', 'twd-article-publisher' ); ?></p>
						<button type="button" class="twd-ap-close-btn twd-ap-grid-settings-close" aria-label="<?php esc_attr_e( 'Close', 'twd-article-publisher' ); ?>">&times;</button>
					</div>
					<div class="twd-ap-modal-body">
						<p id="twd-ap-grid-settings-status" class="twd-ap-status" hidden></p>
						<label class="twd-ap-label" for="twd-ap-gs-cats-count"><?php esc_html_e( 'How many categories to show', 'twd-article-publisher' ); ?></label>
						<input type="number" id="twd-ap-gs-cats-count" class="twd-ap-input" min="1" max="12" />

						<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-posts-count"><?php esc_html_e( 'How many articles per category', 'twd-article-publisher' ); ?></label>
						<input type="number" id="twd-ap-gs-posts-count" class="twd-ap-input" min="1" max="24" />

						<label class="twd-ap-checkbox-label twd-ap-spaced">
							<input type="checkbox" id="twd-ap-gs-thumbnails" />
							<?php esc_html_e( 'Show thumbnails', 'twd-article-publisher' ); ?>
						</label>

						<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-readmore-color"><?php esc_html_e( '"Read more" link colour', 'twd-article-publisher' ); ?></label>
						<input type="color" id="twd-ap-gs-readmore-color" class="twd-ap-gs-color-input" />

						<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-accent-color"><?php esc_html_e( 'Accent colour (search focus, category links)', 'twd-article-publisher' ); ?></label>
						<input type="color" id="twd-ap-gs-accent-color" class="twd-ap-gs-color-input" />

						<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-swipebook-color"><?php esc_html_e( '"View as a swipe book" button colour', 'twd-article-publisher' ); ?></label>
						<input type="color" id="twd-ap-gs-swipebook-color" class="twd-ap-gs-color-input" />

						<div class="twd-ap-section twd-ap-spaced">
							<p class="twd-ap-section-heading"><?php esc_html_e( 'Swipe Book', 'twd-article-publisher' ); ?></p>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'The heading, label, and logo shown at the top of every article\'s swipe book.', 'twd-article-publisher' ); ?></p>

							<label class="twd-ap-label" for="twd-ap-gs-sb-heading"><?php esc_html_e( 'Heading (blank uses the site name)', 'twd-article-publisher' ); ?></label>
							<input type="text" id="twd-ap-gs-sb-heading" class="twd-ap-input" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />

							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-sb-label"><?php esc_html_e( '"Swipe Book" label text', 'twd-article-publisher' ); ?></label>
							<input type="text" id="twd-ap-gs-sb-label" class="twd-ap-input" placeholder="Swipe Book" />

							<label class="twd-ap-label twd-ap-spaced"><?php esc_html_e( 'Logo (overrides the site logo on swipe books only)', 'twd-article-publisher' ); ?></label>
							<div id="twd-ap-gs-sb-logo-preview" class="twd-ap-featured-preview"></div>
							<div class="twd-ap-btn-row">
								<button type="button" id="twd-ap-gs-sb-logo-select" class="twd-ap-btn-secondary"><?php esc_html_e( 'Choose Image', 'twd-article-publisher' ); ?></button>
								<button type="button" id="twd-ap-gs-sb-logo-remove" class="twd-ap-btn-text" hidden><?php esc_html_e( 'Remove', 'twd-article-publisher' ); ?></button>
							</div>

							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-sb-logo-bg"><?php esc_html_e( 'Logo background', 'twd-article-publisher' ); ?></label>
							<select id="twd-ap-gs-sb-logo-bg" class="twd-ap-input">
								<option value="light"><?php esc_html_e( 'Light', 'twd-article-publisher' ); ?></option>
								<option value="dark"><?php esc_html_e( 'Dark', 'twd-article-publisher' ); ?></option>
								<option value="none"><?php esc_html_e( 'None', 'twd-article-publisher' ); ?></option>
							</select>

							<label class="twd-ap-checkbox-label twd-ap-spaced">
								<input type="checkbox" id="twd-ap-gs-sb-export-branding" />
								<?php esc_html_e( 'Include the heading and logo in Save as image / Download as PDF', 'twd-article-publisher' ); ?>
							</label>
						</div>

						<label class="twd-ap-label twd-ap-spaced"><?php esc_html_e( 'Category order (drag to reorder; only the top categories above show on the grid)', 'twd-article-publisher' ); ?></label>
						<ul id="twd-ap-gs-cat-order" class="twd-ap-gs-cat-order"></ul>

						<div class="twd-ap-section twd-ap-spaced">
							<p class="twd-ap-section-heading"><?php esc_html_e( 'Profile page (for the swipe book bio slide)', 'twd-article-publisher' ); ?></p>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'Shown as the closing slide on any article with "Include bio page at end" ticked.', 'twd-article-publisher' ); ?></p>

							<div class="twd-ap-field-col">
								<label class="twd-ap-label"><?php esc_html_e( 'Photo', 'twd-article-publisher' ); ?></label>
								<div id="twd-ap-gs-profile-photo-preview" class="twd-ap-featured-preview"></div>
								<div class="twd-ap-btn-row">
									<button type="button" id="twd-ap-gs-profile-photo-select" class="twd-ap-btn-secondary"><?php esc_html_e( 'Choose Image', 'twd-article-publisher' ); ?></button>
									<button type="button" id="twd-ap-gs-profile-photo-remove" class="twd-ap-btn-text" hidden><?php esc_html_e( 'Remove', 'twd-article-publisher' ); ?></button>
								</div>
							</div>

							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-profile-name"><?php esc_html_e( 'Name', 'twd-article-publisher' ); ?></label>
							<input type="text" id="twd-ap-gs-profile-name" class="twd-ap-input" placeholder="<?php esc_attr_e( 'e.g. Jane Smith, Counsellor', 'twd-article-publisher' ); ?>" />

							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-profile-bio"><?php esc_html_e( 'Bio', 'twd-article-publisher' ); ?></label>
							<textarea id="twd-ap-gs-profile-bio" class="twd-ap-textarea-small" placeholder="<?php esc_attr_e( 'A short line or two about you', 'twd-article-publisher' ); ?>"></textarea>

							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-profile-link-url"><?php esc_html_e( 'Link URL', 'twd-article-publisher' ); ?></label>
							<input type="url" id="twd-ap-gs-profile-link-url" class="twd-ap-input" placeholder="https://" />

							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-profile-link-label"><?php esc_html_e( 'Link button text', 'twd-article-publisher' ); ?></label>
							<input type="text" id="twd-ap-gs-profile-link-label" class="twd-ap-input" placeholder="<?php esc_attr_e( 'e.g. Book a session', 'twd-article-publisher' ); ?>" />
						</div>
					</div>
					<div class="twd-ap-modal-footer">
						<div class="twd-ap-footer-spacer"></div>
						<button type="button" id="twd-ap-gs-save" class="twd-ap-btn-primary"><?php esc_html_e( 'Save', 'twd-article-publisher' ); ?></button>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}
}
