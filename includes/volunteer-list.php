<?php
/**
 * Volunteer List: every upcoming race with one Volunteer button each.
 *
 * Replaces the iVolunteer iframe on /volunteer/. Rows come from the race
 * store (the Races post type the season calendar reads), date order, virtual
 * races left out. Each row's button picks its own destination:
 *
 *   - RaceGoat: the race is in RaceGoat's public volunteer feed for the
 *     Aravaipa org (shifts are posted) and its RaceGoat event is not still
 *     in pre-launch ("upcoming" with an open time in the future). The button
 *     expands a panel under the row and loads RaceGoat's own volunteer
 *     widget for that race into it on first open. RaceGoat pages refuse to
 *     be framed, so the widget is the inline form.
 *   - iVolunteer: the race's own event page, stored per race in the
 *     _arv_volunteer_url meta. The button expands the same kind of panel
 *     and frames that event page in it, created on first open only.
 *     iVolunteer allows framing from aravaiparunning.com (CSP
 *     frame-ancestors), so the signup works inside the row.
 *   - iVolunteer home: a race with no event of its own gets a plain
 *     "Sign up on iVolunteer" link to the home page, never a frame of the
 *     whole home page and never another race's event.
 *
 * Every inline panel ends with a small "Open full page" link (new tab).
 *
 * A race flips from iVolunteer to RaceGoat on its own the first time it
 * shows up in the feed: nothing here names a race.
 *
 * Network: two cached reads, both racegoat.com and both public (the org
 * feed here, and the per-event embed through arv_rg_embed()). Nothing is
 * fetched from iVolunteer, ever. A failed or slow read (3 s timeout) is
 * cached for 15 minutes and every row falls back to iVolunteer, so the page
 * always renders.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** RaceGoat org whose volunteer feed decides the RaceGoat rows. */
const ARV_VOL_RG_ORG = 'aravaipa';

/** The iVolunteer home page, for races with no event of their own. */
const ARV_VOL_IVOL_ROOT = 'https://aravaiparunning.ivolunteer.com/';

/** How long a good feed answer is trusted. */
const ARV_VOL_FEED_TTL = 6 * 3600;

/** How long a failed feed read is remembered. */
const ARV_VOL_FEED_FAIL_TTL = 15 * 60;

/** Transient key for the feed. */
const ARV_VOL_FEED_KEY = 'arv_vol_rg_feed';

/**
 * Division labels, in tab order.
 *
 * @return array slug => label
 */
function arv_vol_divisions() {
	return array(
		'running'   => 'Aravaipa Running',
		'rides'     => 'Aravaipa Rides',
		'steeplife' => 'Steep Life',
	);
}

/**
 * Two-letter state to the name shown on the region chips.
 *
 * @return array
 */
function arv_vol_states() {
	return array(
		'AZ' => 'Arizona',
		'CA' => 'California',
		'CO' => 'Colorado',
		'ID' => 'Idaho',
		'ME' => 'Maine',
		'MI' => 'Michigan',
		'MN' => 'Minnesota',
		'MT' => 'Montana',
		'NH' => 'New Hampshire',
		'NM' => 'New Mexico',
		'NV' => 'Nevada',
		'OR' => 'Oregon',
		'TN' => 'Tennessee',
		'TX' => 'Texas',
		'UT' => 'Utah',
		'VT' => 'Vermont',
		'WA' => 'Washington',
		'WI' => 'Wisconsin',
		'WY' => 'Wyoming',
	);
}

/**
 * Register the two per-race meta fields this list reads.
 */
