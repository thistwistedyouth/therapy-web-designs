<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place an article becomes a grid card, shared by the public REST
 * routes (which feed articles-grid.js) and the [twd_articles] shortcode
 * (which prints the first batch of cards straight into the page HTML so
 * crawlers and no-JavaScript visitors see real links). Query args, card
 * data, the grouped landing view and the card markup all live here so the
 * two paths can never drift apart.
 *
 * card_html() must stay in step with cardHtml() in assets/articles-grid.js.
 */
class TWD_AP_Cards {

	/**
	 * The term ID WordPress puts a post into when none is chosen, via the
	 * default_category option -- the rename-proof way to find it, rather
	 * than matching on the literal name "Uncategorized".
	 */
	public static function uncategorized_id() {
		return (int) get_option( 'default_category' );
	}

	/**
	 * WP_Query args for the flat list: featured first, then newest. One
	 * extra post is fetched beyond what is shown, purely to answer "is
	 * there a next page" from what came back (see fetch()).
	 */
	public static function query_args( $per_page, $offset, $search = '', $category = '', $tag = '' ) {
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			// offset (not paged) keeps every page's items lined up exactly
			// count apart regardless of the +1. no_found_rows since
			// found_posts/max_num_pages are never read.
			'posts_per_page' => $per_page + 1,
			'offset'         => $offset,
			'no_found_rows'  => true,
			// A bare 'meta_key' query var is NOT a LEFT JOIN: WP_Query turns
			// it into an implicit meta_query clause that INNER JOINs
			// postmeta, which excludes every post that has no such row at
			// all (and _twd_ap_featured only exists on posts someone
			// featured). The explicit OR between "has the key" and "does
			// not have the key", a named clause, makes every post match
			// one branch or the other while staying orderable.
			'meta_query'     => array(
				'relation'        => 'OR',
				'featured_clause' => array(
					'key'     => '_twd_ap_featured',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_twd_ap_featured',
					'compare' => 'NOT EXISTS',
				),
			),
			'orderby'        => array(
				'featured_clause' => 'DESC',
				'date'            => 'DESC',
			),
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		if ( '' !== $category ) {
			$query_args['category_name'] = $category;
		}
		if ( '' !== $tag ) {
			$query_args['tag'] = $tag;
		}
		return $query_args;
	}

	/**
	 * Runs the flat-list query. Returns array( 'posts' => WP_Post[],
	 * 'has_more' => bool ). Used by the REST route and the shortcode alike.
	 */
	public static function fetch( $per_page, $offset = 0, $search = '', $category = '', $tag = '' ) {
		$per_page = max( 1, min( 24, (int) $per_page ) );
		$offset   = max( 0, (int) $offset );

		$query = new WP_Query( self::query_args( $per_page, $offset, $search, $category, $tag ) );
		$posts = $query->posts;

		$has_more = count( $posts ) > $per_page;
		if ( $has_more ) {
			$posts = array_slice( $posts, 0, $per_page );
		}
		return array(
			'posts'    => $posts,
			'has_more' => $has_more,
		);
	}

	/**
	 * How many published articles match a flat list (all of them, not just
	 * one page), for the page links under a category or curated list.
	 */
	public static function count_matching( $category = '', $tag = '' ) {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		if ( '' !== $category ) {
			$args['category_name'] = $category;
		}
		if ( '' !== $tag ) {
			$args['tag'] = $tag;
		}
		$query = new WP_Query( $args );
		return (int) $query->found_posts;
	}

