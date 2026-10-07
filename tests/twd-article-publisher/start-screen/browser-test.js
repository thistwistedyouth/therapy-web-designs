// Real-browser test of the popup start screen. Uses the real template, CSS and JS; only server replies are faked.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/browser-test.js
// Needs Playwright and a Chromium (PLAYWRIGHT_BROWSERS_PATH is picked up automatically).
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-start-'));
const REST = 'https://example.test/wp-json/twd-publisher/v1';

function render(mode) {
	const out = path.join(tmp, mode + '.html');
	execFileSync('php', [path.join(__dirname, 'render.php'), mode, out]);
	return out;
}

let pass = 0, fail = 0;
function t(name, ok, extra) {
	if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); }
}

async function open(browser, mode, cfg, viewport) {
	const ctx = await browser.newContext({ viewport: viewport || { width: 1100, height: 900 } });
	const page = await ctx.newPage();
	const calls = [];
	const saves = [];
	const replies = { generate: null, post: {}, save: null };
	await page.route(REST + '/**', async (route) => {
		const req = route.request();
		const url = req.url().replace(REST, '');
		if (url.startsWith('/generate')) {
			calls.push({ url, body: JSON.parse(req.postData() || '{}') });
			if (replies.delay) { await new Promise((r) => setTimeout(r, replies.delay)); }
			const r = replies.generate || { status: 200, json: {} };
			return route.fulfill({ status: r.status, contentType: 'application/json', body: JSON.stringify(r.json) });
		}
		if (req.method() === 'POST' && /^\/posts(\/\d+)?$/.test(url)) {
			const body = JSON.parse(req.postData() || '{}');
			saves.push({ url, body });
			if (replies.delay) { await new Promise((r) => setTimeout(r, replies.delay)); }
			if (replies.save) {
				return route.fulfill({ status: replies.save.status, contentType: 'application/json', body: JSON.stringify(replies.save.json) });
			}
			const id = url === '/posts' ? 9 : 5;
			return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ id, status: body.status, link: 'https://example.test/article-' + id + '/' }) });
		}
		if (url.startsWith('/posts/')) {
			return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(Object.assign({ id: 5, title: 'Existing article', status: 'publish', link: 'https://example.test/article-5/', content_html: '<p>Existing body</p>', category_ids: [], tags: '', excerpt: 'x', featured_media: 0, featured_media_url: '' }, replies.post)) });
		}
		return route.fulfill({ status: 200, contentType: 'application/json', body: '[]' });
	});
	await page.goto('file://' + render(mode));
	await page.evaluate((c) => { window.TWD_AP = Object.assign({ restUrl: 'https://example.test/wp-json/twd-publisher/v1', nonce: 'n', uncategorizedId: 1, yoastEnabled: 0, currentPostId: 5, canEditThis: 1, adminEditUrl: '', i18n: { saving: 'Saving', error: 'Error', confirmClose: 'Discard?', confirmDelete: 'Delete?' } }, c); }, cfg);
	await page.addScriptTag({ path: path.join(plugin, 'assets/publisher.js') });
	await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
	return { ctx, page, calls, replies, saves };
}

const vis = (page, sel) => page.locator(sel).isVisible();
const GEN = (over) => ({ status: 200, json: Object.assign({ title: 'Generated title', seo_title: 'SEO', meta_description: 'A summary', category: '', tags: ['a', 'b'], html: '<h2>Generated heading</h2><p>Generated body.</p>', summary_book: { slides: [] }, ai_remaining: 29 }, over || {}) });

