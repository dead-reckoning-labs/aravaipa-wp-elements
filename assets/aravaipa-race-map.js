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

	/**
	 * Padding for every fitBounds on this map, sized to its own furniture.
	 *
	 * Leaflet's default is none, which puts the outermost pins exactly on the
	 * container's edges. That is already tight, and this map has a collapse
	 * toggle floating across the top right and the search and Near me across
	 * the bottom left, so "on the edge" also means "underneath a control".
	 * Bottom is the larger number because the search row is the taller of the
	 * two.
	 */
	var FIT = {
		paddingTopLeft: [ 20, 72 ],
		paddingBottomRight: [ 20, 84 ],
	};

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
	 * A race pin, in Aravaipa's teal rather than Leaflet's stock blue.
	 *
	 * Drawn as an inline SVG divIcon instead of loading Leaflet's default PNG
	 * pair: the stock marker is a fixed image that cannot be recoloured, and
	 * it also depends on Leaflet resolving its own image path, which breaks
	 * when the CSS comes from a CDN and the images are looked for relative to
	 * the page. This has neither problem and is one fewer request.
	 *
	 * Teal, not the accent red, on purpose. Red means "there is something to
	 * buy" everywhere else on the site, and a map of every race is not an
	 * offer. The register button inside the popup stays red.
	 */
	function raceIcon() {
		return window.L.divIcon( {
			className: 'arv-map__pin',
			html: '<svg viewBox="0 0 24 34" width="24" height="34" aria-hidden="true">' +
				'<path d="M12 0C5.4 0 0 5.4 0 12c0 8.4 12 22 12 22s12-13.6 12-22C24 5.4 18.6 0 12 0z" fill="#2a5e6b"/>' +
				'<circle cx="12" cy="12" r="4.4" fill="#ffffff"/></svg>',
			iconSize: [ 24, 34 ],
			// Anchored at the point of the pin, not its centre, so it marks
			// the venue rather than hovering above it.
			iconAnchor: [ 12, 34 ],
			popupAnchor: [ 0, -32 ],
		} );
	}

	/**
	 * The count bubble for a cluster of races.
	 *
	 * Sized in three steps rather than continuously: the useful signal is "a
	 * few / a lot / most of the calendar", and a radius that grows smoothly
	 * with the count just makes two adjacent clusters hard to tell apart.
	 */
	function clusterIcon( cluster ) {
		var count = cluster.getChildCount();
		var size  = count < 10 ? 34 : ( count < 25 ? 42 : 50 );

		return window.L.divIcon( {
			className: 'arv-map__cluster',
			html: '<span>' + count + '</span>',
			iconSize: [ size, size ],
		} );
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
	function addNearMe( canvas, map, pins, markers, group ) {
		if ( ! navigator.geolocation ) {
			return;
		}

		function build( wrap ) {
			var link = window.L.DomUtil.create( 'a', '', wrap );
			link.href = '#';
			link.title = 'Find races near me';
			link.setAttribute( 'role', 'button' );
			// An inline SVG rather than the &#9678; character it used to be.
			// That glyph is a text codepoint: its weight, size and vertical
			// alignment come from whatever font happens to resolve it, which
			// on a phone rendered as a faint hairline circle sitting off
			// centre in the button. This draws the same idea at a known
			// weight and scales with the button.
			link.innerHTML =
				'<svg class="arv-map__nearme-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
					'<circle cx="12" cy="12" r="3.2" fill="currentColor"/>' +
					'<circle cx="12" cy="12" r="7" fill="none" stroke="currentColor" stroke-width="1.8"/>' +
					'<path d="M12 1.6v3M12 19.4v3M1.6 12h3M19.4 12h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>' +
				'</svg>' +
				'<span class="arv-map__nearme-label">Near me</span>';

			// Without this Leaflet treats the click as a map drag/zoom
			// gesture and the anchor also tries to navigate to "#".
			window.L.DomEvent.disableClickPropagation( wrap );
			window.L.DomEvent.on( link, 'click', function ( e ) {
				window.L.DomEvent.preventDefault( e );
				locate( link, map, pins, markers, group );
			} );

			return wrap;
		}

		// Sits next to the search box rather than in a corner of its own.
		// Search and Near me answer the same question, "which race", by name
		// or by where you are standing, and splitting them across opposite
		// corners made the visitor hunt for the second one after finding the
		// first. Beside the input they read as one control.
		//
		// It also settles a collision this had either way: the search results
		// open upward out of the bottom left, so anything parked up the left
		// edge gets covered while someone types, and the top right is where
		// the collapse toggle now floats. Grouped with the search, there is
		// no corner left to fight over.
		var find = canvas.parentNode.querySelector( '[data-arv-map-search]' );
		if ( find ) {
			var inline = document.createElement( 'div' );
			inline.className = 'leaflet-bar arv-map__nearme arv-map__nearme--inline';
			find.appendChild( build( inline ) );
			return;
		}

		// No search box (a map with few enough races not to need one), so it
		// falls back to being a control in its own right.
		var Control = window.L.Control.extend( {
			options: { position: 'topright' },
			onAdd: function () {
				return build( window.L.DomUtil.create( 'div', 'leaflet-bar arv-map__nearme' ) );
			},
		} );

		map.addControl( new Control() );
	}

	/**
	 * Open a race's popup, even when its pin is currently inside a cluster.
	 *
	 * openPopup() on a clustered marker does nothing at all: the marker is not
	 * on the map, the cluster bubble standing in for it is. This was a real
	 * silent break, not a hypothetical one, the moment clustering went in:
	 * Near Me's "open the closest race" stopped doing anything for any race
	 * that happened to be in a cluster, which at the country view is all of
	 * them. zoomToShowLayer flies in and splits the cluster first, then runs
	 * the callback.
	 */
	function revealMarker( group, marker ) {
		if ( group && typeof group.zoomToShowLayer === 'function' ) {
			group.zoomToShowLayer( marker, function () {
				marker.openPopup();
			} );
			return;
		}

		marker.openPopup();
	}

	/**
	 * A type-as-you-go search that flies to one race.
	 *
	 * Deliberately not a second filter. The calendar below already filters a
	 * list, and putting a near-identical control here that did the same thing
	 * to a different view is how the two "every race" headings happened. This
	 * answers a different question: "where is Javelina", one race, go there.
	 * So it moves the map and opens a popup rather than hiding pins.
	 *
	 * Matches name and location together, because "phoenix" and "tennessee"
	 * are both things a runner would type looking for a race whose name
	 * contains neither.
	 */
	function addSearch( canvas, map, pins, markers, group ) {
		var wrap = canvas.parentNode.querySelector( '[data-arv-map-search]' );
		if ( ! wrap ) {
			return;
		}

		var input = wrap.querySelector( 'input' );
		var list  = wrap.querySelector( '[data-arv-map-results]' );
		if ( ! input || ! list ) {
			return;
		}

		// Rendered above the map in the markup, then moved inside it here.
		// Server-rendering it outside and relocating it, rather than building
		// it in JS, keeps it in the HTML for anything that never runs the
		// script, and means the input is real and focusable before Leaflet
		// has finished starting up.
		//
		// Bottom left, with the results opening upward. That direction is the
		// whole reason this can live inside the map at all: a list dropping
		// downward from a control gets clipped at the canvas edge, which is
		// exactly where it would open from. Upward it expands into the map.
		var Control = window.L.Control.extend( {
			options: { position: 'bottomleft' },
			onAdd: function () {
				return wrap;
			},
		} );
		map.addControl( new Control() );

		// Without this, typing drags the map and a click on a result is
		// treated as a map gesture. Scroll propagation stays enabled: the
		// results list can overflow, and it should scroll rather than zoom.
		window.L.DomEvent.disableClickPropagation( wrap );
		window.L.DomEvent.disableScrollPropagation( wrap );

		var active = -1;
		var shown  = [];

		function close() {
			list.innerHTML = '';
			wrap.classList.remove( 'is-open' );
			input.setAttribute( 'aria-expanded', 'false' );
			active = -1;
			shown  = [];
		}

		function choose( i ) {
			if ( ! shown[ i ] ) {
				return;
			}
			var index = shown[ i ].index;
			close();
			input.value = pins[ index ].name;
			map.setView( [ pins[ index ].lat, pins[ index ].lng ], 11 );
			revealMarker( group, markers[ index ] );
		}

		function render() {
			var q = input.value.trim().toLowerCase();
			if ( q.length < 2 ) {
				close();
				return;
			}

			shown = [];
			for ( var i = 0; i < pins.length && shown.length < 6; i++ ) {
				// pin.search is built server side and already lowercased, and
				// carries the full state name as well as the two-letter code,
				// so "tennessee" finds a race whose row only ever said "TN".
				var hay = pins[ i ].search || ( pins[ i ].name + ' ' + ( pins[ i ].where || '' ) ).toLowerCase();
				if ( hay.indexOf( q ) !== -1 ) {
					shown.push( { index: i } );
				}
			}

			if ( ! shown.length ) {
				close();
				return;
			}

			var html = '';
			for ( var j = 0; j < shown.length; j++ ) {
				var p = pins[ shown[ j ].index ];
				html += '<li role="option" id="arv-map-opt-' + j + '" aria-selected="false" data-i="' + j + '">' +
					'<span class="arv-map__result-name">' + escapeHtml( p.name ) + '</span>' +
					( p.where ? '<span class="arv-map__result-where">' + escapeHtml( p.where ) + '</span>' : '' ) +
					'</li>';
			}
			list.innerHTML = html;
			wrap.classList.add( 'is-open' );
			clampHeight();
			input.setAttribute( 'aria-expanded', 'true' );
			active = -1;
		}

		/**
		 * Never let the results grow past the top of the map.
		 *
		 * The CSS max-height is sized for six results on a normal map, but
		 * the map's height is an element setting an editor can drop to 300px,
		 * and on a short one a six-result list would run off the top of the
		 * canvas. Measured against the real canvas rather than assumed, so
		 * this holds at whatever height the element is configured to and on
		 * a phone in landscape, where the map is short for a different
		 * reason. Below the cap nothing changes; the CSS still decides.
		 */
		function clampHeight() {
			var room = input.getBoundingClientRect().top - canvas.getBoundingClientRect().top - 12;
			list.style.maxHeight = Math.max( 96, room ) + 'px';
		}

		function highlight( next ) {
			var items = list.querySelectorAll( 'li' );
			if ( ! items.length ) {
				return;
			}
			// Wraps at both ends, so holding Down cycles rather than sticking.
			active = ( next + items.length ) % items.length;
			for ( var i = 0; i < items.length; i++ ) {
				var on = i === active;
				items[ i ].classList.toggle( 'is-active', on );
				items[ i ].setAttribute( 'aria-selected', on ? 'true' : 'false' );
			}
			input.setAttribute( 'aria-activedescendant', 'arv-map-opt-' + active );
		}

		input.addEventListener( 'input', render );

		input.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				highlight( active + 1 );
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				highlight( active - 1 );
			} else if ( 'Enter' === e.key ) {
				// Enter with nothing highlighted takes the top match, which is
				// what someone who typed a full race name and hit return means.
				if ( shown.length ) {
					e.preventDefault();
					choose( active === -1 ? 0 : active );
				}
			} else if ( 'Escape' === e.key ) {
				close();
			}
		} );

		// mousedown, not click: the input's blur fires first on click and
		// would close the list out from under the pointer.
		list.addEventListener( 'mousedown', function ( e ) {
			var li = e.target.closest( 'li' );
			if ( li ) {
				e.preventDefault();
				choose( parseInt( li.getAttribute( 'data-i' ), 10 ) );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! wrap.contains( e.target ) ) {
				close();
			}
		} );
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
				map.fitBounds( box, {
					paddingTopLeft: FIT.paddingTopLeft,
					paddingBottomRight: FIT.paddingBottomRight,
					maxZoom: 10,
				} );

				window.L.circleMarker( [ lat, lng ], {
					radius: 7,
					color: '#ffffff',
					weight: 2,
					fillColor: '#ff2a13',
					fillOpacity: 1,
				} ).addTo( map ).bindPopup( 'You are here' );

				revealMarker( group, markers[ ranked[ 0 ].i ] );
			},
			function () {
				// Denied, unavailable, or timed out. All three mean the same
				// thing to the visitor, so the map just stays where it was
				// rather than throwing an error at someone who may have
				// simply said no.
				link.classList.remove( 'is-busy' );
				map.fitBounds( group.getBounds(), FIT );
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
			return window.L.marker( [ pin.lat, pin.lng ], { icon: raceIcon() } )
				.bindPopup( popupHtml( pin ), {
					// Leaflet pans a popup into view by default, but only far
					// enough to clear the container edge by 5px. This map has
					// its own furniture floating over those edges: the collapse
					// toggle across the top right, the search and Near me
					// across the bottom left. Clearing the edge by 5px puts a
					// popup underneath them instead of off the map, which is
					// the same problem one layer in. These paddings are sized
					// to the controls, so a popup comes to rest in open map.
					autoPan: true,
					autoPanPaddingTopLeft: [ 20, 72 ],
					autoPanPaddingBottomRight: [ 20, 84 ],
					// A pin near an edge can otherwise be dragged so its popup
					// leaves the map while still open.
					keepInView: true,
				} );
		} );

		// Clustered rather than added straight to the map. At the country
		// view the Arizona races sat on top of each other as one indistinct
		// blob, so a state with thirty races read as a state with one. A
		// count bubble says thirty, and splits as you zoom.
		//
		// Falls back to plain markers if the plugin did not load, rather than
		// throwing and leaving an empty grey box: it is a third-party CDN
		// script, and the map is still useful without it.
		var group;
		if ( window.L.markerClusterGroup ) {
			group = window.L.markerClusterGroup( {
				showCoverageOnHover: false,
				maxClusterRadius: 45,
				iconCreateFunction: clusterIcon,
				// zoomToBoundsOnClick off because this takes that case over
				// below. It is the one that was wrong: it picks a zoom from
				// the cluster's bounds with no padding at all, then centres on
				// the middle of them, so the outermost races land exactly on
				// the edges of the map and some end up under the controls
				// floating over it. Clicking Colorado zoomed in and cut
				// several races off, which is the bug this fixes.
				zoomToBoundsOnClick: false,
				spiderfyOnMaxZoom: true,
				// Restored to what shipped. Removing it was an attempt to fix
				// a separate, real problem: seven pairs of races in the
				// current calendar share a venue to four decimal places
				// (Javelina Jundred and Jackass Night Trail, Pass Mountain and
				// Punisher, Mayhem and Adrenaline, and four more), and above
				// this zoom each pair draws as two pins stacked exactly, so
				// one of each is invisible and unclickable.
				//
				// Clustering all the way down showed them honestly as a "2",
				// but made that bubble a dead control: markercluster only
				// spiderfies at its maximum zoom, and calling spiderfy()
				// directly at a lower zoom did nothing that could be verified
				// in a browser. A visible bubble that does nothing when
				// clicked is worse than the stacking it replaced, so this
				// stays as it was and the overlap is written up as its own
				// problem rather than half-solved here.
				disableClusteringAtZoom: 9,
			} );
			group.addLayers( markers );

			// disableClusteringAtZoom is deliberately not set. It used to be 9,
			// on the reasoning that pins stop overlapping past that, which is
			// true of pins that are merely near each other and false of pins
			// in the same place. Seven pairs of races in the current calendar
			// share a venue exactly, to four decimal places: Javelina Jundred
			// and Jackass Night Trail, Pass Mountain and Punisher, Mayhem and
			// Adrenaline, and four more. Above zoom 9 those drew as two pins
			// stacked precisely on top of each other, so one of each pair was
			// invisible and unclickable at any zoom. Clustering all the way
			// down means they stay a "2" bubble that fans out instead.
			group.on( 'clusterclick', function ( e ) {
				var bounds = e.layer.getBounds();

				// Zooming would not actually get closer, so leave the click to
				// the library, which spiderfies in exactly this case once you
				// are at its maximum zoom.
				if ( map.getBoundsZoom( bounds ) <= map.getZoom() ) {
					return;
				}

				map.fitBounds( bounds, FIT );
			} );
		} else {
			group = window.L.featureGroup( markers );
		}
		map.addLayer( group );

		// Fit to the pins rather than hardcoding a view: the same element is
		// used for the whole country and for a single region's page, and a
		// fixed centre would be wrong for one of them.
		map.fitBounds( group.getBounds(), FIT );

		// One pin gives fitBounds a zero-size box, which zooms to maximum
		// and lands on a rooftop. A region page with a single race hits this.
		if ( markers.length === 1 ) {
			map.setZoom( 9 );
		}

		addNearMe( canvas, map, config.pins, markers, group );
		addSearch( canvas, map, config.pins, markers, group );
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

	/**
	 * Publish the scrollbar's width so CSS can subtract it.
	 *
	 * The full-bleed map breaks out of its container with
	 * calc(50% - 50vw), and 100vw counts the scrollbar on Windows and Linux
	 * but not on macOS's overlay scrollbars. Unadjusted, that overhangs the
	 * window by the scrollbar's width and adds a horizontal scrollbar,
	 * which is invisible to anyone checking the work on a Mac. Measuring it
	 * is the only way to be right on both.
	 *
	 * Re-measured on resize because the scrollbar can appear and disappear
	 * as the page reflows, and because a window moved between a laptop
	 * screen and an external monitor can change it.
	 */
	function measureScrollbar() {
		var width = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.style.setProperty( '--arv-sbw', Math.max( 0, width ) + 'px' );
	}

	function init() {
		var canvases = document.querySelectorAll( '[data-arv-map]' );
		if ( ! canvases.length ) {
			return;
		}

		measureScrollbar();
		window.addEventListener( 'resize', measureScrollbar );

		Array.prototype.forEach.call( canvases, setUp );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
