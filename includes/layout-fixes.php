<?php
/**
 * Site-wide layout fixes for Cornerstone pages.
 *
 * Race pages use a layout column whose only content is a background photo,
 * beside the intro text. On desktop the row stretches it to the text's height;
 * once the row stacks (tablet, phone) the column has nothing inside to give it
 * height and collapses to a 50px strip of photo. Give those photo-only columns
 * a real height so the image reads as a photo again.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Collapsed panels stay readable.
 *
 * The X theme hides a closed accordion panel and an inactive tab panel with
 * display:none (plus aria-hidden="true"), which drops them from the
 * accessibility tree. On a race page that is most of the useful content:
 * cutoffs, aid stations, drop bags, the schedule, the waitlist FAQ, and two
 * of the three distances' course details. Screen readers and AI agents that
 * read only the accessibility tree saw none of it.
 *
 * Swapped for the standard visually hidden clip, so a closed panel still
 * takes no space and shows nothing, but stays in the tree.
 * assets/aravaipa-a11y.js removes the aria-hidden and keeps links in a
 * closed panel out of the tab order. Scoped to the theme's own collapse
 * markers, so the open and animating states are untouched: the theme
 * measures a panel's height only after it has removed .x-collapsed.
 */
const ARV_LAYOUT_FIXES_CSS = '.x-col:has(> .x-bg):not(:has(> :not(.x-bg))){min-height:clamp(220px,45vw,420px)}'
	. '.x-acc-item>[data-x-toggle-collapse].x-collapsed,.x-tabs-panels>.x-tabs-panel:not(.x-active)'
	. '{display:block!important;position:absolute!important;width:1px!important;height:1px!important;'
	. 'margin:-1px!important;padding:0!important;border:0!important;overflow:hidden!important;'
	. 'clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important}';

add_action(
	'wp_head',
	function () {
		echo '<style id="arv-layout-fixes">' . ARV_LAYOUT_FIXES_CSS . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	99
);
