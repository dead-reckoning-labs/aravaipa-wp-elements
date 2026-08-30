<?php
/**
 * Aravaipa Media Sub-nav: the Watch / Films / Podcasts / Photos / Articles
 * strip that goes at the top of each of those pages.
 *
 * The decisions live in includes/media-subnav.php, which the
 * [arv_media_subnav] shortcode also renders through, so placing this by
 * hand in the builder and generating a page from a script produce the
 * same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-media-subnav',
	array(
		'title'   => __( 'Aravaipa Media Sub-nav', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'current' => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_media_subnav_element_builder',
		'render'  => 'arv_media_subnav_element_render',
	)
);

/**
 * @return array
 */
function arv_media_subnav_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'     => 'current',
					'type'    => 'select',
					'label'   => __( 'Current section', 'aravaipa-elements' ),
					'value'   => '',
					'options' => array(
						''         => __( '(none)', 'aravaipa-elements' ),
						'watch'    => __( 'Broadcasts', 'aravaipa-elements' ),
						'films'    => __( 'Films', 'aravaipa-elements' ),
						'podcasts' => __( 'Podcasts', 'aravaipa-elements' ),
						'photos'   => __( 'Photos', 'aravaipa-elements' ),
						'articles' => __( 'Articles', 'aravaipa-elements' ),
					),
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
function arv_media_subnav_element_render( $data ) {
	return arv_media_subnav_render( isset( $data['current'] ) ? (string) $data['current'] : '' );
}
