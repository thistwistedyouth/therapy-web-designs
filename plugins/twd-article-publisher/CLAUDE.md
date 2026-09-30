# Articles & Resource Production Plugin (formerly "TWD Article Publisher")

A WordPress plugin, generic and reusable across every Therapy Web Designs client site. It lets an approved logged-in user (a therapist, not a developer) publish and edit blog posts from a popup on the live front end, without going into wp-admin. It was built to solve one recurring problem: therapists writing blog content with an AI assistant and needing a fast way to get formatted HTML onto their site as a properly tagged, SEO-ready post.

Not tied to any one client's design. Header, footer, page CSS, per-site palettes and the "single HTML widget per page" Elementor build method (documented separately in Therapy Web Designs' own build notes) are unrelated to this plugin and live in each client site's own theme/Elementor content, not here.

This file is the short version: rules, not stories. **HISTORY.md** has the full incident write-ups (what broke, what was tried, why the fix looks the way it does) for anything below that has one — only worth opening if you're debugging something in that specific area.

## What it does

- Adds a floating **New Article** button on every front-end page, visible only to logged-in users in an allowed role.
- Adds a floating **Edit This Article** button on any single post the current user has permission to edit.
- Both open the same popup: title, a Visual/HTML dual-mode editor (inline images from the Media Library, YouTube embeds, click-to-align/drag-to-resize on inline images), category checkboxes plus an inline "add category", a tags field with autocomplete, a featured image picker (native Media Library), an excerpt field, Yoast SEO title/description (shown only if Yoast is active), and Save Draft / Publish / Schedule.
- An "Instructions for use" link opens a built-in help screen (toolbar/shortcode/AI-prompt reference) so a therapist never needs this file.
- An "Edit in WordPress" link opens the normal wp-admin editor for the article being edited. Deliberately no "Edit with Elementor" link — see Conventions below.
- Every published article gets its own baseline typography (`class-twd-ap-article-style.php` + `assets/article-content.css`) for every visitor, not just logged-in editors, so articles read well even on a bare Hello Elementor site with no custom Single Post template.
- `[twd_articles category="" tag="" count="9" columns="3"]` shortcode renders a fully interactive resources grid: search box, category pills, "Load more" pagination, all client-side against a public REST endpoint, no page reload. Featured articles (the "Feature this article" checkbox in the popup, stored as `_twd_ap_featured` post meta) sort first, then newest-first. Given an explicit `category`/`tag` attribute it becomes a curated list with no search/filter UI; given neither, it auto-detects `is_category()`/`is_tag()` so the same bare shortcode works unmodified as a per-category archive page.
- Everything is a normal WordPress post underneath. Nothing here replaces or forks core post storage; this is a front-end convenience layer over `wp_insert_post` / `wp_update_post`.

## Architecture rules

One line each: the rule to follow, and (in parens) where to look if you need
to touch that area. Full backstory for any of these — what broke, what was
tried first, why — lives in **HISTORY.md**; skip it unless you're debugging
something in that specific area or a similar decision has come up again.

**Naming & branding**
- Display name is "Articles & Resource Production Plugin"; folder/file/class/REST-namespace/updater-slug all stay `twd-article-publisher`/`TWD_AP_`/`twd-publisher` regardless — never rename those to match.
- Popup is branded "Resource Maker" everywhere (`#twd-ap-modal-subtitle` is the only part that changes, New/Editing).
- Popup colours are CSS custom properties (`--twd-ap-*` in `publisher.css`), sage/cream/dusty-blue, not client-branded — override the variables from a site's own theme CSS if a client needs their brand colours here, don't fork the stylesheet.

