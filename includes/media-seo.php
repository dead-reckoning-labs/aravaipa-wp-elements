<?php
/**
 * Descriptions and structured data for the pages that had neither.
 *
 * Started at five: /media/, /film-tours/, /articles/, /photos/ and /shop/
 * shipped with no meta description and no structured data at all, so
 * Google wrote its own snippet for each from whatever text it found
 * first, which on a page that opens with a nav strip is usually the nav
 * strip. A later pass found the same gap on five more: Films, Broadcasts
 * and Podcasts had it for the same reason as the original five, and
 * /races/ and the yearly /results-YYYY/ pages had it too, for a different
 * one, see arv_media_seo_page() below.
 *
 * That gap is wider than it looks right now, because this site runs no SEO
 * plugin. Yoast is installed and deactivated, and Site Kit only reports on
 * Search Console, it does not emit a single tag. Nothing else fills in.
 *
 * Every function here yields to a real SEO plugin the moment one is
 * active, the same arv_seo_handled_elsewhere() check the rest of this
 * plugin's head output uses. Reactivating Yoast should silence all of
 * this rather than produce a second, competing description.
 *
 * Pages are recognised by the shortcode they carry rather than by path or
 * by a stored id, wherever a shortcode exists to check for: a page that
 * gets renamed, moved under a different parent, or rebuilt keeps working,
 * and the recogniser cannot drift out of step with what the page actually
 * renders.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which of the five, if any, the current page is.
 *
 * @return string One of media|film-tours|articles|photos|shop, or ''.
 */
function arv_media_seo_page() {
	if ( ! function_exists( 'is_page' ) || ! is_page() ) {
		return '';
	}

	$id = get_queried_object_id();

	if ( ! $id ) {
		return '';
	}

	$post = get_post( $id );

	if ( ! $post ) {
		return '';
	}

	if ( '' !== trim( (string) $post->post_content ) ) {
		// Ordered most specific first: /media/ carries [arv_media_hub], and
		// nothing else does, but checking a looser pattern earlier would
		// match the wrong page.
		$shortcodes = array(
			'film-tours' => 'arv_film_tours',
			'shop'       => 'arv_shop',
			'articles'   => 'arv_articles',
			'photos'     => 'arv_photos',
			'broadcasts' => 'arv_watch',
			'films'      => 'arv_films',
			'podcasts'   => 'arv_podcasts',
			'media'      => 'arv_media_hub',
		);

		foreach ( $shortcodes as $key => $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return $key;
			}
		}
	}

	// /races/ and the yearly /results-YYYY/ pages carry no shortcode at
	// all to check: both are built entirely from Cornerstone elements
	// (aravaipa-season-calendar, aravaipa-race-map, aravaipa-results),
	// none of which register a parallel shortcode the way the rails and
	// the shop do. There is nothing in post_content for has_shortcode()
	// to find, so these two are matched on slug instead, the one signal
	// that is actually available. Narrower than it looks: this only ever
	// fires for these two exact shapes, everything else still goes
	// through the shortcode path above.
	if ( 'races' === $post->post_name ) {
		return 'races';
	}

	if ( preg_match( '/^results-\d{4}$/', $post->post_name ) ) {
		return 'results';
	}

	return '';
}

/**
 * The description and Open Graph pair for one of them.
 *
 * Counts are read from the same stores the pages themselves render from,
 * so a description cannot claim a number the page does not show.
 *
 * @param string $key
 * @return array{title: string, description: string, type: string}
 */
