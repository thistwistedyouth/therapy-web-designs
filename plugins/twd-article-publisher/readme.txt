=== Articles & Resource Production Plugin ===
Contributors: therapywebdesigns
Tags: blog, articles, popup, editor
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.21.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets approved users publish and edit blog articles from the live site in a popup, without going into wp-admin.

== Description ==

Adds a "New Article" button and, on any article the current user can edit, an "Edit This Article" button, both shown only to logged-in users on the site's front end. Both open the same popup, titled "Resource Maker", styled in a calm, therapy-friendly palette and showing the site's own logo (from Customize > Site Identity) if one is set.

The popup lets you:

* Write in a simple visual editor, or switch to an HTML tab and paste AI-formatted HTML
* Insert images inline from the Media Library or upload, and embed YouTube videos by pasting a link
* Click any inline image to align it left, center, right or full-width, and drag its corner handle to resize it
* Tag one or more categories (or add a new one on the fly), and add tags (comma separated, with autocomplete from existing tags)
* Set a featured image from the Media Library
* Write an excerpt
* Set the Yoast SEO title and meta description, if Yoast SEO is active
* Save as a draft, publish immediately, or schedule for a future date and time
* Tick "Feature this article" to pin it to the top of the `[twd_articles]` grid, ahead of newest-first
* Reopen and edit any article you have permission to edit, in the same popup
* While editing, an "Edit in WordPress" link opens the normal wp-admin post editor for that article in a new tab. Deliberately no "Edit with Elementor" link: switching a post into Elementor's builder mode can make it render from Elementor's own saved layout instead of post_content, which would silently stop reflecting future edits made through this popup.
* A third "Paste JSON" tab alongside Visual and HTML: paste a JSON block (e.g. from TRD's Article Assist tool) with title, seo_title, meta_description, category, tags and html fields, then click Fill fields from JSON to bring in the title, excerpt, Yoast fields, category (matched or created), tags and body in one go. Everything else in the popup stays out of the way until you do, then it switches you to the Visual tab so you can review and edit everything before saving. Nothing is locked: imported fields are exactly as editable as if you'd typed them.

All pasted or written HTML is cleaned on the server before saving: scripts, inline styles and anything unsafe are stripped, and any H1 is converted to H2 so the page's own post title stays the only H1.

Every published article can be viewed and shared as a swipe book: a full-screen, swipeable slide version of the same article, split automatically by heading and length. It is a live view generated on request, not a separate saved copy, so it always matches the article. Visit `[the article's URL]?twd_ap_swipebook=1`, or use the "View as a swipe book" link shown under every article, or the "Swipe book link" shown in the popup while editing a published article.

Use the `[twd_articles]` shortcode on any page for a fully interactive resources grid: a search box, a category dropdown, "Load more" pagination -- all client-side, no page reload. Used bare, with no `category`/`tag` attribute, it defaults to a grouped-by-category landing view: the top categories, each as its own titled section, no article repeated across sections. Searching or picking a category from the dropdown switches to a flat, paginated list for that filter, with a "Show all categories" button to return. Featured articles show first within any list, then newest-first. Attributes: `category` (slug), `tag` (slug), `count` (default 9, per page), `columns` (default 3, max 4). Example: `[twd_articles count="9" columns="3"]`.

Give it an explicit `category` or `tag` attribute (e.g. `[twd_articles category="anxiety"]`) for a curated list without search/filters -- useful for a themed page. Or leave both off and drop the plain `[twd_articles]` shortcode straight onto a WordPress category or tag archive template (e.g. via Elementor Theme Builder): it automatically detects the current archive and shows only that category/tag's articles, so one shortcode works for every category page without hardcoding a slug per page.

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

NEW APPROACH (1.21.0): whether a site has more than 12 published
articles is now decided in PHP and baked directly into the resources
grid's own server-rendered HTML (class-twd-ap-shortcode.php), instead
of being fetched afterward over REST. The grouped-vs-flat decision,
and whether "Load more" can ever show at all, no longer depend on any
extra network request landing, or its response being fresh, since the
same page load that renders everything else already carries the
answer. The REST /grid-settings response is still read afterward (for
thumbnails, the read-more colour, and to catch an article published or
deleted since this exact page was rendered), but it no longer gates
this specific decision the way it did in 1.20.0-1.20.5. The DEBUG line
under Load more now also prints config.manyArticles (the new
server-baked value) alongside the REST-based one, to make any gap
between the two immediately visible.

TEMPORARY DIAGNOSTIC (1.20.5): a small red DEBUG line now shows under
"Load more" on the resources grid, printing the exact numbers that
decided whether it's shown (total_articles, manyArticles, per_page,
items returned, offset, and whether Load more ended up hidden). 1.20.4
should already make Load more impossible to show with 12 or fewer
articles; if it's still showing after that update on a site, this line
says why directly instead of guessing again. Remove this once the real
cause is confirmed and fixed.

