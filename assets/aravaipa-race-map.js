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
			// logoDark marks artwork that is white-on-transparent and so
			// vanishes against this popup's white card. It gets a dark chip
			// behind it rather than a re-cut asset.
			html += '<img class="arv-map__popup-logo' + ( pin.logoDark ? ' arv-map__popup-logo--dark' : '' ) +
				'" src="' + escapeHtml( pin.logo ) + '" alt="">';
		}

		html += '<p class="arv-map__popup-date">' + escapeHtml( pin.date ) + '</p>';

		if ( pin.page ) {
			html += '<h3 class="arv-map__popup-name"><a class="arv-map__popup-name-link" href="' + escapeHtml( pin.page ) + '">' + escapeHtml( pin.name ) + '</a></h3>';
		} else {
			html += '<h3 class="arv-map__popup-name">' + escapeHtml( pin.name ) + '</h3>';
		}

		if ( pin.distances ) {
			// Split on BOTH delimiters, because the source data uses both.
			// A race written across several row cells comes back pipe-joined
			// by arv_upcoming_races_parse_row ("50K | 25K | 10K | 5K"); a
			// race written as one cell keeps whatever the editor typed,
			// which is usually commas ("50 Mile, 50K, 30K"). In the current
			// 84-race file that is 29 pipe-joined against 43 comma-joined,
			// so handling only one of them leaves the majority rendering as
			// a single pill with the raw delimiter still showing. Splitting
			// on a comma alone was the original bug; splitting on a pipe
			// alone, the first fix for it, just moved the breakage onto the
			// larger half. No distance value contains a comma of its own
			// (verified: nothing matches digit-comma-digit), so there is
			// nothing here for a thousands separator to break.
			var pills = pin.distances.split( /\s*[|,]\s*/ ).filter( Boolean );
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

	/**
	 * Great-circle distance in miles between two lat/lng pairs.
	 *
	 * Haversine rather than a flat Pythagorean approximation: the pins span
	 * Arizona to New Hampshire to Michigan, and "nearest race" across that
	 * spread is exactly where treating degrees as a flat grid gets the
	 * ordering wrong, since a degree of longitude is ~55 miles in Arizona
	 * and ~48 in New Hampshire.
	 */
	function milesBetween( aLat, aLng, bLat, bLng ) {
		var toRad = Math.PI / 180;
		var dLat = ( bLat - aLat ) * toRad;
		var dLng = ( bLng - aLng ) * toRad;
		var h = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) +
			Math.cos( aLat * toRad ) * Math.cos( bLat * toRad ) *
			Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );
		return 3958.8 * 2 * Math.atan2( Math.sqrt( h ), Math.sqrt( 1 - h ) );
	}

	/**
	 * "Near me": a control that finds the closest races to the visitor.
	 *
	 * Geolocation is permission-gated and the prompt only appears on a real
	 * user gesture, so this is a button rather than something that runs on
	 * load. A page that asks for your location the moment it opens is the
	 * pattern browsers added the permission prompt to discourage.
	 *
	 * Nothing is sent anywhere: the coordinates stay in this function, the
	 * distances are computed here, and the map just moves. There is no
	 * request carrying the visitor's position.
	 */
	function addNearMe( map, pins, markers, group ) {
		if ( ! navigator.geolocation ) {
			return;
		}

		var Control = window.L.Control.extend( {
			options: { position: 'topleft' },
			onAdd: function () {
				var wrap = window.L.DomUtil.create( 'div', 'leaflet-bar arv-map__nearme' );
				var link = window.L.DomUtil.create( 'a', '', wrap );
				link.href = '#';
				link.title = 'Find races near me';
				link.setAttribute( 'role', 'button' );
				link.innerHTML = '<span aria-hidden="true">&#9678;</span><span class="arv-map__nearme-label">Near me</span>';

				// Without this Leaflet treats the click as a map drag/zoom
				// gesture and the anchor also tries to navigate to "#".
				window.L.DomEvent.disableClickPropagation( wrap );
				window.L.DomEvent.on( link, 'click', function ( e ) {
					window.L.DomEvent.preventDefault( e );
					locate( link, map, pins, markers, group );
				} );

				return wrap;
			},
		} );

		map.addControl( new Control() );
	}

	function locate( link, map, pins, markers, group ) {
		if ( link.classList.contains( 'is-busy' ) ) {
			return;
		}
		link.classList.add( 'is-busy' );

		navigator.geolocation.getCurrentPosition(
			function ( pos ) {
				link.classList.remove( 'is-busy' );

				var lat = pos.coords.latitude;
				var lng = pos.coords.longitude;

				var ranked = pins.map( function ( pin, i ) {
					return { i: i, miles: milesBetween( lat, lng, pin.lat, pin.lng ) };
				} ).sort( function ( a, b ) {
					return a.miles - b.miles;
				} );

				// Frame the visitor plus the three closest races rather than
				// snapping to the single nearest one: "what is around me"
				// is the actual question, and a lone pin at max zoom answers
				// a different one. Three keeps the box tight enough to still
				// read as local.
				var box = window.L.latLngBounds( [ [ lat, lng ] ] );
				ranked.slice( 0, 3 ).forEach( function ( r ) {
					box.extend( [ pins[ r.i ].lat, pins[ r.i ].lng ] );
				} );
				map.fitBounds( box, { padding: [ 40, 40 ], maxZoom: 10 } );

				window.L.circleMarker( [ lat, lng ], {
					radius: 7,
					color: '#ffffff',
					weight: 2,
					fillColor: '#ff2a13',
					fillOpacity: 1,
				} ).addTo( map ).bindPopup( 'You are here' );

				markers[ ranked[ 0 ].i ].openPopup();
			},
			function () {
				// Denied, unavailable, or timed out. All three mean the same
				// thing to the visitor, so the map just stays where it was
				// rather than throwing an error at someone who may have
				// simply said no.
				link.classList.remove( 'is-busy' );
				map.fitBounds( group.getBounds(), { padding: [ 30, 30 ] } );
			},
			{ enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
		);
	}

	/**
	 * Wire up the Hide map / Show map toggle, if the element rendered one.
	 *
	 * The critical part is invalidateSize() on expand. Leaflet measures its
	 * container when it draws; a map that was display:none at that moment
	 * measures zero and comes back as a grey box with the tiles bunched into
	 * one corner. Re-measuring after the panel is visible again is the fix,
	 * and it is the same failure the ResizeObserver in setUp() already
	 * guards against for the Cornerstone-still-settling case.
	 */
	function addCollapse( canvas, map ) {
		var wrap = canvas.closest( '.arv-map__inner' );
		if ( ! wrap ) {
			return;
		}

		var toggle = wrap.querySelector( '[data-arv-map-toggle]' );
		var panel  = wrap.querySelector( '[data-arv-map-panel]' );
		if ( ! toggle || ! panel ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
			var next = ! open;

			toggle.setAttribute( 'aria-expanded', next ? 'true' : 'false' );
			panel.hidden = ! next;
			toggle.querySelector( '.arv-map__toggle-label' ).textContent = next ? 'Hide map' : 'Show map';

			if ( next ) {
				// Next frame, so the panel has actually been laid out before
				// Leaflet asks how big it is.
				window.requestAnimationFrame( function () {
					map.invalidateSize();
				} );
			}
		} );
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

		addNearMe( map, config.pins, markers, group );
		addCollapse( canvas, map );

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
