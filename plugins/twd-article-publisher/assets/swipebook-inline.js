(function () {
	'use strict';
	if (typeof TWD_AP_SB === 'undefined') {
		return;
	}

	document.addEventListener('click', function (e) {
		// The main article-page button(s) (swipe book and/or Summary Book), a
		// "more articles" item inside an already-open swipe book, and a
		// book's own companion cross-link to the other book -- all the same
		// open-in-overlay flow. A related-item or companion-link click also
		// has to close the book it's inside of first, since open() no-ops
		// while one is already showing. Which book gets fetched is decided
		// by data-twd-ap-book-variant, defaulting to the swipe book for the
		// related-item case (which never carries that attribute).
		var link = e.target.closest('.twd-ap-swipebook-btn, .twd-sb-related-item, .twd-sb-companion-link');
		if (!link) { return; }

		var postId = link.getAttribute('data-twd-ap-post-id');
		if (!postId || typeof window.TWD_AP_SwipeBook === 'undefined') {
			// No JS support or the shared module failed to load: fall back
			// to the normal link, the full standalone page for that book.
			return;
		}
		e.preventDefault();

		var isReplacing = link.classList.contains('twd-sb-related-item') || link.classList.contains('twd-sb-companion-link');
		var original = isReplacing ? null : link.textContent;
		if (isReplacing) {
			link.classList.add('twd-sb-related-item-loading');
		} else {
			link.textContent = 'Loading…';
		}

		var variant = link.getAttribute('data-twd-ap-book-variant') || 'swipebook';
		var endpoint = 'summary-book' === variant ? 'summary-book' : 'swipebook';

		fetch(TWD_AP_SB.restUrl + '/articles/' + encodeURIComponent(postId) + '/' + endpoint)
			.then(function (r) {
				if (!r.ok) { throw new Error('failed'); }
				return r.json();
			})
			.then(function (data) {
				if (isReplacing && window.TWD_AP_SwipeBook.close) {
					window.TWD_AP_SwipeBook.close();
				} else if (!isReplacing) {
					link.textContent = original;
				}
				window.TWD_AP_SwipeBook.open(data);
			})
			.catch(function () {
				if (isReplacing) {
					link.classList.remove('twd-sb-related-item-loading');
				} else {
					link.textContent = original;
				}
				window.location.href = link.href;
			});
	});
}());
