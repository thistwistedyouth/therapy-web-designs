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
const indexFile = path.join(tmp, 'index.html');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/index-test.php')], { env: Object.assign({}, process.env, { TWD_INDEX_DUMP: indexFile }) });
const INDEX_HTML = fs.readFileSync(indexFile, 'utf8');
const CSS = fs.readFileSync(path.join(plugin, 'assets/articles-grid.css'), 'utf8');
const JS = fs.readFileSync(path.join(plugin, 'assets/articles-grid.js'), 'utf8');
const REST = 'https://example.test/wp-json/twd-publisher/v1';

let pass = 0, fail = 0;
const saved = [];
let window_topics = 'all';
let window_cta = {};
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
		else if (/\/articles\/facets/.test(url)) { body = { categories: [{ name: 'Anxiety', slug: 'anxiety' }, { name: 'Sleep', slug: 'sleep' }], tags: [] }; }
		else if (/\/articles\?/.test(url)) { body = o.empty ? { items: [], has_more: false } : DUMP[name].list; }
		else if (/\/grid-settings/.test(url)) { if (route.request().method() === 'POST') { saved.push(JSON.parse(route.request().postData())); return route.fulfill({ status: 200, contentType: 'application/json', body: '{"saved":true}' }); } body = { show_thumbnails: true, read_more_color: '#1d4ed8', accent_color: '#2563eb', total_articles: DUMP[name].total, categories_count: 4, posts_per_category: 6, categories: [{ id: 2, name: 'Anxiety' }, { id: 3, name: 'Sleep' }, { id: 5, name: 'Boundaries' }], topic_category_ids: window_topics, look_text_color: '', look_heading_color: '', look_link_color: '', look_quote_color: '', look_font: 'system', look_size: 'medium', look_spacing: 'normal', cta_heading: '', cta_text: '', cta_button_label: '', cta_button_url: '', cta_show_grid: false, cta_show_categories: false, cta_show_index: false, cta_show_articles: false };
			Object.assign(body, window_cta); }
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

	// 6. The top filter starts with All, then the categories; choosing All goes back.
	{
		const requests = [];
		const { ctx, page } = await open(browser, 'small', { delay: 50 });
		page.on('request', (r) => { if (/\/articles\?/.test(r.url())) { requests.push(r.url()); } });
		await wait(700);
		const opts = await page.$$eval('.twd-ap-articles-category-select option', (o) => o.map((x) => x.textContent));
		t('filter: All first, then the categories in order, no Show all', JSON.stringify(opts) === JSON.stringify(['All', 'Anxiety', 'Sleep']), JSON.stringify(opts));
		t('filter: All is selected to start with', (await page.locator('.twd-ap-articles-category-select').inputValue()) === '');
		await page.selectOption('.twd-ap-articles-category-select', 'sleep');
		await wait(500);
		t('filter: choosing a category asks for just that category', requests.some((u) => /category=sleep/.test(u)));
		const before = requests.length;
		await page.selectOption('.twd-ap-articles-category-select', '');
		await wait(500);
		t('filter: choosing All asks for the unfiltered list again', requests.length > before && !/category=/.test(requests[requests.length - 1]));
		await ctx.close();
	}

	// 7. The index link is a pill in the accent colour.
	{
		const { ctx, page } = await open(browser, 'indexlink', { js: false });
		const pill = page.locator('a.twd-ap-articles-index-pill');
		const style = await pill.evaluate((el) => { const c = getComputedStyle(el); return { bg: c.backgroundColor, radius: parseFloat(c.borderTopLeftRadius), color: c.color }; });
		t('pill: the index link is a pill button with the accent colour', style.bg === 'rgb(37, 99, 235)' && style.radius >= 20 && style.color === 'rgb(255, 255, 255)', JSON.stringify(style));
		t('pill: it reads Browse all articles', (await pill.innerText()).includes('Browse all articles'));
		await ctx.close();
	}

	// 8. Few articles: one is a wide card, two sit two across, five stay three across.
	{
		const { ctx, page } = await open(browser, 'small', { js: false });
		const grid = page.locator('.twd-ap-articles-grid').first();
		const widths = async () => ({ g: (await grid.boundingBox()).width, c: (await page.locator('.twd-ap-article-card').first().boundingBox()).width });
		let w = await widths();
		t('few articles: five cards stay three across', w.c < w.g * 0.4, JSON.stringify(w));
		await page.evaluate(() => { document.querySelectorAll('.twd-ap-article-card').forEach((c, i) => { if (i > 1) { c.remove(); } }); });
		w = await widths();
		t('few articles: two cards sit two across', w.c > w.g * 0.45 && w.c < w.g * 0.55, JSON.stringify(w));
		await page.evaluate(() => { document.querySelectorAll('.twd-ap-article-card').forEach((c, i) => { if (i > 0) { c.remove(); } }); });
		w = await widths();
		const dir = await page.locator('.twd-ap-article-card').first().evaluate((el) => getComputedStyle(el).flexDirection);
		t('few articles: one card is a wide card, image beside the text', w.c > w.g * 0.95 && dir === 'row', JSON.stringify(w) + ' ' + dir);
		await ctx.close();
		const phone = await browser.newContext({ viewport: { width: 375, height: 800 }, javaScriptEnabled: false });
		const pp = await phone.newPage();
		await pp.route('https://example.test/page', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: pageHtml('small', false) }));
		await pp.goto('https://example.test/page');
		await pp.evaluate(() => { document.querySelectorAll('.twd-ap-article-card').forEach((c, i) => { if (i > 0) { c.remove(); } }); });
		const pdir = await pp.locator('.twd-ap-article-card').first().evaluate((el) => getComputedStyle(el).flexDirection);
		const over = await pp.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
		t('few articles: on a phone the wide card stacks and nothing scrolls sideways', pdir === 'column' && !over, pdir + ' overflow=' + over);
		await phone.close();
	}

	// 9. The index page: the dropdown shows one topic at a time; without JavaScript everything shows.
	{
		const open2 = async (js) => {
			const ctx = await browser.newContext({ viewport: { width: 900, height: 800 }, javaScriptEnabled: js });
			const page = await ctx.newPage();
			const errors = [];
			page.on('pageerror', (e) => errors.push(String(e)));
			await page.route('https://example.test/index', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head><meta charset="utf-8"><style>' + CSS + '</style></head><body><h1>Article index</h1>' + INDEX_HTML + (js ? '<script>' + JS + '</script>' : '') + '</body></html>' }));
			await page.goto('https://example.test/index');
			return { ctx, page, errors };
		};
		let r = await open2(false);
		t('index without JavaScript: every section shows, the jump links show, the dropdown does not',
			(await r.page.locator('.twd-ap-index-section:visible').count()) === 4 && await r.page.locator('.twd-ap-index-jump').isVisible() && !(await r.page.locator('.twd-ap-index-filter').isVisible()));
		await r.ctx.close();
		r = await open2(true);
		t('index with JavaScript: the dropdown shows and replaces the jump links', await r.page.locator('.twd-ap-index-filter select').isVisible() && !(await r.page.locator('.twd-ap-index-jump').isVisible()));
		const opts = await r.page.$$eval('.twd-ap-index-filter option', (o) => o.map((x) => x.textContent));
		t('index dropdown: All topics first, then each topic', opts[0] === 'All topics' && opts.length === 5, JSON.stringify(opts));
		await r.page.selectOption('.twd-ap-index-filter select', 'twd-ap-index-sleep');
		const visible = await r.page.$$eval('.twd-ap-index-section', (s) => s.filter((x) => x.getBoundingClientRect().height > 0).map((x) => x.id));
		t('index dropdown: choosing a topic shows only that topic', JSON.stringify(visible) === JSON.stringify(['twd-ap-index-sleep']), JSON.stringify(visible));
		await r.page.selectOption('.twd-ap-index-filter select', '');
		t('index dropdown: All topics shows everything again', (await r.page.locator('.twd-ap-index-section:visible').count()) === 4);
		t('index: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();
	}

	// 10. The gear popup: pick which categories the Browse by topic row lists.
	{
		const { ctx, page, errors } = await open(browser, 'admin', { delay: 50 });
		await page.click('.twd-ap-articles-settings-btn');
		await page.waitForSelector('#twd-ap-gs-topics input');
		const boxes = () => page.$$eval('#twd-ap-gs-topics input', (b) => b.map((x) => x.checked));
		t('topics setting: with the default (all) every category is ticked', JSON.stringify(await boxes()) === '[true,true,true]');
		await page.uncheck('#twd-ap-gs-topics input[value="5"]');
		await page.click('#twd-ap-gs-save');
		await wait(300);
		t('topics setting: unticking one saves the list of the others', JSON.stringify(saved[saved.length - 1].topic_category_ids) === '[2,3]', JSON.stringify(saved[saved.length - 1] && saved[saved.length - 1].topic_category_ids));
		await page.check('#twd-ap-gs-topics input[value="5"]');
		await page.click('#twd-ap-gs-save');
		await wait(300);
		t('topics setting: ticking them all again saves all (new categories included)', saved[saved.length - 1].topic_category_ids === 'all');
		t('topics setting: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await ctx.close();
		window_topics = [2, 3];
		const again = await open(browser, 'admin', { delay: 50 });
		await again.page.click('.twd-ap-articles-settings-btn');
		await again.page.waitForSelector('#twd-ap-gs-topics input');
		t('topics setting: a saved list shows only those ticked', JSON.stringify(await again.page.$$eval('#twd-ap-gs-topics input', (b) => b.map((x) => x.checked))) === '[true,true,false]');
		await again.ctx.close();
	}


	// 11. The gear popup: Article look, with a live preview, saving only what was changed.
	{
		const { ctx, page, errors } = await open(browser, 'admin', { delay: 50 });
		await page.click('.twd-ap-articles-settings-btn');
		await page.waitForSelector('#twd-ap-gs-look-details');
		await page.click('#twd-ap-gs-look-details > summary');
		const pc = (sel) => page.locator('#twd-ap-gs-look-preview ' + sel).evaluate((el) => getComputedStyle(el).color);
		t('look setting: opens with the plugin defaults and a sample', (await page.locator('#twd-ap-gs-look-text').inputValue()) === '#1f2937' && (await page.locator('#twd-ap-gs-look-font').inputValue()) === 'system' && (await pc('p')) === 'rgb(31, 41, 55)');
		await page.locator('#twd-ap-gs-look-text').evaluate((el) => { el.value = '#aa0000'; el.dispatchEvent(new Event('input', { bubbles: true })); });
		await page.locator('#twd-ap-gs-look-link').evaluate((el) => { el.value = '#00aa00'; el.dispatchEvent(new Event('input', { bubbles: true })); });
		t('look setting: the preview changes as the colours change', (await pc('p')) === 'rgb(170, 0, 0)' && (await pc('a')) === 'rgb(0, 170, 0)' && (await pc('h4')) === 'rgb(170, 0, 0)');
		await page.selectOption('#twd-ap-gs-look-font', 'serif');
		t('look setting: choosing serif changes the preview font', /Georgia/.test(await page.locator('#twd-ap-gs-look-preview').evaluate((el) => getComputedStyle(el).fontFamily)));
		await page.selectOption('#twd-ap-gs-look-font', 'theme');
		t('look setting: choosing the theme font makes the preview inherit', !/Georgia/.test(await page.locator('#twd-ap-gs-look-preview').evaluate((el) => getComputedStyle(el).fontFamily)));
		await page.selectOption('#twd-ap-gs-look-size', 'large');
		t('look setting: large text size shows in the preview', (await page.locator('#twd-ap-gs-look-preview').evaluate((el) => getComputedStyle(el).fontSize)) === '19px');
		await page.click('#twd-ap-gs-save');
		await wait(300);
		const body = saved[saved.length - 1];
		t('look setting: saving sends exactly what was changed and the plugin default for the rest', body.look_text_color === '#aa0000' && body.look_link_color === '#00aa00' && body.look_heading_color === '' && body.look_quote_color === '' && body.look_font === 'theme' && body.look_size === 'large' && body.look_spacing === 'normal', JSON.stringify(body));
		await page.click('#twd-ap-gs-look-reset');
		t('look setting: reset puts back the defaults and the preview', (await page.locator('#twd-ap-gs-look-text').inputValue()) === '#1f2937' && (await pc('p')) === 'rgb(31, 41, 55)' && (await page.locator('#twd-ap-gs-look-size').inputValue()) === 'medium');
		await page.click('#twd-ap-gs-save');
		await wait(300);
		const cleared = saved[saved.length - 1];
		t('look setting: saving after a reset clears every choice', cleared.look_text_color === '' && cleared.look_link_color === '' && cleared.look_font === 'system' && cleared.look_size === 'medium');
		t('look setting: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await ctx.close();
	}


	// 12. The call to action: a centred box with an accent pill button, untouched by the grid's JS.
	{
		const { ctx, page, errors } = await open(browser, 'cta', { delay: 50 });
		await wait(700);
		const box = page.locator('.twd-ap-cta-grid');
		t('cta: the box is on the page after the JS has taken over', await box.isVisible());
		t('cta: it has the heading, the text and the button', (await box.innerText()).includes('Ready to talk?') && (await box.innerText()).includes('A short chat is free.') && (await box.locator('a.twd-ap-cta-button').innerText()) === 'Book a call');
		const style = await box.locator('a.twd-ap-cta-button').evaluate((el) => { const c = getComputedStyle(el); return { bg: c.backgroundColor, color: c.color, radius: parseFloat(c.borderTopLeftRadius) }; });
		t('cta: the button is an accent coloured pill with white text', style.bg === 'rgb(37, 99, 235)' && style.color === 'rgb(255, 255, 255)' && style.radius >= 20, JSON.stringify(style));
		const bb = await box.boundingBox();
		const gb = await page.locator('.twd-ap-articles').boundingBox();
		t('cta: the box is centred under the grid and no wider than 720px', Math.abs((bb.x + bb.width / 2) - (gb.x + gb.width / 2)) < 2 && bb.width <= 721, JSON.stringify([bb, gb]));
		t('cta: the box sits below the grid, not over it', bb.y > (await page.locator('.twd-ap-articles-grid').first().boundingBox()).y);
		t('cta: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await ctx.close();
		const phone = await browser.newContext({ viewport: { width: 375, height: 800 } });
		const pp = await phone.newPage();
		await pp.route('https://example.test/page', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: pageHtml('cta', false) }));
		await pp.goto('https://example.test/page');
		t('cta: on a phone nothing scrolls sideways', !(await pp.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)));
		await phone.close();
	}

	// 13. The gear popup: Call to action fields fill from the saved values and save as typed.
	{
		window_cta = { cta_heading: 'Hello', cta_text: 'Line one', cta_button_label: 'Go', cta_button_url: 'https://example.test/x', cta_show_grid: true, cta_show_articles: true };
		const { ctx, page, errors } = await open(browser, 'admin', { delay: 50 });
		await page.click('.twd-ap-articles-settings-btn');
		await page.waitForSelector('#twd-ap-gs-cta-details');
		await page.click('#twd-ap-gs-cta-details > summary');
		t('cta setting: the fields fill from what was saved', (await page.inputValue('#twd-ap-gs-cta-heading')) === 'Hello' && (await page.inputValue('#twd-ap-gs-cta-label')) === 'Go' && (await page.inputValue('#twd-ap-gs-cta-url')) === 'https://example.test/x' && (await page.isChecked('#twd-ap-gs-cta-grid')) && !(await page.isChecked('#twd-ap-gs-cta-index')) && (await page.isChecked('#twd-ap-gs-cta-articles')));
		await page.fill('#twd-ap-gs-cta-heading', 'Ready to talk?');
		await page.check('#twd-ap-gs-cta-index');
		await page.uncheck('#twd-ap-gs-cta-grid');
		await page.click('#twd-ap-gs-save');
		await wait(300);
		const body = saved[saved.length - 1];
		t('cta setting: saving sends the new values and ticks', body.cta_heading === 'Ready to talk?' && body.cta_text === 'Line one' && body.cta_show_index === true && body.cta_show_grid === false && body.cta_show_articles === true && body.cta_show_categories === false, JSON.stringify(body));
		t('cta setting: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await ctx.close();
		window_cta = {};
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
