<?php
/**
 * Which upcoming Aravaipa races an athlete is registered for, and results
 * for races already run, both matched against UltraSignup rather than
 * typed in by hand.
 *
 * An athlete's profile going stale is the default outcome of a hand-typed
 * field: the person who would update it is out training, not editing
 * WordPress. This makes "next race" and "recent result" a fact the site
 * looks up instead of a fact someone has to remember to type.
 *
 * Rebuilt on a daily cron rather than fetched at page-render time. That
 * distinction matters here specifically: /photos/ and /results/ were
 * measured at 20-35 seconds to generate because they did real work per
 * page view, and this would be worse, one UltraSignup request per
 * confirmed upcoming race, on every athlete page load. The build runs
 * once a day; every page read is a fast local option read.
 *
 * Matching is name-based where an athlete has no stored UltraSignup ID
 * yet, and that is a known soft spot: a name matcher already missed a real
 * result once this week because a race name differed slightly between two
 * sources, and separately, 12 of 51 UltraSignup links inherited from the
 * old roster page turned out to point at the wrong person entirely. Every
 * name match here is required to be unique among that race's entrants
 * before it counts, and the first confirmed match for an athlete writes
 * their UltraSignup participant ID back to their profile, so the same
 * athlete is joined by ID, not name, on every rebuild after the first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ARV_ATHLETE_UPCOMING_OPTION = 'arv_athlete_upcoming';

/**
 * The UltraSignup event id (did) a race's registration URL points at.
 *
 * A race page can carry either did= directly, or dtid= (a per-distance
 * registration id) that only resolves to a did by following the actual
 * register.aspx redirect. Both are handled because the store has both:
 * dtid is what most confirmed upcoming races on this site actually use.
 *
 * @param string $register_url
 * @return int 0 if no id could be resolved.
 */
function arv_athlete_ultrasignup_did( $register_url ) {
	if ( '' === $register_url ) {
		return 0;
	}

	if ( preg_match( '~[?&]did=(\d+)~', $register_url, $m ) ) {
		return (int) $m[1];
	}

	if ( ! preg_match( '~[?&]dtid=(\d+)~', $register_url, $m ) ) {
		return 0;
	}

	$dtid = $m[1];
	$key  = 'arv_us_did_' . $dtid;
	$did  = get_transient( $key );

	if ( false !== $did ) {
		return (int) $did;
	}

	$response = wp_remote_get(
		'https://ultrasignup.com/register.aspx?dtid=' . rawurlencode( $dtid ),
		array( 'timeout' => 6, 'redirection' => 5 )
	);

	$did = 0;

	if ( ! is_wp_error( $response ) ) {
		$body = wp_remote_retrieve_body( $response );
		if ( preg_match( '~did=(\d+)~', $body, $m2 ) ) {
			$did = (int) $m2[1];
		}
	}

	// A month: a race's dtid-to-did mapping is fixed once UltraSignup builds
	// the event, so this only needs to survive not being refetched forever
	// for a race that never resolves (host down, page changed).
	set_transient( $key, $did, MONTH_IN_SECONDS );

	return $did;
}

/**
 * An UltraSignup event's entrant list.
 *
 * @param int $did
 * @return array<int, array>
 */
function arv_athlete_ultrasignup_entrants( $did ) {
	if ( ! $did ) {
		return array();
	}

	$key    = 'arv_us_entrants_' . $did;
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://ultrasignup.com/service/events.svc/entrants/' . $did . '/json',
		array( 'timeout' => 8 )
	);

	$entrants = array();

	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) ) {
			$entrants = $body;
		}
	}

	// Six hours: an entrant list changes as people register or scratch, and
	// this is read once a day by the rebuild, not per page view, so there is
	// no cost to keeping it reasonably fresh.
	set_transient( $key, $entrants, 6 * HOUR_IN_SECONDS );

	return $entrants;
}

/**
 * Find the one entrant, if any, who is unambiguously a given athlete.
 *
 * Exact ID match first, when the athlete already has one on file. Falling
 * back to a first-and-last-name match only when it identifies exactly one
 * entrant: two entrants who share a name is the same ambiguity a wrong
 * match would be, and no result is the honest answer to that, the same
 * rule the results and photos stores already use for their own name
 * matching.
 *
 * @param array          $athlete    From arv_athlete_store_get_one().
 * @param array<int, array> $entrants
 * @return array|null
 */