	/**
	 * A post becomes a grid card. Accepts either a WP_Post or a post ID.
	 */
	public static function format( $post ) {
		$post = get_post( $post );

		$thumb_id = get_post_thumbnail_id( $post );
		$uncat_id = self::uncategorized_id();
		// The default category ("Uncategorized") is a fallback bucket, never
		// a topic, so it is never a card's label.
		$cats = array_values(
			array_filter(
				get_the_category( $post->ID ),
				function ( $c ) use ( $uncat_id ) {
					return (int) $c->term_id !== $uncat_id;
				}
			)
		);
		// "Featured Articles" is an organisational category for the grouped
		// landing view's own section heading, not a topic -- showing it as a
		// card's eyebrow reads as a sales tag. Prefer any other category,
		// falling back to it only when it is the sole one on the post.
		$topic_cats = array_values(
			array_filter(
				$cats,
				function ( $c ) {
					return 0 !== strcasecmp( $c->name, 'Featured Articles' );
				}
			)
		);
		$topic_cats = ! empty( $topic_cats ) ? $topic_cats : $cats;
		$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
		$excerpt    = $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags( $post->post_content );
		// A password-protected article must not leak its text into a public
		// card or into the page HTML.
		if ( post_password_required( $post ) ) {
			$excerpt = '';
		}

		// get_the_title() runs wptexturize, which turns a plain apostrophe
		// or a run of dots into literal entity text ("&#8217;", "&hellip;"),
		// meant for direct, unescaped HTML output. The grid escapes what it
		// is given (JS or esc_html), which would double-encode the leading
		// "&". html_entity_decode() is the general decoder (named and
		// numeric entities), so the consumer only ever encodes once.
		return array(
			'id'           => $post->ID,
			'title'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'excerpt'      => html_entity_decode( wp_trim_words( $excerpt, 22 ), ENT_QUOTES, 'UTF-8' ),
			'link'         => get_permalink( $post ),
			'date'         => get_the_date( '', $post ),
			'reading_time' => max( 1, (int) ceil( $word_count / 200 ) ),
			'thumbnail'    => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '',
			'category'     => ! empty( $topic_cats ) ? html_entity_decode( $topic_cats[0]->name, ENT_QUOTES, 'UTF-8' ) : '',
			'featured'     => (bool) get_post_meta( $post->ID, '_twd_ap_featured', true ),
			// Only used by the REST grid (the pencil is added client-side).
			'can_edit'     => current_user_can( 'edit_post', $post->ID ),
		);
	}

	/**
	 * The default landing view: published posts grouped by their primary
	 * category (never repeated across sections), top categories first. One
	 * pass over all published posts in PHP, so the "already used in an
	 * earlier section" exclusion and the true per-primary-category counts
	 * come from a single consistent source. Capped at a generous but finite
	 * number since this plugin targets a single therapist's or small
	 * practice's blog, not a high-volume publication.
	 */
	public static function grouped() {
		$settings = TWD_AP_Grid_Settings::get();

		$post_ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$by_category = array();
		foreach ( $post_ids as $post_id ) {
			$cat_ids = wp_get_post_categories( $post_id );
			if ( empty( $cat_ids ) ) {
				continue;
			}

			$primary = (int) get_post_meta( $post_id, '_twd_ap_primary_category', true );
			if ( ! $primary || ! in_array( $primary, $cat_ids, true ) ) {
				$primary = $cat_ids[0];
			}

			if ( ! isset( $by_category[ $primary ] ) ) {
				$by_category[ $primary ] = array();
			}
			$by_category[ $primary ][] = $post_id;
		}

		// WordPress's own fallback bucket for a post nobody categorised is
		// not a real content category, and letting it win a section pushes
		// out the categories that actually say something about the site. A
		// post whose only category is this one just does not show on the
		// grouped view; a search still finds it by text.
		unset( $by_category[ self::uncategorized_id() ] );

		$ordered_cat_ids = array();
		foreach ( $settings['category_order'] as $cat_id ) {
			if ( isset( $by_category[ $cat_id ] ) ) {
				$ordered_cat_ids[] = $cat_id;
			}
		}
		$remaining = array_diff( array_keys( $by_category ), $ordered_cat_ids );
		usort(
			$remaining,
			function ( $a, $b ) use ( $by_category ) {
				return count( $by_category[ $b ] ) - count( $by_category[ $a ] );
			}
		);
		$ordered_cat_ids = array_merge( $ordered_cat_ids, $remaining );
		$ordered_cat_ids = array_slice( $ordered_cat_ids, 0, $settings['categories_count'] );

		$groups = array();
		foreach ( $ordered_cat_ids as $cat_id ) {
			$term = get_category( $cat_id );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			// Featured first, otherwise the newest-first order already in
			// the list is kept as-is (array_merge, not usort, so this stays
			// stable regardless of PHP version).
			$featured = array();
			$rest     = array();
			foreach ( $by_category[ $cat_id ] as $id ) {
				if ( get_post_meta( $id, '_twd_ap_featured', true ) ) {
					$featured[] = $id;
				} else {
					$rest[] = $id;
				}
			}
			$ids = array_slice( array_merge( $featured, $rest ), 0, $settings['posts_per_category'] );

			$items = array();
			foreach ( $ids as $id ) {
				$items[] = self::format( $id );
			}

			$link = get_category_link( $cat_id );
			$groups[] = array(
				'category' => array(
					'id'   => $cat_id,
					'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
					'slug' => $term->slug,
					'link' => is_wp_error( $link ) ? '' : $link,
				),
				'posts'    => $items,
				'total'    => count( $by_category[ $cat_id ] ),
			);
		}

		return array(
			'groups'   => $groups,
			'settings' => array(
				'show_thumbnails' => (bool) $settings['show_thumbnails'],
				'read_more_color' => $settings['read_more_color'],
				'accent_color'    => $settings['accent_color'],
			),
		);
	}

