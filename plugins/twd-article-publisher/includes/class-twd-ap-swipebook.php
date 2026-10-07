<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders any published article as a shareable, swipeable slide deck. Two
 * ways in: a full standalone page at ?twd_ap_swipebook=1 on the article's
 * own permalink (for a shared link opened fresh, no other page behind it to
 * show), and an in-page overlay opened by JS over the resources/article page
 * the visitor is already on (assets/swipebook-inline.js, fed by the
 * /articles/{id}/swipebook REST route) -- the button under an article uses
 * the overlay when JS runs, and falls back to the full page if it can't.
 * No new content is stored either way: slides are built from the live post
 * content every time, so a swipe book always matches the article. Splitting
 * is done in plain PHP (DOMDocument, chunked by heading and length), not by
 * an AI call, since this plugin runs on many client sites and none of them
 * carry an Anthropic key of their own.
 */
class TWD_AP_Swipebook {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		// Priority 15: before TWD_AP_Article_Style::wrap_content() (20), so
		// the pill lands inside that centred, styled wrapper along with the
		// rest of the content, instead of getting prepended outside it --
		// which left it full-width against the page's actual left edge,
		// disconnected from the article column entirely.
		add_filter( 'the_content', array( $this, 'append_button' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_inline' ) );
	}

	public function register_query_var( $vars ) {
		$vars[] = 'twd_ap_swipebook';
		return $vars;
	}

	public static function url_for( $post ) {
		return add_query_arg( 'twd_ap_swipebook', '1', get_permalink( $post ) );
	}

	// Stored only when an article has the swipe book switched OFF, so every
	// article that existed before this switch (no meta at all) keeps its
	// swipe book exactly as before.
	const META_OFF = '_twd_ap_swipebook_off';

	/**
	 * The per-article "Offer a swipe book" switch. Off hides the swipe book
	 * button, the Summary Book button, both standalone pages, both public
	 * data feeds, and the article's slot in other articles' "More Articles"
	 * slide.
	 */
	public static function is_enabled( $post_id ) {
		return '1' !== (string) get_post_meta( (int) $post_id, self::META_OFF, true );
	}

	public function maybe_render() {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		if ( '1' !== (string) get_query_var( 'twd_ap_swipebook' ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status || ! self::is_enabled( $post->ID ) ) {
			return;
		}

		self::render_standalone_page( $post, $this->get_payload( $post ) );
		exit;
	}

	/**
	 * The overlay script only needs to load on a page that could actually
	 * show the button (a single published article), not site-wide.
	 */
	public function maybe_enqueue_inline() {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		if ( '1' === (string) get_query_var( 'twd_ap_swipebook' ) ) {
			return;
		}

		wp_enqueue_style(
			'twd-ap-swipebook',
			TWD_AP_URL . 'assets/swipebook.css',
			array(),
			TWD_AP_VERSION
		);
		wp_enqueue_script(
			'twd-ap-swipebook',
			TWD_AP_URL . 'assets/swipebook.js',
			array(),
			TWD_AP_VERSION,
			true
		);
		wp_enqueue_script(
			'twd-ap-swipebook-inline',
			TWD_AP_URL . 'assets/swipebook-inline.js',
			array( 'twd-ap-swipebook' ),
			TWD_AP_VERSION,
			true
		);
		wp_localize_script(
			'twd-ap-swipebook-inline',
			'TWD_AP_SB',
			array( 'restUrl' => esc_url_raw( rest_url( 'twd-publisher/v1' ) ) )
		);
	}

	/**
	 * A "View as a swipe book" pill below every published article's content.
	 * Carries the post ID and the full-page URL so swipebook-inline.js can
	 * open it as an in-page overlay when JS runs, and fall back to a normal
	 * link (the full standalone page) if it doesn't. Back at the bottom on
	 * request -- a brief attempt at the top sat oddly next to a theme's own
	 * "Back to Resources" link (which this plugin has no reach into at all;
	 * it's the theme's own template markup, rendered before the_content()
	 * ever runs, not something a the_content filter can touch, let alone
	 * remove).
	 */
	public function append_button( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( '1' === (string) get_query_var( 'twd_ap_swipebook' ) ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || 'publish' !== $post->post_status || ! self::is_enabled( $post->ID ) ) {
			return $content;
		}

		$url   = esc_url( self::url_for( $post ) );
		$color = TWD_AP_Grid_Settings::get()['swipebook_color'];
		$style = 'style="' . esc_attr( self::color_custom_properties( $color ) ) . '"';
		$button = '<p class="twd-ap-swipebook-row"><a class="twd-ap-swipebook-btn" ' . $style . ' href="' . $url . '" data-twd-ap-post-id="' . (int) $post->ID . '">'
			. esc_html__( 'View as a swipe book', 'twd-article-publisher' ) . ' &#8599;</a></p>';

		return $content . $button;
	}

	/**
	 * Inline CSS custom properties for the button's colour, set per-request
	 * from TWD_AP_Grid_Settings rather than a REST fetch -- this button
	 * renders on every published article page, not just ones carrying the
	 * [twd_articles] shortcode, so it can't rely on that grid's own JS ever
	 * having run. The faint/hover backgrounds are precomputed here (plain
	 * rgba, not color-mix()) for broad browser support without a client-side
	 * hex parser.
	 */
	private static function color_custom_properties( $hex ) {
		list( $r, $g, $b ) = self::hex_to_rgb( $hex );
		return sprintf(
			'--twd-ap-swipebook-color:%s;--twd-ap-swipebook-bg:rgba(%d,%d,%d,0.08);--twd-ap-swipebook-bg-hover:rgba(%d,%d,%d,0.16);',
			$hex,
			$r,
			$g,
			$b,
			$r,
			$g,
			$b
		);
	}

	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return array( 91, 138, 114 ); // #5b8a72 fallback.
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Splits post_content into an ordered list of top-level block elements
	 * (p, h2, h3, ul, blockquote, figure, etc.) using DOMDocument, since a
	 * regex over arbitrary HTML is unreliable once lists or nested tags are
	 * involved. Content is already sanitised by TWD_AP_Sanitizer on save,
	 * so nothing unexpected should reach this parser.
	 */
	private function get_body_blocks( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return array();
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return array();
		}

		$blocks = array();
		foreach ( $body->childNodes as $node ) {
			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}
			$blocks[] = array(
				'tag'  => strtolower( $node->nodeName ),
				'html' => $dom->saveHTML( $node ),
				'text' => trim( $node->textContent ),
			);
		}
		return $blocks;
	}

