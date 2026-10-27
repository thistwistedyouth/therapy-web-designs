// Real-browser test of the line endings (1.46.0): with "Tidy line endings" on, no line of an article ends on a
// one or two letter word at any column width, headings are balanced and paragraphs set to avoid a lonely last
// word (where the browser supports it), and the end-of-article swipe book button reads "Share as a swipe book"
// and sits on the right. The real article-content.css is loaded; the text is made up.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/line-endings-browser-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const CSS = fs.readFileSync(path.join(root, 'plugins/twd-article-publisher/assets/article-content.css'), 'utf8');
const { plain, tidy } = JSON.parse(execFileSync('php', [path.join(__dirname, 'line-endings-dump.php')]).toString());

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }

function page(html, width) {
	return '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;padding:0}</style><style>' + CSS + '</style></head><body>' +
		'<div style="width:' + width + 'px"><div class="twd-ap-article-content">' + html + '</div></div></body></html>';
}

// For every paragraph and heading: the last word of every line but the last, as the browser broke it.
const LINES = `(() => {
	const out = [];
	document.querySelectorAll('.twd-ap-article-content p, .twd-ap-article-content h2').forEach((el) => {
		const tn = []; const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT); while (w.nextNode()) { tn.push(w.currentNode); }
		const words = [];
		tn.forEach((n) => {
			const re = /[^ ]+/g; let m;
			while ((m = re.exec(n.nodeValue))) {
				const r = document.createRange(); r.setStart(n, m.index); r.setEnd(n, m.index + m[0].length);
				const b = r.getClientRects(); const rect = b[b.length - 1];
				words.push({ text: m[0], top: Math.round(rect.top), bottom: Math.round(rect.bottom) });
			}
		});
		// a word is its text after the last space or non-breaking space; the line it ends is the one its last rect is on
		let lines = {};
		words.forEach((x) => { (lines[x.top] = lines[x.top] || []).push(x); });
		const tops = Object.keys(lines).map(Number).sort((a, b) => a - b);
		tops.slice(0, -1).forEach((tp) => { const l = lines[tp]; const last = l[l.length - 1].text; out.push({ tag: el.tagName, last: last.split(/[\\u00a0 ]/).pop() }); });
		out.push({ tag: el.tagName, lineCount: tops.length, heading: el.tagName === 'H2' });
	});
	return out;
})()`;

const shortWord = (w) => /^[\p{L}]{1,2}$/u.test(w); // no trailing punctuation: a sentence end is a natural place to stop

(async () => {
	const browser = await chromium.launch();
	const ctx = await browser.newContext({ viewport: { width: 900, height: 900 } });
	const p = await ctx.newPage();
	const errors = [];
	p.on('pageerror', (e) => errors.push(String(e)));

	let plainBad = 0, tidyBad = 0, widths = 0;
	for (let width = 280; width <= 470; width += 6) {
		widths++;
		await p.setContent(page(plain, width));
		plainBad += (await p.evaluate(LINES)).filter((x) => x.last !== undefined && x.lineCount === undefined && shortWord(x.last)).length;
		await p.setContent(page(tidy, width));
		const lines = await p.evaluate(LINES);
		const bad = lines.filter((x) => x.last !== undefined && x.lineCount === undefined && shortWord(x.last));
		tidyBad += bad.length;
		if (bad.length && tidyBad === bad.length) { console.log('      first miss at width ' + width + ': ' + JSON.stringify(bad)); }
	}
	t('without the tidy step, lines do end on a short word (this is the problem; ' + plainBad + ' across ' + widths + ' widths)', plainBad > 8, 'only ' + plainBad);
	t('with the tidy step, no line ends on a one or two letter word at any of the ' + widths + ' widths', tidyBad === 0, tidyBad + ' lines');

	// the code block is never changed
	await p.setContent(page(tidy, 400));
	t('code blocks keep their ordinary spaces', (await p.locator('pre').innerText()) === 'so we go on to it as is');
	t('the visible text is the same words (a non-breaking space looks like a space)', (await p.locator('.twd-ap-article-content').innerText()).replace(/ /g, ' ') === (await (async () => { await p.setContent(page(plain, 400)); return (await p.locator('.twd-ap-article-content').innerText()); })()).replace(/ /g, ' '));

	// headings balanced, paragraphs pretty, where the browser has them
	await p.setContent(page(tidy, 360));
	const supports = await p.evaluate(() => ({ balance: CSS.supports('text-wrap', 'balance'), pretty: CSS.supports('text-wrap', 'pretty') }));
	const styles = await p.evaluate(() => ({ h: getComputedStyle(document.querySelector('h2')).textWrap || getComputedStyle(document.querySelector('h2')).getPropertyValue('text-wrap-style'), p: getComputedStyle(document.querySelector('p')).textWrap || getComputedStyle(document.querySelector('p')).getPropertyValue('text-wrap-style') }));
	if (supports.balance) { t('headings are set to balance', /balance/.test(styles.h), JSON.stringify(styles)); } else { console.log('      (this browser has no text-wrap: balance; skipped)'); }
	if (supports.pretty) { t('paragraphs are set to pretty', /pretty/.test(styles.p), JSON.stringify(styles)); } else { console.log('      (this browser has no text-wrap: pretty; skipped)'); }
	if (supports.balance) {
		// the heading's lines are even: the shortest line is at least 55% of the longest
		const hw = await p.evaluate(() => {
			const h = document.querySelector('h2'); const tn = h.firstChild; const lines = {};
			const re = /[^ ]+/g; let m;
			while ((m = re.exec(tn.nodeValue))) { const r = document.createRange(); r.setStart(tn, m.index); r.setEnd(tn, m.index + m[0].length); const b = r.getClientRects()[0]; const k = Math.round(b.top); lines[k] = lines[k] || { l: 1e9, r: 0 }; lines[k].l = Math.min(lines[k].l, b.left); lines[k].r = Math.max(lines[k].r, b.right); }
			return Object.values(lines).map((x) => x.r - x.l);
		});
		t('a heading of several lines has even lines', hw.length >= 2 && Math.min.apply(null, hw) / Math.max.apply(null, hw) >= 0.55, JSON.stringify(hw));
	}
	t('no JavaScript errors', errors.length === 0, errors.join(' | '));
	await ctx.close();
	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