	/**
	 * Real (non-default, non-empty) categories, for crawlable archive links.
	 */
	public static function topic_categories() {
		$uncat_id = self::uncategorized_id();
		$cats     = get_categories( array( 'hide_empty' => true ) );
		$cats     = array_values(
			array_filter(
				$cats,
				function ( $c ) use ( $uncat_id ) {
					return (int) $c->term_id !== $uncat_id;
				}
			)
		);
		// The owner can choose which categories the row lists (Customise
		// this grid). 'all' is the default and includes future categories.
		$settings = TWD_AP_Grid_Settings::get();
		$chosen   = isset( $settings['topic_category_ids'] ) ? $settings['topic_category_ids'] : 'all';
		if ( is_array( $chosen ) ) {
			$cats = array_values(
				array_filter(
					$cats,
					function ( $c ) use ( $chosen ) {
						return in_array( (int) $c->term_id, array_map( 'intval', $chosen ), true );
					}
				)
			);
		}
		return $cats;
	}

	// -- Markup. Mirrors articles-grid.js; every value is escaped here. ------

	const PLACEHOLDER_SVG = '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="M21 16l-5.5-5.5-4 4L8 11l-5 5" /></svg>';

	/**
	 * One card, as a.twd-ap-article-card, matching cardHtml() in the JS.
	 * The edit pencil is not part of the server HTML (it is per person and
	 * the JS adds it), so the page HTML is the same for every visitor.
	 */
	public static function card_html( $item, $show_thumbnails ) {
		$thumb = '';
		if ( $show_thumbnails ) {
			if ( ! empty( $item['thumbnail'] ) ) {
				$url   = str_replace( "'", '%27', esc_url_raw( $item['thumbnail'] ) );
				$thumb = '<span class="twd-ap-article-thumb" style="' . esc_attr( "background-image:url('" . $url . "')" ) . '"></span>';
			} else {
				$thumb = '<span class="twd-ap-article-thumb twd-ap-article-thumb-placeholder" aria-hidden="true">' . self::PLACEHOLDER_SVG . '</span>';
			}
		}
		$eyebrow = ! empty( $item['category'] ) ? esc_html( $item['category'] ) . ' &middot; ' : '';

		return '<a class="twd-ap-article-card" href="' . esc_url( $item['link'] ) . '">' .
			$thumb .
			'<span class="twd-ap-article-body">' .
				'<span class="twd-ap-article-date">' . $eyebrow . esc_html( $item['date'] ) . ' &middot; ' . (int) $item['reading_time'] . ' min read</span>' .
				'<span class="twd-ap-article-title">' . esc_html( $item['title'] ) . '</span>' .
				'<span class="twd-ap-article-excerpt">' . esc_html( $item['excerpt'] ) . '</span>' .
				'<span class="twd-ap-article-readmore">Read more</span>' .
			'</span>' .
		'</a>';
	}

	/**
	 * Flat list of cards from WP_Post objects.
	 */
	public static function cards_html( $posts, $show_thumbnails ) {
		$html = '';
		foreach ( $posts as $post ) {
			$html .= self::card_html( self::format( $post ), $show_thumbnails );
		}
		return $html;
	}

	/**
	 * The grouped landing view, matching fetchGrouped() in the JS. "See all"
	 * is a plain link to the category archive here; the JS swaps in its
	 * button when it takes over.
	 */
	public static function groups_html( $groups, $show_thumbnails ) {
		$html = '';
		foreach ( $groups as $group ) {
			$more = '';
			if ( $group['total'] > count( $group['posts'] ) && ! empty( $group['category']['link'] ) ) {
				$more = '<a class="twd-ap-articles-group-more" href="' . esc_url( $group['category']['link'] ) . '" data-slug="' . esc_attr( $group['category']['slug'] ) . '">See all</a>';
			}
			$cards = '';
			foreach ( $group['posts'] as $item ) {
				$cards .= self::card_html( $item, $show_thumbnails );
			}
			$html .= '<div class="twd-ap-articles-group">' .
				'<div class="twd-ap-articles-group-head">' .
					'<h2 class="twd-ap-articles-group-title">' . esc_html( $group['category']['name'] ) . '</h2>' .
					$more .
				'</div>' .
				'<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: 3;">' . $cards . '</div>' .
			'</div>';
		}
		return $html;
	}

