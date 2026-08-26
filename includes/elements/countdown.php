<?php
/**
 * Countdown.
 *
 * Counts down to a race start. The target is stored as a plain local datetime
 * plus an explicit UTC offset rather than a timestamp, so an editor can type
 * "2027-02-13 07:00" and pick Arizona without thinking about epoch seconds.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-countdown',
	array(
		'title'   => __( 'Aravaipa Countdown', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading'    => cs_value( '', 'markup' ),
				'target'     => cs_value( '2027-02-13 07:00', 'markup' ),
				'offset'     => cs_value( '-07:00', 'markup' ),
				'expired'    => cs_value( 'The race is underway.', 'markup' ),
				'accent'     => cs_value( '#ff2a13', 'style' ),
			),
			'omega'
		),
		'builder' => 'arv_countdown_builder',
		'render'  => 'arv_countdown_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_countdown_builder() {
	return cs_compose_controls(
		array(
			'controls'    => array(
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'target',
					'type'        => 'text',
					'label'       => __( 'Start time', 'aravaipa-elements' ),
					'description' => __( 'Local race time as YYYY-MM-DD HH:MM, 24 hour.', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'offset',
					'type'    => 'select',
					'label'   => __( 'Time zone', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => '-07:00',
								'label' => __( 'Arizona / Mountain (no DST)', 'aravaipa-elements' ),
							),
							array(
								'value' => '-06:00',
								'label' => __( 'Mountain Daylight', 'aravaipa-elements' ),
							),
							array(
								'value' => '-08:00',
								'label' => __( 'Pacific Standard', 'aravaipa-elements' ),
							),
						),
					),
				),
				array(
					'key'   => 'expired',
					'type'  => 'text',
					'label' => __( 'Message after start', 'aravaipa-elements' ),
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
 * Renders the four units server-side as static zeros and lets the inline
 * script fill them in. That keeps the block from collapsing to zero height
 * before JS runs, which otherwise shifts the whole page on load.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_countdown_render( $data ) {
	$target = isset( $data['target'] ) ? trim( (string) $data['target'] ) : '';
	$offset = isset( $data['offset'] ) ? trim( (string) $data['offset'] ) : '-07:00';

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $target ) ) {
		return '';
	}

	if ( ! preg_match( '/^[+-]\d{2}:\d{2}$/', $offset ) ) {
		$offset = '-07:00';
	}

	$iso     = str_replace( ' ', 'T', $target ) . ':00' . $offset;
	$accent  = ! empty( $data['accent'] ) ? $data['accent'] : '#ff2a13';
	$heading = isset( $data['heading'] ) ? $data['heading'] : '';
	$expired = isset( $data['expired'] ) ? $data['expired'] : '';

	$units = array(
		'days'    => __( 'Days', 'aravaipa-elements' ),
		'hours'   => __( 'Hours', 'aravaipa-elements' ),
		'minutes' => __( 'Minutes', 'aravaipa-elements' ),
		'seconds' => __( 'Seconds', 'aravaipa-elements' ),
	);

	$out  = '<div class="' . arv_wrapper_class( $data, 'arv-countdown' ) . '"';
	$out .= ' style="--arv-accent:' . esc_attr( $accent ) . '"';
	$out .= ' data-arv-countdown="' . esc_attr( $iso ) . '"';
	$out .= ' data-arv-expired="' . esc_attr( $expired ) . '">';

	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-countdown__heading">' . esc_html( $heading ) . '</h2>';
	}

	$out .= '<div class="arv-countdown__units">';

	foreach ( $units as $key => $label ) {
		$out .= '<div class="arv-countdown__unit">';
		$out .= '<span class="arv-countdown__value" data-unit="' . esc_attr( $key ) . '">00</span>';
		$out .= '<span class="arv-countdown__label">' . esc_html( $label ) . '</span>';
		$out .= '</div>';
	}

	$out .= '</div></div>';

	return $out;
}
