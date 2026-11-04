# Release 1.50.8: steps for Clive

Status: built, tested on graham.twdstaging.co.uk, NOT pushed, NOT merged, NOT released.
Branch: `claude/articles-display-636f5d` (based on `main` at 1.49.0, nothing else in between).

## What is ready
- Version 1.50.8 in the plugin header, `TWD_AP_VERSION` and `readme.txt` (Stable tag).
- `dist/twd-article-publisher-latest.zip` built from the plugin folder (35 files, one top-level `twd-article-publisher/` folder, no CLAUDE.md, HISTORY.md, BUILD-LOG.md or RELEASE file inside). Checked: the copies of six changed files inside the zip are identical to the plugin folder, every PHP file passes a syntax check, and the zip installs cleanly with `wp plugin install --force` on graham.
- `dist/twd-article-publisher-update.json` has version 1.50.8, today's date and a plain-English changelog.
- Tests: all PHP tests in `tests/twd-article-publisher` pass except `archive-test.php`, which fails the same way on `main` (not caused by this work). A new `seo-test.php` was added. Seven existing tests were updated because the changes are intentional (card category line, accent colour default, font fallbacks, and a new class the REST file now needs).

## Read before you say yes: what changes on every client site
When a site updates, these happen with no setting touched:
1. **Article pages get a header block** (lead line only if a hand-written excerpt exists, topic, date, reading time, and the featured picture). A site that does not want the picture unticks "Show the featured picture at the top of every article" in Customise this grid, or ticks "Do not show this picture" on one article.
2. **Article text follows the site's own fonts and colours** only if the theme publishes `--twd-site-*` variables (Site Kit does). Other sites look as before.
3. **On sites with no SEO plugin** the plugin now prints the page title, description and share tags for articles. Check any site where something else (a theme or another plugin) already prints share tags for posts, so there is not a second set. Sites with Yoast, Rank Math, All in One SEO, SEOPress or The SEO Framework are unaffected.
4. **The resources grid shows category pills** and the category on its own line on each card. Pages cached before the update keep the old card text until the cache clears.
5. **Phone card pictures become 3:2** (sites that chose 4:3 or square keep it).
6. **Swipe book:** the cover gets a faded featured picture (same two ticks as item 1); a book with no logo no longer repeats the site name; a logo with no pill behind it is capped at 38px.

## Steps (you do these, or say "go" and I do steps 1 to 3)
1. **Push the branch:** `git push -u origin claude/articles-display-636f5d`
2. **Open a pull request** into `main` titled "1.50.8: articles on a Site Kit site, SEO box, grid and swipe book polish".
3. **Look at the pull request** (files changed: plugin folder, `dist/` zip and JSON, tests).
4. **Merge it** (you). The updater reads `dist/twd-article-publisher-update.json` from `main`, so merging is the moment every client site can see 1.50.8. Sites pick it up within 12 hours, or at once with Check again on their Plugins page.
5. The "Deploy TWD Article Publisher" workflow has failed at the FTPS login on recent merges (`530 Login failed`). That only affects the copy on therapyresourcedirectory.com. Update that site with Check for updates instead.
6. Optional: tag the release, `git tag v1.50.8 <merge commit>` and push the tag.

## How to undo after release
- **Before anyone updates:** revert the merge commit on `main` (or restore `dist/twd-article-publisher-update.json` to 1.49.0 and the previous zip). Nobody has 1.50.8 yet, so nothing else is needed.
- **After sites updated:** put 1.49.0 back as the newest version by publishing a 1.49.1 that is the old code (the updater only offers newer versions), or install the 1.49.0 zip by hand on a site. The previous zip is `git show 4d0cd7d:dist/twd-article-publisher-latest.zip`.

## Graham (staging) right now
Running 1.50.8 from the release zip. Back to 1.49.0: `ssh graham`, then `cd ~/public_html/wp-content && rm -rf plugins/twd-article-publisher && tar -xzf ~/twd-backups/twd-article-publisher-1.49.0.tgz`.
Graham-only settings that are data, not code: swipe book logo is the white star (media item 62) with no pill; site context is "Clinical psychologist, Dubai and online"; article 60 has hand-typed test SEO text.
