<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [twd_articles] renders a self-contained grid whose first batch of cards is
 * printed into the page HTML (real links for crawlers and no-JavaScript
 * visitors), then taken over by JS that fetches from the public /articles
 * REST route (search, category, tag, pagination all happen client-side with
 * no page reload). When no category/tag attribute
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
				'intro'    => 'yes',
			),
			$atts,
			'twd_articles'
		);

		$on_archive = false;
		if ( '' === $atts['category'] && '' === $atts['tag'] ) {
			if ( is_category() ) {
				$atts['category'] = get_queried_object()->slug;
				$on_archive       = true;
			} elseif ( is_tag() ) {
				$atts['tag'] = get_queried_object()->slug;
				$on_archive  = true;
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

		$grid_settings  = TWD_AP_Grid_Settings::get();
		$show_thumbs    = (bool) $grid_settings['show_thumbnails'];
		$many_articles  = $total_articles > 12;
		$category       = sanitize_title( $atts['category'] );
		$tag            = sanitize_title( $atts['tag'] );
		// 24 is the most the REST route (and TWD_AP_Cards::fetch()) will return
		// per request, so the printed cards, the page links, the start offset
		// and the JS's Load more all use the same page size.
		$count          = max( 1, min( 24, (int) $atts['count'] ) );
		// A site with 12 or fewer articles shows every one of them in a single
		// flat list with no Load more (articles-grid.js applies the same rule
		// once its settings reply lands). Doing it here means the first batch
		// printed below and the JS's first fetch agree, so nothing changes
		// when the JS takes over.
		if ( ! $many_articles ) {
			$count = max( $count, min( 24, $total_articles ) );
		}

		// The first view is printed into the page itself, in the same markup
		// the JS draws, so crawlers and visitors without JavaScript get real
		// links. The JS replaces it with identical markup when it loads. Large
		// sites on the bare shortcode start grouped by category, everything
		// else starts as one flat list (see canGroup() in articles-grid.js).
		$grouped      = $show_filters && $many_articles;
		$groups_html  = '';
		$cards_html   = '';
		$pager_html   = '';
		$start_offset = 0;
		if ( $grouped ) {
			$grouped_data = TWD_AP_Cards::grouped();
			$groups_html  = TWD_AP_Cards::groups_html( $grouped_data['groups'], $show_thumbs );
		} else {
			// A long flat list (a category with more than `count` articles) is
			// split into pages with real ?twd_page=N links. The first fetch
			// asks for that page too, so a link someone follows opens on it.
			$page  = isset( $_GET['twd_page'] ) ? max( 1, absint( wp_unslash( $_GET['twd_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification -- read-only page number.
			$first = TWD_AP_Cards::fetch( $count, ( $page - 1 ) * $count, '', $category, $tag );
			$pages = 1;
			if ( $first['has_more'] || $page > 1 ) {
				$pages = max( 1, (int) ceil( TWD_AP_Cards::count_matching( $category, $tag ) / $count ) );
				if ( $page > $pages ) {
					// A page past the end shows the last page, not an empty grid.
					$page  = $pages;
					$first = TWD_AP_Cards::fetch( $count, ( $page - 1 ) * $count, '', $category, $tag );
				}
				$pager_html = TWD_AP_Cards::pager_html( $page, $pages, remove_query_arg( 'twd_page' ) );
			}
			$start_offset = ( $page - 1 ) * $count;
			$cards_html   = TWD_AP_Cards::cards_html( $first['posts'], $show_thumbs );
		}
		$has_items = ( '' !== $groups_html || '' !== $cards_html );
		// The automatic link to the full alphabetical index page (1.37.3), on
		// every grid, so a crawler can reach every article from any of them.
		$index_url = TWD_AP_Index::index_url();
		// "Browse by topic" (the categories the owner chose, then a quiet "All
		// articles" link) on the default view; a view without that row gets
		// the same quiet link on its own.
		$topics_html = $show_filters ? TWD_AP_Cards::topics_html( $index_url ) : '';

		$config = array(
			'restUrl'      => esc_url_raw( rest_url( 'twd-publisher/v1' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'category'     => $category,
			'tag'          => $tag,
			'count'        => $count,
			'showFilters'  => $show_filters,
			'isAdmin'      => $is_admin_user,
			'manyArticles' => $many_articles,
			'startOffset'  => $start_offset,
		);

		$root_classes = 'twd-ap-articles' . ( $show_thumbs ? '' : ' twd-ap-no-thumbnails' );
		// Same custom properties the JS sets once /grid-settings replies, set
		// here so the colours are right from the first paint.
		$root_style = '--twd-ap-readmore-color:' . $grid_settings['read_more_color'] . ';--twd-ap-accent-color:' . $grid_settings['accent_color'] . ';';

		// The category's own Description above the grid, on a category or tag
		// archive (switch off with intro="no" or in Customise this grid).
		$intro_html = '';
		$intro_attr = strtolower( trim( (string) $atts['intro'] ) );
		if ( $on_archive && 'no' !== $intro_attr && ( 'force' === $intro_attr || $grid_settings['intro_show'] ) ) {
			$intro_html = TWD_AP_Intro::html( get_queried_object() );
		}

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="<?php echo esc_attr( $root_classes ); ?>" style="<?php echo esc_attr( $root_style ); ?>" data-ssr="1" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php if ( ! $show_filters && $on_archive && $is_admin_user ) : ?>
				<?php // The plugin's own category page has no filters, but the owner still needs the gear to reach Customise this grid (including the switch for this page). ?>
				<div class="twd-ap-articles-toolbar twd-ap-articles-toolbar-gear">
					<button type="button" class="twd-ap-articles-settings-btn" aria-label="<?php esc_attr_e( 'Customise this grid', 'twd-article-publisher' ); ?>" title="<?php esc_attr_e( 'Customise this grid', 'twd-article-publisher' ); ?>">&#9881;</button>
				</div>
			<?php endif; ?>
			<?php if ( $show_filters ) : ?>
				<div class="twd-ap-articles-toolbar">
					<select class="twd-ap-articles-category-select" aria-label="<?php esc_attr_e( 'Jump to a category', 'twd-article-publisher' ); ?>">
						<option value=""><?php esc_html_e( 'All', 'twd-article-publisher' ); ?></option>
					</select>
					<input type="search" class="twd-ap-articles-search" placeholder="<?php esc_attr_e( 'Search articles…', 'twd-article-publisher' ); ?>" aria-label="<?php esc_attr_e( 'Search articles', 'twd-article-publisher' ); ?>" />
					<?php if ( $is_admin_user ) : ?>
						<button type="button" class="twd-ap-articles-settings-btn" aria-label="<?php esc_attr_e( 'Customise this grid', 'twd-article-publisher' ); ?>" title="<?php esc_attr_e( 'Customise this grid', 'twd-article-publisher' ); ?>">&#9881;</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in TWD_AP_Intro. ?>
			<div class="twd-ap-articles-groups"><?php echo $groups_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in TWD_AP_Cards. ?></div>
			<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: <?php echo esc_attr( $columns ); ?>;"<?php echo ( $grouped || ! $has_items ) ? ' hidden' : ''; ?>><?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in TWD_AP_Cards. ?></div>
			<p class="twd-ap-articles-empty" hidden></p>
			<p class="twd-ap-articles-none"<?php echo $has_items ? ' hidden' : ''; ?>><?php esc_html_e( 'No articles yet. Please check back soon.', 'twd-article-publisher' ); ?></p>
			<div class="twd-ap-articles-loadmore-row">
				<button type="button" class="twd-ap-articles-loadmore" hidden><?php esc_html_e( 'Load more', 'twd-article-publisher' ); ?></button>
			</div>
			<?php echo $pager_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in TWD_AP_Cards. ?>
			<?php echo $topics_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in TWD_AP_Cards. ?>
			<?php if ( '' !== $index_url && '' === $topics_html ) : ?>
				<p class="twd-ap-articles-index-link"><a href="<?php echo esc_url( $index_url ); ?>"><?php esc_html_e( 'Browse all articles', 'twd-article-publisher' ); ?></a></p>
			<?php endif; ?>
			<?php echo TWD_AP_Cta::html( $on_archive ? 'category' : 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in TWD_AP_Cta. ?>
		</div>
		<?php if ( ( $show_filters || $on_archive ) && $is_admin_user ) : ?>
			<div class="twd-ap-overlay twd-ap-grid-settings-overlay" data-for="<?php echo esc_attr( $instance_id ); ?>" hidden>
				<div class="twd-ap-modal twd-ap-grid-settings-modal">
					<div class="twd-ap-modal-header">
						<p class="twd-ap-modal-title"><?php esc_html_e( 'Customise this grid', 'twd-article-publisher' ); ?></p>
						<button type="button" class="twd-ap-close-btn twd-ap-grid-settings-close" aria-label="<?php esc_attr_e( 'Close', 'twd-article-publisher' ); ?>"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" /></svg></button>
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

						<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-accent-color"><?php esc_html_e( 'Accent colour (links, buttons and search focus)', 'twd-article-publisher' ); ?></label>
						<input type="color" id="twd-ap-gs-accent-color" class="twd-ap-gs-color-input" />

						<label class="twd-ap-label twd-ap-spaced"><?php esc_html_e( 'Categories listed under the grid ("Browse by topic")', 'twd-article-publisher' ); ?></label>
						<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'Tick the categories to list as links under the grid. Leave them all ticked to list every category, including new ones. Untick them all to hide the row.', 'twd-article-publisher' ); ?></p>
						<div id="twd-ap-gs-topics" class="twd-ap-gs-topics"></div>

						<details class="twd-ap-gs-details twd-ap-spaced" id="twd-ap-gs-intro-details">
							<summary><?php esc_html_e( 'Category pages', 'twd-article-publisher' ); ?></summary>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'A category page shows the category\'s own description above its articles. Open a category page while logged in and use "Edit intro" to write or change it, or edit it under Posts, Categories.', 'twd-article-publisher' ); ?></p>
							<label class="twd-ap-checkbox-label"><input type="checkbox" id="twd-ap-gs-archive-takeover" /> <?php esc_html_e( 'Use this grid on category and tag pages (no page-builder template needed)', 'twd-article-publisher' ); ?></label>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'The plugin shows the category name, its description with an Edit intro button, the grid and your call to action. It steps aside if your theme or page builder already has its own category template.', 'twd-article-publisher' ); ?></p>
							<label class="twd-ap-checkbox-label"><input type="checkbox" id="twd-ap-gs-intro-show" /> <?php esc_html_e( 'Also show the category description above a grid you place yourself (leave off if your theme already shows it)', 'twd-article-publisher' ); ?></label>
						</details>

						<details class="twd-ap-gs-details twd-ap-spaced" id="twd-ap-gs-cta-details">
							<summary><?php esc_html_e( 'Call to action', 'twd-article-publisher' ); ?></summary>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'A short invitation, such as booking a call, shown in a box where you choose below. Nothing shows until you add a heading, some text or a button, and tick a place.', 'twd-article-publisher' ); ?></p>
							<label class="twd-ap-label" for="twd-ap-gs-cta-heading"><?php esc_html_e( 'Heading', 'twd-article-publisher' ); ?></label>
							<input type="text" id="twd-ap-gs-cta-heading" class="twd-ap-input" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Ready to talk?', 'twd-article-publisher' ); ?>" />
							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-cta-text"><?php esc_html_e( 'Text', 'twd-article-publisher' ); ?></label>
							<textarea id="twd-ap-gs-cta-text" class="twd-ap-textarea-small" maxlength="500" placeholder="<?php esc_attr_e( 'A line or two. Plain text only.', 'twd-article-publisher' ); ?>"></textarea>
							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-cta-label"><?php esc_html_e( 'Button text', 'twd-article-publisher' ); ?></label>
							<input type="text" id="twd-ap-gs-cta-label" class="twd-ap-input" maxlength="40" placeholder="<?php esc_attr_e( 'e.g. Book a free call', 'twd-article-publisher' ); ?>" />
							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-cta-url"><?php esc_html_e( 'Button link', 'twd-article-publisher' ); ?></label>
							<input type="text" id="twd-ap-gs-cta-url" class="twd-ap-input" placeholder="https://" />
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'A web address, or mailto: or tel: for an email or phone link. The button shows only when it has both text and a link.', 'twd-article-publisher' ); ?></p>
							<p class="twd-ap-label twd-ap-spaced"><?php esc_html_e( 'Show it', 'twd-article-publisher' ); ?></p>
							<label class="twd-ap-checkbox-label"><input type="checkbox" id="twd-ap-gs-cta-grid" /> <?php esc_html_e( 'Under the resources grid', 'twd-article-publisher' ); ?></label>
							<label class="twd-ap-checkbox-label"><input type="checkbox" id="twd-ap-gs-cta-categories" /> <?php esc_html_e( 'Under a category page\'s grid', 'twd-article-publisher' ); ?></label>
							<label class="twd-ap-checkbox-label"><input type="checkbox" id="twd-ap-gs-cta-index" /> <?php esc_html_e( 'Under the article index', 'twd-article-publisher' ); ?></label>
							<label class="twd-ap-checkbox-label"><input type="checkbox" id="twd-ap-gs-cta-articles" /> <?php esc_html_e( 'At the end of every article', 'twd-article-publisher' ); ?></label>
						</details>

						<details class="twd-ap-gs-details twd-ap-spaced" id="twd-ap-gs-look-details">
							<summary><?php esc_html_e( 'Article look', 'twd-article-publisher' ); ?></summary>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'How a published article reads on this site. Leave everything as it is to keep the plugin\'s own look; change what you want to match your site.', 'twd-article-publisher' ); ?></p>
							<div class="twd-ap-gs-look-grid">
								<label class="twd-ap-label" for="twd-ap-gs-look-text"><?php esc_html_e( 'Text colour', 'twd-article-publisher' ); ?></label>
								<input type="color" id="twd-ap-gs-look-text" class="twd-ap-gs-color-input" data-look="look_text_color" data-default="#1f2937" />
								<label class="twd-ap-label" for="twd-ap-gs-look-heading"><?php esc_html_e( 'Heading colour', 'twd-article-publisher' ); ?></label>
								<input type="color" id="twd-ap-gs-look-heading" class="twd-ap-gs-color-input" data-look="look_heading_color" data-default="#1f2937" />
								<label class="twd-ap-label" for="twd-ap-gs-look-link"><?php esc_html_e( 'Link colour', 'twd-article-publisher' ); ?></label>
								<input type="color" id="twd-ap-gs-look-link" class="twd-ap-gs-color-input" data-look="look_link_color" data-default="#2563eb" />
								<label class="twd-ap-label" for="twd-ap-gs-look-quote"><?php esc_html_e( 'Quote colour', 'twd-article-publisher' ); ?></label>
								<input type="color" id="twd-ap-gs-look-quote" class="twd-ap-gs-color-input" data-look="look_quote_color" data-default="#6b7280" />
							</div>
							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-look-font"><?php esc_html_e( 'Font', 'twd-article-publisher' ); ?></label>
							<select id="twd-ap-gs-look-font" class="twd-ap-input" data-look="look_font">
								<option value="system"><?php esc_html_e( 'Plain (the plugin default)', 'twd-article-publisher' ); ?></option>
								<option value="serif"><?php esc_html_e( 'Serif (Georgia)', 'twd-article-publisher' ); ?></option>
								<option value="theme"><?php esc_html_e( 'Use my theme\'s own font', 'twd-article-publisher' ); ?></option>
							</select>
							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-look-size"><?php esc_html_e( 'Text size', 'twd-article-publisher' ); ?></label>
							<select id="twd-ap-gs-look-size" class="twd-ap-input" data-look="look_size">
								<option value="medium"><?php esc_html_e( 'Medium (default)', 'twd-article-publisher' ); ?></option>
								<option value="large"><?php esc_html_e( 'Large', 'twd-article-publisher' ); ?></option>
							</select>
							<label class="twd-ap-label twd-ap-spaced" for="twd-ap-gs-look-spacing"><?php esc_html_e( 'Line spacing', 'twd-article-publisher' ); ?></label>
							<select id="twd-ap-gs-look-spacing" class="twd-ap-input" data-look="look_spacing">
								<option value="compact"><?php esc_html_e( 'Compact', 'twd-article-publisher' ); ?></option>
								<option value="normal"><?php esc_html_e( 'Normal (default)', 'twd-article-publisher' ); ?></option>
								<option value="relaxed"><?php esc_html_e( 'Relaxed', 'twd-article-publisher' ); ?></option>
							</select>
							<div class="twd-ap-gs-look-preview" id="twd-ap-gs-look-preview" aria-hidden="true">
								<h4><?php esc_html_e( 'A sample heading', 'twd-article-publisher' ); ?></h4>
								<p><?php esc_html_e( 'This is how the text of an article will read, with a', 'twd-article-publisher' ); ?> <a href="#" tabindex="-1"><?php esc_html_e( 'link inside it', 'twd-article-publisher' ); ?></a>.</p>
								<blockquote><?php esc_html_e( 'And this is how a quotation will look.', 'twd-article-publisher' ); ?></blockquote>
							</div>
							<button type="button" id="twd-ap-gs-look-reset" class="twd-ap-btn-text"><?php esc_html_e( 'Reset to the plugin default', 'twd-article-publisher' ); ?></button>
						</details>

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
