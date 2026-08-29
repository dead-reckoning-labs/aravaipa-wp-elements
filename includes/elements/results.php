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

// Eight days. Longer than any race on the calendar: Cocodona 250 allows
// about 125 hours. See arv_results_backstop_cutoff().
if ( ! defined( 'ARV_RESULTS_MAX_RUN' ) ) {
	define( 'ARV_RESULTS_MAX_RUN', 8 * DAY_IN_SECONDS );
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
			),
			'omega'
		),
		'builder' => 'arv_results_builder',
		'render'  => 'arv_results_render',
	)
);

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

		$rows[] = array(
			'name'         => $race['name'],
			'iso'          => $race['iso'],
			'display'      => $race['display'],
			'live'         => $race['live'],
			'ultrasignup'  => '',
			'ultrarunning' => '',
			'current'      => true,
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
		foreach ( arv_results_live_rows( $today ) as $row ) {
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

	$out .= arv_results_by_race( $rows, $show_search );
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

	foreach ( arv_race_store_get() as $race ) {
		$action = arv_upcoming_races_action( $race, $today );
		$last   = ( '' !== $race['end'] ) ? $race['end'] : $race['iso'];

		// Race week itself, plus a few days the other side of it. Without
		// the tail a race drops out of this block the moment it finishes,
		// which is the exact hour people come looking for it: it would go
		// from "live" straight to gone, and the finished state would never
		// be seen by anyone.
		$recent = ( 'results' === $action['phase'] && $last >= $cutoff );

		if ( ! ( 'live' === $action['phase'] || $recent ) || '' === $action['url'] ) {
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

		$out .= '<a class="arv-results__link arv-results__link--live" href="' . esc_url( $race['url'] ) . '" target="_blank" rel="noopener">'
			. esc_html( __( 'Live Results', 'aravaipa-elements' ) ) . '</a>';

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
 * Now, as a unix timestamp, in a way the test harness can move.
 *
 * @return int
 */
function arv_results_now() {
	$now = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time(); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	if ( is_numeric( $now ) ) {
		return (int) $now;
	}

	$today = function_exists( 'arv_upcoming_races_today' ) ? arv_upcoming_races_today() : gmdate( 'Y-m-d' );

	return (int) strtotime( $today . ' 00:00:00' );
}

/**
 * "50KM" and "50 K" and "50k" are all 50K.
 *
 * The rows are typed by hand from whatever each race's own page calls its
 * distances, so the same distance is written three ways across the
 * calendar. Normalised only for display: the stored value is left alone,
 * since it is also what matches a distance to the timing board's name.
 *
 * @param string $distance
 * @return string
 */
function arv_results_distance_label( $distance ) {
	$label = trim( (string) $distance );

	// 50KM -> 50K, and 50 K -> 50K. Kilometres only: "4 Mile" and
	// "1 Mile Fun Run" are already how anyone would say them.
	$label = preg_replace( '/^(\d+(?:\.\d+)?)\s*(?:km|kms|kilometers?|kilometres?)$/i', '$1K', $label );
	$label = preg_replace( '/^(\d+(?:\.\d+)?)\s+k$/i', '$1K', $label );

	return $label;
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
 * A cutoff for a race that has none, so that "live" cannot last forever.
 *
 * Without one, both this and the script that drives the clock decided a race
 * was live on the strength of its start time alone, which is true from the
 * gun until the end of time. Black Bear's 2025 page carried a LIVE NOW marker
 * and an elapsed clock reading 363 days.
 *
 * ARV_RESULTS_MAX_RUN is longer than anything on the calendar. Cocodona 250,
 * the longest race Aravaipa puts on, allows about 125 hours.
 *
 * Returned rather than applied so the same number reaches the markup, where
 * the script reads it off data-arv-cutoff. One rule, one place, and no way
 * for the server and the browser to disagree a second after load.
 *
 * @param int    $cutoff_ts Real cutoff, or 0 where there is none.
 * @param string $start     ISO 8601 start.
 * @return int
 */
function arv_results_backstop_cutoff( $cutoff_ts, $start ) {
	if ( $cutoff_ts ) {
		return (int) $cutoff_ts;
	}

	$start_ts = strtotime( (string) $start );

	return $start_ts ? ( $start_ts + ARV_RESULTS_MAX_RUN ) : 0;
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
 * The pulsing marker, on its own rather than inside the clock cell.
 *
 * It sits beside the race name because that is what it is about: this
 * race, right now. Keeping it out of the status cell also means the
 * elapsed clock can run next to it rather than instead of it, which is
 * what someone watching a race in progress actually wants to see.
 *
 * @param array $race
 * @return string
 */
function arv_results_week_live_badge( $race ) {
	return '<span class="arv-results__live" data-arv-results-live'
		. ( 'live' === $race['state'] ? '' : ' hidden' ) . '>'
		. '<span class="arv-results__pulse" aria-hidden="true"></span>'
		. esc_html( __( 'Live now', 'aravaipa-elements' ) )
		. '</span>';
}

/**
 * How long a race has been running, coarsely, worked out on the server.
 *
 * Same reason the countdown has a server-rendered value: WP Rocket holds
 * scripts until the visitor interacts, so an empty span is what a real
 * visitor reads first. Hours and minutes rather than seconds, because that
 * is as precise as a number can usefully be before the script takes over
 * and starts ticking.
 *
 * @param string $start ISO 8601, or empty when the board has no time.
 * @return string
 */
function arv_results_elapsed_text( $start ) {
	if ( '' === $start ) {
		return '';
	}

	$since = arv_results_now() - (int) strtotime( $start );

	if ( $since <= 0 ) {
		return '';
	}

	$hours   = (int) floor( $since / 3600 );
	$minutes = (int) floor( ( $since % 3600 ) / 60 );

	return sprintf( '%d:%02d', $hours, $minutes );
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
 * Midnight on race day, as an instant the browser can count down to.
 *
 * The store keeps dates, not gun times, so this is the start of race day
 * rather than the start of the race. That is why the label above it says
 * "first race in" against a date rather than naming a start time it does
 * not have: the honest version of a fact we only half know.
 *
 * Carries the site's own UTC offset rather than leaving the browser to
 * assume its own. A reader in another timezone should be counting down to
 * the same moment as a reader in Phoenix, not to their own local midnight.
 *
 * @param string $iso Y-m-d.
 * @return string ISO 8601 with offset.
 */
function arv_results_start_iso( $iso ) {
	$offset = function_exists( 'get_option' ) ? (float) get_option( 'gmt_offset', 0 ) : 0;
	$sign   = ( $offset < 0 ) ? '-' : '+';
	$abs    = abs( $offset );

	return $iso . 'T00:00:00' . sprintf( '%s%02d:%02d', $sign, (int) floor( $abs ), (int) round( ( $abs - floor( $abs ) ) * 60 ) );
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
function arv_results_by_race( $rows, $show_search ) {
	$races = array();

	foreach ( $rows as $row ) {
		$key = arv_results_race_key( $row['name'] );
		if ( ! isset( $races[ $key ] ) ) {
			$races[ $key ] = array();
		}
		$races[ $key ][] = $row;
	}

	$out = '';

	if ( $show_search ) {
		$out .= '<div class="arv-results__search">'
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
		$out .= '<h4 class="arv-results__race-name">' . esc_html( $latest['name'] ) . '</h4>';

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
		$out .= arv_results_headline_winners( $stats );
		$out .= '</div>';
		$out .= arv_results_links( $latest );
		$out .= '</div>';

		if ( null !== $stats ) {
			$out .= arv_results_winners_table( $stats );
		}

		if ( ! empty( $older ) ) {
			$out .= '<details class="arv-results__older">';
			$out .= '<summary class="arv-results__older-toggle">'
				// An explicit chevron, because setting any display other
				// than list-item on a <summary> removes the browser's own
				// disclosure triangle, and this had ended up with no visible
				// affordance at all: bold teal text that gave no sign it
				// could be opened. Rotates via CSS on [open].
				. '<svg class="arv-results__chevron" viewBox="0 0 16 16" width="11" height="11" aria-hidden="true" focusable="false">'
				. '<path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
				. '</svg>'
				. esc_html(
					sprintf(
						// translators: %d is a count of previous runnings.
						_n( '%d earlier edition', '%d earlier editions', count( $older ), 'aravaipa-elements' ),
						count( $older )
					)
				)
				// The years themselves, next to the count. "3 earlier
				// editions" tells you there is more without telling you
				// whether it is the years you want, so anyone after a
				// particular one has to open every group to find out. The
				// years answer that before the click, and they are already
				// in hand.
				. '<span class="arv-results__older-years">' . esc_html( arv_results_years( $older ) ) . '</span>'
				. '</summary>';

			foreach ( $older as $edition ) {
				$out .= '<div class="arv-results__edition">';
				$out .= '<p class="arv-results__edition-date">' . esc_html( arv_results_edition_label( $edition ) )
					. arv_results_finisher_count( arv_stats_store_find( $edition['live'] ) ) . '</p>';
				$out .= arv_results_links( $edition );
				$out .= '</div>';
			}

			$out .= '</details>';
		}

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
 * The marquee result: who won the event's premier distance.
 *
 * One distance, every division that ran it. Not one winner: at Bear Chase
 * 2024 the women's 100K champion finished in 11:35:21 and the men's in
 * 12:10:00, so naming a single winner was not a summary of that race, it was
 * a wrong answer. The full table below carries the rest.
 *
 * Adaptive rather than fixed at two, for the same reason the table's columns
 * are: most events ran men and women, Javelina and Black Canyon also scored
 * nonbinary, and hardcoding the common case would erase the flagship's own
 * results.
 *
 * @param array $stats
 * @return string
 */
function arv_results_headline_winners( $stats ) {
	// No premier distance means no headline. A lap event runs every category
	// over one loop, so its longest distance is whichever the GPS rounded up.
	// The table still lists them all.
	if ( empty( $stats['winners'] ) || empty( $stats['headline'] ) ) {
		return '';
	}

	$top = $stats['winners'][0];
	$out = '<p class="arv-results__winners">';

	if ( '' !== $top['distance'] ) {
		$out .= '<span class="arv-results__winners-distance">'
			. esc_html( arv_results_distance_label( $top['distance'] ) ) . '</span>';
	}

	foreach ( arv_stats_divisions() as $division ) {
		if ( ! isset( $top[ $division ] ) ) {
			continue;
		}

		$out .= '<span class="arv-results__winner">'
			// The division is named for a screen reader and not drawn, because
			// sighted readers get it from the names and a row reading
			// "Men Alex Bustamante Women Sydney Park" is three words of
			// scaffolding for four words of result.
			. '<span class="arv-results__sr">'
			. esc_html( arv_results_division_label( $division ) ) . ': </span>'
			. '<span class="arv-results__winner-name">' . esc_html( $top[ $division ]['name'] ) . '</span> '
			. '<span class="arv-results__winner-time">' . esc_html( $top[ $division ]['time'] ) . '</span>'
			. '</span>';
	}

	return $out . '</p>';
}

/**
 * Every distance's winners, behind a disclosure.
 *
 * A real table, because that is what this is: a distance and a winner per
 * division, read across. Up to nine rows and three columns at Royal Gorge
 * Groove, which is exactly the amount of information that has to be closed
 * by default; seventy-four of those open at once is not an archive, it is a
 * results booklet.
 *
 * Not rendered when the headline already said everything. An event with one
 * scored distance would otherwise get a control that opens to repeat the
 * line directly above it.
 *
 * @param array $stats
 * @return string
 */
function arv_results_winners_table( $stats ) {
	$winners = isset( $stats['winners'] ) ? $stats['winners'] : array();

	if ( count( $winners ) < 2 && ! empty( $stats['headline'] ) ) {
		return '';
	}

	if ( empty( $winners ) ) {
		return '';
	}

	$divisions = arv_stats_divisions_present( $winners );

	$out = '<details class="arv-results__winners-all">';
	$out .= '<summary class="arv-results__older-toggle">'
		. '<svg class="arv-results__chevron" viewBox="0 0 16 16" width="11" height="11" aria-hidden="true" focusable="false">'
		. '<path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>'
		. esc_html( __( 'Winners', 'aravaipa-elements' ) )
		. '<span class="arv-results__older-years">'
		. esc_html(
			sprintf(
				// translators: %d is a count of distances.
				_n( '%d distance', '%d distances', count( $winners ), 'aravaipa-elements' ),
				count( $winners )
			)
		)
		. '</span></summary>';

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

	foreach ( $winners as $row ) {
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
				. esc_html( $row[ $division ]['time'] ) . '</span></td>';
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
	$out = '<div class="arv-results__links">';

	foreach ( array(
		array( $row['live'], __( 'Live Results', 'aravaipa-elements' ), 'live' ),
		array( $row['ultrasignup'], __( 'UltraSignup', 'aravaipa-elements' ), 'us' ),
		array( $row['ultrarunning'], __( 'UltraRunning', 'aravaipa-elements' ), 'ur' ),
	) as $link ) {
		list( $url, $label, $kind ) = $link;
		$url = trim( (string) $url );

		if ( '' === $url ) {
			// An empty slot, not nothing. There are three fixed columns here
			// even though the layout has no table: dropping a missing link
			// let the row shrink from the left, so a race with two listings
			// started its buttons 119px right of a race with three and the
			// whole page had a ragged edge running down it. The slot holds
			// the column and shows nothing.
			$out .= '<span class="arv-results__slot" aria-hidden="true"></span>';
			continue;
		}

		$out .= '<a class="arv-results__link arv-results__link--' . esc_attr( $kind ) . '" href="'
			. esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
	}

	$out .= '</div>';

	return $out;
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
