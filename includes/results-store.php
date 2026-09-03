<?php
/**
 * Past race results: where to read them, per race, per year.
 *
 * Separate from the race store, because these are different things. That
 * store answers "what can I enter", is edited by hand in wp-admin, and drops
 * a race once it has run. This one answers "where do I read what happened",
 * is entirely derived from the site's own results pages, and only ever grows.
 *
 * An option rather than a post type, unlike races, for the same reason the
 * waitlist map is an option: nothing here is ever edited by a person. It is
 * regenerated wholesale by scripts/fetch-results.mjs, so a record with no
 * author, no revisions and no admin screen is the honest shape for it. About
 * two hundred rows of six short fields, read once per request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_RESULTS_OPTION', 'arv_race_results' );

/**
 * A row's archive links, as label and url pairs.
 *
 * Shared by the reader and the writer so both agree on what a valid one is,
 * and so a malformed entry is dropped in one place rather than two.
 *
 * @param array $row
 * @return array<int, array{label:string,url:string}>
 */
function arv_results_archive_links( $row ) {
	$out = array();

	foreach ( (array) ( isset( $row['archive'] ) ? $row['archive'] : array() ) as $link ) {
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			continue;
		}

		$url = esc_url_raw( trim( (string) $link['url'] ) );

		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			continue;
		}

		$out[] = array(
			'label' => isset( $link['label'] ) ? sanitize_text_field( (string) $link['label'] ) : '',
			'url'   => $url,
		);
	}

	return $out;
}

/**
 * Every stored result, newest first.
 *
 * @return array<int, array> Each: name, iso, display, live, ultrasignup,
 *                           ultrarunning, archive.
 */
