<?php
/**
 * Aravaipa's podcasts, read from their own RSS feeds.
 *
 * This shipped once already reading two Spotify embeds directly, which
 * carried exactly the two shows Jamil happened to send links for. Aravaipa
 * actually runs four: Inside Aravaipa, the White Mountain Endurance
 * Podcast, Aravaipa Rides, and Aravaipa Race Briefings, all discoverable
 * from the public Aravaipa Running channel on Apple Podcasts. Spotify's
 * embed also only ever offers Spotify, on a site whose listeners are not
 * all on Spotify.
 *
 * RSS is the fix for both: every show already publishes one (Anchor's, in
 * this case), it is the one place a show's episodes, artwork and
 * descriptions exist independent of any single platform, and it is what
 * Spotify and Apple themselves read to build their own listings. Reading it
 * here means this plugin owns the same facts they do, rather than
 * embedding a widget that only shows one platform's opinion of them.
 *
 * Same shape as Watch and Films: a feed this plugin does not own, read at
 * render time and cached, normalised defensively because the shape on the
 * other end can change without this plugin knowing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aravaipa's shows: label, RSS feed, and the platform ids for a subscribe
 * link on each.
 *
 * Filterable so a fifth show, a feed migration, or a rename needs no
 * plugin release. Keys are stable and used in URLs
 * (/podcasts/{key}/), so changing one moves a page.
 *
 * @return array<string, array>
 */
function arv_podcasts_shows_config() {
	return apply_filters(
		'arv_podcasts_shows_config',
		array(
			'inside-aravaipa'  => array(
				'title'   => 'Inside Aravaipa',
				'feed'    => 'https://anchor.fm/s/1017c24d0/podcast/rss',
				'spotify' => '0MvdUlDE9VwocRhrIl9Lwv',
				'apple'   => '1797659741',
			),
			'white-mountain'   => array(
				'title'   => 'White Mountain Endurance Podcast',
				'feed'    => 'https://anchor.fm/s/10172af54/podcast/rss',
				'spotify' => '4cg3hl6Ek6pjd76ymrbUQd',
				'apple'   => '1797459207',
			),
			'race-briefings'   => array(
				'title'   => 'Aravaipa Race Briefings',
				'feed'    => 'https://anchor.fm/s/10208075c/podcast/rss',
				'spotify' => '',
				'apple'   => '1800268722',
			),
			'aravaipa-rides'   => array(
				'title'   => 'Aravaipa Rides Podcast',
				'feed'    => 'https://anchor.fm/s/b78c48/podcast/rss',
				'spotify' => '',
				'apple'   => '1691934660',
			),
		)
	);
}

/**
 * Every show, fed and parsed, cached for an hour.
 *
 * One transient for all four feeds rather than one each: the index page
 * needs every show on one render, and four cached values expiring at four
 * different moments is four different chances for the merged "latest
 * episodes" list to show one show a request stale relative to the others.
 * One clock for the whole set means the merge is always internally
 * consistent even when it is not perfectly fresh.
 *
 * A show whose feed fails is dropped rather than failing the whole page:
 * three real shows are a better answer than none because Anchor was slow
 * for one of them.
 *
 * @param bool $fresh Skip the cache.
 * @return array<string, array> key => {title, artwork, desc, spotify,
 *                               apple, feed, episodes}
 */
function arv_podcasts_fetch( $fresh = false ) {
	$key = 'arv_podcasts';

	if ( ! $fresh ) {
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return ( 'none' === $cached ) ? array() : $cached;
		}
	}

	$shows = array();

	foreach ( arv_podcasts_shows_config() as $show_key => $config ) {
		if ( empty( $config['feed'] ) || empty( $config['title'] ) ) {
			continue;
		}

		$response = wp_remote_get(
			$config['feed'],
			array(
				'headers' => array( 'User-Agent' => 'aravaipa-elements-podcasts' ),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			continue;
		}

		$parsed = arv_podcasts_parse_feed( wp_remote_retrieve_body( $response ) );

		if ( null === $parsed ) {
			continue;
		}

		$shows[ $show_key ] = array_merge(
			$parsed,
			array(
				'key'     => $show_key,
				'title'   => $config['title'],
				'spotify' => isset( $config['spotify'] ) ? (string) $config['spotify'] : '',
				'apple'   => isset( $config['apple'] ) ? (string) $config['apple'] : '',
				'feed'    => $config['feed'],
			)
		);
	}

	set_transient( $key, empty( $shows ) ? 'none' : $shows, HOUR_IN_SECONDS );

	return $shows;
}

