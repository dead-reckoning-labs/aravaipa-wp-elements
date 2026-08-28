<?php
/**
 * Race phase and race schema, independent of any element.
 *
 * These three started out inside the Upcoming Races element, which is where
 * they were first needed. Four other elements grew to call them, and now
 * includes/seo.php needs them too, on pages that contain no element at all:
 * an individual race page is a hand-built Cornerstone layout, so nothing
 * there would ever load an element file, and the element files are only
 * required from inside cs_register_elements. Requiring one from wp_head to
 * borrow a function would mean running cs_register_element() outside the
 * hook Cornerstone fires it from, on the site that sells the race entries.
 *
 * So they live here instead, loaded on every request beside the store they
 * read from. Names are unchanged: every existing caller still works, and the
 * arv_upcoming_races_ prefix is kept deliberately rather than renamed, since
 * a rename would be a much larger diff for no behavioural gain.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Today's date in the site's own timezone.
 *
 * current_time() rather than gmdate(): a race in Arizona should drop off the
 * morning after it runs in Arizona, not at 5pm the day before because the
 * server thinks in UTC.
 *
 * Filterable so the "has this passed" logic can be tested against a fixed
 * date without waiting for the calendar.
 *
 * @return string Y-m-d
 */
function arv_upcoming_races_today() {
	$today = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );

	/**
	 * Filters the date used to decide which races have already happened.
	 *
	 * @param string $today Y-m-d in site time.
	 */
	return apply_filters( 'arv_upcoming_races_today', $today );
}

/**
 * Which part of its life a race is in, and what to offer.
 *
 * Driven by dates rather than by asking UltraSignup whether entries are still
 * open, because UltraSignup does not say so in any way worth trusting: every
 * race page carries "Register Now" in its title whether or not it is
 * accepting entries, and Rock Hawk, which is open, contains the word
 * "Closed". Checked before relying on it. Dates are the signal we actually
 * have, and for most races registration closes at or near the start anyway.
 *
 * A row can carry its own live URL (a broadcast, a tracker); without one,
 * both live and results fall back to UltraSignup. It can also carry the date
 * entries close, which UltraSignup publishes for some races.
 *
 * @param array  $race
 * @param string $today Y-m-d in site time.
 * @param int    $lead  Days before race day that live results become the
 *                      offer, even though nobody has run yet.
 * @return array {phase, label, url}
 */
function arv_upcoming_races_action( $race, $today, $lead = 5 ) {
	$last    = '' !== $race['end'] ? $race['end'] : $race['iso'];
	$results = '' !== $race['live'] ? $race['live'] : arv_upcoming_races_results_url( $race['register'] );

	if ( $today < $race['iso'] ) {
		// The live board is populated well before the gun: it carries the
		// start list, with bib numbers, for anyone wanting to see who is
		// running. So the switch happens either once entries close, which is
		// the natural moment because there is nothing left to sell, or a few
		// days out for a race that never published a close date, whichever
		// comes first.
		$entries_closed = ( '' !== $race['closes'] && $today > $race['closes'] );
		$within_lead    = ( $lead > 0 && $today >= gmdate( 'Y-m-d', strtotime( $race['iso'] . ' 00:00:00 UTC' ) - ( $lead * DAY_IN_SECONDS ) ) );

		if ( '' !== $results && ( $entries_closed || $within_lead ) ) {
			return array(
				'phase' => 'live',
				'label' => __( 'Live Results', 'aravaipa-elements' ),
				'url'   => $results,
			);
		}
	}

	if ( $today < $race['iso'] ) {
		// Sold out wins over both Register and Entries Closed: a race can sell
		// out well before its own registration close date, and offering
		// Register up to that date would send people to a dead end same as an
		// expired link would. Checked before the closes-date logic below, not
		// after it, since a sold-out race often has no close date at all left
		// to check.
		$waitlist_url = arv_race_waitlist_for( $race );
		if ( '' !== $waitlist_url ) {
			return array(
				'phase' => 'waitlist',
				'label' => __( 'Join Waitlist', 'aravaipa-elements' ),
				'url'   => $waitlist_url,
			);
		}

		// UltraSignup publishes a registration close date on some races and
		// not others: 9 of the 69 in the current calendar, checked against
		// the live pages. When it is there, entries really do stop that day
		// and offering Register afterwards sends people to a dead end. When
		// it is not, the race keeps offering entries until race day, which is
		// what the other 60 do anyway.
		if ( '' !== $race['closes'] && $today > $race['closes'] ) {
			return array(
				'phase' => 'closed',
				'label' => __( 'Entries Closed', 'aravaipa-elements' ),
				'url'   => '',
			);
		}

		return array(
			'phase' => 'upcoming',
			'label' => __( 'Register', 'aravaipa-elements' ),
			'url'   => $race['register'],
		);
	}

	if ( $today <= $last ) {
		return array(
			'phase' => 'live',
			'label' => __( 'Live Results', 'aravaipa-elements' ),
			'url'   => $results,
		);
	}

	return array(
		'phase' => 'results',
		'label' => __( 'Results', 'aravaipa-elements' ),
		'url'   => $results,
	);
}

