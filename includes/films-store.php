<?php
/**
 * Aravaipa's films, read from Mountain Outpost.
 *
 * Same relation to /api/films that includes/watch-store.php has to
 * /api/broadcast/streams: the data lives somewhere else, and this is the
 * read of it. There is no importer and no scraper, because the films
 * themselves live in two YouTube playlists on the Aravaipa Running channel,
 * not in anything Aravaipa's own systems hold. A film goes up on YouTube
 * and into a playlist; it does not go into WordPress. Copying the list here
 * would be inventing a sync problem rather than solving one.
 *
 * Unlike Watch, there is no "edition": a film is one thing, once, not a
 * race that runs again every year. So there is one page rather than one per
 * race, and one player on it that plays whichever film was asked for.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ARV_FILMS_API' ) ) {
	define( 'ARV_FILMS_API', 'https://mountainoutpost.com/api/films' );
}

/**
 * Every Aravaipa film, grouped by playlist.
 *
 * Cached for an hour: films are added rarely, and Mountain Outpost's own
 * route already caches the YouTube call for the same hour, so reading it
 * more often than that would only ever return the same answer sooner. A
 * failure is cached for one minute, the same asymmetry the Watch store
 * uses and for the same reason: caching nothing would retry on every page
 * view for as long as an outage lasts, and caching it for the full hour
 * would hide a recovery for that long.
 *
 * @param bool $fresh Skip the cache.
 * @return array<int, array> {key, title, films}
 */
function arv_films_fetch( $fresh = false ) {
	$key = 'arv_films';

	if ( ! $fresh ) {
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return ( 'none' === $cached ) ? array() : $cached;
		}
	}

	$response = wp_remote_get(
		ARV_FILMS_API,
		array(
			'headers' => array( 'User-Agent' => 'aravaipa-elements-films' ),
			'timeout' => 5,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		set_transient( $key, 'none', MINUTE_IN_SECONDS );
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) || ! isset( $body['playlists'] ) || ! is_array( $body['playlists'] ) ) {
		set_transient( $key, 'none', MINUTE_IN_SECONDS );
		return array();
	}

	$playlists = arv_films_clean( $body['playlists'] );

	set_transient( $key, $playlists, HOUR_IN_SECONDS );

	return $playlists;
}

/**
 * Normalise the API's playlists into the shape this plugin renders, and
 * fold a trailer into the feature it trails.
 *
 * Everything is treated as untrusted and coerced, the same posture the
 * Watch store takes toward a system this plugin does not own: a schema
 * change on the other side should render a quiet gap here, never a fatal.
 *
 * A trailer is identified by the word appearing in its own title, which
 * Aravaipa's own titling already does consistently ("THE CHASE - Official
 * Trailer", "Inaugural Year | OFFICIAL TRAILER"). It is folded under
 * whichever other film in the same playlist opens with the same words, so
 * "THE CHASE | Cocodona 250 Full Documentary" gets a Trailer link on its
 * own card rather than the trailer sitting beside it as a second, competing
 * card for the same film. Matched on the leading phrase rather than the
 * full title, because that is the one part a feature and its trailer
 * always share and everything after it does not.
 *
 * Folded only on an unambiguous match. A trailer whose leading phrase does
 * not match exactly one other film in its playlist is left as its own
 * card: showing it beside the wrong feature, or hiding it with nothing to
 * attach it to, is worse than a shelf with one extra card on it.
 *
 * @param array $raw
 * @return array
 */
function arv_films_clean( $raw ) {
	$playlists = array();

	foreach ( $raw as $playlist ) {
		if ( ! is_array( $playlist ) || empty( $playlist['key'] ) || empty( $playlist['title'] ) ) {
			continue;
		}

		$films    = array();
		$trailers = array();

		foreach ( ( isset( $playlist['films'] ) && is_array( $playlist['films'] ) ? $playlist['films'] : array() ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['title'] ) ) {
				continue;
			}

			$id    = (string) $item['id'];
			$title = (string) $item['title'];

			$film = array(
				'id'        => $id,
				'title'     => $title,
				'desc'      => isset( $item['description'] ) ? (string) $item['description'] : '',
				'thumbnail' => isset( $item['thumbnail'] ) && '' !== $item['thumbnail']
					? (string) $item['thumbnail']
					: 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
				'published' => isset( $item['publishedAt'] ) ? (string) $item['publishedAt'] : '',
				'url'       => 'https://youtu.be/' . $id,
				'lead'      => arv_films_lead_phrase( $title ),
				'trailer'   => null,
			);

			if ( preg_match( '/\btrailer\b/i', $title ) ) {
				$trailers[] = $film;
			} else {
				$films[] = $film;
			}
		}

		foreach ( $trailers as $trailer ) {
			$matches = array_values(
				array_filter(
					$films,
					function ( $film ) use ( $trailer ) {
						return $film['lead'] === $trailer['lead'];
					}
				)
			);

			if ( 1 === count( $matches ) ) {
				foreach ( $films as $i => $film ) {
					if ( $film['id'] === $matches[0]['id'] ) {
						$films[ $i ]['trailer'] = $trailer;
						break;
					}
				}
			} else {
				// No match, or more than one candidate: kept as its own
				// card rather than guessed onto the wrong feature.
				$films[] = $trailer;
			}
		}

		if ( empty( $films ) ) {
			continue;
		}

		$playlists[] = array(
			'key'   => (string) $playlist['key'],
			'title' => (string) $playlist['title'],
			'films' => $films,
		);
	}

	return $playlists;
}

