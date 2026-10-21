<?php
/**
 * Aravaipa Adventures: markup for the trip page, the landing and the
 * interest form. A straight port of the v3 Astro templates
 * (src/pages/index.astro, src/pages/trips/[slug].astro, src/components),
 * with every class carrying the aa- prefix the scoped stylesheet expects.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two-digit counter (01, 02).
 *
 * @param int $n Number.
 * @return string
 */
function arv_adv_pad( $n ) {
	return str_pad( (string) $n, 2, '0', STR_PAD_LEFT );
}

/**
 * Type badge.
 *
 * @param array $trip Trip.
 * @return string
 */
function arv_adv_badge( $trip ) {
	$type = arv_adv_type_of( $trip );
	return '<span class="aa-badge aa-badge-' . esc_attr( $type['key'] ) . '">' . esc_html( $type['label'] ) . '</span>';
}

/**
 * Trip tile: photo, badge and length top left, destination over the image,
 * name and dates under it.
 *
 * @param array $trip  Trip.
 * @param bool  $eager Load the image eagerly.
 * @return string
 */
function arv_adv_tile( $trip, $eager = false ) {
	$type   = arv_adv_type_of( $trip );
	$suffix = 'Race' === $trip['type'] && ! empty( $trip['race']['name'] ) ? ', ' . $trip['race']['name'] : '';

	return '<a class="aa-tile" href="' . esc_url( $trip['url'] ) . '" data-type="' . esc_attr( $type['key'] ) . '">'
		. '<div class="aa-tile-media">' . arv_adv_img( $trip['photos']['hero'], 'large', array( 'alt' => '', 'loading' => $eager ? 'eager' : 'lazy', 'sizes' => '(max-width: 600px) 100vw, (max-width: 979px) 50vw, 600px' ) ) . '</div>'
		. '<span class="aa-tile-top">' . arv_adv_badge( $trip ) . '<span class="aa-tile-len">' . esc_html( $trip['length'] ) . '</span></span>'
		. '<div class="aa-tile-body">'
		. '<span class="aa-tile-dest">' . esc_html( $trip['destination'] ) . '</span>'
		. '<span class="aa-tile-name"><span>' . esc_html( $trip['name'] . $suffix ) . '</span><em>' . esc_html( $trip['dates'] ) . '</em></span>'
		. '</div></a>';
}

/**
 * FAQ accordion rows.
 *
 * @param array $faqs [[question, answer html], ...].
 * @return string
 */
function arv_adv_faq_rows( $faqs ) {
	$out = '';
	foreach ( $faqs as $faq ) {
		if ( ! is_array( $faq ) || count( $faq ) < 2 ) {
			continue;
		}
		$out .= '<details class="aa-faq"><summary>' . esc_html( $faq[0] ) . '</summary><div class="aa-faq-body"><p>' . wp_kses_post( $faq[1] ) . '</p></div></details>';
	}
	return $out;
}

/**
 * Interest form section. The form itself is Contact Form 7 (the site's form
 * plugin), so entries land in Flamingo and mail goes out the same way every
 * other form on the site does. See includes/adventures-form.php.
 *
 * @param array  $photo     Photo for the left half.
 * @param string $heading   Heading.
 * @param string $preselect Trip slug to preselect.
 * @return string
 */
function arv_adv_render_form( $photo, $heading, $preselect = '' ) {
	$form = function_exists( 'arv_adv_form_html' ) ? arv_adv_form_html( $preselect ) : '';
	if ( '' === $form ) {
		$form = '<p><a class="aa-btn aa-btn-yellow" href="mailto:info@aravaiparunning.com?subject=' . rawurlencode( 'Aravaipa Adventures interest' ) . '">Email us</a></p>';
	}

	return '<section class="aa-interest" id="interest">'
		. '<div class="aa-interest-media" style="' . esc_attr( arv_adv_bg( $photo ) ) . '" role="img" aria-label="' . esc_attr( isset( $photo['alt'] ) ? $photo['alt'] : '' ) . '"></div>'
		. '<div class="aa-interest-body">'
		. '<span class="aa-kicker">Join the Interest List</span>'
		. '<h2 class="aa-display">' . esc_html( $heading ) . '</h2>'
		. '<p>Spots are limited. Get on the list and we will reach out first when registration opens.</p>'
		. $form
		. '</div></section>';
}