function arv_media_seo_meta( $key ) {
	if ( 'media' === $key ) {
		$counts = function_exists( 'arv_media_hub_counts' ) ? arv_media_hub_counts() : array();
		$parts  = array_values( array_filter( $counts ) );

		return array(
			'title'       => __( 'Media | Aravaipa Running', 'aravaipa-elements' ),
			'description' => ! empty( $parts )
				/* translators: %s: a list like "33 broadcasts, 20 films, 46 episodes". */
				? sprintf( __( 'Every Aravaipa Running broadcast, film, podcast episode and article in one place: %s.', 'aravaipa-elements' ), implode( ', ', $parts ) )
				: __( 'Every Aravaipa Running broadcast, film, podcast episode and article in one place.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'film-tours' === $key ) {
		$tours = function_exists( 'arv_tours_config' ) ? arv_tours_config() : array();
		$stops = 0;

		foreach ( $tours as $tour ) {
			$stops += isset( $tour['stops'] ) ? (int) $tour['stops'] : 0;
		}

		return array(
			'title'       => __( 'Film Tours | Aravaipa Running', 'aravaipa-elements' ),
			'description' => $stops
				/* translators: 1: a count of films, 2: a count of tour stops. */
				? sprintf( __( 'Aravaipa Running has taken %1$s films on tour across %2$s screenings worldwide. See where each one played, and watch them now.', 'aravaipa-elements' ), number_format_i18n( count( $tours ) ), number_format_i18n( $stops ) )
				: __( 'Where each Aravaipa Running film played on tour, and where to watch it now.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'articles' === $key ) {
		$items = function_exists( 'arv_articles_items' ) ? arv_articles_items() : array();

		return array(
			'title'       => __( 'Articles | Aravaipa Running', 'aravaipa-elements' ),
			'description' => ! empty( $items )
				/* translators: %s: a count of articles. */
				? sprintf( __( '%s race reports, course previews, training notes and announcements from Aravaipa Running, going back to 2011.', 'aravaipa-elements' ), number_format_i18n( count( $items ) ) )
				: __( 'Race reports, course previews and trail thoughts from Aravaipa Running.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'photos' === $key ) {
		$rows = function_exists( 'arv_photos_store_get' ) ? arv_photos_store_get() : array();

		return array(
			'title'       => __( 'Photo Galleries | Aravaipa Running', 'aravaipa-elements' ),
			'description' => ! empty( $rows )
				/* translators: %s: a count of galleries. */
				? sprintf( __( 'Race photography from %s Aravaipa Running galleries, shot by our own team and by the photographers we work with. Free to browse, by race and by year.', 'aravaipa-elements' ), number_format_i18n( count( $rows ) ) )
				: __( 'Race photography from Aravaipa Running, by race and by year.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'shop' === $key ) {
		$shop     = function_exists( 'arv_shop_get' ) ? arv_shop_get() : array( 'products' => array() );
		$products = isset( $shop['products'] ) ? count( $shop['products'] ) : 0;

		return array(
			'title'       => __( 'Shop | Aravaipa Running', 'aravaipa-elements' ),
			'description' => $products
				/* translators: %s: a count of products. */
				? sprintf( __( '%s pieces of Aravaipa Running race gear: hats, tees, hoodies and accessories, by race and by category.', 'aravaipa-elements' ), number_format_i18n( $products ) )
				: __( 'Aravaipa Running race gear, by race and by category.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'films' === $key ) {
		$films = function_exists( 'arv_films_all' ) && function_exists( 'arv_films_fetch' )
			? arv_films_all( arv_films_fetch() )
			: array();

		return array(
			'title'       => __( 'Films | Aravaipa Running', 'aravaipa-elements' ),
			'description' => ! empty( $films )
				/* translators: %s: a count of films. */
				? sprintf( __( '%s Aravaipa Running films, from race documentaries to full event coverage. Watch them all here.', 'aravaipa-elements' ), number_format_i18n( count( $films ) ) )
				: __( 'Aravaipa Running films: race documentaries and full event coverage.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'broadcasts' === $key ) {
		$events = function_exists( 'arv_watch_events' ) ? arv_watch_events() : array();

		return array(
			'title'       => __( 'Broadcasts | Aravaipa Running', 'aravaipa-elements' ),
			'description' => ! empty( $events )
				/* translators: %s: a count of broadcasts. */
				? sprintf( __( 'Every Aravaipa Running live broadcast, %s and counting: watch upcoming races live and past ones on demand.', 'aravaipa-elements' ), number_format_i18n( count( $events ) ) )
				: __( 'Aravaipa Running live broadcasts: watch upcoming races live and past ones on demand.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'podcasts' === $key ) {
		// The fetched shows, episodes and all, the same source the page
		// itself renders from: arv_podcasts_shows_config() alone is just
		// feed URLs and carries no episodes to count.
		$shows    = function_exists( 'arv_podcasts_fetch' ) ? arv_podcasts_fetch() : array();
		$episodes = function_exists( 'arv_podcasts_all' ) ? arv_podcasts_all( $shows ) : array();

		return array(
			'title'       => __( 'Podcasts | Aravaipa Running', 'aravaipa-elements' ),
			'description' => ! empty( $episodes )
				/* translators: 1: a count of episodes, 2: a count of shows. */
				? sprintf( __( '%1$s episodes across %2$s Aravaipa Running podcasts: race previews, recaps and conversation with the people who run them.', 'aravaipa-elements' ), number_format_i18n( count( $episodes ) ), number_format_i18n( count( $shows ) ) )
				: __( 'Aravaipa Running podcasts: race previews, recaps and conversation with the people who run them.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'races' === $key ) {
		$races  = function_exists( 'arv_race_store_get' ) ? arv_race_store_get() : array();
		$states = array();

		foreach ( $races as $race ) {
			if ( preg_match( '/,\s*([A-Z]{2})\s*$/', (string) $race['location'], $m ) ) {
				$states[ $m[1] ] = true;
			}
		}

		return array(
			'title'       => __( 'Races | Aravaipa Running', 'aravaipa-elements' ),
			'description' => ! empty( $races )
				/* translators: 1: a count of races, 2: a count of states. */
				? sprintf( __( '%1$s Aravaipa Running races across %2$s states: dates, distances and how to register for every one on the calendar.', 'aravaipa-elements' ), number_format_i18n( count( $races ) ), number_format_i18n( count( $states ) ) )
				: __( 'Every Aravaipa Running race: dates, distances and how to register.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	if ( 'results' === $key ) {
		$post = get_post( get_queried_object_id() );
		$year = ( $post && preg_match( '/^results-(\d{4})$/', $post->post_name, $m ) ) ? $m[1] : '';

		$count = 0;

		if ( '' !== $year && function_exists( 'arv_results_store_get' ) ) {
			foreach ( arv_results_store_get() as $result ) {
				if ( $year === substr( (string) $result['iso'], 0, 4 ) ) {
					$count++;
				}
			}
		}

		return array(
			/* translators: %s: a year, e.g. 2026. */
			'title'       => '' !== $year ? sprintf( __( 'Results %s | Aravaipa Running', 'aravaipa-elements' ), $year ) : __( 'Results | Aravaipa Running', 'aravaipa-elements' ),
			'description' => $count
				/* translators: 1: a count of races, 2: a year. */
				? sprintf( __( 'Results from %1$s Aravaipa Running races in %2$s, with live boards for the ones still running.', 'aravaipa-elements' ), number_format_i18n( $count ), $year )
				: __( 'Aravaipa Running race results, with live boards for the ones still running.', 'aravaipa-elements' ),
			'type'        => 'website',
		);
	}

	return array( 'title' => '', 'description' => '', 'type' => 'website' );
}

/**
 * The structured data for one of them.
 *
 * Deliberately CollectionPage rather than anything richer on most of
 * these. /shop/ in particular does not mark its products up as Product
 * with an Offer: this site is not where those are bought, Square is, and
 * claiming an offer on a page that cannot complete one is the kind of
 * mismatch that earns a manual action rather than a rich result. The
 * products are listed as an ItemList, which is what the page honestly is.
 *
 * @param string $key
 * @param array  $meta
 * @return array<int, array>
 */
function arv_media_seo_nodes( $key, $meta ) {
	$page = array(
		'@type'       => 'CollectionPage',
		'name'        => $meta['title'],
		'description' => $meta['description'],
		'url'         => get_permalink( get_queried_object_id() ),
	);

	if ( 'articles' === $key && function_exists( 'arv_articles_items' ) ) {
		$items = array();
		$n     = 0;

		// The newest twenty, not all 296: a head blob carrying three
		// hundred entries is a slower page for every visitor in exchange
		// for a list Google can already reach by following the links.
		foreach ( array_slice( arv_articles_items(), 0, 20 ) as $item ) {
			$stamp = strtotime( $item['date'] );

			if ( '' === trim( $item['title'] ) || ! $stamp ) {
				continue;
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => ++$n,
				'url'      => $item['url'],
				'name'     => $item['title'],
			);
		}

		if ( ! empty( $items ) ) {
			return array(
				$page,
				array(
					'@type'           => 'ItemList',
					'name'            => $meta['title'],
					'numberOfItems'   => count( $items ),
					'itemListElement' => $items,
				),
			);
		}
	}

	if ( 'film-tours' === $key && function_exists( 'arv_tours_config' ) ) {
		$items = array();
		$n     = 0;

		foreach ( arv_tours_config() as $tour ) {
			if ( empty( $tour['title'] ) ) {
				continue;
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => ++$n,
				'item'     => array(
					'@type' => 'Movie',
					'name'  => $tour['title'],
					'url'   => home_url( $tour['page'] ),
				),
			);
		}

		if ( ! empty( $items ) ) {
			return array(
				$page,
				array(
					'@type'           => 'ItemList',
					'name'            => $meta['title'],
					'numberOfItems'   => count( $items ),
					'itemListElement' => $items,
				),
			);
		}
	}

	return array( $page );
}

/**
 * A description for a single post, from its own words.
 *
 * The hand-written excerpt when there is one, since a person chose it.
 * Otherwise the opening of the post, cut at a word boundary rather than
 * mid-word: a description that ends "the runners were appro" reads as
 * broken in a search result, and Google will often rewrite the whole
 * snippet rather than show it.
 *
 * @param WP_Post $post
 * @return string
 */
function arv_post_seo_description( $post ) {
	$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
	$text    = '' !== trim( (string) $excerpt )
		? (string) $excerpt
		: wp_strip_all_tags( (string) $post->post_content );

	$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );

	if ( '' === $text ) {
		return '';
	}

	if ( strlen( $text ) <= 160 ) {
		return $text;
	}

	$cut   = substr( $text, 0, 160 );
	$space = strrpos( $cut, ' ' );

	return rtrim( false !== $space ? substr( $cut, 0, $space ) : $cut, " ,.;:-" ) . '...';
}

/**
 * The image to represent a post when it is shared or listed.
 *
 * The featured image where one is set, and otherwise the first picture in
 * the body, which is the same fallback the Articles archive uses and for
 * the same reason: 58 of these 296 posts have a perfectly good photograph
 * in them and never had one set as featured.
 *
 * @param WP_Post $post
 * @return string
 */
function arv_post_seo_image( $post ) {
	if ( function_exists( 'get_the_post_thumbnail_url' ) ) {
		$url = get_the_post_thumbnail_url( $post->ID, 'large' );

		if ( $url ) {
			return (string) $url;
		}
	}

	if ( function_exists( 'arv_articles_body_image' ) ) {
		return arv_articles_body_image( (string) $post->post_content );
	}

	return '';
}

/**
 * Description, Open Graph and BlogPosting for a single post.
 *
 * 296 posts had none of this. They are the pages that answer a real
 * search, "black canyon 100k course preview", "how to train for a 250
 * mile race", and they were going out with no description at all, which
 * leaves the snippet to whatever text Google finds first.
 *
 * Only posts. A race page is a page and already gets its own SportsEvent
 * from arv_seo_race_schema(); marking one up as a blog post as well would
 * be two competing claims about the same URL.
 */
function arv_post_seo_head() {
	if ( ! function_exists( 'arv_seo_handled_elsewhere' ) || arv_seo_handled_elsewhere() ) {
		return;
	}

	if ( ! function_exists( 'is_singular' ) || ! is_singular( 'post' ) ) {
		return;
	}

	$id = get_queried_object_id();

	if ( ! $id ) {
		return;
	}

	$post = get_post( $id );

	if ( ! $post ) {
		return;
	}

	$title       = get_the_title( $post );
	$description = arv_post_seo_description( $post );
	$image       = arv_post_seo_image( $post );
	$url         = get_permalink( $id );
	$published   = get_the_date( 'c', $post );
	$modified    = function_exists( 'get_the_modified_date' ) ? get_the_modified_date( 'c', $post ) : $published;

	if ( '' !== $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	}

	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:type" content="article" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";

	if ( '' !== $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	}

	$node = array(
		'@type'            => 'BlogPosting',
		'headline'         => $title,
		'url'              => $url,
		'datePublished'    => $published,
		'dateModified'     => $modified ? $modified : $published,
		'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $url ),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => 'Aravaipa Running',
			'url'   => home_url( '/' ),
		),
	);

	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	if ( '' !== $image ) {
		$node['image'] = $image;
	}

	echo arv_seo_schema_script( array( $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'arv_post_seo_head', 4 );

/**
 * Emit it.
 */
function arv_media_seo_head() {
	if ( ! function_exists( 'arv_seo_handled_elsewhere' ) || arv_seo_handled_elsewhere() ) {
		return;
	}

	$key = arv_media_seo_page();

	if ( '' === $key ) {
		return;
	}

	$meta = arv_media_seo_meta( $key );

	if ( '' === $meta['description'] ) {
		return;
	}

	$url = get_permalink( get_queried_object_id() );

	echo '<meta name="description" content="' . esc_attr( $meta['description'] ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $meta['title'] ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $meta['description'] ) . '" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $meta['type'] ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";

	echo arv_seo_schema_script( arv_media_seo_nodes( $key, $meta ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'arv_media_seo_head', 4 );
