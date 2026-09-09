<?php
/**
 * Group Runs: the weekly runs in Phoenix and Colorado Springs.
 *
 * The page this replaces said Wednesdays, rotating locations, and named
 * four leaders off 2017 photos, none of which was current: the schedule
 * had grown to three separate weekly runs across two regions and nothing
 * on the page reflected any of it. That was never caught because nothing
 * on the page was checked against where the runs actually happen, which
 * is Strava: every one of them runs as a Strava group event, and that is
 * the closest thing to a maintained source this has.
 *
 * Two sources, because the two halves of this are maintained in two
 * different places.
 *
 * The recurrence (which weekday, what time, where a fixed run meets) is
 * hand-entered here, from each run's own Strava listing, verified
 * 2026-09-08. Strava itself cannot be read for it: group event details
 * are only visible to a logged-in member, /group_events redirects to a
 * login wall, and the club page shows "Upcoming Club Event, sign up to
 * see these details" to everyone else. A weekday and a time are also the
 * part that genuinely does not drift, so hand-entering them costs
 * nothing on an ongoing basis.
 *
 * The Wednesday run's rotating trailhead is the part that does change
 * every week, and that is read live from the Google Sheet it is planned
 * in, which is the same sheet each week's Strava event is built from.
 * See arv_group_runs_sheet().
 *
 * What neither source gives is a week being cancelled outright: the
 * recurrence will still compute it and the sheet will still list its
 * planned trailhead. Reading Strava through its own API, once a club
 * admin authorises it, is what would close that, in the shape the
 * athlete results sync already uses.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every weekly run, across both regions. A flat list rather than one run
 * per region, because Arizona alone has two (a fixed Monday trailhead and
 * a Wednesday run that moves), and a run belongs to the calendar and the
 * region filter the same way whichever bucket it is in.
 *
 * Edited by hand when a schedule actually changes, the same way
 * arv_racing_team_sort_groups() holds its fixed division order: this is a
 * fact about the world, not data that belongs in a database table nobody
 * but this file's author would think to check.
 *
 * 'weekday' is ISO-8601 (1 = Monday), matching what date('N') returns, so
 * arv_group_runs_next_dates() can compare them directly. 'meet_addr' is
 * blank on a run whose location genuinely rotates; nothing prints an
 * empty address, it prints the honest "location varies" note instead.
 *
 * @return array<string, array>
 */
