<?php
/**
 * Record which topic a contact form submission actually chose.
 *
 * The Contact Us form routes by topic using Contact Form 7's pipe syntax:
 *
 *   [select* category first_as_label
 *     "Registration, refunds or transfers|info@aravaiparunning.com"
 *     "Sponsorship and partnerships|jamil@aravaiparunning.com"
 *     ...]
 *
 * The browser submits the label. CF7 then maps it through the pipe, so
 * [category] in the mail template resolves to the address and the mail
 * routes correctly. That mapping is one-way and lossy: seven of the eight
 * topics point at info@aravaiparunning.com, so once converted there is no
 * telling "Lost and found" from "Media and press" from "Volunteering".
 *
 * Flamingo logs the converted value, which is why every submission on file
 * reads "info@aravaiparunning.com" as its category and a question like
 * "how many of today's enquiries were about registration" has no answer.
 * The label is not gone at that point, though: CF7 keeps the raw request
 * around, which is how [_raw_category] in the mail subject has been
 * printing the real topic all along. So this reads the same raw value CF7
 * does and writes it into what Flamingo stores.
 *
 * Nothing about routing or the outgoing mail changes. This only affects
 * what is recorded.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The topic the visitor picked, before CF7 piped it to an address.
 *
 * Read with wpcf7_superglobal_post(), the same accessor CF7's own
 * do_not_heat mail tags use, so this cannot disagree with what the email
 * says.
 *
 * @return string Empty when the form has no topic field, which is every
 *                form on the site except Contact Us.
 */
function arv_contact_raw_topic() {
	if ( ! function_exists( 'wpcf7_superglobal_post' ) ) {
		return '';
	}

	$raw = wpcf7_superglobal_post( 'category' );

	// A select can post an array. Flatten rather than assume, and take the
	// first entry: this field is single-select and a second value would
	// mean something upstream changed.
	if ( is_array( $raw ) ) {
		$raw = reset( $raw );
	}

	$raw = trim( (string) $raw );

	// first_as_label makes the opening option a prompt rather than a
	// choice. If it ever arrives, it is not a topic.
	if ( '' === $raw || 'Choose a topic' === $raw ) {
		return '';
	}

	return $raw;
}

/**
 * Store the topic, and give the entry a subject worth reading.
 *
 * Flamingo builds its subject from a field called "subject", which this
 * form does not have, so every row in the inbox was titled with the
 * literal, unresolved "[your-subject]". The topic is a better answer to
 * "what is this row" than a placeholder is.
 *
 * @param array $args Flamingo_Inbound_Message::add() parameters.
 * @return array
 */
function arv_contact_log_topic( $args ) {
	$topic = arv_contact_raw_topic();

	if ( '' === $topic ) {
		return $args;
	}

	if ( isset( $args['fields'] ) && is_array( $args['fields'] ) ) {
		$args['fields']['category'] = $topic;
	}

	// Only when Flamingo could not resolve one of its own, so a form that
	// does have a real subject field keeps it.
	$subject = isset( $args['subject'] ) ? trim( (string) $args['subject'] ) : '';

	if ( '' === $subject || preg_match( '/^\[[a-z0-9_-]+\]$/i', $subject ) ) {
		$args['subject'] = $topic;
	}

	return $args;
}
add_filter( 'wpcf7_flamingo_inbound_message_parameters', 'arv_contact_log_topic' );
