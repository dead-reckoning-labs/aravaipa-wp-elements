<?php
/**
 * Open positions, read live from BambooHR rather than typed onto the
 * Careers page by hand.
 *
 * The page had two roles hardcoded with their own "Apply Now" buttons.
 * One of them, Event Crew, pointed at BambooHR job id 24, which no longer
 * exists: BambooHR's own API returns a 404 for it. Only one requisition
 * (Social Media Manager, id 65) was actually open. Anyone who clicked
 * "Apply Now, Event Crew" hit a dead end, on the one role that would get
 * the most volume.
 *
 * A hand-typed list can only ever be behind: closing a requisition in
 * BambooHR does nothing to the page that advertises it. Reading the list
 * live means a closed job disappears from the page the same day it closes
 * in the system where it was actually closed.
 *
 * This also adds JobPosting structured data, which the page had none of.
 * That is what makes a role eligible for Google's job search box, the one
 * placement in recruiting where structured data converts directly into
 * applicants, and BambooHR's API already returns everything schema.org
 * asks for: title, description, location, employment type, compensation,
 * date posted.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ARV_CAREERS_LAST_GOOD_OPTION = 'arv_careers_last_good';

/**
 * Every open requisition, from BambooHR's public careers list.
 *
 * No auth: this is the same JSON the public /careers page on BambooHR's
 * own domain calls to render itself.
 *
 * Cached for 6 hours, the same window the athlete results and upcoming
 * races syncs use for data that changes by the day, not the minute. On a
 * failed request this falls back to the last list that did succeed,
 * stored separately with no expiry, rather than to an empty list: a
 * BambooHR outage should not make the page briefly claim there is nothing
 * open when there is.
 *
 * @return array<int, array>
 */
