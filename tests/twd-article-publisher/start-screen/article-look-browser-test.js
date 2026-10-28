// Real-browser test of "Article look": the real article-content.css with the wrapper the real PHP prints.
// Checks that an untouched site looks exactly as before, and that each choice really changes the page.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/article-look-browser-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-look-'));
const dumpFile = path.join(tmp, 'look.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/article-look-test.php')], { env: Object.assign({}, process.env, { TWD_LOOK_DUMP: dumpFile }) });
const DUMP = JSON.parse(fs.readFileSync(dumpFile, 'utf8'));
const CSS = fs.readFileSync(path.join(plugin, 'assets/article-content.css'), 'utf8');
const relFile = path.join(tmp, 'related.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/related-test.php')], { env: Object.assign({}, process.env, { TWD_RELATED_DUMP: relFile }) });
const RELATED = JSON.parse(fs.readFileSync(relFile, 'utf8')).html;
const gridFile = path.join(tmp, 'related-grid.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/related-test.php')], { env: Object.assign({}, process.env, { TWD_RELATED_DUMP: relFile, TWD_RELATED_DUMP_GRID: gridFile }) });
const RGRID = JSON.parse(fs.readFileSync(gridFile, 'utf8'));
const swFile = path.join(tmp, 'swipe.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/swipe-switch-test.php')], { env: Object.assign({}, process.env, { TWD_SWIPE_DUMP: swFile }) });
const SWIPE = JSON.parse(fs.readFileSync(swFile, 'utf8'));
const GRIDCSS = fs.readFileSync(path.join(plugin, 'assets/articles-grid.css'), 'utf8');
const BODY = '<h2>A heading</h2><p>Some text with a <a href="#x">link</a> in it.</p><blockquote>A quote</blockquote>';

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }

async function load(browser, wrapper, width) {
	const page = await browser.newPage({ viewport: { width: width || 1000, height: 800 } });
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	// Same page chrome a theme would add: its own body font and link colour, which the plugin must beat.
	const html = wrapper.replace('<p>Body</p>', BODY);
	await page.setContent('<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:"Courier New",monospace;color:#222}a{color:hotpink}h2{color:orange}</style><style>' + CSS + '</style></head><body>' + html + '</body></html>');
	return { page, errors };
}
const css = (page, sel, prop) => page.locator(sel).first().evaluate((el, p) => getComputedStyle(el)[p], prop);

