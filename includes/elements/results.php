<?php
/**
 * Aravaipa Results.
 *
 * One list of every race that has run, newest first, with the places you can
 * read what happened: Aravaipa's own live timing, UltraSignup, and
 * UltraRunning where the race is listed there.
 *
 * Replaces a hand-built page per year. Those pages are the source this reads
 * from, so nothing is invented here, but a visitor asking "how did Black
 * Canyon go" should not have to know which year page to open first, and a
 * race that ran on Saturday should be at the top on Sunday without anyone
 * editing a page.
 *
 * That last part is why this reads two stores rather than one. The results
 * store is built by a scraper and lags the site by however long it is
 * between runs. The race store already knows every race and its live URL, so
 * a race that has just run, or is running right now, is merged in from there
 * and appears immediately, and is then quietly superseded by the real row
 * once the scraper catches up.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// The stopgap this grace exists to cover has stretched to cover the whole
// year, not the "few days" arv_results_live_rows was written to describe.
//
// A page per year, /results-2026/, is now rendered by this element rather
// than a hand-built Cornerstone page, and the historical scraper reads that
// same page to build next year's stored rows. It now reads its own output,
// finds a handful of races instead of the season, and cannot write the rest
// of 2026 until it is pointed at an independent source. Left at 10 days,
// every race this year would drop off the results page 10 days after it
// finished and stay gone until that scraper is rebuilt: exactly what
// prompted this comment. 400 days holds a race for the rest of any year it
// could realistically run in. Once the scraper reads a source that is not
// downstream of this element, this can go back to a number that means "a
// few days" again.
if ( ! defined( 'ARV_RESULTS_LIVE_MERGE_GRACE' ) ) {
	define( 'ARV_RESULTS_LIVE_MERGE_GRACE', 400 );
}


cs_register_element(
	'aravaipa-results',
	array(
		'title'   => __( 'Aravaipa Results', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				// Both blank by default. The page this sits on already has a
				// "Results" hero directly above it, so an eyebrow and an
				// <h2> saying it again is the same word three times before
				// any content. Still settable for a page that has no hero.
				'eyebrow'  => cs_value( '', 'markup' ),
				'heading'  => cs_value( '', 'markup' ),
				'intro'    => cs_value( '', 'markup' ),
				// Blank shows everything. A year narrows it, for anyone who
				// still wants the old per-year page.
				'year'     => cs_value( '', 'markup' ),
				'limit'    => cs_value( '0', 'markup' ),
				'upcoming' => cs_value( 'true', 'markup' ),
				// "race" gathers every edition of a race under its own name,
				// which is what someone looking for a result actually wants:
				// they know the race, not the date. "date" is the plain
				// reverse-chronological list, which answers the other
				// question, what happened recently.
				'layout'   => cs_value( 'race', 'markup' ),
				'search'   => cs_value( 'true', 'markup' ),
				// False here on purpose: dropped onto a page with no year of
				// its own, this element has always meant "every race,
				// merged across every year it ran, in one flat list", and
				// that contract is real and tested. The year picker is what
				// the plain [arv_results] shortcode defaults to instead, for
				// the one page that wants it.
				'year_tabs' => cs_value( 'false', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_results_builder',
		'render'  => 'arv_results_render',
	)
);

/**
 * The same element as a plain shortcode.
 *
 * Every other element in this plugin has one of these beside it: the Watch
 * archive, the Films shelf, the Photos grid. Results did not, and that made
 * it the one rail that could only be placed inside Cornerstone. Cornerstone
 * renders a page from a tree it keeps in post meta rather than from the
 * shortcode text in post_content, so a page assembled anywhere other than
 * its own builder gets an empty <div id="cs-content"> and nothing else,
 * which is exactly what the master /results/ page did when it was written
 * over the REST API.
 *
 * This is the way in that does not need the builder. Defaults match the
 * element's own, so a bare [arv_results] is the whole archive: every year,
 * grouped by race, with the search box.
 *
 * @param array $atts
 * @return string
 */
function arv_results_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'eyebrow'  => '',
			'heading'  => '',
			'intro'    => '',
			// Blank shows every year. The element also reads a year off a
			// results-YYYY page slug, and that still applies here: this only
			// sets it explicitly.
			'year'     => '',
			'limit'    => '0',
			'upcoming' => 'true',
			'layout'   => 'race',
			'search'   => 'true',
			// True here, false on the Cornerstone element: this shortcode
			// exists specifically for the one page with no year page of its
			// own to fall back to, /results/, and that page is exactly
			// where "every race in one flat list" stopped being the right
			// answer. year="2020" (or a results-YYYY slug) still means what
			// it always has and turns this off regardless.
			'year_tabs' => 'true',
		),
		$atts,
		'arv_results'
	);

	return arv_results_render( $atts );
}
add_shortcode( 'arv_results', 'arv_results_shortcode' );

/**
 * Builder controls.
 *
 * @return array
 */
