<?php
/**
 * Aravaipa Adventures after Jay's third note (Nov 16), without WordPress:
 * no finisher medal or closing ceremony anywhere, the Unmistakably Aravaipa
 * card about the experience, community and fun, the masthead logo linking
 * to the Adventures home on Adventures pages only, New Zealand at $4,695,
 * Patagonia at $4,895 for October 2027, 8 days and 7 nights with the
 * itinerary and trip details marked coming soon, race entry included on
 * every race trip, Slovenia on September 12-19, 2027, and the new Slovenia
 * and Madeira hero photos with their credits.
 *
 * Run: php scripts/test-adventures-jay3.php [data.json [outdir]]
 *
 * data.json  also check real data ({"trips": [...], "config": {...}} as in
 *            scripts/test-adventures-camps.php).
 * outdir     write outdir/adventures.html and outdir/<slug>.html from it.
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_URL', 'https://www.aravaiparunning.com/wp-content/plugins/aravaipa-elements/' );
define( 'ARV_ELEMENTS_VERSION', 'test' );
define( 'ARV_ELEMENTS_PATH', dirname( __DIR__ ) . '/' );

$GLOBALS['t_posts']   = array();
$GLOBALS['t_options'] = array();
$GLOBALS['t_editor']  = false;
$GLOBALS['t_is_page'] = false;

function add_action() {}
function did_action() {
	return 0;
}
function add_filter() {}
function add_shortcode() {}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function wp_kses_post( $s ) {
	return (string) $s;
}
function home_url( $path = '/' ) {
	return 'https://www.aravaiparunning.com' . $path;
}
function get_option( $key ) {
	return isset( $GLOBALS['t_options'][ $key ] ) ? $GLOBALS['t_options'][ $key ] : false;
}
function current_user_can() {
	return $GLOBALS['t_editor'];
}
function is_page() {
	return $GLOBALS['t_is_page'];
}
function is_singular() {
	return false;
}
function get_post( $p ) {
	if ( is_object( $p ) ) {
		return $p;
	}
	if ( 86050 === (int) $p ) {
		return (object) array( 'ID' => 86050, 'post_name' => 'adventures', 'post_status' => 'publish', 'post_type' => 'page' );
	}
	foreach ( $GLOBALS['t_posts'] as $post ) {
		if ( (int) $post->ID === (int) $p ) {
			return $post;
		}
	}
	return null;
}
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['t_posts'] as $post ) {
		if ( in_array( $post->post_status, (array) $args['post_status'], true ) ) {
			$out[] = $post;
		}
	}
	return $out;
}
function get_post_meta( $id, $key ) {
	$post = get_post( $id );
	return $post && isset( $post->meta[ $key ] ) ? $post->meta[ $key ] : '';
}
function get_the_title( $post ) {
	return get_post( $post )->post_title;
}
function get_permalink( $post ) {
	$post = get_post( $post );
	return 86050 === (int) $post->ID ? home_url( '/adventures/' ) : home_url( '/adventures/' . $post->post_name . '/' );
}
function wp_get_attachment_image_url( $id, $size ) {
	$suffix = array(
		'thumbnail' => '-150x150',
		'large'     => '-1024x683',
	);
	return 'https://www.aravaiparunning.com/wp-content/uploads/2026/11/p' . (int) $id . ( isset( $suffix[ $size ] ) ? $suffix[ $size ] : '' ) . '.jpg';
}
function wp_attachment_is_image( $id ) {
	return $id > 0;
}
function wp_get_attachment_image( $id, $size, $icon, $atts ) {
	$o = '<img src="' . esc_url( wp_get_attachment_image_url( $id, $size ) ) . '"';
	foreach ( $atts as $k => $v ) {
		$o .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	return $o . ' />';
}

require __DIR__ . '/../includes/adventures.php';
require __DIR__ . '/../includes/adventures-render.php';
require __DIR__ . '/../includes/adventures-form.php';

$fails = 0;
function check( $label, $ok, $why = '' ) {
	global $fails;
	if ( ! $ok ) {
		++$fails;
		echo 'FAIL ' . $label . ( '' !== $why ? ': ' . $why : '' ) . "\n";
	} else {
		echo 'ok ' . $label . "\n";
	}
}

function load_data( $trips, $config ) {
	$GLOBALS['t_posts'] = array();
	foreach ( $trips as $t ) {
		$GLOBALS['t_posts'][] = (object) array(
			'ID'          => (int) $t['ID'],
			'post_name'   => $t['post_name'],
			'post_status' => $t['post_status'],
			'post_title'  => $t['post_title'],
			'menu_order'  => isset( $t['menu_order'] ) ? (int) $t['menu_order'] : 0,
			'meta'        => $t['meta'],
		);
	}
	$GLOBALS['t_options'] = array(
		'arv_adventures_landing'    => json_encode( $config ),
		'arv_adventures_landing_id' => 86050,
	);
}

function ph( $id, $alt = '' ) {
	return array( 'id' => $id, 'alt' => $alt );
}

/** Text of a page as a visitor reads it. */
function plain( $html ) {
	return html_entity_decode( strip_tags( $html ), ENT_QUOTES );
}

