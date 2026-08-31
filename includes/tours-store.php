<?php
/**
 * Film tours: the campaign pages, gathered somewhere permanent.
 *
 * Aravaipa has run two of these, The Chase in 2025 and The Cutoff in 2026,
 * and each got its own page built from scratch for the campaign. Both of
 * those pages then went stale the day the tour ended, because nothing about
 * them knew the tour was over: The Chase was still offering "Book Tickets"
 * sixteen months after its last screening.
 *
 * So this index is not really about tidying the two that exist. It is about
 * the third one. A permanent /film-tours/ means the next film arrives at a
 * URL that already has links and rank instead of launching cold, and the
 * state of each tour is computed from its dates rather than typed into a
 * page nobody revisits.
 *
 * The tours themselves are configured here rather than fetched. There are
 * two of them and they are launched by people, not by a feed; a config that
 * a human edits twice a year is the honest shape for that. What is fetched
 * is everything that changes on its own: the film's artwork and its view
 * count come from the Films store, which already reads the same YouTube
 * playlist the films live in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every film tour, newest first.
 *
 * Dates are the tour window, not the film's release. A tour with no 'to'
 * date has been announced but not scheduled, which is a real state during
 * the weeks between the trailer and the first venue confirming.
 *
 * @return array<int, array>
 */
function arv_tours_config() {
	return apply_filters(
		'arv_tours_config',
		array(
			array(
				'key'       => 'the-cutoff',
				'title'     => 'The Cutoff',
				'sub'       => 'A Film For The Rest Of Us',
				'page'      => '/the-cutoff/',
				'film'      => 'r7GGVbLGxU0',
				// The real span, counted off the tour page's own eighteen
				// listed stops, not the "February 20 - March 31" the page
				// advertises in its heading. The tour kept going for six
				// weeks after the date it told everyone it ended.
				'from'      => '2026-02-20',
				'to'        => '2026-05-14',
				'stops'     => 18,
				// US, Canada, Australia and Thailand.
				'countries' => 4,
				'merch'     => 'https://aravaipa-shop.square.site/shop/the-cutoff-film-merch/K6AYTAFS3AOEA7F57OAIRMKQ',
			),
			array(
				'key'       => 'the-chase',
				'title'     => 'The Chase',
				'sub'       => 'A Cocodona 250 Story',
				// Was /cocodona-old/the-chase-film/ until the page was moved
				// out from under a draft parent named "Cocodona 250 OLD",
				// which is what its permalink had been advertising.
				'page'      => '/the-chase-film/',
				'film'      => 'k0HkYULFVvA',
				'trailer'   => 'YrSxAPy4FFE',
				// Same as The Cutoff: the heading advertised a window ending
				// April 15 and the twenty-one listed stops run to May 2.
				'from'      => '2025-03-15',
				'to'        => '2025-05-02',
				'stops'     => 21,
				// US and Canada.
				'countries' => 2,
				'merch'     => 'https://aravaipa-shop.square.site/shop/the-chase-film-merch/L2T3UMH5LXZO32272R7NQ256',
			),
		)
	);
}

/**
 * Where a tour is in its own life, worked out from its dates.
 *
 * The entire reason this is computed: both existing tour pages went stale
 * the day their last screening happened, and a status typed into a page is
 * a status nobody comes back to change.
 *
 * @param array $tour
 * @return string 'upcoming', 'touring' or 'toured'.
 */
function arv_tours_state( $tour ) {
	$today = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	$from  = isset( $tour['from'] ) ? (string) $tour['from'] : '';
	$to    = isset( $tour['to'] ) ? (string) $tour['to'] : '';

	if ( '' !== $from && $today < $from ) {
		return 'upcoming';
	}

	// No end date is a tour that has started and has not said when it
	// finishes, which reads as running rather than as finished.
	if ( '' === $to || $today <= $to ) {
		return 'touring';
	}

	return 'toured';
}

/**
 * The tour window as a person would say it.
 *
 * "February 20 to March 31, 2026", with the year said once, since a tour
 * that starts and ends in the same year saying it twice reads like two
 * different years at a glance.
 *
 * @param array $tour
 * @return string
 */
function arv_tours_window( $tour ) {
	$from = isset( $tour['from'] ) ? strtotime( (string) $tour['from'] ) : false;
	$to   = isset( $tour['to'] ) ? strtotime( (string) $tour['to'] ) : false;

	if ( ! $from ) {
		return '';
	}

	if ( ! $to ) {
		return gmdate( 'F Y', $from );
	}

	$same = gmdate( 'Y', $from ) === gmdate( 'Y', $to );

	return gmdate( $same ? 'F j' : 'F j, Y', $from ) . ' to ' . gmdate( 'F j, Y', $to );
}

/**
 * What the card says on its badge.
 *
 * @param string $state
 * @return string
 */
