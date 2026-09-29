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

	document.addEventListener('DOMContentLoaded', function () {
		var containers = document.querySelectorAll('.twd-ap-articles');
		Array.prototype.forEach.call(containers, initGrid);
	});

	function initGrid(root) {
		var config;
		try {
			config = JSON.parse(root.dataset.config);
		} catch (e) {
			return;
		}

		var groupsEl    = root.querySelector('.twd-ap-articles-groups');
		var gridEl      = root.querySelector('.twd-ap-articles-grid');
		var emptyEl     = root.querySelector('.twd-ap-articles-empty');
		var loadMoreBtn = root.querySelector('.twd-ap-articles-loadmore');
		var searchEl    = root.querySelector('.twd-ap-articles-search');
		var selectEl    = root.querySelector('.twd-ap-articles-category-select');
		var settingsBtn = root.querySelector('.twd-ap-articles-settings-btn');

		var state = {
			search: '',
			category: config.category || '',
			tag: config.tag || '',
			page: 1,
			loading: false,
			showThumbnails: true,
		};

		// The grouped-by-category landing view only applies to the default,
		// unfiltered usage of the shortcode (no explicit category/tag
		// attribute) -- a page like [twd_articles category="anxiety"] always
		// shows its own flat, paginated list, same as before this feature.
		var canGroup = config.showFilters && '' === state.category && '' === state.tag;

		loadThumbnailSetting();

		if (config.showFilters && selectEl) {
			loadFacets();
		}

		if (canGroup) {
			fetchGrouped();
		} else {
			fetchArticles(true);
		}

		if (searchEl) {
			var searchTimer = null;
			searchEl.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () {
					state.search = searchEl.value.trim();
					state.page = 1;
					switchToFlatIfNeeded();
				}, 350);
			});
		}

		if (selectEl) {
			selectEl.addEventListener('change', function () {
				state.category = selectEl.value;
				state.page = 1;
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
			state.page += 1;
			fetchArticles(false);
		});

		if (settingsBtn) {
			settingsBtn.addEventListener('click', function () {
				openGridSettings(root, config, function () {
					// Settings changed: re-render whichever view is current.
					if (!state.search && !state.category && canGroup) {
						fetchGrouped();
					} else {
						fetchArticles(true);
					}
					loadThumbnailSetting();
				});
			});
		}

		function switchToFlatIfNeeded() {
			if (!canGroup) {
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

		function loadThumbnailSetting() {
			fetch(config.restUrl + '/grid-settings')
				.then(function (r) { return r.json(); })
				.then(function (data) {
					state.showThumbnails = data && false !== data.show_thumbnails;
					root.classList.toggle('twd-ap-no-thumbnails', !state.showThumbnails);
					if (data && data.read_more_color) {
						root.style.setProperty('--twd-ap-readmore-color', data.read_more_color);
					}
				})
				.catch(function () {});
		}

		function loadFacets() {
			fetch(config.restUrl + '/articles/facets')
				.then(function (r) { return r.json(); })
				.then(function (data) {
					renderCategorySelect(data.categories || []);
				})
				.catch(function () {});
		}

		function renderCategorySelect(categories) {
			var html = '';
			categories.forEach(function (cat) {
				html += '<option value="' + escapeHtml(cat.slug) + '">' + escapeHtml(cat.name) + '</option>';
			});
			html += '<option value="">Show all</option>';
			selectEl.innerHTML = html;
			selectEl.value = state.category;
		}

		function fetchGrouped() {
			if (state.loading) { return; }
			state.loading = true;
			groupsEl.innerHTML =
				'<div class="twd-ap-articles-group">' +
					'<span class="twd-ap-skeleton-line twd-ap-skeleton-line-heading"></span>' +
					'<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: 3;">' + skeletonCardsHtml(3) + '</div>' +
				'</div>';
			gridEl.hidden = true;
			emptyEl.hidden = true;
			loadMoreBtn.hidden = true;

			fetch(config.restUrl + '/articles/grouped')
				.then(function (r) { return r.json(); })
				.then(function (data) {
					state.loading = false;
					var groups = data.groups || [];
					if (!groups.length) {
						groupsEl.innerHTML = '';
						emptyEl.hidden = false;
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
							state.page = 1;
							if (selectEl) { selectEl.value = state.category; }
							fetchArticles(true);
							root.scrollIntoView({ behavior: 'smooth', block: 'start' });
						});
					});
				})
				.catch(function () {
					state.loading = false;
					groupsEl.innerHTML = '';
				});
		}

		function fetchArticles(replace) {
			if (state.loading) {
				return;
			}
			state.loading = true;
			loadMoreBtn.textContent = 'Loading…';
			groupsEl.innerHTML = '';
			gridEl.hidden = false;
			// A fresh list (new filter/search, or the very first load) has
			// nothing on screen yet to keep shape while it fetches, so
			// skeleton cards go up immediately instead of an empty grid
			// popping into a full one. "Load more" leaves what's already
			// showing alone and just appends below it.
			if (replace) {
				gridEl.innerHTML = skeletonCardsHtml(config.count);
			}

			var params = ['per_page=' + encodeURIComponent(config.count), 'page=' + encodeURIComponent(state.page)];
			if (state.search) {
				params.push('search=' + encodeURIComponent(state.search));
			}
			if (state.category) {
				params.push('category=' + encodeURIComponent(state.category));
			}
			if (state.tag) {
				params.push('tag=' + encodeURIComponent(state.tag));
			}

			fetch(config.restUrl + '/articles?' + params.join('&'))
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
					emptyEl.hidden = hasResults;
					gridEl.hidden = !hasResults;

					// total_pages alone isn't trusted on its own: a page that
					// came back with fewer items than asked for is clearly the
					// last one, whatever total_pages says, so Load more never
					// shows a page that would come back empty.
					var hasMore = !!data.total_pages && state.page < data.total_pages && items.length >= config.count;
					loadMoreBtn.hidden = !hasMore;
				})
				.catch(function () {
					state.loading = false;
					loadMoreBtn.textContent = 'Load more';
					if (replace) {
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
			var featuredBadge = item.featured ? '<span class="twd-ap-article-featured-badge">Featured</span>' : '';

			return '<a class="twd-ap-article-card' + (item.featured ? ' twd-ap-article-card-featured' : '') + '" href="' + escapeHtml(item.link) + '">' +
				thumb +
				featuredBadge +
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
		var orderList  = overlay.querySelector('#twd-ap-gs-cat-order');
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

		fetch(config.restUrl + '/grid-settings')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				catsCount.value = data.categories_count || 4;
				postsCount.value = data.posts_per_category || 6;
				thumbsBox.checked = false !== data.show_thumbnails;
				colorInput.value = data.read_more_color || '#1d4ed8';
				renderOrderList(data.categories || []);
				profilePhotoId = data.profile_photo_id || 0;
				renderPhotoPreview(data.profile_photo_url || '');
				nameInput.value = data.profile_name || '';
				bioInput.value = data.profile_bio || '';
				linkUrlInput.value = data.profile_link_url || '';
				linkLabelInput.value = data.profile_link_label || '';
				overlay.hidden = false;
			})
			.catch(function () {
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
				category_order: order,
				profile_photo_id: profilePhotoId,
				profile_name: nameInput.value,
				profile_bio: bioInput.value,
				profile_link_url: linkUrlInput.value,
				profile_link_label: linkLabelInput.value,
			};

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
