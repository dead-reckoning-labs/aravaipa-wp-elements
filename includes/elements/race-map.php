<?php
/**
 * Race Map.
 *
 * Every race as a pin, with a popup carrying the basics and links through to
 * Race Info and registration.
 *
 * Coordinates come from UltraSignup's own group listing, which carries a real
 * latitude and longitude for all 121 of its Aravaipa events, so nothing here
 * geocodes anything or guesses a location from a place name.
 *
 * Leaflet with OpenStreetMap tiles by default rather than Mapbox: it needs no
 * account, no token, no billing relationship, and nothing to rotate when
 * someone leaves. The tile URL is a setting, so pointing it at Mapbox (or
 * anything else serving XYZ tiles) is a field change rather than a rewrite —
 * Mapbox's styling is the reason to do that, and it can be decided later
 * without this element being rebuilt for it.
 *
 * The library loads from a CDN and only on pages that actually place this
 * element. It is the one external dependency in this plugin, which is worth
 * stating plainly: everything else here renders without third-party
 * JavaScript, and a map genuinely cannot.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_MAP_LEAFLET_VERSION', '1.9.4' );

// Leaflet.markercluster, the de facto clustering plugin for Leaflet and the
// one that does what was actually asked for: a count bubble while pins
// overlap, splitting apart as you zoom, and spiderfying the last few that
// share a venue. Pinned rather than floated, same as Leaflet itself, since
// this is a third-party CDN script running on aravaiparunning.com.
define( 'ARV_MAP_CLUSTER_VERSION', '1.5.3' );

cs_register_element(
	'aravaipa-race-map',
	array(
		'title'   => __( 'Aravaipa Race Map', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				// Empty by default. "Every race" over "Find a race near you"
				// said the same thing twice and Jamil asked for it gone; the
				// control stays for anyone who wants an eyebrow here.
				'eyebrow'   => cs_value( '', 'markup' ),
				'heading'   => cs_value( 'Find a race near you', 'markup' ),
				'height'    => cs_value( '520', 'markup' ),
				'collapsible' => cs_value( 'true', 'markup' ),
				'full_width'  => cs_value( 'false', 'markup' ),
				'region'    => cs_value( '', 'markup' ),
				'tile_url'  => cs_value( '', 'markup' ),
				'tile_attr' => cs_value( '', 'markup' ),
				'rows'      => cs_value( '', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_race_map_builder',
		'render'  => 'arv_race_map_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_race_map_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
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
					'key'         => 'height',
					'type'        => 'text',
					'label'       => __( 'Map height in pixels', 'aravaipa-elements' ),
					'description' => __( 'Clamped to 300-900. A map shorter than about 300px cannot show a popup without covering itself.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'collapsible',
					'type'        => 'text',
					'label'       => __( 'Collapsible', 'aravaipa-elements' ),
					'description' => __( 'true or false. Adds a Hide map / Show map toggle. The map still starts open either way; this only decides whether a visitor can fold it away.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'full_width',
					'type'        => 'text',
					'label'       => __( 'Full width', 'aravaipa-elements' ),
					'description' => __( 'true or false. Runs the map edge to edge instead of inside the page content column, so it can sit flush under a hero. A heading, if there is one, stays in the content column either way.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'region',
					'type'        => 'text',
					'label'       => __( 'Region slug (optional)', 'aravaipa-elements' ),
					'description' => __( 'Limits the map to one region, for a division page. Leave blank for every race.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'tile_url',
					'type'        => 'text',
					'label'       => __( 'Tile URL (optional)', 'aravaipa-elements' ),
					'description' => __( 'Leave blank for OpenStreetMap, which needs no account. To use Mapbox instead, paste its raster tile URL including your access token.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'tile_attr',
					'type'        => 'text',
					'label'       => __( 'Tile attribution (optional)', 'aravaipa-elements' ),
					'description' => __( 'Required when using a custom tile URL. Most tile providers, including Mapbox and OpenStreetMap, require visible attribution in their terms.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Races', 'aravaipa-elements' ),
					'description' => __( 'Same format as the other race elements. Ignored once races are in the store. Races with no coordinates are listed below the map rather than dropped.', 'aravaipa-elements' ),
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
function arv_race_map_render( $data ) {
	$races = arv_races_source( $data );

	if ( empty( $races ) ) {
		return '';
	}

	$today = arv_upcoming_races_today();

	// Past races are dropped the same way they are everywhere else, so the
	// map agrees with the list next to it rather than quietly showing more.
	$races = array_values(
		array_filter(
			$races,
			function ( $race ) use ( $today ) {
				$last = '' !== $race['end'] ? $race['end'] : $race['iso'];
				return $today < arv_upcoming_races_clears_on( $last );
			}
		)
	);

	$logo_map = array(
		'arizona'                  => ARV_ELEMENTS_URL . 'assets/logos/aravaipa.png',
		'california'               => ARV_ELEMENTS_URL . 'assets/logos/aravaipa.png',
		'colorado'                 => ARV_ELEMENTS_URL . 'assets/logos/colorado.png',
		'nevada'                   => ARV_ELEMENTS_URL . 'assets/logos/aravaipa.png',
		'bad-beard'                => ARV_ELEMENTS_URL . 'assets/logos/bad-beard.png',
		'great-lakes-endurance'    => ARV_ELEMENTS_URL . 'assets/logos/great-lakes-endurance.png',
		'ultra-adventures'         => ARV_ELEMENTS_URL . 'assets/logos/ultra-adventures.png',
		'white-mountain-endurance' => ARV_ELEMENTS_URL . 'assets/logos/white-mountain-endurance.png',
	);

	$pins = array();

	foreach ( $races as $race ) {
		// Checked for presence and for being a real number, not just against
		// the empty string. A race array that has no 'lat' key at all reads
		// as null, and null is not '', so the old check let it through and
		// (float) null then plotted it at 0,0. Every race on the live map
		// sat in the Atlantic that way, because the race store was not
		// carrying coordinates at all. The store is fixed, and this stays
		// strict so a single bad coordinate can never do it again.
		$lat = isset( $race['lat'] ) ? trim( (string) $race['lat'] ) : '';
		$lng = isset( $race['lng'] ) ? trim( (string) $race['lng'] ) : '';

		// 0 is treated as missing rather than as a location. It is what an
		// empty field becomes the moment anything upstream casts before it
		// checks, and no Aravaipa race is in the Gulf of Guinea.
		if ( '' === $lat || '' === $lng || ! is_numeric( $lat ) || ! is_numeric( $lng )
			|| 0.0 === (float) $lat || 0.0 === (float) $lng ) {
			// Simply not a pin. This used to render a "N races have no map
			// location yet" list under the map, which was useful while the
			// store was silently dropping every coordinate (see the
			// null-island fix) but reads as a defect list now that the real
			// gaps are down to genuinely locationless races: a virtual,
			// worldwide race has no coordinates to be missing. The full
			// season list on the calendar is where every race is accounted
			// for; the map only ever claimed to show the ones with a place.
			continue;
		}

		$action = arv_upcoming_races_action( $race, $today );

		// Append the year to whatever display date we have so the popup is
		// unambiguous for a race someone might be planning travel around.
		$year_str     = gmdate( 'Y', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );
		$display_date = '' !== $race['display'] ? $race['display'] : gmdate( 'F j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );
		if ( false === strpos( $display_date, $year_str ) ) {
			$display_date .= ', ' . $year_str;
		}

		$region = arv_race_store_region_for( $race );
		$logo   = isset( $logo_map[ $region ] ) ? $logo_map[ $region ] : '';
		// Bad Beard's mark is white artwork on transparency, so on the
		// popup's white card it renders as nothing at all: the logo loads,
		// the <img> is the right size, and every pixel of it is white. It
		// is the only one of the six like this (the others average a
		// luminance of 74-149 against Bad Beard's 255), so rather than
		// re-cutting the asset this flags it for a dark backing chip in CSS.
		$logo_dark = ( 'bad-beard' === $region );

		$where = trim( implode( ', ', array_filter( array( $race['venue'], $race['location'] ) ) ) );

		// What the jump-to-a-race search matches on, lowercased here rather
		// than in the browser on every keystroke.
		//
		// The full state name is folded in because a location only ever
		// carries the two-letter code. Searching "tennessee" found nothing
		// while three races sat in Chattanooga, since the only thing in the
		// pin was "TN". Exactly the bug the calendar's search already hit and
		// fixed the same way; the map inherited it by being written second.
		$search = strtolower( $race['name'] . ' ' . $where );
		if ( preg_match( '/,\s*([A-Za-z]{2})\s*$/', $race['location'], $sm ) ) {
			$search .= ' ' . strtolower( arv_state_name( strtoupper( $sm[1] ) ) );
		}

		$pins[] = array(
			'name'      => $race['name'],
			'lat'       => (float) $race['lat'],
			'lng'       => (float) $race['lng'],
			'date'      => $display_date,
			'distances' => $race['distances'],
			'where'     => $where,
			'search'    => $search,
			'page'      => $race['page'],
			// Only offered when the phase actually has somewhere to send
			// someone, matching the cards and the calendar exactly.
			'cta'       => '' !== $action['url'] ? $action['label'] : '',
			'ctaUrl'    => $action['url'],
			'phase'     => $action['phase'],
			'logo'      => $logo,
			'logoDark'  => $logo_dark,
		);
	}

	if ( empty( $pins ) ) {
		return '';
	}

	// Only now, with pins to draw, is the library actually needed.
	if ( function_exists( 'wp_enqueue_script' ) ) {
		arv_race_map_enqueue();
	}

	// Same "anything but an explicit false" reading the season calendar's
	// `filters` control uses, so the two behave identically for an editor
	// typing into a text field.
	$collapsible = isset( $data['collapsible'] ) ? $data['collapsible'] : true;
	$collapsible = ! ( 'false' === $collapsible || false === $collapsible || '0' === $collapsible );

	$height = isset( $data['height'] ) ? (int) $data['height'] : 520;
	$height = max( 300, min( 900, $height ) );

	$tile_url  = isset( $data['tile_url'] ) ? trim( $data['tile_url'] ) : '';
	$tile_attr = isset( $data['tile_attr'] ) ? trim( $data['tile_attr'] ) : '';

	if ( '' === $tile_url ) {
		$tile_url  = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
		$tile_attr = '&copy; OpenStreetMap contributors';
	}

	$config = array(
		'pins'     => $pins,
		'tileUrl'  => $tile_url,
		'tileAttr' => $tile_attr,
	);

	$json = wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return '';
	}
	// A "</script>" inside a race name would close this tag early and drop
	// the rest of the JSON into the document as markup. Escaping the "<"
	// itself is what prevents that; JSON parsers read \u003C back as "<", so
	// the data arrives intact.
	//
	// Written out as an escape sequence deliberately: the obvious-looking
	// str_replace( '<', '<', ... ) is a no-op that reads like a fix, and
	// shipped as one here until a test caught it.
	$json = str_replace( '<', '\u003C', $json );

	// Full bleed runs the map edge to edge so it can sit flush under a hero
	// with no seam between them. Only the canvas bleeds: a heading dropped to
	// the window edge stops lining up with every other heading on the page,
	// so the text stays in the content column and the map escapes it.
	$full_width = isset( $data['full_width'] ) ? $data['full_width'] : false;
	$full_width = ! ( 'false' === $full_width || false === $full_width || '0' === $full_width || '' === $full_width );

	$base = 'arv-map';
	if ( $full_width ) {
		$base .= ' arv-map--full';
	}

	$out  = '<div class="' . arv_wrapper_class( $data, $base ) . '">';
	$out .= '<div class="arv-map__inner">';

	$eyebrow = isset( $data['eyebrow'] ) ? $data['eyebrow'] : '';
	$heading = isset( $data['heading'] ) ? $data['heading'] : '';

	if ( '' !== trim( $eyebrow ) ) {
		$out .= '<p class="arv-map__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-map__heading">' . esc_html( $heading ) . '</h2>';
	}

	// Collapse toggle. Rendered here rather than injected by JavaScript so
	// that a visitor who scrolls past before the script runs never sees the
	// control appear late and shift the page under them.
	//
	// Open by default, and it is a real <button> with aria-expanded: this is
	// a disclosure, not decoration, so a screen reader and a keyboard both
	// get it for free. Nothing is hidden from a crawler either way, since the
	// map's own content is a canvas and the <noscript> list below carries the
	// races in markup regardless of this state.
	//
	// The toggle and the panel share a wrapper, and that wrapper is what the
	// toggle is positioned against when it floats over the map. Positioning
	// it against .arv-map__inner instead was a real bug: inner starts at the
	// eyebrow, so on a map that has a heading the button landed level with
	// the heading rather than over the map. It only looked right in the
	// configuration it was checked in, which had no heading at all.
	$out .= '<div class="arv-map__stage">';

	if ( $collapsible ) {
		$out .= '<button type="button" class="arv-map__toggle" data-arv-map-toggle aria-expanded="true">';
		$out .= '<span class="arv-map__toggle-label">' . esc_html( __( 'Hide map', 'aravaipa-elements' ) ) . '</span>';
		$out .= '<span class="arv-map__toggle-caret" aria-hidden="true"></span>';
		$out .= '</button>';
	}

	$out .= '<div class="arv-map__panel" data-arv-map-panel>';

	// Jump-to-a-race search. Rendered here, above the canvas, and then moved
	// into the map by the script as a bottom-left Leaflet control.
	//
	// Server-rendering it and relocating it, rather than building it in JS,
	// keeps a real focusable input in the HTML for anything that never runs
	// the script, and means it exists before Leaflet has finished starting
	// up. Where it ends up is a presentation decision and lives with the
	// presentation; whether it exists at all is a content decision and lives
	// here.
	//
	// combobox rather than a bare input, so the results are announced as
	// options and the arrow keys are expected rather than a surprise. Only
	// useful with enough races to be worth searching; below that the pins
	// are all visible anyway.
	if ( count( $pins ) > 8 ) {
		$out .= '<div class="arv-map__search" data-arv-map-search>';
		$out .= '<input type="search" class="arv-map__search-input"'
			. ' placeholder="' . esc_attr( __( 'Jump to a race', 'aravaipa-elements' ) ) . '"'
			. ' aria-label="' . esc_attr( __( 'Jump to a race on the map', 'aravaipa-elements' ) ) . '"'
			. ' role="combobox" aria-expanded="false" aria-controls="arv-map-results"'
			. ' aria-autocomplete="list" autocomplete="off" />';
		$out .= '<ul class="arv-map__results" id="arv-map-results" role="listbox" data-arv-map-results></ul>';
		$out .= '</div>';
	}

	$out .= '<div class="arv-map__canvas" data-arv-map style="height:' . esc_attr( $height ) . 'px"></div>';
	$out .= '<script type="application/json" data-arv-map-config>' . $json . '</script>';
	$out .= '</div>';

	// Closes .arv-map__stage. The toggle above is positioned against this,
	// not against .arv-map__inner, so it lands over the map rather than over
	// the heading.
	$out .= '</div>';

	// A plain list of every pin, in the markup, always. The map needs
	// JavaScript and a third-party library to draw anything; this does not,
	// so the races are still readable and indexable if either fails, and a
	// screen reader gets a list rather than an empty box.
	$out .= '<noscript class="arv-map__fallback"><ul>';
	foreach ( $pins as $pin ) {
		$label = esc_html( $pin['name'] . ' — ' . $pin['date'] . ( '' !== $pin['where'] ? ', ' . $pin['where'] : '' ) );
		$out  .= '' !== $pin['page']
			? '<li><a href="' . esc_url( $pin['page'] ) . '">' . $label . '</a></li>'
			: '<li>' . $label . '</li>';
	}
	$out .= '</ul></noscript>';

	$out .= '</div></div>';

	return $out;
}

/**
 * Load Leaflet, at the moment a map actually renders.
 *
 * The previous version hooked wp_enqueue_scripts and looked for the element's
 * name in the post content, which is never there: Cornerstone keeps its
 * element data in postmeta and only assembles markup at render time. The
 * check silently never matched, so Leaflet was never enqueued and the map
 * rendered as an empty grey box on the live site.
 *
 * Enqueuing from the render function is both simpler and more accurate: it
 * fires exactly when a map is on the page, rather than guessing from stored
 * content, and it cannot drift if Cornerstone changes where it stores things.
 *
 * Calling this after wp_head has run is fine. The script is registered for
 * the footer, and WordPress prints styles enqueued this late through
 * print_late_styles() on wp_footer, which exists for exactly this case.
 */
