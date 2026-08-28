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
	$live_url = trim( (string) $live_url );

	if ( '' === $live_url ) {
		return null;
	}

	// Everything after "#/", minus any query string the row happens to
	// carry: some rows deep-link a distance with ?raceId=.
	if ( ! preg_match( '#\#/([^?\s]+)#', $live_url, $m ) ) {
		return null;
	}

	$slug  = $m[1];
	$board = arv_live_store_get();

	return isset( $board[ $slug ] ) ? $board[ $slug ] : null;
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