/** The <dd> after a <dt> label in a page. */
function dd( $html, $label ) {
	return preg_match( '#<dt>' . preg_quote( $label, '#' ) . '</dt><dd>(.*?)</dd>#s', $html, $m ) ? plain( $m[1] ) : null;
}

/** Items of the Included or Not Included list. */
function inc_list( $html, $class ) {
	if ( ! preg_match( '#<ul class="aa-inc-list ' . $class . '">(.*?)</ul>#s', $html, $m ) ) {
		return array();
	}
	preg_match_all( '#<li>(.*?)</li>#s', $m[1], $li );
	return array_map( 'plain', $li[1] );
}

/** Credit ids listed in the Photo Credits section. */
function credit_ids( $html ) {
	preg_match_all( '/<li data-photo="(\d+)"/', $html, $m );
	return array_map( 'intval', $m[1] );
}

// Fixture: the four published trips as round 3 leaves them (prices, dates,
// photos), one draft, and the landing config with the new FAQ answer.
$slo_hero = 87101;
$mad_hero = 87102;
$base     = array(
	'_arv_adv_group_size'      => '12',
	'_arv_adv_difficulty'      => '3',
	'_arv_adv_difficulty_note' => 'Steady days.',
	'_arv_adv_airport'         => 'Somewhere (XXX)',
	'_arv_adv_tagline'         => 'Run somewhere beautiful.',
	'_arv_adv_overview'        => json_encode( array( 'A week of trail with a small group.' ) ),
	'_arv_adv_highlights'      => json_encode( array( 'Big views', 'Good food' ) ),
	'_arv_adv_not_included'    => json_encode( array( 'International flights', 'Travel and medical insurance (required)' ) ),
	'_arv_adv_reg_opens'       => 'Open now',
	'_arv_adv_single_room'     => 'Available on request',
);
$days  = json_encode(
	array(
		array( 'title' => 'Arrive', 'body' => 'Welcome run and dinner.' ),
		array( 'title' => 'Big day', 'body' => 'The long run.' ),
	)
);
$trips = array(
	array(
		'ID'          => 86000,
		'post_name'   => 'madeira-2027',
		'post_status' => 'publish',
		'post_title'  => 'Island in the Sky',
		'meta'        => $base + array(
			'_arv_adv_type'        => 'Race',
			'_arv_adv_destination' => 'Madeira, Portugal',
			'_arv_adv_dates'       => 'June 6-13, 2027',
			'_arv_adv_sort_year'   => '2027',
			'_arv_adv_sort_month'  => '6',
			'_arv_adv_length'      => '8 days, 7 nights',
			'_arv_adv_price'       => '$3,895 per person',
			'_arv_adv_book_url'    => 'https://www.wetravel.com/trips/madeira',
			'_arv_adv_itinerary'   => $days,
			'_arv_adv_included'    => json_encode( array( 'Seven nights lodging, shared room', 'Ultra Skyrunning Madeira race registration, any distance' ) ),
			'_arv_adv_race'        => json_encode( array( 'name' => 'Ultra Skyrunning Madeira', 'url' => 'https://www.ultraskymarathon.com/', 'date' => 'June 2027', 'blurb' => 'Skyrunning on the island.', 'facts' => array( array( 'Race Entry', 'Included, any distance' ) ) ) ),
			'_arv_adv_photos'      => json_encode( array( 'hero' => ph( $mad_hero, 'Hiker on the PR1 ridge' ), 'gallery' => array( ph( 86002 ) ) ) ),
		),
	),
	array(
		'ID'          => 86008,
		'post_name'   => 'slovenia-2027',
		'post_status' => 'publish',
		'post_title'  => 'Julian Alps Traverse',
		'meta'        => $base + array(
			'_arv_adv_type'        => 'Adventure',
			'_arv_adv_destination' => 'Julian Alps, Slovenia',
			'_arv_adv_dates'       => 'September 12-19, 2027',
			'_arv_adv_sort_year'   => '2027',
			'_arv_adv_sort_month'  => '9',
			'_arv_adv_length'      => '8 days, 7 nights',
			'_arv_adv_price'       => '$5,595 per person',
			'_arv_adv_book_url'    => 'https://www.wetravel.com/trips/slovenia',
			'_arv_adv_itinerary'   => $days,
			'_arv_adv_included'    => json_encode( array( 'Seven nights in mountain huts and guesthouses' ) ),
			'_arv_adv_photos'      => json_encode( array( 'hero' => ph( $slo_hero, 'Lake Bohinj in summer' ), 'gallery' => array( ph( 86010 ) ) ) ),
		),
	),
	array(
		'ID'          => 86024,
		'post_name'   => 'patagonia-2027',
		'post_status' => 'publish',
		'post_title'  => 'Fitz Roy Frontier',
		'meta'        => $base + array(
			'_arv_adv_type'         => 'Race',
			'_arv_adv_destination'  => 'El Chaltén, Argentina',
			'_arv_adv_dates'        => 'October 2027',
			'_arv_adv_sort_year'    => '2027',
			'_arv_adv_sort_month'   => '10',
			'_arv_adv_length'       => '8 days, 7 nights',
			'_arv_adv_price'        => '$4,895 per person',
			'_arv_adv_book_url'     => 'https://www.wetravel.com/trips/patagonia',
			'_arv_adv_details_soon' => 'Itinerary and trip details coming soon.',
			'_arv_adv_itinerary'    => json_encode( array( array( 'title' => 'Arrive in El Calafate', 'body' => 'Old seven day plan.' ) ) ),
			'_arv_adv_included'     => json_encode( array( 'Ultra Fitz Roy race registration, your choice of distance' ) ),
			'_arv_adv_race'         => json_encode( array( 'name' => 'Ultra Fitz Roy', 'url' => 'https://ultrafitzroy.com/', 'date' => 'October 2027', 'blurb' => 'Race week in El Chaltén.' ) ),
			'_arv_adv_photos'       => json_encode( array( 'hero' => ph( 86025 ), 'gallery' => array( ph( 86026 ) ) ) ),
		),
	),
	array(
		'ID'          => 86028,
		'post_name'   => 'new-zealand-2028',
		'post_status' => 'publish',
		'post_title'  => 'South Island Odyssey',
		'meta'        => $base + array(
			'_arv_adv_type'        => 'Adventure',
			'_arv_adv_destination' => 'South Island, New Zealand',
			'_arv_adv_dates'       => 'December 5-12, 2027',
			'_arv_adv_sort_year'   => '2027',
			'_arv_adv_sort_month'  => '12',
			'_arv_adv_length'      => '8 days, 7 nights',
			'_arv_adv_price'       => '$4,695 per person',
			'_arv_adv_book_url'    => 'https://www.wetravel.com/trips/nz',
			'_arv_adv_itinerary'   => $days,
			'_arv_adv_included'    => json_encode( array( 'Seven nights lodging' ) ),
			'_arv_adv_photos'      => json_encode( array( 'hero' => ph( 86029 ), 'gallery' => array( ph( 86030 ) ) ) ),
		),
	),
	array(
		'ID'          => 86046,
		'post_name'   => 'patagonia-2028',
		'post_status' => 'draft',
		'post_title'  => 'Fitz Roy Frontier',
		'meta'        => $base + array( '_arv_adv_type' => 'Race', '_arv_adv_sort_year' => '2028', '_arv_adv_photos' => json_encode( array( 'hero' => ph( 86027 ) ) ) ),
	),
);
$credit = static function ( $id, $label, $html ) {
	return array( 'photo' => array( 'id' => $id ), 'label' => $label, 'html' => $html );
};
$config = array(
	'hero'          => ph( 86025, 'Fitz Roy at sunrise' ),
	'pano'          => ph( 86003, 'Ridgeline trail above the clouds on Madeira' ),
	'faqs'          => array(
		array( 'Do I have to run the race on race trips?', 'Most people come for the race, and race registration is included in the trip price on every race trip.' ),
	),
	'credits'       => array(
		$credit( $slo_hero, 'Julian Alps Traverse hero', 'WillCalderwood, Public domain, via Wikimedia Commons' ),
		$credit( $mad_hero, 'Island in the Sky hero', 'Krzysztof Popławski, CC BY 4.0, via Wikimedia Commons' ),
		$credit( 86025, 'Adventures hero', 'Trail Run Adventures' ),
	),
	'credits_intro' => 'Trip photography is from Trail Run Adventures, our trip partner.',
);

