/**
 * Region and division filters for the Racing Team grid.
 *
 * Same contract as aravaipa-photos.js: every card is already rendered
 * server side, this only hides what is there, and it no-ops entirely on a
 * page with no [data-arv-team-root].
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-arv-team-root]');

	if (!root) {
		return;
	}

	var region = root.querySelector('[data-arv-team-region]');
	var division = root.querySelector('[data-arv-team-division]');
	var count = root.querySelector('[data-arv-team-count]');
	var grid = root.querySelector('[data-arv-team-grid]');

	if (!grid || (!region && !division)) {
		return;
	}

	var cards = Array.prototype.slice.call(grid.children);

	function apply() {
		var r = region ? region.value : '';
		var d = division ? division.value : '';
		var shown = 0;

		for (var i = 0; i < cards.length; i++) {
			var card = cards[i];
			var cardRegions = (card.getAttribute('data-arv-team-region') || '').split('|');
			var cardDivisions = (card.getAttribute('data-arv-team-division') || '').split('|');
			var hit = ('' === r || cardRegions.indexOf(r) !== -1) &&
				('' === d || cardDivisions.indexOf(d) !== -1);

			card.hidden = !hit;

			if (hit) {
				shown++;
			}
		}

		if (count) {
			count.textContent = ('' === r && '' === d)
				? ''
				: shown + (1 === shown ? ' athlete' : ' athletes');
		}
	}

	if (region) {
		region.addEventListener('change', apply);
	}

	if (division) {
		division.addEventListener('change', apply);
	}
})();
