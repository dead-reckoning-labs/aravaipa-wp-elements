/**
 * Search filter for the FAQ page.
 *
 * Same contract as aravaipa-articles.js: every question is already
 * rendered server side as a <details>, this only hides and shows what is
 * already there, and it no-ops entirely on a page with no
 * [data-arv-faq-root]. A visitor with JS disabled gets a plain, already
 * readable list of accordions, same as before this shipped.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-arv-faq-root]');

	if (!root) {
		return;
	}

	var search = root.querySelector('[data-arv-faq-search]');
	var status = root.querySelector('[data-arv-faq-status]');
	var items = Array.prototype.slice.call(document.querySelectorAll('.arv-faq-q'));
	var sections = Array.prototype.slice.call(document.querySelectorAll('.arv-faq-section'));

	if (!search || 0 === items.length) {
		return;
	}

	var openBeforeSearch = [];
	var loggedQueries = {};
	var debounceTimer = null;

	function haystack(item) {
		if (item._arvHaystack) {
			return item._arvHaystack;
		}

		var summary = item.querySelector('summary');
		var text = (summary ? summary.textContent : '') + ' ' + (item.getAttribute('data-keywords') || '');
		item._arvHaystack = text.toLowerCase();

		return item._arvHaystack;
	}

	function apply() {
		var q = search.value.trim().toLowerCase();
		var shown = 0;

		if ('' === q) {
			// Leaving search restores whatever the visitor had open by hand
			// rather than forcing everything shut.
			for (var r = 0; r < items.length; r++) {
				items[r].hidden = false;
				items[r].open = openBeforeSearch.indexOf(items[r]) !== -1;
			}

			for (var s = 0; s < sections.length; s++) {
				sections[s].hidden = false;
			}

			status.textContent = '';

			return;
		}

		if (0 === openBeforeSearch.length) {
			for (var o = 0; o < items.length; o++) {
				if (items[o].open) {
					openBeforeSearch.push(items[o]);
				}
			}
		}

		for (var i = 0; i < items.length; i++) {
			var hit = haystack(items[i]).indexOf(q) !== -1;
			items[i].hidden = !hit;

			if (hit) {
				items[i].open = true;
				shown++;
			}
		}

		for (var j = 0; j < sections.length; j++) {
			var visible = sections[j].querySelectorAll('.arv-faq-q:not([hidden])');
			sections[j].hidden = 0 === visible.length;
		}

		status.textContent = 0 === shown
			? 'No matches. Try different words, or contact us below.'
			: shown + (1 === shown ? ' match' : ' matches');

		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(function () {
			logSearch(q, shown > 0);
		}, 600);
	}

	function logSearch(q, matched) {
		if (loggedQueries[q] || q.length < 3) {
			return;
		}

		loggedQueries[q] = true;

		if (window.fetch) {
			fetch('/wp-json/aravaipa/v1/faq-search-log', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ q: q, matched: matched }),
				keepalive: true
			}).catch(function () {
				// A missed analytics beacon should never surface to the visitor.
			});
		}
	}

	search.addEventListener('input', apply);

	// A support link to #some-slug should open that answer, not just scroll
	// past a closed one.
	if (window.location.hash) {
		var target = document.getElementById(window.location.hash.slice(1));

		if (target && target.classList.contains('arv-faq-q')) {
			target.open = true;
		}
	}
})();
