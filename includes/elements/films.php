<?php
/**
 * Aravaipa Films: the two YouTube playlists, one page.
 *
 * The decisions live in includes/films-store.php, which the [arv_films]
 * shortcode also renders through, so placing this by hand in the builder
 * and generating a page from a script produce the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-films',
	array(
		'title'   => __( 'Aravaipa Films', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Films', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_films_element_builder',
		'render'  => 'arv_films_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_films_element_builder() {
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
function arv_films_element_render( $data ) {
	return arv_films_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Films',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
		)
	);
}
