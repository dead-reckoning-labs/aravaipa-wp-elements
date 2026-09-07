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

	/**
	 * The compact form: one line of words, not a block of ticking digits.
	 *
	 * Used by the upcoming broadcasts list, where four boxes of numbers per
	 * row would outweigh the race names they sit beside. The thresholds and
	 * wording match arv_watch_countdown_words() in includes/watch-store.php
	 * exactly, because that function has already rendered this same string
	 * into the element on the server. If the two disagreed, every one of
	 * these would visibly rewrite itself a moment after the page settled.
	 */
	function words(remaining) {
		var minute = 60000;
		var hour = 60 * minute;
		var day = 24 * hour;

		if (remaining <= 0) {
			return '';
		}

		if (remaining < hour) {
			var mins = Math.max(1, Math.round(remaining / minute));
			return 'in ' + mins + (mins === 1 ? ' minute' : ' minutes');
		}

		if (remaining < day) {
			var hours = Math.floor(remaining / hour);
			return 'in ' + hours + (hours === 1 ? ' hour' : ' hours');
		}

		var days = Math.floor(remaining / day);

		if (days < 14) {
			return 'in ' + days + (days === 1 ? ' day' : ' days');
		}

		var weeks = Math.round(days / 7);
		return 'in ' + weeks + (weeks === 1 ? ' week' : ' weeks');
	}

	function tickWords(el, targetMs) {
		var text = words(targetMs - Date.now());

		// Nothing left to count. The row keeps its date and, once Mountain
		// Outpost flips the event live, the server renders a "Live now"
		// flag on the next load; removing the stale "in 1 minute" here is
		// all this can usefully do in the meantime.
		if ('' === text) {
			el.remove();
			return false;
		}

		if (el.textContent !== text) {
			el.textContent = text;
		}

		return true;
	}

	function initWords() {
		var elements = document.querySelectorAll('[data-arv-countdown-until]');

		Array.prototype.forEach.call(elements, function (el) {
			var targetMs = Date.parse(el.getAttribute('data-arv-countdown-until'));

			// Same reasoning as the block ticker above: an unparseable date
			// leaves the server's own answer in place rather than replacing
			// it with "in NaN days".
			if (isNaN(targetMs)) {
				return;
			}

			if (!tickWords(el, targetMs)) {
				return;
			}

			// Every 30 seconds, not every second: the coarsest unit this
			// ever shows is a minute, so a per-second timer would do the
			// same string comparison sixty times to change nothing.
			var timer = setInterval(function () {
				if (!tickWords(el, targetMs)) {
					clearInterval(timer);
				}
			}, 30000);
		});
	}

	function boot() {
		init();
		initWords();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
