/*
 * Aravaipa Adventures landing: trip type filter. Hides tiles that do not
 * match and any season left empty. The full list is already in the HTML,
 * so without this script every trip simply shows. No-ops elsewhere.
 */
(function () {
	'use strict';
	var root = document.querySelector('.arv-adv-landing');
	if (!root) {
		return;
	}
	var buttons = root.querySelectorAll('.aa-trip-filter button');
	Array.prototype.forEach.call(buttons, function (b) {
		b.addEventListener('click', function () {
			var f = b.getAttribute('data-filter');
			Array.prototype.forEach.call(buttons, function (x) {
				x.setAttribute('aria-pressed', String(x === b));
			});
			Array.prototype.forEach.call(root.querySelectorAll('#trips .aa-season'), function (season) {
				var grid = season.querySelector('.aa-tile-grid');
				var shown = 0;
				Array.prototype.forEach.call(grid.querySelectorAll('.aa-tile'), function (t) {
					var on = f === 'all' || t.getAttribute('data-type') === f;
					t.hidden = !on;
					if (on) {
						shown++;
					}
				});
				grid.classList.toggle('aa-filtered', f !== 'all');
				season.hidden = shown === 0;
			});
		});
	});
})();
