<?php
/**
 * Upcoming Races.
 *
 * The homepage had no registration link on it at all. Not a weak one: zero,
 * measured against the live page. Meanwhile /races/ carries 72 races with
 * live UltraSignup links, so everything a visitor could actually buy was
 * three clicks away behind a carousel slide. This is the module that puts
 * the next few races, with dates and a real button, on the page people land
 * on.
 *
 * It also emits Event structured data for every race it shows, which is the
 * other half of the same problem: the site had no schema anywhere, so none of
 * those races were eligible for Google's event results or citable by an
 * answer engine. Doing it here rather than in a separate element means the
 * markup and the schema are generated from one row and cannot drift apart.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Name, ISO date, display date, distances, venue, location, register URL,
// race page URL, image URL. Named because the row parser counts backwards
// from it, so the two have to agree.
if ( ! defined( 'ARV_RACES_COLUMNS' ) ) {
	define( 'ARV_RACES_COLUMNS', 9 );
}

cs_register_element(
	'aravaipa-upcoming-races',
	array(
		'title'   => __( 'Aravaipa Upcoming Races', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'eyebrow'   => cs_value( 'Next up', 'markup' ),
				'heading'   => cs_value( 'Races open now', 'markup' ),
				'intro'     => cs_value( '', 'markup' ),
				'theme'     => cs_value( 'light', 'style' ),
				'columns'   => cs_value( '3', 'markup' ),
				'limit'     => cs_value( '6', 'markup' ),
				'cta_label' => cs_value( 'Register', 'markup' ),
				'all_label' => cs_value( 'See all races', 'markup' ),
				'all_url'   => cs_value( 'https://www.aravaiparunning.com/races/', 'markup' ),
				'schema'    => cs_value( 'true', 'style' ),
				'rows'      => cs_value(
					// Name | ISO date | display date | distances | venue | city, ST | register URL | race page URL | image URL
					//
					// Two date columns on purpose. The ISO one is what the
					// Event schema needs and has to be a real machine date;
					// the display one is what a runner reads, and it carries
					// things a date cannot ("September 12-13", "April -
					// September" for a series). Leave display blank and it is
					// formatted from the ISO date instead.
					"Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | 10K | 5K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://www.aravaiparunning.com/bear-chase-series/rock-hawk/ |\n",
					'markup'
				),
			),
			'omega'
		),
		'builder' => 'arv_upcoming_races_builder',
		'render'  => 'arv_upcoming_races_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_upcoming_races_builder() {
	return cs_compose_controls(
		array(
			'control_nav' => array(
				'races' => __( 'Races', 'aravaipa-elements' ),
			),
			'controls'    => array(
				array(
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'   => 'intro',
					'type'  => 'text',
					'label' => __( 'Intro line', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
					'group'   => 'races',
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
					'key'     => 'columns',
					'type'    => 'select',
					'label'   => __( 'Columns', 'aravaipa-elements' ),
					'group'   => 'races',
					'options' => array(
						'choices' => array(
							array(
								'value' => '2',
								'label' => '2',
							),
							array(
								'value' => '3',
								'label' => '3',
							),
							array(
								'value' => '4',
								'label' => '4',
							),
						),
					),
				),
				array(
					'key'         => 'limit',
					'type'        => 'text',
					'label'       => __( 'Maximum races to show', 'aravaipa-elements' ),
					'description' => __( 'Rows past this are skipped. Paste the whole season and let this pick the front of it. 0 shows every row.', 'aravaipa-elements' ),
					'group'       => 'races',
				),
				array(
					'key'   => 'cta_label',
					'type'  => 'text',
					'label' => __( 'Button label', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'   => 'all_label',
					'type'  => 'text',
					'label' => __( 'Footer link label', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'   => 'all_url',
					'type'  => 'text',
					'label' => __( 'Footer link URL', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'         => 'schema',
					'type'        => 'toggle',
					'label'       => __( 'Event structured data', 'aravaipa-elements' ),
					'description' => __( 'Emits schema.org Event JSON-LD for each race, which is what makes them eligible for Google event results and readable by AI answer engines. Turn off only if another plugin is already emitting Event schema for the same races, so they are not described twice.', 'aravaipa-elements' ),
					'group'       => 'races',
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Races', 'aravaipa-elements' ),
					'description' => __( 'One per line: Name | ISO date (2026-08-29) | display date | distances | venue | city, ST | register URL | race page URL | image URL. The ISO date is required and drives both the sort order and the structured data. Display date is optional and is for ranges a single date cannot express, like "September 12-13".', 'aravaipa-elements' ),
					'group'       => 'races',
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
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

/**
 * Turn one pipe-separated row into a race, or null if it cannot be one.
 *
 * The awkward part: distances are written the way the rest of the site writes
 * them, "50K | 25K | 10K | 5K", and that pipe is also the column separator.
 * Rather than make editors rewrite a format they already use everywhere else,
 * a full-length row is read from both ends. The first three columns and the
 * last five are fixed, so whatever is left in the middle is the distance
 * list, however many pipes it happens to contain.
 *
 * A short row (someone typing a quick one by hand and stopping early) has no
 * fixed tail to anchor against, so it falls back to plain positional reading
 * and simply cannot carry pipes in its distances.
 *
 * @param array $row Cells, already trimmed by arv_parse_rows().
 * @return array|null
 */
