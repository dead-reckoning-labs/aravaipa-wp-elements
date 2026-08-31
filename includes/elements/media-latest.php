<?php
/**
 * Aravaipa Media Latest: broadcasts, films, podcast episodes and articles,
 * merged into one feed.
 *
 * The decisions live in includes/media-latest.php, which the
 * [arv_media_latest] shortcode also renders through, so placing this by
 * hand in the builder and generating a page from a script produce the
 * same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-media-latest',
	array(
		'title'   => __( 'Aravaipa Media Latest', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Latest', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
				'limit'   => cs_value( '12', 'markup' ),
				'offset'  => cs_value( '0', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_media_latest_element_builder',
		'render'  => 'arv_media_latest_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_media_latest_element_builder() {
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
					'key'     => 'limit',
					'type'    => 'text',
					'label'   => __( 'How many to show (0 for everything)', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'offset',
					'type'    => 'text',
					'label'   => __( 'Skip the first N (1 when a hero sits above this)', 'aravaipa-elements' ),
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
function arv_media_latest_element_render( $data ) {
	return arv_media_latest_render(
		array(
			'heading' => isset( $data['heading'] ) ? $data['heading'] : 'Latest',
			'intro'   => isset( $data['intro'] ) ? $data['intro'] : '',
			'limit'   => isset( $data['limit'] ) ? (int) $data['limit'] : 12,
			'offset'  => isset( $data['offset'] ) ? (int) $data['offset'] : 0,
		)
	);
}
