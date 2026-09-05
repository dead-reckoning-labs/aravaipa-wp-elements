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
 * The divisions a race can be scored in, in the order a table reads them.
 *
 * Nonbinary is a division Aravaipa actually scores, not a stray value on a
 * few entries: Javelina 2025's Jackass 31K placed four nonbinary finishers
 * first through fourth, and Black Canyon has scored it twice. Nine events on
 * the archive have a nonbinary category winner, so a men-and-women table
 * would quietly erase real results from the flagship race.
 *
 * @return array<int, string>
 */
function arv_stats_divisions() {
	return array( 'men', 'women', 'nonbinary' );
}

/**
 * Only the divisions one event actually scored.
 *
 * The table's columns come from here rather than from the full list, so the
 * hundred and seventy-eight events with no nonbinary entrant do not each
 * carry an empty column to accommodate the nine that do.
 *
 * @param array $winners
 * @return array<int, string>
 */
function arv_stats_divisions_present( $winners ) {
	$present = array();

	foreach ( arv_stats_divisions() as $division ) {
		foreach ( (array) $winners as $row ) {
			if ( isset( $row[ $division ] ) ) {
				$present[] = $division;
				break;
			}
		}
	}

	return $present;
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
		$entry = arv_stats_clean_event( $event );

		if ( null === $entry ) {
			continue;
		}

		$clean[ $entry['slug'] ] = $entry;
	}

	update_option( ARV_STATS_OPTION, $clean, false );

	return count( $clean );
}

/**
 * One event, cleaned: the checks every stored event has to pass, wherever
 * it came from.
 *
 * Extracted so the archive store can run its own events through exactly
 * this and not a second, drifting copy of it. A winner needs both a name
 * and a time or it is not reported, a distance with no surviving division
 * is not a row, and the counts floor at zero.
 *
 * @param array $event
 * @return array|null Null when there is nothing worth storing.
 */
