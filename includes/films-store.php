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
				'views'     => isset( $item['views'] ) ? (int) $item['views'] : 0,
				'duration'  => isset( $item['duration'] ) ? (string) $item['duration'] : '',
				'url'       => 'https://youtu.be/' . $id,
				'lead'      => arv_films_lead_phrase( $title ),
				// Which race this film is about, worked out from its own
				// title. See arv_films_race_for().
				'race'      => arv_films_race_for( $title ),
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

	return arv_films_sort( arv_films_dedupe( $playlists ) );
}

/**
 * Newest film first, inside each playlist.
 *
 * The API hands these back in YouTube playlist order, which is whatever
 * order somebody dragged them into in Studio: on the Documentaries
 * playlist that put a 2019 film above a 2024 one and left The Cutoff,
 * the newest film on the site, fourteenth.
 *
 * Sorted here rather than left to aravaipa-films.js because the sort
 * control on the page says "Newest first" and is selected by default, so
 * that is a claim the HTML has to be able to make on its own. The script
 * only ever ran its sort in response to a change event, so until a
 * visitor touched a control the page and the control disagreed, and a
 * crawler never saw the order at all.
 *
 * Within a playlist only, for the same reason the script sorts that way:
 * the sections are the two playlists, and merging them would answer a
 * question nobody asked.
 *
 * @param array $playlists
 * @return array
 */
function arv_films_sort( $playlists ) {
	foreach ( $playlists as $i => $playlist ) {
		$films = $playlist['films'];

		usort(
			$films,
			function ( $a, $b ) {
				$ta = $a['published'] ? strtotime( $a['published'] ) : 0;
				$tb = $b['published'] ? strtotime( $b['published'] ) : 0;

				if ( $ta === $tb ) {
					return strcasecmp( $a['title'], $b['title'] );
				}

				return $tb - $ta;
			}
		);

		$playlists[ $i ]['films'] = $films;
	}

	return $playlists;
}

/**
 * A film that is in more than one playlist appears once, under the last
 * playlist that carries it.
 *
 * "THE RACE DIRECTOR" is in both Documentaries and Aravaipa Originals on
 * YouTube, so it rendered twice on one page. It belongs in Originals,
 * which is also the later of the two in PLAYLISTS order, and "the last
 * playlist wins" is a rule rather than a special case: a film added to a
 * more specific playlist later is being reclassified, and the newer
 * classification is the one that was meant.
 *
 * The cleaner fix is removing it from the Documentaries playlist in
 * YouTube, which is the source of truth this page is built to follow. This
 * is here because a page should not render the same film twice while
 * waiting for that, and because nothing stops it happening again.
 *
 * @param array $playlists
 * @return array
 */
function arv_films_dedupe( $playlists ) {
	$last = array();

	foreach ( $playlists as $i => $playlist ) {
		foreach ( $playlist['films'] as $film ) {
			$last[ $film['id'] ] = $i;
		}
	}

	foreach ( $playlists as $i => $playlist ) {
		$playlists[ $i ]['films'] = array_values(
			array_filter(
				$playlist['films'],
				function ( $film ) use ( $last, $i ) {
					return $last[ $film['id'] ] === $i;
				}
			)
		);
	}

	// A playlist emptied entirely by the dedupe would render as a heading
	// over nothing.
	return array_values(
		array_filter(
			$playlists,
			function ( $playlist ) {
				return ! empty( $playlist['films'] );
			}
		)
	);
}

/**
 * The race a film is about, worked out from its own title.
 *
 * Aravaipa names its films after the race in them: "THE CHASE | Cocodona
 * 250 Full Documentary", "Stampede of Two | 2024 Coldwater Rumble 100
 * Mile", "EVERY YEAR | Jack Higgs & the North Fork 50 Mile". So the tag
 * does not need to be maintained by hand anywhere; it is already written
 * on the film.
 *
 * Matched against the race store rather than a list kept here, so a race
 * added to the calendar is matchable the same day. The longest matching
 * phrase across every race wins, not the first race that matches
 * anything: "Tushars Mountain Runs 2021" contains the word "mountain",
 * which on its own would just as happily match Mountain Ridge Trail Race,
 * and did while this was being written.
 *
 * A single word only identifies a race if it is not a generic piece of
 * landscape or race vocabulary, for the same reason. Two words are enough
 * on their own.
 *
 * Returns '' for a film that is not about one race, which "THE RACE
 * DIRECTOR | Crafting Endurance in the Midwest" genuinely is not: no tag
 * is the honest answer there rather than the nearest guess.
 *
 * @param string $title
 * @return string Race name as the store spells it, or ''.
 */