function arv_group_runs_list() {
	return array(
		'arizona-monday'    => array(
			'region'      => 'arizona',
			'region_label' => __( 'Arizona', 'aravaipa-elements' ),
			'name'        => __( 'Monday Night', 'aravaipa-elements' ),
			'city'        => __( 'Phoenix', 'aravaipa-elements' ),
			'weekday'     => 1,
			'time'        => '6:30 PM',
			'timezone'    => 'America/Phoenix',
			'meet_name'   => 'Pima Canyon Trailhead',
			'meet_addr'   => '4806 E Pima Canyon Rd, Phoenix, AZ 85044',
			'description' => __( 'A free, all-levels group trail run out of South Mountain. Fun (3.5-4 miles, regular regroup stops), Middle (4.5-5 miles) and Fast (5-6 miles) pace groups run together and split up on the trail. Bring a water bottle and a headlamp or flashlight, it gets dark before the group is back.', 'aravaipa-elements' ),
			'social'      => __( 'Post-run social at Fate Brewing Company, Tempe', 'aravaipa-elements' ),
			'social_addr' => '201 E Southern Ave #111, Tempe, AZ 85282',
			'strava'      => 'https://www.strava.com/clubs/Aravaipa',
			'facebook'    => 'https://www.facebook.com/groups/aravaipagrouprun/',
		),
		'arizona-wednesday' => array(
			'region'      => 'arizona',
			'region_label' => __( 'Arizona', 'aravaipa-elements' ),
			'name'        => __( 'Wednesday Night', 'aravaipa-elements' ),
			'city'        => __( 'Phoenix', 'aravaipa-elements' ),
			'weekday'     => 3,
			'time'        => '6:30 PM',
			'timezone'    => 'America/Phoenix',
			// The trailhead rotates week to week rather than sitting at
			// one fixed spot the way Monday's does, so there is no
			// permanent address to print. Recent week met at Dreamy Draw
			// Recreation Area; that is this week's answer, not a fixed
			// fact worth hardcoding as though it always will be.
			'meet_name'   => '',
			'meet_addr'   => '',
			'description' => __( 'A one-hour trail run at a rotating Phoenix-area trailhead, with multiple pace groups so all levels are welcome. Meet at the trailhead with water and a light; the current week\'s location is posted on the Strava club and the Facebook group ahead of the run.', 'aravaipa-elements' ),
			'social'      => __( 'Social afterward at a nearby spot, announced with the week\'s location', 'aravaipa-elements' ),
			'social_addr' => '',
			'strava'      => 'https://www.strava.com/clubs/Aravaipa',
			'facebook'    => 'https://www.facebook.com/groups/aravaipagrouprun/',
		),
		'colorado-monday'   => array(
			'region'      => 'colorado',
			'region_label' => __( 'Colorado', 'aravaipa-elements' ),
			'name'        => __( 'Monday Night', 'aravaipa-elements' ),
			'city'        => __( 'Colorado Springs', 'aravaipa-elements' ),
			'weekday'     => 1,
			'time'        => '5:30 PM',
			'timezone'    => 'America/Denver',
			'meet_name'   => 'Fossil Craft Beer Company',
			'meet_addr'   => '2845 Ore Mill Rd #1, Colorado Springs, CO 80904',
			'description' => __( 'Check in from 5:00 PM, the run rolls out at 5:30 toward Red Rock Canyon Open Space. Head out solo or with a distance group, whatever pace works. Run in partnership with Fossil Craft Beer Company, who run periodic discounted beer deals, merch giveaways and race entries for regulars.', 'aravaipa-elements' ),
			'social'      => __( 'Runs start and finish at Fossil Craft Beer Company', 'aravaipa-elements' ),
			'social_addr' => '',
			'strava'      => 'https://www.strava.com/clubs/aravaipacolorado',
			'facebook'    => '',
		),
	);
}

const ARV_GROUP_RUNS_SHEET_OPTION = 'arv_group_runs_sheet_url';

/**
 * The Wednesday run's week-by-week schedule, read from the Google Sheet
 * that already drives it.
 *
 * The rotating trailhead is not improvised: it is planned months ahead in
 * a spreadsheet, with the social venue and any time exception alongside
 * it, and that sheet is what the Strava event for a given week is built
 * from. Verified by matching a row against its own Strava listing: the
 * sheet's 9 September 2026 row says Dreamy Draw Park with the social at
 * Linger Longer Lounge, and so does the event.
 *
 * Reading it turns "location varies, check Strava" into the actual
 * trailhead for each upcoming week, which is the entire difference
 * between a page that tells you to go look somewhere else and a page
 * that answers the question.
 *
 * Reads the sheet's /export?format=csv URL, which serves the first tab
 * only. That matters here for a reason beyond convenience: other tabs in
 * the same document carry 44 volunteers' personal email addresses, and
 * the export returns none of them. Checked rather than assumed, against
 * the real published URL: 15KB and zero email addresses, against 103KB
 * for the document as a whole.
 *
 * Sharing has to be set so that anyone with the link can view, which is
 * what makes the export readable without credentials. A sheet that is
 * not shared that way answers 401 and this falls back, rather than
 * breaking the page.
 *
 * Returns an empty array when no URL is configured or the fetch fails,
 * and every caller falls back to the honest "location varies" wording, so
 * this is an upgrade to the page rather than a dependency of it.
 *
 * @return array<string, array> ISO date => row data.
 */
