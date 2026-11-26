<?php
/**
 * Upcoming Races render cases for the byte-identical snapshot. Covers the
 * phases and options the shared card code branches on. Test only.
 */
$row = function ( $name, $iso, $display, $dist, $register, $page, $image, $end = '', $live = '', $closes = '' ) {
	return "$name | $iso | $display | $dist | Venue | Page, AZ | $register | $page | $image | $end | $live | $closes |  |  |  | ";
};
$rows = implode(
	"\n",
	array(
		$row( 'Antelope Canyon Ultras', '2027-03-06', 'March 6', '50 Mile | 55K | 30K | Half Marathon', 'https://ultrasignup.com/register.aspx?did=138695', 'https://www.aravaiparunning.com/antelope/', 'https://x/antelope.png' ),
		$row( 'Zion Ultras', '2027-04-10', 'April 10-11', '100 Mile | 100K | 60K', 'https://ultrasignup.com/register.aspx?did=138715', 'https://www.aravaiparunning.com/zion/', 'https://x/zion.png', '2027-04-11' ),
		$row( 'Bryce Canyon Ultras', '2027-09-18', 'September 18-19', '100M | 50M | 60K', 'https://racegoat.com/events/bryce-canyon-ultras-2027', 'https://www.aravaiparunning.com/bryce/', '', '2027-09-19', 'https://live.aravaiparunning.com/#/bryce' ),
		$row( 'No Page Race', '2026-12-05', '', '10K', 'https://ultrasignup.com/register.aspx?dtid=1', '', 'https://x/n.png' ),
		$row( 'Closed Race', '2026-12-12', 'December 12', '50K', 'https://ultrasignup.com/register.aspx?did=2', 'https://www.aravaiparunning.com/closed/', '', '', '', '2026-11-20' ),
		$row( 'Race Day', '2026-11-25', 'November 25', '5K', 'https://ultrasignup.com/register.aspx?did=3', 'https://www.aravaiparunning.com/today/', 'https://x/t.png', '', 'https://live.aravaiparunning.com/#/today' ),
	)
);
return array(
	'default'  => array( 'rows' => $rows ),
	'dark4'    => array( 'rows' => $rows, 'theme' => 'dark', 'columns' => '4', 'eyebrow' => 'Next up', 'heading' => 'Races', 'intro' => 'Intro', 'all_url' => '/races/', 'all_label' => 'All races' ),
	'cta'      => array( 'rows' => $rows, 'cta_label' => 'Sign Up', 'limit' => '0', 'schema' => 'false' ),
	'featured' => array( 'rows' => $rows, 'featured' => 'Bryce Canyon Ultras', 'limit' => '3', 'only_confirmed' => 'false' ),
);
