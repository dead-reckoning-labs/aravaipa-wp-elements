/**
 * The live board's touch shield.
 *
 * The frame is sized to its whole content so nothing inside it is cut off,
 * which on a race with 82 finishers is 3912px: five straight screens on a
 * phone where the only thing under a thumb is a cross-origin iframe. iOS
 * does not hand that vertical drag back to the page, so the page could not
 * be scrolled past the board at all. Found on race day, on the page people
 * were actually on.
 *
 * A transparent layer over the frame takes the touch instead: a drag scrolls
 * the page the way it does everywhere else, a tap lifts the shield so the
 * board underneath is fully usable, and scrolling the board off screen puts
 * it back so the next drag works too. Same pattern an embedded Google Map
 * uses, for the same reason.
 *
 * Revealed here rather than rendered visible, so a page whose JavaScript
 * never runs has no undismissable layer sitting over its results: the bug
 * that would cause is worse than the one this fixes.
 *
 * Touch devices only. A mouse wheel over an iframe already scrolls the page,
 * so a desktop never sees this and nothing here runs there.
 */
( function () {
	'use strict';

	if ( ! window.matchMedia || ! window.matchMedia( '(hover: none) and (pointer: coarse)' ).matches ) {
		return;
	}

	function wire( frame ) {
		var shield = frame.querySelector( '[data-arv-live-shield]' );

		if ( ! shield ) {
			return;
		}

		shield.hidden = false;

		shield.addEventListener( 'click', function () {
			shield.hidden = true;
		} );

		// Put it back once the board has left the screen, so a reader who
		// scrolls back to it can scroll past it again rather than being
		// trapped a second time by their own earlier tap.
		if ( ! window.IntersectionObserver ) {
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					if ( ! entries[ i ].isIntersecting ) {
						shield.hidden = false;
					}
				}
			},
			{ threshold: 0 }
		);

		observer.observe( frame );
	}

	var frames = document.querySelectorAll( '[data-arv-live-frame]' );

	for ( var i = 0; i < frames.length; i++ ) {
		wire( frames[ i ] );
	}
} )();
