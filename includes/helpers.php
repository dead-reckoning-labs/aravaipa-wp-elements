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
 * Who actually puts a race on, when that is not Aravaipa.
 *
 * The Nevada races bought from Calico Racing become Aravaipa events in
 * 2027, and Calico is still producing the 2026 editions. They are listed
 * here now because a runner looking at the Nevada calendar should be able
 * to find them, but a listing on aravaiparunning.com is an implicit claim
 * of who is running the event, and for these two that claim would be
 * wrong for another year. Saying so on the card is cheaper than the
 * confusion of not saying it.
 *
 * Keyed by race name in an option rather than added as a seventeenth
 * column, exactly like the waitlist store above and for the same reason:
 * arv_upcoming_races_parse_row() counts backwards from ARV_RACES_COLUMNS
 * to find its fixed tail, so a new column silently re-slices every
 * existing row carrying multi-cell distances. This also changes on a
 * completely different cadence to a row, once when an event changes
 * hands, where a row changes every season.
 *
 * @param array $race Race array.
 * @return string A short producer note, or '' for an ordinary Aravaipa race.
 */
function arv_race_presented_by( $race ) {
	if ( ! isset( $race['name'] ) || '' === trim( (string) $race['name'] ) ) {
		return '';
	}

	$notes = array();

	// defined() as well as function_exists(): the constant lives in
	// race-store.php and this file loads first, so a caller reaching this
	// before the store is loaded would otherwise fatal on the constant
	// rather than simply having no note to show.
	if ( function_exists( 'get_option' ) && defined( 'ARV_RACE_NOTE_OPTION' ) ) {
		$stored = get_option( ARV_RACE_NOTE_OPTION, array() );

		if ( is_array( $stored ) ) {
			$notes = $stored;
		}
	}

	/**
	 * Filters the per-race producer notes.
	 *
	 * @param array $notes Race name => note.
	 */
	$notes = apply_filters( 'arv_race_presented_by_notes', $notes );

	return isset( $notes[ $race['name'] ] ) ? (string) $notes[ $race['name'] ] : '';
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
 * distances, so the same distance is written several ways across the
 * calendar. Counted across the results store: 163 distinct labels, of which
 * a good third are another spelling of one already in the list. Javelina
 * alone offers "100 Mile" on 2015, "100 Miler" on 2016 and "100M"
 * elsewhere, and its 100K is "100K" on one edition and "100k" on the next,
 * which is why the buttons down an older race's page stop matching each
 * other the further down you read.
 *
 * Every rule here is anchored end to end, so only a label that is nothing
 * but a distance is touched. "3hr Ride" stays a ride, "Dawnbreaker 100 Mile
 * Ride" keeps its name, and "Kids", "Hike", "Marathon and Half" and
 * "Last Person Standing" are left exactly as somebody wrote them.
 *
 * Normalised only for display: the stored value is left alone, since it is
 * also what matches a distance to the timing board's name.
 *
 * @param string $distance
 * @return string
 */
function arv_results_distance_label( $distance ) {
	$label = trim( (string) $distance );

	// 50KM, 50 K and 50k are all 50K.
	$label = preg_replace( '/^(\d+(?:\.\d+)?)\s*(?:k|km|kms|kilometers?|kilometres?)$/i', '$1K', $label );

	// 50M and 100 Miler are 50 Mile and 100 Mile. M is miles here and never
	// metres: Mesquite Canyon ran the same race as "50 Mile" in 2015 and
	// "50M" in 2016.
	$label = preg_replace( '/^(\d+(?:\.\d+)?)\s*(?:m|mi|miles?|miler)$/i', '$1 Mile', $label );

	// 24H, 24h and 24 Hours are one race, and the fixed-time events are
	// written every one of those ways across the archive.
	$label = preg_replace( '/^(\d+(?:\.\d+)?)\s*(?:h|hr|hrs|hours?)$/i', '$1 Hour', $label );
	$label = preg_replace( '/^(\d+)\s*(?:d|days?)$/i', '$1 Day', $label );

	// Steep Camp's weighted carries. The plus is carried through rather
	// than assumed: 45 and 60 Lbs are exact classes and only 75 is "and up",
	// so adding one to all three would invent two classes that never ran.
	$label = preg_replace( '/^(\d+)\s*(\+?)\s*lbs\.?\s*(\+?)$/i', '$1$2$3 Lbs', $label );

	// "Half", "1/2" and "Half Marathon" are the same thing said three ways.
	if ( preg_match( '#^(?:half|1/2)(?:\s*marathon)?$#i', $label ) ) {
		$label = __( 'Half Marathon', 'aravaipa-elements' );
	}

	if ( preg_match( '/^(?:vk|vertical\s*k(?:ilometers?)?)$/i', $label ) ) {
		$label = __( 'Vertical K', 'aravaipa-elements' );
	}

	return $label;
}

/**
 * Whether at least one of an edition's winners is scored over a real ground
 * or clock distance, rather than every one of them being some other kind of
 * category entirely.
 *
 * March of the Fallen is a ruck march: everybody walks the same course, and
 * what is labeled a "distance" on it is how much weight they carried,
 * "45 Lbs", "Heavyweight", "Litter", "Unknown". Shown with the vocabulary a
 * hundred other rows use for an actual distance, "Winners, 4 distances"
 * reads as though picking the wrong one of four routes were possible, and
 * a reader who opens it to see which is longest finds nothing that answers
 * that question, because none of them is one.
 *
 * True already covers everything else on the archive: a headline event's
 * own distance, a lap race whose categories tie at the same course length,
 * Blue Ribbon Run's "10K/5K/2K" run together as one string. Only a row
 * where nothing at all measures as a distance says no.
 *
 * @param array $winners
 * @return bool
 */
function arv_stats_has_real_distance( $winners ) {
	foreach ( (array) $winners as $row ) {
		$label = arv_results_distance_label( isset( $row['distance'] ) ? $row['distance'] : '' );

		if ( preg_match( '/\d+(?:\.\d+)?\s*(?:K|Mile|Hour|Day)\b/i', $label ) ) {
			return true;
		}

		if ( preg_match( '/^(?:Marathon|Half Marathon|Vertical K)$/i', $label ) ) {
			return true;
		}
	}

	return false;
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
		? arv_race_cutoff_for( $race['name'], $board, $start_ts )
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
/**
 * When a race actually starts: the board's clock, or a director's word.
 *
 * The board knows the gun time for a race Aravaipa times itself. For one
 * scored somewhere else there is no board at all, and the only real start
 * is the one a director gave us, which arv_race_start_override_ts() holds.
 *
 * Extracted because three places needed this order and only two had it.
 * The race week block and its status line both resolved the override; the
 * results list did not, so a boardless race fell through to "is it today"
 * and read as happening now for the whole day. Oli Kai is scored on
 * RaceResult, so it had no board, so it never got a start, so its nine
 * hour cutoff had nothing to measure from and was never consulted.
 *
 * @param array      $race  Needs 'name' and 'iso'.
 * @param array|null $board
 * @return int 0 when neither source has an answer.
 */
function arv_race_start_ts( $race, $board ) {
	if ( null !== $board && ! empty( $board['start'] ) ) {
		return (int) strtotime( $board['start'] );
	}

	if ( function_exists( 'arv_race_start_override_ts' ) ) {
		$override = arv_race_start_override_ts(
			isset( $race['name'] ) ? $race['name'] : '',
			isset( $race['iso'] ) ? $race['iso'] : ''
		);

		if ( null !== $override ) {
			return (int) $override;
		}
	}

	return 0;
}

/* ------------------------------------------------------------------ *
 * Start times and cutoffs, in the race's own time zone.
 *
 * The race week block used to show a single number: a countdown or an
 * elapsed clock. With no gun time it counted down to midnight in Phoenix,
 * so a Tucson race that goes off at 6:00 AM read "Starts in 1:13:41" at a
 * quarter to eleven the night before. Jamil's ask was to show the real
 * start and cutoff instead, so a card now carries both, read from the same
 * sources the clock already trusts and nothing new:
 *
 *   1. The timing board (arv_live_store_find()): a start per distance and
 *      the event's cutoff, maintained by the timing team.
 *   2. A director's gun time (arv_race_start_store_get()), for a race the
 *      board does not carry. Optionally per distance.
 *   3. A cutoff override in hours (arv_race_cutoff_store_get()), measured
 *      from the first gun, which beats the board's own cutoff.
 *
 * Neither the calendar rows nor UltraSignup/RunSignup carry a gun time, so
 * a race with none of the three shows its date and no clock at all.
 * ------------------------------------------------------------------ */

/**
 * The IANA zone a US state's races run in, where a state has only one.
 *
 * Split states (TN, KY, FL, IN, ND, SD, NE, KS, TX, OR, ID) are left out on
 * purpose: Chattanooga is Eastern and Nashville Central, and guessing the
 * wrong half would print a confidently wrong hour. A race in one of those
 * gets its zone from the board's own offset or a start override instead.
 *
 * @param string $location "Town, ST".
 * @return string Empty when the state is unknown or split.
 */
function arv_race_state_zone( $location ) {
	if ( ! preg_match( '/,\s*([A-Z]{2})\s*$/', trim( (string) $location ), $m ) ) {
		return '';
	}

	$zones = array(
		'AZ' => 'America/Phoenix',
		'CO' => 'America/Denver',
		'UT' => 'America/Denver',
		'NM' => 'America/Denver',
		'WY' => 'America/Denver',
		'MT' => 'America/Denver',
		'CA' => 'America/Los_Angeles',
		'NV' => 'America/Los_Angeles',
		'WA' => 'America/Los_Angeles',
		'IL' => 'America/Chicago',
		'WI' => 'America/Chicago',
		'MN' => 'America/Chicago',
		'IA' => 'America/Chicago',
		'MO' => 'America/Chicago',
		'AR' => 'America/Chicago',
		'LA' => 'America/Chicago',
		'MS' => 'America/Chicago',
		'AL' => 'America/Chicago',
		'OK' => 'America/Chicago',
		'NH' => 'America/New_York',
		'VT' => 'America/New_York',
		'ME' => 'America/New_York',
		'MA' => 'America/New_York',
		'NY' => 'America/New_York',
		'PA' => 'America/New_York',
		'NJ' => 'America/New_York',
		'CT' => 'America/New_York',
		'RI' => 'America/New_York',
		'MI' => 'America/Detroit',
		'OH' => 'America/New_York',
		'GA' => 'America/New_York',
		'NC' => 'America/New_York',
		'SC' => 'America/New_York',
		'VA' => 'America/New_York',
		'WV' => 'America/New_York',
		'MD' => 'America/New_York',
		'DE' => 'America/New_York',
		'DC' => 'America/New_York',
		'HI' => 'Pacific/Honolulu',
	);

	return isset( $zones[ $m[1] ] ) ? $zones[ $m[1] ] : '';
}

/**
 * The zone a race's times should be read and printed in.
 *
 * A director's stated zone first, since it was entered for exactly this.
 * Then the state's zone, but only if it agrees with the board's offset at
 * the gun: the board knows the real offset, the state map only knows the
 * usual one. Where they disagree, or there is no state, the board's offset
 * as a fixed zone. Last, the site's own zone.
 *
 * @param array      $race  Needs 'name'; 'location' when known.
 * @param array|null $board
 * @return DateTimeZone
 */
function arv_race_timezone( $race, $board = null ) {
	$name   = isset( $race['name'] ) ? (string) $race['name'] : '';
	$starts = function_exists( 'arv_race_start_store_get' ) ? arv_race_start_store_get() : array();

	if ( '' !== $name && ! empty( $starts[ $name ]['tz'] ) ) {
		try {
			return new DateTimeZone( $starts[ $name ]['tz'] );
		} catch ( Exception $e ) {
			// Fall through to the next source.
		}
	}

	$state = arv_race_state_zone( isset( $race['location'] ) ? $race['location'] : '' );
	$zone  = ( '' !== $state ) ? new DateTimeZone( $state ) : null;

	$board_ts = ( null !== $board && ! empty( $board['start'] ) ) ? (int) strtotime( $board['start'] ) : 0;
	$offset   = ( null !== $board && ! empty( $board['offset'] ) ) ? (float) $board['offset'] : 0.0;

	if ( $board_ts && 0.0 !== $offset ) {
		$seconds = (int) round( $offset * 3600 );

		if ( null !== $zone && $zone->getOffset( new DateTime( '@' . $board_ts ) ) === $seconds ) {
			return $zone;
		}

		$abs = abs( $seconds );

		return new DateTimeZone( sprintf( '%s%02d:%02d', $seconds < 0 ? '-' : '+', intdiv( $abs, 3600 ), intdiv( $abs % 3600, 60 ) ) );
	}

	if ( null !== $zone ) {
		return $zone;
	}

	if ( function_exists( 'wp_timezone' ) ) {
		return wp_timezone();
	}

	return new DateTimeZone( 'America/Phoenix' );
}

/**
 * "MDT", "MST", or "UTC-6" for a fixed-offset zone, at a given instant.
 *
 * @param int          $ts
 * @param DateTimeZone $tz
 * @return string
 */
function arv_race_zone_label( $ts, $tz ) {
	$dt   = ( new DateTime( '@' . (int) $ts ) )->setTimezone( $tz );
	$abbr = $dt->format( 'T' );

	if ( preg_match( '/^[A-Z]{2,5}$/', $abbr ) ) {
		return $abbr;
	}

	$seconds = $tz->getOffset( $dt );
	$hours   = $seconds / 3600;

	return 'UTC' . ( $hours < 0 ? '-' : '+' ) . rtrim( rtrim( number_format( abs( $hours ), 2, '.', '' ), '0' ), '.' );
}

/**
 * A board distance name as a card should show it.
 *
 * The board names Sunday races "5K Sunday" or "SUNDAY 20K" so its own list
 * can tell the days apart. The card already groups starts by day, so the
 * day word is dropped and the rest normalised the same way the pills are.
 *
 * @param string $name
 * @return string
 */
function arv_race_schedule_label( $name ) {
	$label = preg_replace( '/\b(?:mon|tues|wednes|thurs|fri|satur|sun)day\b/i', '', (string) $name );
	$label = trim( preg_replace( '/\s+/', ' ', $label ), " \t-:" );

	if ( '' === $label ) {
		$label = trim( (string) $name );
	}

	return function_exists( 'arv_results_distance_label' ) ? arv_results_distance_label( $label ) : $label;
}

/**
 * Every known gun time for one race, plus its cutoff, in its own zone.
 *
 * 'first' is the instant the race clock runs from: the board's event start
 * where there is one, the same instant arv_race_start_ts() returns, so the
 * clock and every other caller agree. 'waves' is the first start of each
 * local day, which is what the elapsed clock steps through on a multi-day
 * event (see arv_results_week_status()). 'cutoff' is the real cutoff only,
 * never the eight day backstop, so it is safe to print.
 *
 * @param array      $race  Needs 'name' and 'iso'; 'location' when known.
 * @param array|null $board
 * @return array {tz, starts: list<{label, ts}>, waves: list<{label, ts}>, first, cutoff, hours, source}
 */
function arv_race_schedule( $race, $board = null ) {
	$name   = isset( $race['name'] ) ? (string) $race['name'] : '';
	$iso    = isset( $race['iso'] ) ? (string) $race['iso'] : '';
	$tz     = arv_race_timezone( $race, $board );
	$starts = array();
	$first  = 0;
	$source = '';

	if ( null !== $board && ! empty( $board['start'] ) ) {
		$source = 'board';
		$first  = (int) strtotime( $board['start'] );

		foreach ( (array) ( isset( $board['races'] ) ? $board['races'] : array() ) as $entry ) {
			$ts = ! empty( $entry['start'] ) ? (int) strtotime( $entry['start'] ) : 0;

			if ( $ts > 0 && ! empty( $entry['name'] ) ) {
				$starts[] = array( 'label' => arv_race_schedule_label( $entry['name'] ), 'ts' => $ts );
			}
		}

		if ( empty( $starts ) && $first ) {
			$starts[] = array( 'label' => '', 'ts' => $first );
		}
	} elseif ( '' !== $iso && function_exists( 'arv_race_start_store_get' ) ) {
		$stored = arv_race_start_store_get();
		$entry  = isset( $stored[ $name ] ) ? $stored[ $name ] : null;

		if ( is_array( $entry ) && ! empty( $entry['tz'] ) ) {
			$source = 'override';

			$times = ! empty( $entry['distances'] ) && is_array( $entry['distances'] )
				? $entry['distances']
				: ( ! empty( $entry['time'] ) ? array( '' => $entry['time'] ) : array() );

			foreach ( $times as $label => $time ) {
				try {
					$dt = new DateTime( $iso . ' ' . $time, new DateTimeZone( $entry['tz'] ) );
				} catch ( Exception $e ) {
					continue;
				}

				$starts[] = array( 'label' => arv_race_schedule_label( (string) $label ), 'ts' => $dt->getTimestamp() );
			}

			// The single 'time' stays the race's own start, the one
			// arv_race_start_override_ts() and every sort already use.
			$override = function_exists( 'arv_race_start_override_ts' ) ? arv_race_start_override_ts( $name, $iso ) : null;
			$first    = ( null !== $override ) ? (int) $override : 0;
		}
	}

	usort(
		$starts,
		function ( $a, $b ) {
			return ( $a['ts'] === $b['ts'] ) ? 0 : ( ( $a['ts'] < $b['ts'] ) ? -1 : 1 );
		}
	);

	if ( ! $first && ! empty( $starts ) ) {
		$first = $starts[0]['ts'];
	}

	// The first gun of each local day. Starts are sorted, so the first one
	// seen for a day is that day's earliest.
	$waves = array();
	foreach ( $starts as $start ) {
		$day = ( new DateTime( '@' . $start['ts'] ) )->setTimezone( $tz )->format( 'Y-m-d' );

		if ( ! isset( $waves[ $day ] ) ) {
			$waves[ $day ] = $start;
		}
	}
	$waves = array_values( $waves );

	// The board's event start is normally its first distance's start, but
	// the two are entered separately. Whichever is earlier is the first
	// wave, so the clock never starts after a gun that has already gone.
	if ( ! empty( $waves ) && $first ) {
		$waves[0]['ts'] = min( $waves[0]['ts'], $first );
	}

	$hours = 0.0;
	if ( function_exists( 'arv_race_cutoff_store_get' ) ) {
		$cutoffs = arv_race_cutoff_store_get();
		$hours   = isset( $cutoffs[ $name ] ) ? (float) $cutoffs[ $name ] : 0.0;
		$hours   = (float) apply_filters( 'arv_race_cutoff_hours', $hours, $name, $board );
	}

	$cutoff = ( $first && function_exists( 'arv_race_cutoff_for' ) ) ? (int) arv_race_cutoff_for( $name, $board, $first ) : 0;

	return array(
		'tz'     => $tz,
		'starts' => $starts,
		'waves'  => $waves,
		'first'  => $first,
		'cutoff' => $cutoff,
		'hours'  => ( $cutoff && $hours > 0 ) ? $hours : 0.0,
		'source' => $source,
	);
}

/**
 * "Sat 7:00 AM" in a race's zone.
 *
 * @param int          $ts
 * @param DateTimeZone $tz
 * @param bool         $day Lead with the weekday.
 * @return string
 */
function arv_race_time_text( $ts, $tz, $day = true ) {
	$dt = ( new DateTime( '@' . (int) $ts ) )->setTimezone( $tz );

	return $dt->format( $day ? 'D g:i A' : 'g:i A' );
}

/**
 * The start and cutoff lines for a race card.
 *
 * One start: "Start Sat 7:00 AM MDT". Several: one line per race day,
 * distances in the order they go off, distances sharing a gun listed
 * together: "Sat 100K 5:30 AM, 50 Mile 6:30 AM, 50K 7:30 AM MDT", each one a
 * wrapping slot so a line breaks between distances, never mid-time. Then
 * "Cutoff Sun 11:00 AM MDT", with the time limit beside it when the cutoff
 * is one we hold as a duration.
 *
 * Plain text in the HTML, not filled by a script: these never change while
 * the page is cached, so there is nothing for WP Rocket to get wrong.
 *
 * @param array $schedule From arv_race_schedule().
 * @return string Empty when there is no start time to show.
 */
function arv_race_schedule_markup( $schedule ) {
	if ( empty( $schedule['starts'] ) ) {
		return '';
	}

	$tz   = $schedule['tz'];
	$zone = arv_race_zone_label( $schedule['starts'][0]['ts'], $tz );
	$out  = '<dl class="arv-results__week-times">';

	$distinct = array_unique( arv_race_schedule_instants( $schedule['starts'] ) );

	if ( 1 === count( $distinct ) ) {
		$out .= '<div class="arv-results__week-time">'
			. '<dt>' . esc_html( __( 'Start', 'aravaipa-elements' ) ) . '</dt>'
			. '<dd>' . esc_html( arv_race_time_text( $schedule['starts'][0]['ts'], $tz ) . ' ' . $zone ) . '</dd>'
			. '</div>';
	} else {
		// Day => time text => labels sharing that gun.
		$days = array();
		foreach ( $schedule['starts'] as $start ) {
			$dt   = ( new DateTime( '@' . $start['ts'] ) )->setTimezone( $tz );
			$day  = $dt->format( 'D' );
			$time = $dt->format( 'g:i A' );

			if ( ! isset( $days[ $day ][ $time ] ) ) {
				$days[ $day ][ $time ] = array();
			}

			if ( '' !== $start['label'] && ! in_array( $start['label'], $days[ $day ][ $time ], true ) ) {
				$days[ $day ][ $time ][] = $start['label'];
			}
		}

		$first = true;
		foreach ( $days as $day => $times ) {
			$parts = array();
			$count = count( $times );
			$i     = 0;
			foreach ( $times as $time => $labels ) {
				$i++;
				// Each "100K 5:30 AM" kept on one line, so a wrap falls
				// between distances and never between a time and its AM.
				$parts[] = '<span class="arv-results__week-slot">'
					. esc_html( ( empty( $labels ) ? '' : implode( ' / ', $labels ) . ' ' ) . $time . ( $i === $count ? ' ' . $zone : '' ) )
					. '</span>';
			}

			$out .= '<div class="arv-results__week-time">'
				. '<dt' . ( $first ? '' : ' class="arv-results__week-time-more"' ) . '>'
				. esc_html( $first ? __( 'Start', 'aravaipa-elements' ) : '' ) . '</dt>'
				. '<dd><span class="arv-results__week-day">' . esc_html( $day ) . '</span> '
				. implode( '<span class="arv-results__sr">, </span>', $parts ) . '</dd>'
				. '</div>';

			$first = false;
		}
	}

	if ( ! empty( $schedule['cutoff'] ) ) {
		$text = arv_race_time_text( $schedule['cutoff'], $tz ) . ' ' . arv_race_zone_label( $schedule['cutoff'], $tz );

		if ( ! empty( $schedule['hours'] ) ) {
			$hours = rtrim( rtrim( number_format( (float) $schedule['hours'], 2, '.', '' ), '0' ), '.' );
			/* translators: %s is a number of hours. */
			$text .= ' (' . sprintf( __( '%s hr limit', 'aravaipa-elements' ), $hours ) . ')';
		}

		$out .= '<div class="arv-results__week-time">'
			. '<dt>' . esc_html( __( 'Cutoff', 'aravaipa-elements' ) ) . '</dt>'
			. '<dd>' . esc_html( $text ) . '</dd>'
			. '</div>';
	}

	return $out . '</dl>';
}

/**
 * The instants out of a list of starts.
 *
 * @param array $starts
 * @return int[]
 */
function arv_race_schedule_instants( $starts ) {
	return array_map(
		function ( $s ) {
			return (int) $s['ts'];
		},
		(array) $starts
	);
}

/**
 * The wave a race clock should be measuring from right now.
 *
 * The latest day's first gun that has already gone. On Bear Chase that is
 * the 100K from 5:30 AM Saturday until the Sunday half goes off at 7:00
 * AM, then the half: a clock reading 25:30 on Sunday morning, from a gun
 * nobody on course on Sunday heard, is the wrong number for that crowd.
 *
 * @param array $waves From arv_race_schedule().
 * @param int   $now
 * @return array|null {label, ts}
 */
function arv_race_current_wave( $waves, $now ) {
	$current = null;

	foreach ( (array) $waves as $wave ) {
		if ( $wave['ts'] <= $now ) {
			$current = $wave;
		}
	}

	return $current;
}

function arv_races_live_state( $race, $board, $start_ts ) {
	$now = arv_results_now();

	if ( $now < $start_ts ) {
		return 'soon';
	}

	$cutoff_ts = function_exists( 'arv_race_cutoff_for' )
		? arv_race_cutoff_for( $race['name'], $board, $start_ts )
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
	// A real instant, not current_time( 'timestamp' ).
	//
	// current_time( 'timestamp' ) returns the epoch shifted by the site's
	// UTC offset, which on America/Phoenix is seven hours behind the actual
	// moment. Every caller of this compares it against a true epoch: a
	// board start or cutoff, which arrive as '2026-08-29T10:00:00.000Z' and
	// go through strtotime(); or a director's gun time, which
	// arv_race_start_override_ts() builds with DateTimeZone and returns
	// from DateTime::getTimestamp(). Comparing the two frames made every
	// race read as running for seven hours after it actually finished.
	//
	// That is the whole reason Oli Kai still said "Happening now" at nine
	// at night with a nine hour cutoff stored and correct: 8am Eastern plus
	// nine hours is 21:00 UTC, and the shifted clock did not reach 21:00
	// until 04:00 UTC the next morning. Black Bear and Rock Hawk reading
	// live the morning after they finished was the same seven hours.
	//
	// Date comparisons are unaffected: those go through
	// arv_upcoming_races_today(), which asks current_time( 'Y-m-d' ) for a
	// date string in site time and is the right call for "what day is it
	// in Phoenix".
	//
	// current_time( 'timestamp', true ) rather than a bare time() so the
	// instant stays something a caller can pin: the store test harness
	// drives every clock state in this file through its current_time()
	// stub, and a raw time() would make those tests unable to say when
	// "now" is. The second argument is the whole fix, it asks for GMT.
	$now = function_exists( 'current_time' )
		? current_time( 'timestamp', true ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		: time();

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
 * visitor reads first. Same HH:MM:SS the script ticks in, hours counting
 * past 24 rather than rolling into days, so the first paint and the running
 * clock never disagree about shape: a race cutoff is stated in hours, and
 * "33:53:37" can be read against "38 hours" where "1d 9:53" cannot.
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
	$seconds = (int) ( $since % 60 );

	return sprintf( '%02d:%02d:%02d', $hours, $minutes, $seconds );
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

/**
 * Road or trail, for a race name.
 *
 * Nothing in the calendar's own data says this. UltraSignup's event feed
 * carries no surface field, and the closest thing on the site, the free
 * text in a race's distances ("4 Mile Road Race"), is present on 2 of 87
 * races and would have called the Tucson Marathon a trail race by its
 * absence. So this is Jamil's own list, by name, the same way
 * arv_race_display_names() above is: not derived, because nothing to
 * derive it from exists, and not guessed, because a guess here is a race
 * badged wrong on its own page.
 *
 * Trail is the default for anything not listed, which the numbers back:
 * 75 of the 87 races on the live calendar are trail. Aravaipa is a trail
 * company first; an unrecognised race is far more likely a new trail
 * race than a new road one.
 *
 * @param string $name
 * @return string 'road' or 'trail'.
 */
function arv_race_terrain( $name ) {
	$name = (string) $name;

	if ( '' === $name || ! function_exists( 'arv_results_race_key' ) ) {
		return 'trail';
	}

	/**
	 * Filters which races are road races, by their real name. Everything
	 * not listed here is trail.
	 *
	 * Names, not pre-computed keys: arv_results_race_key() strips "run",
	 * "race", "the", "ultra" and a trailing distance as whole words, so
	 * "Purple Run" keys as "purple" and "Run with the Roosters" keys as
	 * "with rooster". A list of those stems would be correct but
	 * unreadable and would silently break the moment the stemming rule
	 * changes; running every name through the same function this reads
	 * with keeps the two in step by construction.
	 *
	 * @param array $road_names
	 */
	$road_names = apply_filters(
		'arv_race_terrain_road_names',
		array(
			'Tucson Marathon',
			'Mountain to Fountain',
			'ET Full Moon',
			'Labor of Love',
			'Purple Run',
			'Running with the Devil',
			'Vegas Golden Night & Day',
			'Jackpot Ultras',
			'Fat Ox',
			'Run Around Tucson (RAT)',
			'Run with the Roosters',
			'Across the Years',
		)
	);

	// Not memoised in a static: a test that adds to the filter mid-run, or
	// a page that only decides its own copy of the filter after this has
	// already been called once, would otherwise get the first answer for
	// the rest of the request. Twelve names through a regex is string
	// work, not a query, so there is nothing here worth caching against.
	$road = array_fill_keys( array_map( 'arv_results_race_key', $road_names ), true );

	return isset( $road[ arv_results_race_key( $name ) ] ) ? 'road' : 'trail';
}

/**
 * Cache an expensive block of rendered HTML against the data it was built
 * from.
 *
 * /results/ and /photos/ took 17 and 33 seconds to generate. WP Rocket hid
 * that from anonymous visitors by serving a static copy, which is why it
 * went unnoticed, but a cached page is not a fast page: logged-in staff are
 * never served the cache and neither is the first visitor after any purge,
 * and this plugin purges on every release. Those are the people who
 * reported it.
 *
 * Keyed on a fingerprint of the source data rather than on a timer, so the
 * cache is exactly as old as the data and correcting a result shows up on
 * the next page load instead of whenever a TTL happens to lapse. There is
 * no invalidation hook to forget to call.
 *
 * @param string   $name        Cache name, unique per render.
 * @param mixed    $fingerprint Anything serialisable that changes when the output should.
 * @param callable $build       Produces the HTML. Called only on a miss.
 * @param callable $ttl         Optional. Called after the build, returns the seconds to keep it.
 * @return string
 */
function arv_cached_render( $name, $fingerprint, $build, $ttl = null ) {
	$key = 'arv_render_' . $name . '_' . md5( (string) wp_json_encode( $fingerprint ) );

	$cached = get_transient( $key );

	if ( is_string( $cached ) ) {
		return $cached;
	}

	$html = (string) call_user_func( $build );

	// A week, not a day: the fingerprint already handles correctness, so
	// this is only a floor on how long a stale entry can survive a
	// fingerprint that fails to change for a reason nobody predicted.
	//
	// The floor matters more than it looks. The per-year photo archives
	// (/photos-2023/ etc.) get little enough traffic that a one-day TTL
	// meant the transient routinely expired between visits, so the next
	// visitor, often a crawler rather than a person, paid the full 30 to
	// 90 second cold render WP Rocket's own page cache was supposed to
	// hide. A week is still short enough to recover quickly from a bad
	// fingerprint, and long enough that low-traffic pages actually stay
	// warm between the visits they get.
	set_transient( $key, $html, is_callable( $ttl ) ? (int) call_user_func( $ttl ) : WEEK_IN_SECONDS );

	return $html;
}

/**
 * Resolve a caller-supplied heading level to a tag we are willing to print.
 *
 * Section headings in this plugin are h2 by default, which is right when the
 * page already has an h1 above them. Some pages are built almost entirely from
 * these shortcodes and so have no other candidate: on those, the primary
 * section needs to be the h1 instead, and passing heading_tag says so.
 *
 * Anything unrecognised falls back to the default rather than being printed,
 * since the value reaches us from page content and ends up in markup.
 *
 * @param mixed  $value   Requested tag.
 * @param string $default Tag to use when nothing valid was asked for.
 * @return string
 */
function arv_heading_tag( $value, $default = 'h2' ) {
	$allowed = array( 'h1', 'h2', 'h3', 'h4', 'p', 'div' );
	$value   = is_string( $value ) ? strtolower( trim( $value ) ) : '';

	return in_array( $value, $allowed, true ) ? $value : $default;
}

/**
 * Stop Jetpack printing its own Open Graph tags on a page one of this
 * plugin's SEO modules is about to describe itself.
 *
 * Jetpack prints og:tags unconditionally on every singular page via its own
 * wp_head callback, jetpack_og_tags, at the default priority (10). Every
 * module in this plugin that prints its own og:title/og:description runs
 * earlier, at priority 3 or 4, so calling this from inside one of them
 * removes Jetpack's callback from the same wp_head event before it fires.
 * Without it, a page carried two full sets: this module's specific one,
 * then Jetpack's generic fallback a few lines later in the same <head>
 * ("Visit the post for more."), and which one a given scraper honored was
 * whichever it read first.
 *
 * Safe to call unconditionally: each caller has already decided, by the time
 * it calls this, that the current page is one it owns and is about to print
 * tags for.
 */
function arv_seo_suppress_jetpack_og() {
	remove_action( 'wp_head', 'jetpack_og_tags' );
}

/**
 * Cap the rendered width of a Photon-hosted card image.
 *
 * Race card artwork is stored in the race store at whatever size the source
 * upload happened to be, and those are logo files: 50 published races carry a
 * card image over 4MB decoded, the largest 1875x1920. The cards render them at
 * roughly 300-400px wide, so the browser was decoding a 13MB bitmap to paint a
 * thumbnail. Measured on the homepage after a full scroll, card images alone
 * accounted for 122MB of the 193MB of decoded image memory on the page, which
 * is enough on its own to get a tab killed and reloaded by Safari on iPadOS.
 *
 * Every stored URL already points at Jetpack's Photon CDN, which resizes from
 * a width parameter, so capping is a query-string rewrite rather than a
 * re-upload: fit/resize/w/h are dropped and replaced with a single w. 800 is
 * twice the widest the card is ever painted, so it stays sharp on a 2x display.
 *
 * Only for visible <img> tags. Open Graph and schema.org image URLs are
 * deliberately left at full size, since those want the largest available.
 *
 * Returns the URL untouched when it is empty or not on Photon, so a race whose
 * image is hosted anywhere else keeps working.
 *
 * @param string $url
 * @param int    $max_w
 * @return string
 */
function arv_card_image_url( $url, $max_w = 800 ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}

	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || ! preg_match( '~(^|\.)wp\.com$~i', $host ) ) {
		return $url;
	}

	$url = remove_query_arg( array( 'fit', 'resize', 'w', 'h' ), $url );

	return add_query_arg( 'w', (int) $max_w, $url );
}

/**
 * A YouTube thumbnail at card size rather than full frame.
 *
 * The film, YouTube, tour and hub stores keep YouTube's maxresdefault, a
 * 1280x720 JPEG of 165 to 390KB, and Jetpack's Photon does not resize
 * i.ytimg.com: the ?resize= URL 302s straight back to the original. So a
 * 480px card was downloading the full frame. hqdefault is the same frame
 * at 480x360, about 30KB, letterboxed to 4:3; every 16:9 card that uses
 * this crops with object-fit: cover, which removes the bars exactly. The
 * Watch store already made the same switch (see watch-store.php).
 *
 * Only for 16:9 boxes. The 16:10 Latest cards and the square podcast art
 * would show part of the letterbox, so they keep the stored URL.
 *
 * @param string $url
 * @return string
 */
function arv_youtube_card_thumb( $url ) {
	$url = (string) $url;

	if ( preg_match( '#^https?://i\.ytimg\.com/vi(?:_webp)?/([A-Za-z0-9_-]{6,})/(?:maxresdefault|sddefault)(?:_live)?\.(?:jpg|webp)$#', $url, $m ) ) {
		return 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
	}

	return $url;
}
