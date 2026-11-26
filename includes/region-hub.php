<?php
/**
 * Region hub landing page: [arv_region_hub region="ultra-adventures"].
 *
 * One long page for a race division: a photo hero, the season's races as
 * photo tiles (drawn by arv_races_card(), the same card the Upcoming Races
 * element uses), a map of where they are, the story and the founder, a
 * video, a photo strip, socials and the newsletter.
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
 * The whole hub. Pure apart from the URL helpers, so the test can feed it a
 * hub and a store directly.
 *
 * @param array  $hub   Hub data.
 * @param array  $store Every store race.
 * @param string $today Y-m-d.
 * @return string Empty when the hub has no races to show.
 */
function arv_region_hub_render( $hub, $store, $today ) {
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

		$opts = array(
			'details_label' => __( 'Race Info', 'aravaipa-elements' ),
			'photo'         => ! empty( $entry['photo'] ) ? arv_region_hub_img( $entry['photo'], 900 ) : '',
			'photo_alt'     => ! empty( $entry['photo_alt'] ) ? $entry['photo_alt'] : '',
			'links'         => $links,
			'card_class'    => 'arv-hub__card',
		);
		if ( $item['soon'] ) {
			$opts['action']     = array(
				'phase' => 'soon',
				'label' => '',
				'url'   => '',
			);
			$opts['card_class'] = 'arv-hub__card arv-hub__card--soon';
		}

		$card    = arv_races_card( arv_region_hub_tidy( $race ), $today, $opts );
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

	// --- Map.
	$out .= arv_region_hub_map( $hub, $shown );

	// --- Story and founder, side by side on a wide screen.
	$story   = isset( $hub['story'] ) ? $hub['story'] : array();
	$tribute = isset( $hub['tribute'] ) ? $hub['tribute'] : array();
	if ( ! empty( $story['paragraphs'] ) || ! empty( $tribute['paragraphs'] ) ) {
		$out .= '<section class="arv-hub__section arv-hub__story" aria-labelledby="arv-hub-story"><div class="arv-hub__inner arv-hub__split">';
		if ( ! empty( $story['paragraphs'] ) ) {
			$out .= '<div class="arv-hub__prose">' . arv_region_hub_head( $story, 'arv-hub-story' ) . arv_region_hub_paras( $story ) . '</div>';
		}
		if ( ! empty( $tribute['paragraphs'] ) ) {
			$out .= '<div class="arv-hub__prose arv-hub__tribute">' . arv_region_hub_head( $tribute, 'arv-hub-tribute' ) . arv_region_hub_paras( $tribute ) . '</div>';
		}
		$out .= '</div></section>';
	}

	// --- Video, as a click-to-play facade: a thumbnail and a button until
	// someone asks for it, then a youtube-nocookie iframe. The full YouTube
	// player is about a megabyte of script on a page that is otherwise
	// photos; nobody who scrolls past should pay for it.
	$video = isset( $hub['video'] ) ? $hub['video'] : array();
	if ( ! empty( $video['youtube'] ) && preg_match( '/^[A-Za-z0-9_\-]{6,20}$/', $video['youtube'] ) ) {
		$id    = $video['youtube'];
		$vt    = ! empty( $video['title'] ) ? $video['title'] : $title;
		$out  .= '<section class="arv-hub__section arv-hub__video" aria-labelledby="arv-hub-video"><div class="arv-hub__inner">'
			. arv_region_hub_head( $video, 'arv-hub-video' )
			. '<div class="arv-hub__player" data-arv-hub-video="' . esc_attr( $id ) . '" data-title="' . esc_attr( $vt ) . '">'
			. '<a class="arv-hub__play" href="' . esc_url( 'https://www.youtube.com/watch?v=' . $id ) . '" target="_blank" rel="noopener">'
			. '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $id . '/maxresdefault.jpg' ) . '" alt="" loading="lazy" decoding="async" />'
			. '<span class="arv-hub__play-btn"><span class="screen-reader-text">' . esc_html( sprintf( /* translators: %s: video title */ __( 'Play: %s', 'aravaipa-elements' ), $vt ) ) . '</span></span>'
			. '</a></div></div></section>';
		wp_enqueue_script( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.js', array(), ARV_ELEMENTS_VERSION, true );
	}

	// --- Photo strip.
	if ( ! empty( $hub['gallery'] ) && is_array( $hub['gallery'] ) ) {
		$strip = '';
		foreach ( $hub['gallery'] as $g ) {
			if ( empty( $g['src'] ) ) {
				continue;
			}
			$strip .= '<figure class="arv-hub__shot"><img src="' . esc_url( arv_region_hub_img( $g['src'], 900 ) ) . '" alt="' . esc_attr( isset( $g['alt'] ) ? $g['alt'] : '' ) . '" loading="lazy" decoding="async" /></figure>';
		}
		if ( '' !== $strip ) {
			$out .= '<section class="arv-hub__gallery" aria-label="' . esc_attr( __( 'Photos', 'aravaipa-elements' ) ) . '">' . $strip . '</section>';
		}
	}

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

	$html = arv_region_hub_render( $hub, arv_race_store_get(), arv_upcoming_races_today() );
	if ( '' === $html ) {
		return '';
	}

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
 * the hero would paint unstyled first. Cornerstone writes a page's
 * shortcodes into post_content as well as its own data, so a plain content
 * check finds it before anything renders.
 */
function arv_region_hub_assets() {
	if ( ! is_singular() ) {
		return;
	}
	$content = (string) get_post_field( 'post_content', get_queried_object_id() );
	if ( false === strpos( $content, '[arv_region_hub' ) ) {
		return;
	}
	wp_enqueue_style( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.css', array( 'aravaipa-elements' ), ARV_ELEMENTS_VERSION );
}
add_action( 'wp_enqueue_scripts', 'arv_region_hub_assets', 20 );
