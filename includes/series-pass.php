<?php
/**
 * Series pass block: [aravaipa_series_pass series="sonoran-desert-cup"].
 *
 * One small block under the registration area of every race in a series:
 * the series name, one line on the deal with the real price, a button to the
 * pass on RaceGoat and a button to the series page. Every series reads the
 * same file, includes/data/series-passes.json, so the copy and the price live
 * in one place instead of being pasted into seventeen race pages.
 *
 * Prices must not go stale on a page nobody re-edits, so each price carries
 * the dates it is valid for and the pass carries its close date:
 *
 * - The deal line shows the price valid today in the series' own timezone.
 *   One price: "$432 through Dec 14" when a later step is already known.
 *   Several tiers: the lowest one, through the series' price_label template.
 * - No price valid today: the deal line still shows, without a price.
 * - After closes_at: the pass button and the deal line hide, and only the
 *   series page button stays, so a page cached for months never sells a
 *   pass that RaceGoat no longer sells.
 *
 * WP Rocket caches the rendered page, so "today" is the day the cache was
 * built. The cache is reset after every site change and expires on its own,
 * so a price step shows up at most one cache lifetime late.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every series, keyed by slug, from includes/data/series-passes.json.
 *
 * A missing or broken file returns an empty list: the shortcode then renders
 * nothing rather than a fatal on a page that sells race entries.
 *
 * @return array<string, array>
 */
function arv_series_pass_all() {
	static $all = null;
	if ( null !== $all ) {
		return $all;
	}
	$all  = array();
	$file = ARV_ELEMENTS_PATH . 'includes/data/series-passes.json';
	if ( ! is_readable( $file ) ) {
		return $all;
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) {
		return $all;
	}
	foreach ( $data as $key => $series ) {
		if ( is_array( $series ) && '_' !== substr( (string) $key, 0, 1 ) ) {
			$all[ (string) $key ] = $series;
		}
	}
	return $all;
}

/**
 * Whole dollars when the price is whole ("$360"), cents otherwise ("$382.24").
 *
 * @param int $cents Price in cents.
 * @return string
 */
function arv_series_pass_money( $cents ) {
	$cents = (int) $cents;
	if ( 0 === $cents % 100 ) {
		return '$' . number_format( $cents / 100 );
	}
	return '$' . number_format( $cents / 100, 2 );
}

/**
 * "Dec 14" for a Y-m-d date. Month names are fixed English on purpose: the
 * site is English and this must not depend on the server locale.
 *
 * @param string $ymd Date as Y-m-d.
 * @return string
 */
function arv_series_pass_short_date( $ymd ) {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $ymd );
	return $d ? $d->format( 'M j' ) : '';
}

/**
 * What the block shows for one series at one moment. Pure: no WordPress, so
 * arv-series-pass-test.php can walk it across every price step.
 *
 * @param array $series One entry from series-passes.json.
 * @param int   $now    Unix time to evaluate at.
 * @return array{name:string,season:string,closed:bool,deal:?string,price:?string,pass_url:?string,pass_label:string,page_url:string,page_label:string}
 */
function arv_series_pass_view( array $series, $now ) {
	$tz_name = isset( $series['timezone'] ) ? (string) $series['timezone'] : 'America/Phoenix';
	try {
		$tz = new DateTimeZone( $tz_name );
	} catch ( Exception $e ) {
		$tz = new DateTimeZone( 'America/Phoenix' );
	}
	$today = ( new DateTimeImmutable( '@' . (int) $now ) )->setTimezone( $tz )->format( 'Y-m-d' );

	$closed = false;
	if ( ! empty( $series['closes_at'] ) ) {
		$close = strtotime( (string) $series['closes_at'] );
		$closed = ( false !== $close && (int) $now > $close );
	}

	// Prices valid today, one per tier.
	$current = array();
	$prices  = isset( $series['prices'] ) && is_array( $series['prices'] ) ? $series['prices'] : array();
	foreach ( $prices as $p ) {
		if ( ! is_array( $p ) || empty( $p['cents'] ) || (int) $p['cents'] <= 0 ) {
			continue;
		}
		$from  = isset( $p['from'] ) ? (string) $p['from'] : '';
		$until = isset( $p['until'] ) ? (string) $p['until'] : '';
		if ( ( '' === $from || $from <= $today ) && ( '' === $until || $today <= $until ) ) {
			$current[] = $p;
		}
	}

	$price = null;
	if ( count( $current ) === 1 ) {
		$p     = $current[0];
		$price = arv_series_pass_money( $p['cents'] );
		// "through Dec 14" only when the next step is already known, so the
		// line never implies a rise nobody has priced yet.
		$until = isset( $p['until'] ) ? (string) $p['until'] : '';
		if ( '' !== $until ) {
			foreach ( $prices as $q ) {
				if ( is_array( $q ) && ! empty( $q['from'] ) && (string) $q['from'] > $until
					&& ( $q['tier'] ?? '' ) === ( $p['tier'] ?? '' ) ) {
					$price .= ' through ' . arv_series_pass_short_date( $until );
					break;
				}
			}
		}
	} elseif ( count( $current ) > 1 ) {
		$min = min( array_map( function ( $p ) { return (int) $p['cents']; }, $current ) );
		$price = arv_series_pass_money( $min );
	}

	$deal = isset( $series['deal'] ) ? trim( (string) $series['deal'] ) : '';
	if ( '' !== $deal && null !== $price ) {
		$label = isset( $series['price_label'] ) && '' !== $series['price_label'] ? (string) $series['price_label'] : '{price}';
		$deal .= ', ' . str_replace( '{price}', $price, $label );
	}
	if ( '' !== $deal ) {
		$deal .= '.';
	}

	return array(
		'name'       => isset( $series['name'] ) ? (string) $series['name'] : '',
		'season'     => isset( $series['season'] ) ? (string) $series['season'] : '',
		'closed'     => $closed,
		'deal'       => ( $closed || '' === $deal ) ? null : $deal,
		'price'      => $closed ? null : $price,
		'pass_url'   => ( $closed || empty( $series['pass_url'] ) ) ? null : (string) $series['pass_url'],
		'pass_label' => isset( $series['pass_label'] ) ? (string) $series['pass_label'] : 'Get the pass',
		'page_url'   => isset( $series['page_url'] ) ? (string) $series['page_url'] : '',
		'page_label' => isset( $series['page_label'] ) ? (string) $series['page_label'] : 'About the cup',
	);
}

