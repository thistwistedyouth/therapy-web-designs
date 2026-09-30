# Full history and incident write-ups

CLAUDE.md keeps one-line rules ("what to do and never do"). This file keeps
the *why*, in full, for anything that was non-obvious enough to be worth
re-reading if a related bug ever resurfaces or a similar decision comes up
again. Read this when: a `.hidden`-toggled element is still showing, the
grid is silently dropping posts, a card's category/featured styling looks
wrong, or you're about to touch the swipe book's export or layout code.
Not needed for routine feature work — CLAUDE.md's rules cover that.

## Naming and branding

- **Plugin display name changed to "Articles & Resource Production Plugin" (from "TWD Article Publisher"), on request, so it sorts near the top of wp-admin's alphabetically-sorted Plugins list.** Only the `Plugin Name` header (and the matching `readme.txt` title and `TWD_AP_Updater::plugin_info()`'s `$info->name`) changed. The folder name, main file name, text domain, REST namespace (`twd-publisher/v1`), all `TWD_AP_*` class/constant names, and the self-hosted updater's `SLUG` constant deliberately stayed as `twd-article-publisher` throughout — changing any of those would risk breaking the updater or require a full uninstall/reinstall on every client site for a purely cosmetic rename.
- **The popup is branded "Resource Maker", not "New Article"/"Edit Article".** The heading text is fixed in the template; only a small subtitle (`#twd-ap-modal-subtitle`) switches between "New article" and "Editing article" via JS, so the product has one consistent name regardless of mode. The floating trigger buttons ("New Article"/"Edit This Article") keep their original labels since they describe the action, not the tool.
- **Popup colours are all CSS custom properties on `:root` in `publisher.css`, deliberately not client-branded.** Deliberately swapped from primary blue to a calm sage/cream/dusty-blue palette on request, since this runs on therapist sites, not a generic SaaS product. If a specific client ever needs their own brand colours in the popup, override the `--twd-ap-*` variables from that site's own theme CSS rather than forking this stylesheet.

## Bugs that shipped and how they were actually found