/**
 * Parse one show's RSS into artwork, description and episodes.
 *
 * SimpleXML with LIBXML_NOCDATA rather than a hand-rolled regex: this is
 * exactly the well-formed XML-with-a-namespace problem PHP's own XML
 * extension exists for, and RSS is not forgiving enough to be worth a
 * regex's usual excuse of "the real thing is too irregular to parse
 * properly". Wrapped in @ and a libxml error check rather than trusted,
 * because the input is a podcast host's XML, not this plugin's own.
 *
 * @param string $body Raw RSS XML.
 * @return array|null null when the body is not parseable RSS.
 */
function arv_podcasts_parse_feed( $body ) {
	$body = trim( (string) $body );

	if ( '' === $body ) {
		return null;
	}

	$previous = libxml_use_internal_errors( true );
	$xml      = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( false === $xml || ! isset( $xml->channel ) ) {
		return null;
	}

	$channel = $xml->channel;
	$itunes  = $channel->children( 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

	$artwork = isset( $itunes->image ) ? (string) $itunes->image->attributes()->href : '';
	$desc    = isset( $itunes->summary ) ? (string) $itunes->summary : (string) $channel->description;

	$episodes = array();

	foreach ( $channel->item as $item ) {
		$item_itunes = $item->children( 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

		$title = trim( (string) $item->title );
		// isset() first: an item with no <enclosure> at all is not just an
		// empty url, it is no element to call attributes() on, which is a
		// warning rather than a graceful '', on untrusted XML from a host
		// this plugin does not run.
		$audio = isset( $item->enclosure ) ? (string) $item->enclosure->attributes()->url : '';

		// No title, or nowhere to actually play it: not an episode this
		// page can do anything with.
		if ( '' === $title || '' === $audio ) {
			continue;
		}

		$stamp = strtotime( (string) $item->pubDate );

		$episodes[] = array(
			'guid'     => '' !== (string) $item->guid ? (string) $item->guid : $audio,
			'title'    => $title,
			'desc'     => isset( $item_itunes->summary ) ? (string) $item_itunes->summary : trim( (string) $item->description ),
			'audio'    => $audio,
			'link'     => (string) $item->link,
			'artwork'  => isset( $item_itunes->image ) ? (string) $item_itunes->image->attributes()->href : $artwork,
			'duration' => isset( $item_itunes->duration ) ? (string) $item_itunes->duration : '',
			'published' => $stamp ? gmdate( 'c', $stamp ) : '',
		);
	}

	if ( empty( $episodes ) ) {
		return null;
	}

	return array(
		'artwork'  => $artwork,
		'desc'     => trim( wp_strip_all_tags( (string) $desc ) ),
		'episodes' => $episodes,
	);
}

/**
 * A duration string in either shape iTunes feeds use ("01:57:25" or a bare
 * second count like "7045") as ISO 8601, which is what schema.org's
 * duration property requires.
 *
 * @param string $raw
 * @return string '' when $raw is not a recognisable duration.
 */
function arv_podcasts_iso_duration( $raw ) {
	$raw = trim( (string) $raw );

	if ( '' === $raw ) {
		return '';
	}

	if ( ctype_digit( $raw ) ) {
		$seconds = (int) $raw;
	} else {
		$parts = array_map( 'intval', explode( ':', $raw ) );

		if ( count( $parts ) === 2 ) {
			list( $m, $s ) = $parts;
			$seconds = ( $m * 60 ) + $s;
		} elseif ( count( $parts ) === 3 ) {
			list( $h, $m, $s ) = $parts;
			$seconds = ( $h * 3600 ) + ( $m * 60 ) + $s;
		} else {
			return '';
		}
	}

	if ( $seconds <= 0 ) {
		return '';
	}

	$h = intdiv( $seconds, 3600 );
	$m = intdiv( $seconds % 3600, 60 );
	$s = $seconds % 60;

	return 'PT' . ( $h ? $h . 'H' : '' ) . ( $m ? $m . 'M' : '' ) . ( $s || ( ! $h && ! $m ) ? $s . 'S' : '' );
}

/**
 * A duration string as "1:57:25" or "51:05" for display, from either shape
 * iTunes feeds use.
 *
 * @param string $raw
 * @return string
 */
function arv_podcasts_display_duration( $raw ) {
	$raw = trim( (string) $raw );

	if ( '' === $raw ) {
		return '';
	}

	if ( ctype_digit( $raw ) ) {
		$seconds = (int) $raw;
		$h       = intdiv( $seconds, 3600 );
		$m       = intdiv( $seconds % 3600, 60 );
		$s       = $seconds % 60;

		return $h
			? sprintf( '%d:%02d:%02d', $h, $m, $s )
			: sprintf( '%d:%02d', $m, $s );
	}

	// Already "H:MM:SS" or "MM:SS"; iTunes pads all but the leading number,
	// so strip a leading zero on that one and trust the rest.
	return preg_replace( '/^0(\d):/', '$1:', $raw );
}

/**
 * Every episode across every show, newest first, each carrying its show's
 * key and title.
 *
 * @param array $shows From arv_podcasts_fetch().
 * @return array<int, array>
 */
function arv_podcasts_all( $shows ) {
	$all = array();

	foreach ( $shows as $show ) {
		foreach ( $show['episodes'] as $episode ) {
			$episode['show_key']   = $show['key'];
			$episode['show_title'] = $show['title'];

			// An episode with its own artwork keeps it; one without falls
			// back to the show's, so a card never renders with no image at
			// all just because this particular upload skipped it.
			if ( '' === $episode['artwork'] ) {
				$episode['artwork'] = $show['artwork'];
			}

			$all[] = $episode;
		}
	}

	usort(
		$all,
		function ( $a, $b ) {
			$ta = $a['published'] ? strtotime( $a['published'] ) : 0;
			$tb = $b['published'] ? strtotime( $b['published'] ) : 0;
			return $tb <=> $ta;
		}
	);

	return $all;
}

/**
 * One episode row: artwork, show badge, title, date, duration, and a real
 * HTML5 player.
 *
 * A native <audio> element rather than an embed from any platform. RSS
 * hands over the direct file URL, so there is no reason to route playback
 * through a third party's widget the way the Spotify-only version of this
 * page had to. preload="none" because a page can carry sixty of these.
 *
 * @param array $episode
 * @param bool  $show_badge Whether to print the show's name on the row.
 * @return string
 */
function arv_podcasts_episode_row( $episode, $show_badge = true ) {
	$out = '<li class="arv-podcasts__episode">';

	if ( '' !== $episode['artwork'] ) {
		$out .= '<img class="arv-podcasts__ep-art" src="' . esc_url( $episode['artwork'] ) . '" alt=""'
			. ' loading="lazy" decoding="async" width="120" height="120" />';
	}

	$out .= '<div class="arv-podcasts__ep-body">';

	if ( $show_badge && '' !== $episode['show_title'] ) {
		$out .= '<a class="arv-podcasts__ep-show" href="' . esc_url( home_url( '/podcasts/' . $episode['show_key'] . '/' ) ) . '">'
			. esc_html( $episode['show_title'] ) . '</a>';
	}

	$out .= '<h3 class="arv-podcasts__ep-title">' . esc_html( $episode['title'] ) . '</h3>';

	$bits = array();

	if ( '' !== $episode['published'] ) {
		$stamp = strtotime( $episode['published'] );

		if ( $stamp ) {
			$bits[] = gmdate( 'F j, Y', $stamp );
		}
	}

	$duration = arv_podcasts_display_duration( $episode['duration'] );

	if ( '' !== $duration ) {
		$bits[] = $duration;
	}

	if ( ! empty( $bits ) ) {
		$out .= '<p class="arv-podcasts__ep-meta">' . esc_html( implode( ' · ', $bits ) ) . '</p>';
	}

	$out .= '<audio class="arv-podcasts__ep-player" controls preload="none" src="' . esc_url( $episode['audio'] ) . '"></audio>';

	$out .= '</div></li>';

	return $out;
}

/**
 * The Podcasts index: a card per show, then every episode merged, newest
 * first.
 *
 * @param array $args heading, intro, limit.
 * @return string
 */
function arv_podcasts_render( $args = array() ) {
	$shows = arv_podcasts_fetch();

	if ( empty( $shows ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Podcasts';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';
	$limit   = isset( $args['limit'] ) ? (int) $args['limit'] : 0;

	$out = '<section class="arv-podcasts">';
	$out .= '<div class="arv-podcasts__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-podcasts__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-podcasts__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-podcasts__shows">';

	foreach ( $shows as $show ) {
		$out .= '<a class="arv-podcasts__show-card" href="' . esc_url( home_url( '/podcasts/' . $show['key'] . '/' ) ) . '">';

		if ( '' !== $show['artwork'] ) {
			$out .= '<img class="arv-podcasts__show-art" src="' . esc_url( $show['artwork'] ) . '" alt=""'
				. ' loading="lazy" decoding="async" width="200" height="200" />';
		}

		$out .= '<span class="arv-podcasts__show-title">' . esc_html( $show['title'] ) . '</span>';
		$out .= '<span class="arv-podcasts__show-count">'
			. esc_html(
				sprintf(
					/* translators: %s is a count of episodes. */
					_n( '%s episode', '%s episodes', count( $show['episodes'] ), 'aravaipa-elements' ),
					number_format_i18n( count( $show['episodes'] ) )
				)
			)
			. '</span>';
		$out .= '</a>';
	}

	$out .= '</div>';

	$all = arv_podcasts_all( $shows );

	if ( $limit > 0 ) {
		$all = array_slice( $all, 0, $limit );
	}

	if ( ! empty( $all ) ) {
		$out .= '<h3 class="arv-podcasts__section">' . esc_html__( 'Latest episodes', 'aravaipa-elements' ) . '</h3>';
		$out .= '<ul class="arv-podcasts__episodes">';

		foreach ( $all as $episode ) {
			$out .= arv_podcasts_episode_row( $episode, true );
		}

		$out .= '</ul>';
	}

	return $out . '</div></section>';
}

/**
 * One show's own page: artwork, description, subscribe links, and its full
 * episode list.
 *
 * @param array $args show (key).
 * @return string
 */
function arv_podcasts_show_render( $args = array() ) {
	$key   = isset( $args['show'] ) ? trim( (string) $args['show'] ) : '';
	$shows = arv_podcasts_fetch();

	if ( '' === $key || ! isset( $shows[ $key ] ) ) {
		return arv_podcasts_show_missing();
	}

	$show = $shows[ $key ];

	$out = '<section class="arv-podcasts-show">';
	$out .= '<div class="arv-podcasts-show__inner">';

	$out .= '<p class="arv-podcasts-show__back"><a href="' . esc_url( home_url( '/podcasts/' ) ) . '">'
		. '&larr; ' . esc_html__( 'All podcasts', 'aravaipa-elements' ) . '</a></p>';

	$out .= '<div class="arv-podcasts-show__head">';

	if ( '' !== $show['artwork'] ) {
		$out .= '<img class="arv-podcasts-show__art" src="' . esc_url( $show['artwork'] ) . '" alt=""'
			. ' loading="lazy" decoding="async" width="240" height="240" />';
	}

	$out .= '<div class="arv-podcasts-show__head-body">';
	$out .= '<h1 class="arv-podcasts-show__title">' . esc_html( $show['title'] ) . '</h1>';

	if ( '' !== $show['desc'] ) {
		$out .= '<p class="arv-podcasts-show__desc">' . esc_html( $show['desc'] ) . '</p>';
	}

	$out .= '<p class="arv-podcasts-show__links">';

	if ( '' !== $show['spotify'] ) {
		$out .= '<a class="arv-podcasts-show__link" href="https://open.spotify.com/show/' . esc_attr( $show['spotify'] ) . '"'
			. ' target="_blank" rel="noopener">' . esc_html__( 'Spotify', 'aravaipa-elements' ) . '</a>';
	}

	if ( '' !== $show['apple'] ) {
		$out .= '<a class="arv-podcasts-show__link" href="https://podcasts.apple.com/podcast/id' . esc_attr( $show['apple'] ) . '"'
			. ' target="_blank" rel="noopener">' . esc_html__( 'Apple Podcasts', 'aravaipa-elements' ) . '</a>';
	}

	$out .= '<a class="arv-podcasts-show__link" href="' . esc_url( $show['feed'] ) . '"'
		. ' target="_blank" rel="noopener">' . esc_html__( 'RSS', 'aravaipa-elements' ) . '</a>';
	$out .= '</p>';

	$out .= '</div></div>';

	$out .= '<ul class="arv-podcasts__episodes">';

	foreach ( $show['episodes'] as $episode ) {
		$episode['show_key']   = $show['key'];
		$episode['show_title'] = $show['title'];

		if ( '' === $episode['artwork'] ) {
			$episode['artwork'] = $show['artwork'];
		}

		$out .= arv_podcasts_episode_row( $episode, false );
	}

	$out .= '</ul>';

	return $out . '</div></section>';
}

/**
 * What a show page shows for a key nothing configured matches.
 *
 * @return string
 */
function arv_podcasts_show_missing() {
	return '<section class="arv-podcasts-show"><div class="arv-podcasts-show__inner">'
		. '<p class="arv-podcasts-show__back-missing">'
		. esc_html__( "We don't have that show.", 'aravaipa-elements' )
		. ' <a href="' . esc_url( home_url( '/podcasts/' ) ) . '">'
		. esc_html__( 'See every Aravaipa podcast.', 'aravaipa-elements' ) . '</a></p>'
		. '</div></section>';
}

/**
 * [arv_podcasts] so a page can carry the index without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_podcasts_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Podcasts',
			'intro'   => '',
			'limit'   => 0,
		),
		$atts,
		'arv_podcasts'
	);

	return arv_podcasts_render( $atts );
}
add_shortcode( 'arv_podcasts', 'arv_podcasts_shortcode' );

/**
 * [arv_podcast_show show="inside-aravaipa"] for one show's own page.
 *
 * @param array $atts
 * @return string
 */
function arv_podcast_show_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'show' => '' ), $atts, 'arv_podcast_show' );

	return arv_podcasts_show_render( $atts );
}
add_shortcode( 'arv_podcast_show', 'arv_podcast_show_shortcode' );

/**
 * -----------------------------------------------------------------------
 * SEO. PodcastSeries and PodcastEpisode are real schema.org types Google
 * has first-class support for, and unlike video there is no single
 * platform Google treats as canonical for a podcast the way it does
 * YouTube for a video: an RSS feed is the thing every app reads, and this
 * page reading the same feed puts it on equal footing rather than behind
 * Spotify's or Apple's own listing.
 * -----------------------------------------------------------------------
 */

/**
 * The meta key a show page is found by.
 *
 * @return string
 */
function arv_podcasts_meta_key() {
	return '_arv_podcast_show';
}

/**
 * Registers the meta so it is settable through the REST API in the page
 * creation call. See arv_live_register_meta() for why a leading
 * underscore needs an explicit auth_callback at all.
 */
function arv_podcasts_register_meta() {
	register_post_meta(
		'page',
		arv_podcasts_meta_key(),
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
add_action( 'init', 'arv_podcasts_register_meta' );

/**
 * Whether the current request is the Podcasts index or a show page, and
 * its data if so.
 *
 * @return array|null {mode: 'index'|'show', show?: array}
 */
function arv_podcasts_seo_context() {
	if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
		return null;
	}

	$id = get_queried_object_id();

	if ( ! $id ) {
		return null;
	}

	$stored = trim( (string) get_post_meta( $id, arv_podcasts_meta_key(), true ) );

	if ( '' === $stored ) {
		return null;
	}

	$shows = arv_podcasts_fetch();

	if ( empty( $shows ) ) {
		return null;
	}

	if ( 'index' === $stored ) {
		return array(
			'mode'  => 'index',
			'shows' => $shows,
			'url'   => get_permalink( $id ),
		);
	}

	if ( ! isset( $shows[ $stored ] ) ) {
		return null;
	}

	return array(
		'mode' => 'show',
		'show' => $shows[ $stored ],
		'url'  => get_permalink( $id ),
	);
}

/**
 * @param array $parts
 * @return array
 */
function arv_podcasts_seo_title_parts( $parts ) {
	if ( arv_seo_handled_elsewhere() || ! function_exists( 'arv_podcasts_seo_context' ) ) {
		return $parts;
	}

	$ctx = arv_podcasts_seo_context();

	if ( null === $ctx ) {
		return $parts;
	}

	$parts['title'] = ( 'show' === $ctx['mode'] )
		? $ctx['show']['title'] . ' | Aravaipa Running'
		: __( 'Podcasts | Aravaipa Running', 'aravaipa-elements' );

	return $parts;
}
add_filter( 'document_title_parts', 'arv_podcasts_seo_title_parts' );

/**
 * @return void
 */
function arv_podcasts_seo_head() {
	if ( arv_seo_handled_elsewhere() || ! function_exists( 'arv_podcasts_seo_context' ) ) {
		return;
	}

	$ctx = arv_podcasts_seo_context();

	if ( null === $ctx ) {
		return;
	}

	if ( 'show' === $ctx['mode'] ) {
		arv_podcasts_seo_show_head( $ctx );
	} else {
		arv_podcasts_seo_index_head( $ctx );
	}
}
add_action( 'wp_head', 'arv_podcasts_seo_head', 4 );

/**
 * @param array $ctx
 * @return void
 */
function arv_podcasts_seo_index_head( $ctx ) {
	$total = 0;

	foreach ( $ctx['shows'] as $show ) {
		$total += count( $show['episodes'] );
	}

	$description = sprintf(
		/* translators: 1: count of shows, 2: count of episodes. */
		__( '%1$s Aravaipa Running podcasts, %2$s episodes and counting: race previews, athlete interviews and more.', 'aravaipa-elements' ),
		number_format_i18n( count( $ctx['shows'] ) ),
		number_format_i18n( $total )
	);

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr__( 'Podcasts | Aravaipa Running', 'aravaipa-elements' ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $ctx['url'] ) . '" />' . "\n";

	$nodes = array();

	foreach ( $ctx['shows'] as $show ) {
		$node = arv_podcasts_series_node( $show );

		if ( null !== $node ) {
			$nodes[] = $node;
		}
	}

	echo arv_seo_schema_script( $nodes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * @param array $ctx
 * @return void
 */
function arv_podcasts_seo_show_head( $ctx ) {
	$show        = $ctx['show'];
	$description = '' !== $show['desc']
		? $show['desc']
		: sprintf(
			/* translators: %s is a podcast show name. */
			__( 'Listen to %s from Aravaipa Running.', 'aravaipa-elements' ),
			$show['title']
		);

	if ( strlen( $description ) > 160 ) {
		$description = rtrim( substr( $description, 0, strrpos( substr( $description, 0, 158 ), ' ' ) ), " ,.;:" ) . '…';
	}

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $show['title'] . ' | Aravaipa Running' ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $ctx['url'] ) . '" />' . "\n";

	if ( '' !== $show['artwork'] ) {
		echo '<meta property="og:image" content="' . esc_url( $show['artwork'] ) . '" />' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	}

	$node = arv_podcasts_series_node( $show, true );

	echo arv_seo_schema_script( null === $node ? array() : array( $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * A PodcastSeries node for one show, with its episodes as PodcastEpisode
 * children when asked for.
 *
 * Every required field present on every episode node or the node is
 * dropped, the same rule the Watch page's VideoObject nodes follow: an
 * episode with no publish date is not a partial win.
 *
 * @param array $show
 * @param bool  $with_episodes
 * @return array|null
 */
function arv_podcasts_series_node( $show, $with_episodes = false ) {
	if ( '' === trim( $show['title'] ) ) {
		return null;
	}

	$node = array(
		'@type' => 'PodcastSeries',
		'name'  => $show['title'],
		'url'   => home_url( '/podcasts/' . $show['key'] . '/' ),
		'webFeed' => $show['feed'],
	);

	if ( '' !== $show['artwork'] ) {
		$node['image'] = $show['artwork'];
	}

	if ( '' !== $show['desc'] ) {
		$node['description'] = $show['desc'];
	}

	if ( ! $with_episodes ) {
		return $node;
	}

	$episodes = array();

	foreach ( $show['episodes'] as $episode ) {
		if ( '' === trim( $episode['title'] ) || '' === $episode['published'] ) {
			continue;
		}

		$item = array(
			'@type'         => 'PodcastEpisode',
			'name'          => $episode['title'],
			'url'           => '' !== $episode['link'] ? $episode['link'] : home_url( '/podcasts/' . $show['key'] . '/' ),
			'datePublished' => $episode['published'],
			'associatedMedia' => array(
				'@type'      => 'MediaObject',
				'contentUrl' => $episode['audio'],
			),
		);

		if ( '' !== trim( $episode['desc'] ) ) {
			$item['description'] = wp_strip_all_tags( $episode['desc'] );
		}

		$duration = arv_podcasts_iso_duration( $episode['duration'] );

		if ( '' !== $duration ) {
			$item['duration'] = $duration;
		}

		$episodes[] = $item;
	}

	if ( ! empty( $episodes ) ) {
		$node['episode'] = $episodes;
	}

	return $node;
}
