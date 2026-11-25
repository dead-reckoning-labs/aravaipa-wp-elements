<?php
/**
 * Aravaipa Adventures on the home page: [aravaipa_adventures_home].
 *
 * One section with every published trip as a photo tile, the next season's
 * coming-soon tile when the hub shows one, a heading, a one line intro and
 * two buttons (the hub and its interest list). Trips are read live from the
 * adventure post type, so dates and photos change the moment a trip post
 * does and the home page never carries a stale copy.
 *
 * Published trips only, even for a logged-in editor: the home page is the
 * most cached and most public page on the site, so a draft trip must never
 * ride into a cached copy of it. The stylesheet is scoped under
 * .arv-adv-home and enqueued only when the shortcode renders.
 *
 * The plugin only registers the shortcode. Nothing shows anywhere until a
 * page holds [aravaipa_adventures_home].
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grid modifier so a row never ends with a lone tile: the grid has six
 * columns and a tile spans two (three per row). Two or four tiles go two to
 * a row; otherwise with one left over the first four tiles go two to a row,
 * with two left over the first two do.
 *
 * @param int $count Number of tiles.
 * @return string Class suffix: solo, pairs, thirds, lead-2 or lead-4.
 */
function arv_adv_home_grid_mode( $count ) {
	if ( $count <= 1 ) {
		return 'solo';
	}
	if ( 2 === $count || 4 === $count ) {
		return 'pairs';
	}
	$rest = $count % 3;
	if ( 1 === $rest ) {
		return 'lead-4';
	}
	if ( 2 === $rest ) {
		return 'lead-2';
	}
	return 'thirds';
}

/**
 * One trip tile: hero photo, type label and length, destination, name and
 * dates.
 *
 * @param array $trip Trip from arv_adv_trips().
 * @return string
 */
function arv_adv_home_tile( $trip ) {
	$type   = arv_adv_type_of( $trip );
	$suffix = 'Race' === $trip['type'] && ! empty( $trip['race']['name'] ) ? ', ' . $trip['race']['name'] : '';
	$hero   = isset( $trip['photos']['hero'] ) && is_array( $trip['photos']['hero'] ) ? $trip['photos']['hero'] : array();
	$focal  = arv_adv_focal( $hero );
	$atts   = array(
		'alt'     => '',
		'loading' => 'lazy',
		'sizes'   => '(max-width: 600px) 82vw, (max-width: 979px) 50vw, 400px',
	);
	if ( '' !== $focal ) {
		$atts['style'] = 'object-position:' . $focal;
	}

	return '<a class="aah-tile" href="' . esc_url( $trip['url'] ) . '" data-type="' . esc_attr( $type['key'] ) . '">'
		. '<span class="aah-media">' . arv_adv_img( $hero, 'large', $atts ) . '</span>'
		. '<span class="aah-top"><span class="aah-badge aah-badge-' . esc_attr( $type['key'] ) . '">' . esc_html( $type['label'] ) . '</span>'
		. ( '' !== (string) $trip['length'] ? '<span class="aah-len">' . esc_html( $trip['length'] ) . '</span>' : '' ) . '</span>'
		. '<span class="aah-body">'
		. '<span class="aah-dest">' . esc_html( $trip['destination'] ) . '</span>'
		. '<span class="aah-name">' . esc_html( $trip['name'] . $suffix ) . '</span>'
		. '<span class="aah-dates">' . esc_html( $trip['dates'] ) . '</span>'
		. '</span></a>';
}

/**
 * The coming-soon season as a tile, pointing at the interest list.
 *
 * @param array $soon From arv_adv_coming_soon().
 * @param string $interest Interest list URL.
 * @return string
 */
function arv_adv_home_soon_tile( $soon, $interest ) {
	$photo = isset( $soon['photo'] ) && is_array( $soon['photo'] ) ? $soon['photo'] : array();
	return '<a class="aah-tile aah-tile-soon" href="' . esc_url( $interest ) . '">'
		. '<span class="aah-media">' . arv_adv_img( $photo, 'large', array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 600px) 82vw, (max-width: 979px) 50vw, 400px' ) ) . '</span>'
		. '<span class="aah-top"><span class="aah-badge aah-badge-soon">' . (int) $soon['year'] . ' Season</span></span>'
		. '<span class="aah-body">'
		. '<span class="aah-dest">' . esc_html( $soon['title'] ) . '</span>'
		. '<span class="aah-name">' . esc_html( $soon['cta'] ) . '</span>'
		. '</span></a>';
}

/**
 * The section markup. Pure apart from the URL and image helpers, so the dev
 * harness can feed it trips directly.
 *
 * @param array      $trips Published trips.
 * @param array|null $soon  Coming-soon season or null.
 * @param array      $atts  heading, intro.
 * @return string Empty when there are no trips.
 */
function arv_adv_home_render( $trips, $soon, $atts = array() ) {
	if ( empty( $trips ) ) {
		return '';
	}
	$atts     = array_merge(
		array(
			'heading' => 'Aravaipa Adventures',
			'intro'   => 'Small-group trail running trips and race weeks abroad, from Madeira to Patagonia, run with Trail Run Adventures.',
		),
		$atts
	);
	$hub      = home_url( '/adventures/' );
	$interest = home_url( '/adventures/#interest' );

	$tiles = '';
	foreach ( $trips as $trip ) {
		$tiles .= arv_adv_home_tile( $trip );
	}
	$count = count( $trips );
	if ( $soon ) {
		$tiles .= arv_adv_home_soon_tile( $soon, $interest );
		++$count;
	}

	return '<section class="arv-adv-home" aria-labelledby="aah-heading">'
		. '<div class="aah-wrap">'
		. '<div class="aah-head">'
		. '<div class="aah-head-text">'
		. '<span class="aah-kicker">Trail running trips</span>'
		. '<h2 class="aah-title" id="aah-heading">' . esc_html( $atts['heading'] ) . '</h2>'
		. '<p class="aah-intro">' . esc_html( $atts['intro'] ) . '</p>'
		. '</div>'
		. '<div class="aah-actions">'
		. '<a class="aah-btn aah-btn-yellow" href="' . esc_url( $hub ) . '">See All Trips</a>'
		. '<a class="aah-btn aah-btn-dark" href="' . esc_url( $interest ) . '">Join the Interest List</a>'
		. '</div>'
		. '</div>'
		. '<div class="aah-grid aah-grid-' . esc_attr( arv_adv_home_grid_mode( $count ) ) . '">' . $tiles . '</div>'
		. '</div></section>';
}

/**
 * [aravaipa_adventures_home heading="" intro=""].
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function arv_adv_home_shortcode( $atts ) {
	$atts  = shortcode_atts(
		array(
			'heading' => 'Aravaipa Adventures',
			'intro'   => 'Small-group trail running trips and race weeks abroad, from Madeira to Patagonia, run with Trail Run Adventures.',
		),
		$atts,
		'aravaipa_adventures_home'
	);
	$trips = arv_adv_trips( false );
	if ( empty( $trips ) ) {
		return '';
	}
	$soon = arv_adv_coming_soon( arv_adv_landing_config(), $trips );

	wp_enqueue_style( 'aravaipa-adventures-home', ARV_ELEMENTS_URL . 'assets/aravaipa-adventures-home.css', array(), ARV_ELEMENTS_VERSION );

	return arv_adv_home_render( $trips, $soon, $atts );
}
add_shortcode( 'aravaipa_adventures_home', 'arv_adv_home_shortcode' );
