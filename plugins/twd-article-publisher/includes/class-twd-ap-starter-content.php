<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates one published "Getting Started" article the first time the plugin
 * is activated on a site, so a new client's first look at the resources page
 * already shows real, working content instead of an empty grid, and doubles
 * as an onboarding guide they can read without leaving the site. Guarded by
 * an option so it is only ever created once, even across deactivate and
 * reactivate, and even if the article itself is later edited or deleted.
 */
class TWD_AP_Starter_Content {

	const OPTION_KEY = 'twd_ap_starter_created';

	public static function maybe_create() {
		if ( get_option( self::OPTION_KEY ) ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => __( 'Getting Started With Your New Articles Plugin', 'twd-article-publisher' ),
				'post_excerpt' => __( 'A quick tour of the popup you will use to publish and manage articles on this site, and the resources page it feeds.', 'twd-article-publisher' ),
				'post_content' => self::content(),
				'post_status'  => 'publish',
				'post_type'    => 'post',
			),
			true
		);

		update_option( self::OPTION_KEY, 1 );

		if ( ! is_wp_error( $post_id ) && $post_id ) {
			update_post_meta( $post_id, '_twd_ap_getting_started', 1 );
		}
	}

	private static function content() {
		return '<p>This article was created automatically when the plugin was activated, so the resources page has something real to show straight away. Edit it into your own welcome message, or delete it once your team is comfortable with the tools below.</p>'

			. '<h2>Writing an article</h2>'
			. '<p>Look for the <strong>New Article</strong> button, usually floating bottom right on any page while you are logged in. It opens a popup right here on the live site, no need to go into wp-admin. On an article you can edit, an <strong>Edit This Article</strong> button appears too.</p>'

			. '<h2>Three ways to bring in content</h2>'
			. '<ul>'
			. '<li><strong>Visual</strong>: write directly, with a simple toolbar for headings, lists, links, images and YouTube videos.</li>'
			. '<li><strong>HTML</strong>: paste already-formatted HTML, useful if you draft articles elsewhere first.</li>'
			. '<li><strong>Import JSON</strong>: paste a JSON block (for example from Therapy Resource Directory&#8217;s Article Assist tool, linked at the top of the popup) to fill in the title, SEO details, category, tags and body in one go, then review and edit before saving.</li>'
			. '</ul>'

			. '<h2>Choosing where an article belongs</h2>'
			. '<p>Tick one or more categories in the popup&#8217;s Categories field. If an article has more than one, click the star next to the one it should be grouped under on the resources page. If you never click it, the first ticked category is used.</p>'

			. '<h2>The resources page</h2>'
			. '<p>The <strong>[twd_articles]</strong> shortcode, used bare with no attributes, shows the top categories as their own titled sections, so visitors see the range of what you cover at a glance. A search box and a category dropdown are built in. For anyone who can publish articles, a small gear icon opens a settings popup right on the page: how many categories show, how many articles each, their order, and whether thumbnails show at all.</p>'

			. '<h2>Sharing an article as a swipe book</h2>'
			. '<p>Every published article can also be viewed as a swipeable, shareable slide version, split automatically by heading. Look for the <strong>View as a swipe book</strong> link under any article, or the <strong>Swipe book link</strong> in the popup while editing a published one.</p>'

			. '<h2>Need a reminder later</h2>'
			. '<p>The small circular <strong>?</strong> icon in the popup&#8217;s header opens a built-in help screen covering the toolbar, the shortcode, and a ready-to-copy prompt for drafting articles elsewhere.</p>';
	}
}
