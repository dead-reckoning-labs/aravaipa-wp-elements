<?php
/**
 * Region Map.
 *
 * Replaces the "Where to find us" block: a flat US choropleth with circular
 * monogram pins that read like a Slack avatar list rather than a race map.
 * This version is a single vendored outline (assets/us-outline.svg, 48
 * states, no fill differences between them) with real pins positioned by
 * percentage and linked straight to each region's landing page.
 *
 * No JavaScript. Pins are anchors and the hover/focus label is pure CSS, so
 * the whole thing is keyboard reachable and works with JS disabled, unlike a
 * tooltip that only appears from a mouseover handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-region-map',
	array(
		'title'   => __( 'Aravaipa Region Map', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'heading' => cs_value( 'Where to find us', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
				'rows'    => cs_value(
					"Arizona | 20.9 | 61.9 | https://www.aravaiparunning.com/arizona/ | Southwest roots. Home of Cocodona 250, Javelina Jundred, Black Canyon 100K, and more. | primary\n" .
					"Tucson | 23 | 68.9 | https://www.aravaiparunning.com/tucson-runs/ | \n" .
					// California and Nevada's real state centers sit about
					// 25 miles apart, close enough on a page-width US map
					// that their labels touch at a phone's width no matter
					// how small the type goes. Abbreviating whichever
					// regions cluster this tightly is the normal cartographic
					// answer (every small dot map of the US does this),
					// not a bug to fix with smaller and smaller font sizes.
					"CA | 9.2 | 45.9 | https://www.aravaiparunning.com/california-races/ | California\n" .
					"NV | 14.4 | 43.3 | https://www.aravaiparunning.com/nevada/ | Nevada\n" .
					"Colorado | 33.8 | 46.7 | https://www.aravaiparunning.com/colorado/ | \n" .
					"Ultra Adventures | 23.2 | 49.1 | https://www.aravaiparunning.com/ultra-adventures/ | Canyon country. Antelope Canyon, Zion, Tushars, Bryce Canyon.\n" .
					// Same reasoning as CA/NV above: Great Lakes Endurance and
					// White Mountain Endurance are close enough together, and
					// each other's longest labels, that at a phone's width
					// White Mountain's (edge-anchored, since it sits hard
					// against the map's right side) runs left straight into
					// Great Lakes'. Shortened both; full names live in detail.
					"Great Lakes | 67.1 | 25.5 | https://www.aravaiparunning.com/great-lakes-endurance/ | Great Lakes Endurance. Trail and ultra events across the Great Lakes region.\n" .
					'White Mtn | 91.9 | 22 | https://www.aravaiparunning.com/white-mountain-endurance/ | White Mountain Endurance. Trail and ultra events across the Northeast region.',
					'markup'
				),
			),
			'omega'
		),
		'builder' => 'arv_region_map_builder',
		'render'  => 'arv_region_map_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_region_map_builder() {
	return cs_compose_controls(
		array(
			'control_nav' => array(
				'map' => __( 'Map', 'aravaipa-elements' ),
			),
			'controls'    => array(
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
					'group' => 'map',
				),
				array(
					'key'   => 'intro',
					'type'  => 'text',
					'label' => __( 'Intro line', 'aravaipa-elements' ),
					'group' => 'map',
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Regions', 'aravaipa-elements' ),
					'description' => __(
						'One per line: Name | X% | Y% | landing page URL | detail (optional) | primary (optional, marks the HQ pin). X/Y are a position on the map image as a percentage of its width and height, left edge and top edge are 0. To place a new pin: open assets/us-outline.svg full size, find the spot, and read off its position as a percentage of the image\'s width (X) and height (Y).',
						'aravaipa-elements'
					),
					'group'       => 'map',
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
function arv_region_map_render( $data ) {
	$rows = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 4 );

	if ( empty( $rows ) ) {
		return '';
	}

	$pins = '';

	foreach ( $rows as $row ) {
		$name   = arv_cell( $row, 0 );
		$x      = arv_cell( $row, 1 );
		$y      = arv_cell( $row, 2 );
		$url    = arv_cell( $row, 3 );
		$detail = arv_cell( $row, 4 );
		$flag   = strtolower( arv_cell( $row, 5 ) );

		// Name, a real position, and somewhere to send the click: without any
		// one of those a pin cannot render as anything a visitor could use.
		if ( '' === trim( $name ) || '' === trim( $url ) || ! is_numeric( $x ) || ! is_numeric( $y ) ) {
			continue;
		}

		$x = max( 0, min( 100, (float) $x ) );
		$y = max( 0, min( 100, (float) $y ) );

		$classes = 'arv-region-map__pin';
		$classes .= ( 'primary' === $flag ) ? ' arv-region-map__pin--primary' : '';
		// A label centred on a pin within about 12% of either edge runs off
		// the stage; Great Lakes and anything in New England sit right at
		// that edge in a real US outline. Anchoring the label to whichever
		// side has room keeps it inside the map instead of clipped by the
		// element's own overflow.
		$classes .= ( $x < 12 ) ? ' arv-region-map__pin--edge-left' : ( ( $x > 88 ) ? ' arv-region-map__pin--edge-right' : '' );
		// Same idea vertically: a pin in the top third flips its label below
		// the dot rather than off the top of the stage.
		$classes .= ( $y < 20 ) ? ' arv-region-map__pin--label-below' : '';

		// The name is always on: a pin whose label only appears on hover is
		// unusable on a touch screen, which is most of this page's traffic.
		// Detail is the one thing that stays hover/focus-only, since it is
		// prose, not an identifier, and every pin having its full sentence
		// permanently on screen would turn the map back into a wall of text.
		$pins .= '<a class="' . esc_attr( $classes ) . '" style="left:' . esc_attr( $x ) . '%;top:' . esc_attr( $y ) . '%" href="' . esc_url( $url ) . '">';
		$pins .= '<span class="arv-region-map__dot"></span>';
		$pins .= '<span class="arv-region-map__name">' . esc_html( $name ) . '</span>';
		if ( '' !== trim( $detail ) ) {
			$pins .= '<span class="arv-region-map__detail">' . esc_html( $detail ) . '</span>';
		}
		$pins .= '</a>';
	}

	if ( '' === $pins ) {
		return '';
	}

	$heading = isset( $data['heading'] ) ? $data['heading'] : '';
	$intro   = isset( $data['intro'] ) ? $data['intro'] : '';

	$out = '<div class="' . arv_wrapper_class( $data, 'arv-region-map' ) . '">';

	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-region-map__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== trim( $intro ) ) {
		$out .= '<p class="arv-region-map__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-region-map__stage">';
	$out .= '<img class="arv-region-map__base" src="' . esc_url( ARV_ELEMENTS_URL . 'assets/us-outline.svg' ) . '" alt="" width="960" height="609" loading="lazy" decoding="async" />';
	$out .= $pins;
	$out .= '</div></div>';

	return $out;
}
