<?php
/**
 * The shop: Aravaipa's Square catalogue, on Aravaipa's own site.
 *
 * /shop/ was a 404. The site's own SHOP nav item pointed straight at
 * aravaipa-shop.square.site, so every shop click left the domain with no
 * landing page, no internal linking and no analytics behind it. Worse,
 * ninety of Square's collections are named after races that already have
 * pages here, and none of those pages sold anything.
 *
 * An option rather than a live API call, exactly like the results, race and
 * photo stores, and for the same three reasons. The Square access token
 * stays on the machine that runs scripts/fetch-shop.mjs and never reaches
 * the web server, which is the whole reason to prefer this shape for a
 * credential that can read a merchant account. Rendering never waits on
 * Square. And an outage at Square cannot empty the shop page, because
 * nothing is fetched at render time to fail.
 *
 * Every URL here was verified live by the importer before it was stored.
 * That is not belt and braces: Square's catalogue happily reports items as
 * ecom_visibility VISIBLE that have no storefront page at all, and
 * constructing a product URL from the catalogue id alone produces a link
 * that returns HTTP 200 and shows the shop's front door. A card that lies
 * about where it goes is worse than no card, so the importer keeps only
 * what it could confirm against the storefront's own sitemap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_SHOP_OPTION', 'arv_shop_catalog' );

/**
 * Everything stored: collections and products.
 *
 * @return array{collections: array, products: array}
 */
function arv_shop_get() {
	$stored = get_option( ARV_SHOP_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		return array( 'collections' => array(), 'products' => array() );
	}

	return array(
		'collections' => isset( $stored['collections'] ) && is_array( $stored['collections'] ) ? $stored['collections'] : array(),
		'products'    => isset( $stored['products'] ) && is_array( $stored['products'] ) ? $stored['products'] : array(),
	);
}

/**
 * The collections that belong to a race, rather than to a department.
 *
 * Square's categories mix two different things: races (Black Canyon
 * Ultras, Javelina Jundred, Mogollon Monster) and departments (Men's,
 * Women's, Headwear, Accessories). Only the first kind can be matched to a
 * race page, and the importer marks them, so this is a filter rather than
 * a guess made at render time.
 *
 * @return array<int, array>
 */
function arv_shop_race_collections() {
	$out = array();

	foreach ( arv_shop_get()['collections'] as $collection ) {
		if ( ! empty( $collection['race'] ) ) {
			$out[] = $collection;
		}
	}

	return $out;
}

/**
 * The collection matching a race name, or null.
 *
 * Joined on arv_results_race_key(), the same normaliser the results
 * archive, the Watch index and the Films race tags all use, so "Black
 * Canyon Ultras 2026", "Black Canyon Ultras" and "Black Canyon 100K" all
 * reach the same collection without a hand-written mapping table.
 *
 * @param string $race
 * @return array|null
 */
function arv_shop_collection_for_race( $race ) {
	$race = trim( (string) $race );

	if ( '' === $race || ! function_exists( 'arv_results_race_key' ) ) {
		return null;
	}

	$key = arv_results_race_key( $race );

	if ( '' === $key ) {
		return null;
	}

	foreach ( arv_shop_race_collections() as $collection ) {
		if ( arv_results_race_key( $collection['name'] ) === $key ) {
			return $collection;
		}
	}

	return null;
}

/**
 * The products in a collection, newest-looking first.
 *
 * Sold-out items sink rather than disappear. A race whose gear has all
 * sold shows that it sold, which is a truer thing to say on that race's
 * page than showing nothing at all, and it is the state a returning
 * runner most wants to see corrected next season.
 *
 * @param string $collection_id
 * @param int    $limit 0 for everything.
 * @return array<int, array>
 */