function arv_group_runs_sheet() {
	$url = trim( (string) get_option( ARV_GROUP_RUNS_SHEET_OPTION, '' ) );

	if ( '' === $url ) {
		return array();
	}

	$key    = 'arv_group_runs_sheet';
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		// An hour rather than six: a blip should not pin an empty
		// schedule in place for the rest of the day when the page has a
		// perfectly good fallback to fall back to in the meantime.
		set_transient( $key, array(), HOUR_IN_SECONDS );
		return array();
	}

	$rows = arv_group_runs_parse_sheet( wp_remote_retrieve_body( $response ) );

	set_transient( $key, $rows, 6 * HOUR_IN_SECONDS );

	return $rows;
}

/**
 * Parse the published CSV into ISO date => row.
 *
 * Columns are located by their header text rather than by position: the
 * sheet has a spare leading column and several trailing ones, and a
 * column inserted by whoever maintains it should not silently shift the
 * social venue into the start time.
 *
 * @param string $csv
 * @return array<string, array>
 */
function arv_group_runs_parse_sheet( $csv ) {
	$lines = preg_split( '/\R/', trim( (string) $csv ) );

	if ( empty( $lines ) ) {
		return array();
	}

	$cols = array();
	$rows = array();

	foreach ( $lines as $line ) {
		$cells = str_getcsv( $line );

		if ( empty( $cells ) ) {
			continue;
		}

		// The header row is whichever one names the columns; everything
		// above it is title/spacer rows.
		if ( empty( $cols ) ) {
			foreach ( $cells as $i => $cell ) {
				$name = strtolower( trim( $cell ) );

				if ( '' !== $name ) {
					$cols[ $name ] = $i;
				}
			}

			if ( ! isset( $cols['run location'] ) ) {
				$cols = array();
			}

			continue;
		}

		$date = isset( $cells[0] ) ? trim( $cells[0] ) : '';
		$time = arv_group_runs_cell( $cells, $cols, 'start' );

		// A month name ("January") or a blank spacer sits in the same
		// column as the dates, so anything that is not a real date is
		// not a row.
		$stamp = ( '' !== $date ) ? strtotime( $date ) : false;

		if ( ! $stamp || ! preg_match( '~^\d{1,2}/\d{1,2}/\d{4}$~', $date ) ) {
			continue;
		}

		$rows[ gmdate( 'Y-m-d', $stamp ) ] = array(
			'location' => arv_group_runs_cell( $cells, $cols, 'run location' ),
			'social'   => arv_group_runs_cell( $cells, $cols, 'social location' ),
			'race'     => arv_group_runs_cell( $cells, $cols, 'upcoming race' ),
			'raffle'   => ( 'yes' === strtolower( arv_group_runs_cell( $cells, $cols, 'raffle?' ) ) ),
			// Asterisks are how the sheet flags a week that does not
			// start at the usual time, e.g. "***7:00:00 PM" for the
			// Javelina Experience run.
			'time'     => arv_group_runs_clean_time( $time ),
		);
	}

	return $rows;
}

/**
 * One cell by header name, or ''.
 *
 * @param array  $cells
 * @param array  $cols  header name => index.
 * @param string $name
 * @return string
 */
function arv_group_runs_cell( $cells, $cols, $name ) {
	if ( ! isset( $cols[ $name ] ) || ! isset( $cells[ $cols[ $name ] ] ) ) {
		return '';
	}

	return trim( $cells[ $cols[ $name ] ] );
}

/**
 * "***7:00:00 PM" to "7:00 PM", "6:30 PM" unchanged, anything
 * unparseable to ''.
 *
 * @param string $raw
 * @return string
 */
function arv_group_runs_clean_time( $raw ) {
	$raw = trim( str_replace( '*', '', (string) $raw ) );

	if ( '' === $raw ) {
		return '';
	}

	$stamp = strtotime( $raw );

	return $stamp ? gmdate( 'g:i A', $stamp ) : '';
}

/**
 * The regions a run's 'region' key can resolve to, for the filter bar and
 * for grouping the region cards. Derived from the run list rather than
 * held as its own fixed array, so a region only ever appears in the
 * filter once a run actually exists for it.
 *
 * @param array $runs From arv_group_runs_list().
 * @return array<string, string> region key => label, in first-seen order.
 */
