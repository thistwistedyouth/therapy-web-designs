// Real-browser test of the edit pencil on article grid cards. Real template, CSS and JS; only server replies are faked.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/grid-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-grid-'));
const REST = 'https://example.test/wp-json/twd-publisher/v1';
let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }
const vis = (page, sel) => page.locator(sel).isVisible();

const ITEMS = [
	{ id: 11, title: 'Article eleven', excerpt: 'First', link: 'https://example.test/a/11/', date: '1 Oct 2026', reading_time: 3, thumbnail: 'data:image/gif;base64,R0lGODlhAQABAAAAACw=', category: 'Anxiety', featured: false, can_edit: true },
	{ id: 12, title: 'Article twelve', excerpt: 'Second', link: 'https://example.test/a/12/', date: '2 Oct 2026', reading_time: 4, thumbnail: '', category: 'Anxiety', featured: false, can_edit: false },
	{ id: 13, title: 'Article thirteen', excerpt: 'Third', link: 'https://example.test/a/13/', date: '3 Oct 2026', reading_time: 5, thumbnail: '', category: 'Sleep', featured: false, can_edit: true },
];

async function open(browser, o) {
	o = Object.assign({ loggedIn: true, grouped: false, thumbs: true, items: ITEMS, viewport: { width: 1100, height: 900 }, firstStatus: 200 }, o);
	const ctx = await browser.newContext({ viewport: o.viewport });
	const page = await ctx.newPage();
	const log = { list: [], posts: [], deletes: [], errors: [] };
	page.on('pageerror', (e) => log.errors.push(String(e)));
	let listCalls = 0;
	await page.route('https://example.test/a/**', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body><h1 id="article-page">ARTICLE PAGE</h1></body></html>' }));
	await page.route(REST + '/**', async (route) => {
		const req = route.request();
		const url = req.url().replace(REST, '');
		const json = (status, body) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
		if (url.startsWith('/articles/grouped') || (url.startsWith('/articles?'))) {
			listCalls++;
			log.list.push({ url, nonce: req.headers()['x-wp-nonce'] || null });
			if (listCalls === 1 && o.firstStatus !== 200) { return json(o.firstStatus, { code: 'rest_cookie_invalid_nonce' }); }
			if (url.startsWith('/articles/grouped')) { return json(200, { groups: [{ category: { name: 'Anxiety', slug: 'anxiety' }, total: o.items.length, posts: o.items }] }); }
			return json(200, { items: o.items, has_more: false });
		}
		if (url.startsWith('/articles/facets')) { return json(200, { categories: [] }); }
		if (url.startsWith('/grid-settings')) { return json(200, { show_thumbnails: o.thumbs }); }
		if (req.method() === 'DELETE') { log.deletes.push(url); return json(200, { deleted: true }); }
		if (req.method() === 'POST' && /^\/posts\/\d+$/.test(url)) { log.posts.push({ url, body: JSON.parse(req.postData() || '{}') }); return json(200, { id: parseInt(url.split('/')[2], 10), status: 'publish', link: 'https://example.test/a/x/' }); }
		if (url.startsWith('/posts/')) {
			const id = parseInt(url.split('/')[2], 10);
			return json(200, { id, title: 'Article ' + id, status: 'publish', link: 'https://example.test/a/' + id + '/', edit_url: 'https://example.test/wp-admin/post.php?post=' + id + '&action=edit', content_html: '<p>Body ' + id + '</p>', category_ids: [], tags: '', excerpt: 'x', featured_media: 0, featured_media_url: '' });
		}
		return json(200, []);
	});
	const popup = path.join(tmp, 'popup.html');
	execFileSync('php', [path.join(__dirname, 'render.php'), 'key-off', popup]);
	await page.goto('file://' + popup);
	const config = { restUrl: REST, nonce: 'abc', category: '', tag: '', count: 9, showFilters: o.grouped, isAdmin: o.loggedIn, manyArticles: o.grouped };
	await page.evaluate((a) => {
		const wrap = document.createElement('div');
		wrap.innerHTML = '<div id="twd-ap-grid-1" class="twd-ap-articles" data-config=\'' + JSON.stringify(a.config) + '\'>' +
			(a.config.showFilters ? '<div class="twd-ap-articles-toolbar"><select class="twd-ap-articles-category-select"></select><input type="search" class="twd-ap-articles-search"></div>' : '') +
			'<div class="twd-ap-articles-groups"></div><div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: 3;"><p>Loading</p></div>' +
			'<p class="twd-ap-articles-empty" hidden>None</p><div class="twd-ap-articles-loadmore-row"><button class="twd-ap-articles-loadmore" hidden>Load more</button></div></div>';
		document.body.appendChild(wrap);
		window.__stay = true;
		window.confirm = () => true;
		if (a.loggedIn) { window.TWD_AP = { restUrl: a.config.restUrl, nonce: 'abc', yoastEnabled: 0, currentPostId: 0, aiKeyConfigured: 0, i18n: { saving: 'Saving', error: 'Error', confirmClose: 'Discard?', confirmDelete: 'Delete?' } }; }
	}, { config, loggedIn: o.loggedIn });
	await page.addStyleTag({ path: path.join(plugin, 'assets/articles-grid.css') });
	if (o.loggedIn) { await page.addScriptTag({ path: path.join(plugin, 'assets/publisher.js') }); }
	await page.addScriptTag({ path: path.join(plugin, 'assets/articles-grid.js') });
	await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
	await page.waitForSelector('.twd-ap-article-card:not(.twd-ap-skeleton-card)');
	return { ctx, page, log };
}
async function reloaded(page, ms) { await page.waitForTimeout(ms || 1800); return (await page.evaluate(() => window.__stay)) !== true; }

(async () => {
	const browser = await chromium.launch({ executablePath: fs.existsSync('/opt/pw-browsers/chromium-1194/chrome-linux/chrome') ? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' : undefined });

	// ---- G1: flat grid, logged in
	let s = await open(browser, {}); let page = s.page;
	t('G1: list request carried the REST nonce for a logged-in editor', s.log.list.length === 1 && s.log.list[0].nonce === 'abc');
	t('G1: pencil on the two cards the person can edit, none on the third', (await page.locator('.twd-ap-card-edit').count()) === 2 && (await page.locator('.twd-ap-card-edit').evaluateAll((e) => e.map((x) => x.getAttribute('data-edit-id')))).join() === '11,13');
	t('G1: no pencil on the card they cannot edit', (await page.locator('.twd-ap-article-card', { hasText: 'Article twelve' }).locator('.twd-ap-card-edit').count()) === 0);
	const card = await page.locator('.twd-ap-article-card', { hasText: 'Article eleven' }).boundingBox();
	const pb = await page.locator('.twd-ap-card-edit').first().boundingBox();
	t('G1: pencil sits top left of the card, 10px in', Math.abs(pb.x - card.x - 11) <= 2 && Math.abs(pb.y - card.y - 11) <= 2, JSON.stringify({ pb, card }));
	t('G1: pencil is a 34px circle with an icon', Math.round(pb.width) === 34 && Math.round(pb.height) === 34 && (await page.locator('.twd-ap-card-edit').first().evaluate((e) => getComputedStyle(e).borderRadius)) === '50%' && (await page.locator('.twd-ap-card-edit svg').count()) === 2);
	t('G1: labelled for screen readers', (await page.locator('.twd-ap-card-edit').first().getAttribute('aria-label')) === 'Edit this article' && (await page.locator('.twd-ap-card-edit').first().getAttribute('role')) === 'button');
	await page.screenshot({ path: path.join(tmp, 'grid-pencils.png') });
	const urlBefore = page.url();
	await page.locator('.twd-ap-card-edit').first().click();
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Article 11');
	t('G1: clicking the pencil does NOT follow the card link', page.url() === urlBefore && !(await page.locator('#article-page').count()));
	t('G1: it opens the edit popup for that article', await vis(page, '#twd-ap-title') && (await page.locator('#twd-ap-visual-editor').innerText()).includes('Body 11') && await vis(page, '#twd-ap-delete-btn'));
	t('G1: the "Edit in WordPress" link points at THAT article', (await page.locator('#twd-ap-admin-edit-link').getAttribute('href')) === 'https://example.test/wp-admin/post.php?post=11&action=edit' && await vis(page, '#twd-ap-admin-edit-link'));
	await page.click('#twd-ap-publish-btn');
	for (let k = 0; k < 40 && !s.log.posts.length; k++) { await page.waitForTimeout(50); }
	t('G1: Update from a grid card saves that article', s.log.posts.length === 1 && s.log.posts[0].url === '/posts/11');
	t('G1: and the popup closes and the grid page refreshes', await reloaded(page, 2200));
	await s.ctx.close();

	// ---- G1b: the card itself still works as a link; keyboard; delete
	s = await open(browser, {}); page = s.page;
	await page.locator('.twd-ap-article-card', { hasText: 'Article twelve' }).locator('.twd-ap-article-title').click();
	await page.waitForSelector('#article-page');
	t('G1b: clicking the rest of a card still opens the article', page.url() === 'https://example.test/a/12/');
	await s.ctx.close();
	s = await open(browser, {}); page = s.page;
	await page.locator('.twd-ap-card-edit').nth(1).focus();
	await page.keyboard.press('Enter');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Article 13');
	t('G1b: keyboard: Enter on a focused pencil opens the editor', await vis(page, '#twd-ap-title'));
	await page.click('#twd-ap-delete-btn');
	for (let k = 0; k < 40 && !s.log.deletes.length; k++) { await page.waitForTimeout(50); }
	t('G1b: Delete from a grid card deletes that article', s.log.deletes.join() === '/posts/13');
	t('G1b: and the grid page refreshes so the card is gone', await reloaded(page, 2000));
	await s.ctx.close();
	s = await open(browser, {}); page = s.page;
	await page.locator('.twd-ap-card-edit').first().focus();
	await page.keyboard.press(' ');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Article 11');
	t('G1b: keyboard: Space also opens it', true);
	await page.click('#twd-ap-close-btn');
	t('G1b: closing without saving does not refresh', !(await reloaded(page, 900)));
	t('G1: no JavaScript errors on the grid page', s.log.errors.length === 0, s.log.errors.join(' | '));
	await s.ctx.close();

	// ---- G2: grouped view
	s = await open(browser, { grouped: true }); page = s.page;
	t('G2: grouped view uses the grouped feed with the nonce', s.log.list[0].url.startsWith('/articles/grouped') && s.log.list[0].nonce === 'abc');
	t('G2: pencils show in grouped sections too', (await page.locator('.twd-ap-articles-group .twd-ap-card-edit').count()) === 2);
	t('G2: no JavaScript errors', s.log.errors.length === 0, s.log.errors.join(' | '));
	await s.ctx.close();

	// ---- G3: stale nonce falls back and the grid still loads
	s = await open(browser, { firstStatus: 403, items: ITEMS.map((i) => Object.assign({}, i, { can_edit: false })) }); page = s.page;
	t('G3: a rejected nonce is retried once without it', s.log.list.length === 2 && s.log.list[0].nonce === 'abc' && s.log.list[1].nonce === null);
	t('G3: the grid still loads all cards, just without pencils', (await page.locator('.twd-ap-article-card').count()) === 3 && (await page.locator('.twd-ap-card-edit').count()) === 0);
	await s.ctx.close();

	// ---- G4: visitors and logged-out people
	s = await open(browser, { loggedIn: false, items: ITEMS.map((i) => Object.assign({}, i, { can_edit: true })) }); page = s.page;
	t('G4: a visitor sends no nonce', s.log.list.length === 1 && s.log.list[0].nonce === null);
	t('G4: no pencil for a visitor even if the data said can_edit (no editor on the page)', (await page.locator('.twd-ap-card-edit').count()) === 0 && (await page.locator('.twd-ap-article-card').count()) === 3);
	await s.ctx.close();

	// ---- G5: thumbnails off, phone
	s = await open(browser, { thumbs: false }); page = s.page;
	await page.waitForFunction(() => document.querySelector('.twd-ap-articles').classList.contains('twd-ap-no-thumbnails'));
	const c2 = await page.locator('.twd-ap-article-card', { hasText: 'Article eleven' }).boundingBox();
	const p2 = await page.locator('.twd-ap-card-edit').first().boundingBox();
	t('G5: with thumbnails off the pencil moves to the top right', Math.abs((c2.x + c2.width) - (p2.x + p2.width) - 11) <= 2, JSON.stringify({ c2, p2 }));
	await s.ctx.close();
	s = await open(browser, { viewport: { width: 390, height: 800 } }); page = s.page;
	const boxes = await page.evaluate(() => Array.from(document.querySelectorAll('.twd-ap-card-edit')).map((e) => { const r = e.getBoundingClientRect(); const c = e.closest('.twd-ap-article-card').getBoundingClientRect(); return r.left >= c.left && r.right <= c.right && r.top >= c.top && r.right <= window.innerWidth; }));
	t('G5: on a phone the pencil sits inside its card and the screen', boxes.length === 2 && boxes.every(Boolean), JSON.stringify(boxes));
	await page.screenshot({ path: path.join(tmp, 'grid-phone.png') });
	await s.ctx.close();

	await browser.close();
	console.log('\nScreenshots: ' + tmp);
	console.log(pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
