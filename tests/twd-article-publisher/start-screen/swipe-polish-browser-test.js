// Real-browser test of the swipe book polish (1.49.0): Save as image is one even colour (the real html2canvas),
// the logo is bigger, the bio page's button closes the book and goes to its link, the arrows, page numbers and
// "Read the full article" pill, and the owner's own frame and accent colours (everything that sits on the frame
// stays readable on any colour). The real swipebook.css and swipebook.js are loaded; only the book's data is made up.
// Needs the html2canvas library: npm install -g html2canvas@1.4.1
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/swipe-polish-browser-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const CSS = fs.readFileSync(path.join(plugin, 'assets/swipebook.css'), 'utf8');
const JS = fs.readFileSync(path.join(plugin, 'assets/swipebook.js'), 'utf8');
const H2C_PATH = path.join(execFileSync('npm', ['root', '-g']).toString().trim(), 'html2canvas/dist/html2canvas.js');
if (!fs.existsSync(H2C_PATH)) { console.error('html2canvas is not installed: npm install -g html2canvas@1.4.1'); process.exit(2); }
const H2C = fs.readFileSync(H2C_PATH, 'utf8');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-polish-'));
const dumpFile = path.join(tmp, 'colours.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/swipe-colours-dump.php'), dumpFile]);
const COMBOS = JSON.parse(fs.readFileSync(dumpFile, 'utf8'));

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const LOGO = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="400" height="300" fill="#6a9a70"/></svg>');
const sentence = (n) => 'This is made-up sentence number ' + n + ' about breathing slowly and noticing the room around you.';
const DECK = (extra) => Object.assign({
	title: 'Made-up book', siteName: 'Example Site', siteHeading: 'Example Site', label: 'Swipe Book', logoBg: 'light', logoUrl: LOGO,
	siteUrl: 'https://example.test/', articleUrl: 'https://example.test/article/', shareUrl: 'https://example.test/article/?twd_ap_swipebook=1', includeBranding: true,
	slides: [
		{ type: 'text', heading: 'A made-up article', html: '<p>' + sentence(1) + ' ' + sentence(2) + '</p>' },
		{ type: 'quote', heading: '', html: '<p>' + sentence(3) + '</p>', attribution: 'A Made-Up Author' },
		{ type: 'bio', heading: 'Jane Smith', photo: '', bio: 'A made-up bio for this test only.', link_url: 'https://example.test/book-a-session', link_label: 'Book a Session' },
	],
}, extra || {});

function page(data, standalone) {
	return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + CSS + '</style></head><body' + (standalone ? ' class="twd-sb-page"' : ' style="background:#fff"') + '>' +
		(standalone ? '<script>window.TWD_AP_SB_DATA = ' + JSON.stringify(data) + ';</script>' : '') +
		'<script>' + H2C + '</script><script>window.__cap = null; (function () { var o = window.html2canvas; window.html2canvas = function () { return o.apply(this, arguments).then(function (c) { window.__cap = c; return c; }); }; })();</script>' +
		'<script>' + JS + '</script></body></html>';
}