- **Featured-first ordering shipped broken in v1.8.0: a bare `'meta_key' => '_twd_ap_featured'` query var silently excluded nearly every post from the `[twd_articles]` grid.** It looks like it should just be a sort hint, but WP_Query turns a bare `meta_key` into an implicit `meta_query` clause that INNER JOINs postmeta — which excludes every post that doesn't have that meta row at all, and `_twd_ap_featured` only exists on posts someone explicitly ticked "Feature this article" on. Fixed in v1.9.x with an explicit `relation => 'OR'` between a named `EXISTS` clause and a `NOT EXISTS` clause, so every post matches one branch or the other, while still being orderable by the named clause. If this ever needs touching again: test with a post that has never been featured and confirm it still appears in the grid — that's the exact case the bug broke, and it's easy to miss in testing since a site's first few posts are often manually featured.
- **The Load More button "still showing with nothing left to load" bug took five separate version attempts (v1.19.0 through v1.21.1) to actually fix, because three different bugs were layered on top of each other and each fix only addressed one.** v1.19.0's `items.length >= count` check missed the exact-multiple-of-page-size edge case (fixed properly in v1.19.1 with an offset+peek `per_page + 1` fetch that returns an explicit `has_more`, instead of trusting `WP_Query`'s own unreliable page count). v1.20.0's 12-article grouping threshold still didn't fully fix it because it depended on an accurate `total_articles` REST fetch landing before the decision was made (v1.20.4 added a hard client-side `!state.manyArticles` gate independent of that). Even after both of those were genuinely correct, client sites kept reporting the button still showing — which led to a magenta-border deploy-check marker (v1.20.3, to rule out stale caching/CDN — it wasn't that) and eventually a temporary on-page DEBUG line (v1.20.5) printing the exact computed values. That line showed `loadmore_hidden=true` while the button was plainly still visible on screen — meaning the JS was correct the entire time, and something else was overriding the browser's default `[hidden] { display: none }` with its own `!important` on `<button>` (a theme or Elementor's own global button styling). v1.21.1 added an explicit `.twd-ap-articles-loadmore[hidden] { display: none !important; }` and that was the actual fix. Lesson: when a "hidden" element is still showing and the JS logic all checks out, check CSS specificity against the theme before re-auditing the JS again — see the `[hidden]` rule in CLAUDE.md, which exists because of this.
- **`&#8217;`/`&hellip;` (a curly apostrophe or trimmed-excerpt ellipsis) showed as literal escaped text on the grid, swipe book, and elsewhere — twice, because the first fix (v1.8.2-era, using `wp_specialchars_decode()`) never actually touched those two entities.** `wp_specialchars_decode()` only reverses `&amp;`/`&lt;`/`&gt;`/`&quot;`/`&#039;`. `get_the_title()` and excerpt trimming run `wptexturize()`, which turns a plain apostrophe or `...` into that entity text, meant for direct unescaped HTML output — but this plugin's JS re-escapes titles for safety before rendering, double-encoding the leading `&` and leaving the entity text itself showing. `html_entity_decode()` (the general decoder, handles every named/numeric entity) was the real fix, in v1.18.1, applied everywhere a title or excerpt is read for display (`format_article_card()`, the swipe book's `get_payload()`).
- **A post whose only category used to be Uncategorized could vanish from the grouped resources grid entirely after ticking an additional real category and saving, even though the new category looked ticked in the popup (v1.18.2).** The primary category (which section an article appears under, `_twd_ap_primary_category`) only got auto-replaced when it was completely unset, so it silently stayed pointed at Uncategorized, and the grouped view unconditionally drops that whole bucket. Re-saving the article (Update) fixes it retroactively, since primary-category resolution runs on every save.

## Design decisions with a specific backstory

- **The resources grid's accent colour setting (`accent_color`) used to also drive a "Featured" pill badge and a blue card-border ring (added v1.23.0), both removed again two versions later (v1.25.0).** A client asked for it to look "more therapy, not salesy" — a small badge shouting "FEATURED" over an article thumbnail read like a sales tag on a counselling site's resources page, not like a mental-health resource. The setting and its `--twd-ap-accent-color` CSS var stayed (still drives the search/dropdown focus border and category "n more" links), just with fewer things listening to it. Featured articles still sort first server-side; they just don't announce it visually any more.
- **A card's category eyebrow used to show "Featured Articles" as if it were the article's topic, on a site where that's a real WordPress category used purely for the grouped landing view's own section heading.** `format_article_card()` originally just took `get_the_category()`'s first result with no preference logic, so a hormones/menopause article assigned to both "Featured Articles" (as its primary, for grouping) and "Menopause" (the actual topic) could show "FEATURED ARTICLES · 29 September · 7 min read" above its own title — informationally useless and, again, reads as a sales tag. Fixed (v1.25.0) by preferring any category whose name isn't (case-insensitively) "Featured Articles", falling back to it only when it's the post's sole category. This is a hardcoded name match, not an ID/flag lookup, so it silently stops working if a client ever renames that category — unlike the "Uncategorized" exclusion elsewhere in this plugin, which is rename-proof because it looks up the site's actual `default_category` option instead of matching a string. Worth converting to an explicit meta flag (`_twd_ap_is_organisational_category` or similar) if this ever needs to generalise past one hardcoded name.
- **The swipe book was retitled "Summary Book" with a larger logo, its progress bar moved from the top of the frame to a small strip under the page-number counter, and slide cards were redesigned as centred "quote cards" with `clamp()`-based text sizing — all one request (v1.22.0), to make the export feel more like a shareable pull-quote card and less like a generic document viewer.** The progress bar move specifically was because the page-number counter ("5 / 8") already carries the primary "where am I" signal, and a second, more prominent bar sitting above the whole frame was visually redundant with it.
- **Save as image and Download as PDF both had to solve the same underlying problem: capturing what's on screen via html2canvas, when `.twd-sb-slides` clips any slide not currently at `translateX(0)`.** Save as image only ever needs the one active card, so it's a direct `html2canvas()` call. Download as PDF needs every slide, so `captureAllCards()` steps each slide to `is-active` in turn (under a `twd-sb-exporting` class that kills the slide transition, so each step is instant rather than visibly animating through the whole book), snapshots it, then restores whichever slide the reader was actually on before the export started. The first version of this button (v1.22.0) only captured whichever slide happened to be active when clicked — a real usability bug, not a design choice — fixed properly in v1.24.0 once a user reported "download as pdf is only doing one page."
- **Clicking Update on an already-published article now closes the popup automatically; a brand-new Publish or a Save as Draft still leaves it open.** Originally every save (including Update) left the popup open with a "you can keep editing or close this window" message, on the reasoning that someone might want to keep tweaking. A user explicitly asked for Update specifically to close, since there's nothing left to review on a second pass that wasn't already reviewed the first time something was published — the popup only stays open now for the two cases where there's a real reason to (a fresh publish's swipe book link just became available; a draft is, by definition, not finished).

## "View as a swipe book" button shipped unstyled on a live site despite `!important` on every property

