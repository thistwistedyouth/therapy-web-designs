// Real-browser test of the swipe book colours and close button (1.47.0): the dark book is the original,
// the light book has dark writing that can be read, the close button is always visible and can be
// clicked from its whole face on every page, and both books fit without scrolling.
// The real swipebook.css and swipebook.js are loaded; only the book's data is made up (no client text).
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/swipe-theme-browser-test.js
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const plugin = path.resolve(__dirname, '../../../plugins/twd-article-publisher');
const CSS = fs.readFileSync(path.join(plugin, 'assets/swipebook.css'), 'utf8');
const JS = fs.readFileSync(path.join(plugin, 'assets/swipebook.js'), 'utf8');

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const sentence = (n) => 'This is made-up sentence number ' + n + ' about breathing slowly and noticing the room around you.';
const DECK = {
	title: 'Made-up book', siteName: 'Example Site', siteHeading: 'Example Site', label: 'Swipe Book', logoBg: 'light',
	siteUrl: 'https://example.test/', articleUrl: 'https://example.test/a/', shareUrl: 'https://example.test/a/?twd_ap_swipebook=1', includeBranding: true,
	slides: [
		{ type: 'text', heading: 'A made-up article', html: '<p>' + sentence(1) + ' ' + sentence(2) + '</p><p>A <a href="https://example.test/x">made-up link</a> in the text.</p>' },
		{ type: 'quote', heading: '', html: '<p>' + sentence(3) + '</p>', attribution: 'A Made-Up Author' },
		{ type: 'question', heading: '', html: '<p>' + sentence(4) + '</p>' },
		{ type: 'bio', heading: 'About the author', photo: '', bio: 'A made-up bio for this test only.', link_url: 'https://example.test/', link_label: 'Visit the site' },
		{ type: 'related', heading: 'More Articles', items: [1, 2, 3].map((n) => ({ id: n, title: 'A made-up related article ' + n, shareUrl: 'https://example.test/r' + n + '/' })) },
	],
};

// Colours: parse "rgb(...)", "rgba(...)" and "#hex" into [r,g,b,a]; contrast per WCAG.
const PARSE = `(c) => {
	c = String(c).trim();
	let m = c.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
	if (m) { let h = m[1]; if (h.length === 3) { h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; } return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16), 1]; }
	m = c.match(/^rgba?\\(([^)]+)\\)$/);
	if (m) { const p = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; }
	return null;
}`;
const lum = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
const contrast = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
const over = (fg, bg) => [0, 1, 2].map((i) => Math.round(fg[i] * fg[3] + bg[i] * (1 - fg[3]))).concat([1]);

// Every piece of writing on the page and in the controls, against the page behind it (light book only:
// the dark book is the original and is left as it was). Switched-off arrows and the faint decorative
// arrow on a related link are left out.
const SURVEY = `(() => {
	const parse = (c) => { const m = c.match(/rgba?\\(([^)]+)\\)/); if (!m) { return null; } const q = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return [q[0], q[1], q[2], q.length > 3 ? q[3] : 1]; };
	const over = (f, bg) => [0, 1, 2].map((i) => f[i] * f[3] + bg[i] * (1 - f[3]));
	const L = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
	const C = (a, b) => { const x = L(a), y = L(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
	const root = document.querySelector('.twd-sb-backdrop');
	const slide = document.querySelector('.twd-sb-slide.is-active');
	const els = Array.from(slide.querySelectorAll('*')).concat(Array.from(root.querySelectorAll('.twd-sb-controls *, .twd-sb-footer *, .twd-sb-counter')));
	const low = []; let n = 0;
	els.forEach((el) => {
		if (!Array.from(el.childNodes).some((x) => x.nodeType === 3 && x.textContent.trim())) { return; }
		if (el.disabled || el.classList.contains('twd-sb-related-arrow')) { return; }
		const cs = getComputedStyle(el); const r = el.getBoundingClientRect();
		if (!r.width || cs.visibility === 'hidden') { return; }
		let bg = [250, 246, 236];
		for (let e = el; e && e !== root; e = e.parentElement) { const c = parse(getComputedStyle(e).backgroundColor); if (c && c[3] > 0.5) { bg = over(c, bg); break; } }
		const ratio = C(over(parse(cs.color), bg), bg); n++;
		if (ratio < 4.5) { low.push((el.className || el.tagName) + ' "' + el.textContent.trim().slice(0, 14) + '" ' + ratio.toFixed(2)); }
	});
	return { n, low };
})()`;