FIX (1.20.4): "Load more" could still show on a site with 12 or fewer
articles (confirmed via the 1.20.3 deploy-check marker that updates
were genuinely reaching the site, ruling out caching). The fix in
1.20.0 tried to fetch enough articles in one request to cover
everything, so Load more's own logic would naturally come back with
nothing left, but that only works if total_articles is accurate --
depending entirely on that count being right was the actual gap.
"12 or fewer" is now also a hard, independent condition on showing
Load more at all, never overridden by anything the per-request fetch
decides on its own, whatever total_articles reports.

"View as a swipe book" moved back to the bottom of the article, on
request, since a brief stay at the top sat oddly next to a theme's own
"Back to Resources" link -- which is the theme's own template markup,
rendered before the_content() ever runs, so this plugin has no reach
into it at all, and can't remove or move it.

FIX (1.20.2): the resources grid's REST responses (article list,
grouped view, category facets, grid settings) never sent explicit
no-cache headers, so a browser's own HTTP cache, or a host's edge/CDN
cache, could serve a stale response to a plain fetch() even after the
plugin itself had been updated -- most visibly, "Load more" still
showing when nothing was actually left to load, or the 12-or-fewer
plain-list behaviour not taking effect, despite both already being
fixed server-side. Every one of these endpoints now sends
nocache_headers(), and the grid's own fetch() calls now also pass
cache: 'no-store', so this self-heals from here even for a browser
that already has a stale response cached from before this update.

FIX (1.20.2): the search box, category dropdown and "Load more"
button on the resources grid were styled as plain soft-rounded boxes,
inconsistent with the pill shape (border-radius: 999px) used
everywhere else in this plugin (Article Assist, the swipe book link,
the Featured badge). All three are pills now too.

FIX (1.20.1): the "View as a swipe book" pill moved to the top of the
article in 1.20.0, but landed outside the article's own centred,
styled content column, prepended after that wrapper had already been
built around the rest of the content rather than inside it. It showed
up full-width against the page's actual left edge instead of sitting
neatly in the article column next to the theme's own "Back to
Resources" link. Reordered so it's added before the content gets
wrapped, landing inside that column like the rest of the article.

NEW (1.20.0): the resources grid only splits into category sections
once a site has more than 12 published articles; 12 or fewer now show
as one plain list, since a handful of articles read better that way
than split into thin, mostly-empty sections each with their own "Load
more". "Load more" itself only ever appears once there is genuinely
another page to fetch.

FIX (1.20.0): "Delete this article" in the popup lost its own styling
on some sites (a bare bordered box instead of the intended quiet
icon+label link) for the same reason the swipe book's buttons did in
1.19.3, it was missing the `!important` guards this plugin's other
buttons already carry against a theme's own global button styling.

FIX (1.20.0): "View as a swipe book" now sits above the article, not
below it, restyled as a proper pill button. Appended after the content
it was easy to miss on a longer article, and read as visually adrift,
sitting alone at the bottom of the page with nothing else around it.

