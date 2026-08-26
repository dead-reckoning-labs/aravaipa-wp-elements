<?php
/**
 * Race Status.
 *
 * One race's current state, for that race's own page. Drop it on
 * /blackcanyon/ and it finds the Black Canyon record by matching the page's
 * URL against the stored race, with nothing to type and nothing to keep in
 * sync when the race is renamed.
 *
 * This is the reason the store was worth building. Before it, every race page
 * on the site hand-wrote its own registration button, which is why some of
 * them still point at a registration that closed months ago: there was no
 * single place that knew a race's state, so each page kept its own answer and
 * they drifted apart. Now the homepage, the calendar and the race's own page
 * all read the same record and change together.
 *
 * Runs through arv_upcoming_races_action(), the same phase logic the other
 * two elements use, so all three say the same thing at the same moment by
 * construction rather than by three implementations agreeing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-race-status',
	array(
		'title'   => __( 'Aravaipa Race Status', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				// Blank means "work it out from the page this is on", which
				// is the whole point. A slug is only needed to show one
				// race's status somewhere that is not its own page.
				'race_page' => cs_value( '', 'markup' ),
				'show_date' => cs_value( 'true', 'style' ),
				'theme'     => cs_value( 'light', 'style' ),
			),
			'omega'
		),
		'builder' => 'arv_race_status_builder',
		'render'  => 'arv_race_status_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_race_status_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'         => 'race_page',
					'type'        => 'text',
					'label'       => __( 'Race page URL (optional)', 'aravaipa-elements' ),
					'description' => __( 'Leave blank on a race\'s own page and it finds that race automatically. Set it to show a different race\'s status somewhere else.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'show_date',
					'type'  => 'toggle',
					'label' => __( 'Show the date', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
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
function arv_race_status_render( $data ) {
	if ( ! function_exists( 'arv_race_store_find_by_page' ) ) {
		return '';
	}

	$race = arv_race_store_find_by_page( isset( $data['race_page'] ) ? trim( $data['race_page'] ) : '' );

	// No matching record is not an error worth shouting about on a live race
	// page. Rendering nothing leaves whatever the page already had, which is
	// the safe outcome during a migration.
	if ( null === $race ) {
		return '';
	}

	$today  = arv_upcoming_races_today();
	$action = arv_upcoming_races_action( $race, $today );

	$theme = ( isset( $data['theme'] ) && 'dark' === $data['theme'] ) ? 'dark' : 'light';

	$show_date = isset( $data['show_date'] ) ? $data['show_date'] : true;
	$show_date = ! ( 'false' === $show_date || false === $show_date || '0' === $show_date );

	$base = 'arv-status arv-status--' . $theme;

	$out = '<div class="' . arv_wrapper_class( $data, $base ) . '">';

	if ( $show_date ) {
		$display = '' !== $race['display'] ? $race['display'] : gmdate( 'F j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );
		$out    .= '<time class="arv-status__date" datetime="' . esc_attr( $race['iso'] ) . '">' . esc_html( $display ) . '</time>';
	}

	if ( '' !== $action['url'] ) {
		$out .= '<a class="arv-status__cta arv-status__cta--' . esc_attr( $action['phase'] ) . '" href="'
			. esc_url( $action['url'] ) . '" target="_blank" rel="noopener">'
			. esc_html( $action['label'] ) . '</a>';
	} else {
		// Entries closed with nowhere to send anyone: a label, not a button,
		// for the same reason as on the cards.
		$out .= '<span class="arv-status__cta arv-status__cta--' . esc_attr( $action['phase'] ) . '">'
			. esc_html( $action['label'] ) . '</span>';
	}

	if ( '' !== $race['closes'] && 'upcoming' === $action['phase'] ) {
		$out .= '<span class="arv-status__note">'
			. esc_html( sprintf( __( 'Entries close %s', 'aravaipa-elements' ), gmdate( 'F j', strtotime( $race['closes'] . ' 00:00:00 UTC' ) ) ) )
			. '</span>';
	}

	$out .= '</div>';

	return $out;
}
