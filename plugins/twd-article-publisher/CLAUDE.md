# Articles & Resource Production Plugin (formerly "TWD Article Publisher")

A WordPress plugin, generic and reusable across every Therapy Web Designs client site. It lets an approved logged-in user (a therapist, not a developer) publish and edit blog posts from a popup on the live front end, without going into wp-admin. It was built to solve one recurring problem: therapists writing blog content with an AI assistant and needing a fast way to get formatted HTML onto their site as a properly tagged, SEO-ready post.

Not tied to any one client's design. Header, footer, page CSS, per-site palettes and the "single HTML widget per page" Elementor build method (documented separately in Therapy Web Designs' own build notes) are unrelated to this plugin and live in each client site's own theme/Elementor content, not here.

**At the start of every session read this file and BUILD-LOG.md. At the end of every piece of work, without being asked, update BUILD-LOG.md in the same commit. Complex changes are discussed first. Other repos are read only; changes there go as written briefs.**

This file is the short version: rules, not stories. **HISTORY.md** has the full incident write-ups (what broke, what was tried, why the fix looks the way it does) for anything below that has one — only worth opening if you're debugging something in that specific area.

## What it does

- Adds a floating **New Article** button on every front-end page, visible only to logged-in users in an allowed role.
- Adds a small round **edit pencil**, fixed top left (below the WordPress admin bar when it is showing), on any single post the current user has permission to edit. It replaced the big "Edit This Article" button in v1.37.0; the element id `twd-ap-edit-btn` is unchanged so `publisher.js` binds it as before.
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
- `read_more_color`, `accent_color`, and `swipebook_color` (`TWD_AP_Grid_Settings`) are the per-site colour knobs — don't sniff theme colours at runtime. `accent_color` drives the search/dropdown focus border and category "n more" links only (it used to also drive a Featured badge/border, removed — see HISTORY.md). `read_more_color`/`accent_color` apply via CSS vars set client-side on the grid's own root element (`--twd-ap-readmore-color`/`--twd-ap-accent-color`, `articles-grid.js`); `swipebook_color` can't use that pattern since its button renders on every article page, not just ones with the grid — it's read server-side and written as inline CSS custom properties by `TWD_AP_Swipebook::append_button()` instead.
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
- "Draft with Article Assist" is a plain new-tab link, never an iframe (cross-origin cookie/frame-blocking issues on Safari/Chrome — see HISTORY.md). It only shows at all when this site has no Anthropic key of its own configured (`class-twd-ap-ai-generate.php`) — see "On-site AI generation" below.

**Sanitisation & REST**
- `TWD_AP_Sanitizer::clean()` is the only path HTML takes before `wp_insert_post`/`wp_update_post` — route any new HTML-accepting field/endpoint through it, don't add a parallel sanitiser. No inline styles allowed through it, by design.
- `strip_dashes()` runs on every text field (title, excerpt, tags, Yoast, category names), not just the body — a new text field needs this explicitly, `sanitize_text_field()` doesn't touch dashes.
- YouTube embeds are click-to-play placeholders (`<figure data-youtube-id>`), never raw iframes through `wp_kses` — don't "simplify" this, it reopens the sanitiser's one deliberate hole.
- Custom REST routes (`twd-publisher/v1`), not `wp/v2/posts`, so every write funnels through the sanitiser.
- `/articles` and `/articles/facets` are the only genuinely public REST routes (`__return_true`) — both read-only, never touching `wp_insert_post`/`wp_update_post`.

