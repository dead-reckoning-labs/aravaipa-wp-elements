<?php
/**
 * Aravaipa Shop: the Square catalogue, on this site.
 *
 * The decisions live in includes/shop-store.php, which the [arv_shop] and
 * [arv_race_merch] shortcodes also render through, so placing this by hand
 * in the builder and generating a page from a script produce the same
 * markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-shop',
	array(
		'title'   => __( 'Aravaipa Shop', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Shop', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_shop_element_builder',
		'render'  => 'arv_shop_element_render',
	)
);

cs_register_element(
	'aravaipa-race-merch',
	array(
		'title'   => __( 'Aravaipa Race Merch', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'race'    => cs_value( '', 'markup' ),
				'limit'   => cs_value( '4', 'markup' ),
				'heading' => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_shop_merch_element_builder',
		'render'  => 'arv_shop_merch_element_render',
	)
);

/**
 * @return array
 */
function arv_shop_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array( 'key' => 'heading', 'type' => 'text', 'label' => __( 'Heading', 'aravaipa-elements' ) ),
				array( 'key' => 'intro', 'type' => 'text', 'label' => __( 'Intro (optional)', 'aravaipa-elements' ) ),
			),
		),
		'omega'
	);
}

/**
 * @return array
 */
function arv_shop_merch_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'   => 'race',
					'type'  => 'text',
					'label' => __( 'Race name (matched the same way Results and Watch match one)', 'aravaipa-elements' ),
				),
				array( 'key' => 'limit', 'type' => 'text', 'label' => __( 'How many products', 'aravaipa-elements' ) ),
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading (defaults to "<race> gear")', 'aravaipa-elements' ),
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
function arv_shop_element_render( $data ) {
	return arv_shop_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Shop',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
		)
	);
}

/**
 * @param array $data
 * @return string
 */
function arv_shop_merch_element_render( $data ) {
	return arv_shop_race_merch_render(
		array(
			'race'    => isset( $data['race'] ) ? $data['race'] : '',
			'limit'   => isset( $data['limit'] ) ? (int) $data['limit'] : 4,
			'heading' => isset( $data['heading'] ) ? $data['heading'] : '',
		)
	);
}
