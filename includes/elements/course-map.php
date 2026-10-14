<?php
/**
 * One distance's course map, on a race page.
 *
 * The decisions live in includes/course-map.php, which the [arv_course_map]
 * shortcode also renders through, so the builder element and the shortcode
 * produce the same markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-course-map',
	array(
		'title'   => __( 'Aravaipa Course Map', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'caltopo' => cs_value( '', 'markup' ),
				'line'    => cs_value( '', 'markup' ),
				'name'    => cs_value( '', 'markup' ),
				'pdf'     => cs_value( '', 'markup' ),
				'logo'    => cs_value( '', 'markup' ),
				'gain'    => cs_value( '', 'markup' ),
				'aid'     => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_course_map_element_builder',
		'render'  => 'arv_course_map_element_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_course_map_element_builder() {
	return array(
		'controls' => array(
			array(
				'key'   => 'caltopo',
				'type'  => 'text',
				'label' => __( 'CalTopo map link or ID', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'line',
				'type'  => 'text',
				'label' => __( 'Line name in CalTopo (blank for the longest)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'name',
				'type'  => 'text',
				'label' => __( 'Distance name (blank to use the line name)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'pdf',
				'type'  => 'text',
				'label' => __( 'Course map PDF link (optional)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'logo',
				'type'  => 'text',
				'label' => __( 'Race logo for the start pin (optional)', 'aravaipa-elements' ),
			),
			array(
				'key'   => 'gain',
				'type'  => 'text',
				'label' => __( 'Published elevation gain in feet (blank to measure)', 'aravaipa-elements' ),
			),
			array(
				'key'         => 'aid',
				'type'        => 'textarea',
				'label'       => __( 'Aid stations', 'aravaipa-elements' ),
				'description' => __( 'Optional. One per line: CalTopo marker title | Display name | Cutoff | Services', 'aravaipa-elements' ),
			),
		),
	);
}

/**
 * @param array $data
 * @return string
 */
function arv_course_map_element_render( $data ) {
	$a = array();
	foreach ( array( 'caltopo', 'line', 'name', 'pdf', 'logo', 'aid', 'gain' ) as $k ) {
		$a[ $k ] = isset( $data[ $k ] ) ? $data[ $k ] : '';
	}

	return arv_course_map_render( $a );
}
