<?php
/**
 * Race photo galleries: who shot a race, and where the pictures are.
 *
 * Aravaipa does not host these. Its own galleries live on SmugMug, and a
 * good share of every race is shot by outside photographers on whatever
 * platform they use: PassGallery, their own site, another SmugMug account.
 * So this is a directory, not a library. The pictures stay where the
 * photographer put them and this points at them.
 *
 * An option rather than a post type, the same reasoning as
 * includes/results-store.php: it is derived wholesale from the site's own
 * photos-YYYY pages by scripts/import-photos.mjs, nothing in it is edited
 * by hand, and it only ever grows. Around two hundred rows of four short
 * fields.
 *
 * The one thing this file does that the results store does not is fetch.
 * A directory of links reads as a wall of text, which is exactly what the
 * hand-built pages it replaces were; a directory of pictures reads as a
 * photo page. Every gallery host worth linking to publishes an Open Graph
 * cover image, because they all want their own links to look like
 * something when shared, so the cover is read from the gallery itself
 * rather than stored here or uploaded twice. See arv_photos_cover().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_PHOTOS_OPTION', 'arv_race_photos' );

/**
 * Every stored gallery, newest year first.
 *
 * @return array<int, array> Each: race, year, by, url.
 */
function arv_photos_store_get() {
	$stored = get_option( ARV_PHOTOS_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	$out = array();

	foreach ( $stored as $row ) {
		if ( ! is_array( $row ) || empty( $row['race'] ) || empty( $row['url'] ) ) {
			continue;
		}

		$race = function_exists( 'arv_race_display_name' )
			? arv_race_display_name( (string) $row['race'] )
			: (string) $row['race'];
		$year = isset( $row['year'] ) ? (int) $row['year'] : 0;

		$out[] = array(
			// Corrected on read for the same reason the results store does
			// it: the same race is spelled two ways across four years of
			// hand-built pages.
			'race' => $race,
			'year' => $year,
			'by'   => isset( $row['by'] ) ? (string) $row['by'] : '',
			'url'  => (string) $row['url'],
			// When the race actually ran, so the newest one is first. See
			// arv_photos_race_date().
			'iso'  => arv_photos_race_date( $race, $year ),
		);
	}

	usort( $out, 'arv_photos_compare' );

	return $out;
}

/**
 * Newest race first, which is the only order anyone wants a photo page in.
 *
 * Sorted on the real race date, not the year: a year on its own put
 * "Across The Years" (December 31st) at the top of 2026 above races that
 * ran eight months earlier, purely because the list fell back to
 * alphabetical inside a year and A comes first. Recency was invisible,
 * which was the complaint.
 *
 * A gallery with no date sorts to the end of its own year rather than out
 * of it: not knowing the day is not a reason to lose the year.
 *
 * @param array $a
 * @param array $b
 * @return int
 */
function arv_photos_compare( $a, $b ) {
	if ( $a['year'] !== $b['year'] ) {
		return $b['year'] - $a['year'];
	}

	// '' sorts last within the year, which is what strcmp gives against a
	// real date string only if the empty one is handled first.
	if ( ( '' === $a['iso'] ) !== ( '' === $b['iso'] ) ) {
		return ( '' === $a['iso'] ) ? 1 : -1;
	}

	if ( $a['iso'] !== $b['iso'] ) {
		return strcmp( $b['iso'], $a['iso'] );
	}

	return strcasecmp( $a['race'], $b['race'] );
}

/**
 * When a race actually ran, for a race name and a year.
 *
 * Read from the results archive and the race calendar rather than stored
 * on the gallery, because those two already know: the archive is every
 * race that has run, the calendar is every race that will. A gallery is
 * just a link to pictures and has no opinion about the date.
 *
 * Matched on the same key the rest of this file groups by, so "Silverton
 * Alpine" and "Silverton Alpine Marathon" find the same date.
 *
 * @param string $race
 * @param int    $year
 * @return string ISO date, or '' when nothing knows.
 */
function arv_photos_race_date( $race, $year ) {
	if ( ! $year ) {
		return '';
	}

	$dates = arv_photos_race_dates();
	$key   = arv_photos_race_key( $race ) . '|' . $year;

	return isset( $dates[ $key ] ) ? $dates[ $key ] : '';
}

/**
 * Every race date the site knows, keyed by race key and year.
 *
 * Deliberately not memoised in a static, for the same reason
 * arv_films_race_names() is not. A static here caches whatever the two
 * stores happened to hold the first time anything asked, and "the first
 * thing to ask" is not something this function gets to choose: in the
 * test suite it was an earlier render against empty stores, and every
 * gallery came back undated for the rest of the run. This was written
 * with a static and a comment claiming it was safe. It was not.
 *
 * Both stores cache their own reads, so recomputing is string work over a
 * couple of hundred rows rather than queries.
 *
 * @return array<string, string>
 */
function arv_photos_race_dates() {
	$dates = array();

	$sources = array();

	if ( function_exists( 'arv_results_store_get' ) ) {
		$sources[] = arv_results_store_get();
	}

	if ( function_exists( 'arv_race_store_get' ) ) {
		$sources[] = arv_race_store_get();
	}

	foreach ( $sources as $rows ) {
		foreach ( (array) $rows as $row ) {
			if ( empty( $row['name'] ) || empty( $row['iso'] ) ) {
				continue;
			}

			$iso  = (string) $row['iso'];
			$year = (int) substr( $iso, 0, 4 );

			if ( ! $year ) {
				continue;
			}

			$key = arv_photos_race_key( (string) $row['name'] ) . '|' . $year;

			// First source wins. Results are read before the calendar, so a
			// race that has run keeps the date it actually ran on rather
			// than a provisional one still sitting on the calendar.
			if ( ! isset( $dates[ $key ] ) ) {
				$dates[ $key ] = $iso;
			}
		}
	}

	return $dates;
}

/**
 * The years that actually have galleries, newest first.
 *
 * @return array<int, int>
 */
function arv_photos_years( $rows = null ) {
	$rows  = ( null === $rows ) ? arv_photos_store_get() : $rows;
	$years = array();

	foreach ( $rows as $row ) {
		if ( $row['year'] ) {
			$years[ $row['year'] ] = true;
		}
	}

	$years = array_keys( $years );
	rsort( $years );

	return $years;
}

/**
 * Galleries grouped into one entry per race per year.
 *
 * A race is one card even when three photographers shot it, because a
 * visitor is looking for "photos from Coldwater Rumble", not for a
 * particular photographer's take on it. The photographers become badges on
 * the one card rather than three near-identical cards in a row.
 *
 * @param array $rows
 * @return array<int, array> Each: race, year, key, galleries.
 */
function arv_photos_group( $rows ) {
	$grouped = array();

	foreach ( $rows as $row ) {
		$key = $row['year'] . '|' . arv_photos_race_key( $row['race'] );

		if ( ! isset( $grouped[ $key ] ) ) {
			$grouped[ $key ] = array(
				'race'      => $row['race'],
				'year'      => $row['year'],
				'key'       => $key,
				// The real race date, joined from results first and the
				// calendar second, so a card can say "March 14, 2026"
				// rather than just the year it happened in.
				'iso'       => isset( $row['iso'] ) ? (string) $row['iso'] : arv_photos_race_date( $row['race'], $row['year'] ),
				'galleries' => array(),
			);
		}

		$grouped[ $key ]['galleries'][] = array(
			'by'  => $row['by'],
			'url' => $row['url'],
		);
	}

	return array_values( $grouped );
}

/**
 * A race name reduced to something two spellings of it agree on.
 *
 * Defers to the results store's own key where that file is loaded, so the
 * two features group "Silverton Alpine" and "Silverton Alpine Marathon"
 * the same way rather than each having an opinion.
 *
 * @param string $name
 * @return string
 */
function arv_photos_race_key( $name ) {
	if ( function_exists( 'arv_results_race_key' ) ) {
		return arv_results_race_key( $name );
	}

	return trim( preg_replace( '/\s+/', ' ', strtolower( preg_replace( '/[^A-Za-z0-9]+/', ' ', (string) $name ) ) ) );
}

/**
 * Every distinct photographer, alphabetically.
 *
 * @param array $rows
 * @return array<int, string>
 */
function arv_photos_photographers( $rows ) {
	$seen = array();

	foreach ( $rows as $row ) {
		if ( '' !== $row['by'] ) {
			$seen[ $row['by'] ] = true;
		}
	}

	$names = array_keys( $seen );
	sort( $names );

	return $names;
}

/**
 * A gallery's cover picture, read from the gallery itself.
 *
 * Open Graph is the reason outside photographers can sit beside Aravaipa's
 * own galleries and look like they belong. Every host here publishes
 * og:image on a gallery page, not because anyone coordinated it but
 * because they all want their links to look like something when someone
 * shares one. So there is no per-host scraper and no list of special
 * cases: one read of a standard tag, and SmugMug, PassGallery and a
 * photographer's own site all answer it the same way.
 *
 * Cached per URL for a week. A gallery's cover changes about never, and
 * these are other people's servers: this should be the lightest possible
 * guest. A failure is cached for an hour rather than not at all, so a host
 * that is down or blocking does not get re-hit on every page view, and
 * rather than a day so that fixing it shows up the same afternoon.
 *
 * Returns '' when there is no cover to be had. Two of the hosts already
 * linked do not give one up: Goat Factory Media refuses the request
 * outright, and Run 200 Photos publishes no tag. Those get a styled card
 * with no picture rather than a broken image or an invented one, which is
 * the honest version of "close enough".
 *
 * @param string $url
 * @param bool   $fresh Skip the cache.
 * @return string An image URL, or ''.
 */
function arv_photos_cover( $url, $fresh = false ) {
	$url = (string) $url;

	if ( '' === $url ) {
		return '';
	}

	// Hashed rather than the URL itself: an option name is capped at 191
	// characters and some of these carry a long access token.
	$key = 'arv_photo_cover_' . md5( $url );

	if ( ! $fresh ) {
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return ( 'none' === $cached ) ? '' : $cached;
		}
	}

	$response = wp_remote_get(
		$url,
		array(
			// A real browser string. Several of these hosts serve a
			// different page, or nothing at all, to anything that looks
			// automated, and the request being made here is the same one a
			// visitor's own browser makes when they click the link.
			'headers'     => array(
				'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
			),
			'timeout'     => 8,
			'redirection' => 3,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		set_transient( $key, 'none', HOUR_IN_SECONDS );
		return '';
	}

	$cover = arv_photos_og_image( wp_remote_retrieve_body( $response ) );

	if ( '' === $cover ) {
		set_transient( $key, 'none', HOUR_IN_SECONDS );
		return '';
	}

	set_transient( $key, $cover, WEEK_IN_SECONDS );

	return $cover;
}

/**
 * The og:image out of a page's markup.
 *
 * Matched both ways round, property-then-content and content-then-property,
 * because the two orders are both common and a tag is not required to
 * write them in the order anyone expects. Deliberately a regex over the
 * <head> rather than a DOM parse: this is one attribute out of documents
 * that are frequently not well-formed HTML, which is the opposite of the
 * podcast feeds where SimpleXML was the right call.
 *
 * @param string $body
 * @return string
 */
function arv_photos_og_image( $body ) {
	$body = (string) $body;

	// Only the head. A gallery page's body is full of <img> and the odd
	// inline og:image in a share widget, and the first match in the whole
	// document is not reliably the page's own cover.
	$head = ( false !== stripos( $body, '</head>' ) )
		? substr( $body, 0, stripos( $body, '</head>' ) )
		: $body;

	$patterns = array(
		'#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i',
		'#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i',
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $head, $m ) ) {
			$url = html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' );

			// Only ever an absolute http(s) URL. A relative one would need
			// resolving against a host this does not own, and a data: or
			// javascript: value has no business reaching an src attribute.
			if ( preg_match( '#^https?://#i', $url ) ) {
				return $url;
			}
		}
	}

	return '';
}

