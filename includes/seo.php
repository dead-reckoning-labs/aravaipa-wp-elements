<?php
/**
 * Site-level SEO output.
 *
 * aravaiparunning.com runs no SEO plugin at all. That is why the homepage
 * had no meta description and the site had no structured data anywhere: not
 * a misconfiguration, just nothing responsible for it. This fills the two
 * gaps that cost the most, and does it defensively, because the day someone
 * installs Yoast or Rank Math this file has to get out of the way rather
 * than produce a second, competing description.
 *
 * Deliberately small. It is not an SEO plugin and should not grow into one:
 * per-page titles, canonicals, breadcrumbs and social cards are all better
 * handled by a real one if that day comes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True when another plugin is already handling descriptions and schema.
 *
 * Checked by class rather than by scanning the output buffer: by the time
 * wp_head runs there is no reliable way to know what a later-priority hook
 * is about to print, and printing first and hoping would be exactly the
 * duplicate-description problem this is trying to avoid.
 *
 * @return bool
 */
function arv_seo_handled_elsewhere() {
	return defined( 'WPSEO_VERSION' )            // Yoast
		|| class_exists( 'RankMath' )            // Rank Math
		|| defined( 'AIOSEO_VERSION' )           // All in One SEO
		|| defined( 'SEOPRESS_VERSION' );        // SEOPress
}

/**
 * Meta description for the front page.
 *
 * Front page only. Every other page on this site would need its own written
 * description to be worth having one, and a templated "X | Aravaipa Running"
 * on 178 pages is the kind of thing that reads as filler to a search engine
 * and to a person.
 *
 * Filterable so the text can be changed without editing the plugin.
 */
