<?php
/**
 * Build a self-contained Region Map for a Cornerstone "Raw Content" element.
 *
 * The plugin is the better long term home for this element, but it is not
 * installed on aravaiparunning.com (checked: the live plugin list is
 * ActiveCampaign, Cornerstone, Flip Boxes, Team Members, Video Background,
 * WP Rocket), and installing a plugin on the site that sells the race entries
 * is a bigger step than swapping one Raw Content block for another. This
 * writes a single paste-in block that needs no plugin at all.
 *
 * Generated, never hand edited. Everything comes from the same sources the
 * plugin element uses:
 *
 *   markup  arv_region_map_render(), the exact render callback
 *   CSS     every rule in assets/aravaipa-elements.css whose selector
 *           mentions arv-region-map, @media wrappers preserved
 *   map     assets/us-outline.svg, inlined so the block depends on no file
 *           the plugin would otherwise have served
 *
 * Editing the element and re-running this is what keeps the two in sync. A
 * hand-maintained second copy would drift the first time either changed.
 *
 *   php arv-standalone.php        # writes standalone-region-map.html
 */

define( 'ABSPATH', true );
define( 'ARV_ELEMENTS_URL', './' );
define( 'ARV_ELEMENTS_PATH', __DIR__ . '/' );

function cs_register_element( $n, $c ) {
	$GLOBALS['ARV_EL'][ $n ] = $c;
}
function cs_value( $d, $x = 'markup', $p = false ) {
	return $d;
}
function cs_compose_values( $v, ...$p ) {
	return $v;
}
function cs_compose_controls( $c, ...$p ) {
	return $c;
}
function cs_partial_controls( $n ) {
	return array();
}
function __( $s, $d = '' ) {
	return $s;
}
function _x( $s, $ctx, $d = '' ) {
	return $s;
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/elements/region-map.php';

/**
 * Pull every rule mentioning a selector fragment out of a stylesheet,
 * preserving the @media block any rule sat inside.
 *
 * Written against this one stylesheet rather than as a general CSS parser:
 * it assumes no braces inside comments or strings, which holds here and is
 * checked by the brace balance assertion at the end of the walk.
 *
 * @param string $css      Stylesheet source.
 * @param string $fragment Selector substring to keep.
 * @return string
 */
function arv_extract_rules( $css, $fragment ) {
	// Comments carry the reasoning for the plugin's own maintainers. The
	// paste-in block is generated output nobody edits in place, so they are
	// dead weight in it.
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );

	$out    = '';
	$len    = strlen( $css );
	$i      = 0;
	$buffer = '';

	while ( $i < $len ) {
		$ch = $css[ $i ];

		if ( '{' !== $ch ) {
			$buffer .= $ch;
			$i++;
			continue;
		}

		$selector = trim( $buffer );
		$buffer   = '';

		// Walk to the matching close brace, counting depth so a nested
		// @media block is consumed whole rather than ending at its first
		// inner rule's brace.
		$depth = 1;
		$start = $i + 1;
		$i++;
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $css[ $i ] ) {
				$depth++;
			} elseif ( '}' === $css[ $i ] ) {
				$depth--;
			}
			$i++;
		}
		$body = substr( $css, $start, ( $i - 1 ) - $start );

		if ( 0 === strpos( $selector, '@media' ) ) {
			// Recurse: keep the wrapper only if something inside survives,
			// so an @media block with no matching rule does not ship as an
			// empty pair of braces.
			$inner = arv_extract_rules( $body, $fragment );
			if ( '' !== trim( $inner ) ) {
				$out .= $selector . " {\n" . $inner . "}\n";
			}
			continue;
		}

		// A selector list can mix matching and non-matching selectors
		// (the shared heading rule names five elements). Keep only the
		// parts that belong to this element, or the block would drag
		// styling for the other four into the paste-in copy.
		$parts = array_filter(
			array_map( 'trim', explode( ',', $selector ) ),
			function ( $s ) use ( $fragment ) {
				return false !== strpos( $s, $fragment );
			}
		);

		if ( ! empty( $parts ) ) {
			$out .= implode( ",\n", $parts ) . ' {' . rtrim( $body ) . "\n}\n";
		}
	}

	return $out;
}