**Grid & categories**
- "Uncategorized" (via the site's real `default_category` option, not a hardcoded name) is never a real category — excluded from the dropdown, category-order list, and grouped view.
- The category dropdown's "Show all" option is generated last, resets both category and search, returns to the grouped landing view.
- `read_more_color` and `accent_color` (`TWD_AP_Grid_Settings`) are the only per-site colour knobs, applied via `--twd-ap-readmore-color`/`--twd-ap-accent-color` — don't sniff theme colours at runtime. `accent_color` drives the search/dropdown focus border and category "n more" links only (it used to also drive a Featured badge/border, removed — see HISTORY.md).
- `format_article_card()` prefers a post's non-"Featured Articles" category for its eyebrow label — that category is the grouped view's own section-heading bucket, not a topic. Hardcoded name match, not ID-based; breaks silently if a client renames that category.
- Grouping (`list_articles_grouped()`) is by primary category (`_twd_ap_primary_category`), one pass over all published posts, one bucket per post — don't reintroduce a per-category exclusion loop.
- Grouped view only replaces the default unfiltered `[twd_articles]`; an explicit `category`/`tag` attribute or an auto-detected archive page always stays a flat list.
- Featured articles (`_twd_ap_featured`) still sort first server-side; there's no visual "featured" treatment on cards any more (removed — see HISTORY.md). Ordering needs an explicit OR `meta_query` (`EXISTS`/`NOT EXISTS`), never a bare `meta_key` — see HISTORY.md for why that matters.
- `assets/articles-grid.css`/`.js` are enqueued unconditionally site-wide (no `has_shortcode()` gate — it can't see a shortcode buried in Elementor's `_elementor_data` JSON).

**Editor popup**
- Capability checks are duplicated on purpose: `current_user_allowed()` (role, for creating) and `current_user_can('edit_post', $id)` (WP's own, for editing) — don't collapse them.
- No "Edit with Elementor" link, ever — see HISTORY.md for what that would silently break.
- Image alignment/resize uses `width`/`height` attributes and CSS classes, never inline `style` (`wp_kses` strips `style` on `img` anyway).
- JSON import is a third tab, never the default, never locks fields — `fillFromJson()` just fills the same fields a person would type, then switches to Visual for review.
- Clicking Update/Schedule on an already-published article closes the popup automatically; a new Publish or a Save as Draft leaves it open (`wasEditing` flag in `submitPost()`, `assets/publisher.js`).
- The optional popup logo reads the site's own Customizer logo (`get_theme_mod('custom_logo')`), rendered as a plain `<img>` (not `the_custom_logo()`, which wraps it in a homepage link).
- "Draft with Article Assist" is a plain new-tab link, never an iframe (cross-origin cookie/frame-blocking issues on Safari/Chrome — see HISTORY.md).

**Sanitisation & REST**
- `TWD_AP_Sanitizer::clean()` is the only path HTML takes before `wp_insert_post`/`wp_update_post` — route any new HTML-accepting field/endpoint through it, don't add a parallel sanitiser. No inline styles allowed through it, by design.
- `strip_dashes()` runs on every text field (title, excerpt, tags, Yoast, category names), not just the body — a new text field needs this explicitly, `sanitize_text_field()` doesn't touch dashes.
- YouTube embeds are click-to-play placeholders (`<figure data-youtube-id>`), never raw iframes through `wp_kses` — don't "simplify" this, it reopens the sanitiser's one deliberate hole.
- Custom REST routes (`twd-publisher/v1`), not `wp/v2/posts`, so every write funnels through the sanitiser.
- `/articles` and `/articles/facets` are the only genuinely public REST routes (`__return_true`) — both read-only, never touching `wp_insert_post`/`wp_update_post`.

**Misc**
- `.hidden`-toggled elements always need their own `[hidden] { display: none !important; }` rule, unconditionally — a page builder's own `!important` button/element styling can beat the browser's non-`!important` `[hidden]` default. Add this the moment a new element gets `.hidden` toggled onto it, not reactively after a client reports it's still showing. Full incident in HISTORY.md.
- Comments/pingbacks are force-closed on every save through the popup (creation and re-save alike), regardless of the site's own default.
- A "Getting Started" article auto-publishes once on activation (`twd_ap_starter_created` option guards it to exactly once).
- "Only on pages with the shortcode" (New Article button scope) is detected via a render-time flag (`rendered_on_this_page()`), not `has_shortcode()` — same Elementor JSON-visibility problem as the asset-enqueue rule above.
- Vanilla JS, no build step, anywhere in this plugin (`execCommand` for the popup toolbar, native Drag and Drop for category reordering) — this has to drop into any client's WordPress with zero dependency risk.

**Swipe book**
- Live view, not a stored copy — every load re-reads `post_content`, splits into slides in plain PHP (DOMDocument, by `h2` and ~420-char length), no AI call (most client sites carry no Anthropic key).
- Two entry paths (`?twd_ap_swipebook=1` standalone page; in-page overlay via `swipebook-inline.js`) share one markup source (`buildMarkup()` in `assets/swipebook.js`) and one data shape (`TWD_AP_Swipebook::get_payload()`) — never let them diverge.
- Standalone page bypasses the theme entirely (`template_redirect` + `exit`, own `<!doctype html>`), `noindex` + canonical back to the real article.
- Navigation is Pointer Events (`pointerdown`/`move`/`up`), not separate touch/mouse handlers.
- Bio slide is opt-in per article (`_twd_ap_include_bio`) but reads one site-wide profile (`TWD_AP_Grid_Settings`), not per-article fields — falls back to a plain "Thanks for reading" slide if no profile name is set.
- Titled "Summary Book" with a larger logo; progress bar sits under the page-number counter, compact, not across the top; slide cards are centred "quote cards" with `clamp()`-based text sizing.
- Save as image / Download as PDF both capture the DOM via html2canvas, never a separately-built layout. PDF has to step every slide to `is-active` in turn first (`captureAllCards()`, under `.twd-sb-exporting` to kill the transition) since `.twd-sb-slides` clips anything not at `translateX(0)` — see HISTORY.md, this shipped single-page-only once before being fixed. Both html2canvas and jsPDF are lazy-loaded from cdnjs on first use.

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
includes/class-twd-ap-swipebook.php      ?twd_ap_swipebook=1 -- renders any published article as a shareable swipe deck; get_payload() also feeds the overlay REST route
includes/class-twd-ap-starter-content.php  Publishes a one-time "Getting Started" article on activation
includes/class-twd-ap-updater.php        Self-hosted update checker (admin-only); see Releases section below
templates/buttons-and-modal.php          The popup's HTML skeleton + the help modal (PHP-rendered once per page load)
assets/publisher.css                     Popup, button and image-toolbar styling (neutral, not client-branded)
assets/publisher.js                      All popup behaviour: tabs, toolbar, media picker, image align/resize, REST calls
assets/article-content.css               Public-facing single-article typography + YouTube/alignment styles
assets/article-content.js                Click-to-play swap for YouTube placeholders, public-facing
assets/articles-grid.css                 Public-facing [twd_articles] grid, toolbar, pills and card styling
assets/articles-grid.js                  Public-facing [twd_articles] behaviour: search (debounced), category pills, Load more, fetches /articles
assets/swipebook.css                     Swipe book styling (backdrop/frame, cards, progress bar, bio slide; independent of publisher.css)
assets/swipebook.js                      buildMarkup()/mount(): shared template + nav (slide index, swipe/keyboard/button, Share, YouTube click-to-play), used by both entry paths
assets/swipebook-inline.js                Intercepts the "View as a swipe book" click on an article page, fetches /articles/{id}/swipebook, opens the in-page overlay
readme.txt                               Standard WP plugin readme (also shown in Plugins list)
```

## Conventions to keep

- WordPress coding standards: tabs for indentation in PHP, `snake_case` functions, `Prefixed_Class_Names` with the `TWD_AP_` prefix on every class to avoid collisions on shared hosting.
- Every option, meta key and REST namespace is prefixed `twd_ap_` / `twd-publisher` — never assume this is the only plugin on the site.
- Yoast fields are optional and gated on `defined( 'WPSEO_VERSION' )`. Don't assume Yoast is present; check every time.
- **`TWD_AP_Sanitizer::strip_dashes()` runs on every text field, not just the HTML body**, because an em or en dash reads as an obvious AI-written tell and this must never slip through regardless of source (typed, pasted from an AI assistant, or a future in-house AI tool). It's called from `build_post_args()` (title, excerpt), `after_save()` (tags, Yoast title/description), and `create_category()`, in addition to `clean()` for the article body itself. It handles the real Unicode character and the three common HTML-entity forms (`&mdash;`, `&#8212;`, `&#x2014;`, and the en-dash equivalents), since pasted content can carry either. If a new text field is ever added, run it through `strip_dashes()` too, don't assume `sanitize_text_field()` covers it (it doesn't touch dashes at all).
- Scheduling uses the site's local time (`current_time( 'mysql' )` / `get_gmt_from_date()`), not the browser's timezone. Keep it that way; don't introduce client-side timezone conversion.
- **Every CSS declaration in a stylesheet that gets injected into the theme's own page (`publisher.css`, `articles-grid.css`, `article-content.css`, and, since the overlay in v1.17.0, `swipebook.css` too) needs `!important` on every visual property (colour, background, border, font, box-shadow, padding, radius), not just the ones that look obviously contested.** This plugin runs on client sites built with page builders (Elementor especially), whose own global button/link/heading styles carry high specificity and load after ours. `swipebook.css` went unguarded for its first several versions because the swipe book was originally a fully standalone document (`template_redirect` + its own bare `<!doctype html>`, no theme CSS ever loaded), nothing to fight there. Once the in-page overlay mode appended that same markup into the live theme page's DOM, Elementor's own button styling started winning over the book's Close/prev/next/Share buttons, leaving them looking like bare default buttons. Any future feature that starts as a standalone/isolated document and later grows an in-page or overlay mode needs this same audit the moment it starts sharing a DOM with the theme, not before.

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
#    that shape, a zip of the folder's *contents* will silently misinstall).
#    Use cp -r, not rsync -- not every environment this runs in has rsync
#    installed, and a script that chains rsync into the same command as the
#    zip step with `set -e` can silently skip straight to zipping an EMPTY
#    or STALE directory if rsync isn't found, producing a zip that looks
#    fine (right file listing, plausible sizes) but is actually last
#    version's content. This happened once; cp -r has no such dependency.
rm -rf /tmp/twd-build && mkdir -p /tmp/twd-build
cp -r plugins/twd-article-publisher /tmp/twd-build/twd-article-publisher
rm -f /tmp/twd-build/twd-article-publisher/CLAUDE.md /tmp/twd-build/twd-article-publisher/HISTORY.md
find /tmp/twd-build/twd-article-publisher -name '.git*' -exec rm -rf {} + 2>/dev/null || true
rm -f dist/twd-article-publisher-latest.zip
( cd /tmp/twd-build && zip -r -q "$OLDPWD/dist/twd-article-publisher-latest.zip" twd-article-publisher )

# 2b. VERIFY the zip actually contains this build, not a stale one, before
#     going any further -- diff at least one file you just changed:
rm -rf /tmp/twd-verify && mkdir /tmp/twd-verify
( cd /tmp/twd-verify && unzip -q "$OLDPWD/dist/twd-article-publisher-latest.zip" )
diff -q /tmp/twd-verify/twd-article-publisher/<a file you edited> \
        plugins/twd-article-publisher/<same file>   # must print nothing
ls /tmp/twd-verify/twd-article-publisher | grep -i claude   # must print nothing

# 3. Update dist/twd-article-publisher-update.json:
#    bump "version" to match step 1, update "changelog", leave
#    "download_url" alone (it's a stable path, never needs to change).
python3 -c "import json; json.load(open('dist/twd-article-publisher-update.json'))"  # must not error

# 4. Commit and push both the zip and the JSON.
```

`download_url` in the JSON always points at
`dist/twd-article-publisher-latest.zip` via `raw.githubusercontent.com` —
that path never changes, so step 3 only ever touches `version` and
`changelog`. Sites pick up the new version automatically within 12 hours,
or immediately if someone clicks "Check again" on their Plugins page.

There are no git tags for any released version as of v1.25.0 — every
version lives only as a commit message and a line in `readme.txt`'s
prose changelog. `git log --oneline -- plugins/twd-article-publisher/twd-article-publisher.php`
finds the commit for any given version if a rollback is ever needed;
tagging each release going forward (`git tag v1.25.0 <commit>`) would
make that a one-step lookup instead.

For a one-off manual install (skipping the updater, e.g. trying a change on
a single site before it's "released" to everyone):

```bash
cd .. && zip -r -X twd-article-publisher.zip twd-article-publisher -x "CLAUDE.md" "HISTORY.md" "*.git*"
```

Upload via **Plugins > Add New > Upload Plugin** on any client site. No
build step, no `npm install`, nothing to compile either way.
