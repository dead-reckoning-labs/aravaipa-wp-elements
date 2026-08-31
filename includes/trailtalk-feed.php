<?php
/**
 * Aravaipa Trail Talk: a real podcast feed for a show that never had one.
 *
 * 44 episodes ran March 2016 to October 2017, self-hosted as WordPress
 * posts with an <audio> tag rather than through a podcast host. That is
 * why nothing here shows up on Spotify or Apple Podcasts: neither reads a
 * blog, and no RSS feed announcing these as podcast episodes has ever
 * existed. The audio itself is fine, confirmed against the media library
 * (audio/mpeg attachments, real files on disk) rather than assumed; only
 * the delivery format was ever missing.
 *
 * This builds the feed those files were always one step short of having.
 * Once published, the same GET /wp-json/aravaipa/v1/trail-talk/feed URL
 * is what gets submitted to Spotify for Creators and Apple Podcasts
 * Connect, the same one-time submission any new show goes through.
 *
 * A closed archive, not a live show: the category has not gained a post
 * since 2018. Read from the category live rather than hand-copied into a
 * config, since the data already exists correctly in the posts table and
 * copying it would just be a second place for it to drift from the first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_TRAILTALK_CATEGORY', 'Aravaipa Trail Talk' );
define( 'ARV_TRAILTALK_CACHE', 'arv_trailtalk_feed_items' );

/**
 * The mp3 URL inside a post's audio player, or ''.
 *
 * Matches the <source src="..."> shape and the bare <audio src="..."> one
 * these posts use interchangeably across 2016 and 2017's varying editor
 * behaviour, then falls back to the first .mp3 link in the body for the
 * handful that used neither.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function arv_trailtalk_audio_url( $content ) {
	if ( preg_match( '/<source[^>]+src=["\']([^"\']+\.mp3)["\']/i', (string) $content, $m ) ) {
		return $m[1];
	}

	if ( preg_match( '/<audio[^>]+src=["\']([^"\']+\.mp3)["\']/i', (string) $content, $m ) ) {
		return $m[1];
	}

	if ( preg_match( '/https?:\/\/[^\s"\'<>]+\.mp3/i', (string) $content, $m ) ) {
		return $m[0];
	}

	return '';
}

/**
 * The episode number in a title, or 0.
 *
 * Titles carry it inconsistently ("Episode #044", "Trail Talk 39",
 * "Episode 1") and the archive needs a stable sort that survives all three
 * spellings; publish date alone is not always in step with the number
 * printed on the episode ("Trail-Talk-10-Audio2.mp3" exists alongside a
 * plain "Trail-Talk-10.mp3", one clearly a re-upload).
 *
 * @param string $title
 * @return int
 */
function arv_trailtalk_number( $title ) {
	if ( preg_match( '/(?:episode\s*#?|trail\s*talk\s*[-–—]?\s*)0*(\d+)/i', (string) $title, $m ) ) {
		return (int) $m[1];
	}

	return 0;
}

/**
 * A byte length for the enclosure, read off the local file rather than
 * fetched over HTTP.
 *
 * These are self-hosted attachments, so the file sits on the same
 * filesystem this code runs on: filesize() on the real path is instant
 * and exact. Fetching the URL instead would mean this plugin making 39
 * outbound requests to its own web server on every feed build, for a
 * number the disk already knows.
 *
 * @param string $url
 * @return int
 */
function arv_trailtalk_filesize( $url ) {
	if ( '' === $url || ! function_exists( 'attachment_url_to_postid' ) ) {
		return 0;
	}

	$id = attachment_url_to_postid( $url );

	if ( ! $id || ! function_exists( 'get_attached_file' ) ) {
		return 0;
	}

	$path = get_attached_file( $id );

	return ( $path && file_exists( $path ) ) ? (int) filesize( $path ) : 0;
}

/**
 * Every Trail Talk episode, oldest first, the order a podcast feed reads
 * naturally in most apps.
 *
 * Cached for the hour, like the Latest feed's own post reader: this is a
 * closed archive, not something a listener needs to see change inside a
 * request's lifetime, and building it walks the media library once per
 * episode.
 *
 * @return array<int, array>
 */
