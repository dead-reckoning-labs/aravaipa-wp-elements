<?php
/**
 * The Articles index: every blog post, as the same dark card grid the rest
 * of Media uses.
 *
 * /blog/ is WordPress's own posts page, so it renders through the theme's
 * archive template rather than through anything in this plugin. That is
 * why it was the one Media destination that did not look like the others:
 * light where they are dark, a serif display title where they use the
 * site's own sans, ten posts a page where Watch, Films and Photos each put
 * their whole archive on one searchable page, and no Media sub-nav at all
 * because a posts page has no content field to put a shortcode in.
 *
 * The fix is a real page carrying this element, the same way /media/watch/
 * carries [arv_watch], rather than an attempt to reach inside the theme.
 *
 * Everything is rendered server side and filtered client side, the same
 * contract every other index here uses: with JavaScript off the page is
 * the complete archive, and a search engine sees all 296 posts rather than
 * the first ten and a "next page" link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_ARTICLES_CACHE', 'arv_articles_items' );

/**
 * The first image in a post's body, for a post with no featured image.
 *
 * 71 of the 296 posts here have no featured image, and 58 of those do have
 * a perfectly good photograph in the body: whoever wrote them uploaded a
 * picture and never set it as the featured one. Falling back to it turns
 * 58 placeholder tiles into real cards, and the archive stops looking half
 * broken for reasons that have nothing to do with the archive.
 *
 * Read off the raw post_content rather than by running the_content: this
 * runs once for every post in the table, and the filter chain on a site
 * with Jetpack and a page builder installed is not something to invoke 296
 * times to find an src attribute. The raw markup also carries the original
 * upload URL rather than the CDN's rewrite of it, which is the more stable
 * of the two to store.
 *
 * @param string $content Raw post content.
 * @return string
 */
function arv_articles_body_image( $content ) {
	// \s before src, or "data-src" matches as though it were "src": the
	// leading [^>]* happily eats "data-" and the tail lines up. That reads
	// as a working fallback right up until a lazy-loading plugin is turned
	// on, at which point every card silently takes the placeholder GIF.
	if ( ! preg_match( '/<img[^>]*?\ssrc=["\']([^"\']+)["\']/i', (string) $content, $m ) ) {
		return '';
	}

	$url = trim( html_entity_decode( $m[1] ) );

	// Data URIs are placeholders left by lazy-loading scripts, and the real
	// source sits in a data-src the raw markup does not have. A 1x1
	// transparent GIF stretched across a card is worse than the panel.
	if ( '' === $url || 0 === stripos( $url, 'data:' ) ) {
		return '';
	}

	return $url;
}

/**
 * Every published post, newest first.
 *
 * Deliberately not the Latest feed's reader, though the two look similar.
 * That one asks for forty and drops anything with no featured image,
 * because a mixed feed of four sources can afford to show only the posts
 * that look good beside a broadcast thumbnail. This is the archive: it has
 * to hold every article or the count on /media/ is a lie, so a post with
 * no picture gets a placeholder panel here rather than being dropped.
 *
 * Cached for the hour and flushed on save, like the feed's reader, since
 * this is the one query in the plugin that reads the whole posts table.
 *
 * @return array<int, array>
 */