function arv_tours_badge( $state ) {
	if ( 'upcoming' === $state ) {
		return __( 'Tour announced', 'aravaipa-elements' );
	}

	if ( 'touring' === $state ) {
		return __( 'On tour now', 'aravaipa-elements' );
	}

	return __( 'Watch it now', 'aravaipa-elements' );
}

/**
 * A film's artwork and view count, from the Films store where it has them.
 *
 * Falls back to YouTube's own thumbnail URL, which is derived from the
 * video id and therefore cannot 404 while the video exists. That matters:
 * this page should still look right when the Films feed is down, which is
 * exactly when someone is most likely to be looking at it.
 *
 * @param string $id YouTube video id.
 * @return array{thumb: string, views: int}
 */
function arv_tours_film( $id ) {
	$out = array(
		'thumb' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
		'views' => 0,
	);

	if ( '' === $id || ! function_exists( 'arv_films_fetch' ) || ! function_exists( 'arv_films_all' ) ) {
		return $out;
	}

	foreach ( arv_films_all( arv_films_fetch() ) as $film ) {
		if ( $film['id'] === $id ) {
			if ( '' !== $film['thumbnail'] ) {
				$out['thumb'] = $film['thumbnail'];
			}

			$out['views'] = (int) $film['views'];
			break;
		}
	}

	return $out;
}

/**
 * What the tour actually did, as opposed to when it ran.
 *
 * "18 stops in 4 countries" is the sentence a sponsor, a venue or a
 * distributor wants from this page, and it was nowhere on the site: both
 * tour pages list their venues one by one and neither ever adds them up.
 *
 * Counted off those lists rather than estimated. A tour with no stop count
 * recorded says nothing here instead of guessing, which is the state an
 * announced-but-unscheduled tour is genuinely in.
 *
 * @param array $tour
 * @return string
 */
function arv_tours_recap( $tour ) {
	$stops = isset( $tour['stops'] ) ? (int) $tour['stops'] : 0;

	if ( $stops < 1 ) {
		return '';
	}

	$bits = array(
		sprintf(
			/* translators: %s: a count of tour stops. */
			_n( '%s stop', '%s stops', $stops, 'aravaipa-elements' ),
			number_format_i18n( $stops )
		),
	);

	$countries = isset( $tour['countries'] ) ? (int) $tour['countries'] : 0;

	// One country is not worth saying: every tour happens somewhere. Two or
	// more is the thing worth saying.
	if ( $countries > 1 ) {
		$bits[] = sprintf(
			/* translators: %s: a count of countries. */
			_n( '%s country', '%s countries', $countries, 'aravaipa-elements' ),
			number_format_i18n( $countries )
		);
	}

	return implode( ' · ', $bits );
}

/**
 * One tour's card.
 *
 * @param array $tour
 * @return string
 */
function arv_tours_card( $tour ) {
	$state      = arv_tours_state( $tour );
	$film       = arv_tours_film( isset( $tour['film'] ) ? (string) $tour['film'] : '' );
	$href       = home_url( $tour['page'] );
	$tour_stops = isset( $tour['stops'] ) ? (int) $tour['stops'] : 0;

	$out = '<li class="arv-tours__card arv-tours__card--' . esc_attr( $state ) . '">';
	$out .= '<a class="arv-tours__link" href="' . esc_url( $href ) . '">';

	$out .= '<img class="arv-tours__art" src="' . esc_url( $film['thumb'] ) . '" alt=""'
		. ' loading="lazy" decoding="async" width="480" height="270" />';

	$out .= '<span class="arv-tours__body">';
	$out .= '<span class="arv-tours__badge">' . esc_html( arv_tours_badge( $state ) ) . '</span>';
	$out .= '<span class="arv-tours__title">' . esc_html( $tour['title'] ) . '</span>';

	if ( ! empty( $tour['sub'] ) ) {
		$out .= '<span class="arv-tours__sub">' . esc_html( $tour['sub'] ) . '</span>';
	}

	$bits   = array();
	$window = arv_tours_window( $tour );

	if ( '' !== $window ) {
		$bits[] = ( 'toured' === $state )
			/* translators: %s: a date range, e.g. "February 20 to March 31, 2026". */
			? sprintf( __( 'Toured %s', 'aravaipa-elements' ), $window )
			: $window;
	}

	if ( $film['views'] ) {
		/* translators: %s: a count of YouTube views. */
		$bits[] = sprintf( __( '%s views', 'aravaipa-elements' ), number_format_i18n( $film['views'] ) );
	}

	if ( ! empty( $bits ) ) {
		$out .= '<span class="arv-tours__meta">' . esc_html( implode( ' · ', $bits ) ) . '</span>';
	}

	$recap = arv_tours_recap( $tour );

	if ( '' !== $recap ) {
		$out .= '<span class="arv-tours__recap">' . esc_html( $recap ) . '</span>';
	}

	$out .= '</span></a>';

	// Below the card's own link rather than inside it, since a link inside
	// a link is not a thing a browser can render. Same rule the Photos card
	// follows for its extra photographers.
	$more = array();

	// The tour page itself, said out loud. The whole card already links
	// there, but a card whose only visible buttons are "Watch the film"
	// and "Merch" reads as though those are the only two places to go, and
	// the tour page is where the venues, the sponsors and the stop list
	// actually live.
	$more[] = '<a class="arv-tours__action arv-tours__action--primary" href="' . esc_url( $href ) . '">'
		. esc_html(
			$tour_stops
				? sprintf(
					/* translators: %s: a count of tour stops. */
					_n( 'Tour page: %s stop', 'Tour page: all %s stops', $tour_stops, 'aravaipa-elements' ),
					number_format_i18n( $tour_stops )
				)
				: __( 'Tour page', 'aravaipa-elements' )
		)
		. '</a>';

	if ( ! empty( $tour['film'] ) ) {
		$more[] = '<a class="arv-tours__action" href="'
			. esc_url( home_url( '/films/?v=' . $tour['film'] ) ) . '">'
			. esc_html__( 'Watch the film', 'aravaipa-elements' ) . '</a>';
	}

	if ( ! empty( $tour['merch'] ) ) {
		$more[] = '<a class="arv-tours__action" href="' . esc_url( $tour['merch'] ) . '"'
			. ' target="_blank" rel="noopener">' . esc_html__( 'Merch', 'aravaipa-elements' ) . '</a>';
	}

	if ( ! empty( $more ) ) {
		$out .= '<p class="arv-tours__actions">' . implode( '', $more ) . '</p>';
	}

	return $out . '</li>';
}

