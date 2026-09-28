(function () {
	'use strict';

	var app = document.getElementById('twd-sb-app');
	if (!app) {
		return;
	}

	var slidesWrap = document.getElementById('twd-sb-slides');
	var slides = Array.prototype.slice.call(slidesWrap.querySelectorAll('.twd-sb-slide'));
	var segs = Array.prototype.slice.call(document.querySelectorAll('.twd-sb-seg'));
	var prevBtn = document.getElementById('twd-sb-prev');
	var nextBtn = document.getElementById('twd-sb-next');
	var counterEl = document.getElementById('twd-sb-counter');
	var shareBtn = document.getElementById('twd-sb-share-btn');

	var total = slides.length;
	var index = 0;

	function render() {
		slides.forEach(function (slide, i) {
			slide.classList.toggle('is-active', i === index);
			slide.classList.toggle('is-leaving-left', i < index);
		});
		segs.forEach(function (seg, i) {
			seg.classList.toggle('is-done', i < index);
			seg.classList.toggle('is-current', i === index);
		});
		counterEl.textContent = (index + 1) + ' / ' + total;
		prevBtn.disabled = index === 0;
		nextBtn.disabled = index === total - 1;
	}

	function goTo(i) {
		if (i < 0 || i >= total) {
			return;
		}
		index = i;
		render();
	}

	prevBtn.addEventListener('click', function () { goTo(index - 1); });
	nextBtn.addEventListener('click', function () { goTo(index + 1); });

	document.addEventListener('keydown', function (e) {
		if (e.key === 'ArrowRight') { goTo(index + 1); }
		if (e.key === 'ArrowLeft') { goTo(index - 1); }
	});

	// -- Swipe: horizontal drag past a threshold moves a slide, a mostly
	// vertical drag is left alone so scrolling inside a long slide still works.
	var touchStartX = 0;
	var touchStartY = 0;
	var touchActive = false;

	slidesWrap.addEventListener('touchstart', function (e) {
		if (e.touches.length !== 1) { return; }
		touchStartX = e.touches[0].clientX;
		touchStartY = e.touches[0].clientY;
		touchActive = true;
	}, { passive: true });

	slidesWrap.addEventListener('touchend', function (e) {
		if (!touchActive) { return; }
		touchActive = false;
		var touch = e.changedTouches[0];
		var dx = touch.clientX - touchStartX;
		var dy = touch.clientY - touchStartY;
		if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy)) { return; }
		if (dx < 0) { goTo(index + 1); } else { goTo(index - 1); }
	}, { passive: true });

	if (shareBtn) {
		shareBtn.addEventListener('click', function () {
			var shareUrl = app.getAttribute('data-share-url');
			var title = app.getAttribute('data-title');
			if (navigator.share) {
				navigator.share({ title: title, url: shareUrl }).catch(function () {});
				return;
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(shareUrl).then(function () {
					flashShareLabel();
				}, function () {});
				return;
			}
			var tmp = document.createElement('textarea');
			tmp.value = shareUrl;
			tmp.style.position = 'fixed';
			tmp.style.opacity = '0';
			document.body.appendChild(tmp);
			tmp.select();
			try { document.execCommand('copy'); } catch (e) {}
			document.body.removeChild(tmp);
			flashShareLabel();
		});
	}

	function flashShareLabel() {
		var original = shareBtn.textContent;
		shareBtn.textContent = 'Link copied';
		setTimeout(function () { shareBtn.textContent = original; }, 1500);
	}

	// -- Click-to-play for any YouTube placeholder inside a slide, same
	// markup and behaviour as the full article page (article-content.js).
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

	render();
}());
