<?php
/**
 * Season Calendar.
 *
 * /races/ used one table for two different jobs: "what can I enter right
 * now" and "what is coming up." Those need different treatment. Aravaipa
 * Upcoming Races (with "Only show confirmed races" left on) is the first
 * job. This element is the second.
 *
 * The problem it replaces was concrete, not stylistic: 46 of 72 races on the
 * live page were already-run events still showing a live "Register" button
 * months after they finished, because UltraSignup's listing for a recurring
 * race persists until someone rolls it to the next running. This element
 * never claims a race is open. It only ever offers Race Details.
 *
 * Everything here looks forward, never back. A race that has run flips to
 * its next expected running after a short grace period, rather than sitting
 * in the past or vanishing: an annual race is still a real thing people plan
 * around a year out. Where the next date is genuinely known it is shown;
 * where it is not, the month is shown with the date marked TBD rather than
 * inventing a day. Anything with no expected date at all belongs in the
 * hiatus list at the bottom, which is hand-maintained because nothing can
 * detect "we are not putting this on next year" from the outside.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-season-calendar',
	array(
		'title'   => __( 'Aravaipa Season Calendar', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'eyebrow' => cs_value( 'The full season', 'markup' ),
				'heading' => cs_value( 'Every Aravaipa race', 'markup' ),
				'intro'   => cs_value( 'Grouped by month. Registration for most races opens a few months out; check Race Details for the latest.', 'markup' ),
				'theme'   => cs_value( 'light', 'style' ),
				'grace'   => cs_value( '2', 'markup' ),
				'hiatus_heading' => cs_value( 'On hiatus', 'markup' ),
				'hiatus_intro'   => cs_value( 'Not on the calendar right now. Watch this space.', 'markup' ),
				'hiatus_rows'    => cs_value( '', 'markup' ),
				// Reuses the exact row format Upcoming Races uses, so one
				// paste works for both and scripts/fetch-races.mjs never
				// needs to know two shapes exist.
				'rows'    => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_season_calendar_builder',
		'render'  => 'arv_season_calendar_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_season_calendar_builder() {
	return cs_compose_controls(
		array(
			'control_nav' => array(
				'calendar' => __( 'Calendar', 'aravaipa-elements' ),
			),
			'controls'    => array(
				array(
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
					'group' => 'calendar',
				),
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
					'group' => 'calendar',
				),
				array(
					'key'   => 'intro',
					'type'  => 'text',
					'label' => __( 'Intro line', 'aravaipa-elements' ),
					'group' => 'calendar',
				),
				array(
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
					'group'   => 'calendar',
					'options' => array(
						'choices' => array(
							array(
								'value' => 'light',
								'label' => __( 'Light', 'aravaipa-elements' ),
							),
							array(
								'value' => 'dark',
								'label' => __( 'Dark panel', 'aravaipa-elements' ),
							),
						),
					),
				),
				array(
					'key'         => 'grace',
					'type'        => 'text',
					'label'       => __( 'Days a finished race stays before flipping forward', 'aravaipa-elements' ),
					'description' => __( 'A race that has just run keeps its place in the list for this many days, then rolls to its next expected running at the far end. 0 flips it the morning after.', 'aravaipa-elements' ),
					'group'       => 'calendar',
				),
				array(
					'key'         => 'hiatus_heading',
					'type'        => 'text',
					'label'       => __( 'Hiatus section heading', 'aravaipa-elements' ),
					'group'       => 'calendar',
				),
				array(
					'key'         => 'hiatus_intro',
					'type'        => 'text',
					'label'       => __( 'Hiatus section intro', 'aravaipa-elements' ),
					'group'       => 'calendar',
				),
				array(
					'key'         => 'hiatus_rows',
					'type'        => 'textarea',
					'label'       => __( 'On hiatus', 'aravaipa-elements' ),
					'description' => __( 'Races with no planned date at all, one per line: Name | race page URL (optional) | note (optional). Hand-maintained on purpose: nothing outside can tell the difference between "next year is not scheduled yet" and "we are not running this again", so that call has to be made here.', 'aravaipa-elements' ),
					'group'       => 'calendar',
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Races', 'aravaipa-elements' ),
					'description' => __( 'Same format as Aravaipa Upcoming Races: paste the same rows here. Every race is shown regardless of date or whether registration is confirmed open; this element never links to registration directly, only to the race\'s own page.', 'aravaipa-elements' ),
					'group'       => 'calendar',
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * How many days from today until this race next comes round.
 *
 * The sort key for the whole element, and the reason it reads as "what is
 * coming up" rather than "January to December". Everything is measured
 * forward from today: a race in three weeks sorts near the top, a race that
 * ran last month sorts near the bottom because its next running is eleven
 * months away, and the list rolls over on its own as the year turns without
 * anyone editing anything.
 *
 * Month and day come off the row; the year on the row is deliberately
 * ignored. Roughly 84% of this calendar carries a generator-guessed year
 * (see the confirmed flag), and a guess must never be allowed to decide
 * ordering. The month and day are real either way, taken from the site's own
 * listing rather than from UltraSignup.
 *
 * @param string $iso   Y-m-d, whose year is not trusted.
 * @param string $today Y-m-d in site time.
 * @param int    $grace Days a just-finished race keeps its place before
 *                      flipping to next year.
 * @return int Days until the next occurrence, 0 or more.
 */