/**
 * The Film Tours index.
 *
 * @param array $args heading, intro.
 * @return string
 */
function arv_tours_render( $args = array() ) {
	$tours = arv_tours_config();

	if ( empty( $tours ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Film Tours';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';

	$out  = '<section class="arv-tours">';
	$out .= '<div class="arv-tours__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-tours__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-tours__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<ul class="arv-tours__grid">';

	foreach ( $tours as $tour ) {
		$out .= arv_tours_card( $tour );
	}

	return $out . '</ul></div></section>';
}

/**
 * The strip that sends the Films page here.
 *
 * Films and this page do different jobs and should not be merged: Films is
 * a grid of everything on the YouTube channel, a tour page is a campaign
 * with venues, tickets, sponsors and merch. But someone on Films looking at
 * The Cutoff has no way to know the tour pages exist at all, which is the
 * gap this closes.
 *
 * @return string
 */
function arv_tours_strip_render() {
	$tours = arv_tours_config();

	if ( empty( $tours ) ) {
		return '';
	}

	$touring = 0;

	foreach ( $tours as $tour ) {
		if ( 'toured' !== arv_tours_state( $tour ) ) {
			$touring++;
		}
	}

	// Says the one thing that is worth interrupting the Films page for when
	// it is true, and stays a quiet cross-link the rest of the year.
	$line = $touring
		/* translators: %s: a count of tours currently running or announced. */
		? sprintf( _n( '%s tour on the road right now.', '%s tours on the road right now.', $touring, 'aravaipa-elements' ), number_format_i18n( $touring ) )
		: __( 'Every Aravaipa film tour, where it played and where to watch it now.', 'aravaipa-elements' );

	$out  = '<section class="arv-tours-strip">';
	$out .= '<div class="arv-tours-strip__inner">';
	$out .= '<span class="arv-tours-strip__body">';
	$out .= '<span class="arv-tours-strip__title">' . esc_html__( 'Film Tours', 'aravaipa-elements' ) . '</span>';
	$out .= '<span class="arv-tours-strip__line">' . esc_html( $line ) . '</span>';
	$out .= '</span>';
	$out .= '<a class="arv-tours-strip__cta" href="' . esc_url( home_url( '/film-tours/' ) ) . '">'
		. esc_html__( 'See the tours', 'aravaipa-elements' ) . '</a>';

	return $out . '</div></section>';
}

/**
 * [arv_film_tours] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_tours_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Film Tours',
			'intro'   => '',
		),
		$atts,
		'arv_film_tours'
	);

	return arv_tours_render( $atts );
}
add_shortcode( 'arv_film_tours', 'arv_tours_shortcode' );

/**
 * [arv_film_tours_strip] for the Films page.
 *
 * @return string
 */
function arv_tours_strip_shortcode() {
	return arv_tours_strip_render();
}
add_shortcode( 'arv_film_tours_strip', 'arv_tours_strip_shortcode' );