// The colour of the writing on an element against what is really behind it: every translucent background
// from the backdrop down to the element laid over the page colour.
const BEHIND = `(sel) => {
	const parse = (c) => { const m = c.match(/rgba?\\(([^)]+)\\)/); if (!m) { return null; } const q = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return [q[0], q[1], q[2], q.length > 3 ? q[3] : 1]; };
	const over = (f, bg) => [0, 1, 2].map((i) => f[i] * f[3] + bg[i] * (1 - f[3]));
	const L = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
	const el = document.querySelector(sel);
	const chain = []; for (let e = el; e && e.className !== 'twd-sb-backdrop twd-sb-theme-light'; e = e.parentElement) { chain.unshift(e); }
	let bg = [250, 246, 236];
	chain.forEach((e) => { const c = parse(getComputedStyle(e).backgroundColor); if (c && c[3] > 0) { bg = over(c, bg); } });
	const fg = over(parse(getComputedStyle(el).color), bg);
	const a = L(fg), b = L(bg);
	return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}`;

function page(deck, extraCss) {
	return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + CSS + '</style><style>' + (extraCss || '') + '</style></head><body style="background:#fff">' +
		'<h1 id="under">The article underneath</h1><script>' + JS + '</script></body></html>';
}

async function openBook(browser, theme, vp, extraCss) {
	const ctx = await browser.newContext({ viewport: vp });
	const p = await ctx.newPage();
	const errors = [];
	p.on('pageerror', (e) => errors.push(String(e)));
	await p.route('https://example.test/**', (r) => r.fulfill({ contentType: 'text/html', body: page(DECK, extraCss) }));
	await p.goto('https://example.test/article');
	await p.waitForFunction(() => !!window.TWD_AP_SwipeBook);
	const deck = Object.assign({}, DECK);
	if (theme) { deck.theme = theme; }
	await p.evaluate((d) => window.TWD_AP_SwipeBook.open(d), deck);
	await p.waitForSelector('.twd-sb-slide.is-active');
	await wait(800); // the book fades in over 0.4s
	return { ctx, p, errors };
}

// The close button as the visitor meets it: where it is, what is on top of it at several points of its face.
const CLOSE_PROBE = `(() => {
	const b = document.querySelector('.twd-sb-close');
	const r = b.getBoundingClientRect();
	const cs = getComputedStyle(b);
	// Whatever the button lies over: anything in the page that has writing or a picture in it.
	const covers = [];
	document.querySelectorAll('.twd-sb-slide.is-active *').forEach((el) => {
		const own = Array.from(el.childNodes).some((n) => n.nodeType === 3 && n.textContent.trim()) || el.tagName === 'IMG';
		if (!own) { return; }
		const q = el.getBoundingClientRect();
		if (q.width && q.height && q.left < r.right && q.right > r.left && q.top < r.bottom && q.bottom > r.top) { covers.push(el.className || el.tagName); }
	});
	const cx = r.left + r.width / 2, cy = r.top + r.height / 2, k = r.width * 0.28;
	const pts = [[cx, cy], [cx - k, cy], [cx + k, cy], [cx, cy - k], [cx, cy + k]];
	const hit = pts.map((pt) => { const e = document.elementFromPoint(pt[0], pt[1]); return !!e && (e === b || b.contains(e)); });
	const vis = (el) => { let e = el; while (e && e !== document.documentElement) { const s = getComputedStyle(e); if (s.visibility === 'hidden' || s.display === 'none' || parseFloat(s.opacity) < 1) { return false; } e = e.parentElement; } return true; };
	return {
		x: r.left, y: r.top, w: r.width, h: r.height, right: r.right, bottom: r.bottom, vw: innerWidth, vh: innerHeight,
		hit, visible: vis(b), bg: cs.backgroundColor, ink: cs.color, ring: cs.borderTopColor, ringW: parseFloat(cs.borderTopWidth),
		hasSvg: !!b.querySelector('svg'), svgW: b.querySelector('svg') ? b.querySelector('svg').getBoundingClientRect().width : 0,
		text: b.textContent.trim(), label: b.getAttribute('aria-label'),
		covers,
	};
})()`;

