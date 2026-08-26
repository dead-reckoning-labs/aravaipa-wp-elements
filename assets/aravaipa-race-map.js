/**
 * Pin map for the Aravaipa Race Map element.
 *
 * Reads its data from a <script type="application/json"> block rendered
 * alongside the map rather than from a global or an API call: the data is
 * already known at render time, so fetching it again on load would be a
 * round trip to learn something the page already said.
 *
 * Leaflet is a hard dependency here and is loaded from a CDN by the element
 * that needs it. If it fails to load, the map stays empty and the <noscript>
 * list in the markup is what a visitor sees, which is why that list is a
 * plain list of every race rather than a placeholder.
 */
( function () {
	'use strict';

	function escapeHtml( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * The popup for one race.
	 *
	 * Built as a string because that is what Leaflet's bindPopup takes.
	 * Everything interpolated came from editor input at some point, so it is
	 * all escaped on the way in; the only unescaped parts are the tags this
	 * function writes itself.
	 */
	function popupHtml( pin ) {
		var html = '<div class="arv-map__popup">';

		if ( pin.logo ) {
			html += '<img class="arv-map__popup-logo" src="' + escapeHtml( pin.logo ) + '" alt="">';
		}

		html += '<p class="arv-map__popup-date">' + escapeHtml( pin.date ) + '</p>';

		if ( pin.page ) {
			html += '<h3 class="arv-map__popup-name"><a class="arv-map__popup-name-link" href="' + escapeHtml( pin.page ) + '">' + escapeHtml( pin.name ) + '</a></h3>';
		} else {
			html += '<h3 class="arv-map__popup-name">' + escapeHtml( pin.name ) + '</h3>';
		}

		if ( pin.distances ) {
			var pills = pin.distances.split( /,\s*/ ).filter( Boolean );
			html += '<p class="arv-map__popup-distances">';
			for ( var i = 0; i < pills.length; i++ ) {
				html += '<span class="arv-map__popup-pill">' + escapeHtml( pills[ i ] ) + '</span>';
			}
			html += '</p>';
		}

		if ( pin.where ) {
			html += '<p class="arv-map__popup-where">' + escapeHtml( pin.where ) + '</p>';
		}

		html += '<p class="arv-map__popup-actions">';
		if ( pin.ctaUrl && pin.cta ) {
			html += '<a class="arv-map__popup-cta arv-map__popup-cta--' + escapeHtml( pin.phase ) +
				'" href="' + escapeHtml( pin.ctaUrl ) + '" target="_blank" rel="noopener">' +
				escapeHtml( pin.cta ) + '</a>';
		}
		if ( pin.page ) {
			html += '<a class="arv-map__popup-info" href="' + escapeHtml( pin.page ) + '">Race Info</a>';
		}
		html += '</p></div>';

		return html;
	}

	function setUp( canvas ) {
		if ( typeof window.L === 'undefined' ) {
			return;
		}

		// The config block is a sibling, not a child: putting it inside the
		// canvas would mean Leaflet wipes it when it takes the container over.
		var holder = canvas.parentNode.querySelector( '[data-arv-map-config]' );
		if ( ! holder ) {
			return;
		}

		var config;
		try {
			config = JSON.parse( holder.textContent );
		} catch ( e ) {
			return;
		}

		if ( ! config.pins || ! config.pins.length ) {
			return;
		}

		var map = window.L.map( canvas, {
			// A map inside a long scrolling page that zooms when the wheel
			// passes over it hijacks the scroll. Zoom controls and pinch
			// still work; only the wheel is off, which is the usual fix.
			scrollWheelZoom: false,
		} );

		window.L.tileLayer( config.tileUrl, {
			attribution: config.tileAttr,
			maxZoom: 18,
		} ).addTo( map );

		var markers = config.pins.map( function ( pin ) {
			return window.L.marker( [ pin.lat, pin.lng ] )
				.addTo( map )
				.bindPopup( popupHtml( pin ) );
		} );

		// Fit to the pins rather than hardcoding a view: the same element is
		// used for the whole country and for a single region's page, and a
		// fixed centre would be wrong for one of them.
		var group = window.L.featureGroup( markers );
		map.fitBounds( group.getBounds(), { padding: [ 30, 30 ] } );

		// One pin gives fitBounds a zero-size box, which zooms to maximum
		// and lands on a rooftop. A region page with a single race hits this.
		if ( markers.length === 1 ) {
			map.setZoom( 9 );
		}

		// Leaflet measures its container on creation. Inside a Cornerstone
		// section that is still settling, or a tab that was not visible yet,
		// that measurement can be wrong and the tiles come out grey. This
		// re-measures once the layout has stopped moving.
		if ( typeof window.ResizeObserver !== 'undefined' ) {
			var observer = new window.ResizeObserver( function () {
				map.invalidateSize();
			} );
			observer.observe( canvas );
		} else {
			window.setTimeout( function () {
				map.invalidateSize();
			}, 400 );
		}
	}

	function init() {
		var canvases = document.querySelectorAll( '[data-arv-map]' );
		Array.prototype.forEach.call( canvases, setUp );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
