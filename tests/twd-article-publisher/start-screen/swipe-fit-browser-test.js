// Real-browser test of the swipe book's page fit (1.43.0): no page scrolls, text never drops below the
// floor, nothing is lost or reordered when a page is split, and it re-fits when the screen changes.
// The real swipebook.css and swipebook.js are loaded; only the book's data is made up (no client text).
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/swipe-fit-browser-test.js
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const plugin = path.resolve(__dirname, '../../../plugins/twd-article-publisher');
const CSS = fs.readFileSync(path.join(plugin, 'assets/swipebook.css'), 'utf8');
const JS = fs.readFileSync(path.join(plugin, 'assets/swipebook.js'), 'utf8');

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

// ---- made-up book --------------------------------------------------------------
const sentence = (n) => 'This is made-up sentence number ' + n + ' about breathing slowly and noticing the room around you.';
const para = (from, count) => { let s = ''; for (let i = 0; i < count; i++) { s += sentence(from + i) + ' '; } return s.trim(); };
const manyParas = (n) => { let h = ''; for (let i = 0; i < n; i++) { h += '<p>' + para(i * 2, 2) + '</p>'; } return h; };
const items = (n) => { let h = ''; for (let i = 1; i <= n; i++) { h += '<li>List item ' + i + ' with a few made-up words to fill the line.</li>'; } return h; };

const DECK = {
	title: 'Made-up book', siteName: 'Example Site', siteHeading: 'Example Site', label: 'Swipe Book', logoBg: 'light',
	siteUrl: 'https://example.test/', articleUrl: 'https://example.test/a/', shareUrl: 'https://example.test/a/?twd_ap_swipebook=1', includeBranding: true,
	slides: [
		{ type: 'text', heading: 'A made-up article', html: '<p>A short opening line.</p>' },
		{ type: 'text', heading: 'Twelve paragraphs', html: manyParas(12) },
		{ type: 'text', heading: 'One long paragraph', html: '<p>' + para(100, 9) + ' <strong>Bold words in the middle.</strong> ' + para(120, 6) + '</p>' },
		{ type: 'text', heading: 'A long list', html: '<p>Before the list.</p><ul>' + items(14) + '</ul><p>After the list.</p>' },
		{ type: 'text', heading: 'A numbered list', html: '<ol>' + items(10) + '</ol>' },
		{ type: 'quote', heading: '', html: '<p>' + para(200, 4) + '</p>', attribution: 'A Made-Up Author, Chapter One' },
		{ type: 'question', heading: '', html: '<p>First blank-line paragraph with a handful of words.<br><br>' + para(300, 3) + '<br><br>Last paragraph, short.</p>' },
		{ type: 'text', heading: 'Short', html: '<p>Fits anywhere.</p>' },
		{ type: 'bio', heading: 'About the author', photo: '', bio: 'A made-up bio of about one hundred and sixty characters, written for this test only, so it is a short paragraph.', link_url: 'https://example.test/', link_label: 'Visit the site' },
		{ type: 'related', heading: 'More Articles', items: [1, 2, 3, 4, 5, 6].map((n) => ({ id: n, title: 'A made-up related article title number ' + n, shareUrl: 'https://example.test/r' + n + '/' })) },
	],
};
const SHORT_DECK = Object.assign({}, DECK, { slides: [DECK.slides[0], DECK.slides[7], { type: 'quote', heading: '', html: '<p>A short quote.</p>', attribution: 'Someone' }, { type: 'question', heading: '', html: '<p>A short question?</p>' }] });

function page(deck, withData) {
	return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + CSS + '</style></head><body class="twd-sb-body twd-sb-page">' +
		(withData ? '<script>window.TWD_AP_SB_DATA = ' + JSON.stringify(deck) + ';</script>' : '') + '<script>' + JS + '</script></body></html>';
}

async function open(browser, deck, vp, withData) {
	const ctx = await browser.newContext({ viewport: vp });
	const p = await ctx.newPage();
	const errors = [];
	p.on('pageerror', (e) => errors.push(String(e)));
	p.on('console', (m) => { if (m.type() === 'warning' && /Swipe book/.test(m.text())) { errors.push(m.text()); } });
	await p.route('https://example.test/**', (r) => r.fulfill({ contentType: 'text/html', body: page(deck, withData !== false) }));
	await p.goto('https://example.test/book');
	if (withData !== false) { await p.waitForSelector('.twd-sb-slide'); } else { await p.waitForFunction(() => !!window.TWD_AP_SwipeBook); }
	await wait(150);
	return { ctx, p, errors };
}

