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
			'/term-intro',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_term_intro' ),
				'permission_callback' => array( $this, 'check_permission' ),
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

		// Summary Book: the curated, reviewed-before-publish deck, entirely
		// separate from the swipe book routes above. Edit-gated like
		// /posts/{id} -- this is an authoring surface, not a public one.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/summary-book',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_summary_book' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_summary_book_draft' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/summary-book/publish',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'publish_summary_book' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		// Public read of a published Summary Book, mirroring /articles/{id}/swipebook
		// above -- feeds the in-page overlay (swipebook-inline.js), which needs
		// no login to open a book someone already chose to publish.
		register_rest_route(
			self::NAMESPACE,
			'/articles/(?P<id>\d+)/summary-book',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_summary_book_public' ),
				'permission_callback' => '__return_true',
			)
		);

		// Reads a Book Maker style JSON (title and typed cards) and returns what
		// the popup fills in: article text, a short description and a Summary
		// Book draft. Saves nothing; same gate as /generate (any allowed
		// editor, no post to own yet).
		register_rest_route(
			self::NAMESPACE,
			'/book-import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_book' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Generates an article directly on this site with the client's own
		// Anthropic key (Settings > Article Publisher) -- gated the same
		// way category creation is (any allowed editor, no specific post
		// to own yet), not edit_post, since nothing has been created yet.
		register_rest_route(
			self::NAMESPACE,
			'/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate_article' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function import_book( $request ) {
		$result = TWD_AP_Book_Import::convert( $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function generate_article( $request ) {
		if ( ! TWD_AP_AI_Generate::is_configured() ) {
			return new WP_Error( 'twd_ap_ai_not_configured', __( 'No API key is set up for this site yet. Add one under Settings > Article Publisher, or use Article Assist instead.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}
		if ( TWD_AP_AI_Generate::count() >= TWD_AP_AI_Generate::RATE_LIMIT ) {
			return new WP_Error( 'twd_ap_ai_rate_limited', __( 'You have reached today\'s generation limit on this site. Try again tomorrow.', 'twd-article-publisher' ), array( 'status' => 429 ) );
		}

		$mode  = ( isset( $request['mode'] ) && in_array( $request['mode'], array( 'idea', 'improve', 'format' ), true ) ) ? $request['mode'] : 'idea';
		$input = isset( $request['input'] ) ? trim( sanitize_textarea_field( wp_unslash( $request['input'] ) ) ) : '';

		if ( '' === $input ) {
			return new WP_Error(
				'twd_ap_ai_empty_input',
				'idea' === $mode ? __( 'Please add an idea or topic first.', 'twd-article-publisher' ) : __( 'Please add your article text first.', 'twd-article-publisher' ),
				array( 'status' => 400 )
			);
		}

		// Too long is refused, never silently cut: cutting the end off a
		// finished article would format only the first part and look like
		// it had worked.
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $input ) : strlen( $input );
		if ( $length > TWD_AP_AI_Generate::MAX_INPUT_CHARS ) {
			return new WP_Error(
				'twd_ap_ai_too_long',
				sprintf(
					/* translators: 1: characters entered, 2: maximum characters allowed */
					__( 'That is too long (%1$s characters). The limit is %2$s. Please shorten it or split it into two articles.', 'twd-article-publisher' ),
					number_format_i18n( $length ),
					number_format_i18n( TWD_AP_AI_Generate::MAX_INPUT_CHARS )
				),
				array( 'status' => 400 )
			);
		}

		$result = TWD_AP_AI_Generate::generate( $mode, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		TWD_AP_AI_Generate::increment();
		$result['ai_remaining'] = TWD_AP_AI_Generate::remaining();

		return rest_ensure_response( $result );
	}

	public function get_summary_book( $request ) {
		$post_id = (int) $request['id'];
		return rest_ensure_response(
			array(
				'draft'     => TWD_AP_Summary_Book::get_draft( $post_id ),
				'published' => TWD_AP_Summary_Book::get_published( $post_id ),
			)
		);
	}

	public function save_summary_book_draft( $request ) {
		$post_id = (int) $request['id'];
		$params  = $request->get_json_params();
		$cards   = ( is_array( $params ) && isset( $params['cards'] ) && is_array( $params['cards'] ) ) ? $params['cards'] : array();
		$saved   = TWD_AP_Summary_Book::save_draft( $post_id, $cards );
		return rest_ensure_response( array( 'draft' => $saved ) );
	}

	public function publish_summary_book( $request ) {
		$post_id = (int) $request['id'];
		$params  = $request->get_json_params();
		$cards   = ( is_array( $params ) && isset( $params['cards'] ) && is_array( $params['cards'] ) ) ? $params['cards'] : array();
		$result  = TWD_AP_Summary_Book::publish( $post_id, $cards );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'published' => $result ) );
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
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || ! TWD_AP_Swipebook::is_enabled( $post_id ) ) {
			return new WP_Error( 'twd_ap_not_found', __( 'That article could not be found.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( TWD_AP_Swipebook::instance()->get_payload( $post ) );
	}

	/**
	 * Feeds the in-page Summary Book overlay, same pattern as get_swipebook()
	 * above. Only ever returns a payload when a Summary Book has actually
	 * been published for this article -- an unpublished or never-generated
	 * one is a 404, not an empty book.
	 */
	public function get_summary_book_public( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || ! TWD_AP_Swipebook::is_enabled( $post_id ) ) {
			return new WP_Error( 'twd_ap_not_found', __( 'That article could not be found.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}
		if ( ! TWD_AP_Summary_Book::is_published( $post_id ) ) {
			return new WP_Error( 'twd_ap_not_found', __( 'No summary book has been published for this article yet.', 'twd-article-publisher' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( TWD_AP_Summary_Book::instance()->get_payload( $post ) );
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

	/**
	 * Saves the Description of a category or tag from the "Edit intro" popup
	 * on a category page (see TWD_AP_Intro::save() for the checks).
	 */
	public function save_term_intro( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$result = TWD_AP_Intro::save(
			isset( $params['term_id'] ) ? $params['term_id'] : 0,
			isset( $params['taxonomy'] ) ? sanitize_key( $params['taxonomy'] ) : '',
			isset( $params['description'] ) ? $params['description'] : ''
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
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

		// Grid number: where this article sits among the featured ones (1 is
		// first). Blank, zero or anything that is not a whole number clears it.
		// A save that does not carry the field leaves the stored number alone.
		if ( isset( $request['grid_order'] ) ) {
			$grid_order = TWD_AP_Cards::clean_grid_order( $request['grid_order'] );
			if ( $grid_order > 0 ) {
				update_post_meta( $post_id, TWD_AP_Cards::ORDER_META, $grid_order );
			} else {
				delete_post_meta( $post_id, TWD_AP_Cards::ORDER_META );
			}
		}

		// Include bio page at end (swipe book's closing slide). Only a choice that
		// differs from the site-wide default is stored (see TWD_AP_Swipebook::set_bio_choice()).
		if ( isset( $request['include_bio'] ) ) {
			TWD_AP_Swipebook::set_bio_choice( $post_id, (bool) $request['include_bio'] );
		}

		// Per-article swipe book switch. Absent means "this save did not
		// carry the switch" (an older client, an API call): leave it alone.
		// Off is stored as meta; on removes it, so articles that existed
		// before this switch stay on.
		if ( isset( $request['swipebook_enabled'] ) ) {
			if ( $request['swipebook_enabled'] ) {
				delete_post_meta( $post_id, TWD_AP_Swipebook::META_OFF );
			} else {
				update_post_meta( $post_id, TWD_AP_Swipebook::META_OFF, 1 );
			}
		}

		// "More articles" closing slide: up to 6 manually chosen articles.
		// Empty/absent means the swipe book falls back to the 6 most
		// recent other articles automatically -- see build_related_slide()
		// in TWD_AP_Swipebook, which is where that fallback actually lives,
		// not here. This only stores the manual override, when one exists.
		if ( isset( $request['related_ids'] ) && is_array( $request['related_ids'] ) ) {
			$related_ids = array_slice( array_values( array_filter( array_map( 'absint', $request['related_ids'] ) ) ), 0, 6 );
			if ( ! empty( $related_ids ) ) {
				update_post_meta( $post_id, '_twd_ap_related_ids', $related_ids );
			} else {
				delete_post_meta( $post_id, '_twd_ap_related_ids' );
			}
		}

		// Summary Book draft, carried in from a pasted JSON block's own
		// summary_book.slides (Article Assist bakes this in alongside the
		// article itself). Only ever writes the draft -- saving the article
		// never publishes or touches an already-published Summary Book;
		// that only happens through the dedicated /summary-book/publish
		// route, a deliberate separate action a therapist takes after
		// reviewing it. Absent means "this save didn't carry a new draft,"
		// not "clear the existing one" -- re-saving the article for an
		// unrelated edit must never wipe out a draft from an earlier paste.
		if ( isset( $request['summary_book_draft'] ) && is_array( $request['summary_book_draft'] ) ) {
			TWD_AP_Summary_Book::save_draft( $post_id, $request['summary_book_draft'] );
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
			$data['edit_url']     = (string) get_edit_post_link( $post_id, '' );
			$data['yoast_title']  = get_post_meta( $post_id, '_yoast_wpseo_title', true );
			$data['yoast_desc']   = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			$post_tags            = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			$data['tags']         = implode( ', ', $post_tags );
			$data['featured']     = (bool) get_post_meta( $post_id, '_twd_ap_featured', true );
			$data['grid_order']   = TWD_AP_Cards::grid_order( $post_id );
			$data['include_bio']  = TWD_AP_Swipebook::bio_included( $post_id );
			$data['swipebook_enabled'] = TWD_AP_Swipebook::is_enabled( $post_id );
			$related_ids          = get_post_meta( $post_id, '_twd_ap_related_ids', true );
			$data['related_ids']  = is_array( $related_ids ) ? array_map( 'intval', $related_ids ) : array();
			$primary               = (int) get_post_meta( $post_id, '_twd_ap_primary_category', true );
			$data['primary_category'] = ( $primary && in_array( $primary, $data['category_ids'], true ) ) ? $primary : ( ! empty( $data['category_ids'] ) ? $data['category_ids'][0] : 0 );
			$data['summary_book_has_draft']     = ! empty( TWD_AP_Summary_Book::get_draft( $post_id ) );
			$data['summary_book_is_published']  = TWD_AP_Summary_Book::is_published( $post_id );
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

		// The query and the card shape live in TWD_AP_Cards, shared with the
		// [twd_articles] shortcode, which prints the first batch into the
		// page HTML.
		$result = TWD_AP_Cards::fetch( $per_page, $offset, $search, $category, $tag );

		$items = array();
		foreach ( $result['posts'] as $post ) {
			$items[] = TWD_AP_Cards::format( $post );
		}

		return rest_ensure_response(
			array(
				'items'    => $items,
				'has_more' => $result['has_more'],
			)
		);
	}

	/**
	 * The default landing view (see TWD_AP_Cards::grouped()).
	 */
	public function list_articles_grouped() {
		nocache_headers();
		return rest_ensure_response( TWD_AP_Cards::grouped() );
	}

	/**
	 * The term ID WordPress puts a post into when none is chosen, via the
	 * default_category option -- the correct, rename-proof way to find it,
	 * rather than matching on the literal name "Uncategorized".
	 */
	private function uncategorized_id() {
		return TWD_AP_Cards::uncategorized_id();
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

		$swipebook_logo_url = '';
		if ( ! empty( $settings['swipebook_logo_id'] ) ) {
			$swipebook_logo_url = wp_get_attachment_image_url( (int) $settings['swipebook_logo_id'], 'medium' );
		}

		return rest_ensure_response(
			array(
				'categories_count'    => $settings['categories_count'],
				'posts_per_category'  => $settings['posts_per_category'],
				'show_thumbnails'     => (bool) $settings['show_thumbnails'],
				'thumb_shape'         => in_array( $settings['thumb_shape'], TWD_AP_Grid_Settings::THUMB_SHAPES, true ) ? $settings['thumb_shape'] : 'wide',
				'read_more_color'     => $settings['read_more_color'],
				'accent_color'        => $settings['accent_color'],
				'topic_category_ids'  => $settings['topic_category_ids'],
				'intro_show'          => (bool) $settings['intro_show'],
				'archive_takeover'    => (bool) $settings['archive_takeover'],
				'related_show'        => (bool) $settings['related_show'],
				'related_topics'      => (bool) $settings['related_topics'],
				'swipebook_button_position' => $settings['swipebook_button_position'],
				'swipebook_top_shortcode' => (bool) $settings['swipebook_top_shortcode'],
				'related_count'       => (int) $settings['related_count'],
				'related_place'       => in_array( $settings['related_place'], TWD_AP_Grid_Settings::RELATED_PLACES, true ) ? $settings['related_place'] : 'auto',
				'cta_heading'         => $settings['cta_heading'],
				'cta_text'            => $settings['cta_text'],
				'cta_button_label'    => $settings['cta_button_label'],
				'cta_button_url'      => $settings['cta_button_url'],
				'cta_show_grid'       => (bool) $settings['cta_show_grid'],
				'cta_show_categories' => (bool) $settings['cta_show_categories'],
				'cta_show_index'      => (bool) $settings['cta_show_index'],
				'cta_show_articles'   => (bool) $settings['cta_show_articles'],
				'look_text_color'     => $settings['look_text_color'],
				'look_heading_color'  => $settings['look_heading_color'],
				'look_link_color'     => $settings['look_link_color'],
				'look_quote_color'    => $settings['look_quote_color'],
				'look_font'           => $settings['look_font'],
				'look_size'           => $settings['look_size'],
				'look_spacing'        => $settings['look_spacing'],
				'look_tidy_lines'     => (bool) $settings['look_tidy_lines'],
				'look_h2_size'        => (int) $settings['look_h2_size'],
				'look_h3_size'        => (int) $settings['look_h3_size'],
				'look_h4_size'        => (int) $settings['look_h4_size'],
				'topics_align'        => in_array( $settings['topics_align'], TWD_AP_Grid_Settings::TOPICS_ALIGNS, true ) ? $settings['topics_align'] : 'left',
				'related_layout'      => in_array( $settings['related_layout'], TWD_AP_Grid_Settings::RELATED_LAYOUTS, true ) ? $settings['related_layout'] : 'list',
				'profile_in_all_books' => (bool) $settings['profile_in_all_books'],
				'swipebook_color'     => $settings['swipebook_color'],
				'swipebook_heading'   => $settings['swipebook_heading'],
				'swipebook_label'     => $settings['swipebook_label'],
				'swipebook_logo_id'   => (int) $settings['swipebook_logo_id'],
				'swipebook_logo_url'  => $swipebook_logo_url ? $swipebook_logo_url : '',
				'swipebook_logo_bg'   => $settings['swipebook_logo_bg'],
				'swipebook_theme'     => in_array( $settings['swipebook_theme'], TWD_AP_Grid_Settings::SWIPEBOOK_THEMES, true ) ? $settings['swipebook_theme'] : 'dark',
				'swipebook_export_branding' => (bool) $settings['swipebook_export_branding'],
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