FIX (1.19.3): the swipe book's Close/prev/next/Share buttons could
show up looking like bare default Elementor buttons (thin outline,
no fill) instead of this book's own design, on a site whose theme or
page builder sets its own global button styling. The swipe book's
overlay mode injects its markup straight into the live theme page, so
it inherits that page's CSS the same way the rest of this plugin's
front-end already accounts for; its own stylesheet just hadn't been
updated for that yet, since the swipe book was originally a fully
standalone page with no theme CSS ever loaded at all. Every visual
rule in the swipe book's stylesheet now carries the same defensive
weight already used elsewhere in this plugin, so its own styling
always wins regardless of the theme.

FIX (1.19.2): a published article's text sat flush against the left
edge of the page on a theme whose Single Post template gives the
content area no width limit of its own. The plugin's own article
typography set a max-width but never centered it, which most themes
never surfaced since their own template already centers the content
column, but a theme without a built Single Post design does not. The
article now centers itself regardless of what the theme's template
does around it.

FIX (1.19.1): "Load more" could still show with nothing left to load
on a filtered/searched list whose total happened to land exactly on a
page boundary (e.g. precisely the number of articles the grid shows
per page). The 1.19.0 fix only caught a page that came back with
fewer articles than asked for; it didn't catch this exact-multiple
case, which depended on WP_Query's own page count, and that count
isn't reliable with this grid's featured-articles-first query. The
resources grid endpoint now fetches one extra article beyond what it
shows, purely to answer "is there another page" from what actually
came back rather than from a page count at all.

NEW (1.19.0): "Load more" on the resources grid no longer shows once
there's genuinely nothing left to load -- it now also checks that the
page just loaded actually came back full, not just what the server's
page count claimed. Loading articles now shows skeleton placeholder
cards, sized the same as real ones, instead of a plain "Loading
articles..." line, so the page no longer visibly resizes/jumps once
the real cards land. The popup's Close button is now pinned to the
modal's own top-right corner, outside the row of header buttons, so it
never moves regardless of how many are showing (Delete, Edit in
WordPress, Swipe book link, Article Assist, help); Delete this article
is restyled as a smaller, quieter icon+label link there too, since it
no longer needs to compete visually with the other header buttons.

