<?php
/**
 * Aravaipa Media Hub: cards for Watch, Films, Podcasts, Photos and the blog.
 *
 * The decisions live in includes/media-hub.php, which the [arv_media_hub]
 * shortcode also renders through, so placing this by hand in the builder
 * and generating a page from a script produce the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-media-hub',
	array(
		'title'   => __( 'Aravaipa Media Hub', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Media', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_media_hub_element_builder',
		'render'  => 'arv_media_hub_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_media_hub_element_builder() {
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
function arv_media_hub_element_render( $data ) {
	return arv_media_hub_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Media',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
		)
	);
}