function arv_group_runs_regions_in( $runs ) {
	$regions = array();

	foreach ( $runs as $run ) {
		if ( ! isset( $regions[ $run['region'] ] ) ) {
			$regions[ $run['region'] ] = $run['region_label'];
		}
	}

	return $regions;
}

/**
 * The next several occurrences of one run, computed forward from today
 * rather than stored anywhere.
 *
 * A fixed weekday recurrence is the one part of this that genuinely will
 * not go stale on its own: "every Monday" needs no maintenance the way a
 * list of dates would, right up until an actual week is skipped, which
 * this has no way to know about (see the file header).
 *
 * @param array $run   One entry from arv_group_runs_list().
 * @param int   $count How many upcoming dates to return.
 * @return array<int, string> ISO dates (Y-m-d), soonest first.
 */
function arv_group_runs_next_dates( $run, $count = 6 ) {
	$today   = new DateTimeImmutable( 'today', wp_timezone() );
	$target  = (int) $run['weekday'];
	$current = (int) $today->format( 'N' );

	$offset = ( $target - $current + 7 ) % 7;
	$first  = $today->modify( "+{$offset} days" );

	$dates = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$dates[] = $first->modify( '+' . ( $i * 7 ) . ' days' )->format( 'Y-m-d' );
	}

	return $dates;
}

/**
 * [arv_group_runs]
 *
 * @param array $atts
 * @return string
 */
function arv_group_runs_shortcode( $atts ) {
	$runs    = arv_group_runs_list();
	$regions = arv_group_runs_regions_in( $runs );

	$out  = '<div class="arv-grouprun" data-arv-grouprun-root>';
	$out .= arv_group_runs_filter_markup( $regions );
	$out .= '<div class="arv-grouprun__cards">';

	foreach ( $runs as $key => $run ) {
		$out .= arv_group_runs_card_markup( $key, $run );
	}

	$out .= '</div>';
	$out .= arv_group_runs_upcoming_markup( $runs );
	$out .= '</div>';

	foreach ( $runs as $key => $run ) {
		$out .= arv_group_runs_schema( $key, $run );
	}

	return $out;
}
add_shortcode( 'arv_group_runs', 'arv_group_runs_shortcode' );

/**
 * The region toggle. Same control as the Racing Team division buttons:
 * squared, flat, the active one filled. "All" plus one button per region,
 * filtering the cards above and the combined calendar below from one bar.
 * Filters by region, not by individual run, since Arizona's two runs are
 * one region a visitor either wants to see or does not.
 *
 * @param array $regions region key => label.
 * @return string
 */
function arv_group_runs_filter_markup( $regions ) {
	$out  = '<div class="arv-grouprun__filters" role="group" aria-label="' . esc_attr__( 'Filter by region', 'aravaipa-elements' ) . '">';
	$out .= '<button type="button" class="arv-grouprun__filter is-active" data-arv-grouprun-region="" aria-pressed="true">'
		. esc_html__( 'All', 'aravaipa-elements' ) . '</button>';

	foreach ( $regions as $key => $label ) {
		$out .= '<button type="button" class="arv-grouprun__filter" data-arv-grouprun-region="' . esc_attr( $key ) . '" aria-pressed="false">'
			. esc_html( $label ) . '</button>';
	}

	$out .= '</div>';

	return $out;
}

/**
 * One run's info card: what it is, where it meets, how to find the club.
 *
 * @param string $key
 * @param array  $run
 * @return string
 */
