<?php
/**
 * Aravaipa Watch, one race: every edition, embedded, on its own page.
 *
 * The decisions live in includes/watch-store.php, which the [arv_watch_race]
 * shortcode also renders through, so placing this by hand in the builder and
 * generating a page from a script produce the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-watch-race',
	array(
		'title'   => __( 'Aravaipa Watch: One Race', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'race' => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_watch_race_element_builder',
		'render'  => 'arv_watch_race_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_watch_race_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'         => 'race',
					'type'        => 'text',
					'label'       => __( 'Race key', 'aravaipa-elements' ),
					'description' => __( "Mountain Outpost's own slug with the year stripped off, e.g. \"black-canyon\" for \"black-canyon-2026\".", 'aravaipa-elements' ),
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
function arv_watch_race_element_render( $data ) {
	return arv_watch_race_render(
		array(
			'race' => isset( $data['race'] ) ? $data['race'] : '',
		)
	);
}