/**
 * The words a title and its trailer have in common.
 *
 * "THE CHASE | Cocodona 250 Full Documentary" and "THE CHASE - Official
 * Trailer - A Cocodona 250 Story" agree on nothing after the first
 * delimiter, so this is deliberately only that: split on the first "|",
 * "-" or ":", uppercased and trimmed. Punctuation-light on purpose, since
 * the two sides of a trailer pairing are not always punctuated the same
 * way either.
 *
 * @param string $title
 * @return string
 */
function arv_films_lead_phrase( $title ) {
	$lead = preg_split( '/[|:-]/', $title, 2 )[0];

	return trim( preg_replace( '/[^A-Za-z0-9 ]+/', '', strtoupper( $lead ) ) );
}

/**
 * Every film across both playlists, flattened, newest first.
 *
 * @param array $playlists
 * @return array
 */
function arv_films_all( $playlists ) {
	$all = array();

	foreach ( $playlists as $playlist ) {
		foreach ( $playlist['films'] as $film ) {
			$all[] = $film;
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
 * A film by id, searched across both playlists and into a folded trailer.
 *
 * @param array  $playlists
 * @param string $id
 * @return array|null
 */
function arv_films_find( $playlists, $id ) {
	foreach ( arv_films_all( $playlists ) as $film ) {
		if ( $film['id'] === $id ) {
			return $film;
		}

		if ( null !== $film['trailer'] && $film['trailer']['id'] === $id ) {
			return $film['trailer'];
		}
	}

	return null;
}

/**
 * The Films page: a player, and every film below it in its playlist.
 *
 * One page rather than one per film, unlike Watch's per-race pages: a film
 * is one thing, once, not an edition of something that runs again next
 * year, so there is nothing here that would be gained by splitting it into
 * twenty-four pages. ?v= picks which film the player opens on; a card's
 * own link carries that parameter, and a script upgrades the click into
 * swapping the same player instead of a page reload. See
 * assets/aravaipa-films.js.
 *
 * Renders nothing when the feed is empty or unreachable, the same
 * "no section reads as unbuilt, not broken" rule Watch uses.
 *
 * @param array $args heading, intro.
 * @return string
 */
function arv_films_render( $args = array() ) {
	$playlists = arv_films_fetch();

	if ( empty( $playlists ) ) {
		return '';
	}

	$all = arv_films_all( $playlists );

	$requested = isset( $_GET['v'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', wp_unslash( $_GET['v'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$active    = ( '' !== $requested ) ? arv_films_find( $playlists, $requested ) : null;

	if ( null === $active ) {
		$active = $all[0];
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Films';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';

	$out = '<section class="arv-films">';
	$out .= '<div class="arv-films__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-films__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-films__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-films__frame">'
		. '<iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $active['id'] ) . '"'
		. ' title="' . esc_attr( $active['title'] ) . '"'
		. ' loading="lazy" allowfullscreen'
		. ' allow="accelerometer; encrypted-media; picture-in-picture"'
		. ' referrerpolicy="strict-origin-when-cross-origin"></iframe>'
		. '</div>';

	$out .= '<p class="arv-films__now-title">' . esc_html( $active['title'] ) . '</p>';

	foreach ( $playlists as $playlist ) {
		$out .= '<h3 class="arv-films__section">' . esc_html( $playlist['title'] ) . '</h3>';
		$out .= '<ul class="arv-films__list">';

		foreach ( $playlist['films'] as $film ) {
			$out .= arv_films_card( $film, $active['id'] );
		}

		$out .= '</ul>';
	}

	return $out . '</div></section>';
}

/**
 * One film card: thumbnail, title, date, and a Trailer link if it has one.
 *
 * @param array  $film
 * @param string $active_id The film currently in the player.
 * @return string
 */
function arv_films_card( $film, $active_id ) {
	$is_active = ( $film['id'] === $active_id );

	$out = '<li class="arv-films__card' . ( $is_active ? ' is-active' : '' ) . '"'
		. ' data-arv-films-title="' . esc_attr( strtolower( $film['title'] ) ) . '">';

	$out .= '<a class="arv-films__link" href="' . esc_url( $film['url'] ) . '"'
		. ' data-yt-id="' . esc_attr( $film['id'] ) . '"'
		. ' data-yt-title="' . esc_attr( $film['title'] ) . '"'
		. ( $is_active ? ' aria-current="true"' : '' )
		. ' target="_blank" rel="noopener">';
	$out .= '<img class="arv-films__thumb" src="' . esc_url( $film['thumbnail'] ) . '" alt=""'
		. ' loading="lazy" decoding="async" width="480" height="360" />';
	$out .= '<span class="arv-films__title">' . esc_html( $film['title'] ) . '</span>';

	if ( '' !== $film['published'] ) {
		$stamp = strtotime( $film['published'] );

		if ( $stamp ) {
			$out .= '<span class="arv-films__date">' . esc_html( gmdate( 'F j, Y', $stamp ) ) . '</span>';
		}
	}

	$out .= '</a>';

	if ( null !== $film['trailer'] ) {
		$out .= '<a class="arv-films__trailer" href="' . esc_url( $film['trailer']['url'] ) . '"'
			. ' data-yt-id="' . esc_attr( $film['trailer']['id'] ) . '"'
			. ' data-yt-title="' . esc_attr( $film['trailer']['title'] ) . '"'
			. ' target="_blank" rel="noopener">'
			. esc_html__( 'Watch trailer', 'aravaipa-elements' )
			. '</a>';
	}

	return $out . '</li>';
}

/**
 * [arv_films] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_films_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Films',
			'intro'   => '',
		),
		$atts,
		'arv_films'
	);

	return arv_films_render( $atts );
}
add_shortcode( 'arv_films', 'arv_films_shortcode' );

/**
 * -----------------------------------------------------------------------
 * SEO. Same reasoning as includes/watch-store.php's block: every required
 * field present on every VideoObject node or the node is dropped, and
 * every printer checks arv_seo_handled_elsewhere() first.
 * -----------------------------------------------------------------------
 */

/**
 * The meta key a Films page is found by.
 *
 * @return string
 */
function arv_films_meta_key() {
	return '_arv_films_page';
}

/**
 * Registers the meta so it is settable through the REST API in the same
 * page-creation call that publishes the page. See
 * arv_live_register_meta() for why a leading-underscore key needs an
 * explicit auth_callback at all.
 */
function arv_films_register_meta() {
	register_post_meta(
		'page',
		arv_films_meta_key(),
		array(
			'type'          => 'boolean',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'arv_films_register_meta' );

/**
 * Whether the current request is the Films page, and its data if so.
 *
 * @return array|null
 */
function arv_films_seo_context() {
	if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
		return null;
	}

	$id = get_queried_object_id();

	if ( ! $id || ! get_post_meta( $id, arv_films_meta_key(), true ) ) {
		return null;
	}

	$playlists = arv_films_fetch();

	if ( empty( $playlists ) ) {
		return null;
	}

	return array(
		'playlists' => $playlists,
		'url'       => get_permalink( $id ),
	);
}

/**
 * @param array $parts
 * @return array
 */
function arv_films_seo_title_parts( $parts ) {
	if ( arv_seo_handled_elsewhere() || ! function_exists( 'arv_films_seo_context' ) ) {
		return $parts;
	}

	if ( null !== arv_films_seo_context() ) {
		$parts['title'] = __( 'Films | Aravaipa Running', 'aravaipa-elements' );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'arv_films_seo_title_parts' );

/**
 * @return void
 */
function arv_films_seo_head() {
	if ( arv_seo_handled_elsewhere() || ! function_exists( 'arv_films_seo_context' ) ) {
		return;
	}

	$ctx = arv_films_seo_context();

	if ( null === $ctx ) {
		return;
	}

	$all         = arv_films_all( $ctx['playlists'] );
	$description = sprintf(
		/* translators: %s is a count of films. */
		__( 'Watch %s trail running documentaries and original films from Aravaipa Running, free on YouTube.', 'aravaipa-elements' ),
		number_format_i18n( count( $all ) )
	);

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr__( 'Films | Aravaipa Running', 'aravaipa-elements' ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:type" content="video.other" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $ctx['url'] ) . '" />' . "\n";

	if ( ! empty( $all ) ) {
		echo '<meta property="og:image" content="' . esc_url( $all[0]['thumbnail'] ) . '" />' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	}

	$items = array();
	$n     = 0;

	foreach ( $all as $film ) {
		$stamp = $film['published'] ? strtotime( $film['published'] ) : 0;

		if ( '' === trim( $film['title'] ) || ! $stamp ) {
			continue;
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => ++$n,
			'item'     => array(
				'@type'        => 'VideoObject',
				'name'         => $film['title'],
				'description'  => '' !== trim( $film['desc'] ) ? $film['desc'] : $film['title'],
				'thumbnailUrl' => $film['thumbnail'],
				'uploadDate'   => gmdate( 'c', $stamp ),
				'embedUrl'     => 'https://www.youtube-nocookie.com/embed/' . $film['id'],
				'contentUrl'   => $film['url'],
				'url'          => add_query_arg( 'v', $film['id'], $ctx['url'] ),
			),
		);
	}

	$nodes = array();

	if ( ! empty( $items ) ) {
		$nodes[] = array(
			'@type'           => 'ItemList',
			'name'            => 'Films | Aravaipa Running',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
	}

	echo arv_seo_schema_script( $nodes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'arv_films_seo_head', 4 );
