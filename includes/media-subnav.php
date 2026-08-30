<?php
/**
 * The strip at the top of every media page: Watch, Films, Podcasts, Photos,
 * Articles, with the one a visitor is already on marked.
 *
 * Films, Podcasts and Watch all sit under /media/ in the nav now (see the
 * breadcrumb parent fix that shipped alongside this), but that only helps
 * someone who opens the Media dropdown. Someone who lands on Films from a
 * Google search, which is most of them, sees no sign Podcasts exists.
 * That is the actual leak this closes: one strip, reused on five pages,
 * cheap because the list of sections is not going to change often enough
 * to need to live anywhere but here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The five sections, in the order they render.
 *
 * A key rather than just a URL, so a page can be marked current without
 * string-matching a path: arv_media_subnav_render() is told which key is
 * current by the element/page that places it, the same way a Watch race
 * page is told which race it is rather than working it out from the URL.
 *
 * @return array<int, array{key: string, label: string, url: string}>
 */
function arv_media_subnav_items() {
	return apply_filters(
		'arv_media_subnav_items',
		array(
			array( 'key' => 'watch', 'label' => __( 'Broadcasts', 'aravaipa-elements' ), 'url' => home_url( '/watch/' ) ),
			array( 'key' => 'films', 'label' => __( 'Films', 'aravaipa-elements' ), 'url' => home_url( '/films/' ) ),
			array( 'key' => 'podcasts', 'label' => __( 'Podcasts', 'aravaipa-elements' ), 'url' => home_url( '/podcasts/' ) ),
			array( 'key' => 'photos', 'label' => __( 'Photos', 'aravaipa-elements' ), 'url' => home_url( '/photos/' ) ),
			array( 'key' => 'articles', 'label' => __( 'Articles', 'aravaipa-elements' ), 'url' => home_url( '/blog/' ) ),
		)
	);
}

/**
 * @param string $current One of arv_media_subnav_items()'s keys, or '' to
 *                         mark nothing current (a section this strip does
 *                         not know about, e.g. a single Watch race page).
 * @return string
 */
function arv_media_subnav_render( $current = '' ) {
	$items = arv_media_subnav_items();

	if ( empty( $items ) ) {
		return '';
	}

	$out = '<nav class="arv-media-subnav" aria-label="' . esc_attr__( 'Media', 'aravaipa-elements' ) . '">';
	$out .= '<div class="arv-media-subnav__inner">';

	// The parent link. Jamil's own read on it: not "Media Hub", which is
	// this plugin's internal name for the element and not a runner's word
	// for anything, especially sitting directly under a breadcrumb that
	// already says MEDIA. One word, reused, is the point: the nav says
	// Media, the breadcrumb says Media, this says Media, and it is the one
	// link on a Films or Podcasts page that goes back to the section
	// itself, which nothing else here did before.
	$out .= '<a class="arv-media-subnav__parent" href="' . esc_url( home_url( '/media/' ) ) . '">'
		. esc_html__( 'Media', 'aravaipa-elements' ) . '</a>';

	foreach ( $items as $item ) {
		$is = ( $current === $item['key'] );
		$out .= '<a class="arv-media-subnav__link' . ( $is ? ' is-current' : '' ) . '"'
			. ' href="' . esc_url( $item['url'] ) . '"'
			. ( $is ? ' aria-current="page"' : '' ) . '>'
			. esc_html( $item['label'] ) . '</a>';
	}

	return $out . '</div></nav>';
}

/**
 * [arv_media_subnav current="films"] so a page can carry this without
 * Cornerstone, the same as every other element in this plugin.
 *
 * @param array $atts
 * @return string
 */
function arv_media_subnav_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'current' => '' ), $atts, 'arv_media_subnav' );

	return arv_media_subnav_render( (string) $atts['current'] );
}
add_shortcode( 'arv_media_subnav', 'arv_media_subnav_shortcode' );
