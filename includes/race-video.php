<?php
/**
 * One video, embedded on a race page.
 *
 * Race pages already carry videos: the Jigger Johnson page has two, both
 * raw <iframe> markup pasted into a Cornerstone text block. That works
 * exactly once. It carries no title, no credit, no schema, a fixed 560x315
 * that does not fit the column it sits in, and it hardcodes youtube.com
 * rather than the nocookie host every other embed in this plugin uses.
 * Doing it again on the next race page means pasting the same block and
 * editing the ID by hand.
 *
 * This is the same embed the Watch and Films pages render, given a URL
 * instead of a Mountain Outpost record: those read a system we own, and a
 * race page needs to point at whatever exists, including films made by
 * people who do not work here. The Jigger Johnson 100 film this was built
 * for is Ultra Kraut Running's, not Aravaipa's, which is why crediting the
 * channel is part of the markup rather than an option on it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The video ID from a URL, or from an ID passed straight through.
 *
 * arv_watch_youtube_id() handles the URL forms. A bare ID is accepted here
 * and not there because there the input is always a Mountain Outpost URL,
 * whereas here it is whatever an editor pasted into a builder field, and
 * "the eleven characters from the end of the link" is a thing people paste.
 *
 * @param string $value
 * @return string
 */
function arv_race_video_id( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	$id = arv_watch_youtube_id( $value );

	if ( '' !== $id ) {
		return $id;
	}

	// Anchored, so this only matches a bare ID and never salvages eleven
	// stray characters out of a URL this plugin failed to parse: quietly
	// embedding the wrong video is worse than rendering nothing.
	if ( preg_match( '~^[A-Za-z0-9_-]{11}$~', $value ) ) {
		return $value;
	}

	return '';
}

/**
 * Title, channel and thumbnail for a video, from YouTube's oEmbed endpoint.
 *
 * Public and keyless, so this needs no credential and cannot leak one. The
 * response is cached for a day: the title of a published video effectively
 * never changes, and a race page must not make a blocking network call on
 * every uncached render.
 *
 * A failure caches too, briefly. Without that, a video that is private,
 * deleted, or simply a typo re-requests on every single page load forever,
 * which is how a dead embed turns into a slow page.
 *
 * @param string $id
 * @return array{title:string,author:string,author_url:string,thumb:string}
 */
function arv_race_video_meta( $id ) {
	$empty = array(
		'title'      => '',
		'author'     => '',
		'author_url' => '',
		'thumb'      => '',
	);

	if ( '' === $id ) {
		return $empty;
	}

	$key    = 'arv_race_video_' . $id;
	$cached = get_transient( $key );

	if ( 'none' === $cached ) {
		return $empty;
	}

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$res = wp_remote_get(
		'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $id ),
		array( 'timeout' => 5 )
	);

	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		set_transient( $key, 'none', 10 * MINUTE_IN_SECONDS );
		return $empty;
	}

	$body = json_decode( wp_remote_retrieve_body( $res ), true );

	if ( ! is_array( $body ) ) {
		set_transient( $key, 'none', 10 * MINUTE_IN_SECONDS );
		return $empty;
	}

	$meta = array(
		'title'      => isset( $body['title'] ) ? (string) $body['title'] : '',
		'author'     => isset( $body['author_name'] ) ? (string) $body['author_name'] : '',
		'author_url' => isset( $body['author_url'] ) ? (string) $body['author_url'] : '',
		'thumb'      => isset( $body['thumbnail_url'] ) ? (string) $body['thumbnail_url'] : '',
	);

	set_transient( $key, $meta, DAY_IN_SECONDS );

	return $meta;
}

/**
 * The embed.
 *
 * @param array $atts url, title, credit, credit_url, caption, date, heading.
 * @return string
 */