function arv_athlete_match_entrant( $athlete, $entrants ) {
	if ( empty( $entrants ) ) {
		return null;
	}

	if ( '' !== $athlete['ultrasignup_id'] ) {
		foreach ( $entrants as $e ) {
			if ( isset( $e['participant_id'] ) && (string) $e['participant_id'] === (string) $athlete['ultrasignup_id'] ) {
				return $e;
			}
		}
		return null;
	}

	$parts = preg_split( '/\s+/', trim( $athlete['name'] ) );

	if ( count( $parts ) < 2 ) {
		return null;
	}

	$first = $parts[0];
	$last  = end( $parts );

	$hits = array();

	foreach ( $entrants as $e ) {
		if ( ! isset( $e['firstname'], $e['lastname'] ) ) {
			continue;
		}
		if ( 0 === strcasecmp( $e['firstname'], $first ) && 0 === strcasecmp( $e['lastname'], $last ) ) {
			$hits[] = $e;
		}
	}

	return ( 1 === count( $hits ) ) ? $hits[0] : null;
}

/**
 * Rebuild the whole athlete-to-race mapping. Runs on a daily cron.
 *
 * Scoped to confirmed races in the next six months rather than everything
 * on file: most of the 87 "upcoming" races on this site are unconfirmed
 * placeholder dates a year or more out, dtid or did included, and neither
 * has an entrant list worth reading yet.
 */
function arv_athlete_upcoming_rebuild() {
	$races = function_exists( 'arv_race_store_get' ) ? arv_race_store_get() : array();
	$today = current_time( 'Y-m-d' );
	$limit = gmdate( 'Y-m-d', strtotime( '+6 months', strtotime( $today ) ) );

	$candidates = array();

	foreach ( (array) $races as $race ) {
		$iso = isset( $race['iso'] ) ? $race['iso'] : '';

		if ( '' === $iso || $iso < $today || $iso > $limit ) {
			continue;
		}

		if ( empty( $race['confirmed'] ) ) {
			continue;
		}

		$did = arv_athlete_ultrasignup_did( isset( $race['register'] ) ? $race['register'] : '' );

		if ( ! $did ) {
			continue;
		}

		// A weekend with more than one distance, Javelina Jundred and its
		// own Jackass Night Trail among them, is two rows in the race store
		// sharing one UltraSignup registration. Their entrant list is the
		// whole event, not one distance, so it cannot say which distance a
		// given entrant is actually running. Verified on production data:
		// this pairing produced every runner in the field listed as
		// registered for both races. Kept once, under whichever name
		// appears first, rather than shown as two separate races nobody
		// can actually confirm they are running.
		if ( isset( $candidates[ $did ] ) ) {
			continue;
		}

		$candidates[ $did ] = array(
			'name' => $race['name'],
			'iso'  => $iso,
			'url'  => isset( $race['page'] ) ? $race['page'] : '',
			'did'  => $did,
		);
	}

	$athletes = get_posts(
		array(
			'post_type'      => ARV_ATHLETE_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array( array( 'key' => '_arv_status', 'value' => 'alumni', 'compare' => '!=' ) ),
		)
	);

	$mapping = array();

	foreach ( $candidates as $race ) {
		$entrants = arv_athlete_ultrasignup_entrants( $race['did'] );

		if ( empty( $entrants ) ) {
			continue;
		}

		foreach ( $athletes as $post ) {
			$athlete = arv_athlete_store_get_one( $post );
			$match   = arv_athlete_match_entrant( $athlete, $entrants );

			if ( ! $match ) {
				continue;
			}

			// The first confirmed match backfills the athlete's UltraSignup
			// ID, same as the alt-text safety net does for photos: every
			// rebuild after this one joins this athlete by ID, not name.
			if ( '' === $athlete['ultrasignup_id'] && ! empty( $match['participant_id'] ) ) {
				update_post_meta( $post->ID, '_arv_ultrasignup_id', sanitize_text_field( (string) $match['participant_id'] ) );
			}

			$mapping[ $post->ID ][] = array(
				'race' => $race['name'],
				'iso'  => $race['iso'],
				'url'  => $race['url'],
			);
		}
	}

	foreach ( $mapping as $id => $races_for_athlete ) {
		usort( $races_for_athlete, function ( $a, $b ) { return strcmp( $a['iso'], $b['iso'] ); } );
		$mapping[ $id ] = $races_for_athlete;
	}

	update_option( ARV_ATHLETE_UPCOMING_OPTION, $mapping, false );
}