**Misc**
- `.hidden`-toggled elements always need their own `[hidden] { display: none !important; }` rule, unconditionally — a page builder's own `!important` button/element styling can beat the browser's non-`!important` `[hidden]` default. Add this the moment a new element gets `.hidden` toggled onto it, not reactively after a client reports it's still showing. Full incident in HISTORY.md.
- `!important` alone isn't always enough against a theme/Elementor selector — it only wins ties at equal specificity. A compound theme selector can still beat a single-class `!important` rule with its own. If an `!important`-guarded element still renders unstyled on a live site, raise the selector's own specificity too (a doubled class, e.g. `a.twd-ap-swipebook-btn.twd-ap-swipebook-btn`, is the pattern used so far) rather than assuming the guard failed. See HISTORY.md for the swipe book button incident this came from.
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
- Titled "Swipe Book" with a larger logo; progress bar sits under the page-number counter, compact, not across the top; slide cards are centred "quote cards" with `clamp()`-based text sizing.
- Header order (top to bottom): site heading (`swipebook_heading` setting, blank = site name), the "Swipe Book" label (`swipebook_label` setting), then the logo (`swipebook_logo_id` overrides the Customizer logo for swipe books only; `swipebook_logo_bg` is a fixed Light/Dark/None pill style, not a colour picker). Both heading and logo link out to `home_url('/')` in a new tab. All resolved server-side in `get_payload()`, not fetched separately by JS.
- The header (logo, site heading, "Swipe Book" label) is `.twd-sb-card-header`, the first child *inside* every `.twd-sb-card`, not a separate element outside `.twd-sb-slides` — it was tried as a single shared fixed element above the cards first (v1.29.0) and changed to per-card (v1.30.0) on request, so it moves with the card as one unit on every swipe and the two never show a seam between separately-computed backgrounds. It's built once as an HTML string in `buildMarkup()` and prepended into every slide, so it's still driven from one place even though it appears once per slide in the DOM — identical images/text share a browser cache hit, not a real per-slide cost. `.twd-sb-card-body` (the second child) is the part that actually scrolls; the header itself is `flex-shrink: 0`, non-scrolling, marked off from the body with a hairline `border-bottom`.
- Save as image / Download as PDF capture the DOM via html2canvas, never a separately-built layout — and since the header lives inside the card now, capturing the card captures the header too, no composition step needed. When `swipebook_export_branding` is off, `captureNode()` briefly hides that one `.twd-sb-card-header` child (`display: none`, restored right after), the same "briefly touch the live DOM for a capture" approach `captureAllCards()` already uses to step through slides. PDF still has to step every slide to `is-active` in turn (`captureAllCards()`, under `.twd-sb-exporting` to kill the transition) since `.twd-sb-slides` clips anything not at `translateX(0)` — see HISTORY.md, this shipped single-page-only once before being fixed. Both html2canvas and jsPDF are lazy-loaded from cdnjs on first use.
- User-facing text says "swipe book" (button: "View as a swipe book", popup labels, help text) — **not** "Summary Book". It briefly was ("View summary book" etc., v1.28.0–v1.30.0) until "Summary Book" got reserved for the separate curated feature below and this reverted (v1.31.0) to avoid two different things sharing one name. File names, class names (`TWD_AP_Swipebook`), CSS classes (`twd-sb-*`), the `?twd_ap_swipebook=1` query var, and the `/articles/{id}/swipebook` REST route are all unchanged throughout this, on purpose, since renaming any of those would break existing shared links and the self-hosted updater.
- Every book ends with a "More Articles" slide (`build_related_slide()`): up to 6 manually-picked articles (`_twd_ap_related_ids` post meta, a checklist in the editor popup capped at 6), or the 6 most recent other published articles if none are picked. No thumbnails, plain title list. Clicking an item is a plain `<a>` to that article's own swipe book URL — `swipebook-inline.js`'s existing delegated click listener (the same one the main button uses) intercepts it, closes the current overlay (`TWD_AP_SwipeBook.close()`) and opens the new one, when that script is loaded; on the standalone page (where it never loads) it's a normal navigation instead. Returns no slide at all, not an empty one, if the site has no other published articles.
- `.twd-sb-card-body` uses `justify-content: safe center`, not plain `center` — plain `center` on an overflowing flex column clips whatever overflows *above* the centre point, unreachable by scrolling since scrollTop can't go negative. `safe center` falls back to top-aligned the instant content overflows, so an unusually long slide is always fully scrollable. A browser that doesn't recognise `safe` drops the whole declaration, landing on the same safe default. Don't revert this to plain `center` even if it looks identical for short content — the failure only shows up once something overflows.
- The slide-splitting threshold (`$max_chars` in `build_slides()`) is 320, not a round number chosen for its own sake — see HISTORY.md for the overflow bug that came from it being too generous.

