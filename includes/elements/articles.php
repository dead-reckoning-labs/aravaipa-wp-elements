<?php
/**
 * Aravaipa Articles: the blog archive, as the same grid the rest of Media
 * uses.
 *
 * The decisions live in includes/articles-store.php, which the
 * [arv_articles] shortcode also renders through, so placing this by hand
 * in the builder and generating a page from a script produce the same
 * markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-articles',
	array(
		'title'   => __( 'Aravaipa Articles', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Articles', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
				'limit'   => cs_value( '0', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_articles_element_builder',
		'render'  => 'arv_articles_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_articles_element_builder() {
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
					'key'   => 'limit',
					'type'  => 'text',
					'label' => __( 'How many to show (0 for the whole archive)', 'aravaipa-elements' ),
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
function arv_articles_element_render( $data ) {
	return arv_articles_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Articles',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
			'limit'   => isset( $data['limit'] ) ? (int) $data['limit'] : 0,
		)
	);
}