/**
 * Partner line under the difficulty: who hosts this trip.
 *
 * @param array $trip Trip.
 * @return array{0: string, 1: string} [logo html, sentence html]
 */
function arv_adv_host_line( $trip ) {
	$icon    = '<img src="' . esc_url( ARV_ELEMENTS_URL . 'assets/adventures/arv-icon.png' ) . '" alt="Aravaipa Running" width="60" height="60" loading="lazy" />';
	$tra     = '<a href="https://www.trailrunadventures.com" target="_blank" rel="noopener">Trail Run Adventures</a>';
	$about   = '<a href="' . esc_url( arv_adv_landing_url() . '#partner' ) . '">About our partner</a>';
	$config  = arv_adv_landing_config();

	if ( 'tbd' === $trip['host'] ) {
		return array( $icon, 'Hosted by Aravaipa Adventures, with a guide team and shuttle crew that know the San Juans. Hosts to be announced.', 'Aravaipa Adventures hosts this trip. The guide team and shuttle crew are being confirmed and will be announced with dates and pricing.' );
	}
	if ( 'aravaipa' === $trip['host'] ) {
		$race = ! empty( $trip['race']['name'] ) ? $trip['race']['name'] : 'race';
		return array( $icon, 'Hosted by Aravaipa race staff, the team that designs, marks, and runs the ' . esc_html( $race ) . '. Runs are led and supported by our crew.', 'Aravaipa race staff host this camp directly: the same team that designs the course and runs race week.' );
	}

	$logo = ! empty( $config['partner_logo'] ) ? arv_adv_img( $config['partner_logo'], 'thumbnail', array( 'alt' => 'Trail Run Adventures logo', 'width' => '60', 'height' => '60' ) ) : '';
	$line = 'Run in partnership with ' . $tra . ', guided on the ground by their team and local mountain guides, with an Aravaipa trip lead. ' . $about . '.';
	return array( $logo, $line, $line );
}

/**
 * The trip page body.
 *
 * @param array $trip Trip.
 * @return string
 */
