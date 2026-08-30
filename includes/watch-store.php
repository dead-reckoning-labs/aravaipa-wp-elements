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
				'desc'      => isset( $stream['description'] ) ? (string) $stream['description'] : '',
				// When it actually aired, which is what schema.org's
				// uploadDate means. Null on 25 of 219 rows upstream; the
				// schema builder falls back to the event date rather than
				// omit a field Google requires.
				'aired'     => isset( $stream['actualStart'] ) ? (string) $stream['actualStart'] : '',
				'minutes'   => isset( $stream['durationMinutes'] ) ? (int) $stream['durationMinutes'] : 0,
				'views'     => isset( $stream['views'] ) ? (int) $stream['views'] : 0,
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
			'place'   => isset( $event['location'] ) ? (string) $event['location'] : '',
			'desc'    => isset( $event['description'] ) ? (string) $event['description'] : '',
			'hero'    => isset( $event['heroImage'] ) ? (string) $event['heroImage'] : '',
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

	// Only on the full archive, same gate as the search box: the moment
	// someone has just scrolled a whole shelf of broadcasts is the moment
	// to ask, not a three-card homepage embed where it would be furniture.
	if ( 0 === $limit && function_exists( 'arv_media_follow_render' ) ) {
		$out .= arv_media_follow_render( 'youtube', __( 'broadcast', 'aravaipa-elements' ) );
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
 * Where a card, or one of its segments, should send a click.
 *
 * The race's own page where one exists, carrying the edition so a 2022
 * thumbnail lands on 2022 rather than on whatever is newest, and the video
 * so a named segment opens on that segment. Falls back to the YouTube URL
 * for a race with no page yet, which is the only case where leaving the
 * site is the honest answer.
 *
 * @param string     $base    Page URL, or the YouTube URL to fall back to.
 * @param bool       $on_site Whether $base is a page on this site.
 * @param string     $year
 * @param array|null $stream  Null for the card itself rather than a segment.
 * @return string
 */
function arv_watch_segment_url( $base, $on_site, $year, $stream = null ) {
	if ( ! $on_site ) {
		return ( null === $stream ) ? $base : $stream['url'];
	}

	$args = array();

	if ( '' !== $year ) {
		$args['edition'] = $year;
	}

	if ( null !== $stream ) {
		$args['v'] = $stream['id'];
	}

	return empty( $args ) ? $base : add_query_arg( $args, $base );
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
	$page     = arv_watch_page_map();
	$key      = arv_watch_race_key( $event['name'] );
	$page_hit = isset( $page[ $key ] );
	$href     = $page_hit ? $page[ $key ] : $lead['url'];

	// The card itself opens the race page on the right edition, not just the
	// race: a 2022 thumbnail that lands on 2026 is a card that lied.
	$card = arv_watch_segment_url( $href, $page_hit, '' !== $event['start'] ? substr( $event['start'], 0, 4 ) : '', null );

	$out = '<li class="arv-watch__race" data-arv-watch-name="' . esc_attr( strtolower( $event['name'] ) ) . '"'
		. ' data-arv-watch-year="' . esc_attr( '' !== $event['start'] ? substr( $event['start'], 0, 4 ) : '' ) . '">';
	$out .= '<a class="arv-watch__link" href="' . esc_url( $card ) . '"'
		. ( $page_hit ? '' : ' target="_blank" rel="noopener"' ) . '>';

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

		$year = '' !== $event['start'] ? substr( $event['start'], 0, 4 ) : '';

		foreach ( $event['streams'] as $stream ) {
			// Straight to that segment on our own page, playing here, rather
			// than out to YouTube. Before this every one of the 227 segment
			// links on the index left the site, which is the whole thing the
			// dedicated pages were built to stop.
			$to = arv_watch_segment_url( $href, $page_hit, $year, $stream );

			$out .= '<li><a class="arv-watch__segment" href="' . esc_url( $to ) . '"'
				. ( $page_hit ? '' : ' target="_blank" rel="noopener"' ) . '>'
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
 * The race a broadcast belongs to, independent of which year it ran.
 *
 * This shipped keying on the slug with its trailing year stripped, on the
 * stated assumption that Mountain Outpost's slugs do not drift between
 * seasons the way the race calendar's names do. That assumption was wrong,
 * and checking it would have taken one query:
 *
 *   black-canyon-2026, black-canyon-2025, black-canyon-ultras-2024
 *   jackpot-2025,      jackpot-ultras-2026
 *   javelina-2025,     javelina-jundred-2024
 *
 * So three of the five races split in half. /watch/black-canyon/ offered
 * 2026 and 2025 and silently hid the 2024, 2023 and 2022 broadcasts;
 * /watch/jackpot/ and /watch/javelina/ showed one year each and no switcher
 * at all, while every one of those editions sat on the index one click away.
 *
 * Keyed on the name now, which is consistent where the slug is not:
 * "Javelina Jundred" every year, "Black Canyon Ultras" every year. Run
 * through arv_results_race_key(), the normaliser that already exists to
 * solve exactly this for the results archive, so "Black Canyon" and "Black
 * Canyon Ultras" collapse together here for the same reason and by the same
 * rule they do there.
 *
 * Takes a name or a page's stored key, so both sides of the page lookup
 * normalise through one function and cannot disagree.
 *
 * @param string $value Event name, or a page's stored race key.
 * @return string
 */
function arv_watch_race_key( $value ) {
	$value = trim( (string) $value );

	// Both shapes of trailing year: "Javelina Jundred 2025" from a name and
	// "javelina-jundred-2025" from a slug or a stored key.
	$value = (string) preg_replace( '/[\s-](?:19|20)\d{2}$/', '', $value );

	if ( ! function_exists( 'arv_results_race_key' ) ) {
		return strtolower( str_replace( '-', ' ', $value ) );
	}

	return arv_results_race_key( str_replace( '-', ' ', $value ) );
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
 * The feed key a page's stored key means.
 *
 * A page carries whatever was typed when it was created, which is a slug and
 * not necessarily the exact normalised form the feed's names produce. The
 * Javelina page was created as "javelina" while every edition of the race is
 * named "Javelina Jundred", so its key normalises to "javelina" and the
 * feed's to "javelina jundred", and the page found none of its own five
 * broadcasts.
 *
 * Rather than require whoever creates a page to know the normaliser's exact
 * output, a stored key that is the opening words of exactly one race in the
 * feed resolves to that race. Exactly one: if two races both began with it
 * the answer would be a guess, and showing the wrong race's broadcasts is
 * worse than showing none.
 *
 * @param string $key Already normalised through arv_watch_race_key().
 * @return string The feed's own key, or $key unchanged.
 */
function arv_watch_resolve_key( $key ) {
	$key = trim( (string) $key );

	if ( '' === $key ) {
		return '';
	}

	$matches = array();

	foreach ( arv_watch_events() as $event ) {
		$theirs = arv_watch_race_key( $event['name'] );

		// An exact match settles it; nothing below can beat it.
		if ( $theirs === $key ) {
			return $key;
		}

		if ( 0 === strpos( $theirs, $key . ' ' ) ) {
			$matches[ $theirs ] = true;
		}
	}

	return ( 1 === count( $matches ) ) ? (string) key( $matches ) : $key;
}

/**
 * Every edition of one race, newest first.
 *
 * Grouped by arv_watch_race_key() over the same feed arv_watch_events()
 * already fetched and cached, so this adds no request of its own. Grouped on
 * the name rather than the slug because the slugs drift between seasons; see
 * arv_watch_race_key(). Sorted
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
		if ( arv_watch_race_key( $event['name'] ) === $key ) {
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
		// Normalised on the way in, so a page whose meta is written as a
		// slug ("black-canyon") is found by the key the feed's name produces
		// ("black canyon"). Both sides through one function, or the index
		// links nowhere and does it silently.
		$key = arv_watch_resolve_key( arv_watch_race_key( (string) get_post_meta( $id, arv_watch_meta_key(), true ) ) );

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
function arv_watch_race_player( $edition, $want = '' ) {
	$streams = $edition['streams'];
	$active  = null;

	// A ?v= from a segment link on the index, so "Day 2 | Mingus to Jerome"
	// opens on Day 2. Matched against this edition's own segments rather
	// than trusted, so a stale or invented id falls through to the normal
	// choice instead of embedding whatever was in the URL.
	if ( '' !== $want ) {
		foreach ( $streams as $candidate ) {
			if ( $candidate['id'] === $want ) {
				$active = $candidate;
				break;
			}
		}
	}

	if ( null === $active ) {
		foreach ( $streams as $candidate ) {
			if ( ! empty( $candidate['live'] ) ) {
				$active = $candidate;
				break;
			}
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

	if ( '' === $key ) {
		return '';
	}

	$fallback = '';

	foreach ( arv_race_store_get() as $race ) {
		if ( '' === $race['page'] ) {
			continue;
		}

		$theirs = arv_results_race_key( $race['name'] );

		if ( $theirs === $key ) {
			return $race['page'];
		}

		// The calendar sometimes carries a longer formal name than the
		// broadcast does: Mountain Outpost says "Desert Solstice" and the
		// race store says "Desert Solstice Track Invitational", which are
		// one race and do not match on equality, so Desert Solstice was the
		// one page of five with no registration link on it.
		//
		// Anchored at the start and cut on a word boundary, so this can only
		// ever match a race whose name this one is the opening words of. It
		// cannot pair "Black Canyon" with "Canyon de Chelly", and it cannot
		// fire on a partial word, which is what would let "Rock Hawk" claim
		// a hypothetical "Rock Hawkins". Held as a fallback rather than
		// returned immediately so an exact match anywhere in the store still
		// wins over a prefix match found earlier in the loop.
		if ( '' === $fallback && 0 === strpos( $theirs, $key . ' ' ) ) {
			$fallback = $race['page'];
		}
	}

	return $fallback;
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
	// Normalised for the same reason arv_watch_page_map() normalises: this
	// arrives as whatever was typed into the builder or stored on the page,
	// usually a slug, and it has to match what the feed's names produce.
	$key = isset( $args['race'] ) ? arv_watch_race_key( $args['race'] ) : '';

	if ( '' === $key ) {
		return '';
	}

	$editions = arv_watch_race_editions( arv_watch_resolve_key( $key ) );

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

	$want = isset( $_GET['v'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', wp_unslash( $_GET['v'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$out .= arv_watch_race_years( $editions, $edition['start'] );
	$out .= arv_watch_race_player( $edition, $want );

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

/**
 * -----------------------------------------------------------------------
 * SEO. Everything below answers one question: is this request a Watch race
 * page, and if so what should be in its head.
 *
 * These pages carried nothing. Title was WordPress's bare "Cocodona 250 |
 * Aravaipa Running", there was no meta description at all, og:description
 * was Jetpack's "Visit the post for more." and og:image the site logo, and
 * there was no structured data of any kind. Which means 219 broadcasts
 * totalling four and a third million views were, to a crawler, not videos.
 * Google will not put a video in video results, or report it in Search
 * Console's video indexing report, without a VideoObject carrying at least
 * name, description, thumbnailUrl and uploadDate.
 *
 * Worth being honest about the ceiling: these videos are hosted on YouTube,
 * and Google will generally treat the YouTube watch page as canonical for
 * the video itself. This markup does not win that fight. What it does is
 * make these pages eligible for video-rich results on race-intent queries,
 * put them in the video indexing report so the traffic is measurable at
 * all, and fix the link previews.
 *
 * Same posture as the rest of includes/seo.php: every printer checks
 * arv_seo_handled_elsewhere() first, so a real SEO plugin's output wins
 * rather than competing with a second copy from here.
 * -----------------------------------------------------------------------
 */

/**
 * Everything the head needs about the Watch race page being requested, or
 * null when this request is not one.
 *
 * Built once and shared by the title, the description, the Open Graph tags
 * and the schema, so all four agree about which edition is on screen rather
 * than each resolving ?edition= separately and risking a title that says
 * 2024 above a description that says 2026.
 *
 * @return array|null
 */
function arv_watch_seo_context() {
	static $ctx = null;
	static $done = false;

	if ( $done ) {
		return $ctx;
	}

	$done = true;

	if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
		return null;
	}

	$id = get_queried_object_id();

	if ( ! $id ) {
		return null;
	}

	$stored = trim( (string) get_post_meta( $id, arv_watch_meta_key(), true ) );

	if ( '' === $stored ) {
		return null;
	}

	$editions = arv_watch_race_editions( arv_watch_resolve_key( arv_watch_race_key( $stored ) ) );

	if ( empty( $editions ) ) {
		return null;
	}

	$requested = isset( $_GET['edition'] ) ? wp_unslash( $_GET['edition'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edition   = arv_watch_pick_edition( $editions, $requested );

	return $ctx = array(
		'edition'  => $edition,
		'editions' => $editions,
		'name'     => arv_watch_race_name( $edition['name'] ),
		'year'     => '' !== $edition['start'] ? substr( $edition['start'], 0, 4 ) : '',
		'url'      => get_permalink( $id ),
	);
}

/**
 * "Cocodona 250 2025 Live Broadcast", which is what someone actually types.
 *
 * @param array $ctx
 * @return string
 */
function arv_watch_seo_title( $ctx ) {
	$name = '' !== $ctx['year'] ? $ctx['name'] . ' ' . $ctx['year'] : $ctx['name'];

	return $name . ' ' . __( 'Live Broadcast', 'aravaipa-elements' );
}

/**
 * A description built from what this edition actually is.
 *
 * Mountain Outpost's own event description where there is one, since a
 * human wrote it. Otherwise assembled from the facts, which beats a
 * templated sentence repeated across ten pages.
 *
 * @param array $ctx
 * @return string
 */
function arv_watch_seo_description( $ctx ) {
	$edition = $ctx['edition'];
	$count   = count( $edition['streams'] );

	// isset() throughout this function for the same reason
	// arv_watch_seo_videos() reads its stream fields defensively: $edition
	// comes from the same fifteen-minute transient, so the first request
	// after a deploy that adds a field can hand this a value last cleaned by
	// the version before it, missing the key entirely rather than holding
	// ''.
	$desc  = isset( $edition['desc'] ) ? trim( (string) $edition['desc'] ) : '';
	$place = isset( $edition['place'] ) ? trim( (string) $edition['place'] ) : '';

	if ( '' !== $desc ) {
		// Google truncates around 160 characters, and a description cut
		// mid-word reads as broken; cut on a word boundary instead.
		$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );

		if ( strlen( $desc ) > 160 ) {
			$desc = rtrim( substr( $desc, 0, strrpos( substr( $desc, 0, 158 ), ' ' ) ), " ,.;:" ) . '…';
		}

		return $desc;
	}

	$bits = array();

	if ( '' !== $edition['start'] ) {
		$stamp = strtotime( $edition['start'] );

		if ( $stamp ) {
			$bits[] = gmdate( 'F j, Y', $stamp );
		}
	}

	if ( '' !== $place ) {
		$bits[] = $place;
	}

	return sprintf(
		/* translators: 1: race name and year, 2: video count, 3: date and place. */
		__( 'Watch the full %1$s broadcast from Aravaipa Running: %2$s of live race coverage%3$s.', 'aravaipa-elements' ),
		'' !== $ctx['year'] ? $ctx['name'] . ' ' . $ctx['year'] : $ctx['name'],
		sprintf(
			/* translators: %s is a count of videos. */
			_n( '%s video', '%s videos', $count, 'aravaipa-elements' ),
			number_format_i18n( $count )
		),
		$bits ? ', ' . implode( ', ', $bits ) : ''
	);
}

/**
 * One VideoObject per segment, wrapped in an ItemList.
 *
 * Every required field is present on every node or the node is dropped:
 * a VideoObject missing uploadDate is not a partial win, it is an invalid
 * one, and Google reports the whole page as an error rather than indexing
 * the rest. uploadDate falls back to the event's own start date for the
 * twenty-five segments upstream has no actualStart for.
 *
 * @param array $ctx
 * @return array
 */
function arv_watch_seo_videos( $ctx ) {
	$edition = $ctx['edition'];
	$items   = array();
	$n       = 0;

	foreach ( $edition['streams'] as $stream ) {
		// Read defensively rather than assumed present. arv_watch_events()
		// is cached for fifteen minutes, so a stream in $edition can be a
		// value this very request's arv_watch_clean() never produced: it is
		// last cleaned under whatever plugin version wrote the transient,
		// which on the first request after a deploy that adds a field is
		// the version before this one. A direct $stream['aired'] on a key
		// that version never set is not '', it is null, and null !== ''
		// is true, so the fallback to the event date never ran and every
		// segment silently dropped out of the markup entirely. Reproduced
		// on the live site the day this shipped.
		$title  = isset( $stream['title'] ) ? trim( (string) $stream['title'] ) : '';
		$desc   = isset( $stream['desc'] ) ? trim( (string) $stream['desc'] ) : '';
		$aired  = isset( $stream['aired'] ) && '' !== $stream['aired'] ? $stream['aired'] : $edition['start'];
		$stamp  = $aired ? strtotime( $aired ) : 0;
		$id     = isset( $stream['id'] ) ? $stream['id'] : '';
		$url    = isset( $stream['url'] ) ? $stream['url'] : '';
		$thumb  = isset( $stream['thumbnail'] ) ? $stream['thumbnail'] : '';
		$mins   = isset( $stream['minutes'] ) ? (int) $stream['minutes'] : 0;
		$views  = isset( $stream['views'] ) ? (int) $stream['views'] : 0;

		if ( '' === $title ) {
			$title = $edition['name'];
		}

		if ( '' === $title || ! $stamp ) {
			continue;
		}

		$video = array(
			'@type'        => 'VideoObject',
			'name'         => $title,
			// Its own description where upstream has one, the title
			// otherwise: an empty string here fails validation, and the
			// title is at least true.
			'description'  => '' !== $desc ? $desc : $title,
			'thumbnailUrl' => $thumb,
			'uploadDate'   => gmdate( 'c', $stamp ),
			'embedUrl'     => 'https://www.youtube-nocookie.com/embed/' . $id,
			'contentUrl'   => $url,
			'url'          => arv_watch_segment_url( $ctx['url'], true, $ctx['year'], $stream ),
		);

		// Only where upstream actually has one. Duration is on 63 of 219
		// rows, and an invented PT0M is worse than an absent field.
		if ( $mins > 0 ) {
			$video['duration'] = 'PT' . $mins . 'M';
		}

		if ( $views > 0 ) {
			$video['interactionStatistic'] = array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => array( '@type' => 'WatchAction' ),
				'userInteractionCount' => $views,
			);
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => ++$n,
			'item'     => $video,
		);
	}

	if ( empty( $items ) ) {
		return array();
	}

	return array(
		'@type'           => 'ItemList',
		'name'            => arv_watch_seo_title( $ctx ),
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	);
}

/**
 * Home > Watch > this race, so a result shows a real path rather than a
 * bare URL.
 *
 * @param array $ctx
 * @return array
 */
function arv_watch_seo_breadcrumbs( $ctx ) {
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'aravaipa-elements' ),
				'item'     => home_url( '/' ),
			),
			array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => __( 'Watch', 'aravaipa-elements' ),
				'item'     => home_url( '/watch/' ),
			),
			array(
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $ctx['name'],
				'item'     => $ctx['url'],
			),
		),
	);
}
