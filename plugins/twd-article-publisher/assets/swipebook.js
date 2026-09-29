(function () {
	'use strict';

	/**
	 * The one place the swipe book's DOM gets built, used both by the
	 * standalone page (window.TWD_AP_SB_DATA, embedded server-side) and the
	 * in-page overlay opened over the resources/article page
	 * (swipebook-inline.js, fetched via REST) -- one template, so the two
	 * never drift apart.
	 */
	function buildMarkup(data) {
		var slidesHtml = data.slides.map(function (slide, i) {
			var active = 0 === i ? ' is-active' : '';
			var body;
			if ('bio' === slide.type) {
				body = '<div class="twd-sb-bio">' +
					(slide.photo ? '<img class="twd-sb-bio-photo" src="' + escapeAttr(slide.photo) + '" alt="">' : '') +
					'<h2 class="twd-sb-heading">' + escapeHtml(slide.heading) + '</h2>' +
					(slide.bio ? '<p class="twd-sb-bio-text">' + escapeHtml(slide.bio) + '</p>' : '') +
					(slide.link_url && slide.link_label
						? '<a class="twd-sb-bio-link" href="' + escapeAttr(slide.link_url) + '" target="_blank" rel="noopener">' + escapeHtml(slide.link_label) + '</a>'
						: '') +
					'</div>';
			} else {
				body = (slide.heading ? '<h2 class="twd-sb-heading">' + escapeHtml(slide.heading) + '</h2>' : '') +
					'<div class="twd-sb-body-text">' + slide.html + '</div>';
			}
			return '<section class="twd-sb-slide' + active + '" data-index="' + i + '"><div class="twd-sb-card">' + body + '</div></section>';
		}).join('');

		var segsHtml = data.slides.map(function (s, i) {
			return '<span class="twd-sb-seg" data-seg="' + i + '"></span>';
		}).join('');

		var mastHtml = data.logoUrl
			? '<img class="twd-sb-mast-logo" src="' + escapeAttr(data.logoUrl) + '" alt="">'
			: '<span class="twd-sb-mast-text">' + escapeHtml(data.siteName || '') + '</span>';

		return '<div class="twd-sb-backdrop" data-article-url="' + escapeAttr(data.articleUrl) + '" data-share-url="' + escapeAttr(data.shareUrl) + '" data-title="' + escapeAttr(data.title) + '">' +
			'<div class="twd-sb-frame">' +
				'<button type="button" class="twd-sb-close" aria-label="Close">&times;</button>' +
				'<div class="twd-sb-progress">' + segsHtml + '</div>' +
				'<div class="twd-sb-mast">' + mastHtml + '</div>' +
				'<div class="twd-sb-slides">' + slidesHtml + '</div>' +
				'<div class="twd-sb-controls">' +
					'<button type="button" class="twd-sb-btn twd-sb-prev" aria-label="Previous">&#8249;</button>' +
					'<span class="twd-sb-counter"></span>' +
					'<button type="button" class="twd-sb-btn twd-sb-next" aria-label="Next">&#8250;</button>' +
				'</div>' +
				'<div class="twd-sb-footer">' +
					'<button type="button" class="twd-sb-share-btn">Share</button>' +
					'<a class="twd-sb-read-link" href="' + escapeAttr(data.articleUrl) + '">Read the full article</a>' +
				'</div>' +
			'</div>' +
		'</div>';
	}

	/**
	 * Wires navigation on an already-in-DOM backdrop element (built either
	 * by the PHP-embedded data via buildMarkup(), or already present from
	 * some other source). Returns {close}.
	 */
	function mount(backdrop, opts) {
		opts = opts || {};
		var slidesWrap = backdrop.querySelector('.twd-sb-slides');
		var slides = Array.prototype.slice.call(slidesWrap.querySelectorAll('.twd-sb-slide'));
		var segs = Array.prototype.slice.call(backdrop.querySelectorAll('.twd-sb-seg'));
		var prevBtn = backdrop.querySelector('.twd-sb-prev');
		var nextBtn = backdrop.querySelector('.twd-sb-next');
		var counterEl = backdrop.querySelector('.twd-sb-counter');
		var shareBtn = backdrop.querySelector('.twd-sb-share-btn');
		var closeBtn = backdrop.querySelector('.twd-sb-close');

		var total = slides.length;
		var index = 0;
		var closed = false;

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
			if (i < 0 || i >= total) { return; }
			index = i;
			render();
		}

		prevBtn.addEventListener('click', function () { goTo(index - 1); });
		nextBtn.addEventListener('click', function () { goTo(index + 1); });

		function onKeydown(e) {
			if (e.key === 'ArrowRight') { goTo(index + 1); }
			if (e.key === 'ArrowLeft') { goTo(index - 1); }
			if (e.key === 'Escape') { close(); }
		}
		document.addEventListener('keydown', onKeydown);

		// -- Swipe/drag: a touch swipe, a mouse drag, or a pen all go through
		// the same Pointer Events handlers, so it can be dragged with a mouse
		// on desktop too, not just swiped on a touchscreen. The active card
		// follows the pointer horizontally while dragging and springs back
		// if released short of the threshold. A mostly vertical drag is left
		// alone so scrolling inside a long slide, and text selection, still
		// work normally.
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
			backdrop.classList.add('is-dragging');
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
				card.style.transform = 'translateX(' + (dx * 0.6) + 'px)';
			}
		});

		function endDrag(e) {
			if (!dragActive) { return; }
			dragActive = false;
			backdrop.classList.remove('is-dragging');

			var card = activeCard();
			var dx = e.clientX - dragStartX;
			var crossedThreshold = dragIsHorizontal && Math.abs(dx) >= DRAG_THRESHOLD;

			if (card) {
				if (!crossedThreshold) {
					card.style.transition = 'transform 0.25s cubic-bezier(0.22, 0.61, 0.36, 1)';
					card.style.transform = '';
					setTimeout(function () { card.style.transition = ''; }, 260);
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

		function flashShareLabel() {
			var original = shareBtn.textContent;
			shareBtn.textContent = 'Link copied';
			setTimeout(function () { shareBtn.textContent = original; }, 1500);
		}

		if (shareBtn) {
			shareBtn.addEventListener('click', function () {
				var shareUrl = backdrop.getAttribute('data-share-url');
				var title = backdrop.getAttribute('data-title');
				if (navigator.share) {
					navigator.share({ title: title, url: shareUrl }).catch(function () {});
					return;
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(shareUrl).then(flashShareLabel, function () {});
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

		// -- Click-to-play for any YouTube placeholder inside a slide, same
		// markup and behaviour as the full article page (article-content.js).
		function onClick(e) {
			var embed = e.target.closest('.twd-ap-yt-embed[data-youtube-id]');
			if (embed && !embed.classList.contains('twd-ap-yt-embed-active')) {
				var videoId = embed.getAttribute('data-youtube-id');
				embed.classList.add('twd-ap-yt-embed-active');
				embed.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1" ' +
					'title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
				return;
			}
			if (e.target === backdrop) { close(); }
		}
		backdrop.addEventListener('click', onClick);

		function close() {
			if (closed) { return; }
			closed = true;
			document.removeEventListener('keydown', onKeydown);
			if (opts.onClose) {
				opts.onClose();
			} else {
				window.location.href = backdrop.getAttribute('data-article-url');
			}
		}
		closeBtn.addEventListener('click', close);

		render();
		return { close: close };
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function escapeAttr(str) {
		return escapeHtml(str).replace(/"/g, '&quot;');
	}

	var current = null;

	window.TWD_AP_SwipeBook = {
		/**
		 * Opens the overlay over whatever page is currently showing, given
		 * the same data shape the standalone page embeds. Locks background
		 * scroll and removes itself entirely on close.
		 */
		open: function (data) {
			if (current) { return; }
			var wrap = document.createElement('div');
			wrap.innerHTML = buildMarkup(data);
			var backdrop = wrap.firstElementChild;
			document.body.appendChild(backdrop);
			document.documentElement.style.overflow = 'hidden';
			document.body.style.overflow = 'hidden';
			current = mount(backdrop, {
				onClose: function () {
					document.documentElement.style.overflow = '';
					document.body.style.overflow = '';
					backdrop.parentNode.removeChild(backdrop);
					current = null;
				},
			});
		},
	};

	document.addEventListener('DOMContentLoaded', function () {
		if (!window.TWD_AP_SB_DATA) { return; }
		var wrap = document.createElement('div');
		wrap.innerHTML = buildMarkup(window.TWD_AP_SB_DATA);
		var backdrop = wrap.firstElementChild;
		document.body.appendChild(backdrop);
		mount(backdrop, {});
	});
}());
