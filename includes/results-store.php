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
 * Every stored result, newest first.
 *
 * @return array<int, array> Each: name, iso, display, live, ultrasignup, ultrarunning.
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

		$entry = array(
			'name'         => $name,
			'iso'          => $iso,
			'display'      => isset( $row['display'] ) ? trim( (string) $row['display'] ) : '',
			'live'         => isset( $row['live'] ) ? esc_url_raw( trim( (string) $row['live'] ) ) : '',
			'ultrasignup'  => isset( $row['ultrasignup'] ) ? esc_url_raw( trim( (string) $row['ultrasignup'] ) ) : '',
			'ultrarunning' => isset( $row['ultrarunning'] ) ? esc_url_raw( trim( (string) $row['ultrarunning'] ) ) : '',
		);

		if ( '' === $entry['live'] && '' === $entry['ultrasignup'] && '' === $entry['ultrarunning'] ) {
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