function arv_shop_products_in( $collection_id, $limit = 0 ) {
	$in = array();

	foreach ( arv_shop_get()['products'] as $product ) {
		if ( in_array( $collection_id, (array) $product['collections'], true ) ) {
			$in[] = $product;
		}
	}

	usort(
		$in,
		function ( $a, $b ) {
			if ( $a['sold_out'] !== $b['sold_out'] ) {
				return $a['sold_out'] ? 1 : -1;
			}

			// Index-stabilised: usort is only stable from PHP 8.0 and this
			// plugin supports 7.4, so equal rows would otherwise shuffle
			// between renders on the older runtime.
			return $a['ord'] - $b['ord'];
		}
	);

	return $limit > 0 ? array_slice( $in, 0, $limit ) : $in;
}

/**
 * A price, as a person would read it.
 *
 * Whole dollars lose the trailing zeros: a wall of "$40.00" reads as a
 * spreadsheet, "$40" reads as a price tag.
 *
 * @param int $cents
 * @return string
 */
function arv_shop_price( $cents ) {
	$cents = (int) $cents;

	if ( $cents <= 0 ) {
		return '';
	}

	return ( 0 === $cents % 100 )
		? '$' . number_format_i18n( $cents / 100 )
		: '$' . number_format_i18n( $cents / 100, 2 );
}

/**
 * One product card.
 *
 * @param array $product
 * @return string
 */
function arv_shop_card( $product ) {
	$out = '<li class="arv-shop__card' . ( $product['sold_out'] ? ' arv-shop__card--out' : '' ) . '">';
	$out .= '<a class="arv-shop__link" href="' . esc_url( $product['url'] ) . '"'
		. ' target="_blank" rel="noopener">';

	if ( '' !== $product['image'] ) {
		$out .= '<span class="arv-shop__shot">';
		$out .= '<img class="arv-shop__img" src="' . esc_url( $product['image'] ) . '" alt=""'
			. ' loading="lazy" decoding="async" width="400" height="400" />';

		if ( $product['sold_out'] ) {
			$out .= '<span class="arv-shop__flag">' . esc_html__( 'Sold out', 'aravaipa-elements' ) . '</span>';
		}

		$out .= '</span>';
	}

	$out .= '<span class="arv-shop__body">';
	$out .= '<span class="arv-shop__name">' . esc_html( $product['name'] ) . '</span>';

	$price = arv_shop_price( $product['price'] );

	if ( '' !== $price ) {
		$out .= '<span class="arv-shop__price">' . esc_html( $price ) . '</span>';
	}

	return $out . '</span></a></li>';
}

/**
 * The merch strip for one race.
 *
 * Renders nothing at all when the race has no collection or the collection
 * has no products, which is the common case for most of the calendar and
 * has to stay silent rather than leave an empty shelf on a race page.
 *
 * @param array $args race, limit, heading.
 * @return string
 */
