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
