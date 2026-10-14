/**
 * Interactive course map for the Aravaipa Course Map element.
 *
 * Reads its config from the <script type="application/json"> block the
 * element renders: the course line and markers from CalTopo, plus the
 * aid-station rows typed into the builder. Elevation is not in it. The
 * profile, gain, grade colours and GPX elevations all come from the same
 * terrain tiles MapLibre loads for the 3D view, sampled here, so a course
 * only ever has one source of elevation and the server never fetches tiles
 * during a page render.
 *
 * Maps are built when they scroll into view, not on load: race pages put
 * one map in each distance tab, and a hidden tab has no size to fit a map
 * to.
 */
( function () {
	'use strict';

	var ROUTE = '#E2702A';
	var DEM = 'https://elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{x}/{y}.png';
	var DEM_Z = 14;

	/**
	 * Grade bands for the profile and the map's Grade view, by absolute
	 * grade. Desert tones that sit with the orange route and the pale
	 * terrain base rather than a traffic-light ramp, and dark enough at the
	 * top that a 15% wall reads as one at a glance.
	 */
	var GRADES = [
		[ 3, '#8FAE9C', 'Under 3%' ],
		[ 6, '#E3C26B', '3 to 6%' ],
		[ 10, '#E2702A', '6 to 10%' ],
		[ 15, '#B23A1E', '10 to 15%' ],
		[ Infinity, '#5B1A12', '15%+' ],
	];

	var USGS = 'https://basemap.nationalmap.gov/arcgis/rest/services/';
	var ESRI = 'https://services.arcgisonline.com/ArcGIS/rest/services/';
	var USGS_CREDIT = '<a href="https://www.usgs.gov/programs/national-geospatial-program/national-map">USGS The National Map</a>';

	var ICON_FS = '<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 5V1h4M8 1h4v4M12 8v4H8M5 12H1V8"/></svg>';
	var ICON_PLAY = '<svg width="11" height="12" viewBox="0 0 11 12" fill="currentColor"><path d="M1 0.5l9.5 5.5L1 11.5z"/></svg>';
	var ICON_STOP = '<svg width="11" height="12" viewBox="0 0 11 12" fill="currentColor"><path d="M1 1h3.5v10H1zM6.5 1H10v10H6.5z"/></svg>';

	function esc( v ) {
		return String( v )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function rad( d ) {
		return ( d * Math.PI ) / 180;
	}

	/** Metres between two [lng, lat] points. */
	function dist( a, b ) {
		var dl = rad( b[ 0 ] - a[ 0 ] ),
			dp = rad( b[ 1 ] - a[ 1 ] ),
			h =
				Math.sin( dp / 2 ) * Math.sin( dp / 2 ) +
				Math.cos( rad( a[ 1 ] ) ) * Math.cos( rad( b[ 1 ] ) ) * Math.sin( dl / 2 ) * Math.sin( dl / 2 );
		return 2 * 6371008.8 * Math.asin( Math.sqrt( h ) );
	}

	function bearing( a, b ) {
		var y = Math.sin( rad( b[ 0 ] - a[ 0 ] ) ) * Math.cos( rad( b[ 1 ] ) ),
			x =
				Math.cos( rad( a[ 1 ] ) ) * Math.sin( rad( b[ 1 ] ) ) -
				Math.sin( rad( a[ 1 ] ) ) * Math.cos( rad( b[ 1 ] ) ) * Math.cos( rad( b[ 0 ] - a[ 0 ] ) );
		return ( Math.atan2( y, x ) * 180 ) / Math.PI;
	}

	function mi( m ) {
		return m / 1609.344;
	}

	function ft( m ) {
		return Math.round( m * 3.28084 );
	}

	function fmtMi( m ) {
		var v = mi( m );
		return v < 10 ? v.toFixed( 1 ) : v.toFixed( 1 ).replace( /\.0$/, '' );
	}

	/* ------------------------------------------------------------------ */
	/* Elevation, from Terrarium tiles                                     */
	/* ------------------------------------------------------------------ */

	var tiles = {};

	function tile( x, y ) {
		var k = x + '/' + y;
		if ( ! tiles[ k ] ) {
			tiles[ k ] = fetch( DEM.replace( '{z}', DEM_Z ).replace( '{x}', x ).replace( '{y}', y ) )
				.then( function ( r ) {
					if ( ! r.ok ) {
						throw new Error( 'tile' );
					}
					return r.blob();
				} )
				.then( createImageBitmap )
				.then( function ( bm ) {
					var c = document.createElement( 'canvas' );
					c.width = c.height = 256;
					var g = c.getContext( '2d', { willReadFrequently: true } );
					g.drawImage( bm, 0, 0 );
					return g.getImageData( 0, 0, 256, 256 ).data;
				} )
				.catch( function () {
					return null;
				} );
		}
		return tiles[ k ];
	}

	/** Global pixel position of a point at DEM_Z. */
	function pixel( p ) {
		var n = 256 * Math.pow( 2, DEM_Z ),
			s = Math.sin( rad( p[ 1 ] ) );
		return [ ( ( p[ 0 ] + 180 ) / 360 ) * n, ( 0.5 - Math.log( ( 1 + s ) / ( 1 - s ) ) / ( 4 * Math.PI ) ) * n ];
	}

	/** Elevations in metres for a list of points, bilinear between pixels. */
	function elevations( pts ) {
		var need = {};
		pts.forEach( function ( p ) {
			var q = pixel( p ),
				tx = Math.floor( q[ 0 ] / 256 ),
				ty = Math.floor( q[ 1 ] / 256 );
			for ( var dx = -1; dx <= 1; dx++ ) {
				for ( var dy = -1; dy <= 1; dy++ ) {
					// Only the neighbours a 1px bilinear kernel can reach.
					var ex = q[ 0 ] - tx * 256,
						ey = q[ 1 ] - ty * 256;
					if ( ( dx < 0 && ex > 1 ) || ( dx > 0 && ex < 255 ) || ( dy < 0 && ey > 1 ) || ( dy > 0 && ey < 255 ) ) {
						continue;
					}
					need[ tx + dx + '/' + ( ty + dy ) ] = 1;
				}
			}
		} );

		var keys = Object.keys( need );
		return Promise.all(
			keys.map( function ( k ) {
				var s = k.split( '/' );
				return tile( +s[ 0 ], +s[ 1 ] );
			} )
		).then( function ( data ) {
			var T = {};
			keys.forEach( function ( k, i ) {
				T[ k ] = data[ i ];
			} );
			function v( X, Y ) {
				var tx = Math.floor( X / 256 ),
					ty = Math.floor( Y / 256 ),
					d = T[ tx + '/' + ty ];
				if ( ! d ) {
					return null;
				}
				var i = ( ( Y - ty * 256 ) * 256 + ( X - tx * 256 ) ) * 4;
				return d[ i ] * 256 + d[ i + 1 ] + d[ i + 2 ] / 256 - 32768;
			}
			return pts.map( function ( p ) {
				var q = pixel( p ),
					fx = q[ 0 ] - 0.5,
					fy = q[ 1 ] - 0.5,
					x0 = Math.floor( fx ),
					y0 = Math.floor( fy ),
					ax = fx - x0,
					ay = fy - y0,
					a = v( x0, y0 ),
					b = v( x0 + 1, y0 ),
					c = v( x0, y0 + 1 ),
					d = v( x0 + 1, y0 + 1 );
				if ( a === null || b === null || c === null || d === null ) {
					return a;
				}
				return a * ( 1 - ax ) * ( 1 - ay ) + b * ax * ( 1 - ay ) + c * ( 1 - ax ) * ay + d * ax * ay;
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Course model                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * The line resampled to a point every 20 m or less, with cumulative
	 * distance. Even spacing is what makes the smoothing and grade windows
	 * below mean the same thing on a switchback as on a fire road.
	 */
	function densify( line ) {
		var pts = [ line[ 0 ] ],
			cum = [ 0 ];
		for ( var i = 1; i < line.length; i++ ) {
			var a = line[ i - 1 ],
				b = line[ i ],
				L = dist( a, b ),
				n = Math.max( 1, Math.ceil( L / 20 ) );
			for ( var j = 1; j <= n; j++ ) {
				pts.push( [ a[ 0 ] + ( ( b[ 0 ] - a[ 0 ] ) * j ) / n, a[ 1 ] + ( ( b[ 1 ] - a[ 1 ] ) * j ) / n ] );
				cum.push( cum[ cum.length - 1 ] + L / n );
			}
		}
		return { pts: pts, cum: cum, total: cum[ cum.length - 1 ] };
	}

	/** Index of the densified point nearest a distance along the course. */
	function indexAt( c, d ) {
		var lo = 0,
			hi = c.cum.length - 1;
		while ( hi - lo > 1 ) {
			var mid = ( lo + hi ) >> 1;
			if ( c.cum[ mid ] < d ) {
				lo = mid;
			} else {
				hi = mid;
			}
		}
		return d - c.cum[ lo ] < c.cum[ hi ] - d ? lo : hi;
	}

	/** Mean of v over +-w metres of each point. */
	function smooth( c, v, w ) {
		var out = [],
			a = 0,
			b = 0,
			sum = 0;
		for ( var i = 0; i < v.length; i++ ) {
			while ( b < v.length && c.cum[ b ] <= c.cum[ i ] + w ) {
				sum += v[ b++ ];
			}
			while ( c.cum[ a ] < c.cum[ i ] - w ) {
				sum -= v[ a++ ];
			}
			out.push( sum / ( b - a ) );
		}
		return out;
	}

	/**
	 * Elevation, grade and gain on the densified course.
	 *
	 * Gain only counts once elevation has moved 2 m from the last counted
	 * point, the usual threshold: summing every rise in a raw DEM profile
	 * counts pixel noise as climbing and lands 20 to 40% high on a flat
	 * course.
	 */
	function profile( c, raw ) {
		var last = 0;
		raw = raw.map( function ( e ) {
			if ( e === null ) {
				return last;
			}
			last = e;
			return e;
		} );
		var ele = smooth( c, raw, 30 ),
			grade = [],
			gain = 0,
			loss = 0,
			ref = ele[ 0 ];

		for ( var i = 0; i < ele.length; i++ ) {
			var a = indexAt( c, c.cum[ i ] - 80 ),
				b = indexAt( c, c.cum[ i ] + 80 ),
				run = c.cum[ b ] - c.cum[ a ];
			grade.push( run > 0 ? ( ( ele[ b ] - ele[ a ] ) / run ) * 100 : 0 );

			if ( ele[ i ] > ref + 2 ) {
				gain += ele[ i ] - ref;
				ref = ele[ i ];
			} else if ( ele[ i ] < ref - 2 ) {
				loss += ref - ele[ i ];
				ref = ele[ i ];
			}
		}

		var lo = Infinity,
			hi = -Infinity;
		ele.forEach( function ( e ) {
			lo = Math.min( lo, e );
			hi = Math.max( hi, e );
		} );

		return { ele: ele, grade: grade, gain: gain, loss: loss, lo: lo, hi: hi };
	}

	function band( g ) {
		g = Math.abs( g );
		for ( var i = 0; i < GRADES.length; i++ ) {
			if ( g < GRADES[ i ][ 0 ] ) {
				return i;
			}
		}
		return GRADES.length - 1;
	}

	/**
	 * Runs of one grade band, with anything under 120 m folded into its
	 * neighbour: grade is about what the next stretch of trail feels like,
	 * and a profile striped by every short kink reads as noise.
	 */
	function gradeRuns( c, grade ) {
		var runs = [];
		for ( var i = 0; i < grade.length; i++ ) {
			var b = band( grade[ i ] ),
				r = runs[ runs.length - 1 ];
			if ( r && r.b === b ) {
				r.j = i;
			} else {
				runs.push( { b: b, i: i, j: i } );
			}
		}
		var out = [];
		runs.forEach( function ( r ) {
			var p = out[ out.length - 1 ];
			if ( p && ( c.cum[ r.j ] - c.cum[ r.i ] < 120 || p.b === r.b ) ) {
				p.j = r.j;
			} else {
				out.push( r );
			}
		} );
		return out;
	}

	/**
	 * Every distance along the course that passes within `near` metres of a
	 * point, one per pass. A two-lap course passes its aid stations twice,
	 * and an out-and-back passes most of them twice, so a marker can have
	 * several mileages.
	 */
	function passes( c, p, near ) {
		var out = [],
			best = null;
		for ( var i = 0; i < c.pts.length; i++ ) {
			var d = dist( c.pts[ i ], p );
			if ( d < near ) {
				if ( ! best || d < best.d ) {
					best = { d: d, at: c.cum[ i ] };
				}
			} else if ( best && c.cum[ i ] - best.at > 400 ) {
				out.push( best.at );
				best = null;
			}
		}
		if ( best ) {
			out.push( best.at );
		}
		return out;
	}

	function isStart( t ) {
		return /start|finish/i.test( t );
	}

	function isAid( m ) {
		return /\baid\b|\bas\b|station/i.test( m.title ) || /aid/i.test( m.sym );
	}

	function cleanName( t ) {
		return t.replace( /\s+(aid( station)?|as)\s*$/i, '' ).trim();
	}

	/**
	 * Start, finish, lap turns and aid stations, in course order.
	 *
	 * A course whose two ends are within 300 m of each other is a loop, and
	 * any mid-course pass of its start marker is a lap turn, which is also
	 * where runners get aid, so it is listed as one.
	 */
	function stops( c, cfg ) {
		var first = c.pts[ 0 ],
			last = c.pts[ c.pts.length - 1 ],
			loop = dist( first, last ) < 300,
			aid = [],
			laps = [],
			start = null,
			finish = null;

		cfg.marks.forEach( function ( m ) {
			var row = cfg.aid[ m.title.toLowerCase() ] || null;
			if ( isStart( m.title ) ) {
				// A CalTopo map shared by several distances carries every
				// distance's start, so a marker only counts as this line's
				// start or finish if it is actually at that end of it.
				var ds = dist( m.ll, first ),
					df = dist( m.ll, last );
				if ( ( loop || /start/i.test( m.title ) ) && ds < 300 && ( ! start || ds < dist( start, first ) ) ) {
					start = m.ll;
				}
				if ( ( loop || /finish/i.test( m.title ) ) && df < 300 && ( ! finish || df < dist( finish, last ) ) ) {
					finish = m.ll;
				}
				// Lap turns are measured from the line's own first point,
				// not the marker: the marker sits beside the arch, and a
				// course that runs past the start area mid-race (most of
				// them) would otherwise grow a lap every time it does.
				if ( loop ) {
					passes( c, first, 50 ).forEach( function ( d ) {
						if ( d > 800 && d < c.total - 800 ) {
							laps.push( d );
							aid.push( { name: ( row && row.name ) || 'Start/Finish', at: d, ll: m.ll, row: row, title: m.title } );
						}
					} );
				}
				return;
			}
			if ( ! row && ! isAid( m ) ) {
				return;
			}
			passes( c, m.ll, 120 ).forEach( function ( d ) {
				aid.push( { name: ( row && row.name ) || cleanName( m.title ), at: d, ll: m.ll, row: row, title: m.title } );
			} );
		} );

		aid.sort( function ( a, b ) {
			return a.at - b.at;
		} );
		laps.sort( function ( a, b ) {
			return a - b;
		} );

		return { aid: aid, laps: laps, loop: loop, start: start || first, finish: finish || last };
	}

	/* ------------------------------------------------------------------ */
	/* Map                                                                 */
	/* ------------------------------------------------------------------ */

	function raster( tiles, max, credit ) {
		var s = { type: 'raster', tiles: tiles, tileSize: 256, maxzoom: max };
		if ( credit ) {
			s.attribution = credit;
		}
		return s;
	}

	function style( line ) {
		return {
			version: 8,
			sources: {
				dem: { type: 'raster-dem', tiles: [ DEM ], tileSize: 256, maxzoom: 15, encoding: 'terrarium', attribution: 'Elevation: Mapzen Terrain Tiles' },
				shade: { type: 'raster-dem', tiles: [ DEM ], tileSize: 256, maxzoom: 15, encoding: 'terrarium' },
				hydro: raster( [ USGS + 'USGSHydroCached/MapServer/tile/{z}/{y}/{x}' ], 16, 'Water: ' + USGS_CREDIT ),
				roads: raster( [ ESRI + 'Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}' ], 17 ),
				names: raster( [ ESRI + 'Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}' ], 17, 'Roads and places: Esri, HERE, Garmin, &copy; OpenStreetMap contributors' ),
				topo: raster(
					[ 'a', 'b', 'c' ].map( function ( s ) {
						return 'https://' + s + '.tile.opentopomap.org/{z}/{x}/{y}.png';
					} ),
					17,
					'&copy; OpenStreetMap contributors, SRTM | Style &copy; OpenTopoMap (CC-BY-SA)'
				),
				sat: raster( [ ESRI + 'World_Imagery/MapServer/tile/{z}/{y}/{x}' ], 19, 'Imagery: Esri, Maxar, Earthstar Geographics' ),
				route: { type: 'geojson', lineMetrics: true, data: { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: line } } },
			},
			layers: [
				{ id: 'bg', type: 'background', paint: { 'background-color': '#F1ECBC' } },
				{
					id: 'shade',
					type: 'hillshade',
					source: 'shade',
					paint: {
						'hillshade-shadow-color': '#6F6646',
						'hillshade-highlight-color': '#FFFCEB',
						'hillshade-accent-color': '#9C9068',
						'hillshade-exaggeration': 0.55,
					},
				},
				{ id: 'hydro', type: 'raster', source: 'hydro' },
				{ id: 'topo', type: 'raster', source: 'topo', layout: { visibility: 'none' } },
				{ id: 'sat', type: 'raster', source: 'sat', layout: { visibility: 'none' } },
				{ id: 'roads', type: 'raster', source: 'roads', paint: { 'raster-saturation': -1, 'raster-brightness-max': 0.78, 'raster-opacity': 0.7 } },
				{ id: 'names', type: 'raster', source: 'names', paint: { 'raster-saturation': -1, 'raster-opacity': 0.75 } },
				{ id: 'route-case', type: 'line', source: 'route', layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': '#fff', 'line-width': 9, 'line-opacity': 0 } },
				{ id: 'route', type: 'line', source: 'route', layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': ROUTE, 'line-width': 6 } },
				{ id: 'route-hit', type: 'line', source: 'route', paint: { 'line-color': '#000', 'line-opacity': 0, 'line-width': 24 } },
			],
			terrain: { source: 'dem', exaggeration: 1.5 },
		};
	}

	var BASES = {
		terrain: [ 'bg', 'shade', 'hydro', 'roads', 'names' ],
		topo: [ 'topo' ],
		satellite: [ 'sat', 'roads', 'names' ],
	};
	var BASE_LAYERS = [ 'shade', 'hydro', 'roads', 'names', 'topo', 'sat' ];

	function arrowImage() {
		var cv = document.createElement( 'canvas' );
		cv.width = cv.height = 36;
		var g = cv.getContext( '2d' );
		g.strokeStyle = '#fff';
		g.lineWidth = 6;
		g.lineCap = g.lineJoin = 'round';
		g.beginPath();
		g.moveTo( 12, 8 );
		g.lineTo( 25, 18 );
		g.lineTo( 12, 28 );
		g.stroke();
		return g.getImageData( 0, 0, 36, 36 );
	}

	function slug( s ) {
		return String( s )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-|-$/g, '' );
	}

	/* ------------------------------------------------------------------ */
	/* Build one map                                                       */
	/* ------------------------------------------------------------------ */

	function build( el, cfg ) {
		var c = densify( cfg.line ),
			S = stops( c, cfg ),
			touch = window.matchMedia && window.matchMedia( '(hover: none)' ).matches,
			hint = ( touch ? 'Drag' : 'Hover' ) + ' the profile to follow the course';

		el.classList.add( 'is-built' );
		el.innerHTML =
			'<div class="arv-cmap__stage">' +
			'<div class="arv-cmap__map"></div>' +
			'<div class="arv-cmap__bar arv-cmap__bases">' +
			'<button type="button" data-base="terrain" class="is-on">Terrain</button><button type="button" data-base="topo">Topo</button><button type="button" data-base="satellite">Satellite</button>' +
			'</div>' +
			'<div class="arv-cmap__bar arv-cmap__tools">' +
			'<button type="button" class="arv-cmap__fly" title="Fly the course">' + ICON_PLAY + '<span>Fly</span></button>' +
			'<button type="button" class="arv-cmap__grade" title="Colour the route by grade">Grade</button>' +
			'<button type="button" class="arv-cmap__3d" title="Tilt to 3D">3D</button>' +
			'<button type="button" class="arv-cmap__fs" title="Full screen" aria-label="Full screen">' + ICON_FS + '</button>' +
			'</div>' +
			'</div>' +
			'<div class="arv-cmap__info">' +
			'<div class="arv-cmap__top"><div class="arv-cmap__read"></div>' +
			'<div class="arv-cmap__links"><a href="#" class="arv-cmap__share">Share</a><a href="#" class="arv-cmap__gpx" title="For Garmin, COROS, Suunto, Strava or Apple Watch">Watch (GPX)</a>' +
			( cfg.pdf ? '<a href="' + esc( cfg.pdf ) + '">PDF map</a>' : '' ) +
			'<a href="' + esc( cfg.caltopo ) + '" target="_blank" rel="noopener">CalTopo</a></div></div>' +
			'<div class="arv-cmap__prof"><svg viewBox="0 0 1000 100" preserveAspectRatio="none" aria-hidden="true"></svg><div class="arv-cmap__cur"></div><div class="arv-cmap__dot"></div></div>' +
			'<div class="arv-cmap__axis"></div>' +
			'<div class="arv-cmap__legend">' +
			GRADES.map( function ( g ) {
				return '<span><i style="background:' + g[ 1 ] + '"></i>' + g[ 2 ] + '</span>';
			} ).join( '' ) +
			'</div>' +
			'</div>';

		var q = function ( s ) {
				return el.querySelector( s );
			},
			mapEl = q( '.arv-cmap__map' ),
			read = q( '.arv-cmap__read' ),
			prof = q( '.arv-cmap__prof' ),
			svg = q( '.arv-cmap__prof svg' ),
			cur = q( '.arv-cmap__cur' ),
			dot = q( '.arv-cmap__dot' ),
			bb = cfg.line.reduce(
				function ( b, p ) {
					return [
						[ Math.min( b[ 0 ][ 0 ], p[ 0 ] ), Math.min( b[ 0 ][ 1 ], p[ 1 ] ) ],
						[ Math.max( b[ 1 ][ 0 ], p[ 0 ] ), Math.max( b[ 1 ][ 1 ], p[ 1 ] ) ],
					];
				},
				[
					[ 180, 90 ],
					[ -180, -90 ],
				]
			);

		var m = new maplibregl.Map( {
			container: mapEl,
			style: style( cfg.line ),
			bounds: bb,
			fitBoundsOptions: { padding: 40 },
			scrollZoom: false,
			maxPitch: 75,
			attributionControl: { compact: true },
		} );
		m.addControl( new maplibregl.NavigationControl( { visualizePitch: true } ), 'top-left' );

		function marker( html, cls, ll, z ) {
			var e = document.createElement( 'div' );
			e.className = cls;
			e.innerHTML = html;
			if ( z ) {
				e.style.zIndex = z;
			}
			return new maplibregl.Marker( { element: e } ).setLngLat( ll ).addTo( m );
		}

		/* Start / finish, aid stations, mile markers -------------------- */

		var pin = cfg.logo ? '<img src="' + esc( cfg.logo ) + '" alt="">' : '';
		if ( S.loop ) {
			marker( pin + '<span>Start / Finish</span>', 'arv-cmap__pin' + ( pin ? '' : ' is-plain' ), S.start, 4 );
		} else {
			marker( pin + '<span>Start</span>', 'arv-cmap__pin' + ( pin ? '' : ' is-plain' ), S.start, 4 );
			marker( '<span>Finish</span>', 'arv-cmap__pin is-plain', S.finish, 4 );
		}

		var byMarker = {};
		S.aid.forEach( function ( a ) {
			if ( isStart( a.title ) ) {
				return;
			}
			( byMarker[ a.title ] = byMarker[ a.title ] || { a: a, at: [] } ).at.push( a.at );
		} );
		Object.keys( byMarker ).forEach( function ( k ) {
			var g = byMarker[ k ],
				mk = marker(
					'<span>' + esc( g.a.name ) + ' · mi ' + g.at.map( fmtMi ).join( ' & ' ) + '</span>',
					'arv-cmap__aid',
					g.a.ll,
					3
				);
			mk.getElement().addEventListener( 'click', function () {
				show( indexAt( c, g.at[ 0 ] ) );
			} );
		} );

		for ( var mile = 1; mile < mi( c.total ) - 0.25; mile++ ) {
			marker( String( mile ), 'arv-cmap__mile', c.pts[ indexAt( c, mile * 1609.344 ) ], 2 );
		}
		function mileVis() {
			el.classList.toggle( 'show-miles', m.getZoom() >= 12.5 );
		}
		m.on( 'zoom', mileVis );

		var me = marker( '', 'arv-cmap__me', c.pts[ 0 ], 5 ),
			meEl = me.getElement();

		m.on( 'load', function () {
			mileVis();
			var at = el.querySelector( '.maplibregl-ctrl-attrib' );
			if ( at ) {
				at.classList.remove( 'maplibregl-compact-show' );
			}
			m.addImage( 'arv-arrow', arrowImage(), { pixelRatio: 2 } );
			m.addLayer(
				{
					id: 'arrows',
					type: 'symbol',
					source: 'route',
					layout: {
						'symbol-placement': 'line',
						'symbol-spacing': 110,
						'icon-image': 'arv-arrow',
						'icon-allow-overlap': true,
						'icon-ignore-placement': true,
						'icon-rotation-alignment': 'map',
						'icon-pitch-alignment': 'map',
					},
				},
				'route-hit'
			);
		} );

		/* Bases ---------------------------------------------------------- */

		var base = 'terrain';
		el.querySelectorAll( '[data-base]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var n = b.getAttribute( 'data-base' );
				if ( n === base ) {
					return;
				}
				base = n;
				BASE_LAYERS.forEach( function ( id ) {
					m.setLayoutProperty( id, 'visibility', BASES[ n ].indexOf( id ) > -1 ? 'visible' : 'none' );
				} );
				el.querySelectorAll( '[data-base]' ).forEach( function ( x ) {
					x.classList.toggle( 'is-on', x === b );
				} );
			} );
		} );

		/* 2D / 3D fit ---------------------------------------------------- */

		var tilted = false,
			b3 = q( '.arv-cmap__3d' );

		function setTilt( t ) {
			tilted = t;
			b3.classList.toggle( 'is-on', t );
			b3.textContent = t ? '2D' : '3D';
		}

		/**
		 * The camera that frames the whole course, flat or tilted.
		 *
		 * cameraForBounds only knows a flat map, and a tilted one squeezes the
		 * far side and swells the near side, so in 3D the camera is fitted
		 * iteratively: project the course, rescale and recentre, repeat. Four
		 * rounds converge to within a few pixels.
		 */
		function frame() {
			m.resize();
			var portrait = mapEl.offsetHeight > mapEl.offsetWidth,
				br = tilted ? ( portrait ? -65 : -25 ) : 0,
				cam = m.cameraForBounds( bb, { padding: 40, bearing: br } );
			if ( ! cam ) {
				return null;
			}
			var o = { center: cam.center, zoom: cam.zoom, bearing: br, pitch: tilted ? 62 : 0 };
			if ( ! tilted ) {
				return o;
			}
			var keep = { center: m.getCenter(), zoom: m.getZoom(), bearing: m.getBearing(), pitch: m.getPitch() },
				W = mapEl.offsetWidth,
				H = mapEl.offsetHeight;
			m.jumpTo( o );
			for ( var it = 0; it < 4; it++ ) {
				var x0 = Infinity,
					x1 = -Infinity,
					y0 = Infinity,
					y1 = -Infinity;
				for ( var i = 0; i < cfg.line.length; i += Math.max( 1, Math.floor( cfg.line.length / 300 ) ) ) {
					var p = m.project( cfg.line[ i ] );
					x0 = Math.min( x0, p.x );
					x1 = Math.max( x1, p.x );
					y0 = Math.min( y0, p.y );
					y1 = Math.max( y1, p.y );
				}
				var s = Math.min( ( W - ( portrait ? 90 : 260 ) ) / Math.max( 1, x1 - x0 ), ( H - 170 ) / Math.max( 1, y1 - y0 ) );
				m.jumpTo( {
					center: m.unproject( [ ( x0 + x1 ) / 2, ( y0 + y1 ) / 2 + ( portrait ? 0 : 10 ) ] ),
					zoom: m.getZoom() + Math.max( -2, Math.min( 2, Math.log2( s ) ) ),
				} );
			}
			o = { center: m.getCenter(), zoom: m.getZoom(), bearing: br, pitch: 62 };
			m.jumpTo( keep );
			return o;
		}

		function fit( animate ) {
			var o = frame();
			if ( ! o ) {
				return;
			}
			if ( animate ) {
				o.duration = 1200;
				m.easeTo( o );
			} else {
				m.jumpTo( o );
			}
		}

		b3.addEventListener( 'click', function () {
			stopFly();
			setTilt( ! tilted );
			fit( true );
		} );
		m.on( 'pitchend', function () {
			if ( ! flying && m.getPitch() > 8 !== tilted ) {
				setTilt( m.getPitch() > 8 );
			}
		} );

		/* Full screen ---------------------------------------------------- */

		/**
		 * Full screen moves the whole element, profile included, to <body>.
		 * The Fullscreen API is used where it exists, but iPhone Safari only
		 * grants it to video, so the fixed-position fallback is what most
		 * runners actually get, and it has to escape any transformed
		 * ancestor (Cornerstone tabs) that would otherwise trap position:
		 * fixed inside the tab.
		 */
		var full = false,
			native = false,
			holder = null,
			bFs = q( '.arv-cmap__fs' );

		function fullscreen( on ) {
			if ( on === full ) {
				return;
			}
			full = on;
			bFs.classList.toggle( 'is-on', on );
			if ( on ) {
				holder = document.createComment( 'arv-cmap' );
				el.parentNode.insertBefore( holder, el );
				document.body.appendChild( el );
				el.classList.add( 'is-full' );
				document.documentElement.style.overflow = 'hidden';
				m.scrollZoom.enable();
				var req = el.requestFullscreen || el.webkitRequestFullscreen;
				if ( req ) {
					try {
						var r = req.call( el );
						native = true;
						if ( r && r.catch ) {
							r.catch( function () {
								native = false;
							} );
						}
					} catch ( e ) {
						native = false;
					}
				}
			} else {
				if ( native && ( document.fullscreenElement || document.webkitFullscreenElement ) ) {
					( document.exitFullscreen || document.webkitExitFullscreen ).call( document );
				}
				native = false;
				el.classList.remove( 'is-full' );
				holder.parentNode.insertBefore( el, holder );
				holder.remove();
				document.documentElement.style.overflow = '';
				m.scrollZoom.disable();
				el.scrollIntoView( { block: 'center' } );
			}
			setTimeout( function () {
				fit( false );
			}, 80 );
			setTimeout( function () {
				fit( false );
			}, 400 );
		}

		bFs.addEventListener( 'click', function () {
			fullscreen( ! full );
		} );
		[ 'fullscreenchange', 'webkitfullscreenchange' ].forEach( function ( n ) {
			document.addEventListener( n, function () {
				if ( native && ! ( document.fullscreenElement || document.webkitFullscreenElement ) ) {
					fullscreen( false );
				}
			} );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && full ) {
				fullscreen( false );
			}
		} );

		/* Share ---------------------------------------------------------- */

		var share = q( '.arv-cmap__share' );
		share.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var url = location.origin + location.pathname + '#course-' + cfg.key,
				title = cfg.race + ': ' + cfg.name + ' course';
			if ( navigator.share ) {
				navigator.share( { title: title, url: url } ).catch( function () {} );
			} else if ( navigator.clipboard ) {
				navigator.clipboard.writeText( url ).then( function () {
					share.textContent = 'Link copied';
					setTimeout( function () {
						share.textContent = 'Share';
					}, 2000 );
				} );
			} else {
				window.prompt( 'Copy this link', url );
			}
		} );

		/* Profile -------------------------------------------------------- */

		var P = null,
			runs = null,
			R0 = '';

		function X( d ) {
			return ( d / c.total ) * 1000;
		}

		function Y( e ) {
			var span = Math.max( P.hi - P.lo, 30 ),
				lo = P.lo - span * 0.25,
				hi = P.hi + span * 0.1;
			return 100 - 3 - ( ( e - lo ) / ( hi - lo ) ) * 92;
		}

		function drawProfile() {
			var step = Math.max( 1, Math.floor( c.pts.length / 700 ) ),
				out = '';
			runs.forEach( function ( r ) {
				var pts = [ X( c.cum[ r.i ] ).toFixed( 1 ) + ',100' ];
				for ( var i = r.i; i <= r.j; i += step ) {
					pts.push( X( c.cum[ i ] ).toFixed( 1 ) + ',' + Y( P.ele[ i ] ).toFixed( 1 ) );
				}
				pts.push( X( c.cum[ r.j ] ).toFixed( 1 ) + ',' + Y( P.ele[ r.j ] ).toFixed( 1 ) );
				pts.push( X( c.cum[ r.j ] ).toFixed( 1 ) + ',100' );
				out += '<polygon points="' + pts.join( ' ' ) + '" fill="' + GRADES[ r.b ][ 1 ] + '" fill-opacity=".9"/>';
			} );
			var line = [];
			for ( var i = 0; i < c.pts.length; i += step ) {
				line.push( X( c.cum[ i ] ).toFixed( 1 ) + ',' + Y( P.ele[ i ] ).toFixed( 1 ) );
			}
			out += '<polyline points="' + line.join( ' ' ) + '" fill="none" stroke="#111" stroke-opacity=".7" stroke-width="1.5" vector-effect="non-scaling-stroke"/>';
			svg.innerHTML = out;

			// HTML rather than SVG text: the SVG is stretched to the
			// column width, and stretched text is unreadable.
			S.laps.forEach( function ( d, i ) {
				var l = document.createElement( 'div' );
				l.className = 'arv-cmap__lap';
				l.style.left = ( d / c.total ) * 100 + '%';
				l.innerHTML = '<span>Lap ' + ( i + 2 ) + '</span>';
				prof.appendChild( l );
			} );
			if ( S.laps.length ) {
				var l1 = document.createElement( 'div' );
				l1.className = 'arv-cmap__lap is-first';
				l1.innerHTML = '<span>Lap 1</span>';
				prof.appendChild( l1 );
			}
			S.aid.forEach( function ( a ) {
				var s = document.createElement( 'i' );
				s.className = 'arv-cmap__aidp';
				s.style.left = ( a.at / c.total ) * 100 + '%';
				s.style.top = Y( P.ele[ indexAt( c, a.at ) ] ) + '%';
				prof.appendChild( s );
			} );

			var total = mi( c.total ),
				every = [ 1, 2, 5, 10, 20, 25, 50 ].filter( function ( s ) {
					return total / s <= 8;
				} )[ 0 ] || 50,
				ax = q( '.arv-cmap__axis' ),
				h = '<span style="left:0">0</span>';
			for ( var t = every; t < total - every * 0.4; t += every ) {
				h += '<span style="left:' + ( t / total ) * 100 + '%">' + t + '</span>';
			}
			h += '<span style="left:100%">' + fmtMi( c.total ) + ' mi</span>';
			ax.innerHTML = h;

			R0 =
				'<b>' + fmtMi( c.total ) + ' mi</b> · ' +
				( cfg.gain || ft( P.gain ) ).toLocaleString() + ' ft gain · ' +
				ft( P.lo ).toLocaleString() + ' to ' + ft( P.hi ).toLocaleString() + ' ft' +
				'<span class="arv-cmap__hint"> · ' + hint + '</span>';
			read.innerHTML = R0;
		}

		function show( i ) {
			if ( ! P ) {
				return;
			}
			var d = c.cum[ i ];
			prof.classList.add( 'is-on' );
			cur.style.left = ( d / c.total ) * 100 + '%';
			dot.style.left = cur.style.left;
			dot.style.top = Y( P.ele[ i ] ) + '%';
			me.setLngLat( c.pts[ i ] );
			meEl.style.display = 'block';

			var near = S.aid.filter( function ( a ) {
					return Math.abs( a.at - d ) < 130;
				} )[ 0 ],
				txt = '<b>Mile ' + fmtMi( d ) + '</b> · ' + ft( P.ele[ i ] ).toLocaleString() + ' ft · ' + Math.round( P.grade[ i ] ) + '%';
			if ( near ) {
				var next = S.aid.filter( function ( a ) {
						return a.at > near.at + 400;
					} )[ 0 ],
					to = next ? next.at - near.at : c.total - near.at;
				txt =
					'<b>' + esc( near.name ) + '</b> · mi ' + fmtMi( near.at ) +
					( near.row && near.row.cut ? ' · cutoff ' + esc( near.row.cut ) : '' ) +
					( near.row && near.row.svc ? ' · ' + esc( near.row.svc ) : '' ) +
					' · ' + fmtMi( to ) + ' mi to ' + ( next ? esc( next.name ) : 'finish' );
			}
			read.innerHTML = txt;
		}

		function hide() {
			prof.classList.remove( 'is-on' );
			meEl.style.display = 'none';
			read.innerHTML = R0;
		}

		function atProfile( ev ) {
			var r = prof.getBoundingClientRect(),
				x = ( ev.touches ? ev.touches[ 0 ].clientX : ev.clientX ) - r.left;
			stopFly();
			show( indexAt( c, Math.max( 0, Math.min( 1, x / r.width ) ) * c.total ) );
		}

		prof.addEventListener( 'mousemove', atProfile );
		prof.addEventListener( 'mouseleave', hide );
		prof.addEventListener( 'touchstart', atProfile, { passive: true } );
		prof.addEventListener( 'touchmove', atProfile, { passive: true } );
		prof.addEventListener( 'touchend', hide );

		/**
		 * Map to profile. Where the course overlaps itself (laps, out and
		 * backs) the nearest point is ambiguous, so a candidate far along the
		 * course from the last match costs a little extra: dragging along a
		 * shared stretch stays on the lap you started on.
		 */
		var lastAt = null;
		function atMap( e ) {
			var ll = [ e.lngLat.lng, e.lngLat.lat ],
				best = 0,
				bd = Infinity;
			for ( var i = 0; i < c.pts.length; i++ ) {
				var d = dist( c.pts[ i ], ll );
				if ( lastAt !== null && Math.abs( c.cum[ i ] - lastAt ) > 2400 ) {
					d *= 1.6;
				}
				if ( d < bd ) {
					bd = d;
					best = i;
				}
			}
			lastAt = c.cum[ best ];
			show( best );
		}
		m.on( 'mousemove', 'route-hit', function ( e ) {
			if ( flying ) {
				return;
			}
			m.getCanvas().style.cursor = 'crosshair';
			atMap( e );
		} );
		m.on( 'mouseleave', 'route-hit', function () {
			if ( flying ) {
				return;
			}
			m.getCanvas().style.cursor = '';
			lastAt = null;
			hide();
		} );
		m.on( 'click', 'route-hit', atMap );

		/* Grade view on the map ------------------------------------------ */

		var bGrade = q( '.arv-cmap__grade' ),
			graded = false;

		/**
		 * line-progress runs along the line as MapLibre measures it, which is
		 * the same line the runs were cut from, so fractions of our distance
		 * land within metres of where they should.
		 */
		function gradeExpr() {
			var e = [ 'step', [ 'line-progress' ], GRADES[ runs[ 0 ].b ][ 1 ] ],
				last = 0;
			for ( var i = 1; i < runs.length; i++ ) {
				var f = c.cum[ runs[ i ].i ] / c.total;
				if ( f > last ) {
					e.push( f, GRADES[ runs[ i ].b ][ 1 ] );
					last = f;
				}
			}
			// One band end to end (a flat course) is a plain colour: step
			// needs at least one stop.
			return e.length > 3 ? e : e[ 2 ];
		}

		bGrade.addEventListener( 'click', function () {
			if ( ! runs ) {
				return;
			}
			graded = ! graded;
			bGrade.classList.toggle( 'is-on', graded );
			el.classList.toggle( 'is-graded', graded );
			m.setPaintProperty( 'route', 'line-gradient', graded ? gradeExpr() : undefined );
			m.setPaintProperty( 'route-case', 'line-opacity', graded ? 1 : 0 );
		} );

		/* Flyover -------------------------------------------------------- */

		/**
		 * The camera runs the course in 3D with the profile cursor in step.
		 * Bearing eases toward the direction a few hundred metres ahead
		 * rather than snapping to each segment, which on a technical trail
		 * would spin the map on every switchback.
		 */
		var flying = false,
			raf = 0,
			bFly = q( '.arv-cmap__fly' );

		function stopFly() {
			if ( ! flying ) {
				return;
			}
			flying = false;
			cancelAnimationFrame( raf );
			bFly.classList.remove( 'is-on' );
			bFly.innerHTML = ICON_PLAY + '<span>Fly</span>';
		}

		function angle( to, from ) {
			return ( ( to - from + 540 ) % 360 ) - 180;
		}

		function fly() {
			if ( flying ) {
				stopFly();
				setTilt( m.getPitch() > 8 );
				return;
			}
			flying = true;
			bFly.classList.add( 'is-on' );
			bFly.innerHTML = ICON_STOP + '<span>Stop</span>';
			setTilt( true );

			var dur = Math.min( 120, Math.max( 30, mi( c.total ) * 3 ) ) * 1000,
				zoom = 15.3,
				look = function ( d ) {
					return bearing( c.pts[ indexAt( c, d ) ], c.pts[ indexAt( c, Math.min( c.total, d + 300 ) ) ] );
				},
				br = look( 0 ),
				t0 = null;

			m.flyTo( { center: c.pts[ 0 ], zoom: zoom, pitch: 60, bearing: br, duration: 1800 } );
			m.once( 'moveend', function () {
				if ( ! flying ) {
					return;
				}
				function step( ts ) {
					if ( ! flying ) {
						return;
					}
					if ( null === t0 ) {
						t0 = ts;
					}
					var f = Math.min( 1, ( ts - t0 ) / dur ),
						d = f * c.total,
						i = indexAt( c, d );
					br += angle( look( d ), br ) * 0.035;
					m.jumpTo( { center: c.pts[ i ], bearing: br, pitch: 60, zoom: zoom } );
					show( i );
					if ( f < 1 ) {
						raf = requestAnimationFrame( step );
					} else {
						stopFly();
						hide();
						fit( true );
					}
				}
				raf = requestAnimationFrame( step );
			} );
		}

		bFly.addEventListener( 'click', fly );
		[ 'mousedown', 'touchstart', 'wheel' ].forEach( function ( n ) {
			mapEl.addEventListener(
				n,
				function () {
					if ( flying ) {
						stopFly();
						hide();
					}
				},
				{ passive: true }
			);
		} );

		/* GPX ------------------------------------------------------------ */

		var lineEle = null;

		q( '.arv-cmap__gpx' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var x = function ( s ) {
					return esc( s ).replace( /'/g, '&apos;' );
				},
				name = cfg.race + ' ' + cfg.name,
				g =
					'<?xml version="1.0" encoding="UTF-8"?>\n<gpx version="1.1" creator="Aravaipa Running" xmlns="http://www.topografix.com/GPX/1/1">\n' +
					'<metadata><name>' + x( name ) + '</name><link href="' + x( location.origin + location.pathname ) + '"><text>Aravaipa Running</text></link></metadata>\n';
			g += '<wpt lat="' + S.start[ 1 ] + '" lon="' + S.start[ 0 ] + '"><name>' + ( S.loop ? 'Start / Finish' : 'Start' ) + '</name><sym>Flag, Blue</sym></wpt>\n';
			if ( ! S.loop ) {
				g += '<wpt lat="' + S.finish[ 1 ] + '" lon="' + S.finish[ 0 ] + '"><name>Finish</name><sym>Flag, Blue</sym></wpt>\n';
			}
			Object.keys( byMarker ).forEach( function ( k ) {
				var a = byMarker[ k ];
				g +=
					'<wpt lat="' + a.a.ll[ 1 ] + '" lon="' + a.a.ll[ 0 ] + '"><name>' + x( a.a.name ) + '</name><desc>Mile ' + a.at.map( fmtMi ).join( ' & ' ) +
					( a.a.row && a.a.row.cut ? ', cutoff ' + x( a.a.row.cut ) : '' ) + '</desc><sym>Water Source</sym></wpt>\n';
			} );
			g += '<trk><name>' + x( name ) + '</name><trkseg>\n';
			cfg.line.forEach( function ( p, i ) {
				g += '<trkpt lat="' + p[ 1 ] + '" lon="' + p[ 0 ] + '">' + ( lineEle && lineEle[ i ] !== null ? '<ele>' + lineEle[ i ].toFixed( 1 ) + '</ele>' : '' ) + '</trkpt>\n';
			} );
			g += '</trkseg></trk>\n</gpx>\n';
			var a = document.createElement( 'a' );
			a.href = URL.createObjectURL( new Blob( [ g ], { type: 'application/gpx+xml' } ) );
			a.download = slug( name ) + '.gpx';
			document.body.appendChild( a );
			a.click();
			setTimeout( function () {
				URL.revokeObjectURL( a.href );
				a.remove();
			}, 1000 );
		} );

		/* Elevation arrives ---------------------------------------------- */

		read.textContent = 'Loading elevation…';
		elevations( c.pts ).then( function ( raw ) {
			P = profile( c, raw );
			runs = gradeRuns( c, P.grade );
			drawProfile();
			// Flat courses barely register in 3D at true scale.
			m.setTerrain( { source: 'dem', exaggeration: P.hi - P.lo < 120 ? 2.2 : 1.3 } );
		} );
		elevations( cfg.line ).then( function ( e ) {
			lineEle = e;
		} );

		/* Size ----------------------------------------------------------- */

		if ( window.ResizeObserver ) {
			var lw = 0;
			new ResizeObserver( function () {
				var w = mapEl.offsetWidth;
				if ( w && Math.abs( w - lw ) > 4 ) {
					lw = w;
					el.classList.toggle( 'is-narrow', w < 520 );
					if ( ! flying ) {
						fit( false );
					}
				}
			} ).observe( mapEl );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function config( el ) {
		var s = el.querySelector( 'script[data-arv-cmap]' );
		try {
			return s ? JSON.parse( s.textContent ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function boot() {
		if ( ! window.maplibregl ) {
			return;
		}
		document.querySelectorAll( '.arv-cmap:not([data-watched])' ).forEach( function ( el ) {
			var cfg = config( el );
			el.setAttribute( 'data-watched', '' );
			if ( ! cfg || ! cfg.line || cfg.line.length < 2 ) {
				return;
			}
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( e ) {
					if ( e.isIntersecting && el.offsetWidth > 0 && ! el.classList.contains( 'is-built' ) ) {
						io.disconnect();
						build( el, cfg );
					}
				} );
			} );
			io.observe( el );
		} );
	}

	/**
	 * #course-<distance> opens the tab holding that distance's map and
	 * scrolls to it. This is what Share hands out, so a link to the 10K
	 * course lands on the 10K course rather than the Half in tab one.
	 */
	function deepLink() {
		var m = location.hash.match( /^#course-([a-z0-9-]+)$/ );
		if ( ! m ) {
			return;
		}
		var el = document.querySelector( '.arv-cmap[data-key="' + m[ 1 ] + '"]' );
		if ( ! el ) {
			return;
		}
		var panel = el.closest( '[role=tabpanel]' ),
			tab = panel && panel.id && document.querySelector( '[aria-controls="' + panel.id + '"]' );
		if ( tab ) {
			tab.click();
		}
		setTimeout( function () {
			el.scrollIntoView( { block: 'center' } );
		}, 300 );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
	window.addEventListener( 'load', function () {
		setTimeout( deepLink, 300 );
	} );
} )();
