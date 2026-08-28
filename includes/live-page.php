<?php
/**
 * A branded, indexable page for one race's live results.
 *
 * live.aravaiparunning.com is a single-page app that serves 1.7KB of HTML
 * whose entire body text is "Aravaipa Live doesn't work properly without
 * JavaScript enabled". Every result it holds, four hundred and thirty-odd
 * events back to 2020, is invisible to search. It has no robots.txt, no
 * sitemap, no meta description, no Open Graph tags, and every event lives
 * behind a hash route that crawlers do not index as its own page. Nobody can
 * search for "Javelina Jundred 2025 results" and land on it, and a link
 * pasted into a group chat produces no preview.
 *
 * This page fixes that, but not by embedding. An iframe's contents are not
 * attributed to the page around it: framing the board would give the visitor
 * Aravaipa's own header, footer and navigation, which is worth having, and
 * would give a search engine exactly nothing, which is the part that
 * actually matters. So the frame is only half of this.
 *
 * The other half is that everything a search engine should see is rendered
 * here, in HTML, by the server: the race, the date, the place, how many
 * finished, and who won every distance in every division. That content comes
 * from the stats store, which already holds it for 438 events, so this is a
 * new view of data the site has rather than a new pipeline.
 *
 * Editions are selected with ?edition=, which makes each running its own URL
 * with its own title, its own description and its own winners. That is also
 * the answer to a second problem: the board itself offers no way at all to
 * move between years, so a runner looking for their 2024 time has nowhere to
 * click. Here the years are ordinary links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_LIVE_BASE', 'https://live.aravaiparunning.com/#/' );

/**
 * The query variable that selects an edition.
 *
 * Deliberately not "year". That is one of WordPress's own public query vars,
 * the one that drives date archives, and setting it on a page rewrites the
 * main query underneath us: ?year=2025 on this page 301-redirected to the
 * race's own page and ?year=2024 was a flat 404, while ?yr= and ?foo= on the
 * same URL both returned 200. The collision is silent and total, and it took
 * a live request to see it.
 */
define( 'ARV_LIVE_YEAR_VAR', 'edition' );

/**
 * Every stored edition of the race a given board slug belongs to, newest
 * first.
 *
 * Grouped through the results store rather than by chopping the year off the
 * slug, because the board's slugs are not stable across years: Kilkenny
 * Ridge is "kilkenny_ridge-2025" and then "killeny_ridge-2026", Westminster
 * is "westminster_trail-2025" and then "westminster_trail_race-2026". String
 * surgery on those would silently split a race in half. The results store
 * already groups editions by race name, and that is the grouping the archive
 * page uses, so both agree by construction.
 *
 * @param string $slug Board slug of any edition.
 * @return array<int, array> Result rows, newest first.
 */
function arv_live_editions( $slug ) {
	$slug = trim( (string) $slug );

	if ( '' === $slug ) {
		return array();
	}

	foreach ( arv_results_store_get() as $row ) {
		if ( arv_live_store_slug( $row['live'] ) === $slug ) {
			return arv_live_editions_by_name( $row['name'] );
		}
	}

	return array();
}

/**
 * Every stored edition of a race, by name, newest first.
 *
 * Split out from the slug lookup above because a slug can only find its own
 * siblings once it is itself in the store, and the edition a live page is
 * usually showing is the one that has not been scraped yet. Resolving the
 * name first, from whichever store knows it, is what lets this year's page
 * list last year's.
 *
 * @param string $name
 * @return array<int, array>
 */
function arv_live_editions_by_name( $name ) {
	$name = trim( (string) $name );

	if ( '' === $name ) {
		return array();
	}

	$key  = arv_results_race_key( $name );
	$mine = array();

	foreach ( arv_results_store_get() as $row ) {
		if ( arv_results_race_key( $row['name'] ) === $key ) {
			$mine[] = $row;
		}
	}

	// arv_results_store_get() already sorts newest first.
	return $mine;
}

/**
 * The edition a request is asking for.
 *
 * ?year= wins when it names an edition that exists. Anything else, including
 * a year the race did not run, falls back to the newest rather than to an
 * empty page: a stale link from three years ago should land on this year's
 * race, which is what the visitor almost certainly wants.
 *
 * @param array $editions
 * @param string $year
 * @return array|null
 */
