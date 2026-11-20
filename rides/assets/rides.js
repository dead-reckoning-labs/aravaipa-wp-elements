/*
 * Aravaipa Rides: homepage hero slider. Dependency free.
 * Auto advance about every 6 s, pauses on hover and focus, prev/next, dots,
 * swipe, arrow keys, and stays still under prefers-reduced-motion.
 */
(function () {
	'use strict';

	var DELAY = 6000;

	function initSlider(root) {
		var slides = root.querySelectorAll('.rides-slide');
		var dots = root.querySelectorAll('[data-rides-dot]');
		var live = root.querySelector('[data-rides-live]');
		if (slides.length < 2) {
			return;
		}
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var index = 0;
		var timer = null;
		var hover = false;
		var focus = false;

		for (var i = 0; i < slides.length; i++) {
			if (slides[i].classList.contains('is-active')) {
				index = i;
			}
		}

		function show(n, announce) {
			index = (n + slides.length) % slides.length;
			for (var i = 0; i < slides.length; i++) {
				var on = i === index;
				slides[i].classList.toggle('is-active', on);
				slides[i].setAttribute('aria-hidden', on ? 'false' : 'true');
				var links = slides[i].querySelectorAll('a, button');
				for (var k = 0; k < links.length; k++) {
					if (on) {
						links[k].removeAttribute('tabindex');
					} else {
						links[k].setAttribute('tabindex', '-1');
					}
				}
				var img = slides[i].querySelector('img[data-src]');
				if (img && (on || i === (index + 1) % slides.length)) {
					var u = new URL(img.getAttribute('data-src'), window.location.href);
					if (u.protocol === 'https:' || u.protocol === 'http:') {
						img.src = u.href;
					}
					img.removeAttribute('data-src');
				}
			}
			for (var d = 0; d < dots.length; d++) {
				var cur = d === index;
				dots[d].classList.toggle('is-active', cur);
				dots[d].setAttribute('aria-current', cur ? 'true' : 'false');
			}
			if (announce && live) {
				var h = slides[index].querySelector('h2');
				live.textContent = (index + 1) + ' of ' + slides.length + (h ? ': ' + h.textContent : '');
			}
		}

		function stop() {
			if (timer) {
				clearInterval(timer);
				timer = null;
			}
		}

		function start() {
			stop();
			if (reduce || hover || focus || document.hidden) {
				return;
			}
			timer = setInterval(function () {
				show(index + 1, false);
			}, DELAY);
		}

		function go(n) {
			show(n, true);
			start();
		}

		var prev = root.querySelector('[data-rides-prev]');
		var next = root.querySelector('[data-rides-next]');
		if (prev) {
			prev.addEventListener('click', function () { go(index - 1); });
		}
		if (next) {
			next.addEventListener('click', function () { go(index + 1); });
		}
		for (var d = 0; d < dots.length; d++) {
			dots[d].addEventListener('click', function (ev) {
				go(parseInt(ev.currentTarget.getAttribute('data-rides-dot'), 10) || 0);
			});
		}

		root.addEventListener('mouseenter', function () { hover = true; stop(); });
		root.addEventListener('mouseleave', function () { hover = false; start(); });
		root.addEventListener('focusin', function () { focus = true; stop(); });
		root.addEventListener('focusout', function (ev) {
			if (!root.contains(ev.relatedTarget)) {
				focus = false;
				start();
			}
		});
		root.addEventListener('keydown', function (ev) {
			if (ev.key === 'ArrowLeft') {
				ev.preventDefault();
				go(index - 1);
			} else if (ev.key === 'ArrowRight') {
				ev.preventDefault();
				go(index + 1);
			}
		});

		var x0 = null;
		var y0 = null;
		root.addEventListener('touchstart', function (ev) {
			x0 = ev.touches[0].clientX;
			y0 = ev.touches[0].clientY;
		}, { passive: true });
		root.addEventListener('touchend', function (ev) {
			if (x0 === null) {
				return;
			}
			var dx = ev.changedTouches[0].clientX - x0;
			var dy = ev.changedTouches[0].clientY - y0;
			x0 = null;
			if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
				go(dx < 0 ? index + 1 : index - 1);
			}
		}, { passive: true });

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				stop();
			} else {
				start();
			}
		});

		show(index, false);
		start();
	}

	function boot() {
		var sliders = document.querySelectorAll('[data-rides-slider]');
		for (var i = 0; i < sliders.length; i++) {
			initSlider(sliders[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
