<?php
/**
 * Aravaipa Film Tours: every film tour, and where each one is in its life.
 *
 * The decisions live in includes/tours-store.php, which the
 * [arv_film_tours] shortcode also renders through, so placing this by hand
 * in the builder and generating a page from a script produce the same
 * markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-film-tours',
	array(
		'title'   => __( 'Aravaipa Film Tours', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Film Tours', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_tours_element_builder',
		'render'  => 'arv_tours_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_tours_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
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
			),
		),
		'omega'
	);
}

/**
 * @param array $data
 * @return string
 */
function arv_tours_element_render( $data ) {
	return arv_tours_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Film Tours',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
		)
	);
}