function arv_articles_items() {
	$cached = get_transient( ARV_ARTICLES_CACHE );

	if ( false !== $cached ) {
		return ( 'none' === $cached ) ? array() : $cached;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);

	// One query for every featured image instead of one per post. Without
	// this a 296-post archive is 296 extra round trips to build a page that
	// is then cached for an hour anyway, which is a slow first render for
	// whoever happens to be the one who warms it.
	if ( function_exists( '_prime_post_caches' ) ) {
		$thumbs = array();

		foreach ( $posts as $post ) {
			$id = (int) get_post_thumbnail_id( $post->ID );

			if ( $id ) {
				$thumbs[] = $id;
			}
		}

		if ( ! empty( $thumbs ) ) {
			_prime_post_caches( $thumbs, false, false );
		}
	}

	$items = array();

	foreach ( $posts as $post ) {
		$date  = get_the_date( 'c', $post );
		$thumb = get_the_post_thumbnail_url( $post->ID, 'medium' );

		// A post that never had a featured image set, but does have one in
		// its body. 58 of these, so it is the difference between an archive
		// that looks finished and one that looks half broken.
		if ( ! $thumb && isset( $post->post_content ) ) {
			$thumb = arv_articles_body_image( $post->post_content );
		}

		$cats  = array();

		foreach ( (array) get_the_category( $post->ID ) as $cat ) {
			// Nobody chose "Uncategorized", it is what WordPress does when
			// nobody chose anything, so it is not a filter worth offering.
			if ( isset( $cat->name ) && 'Uncategorized' !== $cat->name ) {
				$cats[] = $cat->name;
			}
		}

		$items[] = array(
			'title' => get_the_title( $post ),
			'url'   => get_permalink( $post ),
			'date'  => (string) $date,
			'year'  => (int) substr( (string) $date, 0, 4 ),
			'thumb' => $thumb ? $thumb : '',
			'cats'  => $cats,
		);
	}

	set_transient( ARV_ARTICLES_CACHE, empty( $items ) ? 'none' : $items, HOUR_IN_SECONDS );

	return $items;
}

/**
 * Drop the cache the moment a post is published or edited, rather than
 * making a fresh article wait up to an hour to reach the archive.
 *
 * @param int $post_id
 */
function arv_articles_flush_on_save( $post_id ) {
	if ( 'post' === get_post_type( $post_id ) ) {
		delete_transient( ARV_ARTICLES_CACHE );
	}
}
add_action( 'save_post', 'arv_articles_flush_on_save' );

/**
 * Every year that has an article, newest first.
 *
 * @param array $items
 * @return array<int, int>
 */
function arv_articles_years( $items ) {
	$years = array();

	foreach ( $items as $item ) {
		if ( $item['year'] ) {
			$years[ $item['year'] ] = true;
		}
	}

	$years = array_keys( $years );
	rsort( $years );

	return $years;
}

/**
 * Every category in use, most used first.
 *
 * Ordered by how many articles carry it rather than alphabetically: the
 * list is there to be picked from, and "Race Report" with eighty behind it
 * should not sit below a category with one.
 *
 * @param array $items
 * @return array<int, string>
 */
function arv_articles_categories( $items ) {
	$counts = array();

	foreach ( $items as $item ) {
		foreach ( $item['cats'] as $cat ) {
			$counts[ $cat ] = isset( $counts[ $cat ] ) ? $counts[ $cat ] + 1 : 1;
		}
	}

	arsort( $counts );

	return array_keys( $counts );
}

/**
 * The search box, the category filter and the year filter.
 *
 * Progressive enhancement over cards already in the HTML, the same
 * contract the Films shelf, the Watch archive and the Photos grid all use.
 *
 * @param array $years
 * @param array $cats
 * @return string
 */
function arv_articles_controls( $years, $cats ) {
	$out = '<div class="arv-articles__controls">';

	$out .= '<span class="arv-articles__search-field">';
	$out .= '<input class="arv-articles__search-input" type="search" autocomplete="off"'
		. ' placeholder="' . esc_attr__( 'Search articles', 'aravaipa-elements' ) . '"'
		. ' aria-label="' . esc_attr__( 'Search articles', 'aravaipa-elements' ) . '"'
		. ' data-arv-articles-search />';
	$out .= '</span>';

	if ( count( $cats ) > 1 ) {
		$out .= '<select class="arv-articles__select" data-arv-articles-cat aria-label="'
			. esc_attr__( 'Filter by category', 'aravaipa-elements' ) . '">';
		$out .= '<option value="">' . esc_html__( 'Every category', 'aravaipa-elements' ) . '</option>';

		foreach ( $cats as $cat ) {
			$out .= '<option value="' . esc_attr( strtolower( $cat ) ) . '">' . esc_html( $cat ) . '</option>';
		}

		$out .= '</select>';
	}

	if ( count( $years ) > 1 ) {
		$out .= '<select class="arv-articles__select" data-arv-articles-year aria-label="'
			. esc_attr__( 'Filter by year', 'aravaipa-elements' ) . '">';
		$out .= '<option value="">' . esc_html__( 'Every year', 'aravaipa-elements' ) . '</option>';

		foreach ( $years as $year ) {
			$out .= '<option value="' . esc_attr( $year ) . '">' . esc_html( $year ) . '</option>';
		}

		$out .= '</select>';
	}

	return $out . '</div>';
}

