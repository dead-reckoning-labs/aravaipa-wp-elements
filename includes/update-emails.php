<?php
/**
 * Quiet the routine auto-update result emails.
 *
 * Auto-updates are enabled for most plugins and for the theme, so WordPress
 * mails admin_email every time the updater finishes a run. That address is a
 * real person's inbox, and "everything updated cleanly" is not news: it
 * arrives on someone else's desk with nothing to do about it.
 *
 * A failed update is the opposite. That is the only case where a human has to
 * act, so those emails still go out. What is suppressed here is strictly the
 * all-clear.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suppress the plugin and theme auto-update email when nothing failed.
 *
 * WordPress hands the filter the result set it is about to summarise. Each
 * entry carries `result === true` when that item updated cleanly, so a set
 * where every entry is true is a run with nothing to report. Anything else,
 * including a WP_Error, falls through and the mail is sent as normal.
 *
 * @param bool  $enabled        Whether to send the email.
 * @param array $update_results Results for the items the updater just handled.
 * @return bool
 */
function arv_quiet_clean_update_emails( $enabled, $update_results ) {
	foreach ( (array) $update_results as $result ) {
		if ( ! isset( $result->result ) || true !== $result->result ) {
			return $enabled;
		}
	}

	return false;
}
add_filter( 'auto_plugin_update_send_email', 'arv_quiet_clean_update_emails', 10, 2 );
add_filter( 'auto_theme_update_send_email', 'arv_quiet_clean_update_emails', 10, 2 );

/**
 * Same rule for core updates, which report their outcome as a type string.
 *
 * 'fail' and 'critical' are left alone deliberately: a core update that did
 * not land is the one piece of update mail worth waking up for.
 *
 * @param bool   $send Whether to send the email.
 * @param string $type One of 'success', 'fail', 'manual', 'critical'.
 * @return bool
 */
function arv_quiet_clean_core_update_emails( $send, $type ) {
	return 'success' === $type ? false : $send;
}
add_filter( 'auto_core_update_send_email', 'arv_quiet_clean_core_update_emails', 10, 2 );