function arv_shop_race_merch_render( $args = array() ) {
	$race = isset( $args['race'] ) ? (string) $args['race'] : '';
	$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 4;

	$collection = arv_shop_collection_for_race( $race );

	if ( null === $collection ) {
		return '';
	}

	$products = arv_shop_products_in( $collection['id'], $limit );

	if ( empty( $products ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) && '' !== trim( (string) $args['heading'] )
		? (string) $args['heading']
		/* translators: %s: a race name. */
		: sprintf( __( '%s gear', 'aravaipa-elements' ), $collection['name'] );

	$out  = '<section class="arv-shop arv-shop--strip">';
	$out .= '<div class="arv-shop__inner">';
	$out .= '<div class="arv-shop__head">';
	$out .= '<h2 class="arv-shop__heading">' . esc_html( $heading ) . '</h2>';
	$out .= '<a class="arv-shop__all" href="' . esc_url( $collection['url'] ) . '"'
		. ' target="_blank" rel="noopener">' . esc_html__( 'Shop all', 'aravaipa-elements' ) . '</a>';
	$out .= '</div>';

	$out .= '<ul class="arv-shop__grid">';

	foreach ( $products as $product ) {
		$out .= arv_shop_card( $product );
	}

	return $out . '</ul></div></section>';
}

/**
 * The shop index: every race collection, then the departments.
 *
 * @param array $args heading, intro.
 * @return string
 */
function arv_shop_render( $args = array() ) {
	$shop = arv_shop_get();

	if ( empty( $shop['collections'] ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Shop';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';

	$races = array();
	$depts = array();

	foreach ( $shop['collections'] as $collection ) {
		if ( empty( $collection['count'] ) ) {
			continue;
		}

		if ( ! empty( $collection['race'] ) ) {
			$races[] = $collection;
		} else {
			$depts[] = $collection;
		}
	}

	$out  = '<section class="arv-shop">';
	$out .= '<div class="arv-shop__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-shop__heading arv-shop__heading--page">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-shop__intro">' . esc_html( $intro ) . '</p>';
	}

	// Departments first: someone who arrived wanting a hat rather than a
	// particular race's hat has the shorter path, and there are six of
	// these against seventy of the other kind.
	$out .= arv_shop_collection_row( $depts, __( 'Browse', 'aravaipa-elements' ), 'dept' );
	$out .= arv_shop_collection_row( $races, __( 'By race', 'aravaipa-elements' ), 'race' );

	return $out . '</div></section>';
}

/**
 * A labelled row of collection tiles.
 *
 * @param array  $collections
 * @param string $label
 * @param string $kind
 * @return string
 */
function arv_shop_collection_row( $collections, $label, $kind ) {
	if ( empty( $collections ) ) {
		return '';
	}

	$out = '<h3 class="arv-shop__section">' . esc_html( $label ) . '</h3>';
	$out .= '<ul class="arv-shop__collections arv-shop__collections--' . esc_attr( $kind ) . '">';

	foreach ( $collections as $collection ) {
		$out .= '<li class="arv-shop__collection">';
		$out .= '<a class="arv-shop__collection-link" href="' . esc_url( $collection['url'] ) . '"'
			. ' target="_blank" rel="noopener">';

		if ( '' !== $collection['image'] ) {
			$out .= '<img class="arv-shop__collection-art" src="' . esc_url( $collection['image'] ) . '" alt=""'
				. ' loading="lazy" decoding="async" width="300" height="300" />';
		}

		$out .= '<span class="arv-shop__collection-name">' . esc_html( $collection['name'] ) . '</span>';
		$out .= '<span class="arv-shop__collection-count">'
			. esc_html(
				sprintf(
					/* translators: %s: a count of products. */
					_n( '%s item', '%s items', $collection['count'], 'aravaipa-elements' ),
					number_format_i18n( $collection['count'] )
				)
			)
			. '</span>';
		$out .= '</a></li>';
	}

	return $out . '</ul>';
}

/**
 * [arv_shop] for the shop page.
 *
 * @param array $atts
 * @return string
 */
function arv_shop_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Shop',
			'intro'   => '',
		),
		$atts,
		'arv_shop'
	);

	return arv_shop_render( $atts );
}
add_shortcode( 'arv_shop', 'arv_shop_shortcode' );

/**
 * [arv_race_merch race="Black Canyon Ultras"] for a race page.
 *
 * @param array $atts
 * @return string
 */
function arv_shop_race_merch_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'race'    => '',
			'limit'   => 4,
			'heading' => '',
		),
		$atts,
		'arv_race_merch'
	);

	return arv_shop_race_merch_render( $atts );
}
add_shortcode( 'arv_race_merch', 'arv_shop_race_merch_shortcode' );

/**
 * Replace the stored catalogue.
 *
 * @param array $payload collections and products.
 * @return array{collections: int, products: int}
 */
