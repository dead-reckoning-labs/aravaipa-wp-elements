<?php
/**
 * Aravaipa Live: the branded live-results page, as a Cornerstone element.
 *
 * Every decision this makes lives in includes/live-page.php, which is loaded
 * unconditionally and also registers the [arv_live] shortcode. This file is
 * only the builder wrapper, so that placing one by eye and generating eighty
 * of them from a script produce exactly the same page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-live',
	array(
		'title'   => __( 'Aravaipa Live Results', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'slug'   => cs_value( '', 'markup' ),
				'height' => cs_value( '780', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_live_element_builder',
		'render'  => 'arv_live_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_live_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'         => 'slug',
					'type'        => 'text',
					'label'       => __( 'Live board slug', 'aravaipa-elements' ),
					// The slug rather than a race picker, because the board is
					// the authority on what it is timing and its slugs are not
					// derivable from a race name: Kilkenny Ridge is
					// "killeny_ridge-2026" on the board, misspelling included.
					'description' => __( 'The part after #/ in the live URL, for example black_bear-2026.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'height',
					'type'  => 'text',
					'label' => __( 'Frame height (px)', 'aravaipa-elements' ),
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
function arv_live_element_render( $data ) {
	return arv_live_page_render(
		array(
			'slug'   => isset( $data['slug'] ) ? $data['slug'] : '',
			'height' => isset( $data['height'] ) ? $data['height'] : 780,
		)
	);
}
