<?php
/**
 * Group Runs: the weekly runs in Phoenix and Colorado Springs.
 *
 * The page this replaces said Wednesdays, rotating locations, and named
 * four leaders off 2017 photos, none of which was current: both runs are
 * on Monday now, at fixed meeting points, and the roster of who leads them
 * has moved on. None of that was caught for years because nothing on the
 * page was checked against where the run actually happens, which is
 * Strava: both regions run their group event there, and that is the
 * closest thing to a maintained source this has.
 *
 * The schedule below is hand-entered from that source rather than synced
 * live, because Strava's group event details are only visible to a
 * logged-in member and there is no public feed to read: /group_events
 * redirects straight to a login wall, and the club page itself shows
 * "Upcoming Club Event, sign up to see these details" to a visitor who
 * is not one. A future version could read this through Strava's own API
 * once a club admin authorises it (see the athlete results sync for the
 * shape that would take), which would also pick up a one-off cancellation
 * a hand-entered weekday recurrence cannot know about. Until then this is
 * the honest middle ground: real days, times and places, verified
 * 2026-09-08 directly from each region's Strava app, computed forward
 * into an actual calendar instead of a paragraph saying "usually
 * Wednesdays."
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The two regions. Edited by hand when a schedule actually changes, the
 * same way arv_racing_team_sort_groups() holds its fixed division order:
 * this is a fact about the world, not data that belongs in a database
 * table nobody but this file's author would think to check.
 *
 * 'weekday' is ISO-8601 (1 = Monday), matching what date('N') returns, so
 * arv_group_runs_next_dates() can compare them directly.
 *
 * @return array<string, array>
 */
