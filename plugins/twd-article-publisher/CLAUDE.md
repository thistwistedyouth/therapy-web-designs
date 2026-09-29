# Articles & Resource Production Plugin (formerly "TWD Article Publisher")

A WordPress plugin, generic and reusable across every Therapy Web Designs client site. It lets an approved logged-in user (a therapist, not a developer) publish and edit blog posts from a popup on the live front end, without going into wp-admin. It was built to solve one recurring problem: therapists writing blog content with an AI assistant and needing a fast way to get formatted HTML onto their site as a properly tagged, SEO-ready post.

Not tied to any one client's design. Header, footer, page CSS, per-site palettes and the "single HTML widget per page" Elementor build method (documented separately in Therapy Web Designs' own build notes) are unrelated to this plugin and live in each client site's own theme/Elementor content, not here.

## What it does

- Adds a floating **New Article** button on every front-end page, visible only to logged-in users in an allowed role.
- Adds a floating **Edit This Article** button on any single post the current user has permission to edit.
- Both open the same popup: title, a Visual/HTML dual-mode editor (inline images from the Media Library, YouTube embeds, click-to-align/drag-to-resize on inline images), category checkboxes plus an inline "add category", a tags field with autocomplete, a featured image picker (native Media Library), an excerpt field, Yoast SEO title/description (shown only if Yoast is active), and Save Draft / Publish / Schedule.
- An "Instructions for use" link opens a built-in help screen (toolbar/shortcode/AI-prompt reference) so a therapist never needs this file.
- An "Edit in WordPress" link opens the normal wp-admin editor for the article being edited. Deliberately no "Edit with Elementor" link — see Conventions below.
- Every published article gets its own baseline typography (`class-twd-ap-article-style.php` + `assets/article-content.css`) for every visitor, not just logged-in editors, so articles read well even on a bare Hello Elementor site with no custom Single Post template.
- `[twd_articles category="" tag="" count="9" columns="3"]` shortcode renders a fully interactive resources grid: search box, category pills, "Load more" pagination, all client-side against a public REST endpoint, no page reload. Featured articles (the "Feature this article" checkbox in the popup, stored as `_twd_ap_featured` post meta) sort first, then newest-first. Given an explicit `category`/`tag` attribute it becomes a curated list with no search/filter UI; given neither, it auto-detects `is_category()`/`is_tag()` so the same bare shortcode works unmodified as a per-category archive page.
- Everything is a normal WordPress post underneath. Nothing here replaces or forks core post storage; this is a front-end convenience layer over `wp_insert_post` / `wp_update_post`.

## Why it's built this way