function arv_vol_register_meta() {
	if ( ! function_exists( 'register_post_meta' ) || ! defined( 'ARV_RACE_POST_TYPE' ) ) {
		return;
	}

	foreach ( array( '_arv_volunteer_url', '_arv_division' ) as $key ) {
		register_post_meta(
			ARV_RACE_POST_TYPE,
			$key,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'arv_vol_register_meta' );

/**
 * Full state name from a "Town, ST" location, or ''.
 *
 * @param string $location
 * @return string
 */
function arv_vol_region( $location ) {
	if ( ! preg_match( '/,\s*([A-Z]{2})\s*$/', trim( (string) $location ), $m ) ) {
		return '';
	}
	$states = arv_vol_states();
	return isset( $states[ $m[1] ] ) ? $states[ $m[1] ] : $m[1];
}

/**
 * The race's division: the _arv_division meta when set, else Rides when the
 * name says Ride or Rides, else Running.
 *
 * @param string $name
 * @param string $meta
 * @return string
 */
function arv_vol_division( $name, $meta = '' ) {
	$meta = strtolower( trim( (string) $meta ) );
	if ( isset( arv_vol_divisions()[ $meta ] ) ) {
		return $meta;
	}
	return preg_match( '/\bRides?\b/i', (string) $name ) ? 'rides' : 'running';
}

/**
 * A race name reduced to the words that tell races apart, for the feed's
 * same-month name match.
 *
 * @param string $name
 * @return string
 */
function arv_vol_norm( $name ) {
	$s = strtolower( str_replace( '&', ' and ', (string) $name ) );
	$s = preg_replace( '/\b(20\d\d(-\d\d)?|the|trail|runs?|races?|ultras?|night|and|weekend)\b/', ' ', $s );
	preg_match_all( '/[a-z0-9]+/', $s, $m );
	return implode( ' ', $m[0] );
}

/**
 * RaceGoat's public volunteer feed for the org, reduced to slug => race,
 * cached. Null when the feed could not be read.
 *
 * @return array|null slug => array( name, month )
 */
function arv_vol_feed() {
	$cached = function_exists( 'get_transient' ) ? get_transient( ARV_VOL_FEED_KEY ) : false;
	if ( is_array( $cached ) ) {
		return empty( $cached['ok'] ) ? null : $cached['data'];
	}

	$data = null;

	if ( function_exists( 'wp_remote_get' ) ) {
		$response = wp_remote_get(
			'https://racegoat.com/api/embed/volunteer/' . ARV_VOL_RG_ORG,
			array(
				'timeout'     => 3,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( ! ( $response instanceof WP_Error ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( is_array( $decoded ) && isset( $decoded['months'] ) && is_array( $decoded['months'] ) ) {
				$data = array();
				foreach ( $decoded['months'] as $month ) {
					if ( ! is_array( $month ) || empty( $month['races'] ) || ! is_array( $month['races'] ) ) {
						continue;
					}
					foreach ( $month['races'] as $race ) {
						$url = is_array( $race ) && isset( $race['url'] ) ? (string) $race['url'] : '';
						if ( preg_match( '#^https://racegoat\.com/events/([a-z0-9][a-z0-9-]*)/volunteer#i', $url, $m ) ) {
							$data[ strtolower( $m[1] ) ] = array(
								'name'  => isset( $race['name'] ) ? (string) $race['name'] : '',
								'month' => isset( $month['key'] ) ? (string) $month['key'] : '',
							);
						}
					}
				}
			}
		}
	}

	if ( function_exists( 'set_transient' ) ) {
		set_transient(
			ARV_VOL_FEED_KEY,
			null === $data ? array( 'ok' => 0 ) : array( 'ok' => 1, 'data' => $data ),
			null === $data ? ARV_VOL_FEED_FAIL_TTL : ARV_VOL_FEED_TTL
		);
	}

	return $data;
}

/**
 * True when the RaceGoat event is still pre-launch: its embed says
 * "upcoming" with an open time still ahead. Unknown (no embed) counts as
 * not launched, so the row stays on iVolunteer.
 *
 * @param string $slug
 * @param int    $now
 * @return bool
 */
function arv_vol_rg_prelaunch( $slug, $now ) {
	$embed = function_exists( 'arv_rg_embed' ) ? arv_rg_embed( $slug ) : null;
	if ( ! is_array( $embed ) ) {
		return true;
	}
	if ( isset( $embed['status'] ) && 'upcoming' === $embed['status'] && ! empty( $embed['opensAt'] ) ) {
		$opens = strtotime( (string) $embed['opensAt'] );
		return false !== $opens && $opens > $now;
	}
	return false;
}

/**
 * The RaceGoat slug a race page's widget names, cached per page URL.
 *
 * @param string $page
 * @return string
 */
function arv_vol_page_slug( $page ) {
	$page = (string) $page;
	if ( '' === $page || ! function_exists( 'url_to_postid' ) || ! function_exists( 'arv_rg_page_slug' ) ) {
		return '';
	}

	static $memo = array();
	if ( isset( $memo[ $page ] ) ) {
		return $memo[ $page ];
	}

	$key  = 'arv_vol_pslug_' . md5( $page );
	$slug = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
	if ( ! is_string( $slug ) ) {
		$post_id = (int) url_to_postid( $page );
		$slug    = $post_id ? (string) arv_rg_page_slug( $post_id ) : '';
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $slug, 12 * 3600 );
		}
	}

	$memo[ $page ] = $slug;
	return $slug;
}

/**
 * Upcoming store races with their post id and volunteer meta.
 *
 * @return array
 */
function arv_vol_store_races() {
	if ( ! defined( 'ARV_RACE_POST_TYPE' ) || ! function_exists( 'arv_race_store_to_race' ) ) {
		return array();
	}

	$posts = get_posts(
		array(
			'post_type'      => ARV_RACE_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);

	$races = array();
	foreach ( (array) $posts as $post ) {
		$race = arv_race_store_to_race( $post );
		if ( null === $race ) {
			continue;
		}
		$race['post_id']       = (int) $post->ID;
		$race['volunteer_url'] = (string) get_post_meta( $post->ID, '_arv_volunteer_url', true );
		$race['division_meta'] = (string) get_post_meta( $post->ID, '_arv_division', true );
		$races[]               = $race;
	}

	return $races;
}

/**
 * Turn store races into list rows.
 *
 * @param array  $races Store race arrays plus post_id, volunteer_url, division_meta.
 * @param string $today Y-m-d; races before it are left out.
 * @param int    $now   Unix time for the pre-launch guard.
 * @return array
 */
function arv_vol_rows( $races, $today, $now ) {
	$feed = arv_vol_feed();
	$rows = array();

	foreach ( (array) $races as $race ) {
		$iso      = isset( $race['iso'] ) ? (string) $race['iso'] : '';
		$location = isset( $race['location'] ) ? (string) $race['location'] : '';
		if ( '' === $iso || $iso < $today ) {
			continue;
		}
		if ( preg_match( '/worldwide|virtual/i', $location ) ) {
			continue;
		}

		$name = (string) $race['name'];
		$slug = function_exists( 'arv_rg_slug' ) ? arv_rg_slug( isset( $race['register'] ) ? $race['register'] : '' ) : '';
		if ( '' === $slug ) {
			$slug = arv_vol_page_slug( isset( $race['page'] ) ? $race['page'] : '' );
		}
		if ( '' === $slug && is_array( $feed ) ) {
			foreach ( $feed as $fslug => $f ) {
				if ( substr( $iso, 0, 7 ) === $f['month'] && arv_vol_norm( $f['name'] ) === arv_vol_norm( $name ) && '' !== arv_vol_norm( $name ) ) {
					$slug = (string) $fslug;
					break;
				}
			}
		}

		$mode = 'ivolunteer';
		if ( '' !== $slug && is_array( $feed ) && isset( $feed[ $slug ] ) && ! arv_vol_rg_prelaunch( $slug, $now ) ) {
			$mode = 'racegoat';
		}

		$vurl = isset( $race['volunteer_url'] ) ? trim( (string) $race['volunteer_url'] ) : '';
		if ( 0 !== strpos( $vurl, ARV_VOL_IVOL_ROOT ) ) {
			$vurl = ARV_VOL_IVOL_ROOT;
		}

		$rows[] = array(
			'post_id'  => isset( $race['post_id'] ) ? (int) $race['post_id'] : 0,
			'name'     => $name,
			'iso'      => $iso,
			'end'      => isset( $race['end'] ) ? (string) $race['end'] : '',
			'location' => $location,
			'region'   => arv_vol_region( $location ),
			'division' => arv_vol_division( $name, isset( $race['division_meta'] ) ? $race['division_meta'] : '' ),
			'page'     => isset( $race['page'] ) ? (string) $race['page'] : '',
			'mode'     => $mode,
			'rg_slug'  => 'racegoat' === $mode ? $slug : '',
			// Framed only when it is a plain event code on our iVolunteer
			// site, the same rule the script applies before making the frame.
			'ivol_own' => 'racegoat' !== $mode && 1 === preg_match( '#^https://aravaiparunning\.ivolunteer\.com/[A-Za-z0-9_-]+/?$#', $vurl ),
			'url'      => 'racegoat' === $mode ? 'https://racegoat.com/events/' . $slug . '/volunteer' : $vurl,
		);
	}

	usort(
		$rows,
		function ( $a, $b ) {
			return array( $a['iso'], $a['name'] ) <=> array( $b['iso'], $b['name'] );
		}
	);

	return $rows;
}

/**
 * Lowercase, dash-separated token for data attributes.
 *
 * @param string $s
 * @return string
 */
function arv_vol_token( $s ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' );
}

/**
 * Render the list.
 *
 * @param array $args heading, intro, rows (optional, for tests), today, now.
 * @return string
 */
function arv_vol_render( $args = array() ) {
	$args = array_merge(
		array(
			'heading' => '',
			'intro'   => '',
			'races'   => null,
			'today'   => function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' ),
			'now'     => time(),
		),
		(array) $args
	);

	$races = is_array( $args['races'] ) ? $args['races'] : arv_vol_store_races();
	$rows  = arv_vol_rows( $races, $args['today'], (int) $args['now'] );
	if ( empty( $rows ) ) {
		return '';
	}

	if ( function_exists( 'wp_enqueue_script' ) && defined( 'ARV_ELEMENTS_URL' ) ) {
		wp_enqueue_script( 'aravaipa-volunteer', ARV_ELEMENTS_URL . 'assets/aravaipa-volunteer.js', array(), ARV_ELEMENTS_VERSION, true );
	}

	$regions   = array();
	$divisions = array();
	foreach ( $rows as $row ) {
		if ( '' !== $row['region'] ) {
			$regions[ $row['region'] ] = arv_vol_token( $row['region'] );
		}
		$divisions[ $row['division'] ] = true;
	}
	ksort( $regions );

	$h = '<section class="arv-vol" id="volunteer-list" data-arv-vol>';
	$h .= '<div class="arv-vol__inner">';
	if ( '' !== trim( (string) $args['heading'] ) ) {
		$h .= '<h2 class="arv-vol__heading">' . esc_html( $args['heading'] ) . '</h2>';
	}
	if ( '' !== trim( (string) $args['intro'] ) ) {
		$h .= '<p class="arv-vol__intro">' . esc_html( $args['intro'] ) . '</p>';
	}

	// Division tabs only when the data has more than one division: one tab
	// on its own filters nothing.
	$labels = arv_vol_divisions();
	$have   = array_values( array_filter( array_keys( $labels ), function ( $d ) use ( $divisions ) {
		return isset( $divisions[ $d ] );
	} ) );
	if ( count( $have ) > 1 ) {
		$h .= '<div class="arv-vol__tabs" role="group" aria-label="Division" hidden data-arv-vol-js>';
		$h .= '<button type="button" class="arv-vol__tab" data-division="" aria-pressed="true">All</button>';
		foreach ( $have as $d ) {
			$h .= '<button type="button" class="arv-vol__tab" data-division="' . esc_attr( $d ) . '" aria-pressed="false">' . esc_html( $labels[ $d ] ) . '</button>';
		}
		$h .= '</div>';
	}

	if ( count( $regions ) > 1 ) {
		$h .= '<div class="arv-vol__chips" role="group" aria-label="Region" hidden data-arv-vol-js>';
		$h .= '<button type="button" class="arv-vol__chip" data-region="" aria-pressed="true">All</button>';
		foreach ( $regions as $name => $token ) {
			$h .= '<button type="button" class="arv-vol__chip" data-region="' . esc_attr( $token ) . '" aria-pressed="false">' . esc_html( $name ) . '</button>';
		}
		$h .= '</div>';
	}

	$month = '';
	$h    .= '<div class="arv-vol__list">';
	foreach ( $rows as $i => $row ) {
		$ts = strtotime( $row['iso'] . ' 12:00:00' );
		$m  = gmdate( 'Y-m', $ts );
		if ( $m !== $month ) {
			if ( '' !== $month ) {
				$h .= '</div></div>';
			}
			$month = $m;
			$h    .= '<div class="arv-vol__month" data-month="' . esc_attr( $m ) . '"><h3 class="arv-vol__month-name">' . esc_html( gmdate( 'F Y', $ts ) ) . '</h3><div class="arv-vol__rows">';
		}

		$pid  = 'arv-vol-p' . $i;
		$name = esc_html( $row['name'] );
		if ( '' !== $row['page'] ) {
			$name = '<a class="arv-vol__name" href="' . esc_url( $row['page'] ) . '">' . $name . '</a>';
		} else {
			$name = '<span class="arv-vol__name">' . $name . '</span>';
		}

		$h .= '<div class="arv-vol__row" data-region="' . esc_attr( arv_vol_token( $row['region'] ) ) . '" data-division="' . esc_attr( $row['division'] ) . '" data-mode="' . esc_attr( $row['mode'] ) . '">';
		$h .= '<div class="arv-vol__main">';
		$h .= '<div class="arv-vol__day"><span class="arv-vol__day-month">' . esc_html( gmdate( 'M', $ts ) ) . '</span><span class="arv-vol__day-num">' . esc_html( gmdate( 'j', $ts ) ) . '</span></div>';
		$h .= '<div class="arv-vol__body">' . $name;
		if ( '' !== $row['location'] ) {
			$h .= '<span class="arv-vol__where">' . esc_html( $row['location'] ) . '</span>';
		}
		$h .= '</div>';

		$inline = 'racegoat' === $row['mode'] || ! empty( $row['ivol_own'] );
		if ( $inline ) {
			// A real link first, so the button still works with scripts off;
			// the script turns it into the toggle for the inline panel.
			$h .= '<a class="arv-vol__btn" href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener" data-arv-vol-open="' . esc_attr( $pid ) . '" aria-controls="' . esc_attr( $pid ) . '" aria-expanded="false">Volunteer</a>';
		} else {
			$h .= '<a class="arv-vol__btn arv-vol__btn--out" href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener">Sign up on iVolunteer</a>';
		}
		$h .= '</div>';

		$full = '<a class="arv-vol__full" href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener">Open full page</a>';
		if ( 'racegoat' === $row['mode'] ) {
			$h .= '<div class="arv-vol__panel" id="' . esc_attr( $pid ) . '" hidden data-racegoat-org="' . esc_attr( ARV_VOL_RG_ORG ) . '" data-racegoat-slug="' . esc_attr( $row['rg_slug'] ) . '">';
			// Inline signup form (racegoat PR 1736): guests sign up right here; the
			// confirmation link comes by email. Allowed POST origins are the two
			// aravaiparunning.com hosts.
			$h .= '<template><div data-racegoat-volunteer-signup="' . esc_attr( $row['rg_slug'] ) . '" data-racegoat-theme="host" data-racegoat-branding="none" data-racegoat-title="none"></div></template>';
			$h .= '<div class="arv-vol__slot"></div>' . $full;
			$h .= '</div>';
		} elseif ( ! empty( $row['ivol_own'] ) ) {
			// The script makes the iframe on first open, so no iVolunteer
			// page loads until someone asks for it.
			$h .= '<div class="arv-vol__panel" id="' . esc_attr( $pid ) . '" hidden data-arv-vol-frame="' . esc_url( $row['url'] ) . '" data-arv-vol-title="' . esc_attr( 'Volunteer at ' . $row['name'] . ' on iVolunteer' ) . '">';
			$h .= '<div class="arv-vol__slot"></div>' . $full;
			$h .= '</div>';
		}

		$h .= '</div>';
	}
	if ( '' !== $month ) {
		$h .= '</div></div>';
	}
	$h .= '</div>';
	$h .= '<p class="arv-vol__empty" hidden>No races match. Try another region.</p>';
	$h .= '</div></section>';

	return $h;
}

/**
 * [aravaipa_volunteer_list heading="" intro=""]
 *
 * @param array|string $atts
 * @return string
 */
function arv_vol_shortcode( $atts ) {
	$atts = is_array( $atts ) ? $atts : array();
	return arv_vol_render(
		array(
			'heading' => isset( $atts['heading'] ) ? (string) $atts['heading'] : '',
			'intro'   => isset( $atts['intro'] ) ? (string) $atts['intro'] : '',
		)
	);
}
add_shortcode( 'aravaipa_volunteer_list', 'arv_vol_shortcode' );
