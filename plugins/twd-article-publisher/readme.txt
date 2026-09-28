=== TWD Article Publisher ===
Contributors: therapywebdesigns
Tags: blog, articles, popup, editor
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets approved users publish and edit blog articles from the live site in a popup, without going into wp-admin.

== Description ==

Adds a "New Article" button and, on any article the current user can edit, an "Edit This Article" button, both shown only to logged-in users on the site's front end.

The popup lets you:

* Write in a simple visual editor, or switch to an HTML tab and paste AI-formatted HTML
* Tag one or more categories
* Set a featured image from the Media Library
* Write an excerpt
* Set the Yoast SEO title and meta description, if Yoast SEO is active
* Save as a draft, publish immediately, or schedule for a future date and time
* Reopen and edit any article you have permission to edit, in the same popup

All pasted or written HTML is cleaned on the server before saving: scripts, inline styles and anything unsafe are stripped, and any H1 is converted to H2 so the page's own post title stays the only H1.

== Installation ==

1. Upload the twd-article-publisher folder to /wp-content/plugins/, or upload the zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Settings > Article Publisher to choose which roles can use it, and pick a default category.
4. Visit the site while logged in as an allowed user. The "New Article" button appears bottom-right.

== Frequently Asked Questions ==

= Does this replace the normal WordPress editor? =

No. Everything it creates is a normal WordPress post, still editable in wp-admin as usual. This is an additional, quicker way in for people who don't need the full editor.

= Where do scheduled times come from? =

The date and time you pick are treated as the site's own local time (Settings > General > Timezone in wp-admin).

= Does it work with any theme? =

Yes. It adds its own floating buttons and popup independently of the theme.
