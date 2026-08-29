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
		// Only on the full archive, not the homepage block a $limit produces:
		// a search box over three cards is furniture, the same reason the
		// segment toggle does not appear over a single video.
		if ( 0 === $limit ) {
			$out .= arv_watch_controls( $past );
		}

		$out .= '<ul class="arv-watch__list" data-arv-watch-list>';

		foreach ( $past as $event ) {
			$out .= arv_watch_event( $event );
		}

		$out .= '</ul>';
	}

	return $out . '</div></section>';
}

/**
 * The search box and year filter above the archive.
 *
 * Progressive enhancement, same contract as the Results element's own
 * search bar: everything below this is already in the HTML, so this only
 * ever hides and shows what aravaipa-watch.js finds through the
 * data-arv-watch-* hooks it sets. A search engine, or a reader with
 * JavaScript off, sees the complete archive regardless of what either
 * control is set to.
 *
 * @param array $past Archive events, for the year options.
 * @return string
 */
function arv_watch_controls( $past ) {
	$years = array();

	foreach ( $past as $event ) {
		if ( '' !== $event['start'] ) {
			$years[ substr( $event['start'], 0, 4 ) ] = true;
		}
	}

	krsort( $years );
	$years = array_keys( $years );

	$out = '<div class="arv-watch__controls">';

	$out .= '<label class="arv-watch__search-label" for="arv-watch-q">'
		. esc_html__( 'Search races', 'aravaipa-elements' ) . '</label>';
	$out .= '<span class="arv-watch__search-field">';
	$out .= '<input class="arv-watch__search-input" id="arv-watch-q" type="search" autocomplete="off"'
		. ' placeholder="' . esc_attr__( 'Race name', 'aravaipa-elements' ) . '" data-arv-watch-search />';
	// Our own clear button rather than the one type="search" gives you, for
	// the same reason the Results search bar has its own: WebKit's is a
	// small unlabelled target and Firefox draws none at all.
	$out .= '<button class="arv-watch__search-clear" type="button" hidden data-arv-watch-clear>'
		. '<span class="arv-results__sr">' . esc_html__( 'Clear search', 'aravaipa-elements' ) . '</span>'
		. '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">'
		. '<path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
		. '</svg></button>';
	$out .= '</span>';

	// Only where there is more than one year to choose between: a filter
	// offering a single, already-showing year is a control with no effect.
	if ( count( $years ) > 1 ) {
		$out .= '<select class="arv-watch__year-select" data-arv-watch-year aria-label="'
			. esc_attr__( 'Filter by year', 'aravaipa-elements' ) . '">';
		$out .= '<option value="">' . esc_html__( 'Every year', 'aravaipa-elements' ) . '</option>';

		foreach ( $years as $year ) {
			$out .= '<option value="' . esc_attr( $year ) . '">' . esc_html( $year ) . '</option>';
		}

		$out .= '</select>';
	}

	$out .= '<p class="arv-watch__count" data-arv-watch-count aria-live="polite"></p>';

	return $out . '</div>';
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

	// A dedicated page once one exists for the race, which embeds every
	// edition rather than sending this click to YouTube. Falls back to the
	// lead segment's own URL for a race nobody has built a page for yet,
	// exactly like arv_live_page_for_live_url() falls back to the board.
	$page = arv_watch_page_map();
	$key  = arv_watch_race_key( $event['slug'] );
	$href = isset( $page[ $key ] ) ? $page[ $key ] : $lead['url'];

	$out = '<li class="arv-watch__race" data-arv-watch-name="' . esc_attr( strtolower( $event['name'] ) ) . '"'
		. ' data-arv-watch-year="' . esc_attr( '' !== $event['start'] ? substr( $event['start'], 0, 4 ) : '' ) . '">';
	$out .= '<a class="arv-watch__link" href="' . esc_url( $href ) . '"'
		. ( isset( $page[ $key ] ) ? '' : ' target="_blank" rel="noopener"' ) . '>';

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

/**
 * -----------------------------------------------------------------------
 * Dedicated pages: one race, every edition, embedded rather than linked.
 *
 * The index above exists to answer "what is there to watch"; these exist to
 * answer "show me the race" without a click that leaves aravaiparunning.com.
 * Everything below groups the same feed by race instead of by edition, and
 * renders it onto whatever page carries [arv_watch_race], the same relation
 * the live-results pages have to includes/live-page.php.
 * -----------------------------------------------------------------------
 */

/**
 * The race a broadcast slug belongs to, independent of which year it ran.
 *
 * Mountain Outpost's own slugs always end in the four-digit year:
 * "cocodona-250-2026", "black-canyon-2026". Stripping it is what lets every
 * edition of a race share one page. Not arv_results_race_key(): that
 * normaliser is tuned for the calendar's spelling drift between seasons,
 * which this feed does not have, and it would throw away the distance in
 * "cocodona-250" that this slug needs to stay unique from a plain
 * "cocodona" MO adds later.
 *
 * @param string $slug
 * @return string
 */
function arv_watch_race_key( $slug ) {
	return (string) preg_replace( '/-(?:19|20)\d{2}$/', '', trim( (string) $slug ) );
}

/**
 * A race's own name, with whatever year arv_watch_event_name() added, or
 * Mountain Outpost already had, taken back off.
 *
 * A dedicated page states the year once, in its own year switcher next to
 * the heading. Leaving it in the name too is how a heading ends up reading
 * "Cocodona 250 2025 2025".
 *
 * @param string $name
 * @return string
 */
function arv_watch_race_name( $name ) {
	return trim( (string) preg_replace( '/\s+(?:19|20)\d{2}$/', '', (string) $name ) );
}

/**
 * Every edition of one race, newest first.
 *
 * Grouped by arv_watch_race_key() over the same feed arv_watch_events()
 * already fetched and cached, so this adds no request of its own. Sorted
 * explicitly rather than trusted to arrive that way: the feed happens to be
 * newest-first today, and a bare assumption about upstream ordering is
 * exactly what put segments out of order the first time this plugin read
 * this API, before scheduledStart replaced sortOrder.
 *
 * @param string $key
 * @return array<int, array>
 */
function arv_watch_race_editions( $key ) {
	$key = trim( (string) $key );

	if ( '' === $key ) {
		return array();
	}

	$mine = array();

	foreach ( arv_watch_events() as $event ) {
		if ( arv_watch_race_key( $event['slug'] ) === $key ) {
			$mine[] = $event;
		}
	}

	usort(
		$mine,
		function ( $a, $b ) {
			$ta = $a['start'] ? (int) strtotime( $a['start'] ) : 0;
			$tb = $b['start'] ? (int) strtotime( $b['start'] ) : 0;
			return $tb <=> $ta;
		}
	);

	return $mine;
}

/**
 * The edition a request is asking for, or the newest.
 *
 * Same fallback the live-results pages use for the same reason: a stale
 * link to a year the race did not run should land on this year's broadcast,
 * not on an empty page.
 *
 * @param array  $editions
 * @param string $year
 * @return array|null
 */
function arv_watch_pick_edition( $editions, $year ) {
	$year = trim( (string) $year );

	if ( '' !== $year ) {
		foreach ( $editions as $edition ) {
			if ( '' !== $edition['start'] && $year === substr( $edition['start'], 0, 4 ) ) {
				return $edition;
			}
		}
	}

	return $editions ? $editions[0] : null;
}

/**
 * Every race that has a dedicated Watch page of its own, race key to URL.
 *
 * Same shape and reason as arv_live_page_map(): found by the meta a page
 * carries rather than by a slug convention, one query for the whole index
 * rather than one lookup per card.
 *
 * @param bool $fresh
 * @return array key => permalink
 */
function arv_watch_page_map( $fresh = false ) {
	static $map = null;

	if ( null !== $map && ! $fresh ) {
		return $map;
	}

	$map = array();

	if ( ! function_exists( 'get_posts' ) ) {
		return $map;
	}

	$ids = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 200,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_key'         => arv_watch_meta_key(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);

	foreach ( (array) $ids as $id ) {
		$key = trim( (string) get_post_meta( $id, arv_watch_meta_key(), true ) );

		if ( '' !== $key ) {
			$map[ $key ] = get_permalink( $id );
		}
	}

	return $map;
}

/**
 * The meta key a Watch race page is found by.
 *
 * @return string
 */
function arv_watch_meta_key() {
	return '_arv_watch_race';
}

/**
 * Register the meta so it is settable through the REST API: create the
 * page, pass meta._arv_watch_race in the same request. See
 * arv_live_register_meta(), which this is the same fix for: a leading
 * underscore makes WordPress treat a meta key as protected, and
 * register_post_meta's default REST permission check refuses to write a
 * protected key without an explicit auth_callback regardless of
 * show_in_rest.
 */
function arv_watch_register_meta() {
	register_post_meta(
		'page',
		arv_watch_meta_key(),
		array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'arv_watch_register_meta' );

/**
 * The player and, where there is more than one segment, the playlist below
 * it that swaps into it.
 *
 * Always embeds, unlike the index: a dedicated page's entire reason to
 * exist is not sending this click to YouTube. Each segment is still a real
 * link to its YouTube URL first, target="_blank" and all, so the page is
 * fully usable with no script; aravaipa-watch.js progressively enhances a
 * click into an in-place swap instead of a new tab.
 *
 * @param array $edition
 * @return string
 */
function arv_watch_race_player( $edition ) {
	$streams = $edition['streams'];
	$active  = null;

	foreach ( $streams as $candidate ) {
		if ( ! empty( $candidate['live'] ) ) {
			$active = $candidate;
			break;
		}
	}

	// On demand, open on the first segment by scheduled start, the same
	// choice arv_watch_event() makes and for the same reason: the last one
	// is the finish on a stage race, and opening there spoils the coverage
	// above it.
	if ( null === $active ) {
		$active = $streams[0];
	}

	$out = '<div class="arv-watch-race__frame">'
		. '<iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $active['id'] ) . '"'
		. ' title="' . esc_attr( $active['title'] ) . '"'
		. ' loading="lazy" allowfullscreen'
		. ' allow="accelerometer; encrypted-media; picture-in-picture"'
		. ' referrerpolicy="strict-origin-when-cross-origin"></iframe>'
		. '</div>';

	if ( count( $streams ) < 2 ) {
		return $out;
	}

	$out .= '<ol class="arv-watch-race__playlist">';

	foreach ( $streams as $stream ) {
		$is_active = ( $stream['id'] === $active['id'] );

		$out .= '<li><a class="arv-watch-race__seg' . ( $is_active ? ' is-active' : '' ) . '"'
			. ' href="' . esc_url( $stream['url'] ) . '"'
			. ' data-yt-id="' . esc_attr( $stream['id'] ) . '"'
			. ' data-yt-title="' . esc_attr( $stream['title'] ) . '"'
			. ( $is_active ? ' aria-current="true"' : '' )
			. ' target="_blank" rel="noopener">'
			. '<img class="arv-watch-race__seg-thumb" src="' . esc_url( $stream['thumbnail'] ) . '" alt=""'
			. ' loading="lazy" decoding="async" width="120" height="90" />'
			. '<span class="arv-watch-race__seg-title">'
			. esc_html( '' !== $stream['title'] ? $stream['title'] : $edition['name'] )
			. '</span></a></li>';
	}

	return $out . '</ol>';
}

/**
 * The other years of this race, as ordinary links.
 *
 * @param array  $editions
 * @param string $current_start ISO start of the edition on screen.
 * @return string
 */
function arv_watch_race_years( $editions, $current_start ) {
	if ( count( $editions ) < 2 ) {
		return '';
	}

	$current = '' !== $current_start ? substr( $current_start, 0, 4 ) : '';

	$out = '<nav class="arv-watch-race__years" aria-label="' . esc_attr__( 'Other years', 'aravaipa-elements' ) . '">';

	foreach ( $editions as $edition ) {
		$year = '' !== $edition['start'] ? substr( $edition['start'], 0, 4 ) : '';

		if ( '' === $year ) {
			continue;
		}

		if ( $year === $current ) {
			$out .= '<span class="arv-watch-race__year-link is-current" aria-current="page">'
				. esc_html( $year ) . '</span>';
			continue;
		}

		$out .= '<a class="arv-watch-race__year-link" href="'
			. esc_url( add_query_arg( 'edition', $year, arv_live_self_url() ) ) . '">'
			. esc_html( $year ) . '</a>';
	}

	return $out . '</nav>';
}

/**
 * A link to the race's own page, for whoever came here to watch and wants
 * to sign up.
 *
 * Matched with arv_results_race_key() against the race store, which is the
 * calendar's own normaliser for the same spelling drift ("Black Canyon" the
 * feed calls it, "Black Canyon Ultras" the calendar does). Nothing renders
 * for a race the store has never heard of, which is every race MO
 * broadcasts that Aravaipa does not currently run: sending a viewer to a
 * 404 is worse than sending them nowhere.
 *
 * @param string $name
 * @return string
 */
function arv_watch_race_page_link( $name ) {
	if ( ! function_exists( 'arv_results_race_key' ) || ! function_exists( 'arv_race_store_get' ) ) {
		return '';
	}

	$key = arv_results_race_key( $name );

	foreach ( arv_race_store_get() as $race ) {
		if ( arv_results_race_key( $race['name'] ) === $key && '' !== $race['page'] ) {
			return $race['page'];
		}
	}

	return '';
}

/**
 * A link to that edition's own results, if this site has them.
 *
 * Matched to the exact year on screen rather than to the race's newest
 * results, because a viewer of the 2019 broadcast asking "what happened"
 * means the 2019 results, and handing them the current year's instead would
 * read as this page having gotten it wrong. Nothing renders where that
 * year's board URL is not on file, which is most of the archive: the results
 * store only goes back a few seasons.
 *
 * @param string $name
 * @param string $year
 * @return string
 */
function arv_watch_race_results_link( $name, $year ) {
	if ( '' === $year || ! function_exists( 'arv_live_editions_by_name' ) ) {
		return '';
	}

	foreach ( arv_live_editions_by_name( $name ) as $row ) {
		if ( empty( $row['iso'] ) || $year !== substr( $row['iso'], 0, 4 ) || empty( $row['live'] ) ) {
			continue;
		}

		$page = function_exists( 'arv_live_page_for_live_url' ) ? arv_live_page_for_live_url( $row['live'] ) : '';

		return '' !== $page ? $page : $row['live'];
	}

	return '';
}

/**
 * The dedicated page for one race: every edition, embedded, with a way to
 * its results and its registration page.
 *
 * @param array $args race.
 * @return string
 */
function arv_watch_race_render( $args = array() ) {
	$key = isset( $args['race'] ) ? trim( (string) $args['race'] ) : '';

	if ( '' === $key ) {
		return '';
	}

	$editions = arv_watch_race_editions( $key );

	if ( empty( $editions ) ) {
		return arv_watch_race_empty();
	}

	// The reader's own request first, same precedence arv_live_page_render()
	// uses and for the same reason: $args['edition'] exists for a caller
	// that already knows which edition it wants, such as the test suite.
	$requested = isset( $_GET['edition'] ) && '' !== $_GET['edition'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? wp_unslash( $_GET['edition'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		: ( isset( $args['edition'] ) ? $args['edition'] : '' );
	$edition   = arv_watch_pick_edition( $editions, $requested );

	$name = arv_watch_race_name( $edition['name'] );
	$year = '' !== $edition['start'] ? substr( $edition['start'], 0, 4 ) : '';

	$out = '<section class="arv-watch-race">';
	$out .= '<div class="arv-watch-race__inner">';

	$out .= '<p class="arv-watch-race__back"><a href="' . esc_url( home_url( '/watch/' ) ) . '">'
		. '&larr; ' . esc_html__( 'All broadcasts', 'aravaipa-elements' ) . '</a></p>';

	$out .= '<div class="arv-watch-race__head">';

	if ( ! empty( $edition['live'] ) ) {
		$out .= arv_results_week_live_badge( array( 'state' => 'live' ) );
	}

	$out .= '<h1 class="arv-watch-race__name">'
		. esc_html( '' !== $year ? $name . ' ' . $year : $name ) . '</h1>';
	$out .= '</div>';

	$out .= arv_watch_race_years( $editions, $edition['start'] );
	$out .= arv_watch_race_player( $edition );

	$links = array();

	$race_page = arv_watch_race_page_link( $name );
	if ( '' !== $race_page ) {
		$links[] = '<a class="arv-watch-race__cta" href="' . esc_url( $race_page ) . '">'
			. esc_html__( 'Race info & registration', 'aravaipa-elements' ) . '</a>';
	}

	$results_page = arv_watch_race_results_link( $name, $year );
	if ( '' !== $results_page ) {
		$links[] = '<a class="arv-watch-race__cta" href="' . esc_url( $results_page ) . '">'
			. esc_html__( 'Results', 'aravaipa-elements' ) . '</a>';
	}

	if ( ! empty( $links ) ) {
		$out .= '<p class="arv-watch-race__links">' . implode( ' ', $links ) . '</p>';
	}

	return $out . '</div></section>';
}

/**
 * What a dedicated page shows for a race key nothing currently airs under.
 *
 * Reachable the moment a page is created ahead of its first broadcast, or
 * once MO retires a slug this page was still pointed at. A blank page under
 * this site's own header reads as broken in a way the index's silent
 * nothing does not, because this page has no other content to fall back to.
 *
 * @return string
 */
function arv_watch_race_empty() {
	return '<section class="arv-watch-race"><div class="arv-watch-race__inner">'
		. '<p class="arv-watch-race__missing">'
		. esc_html__( "We don't have a broadcast for that race right now.", 'aravaipa-elements' )
		. ' <a href="' . esc_url( home_url( '/watch/' ) ) . '">'
		. esc_html__( 'See everything we have on Watch.', 'aravaipa-elements' ) . '</a></p>'
		. '</div></section>';
}

/**
 * [arv_watch_race race="cocodona-250"] for one race's dedicated page.
 *
 * @param array $atts
 * @return string
 */
function arv_watch_race_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'race' => '' ), $atts, 'arv_watch_race' );

	return arv_watch_race_render( $atts );
}
add_shortcode( 'arv_watch_race', 'arv_watch_race_shortcode' );
