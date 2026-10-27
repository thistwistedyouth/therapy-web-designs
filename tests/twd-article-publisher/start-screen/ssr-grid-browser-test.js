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
let window_shape = 'wide';
let window_cta = {};
const introSaves = [];
let introStatus = 200;
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
	if (o.onRequest) { page.on('request', o.onRequest); }
	await page.route('https://example.test/page', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: pageHtml(name, o.js && !o.noScript) }));
	await page.route(REST + '/**', async (route) => {
		const url = route.request().url();
		await wait(o.delay);
		restDone++;
		if (o.fail) { return route.abort(); }
		let body = {};
		if (/\/articles\/grouped/.test(url)) { body = DUMP[name].grouped; }
		else if (/\/articles\/facets/.test(url)) { body = { categories: [{ name: 'Anxiety', slug: 'anxiety' }, { name: 'Sleep', slug: 'sleep' }], tags: [] }; }
		else if (/\/articles\?/.test(url)) { body = o.empty ? { items: [], has_more: false } : DUMP[name].list; }
		else if (/\/grid-settings/.test(url)) { if (o.settingsFail && route.request().method() !== 'POST') { return route.fulfill({ status: 500, contentType: 'application/json', body: '{}' }); } if (route.request().method() === 'POST') { saved.push(JSON.parse(route.request().postData())); return route.fulfill({ status: 200, contentType: 'application/json', body: '{"saved":true}' }); } body = { show_thumbnails: true, thumb_shape: window_shape, read_more_color: '#1d4ed8', accent_color: '#2563eb', total_articles: DUMP[name].total, categories_count: 4, posts_per_category: 6, categories: [{ id: 2, name: 'Anxiety' }, { id: 3, name: 'Sleep' }, { id: 5, name: 'Boundaries' }], topic_category_ids: window_topics, look_text_color: '', look_heading_color: '', look_link_color: '', look_quote_color: '', look_font: 'system', look_size: 'medium', look_spacing: 'normal', related_show: true, related_count: 4, related_topics: true, swipebook_button_position: 'both', cta_heading: '', cta_text: '', cta_button_label: '', cta_button_url: '', cta_show_grid: false, cta_show_categories: false, cta_show_index: false, cta_show_articles: false };
			Object.assign(body, window_cta); }
		if (/\/term-intro/.test(url)) { introSaves.push(JSON.parse(route.request().postData())); return route.fulfill({ status: introStatus, contentType: 'application/json', body: JSON.stringify(introStatus === 200 ? { html: '<p>' + introSaves[introSaves.length - 1].description + '</p>', raw: introSaves[introSaves.length - 1].description } : { code: 'x' }) }); }
		return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
	});
	await page.goto('https://example.test/page');
	return { ctx, page, errors, done: () => restDone };
}