function arv_race_video_render( $atts ) {
	$id = arv_race_video_id( isset( $atts['url'] ) ? $atts['url'] : '' );

	if ( '' === $id ) {
		return '';
	}

	// Only asked for when something is missing. An editor who filled in the
	// title and the credit has told us both, and there is no reason to make
	// a network call to be told them again.
	$title  = isset( $atts['title'] ) ? trim( (string) $atts['title'] ) : '';
	$credit = isset( $atts['credit'] ) ? trim( (string) $atts['credit'] ) : '';
	$chan   = isset( $atts['credit_url'] ) ? trim( (string) $atts['credit_url'] ) : '';

	if ( '' === $title || '' === $credit ) {
		$meta = arv_race_video_meta( $id );

		if ( '' === $title ) {
			$title = $meta['title'];
		}

		if ( '' === $credit ) {
			$credit = $meta['author'];
		}

		if ( '' === $chan ) {
			$chan = $meta['author_url'];
		}
	}

	$heading = isset( $atts['heading'] ) ? trim( (string) $atts['heading'] ) : '';
	$caption = isset( $atts['caption'] ) ? trim( (string) $atts['caption'] ) : '';

	$out = '<section class="arv-race-video">';

	if ( '' !== $heading ) {
		$out .= '<h3 class="arv-race-video__head">' . esc_html( $heading ) . '</h3>';
	}

	// The iframe carries no width or height: the wrapper holds the 16:9 and
	// the frame fills it, so the space is reserved before the embed loads
	// and nothing below it moves when it does.
	$out .= '<div class="arv-race-video__frame">'
		. '<iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $id ) . '"'
		. ' title="' . esc_attr( '' !== $title ? $title : __( 'Race video', 'aravaipa-elements' ) ) . '"'
		. ' loading="lazy" allowfullscreen'
		. ' allow="accelerometer; encrypted-media; picture-in-picture"'
		. ' referrerpolicy="strict-origin-when-cross-origin"></iframe>'
		. '</div>';

	if ( '' !== $title || '' !== $credit || '' !== $caption ) {
		$out .= '<div class="arv-race-video__meta">';

		if ( '' !== $title ) {
			$out .= '<p class="arv-race-video__title">' . esc_html( $title ) . '</p>';
		}

		if ( '' !== $credit ) {
			$who = esc_html( $credit );

			if ( '' !== $chan ) {
				$who = '<a href="' . esc_url( $chan ) . '" target="_blank" rel="noopener">' . $who . '</a>';
			}

			$out .= '<p class="arv-race-video__credit">'
				/* translators: %s: the YouTube channel that made the video. */
				. sprintf( esc_html__( 'A film by %s', 'aravaipa-elements' ), $who )
				. '</p>';
		}

		if ( '' !== $caption ) {
			$out .= '<p class="arv-race-video__caption">' . esc_html( $caption ) . '</p>';
		}

		$out .= '</div>';
	}

	$out .= '</section>';

	return $out . arv_race_video_schema( $id, $title, $caption, $atts );
}

/**
 * VideoObject markup, when there is enough to make a valid one.
 *
 * Same rule the Watch and Films schema blocks follow: every required field
 * present or no node at all. uploadDate is the binding one, and oEmbed does
 * not return it, so a video with no date attribute gets a working embed and
 * no schema rather than an invalid node that makes Google report the whole
 * page as an error.
 *
 * @param string $id
 * @param string $title
 * @param string $caption
 * @param array  $atts
 * @return string
 */
function arv_race_video_schema( $id, $title, $caption, $atts ) {
	if ( function_exists( 'arv_seo_handled_elsewhere' ) && arv_seo_handled_elsewhere() ) {
		return '';
	}

	$node = arv_race_video_schema_node( $id, $title, $caption, $atts );

	if ( empty( $node ) ) {
		return '';
	}

	return '<script type="application/ld+json">'
		. wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. '</script>';
}

/**
 * The node itself, or an empty array where a valid one cannot be built.
 *
 * Split from the printer above so the "is every required field here"
 * decision can be tested on its own. The guard in the printer depends on
 * whether a real SEO plugin is active, which in a single test run is a
 * constant that cannot be unset once another test has defined it.
 *
 * @param string $id
 * @param string $title
 * @param string $caption
 * @param array  $atts
 * @return array
 */
function arv_race_video_schema_node( $id, $title, $caption, $atts ) {
	$date  = isset( $atts['date'] ) ? trim( (string) $atts['date'] ) : '';
	$stamp = '' !== $date ? strtotime( $date ) : 0;

	if ( '' === $id || '' === $title || ! $stamp ) {
		return array();
	}

	return array(
		'@context'     => 'https://schema.org',
		'@type'        => 'VideoObject',
		'name'         => $title,
		'description'  => '' !== $caption ? $caption : $title,
		'thumbnailUrl' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
		'uploadDate'   => gmdate( 'c', $stamp ),
		'embedUrl'     => 'https://www.youtube-nocookie.com/embed/' . $id,
		'contentUrl'   => 'https://www.youtube.com/watch?v=' . $id,
	);
}

/**
 * [arv_race_video url="..."] for a race page.
 *
 * @param array $atts
 * @return string
 */
function arv_race_video_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'url'        => '',
			'heading'    => '',
			'title'      => '',
			'credit'     => '',
			'credit_url' => '',
			'caption'    => '',
			'date'       => '',
		),
		$atts,
		'arv_race_video'
	);

	return arv_race_video_render( $atts );
}
add_shortcode( 'arv_race_video', 'arv_race_video_shortcode' );
