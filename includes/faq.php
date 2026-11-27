<?php
/**
 * The FAQ page's search box, structured data and analytics.
 *
 * The questions and answers themselves are not here: they are hand-written
 * HTML in the FAQ page's own content, the same way the Contact page's copy
 * is. That is deliberate. This site runs no shortcode-driven FAQ builder
 * and does not need one for a single page; what it needed was the three
 * things a hand-written accordion can never do for itself: let a visitor
 * search 40 questions instead of reading them, tell Google what the page
 * actually answers, and tell us what people searched for that the page
 * did not cover.
 *
 * The markup contract the page content is expected to follow:
 *
 *   <details class="arv-faq-q" id="some-slug" data-keywords="extra terms">
 *     <summary>The question?</summary>
 *     <div class="arv-faq-a"><p>The answer.</p></div>
 *   </details>
 *
 * wrapped in sections:
 *
 *   <div class="arv-faq-section"><h2>Section name</h2> ...details... </div>
 *
 * arv_faq_schema_head() reads that same markup back out of the raw post
 * content to build FAQPage structured data, so the questions Google is told
 * about can never drift from the questions a visitor actually sees.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The search box, plus the "no matches" message the script shows.
 *
 * Renders even if JS never loads or fails: with no script running, this is
 * an inert text input above an already-readable list of plain accordions,
 * which is what the page was before. Nothing about the FAQ depends on the
 * search working.
 *
 * @return string
 */
function arv_faq_search_shortcode() {
	$out  = '<div class="arv-faq-search-wrap" data-arv-faq-root>';
	$out .= '<input type="search" class="arv-faq-search" data-arv-faq-search';
	$out .= ' placeholder="Search the FAQ, e.g. &ldquo;dogs&rdquo; or &ldquo;drop bag&rdquo;"';
	$out .= ' aria-label="Search frequently asked questions" />';
	$out .= '<p class="arv-faq-search-status" data-arv-faq-status aria-live="polite"></p>';
	$out .= '</div>';

	return $out;
}
add_shortcode( 'arv_faq_search', 'arv_faq_search_shortcode' );

/**
 * Pull every question and answer back out of the FAQ page's raw content.
 *
 * Reads post_content directly rather than waiting for the_content, so this
 * can run from wp_head before the template has rendered anything. The
 * markup our own editors write is plain HTML, not a shortcode, so nothing
 * is lost by reading it before shortcodes have expanded.
 *
 * @param string $content
 * @return array<int, array{q: string, a: string}>
 */
