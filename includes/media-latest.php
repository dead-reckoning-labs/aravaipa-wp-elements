<?php
/**
 * The Latest feed: broadcasts, films, podcast episodes and blog posts,
 * merged into one reverse-chronological stream.
 *
 * The five cards on /media/ answer "what does Aravaipa make" once. This
 * answers "what did Aravaipa just publish", across all of it, which none
 * of those five cards can: each only knows its own section's newest
 * thing, not whether that thing is newer than what another section put
 * out yesterday.
 *
 * Every source here is already loaded for the same reason the stores
 * above this file are: Watch, Films and Podcasts are read live on every
 * page that needs them, and the merge is just sorting four lists that
 * already exist rather than fetching anything new. Only the blog post
 * query is new, and it is a single get_posts() call, cached the same
 * hour the others are.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One card per Watch event, dated by its lead segment.
 *
 * Same lead-segment and href logic arv_watch_event() uses for the Watch
 * archive itself, so a broadcast card here opens on the same edition and
 * the same page a click from Watch would.
 *
 * @return array<int, array>
 */
function arv_media_latest_from_watch() {
	if ( ! function_exists( 'arv_watch_events' ) ) {
		return array();
	}

	$items = array();
	$pages = function_exists( 'arv_watch_page_map' ) ? arv_watch_page_map() : array();

	foreach ( arv_watch_events() as $event ) {
		if ( empty( $event['streams'] ) ) {
			continue;
		}

		$lead = $event['streams'][0];
		$date = '' !== $event['start'] ? $event['start'] : $lead['aired'];

		if ( '' === $date ) {
			continue;
		}

		$key      = function_exists( 'arv_watch_race_key' ) ? arv_watch_race_key( $event['name'] ) : '';
		$page_hit = isset( $pages[ $key ] );
		$href     = $page_hit ? $pages[ $key ] : $lead['url'];

		if ( function_exists( 'arv_watch_segment_url' ) ) {
			$href = arv_watch_segment_url( $href, $page_hit, substr( $date, 0, 4 ), null );
		}

		$items[] = array(
			'type'  => 'broadcast',
			'badge' => __( 'Broadcast', 'aravaipa-elements' ),
			'title' => $event['name'],
			'date'  => $date,
			'thumb' => $lead['thumbnail'],
			'url'   => $href,
		);
	}

	return $items;
}

/**
 * One card per film.
 *
 * @return array<int, array>
 */
function arv_media_latest_from_films() {
	if ( ! function_exists( 'arv_films_fetch' ) || ! function_exists( 'arv_films_all' ) ) {
		return array();
	}

	$items = array();

	foreach ( arv_films_all( arv_films_fetch() ) as $film ) {
		if ( '' === $film['published'] ) {
			continue;
		}

		$items[] = array(
			'type'  => 'film',
			'badge' => __( 'Film', 'aravaipa-elements' ),
			'title' => $film['title'],
			'date'  => $film['published'],
			'thumb' => $film['thumbnail'],
			'url'   => home_url( '/films/?v=' . $film['id'] ),
		);
	}

	return $items;
}

/**
 * One card per podcast episode, linking to the show's own page rather
 * than the episode's external one: a click from this feed should stay on
 * aravaiparunning.com the same way a click from Films or Watch does.
 *
 * @return array<int, array>
 */
function arv_media_latest_from_podcasts() {
	if ( ! function_exists( 'arv_podcasts_fetch' ) || ! function_exists( 'arv_podcasts_all' ) ) {
		return array();
	}

	$items = array();

	foreach ( arv_podcasts_all( arv_podcasts_fetch() ) as $episode ) {
		if ( '' === $episode['published'] ) {
			continue;
		}

		$items[] = array(
			'type'  => 'podcast',
			'badge' => __( 'Podcast', 'aravaipa-elements' ),
			'title' => $episode['title'],
			'date'  => $episode['published'],
			'thumb' => $episode['artwork'],
			'url'   => home_url( '/podcasts/' . $episode['show_key'] . '/' ),
		);
	}

	return $items;
}

/**
 * One card per blog post with a featured image.
 *
 * Without one: a card with no image beside forty that have one reads as
 * broken, not as a post that simply did not set one. The five section
 * cards on this same page already follow that rule for Photos and
 * Podcasts, by leaving them out of the thumbnail treatment entirely
 * rather than showing a gap; here the equivalent is not showing the
 * post at all, since unlike those two, most posts do have an image and
 * the ones that do not are the exception worth dropping rather than the
 * rule to design around.
 *
 * Cached the same hour every other source in this file already reads
 * with: a fresh cache miss on /media/ would otherwise run this query on
 * every single visit, and posts are not published often enough to need
 * more than an hourly answer.
 *
 * @return array<int, array>
 */
