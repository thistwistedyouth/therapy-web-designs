(function () {
	'use strict';
	if (typeof TWD_AP === 'undefined') {
		return;
	}

	var state = {
		editingId: null,
		featuredMediaId: 0,
		activeTab: 'visual',
		dirty: false,
		categories: [],
		primaryCategoryId: 0,
		relatedIds: [],
		relatedCandidatesLoaded: false,
		summaryBookDraft: null,
		summaryBookHasDraft: false,
		summaryBookIsPublished: false,
		articleStatus: '',
		aiMode: 'idea',
		aiGenerating: false,
	};
	var RELATED_MAX = 6;
	var SUMMARY_BOOK_TYPES = ['text', 'quote', 'question'];

	var els = {};
	var featuredFrame = null;
	var inlineFrame = null;
	var savedRange = null;
	var imgToolbar = null;
	var resizeHandle = null;
	var selectedImg = null;

	document.addEventListener('DOMContentLoaded', init);

	function init() {
		els.overlay = document.getElementById('twd-ap-overlay');
		if (!els.overlay) {
			return;
		}

		els.modal = document.getElementById('twd-ap-modal');
		els.modalTitle = document.getElementById('twd-ap-modal-title');
		els.modalSubtitle = document.getElementById('twd-ap-modal-subtitle');
		els.status = document.getElementById('twd-ap-status');
		els.title = document.getElementById('twd-ap-title');
		els.titleWrap = document.getElementById('twd-ap-title-wrap');
		els.fieldsWrap = document.getElementById('twd-ap-fields-wrap');
		els.modalFooter = document.getElementById('twd-ap-modal-footer');
		els.featuredPreview = document.getElementById('twd-ap-featured-preview');
		els.featuredSelect = document.getElementById('twd-ap-featured-select');
		els.featuredRemove = document.getElementById('twd-ap-featured-remove');
		els.featuredToggle = document.getElementById('twd-ap-featured-toggle');
		els.includeBioToggle = document.getElementById('twd-ap-include-bio-toggle');
		els.relatedPicker = document.getElementById('twd-ap-related-picker');
		els.categories = document.getElementById('twd-ap-categories');
		els.catAddInput = document.getElementById('twd-ap-cat-add-input');
		els.catAddBtn = document.getElementById('twd-ap-cat-add-btn');
		els.tags = document.getElementById('twd-ap-tags');
		els.tagDatalist = document.getElementById('twd-ap-tag-datalist');
		els.youtubeBtn = document.getElementById('twd-ap-youtube-btn');
		els.tabVisual = document.getElementById('twd-ap-tab-visual');
		els.tabHtml = document.getElementById('twd-ap-tab-html');
		els.tabJson = document.getElementById('twd-ap-tab-json');
		els.jsonPanel = document.getElementById('twd-ap-json-panel');
		els.jsonInput = document.getElementById('twd-ap-json-input');
		els.jsonFillBtn = document.getElementById('twd-ap-json-fill-btn');
		els.aiModeIdea = document.getElementById('twd-ap-ai-mode-idea');
		els.aiModeFormat = document.getElementById('twd-ap-ai-mode-format');
		els.aiInput = document.getElementById('twd-ap-ai-input');
		els.aiGenerateBtn = document.getElementById('twd-ap-ai-generate-btn');
		els.toolbar = document.getElementById('twd-ap-toolbar');
		els.visualEditor = document.getElementById('twd-ap-visual-editor');
		els.htmlEditor = document.getElementById('twd-ap-html-editor');
		els.excerpt = document.getElementById('twd-ap-excerpt');
		els.yoastWrap = document.getElementById('twd-ap-yoast-fields');
		els.yoastTitle = document.getElementById('twd-ap-yoast-title');
		els.yoastDesc = document.getElementById('twd-ap-yoast-desc');
		els.scheduleToggle = document.getElementById('twd-ap-schedule-toggle');
		els.scheduleDate = document.getElementById('twd-ap-schedule-date');
		els.draftBtn = document.getElementById('twd-ap-draft-btn');
		els.publishBtn = document.getElementById('twd-ap-publish-btn');
		els.closeBtn = document.getElementById('twd-ap-close-btn');
		els.deleteBtn = document.getElementById('twd-ap-delete-btn');
		els.adminEditLink = document.getElementById('twd-ap-admin-edit-link');
		els.swipebookLink = document.getElementById('twd-ap-swipebook-link');
		els.summaryBookBtn = document.getElementById('twd-ap-summary-book-btn');
		els.summaryBookOverlay = document.getElementById('twd-ap-summary-book-overlay');
		els.summaryBookCloseBtn = document.getElementById('twd-ap-summary-book-close-btn');
		els.summaryBookStatus = document.getElementById('twd-ap-summary-book-status');
		els.summaryBookEmpty = document.getElementById('twd-ap-summary-book-empty');
		els.summaryBookPublishedNote = document.getElementById('twd-ap-summary-book-published-note');
		els.summaryBookCards = document.getElementById('twd-ap-summary-book-cards');
		els.summaryBookAddBtn = document.getElementById('twd-ap-summary-book-add-btn');
		els.summaryBookSaveBtn = document.getElementById('twd-ap-summary-book-save-btn');
		els.summaryBookPublishBtn = document.getElementById('twd-ap-summary-book-publish-btn');
		els.helpBtn = document.getElementById('twd-ap-help-btn');
		els.helpOverlay = document.getElementById('twd-ap-help-overlay');
		els.helpCloseBtn = document.getElementById('twd-ap-help-close-btn');
		els.copyShortcodeBtn = document.getElementById('twd-ap-copy-shortcode-btn');
		els.shortcodeExample = document.getElementById('twd-ap-shortcode-example');
		els.copyPromptBtn = document.getElementById('twd-ap-copy-prompt-btn');
		els.aiPromptExample = document.getElementById('twd-ap-ai-prompt-example');
		els.newBtn = document.getElementById('twd-ap-new-btn');
		els.editBtn = document.getElementById('twd-ap-edit-btn');
		els.linkBtn = document.getElementById('twd-ap-link-btn');
		els.imageBtn = document.getElementById('twd-ap-image-btn');

		if (els.newBtn) {
			els.newBtn.addEventListener('click', function () {
				openModal('new');
			});
		}
		if (els.editBtn) {
			els.editBtn.addEventListener('click', function () {
				openModal('edit', TWD_AP.currentPostId);
			});
		}

		els.closeBtn.addEventListener('click', requestClose);
		els.deleteBtn.addEventListener('click', requestDelete);
		els.overlay.addEventListener('click', function (e) {
			if (e.target === els.overlay) {
				requestClose();
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') {
				return;
			}
			if (els.summaryBookOverlay && !els.summaryBookOverlay.hidden) {
				closeSummaryBookEditor();
			} else if (!els.helpOverlay.hidden) {
				els.helpOverlay.hidden = true;
			} else if (!els.overlay.hasAttribute('hidden')) {
				requestClose();
			}
		});

		els.helpBtn.addEventListener('click', function () {
			els.helpOverlay.hidden = false;
		});
		els.helpCloseBtn.addEventListener('click', function () {
			els.helpOverlay.hidden = true;
		});
		els.helpOverlay.addEventListener('click', function (e) {
			if (e.target === els.helpOverlay) {
				els.helpOverlay.hidden = true;
			}
		});
		if (els.summaryBookBtn) {
			els.summaryBookBtn.addEventListener('click', openSummaryBookEditor);
			els.summaryBookCloseBtn.addEventListener('click', closeSummaryBookEditor);
			els.summaryBookOverlay.addEventListener('click', function (e) {
				if (e.target === els.summaryBookOverlay) {
					closeSummaryBookEditor();
				}
			});
			els.summaryBookAddBtn.addEventListener('click', function () {
				addSummaryBookCard();
			});
			els.summaryBookCards.addEventListener('click', onSummaryBookCardsClick);
			els.summaryBookCards.addEventListener('change', onSummaryBookCardsChange);
			els.summaryBookSaveBtn.addEventListener('click', function () {
				saveSummaryBookDraft();
			});
			els.summaryBookPublishBtn.addEventListener('click', publishSummaryBook);
		}

		els.copyShortcodeBtn.addEventListener('click', function () {
			copyToClipboard(els.shortcodeExample.textContent, els.copyShortcodeBtn, 'Copy');
		});
		els.copyPromptBtn.addEventListener('click', function () {
			copyToClipboard(els.aiPromptExample.textContent, els.copyPromptBtn, 'Copy prompt');
		});

		els.tabVisual.addEventListener('click', function () {
			switchTab('visual');
		});
		els.tabHtml.addEventListener('click', function () {
			switchTab('html');
		});
		els.tabJson.addEventListener('click', function () {
			switchTab('json');
		});
		els.jsonFillBtn.addEventListener('click', fillFromJson);

		if (els.aiGenerateBtn) {
			els.aiModeIdea.addEventListener('click', function () {
				setAiMode('idea');
			});
			els.aiModeFormat.addEventListener('click', function () {
				setAiMode('format');
			});
			els.aiGenerateBtn.addEventListener('click', generateOnSite);
		}

		els.toolbar.addEventListener('click', function (e) {
			var btn = e.target.closest('button');
			if (!btn) {
				return;
			}
			els.visualEditor.focus();
			if (btn.dataset.cmd) {
				document.execCommand(btn.dataset.cmd, false, null);
			} else if (btn.dataset.block) {
				document.execCommand('formatBlock', false, '<' + btn.dataset.block + '>');
			}
			markDirty();
		});

		els.linkBtn.addEventListener('click', function () {
			els.visualEditor.focus();
			var url = window.prompt('Link URL (https://...)');
			if (url) {
				document.execCommand('createLink', false, url);
				markDirty();
			}
		});

		els.imageBtn.addEventListener('click', function () {
			saveSelection();
			openInlineImageFrame();
		});

		els.youtubeBtn.addEventListener('click', function () {
			saveSelection();
			var url = window.prompt('YouTube video URL');
			if (!url) {
				return;
			}
			var videoId = extractYoutubeId(url);
			if (!videoId) {
				showStatus('That does not look like a YouTube URL.', false);
				return;
			}
			els.visualEditor.focus();
			restoreSelection();
			var html = '<figure class="twd-ap-yt-embed" data-youtube-id="' + videoId + '" contenteditable="false">' +
				'<img src="https://img.youtube.com/vi/' + videoId + '/hqdefault.jpg" alt="YouTube video" /></figure>';
			document.execCommand('insertHTML', false, html);
			markDirty();
		});

		els.visualEditor.addEventListener('click', function (e) {
			var img = e.target.closest('img');
			if (img && els.visualEditor.contains(img) && !img.closest('.twd-ap-yt-embed')) {
				selectImage(img);
			} else {
				deselectImage();
			}
		});
		window.addEventListener('scroll', repositionImgUi, true);
		window.addEventListener('resize', repositionImgUi);

		els.featuredToggle.addEventListener('change', markDirty);
		els.includeBioToggle.addEventListener('change', markDirty);

		if (els.relatedPicker) {
			els.relatedPicker.addEventListener('change', function (e) {
				if ('checkbox' !== e.target.type) { return; }
				markDirty();
				updateRelatedLimit();
			});
		}

		els.categories.addEventListener('click', onCategoriesClick);
		els.categories.addEventListener('change', onCategoriesChange);

		els.catAddBtn.addEventListener('click', addCategory);
		els.catAddInput.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				addCategory();
			}
		});

		els.featuredSelect.addEventListener('click', openFeaturedImageFrame);
		els.featuredRemove.addEventListener('click', function () {
			state.featuredMediaId = 0;
			renderFeaturedPreview('');
			markDirty();
		});

		els.scheduleToggle.addEventListener('change', function () {
			els.scheduleDate.hidden = !this.checked;
			els.publishBtn.textContent = this.checked ? 'Schedule' : (state.editingId ? 'Update' : 'Publish');
			markDirty();
		});

		els.draftBtn.addEventListener('click', function () {
			submitPost('draft');
		});
		els.publishBtn.addEventListener('click', function () {
			submitPost(els.scheduleToggle.checked ? 'future' : 'publish');
		});

		[els.title, els.visualEditor, els.htmlEditor, els.excerpt, els.tags, els.yoastTitle, els.yoastDesc].forEach(function (el) {
			el.addEventListener('input', markDirty);
		});

		fetchCategories();
		fetchTags();
	}

	function copyToClipboard(text, btn, originalLabel) {
		if (!navigator.clipboard) {
			return;
		}
		navigator.clipboard.writeText(text).then(function () {
			btn.textContent = 'Copied!';
			setTimeout(function () {
				btn.textContent = originalLabel;
			}, 1500);
		});
	}

	function markDirty() {
		state.dirty = true;
	}

	function switchTab(tab) {
		if (tab === state.activeTab) {
			return;
		}
		deselectImage();
		if (state.activeTab === 'visual' && tab === 'html') {
			els.htmlEditor.value = els.visualEditor.innerHTML.trim();
		} else if (state.activeTab === 'html' && tab === 'visual') {
			els.visualEditor.innerHTML = els.htmlEditor.value;
		}
		state.activeTab = tab;
		els.tabVisual.classList.toggle('twd-ap-tab-active', tab === 'visual');
		els.tabHtml.classList.toggle('twd-ap-tab-active', tab === 'html');
		els.tabJson.classList.toggle('twd-ap-tab-active', tab === 'json');
		els.visualEditor.hidden = tab !== 'visual';
		els.htmlEditor.hidden = tab !== 'html';
		els.jsonPanel.hidden = tab !== 'json';
		els.toolbar.style.display = tab === 'visual' ? 'flex' : 'none';
		setFieldsVisible(tab !== 'json');
	}

	// On the Paste JSON tab, nothing has been filled in yet, so the title
	// and every other field (plus Save Draft/Publish) stay out of the way
	// until "Fill fields from JSON" actually populates them and switches
	// back to the Visual tab.
	function setFieldsVisible(visible) {
		els.titleWrap.hidden = !visible;
		els.fieldsWrap.hidden = !visible;
		els.modalFooter.hidden = !visible;
	}

	function saveSelection() {
		var sel = window.getSelection();
		if (sel && sel.rangeCount > 0) {
			savedRange = sel.getRangeAt(0);
		}
	}

	function restoreSelection() {
		if (savedRange) {
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(savedRange);
		}
	}

	function openFeaturedImageFrame() {
		if (!window.wp || !wp.media) {
			return;
		}
		if (!featuredFrame) {
			featuredFrame = wp.media({ title: 'Choose Featured Image', multiple: false, library: { type: 'image' } });
			featuredFrame.on('select', function () {
				var att = featuredFrame.state().get('selection').first().toJSON();
				state.featuredMediaId = att.id;
				var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
				renderFeaturedPreview(url);
				markDirty();
			});
		}
		featuredFrame.open();
	}

	function openInlineImageFrame() {
		if (!window.wp || !wp.media) {
			return;
		}
		if (!inlineFrame) {
			inlineFrame = wp.media({ title: 'Insert Image', multiple: false, library: { type: 'image' } });
			inlineFrame.on('select', function () {
				var att = inlineFrame.state().get('selection').first().toJSON();
				var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
				els.visualEditor.focus();
				restoreSelection();
				document.execCommand('insertHTML', false, '<img src="' + url + '" alt="" />');
				markDirty();
			});
		}
		inlineFrame.open();
	}

	function renderFeaturedPreview(url) {
		if (url) {
			els.featuredPreview.style.backgroundImage = 'url(' + url + ')';
			els.featuredRemove.hidden = false;
		} else {
			els.featuredPreview.style.backgroundImage = '';
			els.featuredRemove.hidden = true;
		}
	}

	function fetchCategories() {
		fetch(TWD_AP.restUrl + '/categories', {
			headers: { 'X-WP-Nonce': TWD_AP.nonce },
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				state.categories = data;
				renderCategories([]);
			})
			.catch(function () {
				els.categories.innerHTML = '<span class="twd-ap-muted">Could not load categories.</span>';
			});
	}

	function renderCategories(checkedIds, primaryId) {
		if (!state.categories.length) {
			els.categories.innerHTML = '<span class="twd-ap-muted">No categories yet.</span>';
			return;
		}
		if (primaryId === undefined) {
			primaryId = state.primaryCategoryId;
		}
		// The site's Uncategorized/default category never wins as the
		// automatic primary while a real category is also ticked -- the
		// grouped resources view drops that whole bucket outright, so a
		// post left primary'd there would silently vanish from the grid
		// even though it's ticked under a category that would show it.
		var uncategorizedId = (typeof TWD_AP !== 'undefined' && TWD_AP.uncategorizedId) || 0;
		var realCheckedIds = checkedIds.filter(function (id) { return id !== uncategorizedId; });
		if ((!primaryId || (primaryId === uncategorizedId && realCheckedIds.length)) && checkedIds.length) {
			primaryId = realCheckedIds.length ? realCheckedIds[0] : checkedIds[0];
		}
		state.primaryCategoryId = primaryId || 0;

		els.categories.innerHTML = state.categories
			.map(function (cat) {
				var checked = checkedIds.indexOf(cat.id) !== -1 ? 'checked' : '';
				var isPrimary = cat.id === state.primaryCategoryId;
				return '<span class="twd-ap-cat-item">' +
					'<label><input type="checkbox" value="' + cat.id + '" ' + checked + ' /> ' + escapeHtml(cat.name) + '</label>' +
					'<button type="button" class="twd-ap-cat-star' + (isPrimary ? ' is-primary' : '') + '" data-id="' + cat.id + '" title="' + (isPrimary ? 'Primary category' : 'Set as primary category') + '">&#9733;</button>' +
				'</span>';
			})
			.join('');
	}

	function onCategoriesClick(e) {
		var star = e.target.closest('.twd-ap-cat-star');
		if (!star) { return; }
		var id = parseInt(star.dataset.id, 10);
		var box = els.categories.querySelector('input[type="checkbox"][value="' + id + '"]');
		if (box) { box.checked = true; }
		renderCategories(getCheckedCategoryIds(), id);
		markDirty();
	}

	function onCategoriesChange(e) {
		if (!e.target.matches('input[type="checkbox"]')) { return; }
		// If the category that was just unticked was the primary one, fall
		// back to whichever is still checked (or none).
		var checkedIds = getCheckedCategoryIds();
		if (checkedIds.indexOf(state.primaryCategoryId) === -1) {
			renderCategories(checkedIds, checkedIds[0] || 0);
		}
	}

	// -- "More articles" closing-slide picker: up to RELATED_MAX manually
	// chosen articles, read/written as related_ids alongside the rest of the
	// popup's fields. Leaving all unticked isn't stored at all (see
	// after_save() in class-twd-ap-rest.php), so the swipe book falls back
	// to the 6 most recent other articles on its own -- this picker only
	// exists for overriding that default.
	function loadRelatedPicker(excludeId, checkedIds) {
		if (!els.relatedPicker) { return; }
		var excludeIdNum = parseInt(excludeId, 10) || 0;
		els.relatedPicker.innerHTML = '<li class="twd-ap-related-empty">Loading…</li>';
		fetch(TWD_AP.restUrl + '/articles?per_page=20', { cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var items = (data.items || [])
					.filter(function (item) { return item.id !== excludeIdNum; })
					.map(function (item) { return { id: item.id, title: item.title }; });
				renderRelatedPicker(items, checkedIds || []);
			})
			.catch(function () {
				els.relatedPicker.innerHTML = '<li class="twd-ap-related-empty">Could not load articles.</li>';
			});
	}

	function renderRelatedPicker(items, checkedIds) {
		if (!items.length) {
			els.relatedPicker.innerHTML = '<li class="twd-ap-related-empty">No other published articles yet.</li>';
			return;
		}
		els.relatedPicker.innerHTML = items
			.map(function (item) {
				var checked = checkedIds.indexOf(item.id) !== -1 ? 'checked' : '';
				return '<li class="twd-ap-related-row"><label><input type="checkbox" value="' + item.id + '" ' + checked + ' /> ' + escapeHtml(item.title) + '</label></li>';
			})
			.join('');
		updateRelatedLimit();
	}

	// Reverts (not just disables) any checkbox ticked past RELATED_MAX, then
	// disables the rest so it's clear the limit's been reached -- disabling
	// alone wouldn't stop a 7th box from getting checked in the same click
	// that trips the limit.
	function updateRelatedLimit() {
		if (!els.relatedPicker) { return; }
		var boxes = Array.prototype.slice.call(els.relatedPicker.querySelectorAll('input[type="checkbox"]'));
		var checked = boxes.filter(function (b) { return b.checked; });
		if (checked.length > RELATED_MAX) {
			checked[checked.length - 1].checked = false;
			checked = checked.slice(0, RELATED_MAX);
		}
		var atLimit = checked.length >= RELATED_MAX;
		boxes.forEach(function (b) {
			b.disabled = atLimit && !b.checked;
		});
	}

	function getCheckedRelatedIds() {
		if (!els.relatedPicker) { return []; }
		var boxes = els.relatedPicker.querySelectorAll('input[type="checkbox"]:checked');
		return Array.prototype.map.call(boxes, function (b) { return parseInt(b.value, 10); });
	}

	// -- Summary Book: the curated, reviewed-before-publish deck. Separate
	// overlay from the main popup, opened only for an already-published
	// article that has a draft and/or a published Summary Book -- nothing
	// to review or generate otherwise. Cards are rendered as plain editable
	// fields (type, heading, text, attribution), not a live swipe-book
	// preview -- simpler and more robust to build and use than making
	// arbitrary nested card HTML directly contenteditable, at the cost of
	// not being pixel-identical to the published result.
	function updateSummaryBookButton() {
		if (!els.summaryBookBtn) { return; }
		var canShow = 'publish' === state.articleStatus && (state.summaryBookHasDraft || state.summaryBookIsPublished);
		els.summaryBookBtn.hidden = !canShow;
		if (!canShow) { return; }
		els.summaryBookBtn.textContent = state.summaryBookIsPublished ? 'Summary Book (live)' : 'Summary Book (draft)';
	}

	function summaryBookCardTemplate(type) {
		return { type: type || 'text', heading: '', text: '', attribution: '' };
	}

	function renderSummaryBookCards(cards) {
		els.summaryBookCards.innerHTML = '';
		cards.forEach(function (card) {
			els.summaryBookCards.appendChild(buildSummaryBookCardRow(card));
		});
	}

	function buildSummaryBookCardRow(card) {
		var li = document.createElement('li');
		li.className = 'twd-ap-sb-card-row';
		li.dataset.type = card.type || 'text';

		var typeSelect = '<select class="twd-ap-sb-card-type">' +
			SUMMARY_BOOK_TYPES.map(function (t) {
				var label = t.charAt(0).toUpperCase() + t.slice(1);
				var selected = t === card.type ? ' selected' : '';
				return '<option value="' + t + '"' + selected + '>' + label + '</option>';
			}).join('') +
			'</select>';

		li.innerHTML =
			'<div class="twd-ap-sb-card-row-top">' + typeSelect +
				'<button type="button" class="twd-ap-sb-card-remove" aria-label="Remove card">&times;</button>' +
			'</div>' +
			'<input type="text" class="twd-ap-input twd-ap-sb-card-heading" placeholder="Heading (optional)" value="' + escapeHtml(card.heading || '') + '" />' +
			'<textarea class="twd-ap-textarea-small twd-ap-sb-card-text" placeholder="Card text" rows="3">' + escapeHtml(card.text || '') + '</textarea>' +
			'<input type="text" class="twd-ap-input twd-ap-sb-card-attribution" placeholder="Attribution (optional)" value="' + escapeHtml(card.attribution || '') + '" />';

		applySummaryBookCardTypeVisibility(li);
		return li;
	}

	// Heading only really makes sense on a plain text card; attribution
	// only on a quote. Keeping the fields in the DOM regardless (just
	// hidden) is deliberate -- switching a card's type and back doesn't
	// lose whatever was typed into a field that's momentarily out of view.
	function applySummaryBookCardTypeVisibility(li) {
		var type = li.dataset.type;
		var heading = li.querySelector('.twd-ap-sb-card-heading');
		var attribution = li.querySelector('.twd-ap-sb-card-attribution');
		if (heading) { heading.hidden = 'text' !== type; }
		if (attribution) { attribution.hidden = 'quote' !== type; }
	}

	function addSummaryBookCard(type) {
		els.summaryBookEmpty.hidden = true;
		els.summaryBookCards.appendChild(buildSummaryBookCardRow(summaryBookCardTemplate(type)));
	}

	function onSummaryBookCardsClick(e) {
		var removeBtn = e.target.closest('.twd-ap-sb-card-remove');
		if (!removeBtn) { return; }
		var row = removeBtn.closest('.twd-ap-sb-card-row');
		if (row) { row.parentNode.removeChild(row); }
	}

	function onSummaryBookCardsChange(e) {
		if (!e.target.matches('.twd-ap-sb-card-type')) { return; }
		var row = e.target.closest('.twd-ap-sb-card-row');
		if (!row) { return; }
		row.dataset.type = e.target.value;
		applySummaryBookCardTypeVisibility(row);
	}

	function collectSummaryBookCards() {
		var rows = Array.prototype.slice.call(els.summaryBookCards.querySelectorAll('.twd-ap-sb-card-row'));
		return rows.map(function (row) {
			var card = {
				type: row.dataset.type || 'text',
				text: row.querySelector('.twd-ap-sb-card-text').value.trim(),
			};
			var heading = row.querySelector('.twd-ap-sb-card-heading').value.trim();
			if (heading) { card.heading = heading; }
			var attribution = row.querySelector('.twd-ap-sb-card-attribution').value.trim();
			if (attribution) { card.attribution = attribution; }
			return card;
		}).filter(function (card) { return '' !== card.text; });
	}

	function setSummaryBookStatus(msg, ok) {
		els.summaryBookStatus.textContent = msg;
		els.summaryBookStatus.hidden = !msg;
		els.summaryBookStatus.classList.toggle('twd-ap-status-ok', !!ok);
	}

	function openSummaryBookEditor() {
		if (!state.editingId) { return; }
		els.summaryBookOverlay.hidden = false;
		setSummaryBookStatus('Loading…', false);
		els.summaryBookCards.innerHTML = '';
		els.summaryBookEmpty.hidden = true;
		els.summaryBookPublishedNote.hidden = !state.summaryBookIsPublished;

		fetch(TWD_AP.restUrl + '/posts/' + state.editingId + '/summary-book', {
			headers: { 'X-WP-Nonce': TWD_AP.nonce },
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				els.summaryBookStatus.hidden = true;
				var cards = (data.draft && data.draft.length) ? data.draft : (data.published || []);
				if (!cards.length) {
					els.summaryBookEmpty.hidden = false;
					return;
				}
				renderSummaryBookCards(cards);
			})
			.catch(function () {
				setSummaryBookStatus('Could not load the Summary Book.', false);
			});
	}

	function closeSummaryBookEditor() {
		els.summaryBookOverlay.hidden = true;
	}

	function saveSummaryBookDraft(onSaved) {
		if (!state.editingId) { return; }
		var cards = collectSummaryBookCards();
		if (!cards.length) {
			setSummaryBookStatus('Add at least one card first.', false);
			return;
		}
		setSummaryBookStatus('Saving…', false);
		fetch(TWD_AP.restUrl + '/posts/' + state.editingId + '/summary-book', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': TWD_AP.nonce },
			body: JSON.stringify({ cards: cards }),
		})
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
			.then(function (result) {
				if (!result.ok) {
					setSummaryBookStatus('Could not save. Please try again.', false);
					return;
				}
				state.summaryBookHasDraft = !!(result.data.draft && result.data.draft.length);
				updateSummaryBookButton();
				if ('function' === typeof onSaved) {
					onSaved(result.data.draft || cards);
				} else {
					setSummaryBookStatus('Draft saved.', true);
				}
			})
			.catch(function () {
				setSummaryBookStatus('Could not save. Please try again.', false);
			});
	}

	function publishSummaryBook() {
		var cards = collectSummaryBookCards();
		if (!cards.length) {
			setSummaryBookStatus('Add at least one card before publishing.', false);
			return;
		}
		setSummaryBookStatus('Publishing…', false);
		fetch(TWD_AP.restUrl + '/posts/' + state.editingId + '/summary-book/publish', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': TWD_AP.nonce },
			body: JSON.stringify({ cards: cards }),
		})
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
			.then(function (result) {
				if (!result.ok) {
					setSummaryBookStatus((result.data && result.data.message) ? result.data.message : 'Could not publish. Please try again.', false);
					return;
				}
				state.summaryBookHasDraft = true;
				state.summaryBookIsPublished = true;
				updateSummaryBookButton();
				els.summaryBookPublishedNote.hidden = false;
				setSummaryBookStatus('Summary Book published.', true);
			})
			.catch(function () {
				setSummaryBookStatus('Could not publish. Please try again.', false);
			});
	}

	function ensureImgToolbar() {
		if (imgToolbar) {
			return imgToolbar;
		}
		imgToolbar = document.createElement('div');
		imgToolbar.className = 'twd-ap-img-toolbar';
		imgToolbar.hidden = true;
		imgToolbar.innerHTML =
			'<button type="button" data-align="left" title="Align left">&#8676;</button>' +
			'<button type="button" data-align="center" title="Align center">&#8644;</button>' +
			'<button type="button" data-align="right" title="Align right">&#8677;</button>' +
			'<button type="button" data-align="full" title="Full width">&#9635;</button>' +
			'<button type="button" data-align="none" title="Remove alignment">&#10005;</button>';
		document.body.appendChild(imgToolbar);
		imgToolbar.addEventListener('mousedown', function (e) {
			e.preventDefault();
		});
		imgToolbar.addEventListener('click', function (e) {
			var btn = e.target.closest('button');
			if (!btn || !selectedImg) {
				return;
			}
			setImageAlign(selectedImg, btn.dataset.align);
		});
		return imgToolbar;
	}

	function setImageAlign(img, align) {
		img.classList.remove('twd-ap-img-left', 'twd-ap-img-center', 'twd-ap-img-right', 'twd-ap-img-full');
		if (align !== 'none') {
			img.classList.add('twd-ap-img-' + align);
		}
		markDirty();
		repositionImgUi();
	}

	function selectImage(img) {
		if (selectedImg && selectedImg !== img) {
			selectedImg.classList.remove('twd-ap-img-selected');
		}
		selectedImg = img;
		img.classList.add('twd-ap-img-selected');
		var tb = ensureImgToolbar();
		tb.hidden = false;
		attachResizeHandle(img);
		repositionImgUi();
	}

	function deselectImage() {
		if (selectedImg) {
			selectedImg.classList.remove('twd-ap-img-selected');
		}
		selectedImg = null;
		if (imgToolbar) {
			imgToolbar.hidden = true;
		}
		removeResizeHandle();
	}

	function repositionImgUi() {
		if (!selectedImg) {
			return;
		}
		var rect = selectedImg.getBoundingClientRect();
		if (imgToolbar) {
			imgToolbar.style.top = Math.max(8, rect.top - 42) + 'px';
			imgToolbar.style.left = rect.left + 'px';
		}
		if (resizeHandle) {
			resizeHandle.style.top = rect.bottom - 8 + 'px';
			resizeHandle.style.left = rect.right - 8 + 'px';
		}
	}

	function attachResizeHandle(img) {
		removeResizeHandle();
		resizeHandle = document.createElement('div');
		resizeHandle.className = 'twd-ap-img-resize-handle';
		document.body.appendChild(resizeHandle);
		repositionImgUi();

		resizeHandle.addEventListener('mousedown', function (e) {
			e.preventDefault();
			var startX = e.clientX;
			var startWidth = img.getBoundingClientRect().width;
			var ratio = img.naturalWidth ? img.naturalHeight / img.naturalWidth : (img.getBoundingClientRect().height / startWidth);

			function onMove(ev) {
				var maxWidth = els.visualEditor.clientWidth - 28;
				var newWidth = Math.round(Math.max(60, Math.min(startWidth + (ev.clientX - startX), maxWidth)));
				img.setAttribute('width', newWidth);
				img.setAttribute('height', Math.round(newWidth * ratio));
				repositionImgUi();
			}
			function onUp() {
				document.removeEventListener('mousemove', onMove);
				document.removeEventListener('mouseup', onUp);
				markDirty();
			}
			document.addEventListener('mousemove', onMove);
			document.addEventListener('mouseup', onUp);
		});
	}

	function removeResizeHandle() {
		if (resizeHandle) {
			resizeHandle.remove();
			resizeHandle = null;
		}
	}

	function fillFromJson() {
		var raw = els.jsonInput.value.trim();
		if (!raw) {
			showStatus('Paste some JSON first.', false);
			return;
		}
		var data;
		try {
			data = JSON.parse(raw);
		} catch (e) {
			showStatus('That is not valid JSON.', false);
			return;
		}
		applyGeneratedData(data, 'Fields filled from JSON. Review them below before saving.');
	}

	/**
	 * The one place article fields actually get filled from a generated/
	 * pasted result, shared by fillFromJson() (paste path) and
	 * generateOnSite() (on-site AI path) -- same shape either way
	 * (title/seo_title/meta_description/category/tags/html/summary_book),
	 * so this never needs to know which one produced it.
	 */
	function applyGeneratedData(data, successMessage) {
		if (data.title) {
			els.title.value = data.title;
		}

		var meta = data.meta_description || '';
		if (meta) {
			els.excerpt.value = meta;
			if (TWD_AP.yoastEnabled && els.yoastDesc) {
				els.yoastDesc.value = meta;
			}
		}
		if (TWD_AP.yoastEnabled && els.yoastTitle && data.seo_title) {
			els.yoastTitle.value = data.seo_title;
		}

		var tags = Array.isArray(data.tags) ? data.tags.join(', ') : (data.tags || '');
		if (tags) {
			els.tags.value = tags;
		}

		var html = data.html || '';
		if (html) {
			els.visualEditor.innerHTML = html;
			els.htmlEditor.value = html;
		}

		// Summary Book draft, if Article Assist (or the on-site generator)
		// baked one in -- held here, not written anywhere yet. It rides
		// along in the next save's payload (see submitPost()) so it's
		// stored atomically with the rest of the article, but nothing
		// about it is shown or published until "Summary Book" is opened
		// after saving.
		if (data.summary_book && Array.isArray(data.summary_book.slides)) {
			state.summaryBookDraft = data.summary_book.slides;
		}

		markDirty();
		showStatus(successMessage, true);
		switchTabImmediate('visual');

		if (data.category) {
			addOrCheckCategoryByName(data.category);
		}
	}

	function setAiMode(mode) {
		state.aiMode = mode;
		els.aiModeIdea.classList.toggle('twd-ap-mode-btn-active', 'idea' === mode);
		els.aiModeFormat.classList.toggle('twd-ap-mode-btn-active', 'format' === mode);
		els.aiInput.placeholder = 'format' === mode
			? 'Paste the article you have already written...'
			: 'e.g. Why rest can feel unproductive';
	}

	function generateOnSite() {
		if (state.aiGenerating) {
			return;
		}
		var text = els.aiInput.value.trim();
		if (!text) {
			showStatus('format' === state.aiMode ? 'Please paste your article text first.' : 'Please add an idea or topic first.', false);
			return;
		}

		state.aiGenerating = true;
		els.aiGenerateBtn.disabled = true;
		var original = els.aiGenerateBtn.textContent;
		els.aiGenerateBtn.textContent = 'Generating… this can take up to a minute';
		hideStatus();

		fetch(TWD_AP.restUrl + '/generate', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': TWD_AP.nonce,
			},
			body: JSON.stringify({ mode: state.aiMode, input: text }),
		})
			.then(function (r) {
				return r.json().then(function (d) {
					return { ok: r.ok, data: d };
				});
			})
			.then(function (result) {
				state.aiGenerating = false;
				els.aiGenerateBtn.disabled = false;
				els.aiGenerateBtn.textContent = original;
				if (!result.ok) {
					showStatus((result.data && result.data.message) ? result.data.message : 'Something went wrong. Please try again.', false);
					return;
				}
				applyGeneratedData(result.data, 'Article generated. Review it below before saving.');
				els.aiInput.value = '';
			})
			.catch(function () {
				state.aiGenerating = false;
				els.aiGenerateBtn.disabled = false;
				els.aiGenerateBtn.textContent = original;
				showStatus('Something went wrong. Please try again.', false);
			});
	}

	function addOrCheckCategoryByName(name) {
		var checkedIds = getCheckedCategoryIds();
		fetch(TWD_AP.restUrl + '/categories', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': TWD_AP.nonce,
			},
			body: JSON.stringify({ name: name }),
		})
			.then(function (r) {
				return r.json().then(function (d) {
					return { ok: r.ok, data: d };
				});
			})
			.then(function (result) {
				if (!result.ok) {
					return;
				}
				var exists = state.categories.some(function (c) {
					return c.id === result.data.id;
				});
				if (!exists) {
					state.categories.push({ id: result.data.id, name: result.data.name });
				}
				checkedIds.push(result.data.id);
				renderCategories(checkedIds, result.data.id);
			})
			.catch(function () {});
	}

	function addCategory() {
		var name = els.catAddInput.value.trim();
		if (!name) {
			return;
		}
		var checkedIds = getCheckedCategoryIds();
		els.catAddBtn.disabled = true;
		fetch(TWD_AP.restUrl + '/categories', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': TWD_AP.nonce,
			},
			body: JSON.stringify({ name: name }),
		})
			.then(function (r) {
				return r.json().then(function (data) {
					return { ok: r.ok, data: data };
				});
			})
			.then(function (result) {
				els.catAddBtn.disabled = false;
				if (!result.ok) {
					showStatus(result.data && result.data.message ? result.data.message : 'Could not add that category.', false);
					return;
				}
				var exists = state.categories.some(function (c) {
					return c.id === result.data.id;
				});
				if (!exists) {
					state.categories.push({ id: result.data.id, name: result.data.name });
				}
				checkedIds.push(result.data.id);
				renderCategories(checkedIds);
				els.catAddInput.value = '';
			})
			.catch(function () {
				els.catAddBtn.disabled = false;
				showStatus('Could not add that category.', false);
			});
	}

	function extractYoutubeId(url) {
		var match = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
		return match ? match[1] : null;
	}

	function fetchTags() {
		fetch(TWD_AP.restUrl + '/tags', {
			headers: { 'X-WP-Nonce': TWD_AP.nonce },
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (!Array.isArray(data)) {
					return;
				}
				els.tagDatalist.innerHTML = data
					.map(function (name) {
						return '<option value="' + escapeHtml(name) + '"></option>';
					})
					.join('');
			})
			.catch(function () {});
	}

	function getCheckedCategoryIds() {
		var boxes = els.categories.querySelectorAll('input[type="checkbox"]:checked');
		return Array.prototype.map.call(boxes, function (b) {
			return parseInt(b.value, 10);
		});
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function resetForm() {
		deselectImage();
		els.title.value = '';
		els.visualEditor.innerHTML = '';
		els.htmlEditor.value = '';
		els.jsonInput.value = '';
		els.excerpt.value = '';
		els.tags.value = '';
		els.catAddInput.value = '';
		els.featuredToggle.checked = false;
		els.includeBioToggle.checked = false;
		state.relatedIds = [];
		if (els.relatedPicker) { els.relatedPicker.innerHTML = ''; }
		state.summaryBookDraft = null;
		state.summaryBookHasDraft = false;
		state.summaryBookIsPublished = false;
		state.articleStatus = '';
		updateSummaryBookButton();
		els.yoastTitle.value = '';
		els.yoastDesc.value = '';
		els.scheduleToggle.checked = false;
		els.scheduleDate.hidden = true;
		els.scheduleDate.value = '';
		state.featuredMediaId = 0;
		renderFeaturedPreview('');
		switchTabImmediate('visual');
		state.dirty = false;
		hideStatus();
		els.yoastWrap.hidden = !TWD_AP.yoastEnabled;
		state.primaryCategoryId = 0;
		renderCategories([]);
		updateSwipebookLink('', '');
	}

	function switchTabImmediate(tab) {
		state.activeTab = tab;
		els.tabVisual.classList.toggle('twd-ap-tab-active', tab === 'visual');
		els.tabHtml.classList.toggle('twd-ap-tab-active', tab === 'html');
		els.tabJson.classList.toggle('twd-ap-tab-active', tab === 'json');
		els.visualEditor.hidden = tab !== 'visual';
		els.htmlEditor.hidden = tab !== 'html';
		els.jsonPanel.hidden = tab !== 'json';
		els.toolbar.style.display = tab === 'visual' ? 'flex' : 'none';
		setFieldsVisible(tab !== 'json');
	}

	function updateSwipebookLink(status, link) {
		if (!els.swipebookLink) {
			return;
		}
		if ('publish' !== status || !link) {
			els.swipebookLink.hidden = true;
			return;
		}
		var sep = link.indexOf('?') === -1 ? '?' : '&';
		els.swipebookLink.href = link + sep + 'twd_ap_swipebook=1';
		els.swipebookLink.hidden = false;
	}

	function openModal(mode, postId) {
		resetForm();
		if (mode === 'edit' && postId) {
			state.editingId = postId;
			els.modalSubtitle.textContent = 'Editing article';
			els.publishBtn.textContent = 'Update';
			els.draftBtn.textContent = 'Save as Draft';
			loadPostForEdit(postId);
			if (TWD_AP.adminEditUrl) {
				els.adminEditLink.href = TWD_AP.adminEditUrl;
				els.adminEditLink.hidden = false;
			}
			els.deleteBtn.hidden = false;
		} else {
			state.editingId = null;
			els.modalSubtitle.textContent = 'New article';
			els.publishBtn.textContent = 'Publish';
			els.draftBtn.textContent = 'Save Draft';
			els.adminEditLink.hidden = true;
			els.deleteBtn.hidden = true;
			updateSwipebookLink('', '');
			loadRelatedPicker(0, []);
		}
		els.overlay.hidden = false;
		document.documentElement.style.overflow = 'hidden';
		document.body.style.overflow = 'hidden';
		setTimeout(function () {
			els.title.focus();
		}, 50);
	}

	function loadPostForEdit(id) {
		showStatus('Loading article…', false);
		fetch(TWD_AP.restUrl + '/posts/' + id, {
			headers: { 'X-WP-Nonce': TWD_AP.nonce },
		})
			.then(function (r) {
				if (!r.ok) {
					throw new Error('load-failed');
				}
				return r.json();
			})
			.then(function (data) {
				els.title.value = data.title || '';
				els.visualEditor.innerHTML = data.content_html || '';
				els.excerpt.value = data.excerpt || '';
				els.tags.value = data.tags || '';
				els.featuredToggle.checked = !!data.featured;
				els.includeBioToggle.checked = !!data.include_bio;
				state.featuredMediaId = data.featured_media || 0;
				renderFeaturedPreview(data.featured_media_url || '');
				renderCategories(data.category_ids || [], data.primary_category || 0);
				loadRelatedPicker(id, data.related_ids || []);
				if (TWD_AP.yoastEnabled) {
					els.yoastTitle.value = data.yoast_title || '';
					els.yoastDesc.value = data.yoast_desc || '';
				}
				updateSwipebookLink(data.status, data.link);
				state.summaryBookHasDraft = !!data.summary_book_has_draft;
				state.summaryBookIsPublished = !!data.summary_book_is_published;
				state.articleStatus = data.status || '';
				updateSummaryBookButton();
				state.dirty = false;
				hideStatus();
			})
			.catch(function () {
				showStatus('Could not load this article.', false);
			});
	}

	function requestClose() {
		if (state.dirty) {
			if (!window.confirm(TWD_AP.i18n.confirmClose)) {
				return;
			}
		}
		closeModal();
	}

	function requestDelete() {
		if (!state.editingId) {
			return;
		}
		if (!window.confirm(TWD_AP.i18n.confirmDelete)) {
			return;
		}
		els.deleteBtn.disabled = true;
		showStatus('Deleting…', false);
		fetch(TWD_AP.restUrl + '/posts/' + state.editingId, {
			method: 'DELETE',
			headers: { 'X-WP-Nonce': TWD_AP.nonce },
		})
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
			.then(function (result) {
				els.deleteBtn.disabled = false;
				if (!result.ok) {
					showStatus((result.data && result.data.message) ? result.data.message : 'Could not delete this article. Please try again.', false);
					return;
				}
				state.dirty = false;
				closeModal();
				if (TWD_AP.currentPostId && parseInt(TWD_AP.currentPostId, 10) === state.editingId) {
					window.location.reload();
				}
			})
			.catch(function () {
				els.deleteBtn.disabled = false;
				showStatus('Could not delete this article. Please try again.', false);
			});
	}

	function closeModal() {
		deselectImage();
		els.overlay.hidden = true;
		els.helpOverlay.hidden = true;
		document.documentElement.style.overflow = '';
		document.body.style.overflow = '';
	}

	function showStatus(msg, ok) {
		els.status.textContent = msg;
		els.status.hidden = false;
		els.status.classList.toggle('twd-ap-status-ok', !!ok);
	}
	function hideStatus() {
		els.status.hidden = true;
	}

	function setBusy(busy) {
		els.draftBtn.disabled = busy;
		els.publishBtn.disabled = busy;
	}

	function submitPost(status) {
		var title = els.title.value.trim();
		if (!title) {
			showStatus('Please add a title before saving.', false);
			els.title.focus();
			return;
		}

		// Whether this was already an existing article before this save, not
		// whether it still is after (state.editingId gets set either way once
		// the response comes back) -- that's what decides whether the button
		// read "Update"/"Schedule" rather than "Publish" just now.
		var wasEditing = !!state.editingId;

		deselectImage();
		if (state.activeTab === 'html') {
			els.visualEditor.innerHTML = els.htmlEditor.value;
		}
		var contentHtml = els.visualEditor.innerHTML.trim();

		var payload = {
			title: title,
			content_html: contentHtml,
			excerpt: els.excerpt.value,
			tags: els.tags.value,
			featured: els.featuredToggle.checked,
			include_bio: els.includeBioToggle.checked,
			related_ids: getCheckedRelatedIds(),
			category_ids: getCheckedCategoryIds(),
			primary_category: state.primaryCategoryId,
			featured_media: state.featuredMediaId,
			status: status,
		};

		// Only included when this save actually carries a freshly-pasted
		// draft -- see after_save() in class-twd-ap-rest.php, an absent key
		// leaves any existing stored draft untouched rather than wiping it.
		if (state.summaryBookDraft) {
			payload.summary_book_draft = state.summaryBookDraft;
		}

		if (status === 'future') {
			if (!els.scheduleDate.value) {
				showStatus('Please choose a date and time to schedule this article.', false);
				return;
			}
			payload.date = els.scheduleDate.value;
		}

		if (TWD_AP.yoastEnabled) {
			payload.yoast_title = els.yoastTitle.value;
			payload.yoast_desc = els.yoastDesc.value;
		}

		var url = TWD_AP.restUrl + '/posts';
		if (state.editingId) {
			url += '/' + state.editingId;
		}

		setBusy(true);
		showStatus(TWD_AP.i18n.saving, false);

		fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': TWD_AP.nonce,
			},
			body: JSON.stringify(payload),
		})
			.then(function (r) {
				return r.json().then(function (data) {
					return { ok: r.ok, data: data };
				});
			})
			.then(function (result) {
				setBusy(false);
				if (!result.ok) {
					showStatus(result.data && result.data.message ? result.data.message : TWD_AP.i18n.error, false);
					return;
				}
				state.editingId = result.data.id;
				state.dirty = false;
				if (state.summaryBookDraft) {
					// Now persisted server-side -- clear it so it isn't resent
					// (and potentially overwrite a newer edit made through the
					// Summary Book review screen itself) on a later save in
					// this same popup session.
					state.summaryBookDraft = null;
					state.summaryBookHasDraft = true;
				}
				updateSummaryBookButton();
				var label = status === 'draft' ? 'Draft saved.' : status === 'future' ? 'Article scheduled.' : 'Article published.';

				// Updating (or rescheduling) an article that was already
				// published/scheduled before this click closes the popup
				// straight away, same as clicking Close after -- there's
				// nothing left to review that wasn't already reviewed the
				// first time it was published. A brand new Publish, or any
				// Save as Draft, keeps the popup open so the swipe book
				// link/further edits are still reachable.
				if (wasEditing && 'draft' !== status) {
					showStatus(label, true);
					setTimeout(closeModal, 600);
					return;
				}

				showStatus(label + ' You can keep editing or close this window.', true);
				els.modalSubtitle.textContent = 'Editing article';
				els.draftBtn.textContent = 'Save as Draft';
				els.publishBtn.textContent = els.scheduleToggle.checked ? 'Schedule' : 'Update';
				updateSwipebookLink(result.data.status, result.data.link);
			})
			.catch(function () {
				setBusy(false);
				showStatus(TWD_AP.i18n.error, false);
			});
	}
})();