	/**
	 * Real page links under a flat list (?twd_page=N, with rel prev/next) so
	 * a crawler can reach every page of a long category or curated list. The
	 * grid's JS hides them and takes over with Load more; on page 2 and
	 * beyond it keeps just the "Previous" link (see articles-grid.js).
	 * Long runs are shortened to the first, last and nearby pages.
	 */
	public static function pager_html( $page, $pages, $base ) {
		if ( $pages < 2 ) {
			return '';
		}
		$href = function ( $n ) use ( $base ) {
			return 1 === $n ? $base : add_query_arg( 'twd_page', $n, $base );
		};
		$shown = array();
		for ( $i = 1; $i <= $pages; $i++ ) {
			if ( $pages <= 9 || 1 === $i || $pages === $i || abs( $i - $page ) <= 1 ) {
				$shown[] = $i;
			}
		}
		$items = '';
		if ( $page > 1 ) {
			$items .= '<li class="twd-ap-pager-prev"><a rel="prev" href="' . esc_url( $href( $page - 1 ) ) . '">&larr; ' . esc_html__( 'Previous', 'twd-article-publisher' ) . '</a></li>';
		}
		$last = 0;
		foreach ( $shown as $n ) {
			if ( $last && $n - $last > 1 ) {
				$items .= '<li class="twd-ap-pager-gap" aria-hidden="true">&hellip;</li>';
			}
			$last = $n;
			if ( $n === $page ) {
				$items .= '<li><span aria-current="page">' . (int) $n . '</span></li>';
			} else {
				$rel    = $n === $page - 1 ? ' rel="prev"' : ( $n === $page + 1 ? ' rel="next"' : '' );
				$items .= '<li><a' . $rel . ' href="' . esc_url( $href( $n ) ) . '">' . (int) $n . '</a></li>';
			}
		}
		if ( $page < $pages ) {
			$items .= '<li class="twd-ap-pager-next"><a rel="next" href="' . esc_url( $href( $page + 1 ) ) . '">' . esc_html__( 'Next', 'twd-article-publisher' ) . ' &rarr;</a></li>';
		}
		// The inline script right after the nav marks it as "JavaScript is on"
		// before the browser has painted it, so it never flashes up and then
		// vanishes when articles-grid.js (loaded in the footer) runs. Without
		// JavaScript it never runs and the links stay. data-deep is page 2 and
		// beyond, where only the Previous link stays.
		return '<nav class="twd-ap-articles-pager"' . ( $page > 1 ? ' data-deep="1"' : '' ) . ' aria-label="' . esc_attr__( 'More articles', 'twd-article-publisher' ) . '"><ul>' . $items . '</ul></nav>' .
			'<script>document.currentScript.previousElementSibling.setAttribute("data-js","1");</script>';
	}

	/**
	 * Plain links to every real category archive page.
	 */
	public static function topics_html( $index_url = '', $current_id = 0 ) {
		$items = '';
		foreach ( self::topic_categories() as $cat ) {
			$link = get_category_link( $cat->term_id );
			if ( is_wp_error( $link ) || '' === $link ) {
				continue;
			}
			$name = esc_html( html_entity_decode( $cat->name, ENT_QUOTES, 'UTF-8' ) );
			if ( $current_id && (int) $cat->term_id === (int) $current_id ) {
				// On that category's own page: shown, not linked, so a visitor
				// can see where they are in the row.
				$items .= '<li class="twd-ap-topics-current"><span aria-current="page">' . $name . '</span></li>';
			} else {
				$items .= '<li><a href="' . esc_url( $link ) . '">' . $name . '</a></li>';
			}
		}
		if ( '' === $items ) {
			return '';
		}
		// The full alphabetical index sits at the end of the row as a quiet
		// text link: a real link for search engines, not a prominent button.
		if ( '' !== $index_url ) {
			$items .= '<li class="twd-ap-topics-all"><a href="' . esc_url( $index_url ) . '">' . esc_html__( 'All articles', 'twd-article-publisher' ) . '</a></li>';
		}
		return '<nav class="twd-ap-articles-topics" aria-label="' . esc_attr__( 'Browse by topic', 'twd-article-publisher' ) . '">' .
			'<p class="twd-ap-articles-topics-title">' . esc_html__( 'Browse by topic', 'twd-article-publisher' ) . '</p>' .
			'<ul>' . $items . '</ul>' .
		'</nav>';
	}
}
