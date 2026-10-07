// Real-browser test of the Summary Book review screen and the book viewer: styled remove crosses,
// publish then close and show the book, and the "View Summary Book" button on a swipe book's last page.
// Real template, CSS and JS; only server replies are faked.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/summary-book-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-sbk-'));
const REST = 'https://example.test/wp-json/twd-publisher/v1';
let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }
const vis = (page, sel) => page.locator(sel).isVisible();

const CARDS = [
	{ type: 'text', heading: 'Build other anchors', text: 'Sports, creative pursuits and hobbies give teens proof of who they are.' },
	{ type: 'quote', heading: '', text: 'Being listened to is a form of validation.', attribution: '' },
	{ type: 'question', heading: '', text: 'When did you last ask your teen about something they care about?' },
];
const base = { siteName: 'Test Site', articleUrl: 'https://example.test/a/5/', shareUrl: 'https://example.test/a/5/?twd_ap_summary_book=1', title: 'Article five', includeBranding: true };
const SUMMARY = Object.assign({}, base, { label: 'Summary Book', slides: [
	{ type: 'text', heading: 'Build other anchors', html: '<p>Sports and hobbies.</p>' },
	{ type: 'quote', html: 'Being listened to.', attribution: '' },
	{ type: 'question', html: '<p>When did you last ask?</p>' },
], companionUrl: 'https://example.test/a/5/?twd_ap_swipebook=1', companionLabel: 'Back to swipe book', companionPostId: 5, companionBookVariant: 'swipebook' });
const SWIPE = (companion) => Object.assign({}, base, { label: 'Swipe Book', shareUrl: 'https://example.test/a/5/?twd_ap_swipebook=1', slides: [
	{ type: 'text', heading: 'First', html: '<p>One.</p>' },
	{ type: 'text', heading: 'Second', html: '<p>Two.</p>' },
	{ type: 'related', heading: 'More Articles', items: [{ id: 9, title: 'Another article', shareUrl: 'https://example.test/a/9/?twd_ap_swipebook=1' }] },
] }, companion ? { companionUrl: 'https://example.test/a/5/?twd_ap_summary_book=1', companionLabel: 'View Summary Book', companionPostId: 5, companionBookVariant: 'summary-book' } : {});

