<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [twd_articles_index]: a plain, crawlable index of every published article.
 * No JavaScript, no cards: each article is one link (title and date), listed
 * alphabetically under its category, with the categories themselves in
 * alphabetical order and "Other articles" last. It exists so search engines
 * (and anyone without JavaScript) can reach every article from one page, since
 * the grid only ever shows the first batch.
 *
 * Also owns the "Article index" page the plugin creates for it, and the URL
 * the grid links to ("See the full index of articles").
 */
class TWD_AP_Index {

	const SHORTCODE     = 'twd_articles_index';
	const OPTION_PAGE   = 'twd_ap_index_page_id';
	const QUERY_VAR     = 'twd_idx';
	// Only a site bigger than this is split over several pages, and then by
	// whole categories, about PER_PAGE articles to a page.
	const PAGINATE_OVER = 300;
	const PER_PAGE      = 100;
	// A generous but finite ceiling, like the grouped view, since this plugin
	// targets a single therapist's or small practice's blog.
	const MAX_ARTICLES  = 2000;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_page' ) );
	}

	// -- The page the grid links to ------------------------------------------

	/**
	 * Permalink of the index page, or '' when there is none (never created,
	 * trashed, or not published), in which case the grid shows no link.
	 */
	public static function index_url() {
		$id = (int) get_option( self::OPTION_PAGE );
		if ( ! $id || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		$url = get_permalink( $id );
		return $url ? $url : '';
	}

	/**
	 * Once per site, the first time an administrator loads wp-admin after
	 * this version is installed (activation hooks do not run on an update):
	 * adopt a page that already carries the shortcode, or create a published
	 * "Article index" page that does. The stored page ID means it is never
	 * created twice, and a page that is later trashed or unpublished is not
	 * brought back (the grid link simply disappears).
	 */
	public function maybe_create_page() {
		if ( false !== get_option( self::OPTION_PAGE, false ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				's'              => '[' . self::SHORTCODE,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $existing ) ) {
			update_option( self::OPTION_PAGE, (int) $existing[0] );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'     => __( 'Article index', 'twd-article-publisher' ),
				'post_name'      => 'article-index',
				'post_content'   => '<p>' . esc_html__( 'Every article on this site, listed alphabetically by topic.', 'twd-article-publisher' ) . '</p>[' . self::SHORTCODE . ']',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);
		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( self::OPTION_PAGE, (int) $page_id );
		}
	}

	// -- The shortcode -------------------------------------------------------

	public function render() {
		$sections = self::sections();
		if ( empty( $sections ) ) {
			return '<div class="twd-ap-index"><p class="twd-ap-index-none">' . esc_html__( 'No articles yet. Please check back soon.', 'twd-article-publisher' ) . '</p></div>';
		}

		$total = 0;
		foreach ( $sections as $section ) {
			$total += count( $section['items'] );
		}
		$pages = self::split_pages( $sections, $total );
		$page  = isset( $_GET[ self::QUERY_VAR ] ) ? absint( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification -- read-only page number.
		$page  = max( 1, min( count( $pages ), $page ) );
		$show  = $pages[ $page - 1 ];

		// The same saved accent colour the grid uses, so the links match the site.
		$grid_settings = TWD_AP_Grid_Settings::get();
		$html          = '<div class="twd-ap-index" style="' . esc_attr( '--twd-ap-accent-color:' . $grid_settings['accent_color'] . ';' ) . '">';
		if ( count( $show ) > 2 ) {
			$jump    = '';
			$options = '<option value="">' . esc_html__( 'All topics', 'twd-article-publisher' ) . '</option>';
			foreach ( $show as $section ) {
				$jump    .= '<li><a href="#' . esc_attr( $section['id'] ) . '">' . esc_html( $section['name'] ) . '</a></li>';
				$options .= '<option value="' . esc_attr( $section['id'] ) . '">' . esc_html( $section['name'] ) . ' (' . count( $section['items'] ) . ')</option>';
			}
			// Plain jump links for crawlers and no-JavaScript visitors. When
			// the JS runs it swaps them for the dropdown below, which shows
			// one topic at a time (articles-grid.js, initIndex()).
			$html .= '<nav class="twd-ap-index-jump" aria-label="' . esc_attr__( 'Jump to a topic', 'twd-article-publisher' ) . '"><ul>' . $jump . '</ul></nav>';
			$html .= '<div class="twd-ap-index-filter" hidden><select aria-label="' . esc_attr__( 'Choose a topic', 'twd-article-publisher' ) . '">' . $options . '</select></div>';
		}
		foreach ( $show as $section ) {
			$heading = esc_html( $section['name'] );
			if ( '' !== $section['link'] ) {
				$heading = '<a href="' . esc_url( $section['link'] ) . '">' . $heading . '</a>';
			}
			$items = '';
			foreach ( $section['items'] as $item ) {
				$items .= '<li><a href="' . esc_url( $item['link'] ) . '">' . esc_html( $item['title'] ) . '</a> <span class="twd-ap-index-date">' . esc_html( $item['date'] ) . '</span></li>';
			}
			$html .= '<section class="twd-ap-index-section" id="' . esc_attr( $section['id'] ) . '">' .
				'<h2 class="twd-ap-index-title">' . $heading . ' <span class="twd-ap-index-count">(' . count( $section['items'] ) . ')</span></h2>' .
				'<ul class="twd-ap-index-list">' . $items . '</ul>' .
			'</section>';
		}
		if ( count( $pages ) > 1 ) {
			$html .= self::pager_html( $page, count( $pages ) );
		}
		return $html . TWD_AP_Cta::html( 'index' ) . '</div>';
	}

	/**
	 * Every published article once, under its primary category (the one the
	 * grouped grid uses; a "Featured Articles" primary yields to a real topic
	 * when the article has one). Categories alphabetical, "Other articles"
	 * (no real category) last; titles alphabetical within each.
	 */
	public static function sections() {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_ARTICLES,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$uncat_id = TWD_AP_Cards::uncategorized_id();
		$by_cat   = array();
		foreach ( $posts as $post ) {
			$cat_ids = array_values( array_diff( array_map( 'intval', wp_get_post_categories( $post->ID ) ), array( $uncat_id ) ) );
			$cat_id  = 0;
			if ( ! empty( $cat_ids ) ) {
				$primary = (int) get_post_meta( $post->ID, '_twd_ap_primary_category', true );
				$cat_id  = in_array( $primary, $cat_ids, true ) ? $primary : $cat_ids[0];
				if ( self::is_featured_bucket( $cat_id ) ) {
					foreach ( $cat_ids as $other ) {
						if ( ! self::is_featured_bucket( $other ) ) {
							$cat_id = $other;
							break;
						}
					}
				}
			}
			$by_cat[ $cat_id ][] = array(
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'link'  => get_permalink( $post ),
				'date'  => get_the_date( '', $post ),
			);
		}

		$sections = array();
		foreach ( $by_cat as $cat_id => $items ) {
			usort(
				$items,
				function ( $a, $b ) {
					return strnatcasecmp( $a['title'], $b['title'] );
				}
			);
			$term = $cat_id ? get_category( $cat_id ) : null;
			if ( $cat_id && ( ! $term || is_wp_error( $term ) ) ) {
				$cat_id = 0;
				$term   = null;
			}
			$link = '';
			if ( $term ) {
				$got  = get_category_link( $cat_id );
				$link = is_wp_error( $got ) ? '' : $got;
			}
			$sections[] = array(
				'id'    => $term ? 'twd-ap-index-' . $term->slug : 'twd-ap-index-other',
				'name'  => $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : __( 'Other articles', 'twd-article-publisher' ),
				'link'  => $link,
				'other' => ! $term,
				'items' => $items,
			);
		}
		usort(
			$sections,
			function ( $a, $b ) {
				if ( $a['other'] !== $b['other'] ) {
					return $a['other'] ? 1 : -1;
				}
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);
		return $sections;
	}

	private static function is_featured_bucket( $cat_id ) {
		$term = get_category( $cat_id );
		return $term && ! is_wp_error( $term ) && 0 === strcasecmp( $term->name, 'Featured Articles' );
	}

	/**
	 * One page for most sites; a very large one is split into runs of whole
	 * categories of about PER_PAGE articles each.
	 */
	public static function split_pages( $sections, $total ) {
		if ( $total <= self::PAGINATE_OVER ) {
			return array( $sections );
		}
		$pages   = array();
		$current = array();
		$count   = 0;
		foreach ( $sections as $section ) {
			if ( ! empty( $current ) && $count >= self::PER_PAGE ) {
				$pages[] = $current;
				$current = array();
				$count   = 0;
			}
			$current[] = $section;
			$count    += count( $section['items'] );
		}
		if ( ! empty( $current ) ) {
			$pages[] = $current;
		}
		return $pages;
	}

	private static function pager_html( $page, $pages ) {
		$base  = get_permalink();
		$base  = $base ? $base : '';
		$links = '';
		for ( $i = 1; $i <= $pages; $i++ ) {
			$href = 1 === $i ? $base : add_query_arg( self::QUERY_VAR, $i, $base );
			$rel  = $i === $page - 1 ? ' rel="prev"' : ( $i === $page + 1 ? ' rel="next"' : '' );
			if ( $i === $page ) {
				$links .= '<li><span aria-current="page">' . (int) $i . '</span></li>';
			} else {
				$links .= '<li><a href="' . esc_url( $href ) . '"' . $rel . '>' . (int) $i . '</a></li>';
			}
		}
		return '<nav class="twd-ap-index-pager" aria-label="' . esc_attr__( 'Index pages', 'twd-article-publisher' ) . '"><ul>' . $links . '</ul></nav>';
	}
}