/**
 * Build the schema.org Event array for one race.
 *
 * Only fields we actually have are included. An Event carrying a placeholder
 * location or an invented end date is worse than one carrying fewer fields:
 * Google validates what is there, so a wrong value is an error where a
 * missing optional value is just a missing optional value.
 *
 * @param array  $race
 * @param string $phase Where the race is in its life, so the offer can say
 *                      whether entries are actually available.
 * @return array
 */
function arv_upcoming_races_event_schema( $race, $phase = 'upcoming' ) {
	$event = array(
		'@type'               => 'SportsEvent',
		'name'                => $race['name'],
		'startDate'           => $race['iso'],
		// Every race here is a real race in a real place. Saying so
		// explicitly stops Google assuming the pandemic-era default of
		// "online", which it does when the attendance mode is unstated.
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'organizer'           => array(
			'@type' => 'Organization',
			'name'  => 'Aravaipa Running',
			'url'   => 'https://www.aravaiparunning.com/',
		),
	);

	// Only when it differs, and only when it is actually later: an endDate
	// equal to startDate says nothing, and one before it is a validation
	// error rather than a cosmetic flaw. Two rows shipped with the pair
	// inverted (Fat Ox and Jackpot Ultras, both multi-day, both entered end
	// first), which is exactly the kind of typo that is invisible on the
	// page and loud to a validator. The data is fixed, and this makes the
	// next one impossible to publish.
	if ( '' !== $race['end'] && $race['end'] > $race['iso'] ) {
		$event['endDate'] = $race['end'];
	}

	if ( '' !== $race['page'] ) {
		$event['url'] = $race['page'];
	}

	if ( '' !== $race['image'] ) {
		$event['image'] = $race['image'];
	}

	if ( '' !== $race['distances'] ) {
		$event['description'] = $race['name'] . ': ' . $race['distances']
			. ( '' !== $race['location'] ? ' in ' . $race['location'] : '' ) . '.';
	}

	$place = array( '@type' => 'Place' );
	// Venue if we have one, otherwise the town. schema.org's Place requires a
	// name, so a Place with only an address is not valid and is better left
	// off entirely.
	$place['name'] = '' !== $race['venue'] ? $race['venue'] : $race['location'];

	if ( '' !== $race['location'] ) {
		$parts = array_map( 'trim', explode( ',', $race['location'] ) );
		$addr  = array( '@type' => 'PostalAddress' );
		if ( count( $parts ) >= 2 ) {
			$addr['addressLocality'] = $parts[0];
			$addr['addressRegion']   = $parts[1];
		} else {
			// "Arizona" and the like: a region with no town, which is what
			// the series rows carry.
			$addr['addressRegion'] = $race['location'];
		}
		$addr['addressCountry'] = 'US';
		$place['address']       = $addr;
	}

	if ( '' !== $place['name'] ) {
		$event['location'] = $place;
	}

	// An offer only belongs on a race you can still enter. Carrying one for a
	// race that has already run would advertise a closed registration in
	// search results, which is worse than saying nothing about entries.
	//
	// Waitlist counts too, and points at the waitlist itself rather than the
	// original registration link: the entries that link sold are gone, the
	// waitlist is the real, current, actionable offer.
	$waitlist_url = arv_race_waitlist_for( $race );
	$offer_url    = ( 'waitlist' === $phase && '' !== $waitlist_url ) ? $waitlist_url : $race['register'];

	if ( '' !== $offer_url && in_array( $phase, array( 'upcoming', 'closed', 'waitlist' ), true ) ) {
		$event['offers'] = array(
			'@type'        => 'Offer',
			'url'          => $offer_url,
			// No price: entry fees vary by distance and by how early you
			// enter, and a single wrong number in schema is worse than none.
			// availability carries the thing that actually matters, and has
			// to follow the phase: claiming InStock for a race whose entries
			// closed last week, or sold out and moved to a waitlist, is a
			// factual error in the markup, not a cosmetic one.
			'availability' => ( 'upcoming' === $phase )
				? 'https://schema.org/InStock'
				: 'https://schema.org/SoldOut',
			'category'     => 'primary',
		);
	}

	return $event;
}

/**
 * Normalize an ISO-ish date cell into Y-m-d, or '' if it is not a real date.
 *
 * Deliberately strict. A row whose date does not parse is dropped rather than
 * shown with a wrong date or emitted into schema as a malformed startDate,
 * which Google reports as an error against the whole page rather than
 * ignoring the one bad entry.
 *
 * @param string $cell
 * @return string
 */
function arv_upcoming_races_date( $cell ) {
	$cell = trim( $cell );

	if ( '' === $cell || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $cell ) ) {
		return '';
	}

	list( $y, $m, $d ) = array_map( 'intval', explode( '-', $cell ) );

	// checkdate rejects 2026-02-30 and friends, which the regex above happily
	// accepts. An impossible date reaching schema is the same class of error
	// as an unparseable one.
	return checkdate( $m, $d, $y ) ? $cell : '';
}
