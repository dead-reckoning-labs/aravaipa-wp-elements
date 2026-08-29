<?php
/**
 * Aravaipa's broadcasts, read from Mountain Outpost.
 *
 * Same shape as every other store here: the data lives somewhere else and
 * this is the read of it. Unlike the race and results stores, though,
 * nothing writes to this one. There is no scraper and no import endpoint,
 * because Mountain Outpost is a system we own with a real database and a
 * public endpoint, so copying its rows into WordPress would be inventing a
 * sync problem rather than solving one. The trade is that a page render
 * depends on a network call, which is what the transient below is for.
 *
 * The endpoint is Aravaipa-only at the source, not filtered here. Mountain
 * Outpost broadcasts races Aravaipa does not own (Western States, Hardrock,
 * Run Rabbit Run and others), and deciding which of those belong on
 * aravaiparunning.com is Mountain Outpost's call to make, since it is the
 * one holding the is_aravaipa_event flag its ops team maintains. See
 * apps/web/src/app/api/broadcast/streams/route.ts in that repo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Overridable so a staging site can point at a preview deployment without a
// code change, the same way the live board's base URL is a constant here.
if ( ! defined( 'ARV_WATCH_API' ) ) {
	define( 'ARV_WATCH_API', 'https://mountainoutpost.com/api/broadcast/streams' );
}

/**
 * Every Aravaipa broadcast, newest first, live ones first of all.
 *
 * Cached for fifteen minutes. Long enough that a busy page is not making an
 * outbound request per visitor, short enough that a broadcast going live
 * shows up without anyone clearing anything. A failure is cached too, for
 * one minute rather than fifteen: caching nothing would retry on every
 * single page view for as long as an outage lasts, and caching it for the
 * full window would hide a recovery for that long.
 *
 * @param bool $fresh Skip the cache. For tests and for a caller that has
 *                    just changed something and wants to see it.
 * @return array Events, each with a nested list of streams.
 */
function arv_watch_events( $fresh = false ) {
	$key = 'arv_watch_events';

	if ( ! $fresh ) {
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			// A cached failure is the string 'none', since the transient API
			// cannot tell a stored empty array from nothing stored at all,
			// and "the API answered with no broadcasts" and "the API did not
			// answer" want different retry windows.
			return ( 'none' === $cached ) ? array() : $cached;
		}
	}

	$response = wp_remote_get(
		ARV_WATCH_API,
		array(
			'headers' => array( 'User-Agent' => 'aravaipa-elements-watch' ),
			'timeout' => 5,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		set_transient( $key, 'none', MINUTE_IN_SECONDS );
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) || ! isset( $body['events'] ) || ! is_array( $body['events'] ) ) {
		set_transient( $key, 'none', MINUTE_IN_SECONDS );
		return array();
	}

	$events = arv_watch_clean( $body['events'] );

	set_transient( $key, $events, 15 * MINUTE_IN_SECONDS );

	return $events;
}

/**
 * Normalise the API's events into the shape this plugin renders.
 *
 * Everything is treated as untrusted and coerced, the same way the race and
 * results importers treat a pasted row. This one happens to come from a
 * system we own, which is exactly the assumption worth not baking in: a
 * schema change on the other side should render a quiet gap here, never a
 * fatal on aravaiparunning.com.
 *
 * A stream with no YouTube URL is dropped rather than rendered as a card
 * that goes nowhere: the whole point of the entry is somewhere to watch.
 * An event left with no watchable streams goes with them.
 *
 * @param array $raw
 * @return array
 */
function arv_watch_clean( $raw ) {
	$events = array();

	foreach ( $raw as $event ) {
		if ( ! is_array( $event ) || empty( $event['slug'] ) || empty( $event['name'] ) ) {
			continue;
		}

		$streams = array();

		foreach ( ( isset( $event['streams'] ) && is_array( $event['streams'] ) ? $event['streams'] : array() ) as $stream ) {
			if ( ! is_array( $stream ) || empty( $stream['youtubeUrl'] ) ) {
				continue;
			}

			$id = arv_watch_youtube_id( $stream['youtubeUrl'] );

			if ( '' === $id ) {
				continue;
			}

			$streams[] = array(
				'id'        => $id,
				'title'     => isset( $stream['title'] ) ? (string) $stream['title'] : '',
				'url'       => (string) $stream['youtubeUrl'],
				// Built from the id rather than using the stored URL, to pick
				// the size. The thumbnails on file are all maxresdefault at
				// 1280x720 and about 220KB each; an archive page showing
				// eighteen Cocodona segments would be four megabytes of
				// artwork for a grid of 480px cards. hqdefault is the same
				// image at 23KB. Checked both resolve rather than assumed:
				// the stored maxresdefault_live.jpg URLs are still good, they
				// are just ten times heavier than this page needs.
				'thumbnail' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
				'live'      => ! empty( $stream['live'] ),
				'type'      => isset( $stream['streamType'] ) ? (string) $stream['streamType'] : '',
				'start'     => isset( $stream['scheduledStart'] ) ? (string) $stream['scheduledStart'] : '',
			);
		}

		if ( empty( $streams ) ) {
			continue;
		}

		$start = isset( $event['startDate'] ) ? (string) $event['startDate'] : '';

		$events[] = array(
			'slug'    => (string) $event['slug'],
			'name'    => arv_watch_event_name( (string) $event['name'], $start ),
			'live'    => ! empty( $event['live'] ),
			'start'   => $start,
			'streams' => $streams,
		);
	}

	return $events;
}

