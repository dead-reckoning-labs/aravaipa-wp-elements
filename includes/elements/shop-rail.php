<?php
/**
 * Aravaipa Shop Rail: a light, scrollable row of shop items for a page
 * that is not itself the shop.
 *
 * The decisions live in includes/shop-store.php, which the
 * [arv_shop_rail] shortcode also renders through, so placing this by
 * hand in the builder and generating a page from a script produce the
 * same markup.
 *
 * This shipped as a shortcode only, which made it the one rail in the
 * plugin a builder could not place: every other block here registers an
 * element and a shortcode together, precisely so neither route is the
 * privileged one.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-shop-rail',
	array(
		'title'   => __( 'Aravaipa Shop Rail', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Shop', 'markup' ),
				'limit'   => cs_value( '10', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_shop_rail_element_builder',
		'render'  => 'arv_shop_rail_element_render',
	)
);

/**
 * @return array
 */
function arv_shop_rail_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array( 'key' => 'heading', 'type' => 'text', 'label' => __( 'Heading', 'aravaipa-elements' ) ),
				array( 'key' => 'limit', 'type' => 'text', 'label' => __( 'How many items', 'aravaipa-elements' ) ),
			),
		),
		'omega'
	);
}

/**
 * @param array $data
 * @return string
 */
function arv_shop_rail_element_render( $data ) {
	return arv_shop_rail_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Shop',
			'limit'   => isset( $data['limit'] ) ? (int) $data['limit'] : 10,
		)
	);
}
