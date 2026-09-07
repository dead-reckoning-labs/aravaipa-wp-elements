<?php
/**
 * The Aravaipa Running YouTube channel, as a block at the foot of Media.
 *
 * Broadcasts and Films already carry the 53 videos worth a page of their
 * own on this site. The channel itself holds a thousand: course previews,
 * race recaps, interviews, shorts. Listing those here would be a worse
 * YouTube, so this does not try. It says what the channel is, how big it
 * is, and links out.
 *
 * The subscriber count is the reason this is a store and not a paragraph
 * of hard-coded copy. "Nearly 40,000 subscribers" written into a template
 * is true for about a month and then quietly is not, and nobody is coming
 * back to check. Fetched, it stays true on its own.
 *
 * An option refreshed wholesale by scripts/fetch-youtube.mjs, like the
 * films, races and stats stores, and for the same reason: the API key
 * stays on a laptop and out of WordPress, so a site that sells race
 * entries is not also the place a Google credential lives.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_YOUTUBE_OPTION', 'arv_youtube_channel' );

/**
 * Where the channel lives. The handle rather than the channel id: it is
 * the URL a person would recognise, and it is what the existing
 * "Subscribe on YouTube" link in media-follow.php already points at.
 */
define( 'ARV_YOUTUBE_URL', 'https://www.youtube.com/@aravaiparunning' );

/**
 * Everything stored about the channel.
 *
 * @return array
 */
