<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders any published article as a shareable, swipeable slide deck at
 * ?twd_ap_swipebook=1 on the article's own permalink. No new content is
 * stored: slides are built from the live post content every time the page
 * loads, so a swipe book always matches the article and never drifts out
 * of sync with it. Splitting is done in plain PHP (DOMDocument, chunked by
 * heading and length), not by an AI call, since this plugin runs on many
 * client sites and none of them carry an Anthropic key of their own.
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
		add_filter( 'the_content', array( $this, 'append_button' ), 30 );
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
	 * A "View as a swipe book" link under every published article, so a
	 * visitor (or the therapist themselves, sharing it) has an obvious way
	 * in without needing to know the query param exists.
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
		$button = '<p class="twd-ap-swipebook-row"><a class="twd-ap-swipebook-btn" href="' . $url . '">'
			. esc_html__( 'View as a swipe book', 'twd-article-publisher' ) . ' &#8599;</a></p>';

		return $content . $button;
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
	 * single slide is a wall of text), and a closing slide. A continuation
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
			'heading' => get_the_title( $post ),
			'html'    => $intro ? '<p>' . esc_html( $intro ) . '</p>' : '',
		);

		$max_chars       = 420;
		$current_html    = '';
		$current_len     = 0;
		$current_heading = '';

		foreach ( $blocks as $b ) {
			if ( 'h2' === $b['tag'] ) {
				if ( '' !== trim( $current_html ) ) {
					$slides[] = array( 'heading' => $current_heading, 'html' => $current_html );
				}
				$current_html    = '';
				$current_len     = 0;
				$current_heading = $b['text'];
				continue;
			}

			if ( '' !== trim( $current_html ) && ( $current_len + strlen( $b['text'] ) ) > $max_chars ) {
				$slides[] = array( 'heading' => $current_heading, 'html' => $current_html );
				$current_html    = '';
				$current_len     = 0;
				$current_heading = '';
			}

			$current_html .= $b['html'];
			$current_len  += strlen( $b['text'] );
		}
		if ( '' !== trim( $current_html ) ) {
			$slides[] = array( 'heading' => $current_heading, 'html' => $current_html );
		}

		$slides[] = array(
			'heading' => __( 'Thanks for reading', 'twd-article-publisher' ),
			'html'    => '<p>' . esc_html__( 'Share this with someone it might help, or read the full article again any time.', 'twd-article-publisher' ) . '</p>',
		);

		return $slides;
	}

	private function render( $post ) {
		$slides      = $this->build_slides( $post );
		$title       = get_the_title( $post );
		$site_name   = get_bloginfo( 'name' );
		$article_url = get_permalink( $post );
		$share_url   = self::url_for( $post );
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		$image_id    = get_post_thumbnail_id( $post );
		$image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
		$logo_id     = get_theme_mod( 'custom_logo' );
		$logo_url    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		nocache_headers();
		status_header( 200 );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html( $title . ' | ' . $site_name ); ?></title>
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
<body class="twd-sb-body">
<div class="twd-sb-app" id="twd-sb-app" data-article-url="<?php echo esc_url( $article_url ); ?>" data-share-url="<?php echo esc_url( $share_url ); ?>" data-title="<?php echo esc_attr( $title ); ?>">

	<div class="twd-sb-progress" id="twd-sb-progress">
		<?php foreach ( $slides as $i => $slide ) : ?>
			<span class="twd-sb-seg" data-seg="<?php echo (int) $i; ?>"></span>
		<?php endforeach; ?>
	</div>

	<div class="twd-sb-mast">
		<?php if ( $logo_url ) : ?>
			<img class="twd-sb-mast-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
		<?php else : ?>
			<span class="twd-sb-mast-text"><?php echo esc_html( $site_name ); ?></span>
		<?php endif; ?>
	</div>

	<div class="twd-sb-slides" id="twd-sb-slides">
		<?php foreach ( $slides as $i => $slide ) : ?>
			<section class="twd-sb-slide<?php echo 0 === $i ? ' is-active' : ''; ?>" data-index="<?php echo (int) $i; ?>">
				<div class="twd-sb-card">
					<?php if ( ! empty( $slide['heading'] ) ) : ?>
						<h2 class="twd-sb-heading"><?php echo esc_html( $slide['heading'] ); ?></h2>
					<?php endif; ?>
					<div class="twd-sb-body-text"><?php echo $slide['html']; // Already sanitised by TWD_AP_Sanitizer on save. ?></div>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<div class="twd-sb-controls">
		<button type="button" class="twd-sb-btn twd-sb-prev" id="twd-sb-prev" aria-label="<?php esc_attr_e( 'Previous', 'twd-article-publisher' ); ?>">&#8249;</button>
		<span class="twd-sb-counter" id="twd-sb-counter"></span>
		<button type="button" class="twd-sb-btn twd-sb-next" id="twd-sb-next" aria-label="<?php esc_attr_e( 'Next', 'twd-article-publisher' ); ?>">&#8250;</button>
	</div>

	<div class="twd-sb-footer">
		<button type="button" class="twd-sb-share-btn" id="twd-sb-share-btn"><?php esc_html_e( 'Share', 'twd-article-publisher' ); ?></button>
		<a class="twd-sb-read-link" href="<?php echo esc_url( $article_url ); ?>"><?php esc_html_e( 'Read the full article', 'twd-article-publisher' ); ?></a>
	</div>
</div>
<script src="<?php echo esc_url( TWD_AP_URL . 'assets/swipebook.js' ); ?>?v=<?php echo esc_attr( TWD_AP_VERSION ); ?>"></script>
</body>
</html>
		<?php
	}
}
