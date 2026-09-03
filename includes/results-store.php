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
 * Read route for the store.
 *
 * The import route replaces the whole thing, so fixing one row (an editor
 * hand you a corrected UltraRunning link for a single old edition) means
 * dumping the current store, patching that one row, and posting it back.
 * Without this there was nothing to dump from: the only prior way to see
 * live rows was a stale file from an earlier scrape, and the store had
 * moved on since (561 rows in the last dump, 544 actually live, a two-hour
 * gap that would have silently reverted whatever ran in between). Read-only,
 * edit_posts like the import route beside it and the same pair on photos
 * and races, for the scripts that already authenticate as an Editor.
 *
 * @return WP_REST_Response
 */
function arv_results_rest_list() {
	return new WP_REST_Response( array( 'rows' => arv_results_store_get() ), 200 );
}

/**
 * Register the list route.
 */
function arv_results_register_list_route() {
	register_rest_route(
		'aravaipa/v1',
		'/results',
		array(
			'methods'             => 'GET',
			'callback'            => 'arv_results_rest_list',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_results_register_list_route' );

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

/**
 * A race's own URL segment, from its name.
 *
 * Slugged from the name as written, not from arv_results_race_key. The key
 * exists to make "Black Canyon Ultras" and "Black Canyon Trail Runs" match
 * each other, and it does that by stripping words and singularising anything
 * over four letters, which is right for matching and wrong for a URL:
 * "Across The Years" keys to "acros year" and would have been published at
 * /results/acros-year.
 *
 * Grouping is still by key. This only decides what the group's URL is
 * called, and arv_results_race_by_slug resolves it back.
 *
 * @param string $name
 * @return string
 */
function arv_results_race_slug( $name ) {
	$slug = strtolower( trim( (string) $name ) );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

	return trim( (string) $slug, '-' );
}

/**
 * Slug to the race key it names, built from the stored rows.
 *
 * One entry per race, its slug taken from the newest edition's name, which
 * is the same edition the archive headlines a race with, so a race that was
 * renamed is reachable at the name it goes by now.
 *
 * @return array<string, string> slug => race key
 */
function arv_results_race_slugs() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$map  = array();
	$seen = array();

	foreach ( arv_results_store_get() as $row ) {
		$key = arv_results_race_key( $row['name'] );

		// Rows arrive newest first, so the first name seen for a key is the
		// current one.
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;
		$slug         = arv_results_race_slug( $row['name'] );

		if ( '' !== $slug && ! isset( $map[ $slug ] ) ) {
			$map[ $slug ] = $key;
		}
	}

	return $map;
}

/**
 * The race key a URL segment names, or '' when it names nothing.
 *
 * @param string $slug
 * @return string
 */
function arv_results_race_by_slug( $slug ) {
	$slugs = arv_results_race_slugs();
	$slug  = arv_results_race_slug( $slug );

	return isset( $slugs[ $slug ] ) ? $slugs[ $slug ] : '';
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

/**
 * /results/<race> as a real URL.
 *
 * A page cannot have children it does not have, and the archive's races are
 * rows in an option rather than posts, so there is nothing for WordPress to
 * resolve /results/desert-solstice against on its own: it would 404 before
 * any element ran. This maps the segment onto the results page itself and
 * hands the race through as a query var for the element to read.
 *
 * Registered against the page's own slug rather than a fixed "results",
 * read from the page that actually carries the shortcode, so renaming that
 * page does not silently strand every per-race URL.
 */
function arv_results_add_rewrite() {
	add_rewrite_rule(
		'^results/([^/]+)/?$',
		'index.php?pagename=results&arv_race=$matches[1]',
		'top'
	);
}
add_action( 'init', 'arv_results_add_rewrite' );

/**
 * Let WordPress carry arv_race through to the query.
 *
 * @param array $vars
 * @return array
 */
function arv_results_query_vars( $vars ) {
	$vars[] = 'arv_race';

	return $vars;
}
add_filter( 'query_vars', 'arv_results_query_vars' );

/**
 * Flush the rules once, on the version that introduces them.
 *
 * Rewrite rules are cached in an option, so a rule added by an update is
 * inert until something flushes. Doing it on every load is the standard
 * mistake: flush_rewrite_rules() rebuilds and writes the whole set, which is
 * expensive enough that WordPress's own docs say never to call it on init.
 * This runs once per plugin version instead.
 */
function arv_results_maybe_flush_rewrite() {
	$flushed = get_option( 'arv_results_rewrite_version' );

	if ( ARV_ELEMENTS_VERSION === $flushed ) {
		return;
	}

	arv_results_add_rewrite();
	flush_rewrite_rules( false );
	update_option( 'arv_results_rewrite_version', ARV_ELEMENTS_VERSION, false );
}
add_action( 'init', 'arv_results_maybe_flush_rewrite', 20 );