function arv_youtube_store_get() {
	$stored = get_option( ARV_YOUTUBE_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * Replace the stored channel wholesale.
 *
 * @param array $channel
 * @return array The cleaned entry actually stored.
 */
function arv_youtube_store_set( $channel ) {
	$clean = arv_youtube_clean( $channel );

	update_option( ARV_YOUTUBE_OPTION, $clean, false );

	return $clean;
}

/**
 * One channel payload, cleaned.
 *
 * Counts floor at zero and a video needs both an id and a title to be a
 * video, the same rule the stats store applies to a winner needing both a
 * name and a time. A half-formed row is not shown rather than shown half.
 *
 * @param array $channel
 * @return array
 */
function arv_youtube_clean( $channel ) {
	if ( ! is_array( $channel ) ) {
		return array();
	}

	$videos = array();

	foreach ( (array) ( isset( $channel['videos'] ) ? $channel['videos'] : array() ) as $video ) {
		if ( ! is_array( $video ) || empty( $video['id'] ) || empty( $video['title'] ) ) {
			continue;
		}

		$videos[] = array(
			'id'    => sanitize_text_field( (string) $video['id'] ),
			'title' => sanitize_text_field( (string) $video['title'] ),
			'date'  => isset( $video['date'] ) ? sanitize_text_field( (string) $video['date'] ) : '',
			'thumb' => isset( $video['thumb'] ) ? esc_url_raw( (string) $video['thumb'] ) : '',
		);
	}

	return array(
		'title'       => isset( $channel['title'] ) ? sanitize_text_field( (string) $channel['title'] ) : '',
		'subscribers' => isset( $channel['subscribers'] ) ? max( 0, (int) $channel['subscribers'] ) : 0,
		'videoCount'  => isset( $channel['videoCount'] ) ? max( 0, (int) $channel['videoCount'] ) : 0,
		'views'       => isset( $channel['views'] ) ? max( 0, (int) $channel['views'] ) : 0,
		'videos'      => $videos,
	);
}

/**
 * A count as a channel page writes it: 39,500 becomes "39.5K".
 *
 * Rounded down rather than nearest, so this never claims a number the
 * channel has not reached. Below a thousand it is just the number: "900
 * subscribers" needs no help, and "0.9K" reads like a rounding error.
 *
 * @param int $n
 * @return string
 */
function arv_youtube_short_count( $n ) {
	$n = (int) $n;

	if ( $n >= 1000000 ) {
		return rtrim( rtrim( number_format( floor( $n / 100000 ) / 10, 1 ), '0' ), '.' ) . 'M';
	}

	if ( $n >= 1000 ) {
		return rtrim( rtrim( number_format( floor( $n / 100 ) / 10, 1 ), '0' ), '.' ) . 'K';
	}

	return number_format_i18n( $n );
}

/**
 * The channel block.
 *
 * Renders nothing at all when the store is empty. An empty shell with a
 * subscribe button and no numbers behind it is worse than the page simply
 * ending where it ended before, which is what it did until this existed.
 *
 * @return string
 */
function arv_youtube_render() {
	$channel = arv_youtube_store_get();

	if ( empty( $channel['subscribers'] ) && empty( $channel['videos'] ) ) {
		return '';
	}

	$out = '<section class="arv-youtube">';
	$out .= '<div class="arv-youtube__inner">';

	$out .= '<div class="arv-youtube__head">';
	$out .= '<h2 class="arv-youtube__heading">' . esc_html__( 'On YouTube', 'aravaipa-elements' ) . '</h2>';
	$out .= '<p class="arv-youtube__intro">'
		. esc_html__(
			'Course previews, race recaps and interviews, beyond the broadcasts and films above.',
			'aravaipa-elements'
		)
		. '</p>';

	// The two numbers worth saying. Views are deliberately not among them:
	// six million is a real figure and reads as a boast, where a
	// subscriber count reads as an invitation and a video count as a
	// promise about how much is back there.
	if ( ! empty( $channel['subscribers'] ) || ! empty( $channel['videoCount'] ) ) {
		$out .= '<p class="arv-youtube__stats">';

		if ( ! empty( $channel['subscribers'] ) ) {
			$out .= '<span class="arv-youtube__stat">' . esc_html(
				sprintf(
					/* translators: %s: a shortened subscriber count, e.g. "39.5K". */
					__( '%s subscribers', 'aravaipa-elements' ),
					arv_youtube_short_count( $channel['subscribers'] )
				)
			) . '</span>';
		}

		if ( ! empty( $channel['videoCount'] ) ) {
			$out .= '<span class="arv-youtube__stat">' . esc_html(
				sprintf(
					/* translators: %s: a formatted video count. */
					__( '%s videos', 'aravaipa-elements' ),
					number_format_i18n( (int) $channel['videoCount'] )
				)
			) . '</span>';
		}

		$out .= '</p>';
	}

	$out .= '</div>';

	if ( ! empty( $channel['videos'] ) ) {
		$out .= '<ul class="arv-youtube__grid">';

		foreach ( $channel['videos'] as $video ) {
			$out .= '<li class="arv-youtube__card">';
			$out .= '<a class="arv-youtube__link" href="'
				. esc_url( 'https://www.youtube.com/watch?v=' . $video['id'] )
				. '" target="_blank" rel="noopener">';

			if ( '' !== $video['thumb'] ) {
				$out .= '<img class="arv-youtube__thumb" src="' . esc_url( $video['thumb'] ) . '" alt=""'
					. ' loading="lazy" decoding="async" width="480" height="270" />';
			}

			$out .= '<span class="arv-youtube__title">' . esc_html( $video['title'] ) . '</span>';

			if ( '' !== $video['date'] ) {
				$stamp = strtotime( $video['date'] );

				if ( $stamp ) {
					$out .= '<span class="arv-youtube__date">'
						. esc_html( gmdate( 'F j, Y', $stamp ) ) . '</span>';
				}
			}

			$out .= '</a></li>';
		}

		$out .= '</ul>';
	}

	$out .= '<p class="arv-youtube__follow">';
	$out .= '<a class="arv-youtube__cta" href="'
		. esc_url( ARV_YOUTUBE_URL . '?sub_confirmation=1' )
		. '" target="_blank" rel="noopener">'
		. esc_html__( 'Subscribe on YouTube', 'aravaipa-elements' ) . '</a>';
	$out .= '</p>';

	return $out . '</div></section>';
}

/**
 * Write route for the fetcher, scoped like every other store's.
 */
function arv_youtube_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/youtube',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_youtube_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_rest_route(
		'aravaipa/v1',
		'/youtube',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				return new WP_REST_Response( array( 'channel' => arv_youtube_store_get() ), 200 );
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_youtube_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/youtube
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_youtube_rest_set( $request ) {
	$body    = $request->get_json_params();
	$channel = isset( $body['channel'] ) && is_array( $body['channel'] ) ? $body['channel'] : array();
	$dry     = ! empty( $body['dry_run'] );

	$clean = arv_youtube_clean( $channel );

	// A payload with no subscriber count and no videos is a failed fetch,
	// not a channel that emptied. Refused rather than stored, so one bad
	// run cannot blank the block on the live page.
	if ( empty( $clean['subscribers'] ) && empty( $clean['videos'] ) ) {
		return array(
			'status' => 'refused',
			'reason' => 'no subscriber count and no videos in the payload',
		);
	}

	if ( $dry ) {
		return array(
			'status' => 'dry_run',
			'subs'   => $clean['subscribers'],
			'videos' => count( $clean['videos'] ),
		);
	}

	$stored = arv_youtube_store_set( $clean );

	return array(
		'status' => 'ok',
		'subs'   => $stored['subscribers'],
		'videos' => count( $stored['videos'] ),
	);
}

/**
 * [arv_youtube] so a page can carry this without Cornerstone.
 *
 * @return string
 */
function arv_youtube_shortcode() {
	return arv_youtube_render();
}
add_shortcode( 'arv_youtube', 'arv_youtube_shortcode' );