/**
 * Every round 3 check on one data set.
 *
 * @param string $tag    Label prefix ('' for the fixture, 'data: ' for real data).
 * @param string $outdir Write the pages here ('' for none).
 */
function check_round3( $tag, $outdir = '' ) {
	$GLOBALS['t_editor'] = false;
	$landing = arv_adv_render_landing();
	$pages   = array();
	foreach ( arv_adv_trips() as $t ) {
		$pages[ $t['slug'] ] = array( $t, arv_adv_render_trip( $t ) );
	}
	if ( $outdir ) {
		$wrap = static function ( $title, $body ) {
			return "<!doctype html>\n<html><head><meta charset=\"utf-8\"><meta name=\"robots\" content=\"noindex, nofollow\"><title>" . esc_html( $title ) . " (preview)</title></head><body class=\"arv-adv-page\">\n" . $body . "\n</body></html>\n";
		};
		file_put_contents( $outdir . '/adventures.html', $wrap( 'Aravaipa Adventures', $landing ) );
		foreach ( $pages as $slug => $p ) {
			file_put_contents( $outdir . '/' . $slug . '.html', $wrap( $p[0]['name'], $p[1] ) );
		}
	}
	$all = $landing . implode( '', array_column( $pages, 1 ) );
	$GLOBALS['t_is_page'] = true;
	$share                = arv_adv_share_context();
	$GLOBALS['t_is_page'] = false;
	$share_desc           = $share ? $share['desc'] : '';
	foreach ( arv_adv_trips() as $t ) {
		$share_desc .= ' ' . $t['tagline'];
	}

	$hit = preg_match( '/medal|ceremony/i', $all . ' ' . $share_desc, $m ) ? $m[0] : '';
	check( $tag . 'no medals', '' === $hit, 'found ' . $hit );

	$card = preg_match( '#<h3>Unmistakably Aravaipa</h3><p>(.*?)</p>#s', $landing, $cm ) ? plain( $cm[1] ) : '';
	check(
		$tag . 'unmistakably',
		'' !== $card && 1 === preg_match( '/community/i', $card ) && 1 === preg_match( '/\bfun\b/i', $card ) && 1 === preg_match( '/experience/i', $card )
			&& 1 !== preg_match( '/swag|medal|ceremony/i', $card ),
		$card
	);

	$t = array();
	foreach ( $pages as $slug => $p ) {
		$t[ preg_replace( '/-\d{4}$/', '', $slug ) ] = $p;
	}

	if ( isset( $t['new-zealand'] ) ) {
		$nz = $t['new-zealand'][1];
		check( $tag . 'nz price', 0 === strpos( (string) dd( $nz, 'Price' ), '$4,695' ) && 0 === strpos( (string) dd( $nz, 'Trip Price' ), '$4,695' ) && false === strpos( $all, '4,195' ), dd( $nz, 'Price' ) );
	} else {
		check( $tag . 'nz price', false, 'no New Zealand page' );
	}

	if ( isset( $t['patagonia'] ) ) {
		$pat  = $t['patagonia'][1];
		$ptxt = plain( $pat );
		$tile = preg_match( '#<a class="aa-tile" href="[^"]*patagonia-2027/".*?</a>#s', $landing, $tm ) ? plain( $tm[0] ) : '';
		$why  = array();
		if ( 0 !== strpos( (string) dd( $pat, 'Price' ), '$4,895' ) || 0 !== strpos( (string) dd( $pat, 'Trip Price' ), '$4,895' ) ) {
			$why[] = 'price ' . dd( $pat, 'Price' );
		}
		if ( 'October 2027' !== dd( $pat, 'Trip Dates' ) || false === strpos( $pat, '<h2 class="aa-glance-date">October 2027</h2>' ) ) {
			$why[] = 'dates ' . dd( $pat, 'Trip Dates' );
		}
		if ( '8 days, 7 nights' !== dd( $pat, 'Length' ) ) {
			$why[] = 'length ' . dd( $pat, 'Length' );
		}
		if ( 1 !== preg_match( '/Itinerary and trip details coming soon/', $ptxt ) ) {
			$why[] = 'no coming-soon line on the page';
		}
		if ( false !== strpos( $pat, 'aa-timeline' ) || false !== strpos( $pat, 'class="aa-day"' ) || false !== stripos( $ptxt, 'Day by Day' ) ) {
			$why[] = 'day-by-day still shows';
		}
		if ( 1 !== preg_match( '#<a class="aa-btn aa-btn-yellow" href="https://www\.wetravel\.com/[^"]+"[^>]*>Book Now</a>#', $pat ) ) {
			$why[] = 'no Book Now';
		}
		if ( 1 !== preg_match( '/coming soon/i', $tile ) || false === strpos( $tile, '8 days, 7 nights' ) || false === strpos( $tile, 'October 2027' ) ) {
			$why[] = 'landing card: ' . $tile;
		}
		check( $tag . 'patagonia', ! $why, implode( '; ', $why ) );
	} else {
		check( $tag . 'patagonia', false, 'no Patagonia page' );
	}

	$why = array();
	foreach ( array( 'madeira', 'patagonia' ) as $k ) {
		if ( ! isset( $t[ $k ] ) ) {
			$why[] = 'no ' . $k . ' page';
			continue;
		}
		$h     = $t[ $k ][1];
		$entry = (string) dd( $h, 'Race Entry' );
		if ( 0 !== stripos( $entry, 'Included' ) ) {
			$why[] = $k . ' race entry: ' . $entry;
		}
		if ( ! preg_grep( '/race (registration|entry)/i', inc_list( $h, 'aa-yes' ) ) ) {
			$why[] = $k . ' included list has no race registration';
		}
		if ( preg_grep( '/race (registration|entry)/i', inc_list( $h, 'aa-no' ) ) ) {
			$why[] = $k . ' race registration under Not Included';
		}
	}
	if ( 1 === preg_match( '/TBD, being confirmed with the race/i', $all ) ) {
		$why[] = 'race entry TBD';
	}
	$faq = preg_match( '#<summary>Do I have to run the race[^<]*</summary><div class="aa-faq-body"><p>(.*?)</p>#s', $landing, $fm ) ? plain( $fm[1] ) : '';
	if ( 1 !== preg_match( '/included in the (trip )?price/i', $faq ) || 1 === preg_match( '/says whether/i', $faq ) ) {
		$why[] = 'faq: ' . $faq;
	}
	$note = preg_match( '#<p class="aa-grid-note">(.*?)</p>#s', $landing, $nm ) ? plain( $nm[1] ) : '';
	if ( 1 !== preg_match( '/race entry is included/i', $note ) ) {
		$why[] = 'grid note: ' . $note;
	}
	check( $tag . 'race entry', ! $why, implode( '; ', $why ) );

	if ( isset( $t['slovenia'] ) ) {
		$slo = $t['slovenia'][1];
		check( $tag . 'slovenia dates', 'September 12-19, 2027' === dd( $slo, 'Trip Dates' ) && false === strpos( $all, 'August 2027' ), (string) dd( $slo, 'Trip Dates' ) );
	} else {
		check( $tag . 'slovenia dates', false, 'no Slovenia page' );
	}

	$why = array();
	foreach ( array( 'slovenia' => 86009, 'madeira' => 86001 ) as $k => $old ) {
		if ( ! isset( $t[ $k ] ) ) {
			$why[] = 'no ' . $k . ' page';
			continue;
		}
		$hero = (int) ( isset( $t[ $k ][0]['photos']['hero']['id'] ) ? $t[ $k ][0]['photos']['hero']['id'] : 0 );
		if ( ! $hero || $old === $hero ) {
			$why[] = $k . ' hero is still ' . $hero;
		}
		if ( ! in_array( $hero, credit_ids( $t[ $k ][1] ), true ) || ! in_array( $hero, credit_ids( $landing ), true ) ) {
			$why[] = $k . ' hero ' . $hero . ' has no credit';
		}
	}
	check( $tag . 'hero photos', ! $why, implode( '; ', $why ) );

	check( $tag . 'em dash', 1 !== preg_match( '/\x{2014}/u', $all . $share_desc ) );
}

