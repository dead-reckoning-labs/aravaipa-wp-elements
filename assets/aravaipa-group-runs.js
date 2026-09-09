/**
 * Region filtering for Group Runs.
 *
 * Same contract as the Racing Team division buttons: everything is
 * already rendered server side, this only hides what is there, and it
 * no-ops entirely on a page with no [data-arv-grouprun-root]. One filter
 * bar controls both the region cards above and the combined upcoming
 * list below, since both carry the same data-arv-grouprun-region tag.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-arv-grouprun-root]');

	if (!root) {
		return;
	}

	var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-arv-grouprun-region]'))
		.filter(function (el) { return 'BUTTON' === el.tagName; });
	var targets = root.querySelectorAll('.arv-grouprun__card, .arv-grouprun__upcoming-row');
	var count = root.querySelector('[data-arv-grouprun-count]');

	if (!buttons.length) {
		return;
	}

	function apply(region) {
		var shown = 0;

		for (var i = 0; i < targets.length; i++) {
			var hit = ('' === region || targets[i].getAttribute('data-arv-grouprun-region') === region);
			targets[i].hidden = !hit;

			if (hit && targets[i].classList.contains('arv-grouprun__upcoming-row')) {
				shown++;
			}
		}

		if (count) {
			count.textContent = '' === region
				? ''
				: shown + (1 === shown ? ' run' : ' runs') + ' upcoming';
		}
	}

	for (var b = 0; b < buttons.length; b++) {
		buttons[b].addEventListener('click', function (e) {
			var region = e.currentTarget.getAttribute('data-arv-grouprun-region');

			for (var j = 0; j < buttons.length; j++) {
				var on = (buttons[j] === e.currentTarget);
				buttons[j].setAttribute('aria-pressed', on ? 'true' : 'false');
				buttons[j].classList.toggle('is-active', on);
			}

			apply(region);
		});
	}
})();
