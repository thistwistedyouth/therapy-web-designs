// Real-browser test of Remix in the edit popup (1.54.0): the tab shows only for an existing article with
// text, the prompt carries the article as it is in the editor, a pasted reply goes to /remix-check, the
// result fills the editor with the original/remix bar and the fact-check report, nothing is saved until
// Update. The server's reply is the REAL output of TWD_AP_Remix::check() (dumped by remix-test.php); only
// the transport is faked. Made-up article and reply.
//   NODE_PATH="$(npm root -g)" node tests/twd-article-publisher/start-screen/remix-browser-test.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const root = path.resolve(__dirname, '../../..');
const plugin = path.join(root, 'plugins/twd-article-publisher');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'twd-remix-'));
const REST = 'https://example.test/wp-json/twd-publisher/v1';

const dump = path.join(tmp, 'remix.json');
execFileSync('php', [path.join(root, 'tests/twd-article-publisher/remix-test.php')], { env: Object.assign({}, process.env, { TWD_REMIX_DUMP: dump }), stdio: 'pipe' });
const D = JSON.parse(fs.readFileSync(dump, 'utf8'));

let pass = 0, fail = 0;
function t(name, ok, extra) { if (ok) { pass++; console.log('PASS  ' + name); } else { fail++; console.log('FAIL  ' + name + (extra ? '  ' + extra : '')); } }

async function open(browser, key, o) {
	o = Object.assign({ check: { status: 200, json: D.result }, content: D.original }, o || {});
	const pageFile = path.join(tmp, 'page-' + key + '.html');
	if (!fs.existsSync(pageFile)) { execFileSync('php', [path.join(__dirname, 'render.php'), key, pageFile]); }
	const ctx = await browser.newContext({ viewport: { width: 1100, height: 900 } });
	const page = await ctx.newPage();
	const log = { checks: [], saves: [], errors: [] };
	page.on('pageerror', (e) => log.errors.push(String(e)));
	page.on('dialog', (d) => d.accept());
	await page.route(REST + '/**', async (route) => {
		const req = route.request();
		const url = req.url().replace(REST, '');
		const json = (status, body) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
		if (url === '/remix-check') {
			log.checks.push({ body: JSON.parse(req.postData() || '{}'), nonce: req.headers()['x-wp-nonce'] });
			if (o.check === 'abort') { return route.abort(); }
			return json(o.check.status, o.check.json);
		}
		if (req.method() === 'POST' && /^\/posts(\/\d+)?$/.test(url)) {
			log.saves.push(JSON.parse(req.postData() || '{}'));
			return json(200, { id: 5, status: 'publish', link: 'https://example.test/a/' });
		}
		if (/^\/posts\/\d+$/.test(url)) {
			return json(200, { id: 5, title: 'Existing article', status: 'publish', link: 'https://example.test/a/', excerpt: 'Old summary', content_html: o.content, categories: [], tags: '' });
		}
		return json(200, []);
	});
	await page.goto('file://' + pageFile);
	await page.evaluate((d) => {
		window.TWD_AP = { restUrl: 'https://example.test/wp-json/twd-publisher/v1', nonce: 'nonce-1', uncategorizedId: 1, yoastEnabled: 0, currentPostId: 5, canEditThis: 1, adminEditUrl: '', aiKeyConfigured: 0, remixPrompt: d.prompt, remixLengths: d.lengths, i18n: { saving: 'Saving', error: 'Error', confirmClose: 'Discard?', confirmDelete: 'Delete?' } };
	}, D);
	// No clipboard in a file:// page unless allowed; the fallback box is tested separately.
	if (o.clipboard) {
		await page.evaluate(() => {
			window.__copied = null;
			Object.defineProperty(navigator, 'clipboard', { value: { writeText: (s) => { window.__copied = s; return Promise.resolve(); } }, configurable: true });
		});
	} else {
		await page.evaluate(() => { Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true }); });
	}
	await page.addScriptTag({ path: path.join(plugin, 'assets/publisher.js') });
	await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
	return { ctx, page, log };
}

async function openEdit(page) {
	await page.click('#twd-ap-edit-btn');
	await page.waitForFunction(() => document.getElementById('twd-ap-title').value === 'Existing article');
}