function arv_results_store_get() {
	$stored = get_option( ARV_RESULTS_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	$out = array();

	foreach ( $stored as $row ) {
		if ( ! is_array( $row ) || empty( $row['name'] ) || empty( $row['iso'] ) ) {
			continue;
		}

		$out[] = array(
			// Corrected on read for the same reason the race store does it:
			// the board named the same race two different things in two
			// consecutive years and neither is what it is called.
			'name'         => arv_race_display_name( (string) $row['name'] ),
			'iso'          => (string) $row['iso'],
			'display'      => isset( $row['display'] ) ? (string) $row['display'] : '',
			'live'         => isset( $row['live'] ) ? (string) $row['live'] : '',
			'ultrasignup'  => isset( $row['ultrasignup'] ) ? (string) $row['ultrasignup'] : '',
			'ultrarunning' => isset( $row['ultrarunning'] ) ? (string) $row['ultrarunning'] : '',
			// Rebuilt rather than passed through, like every field above it,
			// so a row read out of the option has one shape whenever it was
			// written. A row from before this existed simply has none.
			'archive'      => arv_results_archive_links( $row ),
		);
	}

	usort(
		$out,
		function ( $a, $b ) {
			if ( $a['iso'] === $b['iso'] ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
			return ( $a['iso'] < $b['iso'] ) ? 1 : -1;
		}
	);

	return $out;
}

/**
 * Replace the stored results wholesale.
 *
 * A full replace, like the waitlist map and for a related reason: the
 * scraper reports the complete picture every run, so a row that has stopped
 * appearing has been removed from the site on purpose and should stop
 * appearing here too. Merging would make a deleted result immortal.
 *
 * Rows with no name, no date, or no links at all are dropped rather than
 * stored empty: a results row that links nowhere is a table row promising
 * something it cannot deliver.
 *
 * @param array $rows
 * @return int Number stored.
 */
function arv_results_store_set( $rows ) {
	$clean = array();
	$seen  = array();

	foreach ( (array) $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		$iso  = isset( $row['iso'] ) ? arv_upcoming_races_date( (string) $row['iso'] ) : '';

		if ( '' === $name || '' === $iso ) {
			continue;
		}

		// A labelled way through to a result that is not one of the three
		// named providers. Everything before 2020 is this: the race was
		// scored on Aravaipa's own site, one static file per distance, so a
		// single row carries up to five of them and each needs its own label
		// to be worth anything. The three columns above hold one link each
		// and cannot say which is the 50K.
		//
		// Also where RaceResult, RunSignup, Ultracast and the timing
		// subdomain land. Fifteen years of racing predates settling on one
		// provider, and none of those four earns a column of its own.
		$archive = arv_results_archive_links( $row );

		$entry = array(
			'name'         => $name,
			'iso'          => $iso,
			'display'      => isset( $row['display'] ) ? trim( (string) $row['display'] ) : '',
			'live'         => isset( $row['live'] ) ? esc_url_raw( trim( (string) $row['live'] ) ) : '',
			'ultrasignup'  => isset( $row['ultrasignup'] ) ? esc_url_raw( trim( (string) $row['ultrasignup'] ) ) : '',
			'ultrarunning' => isset( $row['ultrarunning'] ) ? esc_url_raw( trim( (string) $row['ultrarunning'] ) ) : '',
			'archive'      => $archive,
		);

		// A row with no way through to a result is a row that says a race
		// happened and nothing else, which the calendar already says better.
		// The archive counts: before 2020 it is usually the only one there
		// is, and dropping those rows would have discarded most of the years
		// this store was extended to hold.
		if ( '' === $entry['live'] && '' === $entry['ultrasignup'] && '' === $entry['ultrarunning'] && empty( $archive ) ) {
			continue;
		}

		// One row per race per running. The same race appears on one year
		// page once; a duplicate means the scraper saw the same block twice.
		$key = strtolower( $name ) . '|' . $iso;
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;

		$clean[] = $entry;
	}

	update_option( ARV_RESULTS_OPTION, $clean, false );

	return count( $clean );
}

/**
 * Write route for the scraper.
 *
 * Same edit_posts scoping as the race importer and the waitlist map, for the
 * same reason: reachable by the Editor-scoped Application Password the
 * scrapers run as, and by nothing with more reach than that.
 */
function arv_results_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/results/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_results_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_results_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/results/import
 *
 * Guarded the same way the race importer is. This replaces everything, so a
 * scrape that half-failed and reported four races would otherwise wipe two
 * hundred. Refuses a drop of more than 20% unless forced, and supports a dry
 * run so the scraper can report before it writes.
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_results_rest_set( $request ) {
	$body  = $request->get_json_params();
	$rows  = isset( $body['rows'] ) && is_array( $body['rows'] ) ? $body['rows'] : array();
	$dry   = ! empty( $body['dry_run'] );
	$force = ! empty( $body['force'] );

	$current = count( arv_results_store_get() );
	$valid   = 0;

	foreach ( $rows as $row ) {
		if ( is_array( $row ) && ! empty( $row['name'] ) && ! empty( $row['iso'] ) ) {
			$valid++;
		}
	}

	if ( ! $force && $current > 0 && $valid < ( $current * 0.8 ) ) {
		return array(
			'status'  => 'refused',
			'reason'  => 'would drop more than 20% of stored results',
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

	$stored = arv_results_store_set( $rows );

	return array(
		'status'   => 'ok',
		'stored'   => $stored,
		'previous' => $current,
	);
}

define( 'ARV_ULTRARUNNING_OPTION', 'arv_race_ultrarunning' );

/**
 * UltraRunning Magazine result pages, keyed by race.
 *
 * Unlike UltraSignup, this one cannot be derived. UltraSignup's results page
 * is the race's own registration id under a different filename, so it falls
 * out of data the calendar already holds. UltraRunning's is
 * /calendar/event/{slug}/race/{id}/results, and neither part is guessable
 * from anything on this side: the slug is their editorial name for the race
 * ("black-canyon-trail" for Black Canyon Ultras, "jackpot-ultra-running-
 * festival" for Jackpot Ultras), and the id is theirs. Guessing the slug and
 * asking returns 403, and their site sits behind a bot challenge that a real
 * browser bounces off, which is there precisely to stop this being looked
 * up automatically. So it is entered once and remembered.
 *
 * Once, though, and not once a year: their id identifies the race rather
 * than the edition, and the same URL keeps working every season with the
 * year selected on their end. That is the whole reason this is worth
 * storing rather than pasting onto a page each time.
 *
 * Keyed by arv_results_race_key(), the same normaliser the archive groups
 * editions with, so one entry covers every spelling of a race across every
 * year rather than needing a row per edition.
 *
 * @return array<string, string> Race key => path, e.g.
 *                               "black-canyon-trail/race/44116".
 */
function arv_results_ultrarunning_store_get() {
	$stored = get_option( ARV_ULTRARUNNING_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * Replace the stored map wholesale.
 *
 * Takes whatever shape the person pasting it had to hand: a full results
 * URL copied out of the address bar, or just the "{slug}/race/{id}" part.
 * Asking someone to strip a URL down by hand before pasting it is asking
 * them to make a mistake that shows up later as a dead link.
 *
 * @param array<string, string> $map Race name or key => URL or path.
 * @return int How many entries were stored.
 */
function arv_results_ultrarunning_store_set( $map ) {
	$clean = array();

	foreach ( (array) $map as $name => $value ) {
		$key  = function_exists( 'arv_results_race_key' )
			? arv_results_race_key( (string) $name )
			: strtolower( trim( (string) $name ) );
		$path = arv_results_ultrarunning_path( $value );

		if ( '' === $key || '' === $path ) {
			continue;
		}

		$clean[ $key ] = $path;
	}

	ksort( $clean );

	update_option( ARV_ULTRARUNNING_OPTION, $clean, false );

	return count( $clean );
}

/**
 * The "{slug}/race/{id}" part of an UltraRunning results URL.
 *
 * Validated rather than trusted, and deliberately narrow: anything that is
 * not recognisably one of their race paths returns '' and is dropped, so a
 * typo becomes a missing link rather than a link to nowhere. The trailing
 * "/results" and any "#selected_year" anchor are stripped, because those are
 * ours to add back consistently.
 *
 * @param string $value Full URL or bare path.
 * @return string
 */
function arv_results_ultrarunning_path( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '#(?:^|ultrarunning\.com/)(?:calendar/event/)?([a-z0-9-]+)/race/(\d+)#i', $value, $m ) ) {
		return strtolower( $m[1] ) . '/race/' . $m[2];
	}

	return '';
}

/**
 * The UltraRunning results URL for a race, or '' when none is on file.
 *
 * @param string $name
 * @return string
 */
function arv_results_ultrarunning_url( $name ) {
	if ( ! function_exists( 'arv_results_race_key' ) ) {
		return '';
	}

	$map = arv_results_ultrarunning_store_get();
	$key = arv_results_race_key( (string) $name );

	if ( '' === $key || ! isset( $map[ $key ] ) ) {
		return '';
	}

	return 'https://ultrarunning.com/calendar/event/' . $map[ $key ] . '/results';
}

/**
 * Write route for the UltraRunning map.
 *
 * Same shape as the cutoff overrides: a wholesale replace over REST, so the
 * map can be maintained from a script or a paste without a plugin release
 * and without an admin screen nobody would visit twice a year.
 */
function arv_results_ultrarunning_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/races/ultrarunning',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_results_ultrarunning_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_results_ultrarunning_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/races/ultrarunning
 *
 * Body: { "races": { "Black Canyon Ultras": "https://ultrarunning.com/...", ... } }
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_results_ultrarunning_rest_set( $request ) {
	$body  = (array) $request->get_json_params();
	$races = isset( $body['races'] ) && is_array( $body['races'] ) ? $body['races'] : array();

	if ( empty( $races ) ) {
		return array(
			'status' => 'error',
			'reason' => 'no races given',
		);
	}

	// Reported rather than silently dropped: a paste with a typo in it
	// should say so, not quietly store one fewer race than was sent.
	$rejected = array();

	foreach ( $races as $name => $value ) {
		if ( '' === arv_results_ultrarunning_path( $value ) ) {
			$rejected[] = (string) $name;
		}
	}

	$stored = arv_results_ultrarunning_store_set( $races );

	return array(
		'status'   => 'ok',
		'stored'   => $stored,
		'rejected' => $rejected,
	);
}
