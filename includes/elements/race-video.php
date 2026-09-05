<?php
/**
 * One video, on a race page.
 *
 * The decisions live in includes/race-video.php, which the [arv_race_video]
 * shortcode also renders through, so placing this by hand in the builder
 * and dropping the shortcode into a page produce the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-race-video',
	array(
		'title'   => __( 'Aravaipa Race Video', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'url'        => cs_value( '', 'markup' ),
				'heading'    => cs_value( '', 'markup' ),
				'video_title' => cs_value( '', 'markup' ),
				'credit'     => cs_value( '', 'markup' ),
				'credit_url' => cs_value( '', 'markup' ),
				'caption'    => cs_value( '', 'markup' ),
				'date'       => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_race_video_element_builder',
		'render'  => 'arv_race_video_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_race_video_element_builder() {
	return array(
		'controls' => array(
			array(
				'key'       => 'url',
				'type'      => 'text',
				'label'     => __( 'YouTube URL or ID', 'aravaipa-elements' ),
				'condition' => array(),
			),
			array(
				'key'   => 'heading',
				'type'  => 'text',
				'label' => __( 'Heading (optional)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'video_title',
				'type'  => 'text',
				'label' => __( 'Title (blank to read from YouTube)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'credit',
				'type'  => 'text',
				'label' => __( 'Credit (blank to read from YouTube)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'credit_url',
				'type'  => 'text',
				'label' => __( 'Credit link (blank to read from YouTube)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'caption',
				'type'  => 'text',
				'label' => __( 'Caption (optional)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'date',
				'type'  => 'text',
				'label' => __( 'Published date, YYYY-MM-DD (enables video SEO)', 'aravaipa-elements' ),
			),
		),
	);
}

/**
 * @param array $data
 * @return string
 */
function arv_race_video_element_render( $data ) {
	return arv_race_video_render(
		array(
			'url'        => isset( $data['url'] ) ? $data['url'] : '',
			'heading'    => isset( $data['heading'] ) ? $data['heading'] : '',
			// 'title' is Cornerstone's own on every element, so the field is
			// named video_title in the builder and mapped back here.
			'title'      => isset( $data['video_title'] ) ? $data['video_title'] : '',
			'credit'     => isset( $data['credit'] ) ? $data['credit'] : '',
			'credit_url' => isset( $data['credit_url'] ) ? $data['credit_url'] : '',
			'caption'    => isset( $data['caption'] ) ? $data['caption'] : '',
			'date'       => isset( $data['date'] ) ? $data['date'] : '',
		)
	);
}
