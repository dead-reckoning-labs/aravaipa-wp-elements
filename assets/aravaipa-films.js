/**
 * The Films page's player: clicking a card or a Trailer link swaps it into
 * the one player on the page instead of opening a new tab.
 *
 * Same contract as the Watch race page's playlist in aravaipa-watch.js.
 * Every link is a real link to its YouTube URL first, target="_blank" and
 * all, so the page is fully usable with this script absent; this only
 * upgrades a click into an in-place swap, and updates the page's own URL
 * with history.pushState so the film that is actually playing is the one a
 * copied link points back to.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.arv-films__link, .arv-films__trailer' );

		if ( ! link ) {
			return;
		}

		var section = document.querySelector( '.arv-films' );
		var frame = section ? section.querySelector( '.arv-films__frame iframe' ) : null;

		if ( ! frame ) {
			return;
		}

		event.preventDefault();

		var id = link.getAttribute( 'data-yt-id' );
		var title = link.getAttribute( 'data-yt-title' ) || '';

		frame.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1';
		frame.title = title;

		var caption = section.querySelector( '.arv-films__now-title' );

		if ( caption ) {
			caption.textContent = title;
		}

		document.querySelectorAll( '.arv-films__card.is-active' ).forEach( function ( el ) {
			el.classList.remove( 'is-active' );
		} );

		var card = link.closest( '.arv-films__card' );

		if ( card ) {
			card.classList.add( 'is-active' );
		}

		document.querySelectorAll( '.arv-films__link[aria-current]' ).forEach( function ( el ) {
			el.removeAttribute( 'aria-current' );
		} );

		if ( link.classList.contains( 'arv-films__link' ) ) {
			link.setAttribute( 'aria-current', 'true' );
		}

		if ( window.history && window.history.pushState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'v', id );
			window.history.pushState( null, '', url );
		}

		section.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	} );
} )();
