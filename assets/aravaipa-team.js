/**
 * Division filter for the Racing Team roster.
 *
 * Same contract as aravaipa-photos.js: everything is already rendered
 * server side, this only hides what is there, and it no-ops entirely on a
 * page with no [data-arv-team-root].
 *
 * Hides whole division sections rather than individual cards, because the
 * roster is grouped by division: hiding cards one by one would leave the
 * headings of empty divisions behind.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-arv-team-root]');

	if (!root) {
		return;
	}

	var select = root.querySelector('[data-arv-team-division]');
	var count = root.querySelector('[data-arv-team-count]');
	var groups = Array.prototype.slice.call(root.querySelectorAll('.arv-team__group'));

	if (!select || 0 === groups.length) {
		return;
	}

	function apply() {
		var want = select.value;
		var shown = 0;

		for (var i = 0; i < groups.length; i++) {
			var group = groups[i];
			var hit = ('' === want || group.getAttribute('data-arv-team-group') === want);

			group.hidden = !hit;

			if (hit) {
				shown += group.querySelectorAll('.arv-team__card').length;
			}
		}

		if (count) {
			count.textContent = ('' === want)
				? ''
				: shown + (1 === shown ? ' athlete' : ' athletes');
		}
	}

	select.addEventListener('change', apply);
})();
