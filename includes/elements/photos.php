<?php
/**
 * Aravaipa Photos: a card per race, with the gallery's own cover on it.
 *
 * The decisions live in includes/photos-store.php, which the [arv_photos]
 * shortcode also renders through, so placing this by hand in the builder
 * and generating a page from a script produce the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-photos',
	array(
		'title'   => __( 'Aravaipa Photos', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Photos', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
				'year'    => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_photos_element_builder',
		'render'  => 'arv_photos_element_render',
	)
);

/**
 * @return array
 */
function arv_photos_element_builder() {
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
				array(
					'key'     => 'year',
					'type'    => 'text',
					'label'   => __( 'Year (optional)', 'aravaipa-elements' ),
					'tooltip' => __( 'Pin this block to one year, for a per-year page. Leave empty to show every year with a filter.', 'aravaipa-elements' ),
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
function arv_photos_element_render( $data ) {
	return arv_photos_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Photos',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
			'year'    => isset( $data['year'] ) ? $data['year'] : '',
		)
	);
}