(async () => {
	const browser = await chromium.launch();

	let { page } = await load(browser, DUMP.default);
	t('default: text colour is the plugin default', (await css(page, '.twd-ap-article-content p', 'color')) === 'rgb(31, 41, 55)');
	t('default: headings follow the text colour', (await css(page, '.twd-ap-article-content h2', 'color')) === 'rgb(31, 41, 55)');
	t('default: links are the plugin blue', (await css(page, '.twd-ap-article-content a', 'color')) === 'rgb(37, 99, 235)');
	t('default: the quote bar is the link blue and its text is grey', (await css(page, 'blockquote', 'borderLeftColor')) === 'rgb(37, 99, 235)' && (await css(page, 'blockquote', 'color')) === 'rgb(107, 114, 128)');
	t('default: 17px text, 1.75 line height, the plain system font (not the theme font)', (await css(page, '.twd-ap-article-content', 'fontSize')) === '17px' && (await css(page, '.twd-ap-article-content', 'lineHeight')) === '29.75px' && !/Courier/.test(await css(page, '.twd-ap-article-content', 'fontFamily')));
	const defaultBox = await page.locator('.twd-ap-article-content p').first().boundingBox();
	await page.close();

	({ page } = await load(browser, DUMP.custom));
	t('custom: text colour is the chosen colour', (await css(page, '.twd-ap-article-content p', 'color')) === 'rgb(17, 34, 51)');
	t('custom: headings use the heading colour, not the text colour', (await css(page, '.twd-ap-article-content h2', 'color')) === 'rgb(68, 85, 102)');
	t('custom: links are the chosen link colour', (await css(page, '.twd-ap-article-content a', 'color')) === 'rgb(122, 155, 118)');
	t('custom: the quote bar and text use the quote colour', (await css(page, 'blockquote', 'borderLeftColor')) === 'rgb(170, 187, 204)' && (await css(page, 'blockquote', 'color')) === 'rgb(170, 187, 204)');
	t('custom: the theme font is used when chosen', /Courier/.test(await css(page, '.twd-ap-article-content', 'fontFamily')));
	t('custom: compact line spacing', (await css(page, '.twd-ap-article-content', 'lineHeight')) === '26.35px');
	const customBox = await page.locator('.twd-ap-article-content p').first().boundingBox();
	t('custom: the layout really changed from the default', JSON.stringify(customBox) !== JSON.stringify(defaultBox));
	await page.close();

	// Serif + large + relaxed, and the phone size.
	const serif = DUMP.custom.replace('font-family:inherit !important;', '--twd-ap-content-font:Georgia, &quot;Times New Roman&quot;, Times, serif;').replace('--twd-ap-content-leading:1.55;', '--twd-ap-content-leading:1.95;').replace('style="', 'style="--twd-ap-content-size:19px;--twd-ap-content-size-m:17px;');
	({ page } = await load(browser, serif));
	t('large: 19px text on a desktop', (await css(page, '.twd-ap-article-content', 'fontSize')) === '19px');
	t('serif: Georgia is the font', /Georgia/.test(await css(page, '.twd-ap-article-content', 'fontFamily')));
	t('relaxed: 1.95 line height', (await css(page, '.twd-ap-article-content', 'lineHeight')) === '37.05px');
	await page.close();
	({ page } = await load(browser, serif, 375));
	t('large on a phone: 17px text', (await css(page, '.twd-ap-article-content', 'fontSize')) === '17px');
	await page.close();
	({ page } = await load(browser, DUMP.default, 375));
	t('default on a phone: still 16px', (await css(page, '.twd-ap-article-content', 'fontSize')) === '16px');
	await page.close();

	// Heading sizes (1.48.0): the plugin sizes unless the owner set some; the same choice scaled down on a phone.
	{
		const BODY3 = '<h2>Main heading</h2><h3>Sub heading</h3><h4>Small heading</h4><p>Text</p>';
		const loadH = async (wrapper, width) => {
			const pg = await browser.newPage({ viewport: { width, height: 800 } });
			await pg.setContent('<!doctype html><html><head><meta charset="utf-8"><style>h2{font-size:60px}h3{font-size:50px}h4{font-size:40px}</style><style>' + CSS + '</style></head><body>' + wrapper.replace('<p>Body</p>', BODY3) + '</body></html>');
			return pg;
		};
		const sizes = (pg) => pg.evaluate(() => ['h2', 'h3', 'h4'].map((h) => getComputedStyle(document.querySelector('.twd-ap-article-content ' + h)).fontSize));
		let pg = await loadH(DUMP.default, 1000);
		t('headings default: 26, 21 and 18px, whatever the theme says', JSON.stringify(await sizes(pg)) === JSON.stringify(['26px', '21px', '18px']));
		await pg.close();
		pg = await loadH(DUMP.default, 375);
		t('headings default on a phone: 22, 19 and 18px', JSON.stringify(await sizes(pg)) === JSON.stringify(['22px', '19px', '18px']));
		await pg.close();
		pg = await loadH(DUMP.headings, 1000);
		t('headings chosen: 40, 30 and 20px on a desktop', JSON.stringify(await sizes(pg)) === JSON.stringify(['40px', '30px', '20px']), JSON.stringify(await sizes(pg)));
		await pg.close();
		pg = await loadH(DUMP.headings, 375);
		t('headings chosen on a phone: 34, 26 and 17px (85%)', JSON.stringify(await sizes(pg)) === JSON.stringify(['34px', '26px', '17px']), JSON.stringify(await sizes(pg)));
		await pg.close();
	}

	// "More in {category}" at the end of an article, with the real article and grid stylesheets.
	{
		const open3 = async (width) => {
			const page = await browser.newPage({ viewport: { width, height: 900 } });
			const errors = [];
			page.on('pageerror', (e) => errors.push(String(e)));
			await page.setContent('<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:"Courier New",monospace;margin:0}a{color:hotpink}h2{color:orange}ul{list-style:disc}</style><style>' + CSS + '</style><style>' + GRIDCSS + '</style></head><body>' + RELATED + '</body></html>');
			return { page, errors };
		};
		let { page, errors } = await open3(1000);
		const box = await page.locator('.twd-ap-related').boundingBox();
		const art = await page.locator('.twd-ap-article-content').boundingBox();
		t('related: the block sits below the article and is centred, no wider than 720px', box.y >= art.y + art.height && box.width <= 721 && Math.abs((box.x + box.width / 2) - 500) < 2, JSON.stringify([box, art]));
		t('related: four links, the heading links to the category', (await page.locator('.twd-ap-related-list li').count()) === 4 && (await page.locator('.twd-ap-related-title a').getAttribute('href')) === 'https://example.test/category/anxiety/');
		const st = await page.evaluate(() => { const c = (s, p) => getComputedStyle(document.querySelector(s))[p]; return { link: c('.twd-ap-related-list a', 'color'), title: c('.twd-ap-related-title', 'color'), titleLink: c('.twd-ap-related-title a', 'color'), date: c('.twd-ap-related-date', 'color'), bullet: c('.twd-ap-related-list', 'listStyleType'), font: c('.twd-ap-related', 'fontFamily') }; });
		t('related: the plugin beats the theme (accent links, no bullets, its own font, grey dates)', st.link === 'rgb(37, 99, 235)' && st.titleLink === 'rgb(37, 99, 235)' && st.title === 'rgb(31, 41, 55)' && st.date === 'rgb(107, 114, 128)' && st.bullet === 'none' && !/Courier/.test(st.font), JSON.stringify(st));
		const rel = await page.locator('.twd-ap-related').boundingBox();
		const nav = await page.locator('.twd-ap-related .twd-ap-articles-topics').boundingBox();
		t('related: the Browse by topic row sits inside the block, under the list', nav && nav.y > (await page.locator('.twd-ap-related-list').boundingBox()).y && nav.x >= rel.x - 1 && nav.x + nav.width <= rel.x + rel.width + 1, JSON.stringify([nav, rel]));
		t('related: this category is bold text, the others and All articles are links', (await page.locator('.twd-ap-related .twd-ap-topics-current span').count()) === 1 && (await page.locator('.twd-ap-related .twd-ap-articles-topics a').count()) >= 2 && (await page.locator('.twd-ap-related .twd-ap-topics-all a').innerText()) === 'All articles');
		t('related: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await page.close();
		({ page } = await open3(375));
		t('related: on a phone nothing scrolls sideways', !(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)));
		await page.close();
	}

	// The related articles as a grid, and the Browse by topic row (1.48.0), with the real stylesheets, in a 700px article column.
	{
		const openGrid = async (key, width, column) => {
			const pg = await browser.newPage({ viewport: { width, height: 900 } });
			const errors = [];
			pg.on('pageerror', (e) => errors.push(String(e)));
			const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="600" height="400" fill="#7a9b76"/></svg>';
			await pg.route('https://example.test/**', (r) => r.fulfill({ contentType: 'image/svg+xml', body: svg }));
			await pg.setContent('<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:"Courier New",monospace;margin:0}a{color:hotpink}h2{color:orange}ul{list-style:disc}</style><style>' + CSS + '</style><style>' + GRIDCSS + '</style></head><body><div id="col" style="width:' + (column || 700) + 'px;max-width:100%;margin:0 auto;box-sizing:border-box">' + RGRID[key] + '</div></body></html>');
			return { pg, errors };
		};
		const cols = (pg) => pg.$$eval('.twd-ap-related-card', (c) => Array.from(new Set(c.map((x) => Math.round(x.getBoundingClientRect().left)))).length);
		let { pg, errors } = await openGrid('wide', 1200);
		const rb = await pg.locator('.twd-ap-related').boundingBox();
		t('related grid: the block is 900px wide and centred on the 700px article column (not stuck at 720)', Math.abs(rb.width - 900) <= 1 && Math.abs((rb.x + rb.width / 2) - 600) <= 2, JSON.stringify(rb));
		t('related grid: 3 boxes across, 4 boxes in all (so 3 and 1)', (await cols(pg)) === 3 && (await pg.locator('.twd-ap-related-card').count()) === 4);
		const bx = await pg.$$eval('.twd-ap-related-card', (c) => c.map((x) => { const r = x.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.top), Math.round(r.width)]; }));
		t('related grid: boxes are equal width and the fourth starts a new row under the first', bx[0][2] === bx[1][2] && bx[1][2] === bx[2][2] && bx[3][1] > bx[0][1] && bx[3][0] === bx[0][0], JSON.stringify(bx));
		const one = await pg.evaluate(() => { const a = document.querySelector('.twd-ap-related-card a'); const th = a.querySelector('.twd-ap-related-thumb'); const cs = getComputedStyle(a); const tl = getComputedStyle(a.querySelector('.twd-ap-related-card-title')); const li = getComputedStyle(document.querySelector('.twd-ap-related-card')); return { radius: cs.borderTopLeftRadius, shadow: cs.boxShadow !== 'none', deco: cs.textDecorationLine, thumbH: th.getBoundingClientRect().height, bg: getComputedStyle(th).backgroundImage.slice(0, 12), title: tl.color, titleSize: tl.fontSize, bullet: li.listStyleType, vis: document.querySelector('.twd-ap-related-thumb').getBoundingClientRect().width > 100 }; });
		t('related grid: each box looks like a card (rounded, shadow, no underline, no bullet), 150px picture, dark title', one.radius === '12px' && one.shadow && one.deco === 'none' && Math.round(one.thumbH) === 150 && one.title === 'rgb(31, 41, 55)' && one.bullet === 'none', JSON.stringify(one));
		t('related grid: the picture is the article\'s own, the others have the plain placeholder', (await pg.locator('.twd-ap-related-thumb-placeholder').count()) >= 1 && /url\(/.test(one.bg));
		t('related grid: the page does not scroll sideways', !(await pg.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)));
		t('related grid: no JavaScript errors', errors.length === 0, errors.join(' | '));
		await pg.close();

		({ pg } = await openGrid('wide', 800));
		const rb2 = await pg.locator('.twd-ap-related').boundingBox();
		t('related grid on a screen of 800: the block takes the screen less 16px each side, 3 across, no sideways scroll', Math.abs(rb2.width - 768) <= 1 && Math.abs(rb2.x - 16) <= 2 && (await cols(pg)) === 3 && !(await pg.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)), JSON.stringify(rb2) + ' cols ' + (await cols(pg)));
		await pg.close();
		({ pg } = await openGrid('wide', 700));
		t('related grid on a screen of 700: 2 across', (await cols(pg)) === 2);
		await pg.close();
		({ pg } = await openGrid('wide', 400, 400));
		t('related grid on a phone (400): 1 across, inside the screen, no sideways scroll', (await cols(pg)) === 1 && !(await pg.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)) && (await pg.locator('.twd-ap-related').boundingBox()).x >= 15);
		await pg.close();
		({ pg } = await openGrid('two', 1200));
		const two = await pg.$$eval('.twd-ap-related-card', (c) => c.map((x) => Math.round(x.getBoundingClientRect().width)));
		t('related grid: two boxes keep the width of a box in a row of three (they do not stretch)', two.length === 2 && two[0] > 250 && two[0] < 300, JSON.stringify(two));
		await pg.close();
		({ pg } = await openGrid('one', 1200));
		t('related grid: one box keeps the width of a box in a row of three', (await pg.$$eval('.twd-ap-related-card', (c) => c.map((x) => Math.round(x.getBoundingClientRect().width))))[0] < 300);
		await pg.close();
		({ pg } = await openGrid('4-3', 1200));
		const r43 = await pg.locator('.twd-ap-related-thumb').first().boundingBox();
		t('related grid: the 4:3 picture shape is used', Math.abs(r43.width / r43.height - 4 / 3) < 0.02, JSON.stringify(r43));
		await pg.close();
		({ pg } = await openGrid('square', 1200));
		const rsq = await pg.locator('.twd-ap-related-thumb').first().boundingBox();
		t('related grid: the square picture shape is used', Math.abs(rsq.width / rsq.height - 1) < 0.02, JSON.stringify(rsq));
		await pg.close();
		({ pg } = await openGrid('nopics', 1200));
		t('related grid: with pictures switched off the boxes are just title and date', (await pg.locator('.twd-ap-related-thumb').count()) === 0 && (await pg.locator('.twd-ap-related-card').count()) === 4);
		await pg.close();
		({ pg } = await openGrid('list', 1200));
		const lb = await pg.locator('.twd-ap-related').boundingBox();
		t('related list (the original layout) is unchanged: 720px at most, no cards', lb.width <= 721 && (await pg.locator('.twd-ap-related-card').count()) === 0);
		await pg.close();

		// the Browse by topic row: larger, and centred when chosen
		({ pg } = await openGrid('wide', 1200));
		const tc = await pg.evaluate(() => { const nav = document.querySelector('.twd-ap-articles-topics'); const ul = nav.querySelector('ul'); const r = nav.getBoundingClientRect(); const li = Array.from(ul.querySelectorAll('li')).map((x) => x.getBoundingClientRect()); const left = Math.min.apply(null, li.map((x) => x.left)); const right = Math.max.apply(null, li.map((x) => x.right)); return { cls: nav.className, justify: getComputedStyle(ul).justifyContent, align: getComputedStyle(nav).textAlign, gapL: left - r.left, gapR: r.right - right, link: getComputedStyle(nav.querySelector('li a')).fontSize, title: getComputedStyle(nav.querySelector('.twd-ap-articles-topics-title')).fontSize, titleW: nav.querySelector('.twd-ap-articles-topics-title').getBoundingClientRect() }; });
		t('topics row, centred: the links sit in the middle of the block (equal space each side)', /twd-ap-topics-center/.test(tc.cls) && tc.justify === 'center' && Math.abs(tc.gapL - tc.gapR) <= 3 && tc.gapL > 20, JSON.stringify(tc));
		t('topics row: a little larger than before (links 16px, title 14px)', tc.link === '16px' && tc.title === '14px', JSON.stringify([tc.link, tc.title]));
		await pg.close();
		({ pg } = await openGrid('left', 1200));
		const tl = await pg.evaluate(() => { const nav = document.querySelector('.twd-ap-articles-topics'); const r = nav.getBoundingClientRect(); const first = nav.querySelector('li').getBoundingClientRect(); return { cls: nav.className, justify: getComputedStyle(nav.querySelector('ul')).justifyContent, gapL: first.left - r.left }; });
		t('topics row, left aligned: the links start at the left edge', !/twd-ap-topics-center/.test(tl.cls) && tl.gapL <= 2 && tl.justify !== 'center', JSON.stringify(tl));
		await pg.close();
	}

	// The swipe book button under the title.
	{
		const openSwipe = async (pos, width) => {
			const page = await browser.newPage({ viewport: { width: width || 1000, height: 900 } });
			const errors = [];
			page.on('pageerror', (e) => errors.push(String(e)));
			await page.setContent('<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:"Courier New",monospace;margin:0}a{color:hotpink;text-decoration:underline}h1{margin:20px}</style><style>' + CSS + '</style><style>' + GRIDCSS + '</style></head><body><h1 id="post-title">The article title</h1><div class="twd-ap-article-content">' + SWIPE[pos] + '</div></body></html>');
			return { page, errors };
		};
		let { page, errors } = await openSwipe('both');
		const title = await page.locator('#post-title').boundingBox();
		const topBtn = await page.locator('.twd-ap-swipebook-top .twd-ap-swipebook-btn').first().boundingBox();
		const heading = await page.locator('.twd-ap-article-content h2').boundingBox();
		t('swipe top: the button is straight under the title and above the first heading', topBtn.y > title.y + title.height && topBtn.y + topBtn.height <= heading.y, JSON.stringify([title, topBtn, heading]));
		const btns = await page.$$eval('.twd-ap-swipebook-top .twd-ap-swipebook-btn', (b) => b.map((x) => Math.round(x.getBoundingClientRect().top)));
		t('swipe top: the swipe book and Summary Book buttons sit side by side', btns.length === 2 && btns[0] === btns[1], JSON.stringify(btns));
		const style = await page.locator('.twd-ap-swipebook-top .twd-ap-swipebook-btn').first().evaluate((el) => { const c = getComputedStyle(el); return { color: c.color, border: c.borderTopColor, radius: parseFloat(c.borderTopLeftRadius), deco: c.textDecorationLine }; });
		t('swipe top: it looks like the pill (sage colour, rounded, no underline) whatever the theme does to links', style.color === 'rgb(91, 138, 114)' && style.radius >= 20 && style.deco === 'none', JSON.stringify(style));
		t('swipe both: the same button is also after the text', (await page.locator('.twd-ap-swipebook-btn').count()) === 4 && (await page.locator('.twd-ap-swipebook-btn').nth(2).boundingBox()).y > (await page.locator('.twd-ap-article-content p').first().boundingBox()).y);
		t('swipe top: no JavaScript errors', errors.length === 0, errors.join(' | '));
		// The button at the end of the article: "Share as a swipe book", on the right.
		const endBtn = page.locator('.twd-ap-swipebook-row-end a.twd-ap-swipebook-btn').first();
		const endBox = await endBtn.boundingBox();
		const wrapBox = await page.locator('.twd-ap-article-content').boundingBox();
		t('swipe end: it reads "Share as a swipe book" (the one under the title still reads "View as a swipe book")', (await endBtn.innerText()).startsWith('Share as a swipe book') && (await page.locator('.twd-ap-swipebook-top a.twd-ap-swipebook-btn').first().innerText()).startsWith('View as a swipe book'));
		t('swipe end: it sits on the right edge of the article column', Math.abs((endBox.x + endBox.width) - (wrapBox.x + wrapBox.width)) <= 2 && endBox.x > wrapBox.x + wrapBox.width / 2, JSON.stringify([endBox, wrapBox]));
		t('swipe end: the Summary Book button there is on the right too', (await page.$$eval('.twd-ap-swipebook-row-end a', (a) => a.map((x) => Math.round(x.getBoundingClientRect().right)))).every((r, _i, arr) => Math.abs(r - arr[0]) <= 2));
		await page.close();
		({ page } = await openSwipe('top'));
		t('swipe, top only: two buttons at the top and none after the text', (await page.locator('.twd-ap-swipebook-btn').count()) === 2 && (await page.locator('.twd-ap-swipebook-top .twd-ap-swipebook-btn').count()) === 2);
		await page.close();
		({ page } = await openSwipe('bottom'));
		t('swipe, bottom only: no top wrapper, buttons after the text (the behaviour before this version)', (await page.locator('.twd-ap-swipebook-top').count()) === 0 && (await page.locator('.twd-ap-swipebook-btn').count()) === 2);
		await page.close();
		({ page } = await openSwipe('both', 375));
		t('swipe top: on a phone the buttons wrap and nothing scrolls sideways', !(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)));
		const pEnd = await page.locator('.twd-ap-swipebook-row-end a.twd-ap-swipebook-btn').first().boundingBox();
		const pWrap = await page.locator('.twd-ap-article-content').boundingBox();
		t('swipe end: on a phone it is still on the right edge', Math.abs((pEnd.x + pEnd.width) - (pWrap.x + pWrap.width)) <= 2, JSON.stringify([pEnd, pWrap]));
		await page.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