function arv_adv_render_trip( $trip ) {
	$type     = arv_adv_type_of( $trip );
	$race     = $trip['race'];
	$photos   = $trip['photos'];
	$config   = arv_adv_landing_config();
	$host     = arv_adv_host_line( $trip );
	$diff     = $trip['difficulty'];
	$group    = is_numeric( $trip['group_size'] ) ? 'Max ' . (int) $trip['group_size'] . ' runners' : $trip['group_size'];
	$race_facts = ! empty( $race['facts'] ) && is_array( $race['facts'] ) ? $race['facts'] : array(
		array( 'Race Entry', 'TBD, being confirmed with the race and our partner.' ),
		array( 'Race Week', 'We handle packet pickup, shuttles to the start, and the post-race celebration.' ),
	);

	$o  = '<div class="arv-adv">';

	// Hero.
	$o .= '<section class="aa-hero aa-trip" style="' . esc_attr( arv_adv_bg( $photos['hero'] ) ) . '"><div class="aa-container">'
		. '<div class="aa-hero-kicker">' . arv_adv_badge( $trip ) . '<span>Aravaipa Adventures</span></div>'
		. '<h1 class="aa-hero-title">' . esc_html( $trip['name'] ) . '</h1>'
		. ( '' !== $trip['subtitle'] ? '<p class="aa-hero-sub aa-trip-subtitle">' . esc_html( $trip['subtitle'] ) . '</p>' : '' )
		. '<div class="aa-hero-meta"><span>' . esc_html( $trip['destination'] ) . '</span><span>' . esc_html( $trip['length'] ) . '</span></div>'
		. '<div class="aa-hero-actions"><a class="aa-btn aa-btn-yellow" href="#interest">Interest List</a><a class="aa-btn aa-btn-line" href="#itinerary">Itinerary</a></div>'
		. '</div></section>';

	// At a glance.
	$glance = array(
		array( 'Trip Type', arv_adv_badge( $trip ) ),
		array( 'Length', esc_html( $trip['length'] ) ),
		array( 'Group Size', esc_html( $group ) ),
		array( 'Difficulty', esc_html( $diff . ' of 5 (' . arv_adv_difficulty_label( $diff ) . ')' ) ),
		array( 'Fly Into', esc_html( $trip['airport'] ) ),
	);
	if ( ! empty( $race['name'] ) ) {
		$glance[] = array( 'Race', ! empty( $race['url'] ) ? '<a href="' . esc_url( $race['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $race['name'] ) . '</a>' : esc_html( $race['name'] ) );
	}
	$glance[] = array( 'Price', esc_html( $trip['price'] ) );

	$o .= '<section class="aa-glance" aria-label="Trip at a glance"><h2 class="aa-glance-date">' . esc_html( $trip['dates'] ) . '</h2><dl class="aa-container aa-glance-grid">';
	foreach ( $glance as $row ) {
		$o .= '<div><dt>' . esc_html( $row[0] ) . '</dt><dd>' . $row[1] . '</dd></div>';
	}
	$o .= '</dl></section>';

	// Story.
	$o .= '<section class="aa-section"><div class="aa-container aa-split aa-wide-text"><div class="aa-split-text">'
		. '<p class="aa-story-sub">' . esc_html( $type['label'] . ' | ' . $trip['length'] . ' | ' . arv_adv_group_label( $trip ) ) . '</p>'
		. '<h2 class="aa-story-statement">' . esc_html( $trip['tagline'] ) . '</h2>';
	foreach ( $trip['overview'] as $p ) {
		$o .= '<p>' . esc_html( $p ) . '</p>';
	}
	if ( '' !== $trip['note'] ) {
		$o .= '<p class="aa-trip-note">' . esc_html( $trip['note'] ) . '</p>';
	}
	$o .= '</div><div class="aa-split-media">' . arv_adv_img( $photos['intro'], 'large', array( 'alt' => isset( $photos['intro']['alt'] ) && '' !== $photos['intro']['alt'] ? $photos['intro']['alt'] : $trip['destination'], 'loading' => 'eager', 'sizes' => '(max-width: 979px) 100vw, 500px' ) ) . '</div></div></section>';

	// Highlights.
	if ( $trip['highlights'] ) {
		$o .= '<section class="aa-section aa-gray aa-tight"><div class="aa-container"><span class="aa-kicker">Highlights</span><h2 class="aa-h2">Why this trip</h2>'
			. '<div class="aa-numbered aa-plain" style="--aa-n:' . count( $trip['highlights'] ) . '">';
		foreach ( array_values( $trip['highlights'] ) as $i => $h ) {
			$o .= '<div><div class="aa-num">' . arv_adv_pad( $i + 1 ) . '</div><p>' . esc_html( $h ) . '</p></div>';
		}
		$o .= '</div></div></section>';
	}

	// Itinerary.
	$o .= '<section class="aa-section" id="itinerary"><div class="aa-container aa-itin-grid"><div class="aa-itin-side"><span class="aa-kicker">Itinerary</span><h2 class="aa-display">Day by Day</h2><p class="aa-small-note">Sample itinerary, subject to change.</p></div><ol class="aa-timeline">';
	foreach ( array_values( $trip['itinerary'] ) as $i => $d ) {
		$o .= '<li><div class="aa-day"><small>Day</small><b>' . arv_adv_pad( $i + 1 ) . '</b></div><div><h3>' . esc_html( isset( $d['title'] ) ? $d['title'] : '' ) . '</h3>'
			. ( ! empty( $d['stats'] ) ? '<p class="aa-itin-stats">' . esc_html( $d['stats'] ) . '</p>' : '' )
			. '<p>' . esc_html( isset( $d['body'] ) ? $d['body'] : '' ) . '</p></div></li>';
	}
	$o .= '</ol></div></section>';

	// Gallery.
	$gallery = $photos['gallery'];
	if ( $gallery ) {
		$n       = count( $gallery );
		$credit  = '' !== $trip['gallery_credit'] ? $trip['gallery_credit'] : 'Photos: Trail Run Adventures, from past trips';
		$o      .= '<section class="aa-section aa-tight" style="padding-top:0;' . ( $race ? '' : 'padding-bottom:0' ) . '"><div class="aa-container">'
			. '<div class="aa-gallery-head"><h2 class="aa-h2">From the Trail</h2><p class="aa-small-note">' . esc_html( $credit ) . '</p></div>'
			. '<div class="aa-gallery aa-g-' . $n . '" style="--aa-g:' . $n . '">';
		foreach ( $gallery as $g ) {
			$o .= arv_adv_img( $g, 'large', array( 'sizes' => '(max-width: 600px) 50vw, 300px' ) );
		}
		$o .= '</div></div></section>';
	}

	// The race.
	if ( $race ) {
		$o .= '<section class="aa-race-band" id="race"><div class="aa-container aa-race-grid"><div>'
			. '<span class="aa-kicker aa-on-dark">The Race</span><h2 class="aa-display">' . esc_html( $race['name'] ) . '</h2>'
			. '<p class="aa-race-date">' . esc_html( isset( $race['date'] ) ? $race['date'] : '' ) . '</p>'
			. ( ! empty( $race['url'] ) ? '<a class="aa-text-link aa-on-dark" href="' . esc_url( $race['url'] ) . '" target="_blank" rel="noopener">Race Website</a>' : '' )
			. '</div><div><p>' . esc_html( isset( $race['blurb'] ) ? $race['blurb'] : '' ) . '</p><dl class="aa-race-facts">';
		foreach ( $race_facts as $f ) {
			$o .= '<div><dt>' . esc_html( $f[0] ) . '</dt><dd>' . esc_html( $f[1] ) . '</dd></div>';
		}
		$o .= '</dl></div></div></section>';
	}

	// Inclusions.
	$o .= '<section class="aa-section"><div class="aa-container"><span class="aa-kicker">Inclusions</span><h2 class="aa-display" style="margin:0">What is included</h2><div class="aa-inc-grid">'
		. '<div><h3>Included</h3><ul class="aa-inc-list aa-yes">';
	foreach ( $trip['included'] as $x ) {
		$o .= '<li>' . esc_html( $x ) . '</li>';
	}
	$o .= '</ul></div><div><h3>Not Included</h3><ul class="aa-inc-list aa-no">';
	foreach ( $trip['not_included'] as $x ) {
		$o .= '<li>' . esc_html( $x ) . '</li>';
	}
	$o .= '</ul></div></div><p class="aa-small-note" style="margin-top:22px">Draft: inclusions are planned, not final. Items marked TBD are still being confirmed.</p></div></section>';

	// Difficulty + dates and pricing.
	$fine = '' !== $trip['fineprint'] ? $trip['fineprint']
		: 'Pricing is being finalized. A deposit holds your spot, with the balance due 90 days before departure. Twin share by default, single room upgrade available.'
		. ( '' !== $trip['min_group'] ? ' Minimum ' . (int) $trip['min_group'] . ' travelers for the trip to run.' : '' ) . ' Flights not included.';

	$o .= '<section class="aa-section aa-gray" id="dates"><div class="aa-container aa-plan-grid"><div>'
		. '<span class="aa-kicker">Difficulty</span><h2 class="aa-display">' . esc_html( arv_adv_difficulty_label( $diff ) . ', ' . $diff . ' of 5' ) . '</h2>'
		. '<div class="aa-diff-scale" aria-hidden="true">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$o .= '<span' . ( $i <= $diff ? ' class="aa-on"' : '' ) . '>' . $i . '</span>';
	}
	$o .= '</div><p>' . esc_html( $trip['difficulty_note'] ) . '</p>'
		. '<div class="aa-partner-line">' . $host[0] . '<p>' . $host[1] . '</p></div>'
		. '</div><aside class="aa-price-panel"><h3>Dates and Pricing</h3><dl class="aa-price-rows">'
		. '<div><dt>Trip Dates</dt><dd>' . esc_html( $trip['dates'] ) . '</dd></div>'
		. '<div><dt>Length</dt><dd>' . esc_html( $trip['length'] ) . '</dd></div>'
		. '<div><dt>Group Size</dt><dd>' . esc_html( is_numeric( $trip['group_size'] ) ? 'Max ' . (int) $trip['group_size'] : $trip['group_size'] ) . '</dd></div>'
		. '<div><dt>Trip Price</dt><dd>' . esc_html( $trip['price'] ) . '</dd></div>'
		. '<div><dt>Single Room</dt><dd>' . esc_html( $trip['single_room'] ) . '</dd></div>'
		. '<div><dt>Registration Opens</dt><dd>' . esc_html( $trip['reg_opens'] ) . '</dd></div>'
		. '</dl><p class="aa-fineprint">' . esc_html( $fine ) . '</p>'
		. '<a class="aa-btn aa-btn-yellow" href="#interest">Join the Interest List</a>'
		. '<p class="aa-small-note">*Draft trip. Dates, inclusions, and pricing are not final.</p>'
		. '</aside></div></section>';

	// Need to know.
	$faqs = isset( $config['faqs'] ) && is_array( $config['faqs'] ) ? $config['faqs'] : array();
	$o   .= '<section class="aa-section" id="faq"><div class="aa-container aa-faq-grid"><div><span class="aa-kicker">FAQ</span><h2 class="aa-display">Need to Know</h2></div><div class="aa-faq-list">'
		. '<details class="aa-faq"><summary>Who Runs This Trip</summary><div class="aa-faq-body"><p>' . $host[2] . '</p></div></details>'
		. arv_adv_faq_rows( $faqs )
		. '</div></div></section>';

	// Interest form.
	$o .= arv_adv_render_form( $photos['form'], $trip['name'] . ' Interest List', $trip['slug'] );

	// More trips.
	$others = array();
	foreach ( arv_adv_trips() as $t ) {
		if ( $t['id'] !== $trip['id'] && $t['name'] !== $trip['name'] ) {
			$others[] = $t;
		}
		if ( count( $others ) >= 3 ) {
			break;
		}
	}
	if ( $others ) {
		$o .= '<section class="aa-section"><div class="aa-container"><div class="aa-trips-head"><div><span class="aa-kicker">Keep exploring</span><h2 class="aa-display" style="margin:0">More adventures</h2></div>'
			. '<a class="aa-text-link" href="' . esc_url( arv_adv_landing_url() . '#trips' ) . '">See all trips</a></div><div class="aa-tile-grid">';
		foreach ( $others as $t ) {
			$o .= arv_adv_tile( $t, true );
		}
		$o .= '</div></div></section>';
	}

	return $o . '</div>';
}

/**
 * The landing body ([arv_adventures]).
 *
 * @return string
 */
function arv_adv_render_landing() {
	$trips  = arv_adv_trips();
	$config = arv_adv_landing_config();
	$photo  = static function ( $key ) use ( $config ) {
		return isset( $config[ $key ] ) && is_array( $config[ $key ] ) ? $config[ $key ] : array();
	};

	$seasons   = array();
	$countries = array();
	foreach ( $trips as $t ) {
		$seasons[ $t['sort_year'] ][] = $t;
		$parts                        = explode( ',', $t['destination'] );
		$countries[ trim( end( $parts ) ) ] = true;
	}
	ksort( $seasons );
	$years       = array_keys( $seasons );
	$season_span = $years ? ( count( $years ) > 1 ? reset( $years ) . '-' . substr( (string) end( $years ), -2 ) : (string) reset( $years ) ) : '';

	$how = array(
		array( 'Small Groups', 'Never more than 12 runners, and as few as 6. Big enough to make friends, small enough that nobody waits on a bus.' ),
		array( 'Real Guides', 'Run on the ground by Trail Run Adventures and their local guides, with every pace looked after and every detail handled.' ),
		array( 'Race Week, Handled', 'Race trips build the whole week around the start line: packet pickup, shuttles, and the party after.' ),
		array( 'Unmistakably Aravaipa', 'An Aravaipa SWAG kit for every traveler, a personalized finisher\'s medal on the final night, and a closing ceremony you will remember.' ),
	);
	$who = array(
		array( 'The Bucket-List Racer', 'You want to run Madeira or Fitz Roy without spending months on logistics.' ),
		array( 'The Explorer', 'Races are fine, but you would rather link up an entire mountain range point to point.' ),
		array( 'The Aravaipa Regular', 'You have run our desert races and want the same community somewhere new.' ),
		array( 'The Solo Traveler', 'Come alone, leave with a crew. Shared rooms are the default and singles are available.' ),
	);

	$o = '<div class="arv-adv arv-adv-landing">';

	$o .= '<section class="aa-hero" style="' . esc_attr( arv_adv_bg( $photo( 'hero' ) ) ) . '"><div class="aa-container">'
		. '<h1 class="aa-hero-title">Aravaipa Adventures</h1>'
		. '<p class="aa-hero-sub">Race trips, training camps, and guided trail running adventures, from Arizona to Patagonia.</p>'
		. '<div class="aa-hero-actions"><a class="aa-btn aa-btn-yellow" href="#trips">Find Your Next Adventure</a><a class="aa-btn aa-btn-line" href="#interest">Interest List</a></div>'
		. '</div></section>';

	$o .= '<section class="aa-facts"><div class="aa-container aa-facts-grid">'
		. '<div><div class="aa-fact-num">' . count( $trips ) . '</div><div class="aa-fact-label">Departures</div></div>'
		. '<div><div class="aa-fact-num">' . count( $countries ) . '</div><div class="aa-fact-label">Countries</div></div>'
		. '<div><div class="aa-fact-num">12</div><div class="aa-fact-label">Runners Max Per Trip</div></div>'
		. '<div><div class="aa-fact-num">' . esc_html( $season_span ) . '</div><div class="aa-fact-label">Season</div></div>'
		. '</div></section>';

	$o .= '<section class="aa-section aa-tight" style="padding-top:40px"><div class="aa-container aa-intro-grid"><div>'
		. '<span class="aa-kicker">Aravaipa Adventures</span><h2 class="aa-display">Small groups. Great guides. Real trail, run every day.</h2></div><div>'
		. '<p class="aa-lede">For years Aravaipa Running has built trail races in the Arizona desert and beyond. Aravaipa Adventures takes that same crew mentality on the road, to the trails our runners already dream about: race weeks that finish at the Ultra Fitz Roy or Ultra Skyrunning Madeira, the Milford, Routeburn, and Kepler tracks, the Julian Alps, a chartered boat through the Lofoten islands, the Hardrock loop in Colorado\'s San Juans run in stages, and a training camp on our own Cocodona 250 course at home in Arizona.</p>'
		. '<p>We handle the hotels, transfers, meals, and logistics, so the only thing on your plate is the mountain in front of you. Race trips build the week around a world-class event. Training camps put you on a race course before race day. Adventures are pure exploration, point to point through ranges we love.</p>'
		. '<a class="aa-text-link" href="#trips">See the Trips</a></div></div></section>';

	$pano = $photo( 'pano' );
	$o   .= '<div class="aa-pano" style="' . esc_attr( arv_adv_bg( $pano ) ) . '" role="img" aria-label="' . esc_attr( isset( $pano['alt'] ) ? $pano['alt'] : '' ) . '"></div>';

	$types   = arv_adv_types();
	$o      .= '<section class="aa-section" id="trips"><div class="aa-container">'
		. '<div class="aa-trips-head"><div><span class="aa-kicker">The Trips</span><h2 class="aa-display" style="margin:0">Trips open for interest</h2></div><a class="aa-text-link" href="#interest">Join the Interest List</a></div>'
		. '<div class="aa-trip-filter" role="group" aria-label="Filter trips by type"><button type="button" data-filter="all" aria-pressed="true">All</button>';
	foreach ( $types as $t ) {
		$o .= '<button type="button" data-filter="' . esc_attr( $t['key'] ) . '" aria-pressed="false"><span class="aa-swatch aa-' . esc_attr( $t['key'] ) . '" aria-hidden="true"></span>' . esc_html( $t['plural'] ) . '</button>';
	}
	$o    .= '</div>';
	$first = true;
	foreach ( $seasons as $year => $list ) {
		$n     = count( $list );
		$class = 4 === $n ? ' aa-pairs' : ( 5 === $n ? ' aa-lead-2' : '' );
		$o    .= '<div class="aa-season"><div class="aa-season-label">' . (int) $year . ' <small>Season</small></div><div class="aa-tile-grid' . $class . '">';
		foreach ( $list as $t ) {
			$o .= arv_adv_tile( $t, $first );
		}
		$o    .= '</div></div>';
		$first = false;
	}
	$o .= '<p class="aa-grid-note">Race entry details are being confirmed for each race trip. Dates are the planned month and may shift as itineraries are confirmed.</p></div></section>';

	$o .= '<section class="aa-section aa-gray" id="how"><div class="aa-container"><span class="aa-kicker">How It Works</span><h2 class="aa-display" style="max-width:760px">Every detail handled, so you can just run.</h2><div class="aa-numbered" style="--aa-n:4">';
	foreach ( $how as $i => $h ) {
		$o .= '<div><div class="aa-num">' . arv_adv_pad( $i + 1 ) . '</div><h3>' . esc_html( $h[0] ) . '</h3><p>' . esc_html( $h[1] ) . '</p></div>';
	}
	$o .= '</div></div></section>';

	$o .= '<section class="aa-section"><div class="aa-container aa-split"><div class="aa-split-media">' . arv_adv_img( $photo( 'who' ), 'large', array( 'sizes' => '(max-width: 979px) 100vw, 560px' ) ) . '</div>'
		. '<div class="aa-split-text"><span class="aa-kicker">Who It Is For</span><h2 class="aa-display">Trail runners who travel to run.</h2>'
		. '<p>You do not need to be fast. You do need to love long days outside. If you have ever planned a vacation around a trail, you are our people.</p><div class="aa-persona-grid">';
	foreach ( $who as $w ) {
		$o .= '<div><h3>' . esc_html( $w[0] ) . '</h3><p>' . esc_html( $w[1] ) . '</p></div>';
	}
	$o .= '</div></div></div></section>';

	$o .= '<section class="aa-partner" id="partner"><div class="aa-partner-media">' . arv_adv_img( $photo( 'partner_photo' ), 'large', array( 'alt' => 'Trail Run Adventures founders', 'sizes' => '(max-width: 979px) 100vw, 50vw' ) ) . '<p class="aa-caption">Photo: Trail Run Adventures</p></div>'
		. '<div class="aa-partner-text">' . arv_adv_img( $photo( 'partner_logo' ), 'thumbnail', array( 'class' => 'aa-partner-logo', 'alt' => 'Trail Run Adventures logo', 'width' => '76', 'height' => '76' ) )
		. '<span class="aa-kicker aa-on-dark">Our partner on the ground</span><h2 class="aa-display">Trail Run Adventures</h2>'
		. '<p>Every Aravaipa Adventures trip is run with Trail Run Adventures, a family-run company founded in 2016 by Anna Frost and her husband, Ron "Braz" Braselton. They run small-group, fully guided trail running trips to Madeira, New Zealand, Japan, the Julian Alps, Norway\'s Lofoten islands, Patagonia, and the Tour du Mont Blanc, with the lodging, meals, and local guides all handled and trips built for runners of every ability.</p>'
		. '<div class="aa-founders"><div><h3>Anna Frost</h3><p>"Frosty" is a professional mountain, trail, and ultra runner with decades of international racing, including Hardrock 100 podiums and six Skyrunner World Series wins. She splits her time between Durango, Colorado and her native New Zealand, and often runs right alongside guests.</p></div>'
		. '<div><h3>Ron "Braz" Braselton</h3><p>A trail lover who built his travels around races and new cultures, and that is how Trail Run Adventures was born. He co-guides every trip and is known for anticipating the details: the right trail, the right amount of water, the right meal at the right time.</p></div></div>'
		. '<p>Aravaipa brings the race-day know-how and the community. Trail Run Adventures brings the routes, the local guides, and years of running these exact trips.</p>'
		. '<a class="aa-btn aa-btn-yellow" href="https://www.trailrunadventures.com" target="_blank" rel="noopener">trailrunadventures.com</a>'
		. '</div></section>';

	$faqs = isset( $config['faqs'] ) && is_array( $config['faqs'] ) ? $config['faqs'] : array();
	$o   .= '<section class="aa-section" id="faq"><div class="aa-container aa-faq-grid"><div><span class="aa-kicker">FAQ</span><h2 class="aa-display">Need to Know</h2></div><div class="aa-faq-list">'
		. arv_adv_faq_rows( $faqs ) . '</div></div></section>';

	$o .= arv_adv_render_form( $photo( 'form' ), 'Aravaipa Adventures Interest List' );

	// Photo credits, folded so they cost the page nothing until opened.
	$credits = isset( $config['credits'] ) && is_array( $config['credits'] ) ? $config['credits'] : array();
	if ( $credits ) {
		$o .= '<section class="aa-credits" id="credits"><div class="aa-container"><details><summary>Photo Credits</summary><div class="aa-credits-inner">';
		if ( ! empty( $config['credits_intro'] ) ) {
			$o .= '<p>' . wp_kses_post( $config['credits_intro'] ) . '</p>';
		}
		$o .= '<ul class="aa-credits-list">';
		foreach ( $credits as $c ) {
			$o .= '<li>' . arv_adv_img( isset( $c['photo'] ) ? $c['photo'] : array(), 'thumbnail', array( 'alt' => '' ) )
				. '<div><strong>' . esc_html( isset( $c['label'] ) ? $c['label'] : '' ) . '</strong><br />' . wp_kses_post( isset( $c['html'] ) ? $c['html'] : '' ) . '</div></li>';
		}
		$o .= '</ul>';
		if ( ! empty( $config['credits_outro'] ) ) {
			$o .= '<p>' . wp_kses_post( $config['credits_outro'] ) . '</p>';
		}
		$o .= '</div></details></div></section>';
	}

	return $o . '</div>';
}