(async () => {
	const browser = await chromium.launch();

	// 1. Small site: nothing flashes or moves when the JS takes over.
	for (const name of ['small', 'large', 'featuredrow']) {
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
		if (name === 'featuredrow') {
			const heads = await page.$$eval('.twd-ap-articles-group-title', (e) => e.map((x) => x.textContent));
			const firstRow = await page.$$eval('.twd-ap-articles-group:first-child a.twd-ap-article-card', (a) => a.map((x) => x.getAttribute('href').replace(/\D/g, '')));
			t('featured row: the page opens with the Featured row, then the categories', heads[0] === 'Featured' && heads.slice(1).join(',') === 'Anxiety,Sleep', heads.join(','));
			t('featured row: its cards are the featured ones in grid-number order, from the server and after the JS', firstRow.join(',') === '24,26,21,28,30,27', firstRow.join(','));
			t('featured row: it has no See all', (await page.locator('.twd-ap-articles-group:first-child .twd-ap-articles-group-more').count()) === 0);
			t('featured row: no article appears twice on the page', await page.$$eval('a.twd-ap-article-card', (a) => { const h = a.map((x) => x.getAttribute('href')); return h.length === new Set(h).size; }));
		}
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

	// 7. The index link is a quiet text link at the end of the Browse by topic row.
	{
		const { ctx, page } = await open(browser, 'indexlink', { js: false });
		const link = page.locator('.twd-ap-articles-topics .twd-ap-topics-all a');
		const style = await link.evaluate((el) => { const c = getComputedStyle(el); return { bg: c.backgroundColor, color: c.color, size: parseFloat(c.fontSize) }; });
		t('index link: a plain grey text link, no button background', style.bg === 'rgba(0, 0, 0, 0)' && style.color === 'rgb(107, 114, 128)' && style.size <= 15, JSON.stringify(style));
		t('index link: it reads All articles and sits on the same row as the topics', (await link.innerText()) === 'All articles' && (await page.locator('.twd-ap-articles-topics li').count()) >= 2);
		const rows = await page.$$eval('.twd-ap-articles-topics li', (l) => new Set(l.map((x) => Math.round(x.getBoundingClientRect().top))).size);
		t('index link: it does not drop onto a line of its own on a desktop', rows === 1, 'rows=' + rows);
		t('index link: there is no pill button on the page', (await page.locator('.twd-ap-articles-index-pill').count()) === 0);
		await ctx.close();
	}

	// 7b. The plugin's own category page, with a theme header and footer around it.
	{
		let r = await open(browser, 'archivepage', { js: false, delay: 50 });
		const bb = async (sel) => (await r.page.locator(sel).first().boundingBox());
		const title = await bb('.twd-ap-archive-title');
		const intro = await bb('.twd-ap-articles-intro');
		const grid = await bb('.twd-ap-articles-grid');
		const wrap = await bb('.twd-ap-archive');
		t('archive page: title, then intro, then cards, in that order down the page', title.y < intro.y && intro.y < grid.y);
		t('archive page: the intro is visible without any setting ticked', (await r.page.locator('.twd-ap-articles-intro-text').innerText()).includes('Anxiety can feel huge.'));
		const topicsBox = await bb('.twd-ap-articles-topics');
		t('archive page: Browse by topic sits under the cards, inside the page width', topicsBox && topicsBox.y > grid.y + grid.height - 1 && topicsBox.x >= wrap.x - 1 && topicsBox.width <= wrap.width + 1, JSON.stringify([topicsBox, grid]));
		const cur = await r.page.locator('.twd-ap-topics-current span').evaluate((el) => { const c = getComputedStyle(el); return { weight: c.fontWeight, tag: el.tagName, href: el.getAttribute('href') }; });
		t('archive page: this category is bold text, not a link', cur.tag === 'SPAN' && cur.href === null && parseInt(cur.weight, 10) >= 700, JSON.stringify(cur));
		t('archive page: the other topics are links', (await r.page.locator('.twd-ap-articles-topics a').count()) >= 1);
		t('archive page: the content is centred and no wider than 1140px', wrap.width <= 1141 && Math.abs((wrap.x + wrap.width / 2) - 550) < 2, JSON.stringify(wrap));
		t('archive page: the heading is large and the three cards sit in one row', (await r.page.locator('.twd-ap-archive-title').evaluate((el) => parseFloat(getComputedStyle(el).fontSize))) >= 34 && (await r.page.$$eval('.twd-ap-article-card', (c) => new Set(c.map((x) => Math.round(x.getBoundingClientRect().top))).size)) === 1);
		await r.ctx.close();
		const phone = await browser.newContext({ viewport: { width: 375, height: 800 }, javaScriptEnabled: false });
		const pp = await phone.newPage();
		await pp.route('https://example.test/page', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: pageHtml('archivepage', false) }));
		await pp.goto('https://example.test/page');
		t('archive page: on a phone nothing scrolls sideways and the title is smaller', !(await pp.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)) && (await pp.locator('.twd-ap-archive-title').evaluate((el) => parseFloat(getComputedStyle(el).fontSize))) === 28);
		await phone.close();
	}

	// 7c. On the plugin's own category page the owner still has the gear, so the switch can always be reached.
	{
		const r = await open(browser, 'archiveadmin', { delay: 50 });
		await wait(500);
		t('archive page owner: the gear is on the page, right aligned, and opens Customise this grid', await r.page.locator('.twd-ap-articles-settings-btn').isVisible());
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		await r.page.click('#twd-ap-gs-intro-details > summary');
		t('archive page owner: the Use this grid on category and tag pages tick is there and on', await r.page.isChecked('#twd-ap-gs-archive-takeover'));
		await r.page.uncheck('#twd-ap-gs-archive-takeover');
		await r.page.click('#twd-ap-gs-save');
		await wait(300);
		t('archive page owner: unticking it from the category page saves it as off', saved[saved.length - 1].archive_takeover === false);
		t('archive page owner: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();
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


	// 14. The category intro: visitors read it, an editor can change it from the page.
	{
		let r = await open(browser, 'intro', { delay: 50 });
		await wait(500);
		const text = await r.page.locator('.twd-ap-articles-intro-text').innerText();
		t('intro: a visitor reads the description above the grid', text.includes('Anxiety can feel huge.') && text.includes('These pieces help.'));
		t('intro: a visitor has no edit button', (await r.page.locator('.twd-ap-intro-edit').count()) === 0);
		const above = (await r.page.locator('.twd-ap-articles-intro').boundingBox()).y < (await r.page.locator('.twd-ap-articles-grid').first().boundingBox()).y;
		t('intro: it sits above the grid and survives the JS taking over', above && r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();

		r = await open(browser, 'introedit', { delay: 50 });
		await wait(500);
		await r.page.click('.twd-ap-intro-edit');
		const ta = r.page.locator('.twd-ap-intro-text');
		t('intro edit: the popup opens with the current text and the category name', (await ta.inputValue()).includes('Anxiety can feel') && (await r.page.locator('#twd-ap-intro-title').innerText()) === 'Intro for Anxiety');
		await ta.fill('A new intro for this topic.');
		await r.page.click('.twd-ap-intro-save');
		await wait(900);
		const sent = introSaves[introSaves.length - 1];
		t('intro edit: saving sends the category, taxonomy and the new text', sent && sent.term_id === 2 && sent.taxonomy === 'category' && sent.description === 'A new intro for this topic.', JSON.stringify(sent));
		t('intro edit: the page shows the new text without a reload and the popup closes', (await r.page.locator('.twd-ap-articles-intro-text').innerText()).includes('A new intro for this topic.') && !(await r.page.locator('.twd-ap-intro-overlay').isVisible()));
		await r.page.click('.twd-ap-intro-edit');
		t('intro edit: reopening shows the saved text', (await r.page.locator('.twd-ap-intro-text').inputValue()) === 'A new intro for this topic.');
		introStatus = 403;
		await r.page.click('.twd-ap-intro-save');
		await wait(500);
		t('intro edit: a refused save says so and keeps the popup open', (await r.page.locator('.twd-ap-intro-status').innerText()).includes('Could not save') && await r.page.locator('.twd-ap-intro-overlay').isVisible());
		introStatus = 200;
		await r.page.keyboard.press('Escape');
		t('intro edit: Escape closes the popup', !(await r.page.locator('.twd-ap-intro-overlay').isVisible()));
		t('intro edit: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();
	}


	// 15. Page links under a long category: real links without JavaScript, Load more with it.
	{
		let r = await open(browser, 'paged1', { js: false });
		const hrefs = await r.page.$$eval('.twd-ap-articles-pager a', (a) => a.map((x) => x.getAttribute('href')));
		t('paging, no JavaScript: the page links are visible real links', (await r.page.locator('.twd-ap-articles-pager').isVisible()) && hrefs.includes('https://example.test/category/anxiety/?twd_page=2') && hrefs.includes('https://example.test/category/anxiety/?twd_page=4'), JSON.stringify(hrefs));
		await r.ctx.close();

		const reqs = [];
		r = await open(browser, 'paged1', { delay: 50 });
		r.page.on('request', (q) => { if (/\/articles\?/.test(q.url())) { reqs.push(q.url()); } });
		await wait(800);
		t('paging, page 1 with JavaScript: the page links hide and Load more takes over', !(await r.page.locator('.twd-ap-articles-pager').isVisible()) && await r.page.locator('.twd-ap-articles-loadmore').isVisible());
		await r.page.click('.twd-ap-articles-loadmore');
		await wait(500);
		t('paging, page 1: Load more asks for the next 9 (offset 9)', reqs.some((u) => /offset=9/.test(u)), JSON.stringify(reqs));
		t('paging, page 1: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();

		reqs.length = 0;
		r = await open(browser, 'paged2', { delay: 50, onRequest: (q) => { if (/\/articles\?/.test(q.url())) { reqs.push(q.url()); } } });
		await wait(800);
		t('paging, page 2 with JavaScript: the first fetch starts at offset 9, not 0', reqs.length > 0 && /offset=9/.test(reqs[0]), JSON.stringify(reqs));
		const visible = await r.page.$$eval('.twd-ap-articles-pager li', (l) => l.filter((x) => x.getBoundingClientRect().height > 0).map((x) => x.className));
		t('paging, page 2: only the Previous link stays visible', JSON.stringify(visible) === JSON.stringify(['twd-ap-pager-prev']), JSON.stringify(visible));
		await r.page.click('.twd-ap-articles-loadmore');
		await wait(500);
		t('paging, page 2: Load more continues from offset 18 and the Previous link goes', reqs.some((u) => /offset=18/.test(u)) && !(await r.page.locator('.twd-ap-articles-pager').isVisible()), JSON.stringify(reqs));
		t('paging, page 2: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();
	}


	// 16. The page links never flash: with JavaScript on but the grid script not yet loaded, only the tiny inline script has run.
	{
		let r = await open(browser, 'paged1', { noScript: true });
		t('paging flash: page 1 links are hidden by the inline script before the grid script runs', !(await r.page.locator('.twd-ap-articles-pager').isVisible()) && (await r.page.locator('.twd-ap-articles-pager').getAttribute('data-js')) === '1');
		await r.ctx.close();
		r = await open(browser, 'paged2', { noScript: true });
		const vis = await r.page.$$eval('.twd-ap-articles-pager li', (l) => l.filter((x) => x.getBoundingClientRect().height > 0).map((x) => x.className));
		t('paging flash: page 2 shows only Previous before the grid script runs', JSON.stringify(vis) === JSON.stringify(['twd-ap-pager-prev']), JSON.stringify(vis));
		await r.ctx.close();
	}

	// 17. The gear popup: Save is off until the settings load, and an older cached popup cannot wipe settings.
	{
		let r = await open(browser, 'admin', { delay: 50, settingsFail: true });
		await r.page.click('.twd-ap-articles-settings-btn');
		await wait(500);
		t('popup load failure: it says so and Save is switched off', (await r.page.locator('#twd-ap-grid-settings-status').innerText()).includes('Could not load') && await r.page.locator('#twd-ap-gs-save').isDisabled());
		await r.ctx.close();

		window_cta = { cta_heading: 'Keep me', cta_show_grid: true };
		r = await open(browser, 'admin', { delay: 50 });
		await r.page.evaluate(() => { ['#twd-ap-gs-cta-details', '#twd-ap-gs-look-details', '#twd-ap-gs-intro-details', '#twd-ap-gs-topics'].forEach((sel) => { const el = document.querySelector(sel); if (el) { el.remove(); } }); });
		const before = saved.length;
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		await r.page.click('#twd-ap-gs-save');
		await wait(300);
		const sent = saved[saved.length - 1];
		t('older cached popup: saving sends none of the sections it does not have, so nothing is cleared', saved.length === before + 1 && !('cta_heading' in sent) && !('look_text_color' in sent) && !('intro_show' in sent) && !('topic_category_ids' in sent) && 'categories_count' in sent, JSON.stringify(Object.keys(sent)));
		t('older cached popup: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();
		window_cta = {};

		r = await open(browser, 'admin', { delay: 50 });
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		t('popup: the category intro tick starts off (the intro is opt in)', !(await r.page.isChecked('#twd-ap-gs-intro-show')));
		await r.page.click('#twd-ap-gs-intro-details > summary');
		await r.page.check('#twd-ap-gs-intro-show');
		await r.page.click('#twd-ap-gs-save');
		await wait(300);
		t('popup: ticking the intro saves intro_show true', saved[saved.length - 1].intro_show === true);
		t('popup: the category pages option is on to start with (it works out of the box)', (await r.page.isChecked('#twd-ap-gs-archive-takeover')) && saved[saved.length - 1].archive_takeover === true);
		await wait(1000);
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		await r.page.uncheck('#twd-ap-gs-archive-takeover');
		await r.page.click('#twd-ap-gs-save');
		await wait(300);
		t('popup: unticking the category pages option saves it as off', saved[saved.length - 1].archive_takeover === false);
		await r.ctx.close();
	}

	// 19. Card image shape: measured in real Chromium, desktop and phone.
	{
		// Measured once the page has loaded and a picture is really on screen, not while it is still being parsed.
		const shapes = async (page) => { await page.waitForLoadState('load'); await page.waitForSelector('.twd-ap-article-thumb', { state: 'visible' }); return page.$$eval('.twd-ap-article-thumb', (els) => els.filter((e) => e.getBoundingClientRect().width > 0).map((e) => { const r = e.getBoundingClientRect(); return { ratio: r.width / r.height, h: r.height, w: r.width, ph: e.classList.contains('twd-ap-article-thumb-placeholder') }; })); };
		const near = (list, want) => list.length > 0 && list.every((x) => Math.abs(x.ratio - want) < 0.02);

		window_shape = 'wide';
		let r = await open(browser, 'small', { delay: 50 });
		await r.page.waitForSelector('.twd-ap-article-card');
		let s = await shapes(r.page);
		t('shape wide (default): every picture is still the 170px strip', s.length >= 5 && s.every((x) => Math.abs(x.h - 170) < 0.5), JSON.stringify(s.map((x) => x.h)));
		t('shape wide (default): the root has no shape class', (await r.page.locator('.twd-ap-articles').getAttribute('class')) === 'twd-ap-articles');
		await r.ctx.close();

		for (const [name, want, label, saved_shape] of [['shape43', 4 / 3, '4:3', '4-3'], ['shapesq', 1, 'square', 'square']]) {
			window_shape = saved_shape; // the real REST route answers with what is saved, which is what the page was built from
			r = await open(browser, name, { delay: 50 });
			await r.page.waitForSelector('.twd-ap-article-card');
			s = await shapes(r.page);
			t('shape ' + label + ': every picture and placeholder is the shape before the script runs', near(s, want) && s.some((x) => x.ph) && s.some((x) => !x.ph), JSON.stringify(s.map((x) => x.ratio.toFixed(2))));
			const first = await r.page.locator('.twd-ap-article-card').first().boundingBox();
			await wait(600);
			s = await shapes(r.page);
			const firstAfter = await r.page.locator('.twd-ap-article-card').first().boundingBox();
			t('shape ' + label + ': still the shape after the script swaps the cards in, and the first card did not move', near(s, want) && JSON.stringify(first) === JSON.stringify(firstAfter));
			// Skeleton cards while a search runs take the same shape.
			await r.page.route(/\/articles\?/, async (route) => { await wait(1500); route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [], has_more: false }) }); });
			await r.page.fill('.twd-ap-articles-search', 'calm');
			await r.page.waitForSelector('.twd-ap-skeleton-card');
			const sk = await r.page.$$eval('.twd-ap-skeleton-block', (els) => els.map((e) => { const b = e.getBoundingClientRect(); return b.width / b.height; }));
			t('shape ' + label + ': the loading placeholders are the shape too', sk.length > 0 && sk.every((x) => Math.abs(x - want) < 0.02), JSON.stringify(sk));
			await r.page.setViewportSize({ width: 390, height: 800 });
			await r.page.reload();
			await r.page.waitForSelector('.twd-ap-article-card');
			s = await shapes(r.page);
			t('shape ' + label + ': the same on a phone, with no sideways scroll', near(s, want) && await r.page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
			t('shape ' + label + ': no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
			await r.ctx.close();
		}

		// A single article keeps its image-beside-text card on a wide screen and takes the shape on a phone.
		for (const [name, want, label, saved_shape] of [['singlesq', 1, 'square', 'square'], ['single43', 4 / 3, '4:3', '4-3']]) {
			window_shape = saved_shape;
			r = await open(browser, name, { delay: 50 });
			await r.page.waitForSelector('.twd-ap-article-card');
			const lone = await r.page.evaluate(() => { const th = document.querySelector('.twd-ap-article-thumb'); const body = document.querySelector('.twd-ap-article-body'); const a = th.getBoundingClientRect(); const b = body.getBoundingClientRect(); const c = th.closest('.twd-ap-article-card').getBoundingClientRect(); return { ar: getComputedStyle(th).aspectRatio, beside: a.right <= b.left + 1, share: a.width / c.width, h: a.height }; });
			t('single article, ' + label + ', wide screen: image beside the text, about 40% wide, not forced into the shape', lone.beside && lone.ar === 'auto' && Math.abs(lone.share - 0.4) < 0.03 && lone.h >= 220, JSON.stringify(lone));
			await r.page.setViewportSize({ width: 390, height: 800 });
			await r.page.reload();
			await r.page.waitForSelector('.twd-ap-article-card');
			s = await shapes(r.page);
			t('single article, ' + label + ', phone: the picture takes the shape', near(s, want), JSON.stringify(s.map((x) => x.ratio.toFixed(2))));
			await r.ctx.close();
		}

		// The grouped view of a large site.
		window_shape = 'square';
		r = await open(browser, 'largesq', { delay: 50 });
		await r.page.waitForSelector('.twd-ap-article-card');
		s = await shapes(r.page);
		t('shape square: the grouped view of a large site is square too', near(s, 1), JSON.stringify(s.map((x) => x.ratio.toFixed(2))));
		await r.ctx.close();

		// The gear popup: the choice loads, saves, and the page changes without a reload.
		window_shape = '4-3';
		r = await open(browser, 'adminmulti', { delay: 50 });
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		t('popup: the card image shape field starts on what is saved', (await r.page.inputValue('#twd-ap-gs-thumb-shape')) === '4-3' && (await r.page.locator('#twd-ap-gs-thumb-shape option').allInnerTexts()).join('|') === 'Wide strip (the original)|4:3 (landscape)|Square');
		window_shape = 'square';
		await r.page.selectOption('#twd-ap-gs-thumb-shape', 'square');
		await r.page.click('#twd-ap-gs-save');
		await wait(900);
		t('popup: saving sends the chosen shape', saved[saved.length - 1].thumb_shape === 'square');
		t('popup: the grid takes the new shape at once, without a reload', (await r.page.locator('.twd-ap-articles').getAttribute('class')).indexOf('twd-ap-thumbs-square') !== -1 && near(await shapes(r.page), 1));
		window_shape = 'wide';
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		await r.page.selectOption('#twd-ap-gs-thumb-shape', 'wide');
		await r.page.click('#twd-ap-gs-save');
		await wait(900);
		t('popup: going back to wide removes the shape class', (await r.page.locator('.twd-ap-articles').getAttribute('class')).indexOf('twd-ap-thumbs-') === -1 && (await shapes(r.page)).every((x) => Math.abs(x.h - 170) < 0.5));
		t('popup: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();

		// An older cached popup (no shape field) cannot change the setting.
		window_shape = 'square';
		r = await open(browser, 'admin', { delay: 50 });
		await r.page.evaluate(() => { const el = document.querySelector('#twd-ap-gs-thumb-shape'); if (el) { el.remove(); } });
		const before2 = saved.length;
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		await r.page.click('#twd-ap-gs-save');
		await wait(400);
		t('older cached popup: saving does not send a shape', saved.length === before2 + 1 && !('thumb_shape' in saved[saved.length - 1]));
		await r.ctx.close();
		window_shape = 'wide';
	}

	// 18. The gear popup: Related articles tick and count.
	{
		const r = await open(browser, 'admin', { delay: 50 });
		await r.page.click('.twd-ap-articles-settings-btn');
		await r.page.waitForSelector('#twd-ap-gs-save:not([disabled])');
		await r.page.click('#twd-ap-gs-related-details > summary');
		t('related setting: on to start with, showing 4, with the topic links under it', (await r.page.isChecked('#twd-ap-gs-related-show')) && (await r.page.inputValue('#twd-ap-gs-related-count')) === '4' && (await r.page.isChecked('#twd-ap-gs-related-topics')));
		t('swipe button setting: starts as under the title and at the end', (await r.page.inputValue('#twd-ap-gs-sb-position')) === 'both');
		await r.page.selectOption('#twd-ap-gs-sb-position', 'top');
		await r.page.uncheck('#twd-ap-gs-related-topics');
		await r.page.fill('#twd-ap-gs-related-count', '6');
		await r.page.uncheck('#twd-ap-gs-related-show');
		await r.page.click('#twd-ap-gs-save');
		await wait(300);
		const body = saved[saved.length - 1];
		t('related setting: saving sends the tick, the count, the topic links tick and the button position', body.related_show === false && body.related_count === 6 && body.related_topics === false && body.swipebook_button_position === 'top', JSON.stringify([body.related_show, body.related_count, body.related_topics, body.swipebook_button_position]));
		t('related setting: no JavaScript errors', r.errors.length === 0, r.errors.join(' | '));
		await r.ctx.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