async function openBook(browser, data, vp, standalone, touch) {
	const ctx = await browser.newContext({ viewport: vp || { width: 560, height: 800 }, acceptDownloads: true, hasTouch: !!touch });
	const p = await ctx.newPage();
	const errors = [];
	p.on('pageerror', (e) => errors.push(String(e)));
	await p.route('https://example.test/**', (r) => {
		const u = r.request().url();
		if (/book-a-session/.test(u)) { return r.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Booking page</title><h1>Book</h1>' }); }
		if (/\/article\//.test(u) && !standalone) { return r.fulfill({ contentType: 'text/html', body: '<!doctype html><title>The article</title><h1>Article</h1>' }); }
		return r.fulfill({ contentType: 'text/html', body: page(data, standalone) });
	});
	await p.goto('https://example.test/' + (standalone ? 'book' : 'start'));
	await p.waitForFunction(() => !!window.TWD_AP_SwipeBook);
	if (!standalone) { await p.evaluate((d) => window.TWD_AP_SwipeBook.open(d), data); }
	await p.waitForSelector('.twd-sb-slide.is-active');
	await wait(800); // the book fades in over 0.4s
	return { ctx, p, errors };
}

// A deck with every kind of writing the book has on its card, then the bio page.
const RICH = (extra) => DECK(Object.assign({ companionUrl: 'https://example.test/summary/', companionPostId: 7, companionBookVariant: 'summary', companionLabel: 'Read the Summary Book', slides: [
	{ type: 'text', heading: 'A made-up article heading', html: '<p>Body text sentence one about breathing slowly and noticing the room.</p><h3>A sub heading</h3><p>More body text.</p><blockquote><p>A quoted line of text.</p></blockquote>' },
	{ type: 'bio', heading: 'Jane Smith', photo: '', bio: 'A made-up bio for this test only.', link_url: 'https://example.test/book-a-session', link_label: 'Book a Session' },
] }, extra || {}));

// What the writing REALLY sits on: the book is photographed with the writing made transparent and the colour
// behind it is read from the picture (the frame is a gradient and the card is a layer over it, so adding up the
// elements' own background colours does not give the real answer). Returns the pieces that read too weakly.
const FRAME_WRITING = [['.twd-sb-counter', 4.5, 'page numbers'], ['.twd-sb-next', 3, 'the arrow'], ['.twd-sb-prev', 3, 'the back arrow'], ['.twd-sb-share-btn', 4.5, 'Share'], ['.twd-sb-save-btn', 4.5, 'Save as image'], ['.twd-sb-read-link', 4.5, 'Read the full article']];
const CARD_WRITING = [['.twd-sb-slide.is-active .twd-sb-heading', 4.5, 'heading'], ['.twd-sb-slide.is-active .twd-sb-body-text > p', 4.5, 'body text'], ['.twd-sb-slide.is-active .twd-sb-body-text h3', 4.5, 'sub heading'], ['.twd-sb-slide.is-active blockquote p', 4.5, 'quote'], ['.twd-sb-slide.is-active .twd-sb-title', 4.5, 'Swipe Book label'], ['.twd-sb-slide.is-active .twd-sb-sitename', 4.5, 'site name']];
const BIO_WRITING = [['.twd-sb-slide.is-active .twd-sb-bio-link', 4.5, 'Book a Session']];
async function weakWriting(p, targets) {
	const info = await p.evaluate((list) => list.map((l) => { const e = document.querySelector(l[0]); if (!e) { return null; } const r = e.getBoundingClientRect(); return { x: r.x, y: r.y, w: r.width, h: r.height, color: getComputedStyle(e).color }; }), targets);
	await p.addStyleTag({ content: targets.map((l) => l[0] + ',' + l[0] + ':hover').join(',') + ' { color: transparent !important; text-shadow: none !important; transition: none !important; }' });
	await p.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));
	const points = [];
	info.forEach((i) => { if (!i) { points.push([0, 0], [0, 0], [0, 0]); return; } [0.3, 0.5, 0.7].forEach((f) => points.push([i.x + i.w * f, i.y + i.h / 2])); });
	const b64 = (await p.screenshot({ type: 'png' })).toString('base64');
	const px = await p.evaluate(async ({ b64, points }) => {
		const img = new Image(); img.src = 'data:image/png;base64,' + b64; await new Promise((res) => { img.onload = res; });
		const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const g = c.getContext('2d'); g.drawImage(img, 0, 0);
		return points.map((q) => { const d = g.getImageData(Math.round(q[0]), Math.round(q[1]), 1, 1).data; return [d[0], d[1], d[2]]; });
	}, { b64, points });
	await p.evaluate(() => { const st = document.querySelectorAll('style'); st[st.length - 1].remove(); });
	const lum = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
	const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
	const parse = (c) => { const q = c.match(/rgba?\(([^)]+)\)/)[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return [q[0], q[1], q[2], q.length > 3 ? q[3] : 1]; };
	const low = [];
	targets.forEach((l, k) => {
		const i = info[k]; if (!i) { low.push(l[2] + ' missing'); return; }
		const f = parse(i.color);
		const worst = Math.min(...px.slice(k * 3, k * 3 + 3).map((bg) => ratio([0, 1, 2].map((n) => f[n] * f[3] + bg[n] * (1 - f[3])), bg)));
		if (worst < l[1]) { low.push(l[2] + ' ' + worst.toFixed(2)); }
	});
	return low;
}

