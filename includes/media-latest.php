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
		// 'large', not 'medium'. The hero displays this at around 740px
		// wide, and 'medium' on this site is 245px: the Mount to Coast
		// announcement was being upscaled more than three times its own
		// width and looked exactly as bad as that sounds. The feed cards
		// below it are ~280px, so one size covers both rather than the
		// hero and the cards each reading the store differently.
		$src = arv_media_latest_thumb( $post->ID );

		if ( '' === $src['url'] ) {
			continue;
		}

		$items[] = array(
			'type'   => 'article',
			'badge'  => __( 'Article', 'aravaipa-elements' ),
			'title'  => get_the_title( $post ),
			'date'   => get_the_date( 'c', $post ),
			'thumb'  => $src['url'],
			// Carried so the hero can tell a landscape photograph from a
			// portrait poster and stop cropping the second kind to death.
			'width'  => $src['width'],
			'height' => $src['height'],
			'url'    => get_permalink( $post ),
		);
	}

	set_transient( $key, $items, HOUR_IN_SECONDS );

	return $items;
}

/**
 * A post's featured image at a size the hero can actually use, with the
 * shape it happens to be.
 *
 * The dimensions matter as much as the URL here. A 16:9 crop is right for
 * a landscape photograph and wrong for a portrait poster: the Sonoma
 * partnership announcement is a 1568x1920 graphic with faces along the top
 * and sponsor logos along the bottom, and cropping that to 16:9 removes
 * both and leaves the middle. Knowing the shape is what lets the hero
 * decide rather than guess.
 *
 * @param int $post_id
 * @return array{url: string, width: int, height: int}
 */
function arv_media_latest_thumb( $post_id ) {
	$none = array( 'url' => '', 'width' => 0, 'height' => 0 );

	if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
		return $none;
	}

	$id = (int) get_post_thumbnail_id( $post_id );

	if ( ! $id ) {
		return $none;
	}

	if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
		$url = get_the_post_thumbnail_url( $post_id, 'large' );

		return $url ? array( 'url' => $url, 'width' => 0, 'height' => 0 ) : $none;
	}

	$src = wp_get_attachment_image_src( $id, 'large' );

	if ( ! is_array( $src ) || empty( $src[0] ) ) {
		return $none;
	}

	return array(
		'url'    => (string) $src[0],
		'width'  => isset( $src[1] ) ? (int) $src[1] : 0,
		'height' => isset( $src[2] ) ? (int) $src[2] : 0,
	);
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
function arv_media_latest_items( $limit = 0, $offset = 0 ) {
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

	// Offset before limit, so a feed sitting under the hero can skip the
	// one item the hero already showed rather than repeating it as its own
	// first card.
	if ( $offset > 0 ) {
		$items = array_slice( $items, $offset );
	}

	return $limit > 0 ? array_slice( $items, 0, $limit ) : $items;
}

/**
 * The single newest thing Aravaipa has published, at full width.
 *
 * The same merged list the feed below it uses, taking one item. That is
 * the point: the hero and the feed cannot disagree about what is newest,
 * because they are the same sort of the same four sources, and the feed
 * skips whatever the hero already showed rather than repeating it
 * directly underneath itself.
 *
 * Deliberately not restricted to the types with landscape artwork. A
 * podcast episode's square art crops to the hero box perfectly well
 * through object-fit, and quietly skipping a whole content type because
 * of its aspect ratio would mean the page's own "newest" claim is only
 * sometimes true.
 *
 * @param array $args heading (optional eyebrow above the title).
 * @return string
 */
function arv_media_hero_render( $args = array() ) {
	$items = arv_media_latest_items( 1 );

	if ( empty( $items ) ) {
		return '';
	}

	$item  = $items[0];
	$stamp = strtotime( $item['date'] );

	$out  = '<section class="arv-media-hero">';
	$out .= '<a class="arv-media-hero__link" href="' . esc_url( $item['url'] ) . '">';

	if ( '' !== $item['thumb'] ) {
		// A landscape photograph fills the 16:9 box correctly. A portrait
		// poster does not: cropping one to 16:9 keeps its middle and throws
		// away the parts that carry the meaning, which on the Sonoma
		// partnership graphic were the faces along the top and the sponsor
		// logos along the bottom. Anything taller than it is wide is shown
		// whole instead, letterboxed against the section's own background.
		$tall = ! empty( $item['height'] ) && ! empty( $item['width'] )
			&& $item['height'] > $item['width'];

		$out .= '<span class="arv-media-hero__art">';
		$out .= '<img class="arv-media-hero__img' . ( $tall ? ' arv-media-hero__img--tall' : '' ) . '"'
			. ' src="' . esc_url( $item['thumb'] ) . '" alt=""'
			. ' loading="eager" decoding="async" width="960" height="540" />';
		$out .= '</span>';
	}

	$out .= '<span class="arv-media-hero__body">';
	$out .= '<span class="arv-media-hero__badge">' . esc_html( $item['badge'] ) . '</span>';
	$out .= '<span class="arv-media-hero__title">' . esc_html( $item['title'] ) . '</span>';

	if ( $stamp ) {
		$out .= '<span class="arv-media-hero__date">' . esc_html( gmdate( 'F j, Y', $stamp ) ) . '</span>';
	}

	$out .= '</span></a></section>';

	return $out;
}

/**
 * [arv_media_hero] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_media_hero_shortcode( $atts ) {
	return arv_media_hero_render( (array) $atts );
}
add_shortcode( 'arv_media_hero', 'arv_media_hero_shortcode' );

/**
 * The Latest feed: one merged stream, filterable by type.
 *
 * @param array $args heading, intro, limit.
 * @return string
 */
function arv_media_latest_render( $args = array() ) {
	$items = arv_media_latest_items(
		isset( $args['limit'] ) ? (int) $args['limit'] : 0,
		isset( $args['offset'] ) ? (int) $args['offset'] : 0
	);

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
			// 1 where a hero sits directly above this, so the same item is
			// not the hero and the first card at once.
			'offset'  => 0,
		),
		$atts,
		'arv_media_latest'
	);

	return arv_media_latest_render( $atts );
}
add_shortcode( 'arv_media_latest', 'arv_media_latest_shortcode' );
