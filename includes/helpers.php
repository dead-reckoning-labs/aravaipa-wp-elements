<?php
/**
 * Shared helpers for Aravaipa Elements.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse a textarea of pipe-delimited rows into an array of field arrays.
 *
 * Repeating data (race distances, schedule entries, partner logos) is entered
 * as one row per line rather than through a builder repeater control. That is
 * a deliberate trade: the Element API's repeater controls vary across
 * Cornerstone versions, while a textarea behaves identically everywhere and
 * lets staff paste a block straight out of the race spreadsheet instead of
 * clicking "add row" eleven times.
 *
 * Blank lines are skipped so a stray trailing newline cannot render an empty
 * card, which is the most common way this kind of input breaks a layout.
 *
 * @param string $raw       Raw textarea value.
 * @param int    $min_cells Rows with fewer cells than this are discarded.
 * @return array<int, array<int, string>>
 */
function arv_parse_rows( $raw, $min_cells = 1 ) {
	$rows = array();

	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return $rows;
	}

	$lines = preg_split( '/\r\n|\r|\n/', $raw );

	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$cells = array_map( 'trim', explode( '|', $line ) );
		if ( count( $cells ) < $min_cells ) {
			continue;
		}
		$rows[] = $cells;
	}

	return $rows;
}

/**
 * Split a comma-separated control value into a clean list.
 *
 * @param string $raw Raw control value.
 * @return array<int, string>
 */
function arv_parse_list( $raw ) {
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return array();
	}

	$parts = array_map( 'trim', explode( ',', $raw ) );

	return array_values( array_filter( $parts, 'strlen' ) );
}

/**
 * Fetch a cell by index with a safe default.
 *
 * @param array  $row     Row cells.
 * @param int    $index   Cell index.
 * @param string $default Fallback when absent or empty.
 * @return string
 */
function arv_cell( $row, $index, $default = '' ) {
	if ( ! isset( $row[ $index ] ) || '' === $row[ $index ] ) {
		return $default;
	}

	return $row[ $index ];
}

/**
 * Build the wrapper class list for an element.
 *
 * Cornerstone's omega partial supplies `class` and a generated `mod_id` used
 * to scope the builder's own generated CSS. Both are passed through untouched
 * so element styling and builder styling stay in sync; the base class is ours.
 *
 * @param array  $data Element data from the render callback.
 * @param string $base Base class for this element.
 * @return string
 */
function arv_wrapper_class( $data, $base ) {
	$parts = array( $base );

	if ( ! empty( $data['class'] ) ) {
		$parts[] = $data['class'];
	}

	if ( ! empty( $data['mod_id'] ) ) {
		$parts[] = $data['mod_id'];
	}

	return esc_attr( implode( ' ', $parts ) );
}

/**
 * Render an optional anchor, falling back to a plain span when no URL is set.
 *
 * Every element here has at least one "link this if there is somewhere to
 * link" case (register buttons, partner logos, results links). Centralised so
 * an empty URL can never emit `<a href="">`, which navigates to the current
 * page and reads as a broken button.
 *
 * @param string $url     Destination.
 * @param string $label   Already-escaped inner HTML.
 * @param string $classes CSS classes.
 * @param bool   $new_tab Open in a new tab.
 * @return string
 */
function arv_maybe_link( $url, $label, $classes = '', $new_tab = false ) {
	$class_attr = $classes ? ' class="' . esc_attr( $classes ) . '"' : '';

	if ( '' === trim( (string) $url ) ) {
		return '<span' . $class_attr . '>' . $label . '</span>';
	}

	$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

	return '<a href="' . esc_url( $url ) . '"' . $class_attr . $target . '>' . $label . '</a>';
}

/**
 * US state and territory codes to full names.
 *
 * Races store their location as "City, ST", which is what a runner reads on
 * a race page and what the state filter matches on. Search needs the other
 * direction too: someone typing "california" into the calendar's search box
 * found nothing, because the only thing in the row was "CA". The full name
 * is folded into each row's searchable text (see season-calendar.php) so
 * both spellings hit, and it labels the state dropdown so that reads as
 * "California" rather than a bare "CA".
 *
 * Every state is listed, not just the ones Aravaipa currently races in: the
 * schedule grows (Bad Beard added Tennessee, White Mountain added New
 * Hampshire), and a partial map would silently fail for the next one.
 *
 * @return array<string, string> Uppercase code => full name.
 */
