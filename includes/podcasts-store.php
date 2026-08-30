<?php
/**
 * Aravaipa's podcasts, embedded rather than read.
 *
 * Unlike Watch and Films, there is nothing to fetch. Spotify's own embed for
 * a show is a stable URL built from nothing but the show's id
 * (open.spotify.com/embed/show/{id}) and renders itself: player, artwork,
 * and the full, always-current episode list, scrollable inside the frame.
 * A new episode needs no work here for the same reason a new film needs
 * none on the Films page, except there is not even an API call in between;
 * the two shows are simply embedded.
 *
 * The two ids are literal for the same reason the two YouTube playlist ids
 * are literal in includes/films-store.php: which shows these are is a
 * decision about what the site carries, not configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aravaipa's shows: label and Spotify show id.
 *
 * Filterable so a third show, or a rename, does not need a plugin release.
 *
 * @return array<int, array{title: string, id: string}>
 */
function arv_podcasts_shows() {
	return apply_filters(
		'arv_podcasts_shows',
		array(
			array( 'title' => 'Inside Aravaipa', 'id' => '0MvdUlDE9VwocRhrIl9Lwv' ),
			array( 'title' => 'White Mountain Endurance Podcast', 'id' => '4cg3hl6Ek6pjd76ymrbUQd' ),
		)
	);
}

/**
 * The Podcasts page: one embed per show.
 *
 * Renders nothing when there are no shows configured, the same "no section
 * reads as unbuilt, not broken" rule Watch and Films use, reachable here
 * only through the filter above since the two shipped shows are never
 * empty on their own.
 *
 * @param array $args heading, intro.
 * @return string
 */
function arv_podcasts_render( $args = array() ) {
	$shows = arv_podcasts_shows();

	if ( empty( $shows ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Podcasts';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';

	$out = '<section class="arv-podcasts">';
	$out .= '<div class="arv-podcasts__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-podcasts__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-podcasts__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-podcasts__grid">';

	foreach ( $shows as $show ) {
		if ( empty( $show['id'] ) || empty( $show['title'] ) ) {
			continue;
		}

		$out .= arv_podcasts_card( $show );
	}

	$out .= '</div>';

	return $out . '</div></section>';
}

/**
 * One show: its name and Spotify's own embed, which carries the episode
 * list itself.
 *
 * loading="lazy" on an iframe this small is not about page weight, it is
 * about the same third-party-player courtesy the Watch and Films pages
 * apply to their own embeds: nothing autoplaying that nobody asked for.
 *
 * @param array $show title, id.
 * @return string
 */
function arv_podcasts_card( $show ) {
	$out = '<div class="arv-podcasts__card">';
	$out .= '<h3 class="arv-podcasts__name">' . esc_html( $show['title'] ) . '</h3>';
	$out .= '<iframe class="arv-podcasts__frame"'
		. ' src="https://open.spotify.com/embed/show/' . esc_attr( $show['id'] ) . '"'
		. ' title="' . esc_attr( $show['title'] ) . '"'
		. ' loading="lazy" allowfullscreen'
		. ' allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"'
		. '></iframe>';

	$out .= '<a class="arv-podcasts__open" href="https://open.spotify.com/show/' . esc_attr( $show['id'] ) . '"'
		. ' target="_blank" rel="noopener">'
		. esc_html__( 'Open in Spotify', 'aravaipa-elements' )
		. '</a>';

	return $out . '</div>';
}

/**
 * [arv_podcasts] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_podcasts_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Podcasts',
			'intro'   => '',
		),
		$atts,
		'arv_podcasts'
	);

	return arv_podcasts_render( $atts );
}
add_shortcode( 'arv_podcasts', 'arv_podcasts_shortcode' );
