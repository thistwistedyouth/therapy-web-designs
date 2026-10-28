(function () {
	'use strict';

	// The related articles grid is up to 900px wide, centred, wider than the article text. That only works when
	// the article's column is itself centred on the page: with a sidebar (or any column that is off centre) it
	// would be cut off at one side or spill over the sidebar, so there it stays inside its column. Only the
	// browser knows the layout, so it is measured here; without JavaScript the grid simply fits its column.
	function layoutRelatedGrids() {
		var blocks = document.querySelectorAll('.twd-ap-related-wide');
		var vw = document.documentElement.clientWidth;
		for (var i = 0; i < blocks.length; i++) {
			var el = blocks[i];
			el.classList.remove('twd-ap-related-break');
			var parent = el.parentElement;
			if (!parent) { continue; }
			var r = parent.getBoundingClientRect();
			var cs = window.getComputedStyle(parent);
			var left = r.left + (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.borderLeftWidth) || 0);
			var right = r.right - (parseFloat(cs.paddingRight) || 0) - (parseFloat(cs.borderRightWidth) || 0);
			var want = Math.min(900, vw - 32);
			if (right - left < want - 1 && Math.abs((left + right) / 2 - vw / 2) <= 24) {
				el.classList.add('twd-ap-related-break');
			}
		}
	}
	var relatedTimer = 0;
	function laterLayoutRelatedGrids() {
		window.clearTimeout(relatedTimer);
		relatedTimer = window.setTimeout(layoutRelatedGrids, 100);
	}
	layoutRelatedGrids();
	document.addEventListener('DOMContentLoaded', layoutRelatedGrids);
	window.addEventListener('load', layoutRelatedGrids);
	window.addEventListener('resize', laterLayoutRelatedGrids);

	document.addEventListener('click', function (e) {
		var embed = e.target.closest('.twd-ap-yt-embed[data-youtube-id]');
		if (!embed || embed.classList.contains('twd-ap-yt-embed-active')) {
			return;
		}
		var videoId = embed.getAttribute('data-youtube-id');
		embed.classList.add('twd-ap-yt-embed-active');
		embed.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1" ' +
			'title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
	});
})();