function arv_group_runs_card_markup( $key, $run ) {
	$out  = '<article class="arv-grouprun__card" data-arv-grouprun-region="' . esc_attr( $run['region'] ) . '">';
	$out .= '<p class="arv-grouprun__card-eyebrow">' . esc_html( $run['region_label'] ) . '</p>';
	$out .= '<h2 class="arv-grouprun__card-title">' . esc_html( $run['name'] ) . '</h2>';
	$out .= '<p class="arv-grouprun__meta">'
		. esc_html( arv_group_runs_weekday_name( $run['weekday'] ) . 's, ' . $run['time'] . ' · ' . $run['city'] )
		. '</p>';

	if ( '' !== $run['meet_addr'] ) {
		$out .= '<p class="arv-grouprun__where">' . esc_html( $run['meet_name'] ) . '<br>'
			. '<span class="arv-grouprun__addr">' . esc_html( $run['meet_addr'] ) . '</span></p>';
	} else {
		$out .= '<p class="arv-grouprun__where arv-grouprun__where--varies">'
			. esc_html__( 'Location varies week to week, posted on Strava and Facebook beforehand.', 'aravaipa-elements' ) . '</p>';
	}

	$out .= '<p class="arv-grouprun__desc">' . esc_html( $run['description'] ) . '</p>';

	if ( '' !== $run['social'] ) {
		$out .= '<p class="arv-grouprun__social">' . esc_html( $run['social'] );
		if ( '' !== $run['social_addr'] ) {
			$out .= '<br><span class="arv-grouprun__addr">' . esc_html( $run['social_addr'] ) . '</span>';
		}
		$out .= '</p>';
	}

	$out .= '<div class="arv-grouprun__links">';
	$out .= '<a class="arv-grouprun__link arv-grouprun__link--strava" href="' . esc_url( $run['strava'] ) . '" target="_blank" rel="noopener">'
		. esc_html__( 'Join on Strava', 'aravaipa-elements' ) . '</a>';

	if ( '' !== $run['facebook'] ) {
		$out .= '<a class="arv-grouprun__link arv-grouprun__link--facebook" href="' . esc_url( $run['facebook'] ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Facebook group', 'aravaipa-elements' ) . '</a>';
	}

	$out .= '</div></article>';

	return $out;
}

/**
 * The combined upcoming list: every run's next several dates, interleaved
 * and sorted chronologically, each row tagged with its region so the
 * filter bar above can narrow it. This is the "one calendar, toggle a
 * region on or off" Jamil asked for, built from a computed recurrence
 * rather than a fetched feed because that is the data that actually
 * exists right now (see the file header).
 *
 * @param array $runs
 * @return string
 */
function arv_group_runs_upcoming_markup( $runs ) {
	$rows  = array();
	$sheet = arv_group_runs_sheet();

	foreach ( $runs as $run ) {
		foreach ( arv_group_runs_next_dates( $run, 6 ) as $iso ) {
			$where  = ( '' !== $run['meet_name'] ) ? $run['meet_name'] : __( 'Location varies', 'aravaipa-elements' );
			$time   = $run['time'];
			$social = '';

			// A run with no fixed meeting point takes that week's real
			// one from the schedule sheet when it is published, and keeps
			// saying "location varies" when it is not. A row the sheet
			// itself has not filled in yet says TBD, which is the true
			// answer and worth showing as-is rather than papering over.
			if ( '' === $run['meet_name'] && isset( $sheet[ $iso ] ) ) {
				$planned = $sheet[ $iso ];

				if ( '' !== $planned['location'] ) {
					$where = $planned['location'];
				}

				if ( '' !== $planned['time'] ) {
					$time = $planned['time'];
				}

				$social = $planned['social'];
			}

			$rows[] = array(
				'region' => $run['region'],
				'label'  => $run['region_label'] . ' · ' . $run['name'],
				'iso'    => $iso,
				'time'   => $time,
				'where'  => $where,
				'social' => $social,
			);
		}
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			$cmp = strcmp( $a['iso'], $b['iso'] );
			return ( 0 !== $cmp ) ? $cmp : strcmp( $a['label'], $b['label'] );
		}
	);

	$out  = '<div class="arv-grouprun__upcoming">';
	$out .= '<h2>' . esc_html__( 'Upcoming Runs', 'aravaipa-elements' ) . '</h2>';
	$out .= '<p class="arv-grouprun__count" data-arv-grouprun-count aria-live="polite"></p>';
	$out .= '<ul class="arv-grouprun__upcoming-list">';

	foreach ( $rows as $row ) {
		$out .= '<li class="arv-grouprun__upcoming-row" data-arv-grouprun-region="' . esc_attr( $row['region'] ) . '">';
		$out .= '<span class="arv-grouprun__upcoming-date">' . esc_html( date_i18n( 'D, M j', strtotime( $row['iso'] ) ) ) . '</span>';
		$out .= '<span class="arv-grouprun__upcoming-region">' . esc_html( $row['label'] ) . '</span>';
		$out .= '<span class="arv-grouprun__upcoming-time">' . esc_html( $row['time'] ) . '</span>';
		$out .= '<span class="arv-grouprun__upcoming-where">' . esc_html( $row['where'] ) . '</span>';

		if ( ! empty( $row['social'] ) ) {
			$out .= '<span class="arv-grouprun__upcoming-social">'
				. esc_html( sprintf( /* translators: %s: venue name */ __( 'after: %s', 'aravaipa-elements' ), $row['social'] ) )
				. '</span>';
		}

		$out .= '</li>';
	}

	$out .= '</ul></div>';

	return $out;
}