function arv_shop_set( $payload ) {
	$collections = array();
	$products    = array();
	$live        = array();

	foreach ( (array) ( isset( $payload['collections'] ) ? $payload['collections'] : array() ) as $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['name'] ) || empty( $row['url'] ) ) {
			continue;
		}

		$url = arv_shop_clean_url( $row['url'] );

		if ( '' === $url ) {
			continue;
		}

		$id = sanitize_text_field( (string) $row['id'] );

		$collections[] = array(
			'id'    => $id,
			'name'  => sanitize_text_field( (string) $row['name'] ),
			'url'   => $url,
			'image' => arv_shop_clean_url( isset( $row['image'] ) ? $row['image'] : '' ),
			'count' => isset( $row['count'] ) ? (int) $row['count'] : 0,
			'race'  => ! empty( $row['race'] ),
		);

		$live[ $id ] = true;
	}

	$ord = 0;

	foreach ( (array) ( isset( $payload['products'] ) ? $payload['products'] : array() ) as $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['name'] ) || empty( $row['url'] ) ) {
			continue;
		}

		$url = arv_shop_clean_url( $row['url'] );

		if ( '' === $url ) {
			continue;
		}

		// Only collections that survived above: a product pointing at a
		// collection this store dropped would be unreachable from the shop
		// page and would still claim membership on a race page.
		$in = array();

		foreach ( (array) ( isset( $row['collections'] ) ? $row['collections'] : array() ) as $cid ) {
			$cid = sanitize_text_field( (string) $cid );

			if ( isset( $live[ $cid ] ) ) {
				$in[] = $cid;
			}
		}

		$products[] = array(
			'id'          => sanitize_text_field( (string) $row['id'] ),
			'name'        => sanitize_text_field( (string) $row['name'] ),
			'url'         => $url,
			'image'       => arv_shop_clean_url( isset( $row['image'] ) ? $row['image'] : '' ),
			'price'       => isset( $row['price'] ) ? (int) $row['price'] : 0,
			'sold_out'    => ! empty( $row['sold_out'] ),
			'collections' => $in,
			'ord'         => $ord++,
		);
	}

	update_option( ARV_SHOP_OPTION, array( 'collections' => $collections, 'products' => $products ), false );

	return array( 'collections' => count( $collections ), 'products' => count( $products ) );
}

/**
 * An http(s) URL, or ''.
 *
 * These arrive from another company's API and end up in an href and an
 * img src. A javascript: or data: value has no business reaching either,
 * the same rule the photo importer applies to scraped gallery links.
 *
 * @param string $url
 * @return string
 */
function arv_shop_clean_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ) );

	return ( '' !== $url && preg_match( '#^https?://#i', $url ) ) ? $url : '';
}

/**
 * Write route for scripts/fetch-shop.mjs.
 *
 * Same edit_posts scoping as the results, race and photo importers, so it
 * is reachable by the Editor-scoped Application Password those scripts run
 * as and by nothing with more reach.
 */
function arv_shop_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/shop/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_shop_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'arv_shop_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/shop/import
 *
 * Guarded the way the photo importer is, and for the same reason: this
 * replaces everything, so a Square outage or a half-parsed sitemap that
 * yielded nine products must not be allowed to wipe two hundred.
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_shop_rest_set( $request ) {
	$body    = $request->get_json_params();
	$payload = array(
		'collections' => isset( $body['collections'] ) && is_array( $body['collections'] ) ? $body['collections'] : array(),
		'products'    => isset( $body['products'] ) && is_array( $body['products'] ) ? $body['products'] : array(),
	);

	$current = count( arv_shop_get()['products'] );
	$valid   = 0;

	foreach ( $payload['products'] as $row ) {
		if ( is_array( $row ) && ! empty( $row['id'] ) && ! empty( $row['url'] ) ) {
			$valid++;
		}
	}

	if ( empty( $body['force'] ) && $current > 0 && $valid < ( $current * 0.8 ) ) {
		return array(
			'status'  => 'refused',
			'reason'  => 'would drop more than 20% of stored products',
			'current' => $current,
			'valid'   => $valid,
		);
	}

	if ( ! empty( $body['dry_run'] ) ) {
		return array( 'status' => 'dry_run', 'current' => $current, 'valid' => $valid );
	}

	return array( 'status' => 'ok' ) + arv_shop_set( $payload );
}
