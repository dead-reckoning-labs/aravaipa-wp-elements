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
 * Editions are selected with ?year=, which makes each running its own URL
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

	$rows = arv_results_store_get();
	$name = '';

	foreach ( $rows as $row ) {
		if ( arv_live_store_slug( $row['live'] ) === $slug ) {
			$name = $row['name'];
			break;
		}
	}

	if ( '' === $name ) {
		return array();
	}

	$key  = arv_results_race_key( $name );
	$mine = array();

	foreach ( $rows as $row ) {
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

	$editions = arv_live_editions( $slug );

	// A slug the results store has never seen still gets a frame. The board
	// is the source of truth for what is being timed right now, and a race
	// added this week should not need the archive scraper to have run before
	// its live page works.
	$requested = isset( $args['year'] ) ? $args['year'] : ( isset( $_GET['year'] ) ? wp_unslash( $_GET['year'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edition   = arv_live_pick_edition( $editions, $requested );

	$name = $edition ? $edition['name'] : '';
	$show = $edition ? arv_live_store_slug( $edition['live'] ) : $slug;

	if ( '' === $show ) {
		$show = $slug;
	}

	$height = isset( $args['height'] ) ? (int) $args['height'] : 780;
	$height = max( 400, min( 2000, $height ) );

	$meta  = '' !== $name ? arv_live_race_meta( $name ) : null;
	$stats = arv_stats_store_find( ARV_LIVE_BASE . $show );

	$out = '<section class="arv-live" aria-label="' . esc_attr__( 'Live results', 'aravaipa-elements' ) . '">';

	$out .= arv_live_head( $name, $edition, $meta, $slug, $show );
	$out .= arv_live_years( $editions, $show );
	$out .= arv_live_frame( $show, $height, $name );
	$out .= arv_live_report( $stats, $edition, $name );

	$out .= '</section>';

	return $out;
}

/**
 * Title, date and place.
 *
 * @param string $name
 * @param array|null $edition
 * @param array|null $meta
 * @param string $slug
 * @param string $show
 * @return string
 */
function arv_live_head( $name, $edition, $meta, $slug, $show ) {
	$heading = '' !== $name ? $name : __( 'Live Results', 'aravaipa-elements' );

	$out = '<header class="arv-live__head">';
	$out .= '<h2 class="arv-live__title">' . esc_html( $heading );

	if ( $edition ) {
		$out .= ' <span class="arv-live__year">' . esc_html( substr( $edition['iso'], 0, 4 ) ) . '</span>';
	}

	$out .= '</h2>';

	$bits = array();

	if ( $edition ) {
		$bits[] = arv_results_edition_label( $edition );
	}

	if ( $meta ) {
		$where = trim( (string) ( isset( $meta['location'] ) ? $meta['location'] : '' ) );

		if ( '' !== $where ) {
			$bits[] = $where;
		}
	}

	if ( ! empty( $bits ) ) {
		$out .= '<p class="arv-live__meta">' . esc_html( implode( ' · ', $bits ) ) . '</p>';
	}

	return $out . '</header>';
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
		$is   = arv_live_store_slug( $edition['live'] ) === $current;

		if ( $is ) {
			$out .= '<span class="arv-live__year-link is-current" aria-current="page">'
				. esc_html( $year ) . '</span>';
			continue;
		}

		$out .= '<a class="arv-live__year-link" href="'
			. esc_url( add_query_arg( 'year', $year, arv_live_self_url() ) ) . '">'
			. esc_html( $year ) . '</a>';
	}

	return $out . '</nav>';
}

/**
 * This page's own URL, without the year already on it.
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