/**
 * Markup for one view. Square, flat, no accent stripe: brand yellow only on
 * the pass button.
 *
 * @param string $key  Series slug, used for the modifier class.
 * @param array  $view From arv_series_pass_view().
 * @return string
 */
function arv_series_pass_html( $key, array $view ) {
	if ( '' === $view['name'] || ( null === $view['pass_url'] && '' === $view['page_url'] ) ) {
		return '';
	}
	$slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $key ) );

	$h  = '<div class="aravaipa-series-pass aravaipa-series-pass--' . esc_attr( $slug ) . '" role="group" aria-label="' . esc_attr( $view['name'] . ' pass' ) . '">';
	$h .= '<p class="aravaipa-series-pass__name">' . esc_html( $view['name'] );
	if ( '' !== $view['season'] ) {
		$h .= ' <span class="aravaipa-series-pass__season">' . esc_html( $view['season'] ) . '</span>';
	}
	$h .= '</p>';
	if ( null !== $view['deal'] ) {
		$h .= '<p class="aravaipa-series-pass__deal">' . esc_html( $view['deal'] ) . '</p>';
	}
	$h .= '<div class="aravaipa-series-pass__actions">';
	if ( null !== $view['pass_url'] ) {
		$h .= '<a class="aravaipa-series-pass__btn aravaipa-series-pass__btn--pass" href="' . esc_url( $view['pass_url'] ) . '">' . esc_html( $view['pass_label'] ) . '</a>';
	}
	if ( '' !== $view['page_url'] ) {
		$h .= '<a class="aravaipa-series-pass__btn aravaipa-series-pass__btn--page" href="' . esc_url( $view['page_url'] ) . '">' . esc_html( $view['page_label'] ) . '</a>';
	}
	$h .= '</div></div>';
	return $h;
}

/**
 * The stylesheet, from the page head when the page is known to carry the
 * block and from render time otherwise (WordPress then prints it in the
 * footer, which still styles the block, just a beat later).
 */
function arv_series_pass_enqueue() {
	wp_enqueue_style(
		'aravaipa-series-pass',
		ARV_ELEMENTS_URL . 'assets/aravaipa-series-pass.css',
		array(),
		ARV_ELEMENTS_VERSION
	);
}

/**
 * Head enqueue for pages whose content or Cornerstone data names the
 * shortcode, so the block is styled on first paint.
 */
function arv_series_pass_maybe_enqueue() {
	if ( ! is_singular() ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! $id ) {
		return;
	}
	$content = (string) get_post_field( 'post_content', $id );
	$cs      = get_post_meta( $id, '_cornerstone_data', true );
	if ( false !== strpos( $content, 'aravaipa_series_pass' )
		|| ( is_string( $cs ) && false !== strpos( $cs, 'aravaipa_series_pass' ) ) ) {
		arv_series_pass_enqueue();
	}
}
add_action( 'wp_enqueue_scripts', 'arv_series_pass_maybe_enqueue' );

/**
 * [aravaipa_series_pass series="sonoran-desert-cup"]
 *
 * An unknown series renders nothing (and an HTML comment for whoever is
 * editing the page), never a fatal.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function arv_series_pass_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'series' => '' ), $atts, 'aravaipa_series_pass' );
	$key  = sanitize_key( $atts['series'] );
	$all  = arv_series_pass_all();
	if ( '' === $key || ! isset( $all[ $key ] ) ) {
		return '<!-- aravaipa_series_pass: unknown series -->';
	}
	arv_series_pass_enqueue();
	return arv_series_pass_html( $key, arv_series_pass_view( $all[ $key ], time() ) );
}
add_shortcode( 'aravaipa_series_pass', 'arv_series_pass_shortcode' );
