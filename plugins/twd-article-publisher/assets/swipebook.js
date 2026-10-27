(function () {
	'use strict';

	/**
	 * The one place the swipe book's DOM gets built, used both by the
	 * standalone page (window.TWD_AP_SB_DATA, embedded server-side) and the
	 * in-page overlay opened over the resources/article page
	 * (swipebook-inline.js, fetched via REST) -- one template, so the two
	 * never drift apart.
	 */
	/**
	 * The header (logo, site heading, "Swipe Book" label) that goes inside
	 * every card. Built once per deck.
	 */
	function cardHeaderHtml(data) {
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
		var html = '<div class="twd-sb-card-header">' +
			'<div class="twd-sb-mast ' + logoBgClass + '">' + mastOpen + mastHtml + mastClose + '</div>' +
			'<div class="twd-sb-header-text">' +
				headingHtml +
				'<div class="twd-sb-title">' + escapeHtml(data.label || 'Swipe Book') + '</div>' +
			'</div>' +
		'</div>';
		return html;
	}

	/**
	 * The inside of one card (everything under the header) for one slide.
	 * Shared by buildMarkup() and the fit step, so a page that gets split
	 * is drawn exactly the way the original would have been.
	 */
	function slideBodyHtml(slide) {
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
		return body;
	}

	/** One slide's section. src is the source slide it came from, scale the text size multiplier the fit step chose. */
	function sectionHtml(headerHtml, body, i, src, scale) {
		var style = scale && scale < 0.999 ? ' style="--twd-sb-s:' + scale.toFixed(3) + '"' : '';
		return '<section class="twd-sb-slide' + (0 === i ? ' is-active' : '') + '" data-index="' + i + '" data-src="' + src + '"><div class="twd-sb-card">' +
			headerHtml +
			'<div class="twd-sb-card-body"' + style + '>' + body + '</div>' +
		'</div></section>';
	}

	function segsMarkup(n) {
		var out = '';
		for (var i = 0; i < n; i++) { out += '<span class="twd-sb-seg" data-seg="' + i + '"></span>'; }
		return out;
	}

	function buildMarkup(data) {
		var cardHeader = cardHeaderHtml(data);
		var slidesHtml = data.slides.map(function (slide, i) {
			return sectionHtml(cardHeader, slideBodyHtml(slide), i, i, 1);
		}).join('');
		var segsHtml = segsMarkup(data.slides.length);

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

	// -- Fit: every page fits the screen without scrolling (1.43.0) -----------
	//
	// The server splits an article into slides by length, but it cannot know
	// how tall the reader's screen is. So after the book is on screen the
	// pages are measured, in a hidden copy of the card, and any page that
	// would scroll is fixed in two steps: the text shrinks a little (never
	// below FIT_FLOOR_PX), and if it still does not fit the page is split at
	// a paragraph break (or, inside a long paragraph, a sentence) into more
	// pages. The data the server sent is never changed; the fitted pages are
	// worked out again whenever the screen changes size.
	var FIT_FLOOR_PX = 15; // the smallest reading size the text is shrunk to
	var FIT_STEP = 0.04;   // shrink in steps of 4%
	var FIT_SLACK = 12;    // pixels kept free under the text, so a browser toolbar or a slightly different font does not tip a page over
	var SPLITTABLE = { text: 1, quote: 1, question: 1, related: 1 };

	function nodeHtml(n) {
		return 3 === n.nodeType ? escapeHtml(n.nodeValue) : (1 === n.nodeType ? n.outerHTML : '');
	}

	function isBlankText(n) {
		return 3 === n.nodeType && '' === n.nodeValue.replace(/\s+/g, '');
	}

	// A <p> holding blank lines (a Summary Book card keeps its paragraphs as
	// <br><br> inside one <p>) becomes one <p> per paragraph. A single <br>
	// stays where it is.
	function splitAtDoubleBreaks(p) {
		var groups = [[]];
		var held = [];
		var brs = 0;
		Array.prototype.forEach.call(p.childNodes, function (n) {
			var isBr = 1 === n.nodeType && 'BR' === n.tagName;
			if (isBr) { held.push(n); brs++; return; }
			if (isBlankText(n) && brs) { held.push(n); return; }
			if (brs >= 2) {
				groups.push([]);
			} else {
				held.forEach(function (h) { groups[groups.length - 1].push(h); });
			}
			held = [];
			brs = 0;
			groups[groups.length - 1].push(n);
		});
		return groups.filter(function (g) {
			return g.some(function (n) { return 1 === n.nodeType || !isBlankText(n); });
		}).map(function (g) {
			return '<p>' + g.map(nodeHtml).join('') + '</p>';
		});
	}

	// The first-level pieces of a slide's html: each block (paragraph, list,
	// quote, heading, picture) is one piece that is never cut in the middle
	// unless it will not fit a page on its own.
	function blockUnits(html) {
		var box = document.createElement('div');
		box.innerHTML = html || '';
		var out = [];
		Array.prototype.forEach.call(box.childNodes, function (n) {
			if (3 === n.nodeType) {
				if (!isBlankText(n)) { out.push('<p>' + escapeHtml(n.nodeValue.trim()) + '</p>'); }
				return;
			}
			if (1 !== n.nodeType) { return; }
			if ('P' === n.tagName) {
				splitAtDoubleBreaks(n).forEach(function (h) { out.push(h); });
				return;
			}
			out.push(n.outerHTML);
		});
		return out;
	}

	var unitGroup = 0;
	function textLength(html) {
		var d = document.createElement('div');
		d.innerHTML = html;
		return (d.textContent || '').length;
	}

	// Inline wrappers a whole paragraph can sit inside: a bold, italic or
	// linked paragraph (a pull quote, say). Cut through, not treated as one piece.
	var INLINE_WRAP = { EM: 1, STRONG: 1, I: 1, B: 1, A: 1, SPAN: 1, U: 1, MARK: 1, SMALL: 1, S: 1, DEL: 1, INS: 1, CITE: 1, Q: 1 };
	// A sentence with its closing quote or bracket and the space after it; a run
	// of stops at the very start (the full stop after a bold word) is a piece of its own.
	var SENTENCE_PARTS = /[.!?…]+["'”’)\]]*\s*|[^.!?…]+[.!?…]*["'”’)\]]*\s*/g;
	var SENTENCE_END = /[.!?…]["'”’)\]]*\s*$/;

	// The sentences of an inline run, as html: a sentence that holds a link or a
	// bold word stays whole, and nothing in the text is ever lost.
	function inlineAtoms(host) {
		var kids = Array.prototype.filter.call(host.childNodes, function (n) { return !isBlankText(n); });
		if (1 === kids.length && 1 === kids[0].nodeType && INLINE_WRAP[kids[0].tagName]) {
			var shell = kids[0];
			return inlineAtoms(shell).map(function (a) {
				var s = shell.cloneNode(false);
				s.innerHTML = a;
				return s.outerHTML;
			});
		}
		var atoms = [];
		var cur = '';
		function close() { if (cur) { atoms.push(cur); cur = ''; } }
		Array.prototype.forEach.call(host.childNodes, function (n) {
			if (3 === n.nodeType) {
				var parts = n.nodeValue.match(SENTENCE_PARTS);
				if (!parts || parts.join('') !== n.nodeValue) { parts = [n.nodeValue]; }
				parts.forEach(function (t) {
					if (!t) { return; }
					cur += escapeHtml(t);
					if (SENTENCE_END.test(t)) { close(); }
				});
				return;
			}
			cur += nodeHtml(n);
			if (1 === n.nodeType && SENTENCE_END.test(n.textContent || '')) { close(); }
		});
		if (cur) {
			if (atoms.length && '' === cur.replace(/\s+/g, '')) { atoms[atoms.length - 1] += cur; } else { close(); }
		}
		return atoms;
	}

	function isHeadingUnit(u) {
		return !(u.wraps && u.wraps.length) && /^<h[1-6][\s>]/i.test(u.html);
	}

	// A block that is too big for a page by itself, as smaller pieces that join
	// back up into one block when they share a page: a paragraph as its
	// sentences, a list as its items, a quote as its paragraphs. Each piece
	// carries the wrappers it came out of (wraps, outermost first). Anything
	// else cannot be cut.
	function subUnits(unit) {
		if (unit.atom) { return null; }
		var box = document.createElement('div');
		box.innerHTML = unit.html;
		var el = box.firstElementChild;
		if (!el) { return null; }
		var base = unit.wraps || [];
		var group = ++unitGroup;
		function make(html, wraps, atom) { return { html: html, wraps: wraps, atom: atom, len: textLength(html) }; }
		function sentences(host, wraps) {
			var atoms = inlineAtoms(host);
			return atoms.length < 2 ? null : atoms.map(function (a) { return make(a, wraps, true); });
		}
		if ('P' === el.tagName) {
			return sentences(el, base.concat([{ tag: 'p', group: group }]));
		}
		if ('UL' === el.tagName || 'OL' === el.tagName) {
			var tag = el.tagName.toLowerCase();
			var items = Array.prototype.filter.call(el.children, function (c) { return 'LI' === c.tagName; });
			if (1 === items.length) {
				return sentences(items[0], base.concat([{ tag: tag, group: group, start: 1 }, { tag: 'li', group: ++unitGroup }]));
			}
			if (items.length < 2) { return null; }
			return items.map(function (li, i) { return make(li.outerHTML, base.concat([{ tag: tag, group: group, start: i + 1 }]), true); });
		}
		if ('BLOCKQUOTE' === el.tagName) {
			var parts = blockUnits(el.innerHTML);
			var wraps = base.concat([{ tag: 'blockquote', group: group }]);
			if (!parts.length) { return null; }
			if (1 === parts.length) { return subUnits({ html: parts[0], wraps: wraps }); }
			return parts.map(function (h) { return make(h, wraps, false); });
		}
		return null;
	}

	function sameWrap(a, b) { return !!a && !!b && a.tag === b.tag && a.group === b.group; }

	// Units back into html, joining neighbours that came out of one block.
	function unitsHtml(units, depth) {
		depth = depth || 0;
		var out = '';
		var i = 0;
		while (i < units.length) {
			var w = units[i].wraps && units[i].wraps[depth];
			if (!w) { out += units[i].html; i++; continue; }
			var j = i;
			while (j < units.length && sameWrap(units[j].wraps && units[j].wraps[depth], w)) { j++; }
			var inner = unitsHtml(units.slice(i, j), depth + 1);
			out += 'ol' === w.tag ? '<ol start="' + w.start + '">' + inner + '</ol>' : '<' + w.tag + '>' + inner + '</' + w.tag + '>';
			i = j;
		}
		return out;
	}

	function copySlide(slide, over) {
		var c = {};
		for (var k in slide) { if (Object.prototype.hasOwnProperty.call(slide, k)) { c[k] = slide[k]; } }
		for (var o in over) { if (Object.prototype.hasOwnProperty.call(over, o)) { c[o] = over[o]; } }
		return c;
	}

	// A hidden copy of a card, the same size as the real ones, to measure in.
	function makeFitter(backdrop, headerHtml) {
		var wrap = backdrop.querySelector('.twd-sb-slides');
		var probe = document.createElement('section');
		probe.className = 'twd-sb-slide twd-sb-probe';
		probe.setAttribute('aria-hidden', 'true');
		probe.innerHTML = '<div class="twd-sb-card">' + headerHtml + '<div class="twd-sb-card-body"></div></div>';
		wrap.appendChild(probe);
		var body = probe.querySelector('.twd-sb-card-body');
		body.style.maxHeight = Math.max(60, body.clientHeight - FIT_SLACK) + 'px';

		function overflows() { return body.scrollHeight > body.clientHeight + 1; }
		var fitter = {
			show: function (slide, scale) {
				body.innerHTML = slideBodyHtml(slide);
				body.style.setProperty('--twd-sb-s', String(scale || 1));
			},
			fits: function (slide, scale) { fitter.show(slide, scale); return !overflows(); },
			// The reading size as a fraction of full size that keeps text at or above the floor.
			minScale: function (slide) {
				fitter.show(slide, 1);
				var el = body.querySelector('.twd-sb-body-text, .twd-sb-quote-text, .twd-sb-question-text, .twd-sb-bio-text, .twd-sb-related-title');
				var px = el ? parseFloat(window.getComputedStyle(el).fontSize) : 0;
				return px ? Math.max(0.6, Math.min(1, FIT_FLOOR_PX / px)) : 0.8;
			},
			// The largest size, from full size down to min, at which the slide fits; 0 if none.
			bestScale: function (slide, min) {
				for (var s = 1; s > min + 0.001; s -= FIT_STEP) {
					if (fitter.fits(slide, s)) { return s; }
				}
				return fitter.fits(slide, min) ? min : 0;
			},
			destroy: function () { if (probe.parentNode) { probe.parentNode.removeChild(probe); } },
		};
		return fitter;
	}

	// Cuts a list of units into pages, each of which fits at the smallest
	// size, then (when there is more than one page) tries again to share the
	// text out evenly so the last page is not a stray sentence.
	function paginate(fitter, units, makePage, min) {
		function fitsRange(a, b, first) { return fitter.fits(makePage(units.slice(a, b), first, b >= units.length), min); }

		var pages = [];
		var i = 0;
		while (i < units.length) {
			var j = i;
			while (j < units.length && fitsRange(i, j + 1, 0 === pages.length)) { j++; }
			if (j === i) {
				var sub = subUnits(units[i]);
				if (sub) { Array.prototype.splice.apply(units, [i, 1].concat(sub)); continue; }
				j = i + 1; // cannot be cut any smaller: it keeps its own page and may scroll
			}
			// A heading never ends a page when its text is on the next one.
			while (j - i > 1 && j < units.length && isHeadingUnit(units[j - 1])) { j--; }
			pages.push([i, j]);
			i = j;
		}

		if (pages.length > 1) {
			var total = 0;
			units.forEach(function (u) { total += u.len || 0; });
			var even = [];
			var from = 0;
			var left = total;
			var ok = true;
			for (var p = 0; p < pages.length && ok; p++) {
				var remainingPages = pages.length - p;
				var to = from;
				if (1 === remainingPages) {
					to = units.length;
				} else {
					var target = left / remainingPages;
					var got = 0;
					while (to < units.length) {
						var len = units[to].len || 0;
						if (to > from && got + len / 2 > target) { break; }
						if (!fitsRange(from, to + 1, 0 === p)) { break; }
						got += len;
						to++;
					}
				}
				while (to - from > 1 && to < units.length && isHeadingUnit(units[to - 1])) { to--; }
				if (to === from || (1 === remainingPages && !fitsRange(from, to, 0 === p))) { ok = false; break; }
				even.push([from, to]);
				for (var q = from; q < to; q++) { left -= units[q].len || 0; }
				from = to;
			}
			if (ok && from === units.length && even.length === pages.length) { pages = even; }
		}
		return { units: units, ranges: pages };
	}

	// How even a split is: the shortest page's text over the longest page's.
	function evenness(result) {
		var lens = result.ranges.map(function (r) {
			var n = 0;
			for (var k = r[0]; k < r[1]; k++) { n += result.units[k].len || 0; }
			return n;
		});
		return Math.min.apply(null, lens) / Math.max(1, Math.max.apply(null, lens));
	}

	// One source slide as one or more fitted pages: [{ slide, scale }].
	function fitSource(fitter, slide) {
		var min = fitter.minScale(slide);
		var best = fitter.bestScale(slide, min);
		if (best) { return [{ slide: slide, scale: best }]; }
		if (!SPLITTABLE[slide.type]) { return [{ slide: slide, scale: min }]; }

		var units;
		var makePage;
		if ('related' === slide.type) {
			units = (slide.items || []).map(function (it) { return { html: '', item: it, len: 1 }; });
			makePage = function (us, first) { return copySlide(slide, { heading: first ? slide.heading : '', items: us.map(function (u) { return u.item; }) }); };
		} else {
			units = blockUnits(slide.html).map(function (h) { return { html: h, wraps: [], len: textLength(h) }; });
			makePage = function (us, first, last) {
				var over = { html: unitsHtml(us), heading: first ? slide.heading : '' };
				if ('quote' === slide.type) { over.attribution = last ? slide.attribution : ''; }
				return copySlide(slide, over);
			};
		}
		if (units.length < 2 && !(units[0] && subUnits(units[0]))) { return [{ slide: slide, scale: min }]; }

		var result = paginate(fitter, units.slice(), makePage, min);
		// A lopsided split (a big paragraph between two small ones, say) is tried
		// again with every paragraph and list cut into sentences and items, and
		// kept only if that gives fewer pages or a more even spread.
		if (result.ranges.length > 1 && evenness(result) < 0.33) {
			var finer = [];
			units.forEach(function (u) {
				var sub = subUnits(u);
				if (sub) { sub.forEach(function (x) { finer.push(x); }); } else { finer.push(u); }
			});
			var again = paginate(fitter, finer, makePage, min);
			if (again.ranges.length < result.ranges.length || (again.ranges.length === result.ranges.length && evenness(again) > evenness(result))) {
				result = again;
			}
		}
		var pages = result.ranges.map(function (r) { return makePage(result.units.slice(r[0], r[1]), r[0] === 0, r[1] >= result.units.length); });
		// All the pages of one slide share a size, the largest that suits every page.
		var scale = 1;
		pages.forEach(function (pg) {
			var b = fitter.bestScale(pg, min);
			scale = Math.min(scale, b || min);
		});
		return pages.map(function (pg) { return { slide: pg, scale: scale }; });
	}

	// The whole deck as fitted pages: [{ slide, scale, src }].
	function computePlan(backdrop, data) {
		var fitter = makeFitter(backdrop, cardHeaderHtml(data));
		var plan = [];
		try {
			data.slides.forEach(function (slide, i) {
				fitSource(fitter, slide).forEach(function (pg) { plan.push({ slide: pg.slide, scale: pg.scale, src: i }); });
			});
		} finally {
			fitter.destroy();
		}
		return plan;
	}

	function planSignature(plan) {
		return plan.map(function (p) {
			return p.src + ':' + p.scale.toFixed(2) + ':' + (p.slide.html || '').length + ':' + (p.slide.items ? p.slide.items.length : 0) + ':' + (p.slide.heading || '').length;
		}).join('|');
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

		// The fit step (see computePlan): needs the source slides, which the
		// two callers pass in as opts.data. Without them the deck is shown
		// exactly as built.
		var fitData = opts.data || null;
		var progressEl = backdrop.querySelector('.twd-sb-progress');
		var fitSig = fitData ? planSignature(fitData.slides.map(function (sl, i) { return { slide: sl, scale: 1, src: i }; })) : '';
		var lastW = 0;
		var lastH = 0;
		var resizeTimer = 0;
		var picTimer = 0;
		var seenPics = {};
		var exporting = false;   // a PDF export is stepping through the pages: do not rebuild them under it
		var refitWanted = false;

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
				// The pages as they are now; a refit waits until the export is done.
				var pages = slides.slice();
				var pageCount = pages.length;
				exporting = true;
				backdrop.classList.add('twd-sb-exporting');
				var canvases = [];
				var i = 0;
				function step() {
					if (i >= pageCount) {
						backdrop.classList.remove('twd-sb-exporting');
						exporting = false;
						goTo(originalIndex);
						onDone(canvases);
						if (refitWanted) { refitWanted = false; refit(); }
						return;
					}
					pages.forEach(function (slide, si) {
						slide.classList.toggle('is-active', si === i);
						slide.classList.toggle('is-past', si < i);
						slide.classList.toggle('is-future', si > i);
					});
					if (onProgress) { onProgress(i + 1, pageCount); }
					requestAnimationFrame(function () {
						captureNode(pages[i].querySelector('.twd-sb-card'), function (canvas) {
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
			window.removeEventListener('resize', onResize);
			clearTimeout(resizeTimer);
			clearTimeout(picTimer);
			if (opts.onClose) {
				opts.onClose();
			} else {
				window.location.href = backdrop.getAttribute('data-article-url');
			}
		}
		closeBtn.addEventListener('click', close);

		// Replaces the pages with the fitted ones. slides, segs and total are
		// the same variables the buttons, the swipe and the exports read, so
		// they all follow the new pages.
		function applyPlan(plan) {
			var header = cardHeaderHtml(fitData);
			slidesWrap.innerHTML = plan.map(function (p, i) { return sectionHtml(header, slideBodyHtml(p.slide), i, p.src, p.scale); }).join('');
			progressEl.innerHTML = segsMarkup(plan.length);
			slides = Array.prototype.slice.call(slidesWrap.querySelectorAll('.twd-sb-slide'));
			segs = Array.prototype.slice.call(backdrop.querySelectorAll('.twd-sb-seg'));
			total = slides.length;
		}

		function refit() {
			if (!fitData || closed) { return; }
			if (exporting) { refitWanted = true; return; }
			try {
				var box = slidesWrap.getBoundingClientRect();
				lastW = box.width;
				lastH = box.height;
				var plan = computePlan(backdrop, fitData);
				var sig = planSignature(plan);
				if (sig === fitSig) { return; }
				// Stay on the same piece of the book: the first fitted page of the slide being read.
				var keep = slides[index] ? parseInt(slides[index].getAttribute('data-src'), 10) : 0;
				fitSig = sig;
				applyPlan(plan);
				index = 0;
				for (var k = 0; k < slides.length; k++) {
					if (parseInt(slides[k].getAttribute('data-src'), 10) === keep) { index = k; break; }
				}
				render();
			} catch (err) {
				// A fault in the fit must never cost the reader the book: keep it as it was.
				if (window.console && window.console.warn) { window.console.warn('Swipe book: pages not fitted.', err); }
			}
		}

		// Turning a phone, resizing a window: fit again. A browser toolbar
		// sliding away changes the height by well under 100px, which is
		// ignored so the pages do not reshuffle while someone is reading.
		function onResize() {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () {
				var box = slidesWrap.getBoundingClientRect();
				if (Math.abs(box.height - lastH) > 100 || Math.abs(box.width - lastW) > 60) { refit(); }
			}, 200);
		}

		refit();
		render();
		if (fitData) {
			window.addEventListener('resize', onResize);
			// The book's own fonts arriving late change how much fits.
			if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { refit(); }); }
			// So do pictures: a page was measured before its picture had a height. Each
			// picture address fits the book again once (the hidden copy never counts).
			slidesWrap.addEventListener('load', function (e) {
				var t = e.target;
				if (!t || 'IMG' !== t.tagName || (t.closest && t.closest('.twd-sb-probe'))) { return; }
				var key = t.currentSrc || t.src;
				if (seenPics[key]) { return; }
				seenPics[key] = true;
				clearTimeout(picTimer);
				picTimer = setTimeout(refit, 120);
			}, true);
		}
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
				data: data,
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
		mount(backdrop, { data: window.TWD_AP_SB_DATA });
	});
}());