load_data( $trips, $config );
check_round3( '' );

// Brand link: the X masthead brand anchor goes to the Adventures home, every
// other link stays, and off Adventures pages nothing is buffered at all.
$mast = "<div class=\"x-container\">\n<a href=\"https://www.aravaiparunning.com/\" class=\"x-brand img\">\n  <img src=\"/logo.svg\" alt=\"Aravaipa Adventures\"></a>\n<a href=\"https://www.aravaiparunning.com/\" class=\"x-btn-navbar\">Menu</a></div>";
$ok   = function_exists( 'arv_adv_brand_link' ) && function_exists( 'arv_adv_brand_start' ) && function_exists( 'arv_adv_brand_end' );
$why  = $ok ? '' : 'missing arv_adv_brand_link/start/end';
if ( $ok ) {
	$url  = arv_adv_landing_url();
	$out  = arv_adv_brand_link( $mast, $url );
	$want = '<a href="https://www.aravaiparunning.com/adventures/" class="x-brand img">';
	$ok   = false !== strpos( $out, $want ) && false !== strpos( $out, '<a href="https://www.aravaiparunning.com/" class="x-btn-navbar">' )
		&& arv_adv_brand_link( $out, $url ) === $out && 'https://www.aravaiparunning.com/adventures/' === $url;
	$why  = $out;
	$lvl  = ob_get_level();
	arv_adv_brand_start();
	echo '';
	arv_adv_brand_end();
	$ok  = $ok && ob_get_level() === $lvl;
}
check( 'brand link', $ok, $why );

if ( ! empty( $argv[1] ) ) {
	$data = json_decode( (string) file_get_contents( $argv[1] ), true );
	load_data( $data['trips'], $data['config'] );
	check_round3( 'data: ', ! empty( $argv[2] ) ? rtrim( $argv[2], '/' ) : '' );
}

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