function arv_live_pick_edition( $editions, $year ) {
	if ( empty( $editions ) ) {
		return null;
	}

	$year = preg_replace( '/\D/', '', (string) $year );

	if ( '' !== $year ) {
		foreach ( $editions as $edition ) {
			if ( substr( $edition['iso'], 0, 4 ) === $year ) {
				return $edition;
			}
		}
	}

	return $editions[0];
}

/**
 * The race on the calendar whose live URL carries this board slug.
 *
 * The link that was missing. The results store only learns about a running
 * once it has happened and been scraped onto a results page, so on the day
 * of the race, and every day before it, the current edition is not in there
 * and the page could not find its own name. The race store has known all
 * along: every race on the calendar carries its live URL, which is what the
 * race week block on the archive already joins on.
 *
 * @param string $slug
 * @return array|null
 */
function arv_live_race_by_slug( $slug ) {
	$slug = trim( (string) $slug );

	if ( '' === $slug || ! function_exists( 'arv_race_store_get' ) ) {
		return null;
	}

	foreach ( arv_race_store_get() as $race ) {
		if ( arv_live_store_slug( $race['live'] ) === $slug ) {
			return $race;
		}
	}

	return null;
}

/**
 * Every edition of this race, newest first, from both stores.
 *
 * The results store holds the runnings that have happened. The race store
 * holds the one that has not yet, which is precisely the edition a live page
 * is usually showing. Neither alone can build a year switcher that includes
 * both this year and last.
 *
 * @param string $slug
 * @return array<int, array>
 */
function arv_live_all_editions( $slug ) {
	// The name comes from whichever store knows this slug. The calendar
	// knows the running that has not happened yet; the archive knows the
	// ones that have. Going through either alone loses half the years.
	$current = arv_live_race_by_slug( $slug );
	$name    = '';

	if ( null !== $current ) {
		$name = $current['name'];
	} else {
		foreach ( arv_results_store_get() as $row ) {
			if ( arv_live_store_slug( $row['live'] ) === $slug ) {
				$name = $row['name'];
				break;
			}
		}
	}

	if ( '' === $name ) {
		return array();
	}

	$editions = arv_live_editions_by_name( $name );

	// The calendar's entry for this race, whichever year's page we arrived
	// on. Matched by name rather than by the slug we came in with, so the
	// 2025 page can still offer a link forward to 2026.
	$upcoming = arv_live_race_meta( $name );

	if ( null === $upcoming || '' === trim( (string) $upcoming['live'] ) ) {
		return $editions;
	}

	$upcoming_slug = arv_live_store_slug( $upcoming['live'] );

	// Already scraped onto a results page: that row is the better record,
	// since it carries the result links as well as the date.
	foreach ( $editions as $edition ) {
		if ( arv_live_store_slug( $edition['live'] ) === $upcoming_slug ) {
			return $editions;
		}
	}

	$editions[] = array(
		'name'         => $upcoming['name'],
		'iso'          => $upcoming['iso'],
		'display'      => $upcoming['display'],
		'live'         => $upcoming['live'],
		'ultrasignup'  => '',
		'ultrarunning' => '',
	);

	usort(
		$editions,
		function ( $a, $b ) {
			return ( $a['iso'] < $b['iso'] ) ? 1 : -1;
		}
	);

	return $editions;
}

/**
 * Where this edition is in its life: soon, live or done.
 *
 * The same three states the race week block uses, computed the same way and
 * against the same sources, so a race reads identically on the archive and
 * on its own page. The board's clock wins where it has one; an override we
 * hold beats the board's cutoff, because the board has been wrong about
 * that.
 *
 * @param string     $name
 * @param string     $iso
 * @param array|null $board
 * @return string
 */
function arv_live_state( $name, $iso, $board ) {
	$now = arv_results_now();

	if ( null !== $board && '' !== $board['start'] ) {
		$start_ts  = strtotime( $board['start'] );
		$cutoff_ts = function_exists( 'arv_race_cutoff_for' )
			? arv_race_cutoff_for( $name, $board )
			: ( ( '' !== $board['cutoff'] ) ? strtotime( $board['cutoff'] ) : 0 );

		if ( $cutoff_ts && $now >= $cutoff_ts ) {
			return 'done';
		}

		return ( $now >= $start_ts ) ? 'live' : 'soon';
	}

	// No board clock, so the date is all there is. A race is over once its
	// day has passed and not before.
	$day = strtotime( $iso . ' 00:00:00' );

	if ( ! $day ) {
		return 'soon';
	}

	return ( $now > ( $day + DAY_IN_SECONDS ) ) ? 'done' : 'soon';
}