function arv_films_race_for( $title ) {
	if ( ! function_exists( 'arv_race_store_get' ) ) {
		return '';
	}

	$haystack = arv_films_normalise( $title );

	if ( '' === $haystack ) {
		return '';
	}

	$best      = '';
	$best_len  = 0;

	foreach ( arv_films_race_names() as $race ) {
		foreach ( arv_films_race_phrases( $race ) as $phrase ) {
			if ( strlen( $phrase ) <= $best_len ) {
				continue;
			}

			if ( preg_match( '/\b' . preg_quote( $phrase, '/' ) . '\b/', $haystack ) ) {
				$best     = $race;
				$best_len = strlen( $phrase );
			}
		}
	}

	return $best;
}

/**
 * Every race name worth matching a film against: the calendar, plus every
 * race the results archive remembers, since most films are about races
 * that have already run and dropped off the calendar.
 *
 * @return array<int, string>
 */
function arv_films_race_names() {
	// Deliberately not memoised in a static. Both stores below already
	// cache their own reads, so recomputing this is array work rather than
	// queries, and a static here caches whatever the stores happened to
	// hold the very first time anything asked: during the test suite that
	// was an empty race store, and every film came back untagged for the
	// rest of the run. A per-request cache that can be wrong for the whole
	// request is worse than no cache at all for a list this cheap.
	$seen = array();

	if ( function_exists( 'arv_race_store_get' ) ) {
		foreach ( arv_race_store_get() as $race ) {
			$seen[ $race['name'] ] = true;
		}
	}

	if ( function_exists( 'arv_results_store_get' ) ) {
		foreach ( arv_results_store_get() as $row ) {
			$seen[ $row['name'] ] = true;
		}
	}

	return array_keys( $seen );
}

/**
 * The phrases that identify one race, longest first: its whole name, then
 * progressively shorter leading runs of words.
 *
 * "Tushars Mountain Runs" is written "THE TUSHARS" on one film and
 * "Tushars Mountain Runs 2021" on another, so a prefix has to count. The
 * guards below are what stop that generosity turning into a wrong tag.
 *
 * @param string $race
 * @return array<int, string>
 */
function arv_films_race_phrases( $race ) {
	$words = explode( ' ', arv_films_normalise( $race ) );
	$out   = array();

	for ( $n = count( $words ); $n > 0; $n-- ) {
		$phrase = implode( ' ', array_slice( $words, 0, $n ) );

		if ( strlen( $phrase ) < 5 ) {
			continue;
		}

		// One word on its own has to actually name a race. "Mountain",
		// "Canyon" and "Desert" name about a dozen each.
		if ( 1 === $n && ( strlen( $phrase ) < 7 || in_array( $phrase, arv_films_generic_words(), true ) ) ) {
			continue;
		}

		$out[] = $phrase;
	}

	return $out;
}

/**
 * Words that describe where a race is or what it is, rather than which
 * race it is.
 *
 * @return array<int, string>
 */
function arv_films_generic_words() {
	return apply_filters(
		'arv_films_generic_words',
		array(
			'mountain', 'mountains', 'canyon', 'valley', 'ridge', 'creek',
			'desert', 'lake', 'park', 'springs', 'river', 'peak', 'peaks',
			'trail', 'trails', 'endurance', 'festival', 'classic', 'series',
			'marathon', 'ultras', 'ultra', 'night', 'runs', 'race', 'races',
		)
	);
}

/**
 * Lowercase, punctuation flattened to single spaces.
 *
 * @param string $value
 * @return string
 */
function arv_films_normalise( $value ) {
	return trim( preg_replace( '/\s+/', ' ', strtolower( preg_replace( '/[^A-Za-z0-9]+/', ' ', (string) $value ) ) ) );
}

/**
 * This page's own URL, for a badge that filters it in place.
 *
 * @return string
 */
function arv_films_self_url() {
	if ( ! function_exists( 'get_queried_object_id' ) ) {
		return '';
	}

	$id = get_queried_object_id();

	return $id ? get_permalink( $id ) : home_url( '/films/' );
}

/**
 * An ISO 8601 duration from YouTube ("PT1H50M55S") as "1:50:55".
 *
 * @param string $iso
 * @return string '' when it is not a duration this understands.
 */
function arv_films_duration( $iso ) {
	if ( ! preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', trim( (string) $iso ), $m ) ) {
		return '';
	}

	$h = isset( $m[1] ) && '' !== $m[1] ? (int) $m[1] : 0;
	$i = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
	$sec = isset( $m[3] ) && '' !== $m[3] ? (int) $m[3] : 0;

	if ( ! $h && ! $i && ! $sec ) {
		return '';
	}

	return $h
		? sprintf( '%d:%02d:%02d', $h, $i, $sec )
		: sprintf( '%d:%02d', $i, $sec );
}

