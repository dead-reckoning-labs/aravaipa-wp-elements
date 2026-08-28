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
					'description' => __( 'Leave blank for every year, newest first. Set to 2026 for that year alone.', 'aravaipa-elements' ),
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
	if ( '' !== $year && preg_match( '/^\d{4}$/', $year ) ) {
		$rows = array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $year ) {
					return substr( $row['iso'], 0, 4 ) === $year;
				}
			)
		);
	}

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
	$stripped = trim( preg_replace( '/\s+/', ' ', (string) $stripped ) );

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

		$races[] = array(
			'name'      => $race['name'],
			'iso'       => $race['iso'],
			'display'   => $race['display'],
			'image'     => $race['image'],
			'page'      => $race['page'],
			'distances' => $race['distances'],
			'url'       => $action['url'],
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
		$out .= '<span class="arv-results__week-name">';
		$out .= ( '' !== $page )
			? '<a class="arv-results__week-link" href="' . esc_url( $page ) . '">' . esc_html( $race['name'] ) . '</a>'
			: esc_html( $race['name'] );
		$out .= '</span>';

		$distances = arv_split_distances( $race['distances'] );
		if ( ! empty( $distances ) ) {
			$out .= '<span class="arv-results__week-distances">';
			foreach ( $distances as $distance ) {
				$out .= '<span class="arv-results__week-pill">' . esc_html( $distance ) . '</span>';
			}
			$out .= '</span>';
		}

		$out .= '<time class="arv-results__week-date" datetime="' . esc_attr( $race['iso'] ) . '">' . esc_html( $display ) . '</time>';
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
	$out = '<span class="arv-results__week-status">';

	$out .= '<span class="arv-results__countdown" data-arv-results-countdown="'
		. esc_attr( arv_results_start_iso( $race['iso'] ) ) . '"'
		. ( 'soon' === $race['state'] ? '' : ' hidden' ) . '>'
		. '<span class="arv-results__countdown-value" data-arv-results-countdown-value>'
		. esc_html( arv_results_countdown_text( $race['iso'] ) )
		. '</span></span>';

	$out .= '<span class="arv-results__live" data-arv-results-live'
		. ( 'live' === $race['state'] ? '' : ' hidden' ) . '>'
		. '<span class="arv-results__pulse" aria-hidden="true"></span>'
		. esc_html( __( 'Live now', 'aravaipa-elements' ) )
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
function arv_results_countdown_text( $iso ) {
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

	$target = strtotime( $iso . ' 00:00:00' );
	$left   = (int) $target - (int) $now;

	if ( $left <= 0 ) {
		return '';
	}

	$days = (int) floor( $left / DAY_IN_SECONDS );

	if ( $days >= 1 ) {
		/* translators: %d is a number of days. */
		return sprintf( _n( 'in %d day', 'in %d days', $days, 'aravaipa-elements' ), $days );
	}

	$hours = max( 1, (int) round( $left / 3600 ) );

	/* translators: %d is a number of hours. */
	return sprintf( _n( 'in %d hour', 'in %d hours', $hours, 'aravaipa-elements' ), $hours );
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

	foreach ( $races as $editions ) {
		// Newest edition first, and its name is the one to show: a race that
		// was renamed is called whatever it is called now.
		$latest = $editions[0];
		$older  = array_slice( $editions, 1 );

		$out .= '<div class="arv-results__race-group" data-arv-results-race="'
			. esc_attr( strtolower( $latest['name'] ) ) . '">';

		$out .= '<div class="arv-results__latest">';
		$out .= '<div class="arv-results__race-head">';
		$out .= '<h3 class="arv-results__race-name">' . esc_html( $latest['name'] ) . '</h3>';
		$out .= '<p class="arv-results__race-meta">' . esc_html( arv_results_edition_label( $latest ) );

		if ( ! empty( $latest['current'] ) ) {
			$out .= ' <span class="arv-results__flag">' . esc_html( __( 'Happening now', 'aravaipa-elements' ) ) . '</span>';
		}

		$out .= '</p>';
		$out .= '</div>';
		$out .= arv_results_links( $latest );
		$out .= '</div>';

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
				. '</summary>';

			foreach ( $older as $edition ) {
				$out .= '<div class="arv-results__edition">';
				$out .= '<p class="arv-results__edition-date">' . esc_html( arv_results_edition_label( $edition ) ) . '</p>';
				$out .= arv_results_links( $edition );
				$out .= '</div>';
			}

			$out .= '</details>';
		}

		$out .= '</div>';
	}

	$out .= '</div>';

	return $out;
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