// The colour of the writing on an element against what is behind it: every translucent layer from `base` up.
const RATIO = `(args) => {
	const parse = (c) => { const m = c.match(/rgba?\\(([^)]+)\\)/); if (!m) { return null; } const q = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return [q[0], q[1], q[2], q.length > 3 ? q[3] : 1]; };
	const over = (f, bg) => [0, 1, 2].map((i) => f[i] * f[3] + bg[i] * (1 - f[3]));
	const L = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
	const el = document.querySelector(args.sel);
	if (!el) { return null; }
	const stop = document.querySelector('.twd-sb-frame');
	const chain = []; for (let e = el; e && e !== stop; e = e.parentElement) { chain.unshift(e); }
	let bg = args.base;
	chain.forEach((e) => { const c = parse(getComputedStyle(e).backgroundColor); if (c && c[3] > 0) { bg = over(c, bg); } });
	const fg = over(parse(getComputedStyle(el).color), bg);
	const a = L(fg), b = L(bg);
	return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}`;
const hexToRgb = (h) => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16), 1];

(async () => {
	const browser = await chromium.launch();

	// ---------- Save as image: one even colour, light and dark ----------
	for (const theme of ['light', 'dark']) {
		for (const vp of [{ width: 560, height: 800 }, { width: 390, height: 700 }]) {
			const { ctx, p, errors } = await openBook(browser, DECK({ theme }), vp);
			const [dl] = await Promise.all([p.waitForEvent('download'), p.click('.twd-sb-save-btn')]);
			t(theme + ' ' + vp.width + ': Save as image downloads a png', /\.png$/.test(dl.suggestedFilename()), dl.suggestedFilename());
			await wait(300);
			const r = await p.evaluate(() => {
				const c = window.__cap; if (!c) { return null; }
				const g = c.getContext('2d');
				const px = (fx, fy) => { const d = g.getImageData(Math.min(c.width - 1, Math.round(c.width * fx)), Math.min(c.height - 1, Math.round(c.height * fy)), 1, 1).data; return d[0] + ',' + d[1] + ',' + d[2]; };
				const blank = new Set(); // the empty bottom of the card, below the text and clear of the logo
				[0.84, 0.88, 0.92, 0.95].forEach((fy) => [0.012, 0.03, 0.05, 0.08, 0.15, 0.25, 0.35, 0.45, 0.5, 0.55, 0.65, 0.75, 0.85, 0.92, 0.95, 0.97, 0.988].forEach((fx) => blank.add(px(fx, fy))));
				const bd = document.querySelector('.twd-sb-backdrop');
				return { blank: Array.from(blank), corner: px(0, 0), bg: getComputedStyle(bd).getPropertyValue('--twd-sb-export-bg').trim(), left: bd.classList.contains('twd-sb-capture') };
			});
			const want = hexToRgb(r.bg).slice(0, 3).join(',');
			t(theme + ' ' + vp.width + ': the saved picture is one even colour, no split and no inner rectangle (' + r.blank.length + ' colour found in the empty areas)', r.blank.length === 1 && r.blank[0] === want, JSON.stringify(r.blank) + ' wanted ' + want);
			t(theme + ' ' + vp.width + ': and the ground behind the rounded corners is that same colour', r.corner === want, r.corner + ' wanted ' + want);
			t(theme + ' ' + vp.width + ': the capture class is gone afterwards and nothing broke', r.left === false && errors.length === 0, errors.join(' | '));
			await ctx.close();
		}
	}

	// ---------- the logo ----------
	{
		const { ctx, p } = await openBook(browser, DECK({ theme: 'light' }));
		const logo = await p.locator('.twd-sb-slide.is-active .twd-sb-mast-logo').boundingBox();
		t('the logo is bigger: 64px high at most 170 wide, a 4:3 logo fills that height (was 40px)', logo.height >= 56 && logo.height <= 66 && logo.width <= 172, JSON.stringify(logo));
		const small = await p.evaluate(() => document.querySelector('.twd-sb-slide.is-active .twd-sb-card-body').scrollHeight - document.querySelector('.twd-sb-slide.is-active .twd-sb-card-body').clientHeight);
		t('a bigger logo does not make the page scroll', small <= 1, String(small));
		await ctx.close();
	}

	// ---------- the bio page's button ----------
	{
		let { ctx, p } = await openBook(browser, DECK({ theme: 'light' }));
		await p.keyboard.press('ArrowRight'); await wait(300); await p.keyboard.press('ArrowRight'); await wait(300);
		const href = await p.getAttribute('.twd-sb-slide.is-active .twd-sb-bio-link', 'href');
		t('Book a Session: a plain link in the same tab (no new window)', href === 'https://example.test/book-a-session' && !(await p.getAttribute('.twd-sb-slide.is-active .twd-sb-bio-link', 'target')));
		await Promise.all([p.waitForURL('**/book-a-session'), p.click('.twd-sb-slide.is-active .twd-sb-bio-link')]);
		t('Book a Session: the browser goes to the link', /book-a-session$/.test(p.url()), p.url());
		t('Book a Session: and the book is closed (on the way, the page behind is gone)', (await p.locator('.twd-sb-backdrop').count()) === 0);
		await ctx.close();

		// the book is closed BEFORE the browser leaves the page (what the page holds at the moment it starts to unload)
		({ ctx, p } = await openBook(browser, DECK({ theme: 'light' })));
		await p.keyboard.press('ArrowRight'); await wait(200); await p.keyboard.press('ArrowRight'); await wait(300);
		await p.evaluate(() => { window.addEventListener('beforeunload', () => { sessionStorage.setItem('sbAtUnload', String(document.querySelectorAll('.twd-sb-backdrop').length)); }); });
		await Promise.all([p.waitForURL('**/book-a-session'), p.click('.twd-sb-slide.is-active .twd-sb-bio-link')]);
		const atUnload = await p.evaluate(() => sessionStorage.getItem('sbAtUnload'));
		t('Book a Session: the book was already closed when the browser started to leave the page', atUnload === '0', String(atUnload));
		await ctx.close();

		({ ctx, p } = await openBook(browser, DECK({ theme: 'light' })));
		await p.keyboard.press('ArrowRight'); await wait(200); await p.keyboard.press('ArrowRight'); await wait(300);
		const pagePromise = ctx.waitForEvent('page', { timeout: 3000 }).catch(() => null);
		await p.click('.twd-sb-slide.is-active .twd-sb-bio-link', { modifiers: ['Control'] });
		const np = await pagePromise;
		t('Book a Session: a control-click is left to the browser (opens a new tab, the book stays open)', !!np && (await p.locator('.twd-sb-backdrop').count()) === 1 && /start/.test(p.url()), np ? np.url() : 'no new tab');
		await ctx.close();

		// the standalone page (the swipe book's own address): goes to the link, not back to the article
		({ ctx, p } = await openBook(browser, DECK({ theme: 'light' }), { width: 560, height: 800 }, true));
		await p.keyboard.press('ArrowRight'); await wait(200); await p.keyboard.press('ArrowRight'); await wait(300);
		await Promise.all([p.waitForURL('**/book-a-session'), p.click('.twd-sb-slide.is-active .twd-sb-bio-link')]);
		t('Book a Session on the standalone page: goes to the link, not to the article', /book-a-session$/.test(p.url()), p.url());
		await ctx.close();

		// a mailto: button is not taken over
		({ ctx, p } = await openBook(browser, DECK({ theme: 'light', slides: [{ type: 'bio', heading: 'Jane', photo: '', bio: 'Bio.', link_url: 'mailto:jane@example.test', link_label: 'Email me' }] })));
		const prevented = await p.evaluate(() => { const a = document.querySelector('.twd-sb-bio-link'); let stopped = null; document.addEventListener('click', (e) => { stopped = e.defaultPrevented; e.preventDefault(); }, { once: true }); a.click(); return stopped; });
		t('a mailto: button is left to the browser and the book stays open', prevented === false && (await p.locator('.twd-sb-backdrop').count()) === 1, String(prevented));
		await ctx.close();
	}

	// a swipe still turns the page, and letting go over a link at the end of a swipe does not press it
	{
		const { ctx, p } = await openBook(browser, DECK({ theme: 'light' }));
		await p.keyboard.press('ArrowRight'); await wait(300); await p.keyboard.press('ArrowRight'); await wait(300);
		const box = await p.locator('.twd-sb-slide.is-active .twd-sb-bio-link').boundingBox();
		const cx = box.x + box.width / 2, cy = box.y + box.height / 2;
		const idx = () => p.evaluate(() => document.querySelector('.twd-sb-counter').textContent.trim());
		t('swipe: starts on the last page', /^3 \/ 3$/.test(await idx()), await idx());
		await p.mouse.move(cx - 150, cy); await p.mouse.down(); await p.mouse.move(cx - 40, cy, { steps: 6 }); await p.mouse.move(cx + 6, cy, { steps: 6 }); await p.mouse.up();
		await wait(300);
		t('swipe: a drag to the right (starting beside the button, ending on it) turns back a page and does not press the button', /^2 \/ 3$/.test(await idx()) && /\/start$/.test(p.url()) && (await p.locator('.twd-sb-backdrop').count()) === 1, (await idx()) + ' ' + p.url());
		await ctx.close();
	}

	// a swipe that starts on the button and ends on the same button does not press it
	{
		const { ctx, p } = await openBook(browser, DECK({ theme: 'light' }));
		await p.keyboard.press('ArrowRight'); await wait(300); await p.keyboard.press('ArrowRight'); await wait(300);
		const box = await p.locator('.twd-sb-slide.is-active .twd-sb-bio-link').boundingBox();
		const cx = box.x + box.width / 2, cy = box.y + box.height / 2;
		const half = Math.floor(box.width / 2) - 3;
		t('swipe on the button: the button is wide enough to hold a drag of over 50px', half * 2 > 56, String(box.width));
		await p.mouse.move(cx - half, cy); await p.mouse.down(); await p.mouse.move(cx + half, cy, { steps: 8 }); await p.mouse.up();
		await wait(400);
		const idx2 = await p.evaluate(() => { const c = document.querySelector('.twd-sb-counter'); return c ? c.textContent.trim() : 'the page was left'; });
		t('swipe on the button: a drag that starts and ends on the button turns the page and does not press it', /^2 \/ 3$/.test(idx2) && /\/start$/.test(p.url()) && (await p.locator('.twd-sb-backdrop').count()) === 1, idx2 + ' ' + p.url());
		// a short drag along the button (a swipe that was not long enough to turn the page) is not a press either
		await p.keyboard.press('ArrowRight'); await wait(300);
		await p.keyboard.press('ArrowLeft'); await wait(300);
		await p.keyboard.press('ArrowRight'); await wait(300);
		const box2 = await p.locator('.twd-sb-slide.is-active .twd-sb-bio-link').boundingBox();
		const idx3 = () => p.evaluate(() => { const c = document.querySelector('.twd-sb-counter'); return c ? c.textContent.trim() : 'the page was left'; });
		const before = await idx3();
		await p.mouse.move(box2.x + box2.width / 2 - 14, box2.y + box2.height / 2); await p.mouse.down(); await p.mouse.move(box2.x + box2.width / 2 + 14, box2.y + box2.height / 2, { steps: 5 }); await p.mouse.up();
		await wait(400);
		t('swipe on the button: a short drag that does not turn the page does not press the button either', (await idx3()) === before && /\/start$/.test(p.url()) && (await p.locator('.twd-sb-backdrop').count()) === 1, before + ' ' + (await idx3()) + ' ' + p.url());
		await ctx.close();
	}

	// a pen that leaves the pages before it is lifted ends the swipe (no stale "dragging" state), and a touch the
	// browser cancels is no swipe at all (a cancelled touch reports no real position)
	{
		const { ctx, p } = await openBook(browser, DECK({ theme: 'light' }), { width: 900, height: 800 }, false, true);
		const cdp = await ctx.newCDPSession(p);
		const slide = await p.locator('.twd-sb-slide.is-active').boundingBox();
		const ctl = await p.locator('.twd-sb-counter').boundingBox();
		const pen = (type, x, y) => cdp.send('Input.dispatchMouseEvent', { type, x, y, button: type === 'mouseMoved' ? 'none' : 'left', buttons: type === 'mouseReleased' ? 0 : 1, clickCount: type === 'mouseMoved' ? 0 : 1, pointerType: 'pen' });
		await pen('mousePressed', slide.x + slide.width / 2, slide.y + slide.height - 30);
		await pen('mouseMoved', slide.x + slide.width / 2, slide.y + slide.height + 10);
		await pen('mouseMoved', ctl.x + ctl.width / 2, ctl.y + ctl.height / 2);
		await pen('mouseReleased', ctl.x + ctl.width / 2, ctl.y + ctl.height / 2);
		await wait(300);
		t('a pen drag that leaves the pages and is lifted elsewhere does not leave the book in the dragging state', !(await p.evaluate(() => document.querySelector('.twd-sb-backdrop').classList.contains('is-dragging'))));
		const before = await p.evaluate(() => document.querySelector('.twd-sb-counter').textContent.trim());
		const sx = slide.x + slide.width / 2, sy = slide.y + slide.height / 2;
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: sx + 120, y: sy }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: sx + 100, y: sy + 4 }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchCancel', touchPoints: [] });
		await wait(300);
		t('a touch the browser cancels after a small movement is no swipe: the page does not turn', (await p.evaluate(() => document.querySelector('.twd-sb-counter').textContent.trim())) === before && !(await p.evaluate(() => document.querySelector('.twd-sb-backdrop').classList.contains('is-dragging'))), before);
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: sx + 120, y: sy }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: sx + 60, y: sy + 2 }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: sx - 40, y: sy + 4 }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
		await wait(500);
		t('a touch swipe to the left turns the page (the browser may cancel it part way; the last position seen is used, not the 0, 0 a cancel reports)', /^2 \/ 3$/.test(await p.evaluate(() => document.querySelector('.twd-sb-counter').textContent.trim())), await p.evaluate(() => document.querySelector('.twd-sb-counter').textContent.trim()));
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: sx - 120, y: sy }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: sx - 60, y: sy + 2 }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: sx + 60, y: sy + 4 }] });
		await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
		await wait(500);
		t('a touch swipe to the right turns back a page (not forward)', /^1 \/ 3$/.test(await p.evaluate(() => document.querySelector('.twd-sb-counter').textContent.trim())), await p.evaluate(() => document.querySelector('.twd-sb-counter').textContent.trim()));
		await ctx.close();
	}

	// ---------- arrows, page numbers, the Read the full article pill ----------
	for (const theme of ['light', 'dark']) {
		const { ctx, p } = await openBook(browser, DECK({ theme }), { width: 900, height: 800 });
		const arrow = (sel) => p.evaluate((s) => { const e = document.querySelector(s); const c = getComputedStyle(e); return { bg: c.backgroundColor, border: parseFloat(c.borderTopWidth), borderColor: c.borderTopColor, weight: c.fontWeight, size: c.fontSize, op: c.opacity }; }, sel);
		const alpha = (c) => { const m = c.match(/rgba?\(([^)]+)\)/); const q = m[1].split(',').map(Number); return q.length > 3 ? q[3] : 1; };
		const rest = await arrow('.twd-sb-next');
		t(theme + ' arrows: a visible circle at rest (tinted background and a ring), bold', alpha(rest.bg) >= 0.1 && rest.border >= 1 && Number(rest.weight) >= 700, JSON.stringify(rest));
		await p.hover('.twd-sb-next'); await wait(350);
		const hov = await arrow('.twd-sb-next');
		t(theme + ' arrows: the circle is darker on hover, not newly added', alpha(hov.bg) > alpha(rest.bg) + 0.05, JSON.stringify([rest.bg, hov.bg]));
		const prevOff = await p.evaluate(() => { const e = document.querySelector('.twd-sb-prev'); return { disabled: e.disabled, op: getComputedStyle(e).opacity }; });
		t(theme + ' arrows: the unavailable one (first page) is still faded', prevOff.disabled && parseFloat(prevOff.op) < 0.5, JSON.stringify(prevOff));
		const counter = await p.evaluate(() => { const c = getComputedStyle(document.querySelector('.twd-sb-counter')); return { w: c.fontWeight, s: c.fontSize }; });
		t(theme + ' page numbers: bold, 12px', Number(counter.w) >= 700 && counter.s === '12px', JSON.stringify(counter));
		const pill = await p.evaluate(() => { const e = document.querySelector('.twd-sb-read-link'); const c = getComputedStyle(e); return { deco: c.textDecorationLine, radius: parseFloat(c.borderTopLeftRadius), border: parseFloat(c.borderTopWidth), bg: c.backgroundColor, h: e.getBoundingClientRect().height, text: e.textContent.trim() }; });
		t(theme + ' Read the full article: a small pill with no underline', pill.deco === 'none' && pill.radius >= 12 && pill.border >= 1 && alpha(pill.bg) > 0 && pill.h >= 24 && pill.h <= 34 && /Read the full article/.test(pill.text), JSON.stringify(pill));
		await ctx.close();
	}

	// ---------- the owner's own colours ----------
	const keys = Object.keys(COMBOS);
	for (const key of keys) {
		const c = COMBOS[key];
		const { ctx, p, errors } = await openBook(browser, RICH({ theme: c.theme, style: c.style }), { width: 900, height: 800 });
		const label = key + ': ';
		const bgImage = await p.evaluate(() => getComputedStyle(document.querySelector('.twd-sb-frame')).backgroundImage);
		if (c.frame) {
			const rgb = hexToRgb(c.frame).slice(0, 3).join(', ');
			t(label + 'the frame uses the chosen colour', bgImage.indexOf('rgb(' + rgb + ')') !== -1, bgImage.slice(0, 160));
		} else {
			t(label + 'no frame colour: the frame is the book\'s own', bgImage.indexOf('linear-gradient') !== -1);
		}
		const lowFrame = await weakWriting(p, FRAME_WRITING);
		t(label + 'on the frame (page numbers, arrows, Share, Save as image, Read the full article) every piece of writing reads on what is really behind it', lowFrame.length === 0, lowFrame.join('; '));
		await p.hover('.twd-sb-companion-link'); await wait(250);
		const lowComp = await weakWriting(p, [['.twd-sb-companion-link', 4.5, 'the other book link, pointed at']]);
		await p.mouse.move(5, 5); await wait(250);
		t(label + 'the link to the other book reads on the frame, also when it is pointed at', lowComp.length === 0, lowComp.join('; '));
		const lowCard = await weakWriting(p, CARD_WRITING);
		t(label + 'on the card (heading, text, sub heading, quote, label, site name) every piece of writing reads on what is really behind it', lowCard.length === 0, lowCard.join('; '));
		await p.keyboard.press('ArrowRight'); await wait(700);
		const lowBio = await weakWriting(p, BIO_WRITING);
		t(label + 'the bio page button reads on what is really behind it', lowBio.length === 0, lowBio.join('; '));
		await p.keyboard.press('ArrowLeft'); await wait(500);
		if (c.accent) {
			const fill = await p.evaluate(() => { const e = document.querySelector('.twd-sb-pdf-btn'); const s = getComputedStyle(e); return { bg: s.backgroundColor, color: s.color }; });
			t(label + 'the Download as PDF button is the chosen accent', fill.bg === 'rgb(' + hexToRgb(c.accent).slice(0, 3).join(', ') + ')', JSON.stringify(fill));
		}
		const sw = await p.evaluate(() => ({ s: document.documentElement.scrollWidth, c: document.documentElement.clientWidth, over: Array.from(document.querySelectorAll('.twd-sb-slide:not(.twd-sb-probe) .twd-sb-card-body')).map((b) => b.scrollHeight - b.clientHeight).filter((o) => o > 1).length }));
		t(label + 'no sideways scroll and no page scrolls', sw.s <= sw.c && sw.over === 0, JSON.stringify(sw));
		t(label + 'no script errors', errors.length === 0, errors.join(' | '));
		await ctx.close();
	}

	// the saved picture follows the chosen frame (and stays the card's own light or dark colour)
	for (const key of ['pastel-light', 'forest-dark', 'pink-light']) {
		const c = COMBOS[key];
		const { ctx, p } = await openBook(browser, DECK({ theme: c.theme, style: c.style }));
		await Promise.all([p.waitForEvent('download'), p.click('.twd-sb-save-btn')]);
		await wait(300);
		const r = await p.evaluate(() => { const c = window.__cap; const g = c.getContext('2d'); const d = g.getImageData(Math.round(c.width * 0.5), Math.round(c.height * 0.9), 1, 1).data; return { px: d[0] + ',' + d[1] + ',' + d[2], bg: getComputedStyle(document.querySelector('.twd-sb-backdrop')).getPropertyValue('--twd-sb-export-bg').trim() }; });
		t(key + ': the saved picture is the chosen book\'s own flat colour (' + r.bg + ')', r.px === hexToRgb(r.bg).slice(0, 3).join(','), JSON.stringify(r));
		await ctx.close();
	}

	// the book without any colour set is exactly as before (no style attribute)
	{
		const { ctx, p } = await openBook(browser, DECK({ theme: 'dark' }));
		t('no colour chosen: the book has no style attribute at all', (await p.getAttribute('.twd-sb-backdrop', 'style')) === null);
		await ctx.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
