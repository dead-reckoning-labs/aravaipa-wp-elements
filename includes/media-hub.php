<?php
/**
 * Aravaipa Media: one page linking Watch, Films, Podcasts, Photos and the
 * blog.
 *
 * Everything this links to already has a real home: Watch and Films read
 * live feeds, Podcasts embeds Spotify directly, Photos and the blog are
 * existing top-level pages. This page exists because the site's Media menu
 * pointed at mountainoutpost.com and nothing on this site answered "where
 * is Aravaipa's media", not because any of the content needed a new place
 * to live.
 *
 * Loaded unconditionally, like watch-store.php, films-store.php and
 * podcasts-store.php, so [arv_media_hub] works on a page without
 * Cornerstone active. The element wrapper is
 * includes/elements/media-hub.php.
 *
 * Every card carries a live thumbnail, read from the same stores each
 * section already loads: the newest broadcast, film, podcast episode and
 * blog post. Nothing here is a stored image that a visit to the section
 * itself would immediately contradict.
 *
 * That was not always true. Two of these deliberately had no artwork,
 * for two reasons that have both since stopped being reasons: Podcasts
 * would have needed a network call to Spotify at render time, which the
 * RSS rebuild removed by putting real artwork in the store, and the blog
 * had "no single newest post this page has any business picking", which
 * the Latest feed directly below now picks anyway. A card with no image
 * beside three that have one reads as broken rather than as restraint,
 * so the restraint is gone.
 *
 * Photos is not one of the cards, per Jamil, 2026-08-30: it is a
 * runner-facing page, the same intent as Results, not audience-facing
 * media. It keeps its own top-level nav item. See includes/media-subnav.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The newest broadcast's thumbnail, or '' if Watch has nothing to show.
 *
 * Live first, same rule the Watch index itself uses: "on air right now" is
 * the most honest answer to "what does Aravaipa's media look like today"
 * that this card can give.
 *
 * @return string
 */
function arv_media_hub_watch_thumb() {
	if ( ! function_exists( 'arv_watch_events' ) ) {
		return '';
	}

	$events = arv_watch_events();

	if ( empty( $events ) ) {
		return '';
	}

	foreach ( $events as $event ) {
		if ( ! empty( $event['live'] ) && ! empty( $event['streams'] ) ) {
			return end( $event['streams'] )['thumbnail'];
		}
	}

	return $events[0]['streams'][0]['thumbnail'];
}

/**
 * The newest film's thumbnail, or '' if Films has nothing to show.
 *
 * @return string
 */
function arv_media_hub_films_thumb() {
	if ( ! function_exists( 'arv_films_fetch' ) || ! function_exists( 'arv_films_all' ) ) {
		return '';
	}

	$all = arv_films_all( arv_films_fetch() );

	return ! empty( $all ) ? $all[0]['thumbnail'] : '';
}

/**
 * The newest podcast episode's artwork, or '' if none of the shows have
 * anything to show.
 *
 * Episode artwork where the episode has its own, the show's where it does
 * not, which arv_podcasts_all() already resolves. No network call beyond
 * the one the Podcasts store already makes and caches for an hour.
 *
 * @return string
 */
function arv_media_hub_podcasts_thumb() {
	if ( ! function_exists( 'arv_podcasts_fetch' ) || ! function_exists( 'arv_podcasts_all' ) ) {
		return '';
	}

	$all = arv_podcasts_all( arv_podcasts_fetch() );

	return ! empty( $all ) ? $all[0]['artwork'] : '';
}

/**
 * The newest blog post's featured image, or '' if none of the recent
 * posts have one.
 *
 * Reuses the Latest feed's own post reader rather than running a second
 * query: it already asks for exactly this, already drops posts with no
 * featured image, and is already cached for the hour.
 *
 * @return string
 */
function arv_media_hub_articles_thumb() {
	if ( ! function_exists( 'arv_media_latest_from_posts' ) ) {
		return '';
	}

	$posts = arv_media_latest_from_posts();

	return ! empty( $posts ) ? $posts[0]['thumb'] : '';
}

/**
 * The four cards, in a fixed order: what is live and changing first
 * (Broadcasts, Films), then what is steadier (Podcasts, Articles).
 *
 * @return array<int, array>
 */
function arv_media_hub_cards() {
	return array(
		array(
			'title' => __( 'Broadcasts', 'aravaipa-elements' ),
			'desc'  => __( 'Every Aravaipa Running broadcast, live and on demand.', 'aravaipa-elements' ),
			'url'   => home_url( '/watch/' ),
			'thumb' => arv_media_hub_watch_thumb(),
		),
		array(
			'title' => __( 'Films', 'aravaipa-elements' ),
			'desc'  => __( 'Documentaries and original films.', 'aravaipa-elements' ),
			'url'   => home_url( '/films/' ),
			'thumb' => arv_media_hub_films_thumb(),
		),
		array(
			'title' => __( 'Podcasts', 'aravaipa-elements' ),
			'desc'  => __( 'Inside Aravaipa, White Mountain Endurance and Race Briefings.', 'aravaipa-elements' ),
			'url'   => home_url( '/podcasts/' ),
			'thumb' => arv_media_hub_podcasts_thumb(),
		),
		array(
			'title' => __( 'Articles', 'aravaipa-elements' ),
			'desc'  => __( 'News, race updates and announcements.', 'aravaipa-elements' ),
			'url'   => home_url( '/blog/' ),
			'thumb' => arv_media_hub_articles_thumb(),
		),
	);
}

/**
 * The Media hub: a card per section, done rather than fetched.
 *
 * @param array $args heading, intro.
 * @return string
 */
function arv_media_hub_render( $args = array() ) {
	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Media';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';

	$out = '<section class="arv-media-hub">';
	$out .= '<div class="arv-media-hub__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-media-hub__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-media-hub__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-media-hub__grid">';

	foreach ( arv_media_hub_cards() as $card ) {
		$out .= '<a class="arv-media-hub__card' . ( '' === $card['thumb'] ? ' arv-media-hub__card--plain' : '' ) . '"'
			. ' href="' . esc_url( $card['url'] ) . '">';

		if ( '' !== $card['thumb'] ) {
			$out .= '<img class="arv-media-hub__thumb" src="' . esc_url( $card['thumb'] ) . '" alt=""'
				. ' loading="lazy" decoding="async" width="480" height="360" />';
		}

		$out .= '<span class="arv-media-hub__body">';
		$out .= '<span class="arv-media-hub__title">' . esc_html( $card['title'] ) . '</span>';
		$out .= '<span class="arv-media-hub__desc">' . esc_html( $card['desc'] ) . '</span>';
		$out .= '</span></a>';
	}

	return $out . '</div></div></section>';
}

/**
 * [arv_media_hub] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_media_hub_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Media',
			'intro'   => '',
		),
		$atts,
		'arv_media_hub'
	);

	return arv_media_hub_render( $atts );
}
add_shortcode( 'arv_media_hub', 'arv_media_hub_shortcode' );
