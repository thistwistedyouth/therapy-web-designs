# Build log

At the start of every session read CLAUDE.md and this file. Update this file in the same commit as every piece of work.

## Where we are

- **Current version:** 1.34.0 (on branch `claude/ai-filters-1-34-0`, not yet merged to `main`; 1.33.0 is what is live until it is).
- **What it does:** lets an approved logged-in user publish and edit articles from a popup on the live site, with a resources grid shortcode, swipe book, Summary Book, and on-site AI article generation using the site's own Anthropic key. From 1.34.0 it also offers two filters, `twd_ai_is_configured` and `twd_ai_complete`, so another plugin can use that key without seeing it (see "Filters offered to other plugins" in CLAUDE.md).
- **What is next:** merge 1.34.0 to `main` once approved, then update sites through the one-click updater. No further work is planned in this plugin.
- **Waiting on other projects:** nothing. TWD Site Kit (0.5.0, `thistwistedyouth/twd-site-kit`) now depends on these two filters, so do not change their arguments or return contract without a written brief to that repo.

## Log

- **1.34.0** - Added the `twd_ai_is_configured` and `twd_ai_complete` filters and `TWD_AP_AI_Generate::complete()` and `clamp_max_tokens()`. `call_anthropic()` takes an optional `$max_tokens`, default unchanged. `generate()` and the popup untouched. Checked with a throwaway harness (46 checks pass, `generate()` output identical to 1.33.0). No automated suite exists in the plugin.