IMPORTANT FIX (1.18.2): an article whose only category used to be
Uncategorized (WordPress's own default) could vanish from the grouped
resources grid entirely after ticking an additional real category and
saving, even though the new category looked ticked in the popup. The
"primary category" (which section the article appears under) only got
replaced automatically when it was completely unset, so it stayed
Uncategorized, and the grouped view drops that whole bucket outright.
Saving an article now never leaves its primary category stuck on
Uncategorized while a real category is also ticked. If this already
happened to an article, opening it in the popup and clicking Update
(no other change needed) fixes it, since this runs on every save.

FIX (1.18.1): article titles and excerpts with an apostrophe, or a
trimmed excerpt's "...", could still show as literal "&#8217;" or
"&hellip;" text on the resources grid, swipe book and elsewhere,
despite an earlier fix for this same bug. The earlier fix used
wp_specialchars_decode(), which only reverses &amp;/&lt;/&gt;/&quot;/
&#039; and never actually touched &#8217; or &hellip; themselves, so
it never worked for this specific case. Switched to
html_entity_decode(), which decodes every named and numeric HTML
entity back into a real character.

NEW (1.18.0): a "Delete this article" button now sits at the top of the
popup while editing an existing article, moving it to the Trash (not a
permanent delete, recoverable from wp-admin same as any other trashed
post). The swipe book's page transitions are rebuilt to match Therapy
Resource Directory's own book reader: a page now slides fully off and
the next one slides fully on (no more cross-fade), on the same timing
and easing, whether tapping the arrows or swiping on touch or with a
mouse. The old swipe drag also had an inline transform fighting the
slide's own CSS transition, which was the actual cause of the jerky
feel; a swipe now just reads its direction on release, same as TRD's
reader, and lets one clean transition handle the rest.

NEW (1.17.1): renamed "Import JSON" to "Paste JSON", and made it a
clearer two-step flow. Fill fields from JSON is now the highlighted
button on that tab, and the title, excerpt, details, SEO and Save
Draft/Publish all stay out of the way until you actually click it, so
there is nothing to publish by mistake before a draft has been filled
in. Clicking it still switches you to the Visual tab with everything
filled in and editable, same as before.

NEW (1.17.0): the swipe book now opens as an overlay right over the
resources/article page you were already on (dimmed and blurred behind
it), in a fixed-size book card centred on screen, instead of navigating
away to a full-page view -- a shared link still opens the full
standalone page as before, for when there's no page behind it to show.
Slide transitions are smoother (matched fade/move timing, no more
mismatched jerk between them). Also new: a swipe book can end on a bio
slide about you. Set it up once under "Customise this grid" > Profile
page (photo, name, a short bio, and an optional link button), then tick
"Include bio page at end" on any article in the popup to close that
article's swipe book with it. Leave the toggle off, or the profile
name blank, and articles end with the plain "Thanks for reading" slide
as before.

FIX (1.16.3): comments and pingbacks are now always off on an article
saved through this popup, regardless of the site's own default comment
setting. WordPress opens comments on a new post by default, which a
client's article isn't meant to have. Re-saving an existing article
through the popup (Edit This Article, then Update) closes comments on
it too; to close comments on many existing articles at once without
opening each one, use the Posts list's own Bulk Actions, Edit,
Comments: Do not allow.

NEW (1.16.2): the "Getting Started" article created on first activation
now leads with Article Assist (with a direct link straight into it),
rather than listing it as a third option after Visual and HTML. Take
an idea or something already written to Article Assist, paste the JSON
it hands back into the Import JSON tab, and get a categorised, fully
editable article, SEO done, ready to publish and automatically share
as a swipe book. Only affects sites activating the plugin from here on.

FIX (1.16.1): the grouped resources view could leave a stray "Loading
articles..." line showing under the grid after it had actually
finished loading (the grid's own forced display:grid style was
beating the browser's built-in hidden-attribute behaviour). The swipe
book can now also be dragged with the mouse on desktop, not just
swiped on a touchscreen, and its card has a layered "page stack" shadow
instead of a single flat drop shadow, closer to a real book reader.

NEW (1.16.0): the category dropdown now sits before the search box, and
the separate "Show all categories" button is gone -- a "Show all"
option at the bottom of the dropdown returns to the default landing
view instead. "Uncategorized" (or whatever a site's own default
category is named) no longer counts as a real category anywhere: not
in the dropdown, not in the admin's category order list, and it never
takes a section on the grouped landing view. The "Customise this grid"
popup also has a "Read more" link colour picker now, defaulting to a
darker blue, with the link itself darkening slightly on hover.

NEW (1.15.0): Settings > Article Publisher's "New Article" button
setting is now three choices instead of one checkbox: everywhere
(unchanged default), only on pages with the [twd_articles] shortcode,
or not shown at all (Edit This Article still works regardless). An
already-configured site keeps its previous behaviour automatically
after updating.

NEW (1.14.0): a "Getting Started With Your New Articles Plugin" article
is now published automatically the first time the plugin is activated
on a site, so the resources page has real content on day one and new
users have a walkthrough of the New Article button, the Visual, HTML
and Import JSON tabs, the primary-category star, the resources page
and its settings gear, and the swipe book, without leaving the site. Edit
it into your own message or delete it once your team is comfortable.
Created once only, even across deactivate and reactivate.

NEW (1.13.3): renamed to "Articles & Resource Production Plugin" (from
"TWD Article Publisher") so it sorts near the top of the alphabetical
Plugins list in wp-admin. Nothing else changed: same file, same
settings, same shortcode, same update feed. WordPress matches plugins
by file path, not display name, so this update applies cleanly and
future updates will too.

FIX (1.13.2): "Could not reach the update server" now shows the actual
reason underneath (a timeout, a blocked request, an HTTP error code
from GitHub) instead of nothing else, so a site whose host is blocking
the update check can actually be diagnosed instead of guessed at.

NEW (1.13.1): the swipe book page is restyled to a dark, gold-accented
look (Cormorant Garamond serif headings, thin gold hairlines, a gold
progress bar and pager) closer to Therapy Resource Directory's own
book reader, in place of the earlier bright green/blue gradient. No
behaviour changed, styling only.

NEW (1.13.0): the [twd_articles] grid now has a grouped-by-category
landing view by default: the top categories (by number of articles),
each as its own titled section, no article repeated across sections.
A category picks up a "primary category" for this purpose via a small
star next to each ticked category in the popup's Categories field
(defaults to the first ticked one if never explicitly set). A dropdown
next to the search box jumps straight to one category's full list. For
anyone who can publish articles, a small gear icon opens a "Customise
this grid" popup right on the page: how many categories show, how many
articles per category, drag-and-drop category order, and a "Show
thumbnails" on/off toggle. Existing [twd_articles category="..."] or
[twd_articles tag="..."] pages, and category/tag archive pages, are
unaffected and keep their plain flat list as before, since grouping
only applies to the bare, unfiltered [twd_articles] shortcode.

NEW (1.12.0): every published article can now be viewed and shared as a
swipe book. A "View as a swipe book" link appears under every article on
the public site, splitting it into swipeable slides (by heading and
length, no AI call needed) with a progress bar, swipe/arrow-key/button
navigation, and a Share button. It is always a live view of the current
article, generated fresh each time, so it never goes out of date and
needs nothing saved separately. While editing an existing published
article in the popup, a "Swipe book link" now also appears next to
"Edit in WordPress" for a quick way to grab the link right after
publishing.

FIX (1.11.1): scrolling inside the popup could bleed through and scroll
the page behind it once you reached the top or bottom of the popup's
own content. Now contained to the popup itself, and the page behind is
locked from scrolling more reliably across different themes.

NEW (1.11.0): the popup is renamed "Resource Maker" and restyled with a
softer, calmer colour palette (sage green, warm cream, dusty blue) in
place of the old bright blue. It now shows the site's own logo (from
Customize > Site Identity) next to the heading if one is set. The
"Instructions for use" link is now a small circular "?" icon in the
header instead of a text link at the bottom. The "Draft with Article
Assist" link is now a proper button, always visible in the header, not
just on the Import JSON tab.

NEW (1.10.2): the Import JSON tab has a "Draft with Article Assist"
link that opens Therapy Resource Directory's Article Assist tool in a
new tab, landing straight in the popup ready to write. Needs a Therapy
Resource Directory account (Resource Creator plan or above). A plain
new-tab link on purpose, not an embedded popup, since Article Assist
needs the visitor logged into that separate site.

NEW (1.10.1): a "Check for updates" link now sits right on the plugin's
own row on the main Plugins list (next to Deactivate), so you no longer
need to visit Settings > Article Publisher just to trigger an immediate
check. Clicking it forces the same instant recheck and shows the result
right there.

NEW (1.10.0): an "Import JSON" tab lets you paste a JSON block (title,
seo_title, meta_description, category, tags, html) and fill the whole
popup in one go, then review and edit before saving. Designed to pair
with TRD's Article Assist tool, but works with any JSON of that shape.
Visual stays the default tab; nothing is required to use JSON.

IMPORTANT FIX (1.9.1): the [twd_articles] grid was silently excluding
almost every article that had never been marked "Feature this article",
on every site, since v1.8.0. Update to this version as soon as possible.

Em dashes and en dashes are now automatically stripped from every field
(title, excerpt, tags, article body, category names, Yoast fields) before
saving, converted to a comma or dropped where they'd double up on other
punctuation. Handles both the real character and pasted HTML-entity forms.
This applies regardless of whether the text was typed, pasted from an AI
assistant, or generated by any AI tool, so it can never slip through.

Fixed: titles/excerpts with an apostrophe or the automatic "..." on a
trimmed excerpt could show as literal "&#8217;" or "&hellip;" text in the
[twd_articles] grid, instead of rendering as punctuation.

A "Check for updates" button on Settings > Article Publisher forces an
immediate check instead of waiting for the automatic 12-hour cycle.

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
