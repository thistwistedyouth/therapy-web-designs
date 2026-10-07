<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="twd-ap-buttons" class="twd-ap-buttons">
	<?php if ( $show_new ) : ?>
		<button type="button" id="twd-ap-new-btn" class="twd-ap-float-btn twd-ap-new-btn">
			<span class="twd-ap-plus">+</span> <?php esc_html_e( 'New Article', 'twd-article-publisher' ); ?>
		</button>
	<?php endif; ?>
	<?php if ( $show_edit ) : ?>
		<button type="button" id="twd-ap-edit-btn" class="twd-ap-float-btn twd-ap-edit-btn">
			<?php esc_html_e( 'Edit This Article', 'twd-article-publisher' ); ?>
		</button>
	<?php endif; ?>
</div>

<div id="twd-ap-overlay" class="twd-ap-overlay" hidden>
	<div id="twd-ap-modal" class="twd-ap-modal" role="dialog" aria-modal="true" aria-labelledby="twd-ap-modal-title">
		<div class="twd-ap-modal-header">
			<div class="twd-ap-modal-title-wrap">
				<?php
				$twd_ap_logo_id  = get_theme_mod( 'custom_logo' );
				$twd_ap_logo_src = $twd_ap_logo_id ? wp_get_attachment_image_url( $twd_ap_logo_id, 'medium' ) : '';
				?>
				<?php if ( $twd_ap_logo_src ) : ?>
					<span class="twd-ap-modal-logo"><img src="<?php echo esc_url( $twd_ap_logo_src ); ?>" alt="" /></span>
				<?php endif; ?>
				<span class="twd-ap-modal-title-text">
					<p id="twd-ap-modal-title" class="twd-ap-modal-title"><?php esc_html_e( 'Resource Maker', 'twd-article-publisher' ); ?></p>
					<p id="twd-ap-modal-subtitle" class="twd-ap-modal-subtitle"><?php esc_html_e( 'New article', 'twd-article-publisher' ); ?></p>
				</span>
			</div>
			<div class="twd-ap-modal-header-actions">
				<button type="button" id="twd-ap-delete-btn" class="twd-ap-delete-btn" hidden><span aria-hidden="true">&#128465;</span> <?php esc_html_e( 'Delete this article', 'twd-article-publisher' ); ?></button>
				<a href="#" id="twd-ap-admin-edit-link" class="twd-ap-admin-edit-link" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Edit in WordPress ↗', 'twd-article-publisher' ); ?></a>
				<a href="#" id="twd-ap-swipebook-link" class="twd-ap-admin-edit-link" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Swipe book link ↗', 'twd-article-publisher' ); ?></a>
				<button type="button" id="twd-ap-summary-book-btn" class="twd-ap-admin-edit-link" hidden><?php esc_html_e( 'Summary Book', 'twd-article-publisher' ); ?></button>
				<?php if ( ! ( class_exists( 'TWD_AP_AI_Generate' ) && TWD_AP_AI_Generate::is_configured() ) ) : ?>
					<a href="<?php echo esc_url( TWD_AP_ARTICLE_ASSIST_URL ); ?>" class="twd-ap-assist-btn" target="_blank" rel="noopener"><?php esc_html_e( 'Article Assist', 'twd-article-publisher' ); ?></a>
				<?php endif; ?>
				<button type="button" id="twd-ap-help-btn" class="twd-ap-help-icon-btn" aria-label="<?php esc_attr_e( 'Instructions for use', 'twd-article-publisher' ); ?>" title="<?php esc_attr_e( 'Instructions for use', 'twd-article-publisher' ); ?>">?</button>
			</div>
			<button type="button" id="twd-ap-close-btn" class="twd-ap-close-btn" aria-label="<?php esc_attr_e( 'Close', 'twd-article-publisher' ); ?>">&times;</button>
		</div>

		<div class="twd-ap-modal-body">
			<p id="twd-ap-status" class="twd-ap-status" hidden></p>

			<?php if ( class_exists( 'TWD_AP_AI_Generate' ) && TWD_AP_AI_Generate::is_configured() ) : ?>
				<div id="twd-ap-start" class="twd-ap-start">
					<label class="twd-ap-start-heading" for="twd-ap-start-input"><?php esc_html_e( 'Write, dictate or paste your article ideas or a full article', 'twd-article-publisher' ); ?></label>
					<textarea id="twd-ap-start-input" class="twd-ap-start-input" rows="10" placeholder="<?php esc_attr_e( 'Type, use your device\'s dictation, or paste here...', 'twd-article-publisher' ); ?>"></textarea>
					<p class="twd-ap-start-count" id="twd-ap-start-count" aria-live="polite"></p>

					<fieldset class="twd-ap-start-modes">
						<legend class="twd-ap-start-legend"><?php esc_html_e( 'What have you given me?', 'twd-article-publisher' ); ?></legend>
						<label class="twd-ap-start-mode">
							<input type="radio" name="twd-ap-start-mode" value="idea" checked />
							<span class="twd-ap-start-mode-text"><strong><?php esc_html_e( 'Article idea', 'twd-article-publisher' ); ?></strong><em><?php esc_html_e( 'Write a full article from my idea', 'twd-article-publisher' ); ?></em></span>
						</label>
						<label class="twd-ap-start-mode">
							<input type="radio" name="twd-ap-start-mode" value="improve" />
							<span class="twd-ap-start-mode-text"><strong><?php esc_html_e( 'Draft article, improve', 'twd-article-publisher' ); ?></strong><em><?php esc_html_e( 'Keep my voice and meaning, tidy and improve it', 'twd-article-publisher' ); ?></em></span>
						</label>
						<label class="twd-ap-start-mode">
							<input type="radio" name="twd-ap-start-mode" value="format" />
							<span class="twd-ap-start-mode-text"><strong><?php esc_html_e( 'Finished article, only format', 'twd-article-publisher' ); ?></strong><em><?php esc_html_e( 'Keep my words exactly, only add structure and the details', 'twd-article-publisher' ); ?></em></span>
						</label>
					</fieldset>

					<p class="twd-ap-start-note"><?php esc_html_e( 'Your text is sent to an AI service to do this. Leave out client names and details.', 'twd-article-publisher' ); ?></p>

					<div class="twd-ap-start-actions">
						<button type="button" id="twd-ap-start-generate" class="twd-ap-btn-primary"><?php esc_html_e( 'Generate', 'twd-article-publisher' ); ?></button>
						<span id="twd-ap-start-left" class="twd-ap-start-left" aria-live="polite"></span>
						<button type="button" id="twd-ap-start-skip" class="twd-ap-start-skip"><?php esc_html_e( 'Write it myself instead', 'twd-article-publisher' ); ?></button>
					</div>
				</div>

				<div id="twd-ap-original-bar" class="twd-ap-original-bar" hidden>
					<span id="twd-ap-original-text" class="twd-ap-original-text"></span>
					<button type="button" id="twd-ap-original-toggle" class="twd-ap-original-toggle"></button>
				</div>
			<?php endif; ?>

			<div id="twd-ap-title-wrap">
				<label class="twd-ap-label" for="twd-ap-title"><?php esc_html_e( 'Title', 'twd-article-publisher' ); ?></label>
				<input type="text" id="twd-ap-title" class="twd-ap-input" placeholder="<?php esc_attr_e( 'Article title', 'twd-article-publisher' ); ?>" />
			</div>

			<label class="twd-ap-label twd-ap-spaced"><?php esc_html_e( 'Article content', 'twd-article-publisher' ); ?></label>
			<div class="twd-ap-editor-tabs">
				<button type="button" id="twd-ap-tab-visual" class="twd-ap-tab twd-ap-tab-active" data-tab="visual"><?php esc_html_e( 'Visual', 'twd-article-publisher' ); ?></button>
				<button type="button" id="twd-ap-tab-html" class="twd-ap-tab" data-tab="html"><?php esc_html_e( 'HTML', 'twd-article-publisher' ); ?></button>
				<button type="button" id="twd-ap-tab-json" class="twd-ap-tab" data-tab="json"><?php esc_html_e( 'Paste JSON', 'twd-article-publisher' ); ?></button>
			</div>

			<div id="twd-ap-toolbar" class="twd-ap-toolbar">
				<button type="button" data-cmd="bold" title="<?php esc_attr_e( 'Bold', 'twd-article-publisher' ); ?>"><b>B</b></button>
				<button type="button" data-cmd="italic" title="<?php esc_attr_e( 'Italic', 'twd-article-publisher' ); ?>"><i>i</i></button>
				<button type="button" data-block="h2" title="Heading 2">H2</button>
				<button type="button" data-block="h3" title="Heading 3">H3</button>
				<button type="button" data-block="p" title="Paragraph">&para;</button>
				<button type="button" data-cmd="insertUnorderedList" title="<?php esc_attr_e( 'Bullet list', 'twd-article-publisher' ); ?>">&bull; List</button>
				<button type="button" data-cmd="insertOrderedList" title="<?php esc_attr_e( 'Numbered list', 'twd-article-publisher' ); ?>">1. List</button>
				<button type="button" data-block="blockquote" title="<?php esc_attr_e( 'Quote', 'twd-article-publisher' ); ?>">&ldquo;&rdquo;</button>
				<button type="button" id="twd-ap-link-btn" title="<?php esc_attr_e( 'Insert link', 'twd-article-publisher' ); ?>"><?php esc_html_e( 'Link', 'twd-article-publisher' ); ?></button>
				<button type="button" id="twd-ap-image-btn" title="<?php esc_attr_e( 'Insert image', 'twd-article-publisher' ); ?>"><?php esc_html_e( 'Image', 'twd-article-publisher' ); ?></button>
				<button type="button" id="twd-ap-youtube-btn" title="<?php esc_attr_e( 'Insert YouTube video', 'twd-article-publisher' ); ?>"><?php esc_html_e( 'YouTube', 'twd-article-publisher' ); ?></button>
			</div>

			<div id="twd-ap-visual-editor" class="twd-ap-visual-editor" contenteditable="true" data-placeholder="<?php esc_attr_e( 'Write, or paste AI-formatted HTML using the HTML tab above', 'twd-article-publisher' ); ?>"></div>
			<textarea id="twd-ap-html-editor" class="twd-ap-html-editor" hidden placeholder="<?php esc_attr_e( 'Paste formatted HTML here', 'twd-article-publisher' ); ?>"></textarea>
			<div id="twd-ap-json-panel" class="twd-ap-json-panel" hidden>
				<p class="twd-ap-muted"><?php esc_html_e( 'Paste a JSON block from Article Assist (the button above, or anywhere producing the same shape). Fill fields from it below to bring in the title, excerpt, SEO fields, category, tags and content in one go. You can still edit anything afterward.', 'twd-article-publisher' ); ?></p>
				<textarea id="twd-ap-json-input" class="twd-ap-html-editor" rows="8" placeholder='{"title": "...", "seo_title": "...", "meta_description": "...", "category": "...", "tags": "...", "html": "..."}'></textarea>
				<div class="twd-ap-btn-row twd-ap-spaced">
					<button type="button" id="twd-ap-json-fill-btn" class="twd-ap-btn-primary"><?php esc_html_e( 'Fill fields from JSON', 'twd-article-publisher' ); ?></button>
				</div>
			</div>

			<div id="twd-ap-fields-wrap">
				<label class="twd-ap-label twd-ap-spaced" for="twd-ap-excerpt"><?php esc_html_e( 'Excerpt (shown on article cards)', 'twd-article-publisher' ); ?></label>
				<textarea id="twd-ap-excerpt" class="twd-ap-textarea-small" placeholder="<?php esc_attr_e( 'A short summary of the article', 'twd-article-publisher' ); ?>"></textarea>

				<div class="twd-ap-section">
					<p class="twd-ap-section-heading"><?php esc_html_e( 'Details', 'twd-article-publisher' ); ?></p>

					<label class="twd-ap-checkbox-label twd-ap-feature-toggle">
						<input type="checkbox" id="twd-ap-featured-toggle" />
						<?php esc_html_e( 'Feature this article (shows first on the resources grid)', 'twd-article-publisher' ); ?>
					</label>

					<label class="twd-ap-checkbox-label twd-ap-feature-toggle">
						<input type="checkbox" id="twd-ap-include-bio-toggle" />
						<?php esc_html_e( 'Include bio page at end (swipe book closing slide, from Profile page setup)', 'twd-article-publisher' ); ?>
					</label>

					<label class="twd-ap-label twd-ap-spaced"><?php esc_html_e( 'More articles slide (swipe book closing section)', 'twd-article-publisher' ); ?></label>
					<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'Leave all unticked to show the 6 most recent other articles automatically. Tick up to 6 to choose exactly which ones show here instead.', 'twd-article-publisher' ); ?></p>
					<ul id="twd-ap-related-picker" class="twd-ap-related-picker"></ul>

					<div class="twd-ap-field-row">
						<div class="twd-ap-field-col">
							<label class="twd-ap-label"><?php esc_html_e( 'Featured image', 'twd-article-publisher' ); ?></label>
							<div id="twd-ap-featured-preview" class="twd-ap-featured-preview"></div>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'Recommended 3:2 (e.g. 1200×800px)', 'twd-article-publisher' ); ?></p>
							<div class="twd-ap-btn-row">
								<button type="button" id="twd-ap-featured-select" class="twd-ap-btn-secondary"><?php esc_html_e( 'Choose Image', 'twd-article-publisher' ); ?></button>
								<button type="button" id="twd-ap-featured-remove" class="twd-ap-btn-text" hidden><?php esc_html_e( 'Remove', 'twd-article-publisher' ); ?></button>
							</div>
						</div>

						<div class="twd-ap-field-col">
							<label class="twd-ap-label"><?php esc_html_e( 'Categories', 'twd-article-publisher' ); ?></label>
							<div id="twd-ap-categories" class="twd-ap-categories">
								<span class="twd-ap-muted"><?php esc_html_e( 'Loading…', 'twd-article-publisher' ); ?></span>
							</div>
							<p class="twd-ap-muted twd-ap-hint"><?php esc_html_e( 'Click the star to set which category this article is grouped under on the resources page.', 'twd-article-publisher' ); ?></p>
							<div class="twd-ap-btn-row twd-ap-spaced">
								<input type="text" id="twd-ap-cat-add-input" class="twd-ap-input" placeholder="<?php esc_attr_e( 'New category name', 'twd-article-publisher' ); ?>" />
								<button type="button" id="twd-ap-cat-add-btn" class="twd-ap-btn-secondary"><?php esc_html_e( 'Add', 'twd-article-publisher' ); ?></button>
							</div>
						</div>
					</div>

					<label class="twd-ap-label twd-ap-spaced" for="twd-ap-tags"><?php esc_html_e( 'Tags (comma separated)', 'twd-article-publisher' ); ?></label>
					<input type="text" id="twd-ap-tags" class="twd-ap-input" list="twd-ap-tag-datalist" placeholder="<?php esc_attr_e( 'anxiety, self-care, mindfulness', 'twd-article-publisher' ); ?>" />
					<datalist id="twd-ap-tag-datalist"></datalist>
				</div>

				<div id="twd-ap-yoast-fields" class="twd-ap-yoast-fields" hidden>
					<p class="twd-ap-section-heading"><?php esc_html_e( 'SEO', 'twd-article-publisher' ); ?></p>
					<label class="twd-ap-label" for="twd-ap-yoast-title"><?php esc_html_e( 'SEO title', 'twd-article-publisher' ); ?></label>
					<input type="text" id="twd-ap-yoast-title" class="twd-ap-input" />
					<label class="twd-ap-label" for="twd-ap-yoast-desc"><?php esc_html_e( 'Meta description', 'twd-article-publisher' ); ?></label>
					<textarea id="twd-ap-yoast-desc" class="twd-ap-textarea-small"></textarea>
				</div>

				<div class="twd-ap-schedule-row">
					<label class="twd-ap-checkbox-label">
						<input type="checkbox" id="twd-ap-schedule-toggle" />
						<?php esc_html_e( 'Schedule for later', 'twd-article-publisher' ); ?>
					</label>
					<input type="datetime-local" id="twd-ap-schedule-date" class="twd-ap-input twd-ap-schedule-date" hidden />
				</div>
			</div>
		</div>

		<div id="twd-ap-modal-footer" class="twd-ap-modal-footer">
			<div class="twd-ap-footer-spacer"></div>
			<button type="button" id="twd-ap-draft-btn" class="twd-ap-btn-secondary"><?php esc_html_e( 'Save Draft', 'twd-article-publisher' ); ?></button>
			<button type="button" id="twd-ap-publish-btn" class="twd-ap-btn-primary"><?php esc_html_e( 'Publish', 'twd-article-publisher' ); ?></button>
		</div>
	</div>
