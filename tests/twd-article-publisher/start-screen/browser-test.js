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
	const replies = { generate: null };
	await page.route(REST + '/**', async (route) => {
		const req = route.request();
		const url = req.url().replace(REST, '');
		if (url.startsWith('/generate')) {
			calls.push({ url, body: JSON.parse(req.postData() || '{}') });
			const r = replies.generate || { status: 200, json: {} };
			return route.fulfill({ status: r.status, contentType: 'application/json', body: JSON.stringify(r.json) });
		}
		if (url.startsWith('/posts/')) {
			return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ id: 5, title: 'Existing article', status: 'publish', content_html: '<p>Existing body</p>', category_ids: [], tags: '', excerpt: 'x', featured_media: 0, featured_media_url: '' }) });
		}
		return route.fulfill({ status: 200, contentType: 'application/json', body: '[]' });
	});
	await page.goto('file://' + render(mode));
	await page.evaluate((c) => { window.TWD_AP = Object.assign({ restUrl: 'https://example.test/wp-json/twd-publisher/v1', nonce: 'n', uncategorizedId: 1, yoastEnabled: 0, currentPostId: 5, canEditThis: 1, adminEditUrl: '', i18n: { saving: 'Saving', error: 'Error', confirmClose: 'Discard?', confirmDelete: 'Delete?' } }, c); }, cfg);
	await page.addScriptTag({ path: path.join(plugin, 'assets/publisher.js') });
	await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
	return { ctx, page, calls, replies };
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
	t('A: three radios, in order', JSON.stringify(await page.locator('.twd-ap-start-mode strong').allInnerTexts()) === JSON.stringify(['Article idea', 'Draft article, improve', 'Finished article, only format']));
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

	await browser.close();
	console.log('\nScreenshots: ' + tmp);
	console.log(pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