**Summary Book** (`class-twd-ap-summary-book.php`) — deliberately not the swipe book above, and not built on it
- A curated, reviewed-before-publish deck of typed cards (`text`/`quote`/`question`), the opposite of the swipe book's live auto-split: nothing shows publicly until a therapist explicitly publishes it, and once published it does *not* track later article edits.
- Two separate post meta rows, `_twd_ap_summary_book_draft` and `_twd_ap_summary_book_published` — not one row with a status flag. Publishing copies draft → published; it never flips a flag on the same data, so a half-edited draft can never accidentally go live, and an in-progress edit never silently overwrites what's already public. `TWD_AP_Summary_Book::publish()` is the *only* path that writes to the published row.
- A draft arrives as `summary_book.slides` in a pasted JSON block (meant to come from Article Assist, baked in alongside the article) and rides into `after_save()` via the main article save's own `summary_book_draft` request field — see `fillFromJson()`/`submitPost()` in `assets/publisher.js`. Saving the article **never** publishes or touches an already-published Summary Book; an absent `summary_book_draft` key means "this save didn't carry one," not "clear it" — re-saving the article for an unrelated edit must never wipe a stored draft.
- The "Summary Book" button in the editor popup only shows for an already-published article that has a draft and/or a published deck (`updateSummaryBookButton()`) — nothing to review or generate otherwise.
- The review screen (`#twd-ap-summary-book-overlay`) renders cards as plain editable fields (type select, heading, text, attribution), not a live swipe-book preview — simpler and more robust than making arbitrary nested card HTML directly `contenteditable`, at the cost of not being pixel-identical to the eventual published rendering.
- `TWD_AP_Summary_Book::sanitize_cards()` is the only path card data takes before being stored, same principle as `TWD_AP_Sanitizer::clean()` for article HTML — treats Article Assist's JSON as untrusted input like anything else pasted into this popup. Caps at 20 cards, drops anything not shaped like a card, drops a card with no text.
- As of v1.32.0 a published Summary Book is publicly viewable: standalone page at `?twd_ap_summary_book=1` and an in-page overlay fed by the public `/articles/{id}/summary-book` REST route, both built by reusing `TWD_AP_Swipebook::get_header_fields()` and `TWD_AP_Swipebook::render_standalone_page()` rather than duplicating them — one shared header/shell/JS engine (`assets/swipebook.js`) across both books, not two. A "View Summary Book" button (`append_button()`, priority 16, right after the swipe book's own at 15) shows on the article once published. Each book links to the other via `companionUrl`/`companionLabel`/`companionPostId`/`companionBookVariant` in its own `get_payload()`, rendered by `buildMarkup()` as a `.twd-sb-companion-link` in the footer and opened by `swipebook-inline.js`'s click delegation the same close-current/open-other way a "More Articles" item is — `data-twd-ap-book-variant` picks which REST endpoint it fetches (`swipebook` or `summary-book`). Quote/question card types render as their own slide styles in `buildMarkup()`/`swipebook.css` (gold quote with attribution, amber question), styled after Therapy Resource Directory's book reader. Article Assist still doesn't produce the `summary_book` field yet — that remains follow-up work.

**On-site AI generation** (`class-twd-ap-ai-generate.php`) — a per-site opt-in, not a replacement for Article Assist
- A site can optionally carry its own Anthropic API key (Settings > Article Publisher, `anthropic_api_key` in `TWD_AP_Settings`). With no key, the popup behaves exactly as it always has: "Draft with Article Assist" external link, copy/paste JSON tab. With a key (v1.36.0), opening a NEW article shows a single **start screen** instead of the editor: one box ("Write, dictate or paste your article ideas or a full article") and three radios, **Article idea** (`idea`), **Draft article, improve** (`improve`), **Finished article, only format** (`format`), then Generate. The generator no longer lives in the JSON tab (that tab is plain "Paste JSON" again, still the way to bring in Article Assist output). Editing an existing article never shows the start screen.
- Start screen mechanics: `#twd-ap-start` is swapped with the editor by one class, `.twd-ap-start-active` on `#twd-ap-overlay` (CSS hides every other child of the modal body and the footer), so none of the editor's own `hidden` states are touched. "Write it myself instead" carries any typed text into the editor as paragraphs. A failed generation leaves the typed text where it is. After `improve` or `format` a bar offers "Use my original text instead" / "Use the improved version", swapping only the body (title, summary, tags stay) and saving the editor's current content into the slot being left, so edits to either version survive going back and forth (`state.swap`, `toggleOriginal()` in `publisher.js`). No dictation button on purpose: the box works with the device's own dictation, and browser speech recognition sends audio to the browser maker, which is a confidentiality problem for therapists.
- Modes and sizes: `idea` replies are capped at 5000 tokens (`MAX_TOKENS`), `improve` and `format` at 8000 (`LONG_MODE_TOKENS`) because they return a whole article plus SEO fields and a summary book. Input over `MAX_INPUT_CHARS` (20,000) is **refused with a clear message, never silently cut** (cutting the end off a finished article would format only the first part and look like it worked); the start screen shows a live count and disables Generate over the limit. `improve` has **no counterpart in Article Assist** (it only writes or formats), so its prompt in `system_prompt_improve()` is the one prompt here not kept in step with that tool. Unverified: very long replies at 8000 tokens could approach the 120 second request timeout on a slow day.
- The 30 a day cap is shared by all three modes and counts only successful generations. The page gets `aiRemaining`, `aiLimit` and `aiMaxChars` via `wp_localize_script`, and `POST /generate` returns `ai_remaining` so the "X of 30 left today" line stays right without reloading. The `twd_ai_complete` filter does not count against it.
- `TWD_AP_AI_Generate::generate()` deliberately mirrors Article Assist's own prompt and JSON shape almost line for line (`trd_aa_shared_rules()` and friends in therapy-resource-directory's `15 TRD Article Assist.php`) — same category-matching instruction, same HTML tag allowlist, same `summary_book` card rules. This is **duplicated prompt text across two repos on purpose**, not shared code: there's no package boundary between this plugin and the Resource Directory site to share it through, and the two are meant to be interchangeable from the popup's point of view. If the summary_book card rules or the article-writing rules ever change in one, check whether the other needs the same change — nothing enforces that automatically.
- The REST route (`POST /generate`) and the paste-JSON tab both end up at the exact same client-side function, `applyGeneratedData()` (`assets/publisher.js`, factored out of what used to be `fillFromJson()`'s own body) — neither path has its own copy of the "fill the title/excerpt/tags/html/category/summary_book fields" logic.
- Model is fixed (`claude-sonnet-5-5`, effort `medium`), not a setting — predictable behaviour and cost per site rather than a per-site knob. A 30/day transient-based cap (`rate_key()`, keyed by user + day, same shape as TRD's own `trd_aa_rate_key()`) exists purely as a backstop against a bug or abuse of the endpoint, not a real constraint — it's the client's own key and their own bill, unlike Article Assist's 15/day limit which exists because TRD pays that bill.
- The key field in Settings never echoes the saved value back into the input (a masked `sk-ant-••••`-style display isn't worth the extra code for a field only the site owner sees) — it's always rendered blank, so `TWD_AP_Settings::sanitize()` treats a blank submit as "leave the existing key alone," and only an explicit "Remove the saved key" checkbox actually clears it. Don't change this to "blank clears it" without also changing the field to actually show the current value first — otherwise the form's own blank-until-touched default would make every resave silently wipe the key.
- `generate_article()` in `class-twd-ap-rest.php` is gated by `check_permission()` (same as category creation), not `check_edit_permission()` — there's no post to own yet at generation time, same reasoning as every other pre-creation endpoint.

## Filters offered to other plugins

Two server-side WordPress filters, registered by `TWD_AP_AI_Generate::init()` (called from the plugin constructor). Used by **TWD Site Kit** (`thistwistedyouth/twd-site-kit`, 0.5.0 onwards) for its "Ask the AI" feature, so Site Kit can use this site's saved Anthropic key without ever seeing or storing it. No REST route, no JavaScript. The key is read inside `TWD_AP_AI_Generate` and is never passed to, returned from, logged or sent to the browser by either filter.

- `apply_filters( 'twd_ai_is_configured', false )` returns `true` only when `anthropic_api_key` in `TWD_AP_Settings` is non-empty, otherwise the value it was given.
- `apply_filters( 'twd_ai_complete', null, $system, $message, $max_tokens )` returns the reply text (string), a `WP_Error` (same codes and plain-English messages as `generate()`), or `null` when no key is set (nothing answered). It calls `TWD_AP_AI_Generate::complete()`, which reuses `call_anthropic()`, so endpoint, model, headers, 120 second timeout and refusal/max_tokens handling are identical to `generate()`.
- `$max_tokens` is clamped by `clamp_max_tokens()`: below 256 becomes 256, above 8000 becomes 8000, non-numeric becomes 5000.
- These calls do not count against the 30 per day article limit (that count lives in the REST layer, `class-twd-ap-rest.php`). Site Kit has its own rate limit (10 requests per 10 minutes per user) and its own permission checks.
- If another handler has already answered (non-null first argument), `twd_ai_complete` returns that untouched rather than making a second call.
- Do not change the argument order, the null-means-nothing-answered contract or the clamp range without a written brief to Site Kit, because it depends on all three.
- Not touched by this: `generate()`, the article popup, the prompts and the JSON handling.

## Popup behaviour added in v1.37.0

- **Page behind refreshes after Publish or Update.** `submitPost()` sets `state.reloadOnClose = ('publish' === status)`; `closeModal()` reloads the page when it is set. A brand-new Publish keeps the popup open (as before) and refreshes when it is closed; Update closes itself after 600ms and refreshes. The LAST save decides, so publish then Save Draft does not refresh, and a draft or scheduled save never does: those make the article non-public and a reload could land on a "not found" page. A failed save never sets it.
- **Per-article swipe book switch** (`_twd_ap_swipebook_off`, `TWD_AP_Swipebook::is_enabled()`). The tick "Offer a swipe book for this article" is in the Details section for new and existing articles (all sites, key or not). Off is stored as meta `1`; on DELETES the meta, so every article that existed before the switch (no meta) stays on. It hides BOTH books: the swipe book button and the Summary Book button, the standalone pages (`?twd_ap_swipebook=1`, `?twd_ap_summary_book=1` fall through to the normal article page), the public feeds (`/articles/{id}/swipebook` and `/articles/{id}/summary-book` return 404), and the article's slot in other articles' "More Articles" slide (automatic list uses a `NOT EXISTS` meta query, hand-picked list skips it). A save that does not carry `swipebook_enabled` leaves the switch alone. The header "Swipe book link" in the popup follows the tick live.
- **The Summary Book is still review-first.** Nothing here publishes it automatically at creation: an AI-generated draft is only ever published from the Summary Book review screen after saving. Do not add an "also publish the summary book" tick without discussing that rule.
- **"New Article" only on shortcode pages is now the default** for sites that have never saved the setting, and for fresh installs (`activate()` stores `'shortcode_page'`; it used to store `1`, which maps to `everywhere`). Sites that already saved a value keep it: existing sites were deliberately NOT switched over automatically, one tick in Settings does it.

## Elementor converter (v1.35.0)

`includes/class-twd-ap-converter.php`, admin only, **off unless the "Elementor converter" tick-box in Settings > Article Publisher is ticked** (`converter_enabled`). Adds Tools > Convert Elementor posts. Built for sites whose old posts live in an Elementor HTML widget, which the popup cannot edit because it only reads and saves `post_content`, never `_elementor_data`.

- **One post at a time.** The list page shows every post with Elementor data; "Check and preview" analyses one, shows the result, and "Convert this post" does it. No bulk button, deliberately.
- **Same post, converted in place**, so URL, title, date, categories, tags, featured image, excerpt and SEO meta (Yoast, Rank Math) are never touched. Only `post_content` and the `_elementor_*` meta change (plus `_wp_page_template` if it was an `elementor_*` template such as Canvas, reset to `default`).
- **Safe / Warning / Blocked.** Blocked: any widget other than `html`, `text-editor` or `spacer`, no content found, unreadable data, or already converted. Warning: custom CSS, scripts or styles dropped, odd tags (table, iframe and so on) dropped, inline styles dropped, em or en dashes changed to commas, a rule that could not apply, an Elementor page template, or cleaned text noticeably shorter than the source. Anything else is Safe.
- **Tidy step before the cleaner.** `TWD_AP_Sanitizer::clean()` drops every `div` and `class`, which on its own would turn paragraph-styled sub-headings into plain paragraphs. So `tidy()` first applies the per-site rules, unwraps wrapper divs, drops comments, scripts and styles, maps h1/h5/h6 to levels the cleaner keeps, and (optional tick in the preview) promotes h3 to h2, and h4 to h3, when the post has no h2, because the swipe book splits on h2.
- **Per-site rules** live in the same Settings row (`converter_rules`, one per line: `part-of-old-class = h2|h3|h4|p|blockquote|strong|em|unwrap|remove`, `#` for notes, longest class part wins). The Kingfisher `kcc-*` rules are the pre-filled default. Old class names that were dropped are listed in the preview so a missing rule is obvious.
- **Backup and Undo.** Before anything changes, the original `post_content`, every `_elementor_*` meta value and the page template are saved in post meta `_twd_ap_conv_backup`. If the backup cannot be saved, nothing else happens; if the content save fails, the backup is removed again and the post is untouched. "Undo conversion" restores all of it; if the article was edited since converting, undo needs an extra tick to discard those edits. "Keep it converted, delete the backup" removes the backup.
- **Server-side checks on every action**: setting on, `manage_options`, `edit_post`, and a per-post nonce. The preview is never trusted; `convert()` re-runs the analysis.
- Converted posts take the plugin's own article typography, so the old site CSS (`kcc-*` and so on) stops applying. Per-site "article look" controls are planned for v1.37.0, not built yet.
- Not done: no guard in the popup yet for Elementor-built posts that have not been converted (the popup would show them empty or stale). No bulk convert.

## File map

```
twd-article-publisher.php                Plugin bootstrap, loads all classes below
includes/class-twd-ap-settings.php       Settings > Article Publisher screen; allowed roles, default category, optional Anthropic API key
includes/class-twd-ap-ai-generate.php    TWD_AP_AI_Generate: on-site article generation using this site's own API key, mirrors Article Assist's prompt/JSON shape
includes/class-twd-ap-grid-settings.php  TWD_AP_Grid_Settings option: category order/count, posts per category, thumbnails toggle
includes/class-twd-ap-sanitizer.php      TWD_AP_Sanitizer::clean() — the one place HTML gets cleaned
includes/class-twd-ap-rest.php           REST routes: /categories, /tags, /posts, /posts/{id}, /media, /articles, /articles/facets
includes/class-twd-ap-frontend.php       Enqueues editor assets, decides which buttons show, capability gate
includes/class-twd-ap-shortcode.php      [twd_articles] shortcode -- grid/group container, dropdown, admin "Customise this grid" popup markup
includes/class-twd-ap-article-style.php  Public article typography — the_content wrap + unconditional CSS enqueue
includes/class-twd-ap-swipebook.php      ?twd_ap_swipebook=1 -- renders any published article as a shareable swipe deck; get_payload() also feeds the overlay REST route
includes/class-twd-ap-summary-book.php   TWD_AP_Summary_Book: storage/sanitising for the curated Summary Book (draft + published post meta) plus its own ?twd_ap_summary_book=1 standalone page and get_payload() for the overlay REST route
includes/class-twd-ap-starter-content.php  Publishes a one-time "Getting Started" article on activation
includes/class-twd-ap-converter.php      TWD_AP_Converter: admin-only Elementor to normal post converter (tick-box in Settings), one post at a time, backup and Undo
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

Converter tests live OUTSIDE the plugin folder, in `tests/twd-article-publisher/converter-test.php` at the repo root (so they never ship to client sites). They use real WordPress core code for `wp_kses` and only fake the database:

```bash
composer require johnpbloch/wordpress-core --working-dir=/tmp/wpc
TWD_WP_CORE=/tmp/wpc/vendor/johnpbloch/wordpress-core php tests/twd-article-publisher/converter-test.php
```

Run it after any change to `class-twd-ap-converter.php` or `class-twd-ap-sanitizer.php`. It uses short made-up articles, never client text.

Three more, also outside the plugin folder:

```bash
php tests/twd-article-publisher/ai-test.php                                          # modes, reply sizes, input limit, daily count, twd_ai_* filters (no WordPress needed)
NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/browser-test.js   # the popup (start screen, pencil, refresh after save, swipe book tick) in real Chromium via Playwright
php tests/twd-article-publisher/swipe-switch-test.php                               # the swipe book switch, where it is enforced, and the New Article default
```

The browser test renders the real `templates/buttons-and-modal.php` with `render.php`, loads the real `publisher.css` and `publisher.js`, and fakes only the server replies. Run both after touching the popup, `publisher.js`, `publisher.css` or `class-twd-ap-ai-generate.php`.

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
rm -f /tmp/twd-build/twd-article-publisher/CLAUDE.md /tmp/twd-build/twd-article-publisher/HISTORY.md /tmp/twd-build/twd-article-publisher/BUILD-LOG.md
find /tmp/twd-build/twd-article-publisher -name '.git*' -exec rm -rf {} + 2>/dev/null || true
rm -f dist/twd-article-publisher-latest.zip
( cd /tmp/twd-build && zip -r -q "$OLDPWD/dist/twd-article-publisher-latest.zip" twd-article-publisher )

# 2b. VERIFY the zip actually contains this build, not a stale one, before
#     going any further -- diff at least one file you just changed:
rm -rf /tmp/twd-verify && mkdir /tmp/twd-verify
( cd /tmp/twd-verify && unzip -q "$OLDPWD/dist/twd-article-publisher-latest.zip" )
diff -q /tmp/twd-verify/twd-article-publisher/<a file you edited> \
        plugins/twd-article-publisher/<same file>   # must print nothing
ls /tmp/twd-verify/twd-article-publisher | grep -i -E 'claude|build-log|history'   # must print nothing

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