(async () => {
	const browser = await chromium.launch();
	const PARSEFN = PARSE;

	for (const vp of [{ width: 390, height: 700 }, { width: 320, height: 568 }, { width: 360, height: 480 }, { width: 667, height: 375 }, { width: 768, height: 1024 }, { width: 1440, height: 900 }]) {
		for (const theme of ['dark', 'light']) {
			const label = theme + ' ' + vp.width + 'x' + vp.height;
			const { ctx, p, errors } = await openBook(browser, theme, vp);

			const cls = await p.evaluate(() => document.querySelector('.twd-sb-backdrop').className);
			t(label + ': the book is ' + theme, theme === 'light' ? /\btwd-sb-theme-light\b/.test(cls) : !/twd-sb-theme-light/.test(cls), cls);

			// ---- the close button, on every page, with nobody hovering it ----
			const total = await p.evaluate(() => document.querySelectorAll('.twd-sb-slide:not(.twd-sb-probe)').length);
			let allOk = true, why = '';
			for (let i = 0; i < total; i++) {
				const c = await p.evaluate(CLOSE_PROBE);
				const inView = c.x >= 0 && c.y >= 0 && c.right <= c.vw && c.bottom <= c.vh;
				const big = c.w >= 36 && c.h >= 36;
				const clear = c.covers.length === 0;
				const allHit = c.hit.every(Boolean);
				if (!(inView && big && clear && allHit && c.visible && c.hasSvg && c.text === '' && c.label === 'Close')) {
					allOk = false; why += ' page' + i + ':' + JSON.stringify({ inView, big, clear, hit: c.hit, vis: c.visible, svg: c.hasSvg, text: c.text, y: c.y, bottom: c.bottom, covers: c.covers });
				}
				if (i < total - 1) { await p.keyboard.press('ArrowRight'); await wait(180); }
			}
			t(label + ': the close button is on screen, at least 36px, covering none of the writing, drawn as a cross, and the top layer on all ' + total + ' pages', allOk, why);

			const c = await p.evaluate(CLOSE_PROBE);
			const ink = await p.evaluate((a) => (new Function('return ' + a))()(getComputedStyle(document.querySelector('.twd-sb-close')).color), PARSEFN);
			const bgc = await p.evaluate((a) => (new Function('return ' + a))()(getComputedStyle(document.querySelector('.twd-sb-close')).backgroundColor), PARSEFN);
			t(label + ': the cross reads on its button (contrast at least 7:1) with a solid button behind it', bgc[3] === 1 && contrast(ink, bgc) >= 7, 'ink ' + ink + ' bg ' + bgc + ' ' + contrast(ink, bgc).toFixed(1));
			t(label + ': the button has a visible ring and the cross is drawn at least 14px', c.ringW >= 2 && c.svgW >= 14, 'ring ' + c.ringW + ' svg ' + c.svgW);

			// ---- the writing reads on the page ----
			for (let i = 0; i < total - 1; i++) { await p.keyboard.press('ArrowLeft'); await wait(120); }
			await wait(300);
			const read = await p.evaluate((a) => {
				const parse = (new Function('return ' + a))();
				const slide = document.querySelector('.twd-sb-slide.is-active');
				const text = slide.querySelector('.twd-sb-body-text, .twd-sb-quote-text, .twd-sb-question-text');
				const head = slide.querySelector('.twd-sb-heading');
				const backdrop = document.querySelector('.twd-sb-backdrop');
				return {
					text: text ? parse(getComputedStyle(text).color) : null,
					head: head ? parse(getComputedStyle(head).color) : null,
					exportBg: getComputedStyle(backdrop).getPropertyValue('--twd-sb-export-bg').trim(),
					frameBg: getComputedStyle(document.querySelector('.twd-sb-frame')).backgroundImage.slice(0, 400),
				};
			}, PARSEFN);
			// First page is a text page: its body text colour is cream (dark book) or ink (light book).
			const pageBg = theme === 'light' ? [255, 255, 255, 1] : [27, 24, 54, 1];
			t(label + ': the first page is a text page with writing to measure', !!read.text && !!read.head);
			if (read.text) {
				const eff = over(read.text, pageBg);
				t(label + ': the writing reads on the page (contrast at least 4.5:1)', contrast(eff, pageBg) >= 4.5, 'text ' + read.text + ' ' + contrast(eff, pageBg).toFixed(1));
				const darkText = lum(read.text) < 0.3;
				t(label + ': ' + (theme === 'light' ? 'dark writing on a light page' : 'light writing on a dark page'), theme === 'light' ? darkText : !darkText, 'lum ' + lum(read.text).toFixed(2));
			}
			if (theme === 'light') {
				let lowAll = [], seen = 0;
				for (let i = 0; i < total; i++) {
					const r = await p.evaluate(SURVEY);
					seen += r.n; lowAll = lowAll.concat(r.low.map((x) => 'page' + i + ' ' + x));
					if (i < total - 1) { await p.keyboard.press('ArrowRight'); await wait(150); }
				}
				t(label + ': every piece of writing, label and control in the light book has contrast of at least 4.5:1 (' + seen + ' checked)', seen > 30 && lowAll.length === 0, lowAll.join('; '));
				for (let i = 0; i < total - 1; i++) { await p.keyboard.press('ArrowLeft'); await wait(120); }
			}
			t(label + ': the exported pictures use the book\'s own page colour', theme === 'light' ? read.exportBg === '#fcf8ee' : read.exportBg === '#16132f', read.exportBg);

			// ---- nothing scrolls, nothing sideways ----
			const fit = await p.evaluate(() => {
				const bodies = Array.from(document.querySelectorAll('.twd-sb-slide:not(.twd-sb-probe) .twd-sb-card-body'));
				return { over: bodies.map((b) => b.scrollHeight - b.clientHeight), sw: document.documentElement.scrollWidth, iw: innerWidth };
			});
			// A phone on its side (375px high) is below what the fit step supports (the same as in 1.43.0 to 1.46.1).
			t(label + ': no page scrolls and nothing sideways' + (vp.height < 480 ? ' (sideways only: too short for the fit step)' : ''), (vp.height < 480 || fit.over.every((o) => o <= 1)) && fit.sw <= fit.iw, JSON.stringify(fit));

			// ---- the page underneath is still hidden and the close button closes ----
			const box = await p.locator('.twd-sb-close').boundingBox();
			await p.mouse.click(box.x + box.width * 0.15, box.y + box.height * 0.5); // near the left edge of the face, not the centre
			await wait(120);
			const gone = await p.evaluate(() => !document.querySelector('.twd-sb-backdrop'));
			t(label + ': clicking the edge of the close button closes the book', gone);
			t(label + ': no script errors', errors.length === 0, errors.join(' | '));
			await ctx.close();
		}
	}

	// The default (no theme in the data) is the dark book, and an unknown value is too.
	for (const theme of [undefined, 'sepia', '']) {
		const { ctx, p } = await openBook(browser, theme, { width: 390, height: 700 });
		const cls = await p.evaluate(() => document.querySelector('.twd-sb-backdrop').className);
		t('theme ' + JSON.stringify(theme) + ': the book stays dark', !/twd-sb-theme-light/.test(cls), cls);
		await ctx.close();
	}

	// The dark book keeps its original look: the values the dark palette has always had.
	{
		const { ctx, p } = await openBook(browser, 'dark', { width: 1100, height: 800 });
		const v = await p.evaluate(() => {
			const bd = document.querySelector('.twd-sb-backdrop');
			const s = getComputedStyle(bd);
			return { gold: s.getPropertyValue('--twd-sb-gold').trim(), primary: s.getPropertyValue('--twd-sb-primary').trim(), veil: getComputedStyle(bd).backgroundColor, close: getComputedStyle(document.querySelector('.twd-sb-close')).backgroundColor };
		});
		t('dark book: gold accent unchanged', v.gold === '#ffd166' && v.primary === '#ffd166', JSON.stringify(v));
		t('dark book: the backdrop is the dark veil', v.veil === 'rgba(10, 9, 18, 0.72)', v.veil);
		t('dark book: the close button is gold', v.close === 'rgb(255, 209, 102)', v.close);
		await ctx.close();
	}
	{
		const { ctx, p } = await openBook(browser, 'light', { width: 1100, height: 800 });
		const v = await p.evaluate(() => ({ veil: getComputedStyle(document.querySelector('.twd-sb-backdrop')).backgroundColor, close: getComputedStyle(document.querySelector('.twd-sb-close')).backgroundColor }));
		t('light book: the backdrop is a light veil', /rgba\(243, 236, 221/.test(v.veil), v.veil);
		t('light book: the close button is dark ink', v.close === 'rgb(43, 37, 64)', v.close);
		await ctx.close();
	}

	// Hover is a bonus, not the only way it shows: the button looks the same before and after hover.
	{
		const { ctx, p } = await openBook(browser, 'light', { width: 1100, height: 800 });
		const before = await p.evaluate(() => { const s = getComputedStyle(document.querySelector('.twd-sb-close')); return { o: s.opacity, bg: s.backgroundColor }; });
		await p.hover('.twd-sb-close');
		await wait(250);
		const after = await p.evaluate(() => { const s = getComputedStyle(document.querySelector('.twd-sb-close')); return { o: s.opacity, bg: s.backgroundColor }; });
		t('close button: fully opaque without hover, and hover only brightens it', before.o === '1' && after.o === '1', JSON.stringify([before, after]));
		await ctx.close();
	}

	// A theme's own rules cannot take the cross away or stretch the button (a global svg and button sheet, as Elementor sites have).
	{
		const hostile = 'svg path { fill: currentColor !important; stroke: none !important; stroke-width: 0 !important; } button { min-height: 48px !important; max-height: none !important; }';
		for (const theme of ['dark', 'light']) {
			const { ctx, p } = await openBook(browser, theme, { width: 390, height: 700 }, hostile);
			const c = await p.evaluate(() => { const b = document.querySelector('.twd-sb-close'); const r = b.getBoundingClientRect(); const s = getComputedStyle(b.querySelector('path')); return { w: r.width, h: r.height, stroke: s.stroke, sw: parseFloat(s.strokeWidth), fill: s.fill }; });
			t(theme + ' book, hostile theme sheet: the cross is still drawn (stroke ' + c.stroke + ', width ' + c.sw + ')', c.stroke !== 'none' && c.sw >= 2 && c.fill === 'none', JSON.stringify(c));
			t(theme + ' book, hostile theme sheet: the close button is still 36px high and wide', Math.round(c.w) === 36 && Math.round(c.h) === 36, JSON.stringify(c));
			await ctx.close();
		}
	}

	// A browser that does not know :focus-visible drops a whole rule that names it, so no rule may list it with :hover.
	{
		const rules = CSS.replace(/\/\*[\s\S]*?\*\//g, '').split('}').map((r) => r.split('{')[0]).filter((sel) => /:focus-visible/.test(sel) && /:hover/.test(sel));
		t('no rule lists :hover and :focus-visible together (older Safari would lose the hover)', rules.length === 0, rules.join(' | '));
	}

	// The light book on hover and keyboard focus: the writing still reads on the lit-up chip.
	{
		const { ctx, p } = await openBook(browser, 'light', { width: 1100, height: 800 });
		const low = [];
		for (const sel of ['.twd-sb-share-btn', '.twd-sb-save-btn', '.twd-sb-pdf-btn', '.twd-sb-next', '.twd-sb-read-link']) {
			await p.hover(sel);
			await wait(300);
			const ratio = await p.evaluate(`(${BEHIND})('${sel}')`);
			// the arrow is a 20px bold glyph, which is large text (3:1); everything else is held to 4.5:1
			if (ratio < ('.twd-sb-next' === sel ? 3 : 4.5)) { low.push(sel + ' ' + ratio.toFixed(2)); }
		}
		t('light book: every button and link keeps 4.5:1 contrast while hovered', low.length === 0, low.join('; '));
		await ctx.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
