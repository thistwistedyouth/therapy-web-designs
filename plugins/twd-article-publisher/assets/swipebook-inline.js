(function () {
	'use strict';
	if (typeof TWD_AP_SB === 'undefined') {
		return;
	}

	document.addEventListener('click', function (e) {
		// The main article-page button, and a "more articles" item inside an
		// already-open Summary Book -- same open-in-overlay flow, but a
		// related-item click also has to close the book it's inside of
		// first, since open() no-ops while one is already showing.
		var link = e.target.closest('.twd-ap-swipebook-btn, .twd-sb-related-item');
		if (!link) { return; }

		var postId = link.getAttribute('data-twd-ap-post-id');
		if (!postId || typeof window.TWD_AP_SwipeBook === 'undefined') {
			// No JS support or the shared module failed to load: fall back
			// to the normal link, the full standalone Summary Book page.
			return;
		}
		e.preventDefault();

		var isRelated = link.classList.contains('twd-sb-related-item');
		var original = isRelated ? null : link.textContent;
		if (isRelated) {
			link.classList.add('twd-sb-related-item-loading');
		} else {
			link.textContent = 'Loading…';
		}

		fetch(TWD_AP_SB.restUrl + '/articles/' + encodeURIComponent(postId) + '/swipebook')
			.then(function (r) {
				if (!r.ok) { throw new Error('failed'); }
				return r.json();
			})
			.then(function (data) {
				if (isRelated && window.TWD_AP_SwipeBook.close) {
					window.TWD_AP_SwipeBook.close();
				} else if (!isRelated) {
					link.textContent = original;
				}
				window.TWD_AP_SwipeBook.open(data);
			})
			.catch(function () {
				if (isRelated) {
					link.classList.remove('twd-sb-related-item-loading');
				} else {
					link.textContent = original;
				}
				window.location.href = link.href;
			});
	});
}());
