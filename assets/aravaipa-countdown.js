/**
 * Aravaipa countdown ticker.
 *
 * No dependencies: this loads on every page of a site that already ships
 * jQuery, Cornerstone's runtime and WP Rocket's combined bundles, so adding
 * another dependency to the chain is not worth a few lines of DOM work.
 */
(function () {
	'use strict';

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function tick(el, targetMs) {
		var remaining = targetMs - Date.now();

		if (remaining <= 0) {
			var message = el.getAttribute('data-arv-expired') || '';
			var units = el.querySelector('.arv-countdown__units');
			if (units) {
				if (message) {
					var note = document.createElement('p');
					note.className = 'arv-countdown__expired';
					note.textContent = message;
					units.parentNode.replaceChild(note, units);
				} else {
					units.remove();
				}
			}
			return false;
		}

		var totalSeconds = Math.floor(remaining / 1000);
		var values = {
			days: Math.floor(totalSeconds / 86400),
			hours: Math.floor((totalSeconds % 86400) / 3600),
			minutes: Math.floor((totalSeconds % 3600) / 60),
			seconds: totalSeconds % 60
		};

		Object.keys(values).forEach(function (unit) {
			var node = el.querySelector('[data-unit="' + unit + '"]');
			if (node) {
				node.textContent = pad(values[unit]);
			}
		});

		return true;
	}

	function init() {
		var elements = document.querySelectorAll('[data-arv-countdown]');

		Array.prototype.forEach.call(elements, function (el) {
			var targetMs = Date.parse(el.getAttribute('data-arv-countdown'));

			// An unparseable target would otherwise tick NaN into every unit.
			// Leaving the server-rendered zeros in place is the quieter
			// failure, and the element stays in the layout.
			if (isNaN(targetMs)) {
				return;
			}

			if (!tick(el, targetMs)) {
				return;
			}

			var timer = setInterval(function () {
				if (!tick(el, targetMs)) {
					clearInterval(timer);
				}
			}, 1000);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