/**
 * "Monday" from an ISO weekday number, localised.
 *
 * @param int $n 1-7, Monday first.
 * @return string
 */
function arv_group_runs_weekday_name( $n ) {
	// date_i18n() wants a real date, not a bare weekday, so this borrows
	// the nearest one rather than hand-maintaining a translated name list.
	$today   = new DateTimeImmutable( 'today', wp_timezone() );
	$current = (int) $today->format( 'N' );
	$offset  = ( (int) $n - $current + 7 ) % 7;

	return date_i18n( 'l', strtotime( $today->modify( "+{$offset} days" )->format( 'Y-m-d' ) ) );
}

/**
 * Event schema for one run's next occurrence.
 *
 * One event per run, not the six rows each contributes to the visible
 * calendar: schema.org has no clean way to say "this repeats every Monday
 * indefinitely" that Google reliably renders, and marking up every
 * generated future date as its own Event would read as a wall of
 * near-duplicate structured data for what is, factually, one recurring
 * thing. The next date is enough to make the run itself discoverable; it
 * is replaced automatically as this week's date passes.
 *
 * A run with no fixed address (the rotating Wednesday one) omits
 * jobLocation's street address rather than inventing one; schema.org
 * allows a Place with just a name, and "location varies" is not
 * something an address field should ever have to hold.
 *
 * @param string $key
 * @param array  $run
 * @return string
 */
function arv_group_runs_schema( $key, $run ) {
	$dates = arv_group_runs_next_dates( $run, 1 );
	$next  = $dates[0];

	// A group trail run is not a competition, so SportsEvent overstates
	// it; schema.org's own definition of that type is "a sports event."
	// This is a social run, plain Event covers it without the mismatch.
	$start = arv_group_runs_start_datetime( $next, $run['time'], $run['timezone'] );

	$schema = array(
		'@context'            => 'https://schema.org/',
		'@type'               => 'Event',
		'name'                => sprintf(
			/* translators: 1: region label, e.g. "Arizona". 2: run name, e.g. "Monday Night" */
			__( 'Aravaipa Group Run - %1$s (%2$s)', 'aravaipa-elements' ),
			$run['region_label'],
			$run['name']
		),
		'description'         => wp_strip_all_tags( $run['description'] ),
		'startDate'           => $start,
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'isAccessibleForFree' => true,
		'location'            => array_filter(
			array(
				'@type'   => 'Place',
				'name'    => ( '' !== $run['meet_name'] ) ? $run['meet_name'] : $run['city'],
				'address' => $run['meet_addr'],
			)
		),
		'organizer'           => array(
			'@type' => 'Organization',
			'name'  => 'Aravaipa Running',
			'url'   => 'https://www.aravaiparunning.com/',
		),
	);

	return '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
}

/**
 * An ISO-8601 datetime with the region's own UTC offset, from a plain
 * date and a "6:30 PM" string.
 *
 * Each run's time is entered in its own local clock (Phoenix does not
 * observe daylight saving, Colorado Springs does), so the offset has to
 * come from that region's timezone at that specific date, not a single
 * fixed value that would drift an hour off across a DST change.
 *
 * @param string $iso      Y-m-d.
 * @param string $time12h  e.g. "6:30 PM".
 * @param string $tz       PHP timezone name, e.g. "America/Phoenix".
 * @return string
 */
