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

add_action(
	'wp_head',
	function () {
		echo '<style id="arv-layout-fixes">.x-col:has(> .x-bg):not(:has(> :not(.x-bg))){min-height:clamp(220px,45vw,420px)}</style>' . "\n";
	},
	99
);
