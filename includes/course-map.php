<?php
/**
 * An interactive course map for one distance, built from a CalTopo map.
 *
 * Race pages have shown courses three ways: a PDF, a Strava embed, or a
 * screenshot of CalTopo. None of them tells a runner where the climbing is
 * or how far apart the aid stations are without opening something else. The
 * Louisville page got a hand-built map that did, as a script pasted into a
 * raw content block, which works for exactly one race. This is that map
 * made reusable: the input is the CalTopo map ID the course team already
 * maintains, so the page can never drift from the map they edit.
 *
 * Everything that needs elevation (the profile, gain, grade colours, GPX
 * elevations) is worked out in the browser from the same terrain tiles the
 * 3D view loads anyway, rather than here. Doing it server side means
 * fetching dozens of tiles during a page render the first time a course is
 * viewed, and a slow race page is a worse failure than a profile that
 * appears half a second after the map.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_CMAP_MAPLIBRE_VERSION', '4.7.1' );

/**
 * The map ID out of a CalTopo link, or an ID passed straight through.
 *
 * @param string $value
 * @return string
 */
function arv_cmap_caltopo_id( $value ) {
	$value = trim( (string) $value );

	if ( preg_match( '~caltopo\.com/m/([A-Za-z0-9]+)~', $value, $m ) ) {
		return strtoupper( $m[1] );
	}

	// Anchored for the same reason as arv_race_video_id(): salvaging a few
	// characters out of a URL this failed to parse would show the wrong map.
	if ( preg_match( '~^[A-Za-z0-9]{4,12}$~', $value ) ) {
		return strtoupper( $value );
	}

	return '';
}

/**
 * Ramer-Douglas-Peucker on [lng, lat] pairs, tolerance in metres.
 *
 * CalTopo lines traced off a GPS track carry a vertex every metre or two,
 * which is hundreds of kilobytes of inline JSON for a 100 mile course and
 * nothing a map at race-page zoom can show. Iterative rather than
 * recursive so a long course cannot hit PHP's stack.
 *
 * @param array $pts
 * @param float $tol
 * @return array
 */
function arv_cmap_simplify( $pts, $tol ) {
	$n = count( $pts );
	if ( $n < 3 ) {
		return $pts;
	}

	// Local equirectangular metres. Accurate to well under the tolerance
	// across any single race course.
	$lat0 = deg2rad( $pts[0][1] );
	$kx   = 111320 * cos( $lat0 );
	$ky   = 110540;

	$keep           = array_fill( 0, $n, false );
	$keep[0]        = true;
	$keep[ $n - 1 ] = true;
	$stack          = array( array( 0, $n - 1 ) );

	while ( $stack ) {
		list( $a, $b ) = array_pop( $stack );
		$ax            = $pts[ $a ][0] * $kx;
		$ay            = $pts[ $a ][1] * $ky;
		$dx            = $pts[ $b ][0] * $kx - $ax;
		$dy            = $pts[ $b ][1] * $ky - $ay;
		$len2          = $dx * $dx + $dy * $dy;
		$max           = 0;
		$idx           = -1;

		for ( $i = $a + 1; $i < $b; $i++ ) {
			$px = $pts[ $i ][0] * $kx - $ax;
			$py = $pts[ $i ][1] * $ky - $ay;
			$t  = $len2 > 0 ? max( 0, min( 1, ( $px * $dx + $py * $dy ) / $len2 ) ) : 0;
			$ex = $px - $t * $dx;
			$ey = $py - $t * $dy;
			$d  = $ex * $ex + $ey * $ey;
			if ( $d > $max ) {
				$max = $d;
				$idx = $i;
			}
		}

		if ( $idx > 0 && $max > $tol * $tol ) {
			$keep[ $idx ] = true;
			$stack[]      = array( $a, $idx );
			$stack[]      = array( $idx, $b );
		}
	}

	$out = array();
	foreach ( $pts as $i => $p ) {
		if ( $keep[ $i ] ) {
			$out[] = array( round( $p[0], 6 ), round( $p[1], 6 ) );
		}
	}

	return $out;
}