function arv_group_runs_start_datetime( $iso, $time12h, $tz ) {
	$dt = DateTimeImmutable::createFromFormat(
		'Y-m-d g:i A',
		$iso . ' ' . $time12h,
		new DateTimeZone( $tz )
	);

	return $dt ? $dt->format( 'c' ) : $iso . 'T00:00:00';
}

/**
 * Where the schedule sheet's published CSV URL is set.
 *
 * An option rather than a constant in this file, because the URL is a
 * thing Jamil generates in Google Sheets and pastes in, not a fact about
 * the code, and because regenerating it (republishing, or moving the
 * schedule to a new sheet) should not need a plugin release.
 */
function arv_group_runs_admin_menu() {
	add_management_page(
		__( 'Group Runs Schedule', 'aravaipa-elements' ),
		__( 'Group Runs Schedule', 'aravaipa-elements' ),
		'manage_options',
		'arv-group-runs',
		'arv_group_runs_admin_screen'
	);
}
add_action( 'admin_menu', 'arv_group_runs_admin_menu' );

function arv_group_runs_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['arv_group_runs_url'] ) && check_admin_referer( 'arv_group_runs_save' ) ) {
		update_option( ARV_GROUP_RUNS_SHEET_OPTION, esc_url_raw( wp_unslash( $_POST['arv_group_runs_url'] ) ) );
		delete_transient( 'arv_group_runs_sheet' );
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html__( 'Saved and re-read.', 'aravaipa-elements' ) );
	}

	$url    = (string) get_option( ARV_GROUP_RUNS_SHEET_OPTION, '' );
	$parsed = arv_group_runs_sheet();

	echo '<div class="wrap"><h1>' . esc_html__( 'Group Runs Schedule', 'aravaipa-elements' ) . '</h1>';
	echo '<p>' . esc_html__( 'The Wednesday run rotates trailheads on a schedule kept in Google Sheets. Publish that one sheet (File, Share, Publish to web, pick the schedule tab, CSV) and paste the URL here, and the page shows each week\'s real trailhead instead of "location varies".', 'aravaipa-elements' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'The export URL serves the first tab only, which is the schedule. That is deliberate: other tabs in that document hold volunteers\' personal email addresses, and the export returns none of them. Point this at a document whose first tab is not the schedule and it will read the wrong thing.', 'aravaipa-elements' ) . '</strong></p>';

	echo '<form method="post"><table class="form-table"><tr><th scope="row"><label for="arv_group_runs_url">'
		. esc_html__( 'Published CSV URL', 'aravaipa-elements' ) . '</label></th><td>';
	echo '<input type="url" class="regular-text code" id="arv_group_runs_url" name="arv_group_runs_url" value="'
		. esc_attr( $url ) . '" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?gid=0&amp;single=true&amp;output=csv" />';
	echo '</td></tr></table>';
	wp_nonce_field( 'arv_group_runs_save' );
	submit_button( __( 'Save', 'aravaipa-elements' ) );
	echo '</form>';

	if ( '' === $url ) {
		echo '<p>' . esc_html__( 'Not configured. The page currently says "location varies" for the Wednesday run, which is accurate but less useful.', 'aravaipa-elements' ) . '</p></div>';
		return;
	}

	printf( '<p>%s</p>', esc_html( sprintf( '%d dated rows read from the sheet.', count( $parsed ) ) ) );

	$today = current_time( 'Y-m-d' );

	echo '<table class="widefat"><thead><tr><th>Date</th><th>Trailhead</th><th>Social</th><th>Start</th></tr></thead><tbody>';

	$shown = 0;

	foreach ( $parsed as $iso => $row ) {
		if ( $iso < $today || $shown >= 12 ) {
			continue;
		}

		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_html( $iso ),
			esc_html( '' !== $row['location'] ? $row['location'] : '—' ),
			esc_html( '' !== $row['social'] ? $row['social'] : '—' ),
			esc_html( '' !== $row['time'] ? $row['time'] : '—' )
		);

		$shown++;
	}

	echo '</tbody></table></div>';
}
