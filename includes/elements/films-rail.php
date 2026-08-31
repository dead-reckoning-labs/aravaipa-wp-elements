<?php
/**
 * Aravaipa Films Rail: a light, scrollable row of films for a page that
 * is not itself a Media page.
 *
 * The decisions live in includes/films-store.php, which the
 * [arv_films_rail] shortcode also renders through, so placing this by
 * hand in the builder and generating a page from a script produce the
 * same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-films-rail',
	array(
		'title'   => __( 'Aravaipa Films Rail', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Aravaipa Running Films', 'markup' ),
				'limit'   => cs_value( '12', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_films_rail_element_builder',
		'render'  => 'arv_films_rail_element_render',
	)
);

/**
 * @return array
 */
function arv_films_rail_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array( 'key' => 'heading', 'type' => 'text', 'label' => __( 'Heading', 'aravaipa-elements' ) ),
				array( 'key' => 'limit', 'type' => 'text', 'label' => __( 'How many films', 'aravaipa-elements' ) ),
			),
		),
		'omega'
	);
}

/**
 * @param array $data
 * @return string
 */
function arv_films_rail_element_render( $data ) {
	return arv_films_rail_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Aravaipa Running Films',
			'limit'   => isset( $data['limit'] ) ? (int) $data['limit'] : 12,
		)
	);
}