/**
 * Lines and markers from a CalTopo map's public JSON.
 *
 * @param array $features
 * @return array{lines:array,marks:array}
 */
function arv_cmap_parse( $features ) {
	$lines = array();
	$marks = array();

	foreach ( $features as $f ) {
		$g     = isset( $f['geometry'] ) ? $f['geometry'] : array();
		$p     = isset( $f['properties'] ) ? $f['properties'] : array();
		$type  = isset( $g['type'] ) ? $g['type'] : '';
		$title = isset( $p['title'] ) ? trim( (string) $p['title'] ) : '';

		if ( 'LineString' === $type || 'MultiLineString' === $type ) {
			$coords = 'LineString' === $type ? $g['coordinates'] : array_merge( ...$g['coordinates'] );
			$pts    = array();
			foreach ( $coords as $c ) {
				if ( isset( $c[0], $c[1] ) ) {
					$pts[] = array( (float) $c[0], (float) $c[1] );
				}
			}
			if ( count( $pts ) > 1 ) {
				$lines[] = array(
					'title'  => $title,
					'coords' => arv_cmap_simplify( $pts, 2.5 ),
				);
			}
		} elseif ( 'Point' === $type && isset( $g['coordinates'][1] ) ) {
			$marks[] = array(
				'title' => $title,
				'sym'   => isset( $p['marker-symbol'] ) ? (string) $p['marker-symbol'] : '',
				'll'    => array( round( (float) $g['coordinates'][0], 6 ), round( (float) $g['coordinates'][1], 6 ) ),
			);
		}
	}

	return array(
		'lines' => $lines,
		'marks' => $marks,
	);
}

/**
 * A CalTopo map's lines and markers, cached.
 *
 * Twelve hours fresh, because course teams do edit these in race week and
 * the page should follow within the day. The last good copy is also kept
 * in an option with no expiry: if CalTopo is down, or the map is made
 * private, the page keeps showing the course it last had rather than an
 * empty box, which on race morning is the difference that matters.
 *
 * @param string $id
 * @return array{lines:array,marks:array}
 */
function arv_cmap_course( $id ) {
	$key    = 'arv_cmap_' . strtolower( $id );
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$course = null;
	$res    = wp_remote_get( 'https://caltopo.com/api/v1/map/' . rawurlencode( $id ) . '/since/0', array( 'timeout' => 8 ) );

	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $body['result']['state']['features'] ) && is_array( $body['result']['state']['features'] ) ) {
			$course = arv_cmap_parse( $body['result']['state']['features'] );
		}
	}

	if ( $course && $course['lines'] ) {
		set_transient( $key, $course, 12 * HOUR_IN_SECONDS );
		update_option( $key, $course, false );
		return $course;
	}

	// A failure caches briefly, the same trade as arv_race_video_meta():
	// otherwise a dead map ID re-requests on every page load.
	$last = get_option( $key );
	$last = is_array( $last ) ? $last : array(
		'lines' => array(),
		'marks' => array(),
	);
	set_transient( $key, $last, 30 * MINUTE_IN_SECONDS );

	return $last;
}

/**
 * Aid station rows: `CalTopo marker title | Display name | Cutoff | Services`.
 *
 * Keyed by the marker title as written in CalTopo, lowercased and trimmed,
 * since that is the one thing both sides agree on. Every column after the
 * first is optional, so a row can just fix a misspelled marker name.
 *
 * @param string $text
 * @return array
 */
function arv_cmap_aid_rows( $text ) {
	$rows = array();

	foreach ( preg_split( '~\r\n|\r|\n~', (string) $text ) as $line ) {
		$cols = array_map( 'trim', explode( '|', $line ) );
		if ( '' === $cols[0] ) {
			continue;
		}
		$rows[ strtolower( $cols[0] ) ] = array(
			'name' => isset( $cols[1] ) ? $cols[1] : '',
			'cut'  => isset( $cols[2] ) ? $cols[2] : '',
			'svc'  => isset( $cols[3] ) ? $cols[3] : '',
		);
	}

	return $rows;
}