/**
 * An event name a reader can tell apart from the eight below it.
 *
 * Mountain Outpost stores a name per edition, and most of them already carry
 * their year: "Cocodona 250 2025", "Javelina Jundred 2025". Desert Solstice
 * does not, and it has been broadcast every December since 2018, so the
 * archive renders as nine cards all reading "Desert Solstice" with nothing
 * but a date underneath to separate them. The year is appended here rather
 * than fixed in the other system's rows, because which of those names is
 * "wrong" is a judgement call for whoever owns that data, and a page that
 * reads correctly should not wait on it.
 *
 * Left alone where a year is already in the name, so nothing turns into
 * "Cocodona 250 2025 2025".
 *
 * @param string $name
 * @param string $start ISO date, possibly empty.
 * @return string
 */
function arv_watch_event_name( $name, $start ) {
	$name = trim( $name );

	if ( '' === $start || preg_match( '/\b(?:19|20)\d{2}\b/', $name ) ) {
		return $name;
	}

	$stamp = strtotime( $start );

	return $stamp ? $name . ' ' . gmdate( 'Y', $stamp ) : $name;
}

/**
 * The video id out of a YouTube URL.
 *
 * Handles the three shapes the stored URLs actually take rather than every
 * shape YouTube accepts: watch?v=, youtu.be/, and /live/. Anything else
 * returns '' and its stream is dropped, which is the honest outcome for a
 * URL this cannot embed.
 *
 * @param string $url
 * @return string
 */
function arv_watch_youtube_id( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	if ( preg_match( '~[?&]v=([A-Za-z0-9_-]{11})~', $url, $m ) ) {
		return $m[1];
	}

	if ( preg_match( '~youtu\.be/([A-Za-z0-9_-]{11})~', $url, $m ) ) {
		return $m[1];
	}

	if ( preg_match( '~/live/([A-Za-z0-9_-]{11})~', $url, $m ) ) {
		return $m[1];
	}

	return '';
}

/**
 * The Watch page: a live broadcast if there is one, then the archive.
 *
 * Renders nothing at all when the feed is empty or unreachable, rather than
 * a heading over a blank space. A Watch page that says "Watch" and then
 * shows nothing reads as broken; no section at least reads as a page that
 * has not been built yet, which during an outage is the less wrong of the
 * two.
 *
 * @param array $args heading, intro, limit.
 * @return string
 */