async function doRemix(page) {
	await page.click('#twd-ap-tab-remix');
	await page.fill('#twd-ap-remix-input', D.reply);
	await page.click('#twd-ap-remix-check');
	await page.waitForFunction(() => !document.getElementById('twd-ap-remix-report').hidden);
}

(async () => {
	// PW_CHANNEL=msedge (or chrome) runs it on an installed browser when Playwright's own is not downloaded.
	const browser = await chromium.launch(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {});

	for (const key of ['key-off', 'key-on']) {
		// 1. Where the tab shows.
		{
			const { ctx, page, log } = await open(browser, key, { clipboard: true });
			await page.click('#twd-ap-new-btn');
			t(key + ': a new article has no Remix tab', await page.locator('#twd-ap-tab-remix').isHidden());
			await page.click('#twd-ap-close-btn');
			await openEdit(page);
			t(key + ': an existing article with text has the Remix tab', await page.locator('#twd-ap-tab-remix').isVisible());
			t(key + ': the tab sits after Paste JSON', await page.evaluate(() => { const b = Array.from(document.querySelectorAll('.twd-ap-editor-tabs .twd-ap-tab')).map((x) => x.id); return b.indexOf('twd-ap-tab-remix') === b.indexOf('twd-ap-tab-json') + 1; }));

			// 2. The panel.
			await page.click('#twd-ap-tab-remix');
			t(key + ': the panel opens, the editor and the fields step aside', await page.locator('#twd-ap-remix-panel').isVisible() && await page.locator('#twd-ap-visual-editor').isHidden() && await page.locator('#twd-ap-fields-wrap').isHidden() && await page.locator('#twd-ap-modal-footer').isHidden());
			t(key + ': two thirds is the default length', (await page.inputValue('input[name="twd-ap-remix-length"]:checked')) === 'two-thirds');
			t(key + ': Open Claude opens in a new tab', (await page.getAttribute('#twd-ap-remix-open', 'target')) === '_blank' && /^https:\/\/claude\.ai\//.test(await page.getAttribute('#twd-ap-remix-open', 'href')));
			await page.check('input[name="twd-ap-remix-length"][value="half"]');
			await page.click('#twd-ap-remix-copy');
			await page.waitForFunction(() => window.__copied !== null);
			const copied = await page.evaluate(() => window.__copied);
			const expected = D.expected_prompt_half;
			// The editor normalises the HTML a little, so compare everything but the article body exactly.
			const cut = (s) => s.slice(0, s.indexOf('THE ARTICLE:'));
			t(key + ': the copied prompt matches the PHP prompt up to the article', cut(copied) === cut(expected), cut(copied).slice(-200));
			t(key + ': the prompt carries the site, the length, the title and the article text', copied.includes('"Example Antiques"') && copied.includes('about half of its current length') && copied.includes('Title: Existing article') && copied.includes('necklace.jpg') && copied.includes('The riviere style appeared in the 1700s'));
			t(key + ': no placeholder is left in the prompt', !/\{(SITE|LENGTH|TITLE|HTML)\}/.test(copied));
			t(key + ': the prompt is also shown, for copying by hand', await page.locator('#twd-ap-remix-prompt').isVisible() && (await page.inputValue('#twd-ap-remix-prompt')) === copied);
			t(key + ': the button says Copied', (await page.textContent('#twd-ap-remix-copy')).trim() === 'Copied!');
			t(key + ': no save happened', log.saves.length === 0);
			t(key + ': no page errors', log.errors.length === 0, log.errors.join(' | '));
			await ctx.close();
		}
	}

	// 3. Unsaved edits in the HTML tab reach the prompt.
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: true });
		await openEdit(page);
		await page.click('#twd-ap-tab-html');
		await page.fill('#twd-ap-html-editor', '<p>Edited in the HTML tab and not saved.</p>');
		await page.click('#twd-ap-tab-remix');
		await page.click('#twd-ap-remix-copy');
		await page.waitForFunction(() => window.__copied !== null);
		t('html tab: an unsaved HTML edit is in the prompt', (await page.evaluate(() => window.__copied)).includes('Edited in the HTML tab and not saved.'));
		await page.click('#twd-ap-tab-html');
		t('html tab: going back shows the same text', (await page.inputValue('#twd-ap-html-editor')).includes('Edited in the HTML tab'));
		await page.click('#twd-ap-tab-visual');
		t('html tab: and the Visual tab has it too', (await page.innerHTML('#twd-ap-visual-editor')).includes('Edited in the HTML tab'));
		await ctx.close();
	}

	// 4. No clipboard: the prompt is selected for copying by hand.
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: false });
		await openEdit(page);
		await page.click('#twd-ap-tab-remix');
		await page.click('#twd-ap-remix-copy');
		t('no clipboard: the prompt box shows with the prompt', await page.locator('#twd-ap-remix-prompt').isVisible() && (await page.inputValue('#twd-ap-remix-prompt')).includes('JOB 1'));
		t('no clipboard: a note says how to copy by hand', (await page.textContent('#twd-ap-remix-copy-note')).includes('select all'));
		t('no clipboard: the text is selected', await page.evaluate(() => { const b = document.getElementById('twd-ap-remix-prompt'); return document.activeElement === b && b.selectionEnd - b.selectionStart === b.value.length; }));
		await ctx.close();
	}

	// 5. Pasting the reply.
	for (const key of ['key-off', 'key-on']) {
		const { ctx, page, log } = await open(browser, key, { clipboard: true });
		await openEdit(page);
		const before = await page.innerHTML('#twd-ap-visual-editor');
		// TWD_REMIX_SHOTS=<folder> saves pictures of the panel and the report for a person to look at.
		const shots = process.env.TWD_REMIX_SHOTS;
		if (shots && key === 'key-off') {
			await page.click('#twd-ap-tab-remix');
			await page.click('#twd-ap-remix-copy');
			await page.screenshot({ path: path.join(shots, 'remix-panel.png') });
			await page.click('#twd-ap-tab-visual');
		}
		await doRemix(page);
		if (shots && key === 'key-off') {
			await page.locator('#twd-ap-original-bar').scrollIntoViewIfNeeded();
			await page.screenshot({ path: path.join(shots, 'remix-result.png') });
			await page.locator('#twd-ap-remix-report').screenshot({ path: path.join(shots, 'remix-report.png') });
		}
		t(key + ': the server is asked once, with the reply, the original text and the nonce', log.checks.length === 1 && log.checks[0].body.reply === D.reply.trim() && log.checks[0].body.original_html.includes('clasp.jpg') && log.checks[0].nonce === 'nonce-1');
		t(key + ': the remixed text is in the editor, on the Visual tab', (await page.innerHTML('#twd-ap-visual-editor')).includes('18th century') && await page.locator('#twd-ap-visual-editor').isVisible() && await page.locator('#twd-ap-fields-wrap').isVisible());
		t(key + ': the bar offers the original text', await page.locator('#twd-ap-original-bar').isVisible() && (await page.textContent('#twd-ap-original-toggle')).trim() === 'Use my original text instead' && (await page.textContent('#twd-ap-original-text')).includes('remixed version'));
		t(key + ': the status says nothing is saved yet', (await page.textContent('#twd-ap-status')).includes('Nothing has been saved yet'));
		t(key + ': nothing was saved', log.saves.length === 0);
		t(key + ': the title and summary are not changed by themselves', (await page.inputValue('#twd-ap-title')) === 'Existing article' && (await page.inputValue('#twd-ap-excerpt')) === 'Old summary');

		// The report.
		const groups = await page.$$eval('#twd-ap-remix-report .twd-ap-remix-group', (els) => els.map((e) => e.className.replace('twd-ap-remix-group ', '')));
		t(key + ': report groups in order: not confirmed, corrected, confirmed', JSON.stringify(groups) === JSON.stringify(['twd-ap-remix-group-not', 'twd-ap-remix-group-corrected', 'twd-ap-remix-group-confirmed']), JSON.stringify(groups));
		const reportText = await page.textContent('#twd-ap-remix-report');
		t(key + ': report says it is private', reportText.includes('Only you can see this. It is not saved or published.'));
		t(key + ': report counts words and results', reportText.includes('Words: ' + D.result.words_before + ' before, ' + D.result.words_after + ' after.') && reportText.includes('2 not confirmed, 1 corrected, 2 confirmed'));
		t(key + ': report names the missing picture', reportText.includes('1 picture or video from your article is missing') && reportText.includes('Picture: https://pics.example.org/clasp.jpg'));
		t(key + ': the missing picture list sits straight under its warning', await page.evaluate(() => { const l = document.querySelector('#twd-ap-remix-report .twd-ap-remix-missing'); return !!l && l.previousElementSibling.textContent.includes('missing from the remix'); }));
		t(key + ': report says invented pictures were left out', reportText.includes('2 pictures or videos that were not in your article were left out.'));
		t(key + ': report lists the new link', reportText.includes('https://new.example.com/page'));
		t(key + ': report shows what changed', reportText.includes('What changed: Cut the repeated paragraph, and fixed the place.'));
		const links = await page.$$eval('#twd-ap-remix-report .twd-ap-remix-sources a', (as) => as.map((a) => ({ href: a.href, target: a.target, rel: a.rel, text: a.textContent })));
		t(key + ': sources are links that open in a new tab, safely', links.length === 9 && links.every((l) => /^https?:/.test(l.href) && l.target === '_blank' && l.rel === 'noopener noreferrer'), JSON.stringify(links.slice(0, 2)));
		t(key + ': a source with a title shows the title', links.some((l) => l.text === 'A reference, work'));
		t(key + ': no markup from the reply becomes part of the page', (await page.$$('#twd-ap-remix-report img, #twd-ap-remix-report script')).length === 0);

		// Suggestions.
		t(key + ': the suggested title is offered', reportText.includes('Suggested title: The Riviere Necklace, a short history'));
		await page.click('#twd-ap-remix-report [data-remix-action="title"]');
		t(key + ': Use this title fills the title', (await page.inputValue('#twd-ap-title')) === 'The Riviere Necklace, a short history' && (await page.textContent('#twd-ap-remix-report [data-remix-action="title"]')).trim() === 'Used');
		await page.click('#twd-ap-remix-report [data-remix-action="meta"]');
		t(key + ': Use this summary fills the summary', (await page.inputValue('#twd-ap-excerpt')) === D.result.meta_description);

		// The swap.
		await page.click('#twd-ap-original-toggle');
		t(key + ': the original text comes back', (await page.innerHTML('#twd-ap-visual-editor')) === before && (await page.textContent('#twd-ap-original-toggle')).trim() === 'Use the remixed version');
		await page.click('#twd-ap-original-toggle');
		t(key + ': and the remix again', (await page.innerHTML('#twd-ap-visual-editor')).includes('18th century'));

		// Hide.
		await page.click('#twd-ap-remix-report [data-remix-action="hide"]');
		t(key + ': Hide report hides it', await page.locator('#twd-ap-remix-report').isHidden());

		// Update saves the remix, once, as normal.
		await page.click('#twd-ap-publish-btn');
		await page.waitForFunction(() => document.getElementById('twd-ap-overlay').hidden, null, { timeout: 5000 }).catch(() => {});
		t(key + ': Update saves the remixed text', log.saves.length === 1 && String(log.saves[0].content || log.saves[0].content_html || '').includes('18th century'), JSON.stringify(Object.keys(log.saves[0] || {})));
		t(key + ': no page errors', log.errors.length === 0, log.errors.join(' | '));
		await ctx.close();
	}

	// 6. A second remix keeps the real original.
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: true });
		await openEdit(page);
		const before = await page.innerHTML('#twd-ap-visual-editor');
		await doRemix(page);
		await doRemix(page);
		await page.click('#twd-ap-original-toggle');
		t('twice: the original is still the article as it was', (await page.innerHTML('#twd-ap-visual-editor')) === before);
		await ctx.close();
	}

	// 7. Refusals leave the editor alone.
	{
		const { ctx, page, log } = await open(browser, 'key-off', { clipboard: true, check: { status: 400, json: { code: 'twd_ap_remix_not_json', message: 'That reply is not the JSON the prompt asks for.' } } });
		await openEdit(page);
		const before = await page.innerHTML('#twd-ap-visual-editor');
		await page.click('#twd-ap-tab-remix');
		await page.click('#twd-ap-remix-check');
		t('refusal: an empty box is refused without asking the server', log.checks.length === 0 && (await page.textContent('#twd-ap-status')).includes('Paste the reply'));
		await page.fill('#twd-ap-remix-input', 'not json');
		await page.click('#twd-ap-remix-check');
		await page.waitForFunction(() => document.getElementById('twd-ap-status').textContent.includes('not the JSON'));
		t('refusal: the server message is shown', true);
		t('refusal: the panel stays open with the paste still there', await page.locator('#twd-ap-remix-panel').isVisible() && (await page.inputValue('#twd-ap-remix-input')) === 'not json');
		t('refusal: the text is unchanged and there is no bar or report', (await page.innerHTML('#twd-ap-visual-editor')) === before && await page.locator('#twd-ap-original-bar').isHidden() && await page.locator('#twd-ap-remix-report').isHidden());
		await ctx.close();
	}
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: true, check: 'abort' });
		await openEdit(page);
		await page.click('#twd-ap-tab-remix');
		await page.fill('#twd-ap-remix-input', D.reply);
		await page.click('#twd-ap-remix-check');
		await page.waitForFunction(() => document.getElementById('twd-ap-status').textContent.includes('Something went wrong'));
		t('network failure: a plain message, and the button works again', !(await page.isDisabled('#twd-ap-remix-check')));
		await ctx.close();
	}

	// 8. Opening another article clears everything.
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: true });
		await openEdit(page);
		await doRemix(page);
		await page.click('#twd-ap-close-btn');
		await page.click('#twd-ap-new-btn');
		t('reset: a new article has no report, no bar and no Remix tab', await page.locator('#twd-ap-remix-report').isHidden() && await page.locator('#twd-ap-original-bar').isHidden() && await page.locator('#twd-ap-tab-remix').isHidden());
		await page.click('#twd-ap-close-btn');
		await openEdit(page);
		t('reset: reopening an article starts clean, length back to two thirds', await page.locator('#twd-ap-remix-report').isHidden() && (await page.inputValue('input[name="twd-ap-remix-length"]:checked')) === 'two-thirds' && (await page.inputValue('#twd-ap-remix-prompt')) === '');
		await ctx.close();
	}

	// 9. An article with no text has no Remix tab.
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: true, content: '<p> </p>' });
		await openEdit(page);
		t('empty article: no Remix tab', await page.locator('#twd-ap-tab-remix').isHidden());
		await ctx.close();
	}

	// 10. A hostile theme cannot un-hide the tab or the report.
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: true });
		await page.addStyleTag({ content: '.twd-ap-tab, .twd-ap-remix-report, #twd-ap-tab-remix { display: inline-block !important; }' });
		await page.click('#twd-ap-new-btn');
		t('hostile theme: the Remix tab stays hidden on a new article', await page.locator('#twd-ap-tab-remix').isHidden());
		t('hostile theme: the report stays hidden', await page.locator('#twd-ap-remix-report').isHidden());
		await ctx.close();
	}

	// 11. Phone width: the panel fits.
	{
		const { ctx, page } = await open(browser, 'key-off', { clipboard: true });
		await page.setViewportSize({ width: 375, height: 812 });
		await openEdit(page);
		await doRemix(page);
		const over = await page.evaluate(() => { const m = document.getElementById('twd-ap-modal'); return m.scrollWidth - m.clientWidth; });
		t('phone: the report does not make the popup scroll sideways', over <= 1, String(over));
		await page.click('#twd-ap-tab-remix');
		const over2 = await page.evaluate(() => { const m = document.getElementById('twd-ap-modal'); return m.scrollWidth - m.clientWidth; });
		t('phone: nor does the panel', over2 <= 1, String(over2));
		await ctx.close();
	}

	await browser.close();
	console.log('\n' + pass + ' passed, ' + fail + ' failed');
	process.exit(fail ? 1 : 0);
})();