/**
 * The map's markup: an empty container and its config as inline JSON.
 *
 * @param array $a caltopo, line, name, pdf, logo, aid.
 * @return string
 */
function arv_course_map_render( $a ) {
	$id = arv_cmap_caltopo_id( isset( $a['caltopo'] ) ? $a['caltopo'] : '' );
	if ( '' === $id ) {
		return '';
	}

	$course = arv_cmap_course( $id );
	if ( ! $course['lines'] ) {
		return '';
	}

	// The named line, else the longest: a per-distance CalTopo map usually
	// holds one course line plus the odd access road or parking spur.
	$want = strtolower( trim( isset( $a['line'] ) ? (string) $a['line'] : '' ) );
	$line = null;
	foreach ( $course['lines'] as $l ) {
		if ( '' !== $want && strtolower( $l['title'] ) === $want ) {
			$line = $l;
			break;
		}
		if ( '' === $want && ( ! $line || count( $l['coords'] ) > count( $line['coords'] ) ) ) {
			$line = $l;
		}
	}
	if ( ! $line ) {
		return '';
	}

	$name = trim( isset( $a['name'] ) ? (string) $a['name'] : '' );
	$name = '' !== $name ? $name : $line['title'];

	$config = array(
		'key'     => sanitize_title( $name ),
		'name'    => $name,
		'race'    => wp_strip_all_tags( get_the_title() ),
		'caltopo' => 'https://caltopo.com/m/' . $id,
		'pdf'     => isset( $a['pdf'] ) ? esc_url_raw( $a['pdf'] ) : '',
		'logo'    => isset( $a['logo'] ) ? esc_url_raw( $a['logo'] ) : '',
		'line'    => $line['coords'],
		'marks'   => $course['marks'],
		'aid'     => (object) arv_cmap_aid_rows( isset( $a['aid'] ) ? $a['aid'] : '' ),
	);

	arv_course_map_enqueue();

	return '<div class="arv-cmap" data-key="' . esc_attr( $config['key'] ) . '">'
		. '<script type="application/json" data-arv-cmap>' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . '</script>'
		. '<noscript><a href="' . esc_url( $config['caltopo'] ) . '">' . esc_html( $name ) . ' course map</a></noscript>'
		. '</div>';
}

/**
 * MapLibre and the map script, at the moment a map renders. Same reasoning
 * as arv_race_map_enqueue(): Cornerstone assembles element markup at render
 * time, so render time is the only reliable place to know a map is on the
 * page.
 */
function arv_course_map_enqueue() {
	wp_enqueue_style(
		'maplibre-gl',
		'https://unpkg.com/maplibre-gl@' . ARV_CMAP_MAPLIBRE_VERSION . '/dist/maplibre-gl.css',
		array(),
		ARV_CMAP_MAPLIBRE_VERSION
	);

	wp_enqueue_script(
		'maplibre-gl',
		'https://unpkg.com/maplibre-gl@' . ARV_CMAP_MAPLIBRE_VERSION . '/dist/maplibre-gl.js',
		array(),
		ARV_CMAP_MAPLIBRE_VERSION,
		true
	);

	wp_enqueue_script(
		'aravaipa-course-map',
		ARV_ELEMENTS_URL . 'assets/aravaipa-course-map.js',
		array( 'maplibre-gl' ),
		ARV_ELEMENTS_VERSION,
		true
	);
}

/**
 * [arv_course_map caltopo="PMU2Q4J" name="Half Marathon"] for a race page.
 *
 * Aid rows go in the shortcode body, one per line, since a shortcode
 * attribute cannot hold line breaks.
 *
 * @param array  $atts
 * @param string $content
 * @return string
 */
function arv_course_map_shortcode( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'caltopo' => '',
			'line'    => '',
			'name'    => '',
			'pdf'     => '',
			'logo'    => '',
		),
		$atts,
		'arv_course_map'
	);

	$atts['aid'] = wp_strip_all_tags( str_replace( array( '<br />', '<br>', '</p>' ), "\n", (string) $content ) );

	return arv_course_map_render( $atts );
}
add_shortcode( 'arv_course_map', 'arv_course_map_shortcode' );
