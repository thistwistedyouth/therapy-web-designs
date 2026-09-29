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

	// -- Swipe/drag: a touch swipe, a mouse drag, or a pen all go through the
	// same Pointer Events handlers, same as a real book reader lets you drag
	// a page with the mouse on desktop, not just swipe on a phone. The
	// active card follows the pointer horizontally while dragging, for the
	// same "turning a page" feel, and springs back if released short of the
	// threshold. A mostly vertical drag is left alone so scrolling inside a
	// long slide (and text selection, clicking a link) still works normally.
	var dragStartX = 0;
	var dragStartY = 0;
	var dragActive = false;
	var dragIsHorizontal = null;
	var DRAG_THRESHOLD = 60;

	function activeCard() {
		var slide = slides[index];
		return slide ? slide.querySelector('.twd-sb-card') : null;
	}

	slidesWrap.addEventListener('pointerdown', function (e) {
		if (e.pointerType === 'mouse' && e.button !== 0) { return; }
		dragStartX = e.clientX;
		dragStartY = e.clientY;
		dragActive = true;
		dragIsHorizontal = null;
		app.classList.add('is-dragging');
		try { slidesWrap.setPointerCapture(e.pointerId); } catch (err) {}
	});

	slidesWrap.addEventListener('pointermove', function (e) {
		if (!dragActive) { return; }
		var dx = e.clientX - dragStartX;
		var dy = e.clientY - dragStartY;

		if (null === dragIsHorizontal && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
			dragIsHorizontal = Math.abs(dx) > Math.abs(dy);
		}
		if (false === dragIsHorizontal) { return; }

		var card = activeCard();
		if (card) {
			var eased = dx * 0.6;
			card.style.transform = 'translateX(' + eased + 'px)';
		}
	});

	function endDrag(e) {
		if (!dragActive) { return; }
		dragActive = false;
		app.classList.remove('is-dragging');

		var card = activeCard();
		var dx = e.clientX - dragStartX;
		var crossedThreshold = dragIsHorizontal && Math.abs(dx) >= DRAG_THRESHOLD;

		if (card) {
			if (!crossedThreshold) {
				card.style.transition = 'transform 0.2s ease';
				card.style.transform = '';
				setTimeout(function () { card.style.transition = ''; }, 220);
			} else {
				card.style.transform = '';
			}
		}

		if (!crossedThreshold) { return; }
		if (dx < 0) { goTo(index + 1); } else { goTo(index - 1); }
	}

	slidesWrap.addEventListener('pointerup', endDrag);
	slidesWrap.addEventListener('pointercancel', endDrag);
	slidesWrap.addEventListener('pointerleave', function (e) {
		if (dragActive && e.pointerType === 'mouse') { endDrag(e); }
	});

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