function arv_trailtalk_items() {
	$cached = get_transient( ARV_TRAILTALK_CACHE );

	if ( false !== $cached ) {
		return ( 'none' === $cached ) ? array() : $cached;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'category_name'  => ARV_TRAILTALK_CATEGORY,
			'no_found_rows'  => true,
		)
	);

	$items = array();

	foreach ( $posts as $post ) {
		$content = apply_filters( 'the_content', $post->post_content );
		$audio   = arv_trailtalk_audio_url( $content );

		if ( '' === $audio ) {
			continue;
		}

		$title = get_the_title( $post );

		$items[] = array(
			'title'    => $title,
			'number'   => arv_trailtalk_number( $title ),
			'url'      => $audio,
			'bytes'    => arv_trailtalk_filesize( $audio ),
			'date'     => get_the_date( 'c', $post ),
			'link'     => get_permalink( $post ),
			'guid'     => 'arv-trailtalk-' . $post->ID,
			'summary'  => wp_strip_all_tags( (string) get_the_excerpt( $post ) ),
		);
	}

	usort(
		$items,
		function ( $a, $b ) {
			// The printed number first, since it is more consistent than
			// publish date across this particular archive's re-uploads.
			// Two items with no number recognised fall back to date, and
			// items with a number always sort ahead of items without one.
			if ( $a['number'] && $b['number'] ) {
				return $a['number'] - $b['number'];
			}

			if ( $a['number'] xor $b['number'] ) {
				return $a['number'] ? -1 : 1;
			}

			return strcmp( (string) $a['date'], (string) $b['date'] );
		}
	);

	set_transient( ARV_TRAILTALK_CACHE, empty( $items ) ? 'none' : $items, HOUR_IN_SECONDS );

	return $items;
}

/**
 * Drop the cache when a Trail Talk post is edited. The archive has not
 * gained a new post since 2018, but a typo fix should not wait an hour to
 * reach a feed a podcast app may be polling.
 *
 * @param int $post_id
 */
function arv_trailtalk_flush_on_save( $post_id ) {
	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( has_category( ARV_TRAILTALK_CATEGORY, $post_id ) ) {
		delete_transient( ARV_TRAILTALK_CACHE );
	}
}
add_action( 'save_post', 'arv_trailtalk_flush_on_save' );

/**
 * One <item>.
 *
 * @param array $item
 * @return string
 */
function arv_trailtalk_feed_item( $item ) {
	$out  = '<item>';
	$out .= '<title>' . esc_html( $item['title'] ) . '</title>';
	$out .= '<link>' . esc_url( $item['link'] ) . '</link>';
	$out .= '<guid isPermaLink="false">' . esc_html( $item['guid'] ) . '</guid>';
	$out .= '<pubDate>' . esc_html( gmdate( 'r', strtotime( $item['date'] ) ) ) . '</pubDate>';

	if ( '' !== $item['summary'] ) {
		$out .= '<description>' . esc_html( $item['summary'] ) . '</description>';
	}

	if ( $item['number'] ) {
		$out .= '<itunes:episode>' . (int) $item['number'] . '</itunes:episode>';
	}

	$out .= '<enclosure url="' . esc_url( $item['url'] ) . '" length="' . (int) $item['bytes'] . '" type="audio/mpeg" />';

	return $out . '</item>';
}

/**
 * The full feed.
 *
 * @return string
 */
function arv_trailtalk_feed_xml() {
	$items = arv_trailtalk_items();

	$out  = '<?xml version="1.0" encoding="UTF-8"?>';
	$out .= '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">';
	$out .= '<channel>';
	$out .= '<title>' . esc_html__( 'Aravaipa Trail Talk', 'aravaipa-elements' ) . '</title>';
	$out .= '<link>' . esc_url( home_url( '/' ) ) . '</link>';
	$out .= '<language>en-us</language>';
	$out .= '<itunes:author>' . esc_html__( 'Aravaipa Running', 'aravaipa-elements' ) . '</itunes:author>';
	$out .= '<itunes:explicit>false</itunes:explicit>';
	// A closed archive is exactly what the podcast:complete tag means: a
	// listener's app should stop checking for a new episode that is never
	// coming, rather than polling a feed frozen since 2017 forever.
	$out .= '<itunes:complete>Yes</itunes:complete>';
	$out .= '<description>' . esc_html__( 'The original Aravaipa Running podcast, 2016 to 2017: race previews, interviews and Q&A, archived here in full.', 'aravaipa-elements' ) . '</description>';

	foreach ( $items as $item ) {
		$out .= arv_trailtalk_feed_item( $item );
	}

	return $out . '</channel></rss>';
}

/**
 * GET /wp-json/aravaipa/v1/trail-talk/feed
 *
 * The URL submitted to Spotify for Creators and Apple Podcasts Connect,
 * the same one-time step any new show's feed goes through.
 */
function arv_trailtalk_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/trail-talk/feed',
		array(
			'methods'             => 'GET',
			'callback'            => 'arv_trailtalk_rest_feed',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'arv_trailtalk_register_rest_route' );

/**
 * @return WP_REST_Response
 */
function arv_trailtalk_rest_feed() {
	$response = new WP_REST_Response( arv_trailtalk_feed_xml() );
	$response->header( 'Content-Type', 'application/rss+xml; charset=UTF-8' );

	return $response;
}
