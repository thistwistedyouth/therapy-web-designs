# Build log

At the start of every session read CLAUDE.md and this file. Update this file in the same commit as every piece of work.

## Where we are

- **Current version:** 1.35.0 on branch `claude/converter-plan`, built and tested, NOT merged to `main` yet (merging deploys to therapyresourcedirectory.com and publishes the update to every site). 1.34.0 is what is live.
- **What it does:** lets an approved logged-in user publish and edit articles from a popup on the live site, with a resources grid shortcode, swipe book, Summary Book, and on-site AI article generation using the site's own Anthropic key. From 1.34.0 it also offers two filters, `twd_ai_is_configured` and `twd_ai_complete`, so another plugin can use that key without seeing it (see "Filters offered to other plugins" in CLAUDE.md).
- **What is next:** (1) Clive approves, then merge 1.35.0 to `main`. (2) First real use of the converter on a STAGING copy of one site, because the shape of Elementor's stored data was built from knowledge, not seen on a live site. (3) v1.36.0 "Article look" settings (text, heading, link and quote colours, font) so converted posts can match each site. (4) Optional guard in the popup for unconverted Elementor posts.
- **Waiting on other projects:** nothing. TWD Site Kit (0.5.0, `thistwistedyouth/twd-site-kit`) now depends on these two filters, so do not change their arguments or return contract without a written brief to that repo.

## Log

- **1.34.0** - Added the `twd_ai_is_configured` and `twd_ai_complete` filters and `TWD_AP_AI_Generate::complete()` and `clamp_max_tokens()`. `call_anthropic()` takes an optional `$max_tokens`, default unchanged. `generate()` and the popup untouched. Checked with a throwaway harness (46 checks pass, `generate()` output identical to 1.33.0). No automated suite exists in the plugin.
- **1.35.0** - Elementor converter: `includes/class-twd-ap-converter.php`, tick-box and rules box in Settings, Tools > Convert Elementor posts. One post at a time, in place, preview, backup before change, Undo. 75 checks pass against real WordPress core (`tests/twd-article-publisher/converter-test.php`, outside the plugin folder). Not yet tried on a real site.
