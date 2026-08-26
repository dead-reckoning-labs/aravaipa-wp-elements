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