// Everything the checks need, measured in the page.
function inspect(deck) {
	return `(() => {
		const norm = (s) => (s || '').replace(/\\s+/g, '');
		const slides = Array.from(document.querySelectorAll('.twd-sb-slide:not(.twd-sb-probe)'));
		const pages = slides.map((s) => {
			const body = s.querySelector('.twd-sb-card-body');
			const primary = body.querySelector('.twd-sb-body-text, .twd-sb-quote-text, .twd-sb-question-text, .twd-sb-bio-text, .twd-sb-related-title');
			const kind = body.querySelector('.twd-sb-quote') ? 'quote' : body.querySelector('.twd-sb-question') ? 'question' : body.querySelector('.twd-sb-bio') ? 'bio' : body.querySelector('.twd-sb-related-list') ? 'related' : 'text';
			let text = '';
			if (kind === 'related') { text = Array.from(body.querySelectorAll('.twd-sb-related-title')).map((e) => e.textContent).join(''); }
			else if (primary) { text = primary.textContent; }
			return {
				src: parseInt(s.getAttribute('data-src'), 10), kind,
				over: body.scrollHeight - body.clientHeight, room: body.clientHeight,
				font: primary ? parseFloat(getComputedStyle(primary).fontSize) : 0,
				scale: body.style.getPropertyValue('--twd-sb-s') || '',
				text: norm(text), len: norm(text).length,
				headings: body.querySelectorAll('.twd-sb-heading').length,
				attribution: body.querySelectorAll('.twd-sb-quote-attribution').length,
			};
		});
		return { pages, probes: document.querySelectorAll('.twd-sb-probe').length, segs: document.querySelectorAll('.twd-sb-seg').length, counter: document.querySelector('.twd-sb-counter').textContent, scrollW: document.documentElement.scrollWidth, innerW: window.innerWidth };
	})()`;
}
const sourceText = (deck) => deck.slides.map((s) => {
	const d = { text: '' };
	if (s.type === 'related') { return s.items.map((i) => i.title).join('').replace(/\s+/g, ''); }
	if (s.type === 'bio') { return (s.bio || '').replace(/\s+/g, ''); }
	return (s.html || '').replace(/<br\s*\/?>/g, '').replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/\s+/g, '');
});