/**
 * The race post matching a results row, for its venue and location.
 *
 * The results store knows a race's name, date and where to read its results,
 * and nothing about where it is held. The race store knows that, but only
 * for races still on the calendar, so this returns null for a race that has
 * been run and dropped, and the page simply says less.
 *
 * @param string $name
 * @return array|null
 */
function arv_live_race_meta( $name ) {
	$key = arv_results_race_key( $name );

	foreach ( arv_race_store_get() as $race ) {
		if ( arv_results_race_key( $race['name'] ) === $key ) {
			return $race;
		}
	}

	return null;
}

/**
 * The whole page for one race.
 *
 * @param array $args slug, height, heading.
 * @return string
 */
function arv_live_page_render( $args = array() ) {
	$slug = isset( $args['slug'] ) ? trim( (string) $args['slug'] ) : '';

	if ( '' === $slug ) {
		return '';
	}

	$editions = arv_live_all_editions( $slug );

	// A slug neither store has ever seen still gets a frame. The board is the
	// source of truth for what it is timing, and a race added this week
	// should not need anything else to have caught up first.
	$requested = isset( $args['year'] )
		? $args['year']
		: ( isset( $_GET[ ARV_LIVE_YEAR_VAR ] ) ? wp_unslash( $_GET[ ARV_LIVE_YEAR_VAR ] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edition   = arv_live_pick_edition( $editions, $requested );

	$name = $edition ? $edition['name'] : '';
	$show = $edition ? arv_live_store_slug( $edition['live'] ) : $slug;

	if ( '' === $show ) {
		$show = $slug;
	}

	// Falls back to the calendar when the picked edition has no name of its
	// own, which is every race that has not been scraped yet.
	if ( '' === $name ) {
		$current = arv_live_race_by_slug( $show );
		$name    = $current ? $current['name'] : '';
	}

	$height = isset( $args['height'] ) ? (int) $args['height'] : 780;
	$height = max( 400, min( 2000, $height ) );

	$meta  = '' !== $name ? arv_live_race_meta( $name ) : null;
	$stats = arv_stats_store_find( ARV_LIVE_BASE . $show );

	$out = '<section class="arv-live" aria-label="' . esc_attr__( 'Live results', 'aravaipa-elements' ) . '">';

	$out .= arv_live_bar( $name, $edition, $meta, $editions, $show );
	$out .= arv_live_frame( $show, $height, $name );
	$out .= arv_live_report( $stats, $edition, $name );

	$out .= '</section>';

	return $out;
}

/**
 * The bar above the board: what race, when, and how long until it starts.
 *
 * Dark, and sitting directly on top of the frame with no gap, because the
 * board it introduces is a dark panel and a white heading band floating
 * above it read as two unrelated things stacked rather than one component.
 *
 * The clock is the race week block's clock, markup and all: same three
 * states, same attributes, same script already loaded on every page. A race
 * counts down to its start, counts up once it is running, and reads
 * Completed after its cutoff, and it does that identically here and on the
 * archive because it is the same code rather than a second implementation
 * that will drift.
 *
 * @param string     $name
 * @param array|null $edition
 * @param array|null $meta
 * @param array      $editions
 * @param string     $show Board slug being shown.
 * @return string
 */
function arv_live_bar( $name, $edition, $meta, $editions, $show ) {
	$heading = '' !== $name ? $name : __( 'Live Results', 'aravaipa-elements' );
	$year    = $edition ? substr( $edition['iso'], 0, 4 ) : '';

	$board = function_exists( 'arv_live_store_find' )
		? arv_live_store_find( ARV_LIVE_BASE . $show )
		: null;

	$out = '<div class="arv-live__bar" data-arv-results-row>';
	$out .= '<div class="arv-live__bar-inner">';

	$out .= '<div class="arv-live__ident">';
	$out .= '<h2 class="arv-live__title">' . esc_html( $heading );

	if ( '' !== $year ) {
		$out .= ' <span class="arv-live__year">' . esc_html( $year ) . '</span>';
	}

	$out .= '</h2>';

	$bits = array();

	if ( $edition ) {
		$bits[] = arv_results_edition_label( $edition );
	}

	if ( $meta && ! empty( $meta['location'] ) ) {
		$bits[] = $meta['location'];
	}

	if ( ! empty( $bits ) ) {
		$out .= '<p class="arv-live__meta">' . esc_html( implode( ' · ', $bits ) ) . '</p>';
	}

	$out .= '</div>';

	// The clock only means anything for an edition we can date. A year picked
	// out of the archive is history, and counting down to it would be absurd.
	if ( $edition && '' !== $name ) {
		$race = array(
			'name'  => $name,
			'iso'   => $edition['iso'],
			'board' => $board,
			'state' => arv_live_state( $name, $edition['iso'], $board ),
		);

		$out .= '<div class="arv-live__clock">';
		$out .= arv_results_week_live_badge( $race );
		$out .= arv_results_week_status( $race );
		$out .= '</div>';
	}

	$out .= arv_live_years( $editions, $show );
	$out .= '</div></div>';

	return $out;
}

/**
 * The year switcher.
 *
 * Ordinary links, not a dropdown and not a script. Each one is a URL a
 * search engine can follow to a page with different content on it, which is
 * the entire point: the board has no way to move between years at all, so
 * every past running is currently unreachable except by knowing its slug.
 *
 * @param array $editions
 * @param string $current Board slug being shown.
 * @return string
 */
function arv_live_years( $editions, $current ) {
	if ( count( $editions ) < 2 ) {
		return '';
	}

	$out = '<nav class="arv-live__years" aria-label="' . esc_attr__( 'Other years', 'aravaipa-elements' ) . '">';

	foreach ( $editions as $edition ) {
		$year = substr( $edition['iso'], 0, 4 );
		$slug = arv_live_store_slug( $edition['live'] );

		if ( $slug === $current ) {
			$out .= '<span class="arv-live__year-link is-current" aria-current="page">'
				. esc_html( $year ) . '</span>';
			continue;
		}

		$out .= '<a class="arv-live__year-link" href="'
			. esc_url( arv_live_edition_url( $slug, $year ) ) . '">'
			. esc_html( $year ) . '</a>';
	}

	return $out . '</nav>';
}

/**
 * Where a given edition lives.
 *
 * A page of its own if one exists, which is the better shape by a distance:
 * a real path is what gets indexed, shared and linked, and it sidesteps
 * query variables entirely. Falls back to this page with the edition
 * parameter, so a race whose older years have no page yet still switches.
 *
 * @param string $slug Board slug of the edition being linked to.
 * @param string $year
 * @return string
 */
function arv_live_edition_url( $slug, $year ) {
	$page = arv_live_page_for_slug( $slug );

	if ( '' !== $page ) {
		return $page;
	}

	return add_query_arg( ARV_LIVE_YEAR_VAR, $year, arv_live_self_url() );
}

/**
 * The permalink of the page whose live slug is this one, or ''.
 *
 * Looked up by the same meta the SEO layer reads, so a per-year page is
 * discovered by being what it says it is rather than by a naming convention
 * anyone has to remember.
 *
 * @param string $slug
 * @return string
 */
function arv_live_page_for_slug( $slug ) {
	$slug = trim( (string) $slug );

	if ( '' === $slug || ! function_exists( 'get_posts' ) ) {
		return '';
	}

	$found = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_key'         => arv_live_meta_key(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	return empty( $found ) ? '' : (string) get_permalink( $found[0] );
}

/**
 * This page's own URL, without an edition already on it.
 *
 * Built from the permalink rather than from REQUEST_URI so the links are
 * canonical and do not carry along whatever tracking parameters the visitor
 * happened to arrive with.
 *
 * @return string
 */
function arv_live_self_url() {
	$id = get_queried_object_id();

	return $id ? get_permalink( $id ) : home_url( '/' );
}

/**
 * The board itself, framed.
 *
 * Sandboxed to the two things it actually needs. The board is first-party,
 * so this is not a trust boundary so much as a statement of what a results
 * table is allowed to do inside someone else's page: run its own scripts,
 * talk to its own origin, and nothing else. No top-level navigation, so it
 * can never redirect the page out from under a visitor.
 *
 * The link underneath is not a courtesy. An iframe is invisible to a crawler
 * and unreachable to some assistive technology, so there has to be a real
 * anchor to the real board, and it doubles as the escape hatch when the
 * frame is the wrong shape on someone's phone.
 *
 * @param string $slug
 * @param int    $height
 * @param string $name
 * @return string
 */
function arv_live_frame( $slug, $height, $name ) {
	$url = ARV_LIVE_BASE . $slug;

	/* translators: %s is a race name. */
	$title = '' !== $name
		? sprintf( __( 'Live results for %s', 'aravaipa-elements' ), $name )
		: __( 'Live results', 'aravaipa-elements' );

	$out = '<div class="arv-live__frame">';
	$out .= '<iframe class="arv-live__iframe" src="' . esc_url( $url ) . '"'
		. ' title="' . esc_attr( $title ) . '"'
		. ' style="height:' . (int) $height . 'px"'
		. ' loading="lazy"'
		. ' referrerpolicy="no-referrer-when-downgrade"'
		. ' sandbox="allow-scripts allow-same-origin allow-popups"></iframe>';
	$out .= '</div>';

	$out .= '<p class="arv-live__open"><a class="arv-live__open-link" href="' . esc_url( $url ) . '"'
		. ' target="_blank" rel="noopener">'
		. esc_html__( 'Open full live results', 'aravaipa-elements' ) . '</a></p>';

	return $out;
}

/**
 * The part a search engine can actually read.
 *
 * Everything above this is either chrome or an iframe, and an iframe
 * contributes nothing to the page it sits in. This is the page's content:
 * the finisher count and every distance's winners, in real HTML.
 *
 * Silent for a race that has not been run, where there is nothing to report
 * and "0 finishers" would be worse than saying nothing.
 *
 * @param array|null $stats
 * @param array|null $edition
 * @param string     $name
 * @return string
 */
function arv_live_report( $stats, $edition, $name ) {
	if ( null === $stats || empty( $stats['finishers'] ) ) {
		return '';
	}

	$year      = $edition ? substr( $edition['iso'], 0, 4 ) : '';
	$finishers = (int) $stats['finishers'];

	$out = '<section class="arv-live__report">';

	$out .= '<h3 class="arv-live__report-title">'
		. esc_html(
			'' !== $year
				/* translators: %s is a year. */
				? sprintf( __( '%s results', 'aravaipa-elements' ), $year )
				: __( 'Results', 'aravaipa-elements' )
		)
		. '</h3>';

	$out .= '<p class="arv-live__finishers">'
		. esc_html(
			sprintf(
				/* translators: %s is a formatted count of finishers. */
				_n( '%s finisher', '%s finishers', $finishers, 'aravaipa-elements' ),
				number_format_i18n( $finishers )
			)
		)
		. '</p>';

	$winners = isset( $stats['winners'] ) ? $stats['winners'] : array();

	if ( ! empty( $winners ) ) {
		$divisions = arv_stats_divisions_present( $winners );

		$out .= '<table class="arv-live__winners"><caption class="arv-results__sr">'
			. esc_html(
				'' !== $name
					/* translators: 1: race name, 2: year. */
					? sprintf( __( 'Winners of %1$s %2$s', 'aravaipa-elements' ), $name, $year )
					: __( 'Winners', 'aravaipa-elements' )
			)
			. '</caption><thead><tr><th scope="col">'
			. esc_html__( 'Distance', 'aravaipa-elements' ) . '</th>';

		foreach ( $divisions as $division ) {
			$out .= '<th scope="col">' . esc_html( arv_results_division_label( $division ) ) . '</th>';
		}

		$out .= '</tr></thead><tbody>';

		foreach ( $winners as $row ) {
			$out .= '<tr><th scope="row">'
				. esc_html( arv_results_distance_label( $row['distance'] ) ) . '</th>';

			foreach ( $divisions as $division ) {
				if ( ! isset( $row[ $division ] ) ) {
					$out .= '<td></td>';
					continue;
				}

				$out .= '<td><span class="arv-results__winner-name">'
					. esc_html( $row[ $division ]['name'] ) . '</span> '
					. '<span class="arv-results__winner-time">'
					. esc_html( $row[ $division ]['time'] ) . '</span></td>';
			}

			$out .= '</tr>';
		}

		$out .= '</tbody></table>';
	}

	return $out . '</section>';
}

/**
 * [arv_live slug="black_bear-2026"]
 *
 * A shortcode as well as a Cornerstone element, because the eventual shape
 * of this is one page per race rather than one page. Eighty-odd races times
 * however many years they have run is not a set anyone is going to build by
 * hand in a page builder, so there has to be a way to create these
 * programmatically. The element is for placing one by eye; the shortcode is
 * for making them in bulk.
 *
 * @param array $atts
 * @return string
 */
function arv_live_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'slug'   => '',
			'height' => 780,
			'year'   => '',
		),
		$atts,
		'arv_live'
	);

	return arv_live_page_render( $atts );
}
add_shortcode( 'arv_live', 'arv_live_shortcode' );