function arv_season_calendar_days_until( $iso, $today, $grace = 2 ) {
	$month = (int) substr( $iso, 5, 2 );
	$day   = (int) substr( $iso, 8, 2 );
	$now   = strtotime( $today . ' 00:00:00 UTC' );
	$year  = (int) gmdate( 'Y', $now );

	// Feb 29 in a non-leap year is not a date. Nudging to the 28th keeps the
	// race in roughly the right place instead of letting mktime silently roll
	// it into March.
	if ( 2 === $month && $day > 28 && ! checkdate( 2, $day, $year ) ) {
		$day = 28;
	}

	$this_year = gmmktime( 0, 0, 0, $month, $day, $year );
	$diff      = (int) floor( ( $this_year - $now ) / DAY_IN_SECONDS );

	// Still ahead, or recent enough to still be worth showing where it was.
	if ( $diff >= -$grace ) {
		return max( 0, $diff );
	}

	$next = gmmktime( 0, 0, 0, $month, $day, $year + 1 );

	return (int) floor( ( $next - $now ) / DAY_IN_SECONDS );
}

/**
 * The month heading a race belongs under, and the year that goes with it.
 *
 * Derived from the sort position rather than the row's own year, so a race
 * that has already run this year lands under next year's heading without the
 * row needing to know that. This is what makes "flips forward" true in the
 * output and not just in the ordering.
 *
 * @param string $iso
 * @param string $today
 * @param int    $grace
 * @return string e.g. "September 2026"
 */
function arv_season_calendar_bucket( $iso, $today, $grace = 2 ) {
	$days = arv_season_calendar_days_until( $iso, $today, $grace );
	$when = strtotime( $today . ' 00:00:00 UTC' ) + ( $days * DAY_IN_SECONDS );

	return gmdate( 'F Y', $when );
}

/**
 * One race, as a single line.
 *
 * The date shown depends on whether it is actually known. A race whose date
 * came straight off Aravaipa's own listing gets its real day, whether or not
 * registration happens to be open yet. A race whose year was rolled forward
 * by the generator, because its listed date has already passed, gets "TBD"
 * instead: the month is still real, but the day belongs to a running nobody
 * has scheduled, and printing it would state a date no one committed to.
 *
 * Keyed on `guessed`, not `confirmed`. Conflating them hid a real published
 * date (The Bear Chase, October 3-4) behind a TBD purely because its
 * UltraSignup listing had not rolled over yet.
 *
 * @param array $race Parsed row from arv_upcoming_races_parse_row().
 * @return string
 */
