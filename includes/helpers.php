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
 * DRT Series (Desert Runner Trail Series) cannot be read off the path at
 * all. Of its 7 races, only San Tan Scramble is published under
 * /drt-series/; the other 6 (Cave Creek Thriller, Pass Mountain, McDowell
 * Mountain Frenzy, Elephant Mountain, Mesquite Canyon, Dam Good Run) sit at
 * plain top-level paths that look identical to a standalone race. Confirmed
 * by name with Jamil rather than guessed, and checked before the path logic
 * runs.
 *
 * Bad Beard Events is the same situation one level worse: all 3 of its
 * races (Rabid Raccoon 25k, Stump Jump 50k & 10 Miler, Stillhouse 100K)
 * share one literal page URL, https://www.aravaiparunning.com/bad-beard/,
 * so there is no per-race path segment to key off at all. By name, same as
 * DRT.
 *
 * Bear Chase Series (Rock Hawk, The Bear Chase, Chase The Moon) is
 * deliberately absent per Jamil, 2026-08-27: pulled from the series list
 * entirely rather than left path-detectable but unlabeled.
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
			'label' => 'Desert Runner Trail Series',
			'url'   => 'https://www.aravaiparunning.com/drt-series/',
		);
	}

	$bad_beard = array(
		'Rabid Raccoon 25k'          => true,
		'Stump Jump 50k & 10 Miler'  => true,
		'Stillhouse 100K'            => true,
	);

	if ( isset( $race['name'] ) && isset( $bad_beard[ $race['name'] ] ) ) {
		return array(
			'slug'  => 'bad-beard-events',
			'label' => 'Bad Beard Events',
			'url'   => 'https://www.aravaiparunning.com/bad-beard/',
		);
	}

	$series = array(
		'insomniac'                => 'Insomniac Night Series',
		// Same series, second spelling. Adrenaline Night Runs is published
		// under the long form while its nine siblings use the short one, so
		// without this alias one race would sit in a series of its own.
		'insomniac-night-trail-series' => 'Insomniac Night Series',
		'drt-series'               => 'Desert Runner Trail Series',
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
 * Not derivable from anything already in a row, but it is derivable from
 * UltraSignup, which carries it as real structured data: JSON-LD
 * `"availability":"SoldOut"` plus a separate `hlWaitlist` link on the race's
 * own registration page. What it does *not* do is say so anywhere a person
 * or a naive scraper would look. Javelina's visible status line read
 * "Registration closes: Mon, Oct 5" with no hint the event was already sold
 * out underneath, which is exactly why this started as a hand-kept list.
 *
 * scripts/fetch-waitlists.mjs now reads that structured data across every
 * race and writes the result to the store below. Run against the live
 * calendar it independently found the same three races Jamil had named by
 * hand (Mogollon Monster, Javelina Jundred, Jackass Night Trail) with the
 * same waitlist URLs, which is what earned it the job.
 *
 * The hardcoded map is kept as a fallback rather than deleted, so the
 * feature still works on an install where the scraper has never run, and so
 * a scrape that breaks degrades to slightly stale rather than to nothing.
 * The store wins when it has an answer.
 *
 * Jackass Night Trail shares Javelina Jundred's exact registration link
 * (both are `dtid=64465`, one UltraSignup listing sells entry to both), so
 * it is sold out and on the same waitlist for the same reason.
 *
 * @param array $race Race array.
 * @return string Waitlist URL, or '' when the race is not known to be sold out.
 */
function arv_race_waitlist_for( $race ) {
	if ( ! isset( $race['name'] ) || '' === trim( (string) $race['name'] ) ) {
		return '';
	}

	$name = $race['name'];

	// The scraped store first, when this is running inside WordPress and the
	// scraper has ever written to it. An empty store is not the same as no
	// store: once the scraper has run, "this race is absent" is a real answer
	// meaning not sold out, so only fall through when there is nothing stored
	// at all.
	if ( function_exists( 'arv_race_waitlist_store_get' ) ) {
		$stored = arv_race_waitlist_store_get();

		if ( ! empty( $stored ) ) {
			return isset( $stored[ $name ] ) ? $stored[ $name ] : '';
		}
	}

	$waitlist = array(
		'Mogollon Monster Trail Runs'            => 'https://ultrasignup.com/event_waitlist.aspx?did=130408',
		'Javelina Jundred Presented by: HOKA'    => 'https://ultrasignup.com/event_waitlist.aspx?did=133229',
		'Jackass Night Trail Presented by: HOKA' => 'https://ultrasignup.com/event_waitlist.aspx?did=133229',
	);

	return isset( $waitlist[ $name ] ) ? $waitlist[ $name ] : '';
}

/**
 * target and rel for a link that may or may not leave the site.
 *
 * Everything this element links to used to be somewhere else, so a new tab
 * was always right. Live results can now be a page on this site, and opening
 * those in a new tab quietly accumulates windows for anyone clicking down a
 * list of races.
 *
 * Matched on the site's own home URL rather than on a hardcoded domain, so
 * this stays correct on staging and behind a different host.
 *
 * @param string $url
 * @return string Attributes, with a leading space, or ''.
 */
function arv_races_link_target( $url ) {
	$home = function_exists( 'home_url' ) ? (string) home_url() : '';

	if ( '' !== $home ) {
		$host = (string) wp_parse_url( $home, PHP_URL_HOST );
		$to   = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( '' !== $host && $host === $to ) {
			return '';
		}
	}

	return ' target="_blank" rel="noopener"';
}

/* ------------------------------------------------------------------ *
 * Moved here from the Results element. Four elements write a distance
 * now (results, the season calendar, the featured race and the live
 * page), and only the first of them was guaranteed to be loaded: the
 * edge suite renders the calendar on its own and hit an undefined
 * function doing it, which is what a shared helper living inside one
 * element's file eventually does.
 * ------------------------------------------------------------------ */

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

/* ------------------------------------------------------------------ *
 * Race-clock pieces, moved here from the Results element.
 *
 * live-page.php loads unconditionally and calls all three of these, so
 * they were only ever reachable because the Results element happened to
 * be registered too. The same shape of bug as the distance label above:
 * a shared helper living inside one element's file, working right up
 * until something outside that element needs it. The race cards on the
 * home page are that something. arv_results_now() and
 * arv_results_elapsed_text() came along for the same reason: the clock
 * above cannot ask what time it is without them.
 * ------------------------------------------------------------------ */

// Eight days. Longer than any race on the calendar: Cocodona 250 allows
// about 125 hours. See arv_results_backstop_cutoff().
if ( ! defined( 'ARV_RESULTS_MAX_RUN' ) ) {
	define( 'ARV_RESULTS_MAX_RUN', 8 * DAY_IN_SECONDS );
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
 * The live marker and an elapsed clock, for a race card.
 *
 * Only ever shows anything while a race is actually running. Jamil's ask was
 * specifically that: a countdown on every card in a list of eight upcoming
 * races is eight numbers nobody asked for, but "this one is happening right
 * now, four hours in" is worth interrupting the page for.
 *
 * Both states are rendered and hidden rather than decided here and left
 * fixed, because this site is behind WP Rocket: the HTML a visitor gets was
 * very likely generated hours ago, so a card that only grew a live marker
 * when PHP happened to run during the race would never show one at all. The
 * clock script reads data-arv-start and swaps them at the gun, no reload
 * needed, which is the same reason the race week block renders every state.
 *
 * Unlike that block, this renders no countdown and no completed state. The
 * script is null-safe about both, so before the gun and after the cutoff
 * this simply shows nothing, which is the whole point.
 *
 * Nothing at all for a race the board has no start time for. Falling back to
 * midnight, the way the race week block reasonably does for a countdown,
 * would put a confidently wrong "Elapsed 14:32:07" on a race that has not
 * started.
 *
 * @param array $race Race store row.
 * @return string
 */
function arv_races_live_clock( $race ) {
	if ( ! function_exists( 'arv_live_store_find' ) || empty( $race['live'] ) ) {
		return '';
	}

	$board = arv_live_store_find( $race['live'] );

	if ( null === $board || empty( $board['start'] ) ) {
		return '';
	}

	$start_ts = strtotime( $board['start'] );

	if ( ! $start_ts ) {
		return '';
	}

	$state = arv_races_live_state( $race, $board, $start_ts );

	$cutoff_ts = function_exists( 'arv_race_cutoff_for' )
		? arv_race_cutoff_for( $race['name'], $board )
		: 0;
	$cutoff_ts = arv_results_backstop_cutoff( $cutoff_ts, gmdate( 'c', $start_ts ) );

	$out = arv_results_week_live_badge( array( 'state' => $state ) );

	$out .= '<span class="arv-races__clock" data-arv-results-clock'
		. ' data-arv-start="' . esc_attr( gmdate( 'c', $start_ts ) ) . '"'
		. ( $cutoff_ts ? ' data-arv-cutoff="' . esc_attr( gmdate( 'c', $cutoff_ts ) ) . '"' : '' )
		. '>';

	// The value itself only when the race is actually running. Hidden or
	// not, a stale "168:00" sitting in the markup a week after the race is
	// something a scraper can read and nobody wrote on purpose; the script
	// fills it the moment the gun goes, which is the only time it is true.
	$out .= '<span class="arv-results__elapsed" data-arv-results-elapsed'
		. ( 'live' === $state ? '' : ' hidden' ) . '>'
		. '<span class="arv-results__elapsed-value" data-arv-results-elapsed-value>'
		. ( 'live' === $state ? esc_html( arv_results_elapsed_text( $board['start'] ) ) : '' )
		. '</span></span>';

	return $out . '</span>';
}

/**
 * Whether a race is running right now, for the purposes of the card clock.
 *
 * Deliberately not arv_live_state(): that one answers "soon, live or done"
 * for a page about one race, and treats everything before the gun as soon.
 * Here the only question is whether to interrupt a list, so anything that is
 * not running is the same answer.
 *
 * @param array $race
 * @param array $board
 * @param int   $start_ts
 * @return string 'live' or 'soon'.
 */
function arv_races_live_state( $race, $board, $start_ts ) {
	$now = arv_results_now();

	if ( $now < $start_ts ) {
		return 'soon';
	}

	$cutoff_ts = function_exists( 'arv_race_cutoff_for' )
		? arv_race_cutoff_for( $race['name'], $board )
		: 0;
	$cutoff_ts = arv_results_backstop_cutoff( $cutoff_ts, gmdate( 'c', $start_ts ) );

	if ( $cutoff_ts && $now >= $cutoff_ts ) {
		return 'soon';
	}

	return 'live';
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
 * The name a race should be shown under, whatever the source called it.
 *
 * Two sources disagree, and both of them are wrong about some races. The
 * calendar page calls one "Rock Hawk" while its own logo reads ROCK HAWK
 * TRAIL RACES; the timing board called Black Bear "Black Bear Trail Races"
 * in 2025 and "Black Bear Trail Race" in 2026. Picking the newest edition's
 * name, which the live index used to do, just meant inheriting whichever
 * mistake was most recent.
 *
 * Applied on the way out of the stores rather than on the way in, so a
 * re-scrape cannot undo it and nothing has to remember to call it: read a
 * race from either store and it is already named correctly.
 *
 * Keyed by arv_results_race_key() so one entry catches every spelling of a
 * race across every year, which is the same normalisation that already
 * decides two rows are the same race.
 *
 * @param string $name
 * @return string
 */
function arv_race_display_name( $name ) {
	$name = (string) $name;

	if ( '' === $name || ! function_exists( 'arv_results_race_key' ) ) {
		return $name;
	}

	/**
	 * Filters the canonical display names, keyed by race key.
	 *
	 * @param array $names key => display name
	 */
	$names = apply_filters(
		'arv_race_display_names',
		array(
			'rock hawk'  => 'Rock Hawk Trail Races',
			'black bear' => 'Black Bear Trail Races',
		)
	);

	$key = arv_results_race_key( $name );

	return isset( $names[ $key ] ) ? $names[ $key ] : $name;
}
