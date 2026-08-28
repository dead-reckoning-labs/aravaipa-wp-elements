<?php
/**
 * Featured Race.
 *
 * One named race, promoted to a full-width block wherever an editor drops
 * this, independent of where that race sorts by date.
 *
 * The problem this solves: Aravaipa Upcoming Races shows the soonest races
 * by date, which is right for "what's coming up" but wrong for "what do we
 * most want someone to see right now". A month-long virtual race open today
 * sorts behind every in-person race happening sooner, so on a list sorted
 * purely by date it disappears down the page even while it is the one thing
 * a visitor could act on immediately. Aravaipa Upcoming Races also grew a
 * `featured` field for the same reason, pinning a race to the front of its
 * own grid; this is the other half, a dedicated slot for a race that
 * deserves more room than one grid card.
 *
 * Finds its race the same way Aravaipa Race Status does: by matching a page
 * URL against the store, not by name. Names get reworded ("Javelina
 * Jallucinations" could as easily be typed "Javelina Jallucination" or
 * picked up a "Presented by" suffix next season) and a name match would
 * silently render nothing the day someone renames the race. A page URL is
 * already the identifier the rest of the site treats as stable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-featured-race',
	array(
		'title'   => __( 'Aravaipa Featured Race', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				// Defaulted rather than left blank, same reason the calendar's
				// copy and the map's full-width setting are defaults and not
				// something typed into a Cornerstone field: this site's
				// builder is not being used to configure element content, so
				// a blank default here is a permanently-blank block, not a
				// prompt for an editor to fill in. Update this default (and
				// ship a new version) to feature a different race.
				'race_page'  => cs_value( 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/', 'markup' ),
				'eyebrow'    => cs_value( 'Featured Race', 'markup' ),
				'detail'     => cs_value( 'Run it anywhere, any day in October.', 'markup' ),
				'cta_label'  => cs_value( '', 'markup' ),
				// A photograph, and deliberately not the race's own image.
				// The store's image for a race is its logo, and using that as
				// a full-bleed background stretches a flat graphic across the
				// width of the page and then prints the text on top of it, so
				// the artwork and the copy fight each other and neither wins.
				// The logo is rendered as a logo below instead, and this is a
				// real photo behind it.
				'image'      => cs_value( 'https://www.aravaiparunning.com/avr/wp-content/uploads/ScottRokis_Javelina24_SRR30043.jpg', 'markup' ),
				'overlay'    => cs_value( '0.5', 'style' ),
				'full_width' => cs_value( 'true', 'markup' ),
				'theme'      => cs_value( 'dark', 'style' ),

				// The side card. Everything below is a claim made on a live
				// commercial page, so each one is checked against a source
				// rather than written from memory:
				//
				//   price / price_note  read off the challenge's own page on
				//                       obsession.run, which states $49.00
				//                       entry plus a $3.89 fee and an
				//                       increase to $59.00 on September 1.
				//   syncs               Strava and Coros are both shipped
				//                       integrations in the Obsession
				//                       codebase (src/app/settings has a real
				//                       coros-section.tsx with tokens,
				//                       backfill and sync state, and Strava
				//                       has its own importer). Garmin is
				//                       deliberately absent: it is still in
				//                       coming-soon-section.tsx.
				'host_label' => cs_value( 'Hosted on Obsession.run', 'markup' ),
				'host_url'   => cs_value( 'https://obsession.run/challenges/jallucinations', 'markup' ),
				'host_logo'  => cs_value( 'https://www.aravaiparunning.com/avr/wp-content/uploads/obsession-app-icon.png', 'markup' ),
				// The entry price, not the checkout total. obsession.run breaks
				// it out the same way ("$49.00 entry + $3.89 fee"), and a bare
				// "$52.89" reads as a strangely precise number with no
				// explanation, where "$49 + fees" reads as a price. Both
				// halves of the before/after say "+ fees" so the comparison is
				// like for like: quoting one with fees and one without would
				// overstate the increase.
				'price'      => cs_value( '$49 + fees', 'markup' ),
				'price_note' => cs_value( 'Goes up to $59 + fees on September 1', 'markup' ),
				'syncs'      => cs_value( 'Strava, Coros', 'markup' ),

				// A separate fact from the price rise, confirmed directly by
				// Jamil rather than found on the public challenge page: this
				// is the first year of goody-pack fulfilment for this race,
				// and September 1 is also the order cutoff to be guaranteed
				// one before the challenge starts. Two real reasons to act by
				// the same date, kept as two lines rather than merged into
				// one, since a visitor who already has last year's shirt and
				// does not care about a new one should still see the price
				// note clearly, and a visitor lured mainly by the goody pack
				// should not have to parse it out of a sentence about price.
				'deadline_note' => cs_value( 'Order by September 1 to guarantee your goody pack before the challenge begins.', 'markup' ),
			),
			'omega'
		),
		'builder' => 'arv_featured_race_builder',
		'render'  => 'arv_featured_race_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_featured_race_builder() {
	return cs_compose_controls(
		array(
			'controls' => array(
				array(
					'key'         => 'race_page',
					'type'        => 'text',
					'label'       => __( 'Race page URL', 'aravaipa-elements' ),
					'description' => __( 'The race\'s own page. Unlike Aravaipa Race Status this has no page to auto-detect, since it is meant to sit somewhere other than that race\'s own page, so this is required.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'detail',
					'type'        => 'text',
					'label'       => __( 'Detail line (optional)', 'aravaipa-elements' ),
					'description' => __( 'A short line under the race name, for context the race name and date do not carry on their own, e.g. "Run it from anywhere, any day in October." Leave blank to show only the venue.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'cta_label',
					'type'        => 'text',
					'label'       => __( 'Button label override (optional)', 'aravaipa-elements' ),
					'description' => __( 'Leave blank to use the same phase-driven label everywhere else uses (Register, Live Results, Join Waitlist, and so on).', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'deadline_note',
					'type'        => 'text',
					'label'       => __( 'Deadline note (optional)', 'aravaipa-elements' ),
					'description' => __( 'A short urgency line above the button, e.g. "Order by September 1 to guarantee your goody pack before the challenge begins." Leave blank to show none.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'image',
					'type'        => 'image',
					'label'       => __( 'Background image (optional)', 'aravaipa-elements' ),
					'description' => __( 'A photograph. Deliberately not the race\'s own image, which is its logo: a logo stretched across a full-width panel reads as wallpaper. The logo is rendered separately, over the top of this.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'overlay',
					'type'        => 'text',
					'label'       => __( 'Overlay opacity', 'aravaipa-elements' ),
					'description' => __( '0 to 1. Darkens the background image so the text stays readable over it.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'full_width',
					'type'        => 'text',
					'label'       => __( 'Full width', 'aravaipa-elements' ),
					'description' => __( 'true or false. Runs edge to edge instead of inside the page content column.', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => 'dark',
								'label' => __( 'Dark', 'aravaipa-elements' ),
							),
							array(
								'value' => 'light',
								'label' => __( 'Light', 'aravaipa-elements' ),
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
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_featured_race_render( $data ) {
	if ( ! function_exists( 'arv_race_store_find_by_page' ) ) {
		return '';
	}

	$page_url = isset( $data['race_page'] ) ? trim( (string) $data['race_page'] ) : '';

	// Unlike Race Status, an empty setting here is not "detect the current
	// page", it is simply unconfigured: this block has no reason to exist on
	// the race's own page, so there is no current-race context to fall back
	// to. Rendering nothing is the same honest-empty-state choice race
	// status itself makes for a match that fails.
	if ( '' === $page_url ) {
		return '';
	}

	$race = arv_race_store_find_by_page( $page_url );

	if ( null === $race ) {
		return '';
	}

	$today  = arv_upcoming_races_today();
	$action = arv_upcoming_races_action( $race, $today );

	$cta_label = isset( $data['cta_label'] ) ? trim( (string) $data['cta_label'] ) : '';
	$label     = '' !== $cta_label ? $cta_label : $action['label'];

	$theme      = ( isset( $data['theme'] ) && 'light' === $data['theme'] ) ? 'light' : 'dark';
	$full_width = isset( $data['full_width'] ) ? $data['full_width'] : true;
	$full_width = ! ( 'false' === $full_width || false === $full_width || '0' === $full_width );

	// No fallback to $race['image'] here on purpose: that is the race's logo,
	// and a logo stretched to cover a full-width panel is what made the first
	// version of this block look like wallpaper with text on top. Blank means
	// no photo, which renders as the flat dark panel, which is a worse-looking
	// but still legible result rather than a broken-looking one.
	$image = isset( $data['image'] ) ? trim( (string) $data['image'] ) : '';

	$overlay = isset( $data['overlay'] ) ? (float) $data['overlay'] : 0.55;
	$overlay = max( 0, min( 1, $overlay ) );

	$base = 'arv-featured arv-featured--' . $theme;
	if ( $full_width ) {
		$base .= ' arv-featured--full';
	}

	$style = '--arv-overlay:' . esc_attr( (string) $overlay ) . ';';
	if ( '' !== $image ) {
		$base   .= ' arv-featured--image';
		$style  .= "--arv-featured-image:url('" . esc_url( $image ) . "');";
	}

	$out  = '<div class="' . arv_wrapper_class( $data, $base ) . '">';
	$out .= '<div class="arv-featured__panel" style="' . $style . '">';
	$out .= '<div class="arv-featured__inner">';
	$out .= '<div class="arv-featured__main">';

	$eyebrow = isset( $data['eyebrow'] ) ? trim( (string) $data['eyebrow'] ) : '';
	if ( '' !== $eyebrow ) {
		$out .= '<p class="arv-featured__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}

	// The race's logo, at a size where it reads as a logo. Every Aravaipa
	// race logo is a full lockup with the race's name already set in it, so
	// printing the name again underneath is saying the same thing twice in
	// two typefaces. The heading stays in the markup for search engines and
	// screen readers and is hidden visually instead, which keeps one <h2>
	// per section without duplicating it on screen.
	$logo = trim( (string) $race['image'] );
	if ( '' !== $logo ) {
		$out .= '<img class="arv-featured__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $race['name'] ) . '" loading="lazy" decoding="async" />';
	}

	$display = '' !== $race['display'] ? $race['display'] : gmdate( 'F j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );

	$name_class = ( '' !== $logo ) ? 'arv-featured__name arv-featured__name--sr' : 'arv-featured__name';
	$out       .= '<h2 class="' . $name_class . '">' . esc_html( $race['name'] ) . '</h2>';
	$out       .= '<time class="arv-featured__date" datetime="' . esc_attr( $race['iso'] ) . '">' . esc_html( $display ) . '</time>';

	$detail = isset( $data['detail'] ) ? trim( (string) $data['detail'] ) : '';
	$where  = trim( implode( ', ', array_filter( array( $race['venue'], $race['location'] ) ) ) );

	if ( '' !== $detail ) {
		$out .= '<p class="arv-featured__detail">' . esc_html( $detail ) . '</p>';
	} elseif ( '' !== $where ) {
		$out .= '<p class="arv-featured__detail">' . esc_html( $where ) . '</p>';
	}

	$distances = arv_split_distances( $race['distances'] );
	if ( ! empty( $distances ) ) {
		$out .= '<p class="arv-featured__distances">';
		foreach ( $distances as $distance ) {
			$out .= '<span class="arv-featured__pill">' . esc_html( $distance ) . '</span>';
		}
		$out .= '</p>';
	}

	// Only while entries are actually open. A deadline urging someone to
	// order before the challenge begins makes no sense once it already has
	// (Live Results, Results) or once there is nowhere left to send anyone
	// (Entries Closed, Join Waitlist): those states already answer the
	// question this line exists to create urgency about.
	$deadline_note = isset( $data['deadline_note'] ) ? trim( (string) $data['deadline_note'] ) : '';
	if ( '' !== $deadline_note && 'upcoming' === $action['phase'] ) {
		$out .= '<p class="arv-featured__deadline">' . esc_html( $deadline_note ) . '</p>';
	}

	$out .= '<div class="arv-featured__actions">';
	if ( '' !== $action['url'] ) {
		$out .= '<a class="arv-featured__cta arv-featured__cta--' . esc_attr( $action['phase'] ) . '" href="'
			. esc_url( $action['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
	} else {
		// Entries closed with nowhere to send anyone: a label, not a button,
		// same distinction every other element on this site draws.
		$out .= '<span class="arv-featured__cta arv-featured__cta--' . esc_attr( $action['phase'] ) . '">'
			. esc_html( $label ) . '</span>';
	}
	if ( '' !== $race['page'] ) {
		$out .= '<a class="arv-featured__info" href="' . esc_url( $race['page'] ) . '">' . esc_html( __( 'Race Info', 'aravaipa-elements' ) ) . '</a>';
	}
	$out .= '</div>';

	// Closes .arv-featured__main.
	$out .= '</div>';

	$out .= arv_featured_race_card( $data );

	$out .= '</div></div></div>';

	return $out;
}

/**
 * The side card: who hosts the race, what it costs, and what it syncs with.
 *
 * Split out because it is the part that answers "why would I sign up", where
 * everything above it answers "what is this". Renders nothing at all when
 * none of its fields are set, so the element stays useful for a plain
 * in-person race that has no platform, no published price and nothing to
 * sync with.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_featured_race_card( $data ) {
	$host_label = isset( $data['host_label'] ) ? trim( (string) $data['host_label'] ) : '';
	$host_url   = isset( $data['host_url'] ) ? trim( (string) $data['host_url'] ) : '';
	$host_logo  = isset( $data['host_logo'] ) ? trim( (string) $data['host_logo'] ) : '';
	$price      = isset( $data['price'] ) ? trim( (string) $data['price'] ) : '';
	$price_note = isset( $data['price_note'] ) ? trim( (string) $data['price_note'] ) : '';
	$syncs      = isset( $data['syncs'] ) ? arv_parse_list( $data['syncs'] ) : array();

	if ( '' === $host_label && '' === $price && empty( $syncs ) ) {
		return '';
	}

	$out = '<aside class="arv-featured__card">';

	if ( '' !== $host_label ) {
		$inner = '';
		if ( '' !== $host_logo ) {
			$inner .= '<img class="arv-featured__host-logo" src="' . esc_url( $host_logo ) . '" alt="" loading="lazy" decoding="async" />';
		}
		$inner .= '<span class="arv-featured__host-label">' . esc_html( $host_label ) . '</span>';

		// A link only when there is somewhere to go, the same rule every
		// other element here follows rather than emitting a dead anchor.
		$out .= '' !== $host_url
			? '<a class="arv-featured__host" href="' . esc_url( $host_url ) . '" target="_blank" rel="noopener">' . $inner . '</a>'
			: '<div class="arv-featured__host">' . $inner . '</div>';
	}

	if ( '' !== $price ) {
		$out .= '<p class="arv-featured__price">' . esc_html( $price );
		if ( '' !== $price_note ) {
			// Marked up as its own element rather than folded into the price
			// string so it can be styled as the warning it is, and so a race
			// with a price but no deadline simply omits it.
			$out .= '<span class="arv-featured__price-note">' . esc_html( $price_note ) . '</span>';
		}
		$out .= '</p>';
	}

	if ( ! empty( $syncs ) ) {
		$out .= '<ul class="arv-featured__syncs">';
		foreach ( $syncs as $sync ) {
			$out .= '<li class="arv-featured__sync">'
				. arv_featured_race_sync_mark( $sync )
				. '<span>' . esc_html( sprintf( __( 'Syncs with %s', 'aravaipa-elements' ), $sync ) ) . '</span>'
				. '<svg class="arv-featured__tick" viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" focusable="false">'
				. '<path d="M2.5 8.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
				. '</svg>'
				. '</li>';
		}
		$out .= '</ul>';
	}

	$out .= '</aside>';

	return $out;
}

/**
 * The brand mark for a platform we sync with.
 *
 * Inline SVG rather than an uploaded image, for the same reason the map's
 * pins are: these are two small vector marks, and inlining them costs no
 * requests, scales cleanly and lets them inherit sizing from CSS. Both are
 * the marks Obsession itself already uses for these integrations, so the
 * two products show the same logo for the same connection rather than
 * drifting apart.
 *
 * Anything not recognised falls back to no mark at all rather than a
 * generic placeholder: the tick beside it already says the integration
 * exists, and an invented logo for a brand would be worse than none.
 *
 * @param string $name Platform name as written in the syncs list.
 * @return string
 */
function arv_featured_race_sync_mark( $name ) {
	$key = strtolower( trim( $name ) );

	if ( 'strava' === $key ) {
		// Strava's own mark, in their orange. Taken from the asset Obsession
		// ships rather than redrawn.
		return '<svg class="arv-featured__sync-mark" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'
			. '<path fill="#FC4C02" d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066l-2.084 4.116z"/>'
			. '<path fill="#FC4C02" opacity="0.6" d="M7.778 13.828h3.065L5.63 0 0 13.828h3.065L5.63 9.076l2.148 4.752z"/>'
			. '</svg>';
	}

	if ( 'coros' === $key ) {
		return '<svg class="arv-featured__sync-mark" viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true" focusable="false">'
			. '<path d="M12 2l8 4.5v11L12 22l-8-4.5v-11z" stroke="#F2323C" stroke-width="2" stroke-linejoin="round"/>'
			. '<path d="M12 7l4 2.25V17L12 19.25 8 17V9.25z" fill="#F2323C"/>'
			. '</svg>';
	}

	return '';
}
