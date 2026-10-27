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
	}

	public function append_to_article( $content ) {
		if ( self::$appended || is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
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
	 * The block for one article, or '' when switched off, the article has no
	 * topic, or the topic has no other articles.
	 */
	public static function html( $post_id ) {
		$s = TWD_AP_Grid_Settings::get();
		if ( empty( $s['related_show'] ) ) {
			return '';
		}
		$topic = self::topic_for( $post_id );
		if ( ! $topic ) {
			return '';
		}
		$count = max( 1, min( 8, (int) $s['related_count'] ) );
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
		return '<aside class="twd-ap-related" style="' . esc_attr( '--twd-ap-accent-color:' . $s['accent_color'] . ';' ) . '">' .
			'<h2 class="twd-ap-related-title">' . esc_html__( 'More in', 'twd-article-publisher' ) . ' ' . $name . '</h2>' .
			'<ul class="twd-ap-related-list">' . $items . '</ul>' .
		'</aside>';
	}
}
