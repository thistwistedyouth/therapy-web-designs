(function () {
	'use strict';

	var PLACEHOLDER_SVG = '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5">' +
		'<rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="M21 16l-5.5-5.5-4 4L8 11l-5 5" /></svg>';

	// Same box shape as a real .twd-ap-article-card (thumb height, body
	// padding, line count) so swapping skeleton cards for real ones never
	// changes the grid's height -- that swap, not the shimmer itself, is
	// what read as the page "resizing" while articles loaded in.
	function skeletonCardHtml() {
		return '<div class="twd-ap-article-card twd-ap-skeleton-card" aria-hidden="true">' +
			'<span class="twd-ap-article-thumb twd-ap-skeleton-block"></span>' +
			'<span class="twd-ap-article-body">' +
				'<span class="twd-ap-skeleton-line twd-ap-skeleton-line-date"></span>' +
				'<span class="twd-ap-skeleton-line twd-ap-skeleton-line-title"></span>' +
				'<span class="twd-ap-skeleton-line twd-ap-skeleton-line-title-short"></span>' +
				'<span class="twd-ap-skeleton-line twd-ap-skeleton-line-excerpt"></span>' +
				'<span class="twd-ap-skeleton-line twd-ap-skeleton-line-excerpt"></span>' +
			'</span>' +
		'</div>';
	}
	function skeletonCardsHtml(count) {
		var html = '';
		for (var i = 0; i < count; i++) { html += skeletonCardHtml(); }
		return html;
	}

	var PENCIL_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';

	document.addEventListener('DOMContentLoaded', function () {
		var containers = document.querySelectorAll('.twd-ap-articles');
		Array.prototype.forEach.call(containers, initGrid);
		Array.prototype.forEach.call(document.querySelectorAll('.twd-ap-index'), initIndex);
	});

	// [twd_articles_index]: the topic dropdown is printed hidden and only
	// switched on here, so without JavaScript the plain jump links stay and
	// every section shows. Choosing a topic shows just that section.
	// The "Edit intro" popup, built on first use and shared by every grid.
	function openIntroEditor(btn, config) {
		var overlay = document.querySelector('.twd-ap-intro-overlay');
		if (!overlay) {
			overlay = document.createElement('div');
			overlay.className = 'twd-ap-overlay twd-ap-intro-overlay';
			overlay.hidden = true;
			overlay.innerHTML =
				'<div class="twd-ap-modal twd-ap-grid-settings-modal" role="dialog" aria-modal="true" aria-labelledby="twd-ap-intro-title">' +
					'<div class="twd-ap-modal-header">' +
						'<p class="twd-ap-modal-title" id="twd-ap-intro-title"></p>' +
						'<button type="button" class="twd-ap-close-btn twd-ap-intro-close" aria-label="Close"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" /></svg></button>' +
					'</div>' +
					'<div class="twd-ap-modal-body">' +
						'<p class="twd-ap-status twd-ap-intro-status" hidden></p>' +
						'<p class="twd-ap-muted twd-ap-hint">A short introduction shown above the articles on this category page. Plain text is fine; a blank line starts a new paragraph.</p>' +
						'<textarea class="twd-ap-textarea-small twd-ap-intro-text" rows="8"></textarea>' +
					'</div>' +
					'<div class="twd-ap-modal-footer"><div class="twd-ap-footer-spacer"></div><button type="button" class="twd-ap-btn-primary twd-ap-intro-save">Save</button></div>' +
				'</div>';
			document.body.appendChild(overlay);
		}
		var title = overlay.querySelector('#twd-ap-intro-title');
		var textarea = overlay.querySelector('.twd-ap-intro-text');
		var status = overlay.querySelector('.twd-ap-intro-status');
		var saveBtn = overlay.querySelector('.twd-ap-intro-save');
		var closeBtn = overlay.querySelector('.twd-ap-intro-close');
		title.textContent = 'Intro for ' + (btn.getAttribute('data-name') || 'this category');
		textarea.value = btn.getAttribute('data-raw') || '';
		status.hidden = true;
		saveBtn.disabled = false;
		overlay.hidden = false;
		textarea.focus();

		function close() { overlay.hidden = true; document.removeEventListener('keydown', onKey); btn.focus(); }
		function onKey(e) { if (e.key === 'Escape') { close(); } }
		function setStatus(msg, ok) {
			status.textContent = msg;
			status.hidden = !msg;
			status.classList.toggle('twd-ap-status-ok', !!ok);
		}
		document.addEventListener('keydown', onKey);
		closeBtn.onclick = close;
		overlay.onclick = function (e) { if (e.target === overlay) { close(); } };
		saveBtn.onclick = function () {
			saveBtn.disabled = true;
			setStatus('Saving…', false);
			fetch(config.restUrl + '/term-intro', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify({ term_id: parseInt(btn.getAttribute('data-term-id'), 10), taxonomy: btn.getAttribute('data-taxonomy'), description: textarea.value }),
			})
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
				.then(function (result) {
					if (!result.ok) {
						saveBtn.disabled = false;
						setStatus('Could not save. Please try again.', false);
						return;
					}
					// The server returns the description already cleaned and formatted.
					var holder = btn.parentNode.querySelector('.twd-ap-articles-intro-text');
					if (holder) { holder.innerHTML = result.data.html || ''; }
					btn.setAttribute('data-raw', result.data.raw || '');
					btn.lastChild.textContent = result.data.html ? 'Edit intro' : 'Add an intro';
					setStatus('Saved.', true);
					setTimeout(close, 600);
				})
				.catch(function () {
					saveBtn.disabled = false;
					setStatus('Could not save. Please try again.', false);
				});
		};
	}

	function initIndex(root) {
		var filter = root.querySelector('.twd-ap-index-filter');
		var select = filter ? filter.querySelector('select') : null;
		if (!select) { return; }
		filter.hidden = false;
		root.classList.add('twd-ap-index-enhanced');
		select.addEventListener('change', function () {
			var id = select.value;
			Array.prototype.forEach.call(root.querySelectorAll('.twd-ap-index-section'), function (section) {
				section.hidden = !!id && section.id !== id;
			});
		});
	}

	function initGrid(root) {
		var config;
		try {
			config = JSON.parse(root.dataset.config);
		} catch (e) {
			return;
		}

		var groupsEl    = root.querySelector('.twd-ap-articles-groups');
		// The grid that belongs to the root itself. A plain querySelector would
		// find the first grouped section's grid instead, once the server has
		// printed the grouped view into the page, and hiding it would blank it.
		var gridEl      = Array.prototype.filter.call(root.children, function (c) {
			return c.classList.contains('twd-ap-articles-grid');
		})[0];
		var emptyEl     = root.querySelector('.twd-ap-articles-empty');
		var loadMoreBtn = root.querySelector('.twd-ap-articles-loadmore');
		var searchEl    = root.querySelector('.twd-ap-articles-search');
		var selectEl    = root.querySelector('.twd-ap-articles-category-select');
		var settingsBtn = root.querySelector('.twd-ap-articles-settings-btn');
		// A page cached before this version has no such element; the stub
		// keeps the code below from failing on it.
		var noneEl      = root.querySelector('.twd-ap-articles-none') || { hidden: true };

		// The server prints the first view into the page (class-twd-ap-
		// shortcode.php, data-ssr), so the first load must not wipe it for
		// skeleton cards: it fetches quietly and swaps in the same markup in
		// one step. Consumed by the first fetch only; later searches and
		// filters show skeletons as before.
		var keepFirstPaint = root.getAttribute('data-ssr') === '1';

		// Real page links under a long list, for crawlers and no-JavaScript
		// visitors. Load more takes over here; on page 2 and beyond only the
		// "Previous" link stays, until the person searches, filters or loads more.
		var pagerEl = root.querySelector('.twd-ap-articles-pager');
		if (pagerEl) {
			// Normally already done by the tiny inline script printed after the
			// links (so there is no flash); this covers a page where inline
			// scripts were stripped.
			pagerEl.setAttribute('data-js', '1');
		}

		// For a logged-in editor the list requests carry the REST nonce, so
		// the server can say which cards that person may edit (the edit
		// pencil). If the nonce has gone stale (a cached page) WordPress
		// rejects it with a 403 even on a public route, so the request is
		// repeated once without it: the grid then still loads, just without
		// pencils, never broken.
		function listFetch(url) {
			if (config.isAdmin && config.nonce) {
				return fetch(url, { cache: 'no-store', headers: { 'X-WP-Nonce': config.nonce } }).then(function (r) {
					return r.status === 403 ? fetch(url, { cache: 'no-store' }) : r;
				});
			}
			return fetch(url, { cache: 'no-store' });
		}

		// The card is one big link, so the pencil is a span with a button
		// role that stops the click from following the link.
		function openEditFromCard(el) {
			if (typeof window.TWD_AP_OpenEditor === 'function') {
				window.TWD_AP_OpenEditor(el.getAttribute('data-edit-id'));
			}
		}
		// "Edit intro" on a category page: opens a small popup that saves the
		// category's description (only printed for people who may edit it).
		root.addEventListener('click', function (e) {
			var btn = e.target.closest ? e.target.closest('.twd-ap-intro-edit') : null;
			if (!btn) { return; }
			e.preventDefault();
			openIntroEditor(btn, config);
		});
		root.addEventListener('click', function (e) {
			var el = e.target.closest ? e.target.closest('.twd-ap-card-edit') : null;
			if (!el) { return; }
			e.preventDefault();
			e.stopPropagation();
			openEditFromCard(el);
		});
		root.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' && e.key !== ' ') { return; }
			var el = e.target.closest ? e.target.closest('.twd-ap-card-edit') : null;
			if (!el) { return; }
			e.preventDefault();
			e.stopPropagation();
			openEditFromCard(el);
		});

		var state = {
			search: '',
			category: config.category || '',
			tag: config.tag || '',
			// A page link (?twd_page=2) opens on that page, so the first fetch
			// and Load more carry on from there.
			offset: config.startOffset || 0,
			loading: false,
			// Matches the class the server printed from the saved setting, so
			// the first fetch draws the same cards the server did.
			showThumbnails: !root.classList.contains('twd-ap-no-thumbnails'),
			// Set once, synchronously, from config.manyArticles -- baked into
			// this page's own server-rendered HTML (class-twd-ap-shortcode.php),
			// not fetched over REST after load. Nothing async, nothing that
			// can race, nothing that can go stale independently of the page
			// itself. Only ever re-read from the REST /grid-settings response
			// after that (loadThumbnailSetting), in case an article gets
			// published/deleted without a full page reload.
			manyArticles: !!config.manyArticles,
		};

		// Matches the 12 hard-coded in class-twd-ap-shortcode.php, for the
		// re-check against a fresh /grid-settings response further down.
		var GROUP_THRESHOLD = 12;

		// The grouped-by-category landing view only applies to the default,
		// unfiltered usage of the shortcode (no explicit category/tag
		// attribute) -- a page like [twd_articles category="anxiety"] always
		// shows its own flat, paginated list, same as before this feature --
		// and, on top of that, only once there's enough content for split
		// sections to make sense. A handful of articles reads better as one
		// plain list than split into thin, mostly-empty category sections,
		// each with its own "Load more" that would come back empty anyway.
		function canGroup() {
			return config.showFilters && '' === state.category && '' === state.tag && state.manyArticles;
		}

		if (config.showFilters && selectEl) {
			loadFacets();
		}

		// state.manyArticles is already known synchronously (from the page's
		// own server-rendered config), so the very first render doesn't wait
		// on any fetch to decide grouped vs. flat. loadThumbnailSetting still
		// runs, for thumbnails/read-more colour and to catch an article
		// published or deleted since this page itself was rendered, but it
		// no longer gates this decision.
		if (canGroup()) {
			fetchGrouped();
		} else {
			fetchArticles(true);
		}
		loadThumbnailSetting();

		if (searchEl) {
			var searchTimer = null;
			searchEl.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () {
					state.search = searchEl.value.trim();
					state.offset = 0;
					switchToFlatIfNeeded();
				}, 350);
			});
		}

		if (selectEl) {
			selectEl.addEventListener('change', function () {
				state.category = selectEl.value;
				state.offset = 0;
				// The empty-value "Show all" option means a full return to
				// the default landing view, not just clearing the category
				// filter while a search is still active.
				if (!state.category && searchEl) {
					state.search = '';
					searchEl.value = '';
				}
				switchToFlatIfNeeded();
			});
		}

		loadMoreBtn.addEventListener('click', function () {
			state.offset += config.count;
			fetchArticles(false);
		});

		if (settingsBtn) {
			settingsBtn.addEventListener('click', function () {
				openGridSettings(root, config, function () {
					// Settings changed: re-render whichever view is current.
					loadThumbnailSetting(function () {
						if (!state.search && !state.category && canGroup()) {
							fetchGrouped();
						} else {
							fetchArticles(true);
						}
					});
				});
			});
		}

		function switchToFlatIfNeeded() {
			if (!canGroup()) {
				fetchArticles(true);
				return;
			}
			var isDefault = !state.search && !state.category;
			if (isDefault) {
				fetchGrouped();
			} else {
				fetchArticles(true);
			}
		}

		function loadThumbnailSetting(done) {
			fetch(config.restUrl + '/grid-settings', { cache: 'no-store' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					state.showThumbnails = data && false !== data.show_thumbnails;
					root.classList.toggle('twd-ap-no-thumbnails', !state.showThumbnails);
					if (data && data.read_more_color) {
						root.style.setProperty('--twd-ap-readmore-color', data.read_more_color);
					}
					if (data && data.accent_color) {
						root.style.setProperty('--twd-ap-accent-color', data.accent_color);
					}
					var total = data && data.total_articles;
					state.manyArticles = 'number' === typeof total && total > GROUP_THRESHOLD;
					// Fewer than the threshold: fetch enough in one go to
					// show every article on this site, so "Load more" never
					// has a next page to offer.
					if (!state.manyArticles && 'number' === typeof total) {
						config.count = Math.max(config.count, total);
					}
					if (done) { done(); }
				})
				.catch(function () {
					if (done) { done(); }
				});
		}

		function loadFacets() {
			fetch(config.restUrl + '/articles/facets', { cache: 'no-store' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					renderCategorySelect(data.categories || []);
				})
				.catch(function () {});
		}

		function renderCategorySelect(categories) {
			// "All" first, then the categories, so the default view and the way
			// back to it are the first thing in the list.
			var html = '<option value="">All</option>';
			categories.forEach(function (cat) {
				html += '<option value="' + escapeHtml(cat.slug) + '">' + escapeHtml(cat.name) + '</option>';
			});
			selectEl.innerHTML = html;
			selectEl.value = state.category;
		}

		function fetchGrouped() {
			if (state.loading) { return; }
			state.loading = true;
			var firstPaint = keepFirstPaint;
			keepFirstPaint = false;
			if (!firstPaint) {
				groupsEl.innerHTML =
					'<div class="twd-ap-articles-group">' +
						'<span class="twd-ap-skeleton-line twd-ap-skeleton-line-heading"></span>' +
						'<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: 3;">' + skeletonCardsHtml(3) + '</div>' +
					'</div>';
				noneEl.hidden = true;
			}
			gridEl.hidden = true;
			emptyEl.hidden = true;
			loadMoreBtn.hidden = true;

			listFetch(config.restUrl + '/articles/grouped')
				.then(function (r) { return r.json(); })
				.then(function (data) {
					state.loading = false;
					var groups = data.groups || [];
					if (!groups.length) {
						groupsEl.innerHTML = '';
						// No search or category chosen here, so this is a site
						// with nothing to group, not a filter with no matches.
						noneEl.hidden = false;
						return;
					}
					var html = '';
					groups.forEach(function (group) {
						html += '<div class="twd-ap-articles-group">' +
							'<div class="twd-ap-articles-group-head">' +
								'<h2 class="twd-ap-articles-group-title">' + escapeHtml(group.category.name) + '</h2>' +
								(group.total > group.posts.length
									? '<button type="button" class="twd-ap-articles-group-more" data-slug="' + escapeHtml(group.category.slug || '') + '">See all</button>'
									: '') +
							'</div>' +
							'<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: 3;">' +
								group.posts.map(cardHtml).join('') +
							'</div>' +
						'</div>';
					});
					groupsEl.innerHTML = html;

					groupsEl.querySelectorAll('.twd-ap-articles-group-more').forEach(function (btn) {
						btn.addEventListener('click', function () {
							state.category = btn.dataset.slug;
							state.offset = 0;
							if (selectEl) { selectEl.value = state.category; }
							fetchArticles(true);
							root.scrollIntoView({ behavior: 'smooth', block: 'start' });
						});
					});
				})
				.catch(function () {
					state.loading = false;
					// A failed first fetch keeps what the server printed.
					if (!firstPaint) { groupsEl.innerHTML = ''; }
				});
		}

		function fetchArticles(replace) {
			if (state.loading) {
				return;
			}
			state.loading = true;
			var firstPaint = keepFirstPaint;
			keepFirstPaint = false;
			if (pagerEl && !firstPaint) { pagerEl.hidden = true; }
			loadMoreBtn.textContent = 'Loading…';
			groupsEl.innerHTML = '';
			if (!firstPaint) {
				gridEl.hidden = false;
				noneEl.hidden = true;
			}
			// A fresh list (new filter/search, or the very first load) has
			// nothing on screen yet to keep shape while it fetches, so
			// skeleton cards go up immediately instead of an empty grid
			// popping into a full one. "Load more" leaves what's already
			// showing alone and just appends below it.
			if (replace && !firstPaint) {
				gridEl.innerHTML = skeletonCardsHtml(config.count);
			}

			var params = ['per_page=' + encodeURIComponent(config.count), 'offset=' + encodeURIComponent(state.offset)];
			if (state.search) {
				params.push('search=' + encodeURIComponent(state.search));
			}
			if (state.category) {
				params.push('category=' + encodeURIComponent(state.category));
			}
			if (state.tag) {
				params.push('tag=' + encodeURIComponent(state.tag));
			}

			listFetch(config.restUrl + '/articles?' + params.join('&'))
				.then(function (r) {
					return r.json();
				})
				.then(function (data) {
					state.loading = false;
					loadMoreBtn.textContent = 'Load more';

					if (replace) {
						gridEl.innerHTML = '';
					}
					var items = data.items || [];
					items.forEach(function (item) {
						gridEl.insertAdjacentHTML('beforeend', cardHtml(item));
					});

					var hasResults = gridEl.children.length > 0;
					// "No articles match your filters" only when the person
					// searched or picked a category; a site (or a curated
					// category/tag list) with nothing at all gets the
					// friendly line instead.
					var userFiltered = !!state.search || (config.showFilters && !!state.category);
					emptyEl.hidden = hasResults || !userFiltered;
					// The words live here, not in the page HTML, so they are only
					// ever shown for a real search or category with no matches.
					if (!emptyEl.hidden) { emptyEl.textContent = 'No articles match your filters.'; }
					noneEl.hidden = hasResults || userFiltered;
					gridEl.hidden = !hasResults;

					// manyArticles is a hard gate on top of has_more, not just a
					// per_page bump meant to make has_more come back false on
					// its own: relying on that bump alone means a wrong (e.g.
					// stale-cached) total_articles count could still leave
					// Load more showing on a small site. This way it never
					// can, whatever total_articles actually reported.
					loadMoreBtn.hidden = !state.manyArticles || !data.has_more;
				})
				.catch(function () {
					state.loading = false;
					loadMoreBtn.textContent = 'Load more';
					// A failed first fetch keeps what the server printed.
					if (replace && !firstPaint) {
						gridEl.innerHTML = '';
						gridEl.hidden = true;
					}
				});
		}

		function cardHtml(item) {
			var thumb = state.showThumbnails
				? (item.thumbnail
					? '<span class="twd-ap-article-thumb" style="background-image:url(\'' + escapeUrl(item.thumbnail) + '\')"></span>'
					: '<span class="twd-ap-article-thumb twd-ap-article-thumb-placeholder" aria-hidden="true">' + PLACEHOLDER_SVG + '</span>')
				: '';
			var eyebrow = item.category ? escapeHtml(item.category) + ' &middot; ' : '';
			var edit = (item.can_edit && typeof window.TWD_AP_OpenEditor === 'function')
				? '<span class="twd-ap-card-edit" role="button" tabindex="0" data-edit-id="' + parseInt(item.id, 10) + '" aria-label="Edit this article" title="Edit this article">' + PENCIL_SVG + '</span>'
				: '';

			return '<a class="twd-ap-article-card" href="' + escapeHtml(item.link) + '">' +
				edit +
				thumb +
				'<span class="twd-ap-article-body">' +
					'<span class="twd-ap-article-date">' + eyebrow + escapeHtml(item.date) + ' &middot; ' + parseInt(item.reading_time, 10) + ' min read</span>' +
					'<span class="twd-ap-article-title">' + escapeHtml(item.title) + '</span>' +
					'<span class="twd-ap-article-excerpt">' + escapeHtml(item.excerpt) + '</span>' +
					'<span class="twd-ap-article-readmore">Read more</span>' +
				'</span>' +
			'</a>';
		}
	}

	// -- Admin "Customise this grid" popup: category order (drag to reorder),
	// how many categories/posts to show, and the thumbnails toggle. Opened
	// per grid instance, but the settings it saves are site-wide.
	function openGridSettings(root, config, onSaved) {
		var overlay = document.querySelector('.twd-ap-grid-settings-overlay[data-for="' + root.id + '"]');
		if (!overlay) { return; }

		var closeBtn   = overlay.querySelector('.twd-ap-grid-settings-close');
		var catsCount  = overlay.querySelector('#twd-ap-gs-cats-count');
		var postsCount = overlay.querySelector('#twd-ap-gs-posts-count');
		var thumbsBox  = overlay.querySelector('#twd-ap-gs-thumbnails');
		var colorInput = overlay.querySelector('#twd-ap-gs-readmore-color');
		var accentColorInput = overlay.querySelector('#twd-ap-gs-accent-color');
		// Not applied via a CSS var on `root` like the other two colours --
		// the swipe book button it styles lives on every article page, not
		// just pages carrying this grid, so it's set server-side inline by
		// TWD_AP_Swipebook::append_button() instead. This picker only reads
		// and saves the value.
		var swipebookColorInput = overlay.querySelector('#twd-ap-gs-swipebook-color');
		var sbHeadingInput = overlay.querySelector('#twd-ap-gs-sb-heading');
		var sbLabelInput = overlay.querySelector('#twd-ap-gs-sb-label');
		var sbLogoBgSelect = overlay.querySelector('#twd-ap-gs-sb-logo-bg');
		var sbExportBrandingBox = overlay.querySelector('#twd-ap-gs-sb-export-branding');
		var orderList  = overlay.querySelector('#twd-ap-gs-cat-order');
		var topicsBox  = overlay.querySelector('#twd-ap-gs-topics');
		var introShow = overlay.querySelector('#twd-ap-gs-intro-show');
		var archiveTakeover = overlay.querySelector('#twd-ap-gs-archive-takeover');
		var relatedShow = overlay.querySelector('#twd-ap-gs-related-show');
		var relatedCount = overlay.querySelector('#twd-ap-gs-related-count');
		var relatedTopics = overlay.querySelector('#twd-ap-gs-related-topics');
		var sbPosition = overlay.querySelector('#twd-ap-gs-sb-position');
		var ctaHeading = overlay.querySelector('#twd-ap-gs-cta-heading');
		var ctaText = overlay.querySelector('#twd-ap-gs-cta-text');
		var ctaLabel = overlay.querySelector('#twd-ap-gs-cta-label');
		var ctaUrl = overlay.querySelector('#twd-ap-gs-cta-url');
		var ctaGrid = overlay.querySelector('#twd-ap-gs-cta-grid');
		var ctaCategories = overlay.querySelector('#twd-ap-gs-cta-categories');
		var ctaIndex = overlay.querySelector('#twd-ap-gs-cta-index');
		var ctaArticles = overlay.querySelector('#twd-ap-gs-cta-articles');
		var lookInputs = overlay.querySelectorAll('[data-look]');
		var lookPreview = overlay.querySelector('#twd-ap-gs-look-preview');
		var lookReset  = overlay.querySelector('#twd-ap-gs-look-reset');
		// Article look: '' means "not changed, use the plugin default". Only
		// what the owner actually changes is stored, so an untouched site
		// keeps the plugin's own typography.
		var lookState = {};
		var LOOK_SERIF = 'Georgia, "Times New Roman", Times, serif';
		var LOOK_LEADING = { compact: '1.55', relaxed: '1.95' };

		function applyLookPreview() {
			var p = lookPreview;
			if (!p) { return; }
			var map = {
				look_text_color: '--twd-ap-content-text',
				look_heading_color: '--twd-ap-content-heading',
				look_link_color: '--twd-ap-content-link',
				look_quote_color: '--twd-ap-content-quote'
			};
			Object.keys(map).forEach(function (key) {
				if (lookState[key]) { p.style.setProperty(map[key], lookState[key]); } else { p.style.removeProperty(map[key]); }
			});
			p.style.removeProperty('--twd-ap-content-font');
			p.style.removeProperty('font-family');
			if (lookState.look_font === 'serif') { p.style.setProperty('--twd-ap-content-font', LOOK_SERIF); }
			if (lookState.look_font === 'theme') { p.style.setProperty('font-family', 'inherit', 'important'); }
			if (lookState.look_size === 'large') { p.style.setProperty('--twd-ap-content-size', '19px'); } else { p.style.removeProperty('--twd-ap-content-size'); }
			if (LOOK_LEADING[lookState.look_spacing]) { p.style.setProperty('--twd-ap-content-leading', LOOK_LEADING[lookState.look_spacing]); } else { p.style.removeProperty('--twd-ap-content-leading'); }
		}
		function fillLookInputs() {
			Array.prototype.forEach.call(lookInputs, function (el) {
				var key = el.getAttribute('data-look');
				if (el.type === 'color') {
					el.value = lookState[key] || el.getAttribute('data-default');
				} else {
					// selects fall back to their plain default option
					el.value = lookState[key] || (key === 'look_font' ? 'system' : (key === 'look_size' ? 'medium' : 'normal'));
				}
			});
			applyLookPreview();
		}
		Array.prototype.forEach.call(lookInputs, function (el) {
			el.addEventListener('input', function () {
				lookState[el.getAttribute('data-look')] = el.value;
				applyLookPreview();
			});
			el.addEventListener('change', function () {
				lookState[el.getAttribute('data-look')] = el.value;
				applyLookPreview();
			});
		});
		if (lookReset) {
			lookReset.addEventListener('click', function () {
				lookState = { look_text_color: '', look_heading_color: '', look_link_color: '', look_quote_color: '', look_font: 'system', look_size: 'medium', look_spacing: 'normal' };
				fillLookInputs();
			});
		}
		var saveBtn    = overlay.querySelector('#twd-ap-gs-save');
		var statusEl   = overlay.querySelector('#twd-ap-grid-settings-status');

		var photoPreview = overlay.querySelector('#twd-ap-gs-profile-photo-preview');
		var photoSelect  = overlay.querySelector('#twd-ap-gs-profile-photo-select');
		var photoRemove  = overlay.querySelector('#twd-ap-gs-profile-photo-remove');
		var nameInput    = overlay.querySelector('#twd-ap-gs-profile-name');
		var bioInput     = overlay.querySelector('#twd-ap-gs-profile-bio');
		var linkUrlInput = overlay.querySelector('#twd-ap-gs-profile-link-url');
		var linkLabelInput = overlay.querySelector('#twd-ap-gs-profile-link-label');
		var profilePhotoId = 0;
		var profileFrame = null;

		function renderPhotoPreview(url) {
			if (url) {
				photoPreview.style.backgroundImage = 'url(' + url + ')';
				photoRemove.hidden = false;
			} else {
				photoPreview.style.backgroundImage = '';
				photoRemove.hidden = true;
			}
		}

		photoSelect.addEventListener('click', function () {
			if (!window.wp || !wp.media) { return; }
			if (!profileFrame) {
				profileFrame = wp.media({ title: 'Choose Profile Photo', multiple: false, library: { type: 'image' } });
				profileFrame.on('select', function () {
					var att = profileFrame.state().get('selection').first().toJSON();
					profilePhotoId = att.id;
					var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
					renderPhotoPreview(url);
				});
			}
			profileFrame.open();
		});
		photoRemove.addEventListener('click', function () {
			profilePhotoId = 0;
			renderPhotoPreview('');
		});

		var sbLogoPreview = overlay.querySelector('#twd-ap-gs-sb-logo-preview');
		var sbLogoSelect  = overlay.querySelector('#twd-ap-gs-sb-logo-select');
		var sbLogoRemove  = overlay.querySelector('#twd-ap-gs-sb-logo-remove');
		var sbLogoId = 0;
		var sbLogoFrame = null;

		function renderSbLogoPreview(url) {
			if (url) {
				sbLogoPreview.style.backgroundImage = 'url(' + url + ')';
				sbLogoRemove.hidden = false;
			} else {
				sbLogoPreview.style.backgroundImage = '';
				sbLogoRemove.hidden = true;
			}
		}

		sbLogoSelect.addEventListener('click', function () {
			if (!window.wp || !wp.media) { return; }
			if (!sbLogoFrame) {
				sbLogoFrame = wp.media({ title: 'Choose Swipe Book Logo', multiple: false, library: { type: 'image' } });
				sbLogoFrame.on('select', function () {
					var att = sbLogoFrame.state().get('selection').first().toJSON();
					sbLogoId = att.id;
					var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
					renderSbLogoPreview(url);
				});
			}
			sbLogoFrame.open();
		});
		sbLogoRemove.addEventListener('click', function () {
			sbLogoId = 0;
			renderSbLogoPreview('');
		});

		function close() {
			overlay.hidden = true;
		}
		closeBtn.addEventListener('click', close);
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) { close(); }
		});

		function setStatus(msg, ok) {
			statusEl.textContent = msg;
			statusEl.hidden = !msg;
			statusEl.classList.toggle('twd-ap-status-ok', !!ok);
		}

		// Checkboxes for the "Browse by topic" row. 'all' (the default) shows
		// every box ticked; once some are unticked the saved list is used.
		function renderTopics(categories, chosen) {
			topicsBox.innerHTML = '';
			categories.forEach(function (cat) {
				var on = !Array.isArray(chosen) || chosen.indexOf(parseInt(cat.id, 10)) !== -1;
				var label = document.createElement('label');
				label.className = 'twd-ap-checkbox-label';
				label.innerHTML = '<input type="checkbox" value="' + parseInt(cat.id, 10) + '"' + (on ? ' checked' : '') + ' /> ' + escapeHtml(cat.name);
				topicsBox.appendChild(label);
			});
		}
		function topicsValue() {
			var boxes = topicsBox.querySelectorAll('input[type="checkbox"]');
			var ids = [];
			var all = true;
			Array.prototype.forEach.call(boxes, function (b) {
				if (b.checked) { ids.push(parseInt(b.value, 10)); } else { all = false; }
			});
			return all ? 'all' : ids;
		}

		function renderOrderList(categories) {
			orderList.innerHTML = '';
			categories.forEach(function (cat) {
				var li = document.createElement('li');
				li.className = 'twd-ap-gs-cat-row';
				li.draggable = true;
				li.dataset.id = cat.id;
				li.innerHTML = '<span class="twd-ap-gs-drag-handle" aria-hidden="true">&#8942;&#8942;</span><span>' + escapeHtml(cat.name) + '</span>';
				orderList.appendChild(li);
			});
		}

		var dragging = null;
		orderList.addEventListener('dragstart', function (e) {
			var li = e.target.closest('.twd-ap-gs-cat-row');
			if (!li) { return; }
			dragging = li;
			li.classList.add('is-dragging');
		});
		orderList.addEventListener('dragend', function () {
			if (dragging) { dragging.classList.remove('is-dragging'); }
			dragging = null;
		});
		orderList.addEventListener('dragover', function (e) {
			e.preventDefault();
			var after = getDragAfterElement(orderList, e.clientY);
			if (!dragging) { return; }
			if (after == null) {
				orderList.appendChild(dragging);
			} else {
				orderList.insertBefore(dragging, after);
			}
		});

		function getDragAfterElement(container, y) {
			var rows = Array.prototype.slice.call(container.querySelectorAll('.twd-ap-gs-cat-row:not(.is-dragging)'));
			var closest = { offset: -Infinity, element: null };
			rows.forEach(function (row) {
				var box = row.getBoundingClientRect();
				var offset = y - box.top - box.height / 2;
				if (offset < 0 && offset > closest.offset) {
					closest = { offset: offset, element: row };
				}
			});
			return closest.element;
		}

		// Save stays off until the current settings have loaded, so a failed or
		// partial load can never save blank fields over what is stored.
		saveBtn.disabled = true;
		fetch(config.restUrl + '/grid-settings', { cache: 'no-store' })
			.then(function (r) {
				if (!r.ok) { throw new Error('load'); }
				return r.json();
			})
			.then(function (data) {
				catsCount.value = data.categories_count || 4;
				postsCount.value = data.posts_per_category || 6;
				thumbsBox.checked = false !== data.show_thumbnails;
				colorInput.value = data.read_more_color || '#1d4ed8';
				accentColorInput.value = data.accent_color || '#2563eb';
				swipebookColorInput.value = data.swipebook_color || '#5b8a72';
				sbHeadingInput.value = data.swipebook_heading || '';
				sbLabelInput.value = data.swipebook_label || '';
				sbLogoBgSelect.value = data.swipebook_logo_bg || 'light';
				sbExportBrandingBox.checked = false !== data.swipebook_export_branding;
				sbLogoId = data.swipebook_logo_id || 0;
				renderSbLogoPreview(data.swipebook_logo_url || '');
				renderOrderList(data.categories || []);
				if (topicsBox) { renderTopics(data.categories || [], data.topic_category_ids); }
				lookState = {
					look_text_color: data.look_text_color || '',
					look_heading_color: data.look_heading_color || '',
					look_link_color: data.look_link_color || '',
					look_quote_color: data.look_quote_color || '',
					look_font: data.look_font || 'system',
					look_size: data.look_size || 'medium',
					look_spacing: data.look_spacing || 'normal'
				};
				if (lookInputs.length) { fillLookInputs(); }
				if (introShow) { introShow.checked = !!data.intro_show; }
				if (archiveTakeover) { archiveTakeover.checked = false !== data.archive_takeover; }
				if (relatedShow) {
					relatedShow.checked = false !== data.related_show;
					relatedCount.value = data.related_count || 4;
					relatedTopics.checked = false !== data.related_topics;
				}
				if (sbPosition) { sbPosition.value = data.swipebook_button_position || 'both'; }
				// A page cached before an update can carry an older popup without
				// some of these fields; they are skipped here and on save.
				if (ctaHeading) {
					ctaHeading.value = data.cta_heading || '';
					ctaText.value = data.cta_text || '';
					ctaLabel.value = data.cta_button_label || '';
					ctaUrl.value = data.cta_button_url || '';
					ctaGrid.checked = !!data.cta_show_grid;
					ctaCategories.checked = !!data.cta_show_categories;
					ctaIndex.checked = !!data.cta_show_index;
					ctaArticles.checked = !!data.cta_show_articles;
				}
				profilePhotoId = data.profile_photo_id || 0;
				renderPhotoPreview(data.profile_photo_url || '');
				nameInput.value = data.profile_name || '';
				bioInput.value = data.profile_bio || '';
				linkUrlInput.value = data.profile_link_url || '';
				linkLabelInput.value = data.profile_link_label || '';
				saveBtn.disabled = false;
				overlay.hidden = false;
			})
			.catch(function () {
				setStatus('Could not load your current settings, so saving is switched off. Close this and try again.', false);
				overlay.hidden = false;
			});

		saveBtn.onclick = function () {
			var order = Array.prototype.map.call(orderList.querySelectorAll('.twd-ap-gs-cat-row'), function (li) {
				return parseInt(li.dataset.id, 10);
			});
			var payload = {
				categories_count: parseInt(catsCount.value, 10) || 4,
				posts_per_category: parseInt(postsCount.value, 10) || 6,
				show_thumbnails: !!thumbsBox.checked,
				read_more_color: colorInput.value,
				accent_color: accentColorInput.value,
				swipebook_color: swipebookColorInput.value,
				swipebook_heading: sbHeadingInput.value,
				swipebook_label: sbLabelInput.value,
				swipebook_logo_id: sbLogoId,
				swipebook_logo_bg: sbLogoBgSelect.value,
				swipebook_export_branding: !!sbExportBrandingBox.checked,
				category_order: order,
				profile_photo_id: profilePhotoId,
				profile_name: nameInput.value,
				profile_bio: bioInput.value,
				profile_link_url: linkUrlInput.value,
				profile_link_label: linkLabelInput.value,
			};

			// Only the sections this popup actually has are sent, so an older cached
			// popup can never clear a setting it does not show.
			if (topicsBox) { payload.topic_category_ids = topicsValue(); }
			if (introShow) { payload.intro_show = !!introShow.checked; }
			if (archiveTakeover) { payload.archive_takeover = !!archiveTakeover.checked; }
			if (relatedShow) {
				payload.related_show = !!relatedShow.checked;
				payload.related_count = parseInt(relatedCount.value, 10) || 4;
				payload.related_topics = !!relatedTopics.checked;
			}
			if (sbPosition) { payload.swipebook_button_position = sbPosition.value; }
			if (ctaHeading) {
				payload.cta_heading = ctaHeading.value;
				payload.cta_text = ctaText.value;
				payload.cta_button_label = ctaLabel.value;
				payload.cta_button_url = ctaUrl.value;
				payload.cta_show_grid = !!ctaGrid.checked;
				payload.cta_show_categories = !!ctaCategories.checked;
				payload.cta_show_index = !!ctaIndex.checked;
				payload.cta_show_articles = !!ctaArticles.checked;
			}
			if (lookInputs.length) {
				payload.look_text_color = lookState.look_text_color || '';
				payload.look_heading_color = lookState.look_heading_color || '';
				payload.look_link_color = lookState.look_link_color || '';
				payload.look_quote_color = lookState.look_quote_color || '';
				payload.look_font = lookState.look_font || 'system';
				payload.look_size = lookState.look_size || 'medium';
				payload.look_spacing = lookState.look_spacing || 'normal';
			}

			setStatus('Saving…', false);
			fetch(config.restUrl + '/grid-settings', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify(payload),
			})
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
				.then(function (result) {
					if (!result.ok) {
						setStatus('Could not save. Please try again.', false);
						return;
					}
					setStatus('Saved.', true);
					if (onSaved) { onSaved(); }
					setTimeout(close, 700);
				})
				.catch(function () {
					setStatus('Could not save. Please try again.', false);
				});
		};
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function escapeUrl(str) {
		return escapeHtml(str).replace(/'/g, '%27');
	}
})();
