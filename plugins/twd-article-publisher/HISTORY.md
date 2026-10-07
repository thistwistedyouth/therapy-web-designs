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

## Header moved again: from one fixed shared element to inside every card (v1.30.0)

v1.29.0 moved the header inside the card's *visible border* but kept it as
a single shared DOM element sitting outside `.twd-sb-slides`, styled to
look flush with whichever card was showing (matching border colour,
complementary border-radius, zero gap). That produced two visible
problems in practice, both from the same root cause: header and card were
still two separate elements with two separately-computed backgrounds.
- **The gradient seam.** Both `.twd-sb-header` and `.twd-sb-card` had their own `radial-gradient(ellipse at 50% 0%, ...)`, each computed relative to its own box — so the ellipse's centre point landed in a different place in each, and the join between them showed as a visible line/discontinuity rather than one continuous background, exactly as reported from a live screenshot.
- **The header didn't "swipe with the page."** Because it lived outside `.twd-sb-slides` and never had a `transform` applied to it, it stayed visually still while cards translated past underneath — deliberate at the time (documented as "a fixed title bar"), but the person paying for this wanted the opposite: logo, border and titles as part of the same physical "page" that moves as one unit on every swipe, closer to a real page in a book than a chrome/toolbar sitting above the content.

Fixed by moving `.twd-sb-header` (renamed `.twd-sb-card-header`) to be the
first child *inside* every `.twd-sb-card`, built once as an HTML string in
`buildMarkup()` and prepended into each slide's markup rather than
appearing once outside the loop. This solves both complaints at once:
one element means one background computed once (no seam possible), and
because the header is now inside the card, it inherits the card's own
`transform: translateX(...)` during a swipe, so it moves with it.
`.twd-sb-card` itself split into `.twd-sb-card-header` (non-scrolling,
`flex-shrink: 0`, a hairline `border-bottom` marking it off) and
`.twd-sb-card-body` (the part that actually scrolls, carrying the
`safe center` fix from the overflow-bug entry below).

The `swipebook_export_branding` toggle got simpler as a side effect:
previously "branding on" meant composing an offscreen clone of header +
card into a throwaway wrapper (since a card alone, captured directly,
would have no header to include). Now the header is already part of the
card, so "branding on" is just `html2canvas(card, ...)` with nothing
extra; "branding off" is the one case that now needs a small live-DOM
change (`.twd-sb-card-header` briefly `display: none`, restored right
after the capture resolves) — the same pattern `captureAllCards()`
already used to step through slides, not a new technique.

Repeating the logo/heading markup once per slide (rather than once for
the whole book) sounds like it should be more expensive than the old
single shared element, but in practice it isn't: identical `<img>` `src`
values across many DOM nodes are one browser cache entry, not N network
requests, and the extra text nodes are trivial. Don't let that shape
worry you into re-introducing a single shared element for "efficiency" —
that's what caused both problems this entry describes.

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

## "Summary Book" name bounced between two features before settling (v1.28.0 → v1.31.0)

Worth knowing if you ever find "Summary Book" wording in an old commit,
screenshot, or a client's own notes that doesn't match what the code does
today: the name has meant two different things at different points.

v1.28.0 renamed the *swipe book's* own user-facing text from "swipe book"
to "Summary Book" (button: "View summary book," the in-book label, popup
copy) — purely cosmetic at the time, no behaviour changed. That stuck
through v1.29.0 and v1.30.0, both of which fixed real bugs in that same
renamed feature (the overflow-clipping bug, the header-seam/swipe bug).

Then a new, genuinely different feature was designed: a curated,
reviewed-before-publish deck of typed cards, fed by Article Assist's own
JSON, that a therapist explicitly approves before anything goes live —
the opposite of the swipe book's always-live auto-split. Calling it
anything close to "Summary Book" while the *swipe book* was already using
that exact name was caught before it shipped (two different things with
the same label, on the same article, is a real bug waiting to happen, not
a style question) — so v1.31.0 reverted the swipe book's wording back to
"swipe book" everywhere, and "Summary Book" is now reserved exclusively
for the new curated feature.