/**
 * A view count as "792K" or "1.2M", which is what a card has room for.
 *
 * @param int $views
 * @return string '' when there is no count to show.
 */
function arv_films_views( $views ) {
	$views = (int) $views;

	if ( $views <= 0 ) {
		return '';
	}

	if ( $views >= 1000000 ) {
		return rtrim( rtrim( number_format( $views / 1000000, 1 ), '0' ), '.' ) . 'M';
	}

	if ( $views >= 1000 ) {
		return round( $views / 1000 ) . 'K';
	}

	return (string) $views;
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

	// ?race= narrows the whole page to one race, which is what a badge on a
	// card links to. Server-rendered rather than left to the filter script,
	// so the narrowed page is a real URL: shareable, linkable from a race's
	// own page, and visible to a crawler as a page about that race.
	$race = isset( $_GET['race'] ) ? arv_films_normalise( wp_unslash( $_GET['race'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( '' !== $race ) {
		$out .= arv_films_race_notice( $race, $all );
	}

	$out .= arv_films_controls( $all );

	foreach ( $playlists as $playlist ) {
		$films = $playlist['films'];

		if ( '' !== $race ) {
			$films = array_values(
				array_filter(
					$films,
					function ( $film ) use ( $race ) {
						return arv_films_normalise( $film['race'] ) === $race;
					}
				)
			);
		}

		if ( empty( $films ) ) {
			continue;
		}

		$out .= '<h3 class="arv-films__section">' . esc_html( $playlist['title'] ) . '</h3>';
		$out .= '<ul class="arv-films__list" data-arv-films-list>';

		foreach ( $films as $film ) {
			$out .= arv_films_card( $film, $active['id'] );
		}

		$out .= '</ul>';
	}

	// The moment someone has just scrolled the whole shelf, not a badge
	// sitting in a corner nobody was looking at.
	if ( function_exists( 'arv_media_follow_render' ) ) {
		$out .= arv_media_follow_render( 'youtube', __( 'film', 'aravaipa-elements' ) );
	}

	return $out . '</div></section>';
}

/**
 * The line above a race-filtered page, and the way back off it.
 *
 * @param string $race Normalised race name from ?race=.
 * @param array  $all  Every film, for the count and the display name.
 * @return string
 */
function arv_films_race_notice( $race, $all ) {
	$name  = '';
	$count = 0;

	foreach ( $all as $film ) {
		if ( arv_films_normalise( $film['race'] ) === $race ) {
			$name = $film['race'];
			$count++;
		}
	}

	if ( 0 === $count ) {
		return '<p class="arv-films__notice">'
			. esc_html__( 'No films about that race yet.', 'aravaipa-elements' )
			. ' <a href="' . esc_url( remove_query_arg( 'race' ) ) . '">'
			. esc_html__( 'Show every film.', 'aravaipa-elements' ) . '</a></p>';
	}

	return '<p class="arv-films__notice">'
		. esc_html(
			sprintf(
				/* translators: 1: count of films, 2: a race name. */
				_n( '%1$s film about %2$s', '%1$s films about %2$s', $count, 'aravaipa-elements' ),
				number_format_i18n( $count ),
				$name
			)
		)
		. ' <a href="' . esc_url( remove_query_arg( 'race' ) ) . '">'
		. esc_html__( 'Show every film.', 'aravaipa-elements' ) . '</a></p>';
}

/**
 * Search, sort and a race filter above the shelf.
 *
 * Progressive enhancement, the same contract the Watch archive's controls
 * have: every film is already in the HTML and aravaipa-films.js only
 * reorders and hides what is there. With no script the page is still the
 * complete, date-ordered shelf it was before.
 *
 * @param array $all Every film, for the race options.
 * @return string
 */
function arv_films_controls( $all ) {
	$races = array();

	foreach ( $all as $film ) {
		if ( '' !== $film['race'] ) {
			$races[ $film['race'] ] = true;
		}
	}

	ksort( $races );

	$out = '<div class="arv-films__controls">';

	$out .= '<label class="arv-films__search-label" for="arv-films-q">'
		. esc_html__( 'Search films', 'aravaipa-elements' ) . '</label>';
	$out .= '<span class="arv-films__search-field">';
	$out .= '<input class="arv-films__search-input" id="arv-films-q" type="search" autocomplete="off"'
		. ' placeholder="' . esc_attr__( 'Title or race', 'aravaipa-elements' ) . '" data-arv-films-search />';
	$out .= '<button class="arv-films__search-clear" type="button" hidden data-arv-films-clear>'
		. '<span class="arv-results__sr">' . esc_html__( 'Clear search', 'aravaipa-elements' ) . '</span>'
		. '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">'
		. '<path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
		. '</svg></button>';
	$out .= '</span>';

	$out .= '<select class="arv-films__sort" data-arv-films-sort aria-label="'
		. esc_attr__( 'Sort films', 'aravaipa-elements' ) . '">';
	$out .= '<option value="date">' . esc_html__( 'Newest first', 'aravaipa-elements' ) . '</option>';
	$out .= '<option value="views">' . esc_html__( 'Most watched', 'aravaipa-elements' ) . '</option>';
	$out .= '</select>';

	if ( count( $races ) > 1 ) {
		$out .= '<select class="arv-films__race-select" data-arv-films-race aria-label="'
			. esc_attr__( 'Filter by race', 'aravaipa-elements' ) . '">';
		$out .= '<option value="">' . esc_html__( 'Every race', 'aravaipa-elements' ) . '</option>';

		foreach ( array_keys( $races ) as $name ) {
			$out .= '<option value="' . esc_attr( arv_films_normalise( $name ) ) . '">'
				. esc_html( $name ) . '</option>';
		}

		$out .= '</select>';
	}

	$out .= '<p class="arv-films__count" data-arv-films-count aria-live="polite"></p>';

	return $out . '</div>';
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
	$stamp     = $film['published'] ? strtotime( $film['published'] ) : 0;

	// Sorting and filtering happen in the browser over cards that are
	// already on the page, so every value either control reads has to be
	// on the card itself rather than looked up again.
	$out = '<li class="arv-films__card' . ( $is_active ? ' is-active' : '' ) . '"'
		. ' data-arv-films-title="' . esc_attr( strtolower( $film['title'] ) ) . '"'
		. ' data-arv-films-race="' . esc_attr( arv_films_normalise( $film['race'] ) ) . '"'
		. ' data-arv-films-views="' . esc_attr( (int) $film['views'] ) . '"'
		. ' data-arv-films-date="' . esc_attr( $stamp ? $stamp : 0 ) . '">';

	$out .= '<a class="arv-films__link" href="' . esc_url( $film['url'] ) . '"'
		. ' data-yt-id="' . esc_attr( $film['id'] ) . '"'
		. ' data-yt-title="' . esc_attr( $film['title'] ) . '"'
		. ( $is_active ? ' aria-current="true"' : '' )
		. ' target="_blank" rel="noopener">';
	$out .= '<img class="arv-films__thumb" src="' . esc_url( $film['thumbnail'] ) . '" alt=""'
		. ' loading="lazy" decoding="async" width="480" height="360" />';

	$duration = arv_films_duration( $film['duration'] );

	// Over the thumbnail rather than in the body, where YouTube itself puts
	// it and where a viewer already looks for it.
	if ( '' !== $duration ) {
		$out .= '<span class="arv-films__duration">' . esc_html( $duration ) . '</span>';
	}

	$out .= '<span class="arv-films__title">' . esc_html( $film['title'] ) . '</span>';

	$bits = array();

	if ( $stamp ) {
		$bits[] = gmdate( 'F j, Y', $stamp );
	}

	$views = arv_films_views( $film['views'] );

	if ( '' !== $views ) {
		$bits[] = sprintf(
			/* translators: %s is an abbreviated view count, e.g. "792K". */
			__( '%s views', 'aravaipa-elements' ),
			$views
		);
	}

	if ( ! empty( $bits ) ) {
		$out .= '<span class="arv-films__date">' . esc_html( implode( ' · ', $bits ) ) . '</span>';
	}

	$out .= '</a>';

	// The race badge sits outside the card's own link, because it is a link
	// somewhere else: nesting it would be an <a> inside an <a>, which is
	// invalid and which browsers resolve by silently closing the outer one.
	if ( '' !== $film['race'] ) {
		$out .= '<span class="arv-films__race">';
		$out .= '<a class="arv-films__race-tag" href="'
			. esc_url( add_query_arg( 'race', arv_films_normalise( $film['race'] ), arv_films_self_url() ) ) . '">'
			. esc_html( $film['race'] ) . '</a>';

		$race_page = function_exists( 'arv_watch_race_page_link' )
			? arv_watch_race_page_link( $film['race'] )
			: '';

		if ( '' !== $race_page ) {
			$out .= '<a class="arv-films__race-page" href="' . esc_url( $race_page ) . '">'
				. esc_html__( 'Race page', 'aravaipa-elements' ) . '</a>';
		}

		$out .= '</span>';
	}

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
