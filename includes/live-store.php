<?php
/**
 * What the live timing board knows about a race.
 *
 * live.aravaiparunning.com publishes, for every event it is carrying, the
 * real start time of each distance, the event's cutoff, its timezone, and
 * the id the board itself uses for each distance. That last one is what
 * turns a "23K" chip into a link to that distance's own results.
 *
 * This matters because the race store keeps dates and not times. Everything
 * built on it could therefore only ever count down to midnight on race day,
 * which is a day out from a six in the morning start. The board has the
 * real answer and the timing team maintain it as part of running the race,
 * so it is a better source than anything typed into a spreadsheet a second
 * time.
 *
 * An option rather than a post type, like the results store and for the
 * same reason: nothing here is edited by a person, it is refreshed
 * wholesale by scripts/fetch-live.mjs.
 *
 * Keyed by the board's own slug, which the race store already carries at
 * the end of every race's live URL, so the two join without a new mapping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_LIVE_OPTION', 'arv_live_board' );

/**
 * Everything stored, keyed by board slug.
 *
 * @return array<string, array>
 */
function arv_live_store_get() {
	$stored = get_option( ARV_LIVE_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * One event, found by the live URL a race carries.
 *
 * Reads the slug out of the URL rather than asking callers to know the
 * board's naming: a race row holds
 * "https://live.aravaiparunning.com/#/black_bear-2026", and the fragment
 * after the hash is the key.
 *
 * @param string $live_url
 * @return array|null
 */
function arv_live_store_find( $live_url ) {
	$slug = arv_live_store_slug( $live_url );

	if ( '' === $slug ) {
		return null;
	}

	$board = arv_live_store_get();

	return isset( $board[ $slug ] ) ? $board[ $slug ] : null;
}

/**
 * The board's slug for a race, read out of its live URL.
 *
 * Its own function because two stores key on it now: this one and the stats
 * store, which joins to the same archive rows through the same URLs. Two
 * copies of this regex would be two things to keep in step the first time a
 * row is written with a trailing slash or an extra query parameter.
 *
 * @param string $live_url
 * @return string Empty when the URL carries no slug.
 */
function arv_live_store_slug( $live_url ) {
	$live_url = trim( (string) $live_url );

	if ( '' === $live_url ) {
		return '';
	}

	// Everything after "#/", minus any query string the row happens to
	// carry: some rows deep-link a distance with ?raceId=.
	if ( ! preg_match( '#\#/([^?\s]+)#', $live_url, $m ) ) {
		return '';
	}

	return $m[1];
}

/**
 * Replace the stored board wholesale.
 *
 * @param array $events
 * @return int Number stored.
 */
function arv_live_store_set( $events ) {
	$clean = array();

	foreach ( (array) $events as $event ) {
		if ( ! is_array( $event ) || empty( $event['slug'] ) ) {
			continue;
		}

		$races = array();

		foreach ( (array) ( isset( $event['races'] ) ? $event['races'] : array() ) as $race ) {
			if ( ! is_array( $race ) || empty( $race['id'] ) || empty( $race['name'] ) ) {
				continue;
			}

			$races[] = array(
				'id'    => (int) $race['id'],
				'name'  => (string) $race['name'],
				'start' => isset( $race['start'] ) ? (string) $race['start'] : '',
			);
		}

		$clean[ (string) $event['slug'] ] = array(
			'slug'   => (string) $event['slug'],
			'start'  => isset( $event['start'] ) ? (string) $event['start'] : '',
			'cutoff' => isset( $event['cutoff'] ) ? (string) $event['cutoff'] : '',
			'offset' => isset( $event['offset'] ) ? (float) $event['offset'] : 0,
			'races'  => $races,
		);
	}

	update_option( ARV_LIVE_OPTION, $clean, false );

	return count( $clean );
}

/**
 * Write route for the scraper. Same edit_posts scoping as the others.
 */
function arv_live_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/live/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_live_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_live_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/live/import
 *
 * No drop guardrail here, unlike the results importer. The board only
 * carries events it is actively timing, so it legitimately empties out
 * between race weekends: refusing a shrink would mean refusing the truth.
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_live_rest_set( $request ) {
	$body   = $request->get_json_params();
	$events = isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array();
	$dry    = ! empty( $body['dry_run'] );

	$current = count( arv_live_store_get() );

	if ( $dry ) {
		return array(
			'status'  => 'dry_run',
			'current' => $current,
			'valid'   => count( $events ),
		);
	}

	return array(
		'status'   => 'ok',
		'stored'   => arv_live_store_set( $events ),
		'previous' => $current,
	);
}

define( 'ARV_CUTOFF_OPTION', 'arv_race_cutoffs' );

/**
 * Cutoff durations we hold ourselves, in hours, keyed by race name.
 *
 * The timing board publishes a cutoffTime per event and that is normally
 * the right answer, since the timing team maintain it as part of running
 * the race. It is not always right though: Black Bear and Rock Hawk were
 * both reading long against what the race directors actually run to, so
 * there has to be somewhere to say otherwise without editing code.
 *
 * Hours rather than a timestamp, because a cutoff is a duration from the
 * gun: expressed that way it survives the start time moving, which is the
 * thing most likely to change late.
 *
 * @return array<string, float>
 */
function arv_race_cutoff_store_get() {
	$stored = get_option( ARV_CUTOFF_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * Replace the stored overrides wholesale.
 *
 * @param array<string, float> $map Race name => hours.
 * @return int
 */
function arv_race_cutoff_store_set( $map ) {
	$clean = array();

	foreach ( (array) $map as $name => $hours ) {
		$name  = trim( (string) $name );
		$hours = (float) $hours;

		// A zero or negative cutoff would mark a race finished the moment
		// it started, so it is treated as no override rather than stored.
		if ( '' === $name || $hours <= 0 ) {
			continue;
		}

		$clean[ $name ] = $hours;
	}

	update_option( ARV_CUTOFF_OPTION, $clean, false );

	return count( $clean );
}

/**
 * The cutoff for one race, as a timestamp, or 0 when there is none.
 *
 * Order of preference: an override we hold, then whatever the board says,
 * then nothing. Nothing is a real answer and means the race week block
 * falls back to marking a race finished at the end of its last day, which
 * is what it did before any of this existed.
 *
 * @param string $name  Race name.
 * @param array|null $board Board entry, or null.
 * @return int Unix timestamp, or 0.
 */
function arv_race_cutoff_for( $name, $board ) {
	$overrides = arv_race_cutoff_store_get();
	$hours     = isset( $overrides[ $name ] ) ? (float) $overrides[ $name ] : 0;

	/**
	 * Filters the cutoff duration, in hours, for one race.
	 *
	 * @param float      $hours 0 when there is no override.
	 * @param string     $name
	 * @param array|null $board
	 */
	$hours = (float) apply_filters( 'arv_race_cutoff_hours', $hours, $name, $board );

	$start = ( null !== $board && ! empty( $board['start'] ) ) ? strtotime( $board['start'] ) : 0;

	// An override is a duration from the gun, so it needs a gun to measure
	// from. Without a start time the board's own cutoff is all there is.
	if ( $hours > 0 && $start ) {
		return (int) ( $start + round( $hours * 3600 ) );
	}

	if ( null !== $board && ! empty( $board['cutoff'] ) ) {
		return (int) strtotime( $board['cutoff'] );
	}

	return 0;
}

/**
 * Write route for the cutoff overrides.
 */
function arv_race_cutoff_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/races/cutoffs',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_race_cutoff_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_race_cutoff_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/races/cutoffs
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_race_cutoff_rest_set( $request ) {
	$body = $request->get_json_params();
	$map  = isset( $body['cutoffs'] ) && is_array( $body['cutoffs'] ) ? $body['cutoffs'] : array();

	return array(
		'status' => 'ok',
		'stored' => arv_race_cutoff_store_set( $map ),
	);
}
