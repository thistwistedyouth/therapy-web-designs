// Real-browser test of the server-rendered [twd_articles] grid. The page HTML comes from the real PHP
// (ssr-grid-test.php dumps it), the CSS and JS are the real files; only the REST replies are faked, with
// the data the same PHP would have returned. Checks that the JS takes over without a flash or a jump.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/ssr-grid-browser-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-ssr-'));
const dumpFile = path.join(tmp, 'dump.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/ssr-grid-test.php')], { env: Object.assign({}, process.env, { TWD_SSR_DUMP: dumpFile }) });
const DUMP = JSON.parse(fs.readFileSync(dumpFile, 'utf8'));
const CSS = fs.readFileSync(path.join(plugin, 'assets/articles-grid.css'), 'utf8');
const JS = fs.readFileSync(path.join(plugin, 'assets/articles-grid.js'), 'utf8');
const REST = 'https://example.test/wp-json/twd-publisher/v1';

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

function pageHtml(name, withJs) {
	return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>' + CSS + '</style></head><body>' +
		DUMP[name].html + (withJs ? '<script>' + JS + '</script>' : '') + '</body></html>';
}

async function open(browser, name, o) {
	o = Object.assign({ js: true, delay: 2000, fail: false, empty: false }, o);
	const ctx = await browser.newContext({ viewport: { width: 1100, height: 900 }, javaScriptEnabled: o.js });
	const page = await ctx.newPage();
	const errors = [];
	let restDone = 0;
	page.on('pageerror', (e) => errors.push(String(e)));
	await page.route('https://example.test/page', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: pageHtml(name, o.js) }));
	await page.route(REST + '/**', async (route) => {
		const url = route.request().url();
		await wait(o.delay);
		restDone++;
		if (o.fail) { return route.abort(); }
		let body = {};
		if (/\/articles\/grouped/.test(url)) { body = DUMP[name].grouped; }
		else if (/\/articles\/facets/.test(url)) { body = { categories: [], tags: [] }; }
		else if (/\/articles\?/.test(url)) { body = o.empty ? { items: [], has_more: false } : DUMP[name].list; }
		else if (/\/grid-settings/.test(url)) { body = { show_thumbnails: true, read_more_color: '#1d4ed8', accent_color: '#2563eb', total_articles: DUMP[name].total }; }
		return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
	});
	await page.goto('https://example.test/page');
	return { ctx, page, errors, done: () => restDone };
}

(async () => {
	const browser = await chromium.launch();

	// 1. Small site: nothing flashes or moves when the JS takes over.
	for (const name of ['small', 'large']) {
		const { ctx, page, errors, done } = await open(browser, name);
		const target = name === 'small' ? '.twd-ap-articles-grid' : '.twd-ap-articles-groups';
		await page.waitForSelector('.twd-ap-article-card');
		const before = await page.locator(target).innerHTML();
		const boxBefore = await page.locator('.twd-ap-article-card').first().boundingBox();
		const cardsBefore = await page.locator('.twd-ap-article-card').count();
		t(name + ': the server cards are visible before any REST reply lands', cardsBefore > 0 && done() === 0, 'cards=' + cardsBefore + ' done=' + done());
		t(name + ': no skeleton cards appear while the JS fetches', (await page.locator('.twd-ap-skeleton-card').count()) === 0);
		await page.waitForFunction(() => true);
		while (done() < 3) { await wait(100); }
		await wait(400);
		const after = await page.locator(target).innerHTML();
		const boxAfter = await page.locator('.twd-ap-article-card').first().boundingBox();
		// The only intended difference: the server's "See all" is a plain link, the JS swaps in its button.
		const norm = (h) => h.replace(/<a class="twd-ap-articles-group-more" href="[^"]*" data-slug="([^"]*)">See all<\/a>/g, '<button type="button" class="twd-ap-articles-group-more" data-slug="$1">See all</button>');
		t(name + ': after the JS takes over the markup is identical', norm(before) === norm(after), before === after ? '' : 'differs beyond See all');
		t(name + ': the first card did not move', JSON.stringify(boxBefore) === JSON.stringify(boxAfter));
		t(name + ': no JavaScript errors', errors.length === 0, errors.join(' | '));
		t(name + ': the topic links are still there afterwards', (await page.locator('.twd-ap-articles-topics a').count()) >= 2);
		await ctx.close();
	}

	// 2. A failed fetch keeps what the server printed.
	{
		const { ctx, page, errors } = await open(browser, 'small', { fail: true, delay: 100 });
		await wait(1200);
		t('failed fetch: the server cards are still on the page', (await page.locator('.twd-ap-article-card').count()) === 5);
		t('failed fetch: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await ctx.close();
	}

	// 3. No JavaScript at all: real links and titles are there.
	{
		const { ctx, page } = await open(browser, 'small', { js: false });
		const hrefs = await page.$$eval('a.twd-ap-article-card', (a) => a.map((x) => x.getAttribute('href')));
		const text = await page.locator('body').innerText();
		t('no JavaScript: five real article links', hrefs.length === 5 && hrefs.every((h) => /^https:\/\/example\.test\/post-\d+\/$/.test(h)));
		t('no JavaScript: titles are visible text', text.includes('Newest piece') && text.includes('Older featured piece'));
		t('no JavaScript: no loading text and no filter message', !/Loading articles|No articles match/.test(text));
		await ctx.close();
	}

	// 4. An empty site: the friendly line shows, before and after the JS, never the filter words.
	{
		const { ctx, page, errors } = await open(browser, 'empty', { empty: true, delay: 100 });
		const visible = () => page.locator('.twd-ap-articles-none').isVisible();
		t('empty site: the friendly line is visible straight away', await visible());
		await wait(1000);
		t('empty site: still visible after the JS has run', await visible());
		t('empty site: the filter words are not on the page', !(await page.locator('body').innerText()).includes('No articles match'));
		t('empty site: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await ctx.close();
	}

	// 5. A real search with no matches does show the filter words (added by the JS).
	{
		const { ctx, page } = await open(browser, 'small', { delay: 50 });
		await wait(600);
		await page.route(/\/articles\?/, (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [], has_more: false }) }));
		await page.fill('.twd-ap-articles-search', 'zzzz');
		await page.waitForFunction(() => /No articles match your filters/.test(document.body.innerText), null, { timeout: 4000 }).catch(() => {});
		const text = await page.locator('body').innerText();
		t('a search with no matches shows the filter message', text.includes('No articles match your filters.'));
		t('a search with no matches does not show the friendly empty-site line', !(await page.locator('.twd-ap-articles-none').isVisible()));
		await ctx.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
