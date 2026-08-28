<?php
/**
 * What actually happened at a race: how many finished, and who won.
 *
 * The results archive knows where to read a result and nothing about the
 * result itself, so every row on it looks the same: a name, a date, and the
 * same three buttons eighty times over. Nothing on the row varies, so there
 * is nothing for the eye to catch on and the page scans as wallpaper.
 *
 * A finisher count is the fix, and it is also the one fact the events page
 * structurally cannot carry: an event that has not happened has no
 * finishers. Putting it here rather than there is what makes this page a
 * different page rather than the calendar again in past tense.
 *
 * Everything comes from the timing board's own event endpoint, which
 * publishes the full participant list for every event it has ever carried,
 * back to 2020. All 190 events linked from the archive are on it, so this is
 * a complete join rather than a half-populated column.
 *
 * An option rather than a post type, like the results and live stores and
 * for the same reason: nothing here is edited by a person, it is refreshed
 * wholesale by scripts/fetch-stats.mjs.
 *
 * Keyed by the board's own slug, the same key the live store uses, so the
 * archive's existing live URLs join to it without a new mapping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_STATS_OPTION', 'arv_race_stats' );

/**
 * Everything stored, keyed by board slug.
 *
 * @return array<string, array>
 */
function arv_stats_store_get() {
	$stored = get_option( ARV_STATS_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * One event's stats, found by the live URL an archive row carries.
 *
 * @param string $live_url
 * @return array|null
 */
function arv_stats_store_find( $live_url ) {
	$slug = arv_live_store_slug( $live_url );

	if ( '' === $slug ) {
		return null;
	}

	$stats = arv_stats_store_get();

	return isset( $stats[ $slug ] ) ? $stats[ $slug ] : null;
}

/**
 * Replace the stored stats wholesale.
 *
 * A full replace, like the other derived stores: the fetcher walks the whole
 * board every run and reports the complete picture.
 *
 * Events with no finishers are stored anyway rather than dropped, because
 * zero is a real and correct answer for a race that has not been run yet,
 * and the renderer already knows to say nothing rather than "0 finishers".
 * Dropping them would make "we have no data" and "it has not happened"
 * indistinguishable here.
 *
 * @param array $events
 * @return int Number stored.
 */
function arv_stats_store_set( $events ) {
	$clean = array();

	foreach ( (array) $events as $event ) {
		if ( ! is_array( $event ) || empty( $event['slug'] ) ) {
			continue;
		}

		$entry = array(
			'slug'      => (string) $event['slug'],
			'finishers' => isset( $event['finishers'] ) ? max( 0, (int) $event['finishers'] ) : 0,
			'starters'  => isset( $event['starters'] ) ? max( 0, (int) $event['starters'] ) : 0,
		);

		$winner = isset( $event['winner'] ) && is_array( $event['winner'] ) ? $event['winner'] : array();
		$name   = isset( $winner['name'] ) ? trim( (string) $winner['name'] ) : '';
		$time   = isset( $winner['time'] ) ? trim( (string) $winner['time'] ) : '';
		$race   = isset( $winner['race'] ) ? trim( (string) $winner['race'] ) : '';

		// Both halves or neither. "Won by Alex Bustamante" with no time reads
		// as a result we could not finish reporting, and a bare time with no
		// name is not a sentence at all.
		if ( '' !== $name && '' !== $time ) {
			$entry['winner'] = array(
				'name' => $name,
				'time' => $time,
				'race' => $race,
			);
		}

		$clean[ $entry['slug'] ] = $entry;
	}

	update_option( ARV_STATS_OPTION, $clean, false );

	return count( $clean );
}

/**
 * Write route for the fetcher. Same edit_posts scoping as the others.
 */
function arv_stats_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/stats/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_stats_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_stats_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/stats/import
 *
 * Guarded like the results importer rather than the live one. This store
 * only grows: a race that has been run stays run, so a run that reports far
 * fewer events than are held is a walk that failed partway rather than an
 * archive that shrank.
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_stats_rest_set( $request ) {
	$body   = $request->get_json_params();
	$events = isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array();
	$dry    = ! empty( $body['dry_run'] );
	$force  = ! empty( $body['force'] );

	$current = count( arv_stats_store_get() );
	$valid   = 0;

	foreach ( $events as $event ) {
		if ( is_array( $event ) && ! empty( $event['slug'] ) ) {
			$valid++;
		}
	}

	if ( ! $force && $current > 0 && $valid < ( $current * 0.8 ) ) {
		return array(
			'status'  => 'refused',
			'reason'  => 'would drop more than 20% of stored stats',
			'current' => $current,
			'valid'   => $valid,
		);
	}

	if ( $dry ) {
		return array(
			'status'  => 'dry_run',
			'current' => $current,
			'valid'   => $valid,
		);
	}

	return array(
		'status'   => 'ok',
		'stored'   => arv_stats_store_set( $events ),
		'previous' => $current,
	);
}
