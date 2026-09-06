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
 * A row's own UltraRunning field, which is a URL or the word "none".
 *
 * "none" says this edition was checked and genuinely has no page of its
 * own, so the race-keyed fallback should not fill one in for it (see the
 * caller in includes/elements/results.php). It is not a URL, and running
 * it through esc_url_raw() the way the real URLs go turned it into
 * "http://none": a row that meant "no link" rendered a button pointing at
 * a hostname that does not exist. Kept whole here instead.
 *
 * The mangled spelling is accepted too, because it is what the one row
 * using this has held since the day it was written.
 *
 * @param mixed $value
 * @return string
 */
function arv_results_clean_ultrarunning( $value ) {
	$value = trim( (string) $value );

	if ( in_array( strtolower( $value ), array( 'none', 'http://none' ), true ) ) {
		return 'none';
	}

	return esc_url_raw( $value );
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
			'ultrarunning' => arv_results_clean_ultrarunning( isset( $row['ultrarunning'] ) ? $row['ultrarunning'] : '' ),
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

	// The slug map is derived from these rows, so it is stale the moment
	// they change.
	arv_results_flush_slug_cache();

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
 * Every spelling a race has ever been stored under gets an entry, not just
 * the newest one's, and that is the whole point. Grouping is by race key,
 * which deliberately collapses "Black Canyon", "Black Canyon Ultras" and
 * "Black Canyon Trail Runs" into one race, but the archive renders a panel
 * per year and each panel links a race by THAT year's own name. Registering
 * only the newest spelling meant the 2025 panel linked
 * /race-results/black-canyon-ultras/ while only /race-results/black-canyon/
 * resolved: 73 of 553 editions across 23 races pointed at a URL that
 * answered "No race by that name".
 *
 * First spelling wins a given slug, and rows arrive newest first, so where
 * two different races would somehow claim the same slug the current one
 * keeps it. The older spellings are aliases onto the same page, which is
 * also why they cost nothing in search: arv_results_race_url() always
 * builds from the newest name, so every alias canonicals to the one URL.
 *
 * @return array<string, string> slug => race key
 */
function &arv_results_race_slugs_cache() {
	static $map = null;

	return $map;
}

/**
 * Drop the memoized slug map.
 *
 * The map is derived from the store, so a write to the store invalidates it.
 * That is not only a test concern: the import route replaces the whole store
 * inside a single request, and anything rendering after it in that request
 * would otherwise resolve slugs against the set of races that existed before
 * the import. Same reason and same shape as arv_race_store_flush_cache().
 */
function arv_results_flush_slug_cache() {
	$map =& arv_results_race_slugs_cache();
	$map = null;
}

function arv_results_race_slugs() {
	$map =& arv_results_race_slugs_cache();

	if ( null !== $map ) {
		return $map;
	}

	$map = array();

	foreach ( arv_results_store_get() as $row ) {
		$key  = arv_results_race_key( $row['name'] );
		$slug = arv_results_race_slug( $row['name'] );

		if ( '' === $key || '' === $slug || isset( $map[ $slug ] ) ) {
			continue;
		}

		$map[ $slug ] = $key;
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

/**
 * What race, if any, this request is a page for.
 *
 * Resolved once and shared by the renderer and by every piece of SEO, the
 * same arrangement the live pages use, so a race page's title, description,
 * canonical and heading cannot disagree with each other about which race is
 * on screen.
 *
 * @return array|null name, slug, url and editions newest first.
 */
function arv_results_race_context() {
	// Deliberately not memoized. It is called about four times a request, by
	// the title, the canonical, the head tags and the renderer, and each one
	// is a slug lookup against a map that memoizes itself plus one pass over
	// the store. A cache here would buy almost nothing and would have to be
	// invalidated whenever the store changes, which is a real thing that
	// happens: the importer replaces it wholesale.
	$slug = function_exists( 'get_query_var' ) ? (string) get_query_var( 'arv_race' ) : '';

	if ( '' === $slug ) {
		return null;
	}

	$key = arv_results_race_by_slug( $slug );

	if ( '' === $key ) {
		return null;
	}

	$editions = array();

	foreach ( arv_results_store_get() as $row ) {
		if ( arv_results_race_key( $row['name'] ) === $key ) {
			$editions[] = $row;
		}
	}

	if ( empty( $editions ) ) {
		return null;
	}

	return array(
		'name'     => $editions[0]['name'],
		'slug'     => arv_results_race_slug( $editions[0]['name'] ),
		'url'      => function_exists( 'arv_results_race_url' ) ? arv_results_race_url( $editions[0]['name'] ) : '',
		'editions' => $editions,
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
 * The "{slug}/race/{id}" part of an UltraRunning results URL, or bare
 * "{slug}" when no specific edition is on file.
 *
 * UltraRunning mints a new numeric race id every year for the same race, so
 * a single "{slug}/race/{id}" entry can only ever be correct for whichever
 * one year that id belongs to. Applied as the fallback for every edition of
 * a race with no id of its own, which is most editions of most races, that
 * meant one specific year being asserted, confidently, on every year but
 * its own: Javelina's map entry pointed 2009 through 2023 at 2024's
 * results, and Westminster 2026 was showing 2025's. A bare slug fixes that
 * at the cost of a click: it resolves to the race's own results index,
 * every year it has ever run listed there for a reader to pick, which is
 * never the wrong year because it does not claim one.
 *
 * A specific "{slug}/race/{id}" is still accepted and still preferred where
 * it is known to be right; it is what an exact per-edition override (see
 * arv_results_ultrarunning_url()'s caller, which checks a row's own field
 * before ever reaching this map) should hold. This map itself, the shared
 * fallback with one entry per race, should generally hold the bare form.
 *
 * Validated rather than trusted either way: anything not recognisably one
 * of their two path shapes returns '' and is dropped, so a typo becomes a
 * missing link rather than a link to nowhere. A trailing "/results" and any
 * "#selected_year" anchor are stripped from either shape, because those are
 * ours to add back consistently.
 *
 * @param string $value Full URL or bare path, either shape.
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

	if ( preg_match( '~^(?:https?://(?:www\.)?ultrarunning\.com/calendar/event/)?([a-z0-9-]+)/?(?:#.*)?$~i', $value, $m ) ) {
		return strtolower( $m[1] );
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

	$path = $map[ $key ];

	// A specific edition ("{slug}/race/{id}") gets the results page a bare
	// slug cannot. A bare slug gets the race's own index instead of a
	// results page it has no id for: every year it has run, right there to
	// pick, rather than a guess at which one this row actually is.
	$suffix = ( false !== strpos( $path, '/race/' ) ) ? '/results' : '/race';

	return 'https://ultrarunning.com/calendar/event/' . $path . $suffix;
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
 * The base segment the per-race pages live under.
 *
 * NOT "results", and it cannot be. /results/ is a real directory on the web
 * server holding the legacy static archive: /results/2008JJResults100m.htm
 * is a 70KB file that has been served from disk since 2008, and
 * /results/virtual/ is a whole tree of them from the 2020 season. Those
 * files are still linked, by the archive rows in this very store and by
 * however many external pages over eighteen years.
 *
 * Apache resolves that directory before WordPress is ever reached, and
 * because a subdirectory's own rules are not inherited from the root
 * .htaccess, nothing under /results/ reaches index.php at all: a missing
 * path there returns Apache's own 404, not WordPress's. /results/virtual/
 * answers 403, which is a directory refusing to list itself. No rewrite
 * rule registered in PHP can win an argument the filesystem settles first,
 * so the pages moved instead of the archive.
 */
const ARV_RESULTS_RACE_BASE = 'race-results';

/**
 * /race-results/<race> as a real URL.
 *
 * A page cannot have children it does not have, and the archive's races are
 * rows in an option rather than posts, so there is nothing for WordPress to
 * resolve a race against on its own: it would 404 before any element ran.
 * This maps the segment onto the results page itself and hands the race
 * through as a query var for the element to read.
 */
function arv_results_add_rewrite() {
	add_rewrite_rule(
		'^' . ARV_RESULTS_RACE_BASE . '/([^/]+)/?$',
		'index.php?pagename=results&arv_race=$matches[1]',
		'top'
	);
}
add_action( 'init', 'arv_results_add_rewrite' );

/**
 * Keep WordPress from tidying a race URL away.
 *
 * The rule above resolves to pagename=results, and that page's own permalink
 * is /results/, so redirect_canonical sees a URL that does not match the post
 * it resolved and "corrects" it, dropping the race on the way. Worse, with no
 * rule matched at all it guesses: /race-results/black-bear-trail-races/ was
 * 301ing to an unrelated page that happened to share the last segment. Both
 * behaviours are right in general and wrong here, where the extra segment is
 * the whole point, so canonicalisation is declined for exactly these
 * requests and left alone everywhere else.
 *
 * @param string|false $redirect
 * @return string|false
 */
function arv_results_keep_race_url( $redirect ) {
	if ( function_exists( 'get_query_var' ) && '' !== (string) get_query_var( 'arv_race' ) ) {
		return false;
	}

	return $redirect;
}
add_filter( 'redirect_canonical', 'arv_results_keep_race_url' );

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

/* ------------------------------------------------------------------ *
 * A sitemap for the race pages, because no other one knows they exist.
 *
 * /race-results/<slug>/ is a virtual URL: a rewrite rule, not a post, so
 * every sitemap generator that lists actual content is blind to it. 143 of
 * them exist, linked from /results/ so Google can still crawl its way in,
 * but never declared anywhere, discovered only as fast as a crawler
 * chooses to follow a link rather than told about outright. The flat
 * sitemap.xml this site already submits has 501 URLs in it and not one of
 * these; core WordPress's own wp-sitemap.xml, built from real posts and
 * pages, cannot see them for the same reason.
 *
 * A dedicated file rather than trying to inject into either of those: this
 * plugin does not own whatever generates the flat one, and a second
 * sitemap is one more URL to submit in Search Console, not a migration.
 * ------------------------------------------------------------------ */

/**
 * One race, sized down to what the sitemap needs: its URL and the most
 * recent date anything about it changed.
 *
 * lastmod is the newest edition's own date where the race has run since,
 * which is real information a crawler can act on, and today's date on a
 * race that has not run this year, since nothing here would justify
 * claiming otherwise.
 *
 * @param string $slug
 * @param array  $editions Newest first, this race's rows from the store.
 * @return array
 */
function arv_results_sitemap_entry( $slug, $editions ) {
	$newest = isset( $editions[0]['iso'] ) ? (string) $editions[0]['iso'] : '';
	$stamp  = $newest && preg_match( '/^\d{4}-\d{2}-\d{2}/', $newest )
		? substr( $newest, 0, 10 ) . 'T00:00:00+00:00'
		: gmdate( 'c' );

	return array(
		'url'     => arv_results_race_url( $editions[0]['name'] ),
		'lastmod' => $stamp,
	);
}

/**
 * Every race page, present tense: only races with at least one edition in
 * the store today, in the same newest-first order the archive itself uses.
 *
 * @return array
 */
function arv_results_sitemap_entries() {
	$by_key = array();

	foreach ( arv_results_store_get() as $row ) {
		$key = arv_results_race_key( $row['name'] );

		if ( '' === $key ) {
			continue;
		}

		$by_key[ $key ][] = $row;
	}

	$entries = array();

	foreach ( arv_results_race_slugs() as $slug => $key ) {
		if ( empty( $by_key[ $key ] ) ) {
			continue;
		}

		$entries[] = arv_results_sitemap_entry( $slug, $by_key[ $key ] );
	}

	return $entries;
}

/**
 * GET /race-results-sitemap.xml
 *
 * A physical rewrite rather than a query var on the results page, so this
 * is servable and cacheable as a plain file URL, the shape Search Console
 * and every crawler expects a sitemap to have.
 */
function arv_results_sitemap_add_rewrite() {
	add_rewrite_rule( '^race-results-sitemap\.xml$', 'index.php?arv_results_sitemap=1', 'top' );
}
add_action( 'init', 'arv_results_sitemap_add_rewrite' );

function arv_results_sitemap_query_vars( $vars ) {
	$vars[] = 'arv_results_sitemap';

	return $vars;
}
add_filter( 'query_vars', 'arv_results_sitemap_query_vars' );

/**
 * The sitemap XML itself, built and handed back rather than echoed.
 *
 * Kept separate from the route handler below so a test can call this and
 * read a string, rather than the one below, which ends the request: PHP's
 * exit cannot be caught, so a function that calls it is untestable by
 * construction and has to be a thin wrapper around one that is not.
 *
 * @return string
 */
function arv_results_sitemap_xml() {
	$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

	foreach ( arv_results_sitemap_entries() as $entry ) {
		$xml .= "\t<url>\n";
		$xml .= "\t\t<loc>" . esc_url( $entry['url'] ) . "</loc>\n";
		$xml .= "\t\t<lastmod>" . esc_html( $entry['lastmod'] ) . "</lastmod>\n";
		$xml .= "\t</url>\n";
	}

	return $xml . '</urlset>';
}

/**
 * Serve the sitemap and stop, the moment the rewrite matches.
 *
 * On template_redirect rather than a template file: the rewrite resolves
 * to no real post, so there is no template WordPress would otherwise
 * choose, and this needs to run before it tries and serves a 404.
 */
function arv_results_sitemap_render() {
	if ( ! function_exists( 'get_query_var' ) || '1' !== (string) get_query_var( 'arv_results_sitemap' ) ) {
		return;
	}

	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo arv_results_sitemap_xml();
	exit;
}
add_action( 'template_redirect', 'arv_results_sitemap_render' );
