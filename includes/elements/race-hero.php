<?php
/**
 * Race Hero.
 *
 * The date / race name / location lockup that opens every race page, plus the
 * registration status and call to action. Currently rebuilt per page out of a
 * background section, two text elements and a button.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-race-hero',
	array(
		'title'   => __( 'Aravaipa Race Hero', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'date_line'  => cs_value( 'February 13-14, 2027', 'markup' ),
				'race_name'  => cs_value( 'Black Canyon Ultras', 'markup' ),
				'location'   => cs_value( 'Black Canyon City, Arizona', 'markup' ),
				'status'     => cs_value( 'open', 'markup' ),
				'status_note'=> cs_value( '', 'markup' ),
				'cta_label'  => cs_value( 'Register', 'markup' ),
				'cta_url'    => cs_value( '', 'markup' ),
				'image'      => cs_value( '', 'markup' ),
				'overlay'    => cs_value( '0.55', 'style' ),
				'accent'     => cs_value( '#ff2a13', 'style' ),
				'align'      => cs_value( 'center', 'style' ),
			),
			'omega'
		),
		'builder' => 'arv_race_hero_builder',
		'render'  => 'arv_race_hero_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_race_hero_builder() {
	return cs_compose_controls(
		array(
			'controls'    => array(
				array(
					'key'   => 'date_line',
					'type'  => 'text',
					'label' => __( 'Date line', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'race_name',
					'type'  => 'text',
					'label' => __( 'Race name', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'location',
					'type'  => 'text',
					'label' => __( 'Location', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'status',
					'type'    => 'select',
					'label'   => __( 'Registration status', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => 'open',
								'label' => __( 'Open', 'aravaipa-elements' ),
							),
							array(
								'value' => 'lottery',
								'label' => __( 'Lottery', 'aravaipa-elements' ),
							),
							array(
								'value' => 'waitlist',
								'label' => __( 'Waitlist', 'aravaipa-elements' ),
							),
							array(
								'value' => 'closed',
								'label' => __( 'Closed', 'aravaipa-elements' ),
							),
							array(
								'value' => 'none',
								'label' => __( 'Hide badge', 'aravaipa-elements' ),
							),
						),
					),
				),
				array(
					'key'         => 'status_note',
					'type'        => 'text',
					'label'       => __( 'Status note', 'aravaipa-elements' ),
					'description' => __( 'Optional, shown next to the badge. e.g. "Opens Sept 1".', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'cta_label',
					'type'  => 'text',
					'label' => __( 'Button label', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'cta_url',
					'type'  => 'text',
					'label' => __( 'Button URL', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'image',
					'type'  => 'image',
					'label' => __( 'Background image', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'overlay',
					'type'    => 'text',
					'label'   => __( 'Overlay opacity', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'accent',
					'type'  => 'color',
					'label' => __( 'Accent', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'align',
					'type'    => 'select',
					'label'   => __( 'Alignment', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => 'left',
								'label' => __( 'Left', 'aravaipa-elements' ),
							),
							array(
								'value' => 'center',
								'label' => __( 'Center', 'aravaipa-elements' ),
							),
						),
					),
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * Human-readable label for each registration status.
 *
 * @param string $status Status key.
 * @return string
 */
function arv_hero_status_label( $status ) {
	$labels = array(
		'open'     => __( 'Registration Open', 'aravaipa-elements' ),
		'lottery'  => __( 'Lottery', 'aravaipa-elements' ),
		'waitlist' => __( 'Waitlist', 'aravaipa-elements' ),
		'closed'   => __( 'Registration Closed', 'aravaipa-elements' ),
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_race_hero_render( $data ) {
	$accent  = ! empty( $data['accent'] ) ? $data['accent'] : '#ff2a13';
	$align   = ( isset( $data['align'] ) && 'left' === $data['align'] ) ? 'left' : 'center';
	$image   = isset( $data['image'] ) ? trim( (string) $data['image'] ) : '';
	$status  = isset( $data['status'] ) ? $data['status'] : 'none';

	// Clamp rather than trust: an overlay above 1 renders an opaque black box
	// over the photo, which looks like a broken image rather than a dark hero.
	$overlay = isset( $data['overlay'] ) ? (float) $data['overlay'] : 0.55;
	$overlay = max( 0, min( 1, $overlay ) );

	$style  = '--arv-accent:' . esc_attr( $accent ) . ';--arv-overlay:' . esc_attr( (string) $overlay ) . ';';
	$classes = arv_wrapper_class( $data, 'arv-hero arv-hero--' . $align );

	if ( '' !== $image ) {
		$classes .= ' arv-hero--image';
		$style   .= "--arv-hero-image:url('" . esc_url( $image ) . "');";
	}

	$out  = '<div class="' . $classes . '" style="' . $style . '">';
	$out .= '<div class="arv-hero__inner">';

	if ( ! empty( $data['date_line'] ) ) {
		$out .= '<p class="arv-hero__date">' . esc_html( $data['date_line'] ) . '</p>';
	}

	if ( ! empty( $data['race_name'] ) ) {
		$out .= '<h1 class="arv-hero__name">' . esc_html( $data['race_name'] ) . '</h1>';
	}

	if ( ! empty( $data['location'] ) ) {
		$out .= '<p class="arv-hero__location">' . esc_html( $data['location'] ) . '</p>';
	}

	$status_label = arv_hero_status_label( $status );
	$status_note  = isset( $data['status_note'] ) ? trim( (string) $data['status_note'] ) : '';

	if ( '' !== $status_label || '' !== $status_note ) {
		$out .= '<p class="arv-hero__status">';
		if ( '' !== $status_label ) {
			$out .= '<span class="arv-hero__badge arv-hero__badge--' . esc_attr( $status ) . '">' . esc_html( $status_label ) . '</span>';
		}
		if ( '' !== $status_note ) {
			$out .= '<span class="arv-hero__note">' . esc_html( $status_note ) . '</span>';
		}
		$out .= '</p>';
	}

	if ( ! empty( $data['cta_url'] ) && ! empty( $data['cta_label'] ) ) {
		$out .= arv_maybe_link( $data['cta_url'], esc_html( $data['cta_label'] ), 'arv-hero__cta', true );
	}

	$out .= '</div></div>';

	return $out;
}