function arv_season_calendar_row( $race ) {
	$href = '' !== $race['page'] ? $race['page'] : $race['register'];

	$out = '<a class="arv-calendar__row" href="' . esc_url( $href ) . '">';

	if ( '' !== $race['image'] ) {
		$out .= '<span class="arv-calendar__logo"><img src="' . esc_url( $race['image'] ) . '" alt="" loading="lazy" decoding="async" /></span>';
	}

	// Keyed on whether the date itself is real, not on whether registration
	// is open. Those are different questions and only the first one decides
	// whether a day can be printed.
	if ( ! $race['guessed'] ) {
		$day = gmdate( 'j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );
		// A multi-day race states its own span ("September 12-13"); the day
		// part of that is what belongs next to a month heading.
		if ( '' !== $race['display'] && preg_match( '/\d+\s*[-\x{2013}]\s*\d+/u', $race['display'] ) ) {
			$day = preg_replace( '/^[A-Za-z]+\s+/', '', $race['display'] );
		}
		$out .= '<span class="arv-calendar__day">' . esc_html( $day ) . '</span>';
	} else {
		$out .= '<span class="arv-calendar__day arv-calendar__day--tbd">' . esc_html( __( 'TBD', 'aravaipa-elements' ) ) . '</span>';
	}

	$out .= '<span class="arv-calendar__body">';
	$out .= '<span class="arv-calendar__name">' . esc_html( $race['name'] ) . '</span>';
	if ( '' !== $race['distances'] ) {
		$out .= '<span class="arv-calendar__distances">' . esc_html( $race['distances'] ) . '</span>';
	}
	$where = array_filter( array( $race['venue'], $race['location'] ) );
	if ( ! empty( $where ) ) {
		$out .= '<span class="arv-calendar__where">' . esc_html( implode( ', ', $where ) ) . '</span>';
	}
	$out .= '</span>';

	$out .= '<span class="arv-calendar__arrow" aria-hidden="true">&rarr;</span>';
	$out .= '</a>';

	return $out;
}

/**
 * The hand-maintained hiatus list.
 *
 * Deliberately not derived from anything. "No date on UltraSignup yet" and
 * "we are not running this again" look identical from outside, and only one
 * of them should be told to a runner as a hiatus, so the call is made here
 * by a person rather than guessed at by the generator.
 *
 * @param string $raw One per line: Name | URL (optional) | note (optional).
 * @return string
 */
function arv_season_calendar_hiatus( $raw ) {
	$rows = arv_parse_rows( $raw, 1 );

	if ( empty( $rows ) ) {
		return '';
	}

	$out = '';
	foreach ( $rows as $row ) {
		$name = trim( arv_cell( $row, 0 ) );
		if ( '' === $name ) {
			continue;
		}

		$url  = trim( arv_cell( $row, 1 ) );
		$note = trim( arv_cell( $row, 2 ) );

		$inner  = '<span class="arv-calendar__name">' . esc_html( $name ) . '</span>';
		$inner .= '' !== $note ? '<span class="arv-calendar__where">' . esc_html( $note ) . '</span>' : '';

		// A hiatus race with no page left to point at is still worth listing,
		// just not as a link to nowhere.
		$out .= '' !== $url
			? '<a class="arv-calendar__row arv-calendar__row--hiatus" href="' . esc_url( $url ) . '"><span class="arv-calendar__body">' . $inner . '</span><span class="arv-calendar__arrow" aria-hidden="true">&rarr;</span></a>'
			: '<div class="arv-calendar__row arv-calendar__row--hiatus"><span class="arv-calendar__body">' . $inner . '</span></div>';
	}

	return $out;
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_season_calendar_render( $data ) {
	$rows = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 2 );

	$races = array();
	foreach ( $rows as $row ) {
		$race = arv_upcoming_races_parse_row( $row );
		if ( null !== $race ) {
			$races[] = $race;
		}
	}

	// No dated races is not necessarily nothing to show: a page could be
	// down to its hiatus list alone, and silently rendering empty would hide
	// it.
	if ( empty( $races ) && '' === arv_season_calendar_hiatus( isset( $data['hiatus_rows'] ) ? $data['hiatus_rows'] : '' ) ) {
		return '';
	}

	$today = arv_upcoming_races_today();
	$grace = isset( $data['grace'] ) ? (int) $data['grace'] : 2;
	$grace = max( 0, min( 60, $grace ) );

	// Everything measured forward from today. See
	// arv_season_calendar_days_until() for why the row's own year is ignored.
	usort(
		$races,
		function ( $a, $b ) use ( $today, $grace ) {
			$da = arv_season_calendar_days_until( $a['iso'], $today, $grace );
			$db = arv_season_calendar_days_until( $b['iso'], $today, $grace );
			if ( $da === $db ) {
				// Same day: settle it by name so the order does not wobble
				// between page loads for no visible reason.
				return strcmp( $a['name'], $b['name'] );
			}
			return $da - $db;
		}
	);

	$buckets = array();
	foreach ( $races as $race ) {
		$label = arv_season_calendar_bucket( $race['iso'], $today, $grace );
		if ( ! isset( $buckets[ $label ] ) ) {
			$buckets[ $label ] = array();
		}
		$buckets[ $label ][] = $race;
	}

	$rows_html = '';
	foreach ( $buckets as $label => $month_races ) {
		$rows_html .= '<div class="arv-calendar__month">';
		$rows_html .= '<h3 class="arv-calendar__month-name">' . esc_html( $label ) . '</h3>';
		$rows_html .= '<div class="arv-calendar__rows">';

		foreach ( $month_races as $race ) {
			$rows_html .= arv_season_calendar_row( $race );
		}

		$rows_html .= '</div></div>';
	}

	$theme = ( isset( $data['theme'] ) && 'dark' === $data['theme'] ) ? 'dark' : 'light';
	$base  = 'arv-calendar arv-calendar--' . $theme;

	$out  = '<div class="' . arv_wrapper_class( $data, $base ) . '">';
	$out .= '<div class="arv-calendar__inner">';

	$eyebrow = isset( $data['eyebrow'] ) ? $data['eyebrow'] : '';
	$heading = isset( $data['heading'] ) ? $data['heading'] : '';
	$intro   = isset( $data['intro'] ) ? $data['intro'] : '';

	if ( '' !== trim( $eyebrow ) ) {
		$out .= '<p class="arv-calendar__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-calendar__heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( '' !== trim( $intro ) ) {
		$out .= '<p class="arv-calendar__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= $rows_html;

	// The static tail: races with no expected date at all.
	$hiatus = arv_season_calendar_hiatus( isset( $data['hiatus_rows'] ) ? $data['hiatus_rows'] : '' );
	if ( '' !== $hiatus ) {
		// Defaulted rather than left blank when absent, matching how
		// cta_label behaves in Upcoming Races: a hiatus list with no heading
		// above it reads as a broken continuation of the month list.
		$h_heading = ( isset( $data['hiatus_heading'] ) && '' !== trim( $data['hiatus_heading'] ) )
			? $data['hiatus_heading']
			: __( 'On hiatus', 'aravaipa-elements' );
		$h_intro   = isset( $data['hiatus_intro'] ) ? $data['hiatus_intro'] : '';

		$out .= '<div class="arv-calendar__hiatus">';
		if ( '' !== trim( $h_heading ) ) {
			$out .= '<h3 class="arv-calendar__month-name">' . esc_html( $h_heading ) . '</h3>';
		}
		if ( '' !== trim( $h_intro ) ) {
			$out .= '<p class="arv-calendar__hiatus-intro">' . esc_html( $h_intro ) . '</p>';
		}
		$out .= '<div class="arv-calendar__rows">' . $hiatus . '</div>';
		$out .= '</div>';
	}

	$out .= '</div></div>';

	return $out;
}