/**
 * One article: its picture, its category, its title and its date.
 *
 * A post with no featured image gets a panel rather than a gap, the same
 * way a gallery with no cover does on Photos: a card with nothing where
 * its picture should be reads as broken beside cards that have one.
 *
 * @param array $item
 * @return string
 */
function arv_articles_card( $item ) {
	$stamp = strtotime( $item['date'] );
	$cats  = array();

	foreach ( $item['cats'] as $cat ) {
		$cats[] = strtolower( $cat );
	}

	$out = '<li class="arv-articles__card"'
		. ' data-arv-articles-title="' . esc_attr( strtolower( $item['title'] ) ) . '"'
		. ' data-arv-articles-cat="' . esc_attr( implode( '|', $cats ) ) . '"'
		. ' data-arv-articles-year="' . esc_attr( $item['year'] ? $item['year'] : '' ) . '">';

	$out .= '<a class="arv-articles__link" href="' . esc_url( $item['url'] ) . '">';

	if ( '' !== $item['thumb'] ) {
		$out .= '<img class="arv-articles__thumb" src="' . esc_url( $item['thumb'] ) . '" alt=""'
			. ' loading="lazy" decoding="async" width="480" height="270" />';
	} else {
		$out .= '<span class="arv-articles__thumb arv-articles__thumb--none" aria-hidden="true">'
			. '<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5">'
			. '<path d="M5 3h11l3 3v15H5z"/><path d="M8 9h8M8 13h8M8 17h5"/>'
			. '</svg></span>';
	}

	$out .= '<span class="arv-articles__body">';

	if ( ! empty( $item['cats'] ) ) {
		$out .= '<span class="arv-articles__cat">' . esc_html( $item['cats'][0] ) . '</span>';
	}

	$out .= '<span class="arv-articles__title">' . esc_html( $item['title'] ) . '</span>';

	if ( $stamp ) {
		$out .= '<span class="arv-articles__date">' . esc_html( gmdate( 'F j, Y', $stamp ) ) . '</span>';
	}

	return $out . '</span></a></li>';
}

/**
 * The Articles archive.
 *
 * @param array $args heading, intro, limit.
 * @return string
 */
function arv_articles_render( $args = array() ) {
	$items = arv_articles_items();

	if ( empty( $items ) ) {
		return '';
	}

	$heading = isset( $args['heading'] ) ? trim( (string) $args['heading'] ) : 'Articles';
	$intro   = isset( $args['intro'] ) ? trim( (string) $args['intro'] ) : '';
	$limit   = isset( $args['limit'] ) ? (int) $args['limit'] : 0;

	if ( $limit > 0 ) {
		$items = array_slice( $items, 0, $limit );
	}

	$out  = '<section class="arv-articles">';
	$out .= '<div class="arv-articles__inner">';

	if ( '' !== $heading ) {
		$out .= '<h2 class="arv-articles__heading">' . esc_html( $heading ) . '</h2>';
	}

	if ( '' !== $intro ) {
		$out .= '<p class="arv-articles__intro">' . esc_html( $intro ) . '</p>';
	}

	// Only on the full archive, not a limited block: three controls over
	// six cards is furniture, the same gate Watch puts on its search box.
	if ( 0 === $limit ) {
		$out .= arv_articles_controls( arv_articles_years( $items ), arv_articles_categories( $items ) );
	}

	$out .= '<ul class="arv-articles__grid" data-arv-articles-grid>';

	foreach ( $items as $item ) {
		$out .= arv_articles_card( $item );
	}

	$out .= '</ul>';

	if ( 0 === $limit ) {
		$out .= '<p class="arv-articles__count" data-arv-articles-count aria-live="polite"></p>';
	}

	return $out . '</div></section>';
}

