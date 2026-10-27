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
		await page.close();
		({ page } = await openSwipe('top'));
		t('swipe, top only: two buttons at the top and none after the text', (await page.locator('.twd-ap-swipebook-btn').count()) === 2 && (await page.locator('.twd-ap-swipebook-top .twd-ap-swipebook-btn').count()) === 2);
		await page.close();
		({ page } = await openSwipe('bottom'));
		t('swipe, bottom only: no top wrapper, buttons after the text (the behaviour before this version)', (await page.locator('.twd-ap-swipebook-top').count()) === 0 && (await page.locator('.twd-ap-swipebook-btn').count()) === 2);
		await page.close();
		({ page } = await openSwipe('both', 375));
		t('swipe top: on a phone the buttons wrap and nothing scrolls sideways', !(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)));
		await page.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
