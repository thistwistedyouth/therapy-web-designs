(function () {
	'use strict';

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
