// Real-browser test of the Book Maker style JSON import in the New Article popup (1.44.0): pasting a book
// asks the server to read it, fills the article fields, carries the cards along as the Summary Book draft,
// and the Summary Book review screen handles picture cards. The server's reply is the REAL output of
// TWD_AP_Book_Import (dumped by book-import-test.php); only the transport is faked. Made-up book.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/book-import-browser-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-book-'));
const REST = 'https://example.test/wp-json/twd-publisher/v1';

const dump = path.join(tmp, 'book.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/book-import-test.php')], { env: Object.assign({}, process.env, { TWD_BOOK_DUMP: dump }), stdio: 'pipe' });
const { book: BOOK, result: RESULT } = JSON.parse(fs.readFileSync(dump, 'utf8'));
const pageFile = path.join(tmp, 'page.html');
execFileSync('php', [path.join(__dirname, 'render.php'), 'key-off', pageFile]);

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

async function open(browser, o) {
	o = Object.assign({ import: { status: 200, json: RESULT }, editing: false }, o || {});
	const ctx = await browser.newContext({ viewport: { width: 1100, height: 900 } });
	const page = await ctx.newPage();
	const log = { imports: [], saves: [], sbSaves: [], errors: [] };
	page.on('pageerror', (e) => log.errors.push(String(e)));
	await page.route(REST + '/**', async (route) => {
		const req = route.request();
		const url = req.url().replace(REST, '');
		const json = (status, body) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
		if (url === '/book-import') {
			log.imports.push({ body: JSON.parse(req.postData() || '{}'), nonce: req.headers()['x-wp-nonce'] });
			if (o.import === 'abort') { return route.abort(); }
			return json(o.import.status, o.import.json);
		}
		if (/^\/posts\/5\/summary-book$/.test(url)) {
			if (req.method() === 'POST') { log.sbSaves.push(JSON.parse(req.postData() || '{}')); return json(200, { draft: [] }); }
			return json(200, { draft: o.draft || [], published: [] });
		}
		if (req.method() === 'POST' && /^\/posts(\/\d+)?$/.test(url)) {
			log.saves.push(JSON.parse(req.postData() || '{}'));
			return json(200, { id: 9, status: 'publish', link: 'https://example.test/a/' });
		}
		if (url.startsWith('/posts/')) { return json(200, { id: 5, title: 'Existing article', status: 'publish', link: 'https://example.test/a/', content_html: '<p>Body</p>', summary_book_has_draft: true, summary_book_is_published: false, categories: [], tags: [] }); }
		return json(200, []);
	});
	await page.goto('file://' + pageFile);
	await page.evaluate(() => { window.TWD_AP = { restUrl: 'https://example.test/wp-json/twd-publisher/v1', nonce: 'nonce-1', uncategorizedId: 1, yoastEnabled: 0, currentPostId: 5, canEditThis: 1, adminEditUrl: '', aiKeyConfigured: 0, i18n: { saving: 'Saving', error: 'Error', confirmClose: 'Discard?', confirmDelete: 'Delete?' } }; });
	await page.addScriptTag({ path: path.join(plugin, 'assets/publisher.js') });
	await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
	return { ctx, page, log };
}

async function toJsonTab(page, text) {
	await page.click('#twd-ap-new-btn');
	await page.click('#twd-ap-tab-json');
	await page.fill('#twd-ap-json-input', text);
}