function arv_faq_extract_pairs( $content ) {
	if ( ! preg_match_all(
		'~<details[^>]*class="[^"]*arv-faq-q[^"]*"[^>]*>\s*<summary[^>]*>(.*?)</summary>\s*<div[^>]*class="[^"]*arv-faq-a[^"]*"[^>]*>(.*?)</div>\s*</details>~is',
		$content,
		$matches,
		PREG_SET_ORDER
	) ) {
		return array();
	}

	$pairs = array();

	foreach ( $matches as $m ) {
		$q = trim( wp_strip_all_tags( $m[1] ) );
		$a = trim( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />' ), "\n", $m[2] ) ) );

		if ( '' === $q || '' === $a ) {
			continue;
		}

		$pairs[] = array(
			'q' => $q,
			'a' => $a,
		);
	}

	return $pairs;
}

/**
 * FAQPage structured data, on any page carrying the search shortcode.
 *
 * Tied to the shortcode rather than to a hardcoded page ID: this plugin has
 * no other FAQ page today, but if a second one shows up (an event-specific
 * FAQ, say) it gets the same schema for free by placing the same shortcode.
 */
function arv_faq_schema_head() {
	if ( ! function_exists( 'arv_seo_handled_elsewhere' ) || arv_seo_handled_elsewhere() ) {
		return;
	}

	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'arv_faq_search' ) ) {
		return;
	}

	$pairs = arv_faq_extract_pairs( $post->post_content );

	if ( empty( $pairs ) ) {
		return;
	}

	$entities = array();

	foreach ( $pairs as $pair ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $pair['q'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $pair['a'],
			),
		);
	}

	$node = array(
		'@type'          => 'FAQPage',
		'mainEntity'     => $entities,
	);

	echo arv_seo_schema_script( array( $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'arv_faq_schema_head', 5 );

/**
 * Where unmatched searches get recorded.
 *
 * A capped option instead of a custom table: at FAQ-search volume this is
 * a handful of rows a day, and a table plus its own upgrade routine would
 * be a lot of ceremony for something this small. Capped so a bot loop
 * hammering the endpoint fills a bounded amount of space and ages itself
 * out rather than growing the options table forever.
 */
const ARV_FAQ_SEARCH_LOG_OPTION = 'arv_faq_search_log';
const ARV_FAQ_SEARCH_LOG_MAX    = 500;

/**
 * Record one search.
 *
 * No auth: this is a write-only counter of search terms, not user data tied
 * to an identity, and the cap above bounds the cost of abuse. Silently
 * accepts and drops anything that is not a short, real-looking query rather
 * than erroring, since a malformed beacon call is not something the visitor
 * did wrong.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function arv_faq_search_log_handle( $request ) {
	$q = trim( sanitize_text_field( (string) $request->get_param( 'q' ) ) );

	if ( '' !== $q && strlen( $q ) <= 80 ) {
		$log   = get_option( ARV_FAQ_SEARCH_LOG_OPTION, array() );
		$log[] = array(
			'q'       => $q,
			'matched' => (bool) $request->get_param( 'matched' ),
			't'       => time(),
		);

		if ( count( $log ) > ARV_FAQ_SEARCH_LOG_MAX ) {
			$log = array_slice( $log, -1 * ARV_FAQ_SEARCH_LOG_MAX );
		}

		update_option( ARV_FAQ_SEARCH_LOG_OPTION, $log, false );
	}

	return new WP_REST_Response( array( 'ok' => true ) );
}

function arv_faq_search_log_route() {
	register_rest_route(
		'aravaipa/v1',
		'/faq-search-log',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_faq_search_log_handle',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'arv_faq_search_log_route' );

/* ------------------------------------------------------------------
 * Sections, deep links, the Contact page top block and topic answers
 * ----------------------------------------------------------------
 *
 * Extended markup contract (job aclb03): sections carry an id for the jump
 * nav, and each question carries data-topics, a space separated list of
 * contact form topic slugs (see arv_faq_topic_slugs()):
 *
 *   <div class="arv-faq-section" id="race-day"><h2>Race Week and Race Day</h2>
 *     <details class="arv-faq-q" id="parking" data-topics="raceday" data-keywords="...">
 */

/** Path of the one FAQ page the Contact page block reads from. */
const ARV_FAQ_PAGE_PATH = 'faq';

/**
 * Every question with its id, topics, section and answer HTML, in page order.
 *
 * @param string $content Raw FAQ page content.
 * @return array<int, array{id: string, topics: string[], section: string, q: string, a: string}>
 */
function arv_faq_extract_items( $content ) {
	$items   = array();
	$section = '';
	$pattern = '~<div[^>]*class="[^"]*arv-faq-section[^"]*"[^>]*>\s*<h2[^>]*>(.*?)</h2>'
		. '|<details([^>]*class="[^"]*arv-faq-q[^"]*"[^>]*)>\s*<summary[^>]*>(.*?)</summary>\s*<div[^>]*class="[^"]*arv-faq-a[^"]*"[^>]*>(.*?)</div>\s*</details>~is';

	if ( ! preg_match_all( $pattern, (string) $content, $matches, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $matches as $m ) {
		if ( isset( $m[1] ) && '' !== $m[1] ) {
			$section = trim( wp_strip_all_tags( $m[1] ) );
			continue;
		}

		$attrs = $m[2];
		$q     = trim( wp_strip_all_tags( $m[3] ) );
		$a     = trim( $m[4] );

		if ( '' === $q || '' === $a ) {
			continue;
		}

		$id     = preg_match( '~\bid="([a-z0-9-]+)"~', $attrs, $im ) ? $im[1] : '';
		$topics = preg_match( '~\bdata-topics="([^"]*)"~', $attrs, $tm ) ? preg_split( '~\s+~', trim( $tm[1] ), -1, PREG_SPLIT_NO_EMPTY ) : array();

		$items[] = array(
			'id'      => $id,
			'topics'  => $topics,
			'section' => $section,
			'q'       => $q,
			'a'       => $a,
		);
	}

	return $items;
}

/**
 * Sections with an id, for the jump nav.
 *
 * @param string $content
 * @return array<int, array{id: string, name: string}>
 */
function arv_faq_extract_sections( $content ) {
	$out = array();

	if ( preg_match_all( '~<div[^>]*class="[^"]*arv-faq-section[^"]*"[^>]*\bid="([a-z0-9-]+)"[^>]*>\s*<h2[^>]*>(.*?)</h2>~is', (string) $content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			$out[] = array(
				'id'   => $m[1],
				'name' => trim( wp_strip_all_tags( $m[2] ) ),
			);
		}
	}

	return $out;
}

/**
 * [arv_faq_nav]: one row of links to each section, placed under the search.
 *
 * @return string
 */
function arv_faq_nav_shortcode() {
	$post     = get_post();
	$sections = $post ? arv_faq_extract_sections( $post->post_content ) : array();

	if ( empty( $sections ) ) {
		return '';
	}

	$out = '<nav class="arv-faq-nav" aria-label="FAQ sections"><ul>';

	foreach ( $sections as $s ) {
		$out .= '<li><a href="#' . esc_attr( $s['id'] ) . '">' . esc_html( $s['name'] ) . '</a></li>';
	}

	return $out . '</ul></nav>';
}
add_shortcode( 'arv_faq_nav', 'arv_faq_nav_shortcode' );

/**
 * Answer HTML reduced to paragraphs, lists, emphasis and links, for use
 * outside the FAQ page (Contact block and topic answers).
 *
 * @param string $html
 * @return string
 */
function arv_faq_safe_html( $html ) {
	return wp_kses(
		(string) $html,
		array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'a'      => array( 'href' => true ),
		)
	);
}

/**
 * Contact form topic label (CF7 84199 category select) to topic slug.
 *
 * @return array<string, string>
 */
function arv_faq_topic_slugs() {
	return apply_filters(
		'arv_faq_topic_slugs',
		array(
			'Registration, refunds or transfers'   => 'registration',
			'Race day, results or timing'          => 'raceday',
			'Lost and found'                       => 'lost',
			'Volunteering'                         => 'volunteering',
			'Media and press'                      => 'media',
			'Sponsorship and partnerships'         => 'sponsorship',
			'Event services, timing or production' => 'services',
			'Something else'                       => 'other',
		)
	);
}

/**
 * Preferred questions per topic slug, best first. Ids missing from the page
 * are skipped, and a topic is topped up from questions tagged with it.
 *
 * @return array<string, string[]>
 */
function arv_faq_topic_questions() {
	return apply_filters(
		'arv_faq_topic_questions',
		array(
			'registration' => array( 'change-distance', 'race-credits', 'withdraw', 'refund-policy' ),
			'raceday'      => array( 'results', 'live-tracking', 'race-weekend-contact' ),
			'lost'         => array( 'race-weekend-contact' ),
			'volunteering' => array( 'volunteer-signup', 'volunteer-perks' ),
			'media'        => array( 'race-photos' ),
			'sponsorship'  => array( 'sponsorship' ),
			'services'     => array( 'timing-services' ),
			'other'        => array( 'race-weekend-contact', 'accessibility' ),
		)
	);
}

/**
 * Up to $max questions per topic slug.
 *
 * @param array $items From arv_faq_extract_items().
 * @param int   $max
 * @return array<string, array<int, array>>
 */
function arv_faq_topic_answers( $items, $max = 3 ) {
	$by_id = array();

	foreach ( $items as $item ) {
		if ( '' !== $item['id'] ) {
			$by_id[ $item['id'] ] = $item;
		}
	}

	$out = array();

	foreach ( array_unique( array_values( arv_faq_topic_slugs() ) ) as $slug ) {
		$picked    = array();
		$preferred = arv_faq_topic_questions();

		foreach ( isset( $preferred[ $slug ] ) ? $preferred[ $slug ] : array() as $id ) {
			if ( isset( $by_id[ $id ] ) && count( $picked ) < $max ) {
				$picked[ $id ] = $by_id[ $id ];
			}
		}

		foreach ( $items as $item ) {
			if ( count( $picked ) >= $max ) {
				break;
			}

			if ( '' !== $item['id'] && ! isset( $picked[ $item['id'] ] ) && in_array( $slug, $item['topics'], true ) ) {
				$picked[ $item['id'] ] = $item;
			}
		}

		if ( $picked ) {
			$out[ $slug ] = array_values( $picked );
		}
	}

	return $out;
}

/** The FAQ page's raw content, or '' when there is no FAQ page. */
function arv_faq_page_content() {
	$page = get_page_by_path( ARV_FAQ_PAGE_PATH );

	return ( $page instanceof WP_Post ) ? (string) $page->post_content : '';
}

/** Default questions for the Contact page top block, most asked first. */
const ARV_FAQ_TOP_DEFAULT = 'race-credits,change-distance,refund-policy,results,parking,awards';

/**
 * [arv_faq_top ids="a,b"]: a search box that goes to the FAQ page and the
 * six most asked questions as accordions, for the top of the Contact page.
 * Also prints the topic answer data the FAQ script shows under the contact
 * form's topic select.
 *
 * @param array|string $atts
 * @return string
 */
function arv_faq_top_shortcode( $atts = array() ) {
	$atts  = shortcode_atts( array( 'ids' => ARV_FAQ_TOP_DEFAULT ), $atts, 'arv_faq_top' );
	$items = arv_faq_extract_items( arv_faq_page_content() );

	if ( empty( $items ) ) {
		return '';
	}

	return arv_faq_top_html( $items, array_filter( array_map( 'trim', explode( ',', $atts['ids'] ) ) ), home_url( '/' . ARV_FAQ_PAGE_PATH . '/' ) );
}
add_shortcode( 'arv_faq_top', 'arv_faq_top_shortcode' );

/**
 * The top block markup (separate from the shortcode so tests need no WP page).
 *
 * @param array    $items
 * @param string[] $ids
 * @param string   $faq_url
 * @return string
 */
function arv_faq_top_html( $items, $ids, $faq_url ) {
	$by_id = array();

	foreach ( $items as $item ) {
		$by_id[ $item['id'] ] = $item;
	}

	$top = array();

	foreach ( $ids as $id ) {
		if ( isset( $by_id[ $id ] ) && count( $top ) < 6 ) {
			$top[] = $by_id[ $id ];
		}
	}

	$out  = '<div class="arv-faq-top" data-arv-faq-top>';
	$out .= '<h2>Find an answer first</h2>';
	$out .= '<form class="arv-faq-top__search" role="search" method="get" action="' . esc_url( $faq_url ) . '">';
	$out .= '<input type="search" name="q" class="arv-faq-search" placeholder="Search the FAQ, e.g. &ldquo;race credit&rdquo;" aria-label="Search frequently asked questions" />';
	$out .= '<button type="submit" class="arv-faq-top__go">Search</button>';
	$out .= '</form>';

	foreach ( $top as $item ) {
		$out .= '<details class="arv-faq-q" id="top-' . esc_attr( $item['id'] ) . '"><summary>' . esc_html( $item['q'] ) . '</summary>';
		$out .= '<div class="arv-faq-a">' . arv_faq_safe_html( $item['a'] ) . '</div></details>';
	}

	$out .= '<p class="arv-faq-top__all"><a href="' . esc_url( $faq_url ) . '">See all questions</a></p>';
	$out .= '</div>';
	$out .= arv_faq_topic_data_script( $items, $faq_url );

	return $out;
}

/**
 * JSON for the contact form topic answers: { topics: {label: slug}, answers: {slug: [...] } }.
 *
 * @param array  $items
 * @param string $faq_url
 * @return string
 */
function arv_faq_topic_data_script( $items, $faq_url ) {
	$answers = array();

	foreach ( arv_faq_topic_answers( $items ) as $slug => $list ) {
		foreach ( $list as $item ) {
			$answers[ $slug ][] = array(
				'id'  => $item['id'],
				'q'   => $item['q'],
				'a'   => arv_faq_safe_html( $item['a'] ),
				'url' => $faq_url . '#' . $item['id'],
			);
		}
	}

	$data = array(
		'topics'  => arv_faq_topic_slugs(),
		'answers' => $answers,
	);

	return '<script type="application/json" class="arv-faq-topic-data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . '</script>';
}

/**
 * Styles for the jump nav, the Contact top block and the topic answers.
 * Loaded after the main stylesheet so it can lean on its .arv-faq-* rules.
 */
function arv_faq_enqueue_styles() {
	wp_enqueue_style(
		'aravaipa-faq',
		ARV_ELEMENTS_URL . 'assets/aravaipa-faq.css',
		array(),
		ARV_ELEMENTS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'arv_faq_enqueue_styles', 20 );