function arv_upcoming_races_parse_row( $row ) {
	$count = count( $row );
	$name  = trim( arv_cell( $row, 0 ) );
	$iso   = arv_upcoming_races_date( arv_cell( $row, 1 ) );

	if ( '' === $name || '' === $iso ) {
		return null;
	}

	if ( $count >= ARV_RACES_COLUMNS ) {
		$tail      = array_slice( $row, $count - 5 );
		$distances = implode( ' | ', array_slice( $row, 3, $count - 8 ) );
		return array(
			'name'      => $name,
			'iso'       => $iso,
			'display'   => trim( arv_cell( $row, 2 ) ),
			'distances' => trim( $distances ),
			'venue'     => trim( $tail[0] ),
			'location'  => trim( $tail[1] ),
			'register'  => trim( $tail[2] ),
			'page'      => trim( $tail[3] ),
			'image'     => trim( $tail[4] ),
		);
	}

	return array(
		'name'      => $name,
		'iso'       => $iso,
		'display'   => trim( arv_cell( $row, 2 ) ),
		'distances' => trim( arv_cell( $row, 3 ) ),
		'venue'     => trim( arv_cell( $row, 4 ) ),
		'location'  => trim( arv_cell( $row, 5 ) ),
		'register'  => trim( arv_cell( $row, 6 ) ),
		'page'      => trim( arv_cell( $row, 7 ) ),
		'image'     => trim( arv_cell( $row, 8 ) ),
	);
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_upcoming_races_render( $data ) {
	$rows = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 2 );

	if ( empty( $rows ) ) {
		return '';
	}

	$races = array();

	foreach ( $rows as $row ) {
		$race = arv_upcoming_races_parse_row( $row );

		// A race with no name or no usable date cannot be sorted, displayed
		// or described, so there is nothing useful to render for it.
		if ( null === $race ) {
			continue;
		}

		$races[] = $race;
	}

	if ( empty( $races ) ) {
		return '';
	}

	// Sorted here rather than trusting the paste order: the whole point of
	// this module is "what is next", and a row list maintained by hand drifts
	// out of order the first time someone inserts a race in the middle.
	usort(
		$races,
		function ( $a, $b ) {
			return strcmp( $a['iso'], $b['iso'] );
		}
	);

	$limit = isset( $data['limit'] ) ? (int) $data['limit'] : 6;
	if ( $limit > 0 ) {
		$races = array_slice( $races, 0, $limit );
	}

	$cta_label = isset( $data['cta_label'] ) && '' !== trim( $data['cta_label'] ) ? $data['cta_label'] : __( 'Register', 'aravaipa-elements' );

	$cards  = '';
	$events = array();

	foreach ( $races as $race ) {
		$display = '' !== $race['display'] ? $race['display'] : gmdate( 'F j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );

		// The card is a link to the race page when there is one, so the whole
		// tile is clickable, and a plain container when there is not. Falling
		// back to the register URL instead would send someone straight to
		// checkout for a race they have not read about yet.
		$card_url = '' !== $race['page'] ? $race['page'] : '';

		$cards .= '<div class="arv-races__card">';

		if ( '' !== $race['image'] ) {
			$cards .= '<div class="arv-races__media">';
			// Race name as alt rather than empty: unlike the region map's
			// brand marks, this image is the only thing identifying the race
			// visually, and it sits above the name rather than beside it.
			$cards .= '<img class="arv-races__img" src="' . esc_url( $race['image'] ) . '" alt="' . esc_attr( $race['name'] ) . '" loading="lazy" decoding="async" />';
			$cards .= '</div>';
		}

		$cards .= '<div class="arv-races__body">';
		// <time> rather than a bare span so the machine-readable date is in
		// the markup itself, not only in the JSON-LD below.
		$cards .= '<time class="arv-races__date" datetime="' . esc_attr( $race['iso'] ) . '">' . esc_html( $display ) . '</time>';

		$title = esc_html( $race['name'] );
		if ( '' !== $card_url ) {
			$title = '<a class="arv-races__link" href="' . esc_url( $card_url ) . '">' . $title . '</a>';
		}
		$cards .= '<h3 class="arv-races__name">' . $title . '</h3>';

		if ( '' !== $race['distances'] ) {
			$cards .= '<p class="arv-races__distances">' . esc_html( $race['distances'] ) . '</p>';
		}

		$where = array_filter( array( $race['venue'], $race['location'] ) );
		if ( ! empty( $where ) ) {
			$cards .= '<p class="arv-races__where">' . esc_html( implode( ', ', $where ) ) . '</p>';
		}

		$cards .= '<div class="arv-races__actions">';
		if ( '' !== $race['register'] ) {
			// Registration lives on ultrasignup.com, so this leaves the site.
			// noopener because target=_blank without it hands the opened page
			// a live reference back to this window.
			$cards .= '<a class="arv-races__cta" href="' . esc_url( $race['register'] ) . '" target="_blank" rel="noopener">' . esc_html( $cta_label ) . '</a>';
		}
		if ( '' !== $card_url ) {
			$cards .= '<a class="arv-races__details" href="' . esc_url( $card_url ) . '">' . esc_html( __( 'Race details', 'aravaipa-elements' ) ) . '</a>';
		}
		$cards .= '</div>';

		$cards .= '</div></div>';

		$events[] = arv_upcoming_races_event_schema( $race );
	}

	if ( '' === $cards ) {
		return '';
	}

	$theme     = ( isset( $data['theme'] ) && 'dark' === $data['theme'] ) ? 'dark' : 'light';
	$columns   = isset( $data['columns'] ) ? (int) $data['columns'] : 3;
	$columns   = in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : 3;

	// Cornerstone toggles arrive as the strings "true"/"false" as often as
	// booleans depending on how the value was saved, so compare loosely
	// rather than trusting a bare truthiness check ("false" is truthy).
	$want_schema = isset( $data['schema'] ) ? $data['schema'] : true;
	$want_schema = ! ( 'false' === $want_schema || false === $want_schema || '0' === $want_schema );

	$base = 'arv-races arv-races--' . $theme . ' arv-races--cols-' . $columns;

	$out  = '<div class="' . arv_wrapper_class( $data, $base ) . '">';
	$out .= '<div class="arv-races__inner">';

	$eyebrow = isset( $data['eyebrow'] ) ? $data['eyebrow'] : '';
	$heading = isset( $data['heading'] ) ? $data['heading'] : '';
	$intro   = isset( $data['intro'] ) ? $data['intro'] : '';

	if ( '' !== trim( $eyebrow ) ) {
		$out .= '<p class="arv-races__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-races__heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( '' !== trim( $intro ) ) {
		$out .= '<p class="arv-races__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-races__grid">' . $cards . '</div>';

	$all_url   = isset( $data['all_url'] ) ? trim( $data['all_url'] ) : '';
	$all_label = isset( $data['all_label'] ) ? trim( $data['all_label'] ) : '';
	if ( '' !== $all_url && '' !== $all_label ) {
		$out .= '<p class="arv-races__all"><a href="' . esc_url( $all_url ) . '">' . esc_html( $all_label ) . '</a></p>';
	}

	$out .= '</div>';

	if ( $want_schema && ! empty( $events ) ) {
		$out .= arv_upcoming_races_schema_block( $events );
	}

	$out .= '</div>';

	return $out;
}

/**
 * Build the schema.org Event array for one race.
 *
 * Only fields we actually have are included. An Event carrying a placeholder
 * location or an invented end date is worse than one carrying fewer fields:
 * Google validates what is there, so a wrong value is an error where a
 * missing optional value is just a missing optional value.
 *
 * @param array $race
 * @return array
 */
function arv_upcoming_races_event_schema( $race ) {
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

	if ( '' !== $race['register'] ) {
		$event['offers'] = array(
			'@type'         => 'Offer',
			'url'           => $race['register'],
			// No price: entry fees vary by distance and by how early you
			// enter, and a single wrong number in schema is worse than none.
			// availability says the thing that actually matters here, which is
			// that registration is open.
			'availability'  => 'https://schema.org/InStock',
			'category'      => 'primary',
		);
	}

	return $event;
}

/**
 * Wrap the events in a single JSON-LD script tag.
 *
 * One script holding an array rather than one per race: fewer tags for the
 * same graph, and it keeps the events grouped as what they are, a list this
 * one module is responsible for.
 *
 * @param array $events
 * @return string
 */
function arv_upcoming_races_schema_block( $events ) {
	// Wrapped in @context/@graph rather than emitted as a bare array. Without
	// the context, none of the "@type": "SportsEvent" values resolve to
	// anything: a consumer has no way to know they mean schema.org's
	// SportsEvent, so the whole block is ignored rather than misread. @graph
	// is what carries several top-level nodes under one context.
	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $events,
	);

	// JSON_UNESCAPED_SLASHES keeps the URLs readable rather than \/ escaped;
	// JSON_UNESCAPED_UNICODE keeps accented race names as themselves.
	$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if ( false === $json ) {
		return '';
	}

	// The payload is built from editor input, so it gets the same treatment
	// as any other untrusted string heading into a <script>: "</script>"
	// inside a race name would otherwise close the tag early and drop the
	// rest of the JSON into the document as markup.
	$json = str_replace( '<', '\u003C', $json );

	return '<script type="application/ld+json">' . $json . '</script>';
}