function arv_race_map_enqueue() {
	// No static "already done" guard. wp_enqueue_style and wp_enqueue_script
	// both dedupe by handle already, so a page with several maps costs
	// nothing, and a guard that makes the second call a no-op makes the
	// function's behaviour depend on what ran before it, which is worth
	// avoiding for the sake of work WordPress is not doing anyway.
	wp_enqueue_style(
		'bitter-font',
		'https://fonts.googleapis.com/css2?family=Bitter:ital,wght@1,600&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'leaflet',
		'https://unpkg.com/leaflet@' . ARV_MAP_LEAFLET_VERSION . '/dist/leaflet.css',
		array( 'bitter-font' ),
		ARV_MAP_LEAFLET_VERSION
	);

	// MarkerCluster.css is the plugin's structural CSS and is required;
	// MarkerCluster.Default.css is its stock green/yellow bubble skin, which
	// is deliberately NOT loaded. Our own stylesheet draws the bubbles in
	// Aravaipa's teal instead, and loading the default first would only mean
	// overriding it.
	wp_enqueue_style(
		'leaflet-markercluster',
		'https://unpkg.com/leaflet.markercluster@' . ARV_MAP_CLUSTER_VERSION . '/dist/MarkerCluster.css',
		array( 'leaflet' ),
		ARV_MAP_CLUSTER_VERSION
	);

	wp_enqueue_script(
		'leaflet',
		'https://unpkg.com/leaflet@' . ARV_MAP_LEAFLET_VERSION . '/dist/leaflet.js',
		array(),
		ARV_MAP_LEAFLET_VERSION,
		true
	);

	wp_enqueue_script(
		'leaflet-markercluster',
		'https://unpkg.com/leaflet.markercluster@' . ARV_MAP_CLUSTER_VERSION . '/dist/leaflet.markercluster.js',
		array( 'leaflet' ),
		ARV_MAP_CLUSTER_VERSION,
		true
	);

	wp_enqueue_script(
		'aravaipa-race-map',
		ARV_ELEMENTS_URL . 'assets/aravaipa-race-map.js',
		array( 'leaflet', 'leaflet-markercluster' ),
		ARV_ELEMENTS_VERSION,
		true
	);
}