- **The plugin's display name changed to "Articles & Resource Production Plugin" (from "TWD Article Publisher") so it sorts near the top of wp-admin's alphabetically-sorted Plugins list, on request.** Only the `Plugin Name` header (and the matching `readme.txt` title and `TWD_AP_Updater::plugin_info()`'s `$info->name`) changed. The folder name, main file name, text domain, REST namespace (`twd-publisher/v1`), all `TWD_AP_*` class/constant names, and the self-hosted updater's `SLUG` constant deliberately stayed as `twd-article-publisher` throughout — changing any of those would risk breaking the updater or require a full uninstall/reinstall on every client site for a purely cosmetic rename.
- **A "Getting Started" article is auto-created once on activation (`class-twd-ap-starter-content.php`), published immediately, not saved as a draft.** A new client's resources page would otherwise be an empty grid on day one, and a published post can be opened straight from the front end with "Edit This Article" to practice with, unlike a draft (no stable public permalink to reach it from). Guarded by the `twd_ap_starter_created` option so it is created exactly once ever, even across deactivate/reactivate or if the article itself is later edited or deleted. Its content only uses tags already in `TWD_AP_Sanitizer::clean()`'s allow-list, so re-saving it through the popup later never silently strips anything.
- **"Only on pages with the shortcode" (Settings > Article Publisher, the "New Article" button scope) is detected at render time, not with `has_shortcode()`.** Same reasoning as the asset-enqueue note below: a shortcode inside an Elementor widget lives in `_elementor_data` JSON, invisible to `has_shortcode()`. `TWD_AP_Shortcode::render()` sets a static flag (`rendered_on_this_page()`) the moment the shortcode actually executes, read by `TWD_AP_Frontend::should_show_new_button()` only from code hooked to `wp_footer` (never from the earlier `wp_enqueue_scripts`-hooked `maybe_enqueue()`, which enqueues assets unconditionally for any allowed user regardless of button visibility) -- `wp_footer` always runs after the page's own content, and so the shortcode if present, has already rendered by then.
- **"Uncategorized" (or whatever the site's `default_category` option currently points to, rename-proof) never counts as a real category.** Excluded from the category dropdown (`list_facets()`), the admin's category-order list (`get_grid_settings()`), and the grouped landing view (`list_articles_grouped()` unsets it from `$by_category` before ordering) — it is WordPress's own fallback bucket for a post nobody categorised, not content, and being the biggest category by default would otherwise crowd out categories that actually say something about the site. A post whose only category is this one simply does not appear on the grouped view; search still finds it by text.
- **The category dropdown always shows a "Show all" option, generated last, not a separate "All categories" placeholder up front.** Positioned last in the option list on purpose (dropdown-before-search in the toolbar reads as "narrow down," so the reset option belongs at the end, not competing with real categories for the first, most-noticed slot); it still works as the initially-selected option regardless of its position, since a `<select>`'s displayed value follows whichever `<option>` matches the value set on it, not DOM order. Picking it clears both the category and any active search term and returns to the grouped landing view, replacing what used to be a separate "Show all categories" button.
- **The "Read more" link colour is a per-site setting (`read_more_color` in `TWD_AP_Grid_Settings`), not inherited from the theme's own CSS.** Sniffing a theme's real link/hover colour at runtime (reading computed styles from some nearby theme element) is fragile and theme-specific; a plain colour picker in "Customise this grid," applied via a CSS custom property (`--twd-ap-readmore-color`) with a sensible darker-blue default (`#1d4ed8`), gives the same practical result (matching a client's brand colour) without that fragility. Hover darkens it via `filter: brightness(0.8)` rather than a second colour picker, so there is only one colour to set.
- **`.twd-ap-articles-grid[hidden]` needs an explicit `display: none !important` override, same class of bug as the T2LU project's `[hidden]` gotcha.** The grid's own `display: grid !important` rule beats the browser's UA-stylesheet `[hidden] { display: none }` default regardless of specificity, so toggling `.hidden` on it while switching between the grouped and flat views left the old "Loading articles…" placeholder visibly showing underneath the grouped sections. Any element this stylesheet gives its own `!important` `display` needs the same explicit `[hidden]` override, or hiding it via JS silently does nothing.
- **Swipe book navigation uses Pointer Events (`assets/swipebook.js`), not separate touch and mouse handlers.** One set of `pointerdown`/`pointermove`/`pointerup` listeners covers touch, mouse and pen, matching how TRD's own reader supports a mouse drag on desktop, not just a touch swipe. `setPointerCapture` keeps move/up events firing even if the pointer leaves the slide area mid-drag. The active card visually follows the pointer horizontally while dragging (eased to 60% of the raw delta) and springs back with a short transition if released short of the 60px threshold, rather than only reacting on release.
- **The swipe book's card gets a layered, offset box-shadow ("page stack") rather than a single drop shadow**, echoing TRD's own reader card, so it reads as a bound book's page block rather than a plain floating panel.
- **Custom REST routes instead of `wp/v2/posts`.** The core posts endpoint doesn't give a clean hook point to force server-side HTML sanitisation before save. A dedicated namespace (`twd-publisher/v1`) means every write funnels through `TWD_AP_Sanitizer::clean()` first, no exceptions.
- **Sanitise on the server, never trust the browser.** The popup can receive AI-generated HTML of unknown quality. `wp_kses` with an explicit allow-list strips scripts, inline styles and event handlers regardless of what the client sends. Any `<h1>` is demoted to `<h2>` so a pasted article never fights the theme's own post-title H1.
- **Vanilla JS, no build step.** `execCommand` was chosen over pulling in TinyMCE or a bundler because this plugin has to drop into any client's WordPress with zero dependency risk. It is deliberately simple rather than feature-complete.
- **Capability check duplicated in two places on purpose.** `TWD_AP_Frontend::current_user_allowed()` (role-based, for creating) and `current_user_can( 'edit_post', $id )` (WordPress's own per-post capability, for editing) are both enforced. Don't collapse these into one check; they answer different questions.
- **No "Edit with Elementor" button, on purpose.** Once a post is opened and saved in Elementor's builder, Elementor can switch it to render from its own `_elementor_data` postmeta instead of `post_content` on the front end — which would make future edits made through this popup silently stop showing up. That tradeoff isn't this plugin's to make for a client site, so only a plain "Edit in WordPress" (classic wp-admin editor) link is offered.
- **YouTube embeds are click-to-play placeholders, not raw iframes.** The sanitiser's allow-list stays narrow (no `iframe` tag at all) by storing `<figure data-youtube-id="...">` with a thumbnail `<img>`, and swapping it for a real `youtube-nocookie.com` iframe client-side (`assets/article-content.js`) only when a visitor clicks it. Don't "simplify" this by allowing raw iframes through `wp_kses` — that reopens the exact hole the sanitiser exists to close.
- **Image alignment/resize uses `width`/`height` attributes, never inline `style`.** `wp_kses` doesn't allow a `style` attribute on `img`, so the resize handle in `assets/publisher.js` sets `width`/`height` attributes directly instead — that survives sanitisation. Alignment is CSS classes (`twd-ap-img-left/-center/-right/-full`), mirrored in both `publisher.css` (editor) and `article-content.css` (public), so what you build in the popup matches what visitors see.
- **`/articles` and `/articles/facets` REST routes are genuinely public** (`permission_callback => '__return_true'`) — the only routes in this plugin that are, since everything else gates on `check_permission()`. That's deliberate: the `[twd_articles]` grid has to work for anonymous site visitors, not just logged-in editors. Both only ever read published posts/terms; neither accepts anything that touches `wp_insert_post`/`wp_update_post`, so this doesn't reopen the write-path security model the rest of the REST class exists to enforce.
- **Featured-first ordering needs an explicit OR `meta_query`, never a bare `meta_key`.** A bare `'meta_key' => '_twd_ap_featured'` query var looks like it would just be a sort hint, but WP_Query turns it into an implicit meta_query clause that INNER JOINs postmeta — which excludes every post that doesn't have that meta row at all. This was shipped as a bug in v1.8.0 (a wrong assumption that it LEFT JOINs instead) and silently hid nearly every post on every site from the `[twd_articles]` grid, since `_twd_ap_featured` only exists on posts someone explicitly ticked "Feature this article" on. Fixed in v1.9.x with an explicit `relation => 'OR'` between a named `EXISTS` clause and a `NOT EXISTS` clause, so every post matches one branch or the other, while still being orderable by the named clause. If this ever needs touching again: test with a post that has never been featured and confirm it still appears in the grid, that's the exact case the bug broke.
- **The popup is branded "Resource Maker", not "New Article"/"Edit Article".** The heading text is fixed in the template; only a small subtitle (`#twd-ap-modal-subtitle`) switches between "New article" and "Editing article" via JS, so the product has one consistent name regardless of mode. The floating trigger buttons ("New Article"/"Edit This Article") keep their original labels since they describe the action, not the tool.
- **Popup colours are all CSS custom properties on `:root` in `publisher.css`, deliberately not client-branded.** Deliberately swapped from primary blue to a calm sage/cream/dusty-blue palette on request, since this runs on therapist sites, not a generic SaaS product. If a specific client ever needs their own brand colours in the popup, override the `--twd-ap-*` variables from that site's own theme CSS rather than forking this stylesheet.
- **The optional logo next to "Resource Maker" reads the site's own Customizer logo (`get_theme_mod('custom_logo')`), not a plugin setting.** Zero new admin UI needed, since nearly every client site already has a logo set there; the popup just picks it up automatically. Rendered as a plain `<img>`, not `the_custom_logo()`'s output, because that wraps it in a link to the homepage, which would navigate away from the page if clicked while the popup is open.
- **The "Draft with Article Assist" link (`TWD_AP_ARTICLE_ASSIST_URL` constant, now a header button, not just in the JSON Import panel) is a plain new-tab link, deliberately not an iframe popup.** Article Assist lives on a different domain (therapyresourcedirectory.com) and is gated to logged-in TRD members. Framing it cross-domain would depend on that site not sending frame-blocking headers and on the visitor's browser allowing the TRD login cookie inside a third-party iframe context, which Safari blocks by default and Chrome is phasing out. A new-tab link sidesteps both risks entirely and needs no site-specific testing. The `?aa=open` query param on the URL auto-opens the Article Assist modal on load (see `15 TRD Article Assist.php` in the therapy-resource-directory repo), so the link lands a member straight in the tool.
- **JSON import (`#twd-ap-tab-json` in `templates/buttons-and-modal.php`, `fillFromJson()` in `assets/publisher.js`) is a third tab, not the default, and never locks fields.** This plugin runs on multiple client sites, not all of which use a JSON-producing upstream tool (e.g. TRD's Article Assist) — defaulting to JSON or requiring it would break the plain typed/pasted-HTML workflow everywhere else. `fillFromJson()` just writes into the same title/excerpt/Yoast/tags/HTML fields a person would type into, then switches to the Visual tab so everything is reviewed and editable exactly as if typed, and category is matched-or-created via the existing `/categories` REST route rather than a new one.
- **`assets/articles-grid.css`/`.js` are enqueued unconditionally on every front-end page** (`wp_enqueue_scripts`, no `has_shortcode()` gate), same reasoning as `class-twd-ap-article-style.php`: a shortcode inside an Elementor widget lives in `_elementor_data` JSON, not plain `post_content`, so `has_shortcode()` can't reliably see it there, and enqueuing from inside the shortcode's own render callback is too late — that runs after `wp_head` has already fired on most themes, so the stylesheet would often not make it into `<head>` at all. Both files are small enough that loading them site-wide is the cheaper trade.
- **The swipe book (`class-twd-ap-swipebook.php`) is a live view, not a stored copy, and splits slides in plain PHP, not via an AI call.** A `?twd_ap_swipebook=1` query var on the article's own permalink renders it, so there's no new post type, no new storage, and it can never drift out of sync with the article, since every load re-reads the current `post_content`. Splitting is a DOMDocument walk over the top-level blocks, breaking at each `h2` and also whenever a running section passes ~420 characters, so no single slide is a wall of text; a continuation slide from a long section doesn't repeat its heading. No AI call, on purpose: this plugin runs on many client sites and none of them carry an Anthropic key of their own (only TRD and T2LU do), so an AI-assisted split would mean a new setup step and an ongoing cost per site, for a purely cosmetic improvement over algorithmic chunking.
- **The swipe book's dark gold-and-serif look (`assets/swipebook.css`) is a deliberate visual echo of Therapy Resource Directory's own book reader (`trd-resource-reader` plugin), on request, not a coincidence.** Google Fonts Cormorant Garamond (headings) and DM Mono (the counter/labels) are loaded directly in `class-twd-ap-swipebook.php`'s `<head>`, same fonts that reader uses. It's a from-scratch, much simpler stylesheet though, not a port: no page-turn animation, no cover/author/QR page types, none of that reader's other content-type system, since TWD's swipe book only ever has plain article slides.
- **The swipe book page bypasses the theme entirely** (`template_redirect` + `exit`, its own full `<!doctype html>`), rather than rendering inside the site's normal template, so it always looks the same regardless of the client's theme and never fights that theme's own CSS. It sets `noindex` (duplicate content of the real article) with a `rel=canonical` back to the article, and reads Open Graph fields (title, description, image) from the same source as the article itself for a clean share preview.
- **The grouped-by-category landing view (`list_articles_grouped()` in `class-twd-ap-rest.php`, `/articles/grouped`) groups by a post's "primary category", never a per-category exclusion loop.** Each post has exactly one primary category (`_twd_ap_primary_category` post meta, set via a star next to each ticked category in the popup's Categories field, falling back to the first ticked category if never explicitly set), so grouping is a single pass over all published posts into one bucket per primary category — a post can only ever land in one bucket by construction, no separate "already used" tracking needed. This only fetches once (`posts_per_page => 500`) rather than one `WP_Query` per category, so ordering (by true per-primary-category count, not WordPress's own per-term count, which counts every category a post has, not just its primary one) and the no-repeat rule come from one consistent source.
- **The grouped view only replaces the default, unfiltered `[twd_articles]` shortcode.** `[twd_articles category="..."]`, `[twd_articles tag="..."]`, and a plain `[twd_articles]` auto-detecting a category/tag archive page all keep the original flat, paginated list — grouping a page that's already scoped to one category/tag wouldn't make sense. The moment a visitor searches or picks a category from the dropdown on the grouped landing view, it also switches to the same flat list, with a "Show all categories" button to get back.
- **`TWD_AP_Grid_Settings` (category order, category/post counts, thumbnails) is a separate option and class from `TWD_AP_Settings` on purpose** — that one is about who can use the editor popup, a different concern from how the public grid displays. `category_order` only stores explicitly-ordered term IDs; categories not in it are appended afterward sorted by post count, so a newly created category is never silently invisible just for not being in a saved order list yet.
- **Category reordering in the "Customise this grid" popup (`assets/articles-grid.css`/`.js`) uses the native HTML5 Drag and Drop API, not a library.** Consistent with the rest of this plugin's "no build step, no dependencies" approach (see execCommand note above). `getDragAfterElement()` is the standard vanilla-JS midpoint-comparison technique for reordering a list by drag.

## File map

```
twd-article-publisher.php                Plugin bootstrap, loads all classes below
includes/class-twd-ap-settings.php       Settings > Article Publisher screen; allowed roles, default category
includes/class-twd-ap-grid-settings.php  TWD_AP_Grid_Settings option: category order/count, posts per category, thumbnails toggle
includes/class-twd-ap-sanitizer.php      TWD_AP_Sanitizer::clean() — the one place HTML gets cleaned
includes/class-twd-ap-rest.php           REST routes: /categories, /tags, /posts, /posts/{id}, /media, /articles, /articles/facets
includes/class-twd-ap-frontend.php       Enqueues editor assets, decides which buttons show, capability gate
includes/class-twd-ap-shortcode.php      [twd_articles] shortcode -- grid/group container, dropdown, admin "Customise this grid" popup markup
includes/class-twd-ap-article-style.php  Public article typography — the_content wrap + unconditional CSS enqueue
includes/class-twd-ap-swipebook.php      ?twd_ap_swipebook=1 -- renders any published article as a shareable swipe deck
includes/class-twd-ap-starter-content.php  Publishes a one-time "Getting Started" article on activation
includes/class-twd-ap-updater.php        Self-hosted update checker (admin-only); see Releases section below
templates/buttons-and-modal.php          The popup's HTML skeleton + the help modal (PHP-rendered once per page load)
assets/publisher.css                     Popup, button and image-toolbar styling (neutral, not client-branded)
assets/publisher.js                      All popup behaviour: tabs, toolbar, media picker, image align/resize, REST calls
assets/article-content.css               Public-facing single-article typography + YouTube/alignment styles
assets/article-content.js                Click-to-play swap for YouTube placeholders, public-facing
assets/articles-grid.css                 Public-facing [twd_articles] grid, toolbar, pills and card styling
assets/articles-grid.js                  Public-facing [twd_articles] behaviour: search (debounced), category pills, Load more, fetches /articles
assets/swipebook.css                     Full-page swipe book styling (own gradient, cards, progress bar; independent of publisher.css)
assets/swipebook.js                      Swipe book navigation: slide index, swipe/keyboard/button nav, Share button, YouTube click-to-play
readme.txt                               Standard WP plugin readme (also shown in Plugins list)
```

## Conventions to keep

- WordPress coding standards: tabs for indentation in PHP, `snake_case` functions, `Prefixed_Class_Names` with the `TWD_AP_` prefix on every class to avoid collisions on shared hosting.
- Every option, meta key and REST namespace is prefixed `twd_ap_` / `twd-publisher` — never assume this is the only plugin on the site.
- `TWD_AP_Sanitizer::clean()` is the only path HTML should ever take before `wp_insert_post`/`wp_update_post`. If a new field or endpoint accepts HTML, route it through here, don't add a parallel sanitiser.
- Yoast fields are optional and gated on `defined( 'WPSEO_VERSION' )`. Don't assume Yoast is present; check every time.
- No inline styles are allowed through the sanitiser by design (a Recommended-tier decision made when this was scoped). If a future client needs AI-generated inline styling preserved, that's a deliberate change to the allow-list in `class-twd-ap-sanitizer.php`, not a bug to silently fix.
- **`TWD_AP_Sanitizer::strip_dashes()` runs on every text field, not just the HTML body**, because an em or en dash reads as an obvious AI-written tell and this must never slip through regardless of source (typed, pasted from an AI assistant, or a future in-house AI tool). It's called from `build_post_args()` (title, excerpt), `after_save()` (tags, Yoast title/description), and `create_category()`, in addition to `clean()` for the article body itself. It handles the real Unicode character and the three common HTML-entity forms (`&mdash;`, `&#8212;`, `&#x2014;`, and the en-dash equivalents), since pasted content can carry either. If a new text field is ever added, run it through `strip_dashes()` too, don't assume `sanitize_text_field()` covers it (it doesn't touch dashes at all).
- Scheduling uses the site's local time (`current_time( 'mysql' )` / `get_gmt_from_date()`), not the browser's timezone. Keep it that way; don't introduce client-side timezone conversion.

## Known limitations / open items

- No automated test suite. Testing so far has been PHP lint (`php -l`) on every file, a Node syntax check on the JS, and a standalone PHP harness that stubs `wp_kses`/`apply_filters` to prove the sanitiser's own logic (H1 demotion, script/style stripping, `rel` injection on `target="_blank"` links) without a live WordPress install. See "Testing" below to redo this from scratch.
- `execCommand` (used for the Visual tab's Bold/Italic/heading toolbar) is a soft-deprecated browser API. It still works everywhere as of this writing, but if it's ever removed from browsers, the toolbar in `assets/publisher.js` needs replacing, most likely with a small dependency-free `contenteditable` command layer rather than a full editor library.
- No image compression or size limit on featured-image or inline-image uploads beyond what WordPress's own media handling already applies.
- Live-tested end-to-end on therapyresourcedirectory.com (create → categorise → tag → feature image → inline image/YouTube → publish → edit → reschedule → shortcode grid).
- No multisite-specific handling; assume single-site until proven otherwise.
- The self-hosted updater depends on this GitHub repo staying **public** (client sites fetch `dist/twd-article-publisher-update.json` with no auth). If it's ever made private again, every site's update check silently fails closed (no error shown, they just stop seeing new versions) until it's public again or the updater is repointed at a different public host.
- Native `<datalist>` tag autocomplete only really works for the last tag typed (or a single tag) — the browser suggests whole-field matches, not per-token. Fine as a lightweight nudge against typo'd duplicate tags; not a real tokenised tag picker.

## Testing

```bash
# PHP syntax
php -l twd-article-publisher.php
for f in includes/*.php templates/*.php; do php -l "$f"; done

# JS syntax
node --check assets/publisher.js
```

For sanitiser logic without a live WordPress install, stub `wp_kses` and `apply_filters`, then assert against known-bad input (script tags, `onclick`, inline `style`, an `<h1>`, a `target="_blank"` link). This was the method used during the original build; there's no committed test file for it yet, it was written and discarded as a one-off `/tmp` script — worth turning into a real `tests/` directory with a proper WP test stub if this plugin gets ongoing development.

## Releases & self-hosted updates

The plugin isn't on WordPress.org, so it has its own lightweight self-hosted
updater (`includes/class-twd-ap-updater.php`, admin-only). Every client site
checks `dist/twd-article-publisher-update.json` in **this repo** (must stay
**public** — client sites fetch it with no credentials) and, if its
`version` is newer than the installed one, shows the normal "update
available" row in wp-admin Plugins with a one-click update. Checked every
12 hours, cached in a transient, cache cleared on "Check again" or right
after an update runs.

To ship a new version to every client site running the plugin:

```bash
# 1. Bump the version in twd-article-publisher.php (both the header comment
#    and the TWD_AP_VERSION constant), and in readme.txt's Stable tag.

# 2. Build the distributable zip (must contain a single top-level
#    twd-article-publisher/ folder -- WordPress's plugin upgrader requires
#    that shape, a zip of the folder's *contents* will silently misinstall):
rm -f dist/twd-article-publisher-latest.zip
rm -rf /tmp/twd-build && mkdir -p /tmp/twd-build
cp -r plugins/twd-article-publisher /tmp/twd-build/twd-article-publisher
rm -f /tmp/twd-build/twd-article-publisher/CLAUDE.md
( cd /tmp/twd-build && zip -r -X "$OLDPWD/dist/twd-article-publisher-latest.zip" twd-article-publisher -x "*.git*" )

# 3. Update dist/twd-article-publisher-update.json:
#    bump "version" to match step 1, update "changelog", leave
#    "download_url" alone (it's a stable path, never needs to change).

# 4. Commit and push both the zip and the JSON.
```

`download_url` in the JSON always points at
`dist/twd-article-publisher-latest.zip` via `raw.githubusercontent.com` —
that path never changes, so step 3 only ever touches `version` and
`changelog`. Sites pick up the new version automatically within 12 hours,
or immediately if someone clicks "Check again" on their Plugins page.

For a one-off manual install (skipping the updater, e.g. trying a change on
a single site before it's "released" to everyone):

```bash
cd .. && zip -r -X twd-article-publisher.zip twd-article-publisher -x "CLAUDE.md" "*.git*"
```

Upload via **Plugins > Add New > Upload Plugin** on any client site. No
build step, no `npm install`, nothing to compile either way.