function arv_state_names() {
	return array(
		'AL' => 'Alabama',
		'AK' => 'Alaska',
		'AZ' => 'Arizona',
		'AR' => 'Arkansas',
		'CA' => 'California',
		'CO' => 'Colorado',
		'CT' => 'Connecticut',
		'DE' => 'Delaware',
		'DC' => 'District of Columbia',
		'FL' => 'Florida',
		'GA' => 'Georgia',
		'HI' => 'Hawaii',
		'ID' => 'Idaho',
		'IL' => 'Illinois',
		'IN' => 'Indiana',
		'IA' => 'Iowa',
		'KS' => 'Kansas',
		'KY' => 'Kentucky',
		'LA' => 'Louisiana',
		'ME' => 'Maine',
		'MD' => 'Maryland',
		'MA' => 'Massachusetts',
		'MI' => 'Michigan',
		'MN' => 'Minnesota',
		'MS' => 'Mississippi',
		'MO' => 'Missouri',
		'MT' => 'Montana',
		'NE' => 'Nebraska',
		'NV' => 'Nevada',
		'NH' => 'New Hampshire',
		'NJ' => 'New Jersey',
		'NM' => 'New Mexico',
		'NY' => 'New York',
		'NC' => 'North Carolina',
		'ND' => 'North Dakota',
		'OH' => 'Ohio',
		'OK' => 'Oklahoma',
		'OR' => 'Oregon',
		'PA' => 'Pennsylvania',
		'PR' => 'Puerto Rico',
		'RI' => 'Rhode Island',
		'SC' => 'South Carolina',
		'SD' => 'South Dakota',
		'TN' => 'Tennessee',
		'TX' => 'Texas',
		'UT' => 'Utah',
		'VT' => 'Vermont',
		'VA' => 'Virginia',
		'WA' => 'Washington',
		'WV' => 'West Virginia',
		'WI' => 'Wisconsin',
		'WY' => 'Wyoming',
	);
}

/**
 * The full name for a state code, or the code itself when it is not one.
 *
 * @param string $code Two-letter code, any case.
 * @return string
 */
function arv_state_name( $code ) {
	$names = arv_state_names();
	$key   = strtoupper( trim( (string) $code ) );

	return isset( $names[ $key ] ) ? $names[ $key ] : $key;
}

/**
 * Split a distances string into its individual distances.
 *
 * The source data uses two delimiters and always has. A race written across
 * several row cells comes back pipe-joined from
 * arv_upcoming_races_parse_row() ("50K | 25K | 10K | 5K"); a race written as
 * one cell keeps whatever the editor typed, which is usually commas
 * ("50 Mile, 50K, 30K"). In the current 84-race file that is 29 pipe-joined
 * against 43 comma-joined, so anything that handles only one of them breaks
 * the majority of races. The map popup shipped that bug twice, once in each
 * direction, before this became one shared function.
 *
 * No distance value contains a comma of its own (checked across the whole
 * file for digit-comma-digit), so there is no thousands separator here for
 * this to break. A value with neither delimiter, "10K to 50K", comes back as
 * a single item with its wording intact.
 *
 * The JS side (assets/aravaipa-race-map.js) has to match this, since it
 * builds the map popups in the browser rather than in PHP. Keep the two in
 * step.
 *
 * @param string $distances Raw distances string.
 * @return array<int, string> Individual distances, empties removed.
 */
function arv_split_distances( $distances ) {
	$parts = preg_split( '/\s*[|,]\s*/', (string) $distances );

	if ( false === $parts ) {
		return array();
	}

	return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
}

