(function () {
	'use strict';
	if (typeof TWD_AP_SB === 'undefined') {
		return;
	}

	document.addEventListener('click', function (e) {
		var link = e.target.closest('.twd-ap-swipebook-btn');
		if (!link) { return; }

		var postId = link.getAttribute('data-twd-ap-post-id');
		if (!postId || typeof window.TWD_AP_SwipeBook === 'undefined') {
			// No JS support or the shared module failed to load: fall back
			// to the normal link, the full standalone swipe book page.
			return;
		}
		e.preventDefault();

		var original = link.textContent;
		link.textContent = 'Loading…';

		fetch(TWD_AP_SB.restUrl + '/articles/' + encodeURIComponent(postId) + '/swipebook')
			.then(function (r) {
				if (!r.ok) { throw new Error('failed'); }
				return r.json();
			})
			.then(function (data) {
				link.textContent = original;
				window.TWD_AP_SwipeBook.open(data);
			})
			.catch(function () {
				link.textContent = original;
				window.location.href = link.href;
			});
	});
}());
