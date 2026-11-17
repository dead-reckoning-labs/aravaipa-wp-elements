<?php
/**
 * Aravaipa Volunteer List: every upcoming race with one Volunteer button.
 *
 * The rows, the RaceGoat or iVolunteer decision and the markup all live in
 * includes/volunteer-list.php, which the [aravaipa_volunteer_list]
 * shortcode renders through too, so both routes give the same list.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-volunteer-list',
	array(
		'title'   => __( 'Aravaipa Volunteer List', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( '', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_vol_element_builder',
		'render'  => 'arv_vol_element_render',
	)
);

/**
 * @return array
 */
function arv_vol_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array( 'key' => 'heading', 'type' => 'text', 'label' => __( 'Heading (optional)', 'aravaipa-elements' ) ),
				array( 'key' => 'intro', 'type' => 'text', 'label' => __( 'Intro (optional)', 'aravaipa-elements' ) ),
			),
		),
		'omega'
	);
}

/**
 * @param array $data
 * @return string
 */
function arv_vol_element_render( $data ) {
	if ( ! function_exists( 'arv_vol_render' ) ) {
		return '';
	}
	return arv_vol_render(
		array(
			'heading' => isset( $data['heading'] ) ? (string) $data['heading'] : '',
			'intro'   => isset( $data['intro'] ) ? (string) $data['intro'] : '',
		)
	);
}
