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
				'eyebrow'  => cs_value( 'Every race', 'markup' ),
				'heading'  => cs_value( 'Results', 'markup' ),
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