async function open(browser, o) {
	o = Object.assign({ currentPostId: 5, summaryStatus: 200, viewport: { width: 1100, height: 900 }, withInline: false, swipeCompanion: true }, o);
	const ctx = await browser.newContext({ viewport: o.viewport });
	const page = await ctx.newPage();
	const log = { publish: [], errors: [], summaryFetches: 0, fetched9: 0 };
	page.on('pageerror', (e) => log.errors.push(String(e)));
	await page.route('https://example.test/a/**', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body><h1 id="standalone">STANDALONE ' + route.request().url() + '</h1></body></html>' }));
	await page.route(REST + '/**', async (route) => {
		const req = route.request();
		const url = req.url().replace(REST, '');
		const json = (status, body) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
		if (req.method() === 'POST' && url === '/posts/5/summary-book/publish') { log.publish.push(JSON.parse(req.postData() || '{}')); return json(200, { published: true }); }
		if (url === '/posts/5/summary-book') { return json(200, { draft: CARDS, published: [] }); }
		if (url === '/articles/5/summary-book') { log.summaryFetches++; return o.summaryStatus === 200 ? json(200, SUMMARY) : json(o.summaryStatus, { code: 'twd_ap_not_found', message: 'not found' }); }
		if (url === '/articles/9/swipebook') { log.fetched9++; return json(200, SWIPE(false)); }
		if (url === '/articles/5/swipebook') { return json(200, SWIPE(o.swipeCompanion)); }
		if (url.startsWith('/posts/')) { return json(200, { id: 5, title: 'Article five', status: 'publish', link: 'https://example.test/a/5/', edit_url: '', content_html: '<p>Body</p>', category_ids: [], tags: '', excerpt: 'x', featured_media: 0, featured_media_url: '', summary_book_has_draft: true, summary_book_is_published: false }); }
		return json(200, []);
	});
	const popup = path.join(tmp, 'popup.html');
	execFileSync('php', [path.join(__dirname, 'render.php'), 'key-off', popup]);
	await page.goto('file://' + popup);
	await page.evaluate((a) => {
		window.__stay = true; window.confirm = () => true;
		window.TWD_AP = { restUrl: a.rest, nonce: 'n', yoastEnabled: 0, currentPostId: a.cur, canEditThis: 1, adminEditUrl: '', aiKeyConfigured: 0, i18n: { saving: 'Saving', error: 'Error', confirmClose: 'Discard?', confirmDelete: 'Delete?' } };
		window.TWD_AP_SB = { restUrl: a.rest };
	}, { rest: REST, cur: o.currentPostId });
	await page.addStyleTag({ path: path.join(plugin, 'assets/swipebook.css') });
	await page.addScriptTag({ path: path.join(plugin, 'assets/publisher.js') });
	await page.addScriptTag({ path: path.join(plugin, 'assets/swipebook.js') });
	await page.addScriptTag({ path: path.join(plugin, 'assets/swipebook-inline.js') });
	await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
	return { ctx, page, log };
}
async function openEditor(page, fromGrid) {
	// On the resources grid there is no page-level pencil: the editor is opened from a card.
	if (fromGrid) { await page.evaluate(() => window.TWD_AP_OpenEditor(5)); } else { await page.click('#twd-ap-edit-btn'); }
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Article five');
	await page.click('#twd-ap-summary-book-btn');
	await page.waitForSelector('.twd-ap-sb-card-row');
}
async function reloaded(page, ms) { await page.waitForTimeout(ms || 1200); return (await page.evaluate(() => window.__stay)) !== true; }

(async () => {
	const browser = await chromium.launch({ executablePath: fs.existsSync('/opt/pw-browsers/chromium-1194/chrome-linux/chrome') ? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' : undefined });

	// ---- S1: the remove crosses
	let s = await open(browser, {}); let page = s.page;
	await openEditor(page);
	const n = await page.locator('.twd-ap-sb-card-remove').count();
	t('S1: one remove button per card', n === 3);
	const rb = await page.locator('.twd-ap-sb-card-remove').first().boundingBox();
	t('S1: remove buttons are 34px circles', Math.round(rb.width) === 34 && Math.round(rb.height) === 34 && (await page.locator('.twd-ap-sb-card-remove').first().evaluate((e) => getComputedStyle(e).borderRadius)) === '50%');
	t('S1: they are an icon with a label, not a text cross', (await page.locator('.twd-ap-sb-card-remove svg').count()) === 3 && (await page.locator('.twd-ap-sb-card-remove').first().innerText()).trim() === '' && (await page.locator('.twd-ap-sb-card-remove').first().getAttribute('aria-label')) === 'Remove this card');
	t('S1: neutral grey, not pink, until hovered', (await page.locator('.twd-ap-sb-card-remove').first().evaluate((e) => getComputedStyle(e).color)) === 'rgb(131, 128, 111)');
	await page.locator('.twd-ap-sb-card-remove').first().hover();
	await page.waitForTimeout(400);
	t('S1: hover turns it a clear red', (await page.locator('.twd-ap-sb-card-remove').first().evaluate((e) => getComputedStyle(e).color)) === 'rgb(179, 69, 46)');
	await page.screenshot({ path: path.join(tmp, 'crosses.png') });
	await page.locator('.twd-ap-sb-card-remove').nth(1).click();
	t('S1: clicking one removes just that card', (await page.locator('.twd-ap-sb-card-row').count()) === 2);
	await s.ctx.close();
	// against a theme that gives every button a pink border and a wide minimum width
	s = await open(browser, {}); page = s.page;
	await page.addStyleTag({ content: '.twd-ap-modal button, #twd-ap-summary-book-modal button { border: 2px solid hotpink !important; color: hotpink !important; min-width: 120px !important; padding: 8px 20px !important; }' });
	await openEditor(page);
	const hb = await page.locator('.twd-ap-sb-card-remove').first().boundingBox();
	t('S1: still a 34px circle against a theme that restyles every button', Math.round(hb.width) === 34 && Math.round(hb.height) === 34);
	t('S1: and not pink', (await page.locator('.twd-ap-sb-card-remove').first().evaluate((e) => getComputedStyle(e).borderColor)) !== 'rgb(255, 105, 180)');
	await s.ctx.close();

	// ---- S2: publish closes everything and shows the book
	s = await open(browser, {}); page = s.page;
	await openEditor(page);
	await page.click('#twd-ap-summary-book-publish-btn');
	await page.waitForSelector('.twd-sb-backdrop');
	t('S2: publish sent the cards', s.log.publish.length === 1 && s.log.publish[0].cards.length === 3);
	t('S2: the review window is closed', !(await vis(page, '#twd-ap-summary-book-overlay')));
	t('S2: the main popup is closed too', !(await vis(page, '#twd-ap-overlay')));
	const closeBox = await page.locator('.twd-sb-close').boundingBox();
	t('S2: the book close button is really clickable at its centre (nothing paints over it)', await page.evaluate((b) => { const el = document.elementFromPoint(b.x + b.width / 2, b.y + b.height / 2); return !!(el && el.closest('.twd-sb-close')); }, closeBox));
	t('S2: the finished Summary Book is showing', await vis(page, '.twd-sb-backdrop') && (await page.locator('.twd-sb-title').first().innerText()).toLowerCase().includes('summary book'));
	t('S2: it was fetched from the public feed visitors use', s.log.summaryFetches === 1);
	await page.screenshot({ path: path.join(tmp, 'book-shown.png') });
	t('S2: the page behind has not been reloaded while the book is open', (await page.evaluate(() => window.__stay)) === true);
	await page.click('.twd-sb-close');
	t('S2: closing the book on the article own page refreshes it (so the View Summary Book button appears)', await reloaded(page, 1300));
	t('S2: no JavaScript errors', s.log.errors.length === 0, s.log.errors.join(' | '));
	await s.ctx.close();

	// ---- S3: from the grid (a page that is not the article)
	s = await open(browser, { currentPostId: 0 }); page = s.page;
	await openEditor(page, true);
	await page.click('#twd-ap-summary-book-publish-btn');
	await page.waitForSelector('.twd-sb-backdrop');
	await page.click('.twd-sb-close');
	t('S3: from another page the book shows, and closing it does not reload that page', !(await reloaded(page, 1100)));
	await s.ctx.close();

	// ---- S4: swipe books switched off: clear reason, nothing closes
	s = await open(browser, { summaryStatus: 404 }); page = s.page;
	await openEditor(page);
	await page.click('#twd-ap-summary-book-publish-btn');
	await page.waitForFunction(() => /switched off/.test(document.getElementById('twd-ap-summary-book-status').textContent));
	t('S4: explains swipe books are switched off for this article', (await page.locator('#twd-ap-summary-book-status').innerText()).includes('Offer a swipe book'));
	t('S4: the review window stays open and no book appears', await vis(page, '#twd-ap-summary-book-overlay') && !(await vis(page, '.twd-sb-backdrop')));
	await s.ctx.close();

	// ---- S6: pressing a link on a page works, and swiping still turns pages
	s = await open(browser, {}); page = s.page;
	await page.evaluate(() => fetch('https://example.test/wp-json/twd-publisher/v1/articles/5/swipebook').then((r) => r.json()).then((d) => window.TWD_AP_SwipeBook.open(d)));
	await page.waitForSelector('.twd-sb-backdrop');
	await page.waitForTimeout(700);
	const counter = () => page.locator('.twd-sb-counter').innerText();
	t('S6: starts on page 1', (await counter()).replace(/\s/g, '') === '1/3');
	await page.mouse.move(550, 400); await page.mouse.down(); await page.mouse.move(430, 405, { steps: 6 }); await page.mouse.move(300, 410, { steps: 6 }); await page.mouse.up();
	await page.waitForTimeout(700);
	t('S6: a sideways swipe with the mouse still turns the page', (await counter()).replace(/\s/g, '') === '2/3');
	await page.mouse.move(550, 400); await page.mouse.down(); await page.mouse.up();
	await page.waitForTimeout(300);
	t('S6: a plain click on the page does not turn it', (await counter()).replace(/\s/g, '') === '2/3');
	await page.click('.twd-sb-next');
	await page.waitForTimeout(900);
	await page.click('.twd-sb-related-item');
	for (let k = 0; k < 40 && !s.log.fetched9; k++) { await page.waitForTimeout(50); }
	t('S6: the existing "More Articles" links now respond to a click too', s.log.fetched9 === 1);
	await s.ctx.close();

	// ---- S5: the button on the last page of a swipe book
	s = await open(browser, {}); page = s.page;
	await page.evaluate(() => fetch('https://example.test/wp-json/twd-publisher/v1/articles/5/swipebook').then((r) => r.json()).then((d) => window.TWD_AP_SwipeBook.open(d)));
	await page.waitForSelector('.twd-sb-backdrop');
	t('S5: not on the first page', (await page.locator('.twd-sb-slide.is-active .twd-sb-companion-cta').count()) === 0);
	t('S5: exactly one big button in the whole book', (await page.locator('.twd-sb-companion-cta').count()) === 1);
	await page.click('.twd-sb-next'); await page.click('.twd-sb-next');
	await page.waitForSelector('.twd-sb-slide.is-active .twd-sb-companion-cta');
	await page.waitForTimeout(900); // let the page-turn animation finish before looking at it or clicking
	t('S5: it shows on the last page, under More Articles', (await page.locator('.twd-sb-slide.is-active .twd-sb-related-item').count()) === 1 && (await page.locator('.twd-sb-slide.is-active .twd-sb-companion-cta').innerText()).includes('View Summary Book'));
	t('S5: it is a proper button, not the small footer text', (await page.locator('.twd-sb-companion-cta').evaluate((e) => getComputedStyle(e).backgroundColor)) === 'rgb(255, 209, 102)' && (await page.locator('.twd-sb-companion-cta').boundingBox()).height >= 36);
	await page.screenshot({ path: path.join(tmp, 'last-page.png') });
	// save-as-image must not include it
	const seen = await page.evaluate(() => new Promise((resolve) => {
		window.html2canvas = (el) => { const cta = el.querySelector('.twd-sb-last-cta'); window.__ctaDisplayDuringCapture = cta ? getComputedStyle(cta).display : 'missing'; return Promise.resolve(document.createElement('canvas')); };
		document.querySelector('.twd-sb-save-btn').click();
		setTimeout(() => resolve(window.__ctaDisplayDuringCapture), 400);
	}));
	t('S5: hidden while Save as image captures the page', seen === 'none', String(seen));
	t('S5: and back again afterwards', (await page.locator('.twd-sb-last-cta').evaluate((e) => getComputedStyle(e).display)) !== 'none');
	await page.click('.twd-sb-companion-cta');
	await page.waitForFunction(() => document.querySelector('.twd-sb-title') && /Summary Book/.test(document.querySelector('.twd-sb-title').textContent));
	t('S5: clicking it closes the swipe book and opens the Summary Book in the same window', (await page.locator('.twd-sb-backdrop').count()) === 1 && (await page.locator('.twd-sb-title').first().innerText()).toLowerCase().includes('summary book'));
	t('S5: the Summary Book gets no such button on its own last page', (await page.locator('.twd-sb-companion-cta').count()) === 0);
	await s.ctx.close();
	// no summary book published: no button
	s = await open(browser, { swipeCompanion: false }); page = s.page;
	await page.evaluate(() => fetch('https://example.test/wp-json/twd-publisher/v1/articles/5/swipebook').then((r) => r.json()).then((d) => window.TWD_AP_SwipeBook.open(d)));
	await page.waitForSelector('.twd-sb-backdrop');
	t('S5: with no Summary Book published there is no button at all', (await page.locator('.twd-sb-companion-cta').count()) === 0 && (await page.locator('.twd-sb-companion-link').count()) === 0);
	await s.ctx.close();

	await browser.close();
	console.log('\nScreenshots: ' + tmp);
	console.log(pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
