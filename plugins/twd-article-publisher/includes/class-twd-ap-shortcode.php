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

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
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

		$config = array(
			'restUrl'     => esc_url_raw( rest_url( 'twd-publisher/v1' ) ),
			'category'    => sanitize_title( $atts['category'] ),
			'tag'         => sanitize_title( $atts['tag'] ),
			'count'       => max( 1, (int) $atts['count'] ),
			'showFilters' => $show_filters,
		);

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="twd-ap-articles" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php if ( $show_filters ) : ?>
				<div class="twd-ap-articles-toolbar">
					<input type="search" class="twd-ap-articles-search" placeholder="<?php esc_attr_e( 'Search articles…', 'twd-article-publisher' ); ?>" aria-label="<?php esc_attr_e( 'Search articles', 'twd-article-publisher' ); ?>" />
					<div class="twd-ap-articles-pills" aria-label="<?php esc_attr_e( 'Filter by category', 'twd-article-publisher' ); ?>"></div>
				</div>
			<?php endif; ?>
			<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: <?php echo esc_attr( $columns ); ?>;">
				<p class="twd-ap-muted"><?php esc_html_e( 'Loading articles…', 'twd-article-publisher' ); ?></p>
			</div>
			<p class="twd-ap-articles-empty" hidden><?php esc_html_e( 'No articles match your filters.', 'twd-article-publisher' ); ?></p>
			<div class="twd-ap-articles-loadmore-row">
				<button type="button" class="twd-ap-articles-loadmore" hidden><?php esc_html_e( 'Load more', 'twd-article-publisher' ); ?></button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
