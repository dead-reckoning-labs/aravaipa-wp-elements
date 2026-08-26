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
