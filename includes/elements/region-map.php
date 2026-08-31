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
					// Name | X% | Y% | URL | detail | flags | full name | logo URL
					//
					// Every detail is just the races, nothing scenic. A first
					// pass wrote a line of terrain for each pin and invented
					// one: Nevada is not branded around the Spring Mountains,
					// that was scenery reached for rather than anything
					// Aravaipa says about itself. The pin label already says
					// where a region is; the detail only needs to say what to
					// run there.
					//
					// It used to be four different shapes at once besides.
					// Some rows named terrain, some named races, two were the
					// same boilerplate sentence about "trail and ultra
					// events", and Bad Beard was a bare "Chattanooga,
					// Tennessee." Nine pins on one map answered nine different
					// questions.
					//
					// Races named here are on the current calendar, checked
					// against it rather than remembered. The old Ultra
					// Adventures row named the Tushars, which has not been on
					// it, and Antelope Canyon, which runs under Arizona.
					//
					// Flags is a space separated list: `primary` marks the HQ pin,
					// `above` lifts that pin's label over the dot instead of under
					// it. Nothing needs `above` at the moment: the pins are placed
					// on the cities the events are actually in rather than on state
					// centres, which spreads them out enough that every label fits
					// below its own dot.
					//
					// Coordinates are percentages of assets/us-outline.svg's
					// viewBox, checked against that file's own path geometry rather
					// than eyeballed, so a pin lands inside the state it names.
					"Arizona | 20.9 | 61.9 | https://www.aravaiparunning.com/arizona/ | Cocodona 250, Javelina Jundred, Black Canyon Ultras. | primary | | ARV_LOGO:aravaipa.png\n" .
					"Tucson | 23 | 68.9 | https://www.aravaiparunning.com/tucson-runs/ | Catalina State Park 50-Year, Run Around Tucson. | | | ARV_LOGO:aravaipa.png\n" .
					// On Orange County and Las Vegas rather than the state centres:
					// that is where the races are, and it keeps the two labels from
					// stacking on each other the way the centres did.
					"California | 9.4 | 59.7 | https://www.aravaiparunning.com/california-races/ | Sonoma Fall Classic, Harding Hustle, Live Oak Odyssey. | | | ARV_LOGO:aravaipa.png\n" .
					"Nevada | 15.6 | 52.7 | https://www.aravaiparunning.com/nevada/ | Jackpot Ultras, Running With The Devil, ET Full Moon. | | | ARV_LOGO:aravaipa.png\n" .
					"Colorado | 33.8 | 46.7 | https://www.aravaiparunning.com/colorado/ | The Bear Chase, North Fork 50, Silverton Alpine Marathon. | | | ARV_LOGO:colorado.png\n" .
					"Ultra Adventures | 23.2 | 49.1 | https://www.aravaiparunning.com/ultra-adventures/ | Zion Ultras, Bryce Canyon Ultras. | | | ARV_LOGO:ultra-adventures.png\n" .
					// On the Upper Peninsula rather than the state's centre, which
					// is where the events actually are.
					"Great Lakes Endurance | 64.3 | 18.2 | https://www.aravaiparunning.com/great-lakes-endurance/ | Grand Island, Tahqua Trail Runs, Two Hearted. | | | ARV_LOGO:great-lakes-endurance.png\n" .
					"White Mountain Endurance | 91.9 | 22 | https://www.aravaiparunning.com/white-mountain-endurance/ | Kilkenny Ridge, Race The Cog, Black Bear. | | | ARV_LOGO:white-mountain-endurance.png\n" .
					'Bad Beard Events | 71.2 | 61.1 | https://www.aravaiparunning.com/bad-beard/ | Stump Jump 50K, Rabid Raccoon, Stillhouse 100K. | | | ARV_LOGO:bad-beard.png',
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
			'controls'    => array(
				array(
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'intro',
					'type'  => 'text',
					'label' => __( 'Intro line', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
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
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Regions', 'aravaipa-elements' ),
					'description' => __(
						'One per line: Name | X% | Y% | landing page URL | detail (optional) | primary (optional, marks the HQ pin). X/Y are a position on the map image as a percentage of its width and height, left edge and top edge are 0. To place a new pin: open assets/us-outline.svg full size, find the spot, and read off its position as a percentage of the image\'s width (X) and height (Y).',
						'aravaipa-elements'
					),
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
		// Column 6 is a space separated flag list rather than a single
		// value, so a pin can be both the HQ and need its label lifted.
		$flags  = preg_split( '/\s+/', strtolower( trim( arv_cell( $row, 5 ) ) ), -1, PREG_SPLIT_NO_EMPTY );
		$flags  = is_array( $flags ) ? $flags : array();
		// Optional 7th column, appended rather than slotted in beside `name`
		// so rows already saved in a page keep parsing unchanged. `name` is
		// what the map label shows and has to stay short enough not to
		// collide with its neighbours; this is the unabbreviated version for
		// the list below, where there is room for it.
		$full   = arv_cell( $row, 6 );
		// Optional 8th column: the region's own brand mark, shown in the
		// hover card and beside its name in the list. Anything without one
		// simply renders as it did before, so a partner whose logo we do
		// not have yet is not a blank box.
		$logo   = arv_region_map_logo_url( arv_cell( $row, 7 ) );

		// Name, a real position, and somewhere to send the click: without any
		// one of those a pin cannot render as anything a visitor could use.
		if ( '' === trim( $name ) || '' === trim( $url ) || ! is_numeric( $x ) || ! is_numeric( $y ) ) {
			continue;
		}

		$x = max( 0, min( 100, (float) $x ) );
		$y = max( 0, min( 100, (float) $y ) );

		$is_primary = in_array( 'primary', $flags, true );

		$classes = 'arv-region-map__pin';
		$classes .= $is_primary ? ' arv-region-map__pin--primary' : '';
		// "above" lifts the name label over the dot instead of under it.
		// Two pins at nearly the same latitude (California and Nevada sit
		// about 2% apart vertically) collide horizontally no matter how
		// their labels are anchored; putting one above and one below is
		// what actually separates them, and is the standard fix on any
		// crowded dot map.
		$classes .= in_array( 'above', $flags, true ) ? ' arv-region-map__pin--name-above' : '';
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
		// Always a card now, where it used to appear only for a row carrying
		// a detail or a logo: it also holds the call to action, and a pin
		// whose card never opens would be the one pin on the map with no
		// visible way to reach the page it links to.
		$pins .= '<span class="arv-region-map__detail">';
		// The region's name, inside the card, for phone width only. The
		// always-visible label beside the dot is hidden below 767px because
		// four of the regions sit close enough together that the labels
		// collide, which left the opened card describing a place it never
		// named: "Southwest roots. Home of Cocodona 250..." with no way to
		// tell which of the dots you had actually hit. Hidden on desktop,
		// where the label beside the dot already says it and this would be
		// the name twice.
		$pins .= '<span class="arv-region-map__detail-name">' . esc_html( $name ) . '</span>';
		if ( '' !== trim( $logo ) ) {
			// alt is empty on purpose: the region's name is already in
			// the label beside this card and in the list below, so a
			// screen reader announcing the brand a third time off the
			// image is repetition, not information.
			$pins .= '<img class="arv-region-map__detail-logo" src="' . esc_url( $logo ) . '" alt="" loading="lazy" decoding="async" />';
		}
		if ( '' !== trim( $detail ) ) {
			$pins .= '<span class="arv-region-map__detail-text">' . esc_html( $detail ) . '</span>';
		}
		// A span, not a nested <a> or <button>. The whole pin is already the
		// link to this region's page, and putting a second interactive
		// element inside it would be invalid HTML and would announce the
		// same destination to a screen reader twice. This is the affordance
		// for a click the surrounding anchor already handles, which is why
		// it is also aria-hidden.
		$pins .= '<span class="arv-region-map__cta" aria-hidden="true">' . esc_html( __( 'View races', 'aravaipa-elements' ) ) . '</span>';
		$pins .= '</span>';
		$pins .= '</a>';

		// The same regions again as plain text links. On a phone the pins are
		// a few millimetres across and their labels are set small to stop
		// them colliding, so the list is what actually makes this section
		// usable there. It is also the only part a search engine can read:
		// the map itself is one decorative SVG with no place names in it.
		$items .= '<a class="arv-region-map__item" href="' . esc_url( $url ) . '">';
		if ( '' !== trim( $logo ) ) {
			$items .= '<span class="arv-region-map__item-logo"><img src="' . esc_url( $logo ) . '" alt="" loading="lazy" decoding="async" /></span>';
		}
		$items .= '<span class="arv-region-map__item-body">';
		$items .= '<span class="arv-region-map__item-name">' . esc_html( '' !== trim( $full ) ? $full : $name ) . '</span>';
		if ( '' !== trim( $detail ) ) {
			$items .= '<span class="arv-region-map__item-detail">' . esc_html( $detail ) . '</span>';
		}
		$items .= '</span></a>';
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
 * Resolve a logo cell into a usable URL.
 *
 * "ARV_LOGO:name.png" points at assets/logos/ inside the plugin, which is
 * where the marks we have are kept. They were originally hotlinked straight
 * out of the WordPress media library, which turned out to be unreliable:
 * aravaiparunning.com's origin intermittently times out behind Cloudflare
 * (repeatedly measured returning 522 on a cache miss for both logos), so
 * the image only rendered while Cloudflare happened to be holding a cached
 * copy. Bundled, they ship and version with the plugin instead.
 *
 * Anything else is passed through untouched, so a full URL still works for
 * a brand whose mark is not bundled.
 *
 * @param string $cell Raw cell value.
 * @return string
 */
function arv_region_map_logo_url( $cell ) {
	$cell = trim( (string) $cell );

	if ( 0 !== strpos( $cell, 'ARV_LOGO:' ) ) {
		return $cell;
	}

	$file = substr( $cell, strlen( 'ARV_LOGO:' ) );
	// basename() so a value out of the builder cannot walk up out of the
	// logos directory with ../ and point this at some other file.
	$file = basename( $file );

	return ARV_ELEMENTS_URL . 'assets/logos/' . $file;
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