	/**
	 * Chunks the article into slides: a cover slide (title + intro), one
	 * slide per h2 section (further split if a section runs long, so no
	 * single slide is a wall of text), and a closing slide -- a bio slide
	 * about the site's own profile (Customise this grid > Profile page)
	 * if this article has "Include bio page at end" ticked and a profile
	 * name is actually set up, otherwise a plain thank-you. A continuation
	 * slide from a long section doesn't repeat its heading, since the
	 * progress bar already shows the reader is still in the same run.
	 */
	private function build_slides( $post ) {
		$blocks = $this->get_body_blocks( $post->post_content );
		$slides = array();

		$intro = '';
		if ( has_excerpt( $post ) ) {
			$intro = get_the_excerpt( $post );
		} else {
			foreach ( $blocks as $b ) {
				if ( 'p' === $b['tag'] && '' !== $b['text'] ) {
					$intro = wp_trim_words( $b['text'], 30 );
					break;
				}
			}
		}
		$slides[] = array(
			'type'    => 'text',
			'heading' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'html'    => $intro ? '<p>' . esc_html( $intro ) . '</p>' : '',
		);

		// Lowered from 420 -- a full 420 characters of body text plus its
		// heading could overflow the card's fixed height on some screens
		// even with the clamp()-sized text tuned as small as still reads
		// comfortably. 320 leaves more headroom; the safe-center fix on
		// .twd-sb-card (see swipebook.css) means an unusually long section
		// still scrolls into view properly either way, this just makes
		// that the exception rather than something to design around.
		$max_chars       = 320;
		$current_html    = '';
		$current_len     = 0;
		$current_heading = '';

		foreach ( $blocks as $b ) {
			if ( 'h2' === $b['tag'] ) {
				if ( '' !== trim( $current_html ) ) {
					$slides[] = array( 'type' => 'text', 'heading' => $current_heading, 'html' => $current_html );
				}
				$current_html    = '';
				$current_len     = 0;
				$current_heading = $b['text'];
				continue;
			}

			if ( '' !== trim( $current_html ) && ( $current_len + strlen( $b['text'] ) ) > $max_chars ) {
				$slides[] = array( 'type' => 'text', 'heading' => $current_heading, 'html' => $current_html );
				$current_html    = '';
				$current_len     = 0;
				$current_heading = '';
			}

			$current_html .= $b['html'];
			$current_len  += strlen( $b['text'] );
		}
		if ( '' !== trim( $current_html ) ) {
			$slides[] = array( 'type' => 'text', 'heading' => $current_heading, 'html' => $current_html );
		}

		$bio_slide = $this->maybe_bio_slide( $post );
		if ( $bio_slide ) {
			$slides[] = $bio_slide;
		} else {
			$slides[] = array(
				'type'    => 'text',
				'heading' => __( 'Thanks for reading', 'twd-article-publisher' ),
				'html'    => '<p>' . esc_html__( 'Share this with someone it might help, or read the full article again any time.', 'twd-article-publisher' ) . '</p>',
			);
		}

		$related_slide = $this->build_related_slide( $post );
		if ( $related_slide ) {
			$slides[] = $related_slide;
		}

		return $slides;
	}

