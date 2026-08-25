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
				'eyebrow' => cs_value( 'Aravaipa Running Regions', 'markup' ),
				'intro'   => cs_value( '', 'markup' ),
				'theme'   => cs_value( 'dark', 'style' ),
				'list'    => cs_value( 'true', 'style' ),
				'rows'    => cs_value(
					// Name | X% | Y% | URL | detail | primary | full name
					//
					// `Name` is the map label and has to stay short enough not to
					// collide with its neighbours at a phone's width. Where it is
					// abbreviated for that reason, the 7th column carries the real
					// name for the list below the map, which has room for it.
					// CA and NV sit about 25 miles apart in real state centers;
					// Great Lakes and White Mountain are each other's longest
					// labels and land close enough that the map's own edge
					// avoidance pushes one into the other.
					"Arizona | 20.9 | 61.9 | https://www.aravaiparunning.com/arizona/ | Southwest roots. Home of Cocodona 250, Javelina Jundred and Black Canyon 100K. | primary\n" .
					"Tucson | 23 | 68.9 | https://www.aravaiparunning.com/tucson-runs/ | Saguaro country, in the shadow of the Santa Catalinas.\n" .
					"CA | 9.2 | 45.9 | https://www.aravaiparunning.com/california-races/ | Coastal ranges and Sierra foothills. | | California\n" .
					"NV | 14.4 | 43.3 | https://www.aravaiparunning.com/nevada/ | High desert and the Spring Mountains. | | Nevada\n" .
					"Colorado | 33.8 | 46.7 | https://www.aravaiparunning.com/colorado/ | Front Range and high country.\n" .
					"Ultra Adventures | 23.2 | 49.1 | https://www.aravaiparunning.com/ultra-adventures/ | Canyon country. Antelope Canyon, Zion, Tushars, Bryce Canyon.\n" .
					"Great Lakes | 67.1 | 25.5 | https://www.aravaiparunning.com/great-lakes-endurance/ | Trail and ultra events across the Great Lakes region. | | Great Lakes Endurance\n" .
					'White Mtn | 91.9 | 22 | https://www.aravaiparunning.com/white-mountain-endurance/ | Trail and ultra events across the Northeast. | | White Mountain Endurance',
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
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
					'group' => 'map',
				),
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
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
					'group'   => 'map',
					'options' => array(
						'choices' => array(
							array(
								'value' => 'dark',
								'label' => __( 'Dark panel', 'aravaipa-elements' ),
							),
							array(
								'value' => 'light',
								'label' => __( 'Light', 'aravaipa-elements' ),
							),
						),
					),
				),
				array(
					'key'         => 'list',
					'type'        => 'toggle',
					'label'       => __( 'Region list below map', 'aravaipa-elements' ),
					'description' => __( 'Repeats every region as a text link under the map. Carries the section on a phone, where the pins are small, and gives search engines the region names as real text.', 'aravaipa-elements' ),
					'group'       => 'map',
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

	$pins  = '';
	$items = '';

	foreach ( $rows as $row ) {
		$name   = arv_cell( $row, 0 );
		$x      = arv_cell( $row, 1 );
		$y      = arv_cell( $row, 2 );
		$url    = arv_cell( $row, 3 );
		$detail = arv_cell( $row, 4 );
		$flag   = strtolower( arv_cell( $row, 5 ) );
		// Optional 7th column, appended rather than slotted in beside `name`
		// so rows already saved in a page keep parsing unchanged. `name` is
		// what the map label shows and has to stay short enough not to
		// collide with its neighbours; this is the unabbreviated version for
		// the list below, where there is room for it.
		$full   = arv_cell( $row, 6 );

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

		// The same regions again as plain text links. On a phone the pins are
		// a few millimetres across and their labels are set small to stop
		// them colliding, so the list is what actually makes this section
		// usable there. It is also the only part a search engine can read:
		// the map itself is one decorative SVG with no place names in it.
		$items .= '<a class="arv-region-map__item" href="' . esc_url( $url ) . '">';
		$items .= '<span class="arv-region-map__item-name">' . esc_html( '' !== trim( $full ) ? $full : $name ) . '</span>';
		if ( '' !== trim( $detail ) ) {
			$items .= '<span class="arv-region-map__item-detail">' . esc_html( $detail ) . '</span>';
		}
		$items .= '</a>';
	}

	if ( '' === $pins ) {
		return '';
	}

	$heading = isset( $data['heading'] ) ? $data['heading'] : '';
	$eyebrow = isset( $data['eyebrow'] ) ? $data['eyebrow'] : '';
	$intro   = isset( $data['intro'] ) ? $data['intro'] : '';

	$theme = ( isset( $data['theme'] ) && 'light' === $data['theme'] ) ? 'light' : 'dark';

	// Cornerstone toggles arrive as the strings "true"/"false" as often as
	// booleans depending on how the value was saved, so compare loosely
	// rather than trusting a bare truthiness check ("false" is truthy).
	$show_list = isset( $data['list'] ) ? $data['list'] : true;
	$show_list = ! ( 'false' === $show_list || false === $show_list || '0' === $show_list );

	$base = 'arv-region-map arv-region-map--' . $theme;

	$out = '<div class="' . arv_wrapper_class( $data, $base ) . '">';
	$out .= '<div class="arv-region-map__inner">';

	if ( '' !== trim( $eyebrow ) ) {
		$out .= '<p class="arv-region-map__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}

	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-region-map__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== trim( $intro ) ) {
		$out .= '<p class="arv-region-map__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-region-map__stage">';
	$out .= arv_region_map_svg();
	$out .= $pins;
	$out .= '</div>';

	if ( $show_list && '' !== $items ) {
		$out .= '<div class="arv-region-map__list">' . $items . '</div>';
	}

	$out .= '</div></div>';

	return $out;
}

/**
 * The US outline, inlined.
 *
 * Inlined rather than served as <img src="...us-outline.svg">, because an
 * <img> is an opaque document the page's own stylesheet cannot reach into:
 * the state fills and borders could not then follow the element's light or
 * dark theme, and would need a second copy of the file per theme to do it.
 * Inline also drops a request, and the file is ~28 KB that gzips to a few.
 *
 * Read once per request. Cornerstone can render the same element more than
 * once on a page, and re-reading the file each time would be pointless I/O.
 *
 * @return string
 */
function arv_region_map_svg() {
	static $svg = null;

	if ( null !== $svg ) {
		return $svg;
	}

	$path = ARV_ELEMENTS_PATH . 'assets/us-outline.svg';

	// A missing asset should cost the pins, not the page. Returning nothing
	// leaves the labels stacked on a blank stage, which is obviously broken
	// to whoever is editing, and still not a fatal on a live race page.
	if ( ! file_exists( $path ) ) {
		$svg = '';
		return $svg;
	}

	$svg = trim( (string) file_get_contents( $path ) );
	$svg = preg_replace( '/^<\?xml[^>]*\?>\s*/', '', $svg );
	// Decorative: every place name on this map is real text in the pins and
	// the list layered over it, so announcing the outline itself would just
	// be noise in a screen reader.
	$svg = preg_replace( '/^<svg /', '<svg class="arv-region-map__base" aria-hidden="true" focusable="false" ', $svg, 1 );

	return $svg;
}
