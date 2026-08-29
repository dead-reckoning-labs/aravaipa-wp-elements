/**
 * A Watch race page's segment playlist: clicking a segment swaps it into the
 * one player already on the page instead of opening a new tab.
 *
 * Every segment is a real link to its YouTube URL first, target="_blank"
 * and all, so the page is fully usable with this script absent; this only
 * upgrades a click into an in-place swap.
 */
(function () {
	document.addEventListener('click', function (event) {
		var link = event.target.closest('.arv-watch-race__seg');

		if (!link) {
			return;
		}

		var wrap = document.querySelector('.arv-watch-race__frame');
		var frame = wrap ? wrap.querySelector('iframe') : null;

		if (!frame) {
			return;
		}

		event.preventDefault();

		frame.src = 'https://www.youtube-nocookie.com/embed/' + link.getAttribute('data-yt-id') + '?autoplay=1';
		frame.title = link.getAttribute('data-yt-title') || '';

		document.querySelectorAll('.arv-watch-race__seg.is-active').forEach(function (el) {
			el.classList.remove('is-active');
			el.removeAttribute('aria-current');
		});

		link.classList.add('is-active');
		link.setAttribute('aria-current', 'true');

		wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});
})();

/**
 * Search and year filter for the Watch archive.
 *
 * Same contract as aravaipa-results.js: everything is already rendered
 * server side, this only hides and shows what is already there, and it
 * no-ops entirely on a page with no [data-arv-watch-search].
 */
(function () {
	var inputs = document.querySelectorAll('[data-arv-watch-search]');

	for (var i = 0; i < inputs.length; i++) {
		wireWatchSearch(inputs[i]);
	}

	function wireWatchSearch(input) {
		var root = input.closest('.arv-watch');

		if (!root) {
			return;
		}

		var list = root.querySelector('[data-arv-watch-list]');
		var count = root.querySelector('[data-arv-watch-count]');
		var clear = root.querySelector('[data-arv-watch-clear]');
		var year = root.querySelector('[data-arv-watch-year]');

		if (!list) {
			return;
		}

		var cards = list.querySelectorAll('[data-arv-watch-name]');

		function apply() {
			var q = input.value.trim().toLowerCase();
			var y = year ? year.value : '';
			var shown = 0;

			for (var j = 0; j < cards.length; j++) {
				var card = cards[j];
				var name = card.getAttribute('data-arv-watch-name') || '';
				var hit = ('' === q || name.indexOf(q) !== -1) &&
					('' === y || card.getAttribute('data-arv-watch-year') === y);

				card.hidden = !hit;

				if (hit) {
					shown++;
				}
			}

			if (clear) {
				clear.hidden = '' === q;
			}

			if (count) {
				count.textContent = '' === q && '' === y
					? ''
					: shown + (1 === shown ? ' broadcast' : ' broadcasts');
			}
		}

		input.addEventListener('input', apply);

		if (year) {
			year.addEventListener('change', apply);
		}

		if (clear) {
			clear.addEventListener('click', function () {
				input.value = '';
				input.focus();
				apply();
			});
		}
	}
})();