The button (`assets/article-content.css`, `.twd-ap-swipebook-btn`) already
had `!important` on every visual property, matching the CLAUDE.md rule
about theme/Elementor styling winning otherwise — and it still rendered as
a bare blue underlined link on a live client site (v1.26.0 report,
screenshot showed it exactly like a default Elementor Text Editor link).
The lesson from this one: `!important` only wins a tie between two
`!important` declarations of *equal specificity* — it doesn't bypass
specificity comparison itself. A theme's own compound selector (something
like `.elementor-widget-text-editor a`, class+tag = higher specificity
than a single `.twd-ap-swipebook-btn` class) can still beat a single-class
`!important` rule with its own `!important` rule. Fixed by doubling the
class in the selector (`a.twd-ap-swipebook-btn.twd-ap-swipebook-btn`),
which raises this rule's own specificity without needing to know or match
whatever the theme's selector actually is. Worth remembering as a second
tool alongside the `[hidden]` rule: `!important` fixes losing on
load-order/declaration-count, doubling a class fixes losing on
specificity — they're different failure modes and need different fixes.

While fixing this, also added a per-site colour picker (`swipebook_color`
in `TWD_AP_Grid_Settings`) and softened the button's default look (faint
background tint matching the border colour, no box-shadow, a small lift
on hover instead). Since this button renders on every published article
page — not just ones carrying `[twd_articles]` — it can't read its colour
via the grid's own client-side REST fetch/CSS-var pattern the way
`read_more_color`/`accent_color` do; `TWD_AP_Swipebook::append_button()`
reads the setting directly in PHP and writes the colour, plus two
precomputed faint/hover `rgba()` backgrounds, as inline CSS custom
properties on the button itself (`color_custom_properties()`/
`hex_to_rgb()`). Precomputing the rgba values server-side avoids needing a
hex-to-rgba parser in JS just for this one button.

## Summary book header (site heading, logo, branded exports) — why it's split the way it is

Requested as one feature (v1.27.0): a site heading above the "Summary
Book" label, a bigger logo below that, both configurable and both
linking to the site, plus including that header in exported images/PDFs
with a toggle to turn it off. Implemented across several files because
each piece has a different constraint:

- **Why the colour/text/logo settings live in `TWD_AP_Grid_Settings`, read server-side, not fetched by `swipebook.js` the way `read_more_color`/`accent_color` are.** Those two apply via a CSS var set on the `[twd_articles]` grid's own root element — fine, since that only ever needs to be right on a page carrying the grid shortcode. The swipe book button (and now its header) renders on *every* published article, including ones with no grid on the page at all, so there's no client-side REST call it can piggyback on without adding one specifically for this. `TWD_AP_Swipebook::get_payload()` already runs server-side per request, so resolving the heading/label/logo/background there and baking them into the payload (which both the standalone page and the REST overlay route already share) was the natural place, not a new endpoint.
- **Why the logo background is three fixed styles (Light/Dark/None), not a colour picker.** The person asking for this confirmed fixed styles over per-colour pickers when asked directly — fewer settings to manage, and a logo already carries its own colours, so the pill just needs to complement dark or light art, not be tuned precisely.
- **Why the header isn't captured by simply widening the html2canvas target.** `.twd-sb-header` is one element shared by the whole book (title, label, logo — same for every slide), sitting as a sibling to `.twd-sb-slides`, not inside any individual `.twd-sb-card`. The existing capture functions target one card at a time (the whole reason `captureAllCards()` has to step through `is-active` states at all, see the PDF entry above). Rather than restructure the DOM so the header sits inside every slide (which would also mean recreating it 8+ times, in the actual visible modal, for something that only needs to appear once per export), `captureNode()` builds a throwaway offscreen clone — header once, card once, stacked in a wrapper (`.twd-sb-export-compose`) styled to look like a continuation of the card's own dark frame — captures that, and removes it immediately. The live modal is never touched, so there's no flicker.
- **Why the export-inclusion toggle (`swipebook_export_branding`) defaults to true (included).** Asked directly, on this project's stated preference: branding on by default, with an explicit way to turn it off in grid settings, not the reverse.
- **Why heading/logo links open in a new tab.** The swipe book is frequently opened as an in-page overlay on top of the article a visitor was already reading (`swipebook-inline.js`); a same-tab navigation away from that would lose the underlying page entirely for no good reason on what's meant to be a lightweight, glanceable header link.

## "More Articles" closing slide and book-to-book navigation (v1.28.0)

Two related decisions worth remembering if this needs extending:

- **Why the related-item click doesn't fetch-and-swap directly from `swipebook.js`.** The obvious approach would be a click handler inside `mount()` that fetches the next book's payload and calls `TWD_AP_SwipeBook.open()` itself. Instead, related items render as plain `<a href>` tags carrying `data-twd-ap-post-id`, exactly like the main "View summary book" button, and `swipebook-inline.js`'s existing document-level delegated click listener was widened to also match them. Reason: that script is the one place that already knows how to fetch a book's payload and open it in an overlay, and — critically — it's only ever loaded on pages where an overlay can meaningfully exist (`maybe_enqueue_inline()` skips it on the standalone `?twd_ap_swipebook=1` page). Duplicating that fetch/open logic inside `swipebook.js` itself would mean it also runs on the standalone page, where there's no overlay to swap into and `TWD_AP_SB.restUrl` isn't even defined. Reusing the existing delegation gets the right behaviour in both contexts for free: overlay-swap where a script for that exists, plain navigation (to the linked article's own standalone page) everywhere else.
- **Why `TWD_AP_SwipeBook.close()` had to be added.** `open()` no-ops if a book is already showing (`if (current) return;`), which is exactly what you want for the main button (never open two overlays) but wrong for a related-item click made *from inside* an already-open overlay — without an explicit close first, clicking a related article would silently do nothing. `close()` just calls the current instance's own `close()` (the same function the X button and backdrop-click already use), so cleanup (removing the backdrop, restoring scroll) is identical either way.
- **Why the picker in the editor popup reuses the public `/articles` REST route instead of a new one.** It already returns `{id, title, ...}` for every published post, sorted featured-first/newest-first, with no auth required — good enough for a "pick which articles to feature" list without a new endpoint to maintain. The picker only reads `id`/`title` off each item and ignores the rest.
- **Why leaving every checkbox unticked doesn't store an empty array.** `after_save()` only writes `_twd_ap_related_ids` when the submitted array is non-empty; an empty selection deletes the meta key instead. This keeps "nobody's ever touched this picker" and "somebody explicitly picked nothing" indistinguishable from the reader's perspective (both fall back to the latest-6 automatic list) and avoids a stored empty array meaning something different from no meta row at all.

## The overflowing-slide bug that looked like a scrolling bug (v1.29.0)

A live screenshot showed a slide's heading cut off at the top of the
card, with only the tail end of a long paragraph visible below it, no
way to scroll up to see the missing top portion. `.twd-sb-card` already
had `overflow-y: auto`, so the instinct was to look for something
blocking scroll -- but the actual cause was `justify-content: center` on
that same flex column. When a flex container's content is centred and
overflows, the browser centres it around the container's middle exactly
as asked, which means the portion that overflows *above* the visible
area needs a *negative* scroll position to reach -- impossible, so it's
permanently clipped, not just scrolled-past. Only the bottom overflow
(reachable by scrolling down, a positive scrollTop) ever looked
"scrollable"; that's why it read as a partial, confusing bug rather than
a total scroll failure. The fix, `justify-content: safe center`, is
built for exactly this case: centre when it fits, fall back to
start-aligned (top, always scrollable) the moment it doesn't. Lowering
`build_slides()`'s `$max_chars` from 420 to 320 doesn't fix the
underlying mechanism -- it just makes an overflowing slide rarer in
practice, so `safe center` is now a safety net rather than something a
typical article-length section leans on.

## Header moved inside the card's border (v1.29.0)

Requested as: bring the logo/site heading/"Summary Book" label inside
the card's visible border, logo top-left, heading and label stacked to
its right (previously they floated centred above the card, outside its
border entirely). Implemented by giving `.twd-sb-header` and
`.twd-sb-card` matching border colour, matching `max-width`, and
complementary border-radius (header rounded only on top, card only on
the bottom) with zero gap between them, so two separate DOM elements
read as one continuous card shape. The header stays a single shared
element outside `.twd-sb-slides` -- it was already documented as
deliberately not duplicated per slide (one book, one header, see the
"Save as image / Download as PDF" entries above), and that reasoning
didn't change just because it now visually sits inside the border
rather than above it. Because `.twd-sb-export-compose` (the offscreen
export wrapper) clones the same `.twd-sb-header` and `.twd-sb-card`
elements, this layout change reached Save as image/PDF automatically --
the export wrapper's own background/border/gap, which used to visually
join the two pieces itself, was simplified away to nothing once the
header and card could do that joining themselves.

## Zip-build gotcha (self-hosted updater)

Building the release zip with `rsync` in the same shell invocation as the
`zip` command, under `set -e`, can silently produce a **stale** zip if
`rsync` isn't installed in that environment: the script stops at the
failed `rsync` line, but earlier output can make it look like it kept
going, and the previously-built zip already sitting at the destination
path is left untouched — so the "build" step appears to succeed (right
file listing inside the zip, plausible file sizes, recent-looking
timestamps) while actually shipping last version's code under this
version's number. This happened once, mid-session, and was only caught by
`diff`-ing a just-edited source file against the same file pulled back out
of the built zip. `cp -r` doesn't have this dependency and is what
`CLAUDE.md`'s release steps use now; the verification diff step was added
to the release checklist specifically because of this incident, not as
generic caution — skipping it is how this shipped wrong the first time.