function arv_stats_clean_event( $event ) {
	{
		if ( ! is_array( $event ) || empty( $event['slug'] ) ) {
			return null;
		}

		$entry = array(
			'slug'      => (string) $event['slug'],
			'finishers' => isset( $event['finishers'] ) ? max( 0, (int) $event['finishers'] ) : 0,
			'starters'  => isset( $event['starters'] ) ? max( 0, (int) $event['starters'] ) : 0,
			// The largest single distance, which is what sizes the embedded
			// board. Not the same as starters: the board shows one distance
			// at a time, so an event with six of them is six short lists
			// rather than one long one.
			'rows'      => isset( $event['rows'] ) ? max( 0, (int) $event['rows'] ) : 0,
		);

		// Whether the longest distance is the event's premier race rather than
		// one of several the same length. Only the headline depends on it; the
		// table lists every distance either way.
		$entry['headline'] = ! empty( $event['headline'] );

		$winners = array();

		foreach ( (array) ( isset( $event['winners'] ) ? $event['winners'] : array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean_row = array(
				'distance' => isset( $row['distance'] ) ? trim( (string) $row['distance'] ) : '',
			);

			foreach ( arv_stats_divisions() as $division ) {
				if ( ! isset( $row[ $division ] ) || ! is_array( $row[ $division ] ) ) {
					continue;
				}

				$name = isset( $row[ $division ]['name'] ) ? trim( (string) $row[ $division ]['name'] ) : '';
				$time = isset( $row[ $division ]['time'] ) ? trim( (string) $row[ $division ]['time'] ) : '';

				// Both halves or neither. A name with no time reads as a
				// result we could not finish reporting, and a bare time with
				// no name is not a sentence at all.
				if ( '' !== $name && '' !== $time ) {
					$clean_row[ $division ] = array(
						'name' => $name,
						'time' => $time,
					);
				}
			}

			// A distance whose every division failed its checks is not a row,
			// it is an empty line in a table.
			if ( count( $clean_row ) > 1 ) {
				$winners[] = $clean_row;
			}
		}

		if ( ! empty( $winners ) ) {
			$entry['winners'] = $winners;
		}

		return $entry;
	}
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

/**
 * Winners and finisher counts for the years before the timing board.
 *
 * A separate option from ARV_STATS_OPTION, deliberately, and this is the
 * one design decision here worth explaining. Both stores are replaced
 * wholesale by their own scraper, which is right: each one walks its whole
 * source every run, so absence from that walk is meaningful. Put both
 * sources in one option and the next fetch-stats.mjs run, which knows only
 * about Momentum, would silently delete every archive entry it had never
 * heard of. Two sources, two stores, each owning its own wholesale replace,
 * and neither able to destroy the other's work.
 *
 * Keyed by name and date rather than a board slug, because these editions
 * have no board: that is the entire reason their results are static files
 * on this server. Confirmed unambiguous before choosing it, no two rows in
 * the 548-row results store share a name and a date.
 */
define( 'ARV_ARCHIVE_STATS_OPTION', 'arv_race_archive_stats' );

/**
 * The key one archive edition's stats are stored under.
 *
 * @param string $name
 * @param string $iso
 * @return string
 */
function arv_archive_stats_key( $name, $iso ) {
	$name = strtolower( trim( (string) $name ) );
	$iso  = trim( (string) $iso );

	if ( '' === $name || '' === $iso ) {
		return '';
	}

	return $name . '|' . $iso;
}

/**
 * Everything parsed out of the static result files, keyed as above.
 *
 * @return array<string, array>
 */
function arv_archive_stats_store_get() {
	$stored = get_option( ARV_ARCHIVE_STATS_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * Replace the archive stats wholesale.
 *
 * Runs each event through arv_stats_store_set()'s own cleaning by handing
 * it a slug it will accept and reading the result back, so a winner row has
 * to survive exactly the same checks whichever source it came from: both
 * halves of a name and a time or neither, a distance with no divisions
 * dropped, counts floored at zero. One rule, one place.
 *
 * @param array $events Each needs name and iso, plus the usual stats shape.
 * @return int Number stored.
 */
function arv_archive_stats_store_set( $events ) {
	$clean = array();

	foreach ( (array) $events as $event ) {
		if ( ! is_array( $event ) ) {
			continue;
		}

		$name = isset( $event['name'] ) ? (string) $event['name'] : '';
		$iso  = isset( $event['iso'] ) ? (string) $event['iso'] : '';
		$key  = arv_archive_stats_key( $name, $iso );

		if ( '' === $key ) {
			continue;
		}

		$entry         = $event;
		$entry['slug'] = $key;

		$cleaned = arv_stats_clean_event( $entry );

		if ( null === $cleaned ) {
			continue;
		}

		$cleaned['name'] = trim( $name );
		$cleaned['iso']  = trim( $iso );

		$clean[ $key ] = $cleaned;
	}

	update_option( ARV_ARCHIVE_STATS_OPTION, $clean, false );

	return count( $clean );
}

/**
 * One edition's stats, from whichever store has them.
 *
 * The board first, because where a race has one it is the better record:
 * it is what actually timed the race. The static files are the fallback,
 * and for everything before 2020 they are the only record there is.
 *
 * @param array $row A results store row.
 * @return array|null
 */
function arv_stats_for_row( $row ) {
	$live = isset( $row['live'] ) ? (string) $row['live'] : '';

	if ( '' !== $live ) {
		$board = arv_stats_store_find( $live );

		if ( null !== $board ) {
			return $board;
		}
	}

	$key    = arv_archive_stats_key(
		isset( $row['name'] ) ? $row['name'] : '',
		isset( $row['iso'] ) ? $row['iso'] : ''
	);
	$stats  = arv_archive_stats_store_get();

	return ( '' !== $key && isset( $stats[ $key ] ) ) ? $stats[ $key ] : null;
}

/**
 * Register the archive stats write route.
 */
function arv_archive_stats_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/stats/archive',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_archive_stats_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_archive_stats_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/stats/archive
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_archive_stats_rest_set( $request ) {
	$body   = $request->get_json_params();
	$events = isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array();
	$dry    = ! empty( $body['dry_run'] );

	$valid = 0;

	foreach ( $events as $event ) {
		if ( is_array( $event ) && ! empty( $event['name'] ) && ! empty( $event['iso'] ) ) {
			$valid++;
		}
	}

	if ( $dry ) {
		return array(
			'status'  => 'dry_run',
			'current' => count( arv_archive_stats_store_get() ),
			'valid'   => $valid,
		);
	}

	$previous = count( arv_archive_stats_store_get() );

	return array(
		'status'   => 'ok',
		'stored'   => arv_archive_stats_store_set( $events ),
		'previous' => $previous,
	);
}

/**
 * Read route, so a fix to one edition can be a dump, a patch and a post
 * rather than a guess, same as the results and photos stores.
 */
function arv_archive_stats_register_list_route() {
	register_rest_route(
		'aravaipa/v1',
		'/stats/archive',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				return new WP_REST_Response( array( 'events' => arv_archive_stats_store_get() ), 200 );
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_archive_stats_register_list_route' );