/**
 * The Photos page: a card per race, with the picture on it.
 *
 * @param array $args heading, intro, year.
 * @return string
 */
function arv_photos_render( $args = array() ) {
	$rows = arv_photos_store_get();

	if ( empty( $rows ) ) {
		return '';
	}

	// "2026 Photo Galleries", per Jamil, and the same shape for every year.
	// A page that pins its own year gets the year in front of it; the
	// all-years index keeps the plain noun.
	$default = 'Photo Galleries';
	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : $default;
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';

	// A year can be pinned by the element, which is what a per-year page
	// uses, or chosen with ?arv_year=, which is what the filter links use.
	//
	// Namespaced, and it has to be: "year" is one of WordPress's own
	// reserved query vars for date archives. Using it meant
	// /photos/?year=2025 was parsed as a date archive, and WordPress's
	// canonical redirect sent the request to /photos-2025/ instead, which
	// is a different page that pins its own year and therefore renders no
	// controls at all. The filter links looked like they were deleting the
	// search box and the year row.
	$pinned = isset( $args['year'] ) ? (int) $args['year'] : 0;
	$wanted = $pinned;

	if ( ! $wanted && isset( $_GET['arv_year'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wanted = (int) $_GET['arv_year']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$years = arv_photos_years( $rows );

	if ( $wanted && ! in_array( $wanted, $years, true ) ) {
		$wanted = 0;
	}

	if ( $wanted ) {
		$rows = array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $wanted ) {
					return $row['year'] === $wanted;
				}
			)
		);
	}

	$cards = arv_photos_group( $rows );

	// A race still to come has a gallery row the moment its photographer is
	// booked, and nothing to show until the day. Those were rendering as a
	// wall of empty placeholders at the top of the current year's page.
	$cards = array_values( array_filter( $cards, 'arv_photos_has_happened' ) );

	$out  = '<section class="arv-photos">';
	$out .= '<div class="arv-photos__inner">';

	if ( '' !== $heading ) {
		// The year leads the heading rather than trailing it behind a
		// colon: "2026 Photo Galleries" reads as a thing, "Photos: 2026"
		// reads as a database field.
		$shown = ( $wanted && $default === $heading )
			? $wanted . ' ' . $heading
			: $heading;

		$out .= '<h2 class="arv-photos__heading">' . esc_html( $shown ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-photos__intro">' . esc_html( $intro ) . '</p>';
	}

	// Only where the page has not pinned a year itself: a /photos-2026/
	// page switching itself to 2024 would contradict its own URL.
	if ( ! $pinned ) {
		$out .= arv_photos_controls( $years, $wanted, arv_photos_photographers( arv_photos_store_get() ) );
	}

	if ( empty( $cards ) ) {
		return $out . '<p class="arv-photos__empty">'
			. esc_html__( 'No galleries for that year yet.', 'aravaipa-elements' )
			. '</p></div></section>';
	}

	$out .= '<ul class="arv-photos__grid" data-arv-photos-grid>';

	foreach ( $cards as $card ) {
		$out .= arv_photos_card( $card );
	}

	$out .= '</ul>';
	$out .= '<p class="arv-photos__count" data-arv-photos-count aria-live="polite"></p>';

	return $out . '</div></section>';
}

/**
 * Search, year and photographer filters.
 *
 * Progressive enhancement over cards already in the HTML, the same
 * contract the Films shelf and the Watch archive use: with no JavaScript
 * the page is still the complete grid, and the year links below are real
 * URLs rather than script state, so a year is shareable and crawlable.
 *
 * @param array $years
 * @param int   $current
 * @param array $photographers
 * @return string
 */
function arv_photos_controls( $years, $current, $photographers ) {
	$out = '<div class="arv-photos__controls">';

	$out .= '<span class="arv-photos__search-field">';
	$out .= '<input class="arv-photos__search-input" type="search" autocomplete="off"'
		. ' placeholder="' . esc_attr__( 'Search races', 'aravaipa-elements' ) . '"'
		. ' aria-label="' . esc_attr__( 'Search races', 'aravaipa-elements' ) . '"'
		. ' data-arv-photos-search />';
	$out .= '</span>';

	if ( count( $photographers ) > 1 ) {
		$out .= '<select class="arv-photos__by" data-arv-photos-by aria-label="'
			. esc_attr__( 'Filter by photographer', 'aravaipa-elements' ) . '">';
		$out .= '<option value="">' . esc_html__( 'Every photographer', 'aravaipa-elements' ) . '</option>';

		foreach ( $photographers as $name ) {
			$out .= '<option value="' . esc_attr( strtolower( $name ) ) . '">'
				. esc_html( $name ) . '</option>';
		}

		$out .= '</select>';
	}

	if ( count( $years ) > 1 ) {
		$out .= '<nav class="arv-photos__years" aria-label="'
			. esc_attr__( 'Filter by year', 'aravaipa-elements' ) . '">';

		$out .= '<a class="arv-photos__year' . ( $current ? '' : ' is-current' ) . '"'
			. ' href="' . esc_url( remove_query_arg( 'arv_year' ) ) . '"'
			. ( $current ? '' : ' aria-current="true"' ) . '>'
			. esc_html__( 'All years', 'aravaipa-elements' ) . '</a>';

		foreach ( $years as $year ) {
			$is = ( $year === $current );
			$out .= '<a class="arv-photos__year' . ( $is ? ' is-current' : '' ) . '"'
				. ' href="' . esc_url( add_query_arg( 'arv_year', $year ) ) . '"'
				. ( $is ? ' aria-current="true"' : '' ) . '>'
				. esc_html( $year ) . '</a>';
		}

		$out .= '</nav>';
	}

	return $out . '</div>';
}

/**
 * Whether a race has actually happened yet.
 *
 * A gallery row exists for a race the moment its photographer is booked,
 * which for a December race can be most of a year before anyone takes a
 * picture. Those rendered as a grid of empty grey placeholders on the
 * current year's page: eight of them at the top of /photos-2026/, one per
 * race still to come, each promising photographs that do not exist.
 *
 * Judged on the race's own date plus a day, the same grace the upcoming
 * broadcast list uses and for the same reason: a race that finished last
 * night should be able to have its galleries up this morning.
 *
 * A race with no date recorded is treated as past. It is almost always an
 * older gallery whose race predates the calendar, and hiding a real
 * archive because a date is missing would be the worse of the two errors.
 *
 * @param array $card
 * @return bool
 */
function arv_photos_has_happened( $card ) {
	if ( empty( $card['iso'] ) ) {
		return true;
	}

	$stamp = strtotime( (string) $card['iso'] );

	if ( ! $stamp ) {
		return true;
	}

	$now = function_exists( 'current_time' ) ? current_time( 'timestamp', true ) : time();

	return ( $stamp + DAY_IN_SECONDS ) <= $now;
}

/**
 * The race date as a person would read it, or the bare year.
 *
 * @param array $card
 * @return string
 */
function arv_photos_card_when( $card ) {
	if ( ! empty( $card['iso'] ) ) {
		$stamp = strtotime( (string) $card['iso'] );

		if ( $stamp ) {
			return gmdate( 'F j, Y', $stamp );
		}
	}

	return $card['year'] ? (string) $card['year'] : '';
}

/**
 * One race's card: the cover, the race, the year, and who shot it.
 *
 * The card links to the first gallery, and every photographer beyond the
 * first is its own link below. A race shot by one photographer is
 * therefore one click, which is the common case, and a race shot by three
 * does not hide two of them.
 *
 * @param array $card
 * @return string
 */
function arv_photos_card( $card ) {
	$galleries = arv_photos_ordered_galleries( $card['galleries'] );
	$primary   = $galleries[0];
	$cover     = arv_photos_cover( $primary['url'] );

	$by = array();

	foreach ( $galleries as $gallery ) {
		if ( '' !== $gallery['by'] ) {
			$by[] = strtolower( $gallery['by'] );
		}
	}

	$out = '<li class="arv-photos__card"'
		. ' data-arv-photos-race="' . esc_attr( strtolower( $card['race'] ) ) . '"'
		. ' data-arv-photos-by="' . esc_attr( implode( '|', $by ) ) . '">';

	$out .= '<a class="arv-photos__link" href="' . esc_url( $primary['url'] ) . '"'
		. ' target="_blank" rel="noopener">';

	if ( '' !== $cover ) {
		$out .= '<img class="arv-photos__cover" src="' . esc_url( $cover ) . '" alt=""'
			. ' loading="lazy" decoding="async" width="480" height="320"'
			// These are other people's servers and none of them promised
			// this URL forever. A dead cover collapses the card to its
			// no-picture state rather than showing a broken image.
			. ' onerror="this.remove()" />';
	} else {
		// No cover to be had: a deliberate panel rather than a gap, so the
		// card still reads as a card beside the ones that have pictures.
		$out .= '<span class="arv-photos__cover arv-photos__cover--none" aria-hidden="true">'
			. '<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5">'
			. '<rect x="3" y="5" width="18" height="14" rx="2"/>'
			. '<circle cx="8.5" cy="10.5" r="1.5"/>'
			. '<path d="M21 15l-5-5L5 19"/>'
			. '</svg></span>';
	}

	$out .= '<span class="arv-photos__body">';
	$out .= '<span class="arv-photos__race">' . esc_html( $card['race'] ) . '</span>';

	$when = arv_photos_card_when( $card );

	if ( '' !== $when ) {
		$out .= '<span class="arv-photos__year-tag">' . esc_html( $when ) . '</span>';
	}

	$out .= '</span></a>';

	// Every photographer is the same badge, including the primary one.
	// Rendering the first as plain text inside the card's own link and
	// everyone after as an outlined badge was two treatments of the same
	// fact, with nothing on the card explaining the difference: one race
	// looked like it had a name and a link, the next looked like it had a
	// name and two unrelated buttons. Outside the card's own link for the
	// same reason the Films race badge is: an <a> inside an <a> is invalid
	// and browsers resolve it by silently closing the outer one.
	$out .= '<span class="arv-photos__photographers">';

	foreach ( $galleries as $gallery ) {
		$label = ( '' !== $gallery['by'] ) ? $gallery['by'] : __( 'Gallery', 'aravaipa-elements' );
		$out  .= '<a class="arv-photos__by-badge" href="' . esc_url( $gallery['url'] ) . '"'
			. ' target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
	}

	$out .= '</span>';

	return $out . '</li>';
}

/**
 * A card's galleries, Aravaipa's own first.
 *
 * "First" used to just be whatever order the import happened to produce,
 * which made the cover photo and the un-badged name a coin flip between
 * Aravaipa's own gallery and whichever outside photographer's row landed
 * first. Putting Aravaipa's own gallery first when there is one is a real
 * rule instead: it is the gallery this site can vouch for, so it is the
 * one the cover image and the primary link point at.
 *
 * @param array $galleries
 * @return array
 */
function arv_photos_ordered_galleries( $galleries ) {
	// Index carried through as the tiebreaker rather than trusting usort()
	// to be stable: PHP only guarantees that from 8.0, and this plugin's
	// own header supports 7.4. Without it, two outside photographers on
	// the same card could swap places between one render and the next for
	// no reason a visitor could see, which is a worse bug than the one
	// this function exists to fix.
	$indexed = array();

	foreach ( $galleries as $i => $gallery ) {
		$indexed[] = array( $gallery, $i );
	}

	usort(
		$indexed,
		function ( $a, $b ) {
			$a_own = ( false !== stripos( $a[0]['by'], 'aravaipa' ) );
			$b_own = ( false !== stripos( $b[0]['by'], 'aravaipa' ) );

			if ( $a_own !== $b_own ) {
				return $a_own ? -1 : 1;
			}

			return $a[1] - $b[1];
		}
	);

	return array_column( $indexed, 0 );
}

/**
 * [arv_photos] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_photos_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Photos',
			'intro'   => '',
			'year'    => '',
		),
		$atts,
		'arv_photos'
	);

	return arv_photos_render( $atts );
}
add_shortcode( 'arv_photos', 'arv_photos_shortcode' );

/**
 * Replace every stored gallery.
 *
 * @param array $rows
 * @return int How many were kept.
 */
function arv_photos_store_set( $rows ) {
	$clean = array();

	foreach ( (array) $rows as $row ) {
		if ( ! is_array( $row ) || empty( $row['race'] ) || empty( $row['url'] ) ) {
			continue;
		}

		$url = esc_url_raw( (string) $row['url'] );

		// Only ever an http(s) link out. These come from parsing pages, and
		// a javascript: or data: value has no business reaching an href.
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			continue;
		}

		$clean[] = array(
			'race' => sanitize_text_field( (string) $row['race'] ),
			'year' => isset( $row['year'] ) ? (int) $row['year'] : 0,
			'by'   => isset( $row['by'] ) ? sanitize_text_field( (string) $row['by'] ) : '',
			'url'  => $url,
		);
	}

	update_option( ARV_PHOTOS_OPTION, $clean, false );

	return count( $clean );
}

