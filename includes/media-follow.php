<?php
/**
 * "Follow along" links, placed at the moment someone has just watched or
 * listened to something, not as a badge sitting in a corner nobody was
 * looking at.
 *
 * Deliberately not YouTube's own subscribe button. That widget loads
 * YouTube's platform JS and drags in its tracking on every page it sits
 * on, which is a real cost for a control with a low baseline conversion
 * rate wherever it is placed. A plain link to the channel with
 * ?sub_confirmation=1 opens the same subscribe dialog without any of
 * that, and looks like the rest of the site instead of like a widget
 * dropped onto it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $platform 'youtube' is the only one wired up today.
 * @param string $context  A short phrase finishing "so you don't miss the
 *                          next {context}", e.g. "film" or "broadcast".
 * @return string
 */
function arv_media_follow_render( $platform, $context ) {
	if ( 'youtube' !== $platform ) {
		return '';
	}

	return '<p class="arv-media-follow">'
		. '<a class="arv-media-follow__link" href="https://www.youtube.com/@aravaiparunning?sub_confirmation=1" target="_blank" rel="noopener">'
		. esc_html__( 'Subscribe on YouTube', 'aravaipa-elements' )
		. '</a>'
		. ' <span class="arv-media-follow__why">'
		/* translators: %s: what a subscriber gets notified about, e.g. "film" or "broadcast". */
		. sprintf( esc_html__( 'so you don\'t miss the next %s', 'aravaipa-elements' ), esc_html( $context ) )
		. '</span></p>';
}