function arv_watch_render( $args = array() ) {
	$events = arv_watch_events();

	if ( empty( $events ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Watch';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';
	$limit   = isset( $args['limit'] ) ? (int) $args['limit'] : 0;

	// A live broadcast is never cut by the limit. The limit exists to keep a
	// homepage block short, and "we are on air right now" is the one thing
	// that block exists to say.
	$live = array();
	$past = array();

	foreach ( $events as $event ) {
		if ( ! empty( $event['live'] ) ) {
			$live[] = $event;
		} else {
			$past[] = $event;
		}
	}

	if ( $limit > 0 ) {
		$past = array_slice( $past, 0, max( 0, $limit - count( $live ) ) );
	}

	$out = '<section class="arv-watch">';
	$out .= '<div class="arv-watch__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-watch__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-watch__intro">' . esc_html( $intro ) . '</p>';
	}

	foreach ( $live as $event ) {
		$out .= arv_watch_live_block( $event );
	}

	if ( ! empty( $past ) ) {
		$out .= '<ul class="arv-watch__list">';

		foreach ( $past as $event ) {
			$out .= arv_watch_event( $event );
		}

		$out .= '</ul>';
	}

	return $out . '</div></section>';
}

/**
 * A broadcast that is on air, embedded rather than linked.
 *
 * The only place this element embeds a player. Everything else is a link
 * out to YouTube: eighteen iframes on an archive page would be eighteen
 * third-party players loading on a page nobody asked to autoplay.
 *
 * @param array $event
 * @return string
 */
function arv_watch_live_block( $event ) {
	// The live segment if the feed says which one, otherwise the last, which
	// on a feed ordered by scheduled start is the one currently on air: a
	// multi-segment broadcast runs its segments in order.
	$stream = null;

	foreach ( $event['streams'] as $candidate ) {
		if ( ! empty( $candidate['live'] ) ) {
			$stream = $candidate;
			break;
		}
	}

	if ( null === $stream ) {
		$stream = end( $event['streams'] );
	}

	$out = '<div class="arv-watch__live">';
	$out .= '<p class="arv-watch__live-label">'
		. arv_results_week_live_badge( array( 'state' => 'live' ) )
		. '<span class="arv-watch__live-name">' . esc_html( $event['name'] ) . '</span>'
		. '</p>';

	$out .= '<div class="arv-watch__frame">'
		. '<iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $stream['id'] ) . '"'
		. ' title="' . esc_attr( $stream['title'] ) . '"'
		. ' loading="lazy" allowfullscreen'
		. ' allow="accelerometer; encrypted-media; picture-in-picture"'
		. ' referrerpolicy="strict-origin-when-cross-origin"></iframe>'
		. '</div>';

	return $out . '</div>';
}

/**
 * One past broadcast: the event, with its segments behind a toggle.
 *
 * Same shape the results archive uses for a race's earlier editions, and
 * for the same reason. Cocodona is eighteen segments; listed flat, one race
 * would fill the page and bury every other broadcast under it.
 *
 * @param array $event
 * @return string
 */
function arv_watch_event( $event ) {
	$count = count( $event['streams'] );

	// The first segment by scheduled start, which is where someone watching a
	// race back wants to begin. Deliberately not the last one: on Cocodona
	// that is the finish, and opening on the finish is a spoiler for the
	// eighteen hours of coverage above it.
	$lead = $event['streams'][0];

	$out = '<li class="arv-watch__race">';
	$out .= '<a class="arv-watch__link" href="' . esc_url( $lead['url'] ) . '"'
		. ' target="_blank" rel="noopener">';

	$out .= '<img class="arv-watch__thumb" src="' . esc_url( $lead['thumbnail'] ) . '" alt=""'
		. ' loading="lazy" decoding="async" width="480" height="360" />';

	$out .= '<span class="arv-watch__body">';
	$out .= '<span class="arv-watch__name">' . esc_html( $event['name'] ) . '</span>';

	$bits = array();

	if ( '' !== $event['start'] ) {
		$stamp = strtotime( $event['start'] );

		if ( $stamp ) {
			$bits[] = gmdate( 'F j, Y', $stamp );
		}
	}

	$bits[] = sprintf(
		/* translators: %s is a count of video segments in one broadcast. */
		_n( '%s video', '%s videos', $count, 'aravaipa-elements' ),
		number_format_i18n( $count )
	);

	$out .= '<span class="arv-watch__meta">' . esc_html( implode( ' · ', $bits ) ) . '</span>';
	$out .= '</span></a>';

	// Only where there is more than one, since a toggle reading "1 video"
	// over the video already linked above it is furniture.
	if ( $count > 1 ) {
		$out .= '<details class="arv-watch__more">';
		$out .= '<summary class="arv-watch__more-toggle">'
			. esc_html(
				sprintf(
					/* translators: %s is a count of video segments, always two or more. */
					__( 'All %s segments', 'aravaipa-elements' ),
					number_format_i18n( $count )
				)
			)
			. '</summary>';

		$out .= '<ul class="arv-watch__segments">';

		foreach ( $event['streams'] as $stream ) {
			$out .= '<li><a class="arv-watch__segment" href="' . esc_url( $stream['url'] ) . '"'
				. ' target="_blank" rel="noopener">'
				. esc_html( '' !== $stream['title'] ? $stream['title'] : $event['name'] )
				. '</a></li>';
		}

		$out .= '</ul></details>';
	}

	return $out . '</li>';
}

/**
 * [arv_watch] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_watch_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Watch',
			'intro'   => '',
			'limit'   => 0,
		),
		$atts,
		'arv_watch'
	);

	return arv_watch_render( $atts );
}
add_shortcode( 'arv_watch', 'arv_watch_shortcode' );
