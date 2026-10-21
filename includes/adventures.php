<?php
/**
 * Aravaipa Adventures: trips as a post type, rendered by this plugin.
 *
 * A trip is an `adventure` post at /adventures/<slug>/. Its title is the trip
 * name and its slug is the departure (madeira-2027). Everything else lives in
 * post meta: plain fields for the scalars (destination, dates, difficulty)
 * and JSON for the lists (overview, itinerary, inclusions, photos). The
 * landing is an ordinary page whose content is the [arv_adventures]
 * shortcode, so it can be moved, renamed or drafted like any other page.
 *
 * The design is the approved v3 draft (.scratch/aravaipa-adventures/site).
 * Only the body is ours: the theme's own header, breadcrumbs and footer wrap
 * it, and the stylesheet is scoped under .arv-adv and loaded only here.
 *
 * Nothing on this file's path touches an existing page. Without any
 * adventure posts and without a page carrying the shortcode it does nothing
 * a visitor can see.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bump when the rewrite rules this file registers change. */
define( 'ARV_ADV_REWRITE_VERSION', '2' );

/**
 * Meta keys, by kind. Scalars are sanitized as text (or int), JSON keys are
 * stored as a JSON string and decoded on read.
 *
 * @return array{scalar: array<string,string>, json: array<string,string>}
 */
function arv_adv_fields() {
	return array(
		'scalar' => array(
			'type'            => 'Trip type: Race, Training or Adventure',
			'destination'     => 'Destination (e.g. Madeira, Portugal)',
			'region'          => 'Region (e.g. Atlantic Ocean)',
			'subtitle'        => 'Subtitle under the trip name (optional)',
			'tagline'         => 'Tagline (the big statement on the page)',
			'length'          => 'Length (e.g. 8 days, 7 nights)',
			'group_size'      => 'Group size (a number, or TBD)',
			'min_group'       => 'Minimum travelers for the trip to run (optional)',
			'difficulty'      => 'Difficulty, 1 to 5',
			'difficulty_note' => 'Difficulty note',
			'dates'           => 'Dates as shown (e.g. June 2027)',
			'sort_year'       => 'Sort year (e.g. 2027)',
			'sort_month'      => 'Sort month, 1 to 12',
			'airport'         => 'Fly into (e.g. Funchal (FNC))',
			'host'            => 'Host: partner (Trail Run Adventures), aravaipa, or tbd',
			'price'           => 'Trip price (TBD until set)',
			'single_room'     => 'Single room line',
			'reg_opens'       => 'Registration opens',
			'gallery_credit'  => 'Gallery credit line',
			'note'            => 'Small note under the story (optional)',
			'fineprint'       => 'Fine print in the pricing panel (optional, replaces the default)',
		),
		'json'   => array(
			'overview'     => 'Overview paragraphs: ["First paragraph", "Second paragraph"]',
			'highlights'   => 'Highlights: ["One", "Two"]',
			'itinerary'    => 'Itinerary days: [{"title": "Arrive", "stats": "29.8 mi | 9,500 ft gain (optional)", "body": "What happens"}]',
			'included'     => 'Included: ["Seven nights ...", "..."]',
			'not_included' => 'Not included: ["Flights ...", "..."]',
			'race'         => 'Race (or {} for none): {"name": "", "url": "", "date": "", "blurb": "", "facts": [["Race Entry", "TBD"]]}',
			'photos'       => 'Photos: {"hero": {"id": 123, "alt": "", "credit": ""}, "intro": {...}, "form": {...}, "gallery": [{"id": 124, "alt": "", "credit": ""}]}',
		),
	);
}

/**
 * Register the post type.
 *
 * show_in_rest is off on purpose: the editing screen is the classic one with
 * the metaboxes from adventures-admin.php, which is the whole editing story
 * for v1, and the block editor would hide them behind a panel.
 */
