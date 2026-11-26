/* Region hub video: swap the thumbnail for the player on click.
 * The link already points at YouTube, so with this script blocked or
 * failed the click still plays the film, just in a new tab. */
(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var link = e.target.closest ? e.target.closest('.arv-hub__play') : null;
		if (!link) {
			return;
		}
		var box = link.parentNode;
		var id = box.getAttribute('data-arv-hub-video');
		if (!id || !/^[A-Za-z0-9_-]{6,20}$/.test(id)) {
			return;
		}
		e.preventDefault();
		var f = document.createElement('iframe');
		f.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
		f.title = box.getAttribute('data-title') || 'Video';
		f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		f.allowFullscreen = true;
		f.referrerPolicy = 'strict-origin-when-cross-origin';
		box.replaceChild(f, link);
		f.focus();
	});
}());

/* Region hub fade-in: sections and cards marked data-arv-fade ease in the
 * first time they scroll into view. Nothing is hidden until this has run
 * (it adds arv-hub--fx), and nothing moves for prefers-reduced-motion or
 * a browser without IntersectionObserver. */
(function () {
	'use strict';
	if (!('IntersectionObserver' in window)) {
		return;
	}
	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}
	function start() {
		var hubs = document.querySelectorAll('.arv-hub');
		if (!hubs.length) {
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) {
					en.target.classList.add('is-in');
					io.unobserve(en.target);
				}
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
		Array.prototype.forEach.call(hubs, function (hub) {
			var items = hub.querySelectorAll('[data-arv-fade]');
			var vh = window.innerHeight || 800;
			Array.prototype.forEach.call(items, function (el) {
				// Already on screen at load: shown at once, never flashed.
				if (el.getBoundingClientRect().top < vh) {
					el.classList.add('is-in');
				} else {
					io.observe(el);
				}
			});
			hub.classList.add('arv-hub--fx');
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}());
