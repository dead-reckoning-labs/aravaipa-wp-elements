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