/**
 * An athlete's upcoming races, read from the last rebuild. No live request:
 * see the file header for why this has to be a cached read.
 *
 * @param int $post_id
 * @return array<int, array{race: string, iso: string, url: string}>
 */
function arv_athlete_upcoming_races( $post_id ) {
	$mapping = get_option( ARV_ATHLETE_UPCOMING_OPTION, array() );

	return isset( $mapping[ $post_id ] ) ? $mapping[ $post_id ] : array();
}

/**
 * "Next race" markup for an athlete's profile.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_profile_upcoming_markup( $athlete ) {
	$races = arv_athlete_upcoming_races( $athlete['id'] );

	if ( empty( $races ) ) {
		return '';
	}

	$out = '<div class="arv-athlete__upcoming"><h2>' . esc_html__( 'Next Races', 'aravaipa-elements' ) . '</h2><ul class="arv-athlete__upcoming-list">';

	foreach ( $races as $race ) {
		$when = date_i18n( 'F j, Y', strtotime( $race['iso'] ) );

		if ( '' !== $race['url'] ) {
			$out .= '<li><a href="' . esc_url( $race['url'] ) . '">' . esc_html( $race['race'] ) . '</a> &mdash; ' . esc_html( $when ) . '</li>';
		} else {
			$out .= '<li>' . esc_html( $race['race'] ) . ' &mdash; ' . esc_html( $when ) . '</li>';
		}
	}

	$out .= '</ul></div>';

	return $out;
}

/**
 * Daily cron registration.
 */
function arv_athlete_upcoming_cron_schedule() {
	if ( ! wp_next_scheduled( 'arv_athlete_upcoming_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'arv_athlete_upcoming_cron' );
	}
}
add_action( 'init', 'arv_athlete_upcoming_cron_schedule' );
add_action( 'arv_athlete_upcoming_cron', 'arv_athlete_upcoming_rebuild' );

/**
 * Leave no scheduled event running for a plugin that is no longer active.
 */
function arv_athlete_upcoming_deactivate() {
	wp_clear_scheduled_hook( 'arv_athlete_upcoming_cron' );
}
register_deactivation_hook( ARV_ELEMENTS_PATH . 'aravaipa-elements.php', 'arv_athlete_upcoming_deactivate' );

/**
 * A manual rebuild button under Athletes, for confirming this works without
 * waiting for tomorrow's cron.
 */
function arv_athlete_upcoming_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=' . ARV_ATHLETE_POST_TYPE,
		__( 'Upcoming Races', 'aravaipa-elements' ),
		__( 'Upcoming Races', 'aravaipa-elements' ),
		'manage_options',
		'arv-athlete-upcoming',
		'arv_athlete_upcoming_admin_screen'
	);
}
add_action( 'admin_menu', 'arv_athlete_upcoming_admin_menu' );

function arv_athlete_upcoming_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['arv_rebuild'] ) && check_admin_referer( 'arv_athlete_upcoming_rebuild' ) ) {
		$start = microtime( true );
		arv_athlete_upcoming_rebuild();
		$elapsed = round( microtime( true ) - $start, 1 );
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html( sprintf( 'Rebuilt in %s seconds.', $elapsed ) )
		);
	}

	$mapping = get_option( ARV_ATHLETE_UPCOMING_OPTION, array() );

	echo '<div class="wrap"><h1>' . esc_html__( 'Upcoming Races', 'aravaipa-elements' ) . '</h1>';
	echo '<p>' . esc_html__( 'Matches confirmed races in the next 6 months against UltraSignup entrant lists. Runs automatically once a day; use this to run it now.', 'aravaipa-elements' ) . '</p>';

	echo '<form method="post">';
	wp_nonce_field( 'arv_athlete_upcoming_rebuild' );
	submit_button( __( 'Rebuild now', 'aravaipa-elements' ), 'primary', 'arv_rebuild' );
	echo '</form>';

	printf( '<p>%s</p>', esc_html( sprintf( '%d athletes currently have an upcoming race matched.', count( $mapping ) ) ) );

	echo '<table class="widefat"><thead><tr><th>Athlete</th><th>Races</th></tr></thead><tbody>';
	foreach ( $mapping as $post_id => $races ) {
		$title = get_the_title( $post_id );
		$list  = implode( ', ', array_map( function ( $r ) { return $r['race'] . ' (' . $r['iso'] . ')'; }, $races ) );
		printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $title ), esc_html( $list ) );
	}
	echo '</tbody></table></div>';
}
