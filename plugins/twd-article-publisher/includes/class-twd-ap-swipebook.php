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

	public function maybe_render() {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		if ( '1' !== (string) get_query_var( 'twd_ap_swipebook' ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return;
		}

		$this->render( $post );
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
	 * A "View as a swipe book" pill above every published article's content.
	 * Carries the post ID and the full-page URL so swipebook-inline.js can
	 * open it as an in-page overlay when JS runs, and fall back to a normal
	 * link (the full standalone page) if it doesn't. Sits at the top, not
	 * the bottom: after the content it was easy to miss on a long article
	 * (it also just read as visually adrift, sitting alone at the very
	 * bottom-left of the page with nothing else around it), and a reader
	 * deciding how to read the article is better served seeing this before
	 * committing to the long-form version, not after finishing it.
	 */
	public function append_button( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( '1' === (string) get_query_var( 'twd_ap_swipebook' ) ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || 'publish' !== $post->post_status ) {
			return $content;
		}

		$url = esc_url( self::url_for( $post ) );
		$button = '<p class="twd-ap-swipebook-row"><a class="twd-ap-swipebook-btn" href="' . $url . '" data-twd-ap-post-id="' . (int) $post->ID . '">'
			. esc_html__( 'View as a swipe book', 'twd-article-publisher' ) . ' &#8599;</a></p>';

		return $button . $content;
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

		$max_chars       = 420;
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

		return $slides;
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
		$title       = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$site_name   = get_bloginfo( 'name' );
		$article_url = get_permalink( $post );
		$logo_id     = get_theme_mod( 'custom_logo' );

		return array(
			'title'      => $title,
			'siteName'   => $site_name,
			'articleUrl' => $article_url,
			'shareUrl'   => self::url_for( $post ),
			'logoUrl'    => $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '',
			'slides'     => $this->build_slides( $post ),
		);
	}

	private function render( $post ) {
		$payload     = $this->get_payload( $post );
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