function arv_group_runs_regions() {
	return array(
		'arizona'  => array(
			'label'       => __( 'Arizona', 'aravaipa-elements' ),
			'city'        => __( 'Phoenix', 'aravaipa-elements' ),
			'weekday'     => 1, // Monday.
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
		'colorado' => array(
			'label'       => __( 'Colorado', 'aravaipa-elements' ),
			'city'        => __( 'Colorado Springs', 'aravaipa-elements' ),
			'weekday'     => 1, // Monday.
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

/**
 * The next several occurrences of a region's weekly run, computed forward
 * from today rather than stored anywhere.
 *
 * A fixed weekday recurrence is the one part of this that genuinely will
 * not go stale on its own: "every Monday" needs no maintenance the way a
 * list of dates would, right up until an actual week is skipped, which
 * this has no way to know about (see the file header).
 *
 * @param array $region One entry from arv_group_runs_regions().
 * @param int   $count  How many upcoming dates to return.
 * @return array<int, string> ISO dates (Y-m-d), soonest first.
 */
function arv_group_runs_next_dates( $region, $count = 6 ) {
	$today   = new DateTimeImmutable( 'today', wp_timezone() );
	$target  = (int) $region['weekday'];
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
	$regions = arv_group_runs_regions();

	$out  = '<div class="arv-grouprun" data-arv-grouprun-root>';
	$out .= arv_group_runs_filter_markup( $regions );
	$out .= '<div class="arv-grouprun__cards">';

	foreach ( $regions as $key => $region ) {
		$out .= arv_group_runs_card_markup( $key, $region );
	}

	$out .= '</div>';
	$out .= arv_group_runs_upcoming_markup( $regions );
	$out .= '</div>';

	foreach ( $regions as $key => $region ) {
		$out .= arv_group_runs_schema( $key, $region );
	}

	return $out;
}
add_shortcode( 'arv_group_runs', 'arv_group_runs_shortcode' );

/**
 * The region toggle. Same control as the Racing Team division buttons:
 * squared, flat, the active one filled. "All" plus one button per region,
 * filtering the cards above and the combined calendar below from one bar.
 *
 * @param array $regions
 * @return string
 */
function arv_group_runs_filter_markup( $regions ) {
	$out  = '<div class="arv-grouprun__filters" role="group" aria-label="' . esc_attr__( 'Filter by region', 'aravaipa-elements' ) . '">';
	$out .= '<button type="button" class="arv-grouprun__filter is-active" data-arv-grouprun-region="" aria-pressed="true">'
		. esc_html__( 'All', 'aravaipa-elements' ) . '</button>';

	foreach ( $regions as $key => $region ) {
		$out .= '<button type="button" class="arv-grouprun__filter" data-arv-grouprun-region="' . esc_attr( $key ) . '" aria-pressed="false">'
			. esc_html( $region['label'] ) . '</button>';
	}

	$out .= '</div>';

	return $out;
}

/**
 * One region's info card: what it is, where it meets, how to find the
 * club.
 *
 * @param string $key
 * @param array  $region
 * @return string
 */
function arv_group_runs_card_markup( $key, $region ) {
	$out  = '<article class="arv-grouprun__card" data-arv-grouprun-region="' . esc_attr( $key ) . '">';
	$out .= '<h2 class="arv-grouprun__card-title">' . esc_html( $region['label'] ) . '</h2>';
	$out .= '<p class="arv-grouprun__meta">'
		. esc_html( arv_group_runs_weekday_name( $region['weekday'] ) . 's, ' . $region['time'] . ' · ' . $region['city'] )
		. '</p>';

	$out .= '<p class="arv-grouprun__where">' . esc_html( $region['meet_name'] ) . '<br>'
		. '<span class="arv-grouprun__addr">' . esc_html( $region['meet_addr'] ) . '</span></p>';

	$out .= '<p class="arv-grouprun__desc">' . esc_html( $region['description'] ) . '</p>';

	if ( '' !== $region['social'] ) {
		$out .= '<p class="arv-grouprun__social">' . esc_html( $region['social'] );
		if ( '' !== $region['social_addr'] ) {
			$out .= '<br><span class="arv-grouprun__addr">' . esc_html( $region['social_addr'] ) . '</span>';
		}
		$out .= '</p>';
	}

	$out .= '<div class="arv-grouprun__links">';
	$out .= '<a class="arv-grouprun__link arv-grouprun__link--strava" href="' . esc_url( $region['strava'] ) . '" target="_blank" rel="noopener">'
		. esc_html__( 'Join on Strava', 'aravaipa-elements' ) . '</a>';

	if ( '' !== $region['facebook'] ) {
		$out .= '<a class="arv-grouprun__link arv-grouprun__link--facebook" href="' . esc_url( $region['facebook'] ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Facebook group', 'aravaipa-elements' ) . '</a>';
	}

	$out .= '</div></article>';

	return $out;
}

/**
 * The combined upcoming list: every region's next several dates,
 * interleaved and sorted chronologically, each row tagged with its region
 * so the filter bar above can narrow it. This is the "one calendar,
 * toggle a region on or off" Jamil asked for, built from a computed
 * recurrence rather than a fetched feed because that is the data that
 * actually exists right now (see the file header).
 *
 * @param array $regions
 * @return string
 */
function arv_group_runs_upcoming_markup( $regions ) {
	$rows = array();

	foreach ( $regions as $key => $region ) {
		foreach ( arv_group_runs_next_dates( $region, 6 ) as $iso ) {
			$rows[] = array(
				'region' => $key,
				'label'  => $region['label'],
				'iso'    => $iso,
				'time'   => $region['time'],
				'where'  => $region['meet_name'],
			);
		}
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			return strcmp( $a['iso'], $b['iso'] );
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
 * Event schema for a region's next occurrence.
 *
 * One event, not the six rows in the visible calendar: schema.org has no
 * clean way to say "this repeats every Monday indefinitely" that Google
 * reliably renders, and marking up every generated future date as its own
 * Event would read as a wall of near-duplicate structured data for what
 * is, factually, one recurring thing. The next date is enough to make the
 * run itself discoverable; it is replaced automatically as this week's
 * Monday passes; because it is a computed date rather than a fetched one,
 * it is never wrong about being valid, only about whether that week is
 * still actually happening (see the file header on that limit).
 *
 * @param string $key
 * @param array  $region
 * @return string
 */
function arv_group_runs_schema( $key, $region ) {
	$dates = arv_group_runs_next_dates( $region, 1 );
	$next  = $dates[0];

	// A group trail run is not a competition, so SportsEvent overstates
	// it; schema.org's own definition of that type is "a sports event."
	// This is a social run, plain Event covers it without the mismatch.
	$start = arv_group_runs_start_datetime( $next, $region['time'], $region['timezone'] );

	$schema = array(
		'@context'           => 'https://schema.org/',
		'@type'              => 'Event',
		'name'               => sprintf(
			/* translators: %s: region label, e.g. "Arizona" */
			__( 'Aravaipa Group Run - %s', 'aravaipa-elements' ),
			$region['label']
		),
		'description'        => wp_strip_all_tags( $region['description'] ),
		'startDate'           => $start,
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'isAccessibleForFree' => true,
		'location'            => array(
			'@type'   => 'Place',
			'name'    => $region['meet_name'],
			'address' => $region['meet_addr'],
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
 * Each region's time is entered in its own local clock (Phoenix does not
 * observe daylight saving, Colorado Springs does), so the offset has to
 * come from that region's timezone at that specific date, not a single
 * fixed value that would drift an hour off for one of the two regions
 * across a DST change.
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
