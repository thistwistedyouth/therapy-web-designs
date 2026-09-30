<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_AP_REST {

	private static $instance = null;
	const NAMESPACE = 'twd-publisher/v1';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_categories' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_category' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tags',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_tags' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_post' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_post_for_edit' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_post' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_post' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload_media' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/articles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_articles' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/articles/facets',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_facets' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/articles/grouped',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_articles_grouped' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/grid-settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_grid_settings' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_grid_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/articles/(?P<id>\d+)/swipebook',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_swipebook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Feeds the in-page swipe book overlay (assets/swipebook-inline.js),
	 * same payload shape the standalone ?twd_ap_swipebook=1 page embeds
	 * inline for its own copy of the same JS, built by TWD_AP_Swipebook
	 * itself so both paths share one slide-splitting implementation.
	 */
	public function get_swipebook( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'twd_ap_not_found', __( 'That article could not be found.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( TWD_AP_Swipebook::instance()->get_payload( $post ) );
	}

	public function check_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'twd_ap_forbidden', __( 'You must be logged in.', 'twd-article-publisher' ), array( 'status' => 401 ) );
		}
		if ( ! TWD_AP_Frontend::current_user_allowed() ) {
			return new WP_Error( 'twd_ap_forbidden', __( 'You do not have permission to publish articles.', 'twd-article-publisher' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function check_edit_permission( $request ) {
		$allowed = $this->check_permission();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'twd_ap_forbidden', __( 'You do not have permission to edit this article.', 'twd-article-publisher' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function get_categories() {
		$cats = get_categories( array( 'hide_empty' => false ) );
		$out  = array();
		foreach ( $cats as $cat ) {
			$out[] = array(
				'id'   => $cat->term_id,
				'name' => $cat->name,
			);
		}
		return rest_ensure_response( $out );
	}

	public function create_category( $request ) {
		$name = isset( $request['name'] ) ? TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( wp_unslash( $request['name'] ) ) ) : '';
		if ( '' === trim( $name ) ) {
			return new WP_Error( 'twd_ap_missing_name', __( 'Please enter a category name.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}

		$existing = get_term_by( 'name', $name, 'category' );
		if ( $existing ) {
			return rest_ensure_response(
				array(
					'id'   => $existing->term_id,
					'name' => $existing->name,
				)
			);
		}

		$result = wp_insert_term( $name, 'category' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'id'   => (int) $result['term_id'],
				'name' => $name,
			)
		);
	}

	public function get_tags() {
		$tags = get_tags( array( 'hide_empty' => false ) );
		$out  = array();
		foreach ( $tags as $tag ) {
			$out[] = $tag->name;
		}
		return rest_ensure_response( $out );
	}

	private function build_post_args( $request, $existing_id = 0 ) {
		$title   = isset( $request['title'] ) ? TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( wp_unslash( $request['title'] ) ) ) : '';
		$content = isset( $request['content_html'] ) ? TWD_AP_Sanitizer::clean( wp_unslash( $request['content_html'] ) ) : '';
		$excerpt = isset( $request['excerpt'] ) ? TWD_AP_Sanitizer::strip_dashes( sanitize_textarea_field( wp_unslash( $request['excerpt'] ) ) ) : '';
		$status  = isset( $request['status'] ) ? sanitize_key( $request['status'] ) : 'draft';

		if ( ! in_array( $status, array( 'draft', 'publish', 'future' ), true ) ) {
			$status = 'draft';
		}

		if ( '' === trim( $title ) ) {
			return new WP_Error( 'twd_ap_missing_title', __( 'Please add a title before saving.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}

		$args = array(
			'post_title'      => $title,
			'post_content'    => $content,
			'post_excerpt'    => $excerpt,
			'post_status'     => $status,
			'post_type'       => 'post',
			// Comments and pingbacks are always off on an article saved
			// through this popup, regardless of the site's own default
			// comment setting, since a client site's article isn't meant to
			// carry a public comment thread. Applies on every save, not
			// just creation, so re-saving an older article through the
			// popup closes them too.
			'comment_status'  => 'closed',
			'ping_status'     => 'closed',
		);

		if ( 'future' === $status ) {
			$raw = isset( $request['date'] ) ? sanitize_text_field( wp_unslash( $request['date'] ) ) : '';
			// Accept the browser's datetime-local format (2026-10-01T14:30) and normalise it.
			$normalised = str_replace( 'T', ' ', $raw );
			if ( 1 === substr_count( $normalised, ':' ) ) {
				$normalised .= ':00';
			}

			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalised ) ) {
				return new WP_Error( 'twd_ap_bad_date', __( 'Please choose a valid date and time to schedule this article.', 'twd-article-publisher' ), array( 'status' => 400 ) );
			}

			$now_local = current_time( 'mysql' );
			if ( $normalised <= $now_local ) {
				return new WP_Error( 'twd_ap_bad_date', __( 'Please choose a future date and time to schedule this article.', 'twd-article-publisher' ), array( 'status' => 400 ) );
			}

			$args['post_date']     = $normalised;
			$args['post_date_gmt'] = get_gmt_from_date( $normalised );
			$args['edit_date']     = true;
		}

		if ( $existing_id ) {
			$args['ID'] = $existing_id;
		} else {
			$args['post_author'] = get_current_user_id();
		}

		return $args;
	}

	public function create_post( $request ) {
		$args = $this->build_post_args( $request );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$post_id = wp_insert_post( $args, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->after_save( $post_id, $request );

		return rest_ensure_response( $this->format_post( $post_id ) );
	}

	public function update_post( $request ) {
		$post_id = (int) $request['id'];
		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'twd_ap_not_found', __( 'That article could not be found.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}

		$args = $this->build_post_args( $request, $post_id );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$result = wp_update_post( $args, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->after_save( $post_id, $request );

		return rest_ensure_response( $this->format_post( $post_id ) );
	}

	private function after_save( $post_id, $request ) {
		// Categories.
		if ( isset( $request['category_ids'] ) && is_array( $request['category_ids'] ) ) {
			$cat_ids = array_filter( array_map( 'absint', $request['category_ids'] ) );
			if ( empty( $cat_ids ) ) {
				$settings = TWD_AP_Settings::get_settings();
				if ( ! empty( $settings['default_category'] ) ) {
					$cat_ids = array( (int) $settings['default_category'] );
				}
			}
			if ( ! empty( $cat_ids ) ) {
				wp_set_post_categories( $post_id, $cat_ids );
			}
		}

		// Primary category: which section this article appears under on
		// the grouped resources view. Falls back to the first ticked
		// category, so nothing breaks for an article saved before this
		// field existed or if the chosen primary wasn't actually ticked.
		// Never left as the site's Uncategorized (default) category while
		// a real one is also ticked: list_articles_grouped() drops that
		// whole bucket outright, so a post primary'd there vanishes from
		// the grid entirely, even though it's also ticked under a category
		// that would otherwise show it. This came up in practice for a
		// post whose only category used to be Uncategorized -- ticking an
		// additional real category left the primary stuck on Uncategorized
		// (it was already non-empty, so the old "only if unset" fallback
		// never re-picked it), and the post disappeared from the grid on
		// save despite the new category looking ticked in the popup.
		if ( ! empty( $cat_ids ) ) {
			$uncategorized_id = $this->uncategorized_id();
			$real_ids         = array_values( array_diff( $cat_ids, array( $uncategorized_id ) ) );
			$primary          = isset( $request['primary_category'] ) ? absint( $request['primary_category'] ) : 0;
			$primary_ok       = $primary && in_array( $primary, $cat_ids, true )
				&& ( $primary !== $uncategorized_id || empty( $real_ids ) );
			if ( ! $primary_ok ) {
				$primary = ! empty( $real_ids ) ? $real_ids[0] : $cat_ids[0];
			}
			update_post_meta( $post_id, '_twd_ap_primary_category', $primary );
		}

		// Tags.
		if ( isset( $request['tags'] ) ) {
			$raw_tags = TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( wp_unslash( $request['tags'] ) ) );
			$tag_names = array_filter( array_map( 'trim', explode( ',', $raw_tags ) ) );
			wp_set_post_tags( $post_id, $tag_names, false );
		}

		// Featured (pins to the top of the [twd_articles] grid).
		if ( isset( $request['featured'] ) ) {
			if ( $request['featured'] ) {
				update_post_meta( $post_id, '_twd_ap_featured', 1 );
			} else {
				delete_post_meta( $post_id, '_twd_ap_featured' );
			}
		}

		// Include bio page at end (swipe book's closing slide).
		if ( isset( $request['include_bio'] ) ) {
			if ( $request['include_bio'] ) {
				update_post_meta( $post_id, '_twd_ap_include_bio', 1 );
			} else {
				delete_post_meta( $post_id, '_twd_ap_include_bio' );
			}
		}

		// Featured image.
		if ( isset( $request['featured_media'] ) ) {
			$media_id = absint( $request['featured_media'] );
			if ( $media_id ) {
				set_post_thumbnail( $post_id, $media_id );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}

		// Yoast fields, only if Yoast SEO is active on this site.
		if ( defined( 'WPSEO_VERSION' ) ) {
			if ( isset( $request['yoast_title'] ) ) {
				$val = TWD_AP_Sanitizer::strip_dashes( sanitize_text_field( wp_unslash( $request['yoast_title'] ) ) );
				if ( '' !== $val ) {
					update_post_meta( $post_id, '_yoast_wpseo_title', $val );
				} else {
					delete_post_meta( $post_id, '_yoast_wpseo_title' );
				}
			}
			if ( isset( $request['yoast_desc'] ) ) {
				$val = TWD_AP_Sanitizer::strip_dashes( sanitize_textarea_field( wp_unslash( $request['yoast_desc'] ) ) );
				if ( '' !== $val ) {
					update_post_meta( $post_id, '_yoast_wpseo_metadesc', $val );
				} else {
					delete_post_meta( $post_id, '_yoast_wpseo_metadesc' );
				}
			}
		}
	}

	public function get_post_for_edit( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'twd_ap_not_found', __( 'That article could not be found.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->format_post( $post_id, true ) );
	}

	/**
	 * Moves the article to Trash, same as the normal Posts list "Trash"
	 * action, not a permanent delete -- recoverable from wp-admin if
	 * clicked by mistake. check_edit_permission already confirmed the
	 * current user can edit this specific post before this ever runs.
	 */
	public function delete_post( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type ) {
			return new WP_Error( 'twd_ap_not_found', __( 'That article could not be found.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}

		$result = wp_trash_post( $post_id );
		if ( ! $result ) {
			return new WP_Error( 'twd_ap_delete_failed', __( 'Could not delete that article. Please try again.', 'twd-article-publisher' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	private function format_post( $post_id, $for_edit = false ) {
		$post     = get_post( $post_id );
		$cats     = wp_get_post_categories( $post_id );
		$thumb_id = get_post_thumbnail_id( $post_id );

		$data = array(
			'id'                 => $post_id,
			'title'              => $post->post_title,
			'status'             => $post->post_status,
			'link'               => get_permalink( $post_id ),
			'category_ids'       => array_map( 'intval', $cats ),
			'featured_media'     => $thumb_id ? (int) $thumb_id : 0,
			'featured_media_url' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '',
			'excerpt'            => $post->post_excerpt,
			'date_gmt'           => $post->post_date_gmt,
		);

		if ( $for_edit ) {
			$data['content_html'] = $post->post_content;
			$data['yoast_title']  = get_post_meta( $post_id, '_yoast_wpseo_title', true );
			$data['yoast_desc']   = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			$post_tags            = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			$data['tags']         = implode( ', ', $post_tags );
			$data['featured']     = (bool) get_post_meta( $post_id, '_twd_ap_featured', true );
			$data['include_bio']  = (bool) get_post_meta( $post_id, '_twd_ap_include_bio', true );
			$primary               = (int) get_post_meta( $post_id, '_twd_ap_primary_category', true );
			$data['primary_category'] = ( $primary && in_array( $primary, $data['category_ids'], true ) ) ? $primary : ( ! empty( $data['category_ids'] ) ? $data['category_ids'][0] : 0 );
		}

		return $data;
	}

	public function list_articles( $request ) {
		// Every response here changes as soon as an article is published,
		// edited, featured or deleted, or the grid's own settings change --
		// a browser's own HTTP cache, or a host's edge/CDN cache, can and
		// does serve a stale response to a plain GET fetch() with no
		// explicit cache directive, showing old article lists (or an old
		// "Load more" decision) even once the plugin itself is updated.
		nocache_headers();
		$search   = isset( $request['search'] ) ? sanitize_text_field( wp_unslash( $request['search'] ) ) : '';
		$category = isset( $request['category'] ) ? sanitize_title( wp_unslash( $request['category'] ) ) : '';
		$tag      = isset( $request['tag'] ) ? sanitize_title( wp_unslash( $request['tag'] ) ) : '';
		$offset   = isset( $request['offset'] ) ? max( 0, (int) $request['offset'] ) : 0;
		$per_page = isset( $request['per_page'] ) ? max( 1, min( 24, (int) $request['per_page'] ) ) : 9;

		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			// One extra post is fetched beyond what's actually shown, purely
			// to answer "is there a next page" directly from what came back,
			// rather than from WP_Query's own found_posts/max_num_pages --
			// those count matching rows before the featured/not-featured OR
			// meta_query below is resolved down to one match per post, which
			// in practice reported more pages than actually existed and left
			// "Load more" showing with nothing left to load. offset (not
			// paged) keeps every page's items lined up exactly count apart
			// regardless of this +1. no_found_rows since found_posts/
			// max_num_pages are never read here any more.
			'posts_per_page' => $per_page + 1,
			'offset'         => $offset,
			'no_found_rows'  => true,
			// A bare 'meta_key' query var is NOT a LEFT JOIN: WP_Query turns it
			// into an implicit meta_query clause that INNER JOINs postmeta,
			// which excludes every post that doesn't have that meta row at
			// all. Since _twd_ap_featured only ever exists on posts someone
			// explicitly featured, that silently excluded every other post
			// from the grid. The fix is an explicit OR between "has the key"
			// and "doesn't have the key", a named clause, so every post
			// matches one branch or the other, while still being orderable
			// by whether it matched the first branch.
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

		$query = new WP_Query( $query_args );
		$posts = $query->posts;

		$has_more = count( $posts ) > $per_page;
		if ( $has_more ) {
			$posts = array_slice( $posts, 0, $per_page );
		}

		$items = array();
		foreach ( $posts as $post ) {
			$items[] = $this->format_article_card( $post );
		}

		return rest_ensure_response(
			array(
				'items'    => $items,
				'has_more' => $has_more,
			)
		);
	}

	/**
	 * The single place a post becomes a grid card, shared by list_articles()
	 * and list_articles_grouped() so both stay in sync (entity decoding,
	 * excerpt trimming, reading time). Accepts either a WP_Post or a post ID.
	 */
	private function format_article_card( $post ) {
		$post = get_post( $post );

		$thumb_id   = get_post_thumbnail_id( $post );
		$cats       = get_the_category( $post->ID );
		// "Featured Articles" is an organisational category for the grouped
		// landing view's own section heading, not a topic -- showing it as a
		// card's own eyebrow label reads as a sales tag rather than saying
		// anything about what the article covers. Prefer any other assigned
		// category (a post can carry both, with this one only as its primary
		// for grouping purposes), falling back to it only when it's the sole
		// category on the post.
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

		// get_the_title() runs wptexturize, which turns a plain apostrophe
		// or a run of dots into literal entity text ("&#8217;", "&hellip;"),
		// meant for direct, unescaped HTML output. The grid renders this via
		// JS, which re-escapes it for safety, double-encoding the leading
		// "&" and leaving the entity text itself showing on the page.
		// wp_specialchars_decode() looked like the fix but only reverses
		// &amp;/&lt;/&gt;/&quot;/&#039; -- it never touches &#8217; or
		// &hellip; at all, which is why this kept happening. html_entity_decode()
		// is the general decoder: it turns every named and numeric HTML
		// entity back into a real character, so the client only ever
		// encodes once, correctly.
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
		);
	}

	/**
	 * The default landing view: published posts grouped by their primary
	 * category (never repeated across sections), top categories first. One
	 * pass over all published posts, done in PHP rather than a per-category
	 * WP_Query loop, so the "already used in an earlier section" exclusion
	 * and the true per-primary-category counts (used for default ordering)
	 * come from a single consistent source. posts_per_page is capped at a
	 * generous but finite number since this plugin targets a single
	 * therapist's or small practice's blog, not a high-volume publication.
	 */
	public function list_articles_grouped() {
		nocache_headers();
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
		// not a real content category, and letting it win a section on the
		// landing view (as it often does, being the biggest by default)
		// pushes out the categories that actually say something about the
		// site. A post whose only category is this one just does not show
		// on the grouped view; a search still finds it by text.
		unset( $by_category[ $this->uncategorized_id() ] );

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
			// $ids is kept as-is (array_merge, not usort, so this stays
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
				$items[] = $this->format_article_card( $id );
			}

			$groups[] = array(
				'category' => array(
					'id'   => $cat_id,
					'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
					'slug' => $term->slug,
				),
				'posts'    => $items,
				'total'    => count( $by_category[ $cat_id ] ),
			);
		}

		return rest_ensure_response(
			array(
				'groups'   => $groups,
				'settings' => array(
					'show_thumbnails' => (bool) $settings['show_thumbnails'],
					'read_more_color' => $settings['read_more_color'],
					'accent_color'    => $settings['accent_color'],
				),
			)
		);
	}

	/**
	 * The term ID WordPress puts a post into when none is chosen, via the
	 * default_category option -- the correct, rename-proof way to find it,
	 * rather than matching on the literal name "Uncategorized".
	 */
	private function uncategorized_id() {
		return (int) get_option( 'default_category' );
	}

	public function get_grid_settings() {
		nocache_headers();
		$settings     = TWD_AP_Grid_Settings::get();
		$uncat_id     = $this->uncategorized_id();
		$all_cats     = get_categories( array( 'hide_empty' => true ) );
		$categories   = array_values(
			array_filter(
				$all_cats,
				function ( $c ) use ( $uncat_id ) {
					return (int) $c->term_id !== $uncat_id;
				}
			)
		);

		$order = array();
		foreach ( $settings['category_order'] as $cat_id ) {
			foreach ( $categories as $c ) {
				if ( (int) $c->term_id === (int) $cat_id ) {
					$order[] = array( 'id' => $cat_id, 'name' => html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' ) );
					break;
				}
			}
		}
		$ordered_ids = wp_list_pluck( $order, 'id' );
		foreach ( $categories as $c ) {
			if ( ! in_array( (int) $c->term_id, $ordered_ids, true ) ) {
				$order[] = array( 'id' => (int) $c->term_id, 'name' => html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' ) );
			}
		}

		$profile_photo_url = '';
		if ( ! empty( $settings['profile_photo_id'] ) ) {
			$profile_photo_url = wp_get_attachment_image_url( (int) $settings['profile_photo_id'], 'medium' );
		}

		return rest_ensure_response(
			array(
				'categories_count'    => $settings['categories_count'],
				'posts_per_category'  => $settings['posts_per_category'],
				'show_thumbnails'     => (bool) $settings['show_thumbnails'],
				'read_more_color'     => $settings['read_more_color'],
				'accent_color'        => $settings['accent_color'],
				'swipebook_color'     => $settings['swipebook_color'],
				'categories'          => $order,
				'profile_photo_id'    => (int) $settings['profile_photo_id'],
				'profile_photo_url'   => $profile_photo_url ? $profile_photo_url : '',
				'profile_name'        => $settings['profile_name'],
				'profile_bio'         => $settings['profile_bio'],
				'profile_link_url'    => $settings['profile_link_url'],
				'profile_link_label'  => $settings['profile_link_label'],
				// The grid's own decision (categories vs. one flat list, ever
				// showing Load more) uses this: a handful of articles reads
				// better as one plain list than split into thin, mostly-empty
				// category sections.
				'total_articles'      => (int) wp_count_posts( 'post' )->publish,
			)
		);
	}

	public function save_grid_settings( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		TWD_AP_Grid_Settings::save( $params );
		return rest_ensure_response( array( 'saved' => true ) );
	}

	public function list_facets() {
		nocache_headers();
		$uncat_id = $this->uncategorized_id();
		$cats     = array_values(
			array_filter(
				get_categories( array( 'hide_empty' => true ) ),
				function ( $c ) use ( $uncat_id ) {
					return (int) $c->term_id !== $uncat_id;
				}
			)
		);
		$tags = get_tags( array( 'hide_empty' => true ) );

		return rest_ensure_response(
			array(
				'categories' => array_map(
					function ( $c ) {
						return array(
							'name'  => $c->name,
							'slug'  => $c->slug,
							'count' => (int) $c->count,
						);
					},
					$cats
				),
				'tags'       => array_map(
					function ( $t ) {
						return array(
							'name' => $t->name,
							'slug' => $t->slug,
						);
					},
					$tags
				),
			)
		);
	}

	public function upload_media( $request ) {
		if ( empty( $_FILES['file'] ) ) {
			return new WP_Error( 'twd_ap_no_file', __( 'No image was received.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}

		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $_FILES['file']['type'], $allowed_types, true ) ) {
			return new WP_Error( 'twd_ap_bad_type', __( 'Please upload a JPG, PNG, GIF or WebP image.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}

		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$attachment_id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return rest_ensure_response(
			array(
				'id'  => $attachment_id,
				'url' => wp_get_attachment_image_url( $attachment_id, 'medium' ),
			)
		);
	}
}