function arv_results_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'intro',
					'type'  => 'text',
					'label' => __( 'Intro (optional)', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'year',
					'type'        => 'text',
					'label'       => __( 'Year (optional)', 'aravaipa-elements' ),
					'description' => __( 'Leave blank to follow the page: a page called results-2026 shows that year, anything else shows every year. Set it to override.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'limit',
					'type'        => 'text',
					'label'       => __( 'Limit', 'aravaipa-elements' ),
					'description' => __( '0 for no limit.', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'layout',
					'type'    => 'select',
					'label'   => __( 'Layout', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => 'race',
								'label' => __( 'By race, every edition together', 'aravaipa-elements' ),
							),
							array(
								'value' => 'date',
								'label' => __( 'By date, newest first', 'aravaipa-elements' ),
							),
						),
					),
				),
				array(
					'key'         => 'search',
					'type'        => 'text',
					'label'       => __( 'Search box', 'aravaipa-elements' ),
					'description' => __( 'true or false. Filters as you type. Only shown in the by-race layout.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'upcoming',
					'type'        => 'text',
					'label'       => __( 'Include races happening now', 'aravaipa-elements' ),
					'description' => __( 'true or false. Puts a race that is running today, or ran in the last few days, at the top with its live results link before the scraper has caught up.', 'aravaipa-elements' ),
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * Races currently worth showing from the race store.
 *
 * A race is included once it has started, and stays for a few days after it
 * finishes, which is the window where its results exist but no scrape has
 * run yet. Anything older than that is the results store's job, and anything
 * still in the future has nothing to read.
 *
 * @param string $today Y-m-d.
 * @param int    $grace Days after the finish to keep showing it.
 * @return array Rows in the results shape.
 */
function arv_results_live_rows( $today, $grace = 10 ) {
	if ( ! function_exists( 'arv_race_store_get' ) ) {
		return array();
	}

	$rows   = array();
	$cutoff = gmdate( 'Y-m-d', strtotime( $today . ' -' . (int) $grace . ' days' ) );

	foreach ( arv_race_store_get() as $race ) {
		$last = ( '' !== $race['end'] ) ? $race['end'] : $race['iso'];

		// Not started yet, or long enough ago that the scraper has had its
		// chance.
		if ( $race['iso'] > $today || $last < $cutoff ) {
			continue;
		}

		if ( '' === trim( (string) $race['live'] ) ) {
			continue;
		}

		// "Happening now" only while the race is actually running. This was
		// simply true for every row in the window, so a race stayed flagged
		// as happening now for the whole ten days the scraper has to pick it
		// up: Black Bear and Rock Hawk both still said it the morning after
		// they finished, on the same page whose race week block, three
		// inches above, correctly said COMPLETED. That block was already
		// reading the board's real clock; this was reading a date.
		$board    = function_exists( 'arv_live_store_find' ) ? arv_live_store_find( $race['live'] ) : null;
		$start_ts = ( null !== $board && '' !== $board['start'] ) ? strtotime( $board['start'] ) : 0;

		// No board entry means no real clock to read, so fall back to the
		// date: a race whose day it is, is happening. That is what this did
		// for everything before, and for a race the board has never carried
		// it remains the only answer available.
		$current = $start_ts
			? ( 'live' === arv_races_live_state( $race, $board, $start_ts ) )
			: ( $race['iso'] === $today || ( '' !== $race['end'] && $race['iso'] <= $today && $today <= $race['end'] ) );

		// Derived from the calendar's own registration link rather than left
		// blank until the scraper's next run. UltraSignup's results page for
		// a race is the same id as its registration page under a different
		// filename, so a race that has never been scraped can still carry an
		// UltraSignup link the moment it finishes, not up to ten days later.
		$ultrasignup = function_exists( 'arv_upcoming_races_results_url' )
			? arv_upcoming_races_results_url( $race['register'] )
			: '';

		$rows[] = array(
			'name'         => $race['name'],
			'iso'          => $race['iso'],
			'display'      => $race['display'],
			'live'         => $race['live'],
			'ultrasignup'  => $ultrasignup,
			'ultrarunning' => '',
			'current'      => $current,
		);
	}

	return $rows;
}

/**
 * The year a per-year results page is about, read off its own slug.
 *
 * The site has a page per year, /results-2026/ back to /results-2008-2010/,
 * and a Results menu built on them. The element's own Year setting still
 * wins where it is set, but it lives in Cornerstone's data rather than in
 * anything reachable from here, so a page whose whole identity is its year
 * would otherwise have to be told that year a second time by hand, in a
 * builder, once a year, forever.
 *
 * Deliberately strict: results-2026 and nothing else. A page called
 * results-archive keeps every year, which is what a page not named after
 * one should do.
 *
 * @return string Four digits, or '' where the page is not a year page.
 */
function arv_results_year_from_page() {
	if ( ! function_exists( 'get_queried_object_id' ) ) {
		return '';
	}

	$id = (int) get_queried_object_id();

	if ( $id < 1 || ! function_exists( 'get_post_field' ) ) {
		return '';
	}

	$slug = (string) get_post_field( 'post_name', $id );

	return preg_match( '/^results-(\d{4})$/', $slug, $m ) ? $m[1] : '';
}

/**
 * Narrow the archive to one year's races.
 *
 * Keeps a race that ran in the year and drops one that did not, rather than
 * keeping only rows dated in it. The difference is the earlier editions
 * folded under each race: filtering row by row threw away every one of them,
 * so a year page lost the history that is half of what the archive is for.
 * A race's own past is not another year's event.
 *
 * Editions newer than the year go, so that /results-2026/ still headlines
 * each race with its 2026 running once 2027 exists.
 *
 * @param array  $rows Newest first.
 * @param string $year Four digits, or '' to keep everything.
 * @return array
 */
function arv_results_filter_year( $rows, $year ) {
	$year = trim( (string) $year );

	if ( ! preg_match( '/^\d{4}$/', $year ) ) {
		return $rows;
	}

	$ran = array();

	foreach ( $rows as $row ) {
		if ( substr( $row['iso'], 0, 4 ) === $year ) {
			$ran[ arv_results_race_key( $row['name'] ) ] = true;
		}
	}

	$kept = array();

	foreach ( $rows as $row ) {
		$key = arv_results_race_key( $row['name'] );

		if ( isset( $ran[ $key ] ) && substr( $row['iso'], 0, 4 ) <= $year ) {
			$kept[] = $row;
		}
	}

	return $kept;
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_results_render( $data ) {
	if ( ! function_exists( 'arv_results_store_get' ) ) {
		return '';
	}

	$today = function_exists( 'arv_upcoming_races_today' ) ? arv_upcoming_races_today() : gmdate( 'Y-m-d' );

	$rows = arv_results_store_get();

	$include_current = isset( $data['upcoming'] ) ? $data['upcoming'] : true;
	$include_current = ! ( 'false' === $include_current || false === $include_current || '0' === $include_current );

	if ( $include_current ) {
		// Merged in front, then de-duplicated by the same key the store uses,
		// so the scraped row wins the moment it exists: it carries the
		// UltraSignup and UltraRunning links this one cannot know yet.
		$have = array();
		foreach ( $rows as $row ) {
			$have[ strtolower( $row['name'] ) . '|' . $row['iso'] ] = true;
		}

		$extra = array();
		foreach ( arv_results_live_rows( $today, ARV_RESULTS_LIVE_MERGE_GRACE ) as $row ) {
			if ( isset( $have[ strtolower( $row['name'] ) . '|' . $row['iso'] ] ) ) {
				continue;
			}
			$extra[] = $row;
		}

		if ( ! empty( $extra ) ) {
			$rows = array_merge( $extra, $rows );
			usort(
				$rows,
				function ( $a, $b ) {
					if ( $a['iso'] === $b['iso'] ) {
						return strcasecmp( $a['name'], $b['name'] );
					}
					return ( $a['iso'] < $b['iso'] ) ? 1 : -1;
				}
			);
		}
	}

	// Fill in the UltraRunning link from the map for any row that does not
	// already carry one. The scraper only ever finds these where a human had
	// already put one on a results page, so the archive is full of races
	// that have an UltraRunning page nobody ever linked. Their id identifies
	// the race rather than the edition, so one entry lights up every year of
	// that race at once. A row that already has a link keeps it: the scraped
	// one came off the page itself and is the more specific answer.
	if ( function_exists( 'arv_results_ultrarunning_url' ) ) {
		foreach ( $rows as $i => $row ) {
			if ( '' === trim( (string) $row['ultrarunning'] ) ) {
				$rows[ $i ]['ultrarunning'] = arv_results_ultrarunning_url( $row['name'] );
			}
		}
	}

	$year = isset( $data['year'] ) ? trim( (string) $data['year'] ) : '';

	if ( '' === $year ) {
		$year = arv_results_year_from_page();
	}

	$rows = arv_results_filter_year( $rows, $year );

	$limit = isset( $data['limit'] ) ? (int) $data['limit'] : 0;
	if ( $limit > 0 ) {
		$rows = array_slice( $rows, 0, $limit );
	}

	$base = arv_wrapper_class( $data, 'arv-results' );

	$out  = '<div class="' . $base . '">';
	$out .= '<div class="arv-results__inner">';

	$eyebrow = isset( $data['eyebrow'] ) ? trim( (string) $data['eyebrow'] ) : '';
	if ( '' !== $eyebrow ) {
		$out .= '<p class="arv-results__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}

	$heading = isset( $data['heading'] ) ? trim( (string) $data['heading'] ) : '';
	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-results__heading">' . esc_html( $heading ) . '</h2>';
	}

	$intro = isset( $data['intro'] ) ? trim( (string) $data['intro'] ) : '';
	if ( '' !== $intro ) {
		$out .= '<p class="arv-results__intro">' . esc_html( $intro ) . '</p>';
	}

	if ( empty( $rows ) ) {
		// Says the true thing rather than rendering an empty table. An
		// unpopulated store and a year with no races look identical in the
		// markup otherwise.
		$out .= '<p class="arv-results__empty">' . esc_html( __( 'No results to show yet.', 'aravaipa-elements' ) ) . '</p>';
		$out .= '</div></div>';
		return $out;
	}

	$layout = isset( $data['layout'] ) && 'date' === $data['layout'] ? 'date' : 'race';

	if ( 'date' === $layout ) {
		$out .= arv_results_table( $rows );
		$out .= '</div></div>';
		return $out;
	}

	$show_search = isset( $data['search'] ) ? $data['search'] : true;
	$show_search = ! ( 'false' === $show_search || false === $show_search || '0' === $show_search );

	if ( $include_current ) {
		$out .= arv_results_race_week( $today );
	}

	// Opt in, not the default for a blank year: the direct-call contract of
	// "no year means every race, merged across every year it ran, in one
	// flat list" is real and tested (arv-store-test.php's Black Canyon
	// Ultras/Black Canyon/Black Canyon Trail Runs case), and every existing
	// use of this element relies on it. The year picker is a second, opt-in
	// shape for the one page that wants it, the plain [arv_results]
	// shortcode's own default rather than something baked into "year is
	// blank": a page already scoped to one year, by its slug or by the year
	// attribute, has nothing left to pick between regardless of this flag.
	// A race page, when the URL named one. Checked before the year picker
	// because /results/desert-solstice is not a year view narrowed down, it
	// is a different page that happens to live under the same shortcode.
	$race_slug = function_exists( 'get_query_var' ) ? (string) get_query_var( 'arv_race' ) : '';

	if ( '' !== $race_slug ) {
		$race_key = arv_results_race_by_slug( $race_slug );

		if ( '' === $race_key ) {
			$out .= '<p class="arv-results__empty">'
				. esc_html( __( 'No race by that name.', 'aravaipa-elements' ) ) . '</p>';

			return $out . '</div></div>';
		}

		$editions = array();

		foreach ( $rows as $row ) {
			if ( arv_results_race_key( $row['name'] ) === $race_key ) {
				$editions[] = $row;
			}
		}

		if ( empty( $editions ) ) {
			$out .= '<p class="arv-results__empty">'
				. esc_html( __( 'No results to show yet.', 'aravaipa-elements' ) ) . '</p>';

			return $out . '</div></div>';
		}

		$out .= arv_results_race_page( $editions, $editions[0]['name'] );

		return $out . '</div></div>';
	}

	$year_tabs = isset( $data['year_tabs'] ) ? $data['year_tabs'] : false;
	$year_tabs = ! ( 'false' === $year_tabs || false === $year_tabs || '0' === $year_tabs );

	if ( '' === $year && $year_tabs ) {
		$out .= arv_results_by_race_yearly( $rows, $show_search );
	} else {
		$out .= arv_results_by_race( $rows, $show_search );
	}
	$out .= '</div></div>';

	return $out;
}

/**
 * Collapse a race's name to something stable across its editions.
 *
 * The site is not consistent about suffixes from one year to the next:
 * "Rock Hawk" and "Rock Hawk Trail Races", "Black Canyon" and "Black Canyon
 * Ultras", "Mountain To Fountain" and "Mountain to Fountain" are each one
 * race written two ways. Grouping on the raw name splits fourteen of the
 * seventy-five races in the archive into halves.
 *
 * Checked against the real data before trusting it: no two genuinely
 * different races collapse together, verified by looking for a group that
 * ends up with the same year in it twice, which is what an over-merge would
 * look like since a race runs once a year.
 *
 * Falls back to the lightly-cleaned name when stripping leaves too little to
 * be a name. "Race the Cog" is the one that needs it: strip "race" and "the"
 * and only "cog" survives.
 *
 * @param string $name
 * @return string
 */
function arv_results_race_key( $name ) {
	$light = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', ' ', $name ) ) );
	$light = trim( preg_replace( '/\s+/', ' ', $light ) );

	$stripped = preg_replace( '/\b(trail|trails|run|runs|race|races|ultra|ultras|endurance|the|presented by.*)\b/i', ' ', $light );

	// A distance in the name, which the board adds and drops between years:
	// "Cocodona 250" is now "Cocodona", "North Fork 50" is now "North Fork".
	// Nothing here can wrongly merge on it, because two races differing only
	// by a number are one race at two distances, and this store holds a
	// single row per race with its distances listed on that row.
	$stripped = preg_replace( '/\b\d+\s*(k|km|m|mi|mile|miler|hour|hr)?\b/i', ' ', (string) $stripped );
	$stripped = trim( preg_replace( '/\s+/', ' ', (string) $stripped ) );

	// And a plural, for the same reason: "Silverton Alpine Marathons" is now
	// "Silverton Alpine Marathon". Past four letters only, so the list above
	// keeps handling "runs" and "races" and short names are left alone.
	$stripped = implode(
		' ',
		array_map(
			function ( $word ) {
				return ( strlen( $word ) > 4 && 's' === substr( $word, -1 ) ) ? substr( $word, 0, -1 ) : $word;
			},
			array_filter( explode( ' ', $stripped ) )
		)
	);

	return ( strlen( $stripped ) < 4 ) ? $light : $stripped;
}

/**
 * The races this weekend, above everything else.
 *
 * "Race week" is not a new idea here: arv_upcoming_races_action() already
 * has a phase for it, entered once entries close or the race is within five
 * days, whichever comes first, and held until the race finishes. That is the
 * same phase the events page uses to swap a Register button for Live
 * Results, so this block and that page agree by construction rather than by
 * both being told the same rule twice.
 *
 * Before the first one starts this counts down to it. Once it has, the
 * countdown is replaced by a live marker. Both states are rendered here and
 * one is hidden, so the swap at midnight needs no request and no reload,
 * and a visitor with no JavaScript still gets the correct one for whenever
 * the page was built.
 *
 * @param string $today Y-m-d in site time.
 * @return string
 */
function arv_results_race_week( $today, $grace = 3 ) {
	if ( ! function_exists( 'arv_race_store_get' ) ) {
		return '';
	}

	$races  = array();
	$cutoff = gmdate( 'Y-m-d', strtotime( $today . ' -' . (int) $grace . ' days' ) );

	// Sunday of the week we are in, so the coming weekend joins the block on
	// Monday morning rather than waiting for entries to close. The old rule
	// only let a race in once arv_upcoming_races_action() called it live,
	// which for a race still selling entries is the Wednesday at the
	// earliest and race day at the latest: the weekend everyone is actually
	// looking for was the one thing missing from "race week".
	$today_ts = strtotime( $today . ' 00:00:00 UTC' );
	$week_end = gmdate( 'Y-m-d', $today_ts + ( 7 - (int) gmdate( 'N', $today_ts ) ) * DAY_IN_SECONDS );

	foreach ( arv_race_store_get() as $race ) {
		$action = arv_upcoming_races_action( $race, $today );
		$last   = ( '' !== $race['end'] ) ? $race['end'] : $race['iso'];

		// Race week itself, plus a few days the other side of it. Without
		// the tail a race drops out of this block the moment it finishes,
		// which is the exact hour people come looking for it: it would go
		// from "live" straight to gone, and the finished state would never
		// be seen by anyone.
		$recent = ( 'results' === $action['phase'] && $last >= $cutoff );

		// Still to come, and lands before the week is out. This is what puts
		// next weekend behind last weekend on a Monday, each with its own
		// countdown.
		$ahead = ( $today <= $last && $race['iso'] <= $week_end );

		if ( ! ( 'live' === $action['phase'] || $recent || $ahead ) || '' === $action['url'] ) {
			continue;
		}

		if ( $today > $last ) {
			$state = 'done';
		} elseif ( $today >= $race['iso'] ) {
			$state = 'live';
		} else {
			$state = 'soon';
		}

		$board = function_exists( 'arv_live_store_find' ) ? arv_live_store_find( $race['live'] ) : null;

		// The board knows the real clock: when each distance actually goes
		// off and when the event closes. Where it has an answer it wins,
		// because the store only has the date and everything derived from
		// that alone is a day out from a six in the morning start.
		if ( null !== $board && '' !== $board['start'] ) {
			$now = arv_results_now();
			$start_ts  = strtotime( $board['start'] );
			// An override we hold beats the board's own cutoff: see
			// arv_race_cutoff_for(). The board has been wrong about this.
			$cutoff_ts = function_exists( 'arv_race_cutoff_for' )
				? arv_race_cutoff_for( $race['name'], $board )
				: ( ( '' !== $board['cutoff'] ) ? strtotime( $board['cutoff'] ) : 0 );

			if ( $cutoff_ts && $now >= $cutoff_ts ) {
				$state = 'done';
			} elseif ( $now >= $start_ts ) {
				$state = 'live';
			} else {
				$state = 'soon';
			}
		}

		$races[] = array(
			'name'      => $race['name'],
			'iso'       => $race['iso'],
			'display'   => $race['display'],
			'image'     => $race['image'],
			'page'      => $race['page'],
			'location'  => $race['location'],
			'distances' => $race['distances'],
			'url'       => $action['url'],
			'live'      => $race['live'],
			'board'     => $board,
			'social'    => arv_results_race_social( $race ),
			'state'     => $state,
			// The phase's own word for its button. Hardcoding "Live
			// Results" was safe while nothing unstarted could get in here;
			// now that the coming weekend does, a race still selling
			// entries would have offered a live board that does not exist
			// yet, on a link that goes to UltraSignup's entry form.
			'label'     => $action['label'],
		);
	}

	if ( empty( $races ) ) {
		return '';
	}

	usort(
		$races,
		function ( $a, $b ) {
			if ( $a['iso'] === $b['iso'] ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
			return ( $a['iso'] < $b['iso'] ) ? -1 : 1;
		}
	);

	$out  = '<section class="arv-results__week" aria-label="' . esc_attr( __( 'Racing this week', 'aravaipa-elements' ) ) . '">';
	$out .= '<p class="arv-results__week-eyebrow">' . esc_html( __( 'Race week', 'aravaipa-elements' ) ) . '</p>';
	$out .= '<ul class="arv-results__week-list">';

	foreach ( $races as $race ) {
		$stamp   = strtotime( $race['iso'] . ' 00:00:00 UTC' );
		$display = '' !== $race['display'] ? $race['display'] : gmdate( 'F j', $stamp );
		// The year, always. This block spans race week, but a finished race
		// sits here for days afterwards and a bare "August 29" beside a
		// "Completed" tag is the one place on this page where which year is
		// a real question.
		$display .= ', ' . gmdate( 'Y', $stamp );

		$out .= '<li class="arv-results__week-race arv-results__week-race--' . esc_attr( $race['state'] ) . '">';

		$page = trim( (string) $race['page'] );

		if ( '' !== trim( (string) $race['image'] ) ) {
			// alt is empty on purpose: the name is right beside it, so a
			// screen reader announcing the logo too is repetition. That is
			// also why the logo link is aria-hidden and not focusable: it
			// goes exactly where the name beside it goes, and a keyboard
			// should not have to pass through the same destination twice.
			$logo = '<img class="arv-results__week-logo" src="' . esc_url( $race['image'] ) . '" alt="" loading="lazy" decoding="async" />';

			$out .= ( '' !== $page )
				? '<a class="arv-results__week-logo-link" href="' . esc_url( $page ) . '" tabindex="-1" aria-hidden="true">' . $logo . '</a>'
				: $logo;
		}

		$out .= '<div class="arv-results__week-body">';

		// Name and date share a line, and wrap to two when there is not
		// room. On a desktop that turns three stacked lines into two; on a
		// phone it falls back to the stack on its own, without a breakpoint
		// having to guess where the name stops fitting.
		$out .= '<span class="arv-results__week-head">';
		$out .= '<span class="arv-results__week-name">';
		$out .= ( '' !== $page )
			? '<a class="arv-results__week-link" href="' . esc_url( $page ) . '">' . esc_html( $race['name'] ) . '</a>'
			: esc_html( $race['name'] );
		$out .= '</span>';
		$out .= '<time class="arv-results__week-date" datetime="' . esc_attr( $race['iso'] ) . '">' . esc_html( $display ) . '</time>';

		// State only, not the town. On a bar whose job is "what is on right
		// now", "NH" and "CO" is the part that tells two races apart at a
		// glance; the full address is on the race's own page, one click
		// away through the name.
		$state_code = arv_results_state_code( $race['location'] );
		if ( '' !== $state_code ) {
			$out .= '<span class="arv-results__week-place">' . esc_html( $state_code ) . '</span>';
		}

		$out .= arv_results_week_live_badge( $race );
		$out .= '</span>';

		$distances = arv_split_distances( $race['distances'] );
		if ( ! empty( $distances ) ) {
			$out .= '<span class="arv-results__week-distances">';
			foreach ( $distances as $distance ) {
				$label = arv_results_distance_label( $distance );
				$deep  = arv_results_distance_url( $race, $distance );

				$out .= ( '' !== $deep )
					? '<a class="arv-results__week-pill arv-results__week-pill--link" href="' . esc_url( $deep ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>'
					: '<span class="arv-results__week-pill">' . esc_html( $label ) . '</span>';
			}
			$out .= '</span>';
		}

		$out .= '</div>';

		$out .= arv_results_week_status( $race );

		$out .= '<a class="arv-results__link arv-results__link--live" href="' . esc_url( $race['url'] ) . '"'
			. arv_races_link_target( $race['url'] ) . '>'
			. esc_html( $race['label'] ) . '</a>';

		if ( '' !== $race['social']['url'] ) {
			$out .= '<a class="arv-results__week-social" href="' . esc_url( $race['social']['url'] ) . '" target="_blank" rel="noopener">'
				. '<span class="arv-results__sr">'
				. esc_html( sprintf( /* translators: %s is an Instagram account name. */ __( '%s on Instagram', 'aravaipa-elements' ), $race['social']['label'] ) )
				. '</span>'
				. '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">'
				. '<rect x="2.5" y="2.5" width="19" height="19" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/>'
				. '<circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.8"/>'
				. '<circle cx="17.4" cy="6.6" r="1.2" fill="currentColor"/>'
				. '</svg></a>';
		}

		$out .= '</li>';
	}

	$out .= '</ul>';
	$out .= '</section>';

	return $out;
}



/**
 * The timing board's own link for one distance of one race.
 *
 * The board gives every distance an id, and its front end filters on
 * ?raceId=. So a "23K" chip can go straight to the 23K results rather than
 * dropping someone on the event page to find it themselves.
 *
 * Matching is by name, normalised, because the two sources write distances
 * differently: the row says "50KM" where the board says "50K", and the row
 * says "1 Mile" where the board says "1 Mile Fun Run". Exact match first,
 * then one side starting with the other, and nothing at all rather than a
 * guess: a chip pointing at the wrong distance's results is worse than a
 * chip that is not a link.
 *
 * @param array  $race
 * @param string $distance
 * @return string Empty when there is no confident match.
 */
function arv_results_distance_url( $race, $distance ) {
	if ( empty( $race['board'] ) || empty( $race['board']['races'] ) || '' === trim( (string) $race['live'] ) ) {
		return '';
	}

	$want = arv_results_distance_key( $distance );

	if ( '' === $want ) {
		return '';
	}

	$exact = '';
	$loose = '';

	foreach ( $race['board']['races'] as $entry ) {
		$have = arv_results_distance_key( $entry['name'] );

		if ( '' === $have ) {
			continue;
		}

		if ( $have === $want ) {
			$exact = $entry['id'];
			break;
		}

		// "1 mile" against the board's "1 mile fun run". Only in that
		// direction and only once: two loose hits mean the name is
		// ambiguous and neither should be trusted.
		if ( 0 === strpos( $have, $want ) || 0 === strpos( $want, $have ) ) {
			$loose = ( '' === $loose ) ? $entry['id'] : 'ambiguous';
		}
	}

	$id = ( '' !== $exact ) ? $exact : $loose;

	if ( '' === $id || 'ambiguous' === $id ) {
		return '';
	}

	// The row's live URL already carries the event; this adds the filter.
	$base = preg_replace( '/\?.*$/', '', trim( (string) $race['live'] ) );

	return $base . '?raceId=' . (int) $id;
}

/**
 * A distance reduced to something two sources can be compared on.
 *
 * @param string $distance
 * @return string
 */
function arv_results_distance_key( $distance ) {
	$key = strtolower( arv_results_distance_label( $distance ) );
	$key = preg_replace( '/[^a-z0-9]+/', ' ', $key );

	return trim( (string) $key );
}

/**
 * The two-letter state out of a "Town, ST" location.
 *
 * @param string $location
 * @return string
 */
function arv_results_state_code( $location ) {
	$location = trim( (string) $location );

	if ( '' === $location ) {
		return '';
	}

	$parts = array_map( 'trim', explode( ',', $location ) );
	$last  = end( $parts );

	return preg_match( '/^[A-Z]{2}$/', (string) $last ) ? (string) $last : '';
}

/**
 * Which of Aravaipa's accounts actually covers this race.
 *
 * A runner at Black Bear wants White Mountain Endurance, not the national
 * account: that is where the course photos and the start-line video go.
 * Rock Hawk wants Aravaipa Colorado for the same reason.
 *
 * Keyed off the region the store already works out at import time, so this
 * is not a second mapping of races to places that can disagree with the
 * first one.
 *
 * Every handle here was read off that region's own page on
 * aravaiparunning.com rather than guessed. Regions with no account of their
 * own fall through to the national one, which is the honest answer: the
 * California page links an embedded post from a partner club, and sending
 * people there as though it were ours would be worse than sending them to
 * the main account.
 *
 * @param array $race
 * @return array {url, label}
 */
function arv_results_race_social( $race ) {
	$accounts = array(
		'white-mountain-endurance' => array( 'whitemountainendurance', 'White Mountain Endurance' ),
		'great-lakes-endurance'    => array( 'greatlakesendurance', 'Great Lakes Endurance' ),
		'ultra-adventures'         => array( 'ultraadventures', 'Ultra Adventures' ),
		'bad-beard'                => array( 'badbeardevents', 'Bad Beard Events' ),
		'colorado'                 => array( 'aravaipacolorado', 'Aravaipa Colorado' ),
	);

	$region = function_exists( 'arv_race_store_region_for' ) ? arv_race_store_region_for( $race ) : '';

	$account = isset( $accounts[ $region ] )
		? $accounts[ $region ]
		: array( 'aravaiparunning', 'Aravaipa Running' );

	/**
	 * Filters the Instagram account shown beside a race.
	 *
	 * @param array  $account array( handle, label )
	 * @param string $region
	 * @param array  $race
	 */
	$account = apply_filters( 'arv_results_race_social', $account, $region, $race );

	return array(
		'url'   => 'https://www.instagram.com/' . $account[0] . '/',
		'label' => $account[1],
	);
}


/**
 * The three states one race passes through across its own weekend.
 *
 * All three are rendered and two are hidden, so the transitions need no
 * request: a page left open through Friday night becomes "live" on its own
 * at midnight. PHP decides which one starts visible, which means a reader
 * with no JavaScript, or one behind WP Rocket's delayed-JS setting, still
 * gets the right one for whenever the page was built.
 *
 * The countdown carries a server-rendered value rather than an empty span
 * waiting to be filled. WP Rocket holds scripts until the visitor interacts
 * with the page, so an empty span is what a real visitor sees first: the
 * live site was showing "First race in" followed by nothing at all.
 *
 * "Completed" is decided by the day, not a cutoff time. The store keeps
 * dates, so the honest claim is that the race is over once its last day
 * is, and the label says completed rather than naming a cutoff we do not
 * have.
 *
 * @param array $race
 * @return string
 */
function arv_results_week_status( $race ) {
	$board  = $race['board'];
	$has    = ( null !== $board && '' !== $board['start'] );

	// The board's clock where it has one, midnight on race day where it
	// does not. The second is the honest fallback rather than a guess at a
	// start time: it is what the store actually knows.
	$start = $has ? gmdate( 'c', strtotime( $board['start'] ) ) : arv_results_start_iso( $race['iso'] );

	$cutoff_ts = function_exists( 'arv_race_cutoff_for' ) ? arv_race_cutoff_for( $race['name'], $board ) : 0;
	$cutoff_ts = arv_results_backstop_cutoff( $cutoff_ts, $start );
	$cutoff    = $cutoff_ts ? gmdate( 'c', $cutoff_ts ) : '';

	$out = '<span class="arv-results__week-status" data-arv-results-clock'
		. ' data-arv-start="' . esc_attr( $start ) . '"'
		. ( '' !== $cutoff ? ' data-arv-cutoff="' . esc_attr( $cutoff ) . '"' : '' )
		. '>';

	$out .= '<span class="arv-results__countdown" data-arv-results-countdown'
		. ( 'soon' === $race['state'] ? '' : ' hidden' ) . '>'
		. '<span class="arv-results__clock-label">' . esc_html( __( 'Starts in', 'aravaipa-elements' ) ) . '</span> '
		. '<span class="arv-results__countdown-value" data-arv-results-countdown-value>'
		. esc_html( arv_results_countdown_text( $race['iso'], $has ? $board['start'] : '' ) )
		. '</span></span>';

	$out .= '<span class="arv-results__elapsed" data-arv-results-elapsed'
		. ( 'live' === $race['state'] ? '' : ' hidden' ) . '>'
		. '<span class="arv-results__clock-label">' . esc_html( __( 'Elapsed', 'aravaipa-elements' ) ) . '</span> '
		. '<span class="arv-results__elapsed-value" data-arv-results-elapsed-value>'
		. esc_html( arv_results_elapsed_text( $has ? $board['start'] : '' ) )
		. '</span>'
		. '</span>';

	$out .= '<span class="arv-results__done"'
		. ( 'done' === $race['state'] ? '' : ' hidden' ) . '>'
		. esc_html( __( 'Completed', 'aravaipa-elements' ) )
		. '</span>';

	$out .= '</span>';

	return $out;
}



/**
 * How long until race day, in words, worked out on the server.
 *
 * Deliberately coarse: this is counting to the start of race day rather
 * than to a gun time nobody has recorded, so minutes would be precision
 * the underlying fact does not have. The script refines it as the day gets
 * close; this is what is true at the moment the page is built.
 *
 * @param string $iso Y-m-d.
 * @return string
 */
function arv_results_countdown_text( $iso, $start = '' ) {
	// current_time( 'timestamp' ) is an int in WordPress, but this is the
	// one place in the element that does arithmetic on it rather than
	// comparing date strings, so it is worth not assuming. Anything that is
	// not a number falls back to the day this element already works in,
	// which keeps the answer coarse rather than wrong.
	$now = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time(); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	if ( ! is_numeric( $now ) ) {
		$today = function_exists( 'arv_upcoming_races_today' ) ? arv_upcoming_races_today() : gmdate( 'Y-m-d' );
		$now   = strtotime( $today . ' 00:00:00' );
	}

	$target = ( '' !== $start ) ? strtotime( $start ) : strtotime( $iso . ' 00:00:00' );
	$left   = (int) $target - (int) $now;

	if ( $left <= 0 ) {
		return '';
	}

	$days = (int) floor( $left / DAY_IN_SECONDS );

	// No "in": the label beside this already says "Starts in", and the
	// script replaces this with a clock the moment it runs.
	if ( $days >= 1 ) {
		/* translators: %d is a number of days. */
		return sprintf( _n( '%d day', '%d days', $days, 'aravaipa-elements' ), $days );
	}

	$hours = max( 1, (int) round( $left / 3600 ) );

	/* translators: %d is a number of hours. */
	return sprintf( _n( '%d hour', '%d hours', $hours, 'aravaipa-elements' ), $hours );
}


/**
 * "August 2026" from an ISO date.
 *
 * UTC on purpose: these are dates, not moments. Rendering 2026-08-01 in a
 * timezone behind UTC would put it in July.
 *
 * @param string $iso Y-m-d.
 * @return string
 */
function arv_results_month_label( $iso ) {
	// Matched before it is parsed. strtotime( ' 00:00:00 UTC' ) is a valid
	// call that returns today, so an empty date would otherwise label itself
	// with whatever month the page happened to be built in.
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', (string) $iso, $m ) ) {
		return '';
	}

	$stamp = strtotime( $m[0] . ' 00:00:00 UTC' );

	return $stamp ? gmdate( 'F Y', $stamp ) : '';
}

/**
 * Every edition of every race, the most recent race first.
 *
 * Each race shows its latest edition open, because that is the one most
 * people are after and making them click for it would be a click for
 * nothing. Older editions sit behind a native <details>, so expanding needs
 * no JavaScript, works from the keyboard, and is still in the HTML for a
 * search engine whether it is open or not.
 *
 * @param array $rows
 * @param bool  $show_search
 * @return string
 */
function arv_results_search_markup() {
	return '<div class="arv-results__search">'
		. '<label class="arv-results__search-label" for="arv-results-q">'
		. esc_html( __( 'Search races', 'aravaipa-elements' ) ) . '</label>'
		. '<span class="arv-results__search-field">'
		. '<input class="arv-results__search-input" id="arv-results-q" type="search" autocomplete="off"'
		. ' placeholder="' . esc_attr( __( 'Race name', 'aravaipa-elements' ) ) . '" data-arv-results-search />'
		// Our own clear button rather than the one type="search" gives
		// you: WebKit's only appears once there is text and is a small
		// unlabelled target, and Firefox draws none at all. This one is
		// a real button with a real name, and the native one is hidden
		// so there are never two.
		. '<button class="arv-results__search-clear" type="button" hidden data-arv-results-clear>'
		. '<span class="arv-results__sr">' . esc_html( __( 'Clear search', 'aravaipa-elements' ) ) . '</span>'
		. '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">'
		. '<path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
		. '</svg></button>'
		. '</span>'
		. '<p class="arv-results__count" data-arv-results-count aria-live="polite"></p>'
		. '</div>';
}

function arv_results_by_race( $rows, $show_search ) {
	$out = $show_search ? arv_results_search_markup() : '';

	$out .= arv_results_race_groups_markup( $rows );

	return $out;
}

/**
 * The master page: one year at a time, newest first.
 *
 * A single flat list already exists for a page that is itself scoped to one
 * year, results-YYYY and the year attribute both render through
 * arv_results_by_race unchanged. This is only for the page with no year of
 * its own, where "every race, forever" is the whole point and a bare
 * seventeen-year scroll answered a question nobody asked ("what happened
 * across all of history at once") in place of the one a visitor actually
 * has ("what happened this year", then, sometimes, "what about this one
 * before that"). A year picker leading with the current one answers the
 * first without hiding the second: arv_results_filter_year keeps every
 * earlier running of a race that appears in the selected year, exactly as
 * it already does for results-2026 and the other eighteen, so a race's own
 * "N earlier editions" disclosure still opens onto its complete history.
 *
 * One panel per year rather than re-deriving on demand, so a reader with no
 * JavaScript still gets the current year's results, and a search engine
 * still indexes every year rather than only whichever one happened to be
 * selected when it crawled.
 *
 * @param array $rows Newest first, unfiltered.
 * @param bool  $show_search
 * @return string
 */
function arv_results_by_race_yearly( $rows, $show_search ) {
	$years = array();

	foreach ( $rows as $row ) {
		$y = substr( (string) $row['iso'], 0, 4 );
		if ( preg_match( '/^\d{4}$/', $y ) && ! in_array( $y, $years, true ) ) {
			$years[] = $y;
		}
	}

	if ( empty( $years ) ) {
		return arv_results_by_race( $rows, $show_search );
	}

	rsort( $years );
	$current = $years[0];

	// A row of years rather than a select. Nineteen of them is a lot to put
	// behind a click: the archive going back to 2008 is most of what was
	// rescued, and a closed dropdown says nothing about how deep it runs.
	// Laid out as buttons it reads as a range at a glance, and picking a year
	// is one tap instead of two. Scrolls sideways when it does not fit rather
	// than wrapping into a block of numbers.
	$out = '<div class="arv-results__year-bar">'
		. '<span class="arv-results__year-label" id="arv-results-year-label">'
		. esc_html( __( 'Year', 'aravaipa-elements' ) ) . '</span>'
		. '<div class="arv-results__years" role="tablist" aria-labelledby="arv-results-year-label">';

	foreach ( $years as $y ) {
		$on = ( $y === $current );
		$out .= '<button type="button" role="tab"'
			. ' class="arv-results__year' . ( $on ? ' is-on' : '' ) . '"'
			. ' data-arv-results-year="' . esc_attr( $y ) . '"'
			. ' aria-selected="' . ( $on ? 'true' : 'false' ) . '"'
			. ' aria-controls="arv-results-panel-' . esc_attr( $y ) . '">'
			. esc_html( $y ) . '</button>';
	}

	$out .= '</div></div>';

	if ( $show_search ) {
		$out .= arv_results_search_markup();
	}

	foreach ( $years as $y ) {
		$out .= '<div class="arv-results__year-panel" id="arv-results-panel-' . esc_attr( $y ) . '"'
			. ' role="tabpanel" data-arv-results-year-panel="' . esc_attr( $y ) . '"'
			. ( $y === $current ? '' : ' hidden' ) . '>';
		$out .= arv_results_race_groups_markup( arv_results_filter_year( $rows, $y ) );
		$out .= '</div>';
	}

	return $out;
}

/**
 * Race key to the race's own page on the site.
 *
 * Built from the race store, which is the only place a race's page URL lives.
 * That store is forward-looking (it drops a race once it has run), so this is
 * keyed by race rather than by edition on purpose: a race listed for next
 * spring lends its page to every one of its past editions too, which is what
 * a reader clicking a 2014 result actually wants, the race's page, not a
 * 2014 page that never existed.
 *
 * Empty when the race store is not loaded, in which case names render as
 * plain text exactly as they did before.
 *
 * @return array
 */
function arv_results_race_pages() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$map = array();

	if ( ! function_exists( 'arv_race_store_get' ) || ! function_exists( 'arv_results_race_key' ) ) {
		return $map;
	}

	foreach ( arv_race_store_get() as $race ) {
		$page = isset( $race['page'] ) ? trim( (string) $race['page'] ) : '';

		if ( '' === $page ) {
			continue;
		}

		$key = arv_results_race_key( $race['name'] );

		// First one wins. A race in the store twice (this year and next) has
		// the same page both times, and if it somehow does not, the nearer
		// edition is the better guess.
		if ( '' !== $key && ! isset( $map[ $key ] ) ) {
			$map[ $key ] = $page;
		}
	}

	return $map;
}

/**
 * Words that mean two similarly named events are not the same event.
 *
 * The archive and the calendar disagree about names often enough that an
 * exact key match finds a page for only 66 of the 120 races on the archive,
 * and the misses are not retired races: Desert Solstice, Chocorua and Aspen
 * Backcountry are all current and all named differently in the two stores.
 * A word-subset match bridges that, the same way the timing importer bridges
 * it, but subset alone is too generous in one specific way: the archive
 * carries the bike editions of four night races, and "Stunner Night Rides"
 * has every word of "Stunner Night Runs" plus one, so it matched and would
 * have sent riders to the runners' page. A ride is not a run and a virtual
 * edition is not the road one, so a word from this list present on one side
 * only is a rejection rather than a near miss.
 */
const ARV_RESULTS_NOT_THE_SAME = '/\b(ride|rides|virtual)\b/i';

/**
 * The race's own page on the site: entry, course, logistics.
 *
 * Exact key first, then the guarded subset above, and only when exactly one
 * calendar race matches: "Flagstaff 50 Endurance Runs" matches both Flagstaff
 * Sky Peaks and Flagstaff Extreme Big Pine, and two candidates is not an
 * answer. Numbers have to agree too, which is what keeps Silverton 1000 off
 * Silverton Alpine Marathon's page.
 *
 * Returns empty for a race with no page, which is the right answer for the
 * forty-three retired ones: they have results and nothing to enter.
 *
 * @param string $name
 * @return string
 */
function arv_results_race_page_url( $name ) {
	$pages = arv_results_race_pages();
	$key   = arv_results_race_key( $name );

	if ( isset( $pages[ $key ] ) ) {
		return $pages[ $key ];
	}

	if ( ! function_exists( 'arv_race_store_get' ) ) {
		return '';
	}

	$found = '';

	foreach ( arv_race_store_get() as $race ) {
		$page = isset( $race['page'] ) ? trim( (string) $race['page'] ) : '';

		if ( '' === $page || ! arv_results_same_race( $name, $race['name'] ) ) {
			continue;
		}

		// A second, different page means the name was ambiguous. No button
		// beats a button onto the wrong race.
		if ( '' !== $found && $found !== $page ) {
			return '';
		}

		$found = $page;
	}

	return $found;
}

/**
 * Whether two race names, written by two different systems, mean one race.
 *
 * @param string $a
 * @param string $b
 * @return bool
 */
function arv_results_same_race( $a, $b ) {
	if ( preg_match( ARV_RESULTS_NOT_THE_SAME, $a ) !== preg_match( ARV_RESULTS_NOT_THE_SAME, $b ) ) {
		return false;
	}

	// "Silverton 1000" and "Silverton Alpine Marathon" both reduce to
	// "silverton" once the key strips the number, so the numbers are compared
	// before that happens rather than after.
	preg_match_all( '/\d+/', (string) $a, $na );
	preg_match_all( '/\d+/', (string) $b, $nb );

	if ( implode( ',', $na[0] ) !== implode( ',', $nb[0] ) ) {
		return false;
	}

	$aw = array_filter( explode( ' ', arv_results_race_key( $a ) ) );
	$bw = array_filter( explode( ' ', arv_results_race_key( $b ) ) );

	if ( empty( $aw ) || empty( $bw ) ) {
		return false;
	}

	return array() === array_diff( $aw, $bw ) || array() === array_diff( $bw, $aw );
}

function arv_results_race_groups_markup( $rows ) {
	$races = array();

	foreach ( $rows as $row ) {
		$key = arv_results_race_key( $row['name'] );
		if ( ! isset( $races[ $key ] ) ) {
			$races[ $key ] = array();
		}
		$races[ $key ][] = $row;
	}

	// Shown only while a single race is open. Server-rendered hidden rather
	// than built in JavaScript so the control exists before the script runs
	// and cannot end up orphaned if it does not.
	$out  = '<div class="arv-results__back-bar" data-arv-results-back hidden>';
	$out .= '<button type="button" class="arv-results__back">'
		. '<svg viewBox="0 0 16 16" width="11" height="11" aria-hidden="true" focusable="false">'
		. '<path d="M10 3L5 8l5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>'
		. esc_html( __( 'All races', 'aravaipa-elements' ) ) . '</button>';
	$out .= '</div>';

	$out .= '<div class="arv-results__races" data-arv-results-list>';

	// Grouped under the month of its newest edition, the way the calendar
	// already groups the season. Seventy-four races in one unbroken column
	// gave no sense of where in the list you were, and the tail of it, races
	// whose last running was 2023, read as the page wandering off rather than
	// as the archive reaching the end of what it has. A month heading makes
	// that the answer to a question instead of a surprise.
	$month = '';

	foreach ( $races as $editions ) {
		// Newest edition first, and its name is the one to show: a race that
		// was renamed is called whatever it is called now.
		$latest = $editions[0];
		$older  = array_slice( $editions, 1 );

		$this_month = arv_results_month_label( $latest['iso'] );

		if ( $this_month !== $month ) {
			if ( '' !== $month ) {
				$out .= '</section>';
			}
			$month = $this_month;
			// A section per month, rather than headings loose among the
			// races, so the search can hide a month whose every race it
			// filtered out by hiding one element rather than working out
			// which heading belongs to which run of siblings.
			$out .= '<section class="arv-results__month-group" data-arv-results-month>';
			$out .= '<h3 class="arv-results__month-head">' . esc_html( $month ) . '</h3>';
		}

		$out .= '<div class="arv-results__race-group" data-arv-results-race="'
			. esc_attr( strtolower( $latest['name'] ) ) . '">';

		$stats = arv_stats_store_find( $latest['live'] );

		$out .= '<div class="arv-results__latest">';
		$out .= '<div class="arv-results__race-head">';

		// To this race's own results page, not to the race's marketing page.
		// The name pointed at the latter for one release, which was the
		// obvious reading of "make the name clickable" and the wrong one:
		// somebody reading an archive wants the race's results, and the
		// entry page is a click away from there anyway.
		$race_url = arv_results_race_url( $latest['name'] );

		$out .= '<h4 class="arv-results__race-name">';
		$out .= '<a class="arv-results__race-link" href="' . esc_url( $race_url ) . '">'
			. esc_html( $latest['name'] ) . '</a>';
		$out .= '</h4>';

		// The date sits beside the name rather than under it, the way the
		// race week block already puts them, and the finisher count joins it
		// rather than taking a line of its own. Three separate lines of
		// heading before the first fact about the race, seventy-four times
		// down the page, is most of what made this list feel long. They are
		// still separate elements: a heading is the race, a paragraph is when
		// it ran and how many finished, and only the layout puts them on one
		// line.
		$out .= '<p class="arv-results__race-meta">' . esc_html( arv_results_edition_label( $latest ) );

		if ( ! empty( $latest['current'] ) ) {
			$out .= ' <span class="arv-results__flag">' . esc_html( __( 'Happening now', 'aravaipa-elements' ) ) . '</span>';
		}

		$out .= arv_results_finisher_count( $stats ) . '</p>';
		$out .= '</div>';
		$out .= arv_results_links( $latest );
		$out .= '</div>';
		$out .= arv_results_winners_block( $stats );

		// No editions control here at all any more. The year list answers
		// "what happened in 2024", and a race's history is a different
		// question with its own page now: the race name above links to it.
		// An accordion in every row was the third shape this had, after a
		// disclosure and a filter button, and all three were the same
		// mistake, which is putting a race's whole history inside a row of
		// the year it last ran.

		$out .= '</div>';
	}

	if ( '' !== $month ) {
		$out .= '</section>';
	}

	$out .= '</div>';

	return $out;
}

/**
 * How many people finished, as a fragment of the line it sits on.
 *
 * Never its own line. It rides on the date, for the latest edition and for
 * every older one, because a count is a fact about that running rather than
 * a separate thing to say about it, and giving it a line of its own is what
 * made a race group four lines tall before the first result appeared.
 *
 * Silent when there is nothing to say. A race that has not been run yet
 * reads zero finishers, correctly, and "0 finishers" under next weekend's
 * race would be a worse answer than none.
 *
 * @param array|null $stats
 * @return string
 */
function arv_results_finisher_count( $stats ) {
	if ( null === $stats || empty( $stats['finishers'] ) ) {
		return '';
	}

	$finishers = (int) $stats['finishers'];

	return ' <span class="arv-results__stat">' . esc_html(
		sprintf(
			// translators: %s is a formatted count of finishers.
			_n( '%s finisher', '%s finishers', $finishers, 'aravaipa-elements' ),
			number_format_i18n( $finishers )
		)
	) . '</span>';
}

/**
 * A finish time as a person would write it.
 *
 * "0:23:30" is how the board stores a sub-hour time and it reads as a
 * placeholder, or as a stopwatch that has not started, rather than as
 * twenty-three and a half minutes.
 *
 * This was applied to the headline only at first, on the theory that the
 * tables needed the padded form to keep their times in a column. They do
 * not: a time in those tables follows a runner's name in the same cell, so
 * it starts wherever that name ends and there is no column of times to
 * align in the first place. The padding was buying nothing anywhere, so it
 * goes everywhere.
 *
 * @param string $time
 * @return string
 */
function arv_results_time_label( $time ) {
	return preg_replace( '/^0:(?=\d\d:\d\d$)/', '', (string) $time );
}

/**
 * One distance's winners, as a line: the shape both the closed summary and
 * the open headline need, so there is exactly one place that draws a name
 * and a time next to a division.
 *
 * Not one winner: at Bear Chase 2024 the women's 100K champion finished in
 * 11:35:21 and the men's in 12:10:00, so naming a single winner was not a
 * summary of that race, it was a wrong answer. Adaptive rather than fixed at
 * two for the same reason the table's columns are: most events ran men and
 * women, Javelina and Black Canyon also scored nonbinary, and hardcoding the
 * common case would erase the flagship's own results.
 *
 * @param array $row
 * @return string
 */
function arv_results_winner_line( $row ) {
	$out = '';

	if ( '' !== $row['distance'] ) {
		$out .= '<span class="arv-results__winners-distance">'
			. esc_html( arv_results_distance_label( $row['distance'] ) ) . '</span>';
	}

	foreach ( arv_stats_divisions() as $division ) {
		if ( ! isset( $row[ $division ] ) ) {
			continue;
		}

		$out .= '<span class="arv-results__winner">'
			// The division is named for a screen reader and not drawn, because
			// sighted readers get it from the names and a row reading
			// "Men Alex Bustamante Women Sydney Park" is three words of
			// scaffolding for four words of result.
			. '<span class="arv-results__sr">'
			. esc_html( arv_results_division_label( $division ) ) . ': </span>'
			. '<span class="arv-results__winner-name">' . esc_html( $row[ $division ]['name'] ) . '</span> '
			. '<span class="arv-results__winner-time">'
			. esc_html( arv_results_time_label( $row[ $division ]['time'] ) ) . '</span>'
			. '</span>';
	}

	return $out;
}

/**
 * The winners, headline first and the rest behind a disclosure.
 *
 * This used to be two functions and two elements: a headline paragraph, then
 * a details/summary repeating that same top distance as the table's first
 * row before the remaining ones. Every race with more than one distance
 * said its premier result twice, forty or so pixels apart. One function now,
 * because there is one control: closed, it reads as the headline line
 * already did; opening it adds the other distances rather than restating the
 * first one. An event with a single scored distance gets the line with no
 * control at all, same as before.
 *
 * "Winners" as a label sat directly under the winners it was announcing,
 * telling a reader nothing the line above it had not already shown. "4 more
 * distances" says what the click is actually for.
 *
 * Up to nine rows and three columns at Royal Gorge Groove, which is exactly
 * the amount of information that has to be closed by default; seventy-four
 * of those open at once is not an archive, it is a results booklet.
 *
 * @param array $stats
 * @return string
 */
function arv_results_winners_block( $stats ) {
	$winners = isset( $stats['winners'] ) ? $stats['winners'] : array();

	if ( empty( $winners ) ) {
		return '';
	}

	// A headline means winners[0] is the premier distance: feature it and
	// fold the rest behind it. Without one, a lap event runs every category
	// over one loop, so its longest distance is whichever the GPS rounded
	// up and none of them is a "top" result to feature over the others; "who
	// won the six hour solo" still needs an answer, so every distance,
	// including a lone one, goes straight into the table with a plain label
	// on the summary rather than a preview that would overstate one of them.
	$has_headline = ! empty( $stats['headline'] );
	$top          = $has_headline ? $winners[0] : null;
	$rest         = $has_headline ? array_slice( $winners, 1 ) : $winners;

	if ( $has_headline && empty( $rest ) ) {
		return '<p class="arv-results__winners">' . arv_results_winner_line( $top ) . '</p>';
	}

	$divisions = arv_stats_divisions_present( $winners );

	$out  = '<details class="arv-results__winners-all">';
	$out .= '<summary class="arv-results__winners-summary">'
		. '<svg class="arv-results__chevron" viewBox="0 0 16 16" width="11" height="11" aria-hidden="true" focusable="false">'
		. '<path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>';

	if ( $has_headline ) {
		$out .= arv_results_winner_line( $top )
			. '<span class="arv-results__older-years">'
			. esc_html(
				sprintf(
					// translators: %d is a count of additional distances.
					_n( '%d more distance', '%d more distances', count( $rest ), 'aravaipa-elements' ),
					count( $rest )
				)
			)
			. '</span>';
	} else {
		$out .= esc_html( __( 'Winners', 'aravaipa-elements' ) )
			. '<span class="arv-results__older-years">'
			. esc_html(
				sprintf(
					// translators: %d is a count of distances.
					_n( '%d distance', '%d distances', count( $winners ), 'aravaipa-elements' ),
					count( $winners )
				)
			)
			. '</span>';
	}

	$out .= '</summary>';

	// Wrapped so the overflow lives on a div rather than on the table. Set
	// on the table itself it needs display:block, which throws away the table
	// layout that makes the columns line up in the first place.
	$out .= '<div class="arv-results__winners-scroll">';
	$out .= '<table class="arv-results__winners-table"><thead><tr>'
		. '<th scope="col">' . esc_html( __( 'Distance', 'aravaipa-elements' ) ) . '</th>';

	foreach ( $divisions as $division ) {
		$out .= '<th scope="col">' . esc_html( arv_results_division_label( $division ) ) . '</th>';
	}

	$out .= '</tr></thead><tbody>';

	foreach ( $rest as $row ) {
		$out .= '<tr><th scope="row">'
			. esc_html( arv_results_distance_label( $row['distance'] ) ) . '</th>';

		foreach ( $divisions as $division ) {
			if ( ! isset( $row[ $division ] ) ) {
				// A division the board could not resolve a winner for, which
				// happens: one 6K women's winner on the archive has a finish
				// stamp equal to her start. An empty cell is the honest
				// answer; a dash would read as "nobody entered".
				$out .= '<td></td>';
				continue;
			}

			$out .= '<td><span class="arv-results__winner-name">'
				. esc_html( $row[ $division ]['name'] ) . '</span> '
				. '<span class="arv-results__winner-time">'
				. esc_html( arv_results_time_label( $row[ $division ]['time'] ) ) . '</span></td>';
		}

		$out .= '</tr>';
	}

	return $out . '</tbody></table></div></details>';
}

/**
 * The name of one scoring division.
 *
 * @param string $division
 * @return string
 */
function arv_results_division_label( $division ) {
	$labels = array(
		'men'       => __( 'Men', 'aravaipa-elements' ),
		'women'     => __( 'Women', 'aravaipa-elements' ),
		'nonbinary' => __( 'Nonbinary', 'aravaipa-elements' ),
	);

	return isset( $labels[ $division ] ) ? $labels[ $division ] : $division;
}

/**
 * "2025, 2024, 2023" for a run of editions.
 *
 * Read off the stored date rather than the display string, which is a range
 * ("August 14-16") on a multi-day race and has no year in it at all.
 *
 * @param array $editions
 * @return string
 */
function arv_results_years( $editions ) {
	$years = array();

	foreach ( $editions as $edition ) {
		$year = substr( (string) $edition['iso'], 0, 4 );

		// A race that ran twice in one calendar year, which the archive does
		// contain, should say that year once.
		if ( '' !== $year && ! in_array( $year, $years, true ) ) {
			$years[] = $year;
		}
	}

	return implode( ', ', $years );
}

/**
 * "February 14, 2026" for one edition.
 *
 * The year is always spelled out here, unlike the date layout where the
 * month heading above the row already carries it. In this layout the whole
 * point of a row is which year it is.
 *
 * @param array $row
 * @return string
 */
function arv_results_edition_label( $row ) {
	$stamp = strtotime( $row['iso'] . ' 00:00:00 UTC' );
	$day   = '' !== $row['display'] ? $row['display'] : gmdate( 'F j', $stamp );

	return $day . ', ' . gmdate( 'Y', $stamp );
}

/**
 * The row of links for one edition.
 *
 * @param array $row
 * @return string
 */
function arv_results_links( $row ) {
	// The branded page for this edition where one has been built, the board
	// itself where one has not, which is most of them.
	$live = function_exists( 'arv_live_page_for_live_url' )
		? arv_live_page_for_live_url( $row['live'] )
		: '';
	$live = '' !== $live ? $live : $row['live'];

	$slots = array(
		array( $live, __( 'Live Results', 'aravaipa-elements' ), 'live' ),
		array( isset( $row['ultrasignup'] ) ? $row['ultrasignup'] : '', __( 'UltraSignup', 'aravaipa-elements' ), 'us' ),
		array( isset( $row['ultrarunning'] ) ? $row['ultrarunning'] : '', __( 'UltraRunning', 'aravaipa-elements' ), 'ur' ),
	);

	$has_slot = false;

	foreach ( $slots as $slot ) {
		if ( '' !== trim( (string) $slot[0] ) ) {
			$has_slot = true;
			break;
		}
	}

	// Both forms of the live URL: the archive link was scraped off the same
	// page that fed the slots, so it can match either the board or the
	// branded page that stands in for it.
	$primary = array( $live, $row['live'] );

	foreach ( $slots as $slot ) {
		$primary[] = $slot[0];
	}

	$archive = arv_results_archive( $row, $primary );
	$out     = '';

	// A race with no listing and no files of its own has nothing to show.
	// Returning the wrapper anyway would put an empty flex child in the
	// edition row and pull the date off the left with its gap.
	if ( '' === $archive && ! $has_slot ) {
		return '';
	}

	// One box around both rows. The edition line is a space-between flex
	// row of exactly two things, the date and the links, and adding the
	// archive beside them made it three: the chips then sat wherever the
	// wrap happened to put them, right-aligned next to the date on a race
	// with four of them and left-aligned on its own line on a race with
	// nine. Wrapped, they hang off the same right edge as the buttons
	// regardless of how many there are.
	$out .= '<div class="arv-results__actions">';

	// The grid holds three fixed columns still, so a race with two listings
	// does not start its buttons where a race with three starts its second.
	// A race with none of the three has nothing to line up with and gets no
	// grid at all: three empty columns above a row of archive links is a gap
	// holding space for buttons that are never coming, which is most of what
	// the archive years look like.
	if ( $has_slot ) {
		$out .= '<div class="arv-results__links">';

		foreach ( $slots as $slot ) {
			list( $url, $label, $kind ) = $slot;
			$url = trim( (string) $url );

			if ( '' === $url ) {
				// An empty slot, not nothing. Dropping a missing link let the
				// row shrink from the left, so a race with two listings
				// started its buttons 119px right of a race with three and
				// the whole page had a ragged edge running down it. The slot
				// holds the column and shows nothing.
				$out .= '<span class="arv-results__slot" aria-hidden="true"></span>';
				continue;
			}

			$out .= '<a class="arv-results__link arv-results__link--' . esc_attr( $kind ) . '" href="'
				. esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		}

		$out .= '</div>';
	}

	return $out . $archive . '</div>';
}

/**
 * A race's own result files, one per distance.
 *
 * Everything before 2020 was scored on Aravaipa's own site rather than by a
 * timing provider with a page per race, so an edition is not one link but
 * several: "6 Day", "72 Hours", "48 Hours", "24 Hours", "Splits" for Across
 * The Years 2016, each a separate file.
 *
 * Not in the three-column grid above, and not styled as a fourth button.
 * Both would be wrong. The grid holds three fixed columns and this is a
 * variable number of them; and a distance is not the same kind of thing as
 * "UltraSignup", which names a place to go rather than a race to read. So
 * they wrap as a set, sized and weighted below the buttons, which is also
 * how they read: one race, several distances.
 *
 * @param array $row
 * @return string
 */
function arv_results_archive( $row, $primary = array() ) {
	$links = isset( $row['archive'] ) && is_array( $row['archive'] ) ? $row['archive'] : array();

	if ( empty( $links ) ) {
		return '';
	}

	$seen = array();

	foreach ( (array) $primary as $url ) {
		$url = arv_results_archive_key( $url );

		if ( '' !== $url ) {
			$seen[ $url ] = true;
		}
	}

	$chips = array();

	foreach ( $links as $link ) {
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			continue;
		}

		// Some editions list their UltraSignup board twice, once as the
		// listing and once among the per-distance files. Rendering it as a
		// button and again as a chip beside it just looks like a mistake.
		$key = arv_results_archive_key( $link['url'] );

		if ( '' === $key || isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;

		$label = isset( $link['label'] ) ? trim( (string) $link['label'] ) : '';

		// A file with no label of its own still needs a word on it. "Results"
		// is what the page it came from called the ones it did not name.
		if ( '' === $label ) {
			$label = __( 'Results', 'aravaipa-elements' );
		}

		$chips[] = '<a class="arv-results__archive-link" href="' . esc_url( $link['url'] )
			. '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
	}

	// Every link this row had was already a button above it. The row is not
	// empty-looking, it is absent, which is the correct amount of nothing.
	if ( empty( $chips ) ) {
		return '';
	}

	return '<div class="arv-results__archive">' . implode( '', $chips ) . '</div>';
}

/**
 * Normalizes a results URL enough to tell two spellings of the same link
 * apart. Scheme, host case, and a trailing slash all vary between the
 * scraped pages and the stored slots; nothing past that is touched, because
 * query strings are what identify an UltraSignup board.
 */
function arv_results_archive_key( $url ) {
	$url = strtolower( trim( (string) $url ) );

	if ( '' === $url ) {
		return '';
	}

	$url = preg_replace( '#^https?://#', '', $url );
	$url = preg_replace( '#^www\.#', '', $url );

	return rtrim( $url, '/' );
}

/**
 * The list itself, grouped by month.
 *
 * A real <table> with real headers, because that is what this is: four
 * columns of the same kind of thing, read across. The month is a full-width
 * row inside the same table rather than a separate heading between tables,
 * so a screen reader and a keyboard move through one structure instead of
 * eighty.
 *
 * @param array $rows
 * @return string
 */
function arv_results_table( $rows ) {
	$out  = '<div class="arv-results__scroll">';
	$out .= '<table class="arv-results__table">';
	$out .= '<thead><tr>'
		. '<th scope="col">' . esc_html( __( 'Race', 'aravaipa-elements' ) ) . '</th>'
		. '<th scope="col">' . esc_html( __( 'Live Results', 'aravaipa-elements' ) ) . '</th>'
		. '<th scope="col">' . esc_html( __( 'UltraSignup', 'aravaipa-elements' ) ) . '</th>'
		. '<th scope="col">' . esc_html( __( 'UltraRunning', 'aravaipa-elements' ) ) . '</th>'
		. '</tr></thead><tbody>';

	$month = '';

	foreach ( $rows as $row ) {
		$stamp = strtotime( $row['iso'] . ' 00:00:00 UTC' );
		$this_month = gmdate( 'F Y', $stamp );

		if ( $this_month !== $month ) {
			$month = $this_month;
			$out  .= '<tr class="arv-results__month"><th colspan="4" scope="colgroup">'
				. esc_html( $month ) . '</th></tr>';
		}

		$display = '' !== $row['display'] ? $row['display'] : gmdate( 'F j', $stamp );

		$out .= '<tr class="arv-results__row">';
		$out .= '<th scope="row" class="arv-results__race">'
			. '<span class="arv-results__name">' . esc_html( $row['name'] ) . '</span>'
			. '<time class="arv-results__date" datetime="' . esc_attr( $row['iso'] ) . '">' . esc_html( $display ) . '</time>';

		if ( ! empty( $row['current'] ) ) {
			// Only while it is the live board's own race. Once the scrape
			// picks the race up this row is replaced by the stored one and
			// the tag goes with it.
			$out .= '<span class="arv-results__flag">' . esc_html( __( 'Happening now', 'aravaipa-elements' ) ) . '</span>';
		}

		$out .= '</th>';

		$out .= arv_results_cell( $row['live'], __( 'Live Results', 'aravaipa-elements' ), 'live' );
		$out .= arv_results_cell( $row['ultrasignup'], __( 'UltraSignup', 'aravaipa-elements' ), 'us' );
		$out .= arv_results_cell( $row['ultrarunning'], __( 'UltraRunning', 'aravaipa-elements' ), 'ur' );

		$out .= '</tr>';
	}

	$out .= '</tbody></table></div>';

	return $out;
}

/**
 * One link cell.
 *
 * An empty cell where a race has no listing, not a dead button. Roughly half
 * the calendar never appears on UltraRunning at all, because it only lists
 * ultras, so a greyed-out "UltraRunning" on every 5K would be three hundred
 * pieces of furniture saying nothing.
 *
 * @param string $url
 * @param string $label
 * @param string $kind
 * @return string
 */
function arv_results_cell( $url, $label, $kind ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '<td class="arv-results__cell arv-results__cell--empty"><span class="arv-results__dash" aria-hidden="true">&ndash;</span></td>';
	}

	// The label repeats the column header on purpose: on a phone the table
	// stacks and the header is no longer beside the link.
	return '<td class="arv-results__cell">'
		. '<a class="arv-results__link arv-results__link--' . esc_attr( $kind ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
		. esc_html( $label ) . '</a></td>';
}

/**
 * One race, every edition, all time.
 *
 * The page a race name links to from the archive. Its job is the question
 * the year list cannot answer: not "what happened in 2024" but "what is this
 * race, and what has it ever done".
 *
 * Course records are derived rather than stored, because the stats store
 * holds each edition's winner per distance per division and a course record
 * IS the fastest of those: nobody can have run a distance faster than the
 * person who won it. That equivalence is exactly why top ten all time is NOT
 * here. It needs the whole field, and the store keeps only winners, so a
 * "top ten" built from this data would be a top ten of first places, which
 * is a different and misleading thing.
 *
 * @param array  $editions Newest first, every running of one race.
 * @param string $name     The race's current name.
 * @return string
 */
function arv_results_race_page( $editions, $name ) {
	$out = '<div class="arv-results__race-page">';

	$out .= '<div class="arv-results__back-bar">';
	$out .= '<a class="arv-results__back" href="' . esc_url( arv_results_archive_url() ) . '">'
		. '<svg viewBox="0 0 16 16" width="11" height="11" aria-hidden="true" focusable="false">'
		. '<path d="M10 3L5 8l5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>'
		. esc_html( __( 'All races', 'aravaipa-elements' ) ) . '</a>';
	$out .= '</div>';

	// h1, not h2: the page has none of its own (the theme does not print the
	// Page's title here), and on a page whose entire subject is one race the
	// race is the heading, not a subheading of an unstated one.
	//
	// The race's own page sits beside the name rather than under the results,
	// because "where do I enter this" is the one question this page cannot
	// answer and the most likely reason somebody reading a course record
	// leaves it. Absent for a retired race, which has results and nothing to
	// enter, so the heading closes up rather than holding an empty slot.
	$race_page = arv_results_race_page_url( $name );

	$out .= '<div class="arv-results__race-head-row">';
	$out .= '<h1 class="arv-results__race-title">' . esc_html( $name ) . '</h1>';

	if ( '' !== $race_page ) {
		$out .= '<a class="arv-results__race-info" href="' . esc_url( $race_page ) . '">'
			. esc_html( __( 'Race Info', 'aravaipa-elements' ) )
			. '<svg viewBox="0 0 16 16" width="11" height="11" aria-hidden="true" focusable="false">'
			. '<path d="M6 3l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
			. '</svg></a>';
	}

	$out .= '</div>';

	$years = arv_results_years( $editions );
	$out  .= '<p class="arv-results__race-sub">'
		. esc_html(
			sprintf(
				// translators: %d is a count of runnings.
				_n( '%d edition', '%d editions', count( $editions ), 'aravaipa-elements' ),
				count( $editions )
			)
		);

	if ( '' !== $years ) {
		$out .= ' <span class="arv-results__older-years">' . esc_html( $years ) . '</span>';
	}

	$out .= '</p>';

	$out .= arv_results_course_records( $editions );
	$out .= arv_results_editions_table( $editions );

	return $out . '</div>';
}

/**
 * The fastest winning time at each distance, and who set it.
 *
 * Times are compared as strings only after being parsed to seconds, because
 * "10:04:19" and "9:22:16" sort the wrong way round as text, and half this
 * archive is hour-scale while the other half is not.
 *
 * @param array $editions
 * @return string
 */
function arv_results_course_records( $editions ) {
	$best = array();

	foreach ( $editions as $edition ) {
		$stats = arv_stats_store_find( $edition['live'] );

		if ( null === $stats || empty( $stats['winners'] ) ) {
			continue;
		}

		$year = substr( (string) $edition['iso'], 0, 4 );

		foreach ( $stats['winners'] as $row ) {
			$distance = isset( $row['distance'] ) ? (string) $row['distance'] : '';

			if ( '' === $distance ) {
				continue;
			}

			foreach ( arv_stats_divisions() as $division ) {
				if ( ! isset( $row[ $division ]['time'] ) ) {
					continue;
				}

				$seconds = arv_results_time_seconds( $row[ $division ]['time'] );

				if ( null === $seconds ) {
					continue;
				}

				$current = isset( $best[ $distance ][ $division ] ) ? $best[ $distance ][ $division ] : null;

				if ( null === $current || $seconds < $current['seconds'] ) {
					$best[ $distance ][ $division ] = array(
						'name'    => $row[ $division ]['name'],
						'time'    => $row[ $division ]['time'],
						'year'    => $year,
						'seconds' => $seconds,
					);
				}
			}
		}
	}

	if ( empty( $best ) ) {
		return '';
	}

	$divisions = array();

	foreach ( $best as $rows ) {
		foreach ( arv_stats_divisions() as $division ) {
			if ( isset( $rows[ $division ] ) ) {
				$divisions[ $division ] = true;
			}
		}
	}

	$out  = '<section class="arv-results__records">';
	$out .= '<h3 class="arv-results__records-head">' . esc_html( __( 'Course records', 'aravaipa-elements' ) ) . '</h3>';
	$out .= '<div class="arv-results__winners-scroll"><table class="arv-results__winners-table"><thead><tr>';
	$out .= '<th scope="col">' . esc_html( __( 'Distance', 'aravaipa-elements' ) ) . '</th>';

	foreach ( array_keys( $divisions ) as $division ) {
		$out .= '<th scope="col">' . esc_html( arv_results_division_label( $division ) ) . '</th>';
	}

	$out .= '</tr></thead><tbody>';

	foreach ( $best as $distance => $rows ) {
		$out .= '<tr><th scope="row">' . esc_html( arv_results_distance_label( $distance ) ) . '</th>';

		foreach ( array_keys( $divisions ) as $division ) {
			$out .= '<td>';

			if ( isset( $rows[ $division ] ) ) {
				$out .= '<span class="arv-results__winner-name">' . esc_html( $rows[ $division ]['name'] ) . '</span> '
					. '<span class="arv-results__winner-time">'
					. esc_html( arv_results_time_label( $rows[ $division ]['time'] ) ) . '</span> '
					. '<span class="arv-results__record-year">' . esc_html( $rows[ $division ]['year'] ) . '</span>';
			}

			$out .= '</td>';
		}

		$out .= '</tr>';
	}

	return $out . '</tbody></table></div></section>';
}

/**
 * "10:04:19" and "58:22" to seconds, or null if it is neither.
 *
 * Two and three part times both occur here: a 5K winner runs minutes and a
 * hundred miler runs hours, and the board writes each the short way.
 *
 * @param string $time
 * @return int|null
 */
function arv_results_time_seconds( $time ) {
	$parts = explode( ':', trim( (string) $time ) );

	if ( count( $parts ) < 2 || count( $parts ) > 3 ) {
		return null;
	}

	foreach ( $parts as $part ) {
		if ( ! preg_match( '/^\d+$/', trim( $part ) ) ) {
			return null;
		}
	}

	$parts   = array_map( 'intval', $parts );
	$seconds = array_pop( $parts );
	$seconds += array_pop( $parts ) * 60;

	if ( ! empty( $parts ) ) {
		$seconds += array_pop( $parts ) * 3600;
	}

	return $seconds;
}

/**
 * Every edition, one row each.
 *
 * @param array $editions
 * @return string
 */
function arv_results_editions_table( $editions ) {
	$out  = '<section class="arv-results__editions-all">';
	$out .= '<h3 class="arv-results__records-head">' . esc_html( __( 'Every edition', 'aravaipa-elements' ) ) . '</h3>';

	foreach ( $editions as $edition ) {
		$stats = arv_stats_store_find( $edition['live'] );

		$out .= '<div class="arv-results__edition">';
		$out .= '<div class="arv-results__latest">';
		$out .= '<div class="arv-results__race-head">';
		$out .= '<p class="arv-results__edition-date">' . esc_html( arv_results_edition_label( $edition ) )
			. arv_results_finisher_count( $stats ) . '</p>';
		$out .= '</div>';
		$out .= arv_results_links( $edition );
		$out .= '</div>';
		$out .= arv_results_winners_block( $stats );

		$out .= '</div>';
	}

	return $out . '</section>';
}

/**
 * One race's own page.
 *
 * Built from the site root and ARV_RESULTS_RACE_BASE rather than from the
 * archive page's permalink, because the two are deliberately not the same
 * path any more: see the constant's own note for why /results/<race> is
 * unavailable no matter what WordPress is told.
 *
 * @param string $name
 * @return string
 */
function arv_results_race_url( $name ) {
	$base = function_exists( 'home_url' )
		? rtrim( (string) home_url( '/' . ARV_RESULTS_RACE_BASE ), '/' )
		: '/' . ARV_RESULTS_RACE_BASE;

	return $base . '/' . arv_results_race_slug( $name ) . '/';
}

/**
 * The archive's own URL, for the way back out of a race page.
 *
 * @return string
 */
function arv_results_archive_url() {
	$id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;

	return $id > 0 && function_exists( 'get_permalink' ) ? (string) get_permalink( $id ) : '/results/';
}