(async () => {
	const browser = await chromium.launch();

	// 1. Pasting a book.
	{
		const { ctx, page, log } = await open(browser);
		await toJsonTab(page, JSON.stringify(BOOK));
		await page.click('#twd-ap-json-fill-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-title').value !== '');
		t('book: the server is asked once, with the whole book and the nonce', log.imports.length === 1 && log.imports[0].body.cards.length === BOOK.cards.length && log.imports[0].nonce === 'nonce-1' && log.imports[0].body.title === BOOK.title);
		t('book: the title is filled', (await page.inputValue('#twd-ap-title')) === RESULT.title);
		t('book: the description goes in the excerpt', (await page.inputValue('#twd-ap-excerpt')) === RESULT.meta_description && RESULT.meta_description.length > 20);
		const html = await page.evaluate(() => document.getElementById('twd-ap-visual-editor').innerHTML);
		t('book: the article text is in the editor with its chapter headings and quote', html.includes('<h2>Prologue - The First Step</h2>') && html.includes('<blockquote>') && html.includes('real.webp'));
		t('book: it lands on the Visual tab for review', await page.locator('#twd-ap-visual-editor').isVisible());
		const status = await page.locator('#twd-ap-status').innerText();
		t('book: the status says what was read, what was left out and what to do next', status.includes('8 cards were read') && status.includes('picture card was left out') && status.includes('Summary Book draft') && status.includes('before they go public'), status);
		t('book: no title or tags were invented', (await page.inputValue('#twd-ap-tags')) === '');
		// the cards ride along with the save as the Summary Book draft
		await page.click('#twd-ap-publish-btn');
		await page.waitForFunction(() => true);
		await wait(500);
		const save = log.saves[log.saves.length - 1];
		t('book: saving sends the cards as the Summary Book draft (and nothing publishes them)', save && Array.isArray(save.summary_book_draft) && save.summary_book_draft.length === 8 && save.summary_book_draft.some((c) => c.type === 'image' && c.src === 'https://pictures.example.org/real.webp') && !('summary_book_published' in save), JSON.stringify(save && Object.keys(save)));
		t('book: no JavaScript errors', log.errors.length === 0, log.errors.join(' | '));
		await ctx.close();
	}

	// 2. An ordinary article JSON still works the old way and never asks the book route.
	{
		const { ctx, page, log } = await open(browser);
		await toJsonTab(page, JSON.stringify({ title: 'Plain article', meta_description: 'Plain description', html: '<p>Plain body</p>', summary_book: { slides: [{ type: 'text', text: 'One card' }] } }));
		await page.click('#twd-ap-json-fill-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Plain article');
		t('article JSON: filled as before, the book route is not used', log.imports.length === 0 && (await page.inputValue('#twd-ap-excerpt')) === 'Plain description');
		await ctx.close();
	}

	// 3. A JSON with cards AND html is an article, not a book.
	{
		const { ctx, page, log } = await open(browser);
		await toJsonTab(page, JSON.stringify({ title: 'Has both', html: '<p>Body</p>', cards: [{ type: 'insight', text: 'x' }] }));
		await page.click('#twd-ap-json-fill-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Has both');
		t('a JSON with html is treated as an article even if it also has cards', log.imports.length === 0);
		await ctx.close();
	}

	// 4. Server says no.
	{
		const { ctx, page, log } = await open(browser, { import: { status: 400, json: { code: 'twd_ap_book_empty', message: 'No cards with any text in them were found in that JSON.' } } });
		await toJsonTab(page, JSON.stringify({ cards: [{ type: 'insight', text: '' }] }));
		await page.evaluate(() => { document.getElementById('twd-ap-title').value = 'Keep me'; });
		await page.click('#twd-ap-json-fill-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-status').innerText.includes('No cards'));
		t('refused book: the server\'s plain message is shown and nothing is overwritten', (await page.inputValue('#twd-ap-title')) === 'Keep me');
		await ctx.close();
	}
	{
		const { ctx, page } = await open(browser, { import: 'abort' });
		await toJsonTab(page, JSON.stringify(BOOK));
		await page.evaluate(() => { document.getElementById('twd-ap-title').value = 'Keep me too'; });
		await page.click('#twd-ap-json-fill-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-status').innerText.includes('Something went wrong'));
		t('network failure: a plain message and the fields are untouched', (await page.inputValue('#twd-ap-title')) === 'Keep me too');
		await ctx.close();
	}
	{
		const { ctx, page, log } = await open(browser);
		await toJsonTab(page, '{ not json');
		await page.click('#twd-ap-json-fill-btn');
		t('invalid JSON: the old message, and no request', (await page.locator('#twd-ap-status').innerText()).includes('not valid JSON') && log.imports.length === 0);
		await ctx.close();
	}

	// 5. The Summary Book review screen and picture cards.
	{
		const cards = RESULT.summary_book.slides;
		const { ctx, page, log } = await open(browser, { draft: cards });
		await page.click('#twd-ap-edit-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
		await page.click('#twd-ap-summary-book-btn');
		await page.waitForSelector('.twd-ap-sb-card-row');
		const rows = await page.locator('.twd-ap-sb-card-row').count();
		t('review screen: every card is a row', rows === cards.length, rows + ' rows');
		t('review screen: Image is a choice of card type', (await page.locator('.twd-ap-sb-card-row').first().locator('select option').allInnerTexts()).join(',') === 'Text,Quote,Question,Image');
		const imgRow = page.locator('.twd-ap-sb-card-row[data-type="image"]');
		t('review screen: a picture card shows its address, and it is the real one', await imgRow.locator('.twd-ap-sb-card-src').isVisible() && (await imgRow.locator('.twd-ap-sb-card-src').inputValue()) === 'https://pictures.example.org/real.webp');
		t('review screen: other card types do not show the picture address', !(await page.locator('.twd-ap-sb-card-row[data-type="text"]').first().locator('.twd-ap-sb-card-src').isVisible()) && !(await page.locator('.twd-ap-sb-card-row[data-type="quote"]').first().locator('.twd-ap-sb-card-src').isVisible()));
		t('review screen: a quote shows its attribution, a chapter card its heading', await page.locator('.twd-ap-sb-card-row[data-type="quote"] .twd-ap-sb-card-attribution').first().isVisible() && (await page.locator('.twd-ap-sb-card-row[data-type="text"] .twd-ap-sb-card-heading').evaluateAll((els) => els.map((e) => e.value).filter(Boolean))).includes('Prologue - The First Step'));
		// clear the caption: the picture card must survive, a text card with no text must not
		await imgRow.locator('.twd-ap-sb-card-text').fill('');
		await page.locator('.twd-ap-sb-card-row[data-type="question"] .twd-ap-sb-card-text').fill('');
		await page.click('#twd-ap-summary-book-save-btn');
		await wait(400);
		const sent = log.sbSaves[log.sbSaves.length - 1];
		t('review screen: Save sends the picture card with its address and no caption, drops the emptied question', sent && sent.cards.length === cards.length - 1 && sent.cards.some((c) => c.type === 'image' && c.src === 'https://pictures.example.org/real.webp' && c.text === '') && !sent.cards.some((c) => c.type === 'question'), JSON.stringify(sent && sent.cards.map((c) => c.type)));
		t('review screen: only a picture card sends an address', sent && sent.cards.filter((c) => 'src' in c).every((c) => c.type === 'image'));
		// switching a picture card to text hides the address but a card with no text is then dropped
		await imgRow.locator('select').selectOption('text');
		t('review screen: changing the type hides the picture address', !(await page.locator('.twd-ap-sb-card-row').nth(6).locator('.twd-ap-sb-card-src').isVisible()));
		await page.click('#twd-ap-summary-book-add-btn');
		t('review screen: a new card can be added', (await page.locator('.twd-ap-sb-card-row').count()) === rows + 1);
		t('review screen: no JavaScript errors', log.errors.length === 0, log.errors.join(' | '));
		await ctx.close();
	}

	// 6. Review fixes: quotes in card text cannot become attributes, the picture address is checked as the
	// server checks it, every card type can show its heading, and an empty "html" still means a book.
	{
		const evil = 'Ch " onfocus="window.__pwned=1" x="';
		const cards = [
			{ type: 'text', heading: evil, text: 'Plain card.' },
			{ type: 'quote', heading: 'Part Two - Stay', text: 'A quote.', attribution: 'Au " onfocus="window.__pwned=1" y="' },
			{ type: 'image', heading: '', src: 'https://pictures.example.org/real.webp', text: 'Real picture.' },
			{ type: 'image', heading: '', src: 'https://pictures.example.org/other.webp', text: 'Second picture.' },
		];
		const { ctx, page, log } = await open(browser, { draft: cards });
		await page.evaluate(() => { window.__pwned = 0; });
		await page.click('#twd-ap-edit-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
		await page.click('#twd-ap-summary-book-btn');
		await page.waitForSelector('.twd-ap-sb-card-row');
		const rowsEl = page.locator('.twd-ap-sb-card-row');
		const heading0 = rowsEl.nth(0).locator('.twd-ap-sb-card-heading');
		const attr1 = rowsEl.nth(1).locator('.twd-ap-sb-card-attribution');
		await heading0.focus();
		await attr1.focus();
		t('review screen: a quote mark in a heading is kept whole and is not an attribute', (await heading0.inputValue()) === evil && (await heading0.evaluate((e) => e.getAttributeNames().sort().join(','))) === 'class,placeholder,type,value');
		t('review screen: a quote mark in an attribution is kept whole and is not an attribute', (await attr1.inputValue()) === 'Au " onfocus="window.__pwned=1" y="' && (await attr1.evaluate((e) => e.getAttributeNames().sort().join(','))) === 'class,placeholder,type,value');
		t('review screen: focusing those fields runs nothing', (await page.evaluate(() => window.__pwned)) === 0);
		t('review screen: a quote card shows its heading box too', await rowsEl.nth(1).locator('.twd-ap-sb-card-heading').isVisible() && (await rowsEl.nth(1).locator('.twd-ap-sb-card-heading').inputValue()) === 'Part Two - Stay');
		// a picture address the server would drop is not sent, and the owner is told
		await rowsEl.nth(2).locator('.twd-ap-sb-card-src').fill('http://mysite.example/wp-content/uploads/x.jpg');
		await rowsEl.nth(3).locator('.twd-ap-sb-card-src').fill('https://pictures.example.org/with space.webp');
		await page.click('#twd-ap-summary-book-save-btn');
		await wait(400);
		const sent = log.sbSaves[log.sbSaves.length - 1];
		t('review screen: picture cards with an unusable address are not sent', sent && sent.cards.length === 2 && sent.cards.every((c) => c.type !== 'image'), JSON.stringify(sent && sent.cards.map((c) => c.type)));
		const msg = await page.locator('#twd-ap-summary-book-status').innerText();
		t('review screen: the owner is told 2 picture cards were left out and why', /2 picture cards were left out/.test(msg) && /https:\/\//.test(msg), msg);
		// a good address is sent and not mentioned
		await rowsEl.nth(2).locator('.twd-ap-sb-card-src').fill('https://pictures.example.org/good.webp');
		await page.click('#twd-ap-summary-book-save-btn');
		await wait(400);
		const sent2 = log.sbSaves[log.sbSaves.length - 1];
		const msg2 = await page.locator('#twd-ap-summary-book-status').innerText();
		t('review screen: a corrected address is sent, and only the other card is still reported', sent2.cards.some((c) => c.src === 'https://pictures.example.org/good.webp') && /1 picture card was left out/.test(msg2), msg2);
		t('review screen: no JavaScript errors', log.errors.length === 0, log.errors.join(' | '));
		await ctx.close();
	}
	{
		const { ctx, page, log } = await open(browser);
		await toJsonTab(page, JSON.stringify(Object.assign({}, BOOK, { html: '' })));
		await page.click('#twd-ap-json-fill-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-title').value !== '');
		t('empty html with cards: still sent to the server as a book', log.imports.length === 1);
		await ctx.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
