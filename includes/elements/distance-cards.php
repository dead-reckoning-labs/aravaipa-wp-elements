<?php
/**
 * Distance Cards.
 *
 * The "Course Information" grid that appears on every race page, once per
 * distance (Black Canyon has 100K/50K, Cocodona has five, Coldwater has six).
 * Today each of those is a hand-assembled row of Cornerstone columns, which is
 * why no two race pages have quite the same spacing or stat order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-distance-cards',
	array(
		'title'   => __( 'Aravaipa Distance Cards', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading'     => cs_value( 'Course Information', 'markup' ),
				'stat_labels' => cs_value( 'Elevation Gain, Cutoff, Start Time', 'markup' ),
				'rows'        => cs_value( "100K | 8,000 ft | 20 hrs | 7:00 AM | https://ultrasignup.com\n50K | 3,500 ft | 11 hrs | 8:00 AM | https://ultrasignup.com", 'markup' ),
				'cta_label'   => cs_value( 'Register', 'markup' ),
				'columns'     => cs_value( '3', 'markup' ),
				'accent'      => cs_value( '#ff2a13', 'style' ),
			),
			'omega'
		),
		'builder' => 'arv_distance_cards_builder',
		'render'  => 'arv_distance_cards_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_distance_cards_builder() {
	return cs_compose_controls(
		array(
			'controls'    => array(
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'stat_labels',
					'type'        => 'text',
					'label'       => __( 'Stat labels', 'aravaipa-elements' ),
					'description' => __( 'Comma separated. These label the stat columns in every card.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Distances', 'aravaipa-elements' ),
					'description' => __( 'One distance per line: Name | stat | stat | stat | register URL. The URL is optional.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'cta_label',
					'type'  => 'text',
					'label' => __( 'Button label', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'columns',
					'type'    => 'select',
					'label'   => __( 'Columns', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => '2',
								'label' => __( '2', 'aravaipa-elements' ),
							),
							array(
								'value' => '3',
								'label' => __( '3', 'aravaipa-elements' ),
							),
							array(
								'value' => '4',
								'label' => __( '4', 'aravaipa-elements' ),
							),
						),
					),
				),
				array(
					'key'   => 'accent',
					'type'  => 'color',
					'label' => __( 'Accent', 'aravaipa-elements' ),
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_distance_cards_render( $data ) {
	$rows   = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 1 );
	$labels = arv_parse_list( isset( $data['stat_labels'] ) ? $data['stat_labels'] : '' );

	if ( empty( $rows ) ) {
		return '';
	}

	$accent    = ! empty( $data['accent'] ) ? $data['accent'] : '#ff2a13';
	$columns   = isset( $data['columns'] ) ? (int) $data['columns'] : 3;
	$columns   = ( $columns >= 2 && $columns <= 4 ) ? $columns : 3;
	$cta_label = ! empty( $data['cta_label'] ) ? $data['cta_label'] : __( 'Register', 'aravaipa-elements' );
	$heading   = isset( $data['heading'] ) ? $data['heading'] : '';

	$out  = '<div class="' . arv_wrapper_class( $data, 'arv-distances' ) . '"';
	$out .= ' style="--arv-accent:' . esc_attr( $accent ) . ';--arv-cols:' . $columns . '">';

	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-distances__heading">' . esc_html( $heading ) . '</h2>';
	}

	$out .= '<div class="arv-distances__grid">';

	foreach ( $rows as $row ) {
		// Last cell is the register URL only when it actually looks like one.
		// Without this check a race with no registration link yet would render
		// its final stat (say "7:00 AM") as the button target.
		$url        = '';
		$stat_cells = array_slice( $row, 1 );
		$last       = end( $stat_cells );

		if ( false !== $last && preg_match( '#^(https?://|/)#i', $last ) ) {
			$url = $last;
			array_pop( $stat_cells );
		}

		$out .= '<div class="arv-distance">';
		$out .= '<div class="arv-distance__name">' . esc_html( arv_cell( $row, 0 ) ) . '</div>';

		if ( ! empty( $stat_cells ) ) {
			$out .= '<dl class="arv-distance__stats">';
			foreach ( $stat_cells as $i => $stat ) {
				if ( '' === trim( $stat ) ) {
					continue;
				}
				$label = isset( $labels[ $i ] ) ? $labels[ $i ] : '';
				$out  .= '<div class="arv-distance__stat">';
				$out  .= '<dt>' . esc_html( $label ) . '</dt>';
				$out  .= '<dd>' . esc_html( $stat ) . '</dd>';
				$out  .= '</div>';
			}
			$out .= '</dl>';
		}

		if ( '' !== $url ) {
			$out .= arv_maybe_link( $url, esc_html( $cta_label ), 'arv-distance__cta', true );
		}

		$out .= '</div>';
	}

	$out .= '</div></div>';

	return $out;
}
