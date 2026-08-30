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

/**
 * Search, sort and race filter for the Films shelf.
 *
 * Same contract as aravaipa-watch.js and aravaipa-results.js: every film is
 * already rendered server side, and this only reorders and hides what is
 * already there. With no script the page is still the complete shelf in
 * playlist order, and a crawler sees all of it whatever any control says.
 *
 * Sorting reorders within each playlist section rather than across them,
 * because the sections are the two playlists and merging them would answer
 * a question nobody asked: "most watched" means the most watched
 * documentary and the most watched original, still under their own
 * headings.
 */
( function () {
	'use strict';

	var section = document.querySelector( '.arv-films' );

	if ( ! section ) {
		return;
	}

	var search = section.querySelector( '[data-arv-films-search]' );
	var sort = section.querySelector( '[data-arv-films-sort]' );
	var race = section.querySelector( '[data-arv-films-race]' );
	var clear = section.querySelector( '[data-arv-films-clear]' );
	var count = section.querySelector( '[data-arv-films-count]' );
	var lists = section.querySelectorAll( '[data-arv-films-list]' );

	if ( ! search && ! sort && ! race ) {
		return;
	}

	// The order the server sent, kept so "Default order" can be returned
	// to rather than being a one-way door out of it.
	var originals = [];

	for ( var n = 0; n < lists.length; n++ ) {
		originals.push( Array.prototype.slice.call( lists[ n ].children ) );
	}

	function apply() {
		var q = search ? search.value.trim().toLowerCase() : '';
		var wantRace = race ? race.value : '';
		var by = sort ? sort.value : '';
		var shown = 0;

		for ( var i = 0; i < lists.length; i++ ) {
			var list = lists[ i ];
			var original = originals[ i ];
			var cards = Array.prototype.slice.call( list.children );

			for ( var j = 0; j < cards.length; j++ ) {
				var card = cards[ j ];
				var title = card.getAttribute( 'data-arv-films-title' ) || '';
				var cardRace = card.getAttribute( 'data-arv-films-race' ) || '';

				// Search covers the race as well as the title, so typing
				// "cocodona" finds a film whose own title never says it.
				var hit = ( '' === q || title.indexOf( q ) !== -1 || cardRace.indexOf( q ) !== -1 ) &&
					( '' === wantRace || cardRace === wantRace );

				card.hidden = ! hit;

				if ( hit ) {
					shown++;
				}
			}

			// '' is "default order", which is the order the server sent:
			// the two sections are deliberately sorted differently there
			// (most watched for the back catalogue, newest for the
			// running series), so there is nothing for this to compute.
			// Restored from the original order rather than left alone, so
			// switching back to it after picking a sort actually goes
			// back.
			if ( '' === by ) {
				for ( var d = 0; d < original.length; d++ ) {
					list.appendChild( original[ d ] );
				}
			} else {
				cards.sort( function ( a, b ) {
					var key = ( 'views' === by ) ? 'data-arv-films-views' : 'data-arv-films-date';
					return Number( b.getAttribute( key ) || 0 ) - Number( a.getAttribute( key ) || 0 );
				} );

				for ( var k = 0; k < cards.length; k++ ) {
					list.appendChild( cards[ k ] );
				}
			}
		}

		// A section whose every film is filtered out would leave its
		// heading standing over nothing.
		for ( var m = 0; m < lists.length; m++ ) {
			var any = lists[ m ].querySelector( '.arv-films__card:not([hidden])' );
			var heading = lists[ m ].previousElementSibling;

			lists[ m ].hidden = ! any;

			if ( heading && heading.classList.contains( 'arv-films__section' ) ) {
				heading.hidden = ! any;
			}
		}

		if ( clear ) {
			clear.hidden = '' === q;
		}

		if ( count ) {
			count.textContent = ( '' === q && '' === wantRace )
				? ''
				: shown + ( 1 === shown ? ' film' : ' films' );
		}
	}

	if ( search ) {
		search.addEventListener( 'input', apply );
	}

	if ( sort ) {
		sort.addEventListener( 'change', apply );
	}

	if ( race ) {
		race.addEventListener( 'change', apply );
	}

	if ( clear ) {
		clear.addEventListener( 'click', function () {
			search.value = '';
			search.focus();
			apply();
		} );
	}

	// Deliberately not called on load. The server already renders the
	// shelf newest-first, which is what the sort control says by default,
	// so running this immediately would reorder nothing and only risk
	// disagreeing with the HTML a crawler was served. It runs when a
	// control actually changes, and not before.
} )();