function arv_careers_fetch_list() {
	$key    = 'arv_careers_list';
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://aravaipa.bamboohr.com/careers/list',
		array( 'timeout' => 8 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return get_option( ARV_CAREERS_LAST_GOOD_OPTION, array() );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$list = ( is_array( $body ) && isset( $body['result'] ) && is_array( $body['result'] ) ) ? $body['result'] : null;

	if ( null === $list ) {
		return get_option( ARV_CAREERS_LAST_GOOD_OPTION, array() );
	}

	// A genuine empty array (zero requisitions open right now) is a real
	// answer and overwrites the fallback same as a populated one does:
	// the point of the fallback is surviving BambooHR being unreachable,
	// not second-guessing BambooHR when it answers.
	update_option( ARV_CAREERS_LAST_GOOD_OPTION, $list, false );
	set_transient( $key, $list, 6 * HOUR_IN_SECONDS );

	return $list;
}

/**
 * One requisition's full detail: description, compensation, date posted.
 *
 * The list endpoint is a summary. This is the same shape BambooHR's own
 * job page fetches when someone opens a specific posting.
 *
 * @param string $id
 * @return array|null Null on a closed or removed requisition (a 404) or a
 *                     failed request.
 */
function arv_careers_fetch_detail( $id ) {
	$id = sanitize_text_field( (string) $id );

	if ( '' === $id ) {
		return null;
	}

	$key    = 'arv_careers_detail_' . $id;
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	if ( 'miss' === $cached ) {
		return null;
	}

	$response = wp_remote_get(
		'https://aravaipa.bamboohr.com/careers/' . rawurlencode( $id ) . '/detail',
		array( 'timeout' => 8 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		// An hour: a requisition that 404s today might be a BambooHR blip,
		// not a closure, and the list endpoint is what actually decides
		// whether a card renders at all. This only holds off retrying the
		// detail fetch for the one card in question.
		set_transient( $key, 'miss', HOUR_IN_SECONDS );
		return null;
	}

	$body   = json_decode( wp_remote_retrieve_body( $response ), true );
	$detail = ( is_array( $body ) && isset( $body['result']['jobOpening'] ) ) ? $body['result']['jobOpening'] : null;

	if ( null === $detail ) {
		set_transient( $key, 'miss', HOUR_IN_SECONDS );
		return null;
	}

	set_transient( $key, $detail, 6 * HOUR_IN_SECONDS );

	return $detail;
}

/**
 * A one or two sentence summary, not the full posting.
 *
 * The first version of this rendered BambooHR's entire description inline:
 * every section header, every bullet under "What You'll Do," the full
 * eighteen-line race schedule. That is an applicant-tracking-system detail
 * page, written for someone who has already decided to apply and wants
 * every particular. On the public careers page it read as a wall of text
 * before anyone had a reason to be reading it that closely, per Jamil:
 * "way too in depth... pretty awful."
 *
 * Two things dropped before trimming to length, both generic to how these
 * descriptions are written rather than tuned to this one posting's exact
 * wording, so the next role posted does not need this function revisited:
 *
 *   - A short bolded label on its own paragraph ("About the Role", "What
 *     You'll Do", "Required"). BambooHR's format uses these as section
 *     headers throughout, and a heading fragment reads as a sentence
 *     trailing off when it lands at the end of a 40-word trim.
 *   - A leading paragraph containing a "|" character. This posting opens
 *     with "Social Media Manager | Phoenix, AZ | Full-Time | $50,000-
 *     $53,000", restating the title, location and pay already shown in
 *     the meta line above the summary, in a compact-header convention
 *     unlikely to appear in actual prose anywhere else in the document.
 *
 * A bullet list is naturally excluded rather than specifically filtered:
 * this only recognises <p> boundaries as paragraphs, so list items are
 * never candidates in the first place.
 *
 * @param string $html
 * @return string
 */
function arv_careers_summary( $html ) {
	$html = (string) $html;
	$html = preg_replace( '~</p\s*>~i', "\n\n", $html );
	$html = preg_replace( '~<br\s*/?>~i', "\n", $html );

	$paragraphs = preg_split( '~\n{2,}~', wp_strip_all_tags( $html ) );
	$kept       = array();
	$leading    = true;

	foreach ( $paragraphs as $p ) {
		$p = trim( html_entity_decode( preg_replace( '~\s+~', ' ', $p ), ENT_QUOTES ) );

		if ( '' === $p ) {
			continue;
		}

		if ( $leading && false !== strpos( $p, '|' ) ) {
			continue;
		}

		$leading = false;

		if ( str_word_count( $p ) <= 6 ) {
			continue;
		}

		$kept[] = $p;
	}

	return wp_trim_words( implode( ' ', $kept ), 40, '…' );
}

/**
 * The Careers page's open-positions block: one card per requisition, each
 * carrying its own JobPosting schema.
 *
 * @param array $atts Unused; the shortcode takes no attributes.
 * @return string
 */
function arv_careers_render( $atts = array() ) {
	$list = arv_careers_fetch_list();

	if ( empty( $list ) ) {
		return '<p class="arv-careers__empty">'
			. esc_html__( "We don't have any open positions right now, but we're always glad to hear from people who want to work here. Check back soon.", 'aravaipa-elements' )
			. '</p>'
			. arv_careers_board_link();
	}

	$out = '<div class="arv-careers__list">';

	foreach ( $list as $summary ) {
		$id = isset( $summary['id'] ) ? (string) $summary['id'] : '';

		if ( '' === $id ) {
			continue;
		}

		$detail = arv_careers_fetch_detail( $id );

		// The requisition is still open per the list endpoint even without
		// detail (a transient BambooHR error on the detail call alone), so
		// this still renders a card from what the list already gave it
		// rather than dropping a real, current opening off the page.
		$title      = isset( $summary['jobOpeningName'] ) ? (string) $summary['jobOpeningName'] : '';
		$department = isset( $summary['departmentLabel'] ) ? (string) $summary['departmentLabel'] : '';
		$employment = isset( $summary['employmentStatusLabel'] ) ? (string) $summary['employmentStatusLabel'] : '';
		$city       = isset( $summary['location']['city'] ) ? (string) $summary['location']['city'] : '';
		$state      = isset( $summary['location']['state'] ) ? (string) $summary['location']['state'] : '';
		$apply_url  = 'https://aravaipa.bamboohr.com/careers/' . rawurlencode( $id );

		if ( '' === $title ) {
			continue;
		}

		$meta = array_filter( array( $department, trim( $city . ( '' !== $city && '' !== $state ? ', ' : '' ) . $state ), $employment ) );

		$out .= '<article class="arv-careers__card">';
		$out .= '<h3 class="arv-careers__title">' . esc_html( $title ) . '</h3>';

		if ( ! empty( $meta ) ) {
			$out .= '<p class="arv-careers__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}

		if ( $detail ) {
			$compensation = isset( $detail['compensation'] ) ? trim( (string) $detail['compensation'] ) : '';

			if ( '' !== $compensation ) {
				$out .= '<p class="arv-careers__compensation">' . esc_html( $compensation ) . '</p>';
			}

			$summary = ! empty( $detail['description'] ) ? arv_careers_summary( $detail['description'] ) : '';

			if ( '' !== $summary ) {
				$out .= '<p class="arv-careers__summary">' . esc_html( $summary ) . '</p>';
			}
		}

		$out .= '<a class="arv-careers__apply" href="' . esc_url( $apply_url ) . '" target="_blank" rel="noopener">'
			. esc_html( sprintf( __( 'Apply: %s', 'aravaipa-elements' ), $title ) ) . '</a>';

		$out .= arv_careers_schema( $summary, $detail, $apply_url );

		$out .= '</article>';
	}

	$out .= '</div>';
	$out .= arv_careers_board_link();

	return $out;
}

/**
 * A link to the BambooHR board itself.
 *
 * Every role Aravaipa posts goes up there, and this page only ever shows
 * what is open at this moment. That distinction matters to the person who
 * looked, found nothing for them, and would otherwise have no reason to
 * come back or anywhere else to look. On the empty path it is the only
 * useful thing on the page.
 *
 * @return string
 */
function arv_careers_board_link() {
	return '<p class="arv-careers__board">'
		. '<a href="https://aravaipa.bamboohr.com/careers" target="_blank" rel="noopener">'
		. esc_html__( 'See all openings on our job board', 'aravaipa-elements' )
		. '</a></p>';
}

/**
 * JobPosting structured data for one requisition.
 *
 * validThrough is not a field BambooHR's API returns, and Google treats
 * its absence as license to guess an expiry rather than to assume the
 * posting is still live indefinitely. Rather than invent a real closing
 * date this does not have, it sets a rolling 45 days out from render time.
 * The page itself is only true for 6 hours at a time (the list is cached
 * that long), so a requisition that actually closes stops rendering, and
 * therefore stops emitting this schema, well inside that window regardless
 * of what date this field claims.
 *
 * @param array      $summary
 * @param array|null $detail
 * @param string     $apply_url
 * @return string A <script type="application/ld+json"> tag.
 */
function arv_careers_schema( $summary, $detail, $apply_url ) {
	$title       = isset( $summary['jobOpeningName'] ) ? (string) $summary['jobOpeningName'] : '';
	$description = ( $detail && ! empty( $detail['description'] ) )
		? wp_kses( $detail['description'], array() )
		: $title;

	$schema = array(
		'@context'         => 'https://schema.org/',
		'@type'            => 'JobPosting',
		'title'            => $title,
		'description'      => $description,
		'datePosted'       => ( $detail && ! empty( $detail['datePosted'] ) ) ? $detail['datePosted'] : gmdate( 'Y-m-d' ),
		'validThrough'     => gmdate( 'Y-m-d\TH:i:sP', strtotime( '+45 days' ) ),
		'employmentType'   => arv_careers_schema_employment_type( isset( $summary['employmentStatusLabel'] ) ? $summary['employmentStatusLabel'] : '' ),
		'hiringOrganization' => array(
			'@type' => 'Organization',
			'name'  => 'Aravaipa Running',
			'sameAs' => 'https://www.aravaiparunning.com/',
		),
		'directApply'      => true,
		'url'              => $apply_url,
	);

	$city  = isset( $summary['location']['city'] ) ? (string) $summary['location']['city'] : '';
	$state = isset( $summary['location']['state'] ) ? (string) $summary['location']['state'] : '';

	if ( '' !== $city || '' !== $state ) {
		$schema['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => array_filter(
				array(
					'@type'           => 'PostalAddress',
					'addressLocality' => $city,
					'addressRegion'   => $state,
					'addressCountry'  => 'US',
				)
			),
		);
	}

	$salary = ( $detail && ! empty( $detail['compensation'] ) )
		? arv_careers_schema_salary( (string) $detail['compensation'] )
		: null;

	if ( $salary ) {
		$schema['baseSalary'] = array(
			'@type'    => 'MonetaryAmount',
			'currency' => 'USD',
			'value'    => $salary,
		);
	}

	return '<script type="application/ld+json">' . wp_json_encode( array_filter( $schema ) ) . '</script>';
}

/**
 * BambooHR's free-text compensation field, turned into the numeric shape
 * schema.org's QuantitativeValue actually requires.
 *
 * "$50K-$53K DOE" is what is live on the one requisition open right now,
 * and Google's own JobPosting documentation is explicit that value has to
 * be a number, not a string: a validator run against the string this field
 * naturally holds would flag baseSalary as invalid, which is worse than
 * having none at all, since a field search engines flag as malformed can
 * cost the listing's eligibility for the rich result rather than just
 * missing one detail of it. Range and single-figure forms are handled;
 * anything this cannot confidently reduce to a number returns null and
 * baseSalary is left out of the schema entirely rather than guessed at.
 *
 * @param string $raw
 * @return array|null
 */
function arv_careers_schema_salary( $raw ) {
	$raw = strtolower( trim( $raw ) );

	$unit = ( preg_match( '~/\s*hr\b|/\s*hour\b|per\s+hour~', $raw ) ) ? 'HOUR' : 'YEAR';

	// Every number in the string, "K" expanded to its thousands, so "50k"
	// and "50,000" both resolve to 50000 whether the range uses a hyphen,
	// an en dash, or the word "to".
	preg_match_all( '~\$?\s*([\d,]+(?:\.\d+)?)\s*(k)?~', $raw, $m, PREG_SET_ORDER );

	$numbers = array();

	foreach ( $m as $hit ) {
		$value = (float) str_replace( ',', '', $hit[1] );

		if ( ! empty( $hit[2] ) ) {
			$value *= 1000;
		}

		if ( $value > 0 ) {
			$numbers[] = $value;
		}
	}

	if ( empty( $numbers ) ) {
		return null;
	}

	if ( 1 === count( $numbers ) ) {
		return array(
			'@type'    => 'QuantitativeValue',
			'value'    => $numbers[0],
			'unitText' => $unit,
		);
	}

	return array(
		'@type'    => 'QuantitativeValue',
		'minValue' => min( $numbers ),
		'maxValue' => max( $numbers ),
		'unitText' => $unit,
	);
}

/**
 * BambooHR's employment status label, translated to the fixed vocabulary
 * schema.org's employmentType actually expects.
 *
 * @param string $label
 * @return string
 */
function arv_careers_schema_employment_type( $label ) {
	$map = array(
		'full-time' => 'FULL_TIME',
		'part-time' => 'PART_TIME',
		'contract'  => 'CONTRACTOR',
		'temporary' => 'TEMPORARY',
		'seasonal'  => 'TEMPORARY',
		'intern'    => 'INTERN',
	);

	$key = strtolower( trim( (string) $label ) );

	foreach ( $map as $needle => $type ) {
		if ( false !== strpos( $key, $needle ) ) {
			return $type;
		}
	}

	return 'OTHER';
}

/**
 * [arv_careers]
 *
 * @param array $atts
 * @return string
 */
function arv_careers_shortcode( $atts ) {
	return arv_careers_render( $atts );
}
add_shortcode( 'arv_careers', 'arv_careers_shortcode' );

/**
 * A manual refresh, for confirming a change in BambooHR shows up here
 * without waiting out the 6 hour cache.
 */
function arv_careers_admin_menu() {
	add_management_page(
		__( 'Careers Sync', 'aravaipa-elements' ),
		__( 'Careers Sync', 'aravaipa-elements' ),
		'manage_options',
		'arv-careers-sync',
		'arv_careers_admin_screen'
	);
}
add_action( 'admin_menu', 'arv_careers_admin_menu' );

function arv_careers_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['arv_refresh'] ) && check_admin_referer( 'arv_careers_refresh' ) ) {
		delete_transient( 'arv_careers_list' );
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html__( 'Cleared. The next page view re-fetches from BambooHR.', 'aravaipa-elements' ) );
	}

	$list = arv_careers_fetch_list();

	echo '<div class="wrap"><h1>' . esc_html__( 'Careers Sync', 'aravaipa-elements' ) . '</h1>';
	echo '<p>' . esc_html__( 'The Careers page reads open positions live from BambooHR, cached for 6 hours. Use this to force an immediate refresh after closing or posting a role.', 'aravaipa-elements' ) . '</p>';

	echo '<form method="post">';
	wp_nonce_field( 'arv_careers_refresh' );
	submit_button( __( 'Refresh now', 'aravaipa-elements' ), 'primary', 'arv_refresh' );
	echo '</form>';

	echo '<table class="widefat"><thead><tr><th>Title</th><th>Department</th><th>Location</th></tr></thead><tbody>';
	foreach ( $list as $row ) {
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_html( isset( $row['jobOpeningName'] ) ? $row['jobOpeningName'] : '' ),
			esc_html( isset( $row['departmentLabel'] ) ? $row['departmentLabel'] : '' ),
			esc_html( trim( ( isset( $row['location']['city'] ) ? $row['location']['city'] : '' ) . ', ' . ( isset( $row['location']['state'] ) ? $row['location']['state'] : '' ), ', ' ) )
		);
	}
	if ( empty( $list ) ) {
		echo '<tr><td colspan="3">' . esc_html__( 'No open positions.', 'aravaipa-elements' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
