/**
 * Region map: tap to open a region's card before going to its page.
 *
 * The card is otherwise pure CSS, opened by :hover and :focus-visible. On a
 * phone neither ever fires, so the card was unreachable and the whole pin
 * was a bare link: one tap on a dot with no label next to it and you were
 * on a region page, with no idea which one you had picked until it loaded.
 *
 * So on anything without a real pointer, the first tap opens the card and
 * the second follows the link. That is the same two-step every map does,
 * and it costs the hover path nothing: where hover works this file returns
 * immediately and the CSS keeps doing the job on its own.
 *
 * No dependencies, no build step, and it no-ops on any page with no map.
 */
( function () {
	'use strict';

	// The CSS opens the card on :hover inside exactly this query, so asking
	// the same question here is what keeps the two halves from both acting
	// on one device and fighting each other.
	if ( window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) {
		return;
	}

	var OPEN = 'is-open';

	// Touch browsers can emit a second click for the same physical tap: the
	// touch-derived one and a compatibility mouse one, a few milliseconds
	// apart. Without a gate the pair reads as "open" then "already open, so
	// navigate", and the card the first tap opened is gone before it has
	// been drawn, which is exactly the jarring straight-to-the-page
	// behaviour this file exists to remove. A second tap only counts as
	// deliberate once the card has been on screen long enough to read.
	var SETTLE_MS = 400;
	var openedAt = 0;

	function pins() {
		return document.querySelectorAll( '.arv-region-map__pin' );
	}

	function closeAll( except ) {
		var all = pins();
		for ( var i = 0; i < all.length; i++ ) {
			if ( all[ i ] !== except && all[ i ].classList.contains( OPEN ) ) {
				all[ i ].classList.remove( OPEN );
				all[ i ].setAttribute( 'aria-expanded', 'false' );
			}
		}
	}

	function onClick( event ) {
		var pin = event.target.closest( '.arv-region-map__pin' );
		if ( ! pin ) {
			// Anywhere else on the page, including the bare map behind the
			// pins. Tapping away is how you dismiss a card you opened by
			// mistake, and without it the only way out is to follow a link
			// you did not want.
			closeAll( null );
			return;
		}

		// Second tap, or a tap on the card's own call to action: the card is
		// already open and has said where this goes, so let the link run.
		if ( pin.classList.contains( OPEN ) ) {
			if ( Date.now() - openedAt < SETTLE_MS ) {
				// Same tap arriving twice, not a decision. Swallow it.
				event.preventDefault();
			}
			return;
		}

		event.preventDefault();
		closeAll( pin );
		pin.classList.add( OPEN );
		pin.setAttribute( 'aria-expanded', 'true' );
		openedAt = Date.now();
	}

	function onKey( event ) {
		if ( 'Escape' === event.key || 'Esc' === event.key ) {
			closeAll( null );
		}
	}

	function init() {
		var all = pins();
		if ( ! all.length ) {
			return;
		}
		for ( var i = 0; i < all.length; i++ ) {
			all[ i ].setAttribute( 'aria-expanded', 'false' );
		}
		// Delegated on the document rather than bound per pin, so the
		// outside-tap case and the pin case are one code path that cannot
		// disagree about what is open.
		document.addEventListener( 'click', onClick );
		document.addEventListener( 'keydown', onKey );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