Net effect: anywhere in this codebase or its docs that still says
"Summary Book" refers to `class-twd-ap-summary-book.php` and nothing
else. The live auto-split book is the swipe book, full stop, in every
version from here on. Internal identifiers (file names, `TWD_AP_Swipebook`,
`twd-sb-*` CSS classes, the `?twd_ap_swipebook=1` query var, the
`/articles/{id}/swipebook` REST route) were never touched by any of this
naming back-and-forth, on the standing rule that renaming those risks
breaking live shared links and the self-hosted updater — only the words a
person actually reads moved.

## Summary Book v1: storage and an editor, deliberately nothing public yet (v1.31.0)

Built as the first slice of a larger planned feature (see the design
conversation this came out of, not reproduced here) that also includes:
Article Assist generating the `summary_book.slides` draft in its own JSON
output, a public "View summary book" button and page, a compact link
from inside the swipe book to the Summary Book and back (reusing the
same close-current/open-new mechanism the "More Articles" slide already
uses), and Share/Save-as-image/Download-as-PDF parity with the swipe
book. None of that is built yet. This version deliberately stops at
storage (`TWD_AP_Summary_Book`, two post meta rows) and the therapist-facing
editor (the popup's "Summary Book" button and review screen) — on purpose,
not as an oversight: shipping a "View summary book" button with nothing
sensible to render would be worse than not shipping it, and the storage
layer needed to exist and be stable before anything gets built on top of
it.

Why draft and published are two separate meta rows rather than one row
with a status flag: publishing has to be impossible to do by accident.
A single row with a status flag means some code path, someday, could
flip `draft` to `published` without actually copying reviewed content
into it — a bug that would silently publish stale or half-edited text.
Two rows means "publish" can only mean one thing: `TWD_AP_Summary_Book::publish()`
copies the given cards into both rows at once, so the published copy is
always exactly what was just reviewed, never something left over from a
previous edit that never got explicitly approved.

Why the review screen edits plain fields (a type dropdown, heading,
text, attribution) instead of rendering real swipe-book-style cards
with `contenteditable` text: the fidelity would be nicer, but making
arbitrary nested HTML (headings, quote blocks with attribution rows,
question styling) reliably editable in place is a meaningfully harder
and more fragile thing to build than a form, and the therapist using
this is reviewing for accuracy and tone, not fine typography — a plain
field list serves that need without the risk of a `contenteditable`
edge case corrupting a card's markup. Worth revisiting once the public
rendering exists and there's a real published card to preview against.

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

## Summary Book v2: public rendering, built by reusing the swipe book's own shell (v1.32.0)

The second slice of the Summary Book plan (see the v1.31.0 entry above for
the first). This version adds everything the previous one deliberately
left out: the standalone page, the in-page overlay, the "View Summary
Book" button, and cross-links between the two books.

The guiding decision was to reuse `TWD_AP_Swipebook`'s own rendering
machinery rather than duplicate it. `get_header_fields()` was pulled out
of `TWD_AP_Swipebook::get_payload()` into its own public static method so
both books build an identical header from one place, and `render()`
(private, took no arguments) became `render_standalone_page( $post,
$payload )` (public static, payload passed in) so `TWD_AP_Summary_Book`
could call it directly instead of re-implementing the same HTML shell,
OG tags and inline `TWD_AP_SB_DATA` script a second time. `assets/
swipebook.js`'s `buildMarkup()` is the one JS template both books render
through; a Summary Book's payload is just a different `slides` array
(built from published cards, not a live split of the article) shaped to
match what that template already expects, plus two new slide types it
now understands (`quote`, `question`) and a `companionUrl`/
`companionLabel` pair it renders as a footer link when present. Nothing
about the swipe book's own live-split behavior changed.

The cross-link between books reuses the exact mechanism the "More
Articles" slide already used for jumping between articles' swipe books:
close the current overlay, fetch the other book's payload, open it.
`swipebook-inline.js`'s click delegation used to assume every click it
handled was fetching `/swipebook`; it now reads a `data-twd-ap-book-
variant` attribute (`swipebook` or `summary-book`) off whatever was
clicked -- the main button, a related-article item, or a companion link
-- and picks the REST endpoint accordingly. A new public (no
login required) REST route, `/articles/{id}/summary-book`, mirrors the
existing public `/articles/{id}/swipebook` route for this purpose, kept
separate from the edit-gated `/posts/{id}/summary-book` route added in
v1.31.0 for the review screen -- a public visitor reading a shared
Summary Book link has no `edit_post` capability and was never meant to
need one.

## On-site AI generation: one plugin with a key-gated branch, not a fork (v1.33.0)

The idea that started this: a client site generating its own articles
directly, without the therapist ever leaving their own site to visit
Article Assist on Therapy Resource Directory. The first framing floated
was a separate plugin for sites with their own API key. Rejected in
favour of one plugin with a branch, for a concrete reason: the only real
difference is where the generation call happens (this site's own
`wp_remote_post`, vs. a copy/paste round trip through a different site),
not what the result looks like or how it's reviewed. A fork would mean
every future fix to the popup, the sanitiser, or the Summary Book flow
needing to land in two codebases, forever, for a difference that's really
one `if`.

Two gates that look similar but aren't, worth keeping straight: TRD
membership (the Resource Creator tier) gates access to Article Assist
itself, a product on a different site entirely. Whether this specific
site has its own Anthropic key gates whether `class-twd-ap-ai-generate.php`
can generate inline. A site can have either, both, or neither
independently; nothing here checks TRD membership at all.

Why the prompt is duplicated rather than shared: `TWD_AP_AI_Generate`'s
system prompt is close to a line-for-line copy of Article Assist's own
(`trd_aa_shared_rules()`, `trd_aa_system_prompt_idea()`/`_format()` in
`therapy-resource-directory`'s `15 TRD Article Assist.php`) — same
category-matching instruction, same allowed-tags list, same summary_book
card rules (type/quote/question, open on text, close on question, at
least one quote). These are two separate WordPress installs with no
shared package manager or build step between them (consistent with this
plugin never having a build step of its own), so there's no clean way to
share the actual PHP. Accepted as a known, documented duplication rather
than building a shared-prompt mechanism across two repos for one feature
— CLAUDE.md flags it explicitly so a future prompt change in one place
prompts a check of the other, rather than the two silently drifting
apart (the summary_book card rules in particular, since those feed
`TWD_AP_Summary_Book::sanitize_cards()` directly and a mismatched shape
would just mean dropped cards, not an error).

Why the key field in Settings is always rendered blank rather than
showing the saved value (even masked): simplest correct behaviour for a
field only the site owner (or, by design, the client) ever sees, used
rarely (set once, maybe rotated occasionally). The real decision this
forces is in `TWD_AP_Settings::sanitize()`: since the field can never
carry the real value back for comparison, a blank submit has to mean
"nothing changed here," not "clear the key" — otherwise every unrelated
resave of the settings screen (ticking a different role, say) would
silently wipe a working key. An explicit "Remove the saved key" checkbox
is the only path that actually clears it.

Why the rate limit is a backstop (30/day) rather than a real limit like
Article Assist's 15/day: that number exists on the TRD side because TRD
itself pays the Anthropic bill for every member's usage, so it's a real
cost control. Here, the key is the client's own, billed to them directly
— there's no cost reason for this plugin to ration it. The limit exists
purely so a bug in the popup, or a logged-in editor with bad intentions,
can't loop the `/generate` endpoint into a runaway bill on someone else's
card by accident.

Why the model is fixed (`claude-sonnet-5-5`, effort `medium`) rather than
a per-site setting: every site behaves predictably, a future model swap
is a one-line change here instead of a silent per-site drift, and
there's no real reason a therapist would want a different model for this
specific task.

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

## Offering the saved AI key to other plugins as two filters (v1.34.0)

- **TWD Site Kit's "Ask the AI" feature needed to use a site's Anthropic key, and the decision was to share the capability, never the key.** Two WordPress filters, `twd_ai_is_configured` and `twd_ai_complete`, are the whole interface. Site Kit asks "is there a key?" and "run this system prompt and message", and gets back a yes/no and reply text. It never receives, stores or sees the key, so a second plugin cannot leak it, log it or print it into a page.
- **Why filters rather than a shared helper class or a REST route:** a direct class call would make Site Kit depend on this plugin's internals and fatal if the plugin was missing. A filter degrades safely: with the plugin inactive nothing hooks it, `apply_filters` hands back the default (`false` or `null`) and Site Kit can show "not set up". A REST route was ruled out because it would add a new browser-reachable surface next to the key.
- **`complete()` reuses `call_anthropic()` rather than copying it,** so any later fix to the endpoint, model, headers, timeout or error messages applies to both paths. The only refactor was giving `call_anthropic()` a `$max_tokens` argument that defaults to the old constant, so `generate()` sends exactly what it always did. This was checked by running v1.33.0's class and the new one through the same fake-API scenarios and comparing `generate()` output: identical.
- **Why null for "no key", not an error:** Site Kit needs to tell "nothing answered" (no key, plugin inactive, so show setup help) apart from "something answered with a failure" (bad key, refusal, timeout, which has a real message to show). A `WP_Error` for the no-key case would blur those.
- **Token clamp 256 to 8000, non-numeric becomes 5000:** the caller is another plugin, so the input is not trusted to be sensible. 8000 is the ceiling agreed with Site Kit.
- **Not counted against the 30 per day limit.** That backstop exists to catch a loop in the article popup's own endpoint. Site Kit enforces its own limit (10 requests per 10 minutes per user) and its own permission checks, and double counting would let Site Kit use eat into therapists' article generation.
- **No automated test suite was added.** The plugin has none, and the deploy workflow FTP-uploads the whole plugin folder, so a committed `tests/` folder inside it would ship to every client zip. The checks were run from a throwaway harness outside the repo (stubbed WordPress functions, fake HTTP responses): 46 checks covering configured true and false, null with no key and no request made, string on success, every `WP_Error` code passing through, the clamp, the key never appearing in any return value or request body, and `generate()` unchanged.

## The Elementor converter (v1.35.0)

- **Problem:** several client sites have old blog posts built as an Elementor HTML widget. The popup only reads and saves `post_content`, so it cannot edit them: it shows an empty or stale editor, and a save would not change what visitors see, because Elementor renders from its own stored layout (`_elementor_data`).
- **Decision: convert in place, one post at a time, behind a tick-box, with backup and Undo.** In place so the URL, SEO meta and everything else on the post carry over with no redirects. One at a time because every site's markup differs and a wrong bulk run would be hard to review. A tick-box (off by default, administrators only) so it is a tool Clive turns on, not something clients see.
- **Why a tidy step before the cleaner:** the first real sample (a Kingfisher Calm Counselling article) used `div` wrappers, `p` tags classed as sub-headings and `div` quote boxes. `TWD_AP_Sanitizer::clean()` removes `div` and all `class` attributes, so converting it as it stood would have flattened every sub-heading into a plain paragraph, losing the heading structure that SEO and the swipe book (which splits on h2) rely on. The second sample (plain `h3`, `p`, `em`, `ul`, links, no classes) needed no mapping, but had only h3 headings, so the optional h3 to h2 promotion exists for it.
- **Why per-site rules in Settings, not code:** class names differ on every client site. A rules box (`part-of-class = target`) means a new site is a settings edit, not a release. The Kingfisher rules are the default. The preview lists any old class names that were dropped, so a missing rule is visible before converting.
- **Why Blocked is strict:** only `html`, `text-editor` and `spacer` widgets convert. A post with headings, images or buttons built as Elementor widgets would lose them silently, so it is refused with the reason shown. Text lost to the cleaner is also checked (a warning if the cleaned text is under 95 percent of the tidied text).
- **Backup design:** the backup (original content, every `_elementor_*` meta value, the page template) is written BEFORE any change. If it cannot be written, nothing happens. If the content save fails after it, the backup is deleted and the post is left untouched. Undo restores everything, and needs an extra tick if the article was edited after converting, because undoing discards those edits. Meta values are re-added with `wp_slash()`, which keeps the Elementor JSON byte-identical (tested).
- **Elementor page templates:** a post using Elementor Canvas or Header Footer renders without the theme's header and footer. These are reset to the theme default on convert and restored on undo, with a warning.
- **Tests:** 75 checks in `tests/twd-article-publisher/converter-test.php`, run against real WordPress core (`wp_kses` and friends fetched via composer) with only the database faked. That replaced the stubbed `wp_kses` used for v1.34.0 and caught nothing wrong, but it is the reason the claim that `mailto:` links and apostrophes survive the cleaner is now tested rather than assumed. They are kept outside the plugin folder because the deploy workflow FTP-uploads the whole plugin folder. The data shape Elementor stores (`_elementor_data` JSON with `elType`, `widgetType`, `settings.html`) was built from knowledge of Elementor, not from a live site, so the first real use must be on a staging copy.

## The popup start screen (v1.36.0)

- **Why:** with an API key set, the generator was hidden inside the "Generate / Paste JSON" tab, behind the Visual and HTML tabs, so the best feature of the popup was the hardest to find. The ask was one window on opening a new article: "Write, dictate or paste your article ideas or a full article", with three choices under it (article idea, draft article improve, finished article only format).
- **Swap by one class, not by hiding elements one by one.** The editor already toggles `hidden` on its title, fields and footer for the JSON tab, so the start screen is shown by a single class on the overlay with CSS hiding everything else, instead of fighting those states. Leaving the start screen just removes the class.
- **Three modes, one new prompt.** `idea` and `format` are the existing modes unchanged. `improve` is new: keep the author's voice, meaning and spelling, tighten and structure, add nothing of its own. It has no Article Assist counterpart, so it is the one prompt in `class-twd-ap-ai-generate.php` not kept in step with TRD.
- **Silent truncation removed.** The old REST handler cut input at 20,000 characters without telling anyone. For `format` that meant the end of a long finished article disappeared and the rest was formatted as if complete. It is now refused with a clear message and the start screen shows a live count.
- **Larger reply for improve and format (8000 tokens, idea stays 5000).** These return a whole article plus SEO fields and a summary book; at 5000 a long article could be cut off. The risk is the other way round: a very long reply near 8000 tokens could approach the 120 second timeout. Not seen, not verified.
- **Original text kept.** An improved article replacing someone's own words with no way back felt wrong, so `improve` and `format` show a bar to swap the body between their text and the AI's, both ways, saving edits to whichever version is being left. Title, summary and tags are not swapped.
- **"X of 30 left today"** so the daily limit is never a surprise. The limit itself is unchanged and only counts successful generations.
- **No mic button.** Browser speech recognition sends audio to the browser maker's servers, a confidentiality problem for therapists. The box works with the device's own dictation.
- **Found while testing, fixed in the same release:** `.twd-ap-delete-btn { display: inline-flex !important }` beat the `hidden` attribute, so "Delete this article" showed on brand-new articles (it did nothing there, `requestDelete()` returns early without an id, but it looked wrong). Added the `[hidden]` rule CLAUDE.md already requires for hidden-toggled elements.
- **Tests:** `tests/twd-article-publisher/ai-test.php` (60 checks, pure PHP) and `tests/twd-article-publisher/start-screen/browser-test.js` (61 checks in real Chromium through Playwright, with phone width and screenshots). The 1.34.0 filter checks, previously only run from a scratch file, are now committed inside `ai-test.php`. Not run: a live Anthropic call, a real WordPress page, Safari or iPhone.

