(function () {
	'use strict';

	var PLACEHOLDER_SVG = '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5">' +
		'<rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="M21 16l-5.5-5.5-4 4L8 11l-5 5" /></svg>';

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

		var gridEl = root.querySelector('.twd-ap-articles-grid');
		var emptyEl = root.querySelector('.twd-ap-articles-empty');
		var loadMoreBtn = root.querySelector('.twd-ap-articles-loadmore');
		var searchEl = root.querySelector('.twd-ap-articles-search');
		var pillsEl = root.querySelector('.twd-ap-articles-pills');

		var state = {
			search: '',
			category: config.category || '',
			tag: config.tag || '',
			page: 1,
			loading: false,
		};

		if (config.showFilters && pillsEl) {
			loadFacets();
		}
		fetchArticles(true);

		if (searchEl) {
			var searchTimer = null;
			searchEl.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () {
					state.search = searchEl.value.trim();
					state.page = 1;
					fetchArticles(true);
				}, 350);
			});
		}

		loadMoreBtn.addEventListener('click', function () {
			state.page += 1;
			fetchArticles(false);
		});

		function loadFacets() {
			fetch(config.restUrl + '/articles/facets')
				.then(function (r) {
					return r.json();
				})
				.then(function (data) {
					renderPills(data.categories || []);
				})
				.catch(function () {});
		}

		function renderPills(categories) {
			var html = '<button type="button" class="twd-ap-pill twd-ap-pill-active" data-slug="">All</button>';
			categories.forEach(function (cat) {
				html += '<button type="button" class="twd-ap-pill" data-slug="' + escapeHtml(cat.slug) + '">' + escapeHtml(cat.name) + '</button>';
			});
			pillsEl.innerHTML = html;
			pillsEl.addEventListener('click', function (e) {
				var btn = e.target.closest('.twd-ap-pill');
				if (!btn) {
					return;
				}
				var current = pillsEl.querySelector('.twd-ap-pill-active');
				if (current) {
					current.classList.remove('twd-ap-pill-active');
				}
				btn.classList.add('twd-ap-pill-active');
				state.category = btn.dataset.slug;
				state.page = 1;
				fetchArticles(true);
			});
		}

		function fetchArticles(replace) {
			if (state.loading) {
				return;
			}
			state.loading = true;
			loadMoreBtn.textContent = 'Loading…';

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

					var hasMore = data.total_pages && state.page < data.total_pages;
					loadMoreBtn.hidden = !hasMore;
				})
				.catch(function () {
					state.loading = false;
					loadMoreBtn.textContent = 'Load more';
				});
		}

		function cardHtml(item) {
			var thumb = item.thumbnail
				? '<span class="twd-ap-article-thumb" style="background-image:url(\'' + escapeUrl(item.thumbnail) + '\')"></span>'
				: '<span class="twd-ap-article-thumb twd-ap-article-thumb-placeholder" aria-hidden="true">' + PLACEHOLDER_SVG + '</span>';
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

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function escapeUrl(str) {
		return escapeHtml(str).replace(/'/g, '%27');
	}
})();