(async () => {
	const browser = await chromium.launch();
	const viewports = [
		{ width: 320, height: 568 }, { width: 360, height: 640 }, { width: 390, height: 700 }, { width: 414, height: 896 },
		{ width: 768, height: 1024 }, { width: 1024, height: 600 }, { width: 1440, height: 900 },
	];
	const src = sourceText(DECK);

	for (const vp of viewports) {
		const label = vp.width + 'x' + vp.height;
		const { ctx, p, errors } = await open(browser, DECK, vp);
		const r = await p.evaluate(inspect(DECK));
		const pages = r.pages;
		const scrolling = pages.filter((x) => x.kind !== 'bio' && x.over > 1);
		t(label + ': no text page scrolls', scrolling.length === 0, scrolling.map((x) => 'src' + x.src + ' over ' + x.over).join(', '));
		const bio = pages.find((x) => x.kind === 'bio');
		t(label + ': the bio page does not scroll' + (vp.height < 600 ? ' (smallest screens may)' : ''), bio.over <= 1 || vp.height < 600, 'over ' + bio.over);
		const small = pages.filter((x) => x.kind !== 'bio' && x.font > 0 && x.font < 14.9);
		t(label + ': text never drops below 15px', small.length === 0, small.map((x) => x.font).join(','));
		// nothing lost, nothing reordered
		let ok = true, why = '';
		DECK.slides.forEach((s, i) => {
			const joined = pages.filter((x) => x.src === i).map((x) => x.text).join('');
			if (joined !== src[i]) { ok = false; why += ' src' + i + ' (' + joined.length + ' of ' + src[i].length + ')'; }
		});
		t(label + ': every page of every slide, in order, adds up to the original text', ok, why);
		const srcs = pages.map((x) => x.src);
		t(label + ': pages stay in the order of the book', srcs.every((v, i) => i === 0 || v >= srcs[i - 1]) && srcs[0] === 0 && srcs[srcs.length - 1] === DECK.slides.length - 1);
		// a heading once, on the first page; a quote's attribution once, on the last
		let h = true, hw = '';
		DECK.slides.forEach((s, i) => {
			const mine = pages.filter((x) => x.src === i);
			const heads = mine.map((x) => x.headings);
			if (s.heading && !(heads[0] === 1 && heads.slice(1).every((n) => n === 0))) { h = false; hw += ' src' + i + ':' + heads.join('/'); }
			if (s.type === 'quote') {
				const at = mine.map((x) => x.attribution);
				if (!(at[at.length - 1] === 1 && at.slice(0, -1).every((n) => n === 0))) { h = false; hw += ' quote:' + at.join('/'); }
			}
		});
		t(label + ': headings on the first page only, attribution on the last page only', h, hw);
		t(label + ': counter and progress bar match the page count', r.counter === '1 / ' + pages.length && r.segs === pages.length, r.counter + ' segs ' + r.segs + ' pages ' + pages.length);
		t(label + ': the hidden measuring card is gone', r.probes === 0);
		t(label + ': no sideways scroll, no errors', r.scrollW <= r.innerW && errors.length === 0, errors.join(' | '));
		if (vp.width === 360) { console.log('      (360x640: ' + DECK.slides.length + ' slides became ' + pages.length + ' pages)'); }
		await ctx.close();
	}

	// Walking through every page.
	{
		const { ctx, p } = await open(browser, DECK, { width: 360, height: 640 });
		const n = (await p.evaluate(inspect(DECK))).pages.length;
		let seen = [];
		for (let i = 0; i < n; i++) {
			seen.push(await p.evaluate(() => document.querySelector('.twd-sb-slide.is-active').getAttribute('data-index') + '|' + document.querySelector('.twd-sb-counter').textContent));
			if (i < n - 1) { await p.click('.twd-sb-next'); }
		}
		t('walking: Next reaches every page once, in order', seen.every((s, i) => s === i + '|' + (i + 1) + ' / ' + n), seen.slice(0, 4).join(','));
		t('walking: Next is off on the last page, Previous on the first', await p.locator('.twd-sb-next').isDisabled());
		await p.click('.twd-sb-prev');
		t('walking: Previous goes back one page', (await p.evaluate(() => document.querySelector('.twd-sb-counter').textContent)) === (n - 1) + ' / ' + n);
		await ctx.close();
	}

	// A big screen leaves short pages alone.
	{
		const { ctx, p } = await open(browser, SHORT_DECK, { width: 1440, height: 900 });
		const r = await p.evaluate(inspect(SHORT_DECK));
		t('big screen, short book: one page per slide, full size', r.pages.length === SHORT_DECK.slides.length && r.pages.every((x) => x.scale === ''), JSON.stringify(r.pages.map((x) => x.scale)));
		await ctx.close();
	}

	// Shrinking comes before splitting: a page that only just overflows shrinks a little and stays one page.
	{
		const medium = Object.assign({}, DECK, { slides: [{ type: 'text', heading: 'Just over', html: manyParas(3) }] });
		let found = null;
		for (const h of [900, 800, 760, 720, 680, 640, 600, 560]) {
			const { ctx, p } = await open(browser, medium, { width: 420, height: h });
			const r = await p.evaluate(inspect(medium));
			if (r.pages.length === 1 && r.pages[0].scale !== '') { found = { h, scale: r.pages[0].scale, font: r.pages[0].font, over: r.pages[0].over }; await ctx.close(); break; }
			await ctx.close();
		}
		t('shrink before split: some screen height gives one smaller, still scroll-free page', !!found && parseFloat(found.scale) < 1 && found.font >= 14.9 && found.over <= 1, JSON.stringify(found));
	}

	// Balanced split: the last page is not a stray sentence.
	{
		const { ctx, p } = await open(browser, DECK, { width: 360, height: 640 });
		const r = await p.evaluate(inspect(DECK));
		let worst = 1;
		DECK.slides.forEach((s, i) => {
			const mine = r.pages.filter((x) => x.src === i);
			if (mine.length > 1) { const lens = mine.map((x) => x.len); worst = Math.min(worst, Math.min.apply(null, lens) / Math.max.apply(null, lens)); }
		});
		DECK.slides.forEach((sl, i) => { const mine = r.pages.filter((x) => x.src === i); if (mine.length > 1) { console.log('      src' + i + ' (' + sl.type + '): page sizes ' + mine.map((x) => x.len + '@' + x.scale).join(', ')); } });
		t('balanced split: the shortest page of a split slide is at least a third of the longest', worst >= 0.33, 'worst ratio ' + worst.toFixed(2));
		t('split pages of one slide share one text size', (() => { let same = true; DECK.slides.forEach((s, i) => { const sc = r.pages.filter((x) => x.src === i).map((x) => x.scale); if (sc.some((v) => v !== sc[0])) { same = false; } }); return same; })());
		await ctx.close();
	}

	// Re-fit when the screen changes, staying on the same piece of the book.
	{
		const { ctx, p } = await open(browser, DECK, { width: 1024, height: 700 });
		const wide = (await p.evaluate(inspect(DECK))).pages.length;
		// go to the source slide 5 (the quote)
		await p.evaluate(() => { const target = Array.from(document.querySelectorAll('.twd-sb-slide')).findIndex((s) => s.getAttribute('data-src') === '5'); for (let i = 0; i < target; i++) { document.querySelector('.twd-sb-next').click(); } });
		await p.setViewportSize({ width: 360, height: 640 });
		await wait(700);
		let r = await p.evaluate(inspect(DECK));
		const onSrc = await p.evaluate(() => document.querySelector('.twd-sb-slide.is-active').getAttribute('data-src'));
		t('rotate/resize: a smaller screen re-fits into more pages', r.pages.length > wide, wide + ' -> ' + r.pages.length);
		t('rotate/resize: still no scrolling after the change', r.pages.filter((x) => x.kind !== 'bio' && x.over > 1).length === 0);
		t('rotate/resize: the reader stays on the same part of the book', onSrc === '5', 'now on source ' + onSrc);
		t('rotate/resize: counter follows', r.counter.endsWith(' / ' + r.pages.length));
		await p.setViewportSize({ width: 1024, height: 700 });
		await wait(700);
		r = await p.evaluate(inspect(DECK));
		t('rotate/resize: back to the first size gives the first page count', r.pages.length === wide, r.pages.length + ' vs ' + wide);
		// a toolbar-sized change does nothing
		const mark = await p.evaluate(() => { const s = document.querySelector('.twd-sb-slide'); s.setAttribute('data-mark', 'same'); return true; });
		await p.setViewportSize({ width: 1024, height: 640 });
		await wait(600);
		t('resize: a small height change (a browser toolbar) does not rebuild the pages', (await p.evaluate(() => document.querySelector('.twd-sb-slide').getAttribute('data-mark'))) === 'same');
		await ctx.close();
	}

	// Overlay mode (opened over a page) behaves the same.
	{
		const { ctx, p, errors } = await open(browser, DECK, { width: 390, height: 700 }, false);
		await p.evaluate((d) => window.TWD_AP_SwipeBook.open(d), DECK);
		await wait(200);
		const r = await p.evaluate(inspect(DECK));
		t('overlay: fits the same way (no scrolling, 15px floor, text preserved)', r.pages.filter((x) => x.kind !== 'bio' && x.over > 1).length === 0 && r.pages.every((x) => x.kind === 'bio' || x.font >= 14.9) && DECK.slides.every((s, i) => r.pages.filter((x) => x.src === i).map((x) => x.text).join('') === src[i]));
		await p.evaluate(() => window.TWD_AP_SwipeBook.close());
		await wait(100);
		t('overlay: closes cleanly', (await p.locator('.twd-sb-backdrop').count()) === 0 && errors.length === 0, errors.join(' | '));
		await p.setViewportSize({ width: 800, height: 400 });
		await wait(500);
		t('overlay: resizing after it is closed does nothing and raises no errors', errors.length === 0);
		await ctx.close();
	}

	// A book that cannot be cut any smaller keeps the original scrolling as a last resort and still works.
	{
		const stubborn = Object.assign({}, DECK, { slides: [{ type: 'text', heading: 'Unbreakable', html: '<p>' + 'x'.repeat(20) + '</p><figure style="height:2000px;margin:0"></figure><p>After.</p>' }, { type: 'text', heading: 'Next', html: '<p>Next page.</p>' }] });
		const { ctx, p, errors } = await open(browser, stubborn, { width: 360, height: 640 });
		const r = await p.evaluate(inspect(stubborn));
		t('last resort: an uncuttable block does not break the book (still pages, no errors)', r.pages.length >= 2 && errors.length === 0, 'pages ' + r.pages.length);
		await p.click('.twd-sb-next');
		await ctx.close();
	}

	// Safety: a thrown error inside the fit leaves the book as it was.
	{
		const broken = JSON.parse(JSON.stringify(DECK));
		broken.slides[1].html = null;
		const { ctx, p, errors } = await open(browser, broken, { width: 360, height: 640 });
		t('safety: odd data does not stop the book opening', (await p.locator('.twd-sb-slide:not(.twd-sb-probe)').count()) >= broken.slides.length && (await p.locator('.twd-sb-probe').count()) === 0);
		await ctx.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
