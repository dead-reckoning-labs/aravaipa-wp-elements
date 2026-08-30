<?php
/**
 * Aravaipa Podcasts: a card per show, and every episode merged.
 *
 * The decisions live in includes/podcasts-store.php, which the
 * [arv_podcasts] shortcode also renders through, so placing this by hand in
 * the builder and generating a page from a script produce the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-podcasts',
	array(
		'title'   => __( 'Aravaipa Podcasts', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Podcasts', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
				'limit'   => cs_value( '0', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_podcasts_element_builder',
		'render'  => 'arv_podcasts_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_podcasts_element_builder() {
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
					'key'         => 'limit',
					'type'        => 'text',
					'label'       => __( 'Episodes to show', 'aravaipa-elements' ),
					'description' => __( '0 for every one.', 'aravaipa-elements' ),
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
function arv_podcasts_element_render( $data ) {
	return arv_podcasts_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Podcasts',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
			'limit'   => isset( $data['limit'] ) ? (int) $data['limit'] : 0,
		)
	);
}