/**
 * The series a race belongs to, or null.
 *
 * Read off the race's own page URL, which already encodes it:
 * /insomniac/thrasher-night-trail/ is an Insomniac race. Nothing new to
 * type per race, and it stays correct on its own as long as the site keeps
 * its URLs.
 *
 * Deliberately NOT arv_race_store_region_for(). That function answers a
 * different question and collapses series into geography on purpose:
 * Insomniac and DRT both come back as "arizona" there, because a runner
 * filtering by state wants every Arizona race regardless of which series it
 * belongs to. Series is the other axis, and a race has both.
 *
 * Only a known series counts. The first path segment is not a series just
 * because it exists: /races/dam-good-run, /virtual/javelina-jallucinations
 * and /colorado/aspen-backcountry are structure, not branding, and treating
 * them as series would put a "Races" chip on one race and a "Virtual" chip
 * on another.
 *
 * DRT Series cannot be read off the path at all. Of its 7 races, only San
 * Tan Scramble is published under /drt-series/; the other 6 (Cave Creek
 * Thriller, Pass Mountain, McDowell Mountain Frenzy, Elephant Mountain,
 * Mesquite Canyon, Dam Good Run) sit at plain top-level paths that look
 * identical to a standalone race. Confirmed by name with Jamil rather than
 * guessed, and checked before the path logic runs.
 *
 * @param array $race Race array.
 * @return array|null {slug, label, url} or null when the race is standalone.
 */
function arv_race_series_for( $race ) {
	$drt = array(
		'Cave Creek Thriller'      => true,
		'Pass Mountain'            => true,
		'McDowell Mountain Frenzy' => true,
		'San Tan Scramble'         => true,
		'Elephant Mountain'        => true,
		'Mesquite Canyon'          => true,
		'Dam Good Run'             => true,
	);

	if ( isset( $race['name'] ) && isset( $drt[ $race['name'] ] ) ) {
		return array(
			'slug'  => 'drt-series',
			'label' => 'DRT Series',
			'url'   => 'https://www.aravaiparunning.com/drt-series/',
		);
	}

	$series = array(
		'insomniac'                => 'Insomniac Night Series',
		// Same series, second spelling. Adrenaline Night Runs is published
		// under the long form while its nine siblings use the short one, so
		// without this alias one race would sit in a series of its own.
		'insomniac-night-trail-series' => 'Insomniac Night Series',
		'bear-chase-series'        => 'Bear Chase Series',
		'drt-series'               => 'DRT Series',
		'great-lakes-endurance'    => 'Great Lakes Endurance',
		'white-mountain-endurance' => 'White Mountain Endurance',
	);

	$canonical = array(
		'insomniac-night-trail-series' => 'insomniac',
	);

	if ( empty( $race['page'] ) ) {
		return null;
	}

	$path  = (string) wp_parse_url( $race['page'], PHP_URL_PATH );
	$parts = array_values( array_filter( explode( '/', $path ), 'strlen' ) );

	// Two segments minimum: /insomniac/ on its own is the series page itself,
	// not a race within it.
	if ( count( $parts ) < 2 || ! isset( $series[ $parts[0] ] ) ) {
		return null;
	}

	$slug = isset( $canonical[ $parts[0] ] ) ? $canonical[ $parts[0] ] : $parts[0];

	return array(
		'slug'  => $slug,
		'label' => $series[ $parts[0] ],
		// The series' own page, built from the segment as published rather
		// than the canonical slug, so the alias still links somewhere real.
		'url'   => 'https://www.aravaiparunning.com/' . $parts[0] . '/',
	);
}

/**
 * Waitlist link for a race that has sold out, keyed by name.
 *
 * Not derivable from anything already in a row. UltraSignup does carry this
 * as real structured data (JSON-LD `"availability":"SoldOut"` plus a
 * separate `hlWaitlist` link, both confirmed live on Javelina Jundred and
 * Mogollon Monster's own registration pages), but nothing in this codebase
 * fetches a live UltraSignup page at render time, and the registration
 * status label rendered elsewhere on that same page does not mention it at
 * all: Javelina's said "Registration closes: Mon, Oct 5" with no hint the
 * event was sold out underneath. Told directly by Jamil instead, 2026-08-27,
 * same as DRT and Bad Beard's series membership: a fact about the real
 * world this file has no other way to reach.
 *
 * @param array $race Race array.
 * @return string Waitlist URL, or '' when the race is not known to be sold out.
 */
function arv_race_waitlist_for( $race ) {
	$waitlist = array(
		'Mogollon Monster Trail Runs'         => 'https://ultrasignup.com/event_waitlist.aspx?did=130408',
		'Javelina Jundred Presented by: HOKA' => 'https://ultrasignup.com/event_waitlist.aspx?did=133229',
	);

	if ( ! isset( $race['name'] ) || ! isset( $waitlist[ $race['name'] ] ) ) {
		return '';
	}

	return $waitlist[ $race['name'] ];
}