/**
 * Which race a WP page is showing live results for.
 *
 * Kept on the page as its own piece of meta rather than found by scanning
 * the page's content for the shortcode. A race page finds itself by matching
 * the current URL against the race store, which works because the race
 * store already knows its own URL; a live page has no equivalent store to
 * match against; it is an ordinary WP Page with a shortcode placed inside
 * whatever else an editor put on it, and parsing that back out on every
 * request to recover one attribute is more moving parts than writing the one
 * attribute down once, when the page is created.
 *
 * @return string
 */
function arv_live_meta_key() {
	return '_arv_live_slug';
}

/**
 * Register the meta so it is settable through the REST API, which is how
 * the eventual bulk page-creation script sets it: create the page, pass
 * meta._arv_live_slug in the same request.
 */
function arv_live_register_meta() {
	register_post_meta(
		'page',
		arv_live_meta_key(),
		array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			// A leading underscore makes WordPress treat a meta key as
			// protected, and register_post_meta's own default REST
			// permission check refuses to write a protected key without an
			// explicit auth_callback, no matter what show_in_rest says. That
			// silently made the bulk page-creation path this key exists for
			// impossible: the exact request the docblock above describes,
			// meta._arv_live_slug in a page-creation call, comes back 403.
			// edit_posts, not edit_post_meta with no callback, because this
			// is written once at creation by the same editor-scoped account
			// that creates the page, not something that needs a finer check
			// per post.
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'arv_live_register_meta' );

/**
 * Everything the SEO layer needs about the live page being requested, or
 * null when the current request is not one.
 *
 * Built once and passed to the title, description, Open Graph and schema
 * builders below, so all four agree with each other by construction: they
 * are reading the same edition and the same stats rather than each
 * resolving ?year= or the board slug on its own and risking a mismatch
 * between what the title says and what the description says.
 *
 * @return array|null slug, editions, edition, name, show, meta, stats, url.
 */
function arv_live_seo_context() {
	if ( ! is_singular() ) {
		return null;
	}

	$id = get_queried_object_id();

	if ( ! $id ) {
		return null;
	}

	$slug = get_post_meta( $id, arv_live_meta_key(), true );
	$slug = trim( (string) $slug );

	if ( '' === $slug ) {
		return null;
	}

	$editions  = arv_live_editions( $slug );
	$requested = isset( $_GET[ ARV_LIVE_YEAR_VAR ] ) ? wp_unslash( $_GET[ ARV_LIVE_YEAR_VAR ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edition   = arv_live_pick_edition( $editions, $requested );
	$name      = $edition ? $edition['name'] : '';
	$show      = $edition ? arv_live_store_slug( $edition['live'] ) : $slug;

	if ( '' === $show ) {
		$show = $slug;
	}

	return array(
		'slug'     => $slug,
		'editions' => $editions,
		'edition'  => $edition,
		'name'     => $name,
		'show'     => $show,
		'meta'     => '' !== $name ? arv_live_race_meta( $name ) : null,
		'stats'    => arv_stats_store_find( ARV_LIVE_BASE . $show ),
		'url'      => get_permalink( $id ),
	);
}

/**
 * The <title> for a live page.
 *
 * The site name is not part of this: WordPress appends it itself from the
 * "site" part of document_title_parts, the same way it does for every other
 * page, so adding it here would print it twice.
 *
 * @param array $ctx
 * @return string
 */
function arv_live_seo_title( $ctx ) {
	if ( empty( $ctx['name'] ) ) {
		return '';
	}

	$bits = array( $ctx['name'] );

	if ( $ctx['edition'] ) {
		$bits[] = substr( $ctx['edition']['iso'], 0, 4 );
	}

	$bits[] = ( ! empty( $ctx['stats']['finishers'] ) )
		? __( 'Results', 'aravaipa-elements' )
		: __( 'Live Results', 'aravaipa-elements' );

	return implode( ' ', $bits );
}

/**
 * The meta description, and the same text Open Graph and Twitter cards use.
 *
 * Genuinely different content per page rather than a template filled in with
 * a name, which is the distinction that makes this worth having at all: a
 * finisher count and a winner's name are facts about this specific running
 * of this specific race, not filler restating the title in sentence form.
 *
 * Two shapes, because there are two real states. A race with results says
 * how many finished and who won; a race that has not run yet says when and
 * where, which is the useful question someone searching before race day is
 * actually asking.
 *
 * @param array $ctx
 * @return string
 */
function arv_live_seo_description( $ctx ) {
	if ( empty( $ctx['name'] ) ) {
		return '';
	}

	$year  = $ctx['edition'] ? substr( $ctx['edition']['iso'], 0, 4 ) : '';
	$stats = $ctx['stats'];

	if ( $stats && ! empty( $stats['finishers'] ) ) {
		$bits = array(
			sprintf(
				/* translators: 1: formatted finisher count, 2: race name, 3: year. */
				__( '%1$s finishers at %2$s%3$s.', 'aravaipa-elements' ),
				number_format_i18n( (int) $stats['finishers'] ),
				$ctx['name'],
				'' !== $year ? ' ' . $year : ''
			),
		);

		if ( ! empty( $stats['headline'] ) && ! empty( $stats['winners'][0] ) ) {
			$names = array();

			foreach ( arv_stats_divisions() as $division ) {
				if ( isset( $stats['winners'][0][ $division ] ) ) {
					$names[] = $stats['winners'][0][ $division ]['name'];
				}
			}

			if ( ! empty( $names ) ) {
				$bits[] = sprintf(
					/* translators: %s is a comma-separated list of winner names. */
					__( 'Winners: %s.', 'aravaipa-elements' ),
					implode( ', ', $names )
				);
			}
		}

		return implode( ' ', $bits );
	}

	$when = $ctx['edition'] ? arv_results_edition_label( $ctx['edition'] ) : '';

	$bits = array(
		trim(
			sprintf(
				/* translators: 1: race name, 2: date, blank when unknown. */
				__( 'Live results for %1$s%2$s.', 'aravaipa-elements' ),
				$ctx['name'],
				'' !== $when ? ', ' . $when : ''
			)
		),
	);

	$where = $ctx['meta'] && ! empty( $ctx['meta']['location'] ) ? $ctx['meta']['location'] : '';

	if ( '' !== $where ) {
		$bits[] = sprintf(
			/* translators: %s is a city and state. */
			__( 'Held in %s.', 'aravaipa-elements' ),
			$where
		);
	}

	return implode( ' ', $bits );
}

/**
 * A SportsEvent for the edition being shown, or an empty array when there is
 * not enough to say anything valid.
 *
 * Built through arv_upcoming_races_event_schema(), the same builder the
 * calendar and the race pages use, rather than a second hand-rolled Event
 * shape: one function knows what a valid SportsEvent looks like for this
 * site, and a live page's edition is a race like any other race, just one
 * that has usually already happened.
 *
 * @param array $ctx
 * @return array
 */
function arv_live_seo_event( $ctx ) {
	if ( empty( $ctx['name'] ) || empty( $ctx['edition'] ) ) {
		return array();
	}

	$meta = $ctx['meta'];

	$race = array(
		'name'      => $ctx['name'],
		'iso'       => $ctx['edition']['iso'],
		'end'       => '',
		'distances' => '',
		'venue'     => $meta ? $meta['venue'] : '',
		'location'  => $meta ? $meta['location'] : '',
		'image'     => $meta ? $meta['image'] : '',
		'page'      => isset( $ctx['url'] ) ? $ctx['url'] : '',
		'register'  => '',
	);

	$finished = ! empty( $ctx['stats']['finishers'] );

	// 'upcoming' and 'closed' both leave a race with no register link
	// carrying no offer, which this always is here; the phase only changes
	// whether the schema is honest about the race being over.
	$event = arv_upcoming_races_event_schema( $race, $finished ? 'closed' : 'upcoming' );

	if ( $finished ) {
		$event['eventStatus'] = 'https://schema.org/EventCompleted';
	}

	$description = arv_live_seo_description( $ctx );

	if ( '' !== $description ) {
		// Overrides the distances-based description the shared builder would
		// otherwise write, which would be empty here anyway since this race
		// array carries no distances: a live page's description is about
		// what happened, not what is on offer.
		$event['description'] = $description;
	}

	return $event;
}
