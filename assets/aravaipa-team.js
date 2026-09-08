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
 * The clicked video loads into the big stage above the row, and the row of
 * thumbnails stays put underneath so the next one is always one click away.
 * Playing in place inside a 260px card was the wrong trade: the whole point
 * of clicking is wanting to actually watch it.
 */
(function () {
	'use strict';

	var blocks = document.querySelectorAll('[data-arv-videos]');

	if (!blocks.length) {
		return;
	}

	for (var i = 0; i < blocks.length; i++) {
		wire(blocks[i]);
	}

	function wire(block) {
		var stage = block.querySelector('[data-arv-video-stage]');
		var row = block.querySelector('[data-arv-video-row]');

		if (!stage || !row) {
			return;
		}

		row.addEventListener('click', function (e) {
			var button = e.target.closest('[data-arv-video-id]');

			if (!button) {
				return;
			}

			var id = button.getAttribute('data-arv-video-id');

			if (!id) {
				return;
			}

			var title = button.getAttribute('data-arv-video-title') || button.textContent.trim();

			var frame = document.createElement('iframe');
			frame.className = 'arv-athlete__video-frame';
			// autoplay: the click was the request to watch, so making them
			// press play a second time inside the player is a wasted step.
			frame.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
			frame.title = title;
			frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
			frame.allowFullscreen = true;

			// Replacing the whole stage rather than swapping frame.src, so
			// switching videos tears the old player down instead of leaving
			// it loaded and audible behind the new one.
			stage.textContent = '';
			stage.appendChild(frame);
			stage.hidden = false;

			// On a narrow screen the stage can sit above the fold once the
			// thumbnails are what you were looking at. Nudge the window, not
			// scrollIntoView, which would also yank the horizontal scroller.
			var top = stage.getBoundingClientRect().top;

			if (top < 0) {
				window.scrollBy({ top: top - 16, behavior: 'smooth' });
			}

			var played = row.querySelectorAll('[data-arv-video-id]');

			for (var j = 0; j < played.length; j++) {
				var on = (played[j] === button);
				played[j].setAttribute('aria-pressed', on ? 'true' : 'false');
				played[j].classList.toggle('is-playing', on);
			}
		});
	}
})();