</div>

<div id="twd-ap-help-overlay" class="twd-ap-overlay twd-ap-help-overlay" hidden>
	<div id="twd-ap-help-modal" class="twd-ap-modal" role="dialog" aria-modal="true" aria-labelledby="twd-ap-help-title">
		<div class="twd-ap-modal-header">
			<p id="twd-ap-help-title" class="twd-ap-modal-title"><?php esc_html_e( 'Instructions for use', 'twd-article-publisher' ); ?></p>
			<button type="button" id="twd-ap-help-close-btn" class="twd-ap-close-btn" aria-label="<?php esc_attr_e( 'Close', 'twd-article-publisher' ); ?>">&times;</button>
		</div>
		<div class="twd-ap-modal-body twd-ap-help-body">

			<p class="twd-ap-section-heading"><?php esc_html_e( 'Writing an article', 'twd-article-publisher' ); ?></p>
			<ul class="twd-ap-help-list">
				<?php if ( class_exists( 'TWD_AP_AI_Generate' ) && TWD_AP_AI_Generate::is_configured() ) : ?>
					<li><?php esc_html_e( 'A new article starts with one box: write, dictate or paste your idea, a rough draft or a finished article, choose which it is, and click Generate. The editor then opens with the title, summary, tags and article filled in. After a draft or finished article, a button lets you swap back to your own text and forward again. Click "Write it myself instead" to skip this.', 'twd-article-publisher' ); ?></li>
				<?php endif; ?>
				<li><?php esc_html_e( 'Write directly in the Visual tab, or write with an AI assistant and paste the HTML into the HTML tab (see the AI prompt below).', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'Use the toolbar for headings, bold/italic, lists and quotes. Only H2/H3/H4 are available; any H1 you paste in is automatically changed to H2 so it never clashes with the page\'s own title.', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'Click the Image button to insert a photo inline from your Media Library or by uploading a new one.', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'Click the YouTube button and paste a video link to embed it. It only loads when a visitor clicks play.', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'Click any inline image to align it left/center/right/full-width, and drag its bottom-right corner to resize it.', 'twd-article-publisher' ); ?></li>
			</ul>

			<p class="twd-ap-section-heading twd-ap-spaced"><?php esc_html_e( 'Details, categories & tags', 'twd-article-publisher' ); ?></p>
			<ul class="twd-ap-help-list">
				<li><?php esc_html_e( 'Featured image should be roughly 3:2 (e.g. 1200×800px) so it isn\'t cropped oddly on article cards.', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'Tick as many categories as apply, or type a new one under the list and click Add.', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'Tags are comma separated (e.g. anxiety, self-care, mindfulness). Start typing to see existing tags.', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'Save Draft any time to save your progress without publishing. Tick "Schedule for later" to publish automatically at a future date and time.', 'twd-article-publisher' ); ?></li>
			</ul>

			<p class="twd-ap-section-heading twd-ap-spaced"><?php esc_html_e( 'Showing articles on a page', 'twd-article-publisher' ); ?></p>
			<p><?php esc_html_e( 'Add this shortcode to any page (e.g. in an Elementor Shortcode or HTML widget) to display a grid of published articles:', 'twd-article-publisher' ); ?></p>
			<div class="twd-ap-code-row">
				<code id="twd-ap-shortcode-example">[twd_articles count="6" columns="3"]</code>
				<button type="button" id="twd-ap-copy-shortcode-btn" class="twd-ap-btn-secondary"><?php esc_html_e( 'Copy', 'twd-article-publisher' ); ?></button>
			</div>
			<p class="twd-ap-muted"><?php esc_html_e( 'Optional: category="slug", tag="slug", count="6", columns="3" (max 4).', 'twd-article-publisher' ); ?></p>

			<p class="twd-ap-section-heading twd-ap-spaced"><?php esc_html_e( 'Writing HTML articles with an AI prompt', 'twd-article-publisher' ); ?></p>
			<p><?php esc_html_e( 'Copy this prompt into ChatGPT, Claude or similar, fill in the brackets, then paste the HTML it gives you into the HTML tab above:', 'twd-article-publisher' ); ?></p>
			<div class="twd-ap-code-row twd-ap-code-row-block">
				<pre id="twd-ap-ai-prompt-example">Write a blog article as valid HTML only, following these rules:
- Do not include an &lt;h1&gt; tag. Start with &lt;h2&gt; for the first heading, and use &lt;h3&gt; for subheadings.
- Only use these tags: &lt;p&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;h4&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;blockquote&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;a href=""&gt;, &lt;hr&gt;.
- No inline styles, no &lt;div&gt;, &lt;span&gt;, &lt;table&gt; or &lt;script&gt; tags -- they will be stripped out.
- Keep paragraphs short (2-4 sentences) with a subheading every 2-3 paragraphs.
- Topic: [describe your topic]
- Audience: [e.g. anxious first-time therapy clients]
- Tone: [e.g. warm, reassuring, plain-English]
- Length: [e.g. 600-800 words]</pre>
				<button type="button" id="twd-ap-copy-prompt-btn" class="twd-ap-btn-secondary"><?php esc_html_e( 'Copy prompt', 'twd-article-publisher' ); ?></button>
			</div>
			<p class="twd-ap-muted"><?php esc_html_e( 'The AI can\'t add real images or a real featured image for you -- add those yourself afterwards using the Image button and the Featured image picker.', 'twd-article-publisher' ); ?></p>

			<p class="twd-ap-section-heading twd-ap-spaced"><?php esc_html_e( 'Editing an existing article', 'twd-article-publisher' ); ?></p>
			<ul class="twd-ap-help-list">
				<li><?php esc_html_e( 'Open any article you can edit and click "Edit This Article" to change it in this same popup.', 'twd-article-publisher' ); ?></li>
				<li><?php esc_html_e( 'The "Edit in WordPress" link opens the normal WordPress editor for advanced changes. Avoid using Elementor to edit articles published here -- once a post is edited in Elementor, further edits made in this popup may stop appearing on the page.', 'twd-article-publisher' ); ?></li>
			</ul>

		</div>
	</div>
