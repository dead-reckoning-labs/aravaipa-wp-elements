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

/**
 * Click to play on an athlete's video row.
 *
 * Swaps the thumbnail button for the real YouTube player in place, so the
 * page ships three images instead of three embedded players and whoever
 * actually wants to watch still never leaves the page.
 */
(function () {
	'use strict';

	var rows = document.querySelectorAll('[data-arv-video-row]');

	if (!rows.length) {
		return;
	}

	for (var i = 0; i < rows.length; i++) {
		rows[i].addEventListener('click', function (e) {
			var button = e.target.closest('[data-arv-video-id]');

			if (!button) {
				return;
			}

			var id = button.getAttribute('data-arv-video-id');

			if (!id) {
				return;
			}

			var frame = document.createElement('iframe');
			frame.className = 'arv-athlete__video-frame';
			// autoplay: the click was the request to watch, so making them
			// press play a second time inside the player is a wasted step.
			frame.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
			frame.title = button.textContent.trim();
			frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
			frame.allowFullscreen = true;
			frame.loading = 'lazy';

			button.parentNode.replaceChild(frame, button);
		});
	}
})();
