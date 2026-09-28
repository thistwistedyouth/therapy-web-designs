# TWD Article Publisher

A WordPress plugin, generic and reusable across every Therapy Web Designs client site. It lets an approved logged-in user (a therapist, not a developer) publish and edit blog posts from a popup on the live front end, without going into wp-admin. It was built to solve one recurring problem: therapists writing blog content with an AI assistant and needing a fast way to get formatted HTML onto their site as a properly tagged, SEO-ready post.

Not tied to any one client's design. Header, footer, page CSS, per-site palettes and the "single HTML widget per page" Elementor build method (documented separately in Therapy Web Designs' own build notes) are unrelated to this plugin and live in each client site's own theme/Elementor content, not here.

## What it does

- Adds a floating **New Article** button on every front-end page, visible only to logged-in users in an allowed role.
- Adds a floating **Edit This Article** button on any single post the current user has permission to edit.
- Both open the same popup: title, a Visual/HTML dual-mode editor, category checkboxes, a featured image picker (native Media Library), an excerpt field, Yoast SEO title/description (shown only if Yoast is active), and Save Draft / Publish / Schedule.
- Everything is a normal WordPress post underneath. Nothing here replaces or forks core post storage; this is a front-end convenience layer over `wp_insert_post` / `wp_update_post`.

## Why it's built this way

- **Custom REST routes instead of `wp/v2/posts`.** The core posts endpoint doesn't give a clean hook point to force server-side HTML sanitisation before save. A dedicated namespace (`twd-publisher/v1`) means every write funnels through `TWD_AP_Sanitizer::clean()` first, no exceptions.
- **Sanitise on the server, never trust the browser.** The popup can receive AI-generated HTML of unknown quality. `wp_kses` with an explicit allow-list strips scripts, inline styles and event handlers regardless of what the client sends. Any `<h1>` is demoted to `<h2>` so a pasted article never fights the theme's own post-title H1.
- **Vanilla JS, no build step.** `execCommand` was chosen over pulling in TinyMCE or a bundler because this plugin has to drop into any client's WordPress with zero dependency risk. It is deliberately simple rather than feature-complete.
- **Capability check duplicated in two places on purpose.** `TWD_AP_Frontend::current_user_allowed()` (role-based, for creating) and `current_user_can( 'edit_post', $id )` (WordPress's own per-post capability, for editing) are both enforced. Don't collapse these into one check; they answer different questions.

## File map

```
twd-article-publisher.php                Plugin bootstrap, loads the four classes below
includes/class-twd-ap-settings.php       Settings > Article Publisher screen; allowed roles, default category
includes/class-twd-ap-sanitizer.php      TWD_AP_Sanitizer::clean() — the one place HTML gets cleaned
includes/class-twd-ap-rest.php           REST routes: /categories, /posts, /posts/{id}, /media
includes/class-twd-ap-frontend.php       Enqueues assets, decides which buttons show, capability gate
templates/buttons-and-modal.php          The popup's HTML skeleton (PHP-rendered once per page load)
assets/publisher.css                     Popup and button styling (neutral, not client-branded)
assets/publisher.js                      All popup behaviour: tabs, toolbar, media picker, REST calls
readme.txt                               Standard WP plugin readme (also shown in Plugins list)
```

## Conventions to keep

- WordPress coding standards: tabs for indentation in PHP, `snake_case` functions, `Prefixed_Class_Names` with the `TWD_AP_` prefix on every class to avoid collisions on shared hosting.
- Every option, meta key and REST namespace is prefixed `twd_ap_` / `twd-publisher` — never assume this is the only plugin on the site.
- `TWD_AP_Sanitizer::clean()` is the only path HTML should ever take before `wp_insert_post`/`wp_update_post`. If a new field or endpoint accepts HTML, route it through here, don't add a parallel sanitiser.
- Yoast fields are optional and gated on `defined( 'WPSEO_VERSION' )`. Don't assume Yoast is present; check every time.
- No inline styles are allowed through the sanitiser by design (a Recommended-tier decision made when this was scoped). If a future client needs AI-generated inline styling preserved, that's a deliberate change to the allow-list in `class-twd-ap-sanitizer.php`, not a bug to silently fix.
- Scheduling uses the site's local time (`current_time( 'mysql' )` / `get_gmt_from_date()`), not the browser's timezone. Keep it that way; don't introduce client-side timezone conversion.

## Known limitations / open items

- No automated test suite. Testing so far has been PHP lint (`php -l`) on every file, a Node syntax check on the JS, and a standalone PHP harness that stubs `wp_kses`/`apply_filters` to prove the sanitiser's own logic (H1 demotion, script/style stripping, `rel` injection on `target="_blank"` links) without a live WordPress install. See "Testing" below to redo this from scratch.
- `execCommand` (used for the Visual tab's Bold/Italic/heading toolbar) is a soft-deprecated browser API. It still works everywhere as of this writing, but if it's ever removed from browsers, the toolbar in `assets/publisher.js` needs replacing, most likely with a small dependency-free `contenteditable` command layer rather than a full editor library.
- No image compression or size limit on featured-image or inline-image uploads beyond what WordPress's own media handling already applies.
- Not yet tested inside a real WordPress instance end-to-end (create → categorise → feature image → publish → edit → reschedule). The sandbox this was built in has no WordPress install. Treat first real-site install as the actual integration test.
- No multisite-specific handling; assume single-site until proven otherwise.

## Testing

```bash
# PHP syntax
php -l twd-article-publisher.php
for f in includes/*.php templates/*.php; do php -l "$f"; done

# JS syntax
node --check assets/publisher.js
```

For sanitiser logic without a live WordPress install, stub `wp_kses` and `apply_filters`, then assert against known-bad input (script tags, `onclick`, inline `style`, an `<h1>`, a `target="_blank"` link). This was the method used during the original build; there's no committed test file for it yet, it was written and discarded as a one-off `/tmp` script — worth turning into a real `tests/` directory with a proper WP test stub if this plugin gets ongoing development.

## Packaging for distribution

```bash
cd .. && zip -r -X twd-article-publisher.zip twd-article-publisher -x "*.git*"
```

Upload via **Plugins > Add New > Upload Plugin** on any client site. No build step, no `npm install`, nothing to compile.
