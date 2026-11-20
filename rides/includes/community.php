<?php
/**
 * Community block data: latest Aravaipa Rides YouTube videos from the public
 * channel RSS feed, cached for 12 hours, with a fallback list that ships in
 * rides/data/community.json so the block is never empty.
 */

if ( ! defined( 'RIDES_DIR' ) ) {
	exit;
}

define( 'RIDES_YT_CHANNEL', 'UCjGtoAWVpwUYPEHV5IrN8Yg' );
define( 'RIDES_YT_FEED', 'https://www.youtube.com/feeds/videos.xml?channel_id=UCjGtoAWVpwUYPEHV5IrN8Yg' );
define( 'RIDES_YT_TRANSIENT', 'rides_yt_feed_v1' );

/**
 * Parse a YouTube channel Atom feed into [{id, title, published}], newest first.
 * Pure function (no WordPress), so the tests can feed it fixtures.
 *
 * @param string $xml Feed body.
 * @return array
 */
function rides_parse_yt_feed( $xml ) {
	$out = array();
	if ( ! is_string( $xml ) || false === strpos( $xml, '<entry' ) ) {
		return $out;
	}
	if ( ! preg_match_all( '#<entry\b[^>]*>(.*?)</entry>#s', $xml, $m ) ) {
		return $out;
	}
	foreach ( $m[1] as $entry ) {
		if ( ! preg_match( '#<yt:videoId>\s*([\w-]{11})\s*</yt:videoId>#', $entry, $id ) ) {
			continue;
		}
		$title = preg_match( '#<title>(.*?)</title>#s', $entry, $t ) ? html_entity_decode( trim( $t[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) : '';
		$pub   = preg_match( '#<published>\s*(\d{4}-\d\d-\d\d)#', $entry, $p ) ? $p[1] : '';
		$out[] = array(
			'id'        => $id[1],
			'title'     => '' !== $title ? $title : 'Aravaipa Rides video',
			'published' => $pub,
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return strcmp( $b['published'], $a['published'] );
		}
	);
	return $out;
}

/**
 * Fallback videos captured at build time.
 *
 * @return array
 */
function rides_fallback_videos() {
	$c = rides_data( 'community' );
	$v = array();
	foreach ( (array) ( $c['videos'] ?? array() ) as $row ) {
		if ( ! empty( $row['id'] ) && preg_match( '/^[\w-]{11}$/', $row['id'] ) ) {
			$v[] = array(
				'id'        => $row['id'],
				'title'     => (string) ( $row['title'] ?? 'Aravaipa Rides video' ),
				'published' => (string) ( $row['published'] ?? '' ),
			);
		}
	}
	return $v;
}

/**
 * Latest $n channel videos. On WordPress: the feed, cached in a 12 h transient
 * (a failed fetch caches the fallback for 1 h so a YouTube outage never slows pages).
 * Without WordPress (harness) or when the feed is empty: the fallback list.
 *
 * @param int $n How many.
 * @return array
 */
function rides_latest_videos( $n = 3 ) {
	$fallback = rides_fallback_videos();
	if ( ! function_exists( 'get_transient' ) || ! function_exists( 'wp_remote_get' ) ) {
		return array_slice( $fallback, 0, $n );
	}
	$cached = get_transient( RIDES_YT_TRANSIENT );
	if ( is_array( $cached ) && count( $cached ) >= $n ) {
		return array_slice( $cached, 0, $n );
	}
	$videos = array();
	$res    = wp_remote_get( RIDES_YT_FEED, array( 'timeout' => 6 ) );
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$videos = rides_parse_yt_feed( (string) wp_remote_retrieve_body( $res ) );
	}
	if ( count( $videos ) >= $n ) {
		set_transient( RIDES_YT_TRANSIENT, $videos, 12 * HOUR_IN_SECONDS );
		return array_slice( $videos, 0, $n );
	}
	set_transient( RIDES_YT_TRANSIENT, $fallback, HOUR_IN_SECONDS );
	return array_slice( $fallback, 0, $n );
}
