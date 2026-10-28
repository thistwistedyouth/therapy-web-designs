<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "More in {category}": a short list of plain links to other articles from the
 * same category, printed at the end of every article (after the article, before
 * the call to action). It keeps readers reading and gives every article real
 * internal links to its siblings and to its category page, for search engines
 * as well as visitors. Switch it off, or change how many links, in Customise
 * this grid > Related articles.
 */
class TWD_AP_Related {

	private static $instance = null;
	private static $appended = false;
	/** True once a [twd_related] shortcode has printed the block on this page. */
	private static $placed = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// After TWD_AP_Article_Style::wrap_content() (20) so it sits below the
		// article's narrow column, and before the call to action (25).
		add_filter( 'the_content', array( $this, 'append_to_article' ), 22 );
		add_shortcode( 'twd_related', array( $this, 'shortcode' ) );
	}

	/** 'auto' (the default), 'shortcode' or 'off': where the block goes (Customise this grid > Related articles). */
	public static function place() {
		$p = TWD_AP_Grid_Settings::get()['related_place'];
		return in_array( $p, TWD_AP_Grid_Settings::RELATED_PLACES, true ) ? $p : 'auto';
	}

	/**
	 * [twd_related]: the same block as the automatic one, wherever it is
	 * placed (a single article template, an article's own text). It works for
	 * the article being shown; id="123" names another, count="3" changes how
	 * many links for this one place. Prints nothing when the owner has chosen
	 * "Nowhere", or when the article has nothing to show. When the owner has
	 * left it on "automatically at the end" and the shortcode for THIS article
	 * sits in the article's own text, the automatic copy steps aside, so it
	 * never shows twice there; in a template that prints after the text, choose
	 * "Only where I place the shortcode".
	 */
	public function shortcode( $atts ) {
		if ( 'off' === self::place() ) {
			return '';
		}
		$atts = shortcode_atts( array( 'id' => 0, 'count' => 0 ), (array) $atts, 'twd_related' );
		$id   = absint( $atts['id'] );
		if ( ! $id ) {
			$id = (int) get_the_ID();
		}
		if ( ! $id ) {
			return '';
		}
		$count = absint( $atts['count'] );
		$box   = self::html( $id, $count ? max( 1, min( 8, $count ) ) : 0 );
		if ( '' !== $box ) {
			// Only a block for the article being shown makes the automatic one step
			// aside; one for a different article (id="5" in a sidebar) does not.
			if ( $id === (int) get_queried_object_id() ) {
				self::$placed = true;
			}
			TWD_AP_Article_Style::enqueue();
		}
		return $box;
	}

	public function append_to_article( $content ) {
		if ( self::$appended || self::$placed || 'auto' !== self::place() || is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$box = self::html( (int) get_the_ID() );
		if ( '' === $box ) {
			return $content;
		}
		self::$appended = true;
		return $content . $box;
	}

	/**
	 * The article's topic: its primary category (the one the grid groups it
	 * under), ignoring WordPress's default category, and preferring a real
	 * topic over the organisational "Featured Articles" category. Null when
	 * it has none.
	 */
	public static function topic_for( $post_id ) {
		$uncat = TWD_AP_Cards::uncategorized_id();
		$cats  = array();
		foreach ( get_the_category( $post_id ) as $c ) {
			if ( (int) $c->term_id !== $uncat ) {
				$cats[] = $c;
			}
		}
		if ( empty( $cats ) ) {
			return null;
		}
		$primary = (int) get_post_meta( $post_id, '_twd_ap_primary_category', true );
		$chosen  = $cats[0];
		foreach ( $cats as $c ) {
			if ( $primary && (int) $c->term_id === $primary ) {
				$chosen = $c;
				break;
			}
		}
		if ( 0 === strcasecmp( $chosen->name, 'Featured Articles' ) ) {
			foreach ( $cats as $c ) {
				if ( 0 !== strcasecmp( $c->name, 'Featured Articles' ) ) {
					$chosen = $c;
					break;
				}
			}
		}
		return $chosen;
	}

	/**
	 * The block for one article: "More in {category}" with its list of links
	 * and, under it, the Browse by topic row (the owner's chosen categories
	 * and the quiet All articles link, this article's own category shown but
	 * not linked). Either part can be switched off; '' when neither shows.
	 */
	public static function html( $post_id, $count = 0 ) {
		$s     = TWD_AP_Grid_Settings::get();
		$topic = self::topic_for( $post_id );
		$list  = '';
		if ( ! empty( $s['related_show'] ) && $topic ) {
			$list = self::list_html( $post_id, $topic, $count ? max( 1, min( 8, (int) $count ) ) : max( 1, min( 8, (int) $s['related_count'] ) ), 'grid' === $s['related_layout'], ! empty( $s['show_thumbnails'] ) );
		}
		$nav = '';
		if ( ! empty( $s['related_topics'] ) ) {
			$nav = TWD_AP_Cards::topics_html( TWD_AP_Index::index_url(), $topic ? (int) $topic->term_id : 0 );
		}
		if ( '' === $list && '' === $nav ) {
			return '';
		}
		// 1.48.0: as a grid of picture cards the block is wider than the article column
		// (up to 900px, centred); the card picture follows the grid's image shape.
		// Only when there is a grid to show: a block with just the topics row stays as it was.
		$grid  = 'grid' === $s['related_layout'] && '' !== $list;
		$class = 'twd-ap-related' . ( $grid ? ' twd-ap-related-wide ' . TWD_AP_Grid_Settings::thumb_shape_class( $s['thumb_shape'] ) : '' );
		return '<aside class="' . esc_attr( trim( $class ) ) . '" style="' . esc_attr( '--twd-ap-accent-color:' . $s['accent_color'] . ';--twd-ap-accent-ink:' . TWD_AP_Swipebook_Colours::best_ink( '' !== TWD_AP_Swipebook_Colours::clean_hex( $s['accent_color'] ) ? TWD_AP_Swipebook_Colours::clean_hex( $s['accent_color'] ) : '#2563eb' ) . ';' ) . '">' . $list . $nav . '</aside>';
	}

	/**
	 * The heading and the plain list of other articles in the topic, or ''
	 * when the topic has no other articles.
	 */
	private static function list_html( $post_id, $topic, $count, $grid = false, $thumbs = true ) {
		// One extra, so the article itself can be dropped and still leave $count.
		$found = TWD_AP_Cards::fetch( $count + 1, 0, '', $topic->slug );
		$items = '';
		$shown = 0;
		foreach ( $found['posts'] as $post ) {
			if ( (int) $post->ID === (int) $post_id ) {
				continue;
			}
			if ( $shown >= $count ) {
				break;
			}
			$shown++;
			if ( $grid ) {
				$items .= self::card_html( $post, $thumbs );
				continue;
			}
			$items .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) . '</a> <span class="twd-ap-related-date">' . esc_html( get_the_date( '', $post ) ) . '</span></li>';
		}
		if ( 0 === $shown ) {
			return '';
		}
		$link = get_category_link( $topic->term_id );
		$name = esc_html( html_entity_decode( $topic->name, ENT_QUOTES, 'UTF-8' ) );
		if ( ! is_wp_error( $link ) && '' !== $link ) {
			$name = '<a href="' . esc_url( $link ) . '">' . $name . '</a>';
		}
		return '<h2 class="twd-ap-related-title">' . esc_html__( 'More in', 'twd-article-publisher' ) . ' ' . $name . '</h2>' .
			'<ul class="twd-ap-related-list' . ( $grid ? ' twd-ap-related-cards' : '' ) . '">' . $items . '</ul>';
	}

	/**
	 * One box in the grid layout: the article's picture (or a plain block when it
	 * has none; no picture at all when the grid's pictures are switched off), its
	 * title and its date, the whole box one link.
	 */
	private static function card_html( $post, $thumbs ) {
		$pic = '';
		if ( $thumbs ) {
			$thumb_id = get_post_thumbnail_id( $post );
			$url      = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';
			if ( $url ) {
				$url = str_replace( "'", '%27', esc_url_raw( $url ) );
				$pic = '<span class="twd-ap-related-thumb" style="' . esc_attr( "background-image:url('" . $url . "')" ) . '"></span>';
			} else {
				$pic = '<span class="twd-ap-related-thumb twd-ap-related-thumb-placeholder" aria-hidden="true">' . TWD_AP_Cards::PLACEHOLDER_SVG . '</span>';
			}
		}
		return '<li class="twd-ap-related-card"><a href="' . esc_url( get_permalink( $post ) ) . '">' . $pic .
			'<span class="twd-ap-related-card-title">' . esc_html( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) . '</span>' .
			'<span class="twd-ap-related-date">' . esc_html( get_the_date( '', $post ) ) . '</span></a></li>';
	}
}
