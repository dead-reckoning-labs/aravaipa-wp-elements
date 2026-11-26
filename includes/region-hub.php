<?php
/**
 * Region hub landing page: [arv_region_hub region="ultra-adventures"].
 *
 * One long page for a race division: a photo hero, the season's races as
 * photo tiles (drawn by arv_races_card(), the same card the Upcoming Races
 * element uses), a numbers strip, full-bleed photo bands, a terrain map,
 * the story, the history by era, past races, films, a gallery, socials and
 * the newsletter, and last an In Remembrance section behind an option.
 *
 * Race facts (date, distances, venue, logo, Register link) come from the
 * race store at render time, so the hub never carries a second copy of a
 * date that can drift. The hub data file (includes/data/hubs/<region>.json)
 * holds only what the store does not: photos, copy, archive links, and a
 * race that has no store entry yet, which renders as a date-coming-soon
 * tile with Race Info and no Register button.
 *
 * Nothing shows anywhere until a page holds the shortcode.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A hub's data file, decoded, or null.
 *
 * The region is reduced to a slug before it touches the filesystem, so a
 * shortcode attribute can never reach outside includes/data/hubs.
 *
 * @param string $region Region slug.
 * @return array|null
 */
function arv_region_hub_data( $region ) {
	$region = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $region ) );
	if ( '' === $region ) {
		return null;
	}

	$file = ARV_ELEMENTS_PATH . 'includes/data/hubs/' . $region . '.json';
	if ( ! is_readable( $file ) ) {
		return null;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a bundled file, not a URL.
	$data = json_decode( (string) file_get_contents( $file ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * The hub's races, in the order the hub file lists them.
 *
 * Matched to the store by name rather than by region term, so a race that
 * belongs to two hubs (Antelope Canyon is an Arizona race and an Ultra
 * Adventures race) needs no second tag to appear on both, and the hub file
 * decides the order. A listed race the store does not have is dropped,
 * unless the hub gives it a date-coming-soon entry of its own.
 *
 * @param array $hub   Hub data.
 * @param array $store Races from arv_race_store_get() (all regions).
 * @return array<int, array{race: array, entry: array, soon: bool}>
 */
function arv_region_hub_races( $hub, $store ) {
	$by_name = array();
	foreach ( $store as $race ) {
		if ( isset( $race['name'] ) ) {
			$by_name[ strtolower( trim( $race['name'] ) ) ] = $race;
		}
	}

	$out = array();
	foreach ( isset( $hub['races'] ) && is_array( $hub['races'] ) ? $hub['races'] : array() as $entry ) {
		if ( empty( $entry['name'] ) ) {
			continue;
		}
		$key = strtolower( trim( $entry['name'] ) );

		if ( isset( $by_name[ $key ] ) ) {
			$race = $by_name[ $key ];

			// The race page's own Register link, where it differs from the
			// store's. The store keeps UltraSignup's series link (dtid) and
			// the race pages carry the edition link (did); a hub must send
			// people where the race page does, byte for byte. Only while the
			// store still points at UltraSignup: on a race's RaceGoat launch
			// day the store changes and wins again, so this can never hold
			// a race back on a stale UltraSignup page.
			if ( ! empty( $entry['register_page'] )
				&& preg_match( '#^https://(www\.)?ultrasignup\.com/#i', (string) $race['register'] )
				&& preg_match( '#^https://(www\.)?ultrasignup\.com/register\.aspx\?did=\d+$#i', (string) $entry['register_page'] ) ) {
				$race['register'] = $entry['register_page'];
			}

			$out[] = array(
				'race'  => $race,
				'entry' => $entry,
				'soon'  => false,
			);
		} elseif ( ! empty( $entry['soon'] ) && is_array( $entry['soon'] ) ) {
			$soon  = $entry['soon'];
			$out[] = array(
				'race'  => array(
					'name'      => $entry['name'],
					// A date the hub can stand behind (Master Events) while the
					// race has no store entry: shown, but still no <time>, no
					// schema and no Register, because nothing is on sale yet.
					'dated'     => ! empty( $soon['dated'] ),
					'iso'       => '',
					'display'   => isset( $soon['display'] ) ? $soon['display'] : __( 'Date coming soon', 'aravaipa-elements' ),
					'distances' => isset( $soon['distances'] ) ? $soon['distances'] : '',
					'venue'     => isset( $soon['venue'] ) ? $soon['venue'] : '',
					'location'  => isset( $soon['location'] ) ? $soon['location'] : '',
					'page'      => isset( $soon['page'] ) ? $soon['page'] : '',
					'image'     => isset( $soon['image'] ) ? $soon['image'] : '',
					'lat'       => isset( $soon['lat'] ) ? $soon['lat'] : '',
					'lng'       => isset( $soon['lng'] ) ? $soon['lng'] : '',
					// Never a register link: a race with no date has nothing
					// to sell yet.
					'register'  => '',
				),
				'entry' => $entry,
				'soon'  => true,
			);
		}
	}

	return $out;
}

/**
 * A store race made ready for a hub tile, without touching the store.
 *
 * - The year on the date line. The homepage list is "what is next" and can
 *   leave it off; a hub is a season page read months ahead, where "March 6"
 *   alone is ambiguous.
 * - One distance separator. The store keeps what its source said, which for
 *   one race is "100 Mile, 100K, 60K" and for the next "50 Mile | 55K".
 * - www on the site's own links, so Race Info does not cost a redirect.
 *
 * @param array $race
 * @return array
 */
function arv_region_hub_tidy( $race ) {
	if ( ! empty( $race['iso'] ) && ! empty( $race['display'] ) && ! preg_match( '/\b(19|20)\d\d\b/', $race['display'] ) ) {
		$race['display'] .= ', ' . substr( $race['iso'], 0, 4 );
	}
	if ( ! empty( $race['distances'] ) && false === strpos( $race['distances'], '|' ) ) {
		$race['distances'] = implode( ' | ', array_filter( array_map( 'trim', explode( ',', $race['distances'] ) ), 'strlen' ) );
	}
	if ( ! empty( $race['page'] ) ) {
		$race['page'] = preg_replace( '#^https?://aravaiparunning\.com/#i', 'https://www.aravaiparunning.com/', $race['page'] );
	}
	return $race;
}

/**
 * A Photon (i0.wp.com) URL at a given width; anything else unchanged.
 *
 * @param string $url
 * @param int    $w
 * @return string
 */
function arv_region_hub_img( $url, $w ) {
	return function_exists( 'arv_card_image_url' ) ? arv_card_image_url( $url, $w ) : $url;
}

/**
 * srcset for a Photon URL, or '' for anything Photon does not resize.
 *
 * @param string $url
 * @param int[]  $widths
 * @return string
 */
function arv_region_hub_srcset( $url, $widths ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || ! preg_match( '~(^|\.)wp\.com$~i', $host ) ) {
		return '';
	}
	$set = array();
	foreach ( $widths as $w ) {
		$set[] = arv_region_hub_img( $url, $w ) . ' ' . (int) $w . 'w';
	}
	return implode( ', ', $set );
}

/**
 * Project a longitude and latitude the way the bundled state outlines were
 * drawn: d3.geoAlbersUsa's lower-48 conic equal-area, scale 1300, translate
 * 487.5 305 (includes/data/us-states-albers.json says so in its header).
 * Pins placed with the same projection land on the same map.
 *
 * @param float $lng
 * @param float $lat
 * @return float[] x, y in the outline's 975x610 space.
 */
function arv_region_hub_project( $lng, $lat ) {
	$rad  = M_PI / 180;
	$phi0 = 29.5 * $rad;
	$phi1 = 45.5 * $rad;
	$n    = ( sin( $phi0 ) + sin( $phi1 ) ) / 2;
	$c    = 1 + sin( $phi0 ) * ( 2 * $n - sin( $phi0 ) );
	$r0   = sqrt( $c ) / $n;

	$raw = function ( $lambda, $phi ) use ( $n, $c, $r0 ) {
		$r = sqrt( $c - 2 * $n * sin( $phi ) ) / $n;
		return array( $r * sin( $lambda * $n ), $r0 - $r * cos( $lambda * $n ) );
	};

	$k      = 1300;
	$center = $raw( -0.6 * $rad, 38.7 * $rad );
	$p      = $raw( ( (float) $lng + 96 ) * $rad, (float) $lat * $rad );

	return array( 487.5 + $k * ( $p[0] - $center[0] ), 305 - $k * ( $p[1] - $center[1] ) );
}

/**
 * The map: the hub's states in outline with a numbered pin per race.
 *
 * Inline SVG off the outlines the Racing Team form already ships, so it
 * costs no tile server, no script and no extra request, and stays sharp on
 * a phone. Races without real coordinates get no pin rather than a guess.
 *
 * @param array $hub   Hub data.
 * @param array $items From arv_region_hub_races().
 * @return string
 */
function arv_region_hub_map( $hub, $items ) {
	if ( ! empty( $hub['map']['image'] ) ) {
		return arv_region_hub_map_image( $hub['map'] );
	}
	if ( empty( $hub['map'] ) || ! function_exists( 'arv_rt_apply_data' ) ) {
		return '';
	}
	$atlas = arv_rt_apply_data( 'us-states-albers.json' );
	if ( ! is_array( $atlas ) || empty( $atlas['states'] ) ) {
		return '';
	}
	$states = isset( $hub['map']['states'] ) ? (array) $hub['map']['states'] : array();

	$pins = array();
	foreach ( $items as $i => $item ) {
		$lat = isset( $item['race']['lat'] ) ? $item['race']['lat'] : '';
		$lng = isset( $item['race']['lng'] ) ? $item['race']['lng'] : '';
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			continue;
		}
		$xy     = arv_region_hub_project( (float) $lng, (float) $lat );
		$pins[] = array(
			'n'    => $i + 1,
			'x'    => $xy[0],
			'y'    => $xy[1],
			'name' => $item['race']['name'],
			'url'  => isset( $item['race']['page'] ) ? $item['race']['page'] : '',
		);
	}
	if ( empty( $pins ) ) {
		return '';
	}

	// Frame: the hub's own states whole, so the pins read against places
	// people know rather than an anonymous crop, padded a little. Falls back
	// to the pins when the hub names no state the outlines have.
	$xs = array_column( $pins, 'x' );
	$ys = array_column( $pins, 'y' );
	foreach ( $states as $code ) {
		if ( isset( $atlas['states'][ $code ] ) && preg_match_all( '/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $atlas['states'][ $code ], $sm ) ) {
			$xs[] = min( $sm[1] );
			$xs[] = max( $sm[1] );
			$ys[] = min( $sm[2] );
			$ys[] = max( $sm[2] );
		}
	}
	$pad  = 0.06 * max( max( $xs ) - min( $xs ), max( $ys ) - min( $ys ), 20 );
	$x0   = min( $xs ) - $pad;
	$y0   = min( $ys ) - $pad;
	$w    = max( $xs ) - min( $xs ) + 2 * $pad;
	$h    = max( $ys ) - min( $ys ) + 2 * $pad;
	$view = sprintf( '%.1f %.1f %.1f %.1f', $x0, $y0, $w, $h );
	$unit = max( $w, $h ) / 160;

	$svg  = '<svg class="arv-hub__map-svg" viewBox="' . esc_attr( $view ) . '" role="img" aria-label="' . esc_attr( __( 'Map of the race locations', 'aravaipa-elements' ) ) . '">';
	foreach ( $atlas['states'] as $code => $d ) {
		$on   = in_array( $code, $states, true );
		$svg .= '<path class="arv-hub__state' . ( $on ? ' arv-hub__state--on' : '' ) . '" d="' . esc_attr( $d ) . '" />';
	}
	foreach ( $states as $code ) {
		if ( isset( $atlas['names'][ $code ] ) && isset( $atlas['states'][ $code ] ) ) {
			// Label at the state's own bbox middle, read off the path.
			preg_match_all( '/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $atlas['states'][ $code ], $m );
			if ( ! empty( $m[1] ) ) {
				$lx   = ( min( $m[1] ) + max( $m[1] ) ) / 2;
				$ly   = ( min( $m[2] ) + max( $m[2] ) ) / 2;
				$svg .= sprintf( '<text class="arv-hub__state-name" x="%.1f" y="%.1f" font-size="%.2f">%s</text>', $lx, $ly, 5.5 * $unit, esc_html( strtoupper( $atlas['names'][ $code ] ) ) );
			}
		}
	}
	foreach ( $pins as $pin ) {
		$svg .= sprintf(
			'<g class="arv-hub__pin"><circle cx="%.1f" cy="%.1f" r="%.2f" /><text x="%.1f" y="%.1f" font-size="%.2f">%d</text></g>',
			$pin['x'],
			$pin['y'],
			4.2 * $unit,
			$pin['x'],
			$pin['y'] + 1.9 * $unit,
			5.2 * $unit,
			$pin['n']
		);
	}
	$svg .= '</svg>';

	$key = '<ol class="arv-hub__map-key">';
	foreach ( $pins as $pin ) {
		$name = esc_html( $pin['name'] );
		if ( '' !== $pin['url'] ) {
			$name = '<a href="' . esc_url( $pin['url'] ) . '">' . $name . '</a>';
		}
		$key .= '<li><span class="arv-hub__map-n">' . (int) $pin['n'] . '</span>' . $name . '</li>';
	}
	$key .= '</ol>';

	$map = $hub['map'];

	return '<section class="arv-hub__section arv-hub__map" aria-labelledby="arv-hub-map">'
		. '<div class="arv-hub__inner arv-hub__split">'
		. '<div class="arv-hub__split-text">'
		. arv_region_hub_head( $map, 'arv-hub-map' )
		. $key
		. ( ! empty( $map['note'] ) ? '<p class="arv-hub__note">' . esc_html( $map['note'] ) . '</p>' : '' )
		. '</div>'
		. '<div class="arv-hub__map-frame">' . $svg . '</div>'
		. '</div></section>';
}

/**
 * The map as a rendered shaded relief image, full width.
 *
 * A static picture rather than tiles or a script: the terrain is the point,
 * it costs one image, and a phone gets its own tighter crop through
 * <picture> instead of a shrunk desktop frame with unreadable labels.
 *
 * @param array $map Hub map block: image, image_mobile, sizes, alt, attribution.
 * @return string
 */
function arv_region_hub_map_image( $map ) {
	$w  = ! empty( $map['width'] ) ? (int) $map['width'] : 2400;
	$h  = ! empty( $map['height'] ) ? (int) $map['height'] : 1600;
	$pic = '<picture>';
	if ( ! empty( $map['image_mobile'] ) ) {
		$mw   = ! empty( $map['mobile_width'] ) ? (int) $map['mobile_width'] : 1080;
		$ms   = arv_region_hub_srcset( $map['image_mobile'], array( 640, $mw ) );
		$pic .= '<source media="(max-width: 619px)" srcset="' . esc_attr( '' !== $ms ? $ms : $map['image_mobile'] ) . '" sizes="100vw" />';
	}
	$ds   = arv_region_hub_srcset( $map['image'], array( 1200, 1800, $w ) );
	$pic .= '<img class="arv-hub__map-img" src="' . esc_url( arv_region_hub_img( $map['image'], 1800 ) ) . '"'
		. ( '' !== $ds ? ' srcset="' . esc_attr( $ds ) . '" sizes="100vw"' : '' )
		. ' width="' . $w . '" height="' . $h . '" alt="' . esc_attr( isset( $map['alt'] ) ? $map['alt'] : '' ) . '" loading="lazy" decoding="async" />';
	$pic .= '</picture>';

	return '<section class="arv-hub__section arv-hub__map arv-hub__map--terrain" aria-labelledby="arv-hub-map">'
		. '<div class="arv-hub__inner arv-hub__map-head" data-arv-fade>'
		. arv_region_hub_head( $map, 'arv-hub-map' )
		. ( ! empty( $map['text'] ) ? '<p class="arv-hub__map-text">' . esc_html( $map['text'] ) . '</p>' : '' )
		. '</div>'
		. '<figure class="arv-hub__map-figure">' . $pic
		. ( ! empty( $map['attribution'] ) ? '<figcaption class="arv-hub__inner arv-hub__map-credit">' . esc_html( $map['attribution'] ) . '</figcaption>' : '' )
		. '</figure></section>';
}

/**
 * Numbers strip under the races. Only numbers the hub file states, which in
 * turn are only numbers with a source (no finisher counts we cannot back).
 *
 * @param array $stats List of value, label.
 * @return string
 */
function arv_region_hub_stats( $stats ) {
	$li = '';
	foreach ( is_array( $stats ) ? $stats : array() as $st ) {
		if ( empty( $st['value'] ) || empty( $st['label'] ) ) {
			continue;
		}
		$li .= '<li data-arv-fade><span class="arv-hub__stat-value">' . esc_html( $st['value'] ) . '</span><span class="arv-hub__stat-label">' . esc_html( $st['label'] ) . '</span></li>';
	}
	return '' === $li ? '' : '<section class="arv-hub__stats" aria-label="' . esc_attr( __( 'The series in numbers', 'aravaipa-elements' ) ) . '"><ul class="arv-hub__inner arv-hub__stats-list">' . $li . '</ul></section>';
}

/**
 * Full-bleed photo bands, each placed after a named section.
 *
 * @param array  $hub   Hub data.
 * @param string $after Section key: stats, story, past, gallery.
 * @return string
 */
function arv_region_hub_bands( $hub, $after ) {
	$out = '';
	foreach ( isset( $hub['bands'] ) && is_array( $hub['bands'] ) ? $hub['bands'] : array() as $b ) {
		if ( empty( $b['src'] ) || ! isset( $b['after'] ) || $after !== $b['after'] ) {
			continue;
		}
		$set  = arv_region_hub_srcset( $b['src'], array( 768, 1280, 1920 ) );
		$out .= '<figure class="arv-hub__band"><img src="' . esc_url( arv_region_hub_img( $b['src'], 1920 ) ) . '"'
			. ( '' !== $set ? ' srcset="' . esc_attr( $set ) . '" sizes="100vw"' : '' )
			. ' alt="' . esc_attr( isset( $b['alt'] ) ? $b['alt'] : '' ) . '" loading="lazy" decoding="async"'
			. ( ! empty( $b['position'] ) ? ' style="object-position:' . esc_attr( $b['position'] ) . '"' : '' ) . ' /></figure>';
	}
	return $out;
}

/**
 * In Remembrance: the founder, at the very bottom of the hub. Rendered only
 * when the caller says the gate is on (see the shortcode), so the copy and
 * portrait can be signed off before they show.
 *
 * @param array $t Hub tribute block.
 * @return string
 */
function arv_region_hub_tribute( $t ) {
	if ( empty( $t['paragraphs'] ) ) {
		return '';
	}
	$img = '';
	if ( ! empty( $t['portrait'] ) ) {
		$img = '<div class="arv-hub__tribute-portrait"><img src="' . esc_url( arv_region_hub_img( $t['portrait'], 720 ) ) . '" alt="'
			. esc_attr( isset( $t['portrait_alt'] ) ? $t['portrait_alt'] : '' ) . '" width="360" height="360" loading="lazy" decoding="async" /></div>';
	}
	return '<section class="arv-hub__section arv-hub__tribute" aria-labelledby="arv-hub-tribute"><div class="arv-hub__inner arv-hub__tribute-inner" data-arv-fade>'
		. $img
		. '<div class="arv-hub__prose arv-hub__tribute-text">' . arv_region_hub_head( $t, 'arv-hub-tribute' ) . arv_region_hub_paras( $t ) . '</div>'
		. '</div></section>';
}

/**
 * Eyebrow and heading for a section.
 *
 * @param array  $section Has eyebrow, heading.
 * @param string $id      Heading id for aria-labelledby.
 * @return string
 */
function arv_region_hub_head( $section, $id ) {
	$out = '';
	if ( ! empty( $section['eyebrow'] ) ) {
		$out .= '<p class="arv-hub__eyebrow">' . esc_html( $section['eyebrow'] ) . '</p>';
	}
	if ( ! empty( $section['heading'] ) ) {
		$out .= '<h2 class="arv-hub__h2" id="' . esc_attr( $id ) . '">' . esc_html( $section['heading'] ) . '</h2>';
	}
	return $out;
}

/**
 * Paragraph list.
 *
 * @param array $section Has paragraphs.
 * @return string
 */
function arv_region_hub_paras( $section ) {
	$out = '';
	foreach ( isset( $section['paragraphs'] ) ? (array) $section['paragraphs'] : array() as $p ) {
		$out .= '<p>' . esc_html( $p ) . '</p>';
	}
	return $out;
}

/**
 * A YouTube id we are willing to put in a URL.
 *
 * @param string $id Video id.
 * @return bool
 */
function arv_region_hub_yt_ok( $id ) {
	return is_string( $id ) && 1 === preg_match( '/^[A-Za-z0-9_\-]{6,20}$/', $id );
}

/**
 * Click-to-play video facade: a thumbnail and a button until someone asks
 * for it, then a youtube-nocookie iframe (assets/aravaipa-region-hub.js).
 * The full YouTube player is about a megabyte of script per embed on a page
 * that is otherwise photos; nobody who scrolls past should pay for it. The
 * link goes to YouTube, so with the script blocked the click still plays.
 *
 * @param string $id    Checked video id.
 * @param string $title Video title, for the iframe and the button.
 * @param string $thumb YouTube thumbnail name (maxresdefault, hqdefault).
 * @return string
 */
function arv_region_hub_player( $id, $title, $thumb ) {
	wp_enqueue_script( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.js', array(), ARV_ELEMENTS_VERSION, true );
	return '<div class="arv-hub__player" data-arv-hub-video="' . esc_attr( $id ) . '" data-title="' . esc_attr( $title ) . '">'
		. '<a class="arv-hub__play" href="' . esc_url( 'https://www.youtube.com/watch?v=' . $id ) . '" target="_blank" rel="noopener">'
		. '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $id . '/' . $thumb . '.jpg' ) . '" alt="" loading="lazy" decoding="async" />'
		. '<span class="arv-hub__play-btn"><span class="screen-reader-text">' . esc_html( sprintf( /* translators: %s: video title */ __( 'Play: %s', 'aravaipa-elements' ), $title ) ) . '</span></span>'
		. '</a></div>';
}

/**
 * Series history: short prose, then one block per era (photo, years, what
 * happened, and the year rows inside it), then a source line. Falls back to
 * a flat timeline for a hub that has no eras.
 *
 * @param array $h Hub history block.
 * @return string Empty when there is nothing to say.
 */
function arv_region_hub_history( $h ) {
	$eras = '';
	foreach ( isset( $h['eras'] ) && is_array( $h['eras'] ) ? $h['eras'] : array() as $e ) {
		if ( empty( $e['label'] ) ) {
			continue;
		}
		$rows = '';
		foreach ( isset( $e['rows'] ) && is_array( $e['rows'] ) ? $e['rows'] : array() as $t ) {
			if ( ! empty( $t['year'] ) && ! empty( $t['text'] ) ) {
				$rows .= '<li><span class="arv-hub__year">' . esc_html( $t['year'] ) . '</span><span class="arv-hub__year-text">' . esc_html( $t['text'] ) . '</span></li>';
			}
		}
		$photo = '';
		if ( ! empty( $e['photo'] ) ) {
			$photo = '<div class="arv-hub__era-photo"><img src="' . esc_url( arv_region_hub_img( $e['photo'], 900 ) ) . '" alt="' . esc_attr( isset( $e['alt'] ) ? $e['alt'] : '' ) . '" loading="lazy" decoding="async" /></div>';
		}
		$eras .= '<li class="arv-hub__era" data-arv-fade>' . $photo . '<div class="arv-hub__era-body">'
			. ( ! empty( $e['years'] ) ? '<p class="arv-hub__era-years">' . esc_html( $e['years'] ) . '</p>' : '' )
			. '<h3 class="arv-hub__era-label">' . esc_html( $e['label'] ) . '</h3>'
			. ( ! empty( $e['text'] ) ? '<p class="arv-hub__era-text">' . esc_html( $e['text'] ) . '</p>' : '' )
			. ( '' !== $rows ? '<ol class="arv-hub__timeline">' . $rows . '</ol>' : '' )
			. '</div></li>';
	}
	$rows = '';
	if ( '' === $eras ) {
		foreach ( isset( $h['timeline'] ) && is_array( $h['timeline'] ) ? $h['timeline'] : array() as $t ) {
			if ( ! empty( $t['year'] ) && ! empty( $t['text'] ) ) {
				$rows .= '<li><span class="arv-hub__year">' . esc_html( $t['year'] ) . '</span><span class="arv-hub__year-text">' . esc_html( $t['text'] ) . '</span></li>';
			}
		}
	}
	if ( '' === $eras && '' === $rows && empty( $h['paragraphs'] ) ) {
		return '';
	}
	$src = '';
	if ( ! empty( $h['source_text'] ) ) {
		$src = '<p class="arv-hub__note">' . esc_html( $h['source_text'] );
		if ( ! empty( $h['source_url'] ) && ! empty( $h['source_label'] ) ) {
			$src .= ' <a href="' . esc_url( $h['source_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $h['source_label'] ) . '</a>';
		}
		$src .= '</p>';
	}
	if ( '' !== $eras ) {
		return '<section class="arv-hub__section arv-hub__history" aria-labelledby="arv-hub-history"><div class="arv-hub__inner">'
			. '<div class="arv-hub__prose arv-hub__history-head" data-arv-fade>' . arv_region_hub_head( $h, 'arv-hub-history' ) . arv_region_hub_paras( $h ) . '</div>'
			. '<ol class="arv-hub__eras">' . $eras . '</ol>'
			. $src
			. '</div></section>';
	}
	return '<section class="arv-hub__section arv-hub__history" aria-labelledby="arv-hub-history"><div class="arv-hub__inner arv-hub__split">'
		. '<div class="arv-hub__prose">' . arv_region_hub_head( $h, 'arv-hub-history' ) . arv_region_hub_paras( $h ) . $src . '</div>'
		. ( '' !== $rows ? '<ol class="arv-hub__timeline">' . $rows . '</ol>' : '' )
		. '</div></section>';
}

/**
 * Retired races, drawn like the season's tiles: a course photo with the
 * race name on a plate, then years, place, distances, a line about the
 * course, a film link and the old results. Results before Aravaipa stay
 * here, never in the site-wide results archive, and while they still live
 * on the timers' sites they are small secondary links, not buttons.
 *
 * @param array $p Hub past block.
 * @return string
 */
function arv_region_hub_past( $p ) {
	$cards = '';
	foreach ( isset( $p['races'] ) && is_array( $p['races'] ) ? $p['races'] : array() as $r ) {
		if ( empty( $r['name'] ) ) {
			continue;
		}
		$media = '';
		if ( ! empty( $r['photo'] ) ) {
			$media = '<figure class="arv-hub__past-media"><img src="' . esc_url( arv_region_hub_img( $r['photo'], 900 ) ) . '" alt="' . esc_attr( isset( $r['photo_alt'] ) ? $r['photo_alt'] : '' ) . '" loading="lazy" decoding="async" />'
				. '<span class="arv-hub__past-plate" aria-hidden="true">' . esc_html( $r['name'] ) . '</span>'
				. ( ! empty( $r['photo_credit'] ) ? '<figcaption class="arv-hub__past-credit">' . esc_html( $r['photo_credit'] ) . '</figcaption>' : '' )
				. '</figure>';
		}
		$links = '';
		foreach ( isset( $r['results'] ) && is_array( $r['results'] ) ? $r['results'] : array() as $l ) {
			if ( ! empty( $l['url'] ) && ! empty( $l['label'] ) ) {
				$links .= '<li><a href="' . esc_url( $l['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $l['label'] ) . '</a></li>';
			}
		}
		$film = '';
		if ( ! empty( $r['film']['url'] ) && ! empty( $r['film']['label'] ) && preg_match( '#^https://www\.youtube\.com/watch\?v=[A-Za-z0-9_\-]{6,20}$#', $r['film']['url'] ) ) {
			$film = '<p class="arv-hub__past-film"><a class="arv-hub__btn arv-hub__btn--ghost" href="' . esc_url( $r['film']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $r['film']['label'] ) . '</a></p>';
		}
		$cards .= '<article class="arv-hub__past-race" data-arv-fade>' . $media
			. '<div class="arv-hub__past-body">'
			. ( ! empty( $r['years'] ) ? '<p class="arv-hub__past-years">' . esc_html( $r['years'] ) . '</p>' : '' )
			. '<h3 class="arv-hub__past-name">' . esc_html( $r['name'] ) . '</h3>'
			. ( ! empty( $r['location'] ) ? '<p class="arv-hub__past-place">' . esc_html( $r['location'] ) . '</p>' : '' )
			. ( ! empty( $r['distances'] ) ? '<p class="arv-hub__past-dist">' . esc_html( $r['distances'] ) . '</p>' : '' )
			. ( ! empty( $r['text'] ) ? '<p class="arv-hub__past-text">' . esc_html( $r['text'] ) . '</p>' : '' )
			. $film
			. ( '' !== $links ? '<div class="arv-hub__past-results"><span class="arv-hub__past-results-label">' . esc_html( __( 'Results', 'aravaipa-elements' ) ) . '</span><ul class="arv-hub__past-links">' . $links . '</ul></div>' : '' )
			. '</div></article>';
	}
	if ( '' === $cards ) {
		return '';
	}
	return '<section class="arv-hub__section arv-hub__past" aria-labelledby="arv-hub-past"><div class="arv-hub__inner">'
		. '<div data-arv-fade>' . arv_region_hub_head( $p, 'arv-hub-past' )
		. ( ! empty( $p['intro'] ) ? '<p class="arv-hub__past-intro">' . esc_html( $p['intro'] ) . '</p>' : '' ) . '</div>'
		. '<div class="arv-hub__past-grid">' . $cards . '</div>'
		. '</div></section>';
}

/**
 * The whole hub. Pure apart from the URL helpers, so the test can feed it a
 * hub and a store directly.
 *
 * @param array  $hub   Hub data.
 * @param array  $store Every store race.
 * @param string $today Y-m-d.
 * @param array  $opts  tribute: true to draw the In Remembrance section
 *                      (the shortcode passes its gate option).
 * @return string Empty when the hub has no races to show.
 */
function arv_region_hub_render( $hub, $store, $today, $opts = array() ) {
	$items = arv_region_hub_races( $hub, $store );
	if ( empty( $items ) ) {
		return '';
	}

	// --- Races. Store races go through the shared card; one already over
	// (its weekend has cleared) is left off rather than shown as next.
	$cards  = '';
	$events = array();
	$shown  = array();
	foreach ( $items as $item ) {
		$race  = $item['race'];
		$entry = $item['entry'];

		if ( ! $item['soon'] ) {
			$last = ! empty( $race['end'] ) ? $race['end'] : $race['iso'];
			if ( $today >= arv_upcoming_races_clears_on( $last ) ) {
				continue;
			}
		}

		$links = array();
		if ( ! empty( $entry['results'] ) ) {
			$links[] = array(
				'label' => __( 'Results', 'aravaipa-elements' ),
				'url'   => $entry['results'],
			);
		}
		if ( ! empty( $entry['photos'] ) ) {
			$links[] = array(
				'label' => __( 'Photos', 'aravaipa-elements' ),
				'url'   => $entry['photos'],
			);
		}
		if ( ! empty( $entry['video']['youtube'] ) && arv_region_hub_yt_ok( $entry['video']['youtube'] ) ) {
			$links[] = array(
				'label' => __( 'Watch', 'aravaipa-elements' ),
				'url'   => 'https://www.youtube.com/watch?v=' . $entry['video']['youtube'],
			);
		}

		$card_opts = array(
			'details_label' => __( 'Race Info', 'aravaipa-elements' ),
			'photo'         => ! empty( $entry['photo'] ) ? arv_region_hub_img( $entry['photo'], 900 ) : '',
			'photo_alt'     => ! empty( $entry['photo_alt'] ) ? $entry['photo_alt'] : '',
			'links'         => $links,
			'card_class'    => 'arv-hub__card',
		);
		// The race's own baked tile (logo over a course photo on a name
		// plate), the look the old hub had. The logo is in the picture, so
		// no logo plate goes on top of it.
		if ( ! empty( $entry['tile'] ) ) {
			$card_opts['photo']      = arv_region_hub_img( $entry['tile'], 900 );
			$card_opts['photo_alt']  = ! empty( $entry['tile_alt'] ) ? $entry['tile_alt'] : $race['name'];
			$card_opts['card_class'] = 'arv-hub__card arv-hub__card--tile';
			$race['image']           = '';
		}
		if ( $item['soon'] ) {
			$card_opts['action']      = array(
				'phase' => 'soon',
				'label' => '',
				'url'   => '',
			);
			$card_opts['card_class'] .= empty( $race['dated'] ) ? ' arv-hub__card--soon' : ' arv-hub__card--dated';
		}

		$card    = arv_races_card( arv_region_hub_tidy( $race ), $today, $card_opts );
		$cards  .= $card['html'];
		$shown[] = $item;
		if ( null !== $card['event'] ) {
			$events[] = $card['event'];
		}
	}
	if ( '' === $cards ) {
		return '';
	}

	$title = isset( $hub['title'] ) ? $hub['title'] : '';
	$out   = '<div class="arv-hub">';

	// --- Hero. A real <img> rather than a CSS background, so it carries alt
	// text, srcset and fetchpriority, and nothing is baked into the photo.
	$hero = isset( $hub['hero'] ) ? $hub['hero'] : array();
	$out .= '<header class="arv-hub__hero">';
	if ( ! empty( $hero['photo'] ) ) {
		$srcset = arv_region_hub_srcset( $hero['photo'], array( 768, 1280, 1920 ) );
		$out   .= '<img class="arv-hub__hero-img" src="' . esc_url( arv_region_hub_img( $hero['photo'], 1920 ) ) . '"'
			. ( '' !== $srcset ? ' srcset="' . esc_attr( $srcset ) . '" sizes="100vw"' : '' )
			. ' alt="' . esc_attr( isset( $hero['alt'] ) ? $hero['alt'] : '' ) . '" fetchpriority="high" decoding="async"'
			. ( ! empty( $hero['position'] ) ? ' style="object-position:' . esc_attr( $hero['position'] ) . '"' : '' ) . ' />';
	}
	$out .= '<div class="arv-hub__hero-text arv-hub__inner">';
	if ( ! empty( $hub['logo'] ) ) {
		$out .= '<img class="arv-hub__logo" src="' . esc_url( arv_region_hub_img( $hub['logo'], 360 ) ) . '" alt="" width="180" height="167" decoding="async" />';
	}
	if ( ! empty( $hub['eyebrow'] ) ) {
		$out .= '<p class="arv-hub__eyebrow arv-hub__eyebrow--light">' . esc_html( $hub['eyebrow'] ) . '</p>';
	}
	$out .= '<h1 class="arv-hub__h1">' . esc_html( $title ) . '</h1>';
	if ( ! empty( $hub['intro'] ) ) {
		$out .= '<p class="arv-hub__lead">' . esc_html( $hub['intro'] ) . '</p>';
	}
	$out .= '<p class="arv-hub__hero-actions"><a class="arv-hub__btn" href="#races">'
		. esc_html( ! empty( $hub['cta_label'] ) ? $hub['cta_label'] : __( 'See the Races', 'aravaipa-elements' ) ) . '</a></p>';
	$out .= '</div></header>';

	// --- Races first, right under the hero.
	$out .= '<section class="arv-hub__section arv-hub__races" id="races" aria-labelledby="arv-hub-races">'
		. '<div class="arv-hub__inner">'
		. arv_region_hub_head(
			array(
				'eyebrow' => isset( $hub['races_eyebrow'] ) ? $hub['races_eyebrow'] : '',
				'heading' => isset( $hub['races_heading'] ) ? $hub['races_heading'] : '',
			),
			'arv-hub-races'
		)
		. '<div class="arv-races arv-races--light arv-hub__grid"><div class="arv-races__grid">' . $cards . '</div></div>'
		. '</div></section>';

	// --- Numbers, then the first photo band.
	$out .= arv_region_hub_stats( isset( $hub['stats'] ) ? $hub['stats'] : array() );
	$out .= arv_region_hub_bands( $hub, 'stats' );

	// --- Map.
	$out .= arv_region_hub_map( $hub, $shown );

	// --- Story. The founder is no longer beside it: he has his own section
	// at the very bottom (arv_region_hub_tribute()).
	$story = isset( $hub['story'] ) ? $hub['story'] : array();
	if ( ! empty( $story['paragraphs'] ) ) {
		$out .= '<section class="arv-hub__section arv-hub__story" aria-labelledby="arv-hub-story"><div class="arv-hub__inner"><div class="arv-hub__prose arv-hub__story-text" data-arv-fade>'
			. arv_region_hub_head( $story, 'arv-hub-story' ) . arv_region_hub_paras( $story ) . '</div></div></section>';
	}
	$out .= arv_region_hub_bands( $hub, 'story' );

	// --- History: short prose, the eras with their years, and the sources.
	$out .= arv_region_hub_history( isset( $hub['history'] ) ? $hub['history'] : array() );

	// --- Past races: retired races with their years, results and a film.
	$out .= arv_region_hub_past( isset( $hub['past'] ) ? $hub['past'] : array() );
	$out .= arv_region_hub_bands( $hub, 'past' );

	// --- Films: the series film big, then a grid of the rest. Every one is a
	// click-to-play facade (see arv_region_hub_player()).
	$video = isset( $hub['video'] ) ? $hub['video'] : array();
	$films = '';
	foreach ( isset( $hub['films'] ) && is_array( $hub['films'] ) ? $hub['films'] : array() as $f ) {
		if ( empty( $f['youtube'] ) || ! arv_region_hub_yt_ok( $f['youtube'] ) ) {
			continue;
		}
		$films .= '<figure class="arv-hub__film">' . arv_region_hub_player( $f['youtube'], isset( $f['title'] ) ? $f['title'] : '', 'hqdefault' )
			. '<figcaption><span class="arv-hub__film-title">' . esc_html( isset( $f['title'] ) ? $f['title'] : '' ) . '</span>'
			. '<span class="arv-hub__film-meta">' . esc_html( trim( ( isset( $f['by'] ) ? $f['by'] : '' ) . ( ! empty( $f['year'] ) ? ', ' . $f['year'] : '' ), ', ' ) ) . '</span>'
			. '</figcaption></figure>';
	}
	$has_main = ! empty( $video['youtube'] ) && arv_region_hub_yt_ok( $video['youtube'] );
	if ( $has_main || '' !== $films ) {
		$out .= '<section class="arv-hub__section arv-hub__video" aria-labelledby="arv-hub-video"><div class="arv-hub__inner">'
			. arv_region_hub_head( $video, 'arv-hub-video' );
		if ( $has_main ) {
			$vt   = ! empty( $video['title'] ) ? $video['title'] : $title;
			$out .= arv_region_hub_player( $video['youtube'], $vt, 'maxresdefault' );
		}
		if ( '' !== $films ) {
			$out .= '<div class="arv-hub__films">' . $films . '</div>';
		}
		$more = isset( $hub['films_more'] ) ? $hub['films_more'] : array();
		if ( ! empty( $more['url'] ) && ! empty( $more['label'] ) ) {
			$out .= '<p class="arv-hub__films-more"><a href="' . esc_url( $more['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $more['label'] ) . '</a></p>';
		}
		$out .= '</div></section>';
	}

	// --- Photo strip.
	if ( ! empty( $hub['gallery'] ) && is_array( $hub['gallery'] ) ) {
		$strip = '';
		foreach ( $hub['gallery'] as $g ) {
			if ( empty( $g['src'] ) ) {
				continue;
			}
			$big    = ! empty( $g['feature'] );
			$strip .= '<figure class="arv-hub__shot' . ( $big ? ' arv-hub__shot--big' : '' ) . '"><img src="' . esc_url( arv_region_hub_img( $g['src'], $big ? 1200 : 700 ) ) . '" alt="' . esc_attr( isset( $g['alt'] ) ? $g['alt'] : '' ) . '" loading="lazy" decoding="async" /></figure>';
		}
		if ( '' !== $strip ) {
			$gh   = isset( $hub['gallery_heading'] ) && is_array( $hub['gallery_heading'] ) ? $hub['gallery_heading'] : array();
			$out .= '<section class="arv-hub__section arv-hub__gallery-wrap"'
				. ( ! empty( $gh['heading'] ) ? ' aria-labelledby="arv-hub-gallery"' : ' aria-label="' . esc_attr( __( 'Photos', 'aravaipa-elements' ) ) . '"' ) . '>'
				. ( ! empty( $gh ) ? '<div class="arv-hub__inner" data-arv-fade>' . arv_region_hub_head( $gh, 'arv-hub-gallery' ) . '</div>' : '' )
				. '<div class="arv-hub__gallery">' . $strip . '</div></section>';
		}
	}
	$out .= arv_region_hub_bands( $hub, 'gallery' );

	// --- Follow and newsletter.
	$socials = isset( $hub['socials'] ) && is_array( $hub['socials'] ) ? $hub['socials'] : array();
	$news    = isset( $hub['newsletter'] ) ? $hub['newsletter'] : array();
	if ( ! empty( $socials ) || ! empty( $news['url'] ) ) {
		$out .= '<section class="arv-hub__section arv-hub__connect" aria-labelledby="arv-hub-connect"><div class="arv-hub__inner arv-hub__split">';
		if ( ! empty( $news['url'] ) ) {
			$out .= '<div class="arv-hub__news">'
				. arv_region_hub_head( array( 'heading' => isset( $news['heading'] ) ? $news['heading'] : '' ), 'arv-hub-connect' )
				. ( ! empty( $news['text'] ) ? '<p>' . esc_html( $news['text'] ) . '</p>' : '' )
				. '<p><a class="arv-hub__btn" href="' . esc_url( $news['url'] ) . '"' . arv_races_link_target( $news['url'] ) . '>'
				. esc_html( ! empty( $news['label'] ) ? $news['label'] : __( 'Join the Newsletter', 'aravaipa-elements' ) ) . '</a></p>'
				. '</div>';
		}
		if ( ! empty( $socials ) ) {
			$out .= '<div class="arv-hub__follow"><p class="arv-hub__eyebrow">' . esc_html( __( 'Follow', 'aravaipa-elements' ) ) . '</p><ul class="arv-hub__socials">';
			foreach ( $socials as $s ) {
				if ( empty( $s['url'] ) || empty( $s['label'] ) ) {
					continue;
				}
				$out .= '<li><a href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener"><span class="arv-hub__social-label">' . esc_html( $s['label'] ) . '</span>'
					. ( ! empty( $s['handle'] ) ? '<span class="arv-hub__social-handle">' . esc_html( $s['handle'] ) . '</span>' : '' ) . '</a></li>';
			}
			$out .= '</ul></div>';
		}
		$out .= '</div></section>';
	}

	// --- In Remembrance, last on the page, only while its gate is on.
	if ( ! empty( $opts['tribute'] ) && ! empty( $hub['tribute'] ) ) {
		$out .= arv_region_hub_tribute( $hub['tribute'] );
	}

	if ( ! empty( $events ) ) {
		$out .= arv_upcoming_races_schema_block( $events );
	}

	$out .= '</div>';

	return $out;
}

/**
 * [arv_region_hub region="ultra-adventures"].
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function arv_region_hub_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'region' => '' ), $atts, 'arv_region_hub' );
	$hub  = arv_region_hub_data( $atts['region'] );

	// The shared card lives with the Upcoming Races element, which loads on
	// cs_register_elements. A hub page is a Cornerstone page so that has run,
	// but a shortcode can be rendered anywhere; load it rather than go blank.
	if ( ! function_exists( 'arv_races_card' ) && function_exists( 'cs_register_element' ) ) {
		require_once ARV_ELEMENTS_PATH . 'includes/elements/upcoming-races.php';
	}
	if ( null === $hub || ! function_exists( 'arv_races_card' ) || ! function_exists( 'arv_race_store_get' ) ) {
		return '';
	}

	// The tribute shows only once its option is switched on, so its copy and
	// portrait can be signed off before anyone sees them.
	$gate    = ! empty( $hub['tribute']['gate_option'] ) ? sanitize_key( $hub['tribute']['gate_option'] ) : '';
	$tribute = '' !== $gate && '1' === (string) get_option( $gate, '0' );

	$html = arv_region_hub_render( $hub, arv_race_store_get(), arv_upcoming_races_today(), array( 'tribute' => $tribute ) );
	if ( '' === $html ) {
		return '';
	}

	// Fade-in on scroll and the film facades. Off for reduced motion.
	wp_enqueue_script( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.js', array(), ARV_ELEMENTS_VERSION, true );

	// Normally already in the head (arv_region_hub_assets below); this is
	// the fallback for a hub rendered somewhere that check cannot see.
	wp_enqueue_style( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.css', array( 'aravaipa-elements' ), ARV_ELEMENTS_VERSION );

	return $html;
}
add_shortcode( 'arv_region_hub', 'arv_region_hub_shortcode' );

/**
 * The hub stylesheet in the head, on the pages that hold a hub.
 *
 * Enqueued at render time only, the styles would print in the footer and
 * the hero would paint unstyled first. Cornerstone keeps a raw content
 * element's shortcode in _cornerstone_data, not in post_content (checked on
 * a live course map page), so both are looked at; the meta is already in
 * the object cache for the page being viewed.
 */
function arv_region_hub_assets() {
	if ( ! is_singular() ) {
		return;
	}
	$id      = get_queried_object_id();
	$content = (string) get_post_field( 'post_content', $id ) . (string) get_post_meta( $id, '_cornerstone_data', true );
	if ( false === strpos( $content, '[arv_region_hub' ) ) {
		return;
	}
	wp_enqueue_style( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.css', array( 'aravaipa-elements' ), ARV_ELEMENTS_VERSION );
}
add_action( 'wp_enqueue_scripts', 'arv_region_hub_assets', 20 );
