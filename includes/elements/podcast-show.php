<?php
/**
 * Aravaipa Podcast Show: one show's own page.
 *
 * The decisions live in includes/podcasts-store.php, which the
 * [arv_podcast_show] shortcode also renders through, so placing this by
 * hand in the builder and generating a page from a script produce the same
 * markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-podcast-show',
	array(
		'title'   => __( 'Aravaipa Podcast Show', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'show' => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_podcast_show_element_builder',
		'render'  => 'arv_podcast_show_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_podcast_show_element_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'         => 'show',
					'type'        => 'text',
					'label'       => __( 'Show key', 'aravaipa-elements' ),
					'description' => __( 'e.g. "inside-aravaipa". See arv_podcasts_shows_config().', 'aravaipa-elements' ),
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
function arv_podcast_show_element_render( $data ) {
	return arv_podcasts_show_render(
		array(
			'show' => isset( $data['show'] ) ? $data['show'] : '',
		)
	);
}
