<?php
/**
 * Aravaipa Watch: every broadcast, live one first.
 *
 * The decisions live in includes/watch-store.php and in the functions
 * below, which the [arv_watch] shortcode also renders through, so placing
 * this by hand in the builder and generating a page from a script produce
 * the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-watch',
	array(
		'title'   => __( 'Aravaipa Watch', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Broadcasts', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
				'limit'   => cs_value( '0', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_watch_element_builder',
		'render'  => 'arv_watch_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_watch_element_builder() {
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
					'label'       => __( 'Broadcasts to show', 'aravaipa-elements' ),
					'description' => __( '0 for every one. A live broadcast is always shown whatever this is set to.', 'aravaipa-elements' ),
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
function arv_watch_element_render( $data ) {
	return arv_watch_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Broadcasts',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
			'limit'   => isset( $data['limit'] ) ? (int) $data['limit'] : 0,
		)
	);
}
