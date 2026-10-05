# Build log

At the start of every session read CLAUDE.md and this file. Update this file in the same commit as every piece of work.

## Where we are

- **Current version:** 1.34.0 (merged to `main`, deploy workflow run for it started 2026-10-04).
- **What it does:** lets an approved logged-in user publish and edit articles from a popup on the live site, with a resources grid shortcode, swipe book, Summary Book, and on-site AI article generation using the site's own Anthropic key. From 1.34.0 it also offers two filters, `twd_ai_is_configured` and `twd_ai_complete`, so another plugin can use that key without seeing it (see "Filters offered to other plugins" in CLAUDE.md).
- **What is next:** 1.34.0 is live on `main`. Next is the Elementor converter (plan below), agreed in principle, not yet built, waiting on the open questions.
- **Waiting on other projects:** nothing. TWD Site Kit (0.5.0, `thistwistedyouth/twd-site-kit`) now depends on these two filters, so do not change their arguments or return contract without a written brief to that repo.

## Log

- **1.34.0** - Added the `twd_ai_is_configured` and `twd_ai_complete` filters and `TWD_AP_AI_Generate::complete()` and `clamp_max_tokens()`. `call_anthropic()` takes an optional `$max_tokens`, default unchanged. `generate()` and the popup untouched. Checked with a throwaway harness (46 checks pass, `generate()` output identical to 1.33.0). No automated suite exists in the plugin.

## Planned: Elementor to plugin converter (v1.35.0, not built)

Agreed so far: wp-admin tool only, off unless a tick-box in Settings > Article Publisher is ticked, one post at a time, original saved before converting with an Undo button. Same post is converted in place, so URL, title, categories, tags, featured image, excerpt and SEO meta (Yoast or Rank Math) are not touched. Only `post_content` and the Elementor meta change.

Findings from the first sample (Kingfisher Calm Counselling article 5, widget HTML only, stored Elementor data not yet seen):
- The cleaner strips `div` and every `class`. The sample's sub-headings are `<p class="kcc-article__subhead">`, not headings, so plain stripping would turn them into ordinary paragraphs and lose the heading structure. Quotes are `<div class="kcc-article__quote">` and would become plain paragraphs.
- So the converter needs a tidy step BEFORE the cleaner: subhead paragraphs become `h2`, quote divs become `blockquote`, wrapper and footer divs are unwrapped (footer text kept as a paragraph), HTML comments dropped. The preview must show the result, and the site's `kcc-*` page CSS no longer applies afterwards (the plugin's own article typography takes over), so the look will change.
- Un-mappable things (script, style, iframes, other widgets, unknown divs with their own styling) give a Warning or a Refusal, with the reason shown. Nothing is converted silently.

Open questions: are the live posts really stored in an Elementor HTML widget, or already in the normal editor? Which sibling sites (other than KCC) use different class names?