(async () => {
	const browser = await chromium.launch({ executablePath: fs.existsSync('/opt/pw-browsers/chromium-1194/chrome-linux/chrome') ? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' : undefined });
	const cfgOn = { aiKeyConfigured: 1, aiLimit: 30, aiRemaining: 30, aiMaxChars: 20000 };

	// ---------- A. key on: New Article shows the start screen only ----------
	let s = await open(browser, 'key-on', cfgOn);
	let page = s.page;
	t('A: start screen hidden before the popup opens', !(await vis(page, '#twd-ap-start')));
	await page.click('#twd-ap-new-btn');
	t('A: start screen shows on New Article', await vis(page, '#twd-ap-start'));
	t('A: heading text exact', (await page.locator('.twd-ap-start-heading').innerText()).trim() === 'Write, dictate or paste your article ideas or a full article');
	t('A: one big text box', await vis(page, '#twd-ap-start-input'));
	t('A: three radios, in order', JSON.stringify((await page.locator('.twd-ap-start-mode span').allInnerTexts()).map((x) => x.trim())) === JSON.stringify(['Article idea', 'Draft article, improve', 'Finished article, only format']));
	t('A: Article idea is the default', await page.locator('input[value="idea"]').isChecked());
	t('A: title field hidden', !(await vis(page, '#twd-ap-title')));
	t('A: editor toolbar hidden', !(await vis(page, '#twd-ap-toolbar')));
	t('A: visual editor hidden', !(await vis(page, '#twd-ap-visual-editor')));
	t('A: details/fields hidden', !(await vis(page, '#twd-ap-fields-wrap')));
	t('A: Save/Publish footer hidden', !(await vis(page, '#twd-ap-modal-footer')));
	t('A: tabs hidden', !(await vis(page, '#twd-ap-tab-json')));
	t('A: Delete button hidden on a NEW article', !(await vis(page, '#twd-ap-delete-btn')));
	t('A: original bar hidden', !(await vis(page, '#twd-ap-original-bar')));
	t('A: confidentiality note shown', (await page.locator('.twd-ap-start-note').innerText()).includes('Leave out client names'));
	t('A: left-today shown', (await page.locator('#twd-ap-start-left').innerText()) === '30 of 30 left today');
	t('A: text box has focus', await page.evaluate(() => document.activeElement && document.activeElement.id === 'twd-ap-start-input'));
	await page.screenshot({ path: path.join(tmp, 'start-desktop.png') });

	// ---------- B. improve: request, fill, original bar ----------
	const draft = 'First paragraph of my draft.\n\nSecond paragraph, with <b>odd</b> & chars.';
	await page.fill('#twd-ap-start-input', draft);
	t('B: counter shows characters', (await page.locator('#twd-ap-start-count').innerText()).includes('of 20,000 characters'));
	await page.check('input[value="improve"]');
	s.replies.generate = GEN();
	await page.click('#twd-ap-start-generate');
	await page.waitForSelector('#twd-ap-title:visible');
	t('B: one request, mode improve, input exact', s.calls.length === 1 && s.calls[0].body.mode === 'improve' && s.calls[0].body.input === draft);
	t('B: start screen gone, editor back', !(await vis(page, '#twd-ap-start')) && await vis(page, '#twd-ap-visual-editor') && await vis(page, '#twd-ap-modal-footer'));
	t('B: title filled', (await page.inputValue('#twd-ap-title')) === 'Generated title');
	t('B: body filled', (await page.locator('#twd-ap-visual-editor h2').innerText()) === 'Generated heading');
	t('B: summary and tags filled', (await page.inputValue('#twd-ap-excerpt')) === 'A summary' && (await page.inputValue('#twd-ap-tags')) === 'a, b');
	t('B: original bar shown, says improved', (await vis(page, '#twd-ap-original-bar')) && (await page.locator('#twd-ap-original-text').innerText()).includes('improved version'));
	t('B: toggle offers original', (await page.locator('#twd-ap-original-toggle').innerText()) === 'Use my original text instead');
	await page.screenshot({ path: path.join(tmp, 'after-generate.png') });

	// ---------- C. swap back and forth, edits survive ----------
	await page.click('#twd-ap-original-toggle');
	const origHtml = await page.locator('#twd-ap-visual-editor').innerHTML();
	t('C: original shown as paragraphs', origHtml.includes('<p>First paragraph of my draft.</p>') && origHtml.includes('<p>Second paragraph'));
	t('C: original text escaped, not run as HTML', origHtml.includes('&lt;b&gt;odd&lt;/b&gt; &amp; chars') && !origHtml.includes('<b>odd'));
	t('C: toggle now offers the improved version', (await page.locator('#twd-ap-original-toggle').innerText()) === 'Use the improved version');
	await page.evaluate(() => { document.getElementById('twd-ap-visual-editor').insertAdjacentHTML('beforeend', '<p>EDIT IN ORIGINAL</p>'); });
	await page.click('#twd-ap-original-toggle');
	t('C: back to AI version', (await page.locator('#twd-ap-visual-editor h2').innerText()) === 'Generated heading');
	await page.click('#twd-ap-original-toggle');
	t('C: edit made to original survived the round trip', (await page.locator('#twd-ap-visual-editor').innerHTML()).includes('EDIT IN ORIGINAL'));
	t('C: title untouched by swapping', (await page.inputValue('#twd-ap-title')) === 'Generated title');

	// ---------- D. reopen resets; remaining counts down ----------
	await page.evaluate(() => { document.getElementById('twd-ap-close-btn').click(); });
	await page.evaluate(() => { window.confirm = () => true; });
	if (await vis(page, '#twd-ap-overlay')) { await page.click('#twd-ap-close-btn'); }
	await page.click('#twd-ap-new-btn');
	t('D: reopening New shows a clean start screen', await vis(page, '#twd-ap-start') && (await page.inputValue('#twd-ap-start-input')) === '' && await page.locator('input[value="idea"]').isChecked());
	t('D: original bar cleared on reopen', !(await vis(page, '#twd-ap-original-bar')));
	t('D: left-today dropped to 29', (await page.locator('#twd-ap-start-left').innerText()) === '29 of 30 left today');

	// ---------- E. idea mode: no original bar; ctrl+enter ----------
	await page.fill('#twd-ap-start-input', 'Why rest feels unproductive');
	s.replies.generate = GEN({ ai_remaining: 28 });
	await page.press('#twd-ap-start-input', 'Control+Enter');
	await page.waitForSelector('#twd-ap-title:visible');
	t('E: ctrl+enter generates, mode idea', s.calls.length === 2 && s.calls[1].body.mode === 'idea');
	t('E: idea mode shows no original bar', !(await vis(page, '#twd-ap-original-bar')));

	// ---------- F. format mode ----------
	await page.evaluate(() => { window.confirm = () => true; document.getElementById('twd-ap-close-btn').click(); });
	await page.click('#twd-ap-new-btn');
	await page.fill('#twd-ap-start-input', 'My finished article text.');
	await page.check('input[value="format"]');
	s.replies.generate = GEN({ ai_remaining: 27 });
	await page.click('#twd-ap-start-generate');
	await page.waitForSelector('#twd-ap-title:visible');
	t('F: mode format sent', s.calls[2].body.mode === 'format');
	t('F: bar says formatted', (await page.locator('#twd-ap-original-text').innerText()).includes('formatted version'));

	// ---------- G. server error keeps the text ----------
	await page.evaluate(() => { window.confirm = () => true; document.getElementById('twd-ap-close-btn').click(); });
	await page.click('#twd-ap-new-btn');
	await page.fill('#twd-ap-start-input', 'Keep me if it fails');
	s.replies.generate = { status: 500, json: { message: 'The AI service returned an error (500, boom).' } };
	await page.click('#twd-ap-start-generate');
	await page.waitForSelector('#twd-ap-status:visible');
	t('G: error message shown', (await page.locator('#twd-ap-status').innerText()).includes('boom'));
	t('G: still on start screen, text kept', await vis(page, '#twd-ap-start') && (await page.inputValue('#twd-ap-start-input')) === 'Keep me if it fails');
	t('G: Generate usable again', await page.locator('#twd-ap-start-generate').isEnabled() && (await page.locator('#twd-ap-start-generate').innerText()) === 'Generate');

	// ---------- H. skip carries text over ----------
	await page.fill('#twd-ap-start-input', 'Typed before skipping.\n\nSecond bit.');
	await page.click('#twd-ap-start-skip');
	t('H: skip shows the normal editor', !(await vis(page, '#twd-ap-start')) && await vis(page, '#twd-ap-title') && await vis(page, '#twd-ap-modal-footer'));
	t('H: typed text carried into the editor', (await page.locator('#twd-ap-visual-editor').innerHTML()).includes('<p>Typed before skipping.</p>'));
	t('H: no request made by skipping', s.calls.length === 4);

	// ---------- I. over the limit ----------
	await page.evaluate(() => { window.confirm = () => true; document.getElementById('twd-ap-close-btn').click(); });
	await page.click('#twd-ap-new-btn');
	await page.fill('#twd-ap-start-input', 'x'.repeat(20001));
	t('I: Generate disabled over the limit', await page.locator('#twd-ap-start-generate').isDisabled());
	t('I: count explains and turns red', (await page.locator('#twd-ap-start-count').innerText()).includes('Too long') && (await page.locator('#twd-ap-start-count').evaluate((e) => getComputedStyle(e).color)) === 'rgb(179, 69, 46)');
	await page.fill('#twd-ap-start-input', 'x'.repeat(20000));
	t('I: exactly 20,000 is allowed', await page.locator('#twd-ap-start-generate').isEnabled());
	await page.fill('#twd-ap-start-input', '');
	await page.click('#twd-ap-start-generate');
	t('I: empty text gives a message, no request', (await page.locator('#twd-ap-status').innerText()).includes('idea or topic') && s.calls.length === 4);
	await s.ctx.close();

	// ---------- J. editing an existing article: no start screen ----------
	s = await open(browser, 'key-on', cfgOn);
	page = s.page;
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	t('J: edit mode goes straight to the editor', !(await vis(page, '#twd-ap-start')) && await vis(page, '#twd-ap-title') && await vis(page, '#twd-ap-modal-footer'));
	t('J: Delete button shown when editing', await vis(page, '#twd-ap-delete-btn'));
	t('J: existing body loaded', (await page.locator('#twd-ap-visual-editor').innerText()).includes('Existing body'));
	await s.ctx.close();

	// ---------- K. limit reached ----------
	s = await open(browser, 'key-on', Object.assign({}, cfgOn, { aiRemaining: 0 }));
	page = s.page;
	await page.click('#twd-ap-new-btn');
	await page.fill('#twd-ap-start-input', 'hello');
	t('K: Generate disabled at zero left', await page.locator('#twd-ap-start-generate').isDisabled());
	t('K: says so and points to writing it yourself', (await page.locator('#twd-ap-start-left').innerText()).includes('used today') && (await page.locator('#twd-ap-start-left').innerText()).includes('write it yourself'));
	await page.click('#twd-ap-start-skip');
	t('K: can still write it yourself', await vis(page, '#twd-ap-title'));
	await s.ctx.close();

	// ---------- L. phone-width layout ----------
	s = await open(browser, 'key-on', cfgOn, { width: 390, height: 800 });
	page = s.page;
	await page.click('#twd-ap-new-btn');
	const overflow = await page.evaluate(() => { const m = document.getElementById('twd-ap-modal'); return m.scrollWidth - m.clientWidth; });
	t('L: no sideways overflow at 390px', overflow <= 1, 'overflow ' + overflow);
	const boxes = await page.evaluate(() => ['.twd-ap-start-mode', '#twd-ap-start-generate', '#twd-ap-start-skip', '#twd-ap-start-input'].map((sel) => { const r = document.querySelector(sel).getBoundingClientRect(); return r.left >= 0 && r.right <= window.innerWidth; }));
	t('L: radios, text box, Generate and skip all inside the screen width', boxes.every(Boolean), JSON.stringify(boxes));
	await page.screenshot({ path: path.join(tmp, 'start-phone.png'), fullPage: false });
	await s.ctx.close();

	// ---------- M. key off: nothing changes ----------
	s = await open(browser, 'key-off', { aiKeyConfigured: 0 });
	page = s.page;
	await page.click('#twd-ap-new-btn');
	t('M: no start screen element at all', (await page.locator('#twd-ap-start').count()) === 0);
	t('M: normal editor opens', await vis(page, '#twd-ap-title') && await vis(page, '#twd-ap-visual-editor') && await vis(page, '#twd-ap-modal-footer'));
	t('M: Article Assist link still shown', (await page.locator('.twd-ap-assist-btn').count()) === 1);
	await page.click('#twd-ap-tab-json');
	t('M: JSON tab present with paste box, no generator', await vis(page, '#twd-ap-json-input') && (await page.locator('#twd-ap-ai-generate-box').count()) === 0);
	t('M: JSON tab labelled Paste JSON', (await page.locator('#twd-ap-tab-json').innerText()) === 'Paste JSON');
	await s.ctx.close();


	// ---------- N. the small edit pencil, top left ----------
	s = await open(browser, 'key-on', cfgOn);
	page = s.page;
	let box = await page.locator('#twd-ap-edit-btn').boundingBox();
	t('N: pencil is top left, 16px in', Math.round(box.x) === 16 && Math.round(box.y) === 16);
	t('N: pencil is a 40px circle', Math.round(box.width) === 40 && Math.round(box.height) === 40 && (await page.locator('#twd-ap-edit-btn').evaluate((e) => getComputedStyle(e).borderRadius)) === '50%');
	t('N: pencil is an icon, not a text button', (await page.locator('#twd-ap-edit-btn svg').count()) === 1 && (await page.locator('#twd-ap-edit-btn').innerText()).trim() === '');
	t('N: pencil is labelled for screen readers', (await page.locator('#twd-ap-edit-btn').getAttribute('aria-label')) === 'Edit this article' && (await page.locator('#twd-ap-edit-btn').getAttribute('title')) === 'Edit this article');
	t('N: the old big "Edit This Article" button is gone', !(await page.content()).includes('Edit This Article</button>') && (await page.locator('.twd-ap-float-btn', { hasText: 'Edit This Article' }).count()) === 0);
	box = await page.locator('#twd-ap-new-btn').boundingBox();
	t('N: New Article button unchanged, bottom right', Math.round(box.x + box.width) === 1100 - 20 && Math.round(box.y + box.height) > 900 - 60);
	await page.evaluate(() => document.body.classList.add('admin-bar'));
	box = await page.locator('#twd-ap-edit-btn').boundingBox();
	t('N: pencil sits below the WordPress admin bar (48px)', Math.round(box.y) === 48);
	await page.setViewportSize({ width: 390, height: 800 });
	box = await page.locator('#twd-ap-edit-btn').boundingBox();
	t('N: and below the taller phone admin bar (62px)', Math.round(box.y) === 62 && Math.round(box.x) === 16);
	await page.screenshot({ path: path.join(tmp, 'pencil-phone.png') });
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	t('N: pencil opens the edit popup', await vis(page, '#twd-ap-title') && !(await vis(page, '#twd-ap-start')));
	await s.ctx.close();

	// ---------- O. publishing closes the popup and refreshes the page behind ----------
	async function reloaded(page, ms) { await page.waitForTimeout(ms || 1500); return (await page.evaluate(() => window.__stay)) !== true; }
	async function waitSaves(s, n) { for (let k = 0; k < 60 && s.saves.length < n; k++) { await new Promise((r) => setTimeout(r, 50)); } }
	async function newThenSkip(page) {
		await page.evaluate(() => { window.__stay = true; window.confirm = () => true; });
		await page.click('#twd-ap-new-btn');
		await page.click('#twd-ap-start-skip');
		await page.fill('#twd-ap-title', 'My article');
	}
	// O1: a brand-new Publish closes the popup and refreshes by itself
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await newThenSkip(page);
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	t('O1: new Publish saved with status publish', s.saves.length === 1 && s.saves[0].body.status === 'publish');
	t('O1: page behind refreshes without anyone clicking Close', await reloaded(page, 1800));
	await s.ctx.close();
	// O2: draft keeps the popup open and never refreshes
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await newThenSkip(page);
	await page.click('#twd-ap-draft-btn');
	await page.waitForSelector('#twd-ap-status:visible');
	t('O2: draft: popup stays open with a saved message', await vis(page, '#twd-ap-overlay') && (await page.locator('#twd-ap-status').innerText()).includes('Draft saved'));
	t('O2: draft: working layer is gone again', !(await vis(page, '#twd-ap-working')));
	await page.click('#twd-ap-close-btn');
	t('O2: a draft save does not refresh the page', !(await reloaded(page, 1200)));
	await s.ctx.close();
	// O3: draft, then publish: the publish closes and refreshes
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await newThenSkip(page);
	await page.click('#twd-ap-draft-btn');
	await page.waitForSelector('#twd-ap-status:visible');
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 2);
	t('O3: draft then publish: second save is a publish to the same article', s.saves[1].body.status === 'publish' && s.saves[1].url === '/posts/9');
	t('O3: and the page refreshes', await reloaded(page, 1800));
	await s.ctx.close();
	// O4: editing an existing article: Update closes and refreshes
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.evaluate(() => { window.__stay = true; window.confirm = () => true; });
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	t('O4: Update sent to the existing article', s.saves.length === 1 && s.saves[0].url === '/posts/5' && s.saves[0].body.status === 'publish');
	t('O4: Update closes the popup and refreshes the page the first time', await reloaded(page, 1800));
	await s.ctx.close();
	// O5: opening and closing without saving never refreshes
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.evaluate(() => { window.__stay = true; window.confirm = () => true; });
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	await page.click('#twd-ap-close-btn');
	t('O5: closing without saving does not refresh', !(await reloaded(page, 1000)));
	await s.ctx.close();
	// O6: a failed save keeps the popup open, shows the reason, never refreshes
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await newThenSkip(page);
	s.replies.save = { status: 500, json: { message: 'Could not save.' } };
	await page.click('#twd-ap-publish-btn');
	await page.waitForFunction(() => /Could not save/.test(document.getElementById('twd-ap-status').textContent));
	t('O6: failed save: message shown, popup still open, working layer gone', await vis(page, '#twd-ap-overlay') && !(await vis(page, '#twd-ap-working')));
	await page.click('#twd-ap-close-btn');
	t('O6: failed save then close: no refresh', !(await reloaded(page, 1000)));
	await s.ctx.close();
	// O7: scheduling closes the popup but does not refresh (the article is not public yet)
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await newThenSkip(page);
	await page.check('#twd-ap-schedule-toggle');
	await page.fill('#twd-ap-schedule-date', '2030-01-01T09:00');
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	await page.waitForTimeout(1500);
	t('O7: schedule saved as future', s.saves[0].body.status === 'future');
	t('O7: scheduling closes the popup but does not refresh', !(await vis(page, '#twd-ap-overlay')) && (await page.evaluate(() => window.__stay)) === true);
	await s.ctx.close();

	// ---------- P. swipe book tick ----------
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await newThenSkip(page);
	t('P: after skipping, the Details swipe book tick is there and ticked', await vis(page, '#twd-ap-swipebook-toggle') && await page.locator('#twd-ap-swipebook-toggle').isChecked());
	t('P: label explains it covers the Summary Book too', (await page.locator('label:has(#twd-ap-swipebook-toggle)').innerText()).includes('Summary Book'));
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	t('P: new article saved with swipebook_enabled true', s.saves[0].body.swipebook_enabled === true);
	await s.ctx.close();
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await newThenSkip(page);
	await page.uncheck('#twd-ap-swipebook-toggle');
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	t('P: unticked in Details: saved with swipebook_enabled false', s.saves[0].body.swipebook_enabled === false);
	await s.ctx.close();
	// the start screen's own tick carries through
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.evaluate(() => { window.confirm = () => true; });
	await page.click('#twd-ap-new-btn');
	await page.uncheck('#twd-ap-start-swipebook');
	await page.click('#twd-ap-start-skip');
	t('P: unticked on the start screen, skip: Details tick is unticked too', !(await page.locator('#twd-ap-swipebook-toggle').isChecked()));
	await s.ctx.close();
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.evaluate(() => { window.confirm = () => true; });
	await page.click('#twd-ap-new-btn');
	await page.uncheck('#twd-ap-start-swipebook');
	await page.fill('#twd-ap-start-input', 'An idea');
	s.replies.generate = GEN();
	await page.click('#twd-ap-start-generate');
	await page.waitForSelector('#twd-ap-title:visible');
	t('P: unticked on the start screen, Generate: Details tick is unticked too', !(await page.locator('#twd-ap-swipebook-toggle').isChecked()));
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	t('P: and the article is saved with swipebook_enabled false', s.saves[0].body.swipebook_enabled === false);
	await s.ctx.close();
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.evaluate(() => { window.confirm = () => true; });
	await page.click('#twd-ap-new-btn');
	t('P: start screen tick is ticked by default and says what it does', await page.locator('#twd-ap-start-swipebook').isChecked() && (await page.locator('.twd-ap-start-swipe').innerText()).includes('Also publish as a swipe book') && (await page.locator('.twd-ap-start-swipe').innerText()).includes('presents the full article'));
	await s.ctx.close();
	// edit page
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	s.replies.post = { swipebook_enabled: false };
	await page.evaluate(() => { window.confirm = () => true; });
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	t('P: edit page loads an OFF article unticked', !(await page.locator('#twd-ap-swipebook-toggle').isChecked()));
	t('P: its swipe book link in the header is hidden', !(await vis(page, '#twd-ap-swipebook-link')));
	await page.check('#twd-ap-swipebook-toggle');
	t('P: ticking it shows the link straight away', await vis(page, '#twd-ap-swipebook-link') && (await page.locator('#twd-ap-swipebook-link').getAttribute('href')).includes('twd_ap_swipebook=1'));
	await page.uncheck('#twd-ap-swipebook-toggle');
	t('P: unticking hides it again', !(await vis(page, '#twd-ap-swipebook-link')));
	await page.check('#twd-ap-swipebook-toggle');
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	t('P: Update after ticking sends swipebook_enabled true', s.saves[0].body.swipebook_enabled === true);
	await s.ctx.close();
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.evaluate(() => { window.confirm = () => true; });
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	t('P: an article from before the switch existed (no value sent) loads as ON', await page.locator('#twd-ap-swipebook-toggle').isChecked() && await vis(page, '#twd-ap-swipebook-link'));
	await page.uncheck('#twd-ap-swipebook-toggle');
	await page.click('#twd-ap-publish-btn');
	await waitSaves(s, 1);
	t('P: unticking and updating sends swipebook_enabled false', s.saves[0].body.swipebook_enabled === false);
	await s.ctx.close();
	s = await open(browser, 'key-off', { aiKeyConfigured: 0 }); page = s.page;
	await page.click('#twd-ap-new-btn');
	t('P: sites with no AI key also get the swipe book tick', await vis(page, '#twd-ap-swipebook-toggle') && await page.locator('#twd-ap-swipebook-toggle').isChecked());
	await s.ctx.close();

	// ---------- Q. layout: order, one row, no scrolling, buttons ----------
	for (const vp of [{ width: 1280, height: 800 }, { width: 1366, height: 768 }, { width: 1100, height: 820 }]) {
		s = await open(browser, 'key-on', cfgOn, vp); page = s.page;
		await page.click('#twd-ap-new-btn');
		const m = await page.evaluate(() => {
			const r = (sel) => document.querySelector(sel).getBoundingClientRect();
			const ov = document.getElementById('twd-ap-overlay');
			const body = document.querySelector('#twd-ap-modal .twd-ap-modal-body');
			return {
				modes: r('.twd-ap-start-row').top, heading: r('.twd-ap-start-heading').top, box: r('#twd-ap-start-input').top,
				swipe: r('.twd-ap-start-swipe').top, gen: r('#twd-ap-start-generate').top, genBottom: r('#twd-ap-start-generate').bottom,
				modalBottom: r('#twd-ap-modal').bottom, vh: window.innerHeight,
				overlayScrolls: ov.scrollHeight > ov.clientHeight + 1, bodyScrolls: body.scrollHeight > body.clientHeight + 1,
				bodyOverflow: getComputedStyle(body).overflowY,
			};
		});
		const tag = vp.width + 'x' + vp.height;
		t('Q ' + tag + ': order is options, then heading, box, swipe book tick, Generate', m.modes < m.heading && m.heading < m.box && m.box < m.swipe && m.swipe < m.gen);
		t('Q ' + tag + ': Generate fully visible, nothing cropped', m.genBottom <= m.vh && m.modalBottom <= m.vh, JSON.stringify(m));
		t('Q ' + tag + ': no scrollbar anywhere on the start screen', !m.overlayScrolls && !m.bodyScrolls, JSON.stringify(m));
		if (vp.width === 1280) { await page.screenshot({ path: path.join(tmp, 'start-laptop.png') }); }
		await s.ctx.close();
	}
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.click('#twd-ap-new-btn');
	const tops = await page.locator('.twd-ap-start-mode').evaluateAll((els) => els.map((e) => Math.round(e.getBoundingClientRect().top)));
	t('Q: the three options sit in ONE row on desktop', tops.length === 3 && tops[0] === tops[1] && tops[1] === tops[2], JSON.stringify(tops));
	t('Q: hint under the options follows the choice', (await page.locator('#twd-ap-start-hint').innerText()) === 'Write a full article from my idea');
	await page.check('input[value="improve"]');
	t('Q: choosing Draft improve changes the hint', (await page.locator('#twd-ap-start-hint').innerText()).includes('Keep my voice'));
	await page.check('input[value="format"]');
	t('Q: choosing Finished article changes the hint', (await page.locator('#twd-ap-start-hint').innerText()).includes('Keep my words exactly'));
	const cb = await page.locator('#twd-ap-close-btn').boundingBox();
	t('Q: close button is a clean 36px circle (not squashed)', Math.round(cb.width) === 36 && Math.round(cb.height) === 36 && (await page.locator('#twd-ap-close-btn').evaluate((e) => getComputedStyle(e).borderRadius)) === '50%');
	t('Q: close button is an icon, not a text cross', (await page.locator('#twd-ap-close-btn svg').count()) === 1 && (await page.locator('#twd-ap-close-btn').innerText()).trim() === '');
	const hb = await page.locator('#twd-ap-help-btn').boundingBox();
	t('Q: help ? button is a 32px circle, not squashed', Math.round(hb.width) === 32 && Math.round(hb.height) === 32 && (await page.locator('#twd-ap-help-btn').evaluate((e) => getComputedStyle(e).borderRadius)) === '50%');
	t('Q: help and close do not overlap', hb.x + hb.width <= cb.x);
	await page.evaluate(() => { window.confirm = () => true; });
	await s.ctx.close();
	// a page builder styling every button must not un-hide hidden header buttons
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	await page.addStyleTag({ content: '#twd-ap-modal button, #twd-ap-modal a, .twd-ap-modal button { display: inline-block !important; border: 2px solid hotpink !important; min-width: 120px !important; }' });
	await page.click('#twd-ap-new-btn');
	t('Q: Summary Book button NOT showing on a new article, even against a theme that restyles buttons', !(await vis(page, '#twd-ap-summary-book-btn')));
	t('Q: Delete, edit-in-WordPress and swipe book links also hidden on a new article', !(await vis(page, '#twd-ap-delete-btn')) && !(await vis(page, '#twd-ap-admin-edit-link')) && !(await vis(page, '#twd-ap-swipebook-link')));
	await s.ctx.close();
	// ... but the Summary Book button does show when editing a published article
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	s.replies.post = { summary_book_has_draft: true, summary_book_is_published: false };
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	t('Q: Summary Book button shows on the edit page', await vis(page, '#twd-ap-summary-book-btn'));
	await s.ctx.close();

	// ---------- R. animations ----------
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	s.replies.delay = 1800;
	await page.evaluate(() => { window.confirm = () => true; });
	await page.click('#twd-ap-new-btn');
	await page.fill('#twd-ap-start-input', 'Idea for the animation');
	s.replies.generate = GEN();
	await page.click('#twd-ap-start-generate');
	await page.waitForSelector('#twd-ap-working:visible');
	t('R: generating shows the working layer with a title', (await page.locator('#twd-ap-working-title').innerText()) === 'Writing your article' && (await page.locator('#twd-ap-working-sub').innerText()).includes('Reading your idea'));
	t('R: the lines are animating', (await page.locator('.twd-ap-working-line-1').evaluate((e) => getComputedStyle(e).animationName)) === 'twd-ap-write' && (await page.locator('.twd-ap-working-pen').evaluate((e) => getComputedStyle(e).animationName)) === 'twd-ap-pen');
	const wk = await page.locator('#twd-ap-working').boundingBox();
	const hd = await page.locator('#twd-ap-modal .twd-ap-modal-header').boundingBox();
	t('R: the layer starts below the header, so Close stays clickable', wk.y >= hd.y + hd.height - 1);
	t('R: the layer covers the form (nothing underneath can be clicked)', await page.evaluate(() => { const r = document.getElementById('twd-ap-start-input').getBoundingClientRect(); const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return !!el.closest('#twd-ap-working'); }));
	await page.screenshot({ path: path.join(tmp, 'generating.png') });
	await page.waitForSelector('#twd-ap-title:visible');
	t('R: the layer is gone once the article is ready', !(await vis(page, '#twd-ap-working')));
	await s.ctx.close();
	// generate failure: layer gone, text kept
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	s.replies.delay = 400;
	await page.click('#twd-ap-new-btn');
	await page.fill('#twd-ap-start-input', 'Will fail');
	s.replies.generate = { status: 500, json: { message: 'The AI service returned an error (500, boom).' } };
	await page.click('#twd-ap-start-generate');
	await page.waitForSelector('#twd-ap-working:visible');
	await page.waitForSelector('#twd-ap-status:visible');
	t('R: failed generation: layer gone, error shown, text kept', !(await vis(page, '#twd-ap-working')) && (await page.inputValue('#twd-ap-start-input')) === 'Will fail');
	await s.ctx.close();
	// publishing animation
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	s.replies.delay = 1500;
	await newThenSkip(page);
	await page.click('#twd-ap-publish-btn');
	await page.waitForSelector('#twd-ap-working:visible');
	t('R: publishing shows "Publishing your article"', (await page.locator('#twd-ap-working-title').innerText()) === 'Publishing your article');
	t('R: buttons cannot be pressed twice while publishing', await page.locator('#twd-ap-publish-btn').isDisabled());
	await page.screenshot({ path: path.join(tmp, 'publishing.png') });
	await waitSaves(s, 1);
	await page.waitForFunction(() => document.getElementById('twd-ap-working-title').textContent === 'Published');
	t('R: then says Published and that the page is refreshing', (await page.locator('#twd-ap-working-sub').innerText()).includes('Refreshing the page'));
	t('R: and then the page really refreshes', await reloaded(page, 1800));
	await s.ctx.close();
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	s.replies.delay = 600;
	await page.evaluate(() => { window.confirm = () => true; });
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
	await page.click('#twd-ap-publish-btn');
	await page.waitForSelector('#twd-ap-working:visible');
	t('R: updating shows "Updating your article"', (await page.locator('#twd-ap-working-title').innerText()) === 'Updating your article');
	await page.waitForFunction(() => document.getElementById('twd-ap-working-title').textContent === 'Updated');
	t('R: then says Updated', true);
	await s.ctx.close();
	s = await open(browser, 'key-on', cfgOn); page = s.page;
	s.replies.delay = 600;
	await newThenSkip(page);
	await page.click('#twd-ap-draft-btn');
	await page.waitForSelector('#twd-ap-working:visible');
	t('R: saving a draft shows "Saving your draft"', (await page.locator('#twd-ap-working-title').innerText()) === 'Saving your draft');
	await s.ctx.close();
	// reduced motion respected
	const ctxRM = await browser.newContext({ viewport: { width: 1100, height: 900 }, reducedMotion: 'reduce' });
	const pageRM = await ctxRM.newPage();
	await pageRM.route(REST + '/**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: '[]' }));
	await pageRM.goto('file://' + render('key-on'));
	await pageRM.evaluate(() => { window.TWD_AP = { restUrl: 'https://example.test/wp-json/twd-publisher/v1', nonce: 'n', currentPostId: 5, aiKeyConfigured: 1, aiLimit: 30, aiRemaining: 30, aiMaxChars: 20000, i18n: { confirmClose: 'x' } }; });
	await pageRM.addScriptTag({ path: path.join(plugin, 'assets/publisher.js') });
	await pageRM.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
	await pageRM.evaluate(() => { document.getElementById('twd-ap-working').hidden = false; });
	t('R: people who ask for reduced motion get no animation', (await pageRM.locator('.twd-ap-working-line-1').evaluate((e) => getComputedStyle(e).animationName)) === 'none');
	await ctxRM.close();

	await browser.close();
	console.log('\nScreenshots: ' + tmp);
	console.log(pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