	/**
	 * A compact, no-thumbnail "more articles" closing slide -- on by
	 * default (falls back to the 6 most recent other published articles),
	 * or up to 6 specific articles ticked in the popup's Categories field
	 * (_twd_ap_related_ids). Returns null (no slide at all) if there are no
	 * other published articles on the site to show, rather than an empty
	 * "more articles" slide.
	 */
	private function build_related_slide( $post ) {
		$related_ids = get_post_meta( $post->ID, '_twd_ap_related_ids', true );
		$related_ids = is_array( $related_ids ) ? array_map( 'absint', $related_ids ) : array();

		if ( ! empty( $related_ids ) ) {
			$posts = array();
			foreach ( $related_ids as $id ) {
				$candidate = get_post( $id );
				if ( $candidate && 'publish' === $candidate->post_status && (int) $candidate->ID !== (int) $post->ID && self::is_enabled( $candidate->ID ) ) {
					$posts[] = $candidate;
				}
			}
			$posts = array_slice( $posts, 0, 6 );
		} else {
			$posts = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 6,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'post__not_in'   => array( $post->ID ),
					'no_found_rows'  => true,
					'meta_query'     => array(
						array(
							'key'     => self::META_OFF,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
		}

		if ( empty( $posts ) ) {
			return null;
		}

		$items = array();
		foreach ( $posts as $related_post ) {
			$items[] = array(
				'id'       => $related_post->ID,
				'title'    => html_entity_decode( get_the_title( $related_post ), ENT_QUOTES, 'UTF-8' ),
				'shareUrl' => self::url_for( $related_post ),
			);
		}

		return array(
			'type'    => 'related',
			'heading' => __( 'More Articles', 'twd-article-publisher' ),
			'items'   => $items,
		);
	}

	private function maybe_bio_slide( $post ) {
		if ( ! get_post_meta( $post->ID, '_twd_ap_include_bio', true ) ) {
			return null;
		}

		$settings = TWD_AP_Grid_Settings::get();
		$name     = trim( (string) $settings['profile_name'] );
		if ( '' === $name ) {
			return null;
		}

		$photo_url = '';
		if ( ! empty( $settings['profile_photo_id'] ) ) {
			$photo_url = wp_get_attachment_image_url( (int) $settings['profile_photo_id'], 'medium' );
		}

		return array(
			'type'       => 'bio',
			'heading'    => $name,
			'photo'      => $photo_url ? $photo_url : '',
			'bio'        => (string) $settings['profile_bio'],
			'link_url'   => (string) $settings['profile_link_url'],
			'link_label' => (string) $settings['profile_link_label'],
		);
	}

	/**
	 * The header fields both books share (logo, site heading, branding
	 * toggle -- all site-wide "Customise this grid" settings, not specific
	 * to either book), parametrised only by which label to show. Pulled out
	 * of get_payload() so TWD_AP_Summary_Book can build its own payload
	 * without duplicating this, and so the two books can never quietly
	 * drift apart on what counts as "the header."
	 */
	public static function get_header_fields( $label ) {
		$site_name = get_bloginfo( 'name' );
		$logo_id   = get_theme_mod( 'custom_logo' );
		$settings  = TWD_AP_Grid_Settings::get();

		// swipebook_logo_id overrides the site's own Customizer logo when
		// set, so a client can carry a different mark on their swipe books
		// (e.g. a wordmark suited to a dark background) without changing
		// their site logo everywhere else.
		$logo_url = '';
		if ( ! empty( $settings['swipebook_logo_id'] ) ) {
			$logo_url = wp_get_attachment_image_url( (int) $settings['swipebook_logo_id'], 'medium' );
		} elseif ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
		}

		return array(
			'siteName'        => $site_name,
			// Blank swipebook_heading falls back to the site's own name --
			// the site heading line above the book's own label is on by
			// default, not something a site has to opt into.
			'siteHeading'     => '' !== $settings['swipebook_heading'] ? $settings['swipebook_heading'] : $site_name,
			'siteUrl'         => home_url( '/' ),
			'label'           => $label,
			'logoUrl'         => $logo_url ? $logo_url : '',
			'logoBg'          => $settings['swipebook_logo_bg'],
			'includeBranding' => (bool) $settings['swipebook_export_branding'],
		);
	}

	/**
	 * The JSON payload swipebook-inline.js fetches to open the overlay, and
	 * the same shape the standalone page embeds inline for its own JS to
	 * read, so the two rendering paths are driven from one data structure.
	 */
	public function get_payload( $post ) {
		// get_the_title() runs wptexturize, which turns a plain apostrophe or
		// a run of dots into literal entity text ("&#8217;", "&hellip;"),
		// meant for direct, unescaped HTML output. Both the page's own
		// esc_html()/esc_attr() calls below and the JS overlay's escapeHtml()
		// re-escape that, leaving the entity text itself showing instead of
		// the punctuation it stands for. html_entity_decode() turns it back
		// into a real character first, so it only ever gets encoded once.
		$title    = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$settings = TWD_AP_Grid_Settings::get();
		$header   = self::get_header_fields( $settings['swipebook_label'] );

		$payload = array_merge(
			$header,
			array(
				'title'      => $title,
				'articleUrl' => get_permalink( $post ),
				'shareUrl'   => self::url_for( $post ),
				'slides'     => $this->build_slides( $post ),
			)
		);

		// A companion link to the curated Summary Book, only when one's
		// actually been published for this article -- swipebook.js renders
		// this as a small footer link, opened the same way the "More
		// Articles" slide opens another article's book (close this one,
		// open the other), just within the same article instead of across
		// to a different one.
		if ( class_exists( 'TWD_AP_Summary_Book' ) && TWD_AP_Summary_Book::is_published( $post->ID ) ) {
			$payload['companionUrl']         = TWD_AP_Summary_Book::url_for( $post );
			$payload['companionLabel']       = __( 'View Summary Book', 'twd-article-publisher' );
			$payload['companionPostId']      = $post->ID;
			$payload['companionBookVariant'] = 'summary-book';
		}

		return $payload;
	}

	/**
	 * Shared HTML shell for the standalone full-page view -- built once so
	 * TWD_AP_Summary_Book's own standalone page (?twd_ap_summary_book=1)
	 * renders identically to the swipe book's, parametrised only by the
	 * payload (already built by the caller) and which query var brought the
	 * visitor here (only used for the OG/canonical URLs, which already live
	 * in the payload, so this barely does anything post-specific itself).
	 */
	public static function render_standalone_page( $post, $payload ) {
		$slides      = $payload['slides'];
		$title       = $payload['title'];
		$article_url = $payload['articleUrl'];
		$share_url   = $payload['shareUrl'];
		$logo_url    = $payload['logoUrl'];
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		$description = html_entity_decode( $description, ENT_QUOTES, 'UTF-8' );
		$image_id    = get_post_thumbnail_id( $post );
		$image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

		nocache_headers();
		status_header( 200 );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html( $title . ' | ' . $payload['siteName'] ); ?></title>
<meta name="robots" content="noindex, follow">
<link rel="canonical" href="<?php echo esc_url( $article_url ); ?>">
<meta property="og:type" content="article">
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
<meta property="og:url" content="<?php echo esc_url( $share_url ); ?>">
<?php if ( $image_url ) : ?>
<meta property="og:image" content="<?php echo esc_url( $image_url ); ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( TWD_AP_URL . 'assets/swipebook.css' ); ?>?v=<?php echo esc_attr( TWD_AP_VERSION ); ?>">
</head>
<body class="twd-sb-page">
<script>window.TWD_AP_SB_DATA = <?php echo wp_json_encode( $payload ); ?>;</script>
<script src="<?php echo esc_url( TWD_AP_URL . 'assets/swipebook.js' ); ?>?v=<?php echo esc_attr( TWD_AP_VERSION ); ?>"></script>
</body>
</html>
		<?php
	}
}