/**
 * [arv_articles] so a page can carry this without Cornerstone.
 *
 * @param array $atts
 * @return string
 */
function arv_articles_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Articles',
			'intro'   => '',
			'limit'   => 0,
		),
		$atts,
		'arv_articles'
	);

	return arv_articles_render( $atts );
}
add_shortcode( 'arv_articles', 'arv_articles_shortcode' );

/**
 * The same posts, sized for a sidebar.
 *
 * The blog sidebar ran WordPress's own Recent Posts widget, which is a
 * bare <ul> of titles and dates. On a site whose whole subject is
 * photographs of people running through mountains, a text list is the one
 * thing the sidebar could have been that carries none of that.
 *
 * Built here rather than swapped for core's Latest Posts block, which does
 * support featured images but shows a blank where a post has none. Three
 * of the twenty most recent posts have no featured image set, and
 * arv_articles_items() already solves that by falling back to the first
 * photograph in the body: 58 posts across the archive are only illustrated
 * because of that fallback, so reusing it is the difference between a rail
 * that looks finished and one with holes in it.
 *
 * @param array $atts heading, limit.
 * @return string
 */
function arv_articles_rail_render( $atts ) {
	$limit = max( 1, (int) $atts['limit'] );
	$items = array_slice( arv_articles_items(), 0, $limit );

	if ( empty( $items ) ) {
		return '';
	}

	$out = '<section class="arv-articles-rail">';

	if ( '' !== trim( (string) $atts['heading'] ) ) {
		$out .= '<h4 class="arv-articles-rail__head">' . esc_html( $atts['heading'] ) . '</h4>';
	}

	$out .= '<ul class="arv-articles-rail__list">';

	foreach ( $items as $item ) {
		$out .= '<li class="arv-articles-rail__item">'
			. '<a class="arv-articles-rail__link" href="' . esc_url( $item['url'] ) . '">';

		// A tile either way. Without one the rows below it shift left and
		// the rail stops reading as a list of the same kind of thing.
		$out .= '<span class="arv-articles-rail__thumb">';

		if ( '' !== $item['thumb'] ) {
			$out .= '<img src="' . esc_url( $item['thumb'] ) . '" alt="" loading="lazy" decoding="async" />';
		}

		$out .= '</span>';

		$out .= '<span class="arv-articles-rail__body">';

		if ( ! empty( $item['cats'] ) ) {
			$out .= '<span class="arv-articles-rail__cat">' . esc_html( $item['cats'][0] ) . '</span>';
		}

		$out .= '<span class="arv-articles-rail__title">' . esc_html( $item['title'] ) . '</span>';

		$stamp = strtotime( $item['date'] );

		if ( $stamp ) {
			$out .= '<time class="arv-articles-rail__date" datetime="' . esc_attr( gmdate( 'Y-m-d', $stamp ) ) . '">'
				. esc_html( gmdate( 'F j, Y', $stamp ) ) . '</time>';
		}

		$out .= '</span></a></li>';
	}

	return $out . '</ul></section>';
}

/**
 * [arv_articles_rail] for the blog sidebar.
 *
 * @param array $atts
 * @return string
 */
function arv_articles_rail_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => 'Recent Posts',
			'limit'   => 5,
		),
		$atts,
		'arv_articles_rail'
	);

	return arv_articles_rail_render( $atts );
}
add_shortcode( 'arv_articles_rail', 'arv_articles_rail_shortcode' );

/**
 * Run shortcodes inside Custom HTML widgets.
 *
 * Core runs them in the legacy Text widget and in post content, but not
 * here, so a shortcode dropped into a Custom HTML widget renders as its
 * own literal source text. It fails quietly and looks like the shortcode
 * is broken rather than unsupported, which is exactly how [arv_articles_rail]
 * in the sidebar first appeared to do nothing at all.
 *
 * Widget content is admin-authored, the same trust level as post content,
 * so there is nothing here that do_shortcode does not already accept from
 * the editor.
 */
add_filter( 'widget_custom_html_content', 'do_shortcode', 11 );
