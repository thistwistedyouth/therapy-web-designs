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
	};

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
		els.featuredPreview = document.getElementById('twd-ap-featured-preview');
		els.featuredSelect = document.getElementById('twd-ap-featured-select');
		els.featuredRemove = document.getElementById('twd-ap-featured-remove');
		els.featuredToggle = document.getElementById('twd-ap-featured-toggle');
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
		els.adminEditLink = document.getElementById('twd-ap-admin-edit-link');
		els.swipebookLink = document.getElementById('twd-ap-swipebook-link');
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
		els.overlay.addEventListener('click', function (e) {
			if (e.target === els.overlay) {
				requestClose();
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') {
				return;
			}
			if (!els.helpOverlay.hidden) {
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

	function renderCategories(checkedIds) {
		if (!state.categories.length) {
			els.categories.innerHTML = '<span class="twd-ap-muted">No categories yet.</span>';
			return;
		}
		els.categories.innerHTML = state.categories
			.map(function (cat) {
				var checked = checkedIds.indexOf(cat.id) !== -1 ? 'checked' : '';
				return '<label class="twd-ap-cat-item"><input type="checkbox" value="' + cat.id + '" ' + checked + ' /> ' + escapeHtml(cat.name) + '</label>';
			})
			.join('');
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

		markDirty();
		showStatus('Fields filled from JSON. Review them below before saving.', true);
		switchTabImmediate('visual');

		if (data.category) {
			addOrCheckCategoryByName(data.category);
		}
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
				renderCategories(checkedIds);
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
		} else {
			state.editingId = null;
			els.modalSubtitle.textContent = 'New article';
			els.publishBtn.textContent = 'Publish';
			els.draftBtn.textContent = 'Save Draft';
			els.adminEditLink.hidden = true;
			updateSwipebookLink('', '');
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
				state.featuredMediaId = data.featured_media || 0;
				renderFeaturedPreview(data.featured_media_url || '');
				renderCategories(data.category_ids || []);
				if (TWD_AP.yoastEnabled) {
					els.yoastTitle.value = data.yoast_title || '';
					els.yoastDesc.value = data.yoast_desc || '';
				}
				updateSwipebookLink(data.status, data.link);
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
			category_ids: getCheckedCategoryIds(),
			featured_media: state.featuredMediaId,
			status: status,
		};

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
				var label = status === 'draft' ? 'Draft saved.' : status === 'future' ? 'Article scheduled.' : 'Article published.';
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
