<?php
/**
 * Careers landing page: [arv_careers_page].
 *
 * The whole /careers/ page body as one shortcode, built the way the region
 * hubs are: a photo hero, the live openings, a numbers strip, Life at
 * Aravaipa as three photo splits, a behind the scenes gallery, and the other
 * ways to work a race with us. It borrows the hub stylesheet for the shared
 * pieces (hero, sections, stats, gallery, buttons) and adds
 * assets/aravaipa-careers.css for what only this page has.
 *
 * Openings come live from BambooHR through includes/careers-store.php (same
 * fetch, last good fallback and JobPosting schema as [arv_careers]). Copy
 * and photos live in includes/data/careers.json, sourced from our own site;
 * nothing about pay or benefits is written here.
 *
 * JobPosting structured data is emitted once per live opening and never on
 * the empty page: a schema block for a job that does not exist is exactly
 * what Google penalises.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The careers data file, decoded, or null.
 *
 * @return array|null
 */
function arv_careers_page_data() {
	$file = ARV_ELEMENTS_PATH . 'includes/data/careers.json';
	if ( ! is_readable( $file ) ) {
		return null;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a bundled file, not a URL.
	$data = json_decode( (string) file_get_contents( $file ), true );
	return is_array( $data ) ? $data : null;
}

/**
 * A Photon URL at a width (region hub helper when loaded).
 *
 * @param string $url
 * @param int    $w
 * @return string
 */
function arv_careers_page_img( $url, $w ) {
	return function_exists( 'arv_region_hub_img' ) ? arv_region_hub_img( $url, $w ) : $url;
}

/**
 * srcset for a Photon URL, or ''.
 *
 * @param string $url
 * @param int[]  $widths
 * @return string
 */
function arv_careers_page_srcset( $url, $widths ) {
	return function_exists( 'arv_region_hub_srcset' ) ? arv_region_hub_srcset( $url, $widths ) : '';
}

/**
 * Eyebrow and h2.
 *
 * @param array  $s  Has eyebrow, heading.
 * @param string $id Heading id.
 * @return string
 */
function arv_careers_page_head( $s, $id ) {
	$out = '';
	if ( ! empty( $s['eyebrow'] ) ) {
		$out .= '<p class="arv-hub__eyebrow">' . esc_html( $s['eyebrow'] ) . '</p>';
	}
	if ( ! empty( $s['heading'] ) ) {
		$out .= '<h2 class="arv-hub__h2" id="' . esc_attr( $id ) . '">' . esc_html( $s['heading'] ) . '</h2>';
	}
	return $out;
}

/**
 * The openings block: one row per live requisition, or the empty state.
 *
 * @param array $list Requisitions from arv_careers_fetch_list().
 * @param array $o    The data file's openings block.
 * @return array{html: string, count: int}
 */
function arv_careers_page_openings( $list, $o ) {
	$rows  = '';
	$count = 0;

	foreach ( is_array( $list ) ? $list : array() as $summary ) {
		$id    = isset( $summary['id'] ) ? (string) $summary['id'] : '';
		$title = isset( $summary['jobOpeningName'] ) ? trim( (string) $summary['jobOpeningName'] ) : '';
		if ( '' === $id || '' === $title ) {
			continue;
		}

		$detail     = function_exists( 'arv_careers_fetch_detail' ) ? arv_careers_fetch_detail( $id ) : null;
		$apply_url  = 'https://aravaipa.bamboohr.com/careers/' . rawurlencode( $id );
		$department = isset( $summary['departmentLabel'] ) ? (string) $summary['departmentLabel'] : '';
		$employment = isset( $summary['employmentStatusLabel'] ) ? (string) $summary['employmentStatusLabel'] : '';
		$city       = isset( $summary['location']['city'] ) ? (string) $summary['location']['city'] : '';
		$state      = isset( $summary['location']['state'] ) ? (string) $summary['location']['state'] : '';
		$place      = trim( $city . ( '' !== $city && '' !== $state ? ', ' : '' ) . $state );
		if ( ! empty( $summary['isRemote'] ) ) {
			$place = '' !== $place ? $place . ' or remote' : 'Remote';
		}

		$meta = '';
		foreach ( array( $department, $place, $employment ) as $m ) {
			if ( '' !== trim( $m ) ) {
				$meta .= '<li>' . esc_html( $m ) . '</li>';
			}
		}

		$summary_text = ( $detail && ! empty( $detail['description'] ) && function_exists( 'arv_careers_summary' ) ) ? arv_careers_summary( $detail['description'] ) : '';
		$pay          = ( $detail && ! empty( $detail['compensation'] ) ) ? trim( (string) $detail['compensation'] ) : '';

		$rows .= '<li class="arv-careers-page__job">'
			. '<div class="arv-careers-page__job-text">'
			. '<h3 class="arv-careers-page__job-title">' . esc_html( $title ) . '</h3>'
			. ( '' !== $meta ? '<ul class="arv-careers-page__job-meta">' . $meta . '</ul>' : '' )
			. ( '' !== $pay ? '<p class="arv-careers-page__job-pay">' . esc_html( $pay ) . '</p>' : '' )
			. ( '' !== $summary_text ? '<p class="arv-careers-page__job-summary">' . esc_html( $summary_text ) . '</p>' : '' )
			. '</div>'
			. '<p class="arv-careers-page__job-apply"><a class="arv-hub__btn" href="' . esc_url( $apply_url ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Apply', 'aravaipa-elements' ) . '<span class="screen-reader-text">: ' . esc_html( $title ) . '</span></a></p>'
			. ( function_exists( 'arv_careers_schema' ) ? arv_careers_schema( $summary, $detail, $apply_url ) : '' )
			. '</li>';
		++$count;
	}

	$board = '';
	if ( ! empty( $o['board_url'] ) ) {
		$board = '<a href="' . esc_url( $o['board_url'] ) . '" target="_blank" rel="noopener">' . esc_html( ! empty( $o['board_label'] ) ? $o['board_label'] : __( 'Our job board', 'aravaipa-elements' ) ) . '</a>';
	}

	if ( $count > 0 ) {
		$html = '<ul class="arv-careers-page__jobs">' . $rows . '</ul>'
			. ( '' !== $board ? '<p class="arv-careers-page__board">' . sprintf( esc_html__( 'Every opening is also on %s.', 'aravaipa-elements' ), $board ) . '</p>' : '' );
		return array(
			'html'  => $html,
			'count' => $count,
		);
	}

	$html = '<div class="arv-careers-page__empty">'
		. '<h3 class="arv-careers-page__empty-title">' . esc_html( isset( $o['empty_heading'] ) ? $o['empty_heading'] : '' ) . '</h3>'
		. '<p>' . esc_html( isset( $o['empty_text'] ) ? $o['empty_text'] : '' ) . ( '' !== $board ? ' ' . $board . '.' : '' ) . '</p>'
		. ( ! empty( $o['stay_text'] ) ? '<p>' . esc_html( $o['stay_text'] ) . '</p>' : '' )
		. ( ! empty( $o['ways_text'] ) ? '<p>' . esc_html( $o['ways_text'] ) . '</p>' : '' )
		. '<p class="arv-careers-page__empty-actions">'
		. ( ! empty( $o['stay_url'] ) ? '<a class="arv-hub__btn" href="' . esc_url( $o['stay_url'] ) . '">' . esc_html( ! empty( $o['stay_label'] ) ? $o['stay_label'] : __( 'Contact Us', 'aravaipa-elements' ) ) . '</a>' : '' )
		. '<a class="arv-careers-page__btn-line" href="#ways">' . esc_html__( 'Other Ways In', 'aravaipa-elements' ) . '</a>'
		. '</p></div>';

	return array(
		'html'  => $html,
		'count' => 0,
	);
}

/**
 * The whole page.
 *
 * @param array $data Decoded careers.json.
 * @param array $list Requisitions (live, last good, or empty).
 * @return string
 */
function arv_careers_page_render( $data, $list ) {
	if ( ! is_array( $data ) ) {
		return '';
	}

	$out = '<div class="arv-hub arv-careers-page">';

	// --- Hero.
	$hero = isset( $data['hero'] ) ? $data['hero'] : array();
	$out .= '<header class="arv-hub__hero arv-careers-page__hero">';
	if ( ! empty( $hero['photo'] ) ) {
		$set  = arv_careers_page_srcset( $hero['photo'], array( 768, 1280, 1920 ) );
		$out .= '<img class="arv-hub__hero-img" src="' . esc_url( arv_careers_page_img( $hero['photo'], 1920 ) ) . '"'
			. ( '' !== $set ? ' srcset="' . esc_attr( $set ) . '" sizes="100vw"' : '' )
			. ' alt="' . esc_attr( isset( $hero['alt'] ) ? $hero['alt'] : '' ) . '" fetchpriority="high" decoding="async"'
			. ( ! empty( $hero['position'] ) ? ' style="object-position:' . esc_attr( $hero['position'] ) . '"' : '' ) . ' />';
	}
	$out .= '<div class="arv-hub__hero-text arv-hub__inner">';
	if ( ! empty( $data['eyebrow'] ) ) {
		$out .= '<p class="arv-hub__eyebrow arv-hub__eyebrow--light">' . esc_html( $data['eyebrow'] ) . '</p>';
	}
	$out .= '<h1 class="arv-hub__h1">' . esc_html( isset( $data['title'] ) ? $data['title'] : '' ) . '</h1>';
	if ( ! empty( $data['intro'] ) ) {
		$out .= '<p class="arv-hub__lead">' . esc_html( $data['intro'] ) . '</p>';
	}
	$out .= '<p class="arv-hub__hero-actions"><a class="arv-hub__btn" href="#openings">' . esc_html( ! empty( $data['cta_label'] ) ? $data['cta_label'] : __( 'See Openings', 'aravaipa-elements' ) ) . '</a></p>';
	$out .= '</div></header>';

	// --- Openings.
	$o    = isset( $data['openings'] ) ? $data['openings'] : array();
	$jobs = arv_careers_page_openings( $list, $o );
	$out .= '<section class="arv-hub__section arv-careers-page__openings' . ( 0 === $jobs['count'] ? ' arv-careers-page__openings--empty' : '' ) . '" id="openings" aria-labelledby="arv-careers-openings">'
		. '<div class="arv-hub__inner">' . arv_careers_page_head( $o, 'arv-careers-openings' ) . $jobs['html'] . '</div></section>';

	// --- Numbers.
	if ( ! empty( $data['stats'] ) && function_exists( 'arv_region_hub_stats' ) ) {
		$out .= arv_region_hub_stats( $data['stats'] );
	}

	// --- Life at Aravaipa: photo splits, alternating sides.
	$n = 0;
	foreach ( isset( $data['life'] ) && is_array( $data['life'] ) ? $data['life'] : array() as $s ) {
		++$n;
		$id    = 'arv-careers-life-' . $n;
		$text  = arv_careers_page_head( $s, $id );
		foreach ( isset( $s['paragraphs'] ) ? (array) $s['paragraphs'] : array() as $p ) {
			$text .= '<p>' . esc_html( $p ) . '</p>';
		}
		if ( ! empty( $s['items'] ) && is_array( $s['items'] ) ) {
			$text .= '<dl class="arv-careers-page__items">';
			foreach ( $s['items'] as $it ) {
				if ( empty( $it['title'] ) ) {
					continue;
				}
				$t     = esc_html( $it['title'] );
				$text .= '<div class="arv-careers-page__item"><dt>' . ( ! empty( $it['url'] ) ? '<a href="' . esc_url( $it['url'] ) . '">' . $t . '</a>' : $t ) . '</dt>'
					. '<dd>' . esc_html( isset( $it['text'] ) ? $it['text'] : '' ) . '</dd></div>';
			}
			$text .= '</dl>';
		}
		$img = '';
		if ( ! empty( $s['photo'] ) ) {
			$set = arv_careers_page_srcset( $s['photo'], array( 640, 960, 1280 ) );
			$img = '<figure class="arv-careers-page__photo"><img src="' . esc_url( arv_careers_page_img( $s['photo'], 1280 ) ) . '"'
				. ( '' !== $set ? ' srcset="' . esc_attr( $set ) . '" sizes="(max-width: 979px) 100vw, 600px"' : '' )
				. ' alt="' . esc_attr( isset( $s['alt'] ) ? $s['alt'] : '' ) . '" loading="lazy" decoding="async" /></figure>';
		}
		$out .= '<section class="arv-hub__section arv-careers-page__life' . ( ! empty( $s['flip'] ) ? ' arv-careers-page__life--flip' : '' ) . '" aria-labelledby="' . esc_attr( $id ) . '">'
			. '<div class="arv-hub__inner arv-careers-page__split">'
			. $img
			. '<div class="arv-hub__prose arv-careers-page__life-text">' . $text . '</div>'
			. '</div></section>';
	}

	// --- Gallery: one big frame and four small, four across on a desktop.
	$strip = '';
	foreach ( isset( $data['gallery'] ) && is_array( $data['gallery'] ) ? $data['gallery'] : array() as $g ) {
		if ( empty( $g['src'] ) ) {
			continue;
		}
		$big    = ! empty( $g['feature'] );
		$strip .= '<figure class="arv-hub__shot' . ( $big ? ' arv-hub__shot--big' : '' ) . '"><img src="' . esc_url( arv_careers_page_img( $g['src'], $big ? 1200 : 700 ) ) . '" alt="' . esc_attr( isset( $g['alt'] ) ? $g['alt'] : '' ) . '" loading="lazy" decoding="async" /></figure>';
	}
	if ( '' !== $strip ) {
		$gh   = isset( $data['gallery_heading'] ) && is_array( $data['gallery_heading'] ) ? $data['gallery_heading'] : array();
		$out .= '<section class="arv-hub__section arv-hub__gallery-wrap arv-careers-page__gallery" aria-labelledby="arv-careers-gallery">'
			. '<div class="arv-hub__inner">' . arv_careers_page_head( $gh, 'arv-careers-gallery' ) . '</div>'
			. '<div class="arv-hub__gallery">' . $strip . '</div></section>';
	}

	// --- Other ways in.
	$w = isset( $data['ways'] ) ? $data['ways'] : array();
	if ( ! empty( $w['items'] ) && is_array( $w['items'] ) ) {
		$cols = '';
		foreach ( $w['items'] as $it ) {
			if ( empty( $it['title'] ) || empty( $it['url'] ) ) {
				continue;
			}
			$cols .= '<div class="arv-careers-page__way"><h3>' . esc_html( $it['title'] ) . '</h3>'
				. ( ! empty( $it['text'] ) ? '<p>' . esc_html( $it['text'] ) . '</p>' : '' )
				. '<p><a class="arv-careers-page__btn-line" href="' . esc_url( $it['url'] ) . '">' . esc_html( ! empty( $it['label'] ) ? $it['label'] : $it['title'] ) . '</a></p></div>';
		}
		$out .= '<section class="arv-hub__section arv-careers-page__ways" id="ways" aria-labelledby="arv-careers-ways"><div class="arv-hub__inner">'
			. arv_careers_page_head( $w, 'arv-careers-ways' )
			. '<div class="arv-careers-page__ways-grid">' . $cols . '</div></div></section>';
	}

	$out .= '</div>';
	return $out;
}

/**
 * [arv_careers_page]
 *
 * @param array|string $atts Unused.
 * @return string
 */
function arv_careers_page_shortcode( $atts = array() ) {
	$data = arv_careers_page_data();
	if ( null === $data ) {
		return '';
	}
	$list = function_exists( 'arv_careers_fetch_list' ) ? arv_careers_fetch_list() : array();

	wp_enqueue_script( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.js', array(), ARV_ELEMENTS_VERSION, true );
	arv_careers_page_styles();

	return arv_careers_page_render( $data, $list );
}
add_shortcode( 'arv_careers_page', 'arv_careers_page_shortcode' );

/**
 * Hub stylesheet plus the careers one.
 */
function arv_careers_page_styles() {
	wp_enqueue_style( 'aravaipa-region-hub', ARV_ELEMENTS_URL . 'assets/aravaipa-region-hub.css', array( 'aravaipa-elements' ), ARV_ELEMENTS_VERSION );
	wp_enqueue_style( 'aravaipa-careers', ARV_ELEMENTS_URL . 'assets/aravaipa-careers.css', array( 'aravaipa-region-hub' ), ARV_ELEMENTS_VERSION );
}

/**
 * Styles in the head on the page that holds the shortcode, so the hero does
 * not paint unstyled (same check as arv_region_hub_assets()).
 */
function arv_careers_page_assets() {
	if ( ! is_singular() ) {
		return;
	}
	$id      = get_queried_object_id();
	$content = (string) get_post_field( 'post_content', $id ) . (string) get_post_meta( $id, '_cornerstone_data', true );
	if ( false === strpos( $content, '[arv_careers_page' ) ) {
		return;
	}
	arv_careers_page_styles();
}
add_action( 'wp_enqueue_scripts', 'arv_careers_page_assets', 20 );