</div>

<div id="twd-ap-summary-book-overlay" class="twd-ap-overlay twd-ap-summary-book-overlay" hidden>
	<div id="twd-ap-summary-book-modal" class="twd-ap-modal" role="dialog" aria-modal="true" aria-labelledby="twd-ap-summary-book-title">
		<div class="twd-ap-modal-header">
			<p id="twd-ap-summary-book-title" class="twd-ap-modal-title"><?php esc_html_e( 'Summary Book', 'twd-article-publisher' ); ?></p>
			<button type="button" id="twd-ap-summary-book-close-btn" class="twd-ap-close-btn" aria-label="<?php esc_attr_e( 'Close', 'twd-article-publisher' ); ?>">&times;</button>
		</div>
		<div class="twd-ap-modal-body">
			<p id="twd-ap-summary-book-status" class="twd-ap-status" hidden></p>
			<p id="twd-ap-summary-book-empty" class="twd-ap-muted" hidden><?php esc_html_e( 'No draft yet. Paste a JSON block with a summary book from Article Assist, save the article, then reopen this.', 'twd-article-publisher' ); ?></p>
			<p id="twd-ap-summary-book-published-note" class="twd-ap-muted" hidden><?php esc_html_e( 'This Summary Book is live. Review your edits below, then publish to update it.', 'twd-article-publisher' ); ?></p>
			<ul id="twd-ap-summary-book-cards" class="twd-ap-summary-book-cards"></ul>
			<button type="button" id="twd-ap-summary-book-add-btn" class="twd-ap-btn-secondary"><?php esc_html_e( '+ Add card', 'twd-article-publisher' ); ?></button>
		</div>
		<div class="twd-ap-modal-footer">
			<div class="twd-ap-footer-spacer"></div>
			<button type="button" id="twd-ap-summary-book-save-btn" class="twd-ap-btn-secondary"><?php esc_html_e( 'Save Draft', 'twd-article-publisher' ); ?></button>
			<button type="button" id="twd-ap-summary-book-publish-btn" class="twd-ap-btn-primary"><?php esc_html_e( 'Publish Summary Book', 'twd-article-publisher' ); ?></button>
		</div>
	</div>
</div>
