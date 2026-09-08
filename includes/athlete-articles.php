<?php
/**
 * "In the News": our own blog posts about an athlete, linked from their
 * profile.
 *
 * Curated by hand into _arv_article_urls, not matched automatically. A
 * name match against post content sounds like the video and results
 * approach, but it fails differently here: a roster-announcement post
 * ("Meet the 2019 Aravaipa Racing Team!") names fifty athletes and matches
 * all fifty, and a real, unambiguous match is not automatically one an
 * athlete wants surfaced, the way a DQ writeup naming them is real and
 * exact and still the wrong thing to feature on their own page. Both are
 * judgment calls a machine match cannot make, so a human makes them once
 * and the field just holds the answer.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The articles block on an athlete profile.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_profile_articles_markup( $athlete ) {
	$raw = isset( $athlete['article_urls'] ) ? trim( (string) $athlete['article_urls'] ) : '';

	if ( '' === $raw ) {
		return '';
	}

	$urls = array_filter( array_map( 'trim', preg_split( '/\R/', $raw ) ) );

	if ( empty( $urls ) ) {
		return '';
	}

	$items = '';

	foreach ( $urls as $url ) {
		$article = arv_athlete_article_meta( $url );

		if ( null === $article ) {
			continue;
		}

		$items .= '<li class="arv-athlete__article">';
		$items .= '<a class="arv-athlete__article-link" href="' . esc_url( $article['url'] ) . '">'
			. esc_html( $article['title'] ) . '</a>';
		$items .= '<span class="arv-athlete__article-date">' . esc_html( $article['date'] ) . '</span>';
		$items .= '</li>';
	}

	if ( '' === $items ) {
		return '';
	}

	return '<div class="arv-athlete__articles"><h2>' . esc_html__( 'In the News', 'aravaipa-elements' ) . '</h2>'
		. '<ul class="arv-athlete__articles-list">' . $items . '</ul></div>';
}

/**
 * A stored article URL resolved to a title and a date.
 *
 * These are our own posts, so this is a local lookup rather than a network
 * call: no oEmbed, no cache needed for something a plain DB query already
 * answers in under a millisecond.
 *
 * @param string $url
 * @return array{title: string, date: string, url: string}|null Null if the
 *                                                                URL no
 *                                                                longer
 *                                                                resolves
 *                                                                to a post
 *                                                                (retitled,
 *                                                                unpublished,
 *                                                                deleted).
 */
function arv_athlete_article_meta( $url ) {
	$id = url_to_postid( $url );

	if ( ! $id || 'publish' !== get_post_status( $id ) ) {
		return null;
	}

	return array(
		'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
		'date'  => get_the_date( 'F Y', $id ),
		'url'   => get_permalink( $id ),
	);
}
