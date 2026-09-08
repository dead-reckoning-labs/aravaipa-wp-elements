/**
 * Search and division filtering for the Racing Team roster.
 *
 * Same contract as aravaipa-photos.js: everything is already rendered
 * server side, this only hides what is there, and it no-ops entirely on a
 * page with no [data-arv-team-root].
 *
 * Both filters run through one pass over the cards rather than one hiding
 * groups and the other hiding cards. Combining them any other way meant a
 * search inside a picked division had to reconcile two different notions
 * of hidden, and an empty division heading was left behind whenever the
 * two disagreed.
 */
(function () {
	'use strict';

	var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-arv-team-root]'));

	if (!blocks.length) {
		return;
	}

	// The controls render once, on the roster, because the alumni block is a
	// single group and has nothing to filter by. Search still has to reach
	// the alumni cards though: typing a name and being told there are no
	// results while that athlete sits in a grid further down the same page
	// would just be wrong.
	var search = document.querySelector('[data-arv-team-search]');
	var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-arv-team-division]'))
		.filter(function (el) { return 'BUTTON' === el.tagName; });

	if (!search && !buttons.length) {
		return;
	}

	var division = '';

	for (var b = 0; b < buttons.length; b++) {
		if ('true' === buttons[b].getAttribute('aria-pressed')) {
			division = buttons[b].getAttribute('data-arv-team-division');
		}
	}

	function apply() {
		var query = search ? search.value.trim().toLowerCase() : '';
		// Keyed on the profile URL rather than counted per card, so an
		// athlete rendered by more than one block counts once. Page 79463
		// does not currently do that ([arv_racing_team] defaults to
		// status="current", so the roster's 51 and the alumni block's 10
		// are disjoint), but nothing stops a page from overlapping two
		// blocks, and a count that says "2 athletes" for one person is
		// worse than the extra line it takes to not.
		var seen = {};

		for (var i = 0; i < blocks.length; i++) {
			var groups = blocks[i].querySelectorAll('.arv-team__group');

			for (var g = 0; g < groups.length; g++) {
				var cards = groups[g].querySelectorAll('.arv-team__card');
				var visible = 0;

				for (var c = 0; c < cards.length; c++) {
					var card = cards[c];
					var divisions = (card.getAttribute('data-arv-team-division') || '').split('|');
					var text = card.getAttribute('data-arv-team-text') || '';

					var hit = ('' === division || -1 !== divisions.indexOf(division))
						&& ('' === query || -1 !== text.indexOf(query));

					// hidden, not display:none in a class, so the grid's own
					// layout rules stay in one place: CSS owns how a card
					// looks, this owns whether it is there at all.
					card.hidden = !hit;

					if (hit) {
						visible++;
						seen[card.getAttribute('href') || card.getAttribute('data-arv-team-text')] = 1;
					}
				}

				// A heading with nothing under it reads as a broken section,
				// so a group with no surviving cards goes away entirely.
				groups[g].hidden = (0 === visible);
			}
		}

		report(Object.keys(seen).length, query);
	}

	function report(shown, query) {
		var filtering = ('' !== division || '' !== query);

		for (var i = 0; i < blocks.length; i++) {
			var count = blocks[i].querySelector('[data-arv-team-count]');

			if (!count) {
				continue;
			}

			// Only the block that owns the controls reports, otherwise the
			// same total is printed twice on the page.
			if (search && !blocks[i].contains(search)) {
				count.textContent = '';
				continue;
			}

			if (!filtering) {
				count.textContent = '';
			} else if (0 === shown) {
				count.textContent = 'No athletes match' + (query ? ' "' + query + '"' : '') + '.';
			} else {
				count.textContent = shown + (1 === shown ? ' athlete' : ' athletes');
			}
		}
	}

	for (var k = 0; k < buttons.length; k++) {
		buttons[k].addEventListener('click', function (e) {
			division = e.currentTarget.getAttribute('data-arv-team-division');

			for (var j = 0; j < buttons.length; j++) {
				var on = (buttons[j] === e.currentTarget);
				buttons[j].setAttribute('aria-pressed', on ? 'true' : 'false');
				buttons[j].classList.toggle('is-active', on);
			}

			apply();
		});
	}

	if (search) {
		search.addEventListener('input', apply);
		// Escape clears, which is what the native search-field X does, and
		// keyboards without that X still need a way out.
		search.addEventListener('keydown', function (e) {
			if ('Escape' === e.key) {
				search.value = '';
				apply();
			}
		});
	}

	// A division preset by the shortcode attribute has to be applied on load,
	// not just on click.
	if ('' !== division) {
		apply();
	}
})();

/**
 * The athlete video stage.
 *
 * The stage arrives showing the first video's poster, and a click anywhere
 * (the poster itself, or any thumbnail in the row below) swaps that poster
 * for the real player. Loading a player up front would cost roughly a
 * megabyte for something most visitors scroll straight past.
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

		upgradePoster(stage.querySelector('[data-arv-video-hires]'));

		block.addEventListener('click', function (e) {
			var button = e.target.closest('[data-arv-video-id]');

			if (!button) {
				return;
			}

			var id = button.getAttribute('data-arv-video-id');

			if (!id) {
				return;
			}

			// Already the one playing, and it is a real player rather than
			// the poster: leave it alone instead of reloading it from the
			// start under someone who is watching it.
			if (button.classList.contains('is-playing') && stage.querySelector('iframe')) {
				return;
			}

			play(stage, row, button, id);
		});
	}

	function play(stage, row, button, id) {
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

		// On a narrow screen the stage can sit above the fold once the
		// thumbnails are what you were looking at. Nudge the window, not
		// scrollIntoView, which would also yank the horizontal scroller.
		var top = stage.getBoundingClientRect().top;

		if (top < 0) {
			window.scrollBy({ top: top - 16, behavior: 'smooth' });
		}

		var thumbs = row.querySelectorAll('[data-arv-video-id]');

		for (var j = 0; j < thumbs.length; j++) {
			var on = (thumbs[j].getAttribute('data-arv-video-id') === id);
			thumbs[j].setAttribute('aria-pressed', on ? 'true' : 'false');
			thumbs[j].classList.toggle('is-playing', on);
		}
	}

	/**
	 * Swap the poster's 4:3 hqdefault for the real 16:9 maxresdefault, but
	 * only once it has loaded. Roughly one video in ten has no maxres, and
	 * setting the src directly would show a broken image on those.
	 */
	function upgradePoster(img) {
		if (!img) {
			return;
		}

		var hires = img.getAttribute('data-arv-video-hires');

		if (!hires) {
			return;
		}

		var probe = new Image();

		probe.onload = function () {
			// YouTube answers a missing thumbnail with a 120x90 placeholder
			// on some paths rather than a 404, so size is the real test.
			if (probe.naturalWidth > 320) {
				img.src = hires;
			}
		};

		probe.src = hires;
	}
})();
