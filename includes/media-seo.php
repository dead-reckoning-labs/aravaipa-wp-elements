<?php
/**
 * Descriptions and structured data for the five pages that had neither.
 *
 * Watch, Films, Podcasts and the race pages each grew their own head
 * output as they were built. /media/, /film-tours/, /articles/, /photos/
 * and /shop/ did not, so they shipped with no meta description and no
 * structured data at all: Google wrote its own snippet for each from
 * whatever text it found first, which on a page that opens with a nav
 * strip is usually the nav strip.
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
 * by a stored id. A page that gets renamed, moved under a different
 * parent, or rebuilt keeps working, and the recogniser cannot drift out of
 * step with what the page actually renders.
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

	if ( ! $post || empty( $post->post_content ) ) {
		return '';
	}

	// Ordered most specific first: /media/ carries [arv_media_hub], and
	// nothing else does, but checking a looser pattern earlier would match
	// the wrong page.
	$shortcodes = array(
		'film-tours' => 'arv_film_tours',
		'shop'       => 'arv_shop',
		'articles'   => 'arv_articles',
		'photos'     => 'arv_photos',
		'media'      => 'arv_media_hub',
	);

	foreach ( $shortcodes as $key => $tag ) {
		if ( has_shortcode( $post->post_content, $tag ) ) {
			return $key;
		}
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