function arv_media_latest_from_posts() {
	$key    = 'arv_media_latest_posts';
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return $cached;
	}

	$items = array();

	// More than this feed will ever show, on purpose: a post with no
	// featured image is dropped below, and a run of a few image-less
	// announcement posts should not be able to starve the feed of the
	// real ones sitting just behind them.
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 40,
			'no_found_rows'  => true,
		)
	);

	foreach ( $posts as $post ) {
		$thumb = get_the_post_thumbnail_url( $post->ID, 'medium' );

		if ( ! $thumb ) {
			continue;
		}

		$items[] = array(
			'type'  => 'article',
			'badge' => __( 'Article', 'aravaipa-elements' ),
			'title' => get_the_title( $post ),
			'date'  => get_the_date( 'c', $post ),
			'thumb' => $thumb,
			'url'   => get_permalink( $post ),
		);
	}

	set_transient( $key, $items, HOUR_IN_SECONDS );

	return $items;
}

/**
 * Drop the cached post list the moment a post is published or edited,
 * rather than making a fresh post wait up to an hour to reach this feed.
 *
 * @param int $post_id
 */
function arv_media_latest_flush_on_save( $post_id ) {
	if ( 'post' === get_post_type( $post_id ) ) {
		delete_transient( 'arv_media_latest_posts' );
	}
}
add_action( 'save_post', 'arv_media_latest_flush_on_save' );

/**
 * Every source, merged and sorted newest first.
 *
 * @param int $limit 0 for everything.
 * @return array<int, array>
 */
function arv_media_latest_items( $limit = 0 ) {
	$items = array_merge(
		arv_media_latest_from_watch(),
		arv_media_latest_from_films(),
		arv_media_latest_from_podcasts(),
		arv_media_latest_from_posts()
	);

	usort(
		$items,
		function ( $a, $b ) {
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		}
	);

	return $limit > 0 ? array_slice( $items, 0, $limit ) : $items;
}

/**
 * The Latest feed: one merged stream, filterable by type.
 *
 * @param array $args heading, intro, limit.
 * @return string
 */
function arv_media_latest_render( $args = array() ) {
	$items = arv_media_latest_items( isset( $args['limit'] ) ? (int) $args['limit'] : 0 );

	if ( empty( $items ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Latest';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';

	$out = '<section class="arv-media-latest">';
	$out .= '<div class="arv-media-latest__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-media-latest__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-media-latest__intro">' . esc_html( $intro ) . '</p>';
	}

	// Only worth a control when the page actually mixes types: a feed of
	// nothing but articles would offer three buttons that all do the same
	// thing as "All".
	$types = array();
	foreach ( $items as $item ) {
		$types[ $item['type'] ] = $item['badge'];
	}

	if ( count( $types ) > 1 ) {
		$out .= '<div class="arv-media-latest__filters" data-arv-latest-filters>';
		$out .= '<button type="button" class="arv-media-latest__filter is-current" data-arv-latest-type="">'
			. esc_html__( 'All', 'aravaipa-elements' ) . '</button>';

		foreach ( $types as $type => $badge ) {
			$out .= '<button type="button" class="arv-media-latest__filter" data-arv-latest-type="' . esc_attr( $type ) . '">'
				. esc_html( $badge ) . '</button>';
		}

		$out .= '</div>';
	}

	$out .= '<ul class="arv-media-latest__grid" data-arv-latest-grid>';

	foreach ( $items as $item ) {
		$out .= arv_media_latest_card( $item );
	}

	$out .= '</ul>';

	return $out . '</div></section>';
}

/**
 * @param array $item type, badge, title, date, thumb, url.
 * @return string
 */
function arv_media_latest_card( $item ) {
	$stamp = strtotime( $item['date'] );

	$out = '<li class="arv-media-latest__card" data-arv-latest-type="' . esc_attr( $item['type'] ) . '">';
	$out .= '<a class="arv-media-latest__link" href="' . esc_url( $item['url'] ) . '">';

	if ( '' !== $item['thumb'] ) {
		$out .= '<img class="arv-media-latest__thumb" src="' . esc_url( $item['thumb'] ) . '" alt=""'
			. ' loading="lazy" decoding="async" width="480" height="320" />';
	}

	$out .= '<span class="arv-media-latest__badge">' . esc_html( $item['badge'] ) . '</span>';
	$out .= '<span class="arv-media-latest__title">' . esc_html( $item['title'] ) . '</span>';

	if ( $stamp ) {
		$out .= '<span class="arv-media-latest__date">' . esc_html( gmdate( 'F j, Y', $stamp ) ) . '</span>';
	}

	$out .= '</a></li>';

	return $out;
}

/**
 * [arv_media_latest] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_media_latest_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Latest',
			'intro'   => '',
			'limit'   => 12,
		),
		$atts,
		'arv_media_latest'
	);

	return arv_media_latest_render( $atts );
}
add_shortcode( 'arv_media_latest', 'arv_media_latest_shortcode' );
