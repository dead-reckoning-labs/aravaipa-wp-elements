/**
 * Search, category and year filters for the Articles archive.
 *
 * Same contract as aravaipa-photos.js: every card is already rendered
 * server side, this only hides and shows what is already there, and it
 * no-ops entirely on a page with no [data-arv-articles-grid].
 */
(function () {
	var grids = document.querySelectorAll('[data-arv-articles-grid]');

	for (var i = 0; i < grids.length; i++) {
		wireArticles(grids[i]);
	}

	function wireArticles(grid) {
		var root = grid.closest('.arv-articles');

		if (!root) {
			return;
		}

		var search = root.querySelector('[data-arv-articles-search]');
		var cat = root.querySelector('[data-arv-articles-cat]');
		var year = root.querySelector('[data-arv-articles-year]');
		var count = root.querySelector('[data-arv-articles-count]');
		var cards = grid.querySelectorAll('.arv-articles__card');

		if (!search && !cat && !year) {
			return;
		}

		function apply() {
			var q = search ? search.value.trim().toLowerCase() : '';
			var c = cat ? cat.value : '';
			var y = year ? year.value : '';
			var shown = 0;

			for (var j = 0; j < cards.length; j++) {
				var card = cards[j];
				var title = card.getAttribute('data-arv-articles-title') || '';
				// Split on the separator rather than substring-matching the
				// joined string: "news" must not match "press release" just
				// because some other category happens to contain it.
				var cats = (card.getAttribute('data-arv-articles-cat') || '').split('|');
				var hit = ('' === q || title.indexOf(q) !== -1) &&
					('' === c || cats.indexOf(c) !== -1) &&
					('' === y || card.getAttribute('data-arv-articles-year') === y);

				card.hidden = !hit;

				if (hit) {
					shown++;
				}
			}

			if (count) {
				count.textContent = ('' === q && '' === c && '' === y)
					? ''
					: shown + (1 === shown ? ' article' : ' articles');
			}
		}

		if (search) {
			search.addEventListener('input', apply);
		}

		if (cat) {
			cat.addEventListener('change', apply);
		}

		if (year) {
			year.addEventListener('change', apply);
		}
	}
})();
