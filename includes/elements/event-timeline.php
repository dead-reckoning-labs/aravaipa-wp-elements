<?php
/**
 * Event Timeline.
 *
 * The "Event Timeline" / "Start Times" / "Bib Pickup" schedule blocks. Rows
 * are grouped under their day heading, so one element covers a whole race
 * weekend instead of one table per day.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-event-timeline',
	array(
		'title'   => __( 'Aravaipa Event Timeline', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Event Timeline', 'markup' ),
				'rows'    => cs_value( "Friday, February 12 | 4:00 PM | Packet pickup opens\nFriday, February 12 | 7:00 PM | Mandatory pre-race briefing\nSaturday, February 13 | 7:00 AM | 100K start", 'markup' ),
				'accent'  => cs_value( '#ff2a13', 'style' ),
			),
			'omega'
		),
		'builder' => 'arv_event_timeline_builder',
		'render'  => 'arv_event_timeline_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_event_timeline_builder() {
	return cs_compose_controls(
		array(
			'controls'    => array(
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Schedule', 'aravaipa-elements' ),
					'description' => __( 'One entry per line: Day | Time | What happens. Consecutive entries sharing a day are grouped under one heading.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'accent',
					'type'  => 'color',
					'label' => __( 'Accent', 'aravaipa-elements' ),
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_event_timeline_render( $data ) {
	$rows = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 2 );

	if ( empty( $rows ) ) {
		return '';
	}

	$accent  = ! empty( $data['accent'] ) ? $data['accent'] : '#ff2a13';
	$heading = isset( $data['heading'] ) ? $data['heading'] : '';

	// Group by day in source order rather than sorting. The schedule is
	// already written in the order it happens, and parsing "Friday, February
	// 12" into a sortable date would guess at a year the copy never states.
	$groups  = array();
	$current = null;

	foreach ( $rows as $row ) {
		$day = arv_cell( $row, 0 );
		if ( null === $current || $current['day'] !== $day ) {
			if ( null !== $current ) {
				$groups[] = $current;
			}
			$current = array(
				'day'     => $day,
				'entries' => array(),
			);
		}
		$current['entries'][] = array(
			'time' => arv_cell( $row, 1 ),
			'what' => arv_cell( $row, 2 ),
		);
	}

	if ( null !== $current ) {
		$groups[] = $current;
	}

	$out  = '<div class="' . arv_wrapper_class( $data, 'arv-timeline' ) . '"';
	$out .= ' style="--arv-accent:' . esc_attr( $accent ) . '">';

	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-timeline__heading">' . esc_html( $heading ) . '</h2>';
	}

	foreach ( $groups as $group ) {
		$out .= '<div class="arv-timeline__day">';
		if ( '' !== trim( $group['day'] ) ) {
			$out .= '<h3 class="arv-timeline__day-label">' . esc_html( $group['day'] ) . '</h3>';
		}
		$out .= '<ul class="arv-timeline__entries">';
		foreach ( $group['entries'] as $entry ) {
			$out .= '<li class="arv-timeline__entry">';
			$out .= '<span class="arv-timeline__time">' . esc_html( $entry['time'] ) . '</span>';
			$out .= '<span class="arv-timeline__what">' . esc_html( $entry['what'] ) . '</span>';
			$out .= '</li>';
		}
		$out .= '</ul></div>';
	}

	$out .= '</div>';

	return $out;
}
