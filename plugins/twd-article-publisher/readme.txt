=== TWD Article Publisher ===
Contributors: therapywebdesigns
Tags: blog, articles, popup, editor
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets approved users publish and edit blog articles from the live site in a popup, without going into wp-admin.

== Description ==

Adds a "New Article" button and, on any article the current user can edit, an "Edit This Article" button, both shown only to logged-in users on the site's front end.

The popup lets you:

* Write in a simple visual editor, or switch to an HTML tab and paste AI-formatted HTML
* Insert images inline from the Media Library or upload, and embed YouTube videos by pasting a link
* Click any inline image to align it left, center, right or full-width, and drag its corner handle to resize it
* Tag one or more categories (or add a new one on the fly), and add tags (comma separated, with autocomplete from existing tags)
* Set a featured image from the Media Library
* Write an excerpt
* Set the Yoast SEO title and meta description, if Yoast SEO is active
* Save as a draft, publish immediately, or schedule for a future date and time
* Reopen and edit any article you have permission to edit, in the same popup
* While editing, an "Edit in WordPress" link opens the normal wp-admin post editor for that article in a new tab. Deliberately no "Edit with Elementor" link: switching a post into Elementor's builder mode can make it render from Elementor's own saved layout instead of post_content, which would silently stop reflecting future edits made through this popup.

All pasted or written HTML is cleaned on the server before saving: scripts, inline styles and anything unsafe are stripped, and any H1 is converted to H2 so the page's own post title stays the only H1.

Use the `[twd_articles]` shortcode on any page to display a card grid of published articles. Attributes: `category` (slug), `tag` (slug), `count` (default 6), `columns` (default 3, max 4). Example: `[twd_articles category="anxiety" count="9" columns="3"]`.

An "Instructions for use" link at the bottom of the popup opens a built-in help screen covering the toolbar, categories/tags, the `[twd_articles]` shortcode (with a copy button), and a ready-to-copy AI prompt for drafting compatible HTML articles.

Every article page automatically gets its own built-in typography (headings, paragraphs, lists, blockquotes, images) so it reads well on any theme, even a bare Hello Elementor site with no custom Single Post template designed yet.

== Installation ==

1. Upload the twd-article-publisher folder to /wp-content/plugins/, or upload the zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Settings > Article Publisher to choose which roles can use it, and pick a default category.
4. Visit the site while logged in as an allowed user. The "New Article" button appears bottom-right.

== Updates ==

Not distributed via WordPress.org. Every site running this plugin checks
https://github.com/thistwistedyouth/therapy-web-designs (a public repo)
for a newer version and shows the normal "update available" row in
Plugins, with a one-click update -- no manual re-zip-and-upload needed
per site. See includes/class-twd-ap-updater.php.

To ship a new version to every client site: bump the Version header and
TWD_AP_VERSION here, then update dist/twd-article-publisher-update.json
(version, changelog) and overwrite dist/twd-article-publisher-latest.zip
with a fresh build (packaging command below), and push. Sites pick it up
within 12 hours automatically, or immediately via Plugins > "Check again".

== Frequently Asked Questions ==

= Does this replace the normal WordPress editor? =

No. Everything it creates is a normal WordPress post, still editable in wp-admin as usual. This is an additional, quicker way in for people who don't need the full editor.

= Where do scheduled times come from? =

The date and time you pick are treated as the site's own local time (Settings > General > Timezone in wp-admin).

= Does it work with any theme? =

Yes. It adds its own floating buttons and popup independently of the theme.
