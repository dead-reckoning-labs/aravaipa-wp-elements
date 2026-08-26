<?php
/**
 * Partner Grid.
 *
 * The "Thank you to our partners!" logo wall that closes every race page.
 * Supports tiers so title sponsors can render larger than supporting ones
 * without building a separate grid per tier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-partner-grid',
	array(
		'title'   => __( 'Aravaipa Partner Grid', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading'   => cs_value( 'Thank you to our partners!', 'markup' ),
				'rows'      => cs_value( '', 'markup' ),
				'grayscale' => cs_value( 'true', 'style' ),
			),
			'omega'
		),
		'builder' => 'arv_partner_grid_builder',
		'render'  => 'arv_partner_grid_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_partner_grid_builder() {
	return cs_compose_controls(
		array(
			'controls'    => array(
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Partners', 'aravaipa-elements' ),
					'description' => __( 'One per line: Name | logo URL | link URL | tier. Tier is title, presenting or supporting and defaults to supporting.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'grayscale',
					'type'  => 'toggle',
					'label' => __( 'Grayscale until hover', 'aravaipa-elements' ),
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
function arv_partner_grid_render( $data ) {
	$rows = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 2 );

	if ( empty( $rows ) ) {
		return '';
	}

	$heading = isset( $data['heading'] ) ? $data['heading'] : '';

	// Cornerstone toggles arrive as the strings "true"/"false" as often as
	// booleans depending on how the value was saved, so compare loosely rather
	// than trusting a bare truthiness check ("false" is a truthy string).
	$grayscale = isset( $data['grayscale'] ) ? $data['grayscale'] : true;
	$grayscale = ! ( 'false' === $grayscale || false === $grayscale || '0' === $grayscale );

	$allowed_tiers = array( 'title', 'presenting', 'supporting' );

	// Items are built before any wrapper markup. A row can be dropped here (no
	// logo URL), and if every row drops, emitting the wrapper anyway would
	// leave a "Thank you to our partners!" heading floating above nothing.
	$items = '';

	foreach ( $rows as $row ) {
		$name = arv_cell( $row, 0 );
		$logo = arv_cell( $row, 1 );
		$link = arv_cell( $row, 2 );
		$tier = strtolower( arv_cell( $row, 3, 'supporting' ) );

		if ( ! in_array( $tier, $allowed_tiers, true ) ) {
			$tier = 'supporting';
		}

		if ( '' === trim( $logo ) ) {
			continue;
		}

		// Alt text is the partner name, not "partner logo": these are the only
		// identification a screen reader gets for a sponsor whose contract
		// says their name appears on the page.
		$img = '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async" />';

		$items .= '<div class="arv-partners__item arv-partners__item--' . esc_attr( $tier ) . '">';
		$items .= arv_maybe_link( $link, $img, 'arv-partners__link', true );
		$items .= '</div>';
	}

	if ( '' === $items ) {
		return '';
	}

	$classes = 'arv-partners' . ( $grayscale ? ' arv-partners--grayscale' : '' );

	$out = '<div class="' . arv_wrapper_class( $data, $classes ) . '">';

	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-partners__heading">' . esc_html( $heading ) . '</h2>';
	}

	$out .= '<div class="arv-partners__grid">' . $items . '</div></div>';

	return $out;
}
