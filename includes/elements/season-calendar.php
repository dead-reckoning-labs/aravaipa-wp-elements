<?php
/**
 * Season Calendar.
 *
 * /races/ used one table for two different jobs: "what can I enter right
 * now" and "what does Aravaipa's year look like." Those need different
 * treatment. Aravaipa Upcoming Races (with "Only show confirmed races" left
 * on) is the first job. This element is the second: a full-season reference
 * that lists every race regardless of whether next year's registration has
 * been set up yet, with no Register button pretending otherwise.
 *
 * The problem this replaces was concrete, not stylistic: 46 of 72 races on
 * the live page were already-run 2026 events still showing a live "Register"
 * button months after they finished, because UltraSignup's listing for a
 * recurring race persists until someone rolls it to the next year. This
 * element never claims a race is open. It only ever offers Race Details.
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
 * A row's month and day, independent of the year on it.
 *
 * Roughly half this calendar's rows carry a year that is really a guess
 * (see arv_upcoming_races_action's $confirmed handling), because most
 * recurring races have not had next year's UltraSignup listing created yet.
 * A guessed year is still the right month and day, since that part comes
 * straight off the site's own listing, not off UltraSignup at all. Grouping
 * on month/day rather than the full date is what lets this ignore that guess
 * entirely and still produce a correct January-to-December calendar.
 *
 * @param string $iso Y-m-d.
 * @return string m-d, for sorting and grouping.
 */
function arv_season_calendar_month_day( $iso ) {
	return substr( $iso, 5 );
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_season_calendar_render( $data ) {
	$rows = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 2 );

	if ( empty( $rows ) ) {
		return '';
	}

	$races = array();
	foreach ( $rows as $row ) {
		$race = arv_upcoming_races_parse_row( $row );
		if ( null !== $race ) {
			$races[] = $race;
		}
	}

	if ( empty( $races ) ) {
		return '';
	}

	// Sorted and grouped by month/day, not the literal date: see
	// arv_season_calendar_month_day() for why a guessed year cannot be
	// allowed to reorder the calendar.
	usort(
		$races,
		function ( $a, $b ) {
			return strcmp( arv_season_calendar_month_day( $a['iso'] ), arv_season_calendar_month_day( $b['iso'] ) );
		}
	);

	$months = array();
	foreach ( $races as $race ) {
		$month_num           = (int) substr( $race['iso'], 5, 2 );
		$months[ $month_num ] = isset( $months[ $month_num ] ) ? $months[ $month_num ] : array();
		$months[ $month_num ][] = $race;
	}

	$rows_html = '';
	foreach ( $months as $month_num => $month_races ) {
		$rows_html .= '<div class="arv-calendar__month">';
		$rows_html .= '<h3 class="arv-calendar__month-name">' . esc_html( gmdate( 'F', mktime( 0, 0, 0, $month_num, 1 ) ) ) . '</h3>';
		$rows_html .= '<div class="arv-calendar__rows">';

		foreach ( $month_races as $race ) {
			$display = '' !== $race['display'] ? $race['display'] : gmdate( 'j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );
			// The display date already carries a year some of the time
			// ("December 28-January 3"); the day-of-month alone is what a
			// calendar row needs, since the month heading above already
			// says which month this is.
			$day_only = preg_replace( '/^[A-Za-z]+\s+/', '', $display );

			$rows_html .= '<a class="arv-calendar__row" href="' . esc_url( '' !== $race['page'] ? $race['page'] : $race['register'] ) . '">';

			if ( '' !== $race['image'] ) {
				$rows_html .= '<span class="arv-calendar__logo"><img src="' . esc_url( $race['image'] ) . '" alt="" loading="lazy" decoding="async" /></span>';
			}

			$rows_html .= '<span class="arv-calendar__day">' . esc_html( $day_only ) . '</span>';
			$rows_html .= '<span class="arv-calendar__body">';
			$rows_html .= '<span class="arv-calendar__name">' . esc_html( $race['name'] ) . '</span>';
			if ( '' !== $race['distances'] ) {
				$rows_html .= '<span class="arv-calendar__distances">' . esc_html( $race['distances'] ) . '</span>';
			}
			$where = array_filter( array( $race['venue'], $race['location'] ) );
			if ( ! empty( $where ) ) {
				$rows_html .= '<span class="arv-calendar__where">' . esc_html( implode( ', ', $where ) ) . '</span>';
			}
			$rows_html .= '</span>';

			if ( ! $race['confirmed'] ) {
				// The one thing this element exists to say honestly: this is
				// not open yet. Not a Register button aimed at last year's
				// page, not silence either.
				$rows_html .= '<span class="arv-calendar__status">' . esc_html( __( 'Details soon', 'aravaipa-elements' ) ) . '</span>';
			}

			$rows_html .= '<span class="arv-calendar__arrow" aria-hidden="true">&rarr;</span>';
			$rows_html .= '</a>';
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
	$out .= '</div></div>';

	return $out;
}