function arv_adv_register() {
	register_post_type(
		'adventure',
		array(
			'labels'              => array(
				'name'               => 'Adventures',
				'singular_name'      => 'Adventure',
				'add_new_item'       => 'Add New Trip',
				'edit_item'          => 'Edit Trip',
				'new_item'           => 'New Trip',
				'view_item'          => 'View Trip',
				'search_items'       => 'Search Trips',
				'not_found'          => 'No trips found',
				'not_found_in_trash' => 'No trips found in Trash',
				'all_items'          => 'All Trips',
				'menu_name'          => 'Adventures',
			),
			'public'              => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-palmtree',
			'supports'            => array( 'title', 'revisions', 'page-attributes' ),
			'rewrite'             => array(
				'slug'       => 'adventures',
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
			),
		)
	);

	foreach ( arv_adv_fields() as $kind => $keys ) {
		foreach ( array_keys( $keys ) as $key ) {
			register_post_meta(
				'adventure',
				'_arv_adv_' . $key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

}
add_action( 'init', 'arv_adv_register' );

/**
 * Rewrite rules for a new post type only exist after a flush. Once per
 * version, soft (no .htaccess write), and on wp_loaded rather than inside
 * init: the 0.101.0 deploy flushed from init and the stored rules came out
 * without the adventure ones, so this waits until every plugin has finished
 * registering, then checks the rules really are there before recording it.
 */
function arv_adv_maybe_flush() {
	if ( get_option( 'arv_adv_rewrite_version' ) === ARV_ADV_REWRITE_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && isset( $rules['adventures/([^/]+)(?:/([0-9]+))?/?$'] ) ) {
		update_option( 'arv_adv_rewrite_version', ARV_ADV_REWRITE_VERSION, false );
	}
}
add_action( 'wp_loaded', 'arv_adv_maybe_flush' );

/**
 * Read one meta field. JSON fields come back decoded (array), scalars as
 * strings.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Field key without the prefix.
 * @return mixed
 */
function arv_adv_meta( $post_id, $key ) {
	$raw    = get_post_meta( $post_id, '_arv_adv_' . $key, true );
	$fields = arv_adv_fields();

	if ( isset( $fields['json'][ $key ] ) ) {
		$val = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $val ) ? $val : array();
	}

	return is_scalar( $raw ) ? (string) $raw : '';
}

/**
 * A trip as a plain array, the shape the templates read.
 *
 * @param WP_Post|int $post Post.
 * @return array
 */
function arv_adv_trip( $post ) {
	$post = get_post( $post );
	$trip = array(
		'id'     => $post->ID,
		'name'   => get_the_title( $post ),
		'slug'   => $post->post_name ? $post->post_name : (string) $post->ID,
		'url'    => get_permalink( $post ),
		'status' => $post->post_status,
		'order'  => (int) $post->menu_order,
	);

	foreach ( arv_adv_fields() as $kind => $keys ) {
		foreach ( array_keys( $keys ) as $key ) {
			$trip[ $key ] = arv_adv_meta( $post->ID, $key );
		}
	}

	$trip['difficulty'] = max( 1, min( 5, (int) $trip['difficulty'] ) );
	$trip['sort_year']  = (int) $trip['sort_year'];
	$trip['sort_month'] = (int) $trip['sort_month'];
	if ( ! in_array( $trip['type'], array( 'Race', 'Training', 'Adventure' ), true ) ) {
		$trip['type'] = 'Adventure';
	}
	if ( empty( $trip['race']['name'] ) ) {
		$trip['race'] = array();
	}
	foreach ( array( 'hero', 'intro', 'form' ) as $slot ) {
		if ( empty( $trip['photos'][ $slot ] ) || ! is_array( $trip['photos'][ $slot ] ) ) {
			$trip['photos'][ $slot ] = array();
		}
	}
	if ( empty( $trip['photos']['intro'] ) ) {
		$trip['photos']['intro'] = $trip['photos']['hero'];
	}
	if ( empty( $trip['photos']['form'] ) ) {
		$trip['photos']['form'] = $trip['photos']['hero'];
	}
	if ( empty( $trip['photos']['gallery'] ) || ! is_array( $trip['photos']['gallery'] ) ) {
		$trip['photos']['gallery'] = array();
	}
	foreach ( array( 'price', 'reg_opens' ) as $key ) {
		if ( '' === $trip[ $key ] ) {
			$trip[ $key ] = 'TBD';
		}
	}
	if ( '' === $trip['single_room'] ) {
		$trip['single_room'] = 'Optional, TBD';
	}

	return $trip;
}

/**
 * Every trip a visitor may see, in calendar order.
 *
 * Published trips for everyone. Logged-in editors also get drafts, pending
 * and scheduled trips, which is what lets the draft landing preview the
 * draft trips before anything is public.
 *
 * @param bool|null $with_unpublished Force including unpublished trips.
 * @return array<int, array>
 */
function arv_adv_trips( $with_unpublished = null ) {
	if ( null === $with_unpublished ) {
		$with_unpublished = current_user_can( 'edit_posts' );
	}

	$posts = get_posts(
		array(
			'post_type'        => 'adventure',
			'post_status'      => $with_unpublished ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : array( 'publish' ),
			'posts_per_page'   => 100,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
			'suppress_filters' => true,
		)
	);

	$trips = array_map( 'arv_adv_trip', $posts );

	usort(
		$trips,
		static function ( $a, $b ) {
			return array( $a['sort_year'], $a['sort_month'], $a['order'], $a['id'] )
				<=> array( $b['sort_year'], $b['sort_month'], $b['order'], $b['id'] );
		}
	);

	return $trips;
}

/**
 * Trip types: label, plural and the key used for badge colour and filter.
 *
 * @return array<string, array{label: string, plural: string, key: string}>
 */
function arv_adv_types() {
	return array(
		'Race'      => array(
			'label'  => 'Race Trip',
			'plural' => 'Race Trips',
			'key'    => 'race',
		),
		'Training'  => array(
			'label'  => 'Training Camp',
			'plural' => 'Training Camps',
			'key'    => 'training',
		),
		'Adventure' => array(
			'label'  => 'Adventure',
			'plural' => 'Adventures',
			'key'    => 'adventure',
		),
	);
}

/**
 * @param array $trip Trip.
 * @return array{label: string, plural: string, key: string}
 */
function arv_adv_type_of( $trip ) {
	$types = arv_adv_types();
	return isset( $types[ $trip['type'] ] ) ? $types[ $trip['type'] ] : $types['Adventure'];
}

/**
 * @param int $n 1 to 5.
 * @return string
 */
function arv_adv_difficulty_label( $n ) {
	$labels = array( '', 'Easy', 'Moderate', 'Moderate', 'Challenging', 'Expert' );
	return isset( $labels[ $n ] ) ? $labels[ $n ] : '';
}

/**
 * "Max 12 runners" for a number, "Group size TBD" otherwise.
 *
 * @param array $trip Trip.
 * @return string
 */
function arv_adv_group_label( $trip ) {
	return is_numeric( $trip['group_size'] ) ? 'Max ' . (int) $trip['group_size'] . ' runners' : 'Group size ' . $trip['group_size'];
}

/**
 * The landing page: the page whose ID the importer stored, else /adventures/.
 *
 * @return string URL.
 */
function arv_adv_landing_url() {
	$id = (int) get_option( 'arv_adventures_landing_id' );
	if ( $id && get_post( $id ) ) {
		return get_permalink( $id );
	}
	return home_url( '/adventures/' );
}

/**
 * Landing configuration (copy that is not per trip: FAQs, partner, photo
 * picks and credits), stored as one JSON option by the importer.
 *
 * @return array
 */
function arv_adv_landing_config() {
	$raw = get_option( 'arv_adventures_landing' );
	$val = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	return is_array( $val ) ? $val : array();
}

/**
 * URL for a photo object ({id, url}).
 *
 * @param array  $photo Photo.
 * @param string $size  Image size.
 * @return string
 */
function arv_adv_img_url( $photo, $size = 'full' ) {
	if ( ! empty( $photo['id'] ) ) {
		$url = wp_get_attachment_image_url( (int) $photo['id'], $size );
		if ( $url ) {
			return $url;
		}
	}
	return isset( $photo['url'] ) ? (string) $photo['url'] : '';
}

/**
 * An <img> for a photo object, with srcset when it is a library image.
 *
 * @param array  $photo Photo.
 * @param string $size  Image size.
 * @param array  $atts  Extra attributes (class, loading, sizes, alt).
 * @return string
 */
function arv_adv_img( $photo, $size = 'large', $atts = array() ) {
	$alt = isset( $atts['alt'] ) ? $atts['alt'] : ( isset( $photo['alt'] ) ? $photo['alt'] : '' );
	$atts = array_merge( array( 'loading' => 'lazy', 'decoding' => 'async' ), $atts, array( 'alt' => $alt ) );

	if ( 'eager' === $atts['loading'] ) {
		$atts['data-no-lazy'] = '1';
		unset( $atts['decoding'] );
	}

	if ( ! empty( $photo['id'] ) && wp_attachment_is_image( (int) $photo['id'] ) ) {
		return wp_get_attachment_image( (int) $photo['id'], $size, false, $atts );
	}

	$url = arv_adv_img_url( $photo, $size );
	if ( '' === $url ) {
		return '';
	}
	$out = '<img src="' . esc_url( $url ) . '"';
	foreach ( $atts as $k => $v ) {
		$out .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	return $out . ' />';
}

/**
 * A background-image style attribute value for a photo.
 *
 * @param array $photo Photo.
 * @return string
 */
function arv_adv_bg( $photo ) {
	$url = arv_adv_img_url( $photo, 'full' );
	return '' === $url ? '' : 'background-image:url(' . esc_url( $url ) . ')';
}

/**
 * Is this request an Adventures page (a trip, or the page with the landing
 * shortcode)? Drives the stylesheet, the body class and the logo swap.
 *
 * @return bool
 */
function arv_adv_is_adventures_page() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	if ( ! did_action( 'wp' ) ) {
		return false;
	}

	$memo = false;
	if ( is_singular( 'adventure' ) ) {
		$memo = true;
	} elseif ( is_page() ) {
		$post = get_queried_object();
		$memo = $post instanceof WP_Post
			&& ( (int) get_option( 'arv_adventures_landing_id' ) === (int) $post->ID || has_shortcode( $post->post_content, 'arv_adventures' ) );
	}
	return $memo;
}

/**
 * Our template for a single trip: the theme's header and footer around the
 * plugin's body, the same frame the theme's own blank page template uses.
 *
 * @param string $template Template the theme picked.
 * @return string
 */
function arv_adv_template( $template ) {
	if ( is_singular( 'adventure' ) ) {
		$ours = ARV_ELEMENTS_PATH . 'includes/templates/single-adventure.php';
		if ( file_exists( $ours ) ) {
			return $ours;
		}
	}
	return $template;
}
add_filter( 'template_include', 'arv_adv_template', 99 );

/**
 * Styles and the landing filter script, on Adventures pages only.
 */
function arv_adv_assets() {
	if ( ! arv_adv_is_adventures_page() ) {
		return;
	}
	wp_enqueue_style( 'aravaipa-adventures', ARV_ELEMENTS_URL . 'assets/aravaipa-adventures.css', array(), ARV_ELEMENTS_VERSION );
	wp_enqueue_script( 'aravaipa-adventures', ARV_ELEMENTS_URL . 'assets/aravaipa-adventures.js', array(), ARV_ELEMENTS_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'arv_adv_assets', 20 );

/**
 * Body class, which the logo swap and the theme overrides key on.
 *
 * @param array $classes Classes.
 * @return array
 */
function arv_adv_body_class( $classes ) {
	if ( arv_adv_is_adventures_page() ) {
		$classes[] = 'arv-adv-page';
	}
	return $classes;
}
add_filter( 'body_class', 'arv_adv_body_class' );

/**
 * Header logo swap. The X theme reads its logo through x_get_option, which
 * runs every option through an "x_option_<name>" filter, so on Adventures
 * pages the masthead gets the Aravaipa Adventures lockup and everywhere else
 * keeps the Aravaipa Running logo untouched. No markup or option is changed.
 *
 * @param mixed $value Logo option value.
 * @return mixed
 */
function arv_adv_logo( $value ) {
	if ( is_admin() || ! arv_adv_is_adventures_page() ) {
		return $value;
	}
	return ARV_ELEMENTS_URL . 'assets/adventures/aravaipa-adventures-logo.svg';
}
add_filter( 'x_option_x_logo', 'arv_adv_logo' );

/**
 * @param mixed $value Logo text option.
 * @return mixed
 */
function arv_adv_logo_text( $value ) {
	if ( is_admin() || ! arv_adv_is_adventures_page() ) {
		return $value;
	}
	return 'Aravaipa Adventures';
}
add_filter( 'x_option_x_logo_text', 'arv_adv_logo_text' );

/**
 * Keep anything that is not published out of search engines, belt and
 * braces on top of WordPress refusing drafts to logged-out visitors.
 * Covers draft trips and the landing while it is a draft.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function arv_adv_robots( $robots ) {
	if ( arv_adv_is_adventures_page() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && 'publish' !== $post->post_status ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
	}
	return $robots;
}
add_filter( 'wp_robots', 'arv_adv_robots' );

/**
 * Meta description on a trip, from its tagline.
 */
function arv_adv_meta_description() {
	if ( ! is_singular( 'adventure' ) ) {
		return;
	}
	$tagline = arv_adv_meta( get_queried_object_id(), 'tagline' );
	if ( '' !== $tagline ) {
		echo '<meta name="description" content="' . esc_attr( $tagline ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'arv_adv_meta_description', 2 );

/**
 * Share cards. A trip's card is the attachment in _arv_adv_og_card; the
 * landing's is the arv_adventures_og_card option. Both are 1200x630 images
 * with the Adventures logo baked in, so iMessage, Slack and social previews
 * show the brand rather than whatever photo Jetpack guesses.
 */
function arv_adv_share_context() {
	$landing_id = (int) get_option( 'arv_adventures_landing_id' );
	if ( $landing_id && is_page( $landing_id ) ) {
		return array(
			'card'  => (int) get_option( 'arv_adventures_og_card' ),
			'title' => 'Aravaipa Adventures',
			'desc'  => 'Race trips, training camps, and guided trail running adventures, from Arizona to Patagonia.',
		);
	}
	if ( is_singular( 'adventure' ) ) {
		$id = get_queried_object_id();
		return array(
			'card'  => (int) get_post_meta( $id, '_arv_adv_og_card', true ),
			'title' => get_the_title( $id ) . ' | Aravaipa Adventures',
			'desc'  => arv_adv_meta( $id, 'tagline' ),
		);
	}
	return null;
}

function arv_adv_og_tags( $tags ) {
	$ctx = arv_adv_share_context();
	if ( ! $ctx ) {
		return $tags;
	}
	$tags['og:title']           = $ctx['title'];
	$tags['twitter:text:title'] = $ctx['title'];
	$tags['og:site_name']       = 'Aravaipa Adventures';
	if ( '' !== $ctx['desc'] ) {
		$tags['og:description']      = $ctx['desc'];
		$tags['twitter:description'] = $ctx['desc'];
	}
	$url = $ctx['card'] ? wp_get_attachment_url( $ctx['card'] ) : '';
	if ( $url ) {
		$tags['og:image']            = $url;
		$tags['og:image:width']      = 1200;
		$tags['og:image:height']     = 630;
		$tags['og:image:alt']        = $ctx['title'];
		$tags['twitter:image']       = $url;
		$tags['twitter:image:alt']   = $ctx['title'];
	}
	return $tags;
}
add_filter( 'jetpack_open_graph_tags', 'arv_adv_og_tags', 99 );

function arv_adv_title_parts( $parts ) {
	$landing_id = (int) get_option( 'arv_adventures_landing_id' );
	if ( $landing_id && is_page( $landing_id ) ) {
		$parts['title'] = 'Aravaipa Adventures';
		unset( $parts['site'] );
	} elseif ( is_singular( 'adventure' ) ) {
		$parts['site'] = 'Aravaipa Adventures';
	}
	return $parts;
}
add_filter( 'document_title_parts', 'arv_adv_title_parts', 99 );

/**
 * Breadcrumbs on a trip: Home > Adventures > Trip, in the theme's own markup.
 *
 * @param string $out  Breadcrumb HTML from earlier filters.
 * @param array  $args The theme's breadcrumb args.
 * @return string
 */
function arv_adv_breadcrumbs( $out, $args ) {
	if ( ! is_singular( 'adventure' ) || ! is_array( $args ) ) {
		return $out;
	}

	$a     = wp_parse_args(
		$args,
		array(
			'item_before'      => '<span>',
			'item_after'       => '</span>',
			'label_before'     => '<span>',
			'label_after'      => '</span>',
			'delimiter_before' => ' <span class="delimiter">',
			'delimiter_after'  => '</span> ',
			'delimiter_ltr'    => '&rsaquo;',
			'home_label'       => 'Home',
		)
	);
	$items = array(
		array( home_url( '/' ), $a['home_label'], true ),
		array( arv_adv_landing_url(), esc_html__( 'Adventures', 'aravaipa-elements' ), false ),
		array( get_permalink(), esc_html( get_the_title() ), false ),
	);

	$html = '';
	$last = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		$current = $i === $last;
		$html   .= $a['item_before']
			. '<a itemtype="http://schema.org/Thing" itemprop="item" href="' . esc_url( $item[0] ) . '"' . ( $current ? ' title="You Are Here" class="current "' : ' class=""' ) . '>'
			. $a['label_before'] . $item[1] . $a['label_after'] . '</a>'
			. ( $current ? '' : $a['delimiter_before'] . $a['delimiter_ltr'] . $a['delimiter_after'] )
			. '<meta itemprop="position" content="' . ( $i + 1 ) . '">'
			. $a['item_after'];
	}
	return $html;
}
add_filter( 'x_breadcrumbs', 'arv_adv_breadcrumbs', 99, 2 );

/**
 * The landing shortcode.
 *
 * @return string
 */
function arv_adv_shortcode() {
	if ( ! function_exists( 'arv_adv_render_landing' ) ) {
		return '';
	}
	return arv_adv_render_landing();
}
add_shortcode( 'arv_adventures', 'arv_adv_shortcode' );
