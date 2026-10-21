<?php
/**
 * Aravaipa Adventures interest form, on Contact Form 7.
 *
 * CF7 is the form plugin the site already runs (Contact Us, Artworx,
 * Timing Corrections), with Flamingo keeping every entry and Turnstile
 * keeping the bots out, so the interest list uses it too instead of a
 * bespoke endpoint: entries show up under Flamingo, Inbound Messages next to
 * everything else, and mail goes out the same way the other forms' does.
 *
 * The form itself is an ordinary CF7 form (created by the importer, its ID
 * kept in the arv_adventures_form_id option), so staff can change the
 * recipient or the copy in Contact, Contact Forms. The one thing it cannot
 * hold is the trip list, which is why the "adv-trip" select is filled in
 * here from the adventure posts: add or retitle a trip and the form follows.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Trip slug to preselect while the form renders on a trip page. */
$GLOBALS['arv_adv_form_preselect'] = '';

/**
 * The form's HTML, with a trip preselected.
 *
 * @param string $preselect Trip slug, or '' for "any trip".
 * @return string Empty when CF7 or the form is missing.
 */
function arv_adv_form_html( $preselect = '' ) {
	$id = (int) get_option( 'arv_adventures_form_id' );
	if ( ! $id || ! function_exists( 'wpcf7_contact_form' ) ) {
		return '';
	}
	$form = wpcf7_contact_form( $id );
	if ( ! $form ) {
		return '';
	}

	$GLOBALS['arv_adv_form_preselect'] = (string) $preselect;
	$html                              = $form->form_html( array( 'html_class' => 'aa-cf7' ) );
	$GLOBALS['arv_adv_form_preselect'] = '';

	return $html;
}

/**
 * Option label for a trip in the select, which is also what the email says.
 *
 * @param array $trip Trip.
 * @return string
 */
function arv_adv_form_label( $trip ) {
	return $trip['name'] . ', ' . $trip['dates'];
}

/**
 * Fill the trip select from the adventure posts.
 *
 * $replace is true while CF7 renders the form, false while it scans the
 * form to validate a submission. Rendering lists what the viewer may see
 * (published trips, plus drafts for logged-in editors previewing).
 * Validation accepts every trip that is not trashed, since CF7 rejects any
 * value not on the list and a submission made from a draft preview must
 * still go through.
 *
 * @param array $tag     Scanned form tag.
 * @param bool  $replace Rendering (true) or scanning (false).
 * @return array
 */
function arv_adv_form_tag( $tag, $replace ) {
	if ( ! is_array( $tag ) || ! isset( $tag['name'] ) || 'adv-trip' !== $tag['name'] ) {
		return $tag;
	}
	if ( ! function_exists( 'arv_adv_trips' ) ) {
		return $tag;
	}

	$trips  = arv_adv_trips( $replace ? null : true );
	$values = array( 'Any trip, keep me posted' );
	$pick   = 0;
	foreach ( $trips as $trip ) {
		$values[] = arv_adv_form_label( $trip );
		if ( '' !== $GLOBALS['arv_adv_form_preselect'] && $trip['slug'] === $GLOBALS['arv_adv_form_preselect'] ) {
			$pick = count( $values );
		}
	}

	$tag['raw_values'] = $values;
	$tag['values']     = $values;
	$tag['labels']     = $values;
	if ( class_exists( 'WPCF7_Pipes' ) ) {
		$tag['pipes'] = new WPCF7_Pipes( $values );
	}

	$options = array();
	foreach ( (array) $tag['options'] as $opt ) {
		if ( 0 !== strpos( $opt, 'default:' ) ) {
			$options[] = $opt;
		}
	}
	if ( $replace && $pick ) {
		$options[] = 'default:' . $pick;
	}
	$tag['options'] = $options;

	return $tag;
}
add_filter( 'wpcf7_form_tag', 'arv_adv_form_tag', 10, 2 );
