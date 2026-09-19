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

	// How far the page has to move, as a share of the screen, before a lifted
	// shield goes back down. Scrolling the page at all while the board is live
	// means the reader found a way to move it (the gutters, or the button
	// below), and the next drag over the board should do the same.
	var REARM_SCROLL = 0.5;

	function wire( frame ) {
		var shield = frame.querySelector( '[data-arv-live-shield]' );

		if ( ! shield ) {
			return;
		}

		// Without IntersectionObserver there is no way to tell, so assume yes.
		var visible = ! window.IntersectionObserver;
		var liftedAt = 0;

		// Fixed to the bottom of the screen while the board is live and on
		// screen, so there is always a visible way to get the page back. The
		// old rule re-armed the shield only once the whole frame had left the
		// screen, and on a board several screens tall that never happens while
		// anyone is reading it: one tap and the page was stuck until a reload.
		var page = document.createElement( 'button' );
		page.type = 'button';
		page.className = 'arv-live__scroll';
		page.hidden = true;
		page.textContent = shield.getAttribute( 'data-arv-scroll-label' ) || 'Scroll page';
		frame.appendChild( page );

		function sync() {
			page.hidden = ! shield.hidden || ! visible;
		}

		function arm() {
			shield.hidden = false;
			sync();
		}

		function lift() {
			shield.hidden = true;
			liftedAt = window.pageYOffset;
			sync();
		}

		shield.hidden = false;

		shield.addEventListener( 'click', lift );
		page.addEventListener( 'click', arm );

		window.addEventListener(
			'scroll',
			function () {
				if ( shield.hidden && Math.abs( window.pageYOffset - liftedAt ) > window.innerHeight * REARM_SCROLL ) {
					arm();
				}
			},
			{ passive: true }
		);

		if ( ! window.IntersectionObserver ) {
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					visible = entries[ i ].isIntersecting;

					// Off screen entirely: put it back, as before, so a reader
					// who scrolls back to the board is not trapped by an old tap.
					if ( ! visible ) {
						shield.hidden = false;
					}
				}

				sync();
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
