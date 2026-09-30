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
		// Built once, then prepended into every card below -- the header
		// (logo, site heading, "Swipe Book" label) is part of each card's
		// own single element now, not a separate fixed piece above it, so it
		// slides with the card during a swipe and shares one continuous
		// background/border with it instead of two boxes visibly seamed
		// together. A toggle to exclude it from Save as image/PDF (see
		// captureNode() below) hides this one child node during that
		// specific capture rather than needing a second markup path.
		var mastHtml = data.logoUrl
			? '<img class="twd-sb-mast-logo" src="' + escapeAttr(data.logoUrl) + '" alt="">'
			: '<span class="twd-sb-mast-text">' + escapeHtml(data.siteName || '') + '</span>';
		var siteUrl = data.siteUrl || '';
		var mastOpen = siteUrl ? '<a class="twd-sb-mast-link" href="' + escapeAttr(siteUrl) + '" target="_blank" rel="noopener">' : '';
		var mastClose = siteUrl ? '</a>' : '';
		var headingText = data.siteHeading || data.siteName || '';
		var headingOpen = siteUrl ? '<a class="twd-sb-sitename-link" href="' + escapeAttr(siteUrl) + '" target="_blank" rel="noopener">' : '';
		var headingClose = siteUrl ? '</a>' : '';
		var headingHtml = headingText
			? '<div class="twd-sb-sitename">' + headingOpen + escapeHtml(headingText) + headingClose + '</div>'
			: '';
		var logoBgClass = 'twd-sb-mast-bg-' + (data.logoBg || 'light');
		var cardHeaderHtml = '<div class="twd-sb-card-header">' +
			'<div class="twd-sb-mast ' + logoBgClass + '">' + mastOpen + mastHtml + mastClose + '</div>' +
			'<div class="twd-sb-header-text">' +
				headingHtml +
				'<div class="twd-sb-title">' + escapeHtml(data.label || 'Swipe Book') + '</div>' +
			'</div>' +
		'</div>';

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
			} else if ('related' === slide.type) {
				// Plain <a> tags, not click handlers -- swipebook-inline.js's
				// existing delegated click listener (the same one the main
				// "View as a swipe book" button uses) opens these in the overlay
				// when it's loaded, closing the current book first. On the
				// standalone page, where that script never loads, they just
				// navigate to the linked article's own swipe book normally.
				body = (slide.heading ? '<h2 class="twd-sb-heading">' + escapeHtml(slide.heading) + '</h2>' : '') +
					'<div class="twd-sb-related-list">' +
					(slide.items || []).map(function (item) {
						return '<a class="twd-sb-related-item" href="' + escapeAttr(item.shareUrl) + '" data-twd-ap-post-id="' + item.id + '">' +
							'<span class="twd-sb-related-title">' + escapeHtml(item.title) + '</span>' +
							'<span class="twd-sb-related-arrow" aria-hidden="true">&#8250;</span>' +
						'</a>';
					}).join('') +
					'</div>';
			} else if ('quote' === slide.type) {
				body = '<div class="twd-sb-quote">' +
					(slide.heading ? '<h2 class="twd-sb-heading">' + escapeHtml(slide.heading) + '</h2>' : '') +
					'<blockquote class="twd-sb-quote-text">' + slide.html + '</blockquote>' +
					(slide.attribution ? '<div class="twd-sb-quote-attribution">' + escapeHtml(slide.attribution) + '</div>' : '') +
					'</div>';
			} else if ('question' === slide.type) {
				body = '<div class="twd-sb-question">' +
					(slide.heading ? '<h2 class="twd-sb-heading">' + escapeHtml(slide.heading) + '</h2>' : '') +
					'<div class="twd-sb-question-text">' + slide.html + '</div>' +
					'</div>';
			} else {
				body = (slide.heading ? '<h2 class="twd-sb-heading">' + escapeHtml(slide.heading) + '</h2>' : '') +
					'<div class="twd-sb-body-text">' + slide.html + '</div>';
			}
			return '<section class="twd-sb-slide' + active + '" data-index="' + i + '"><div class="twd-sb-card">' +
				cardHeaderHtml +
				'<div class="twd-sb-card-body">' + body + '</div>' +
			'</div></section>';
		}).join('');

		var segsHtml = data.slides.map(function (s, i) {
			return '<span class="twd-sb-seg" data-seg="' + i + '"></span>';
		}).join('');

		// The cross-link to the article's other book (swipe book <-> Summary
		// Book), only present when one's actually been published -- opened
		// the same close-current/open-other way as a "More Articles" item,
		// via data-twd-ap-post-id + data-twd-ap-book-variant and
		// swipebook-inline.js's delegated click handler.
		var companionHtml = data.companionUrl
			? '<a class="twd-sb-companion-link" href="' + escapeAttr(data.companionUrl) + '" data-twd-ap-post-id="' + data.companionPostId + '" data-twd-ap-book-variant="' + escapeAttr(data.companionBookVariant || '') + '">' + escapeHtml(data.companionLabel || '') + '</a>'
			: '';

		return '<div class="twd-sb-backdrop" data-article-url="' + escapeAttr(data.articleUrl) + '" data-share-url="' + escapeAttr(data.shareUrl) + '" data-title="' + escapeAttr(data.title) + '" data-include-branding="' + (false === data.includeBranding ? '0' : '1') + '">' +
			'<div class="twd-sb-frame">' +
				'<button type="button" class="twd-sb-close" aria-label="Close">&times;</button>' +
				'<div class="twd-sb-slides">' + slidesHtml + '</div>' +
				'<div class="twd-sb-controls">' +
					'<button type="button" class="twd-sb-btn twd-sb-prev" aria-label="Previous">&#8249;</button>' +
					'<span class="twd-sb-counter"></span>' +
					'<button type="button" class="twd-sb-btn twd-sb-next" aria-label="Next">&#8250;</button>' +
				'</div>' +
				'<div class="twd-sb-progress">' + segsHtml + '</div>' +
				'<div class="twd-sb-footer">' +
					'<button type="button" class="twd-sb-share-btn">Share</button>' +
					'<button type="button" class="twd-sb-save-btn">Save as image</button>' +
					'<button type="button" class="twd-sb-pdf-btn">Download as PDF</button>' +
					'<a class="twd-sb-read-link" href="' + escapeAttr(data.articleUrl) + '">Read the full article</a>' +
					companionHtml +
				'</div>' +
			'</div>' +
		'</div>';
	}

	var html2canvasLoading = false;
	var html2canvasQueue = [];
	function loadHtml2Canvas(onReady) {
		if (window.html2canvas) { onReady(); return; }
		html2canvasQueue.push(onReady);
		if (html2canvasLoading) { return; }
		html2canvasLoading = true;
		var s = document.createElement('script');
		s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
		s.onload = function () {
			html2canvasQueue.forEach(function (cb) { cb(); });
			html2canvasQueue = [];
		};
		document.head.appendChild(s);
	}

	var jsPdfLoading = false;
	var jsPdfQueue = [];
	function loadJsPDF(onReady) {
		if (window.jspdf && window.jspdf.jsPDF) { onReady(); return; }
		jsPdfQueue.push(onReady);
		if (jsPdfLoading) { return; }
		jsPdfLoading = true;
		var s = document.createElement('script');
		s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
		s.onload = function () {
			jsPdfQueue.forEach(function (cb) { cb(); });
			jsPdfQueue = [];
		};
		document.head.appendChild(s);
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
		var saveBtn = backdrop.querySelector('.twd-sb-save-btn');
		var pdfBtn = backdrop.querySelector('.twd-sb-pdf-btn');
		var closeBtn = backdrop.querySelector('.twd-sb-close');

		var total = slides.length;
		var index = 0;
		var closed = false;

		// Each slide parks off to whichever side it isn't showing on (is-past
		// to the left, is-future to the right) and the active one sits at
		// translateX(0) -- a plain state change, so moving to any index
		// (next/prev, a swipe, or jumping via the progress bar) always reads
		// as the current page sliding fully off and the next one sliding
		// fully on, matching Therapy Resource Directory's own book reader.
		function render() {
			slides.forEach(function (slide, i) {
				slide.classList.toggle('is-active', i === index);
				slide.classList.toggle('is-past', i < index);
				slide.classList.toggle('is-future', i > index);
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
		// on desktop too, not just swiped on a touchscreen. Only the gesture's
		// direction is read, on release, same as TRD's own reader -- no
		// element follows the pointer mid-drag, which is what let the old
		// per-drag inline transform fight the slide's own CSS transition and
		// read as jerky. A mostly vertical drag is left alone so scrolling
		// inside a long slide, and text selection, still work normally.
		var dragStartX = 0;
		var dragStartY = 0;
		var dragActive = false;
		var dragIsHorizontal = null;
		var DRAG_THRESHOLD = 50;

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
			if (!dragActive || null !== dragIsHorizontal) { return; }
			var dx = e.clientX - dragStartX;
			var dy = e.clientY - dragStartY;
			if (Math.abs(dx) > 8 || Math.abs(dy) > 8) {
				dragIsHorizontal = Math.abs(dx) > Math.abs(dy);
			}
		});

		function endDrag(e) {
			if (!dragActive) { return; }
			dragActive = false;
			backdrop.classList.remove('is-dragging');

			var dx = e.clientX - dragStartX;
			if (!dragIsHorizontal || Math.abs(dx) < DRAG_THRESHOLD) { return; }
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

		// -- Save as image captures the currently active card exactly as it
		// renders on screen (html2canvas). Download as PDF captures every
		// slide the same way, one at a time, into a single multi-page PDF --
		// both libraries are only fetched the first time either button is
		// used, same lazy-load pattern as the pledge postcard's own
		// save-as-image in 04 Page Shell.php.
		var includeBranding = '0' !== backdrop.getAttribute('data-include-branding');

		// The header (logo, site heading, "Swipe Book" label) is already
		// part of each card's own markup (.twd-sb-card-header, the first
		// child), so capturing the card captures it too, no composition
		// needed. When branding is toggled off, that one child is hidden for
		// just the moment of capture and restored right after -- the same
		// "briefly change the live DOM for an export" approach
		// captureAllCards() already uses to step through slides.
		function captureNode(cardEl, onCanvas) {
			var headerEl = includeBranding ? null : cardEl.querySelector('.twd-sb-card-header');
			if (headerEl) { headerEl.style.display = 'none'; }
			window.html2canvas(cardEl, { backgroundColor: '#13112e', scale: 2, useCORS: true }).then(function (canvas) {
				if (headerEl) { headerEl.style.display = ''; }
				onCanvas(canvas);
			});
		}

		function captureActiveCard(onCanvas) {
			loadHtml2Canvas(function () {
				var card = backdrop.querySelector('.twd-sb-slide.is-active .twd-sb-card');
				if (!card || !window.html2canvas) { return; }
				captureNode(card, onCanvas);
			});
		}

		// Steps every slide to is-active in turn (each one has to actually be
		// the on-screen slide, not just off to the side under a transform,
		// since .twd-sb-slides clips anything not at translateX(0)), snapshots
		// its card, then restores whichever slide the reader was actually on.
		// twd-sb-exporting turns off the slide transition for this so each
		// step is instant, not a visible slide animation the reader didn't ask
		// for.
		function captureAllCards(onDone, onProgress) {
			loadHtml2Canvas(function () {
				if (!window.html2canvas) { onDone([]); return; }
				var originalIndex = index;
				backdrop.classList.add('twd-sb-exporting');
				var canvases = [];
				var i = 0;
				function step() {
					if (i >= total) {
						backdrop.classList.remove('twd-sb-exporting');
						goTo(originalIndex);
						onDone(canvases);
						return;
					}
					slides.forEach(function (slide, si) {
						slide.classList.toggle('is-active', si === i);
						slide.classList.toggle('is-past', si < i);
						slide.classList.toggle('is-future', si > i);
					});
					if (onProgress) { onProgress(i + 1, total); }
					requestAnimationFrame(function () {
						captureNode(slides[i].querySelector('.twd-sb-card'), function (canvas) {
							canvases.push(canvas);
							i++;
							step();
						});
					});
				}
				step();
			});
		}

		function exportFilename(ext, pageNum) {
			var title = backdrop.getAttribute('data-title') || 'summary-book';
			var slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'summary-book';
			return slug + (pageNum ? '-page-' + pageNum : '') + '.' + ext;
		}

		if (saveBtn) {
			saveBtn.addEventListener('click', function () {
				var original = saveBtn.textContent;
				saveBtn.textContent = 'Saving…';
				saveBtn.disabled = true;
				captureActiveCard(function (canvas) {
					var link = document.createElement('a');
					link.download = exportFilename('png', index + 1);
					link.href = canvas.toDataURL('image/png');
					link.click();
					saveBtn.textContent = original;
					saveBtn.disabled = false;
				});
			});
		}

		if (pdfBtn) {
			pdfBtn.addEventListener('click', function () {
				var original = pdfBtn.textContent;
				pdfBtn.disabled = true;
				captureAllCards(function (canvases) {
					if (!canvases.length) {
						pdfBtn.textContent = original;
						pdfBtn.disabled = false;
						return;
					}
					pdfBtn.textContent = 'Building PDF…';
					loadJsPDF(function () {
						if (!window.jspdf || !window.jspdf.jsPDF) {
							pdfBtn.textContent = original;
							pdfBtn.disabled = false;
							return;
						}
						var JsPdfCtor = window.jspdf.jsPDF;
						var pdf = null;
						canvases.forEach(function (canvas, i) {
							var orientation = canvas.width >= canvas.height ? 'landscape' : 'portrait';
							if (0 === i) {
								pdf = new JsPdfCtor({ orientation: orientation, unit: 'px', format: [canvas.width, canvas.height] });
							} else {
								pdf.addPage([canvas.width, canvas.height], orientation);
							}
							pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, canvas.width, canvas.height);
						});
						pdf.save(exportFilename('pdf'));
						pdfBtn.textContent = original;
						pdfBtn.disabled = false;
					});
				}, function (done, totalSlides) {
					pdfBtn.textContent = 'Page ' + done + '/' + totalSlides + '…';
				});
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
		/**
		 * Closes the currently-open overlay, if any, synchronously -- used
		 * before opening a related article's book from inside an already-open
		 * one, since open() otherwise no-ops while current is still set.
		 */
		close: function () {
			if (current) { current.close(); }
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
