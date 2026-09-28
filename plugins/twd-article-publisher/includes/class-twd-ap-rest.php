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
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_categories' ),
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

	private function build_post_args( $request, $existing_id = 0 ) {
		$title   = isset( $request['title'] ) ? sanitize_text_field( wp_unslash( $request['title'] ) ) : '';
		$content = isset( $request['content_html'] ) ? TWD_AP_Sanitizer::clean( wp_unslash( $request['content_html'] ) ) : '';
		$excerpt = isset( $request['excerpt'] ) ? sanitize_textarea_field( wp_unslash( $request['excerpt'] ) ) : '';
		$status  = isset( $request['status'] ) ? sanitize_key( $request['status'] ) : 'draft';

		if ( ! in_array( $status, array( 'draft', 'publish', 'future' ), true ) ) {
			$status = 'draft';
		}

		if ( '' === trim( $title ) ) {
			return new WP_Error( 'twd_ap_missing_title', __( 'Please add a title before saving.', 'twd-article-publisher' ), array( 'status' => 400 ) );
		}

		$args = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
			'post_type'    => 'post',
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
				$val = sanitize_text_field( wp_unslash( $request['yoast_title'] ) );
				if ( '' !== $val ) {
					update_post_meta( $post_id, '_yoast_wpseo_title', $val );
				} else {
					delete_post_meta( $post_id, '_yoast_wpseo_title' );
				}
			}
			if ( isset( $request['yoast_desc'] ) ) {
				$val = sanitize_textarea_field( wp_unslash( $request['yoast_desc'] ) );
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
		}

		return $data;
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