/**
 * Write route for the importer.
 *
 * Same edit_posts scoping as the results and race importers, for the same
 * reason: reachable by the Editor-scoped Application Password the scripts
 * run as, and by nothing with more reach than that.
 */
function arv_photos_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/photos/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_photos_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_photos_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/photos/import
 *
 * Guarded exactly the way the results importer is, and for the same
 * reason: this replaces everything, so a parse that half-failed and found
 * eleven galleries would otherwise wipe two hundred.
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_photos_rest_set( $request ) {
	$body  = $request->get_json_params();
	$rows  = isset( $body['rows'] ) && is_array( $body['rows'] ) ? $body['rows'] : array();
	$dry   = ! empty( $body['dry_run'] );
	$force = ! empty( $body['force'] );

	$current = count( arv_photos_store_get() );
	$valid   = 0;

	foreach ( $rows as $row ) {
		if ( is_array( $row ) && ! empty( $row['race'] ) && ! empty( $row['url'] ) ) {
			$valid++;
		}
	}

	if ( ! $force && $current > 0 && $valid < ( $current * 0.8 ) ) {
		return array(
			'status'  => 'refused',
			'reason'  => 'would drop more than 20% of stored galleries',
			'current' => $current,
			'valid'   => $valid,
		);
	}

	if ( $dry ) {
		return array(
			'status'  => 'dry_run',
			'current' => $current,
			'valid'   => $valid,
		);
	}

	$stored = arv_photos_store_set( $rows );

	return array(
		'status'   => 'ok',
		'stored'   => $stored,
		'previous' => $current,
	);
}
