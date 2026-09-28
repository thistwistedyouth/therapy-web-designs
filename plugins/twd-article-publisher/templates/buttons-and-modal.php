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
			<p id="twd-ap-modal-title" class="twd-ap-modal-title"><?php esc_html_e( 'New Article', 'twd-article-publisher' ); ?></p>
			<button type="button" id="twd-ap-close-btn" class="twd-ap-close-btn" aria-label="<?php esc_attr_e( 'Close', 'twd-article-publisher' ); ?>">&times;</button>
		</div>

		<div class="twd-ap-modal-body">
			<p id="twd-ap-status" class="twd-ap-status" hidden></p>

			<label class="twd-ap-label" for="twd-ap-title"><?php esc_html_e( 'Title', 'twd-article-publisher' ); ?></label>
			<input type="text" id="twd-ap-title" class="twd-ap-input" placeholder="<?php esc_attr_e( 'Article title', 'twd-article-publisher' ); ?>" />

			<label class="twd-ap-label twd-ap-spaced"><?php esc_html_e( 'Article content', 'twd-article-publisher' ); ?></label>
			<div class="twd-ap-editor-tabs">
				<button type="button" id="twd-ap-tab-visual" class="twd-ap-tab twd-ap-tab-active" data-tab="visual"><?php esc_html_e( 'Visual', 'twd-article-publisher' ); ?></button>
				<button type="button" id="twd-ap-tab-html" class="twd-ap-tab" data-tab="html"><?php esc_html_e( 'HTML', 'twd-article-publisher' ); ?></button>
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

			<label class="twd-ap-label twd-ap-spaced" for="twd-ap-excerpt"><?php esc_html_e( 'Excerpt (shown on article cards)', 'twd-article-publisher' ); ?></label>
			<textarea id="twd-ap-excerpt" class="twd-ap-textarea-small" placeholder="<?php esc_attr_e( 'A short summary of the article', 'twd-article-publisher' ); ?>"></textarea>

			<div class="twd-ap-section">
				<p class="twd-ap-section-heading"><?php esc_html_e( 'Details', 'twd-article-publisher' ); ?></p>

				<div class="twd-ap-field-row">
					<div class="twd-ap-field-col">
						<label class="twd-ap-label"><?php esc_html_e( 'Featured image', 'twd-article-publisher' ); ?></label>
						<div id="twd-ap-featured-preview" class="twd-ap-featured-preview"></div>
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

		<div class="twd-ap-modal-footer">
			<button type="button" id="twd-ap-draft-btn" class="twd-ap-btn-secondary"><?php esc_html_e( 'Save Draft', 'twd-article-publisher' ); ?></button>
			<button type="button" id="twd-ap-publish-btn" class="twd-ap-btn-primary"><?php esc_html_e( 'Publish', 'twd-article-publisher' ); ?></button>
		</div>
	</div>
</div>