function arv_seo_meta_description() {
	if ( ! is_front_page() || arv_seo_handled_elsewhere() ) {
		return;
	}

	$default = 'Aravaipa Running organizes more than 70 trail, ultra and endurance races a year across Arizona, California, Colorado, Nevada, Utah, Tennessee, New Hampshire and the Great Lakes, including Cocodona 250, Javelina Jundred and Black Canyon 100K.';

	/**
	 * Filters the front page meta description.
	 *
	 * @param string $description
	 */
	$description = apply_filters( 'arv_seo_front_page_description', $default );

	if ( '' === trim( $description ) ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'arv_seo_meta_description', 1 );

/**
 * Organization and WebSite structured data, on the front page.
 *
 * This is the block that tells Google and any answer engine what this
 * organisation actually is: the name, the logo, where it operates, and which
 * social profiles are the real ones. Without it, a model answering "who runs
 * Cocodona 250" is inferring from prose rather than reading a fact.
 *
 * Front page only, because Organization describes the site as a whole and
 * repeating it on every page says nothing new.
 */
function arv_seo_organization_schema() {
	if ( ! is_front_page() || arv_seo_handled_elsewhere() ) {
		return;
	}

	$home = home_url( '/' );

	$organization = array(
		'@type'       => 'SportsOrganization',
		'@id'         => $home . '#organization',
		'name'        => 'Aravaipa Running',
		'url'         => $home,
		'description' => 'Trail, ultra and endurance race organizer.',
		'foundingDate' => '2009',
		'telephone'   => '+1-602-346-0554',
		'email'       => 'info@aravaiparunning.com',
		'address'     => array(
			'@type'           => 'PostalAddress',
			'addressRegion'   => 'AZ',
			'addressCountry'  => 'US',
		),
		// sameAs is how a search engine confirms these accounts are the same
		// entity as this site rather than fan pages using the name.
		'sameAs'      => array(
			'https://www.facebook.com/aravaiparunning',
			'https://instagram.com/aravaiparunning',
			'https://www.youtube.com/user/aravaiparunning',
			'https://twitter.com/aravaiparunning',
		),
	);

	/**
	 * Filters the Organization node before it is printed, for the fields
	 * most likely to need correcting without a plugin release: address,
	 * founding date, social profiles.
	 *
	 * @param array $organization
	 */
	$organization = apply_filters( 'arv_seo_organization', $organization );

	$graph = array(
		$organization,
		array(
			'@type'     => 'WebSite',
			'@id'       => $home . '#website',
			'url'       => $home,
			'name'      => 'Aravaipa Running',
			'publisher' => array( '@id' => $home . '#organization' ),
			// Tells Google the site has its own search, which is what powers
			// a sitelinks search box in results.
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => $home . '?s={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		),
	);

	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	if ( false === $json ) {
		return;
	}

	// Same reason as the races element: a "<" reaching a script body from a
	// filtered value could close the tag early.
	$json = str_replace( '<', '\u003C', $json );

	echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
add_action( 'wp_head', 'arv_seo_organization_schema', 2 );

/**
 * Encode a set of schema.org nodes as a JSON-LD script tag.
 *
 * @param array $nodes Top-level nodes for the graph.
 * @return string Empty when the payload cannot be encoded.
 */
function arv_seo_schema_script( $nodes ) {
	if ( empty( $nodes ) ) {
		return '';
	}

	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	if ( false === $json ) {
		return '';
	}

	// A "<" reaching a script body could close the tag early and spill the
	// rest of the JSON into the document as markup.
	$json = str_replace( '<', '\u003C', $json );

	return '<script type="application/ld+json">' . $json . '</script>' . "\n";
}

/**
 * SportsEvent structured data on an individual race's own page.
 *
 * These are the pages the searches actually land on. Someone looking up
 * "javelina jundred 2026" is asking a date-and-place question, and the page
 * answering it carried no machine-readable date, no location and no
 * registration link: everything was prose and Cornerstone markup. The
 * homepage has had SportsEvent for its six featured races the whole time,
 * so the data and the builder already existed, they were just never reached
 * from the one page per race where they matter most.
 *
 * Matched by URL against the race store, the same way Aravaipa Race Status
 * finds its race, so this needs no element on the page and no per-page
 * configuration. A page that is not a race simply finds nothing and prints
 * nothing.
 */
function arv_seo_race_schema() {
	if ( ! is_singular() || is_front_page() || arv_seo_handled_elsewhere() ) {
		return;
	}

	if ( ! function_exists( 'arv_race_store_find_by_page' ) || ! function_exists( 'arv_upcoming_races_event_schema' ) ) {
		return;
	}

	$race = arv_race_store_find_by_page();

	if ( null === $race ) {
		return;
	}

	$today  = arv_upcoming_races_today();
	$action = arv_upcoming_races_action( $race, $today );

	$event = arv_upcoming_races_event_schema( $race, $action['phase'] );

	/**
	 * Filters the SportsEvent node for a single race page.
	 *
	 * @param array $event
	 * @param array $race
	 */
	$event = apply_filters( 'arv_seo_race_event', $event, $race );

	echo arv_seo_schema_script( array( $event ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'arv_seo_race_schema', 3 );

/**
 * The races index: every upcoming race as one ItemList.
 *
 * /races/ lists the whole season and emitted nothing at all, while the
 * homepage described six races. For a search engine the index page was the
 * least informative page on the site about the thing the site does.
 *
 * Past races are left out rather than marked as over. A listing page is a
 * claim about what is on offer, and eighty entries of which half already
 * happened is a worse answer than forty that have not.
 *
 * ItemList rather than eighty bare events, because that is what the page is:
 * an ordered list, and the position is real information (it is sorted by
 * date). Which page this is, is a filter rather than a hardcoded slug, since
 * nothing in the markup at wp_head time can tell us the calendar is below.
 *
 * Each item is a position and a URL, not a repeat of the full event. That is
 * the summary-page shape Google documents: the listing enumerates and points,
 * each race's own page carries the SportsEvent with the dates, venue and
 * offer, which arv_seo_race_schema() above now puts there. Inlining all of it
 * here instead produced an 80KB script tag in the head of a page that already
 * renders eighty rows, to restate what is one click away.
 *
 * A race with no page of its own is skipped rather than inlined, since the
 * entry would be a list item pointing nowhere.
 */
function arv_seo_races_index_schema() {
	if ( ! is_page() || arv_seo_handled_elsewhere() ) {
		return;
	}

	if ( ! function_exists( 'arv_race_store_get' ) || ! function_exists( 'arv_upcoming_races_event_schema' ) ) {
		return;
	}

	/**
	 * Filters the paths that get the full race list. Relative, no slashes.
	 *
	 * @param array $paths
	 */
	$paths = apply_filters( 'arv_seo_races_index_paths', array( 'races' ) );

	$path = trim( (string) wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH ), '/' );

	if ( ! in_array( $path, (array) $paths, true ) ) {
		return;
	}

	$today = arv_upcoming_races_today();
	$items = array();

	foreach ( arv_race_store_get() as $race ) {
		// A race is over once its last day has passed; the end date is what
		// says so for a multi-day race, and the start date for everything
		// else.
		$last = ( '' !== $race['end'] ) ? $race['end'] : $race['iso'];
		if ( $today > $last ) {
			continue;
		}

		if ( '' === trim( (string) $race['page'] ) ) {
			continue;
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'name'     => $race['name'],
			'url'      => $race['page'],
		);
	}

	if ( empty( $items ) ) {
		return;
	}

	$list = array(
		'@type'           => 'ItemList',
		'name'            => 'Aravaipa Running races',
		'numberOfItems'   => count( $items ),
		'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
		'itemListElement' => $items,
	);

	/**
	 * Filters the races index ItemList before it is printed.
	 *
	 * @param array $list
	 */
	$list = apply_filters( 'arv_seo_races_index_list', $list );

	echo arv_seo_schema_script( array( $list ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'arv_seo_races_index_schema', 3 );