$css_source = file_get_contents( __DIR__ . '/assets/aravaipa-elements.css' );
$rules      = arv_extract_rules( $css_source, 'arv-region-map' );

if ( '' === trim( $rules ) ) {
	fwrite( STDERR, "no region map rules found, refusing to write an unstyled block\n" );
	exit( 1 );
}

// No hardcoded copy of the custom properties here. They live on a shared
// selector list in the plugin (--arv-accent and friends, declared once for
// all six elements), and the extractor above already narrows that list down
// to this element, so restating them would emit the same block twice.
//
// The SVG needs no handling here either: the element inlines it itself now
// (arv_region_map_svg), so that the page stylesheet can theme the state
// fills. Render output is already self contained.
$markup = arv_region_map_render(
	array_merge(
		$GLOBALS['ARV_EL']['aravaipa-region-map']['values'],
		array(
			'mod_id' => '',
			'class'  => '',
		)
	)
);

if ( '' === $markup ) {
	fwrite( STDERR, "render returned nothing, refusing to write an empty block\n" );
	exit( 1 );
}

// Bundled logos are inlined as data URIs for the same reason the SVG is:
// this block is pasted somewhere the plugin does not exist, so a relative
// ./assets/logos/ path would resolve against the page's own URL and 404.
$markup = preg_replace_callback(
	'#src="\./assets/logos/([A-Za-z0-9._-]+)"#',
	function ( $m ) {
		$path = __DIR__ . '/assets/logos/' . basename( $m[1] );
		if ( ! file_exists( $path ) ) {
			fwrite( STDERR, "logo {$m[1]} referenced but missing, refusing to write a block with a broken image\n" );
			exit( 1 );
		}
		$mime = 'image/' . ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ? 'svg+xml' : strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) );
		return 'src="data:' . $mime . ';base64,' . base64_encode( file_get_contents( $path ) ) . '"';
	},
	$markup
);

// The block must carry the map itself, not a reference to a plugin asset
// URL that would 404 wherever the plugin is not installed, which is the
// whole point of this build.
if ( false === strpos( $markup, '<svg' ) ) {
	fwrite( STDERR, "render produced no inline SVG, refusing to write a block with no map in it\n" );
	exit( 1 );
}

// Catches anything still pointing into the plugin directory: the map as an
// <img>, a logo whose path did not match the inliner above, or any future
// asset added to an element without a matching case here.
if ( false !== strpos( $markup, 'ARV_ELEMENTS_URL' ) || preg_match( '#(src|href)="\.?/?assets/#', $markup ) || preg_match( '#<img[^>]*us-outline#', $markup ) ) {
	fwrite( STDERR, "render still references a plugin asset path, refusing to write a block that would 404\n" );
	exit( 1 );
}

$out  = "<!--\n";
$out .= "  Aravaipa Region Map. Paste this whole block into a Cornerstone\n";
$out .= "  \"Raw Content\" element.\n\n";
$out .= "  Generated by arv-standalone.php in dead-reckoning-labs/aravaipa-wp-elements.\n";
$out .= "  Edit there and re-run rather than editing this, or the next\n";
$out .= "  regeneration overwrites the change.\n\n";
$out .= "  Self contained: no plugin, no JavaScript, no external requests, no\n";
$out .= "  API keys. Pins are real links, so this works with JS disabled and is\n";
$out .= "  keyboard navigable.\n\n";
$out .= "  To move a pin or add a region, edit the rows in the element source\n";
$out .= "  (includes/elements/region-map.php) and re-run. Row format:\n";
$out .= "  Name | X% | Y% | landing page URL | detail | primary | full name\n";
$out .= "-->\n";
$out .= '<style>' . "\n" . $rules . '</style>' . "\n";
$out .= $markup . "\n";

file_put_contents( __DIR__ . '/standalone-region-map.html', $out );

printf(
	"wrote standalone-region-map.html (%s KB, %d CSS rules, 0 JS, 0 external requests)\n",
	number_format( strlen( $out ) / 1024, 1 ),
	substr_count( $rules, '{' )
);
