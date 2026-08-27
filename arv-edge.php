<?php
define('ABSPATH', true); define('ARV_ELEMENTS_URL','./');
define('ARV_ELEMENTS_VERSION','0.0.0-test');
define( 'ARV_ELEMENTS_PATH', __DIR__ . '/' );
function cs_register_element($n,$c){$GLOBALS['ARV_EL'][$n]=$c;}
function cs_value($d,$x='markup',$p=false){return $d;}
function cs_compose_values($v,...$p){return $v;}
function cs_compose_controls($c,...$p){return $c;}
function cs_partial_controls($n){return array();}
function __($s,$d=''){return $s;}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function wp_json_encode($d,$f=0){return json_encode($d,$f);}
// race-map.php enqueues Leaflet from its render function. Recorded rather
// than ignored, so a test can assert it actually happens: it silently did
// not on the live site, which is what left the map an empty grey box.
function add_action($t,$f,$p=10,$n=1){}
$GLOBALS['ENQUEUED'] = array();
function wp_enqueue_script($h,$src='',$deps=array(),$v=false,$footer=false){ $GLOBALS['ENQUEUED'][]='js:'.$h; }
function wp_enqueue_style($h,$src='',$deps=array(),$v=false){ $GLOBALS['ENQUEUED'][]='css:'.$h; }
function _n($single,$plural,$count,$domain=''){return $count===1?$single:$plural;}
$GLOBALS['NOW']='2026-08-25';
function current_time($f){return $GLOBALS['NOW'];}
function apply_filters($tag,$v){return $v;}
define('DAY_IN_SECONDS',86400);
$d=__DIR__.'/'; require_once $d.'includes/helpers.php';
// race-map.php now calls arv_race_store_region_for() for the popup's
// division logo, a real cross-file dependency this harness did not have
// before. register_post_type/register_post_meta are never called because
// add_action above is a no-op, but arv_race_store_has_races() (which every
// element's arv_races_source() calls first) hits get_posts() directly, not
// through a hook. Stubbed to return none, so every element here keeps
// exercising the pasted-rows path this harness has always tested, exactly
// as it did before this file pulled the store in for one pure function.
function get_posts($args=array()){ return array(); }
function wp_parse_url($url,$component=-1){ return parse_url($url,$component); }
require_once $d.'includes/race-store.php';
foreach(['race-hero','distance-cards','event-timeline','partner-grid','countdown','region-map','upcoming-races','season-calendar','race-map'] as $e) require_once $d."includes/elements/$e.php";

$pass=0;$fail=0;
function t($name,$cond){ global $pass,$fail; if($cond){$pass++; echo "  ok   $name\n";} else {$fail++; echo "  FAIL $name\n";} }

echo "empty input returns nothing (no orphan headings):\n";
t('distances empty', arv_distance_cards_render(array('rows'=>'','heading'=>'Course Information'))==='');
t('timeline empty',  arv_event_timeline_render(array('rows'=>"\n \n",'heading'=>'X'))==='');
t('partners empty',  arv_partner_grid_render(array('rows'=>''))==='');

echo "malformed / partial rows:\n";
$r=arv_distance_cards_render(array('rows'=>"50K",'stat_labels'=>'Gain'));
t('name-only row still renders card', strpos($r,'50K')!==false);
t('name-only row has no empty stat list', strpos($r,'<dl')===false);
t('name-only row has no button', strpos($r,'__cta')===false);

$r=arv_distance_cards_render(array('rows'=>"50K | 2,700 ft | 8:00 AM",'stat_labels'=>'Gain'));
t('extra stat w/o label still shows value', strpos($r,'8:00 AM')!==false);

$r=arv_distance_cards_render(array('rows'=>"50K | 2,700 ft | 8:00 AM",'stat_labels'=>'Gain, Start'));
t('final stat not mistaken for URL', strpos($r,'__cta')===false);
$r=arv_distance_cards_render(array('rows'=>"50K | 2,700 ft | https://u.com",'stat_labels'=>'Gain'));
t('real URL becomes button', strpos($r,'__cta')!==false && strpos($r,'https://u.com')!==false);

echo "countdown validation:\n";
t('bad date returns nothing', arv_countdown_render(array('target'=>'not a date'))==='');
t('good date renders', strpos(arv_countdown_render(array('target'=>'2027-02-13 07:00','offset'=>'-07:00')),'2027-02-13T07:00:00-07:00')!==false);
t('bad offset falls back', strpos(arv_countdown_render(array('target'=>'2027-02-13 07:00','offset'=>'garbage')),'-07:00')!==false);

echo "region map:\n";
t('empty rows return nothing', arv_region_map_render(array('rows'=>''))==='');
$r=arv_region_map_render(array('rows'=>"Arizona | 20.9 | 61.9 | https://x/arizona/"));
t('minimal valid row renders a pin', strpos($r,'arv-region-map__pin')!==false);
// The card used to be skipped entirely for a row with nothing to put in it.
// It now always renders, because it carries the "View races" button, and a pin
// whose card never opens would be the only pin on the map with no visible way
// into the page it links to. What a bare row must still not produce is an
// empty logo image or an empty line of detail text.
t('minimal valid row still gets a card, since the card holds the button', strpos($r,'arv-region-map__detail"')!==false);
t('minimal valid row card has the call to action', strpos($r,'arv-region-map__cta')!==false);
t('minimal valid row renders no logo img', strpos($r,'arv-region-map__detail-logo')===false);
t('minimal valid row renders no detail text span', strpos($r,'arv-region-map__detail-text')===false);
t('the call to action is hidden from screen readers, the anchor already says it', strpos($r,'arv-region-map__cta" aria-hidden="true"')!==false);
t('row missing url is dropped', arv_region_map_render(array('rows'=>"Arizona | 20.9 | 61.9 | "))==='');
t('row with non-numeric x is dropped', arv_region_map_render(array('rows'=>"Arizona | not-a-number | 61.9 | https://x/"))==='');
t('one bad row does not sink a good one', strpos(arv_region_map_render(array('rows'=>"Bad | x | y | \nArizona | 20.9 | 61.9 | https://x/")),'Arizona')!==false);
$r=arv_region_map_render(array('rows'=>"HQ | 50 | 50 | https://x/ | Home base | primary"));
t('primary flag adds modifier class', strpos($r,'arv-region-map__pin--primary')!==false);
t('primary detail text present', strpos($r,'Home base')!==false);
$r=arv_region_map_render(array('rows'=>"Vermont | 95 | 30 | https://x/"));
t('x near right edge gets edge-right class', strpos($r,'arv-region-map__pin--edge-right')!==false);
$r=arv_region_map_render(array('rows'=>"Washington | 20 | 5 | https://x/"));
t('y near top gets label-below class', strpos($r,'arv-region-map__pin--label-below')!==false);
t('x/y out of range clamped into 0-100', strpos(arv_region_map_render(array('rows'=>"X | 500 | -50 | https://x/")),'left:100%;top:0%')!==false);

echo "upcoming races:\n";
t('empty rows return nothing', arv_upcoming_races_render(array('rows'=>''))==='');
t('row with no date is dropped', arv_upcoming_races_render(array('rows'=>"Race | | today"))==='');
t('row with unparseable date is dropped', arv_upcoming_races_render(array('rows'=>"Race | someday | x | 50K | V | Pine, AZ | https://u.com | https://a.com | | "))==='');
// 2026-02-30 passes a regex but is not a day. Emitting it as an Event startDate
// makes Google report the whole page as invalid, not just skip the one entry.
t('impossible calendar date is dropped', arv_upcoming_races_render(array('rows'=>"Race | 2027-02-30 | x | 50K | V | Pine, AZ | https://u.com | https://a.com | | "))==='');
t('valid date renders', strpos(arv_upcoming_races_render(array('rows'=>"Race | 2027-02-28 | x | 50K | V | Pine, AZ | https://u.com | https://a.com | | ")),'Race')!==false);

// The distances column is written the way the site writes it, with pipes,
// which is also the column separator. A full-length row is read from both
// ends so those pipes survive.
$r = arv_upcoming_races_render(array('rows'=>"Jangover | 2027-09-19 | September 19 | 75K | 50K | 25K | 15K | 7K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?did=1 | https://www.aravaiparunning.com/insomniac/jangover/ | https://x/i.png |  |  |  |  |  |  | "));
t('pipes inside distances are kept, not split into columns', strpos($r,'75K | 50K | 25K | 15K | 7K')!==false);
t('and the column after distances is still the venue', strpos($r,'McDowell Mountain Regional Park')!==false);
t('and the register URL is not mistaken for a distance', strpos($r,'ultrasignup.com/register.aspx?did=1')!==false);
t('image lands in an img, not the location line', strpos($r,'src="https://x/i.png"')!==false);

// Sorting, because the whole point of the module is "what is next".
$r = arv_upcoming_races_render(array('rows'=>"Later | 2028-01-01 | | 50K | V | Pine, AZ | https://u.com | https://a.com | | \nSooner | 2027-09-01 | | 10K | V | Pine, AZ | https://u.com | https://a.com | | "));
t('races are sorted by date regardless of paste order', strpos($r,'Sooner') < strpos($r,'Later'));
$three = "Zulu | 2027-09-03 | | 1 | V | P, AZ | https://u.com | https://a.com | | \nXray | 2027-09-01 | | 1 | V | P, AZ | https://u.com | https://a.com | | \nYankee | 2027-09-02 | | 1 | V | P, AZ | https://u.com | https://a.com | | ";
$r = arv_upcoming_races_render(array('rows'=>$three, 'limit'=>'2'));
t('limit keeps the two soonest', strpos($r,'Xray')!==false && strpos($r,'Yankee')!==false);
t('and drops the third', strpos($r,'Zulu')===false);
t('limit trims the schema too, not just the cards', substr_count($r,'"@type":"SportsEvent"')===2);
$r = arv_upcoming_races_render(array('rows'=>$three, 'limit'=>'0'));
t('limit 0 shows every race', strpos($r,'Zulu')!==false);

echo "event schema:\n";
$row = "Black Canyon 100K | 2027-02-13 | February 13 | 100K | 60K | Black Canyon Trail | Mayer, AZ | https://ultrasignup.com/register.aspx?did=9 | https://www.aravaiparunning.com/blackcanyon/ | https://x/bc.png |  |  |  |  |  |  | ";
$r = arv_upcoming_races_render(array('rows'=>$row));
$m = array(); preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $r, $m);
t('schema block is emitted', !empty($m));
$json = json_decode($m[1] ?? '', true);
t('schema is valid json', is_array($json));
// Without @context none of the "@type" values resolve to schema.org and the
// whole block is silently ignored by every consumer that reads it.
t('block declares the schema.org context', ($json['@context'] ?? '')==='https://schema.org');
t('events hang off @graph', isset($json['@graph']) && count($json['@graph'])===1);
$e = $json['@graph'][0] ?? array();
t('type is SportsEvent', ($e['@type'] ?? '')==='SportsEvent');
t('startDate is the ISO date', ($e['startDate'] ?? '')==='2027-02-13');
t('attendance mode is offline, not Google\'s online default', strpos($e['eventAttendanceMode'] ?? '','Offline')!==false);
t('location name is the venue', ($e['location']['name'] ?? '')==='Black Canyon Trail');
t('city is split out of "City, ST"', ($e['location']['address']['addressLocality'] ?? '')==='Mayer');
t('state is split out of "City, ST"', ($e['location']['address']['addressRegion'] ?? '')==='AZ');
t('offer points at registration', ($e['offers']['url'] ?? '')==='https://ultrasignup.com/register.aspx?did=9');
t('no invented price on the offer', !isset($e['offers']['price']));
t('organizer is named', ($e['organizer']['name'] ?? '')==='Aravaipa Running');
$r2 = arv_upcoming_races_render(array('rows'=>$row, 'schema'=>'false'));
t('schema toggle off suppresses the block', strpos($r2,'application/ld+json')===false);
t('but the cards still render with it off', strpos($r2,'Black Canyon 100K')!==false);

// A race name containing "</script>" would otherwise close the tag early and
// spill the rest of the JSON into the document as markup.
$r = arv_upcoming_races_render(array('rows'=>"Bad</script><b>x</b> | 2027-09-01 | | 50K | V | P, AZ | https://u.com | https://a.com | | "));
t('script-closing text cannot break out of the json-ld block', strpos($r,'</script><b>')===false);
t('and cannot break out of the card markup either', strpos($r,'<b>x</b>')===false);

// A region-only location ("Arizona") has no comma to split on.
$e = json_decode(preg_replace('#.*<script type="application/ld\+json">(.*?)</script>.*#s','$1',
  arv_upcoming_races_render(array('rows'=>"S | 2027-09-01 | | 5K | Multiple Regional Parks | Arizona | https://u.com | https://a.com | |  |  |  |  | "))), true)['@graph'][0];
t('region-only location becomes addressRegion with no bogus locality', ($e['location']['address']['addressRegion'] ?? '')==='Arizona' && !isset($e['location']['address']['addressLocality']));

echo "past races drop off:\n";
$GLOBALS['NOW']='2026-09-15';
$list = "Past | 2026-09-01 | | 50K | V | P, AZ | https://u.com | https://a.com |  |  |  |  |  | \n"
      . "Today | 2026-09-15 | | 50K | V | P, AZ | https://u.com | https://a.com |  |  |  |  |  | \n"
      . "Future | 2026-09-20 | | 50K | V | P, AZ | https://u.com | https://a.com |  |  |  |  |  | ";
$r = arv_upcoming_races_render(array('rows'=>$list));
t('a race that already ran is gone', strpos($r,'Past')===false);
t('a race running today is still up', strpos($r,'Today')!==false);
t('a future race is still up', strpos($r,'Future')!==false);
t('and the dropped race is out of the schema too', substr_count($r,'"@type":"SportsEvent"')===2);

// Cocodona runs the better part of a week. Dropping it on day two, while
// runners are still on the course, would be worse than leaving it a day long.
$GLOBALS['NOW']='2026-09-13';
$multi = "Cocodona | 2026-09-12 | September 12-18 | 250 Mile | Start | Black Canyon City, AZ | https://u.com | https://a.com |  | 2026-09-18 |  |  |  | ";
t('a multi-day race stays up mid-race', strpos(arv_upcoming_races_render(array('rows'=>$multi)),'Cocodona')!==false);
$GLOBALS['NOW']='2026-09-18';
t('and through its final day', strpos(arv_upcoming_races_render(array('rows'=>$multi)),'Cocodona')!==false);
$GLOBALS['NOW']='2026-09-19';
t('holds its results through the weekend', strpos(arv_upcoming_races_render(array('rows'=>$multi)),'Cocodona')!==false);
$GLOBALS['NOW']='2026-09-21';
t('and clears on the Monday after', arv_upcoming_races_render(array('rows'=>$multi))==='');
$e = json_decode(preg_replace('#.*<script type="application/ld\+json">(.*?)</script>.*#s','$1',
  (function(){ $GLOBALS['NOW']='2026-09-13'; return arv_upcoming_races_render(array('rows'=>"Cocodona | 2026-09-12 | Sept 12-18 | 250 Mile | Start | Black Canyon City, AZ | https://u.com | https://a.com |  | 2026-09-18 |  |  |  | ")); })()), true)['@graph'][0];
t('multi-day race carries endDate in schema', ($e['endDate'] ?? '')==='2026-09-18');
$GLOBALS['NOW']='2026-08-25';
$e = json_decode(preg_replace('#.*<script type="application/ld\+json">(.*?)</script>.*#s','$1',
  arv_upcoming_races_render(array('rows'=>"One | 2026-09-01 | | 50K | V | P, AZ | https://u.com | https://a.com |  | 2026-09-01"))), true)['@graph'][0];
t('single-day race omits a pointless endDate', !isset($e['endDate']));

$GLOBALS['NOW']='2030-01-01';
t('every race in the past renders nothing, not an empty heading', arv_upcoming_races_render(array('rows'=>$list))==='');
$GLOBALS['NOW']='2026-08-25';

echo "lifecycle:\n";
// Rock Hawk runs Saturday 2026-08-29.
$rh = "Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://www.aravaiparunning.com/rock-hawk/ |  |  |  |  |  |  |  | ";
function phase_of($rows, $opts = array()){
  $r = arv_upcoming_races_render(array_merge(array('rows'=>$rows), $opts));
  if ($r==='') return 'gone';
  if (strpos($r,'arv-races__cta--upcoming')!==false) return 'upcoming';
  if (strpos($r,'arv-races__cta--live')!==false) return 'live';
  if (strpos($r,'arv-races__cta--results')!==false) return 'results';
  if (strpos($r,'arv-races__cta--closed')!==false) return 'closed';
  return 'none';
}
$GLOBALS['NOW']='2026-08-25'; t('four days out: entries open',  phase_of($rh, array('live_lead'=>'0'))==='upcoming');
$GLOBALS['NOW']='2026-08-28'; t('day before: still entries',    phase_of($rh, array('live_lead'=>'0'))==='upcoming');
$GLOBALS['NOW']='2026-08-29'; t('race day: live',               phase_of($rh, array('live_lead'=>'0'))==='live');
$GLOBALS['NOW']='2026-08-30'; t('Sunday after: results',        phase_of($rh, array('live_lead'=>'0'))==='results');
$GLOBALS['NOW']='2026-08-31'; t('Monday morning: gone',         phase_of($rh, array('live_lead'=>'0'))==='gone');

$GLOBALS['NOW']='2026-08-25';
$r = arv_upcoming_races_render(array('rows'=>$rh,'live_lead'=>'0'));
t('primary reads Register while open', strpos($r,'>Register<')!==false);
t('and points at ultrasignup registration', strpos($r,'register.aspx?did=131056')!==false);
$GLOBALS['NOW']='2026-08-29';
$r = arv_upcoming_races_render(array('rows'=>$rh));
t('primary reads Live Results on race day', strpos($r,'>Live Results<')!==false);
// Derived from the register link's did rather than carried as a second URL
// that could fall out of sync with it.
t('and points at the derived results url', strpos($r,'results_event.aspx?did=131056')!==false);
$GLOBALS['NOW']='2026-08-30';
t('primary reads Results after the race', strpos(arv_upcoming_races_render(array('rows'=>$rh)),'>Results<')!==false);

// The configured button label is about selling entries. Once the race is
// running it would be wrong whatever the setting says.
$GLOBALS['NOW']='2026-08-25';
t('custom label used while open', strpos(arv_upcoming_races_render(array('rows'=>$rh,'cta_label'=>'Enter now','live_lead'=>'0')),'>Enter now<')!==false);
$GLOBALS['NOW']='2026-08-29';
$r = arv_upcoming_races_render(array('rows'=>$rh,'cta_label'=>'Enter now'));
t('but ignored once the race is running', strpos($r,'Enter now')===false && strpos($r,'>Live Results<')!==false);

echo "race details button:\n";
$GLOBALS['NOW']='2026-08-25';
$r = arv_upcoming_races_render(array('rows'=>$rh));
t('Race Details always present', substr_count($r,'arv-races__details')===1);
$GLOBALS['NOW']='2026-08-30';
t('including after the race',   substr_count(arv_upcoming_races_render(array('rows'=>$rh)),'arv-races__details')===1);
$GLOBALS['NOW']='2026-08-25';
t('and it stays on-site, not a new tab', preg_match('#arv-races__details" href="[^"]*aravaiparunning[^"]*">#',$r)===1);

echo "monday cutoff:\n";
t('Saturday race clears the Monday after',   arv_upcoming_races_clears_on('2026-08-29')==='2026-08-31');
t('Sunday race clears the next day',         arv_upcoming_races_clears_on('2026-08-30')==='2026-08-31');
// Strictly after, so a Monday race is not gone the instant it finishes.
t('Monday race gets the following week',     arv_upcoming_races_clears_on('2026-08-31')==='2026-09-07');
t('Wednesday race clears the Monday after',  arv_upcoming_races_clears_on('2026-09-02')==='2026-09-07');

echo "results url derivation:\n";
t('derives from a register link', arv_upcoming_races_results_url('https://ultrasignup.com/register.aspx?did=42')==='https://ultrasignup.com/results_event.aspx?did=42');
t('handles the www host',        arv_upcoming_races_results_url('https://www.ultrasignup.com/register.aspx?did=42')!=='');
t('non-ultrasignup url derives nothing, rather than a broken link', arv_upcoming_races_results_url('https://runsignup.com/Race/x')==='');
// A row can override with its own tracker or broadcast page.
$GLOBALS['NOW']='2026-08-29';
$withlive = "R | 2026-08-29 | Aug 29 | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=7 | https://a.com/r/ |  |  | https://mountainoutpost.com/live/r | ";
t('an explicit live url wins over the derived one', strpos(arv_upcoming_races_render(array('rows'=>$withlive)),'mountainoutpost.com/live/r')!==false);
$GLOBALS['NOW']='2026-08-25';

echo "registration close date:\n";
// Entries shut, and no results board and no derivable results link either, so
// there is genuinely nowhere to send anyone.
$nolive = "NoLive | 2026-08-29 | Aug 29 | 50K | V | P, AZ | https://runsignup.com/x | https://a.com/n/ |  |  |  | 2026-08-24 | ";
// Snow Mountain Ranch: races Sat 2026-09-12, UltraSignup publishes
// "Registration closes: Mon, Sep 7 @ 11:59PM MT".
$smr = "Snow Mountain Ranch | 2026-09-12 | September 12 | 50KM | 33KM | Snow Mountain Ranch | Granby, CO | https://ultrasignup.com/register.aspx?did=131162 | https://a.com/smr/ |  |  |  | 2026-09-07 | 1 | 0 |  | ";
$GLOBALS['NOW']='2026-09-05'; t('before the close date: entries open',  phase_of($smr, array('live_lead'=>'0'))==='upcoming');
$GLOBALS['NOW']='2026-09-07'; t('on the close date: still open all day', phase_of($smr, array('live_lead'=>'0'))==='upcoming');
// Once entries close there is a results board to send people to, so closing
// hands straight over to live rather than parking on a dead chip. The chip is
// now only for a race with no results link at all, which $nolive covers below.
$GLOBALS['NOW']='2026-09-08'; t('day after close: hands over to live',   phase_of($smr, array('live_lead'=>'0'))==='live');
$GLOBALS['NOW']='2026-09-11'; t('still live the day before the race',    phase_of($smr, array('live_lead'=>'0'))==='live');
$GLOBALS['NOW']='2026-09-13';
$jr = json_decode(preg_replace('#.*<script type="application/ld\+json">(.*?)</script>.*#s','$1',
  arv_upcoming_races_render(array('rows'=>$smr))), true);
t('a finished race is still rendered on the Sunday', is_array($jr));
t('and advertises no entries at all', !isset($jr['@graph'][0]['offers']));
$GLOBALS['NOW']='2026-09-12'; t('race day still flips to live',          phase_of($smr, array('live_lead'=>'0'))==='live');

// The closed chip needs a race with nowhere to send anyone: entries shut and
// no results board, so there is genuinely nothing to press.
$GLOBALS['NOW']='2026-08-25';
$r = arv_upcoming_races_render(array('rows'=>$nolive));
t('closed phase says so', strpos($r,'Entries Closed')!==false);
// No destination, so it must not be a link: not focusable, not clickable.
t('and is not a link', strpos($r,'<span class="arv-races__cta arv-races__cta--closed"')!==false);
// The register URL is still in the schema's offer, which is right: the offer
// describes entries, and entries existing but being closed is a fact worth
// stating. What must not survive is the claim that they are available.
$j = json_decode(preg_replace('#.*<script type="application/ld\+json">(.*?)</script>.*#s','$1',$r), true)['@graph'][0];
t('schema no longer claims entries are available', strpos($j['offers']['availability'] ?? '','SoldOut')!==false);
t('and the offer still points at the registration page', ($j['offers']['url'] ?? '')==='https://runsignup.com/x');
t('Race Details is still there, and is now the only thing to press', substr_count($r,'arv-races__details')===1);

// 60 of the 69 races publish no close date at all. Those must behave exactly
// as before rather than being treated as closed.
$nodate = "Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://a.com/rh/ |  |  |  | ";
$GLOBALS['NOW']='2026-08-28'; t('no close date: open right up to race day', phase_of($nodate, array('live_lead'=>'0'))==='upcoming');
$GLOBALS['NOW']='2026-08-29'; t('no close date: still flips on race day',   phase_of($nodate, array('live_lead'=>'0'))==='live');
$GLOBALS['NOW']='2026-08-25';

echo "live results lead window:\n";
// Rock Hawk runs Sat 2026-08-29 and entries closed Mon 2026-08-24. The live
// board already carries its start list, so there is something worth reaching
// before race morning.
$rh2 = "Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://a.com/rh/ |  |  | https://live.aravaiparunning.com/#/rock_hawk-2026 | 2026-08-24 | 1 | 0 |  | ";
$GLOBALS['NOW']='2026-08-23'; t('before entries close: still selling',        phase_of($rh2)==='upcoming');
$GLOBALS['NOW']='2026-08-25'; t('entries closed: live results, not a dead chip', phase_of($rh2)==='live');
$GLOBALS['NOW']='2026-08-29'; t('race day: still live',                       phase_of($rh2)==='live');
$GLOBALS['NOW']='2026-08-30'; t('day after: results',                         phase_of($rh2)==='results');

$GLOBALS['NOW']='2026-08-25';
$r = arv_upcoming_races_render(array('rows'=>$rh2));
t('and it points at the real timing board', strpos($r,'live.aravaiparunning.com/#/rock_hawk-2026')!==false);

// A race with no published close date falls back to the lead window.
$noclose = "Later | 2026-09-20 | Sept 20 | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=5 | https://a.com/l/ |  |  | https://live.aravaiparunning.com/#/later-2026 | ";
$GLOBALS['NOW']='2026-09-14'; t('outside the lead window: still selling', phase_of($noclose)==='upcoming');
$GLOBALS['NOW']='2026-09-15'; t('lead window opens 5 days out',           phase_of($noclose)==='live');

// Lead is configurable, and 0 means wait for race day.
$GLOBALS['NOW']='2026-09-15';
t('lead 0 waits for race day', strpos(arv_upcoming_races_render(array('rows'=>$noclose,'live_lead'=>'0')),'arv-races__cta--upcoming')!==false);
t('lead 10 opens earlier',     strpos(arv_upcoming_races_render(array('rows'=>"Later | 2026-09-24 | S | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=5 | https://a.com/l/ |  |  | https://live.aravaiparunning.com/#/x-2026 | ",'live_lead'=>'10')),'arv-races__cta--live')!==false);
// A lead longer than the gap between races would put the whole season live at
// once, so it is clamped rather than trusted.
t('absurd lead is clamped, not obeyed', strpos(arv_upcoming_races_render(array('rows'=>"Far | 2027-06-01 | J | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=5 | https://a.com/f/ |  |  | https://live.aravaiparunning.com/#/f-2027 | ",'live_lead'=>'9999')),'arv-races__cta--live')===false);

// With entries closed but no live board and no derivable results link, the
// dead "Entries Closed" chip is still the right answer.
$GLOBALS['NOW']='2026-08-25'; t('no results link anywhere: entries closed chip', phase_of($nolive)==='closed');
$GLOBALS['NOW']='2026-08-25';

echo "confirmed flag:\n";
// Unconfirmed: entries closed, has a live board, would otherwise show Live
// Results. only_confirmed strips it out regardless of what phase it would
// have been in, because the whole point is that its date is a guess.
$unconf = "Guess | 2027-04-25 | | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/g/ |  |  | https://live.aravaiparunning.com/#/g-2027 |  | 0 | 1";
t('unconfirmed race is dropped by default', arv_upcoming_races_render(array('rows'=>$unconf))==='');
t('unconfirmed race appears with the toggle off', strpos(arv_upcoming_races_render(array('rows'=>$unconf,'only_confirmed'=>'false')),'Guess')!==false);

$conf = "Real | 2026-09-01 | | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/r/ |  |  |  |  | 1 | 0";
t('confirmed race renders under the default', strpos(arv_upcoming_races_render(array('rows'=>$conf)),'Real')!==false);

// A row from before this column existed, or written by hand without it, has
// no eleventh tail value. It should behave exactly as it always did rather
// than silently disappear from every page that filters on confirmed.
$old = "Legacy | 2026-09-01 | | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/l/ |  |  | ";
t('a row with no confirmed column is treated as confirmed', strpos(arv_upcoming_races_render(array('rows'=>$old)),'Legacy')!==false);

echo "season calendar:\n";
t('empty rows return nothing', arv_season_calendar_render(array('rows'=>''))==='');

// Today is pinned to 2026-08-26 by the harness.
$GLOBALS['NOW']='2026-08-26';
// Soon: real published future date. Ran: date already passed, so the
// generator rolled its year forward and flagged it as a guess.
$cal = "Soon | 2026-09-15 | September 15 | 50K | V1 | Reno, NV | https://u.com/1 | https://a.com/1 |  |  |  |  | 1 | 0\n"
     . "Ran | 2027-03-01 | March 1 | 50K | V2 | Denver, CO | https://u.com/2 | https://a.com/2 |  |  |  |  | 0 | 1";
$r = arv_season_calendar_render(array('rows'=>$cal));

// The whole model: forward-looking, never a Jan-to-Dec table. A race in three
// weeks comes first; one that ran in March is eleven months out, not five
// months back, so it sorts last rather than at the top of the year.
t('a race coming up sorts before one that already ran', strpos($r,'Soon') < strpos($r,'Ran'));
t('the upcoming race is bucketed under its real month', strpos($r,'September 2026')!==false);
t('the rolled-forward race sits in next year', strpos($r,'March 2027')!==false);
t('and not back into this year', strpos($r,'March 2026')===false);

// A confirmed date is stated; an unconfirmed one is not invented.
t('confirmed race shows its day', strpos($r,'arv-calendar__day-num">15<')!==false);
t('a rolled-forward date says TBD rather than a guessed day', strpos($r,'arv-calendar__day--tbd">TBD<')!==false);
// The distinction that matters: a real published date must still print even
// when registration has not opened, or a race five weeks out reads as TBD.
$realdate = "Bear | 2026-10-03 | October 3-4 | 100K | V | Lakewood, CO | https://u.com/b | https://a.com/b |  |  |  |  | 0 | 0";
$rb = arv_season_calendar_render(array('rows'=>$realdate));
t('a real future date prints even with registration unconfirmed', strpos($rb,'TBD')===false);
t('and shows the published span', strpos($rb,'>3-4<')!==false);
// Register is offered now, but gated on the same confirmed flag as
// everything else, so the unconfirmed row in this fixture must not have one.
t('the unconfirmed race in this pair has no Register', substr_count($r,'arv-calendar__action--upcoming')===0 || substr_count($r,'arv-calendar__action--upcoming')===1);
t('and this element never renders a card-style cta', strpos($r,'arv-races__cta')===false);

echo "series resolution:\n";
$mk = function ( $page, $name = 'Test Race' ) { return array( 'page' => $page, 'name' => $name ); };
t('an insomniac race resolves',        'insomniac' === arv_race_series_for($mk('https://www.aravaiparunning.com/insomniac/thrasher/'))['slug']);
// Adrenaline Night Runs is published under the long path while its nine
// siblings use the short one. Without the alias it would sit alone in a
// series of one, and the dropdown would list Insomniac twice.
t('the long insomniac path aliases to the same series', 'insomniac' === arv_race_series_for($mk('https://www.aravaiparunning.com/insomniac-night-trail-series/adrenaline/'))['slug']);
t('and both carry the same label',     arv_race_series_for($mk('https://www.aravaiparunning.com/insomniac/x/'))['label'] === arv_race_series_for($mk('https://www.aravaiparunning.com/insomniac-night-trail-series/y/'))['label']);
t('the alias still links to its own published path', false !== strpos(arv_race_series_for($mk('https://www.aravaiparunning.com/insomniac-night-trail-series/y/'))['url'], 'insomniac-night-trail-series'));
t('a partner brand resolves',          'great-lakes-endurance' === arv_race_series_for($mk('https://www.aravaiparunning.com/great-lakes-endurance/tahqua/'))['slug']);
// Structure is not branding. Treating any first segment as a series would
// put a "Races" chip on one race and a "Virtual" chip on another.
t('/races/ is not a series',           null === arv_race_series_for($mk('https://www.aravaiparunning.com/races/dam-good-run/')));
t('/virtual/ is not a series',         null === arv_race_series_for($mk('https://www.aravaiparunning.com/virtual/jallucinations/')));
t('/colorado/ is not a series',        null === arv_race_series_for($mk('https://www.aravaiparunning.com/colorado/aspen/')));
// One segment is the series' own landing page, not a race inside it.
t('a series page itself is not a race in it', null === arv_race_series_for($mk('https://www.aravaiparunning.com/insomniac/')));
t('a standalone race has no series',   null === arv_race_series_for($mk('https://www.aravaiparunning.com/javelina/')));
t('an empty page is safe',             null === arv_race_series_for(array('page'=>'','name'=>'X')));

// DRT Series cannot be read off the path: only 1 of its 7 races uses
// /drt-series/, the other 6 sit at plain top-level paths indistinguishable
// from a standalone race. Confirmed by name with Jamil instead.
foreach (array('Cave Creek Thriller','Pass Mountain','McDowell Mountain Frenzy','San Tan Scramble','Elephant Mountain','Mesquite Canyon','Dam Good Run') as $drtName) {
	t("$drtName resolves to DRT Series despite its own URL", 'drt-series' === arv_race_series_for($mk('https://www.aravaiparunning.com/'.strtolower(str_replace(' ','-',$drtName)).'/', $drtName))['slug']);
}
t('a same-shaped race NOT on the DRT roster does not resolve', null === arv_race_series_for($mk('https://www.aravaiparunning.com/some-other-race/', 'Some Other Race')));

echo "series chip and filter:\n";
$serCal = '';
foreach (array(
  array('Jangover','insomniac/jangover'), array('Thrasher','insomniac/thrasher'),
  array('Adrenaline','insomniac-night-trail-series/adrenaline'), array('Tahqua','great-lakes-endurance/tahqua'),
  array('Grand Island','great-lakes-endurance/grand-island'), array('Solo','javelina'),
) as $i => $x) {
  $serCal .= sprintf("%s | 2026-%02d-10 | Month 10 | 50K | V | Pine, AZ | https://u.com/%d | https://www.aravaiparunning.com/%s/ |  |  |  |  | 1 | 0\n", $x[0], 9 + ($i % 4), $i, $x[1]);
}
$sr = arv_season_calendar_render(array('rows'=>trim($serCal)));
t('a series race gets a chip',            strpos($sr,'arv-calendar__series')!==false);
t('the chip names the series',            strpos($sr,'>Insomniac Night Series<')!==false);
t('the row carries data-series',          strpos($sr,'data-series="insomniac"')!==false);
t('a standalone race has an empty one',   strpos($sr,'data-series=""')!==false);
t('the chip is not a nested anchor',      strpos($sr,'<a class="arv-calendar__series"')===false);
t('a Series dropdown is offered',         strpos($sr,'data-arv-series')!==false);
// Two spellings, one series: the dropdown must not list Insomniac twice.
t('Insomniac appears once in the dropdown', substr_count($sr,'<option value="insomniac">')===1);
t('and the other series is listed too',   strpos($sr,'<option value="great-lakes-endurance">')!==false);
t('series is searchable by name',         strpos($sr,'insomniac night series')!==false);

echo "shared distance splitter:\n";
// One function now backs both the calendar pills and the map popup pills,
// because the popup shipped the split bug twice, once in each direction,
// while the two lived apart.
t('splits a pipe-joined race',      array('50K','25K','10K','5K') === arv_split_distances('50K | 25K | 10K | 5K'));
t('splits a comma-joined race',     array('50 Mile','50K','30K') === arv_split_distances('50 Mile, 50K, 30K'));
t('a single distance stays single', array('31K') === arv_split_distances('31K'));
t('a range keeps its wording',      array('10K to 50K') === arv_split_distances('10K to 50K'));
t('empty in, empty out',            array() === arv_split_distances(''));
t('drops empty segments',           array('50K','25K') === arv_split_distances('50K | | 25K'));

echo "calendar distance pills:\n";
$pillCal = "Bear | 2026-10-03 | October 3 | 100K, 50 Mile, 50K | Bear Creek | Lakewood, CO | https://u.com/b | https://a.com/b |  |  |  |  | 1 | 0";
$pc = arv_season_calendar_render(array('rows'=>$pillCal));
t('each distance becomes its own chip',   substr_count($pc,'arv-calendar__pill') === 3);
t('and the raw comma list is gone',       strpos($pc,'>100K, 50 Mile, 50K<') === false);
t('while data-distances keeps it intact', strpos($pc,'data-distances="100k, 50 mile, 50k"') !== false);

echo "map collapse toggle:\n";
// Own fixture: $mapRow is defined further down, in the "race map:" section.
$togRow = "Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | Phillip S. Miller Park | Castle Rock, CO | https://u.com/r | https://a.com/rh/ |  |  |  | 2026-08-24 | 1 | 0 | 39.3698 | -104.8785";
// Restored below: the grace-period tests further down assert against the
// harness clock, and leaving this set silently flipped one of them.
$togWas = $GLOBALS['NOW'];
$GLOBALS['NOW']='2026-08-20';
$tm = arv_race_map_render(array('rows'=>$togRow));
t('a toggle is rendered by default',    strpos($tm,'data-arv-map-toggle')!==false);
t('it starts expanded',                 strpos($tm,'aria-expanded="true"')!==false);
t('it is a real button, not a div',     strpos($tm,'<button type="button" class="arv-map__toggle"')!==false);
t('the map sits in a panel it can fold', strpos($tm,'data-arv-map-panel')!==false);
$tmOff = arv_race_map_render(array('rows'=>$togRow,'collapsible'=>'false'));
t('collapsible=false renders no toggle', strpos($tmOff,'data-arv-map-toggle')===false);
t('but the map itself still renders',    strpos($tmOff,'data-arv-map')!==false);
$GLOBALS['NOW'] = $togWas;

echo "calendar state search and filter:\n";
// Searching a state by its full name found nothing, because the only thing
// in a row was the two-letter code: "california" never matched "CA". The
// full name now rides along in the row's searchable text.
$stateCal = "Moon | 2026-10-10 | October 10 | 50 Mile | Black Star Canyon | Silverado, CA | https://u.com/m | https://a.com/m |  |  |  |  | 1 | 0\n"
          . "Roost | 2026-11-14 | November 14 | 4 Mile | Sabino Canyon | Tucson, AZ | https://u.com/r | https://a.com/r |  |  |  |  | 1 | 0";
$sc = arv_season_calendar_render(array('rows'=>$stateCal));
t('the row search text carries the full state name', strpos($sc,'california')!==false);
t('and still carries the code it came from',         strpos($sc,'silverado, ca')!==false);
t('the other state resolves too',                    strpos($sc,'arizona')!==false);
// data-state stays the raw code: the dropdown compares against it, and only
// the visible label was ever meant to change.
t('data-state is still the two-letter code',         strpos($sc,'data-state="CA"')!==false);

// The filter bar only renders above 5 races (below that the whole list fits
// on screen and a filter is noise), so the dropdown assertions need a
// fixture that clears that bar. NV and NH are in here deliberately: sorting
// on the CODE puts NH above NV, which renders as "New Hampshire" above
// "Nevada", alphabetical by a string the dropdown never shows.
$many = '';
$sixStates = array('Silverado, CA', 'Tucson, AZ', 'Reno, NV', 'Lincoln, NH', 'Denver, CO', 'Marquette, MI');
foreach ( $sixStates as $i => $place ) {
	$many .= sprintf(
		"R%d | 2026-%02d-10 | Month 10 | 50K | V | %s | https://u.com/%d | https://a.com/%d |  |  |  |  | 1 | 0\n",
		$i, 9 + ( $i % 4 ), $place, $i, $i
	);
}
$sm = arv_season_calendar_render(array('rows'=>trim($many)));
t('the filter bar renders once past the threshold', strpos($sm,'data-arv-state')!==false);
t('the dropdown labels states in full',             strpos($sm,'>California<')!==false);
t('and keeps the code as the option value',         strpos($sm,'value="CA"')!==false);
t('Nevada is listed before New Hampshire',          strpos($sm,'>Nevada<') < strpos($sm,'>New Hampshire<'));

echo "calendar grace period:\n";
// A race that ran two days ago keeps its place; the same race outside the
// grace window has flipped forward a year.
$just = "Just | 2026-08-24 | August 24 | 50K | V | X, AZ | https://u.com/j | https://a.com/j |  |  |  |  | 1 | 0";
t('within grace, a finished race stays in the current month', strpos(arv_season_calendar_render(array('rows'=>$just,'grace'=>'5')),'August 2026')!==false);
t('outside grace, it flips to next year',                    strpos(arv_season_calendar_render(array('rows'=>$just,'grace'=>'0')),'August 2027')!==false);
t('grace is clamped rather than trusted', strpos(arv_season_calendar_render(array('rows'=>$just,'grace'=>'99999')),'August 2026')!==false);

// Feb 29 is not a date in 2027. It must not silently become March.
$leap = "Leap | 2028-02-29 | February 29 | 50K | V | X, AZ | https://u.com/l | https://a.com/l |  |  |  |  | 1 | 0";
t('Feb 29 in a non-leap year does not roll into March', strpos(arv_season_calendar_render(array('rows'=>$leap)),'February')!==false);

echo "calendar hiatus list:\n";
$h = "Old Race | https://a.com/old | Paused since 2024\nNo Link Race";
$r = arv_season_calendar_render(array('rows'=>$cal, 'hiatus_rows'=>$h));
t('hiatus race is listed',        strpos($r,'Old Race')!==false);
t('its note is shown',            strpos($r,'Paused since 2024')!==false);
t('it links to its page',         strpos($r,'href="https://a.com/old"')!==false);
// A hiatus race with nowhere left to point is still worth listing, but must
// not render as a link to nothing.
t('a hiatus race with no url is not a link', strpos($r,'<span class="arv-calendar__main"><span class="arv-calendar__body"><span class="arv-calendar__name">No Link Race')!==false);
t('the hiatus section has its own heading',  strpos($r,'On hiatus')!==false);
// The hiatus list is the only content on a page that has no dated races yet.
t('hiatus alone still renders', strpos(arv_season_calendar_render(array('rows'=>'','hiatus_rows'=>$h)),'Old Race')!==false);
t('no hiatus rows means no hiatus section', strpos(arv_season_calendar_render(array('rows'=>$cal)),'arv-calendar__hiatus')===false);

$x='<script>alert(1)</script>';
t('race name is escaped',   strpos(arv_season_calendar_render(array('rows'=>"$x | 2027-03-05 | | 50K | V | X, AZ | https://u.com/a | |  |  |  |  | 1 | 0")),'<script>alert')===false);
t('hiatus name is escaped', strpos(arv_season_calendar_render(array('rows'=>'','hiatus_rows'=>$x)),'<script>alert')===false);

echo "calendar live state:\n";
// Same phase function the homepage runs on, so both pages change state at the
// same moment by construction rather than by two implementations agreeing.
$live = "Rock Hawk | 2026-08-29 | August 29 | 50K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://a.com/rh/ |  |  | https://live.aravaiparunning.com/#/rock_hawk-2026 | 2026-08-24 | 1 | 0 |  | ";
// Well before the race a confirmed row now shows Register rather than
// nothing; what it must not show is a live/results link.
$GLOBALS['NOW']='2026-08-20'; t('well before the race: no live or results link', strpos(arv_season_calendar_render(array('rows'=>$live)),'arv-calendar__action--live')===false);
$GLOBALS['NOW']='2026-08-29';
$r = arv_season_calendar_render(array('rows'=>$live));
t('race day: live results link appears', strpos($r,'arv-calendar__action--live')!==false);
t('and it says Live Results',            strpos($r,'>Live Results<')!==false);
t('pointing at the real timing board',   strpos($r,'live.aravaiparunning.com/#/rock_hawk-2026')!==false);
$GLOBALS['NOW']='2026-08-30';
t('day after: results link',             strpos(arv_season_calendar_render(array('rows'=>$live)),'arv-calendar__action--results')!==false);

// A published date is only real for the running it was published for. Once a
// race rolls past its grace window into next year, its old day must stop
// being printed: the same mistake the `guessed` column prevents at generation
// time, arriving from the other direction as the calendar moves.
$GLOBALS['NOW']='2026-08-30';
t('a just-run race still shows its real day inside grace', strpos(arv_season_calendar_render(array('rows'=>$live)),'__day-num">29<')!==false);
$GLOBALS['NOW']='2026-09-05';
$rolled = arv_season_calendar_render(array('rows'=>$live));
t('once rolled forward, the day becomes TBD',  strpos($rolled,'day--tbd">TBD<')!==false);
t('and it is not still asserting the old day', strpos($rolled,'__day-num">29<')===false);
t('under next year\'s heading',                strpos($rolled,'August 2027')!==false);
$GLOBALS['NOW']='2026-08-26';

// Register is never offered here: that is Upcoming Races' job, and two
// Register buttons on one page pointing at the same place is noise.
$GLOBALS['NOW']='2026-08-26';
t('never offers Register', strpos(arv_season_calendar_render(array('rows'=>$live)),'>Register<')===false);

// The 58 unconfirmed races have no safe URL to offer: every one is derived
// from a listing describing a previous running, so "results" would be last
// year's. Those rows stay quiet.
$unconf2 = "Old | 2026-08-29 | August 29 | 50K | V | X, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/o/ |  |  |  |  | 0 | 0";
$GLOBALS['NOW']='2026-08-29';
t('an unconfirmed race gets no action link even on its race day', strpos(arv_season_calendar_render(array('rows'=>$unconf2)),'arv-calendar__action--')===false);
$GLOBALS['NOW']='2026-08-26';

// The row is a container now, so the main link must still be the big target.
t('the row main link still wraps the name and logo', strpos(arv_season_calendar_render(array('rows'=>$live)),'<a class="arv-calendar__main"')!==false);

echo "calendar dual buttons:\n";
$GLOBALS['NOW']='2026-08-20';
$r = arv_season_calendar_render(array('rows'=>$live));
t('a confirmed upcoming race offers Register', strpos($r,'arv-calendar__action--upcoming')!==false);
t('and Race Info beside it',                   strpos($r,'arv-calendar__info')!==false);
// The 58 unconfirmed races must never offer Register: their link still points
// at a previous running, which is the whole problem this element exists for.
$unconf3 = "Old | 2027-04-25 | | 50K | V | X, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/o/ |  |  |  |  | 0 | 1";
$ru = arv_season_calendar_render(array('rows'=>$unconf3));
t('an unconfirmed race never offers Register', strpos($ru,'arv-calendar__action--upcoming')===false);
t('but still offers Race Info',                strpos($ru,'arv-calendar__info')!==false);

echo "calendar date block:\n";
$GLOBALS['NOW']='2026-08-20';
$r = arv_season_calendar_render(array('rows'=>$live));
t('the day carries its month abbreviation', strpos($r,'arv-calendar__day-month">Aug<')!==false);
t('alongside the day number',               strpos($r,'arv-calendar__day-num">29<')!==false);
$GLOBALS['NOW']='2026-08-26';

echo "coordinates:\n";
// Validated, not trusted. A pin at a plausible-looking wrong coordinate is
// worse than no pin, because nothing about the map looks broken.
t('a real latitude passes',        arv_upcoming_races_coord('34.0813', 90) === '34.0813');
t('a real longitude passes',       arv_upcoming_races_coord('-112.1574', 180) === '-112.1574');
t('out-of-range latitude rejected',arv_upcoming_races_coord('91.5', 90) === '');
t('out-of-range longitude rejected',arv_upcoming_races_coord('-181', 180) === '');
t('non-numeric rejected',          arv_upcoming_races_coord('Pine, AZ', 90) === '');
t('empty rejected',                arv_upcoming_races_coord('', 90) === '');
// 0,0 is in the Atlantic: it is what an empty field becomes when something
// upstream casts before checking, not a race location.
t('zero treated as missing, not the Atlantic', arv_upcoming_races_coord('0', 90) === '');

echo "race map:\n";
$mapRow = "Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://a.com/rh/ |  |  |  | 2026-08-24 | 1 | 0 | 39.3698 | -104.8785";
$GLOBALS['NOW']='2026-08-20';
$m = arv_race_map_render(array('rows'=>$mapRow));
t('map renders with a canvas',        strpos($m,'data-arv-map')!==false);
t('and a json config block',          strpos($m,'data-arv-map-config')!==false);
t('carrying the coordinates',         strpos($m,'39.3698')!==false && strpos($m,'-104.8785')!==false);
t('and the race name',                strpos($m,'Rock Hawk')!==false);
// The map needs JavaScript and a third-party library; the list underneath
// needs neither, so the races stay readable and indexable if either fails.
t('a noscript list of every race is in the markup', strpos($m,'arv-map__fallback')!==false);

// Popup polish (division logo, date+year, distance pills, clickable name).
// Config is JSON in the markup, read back the same way the browser does
// rather than string-search, since the whole point of the pill bug this
// caught is that a wrong delimiter still produces SOME string containing
// "50K" as a substring of the raw joined text.
$cfgJson = null;
if ( preg_match('/data-arv-map-config>(.*?)<\/script>/s', $m, $cm) ) {
	$cfgJson = json_decode( str_replace('<', '<', $cm[1]), true );
}
$rockPin = $cfgJson ? $cfgJson['pins'][0] : null;
t('config decodes to real pin data',   is_array($rockPin));
t('date has the year appended',        $rockPin && false !== strpos($rockPin['date'], '2026'));
t('region resolves to a division',     $rockPin && '' !== $rockPin['logo']);
t('logo points at the right asset',    $rockPin && false !== strpos($rockPin['logo'], 'colorado.png'));

// Bad Beard's mark is white artwork on transparency, so on the popup's
// white card it rendered as nothing: the image loaded, the <img> was the
// right size, every pixel was white. Measured against the live assets, it
// is the only one of the six like that (luminance 255 vs 74-149), so it is
// flagged per-logo for a dark backing chip rather than backing all of them.
$bbRow = "Rabid Raccoon 25k | 2027-01-09 | January 9 | 25K | Raccoon Mountain | Chattanooga, TN | https://u.com/r | https://www.aravaiparunning.com/bad-beard/ |  |  |  |  | 1 | 0 | 35.04 | -85.38";
$mb = arv_race_map_render(array('rows'=>$bbRow));
$bbPin = null;
if ( preg_match('/data-arv-map-config>(.*?)<\/script>/s', $mb, $bm) ) {
	$bcfg  = json_decode( str_replace('<', '<', $bm[1]), true );
	$bbPin = $bcfg ? $bcfg['pins'][0] : null;
}
t('a bad-beard race is flagged logoDark',  $bbPin && true === $bbPin['logoDark']);
t('and still carries its logo',            $bbPin && false !== strpos($bbPin['logo'], 'bad-beard.png'));
t('a normal-contrast logo is not flagged', $rockPin && empty($rockPin['logoDark']));
// The popup itself (pills, logo img, clickable name) is built client-side
// in aravaipa-race-map.js from this config, not by PHP, so this only
// asserts the raw data the JS pill-splitter actually receives: pipe-joined
// ("50K | 25K"), not comma-joined. The JS side split on "," found nothing
// to split on there and rendered one pill with the raw " | " still in it;
// verified directly (`"50K | 25K".split(/\s*\|\s*/)` -> 2 clean tokens)
// since a PHP harness can't execute the JS that builds the popup.
t('distances field is intact for the JS splitter', $rockPin && '50K | 25K' === $rockPin['distances']);

// The source data uses BOTH delimiters and always has: a race spread over
// several row cells comes back pipe-joined, a race written as one cell keeps
// the editor's commas. In the live 84-race file that is 29 pipe against 43
// comma, so the popup's splitter has to handle both. Splitting on a comma
// alone was the original bug; splitting on a pipe alone (the first fix)
// moved the same breakage onto the larger half of the file.
$commaRow = "Moon | 2026-10-10 | October 10 | 50 Mile, 50K, 30K | Black Star Canyon | Silverado, CA | https://u.com/m | https://a.com/m |  |  |  |  | 1 | 0 | 33.76 | -117.67";
$mc = arv_race_map_render(array('rows'=>$commaRow));
$mcPin = null;
if ( preg_match('/data-arv-map-config>(.*?)<\/script>/s', $mc, $cm2) ) {
	$cfg2  = json_decode( str_replace('<', '<', $cm2[1]), true );
	$mcPin = $cfg2 ? $cfg2['pins'][0] : null;
}
t('a comma-written race keeps its commas in the payload', $mcPin && '50 Mile, 50K, 30K' === $mcPin['distances']);
// Both shapes have to survive the same regex. Asserted here in PHP against
// the exact pattern the JS uses, since this harness cannot run the JS that
// builds the popup.
$split = function ( $d ) { return array_values(array_filter(preg_split('/\s*[|,]\s*/', $d))); };
t('the shared splitter splits a comma race into 3',  3 === count($split('50 Mile, 50K, 30K')));
t('and a pipe race into 4',                          4 === count($split('50K | 25K | 10K | 5K')));
t('a single distance stays one pill',                1 === count($split('31K')));
t('and a range keeps its own wording intact',        array('10K to 50K') === $split('10K to 50K'));

// A race with no coordinates is simply not a pin. It used to be named under
// the map as "N races have no map location yet", which was the right call
// while the store was silently dropping EVERY coordinate, but now that the
// real remainder is virtual/worldwide races, listing them reads as a defect
// list for races that have no location to be missing.
$noCoord = "Ghost Race | 2026-09-05 | Sept 5 | 50K | V | X, AZ | https://u.com/g | https://a.com/g |  |  |  |  | 1 | 0 |  | ";
$m2 = arv_race_map_render(array('rows'=>$mapRow . "\n" . $noCoord));
t('no "missing location" list is rendered at all',    strpos($m2,'arv-map__missing')===false);
t('the uncoordinated race is not named under the map', strpos($m2,'Ghost Race')===false);
t('while the mapped race still gets a pin',           strpos($m2,'39.3698')!==false);

// Nothing to plot at all is nothing to render: an empty grey box with a
// heading over it reads as broken.
t('no coordinates anywhere renders nothing', arv_race_map_render(array('rows'=>$noCoord))==='');

// Null island. A race whose coordinates are absent, zero, or not numbers is
// listed as missing a location, never plotted at 0,0. The live map drew all
// 84 upcoming races in the Atlantic because the store returned no 'lat' key
// at all, null was not caught by an '' check, and (float) null is 0.0.
$zero    = "Zero Race | 2026-09-05 | Sept 5 | 50K | V | X, AZ | https://u.com/z | https://a.com/z |  |  |  |  | 1 | 0 | 0 | 0";
$mz      = arv_race_map_render(array('rows'=>$mapRow . "\n" . $zero));
t('a 0,0 race is dropped, not plotted',               strpos($mz,'Zero Race')===false);
t('and no pin is placed at null island',              strpos($mz,'"lat":0')===false && strpos($mz,'"lng":0')===false);

$junk = "Junk Race | 2026-09-05 | Sept 5 | 50K | V | X, AZ | https://u.com/j | https://a.com/j |  |  |  |  | 1 | 0 | north | west";
$mj   = arv_race_map_render(array('rows'=>$mapRow . "\n" . $junk));
t('non-numeric coordinates are dropped too',          strpos($mj,'Junk Race')===false);
t('and the good race in the same batch survives',     strpos($mj,'Rock Hawk')!==false);

// The store path is the one that actually feeds the live site, and it is
// where the bug was: every pin must carry a real coordinate.
$mAll = arv_race_map_render(array('rows'=>$mapRow));
t('every rendered pin has a non-zero lat',            strpos($mAll,'"lat":0,')===false);
t('every rendered pin has a non-zero lng',            strpos($mAll,'"lng":0,')===false);

// Same phase logic as everywhere else, so a popup cannot disagree with the
// card or the calendar row for the same race.
$GLOBALS['NOW']='2026-08-29';
t('on race day the popup offers live results', strpos(arv_race_map_render(array('rows'=>$mapRow)),'Live Results')!==false);
$GLOBALS['NOW']='2026-08-20';
t('before the race it offers registration',    strpos(arv_race_map_render(array('rows'=>$mapRow)),'Register')!==false);

// OpenStreetMap by default: no account, no token, nothing to rotate.
t('defaults to OpenStreetMap tiles', strpos(arv_race_map_render(array('rows'=>$mapRow)),'tile.openstreetmap.org')!==false);
t('a custom tile url is used when given', strpos(arv_race_map_render(array('rows'=>$mapRow,'tile_url'=>'https://api.mapbox.com/x/{z}/{x}/{y}','tile_attr'=>'Mapbox')),'api.mapbox.com')!==false);

// Height is clamped: a map shorter than a popup covers itself.
t('absurd height clamped down', strpos(arv_race_map_render(array('rows'=>$mapRow,'height'=>'99999')),'height:900px')!==false);
t('tiny height clamped up',     strpos(arv_race_map_render(array('rows'=>$mapRow,'height'=>'10')),'height:300px')!==false);

$x='<script>alert(1)</script>';
t('race name escaped in the map config', strpos(arv_race_map_render(array('rows'=>"$x | 2026-09-05 | S | 50K | V | X, AZ | https://u.com | https://a.com |  |  |  |  | 1 | 0 | 34.1 | -112.1")),'<script>alert')===false);
$GLOBALS['NOW']='2026-08-26';

echo "map asset loading:\n";
// The bug this covers: the enqueue was gated on the element's name appearing
// in post_content, which Cornerstone never puts there, so Leaflet was never
// loaded and the map rendered as an empty grey box on the live site.
$GLOBALS['ENQUEUED'] = array();
$GLOBALS['NOW']='2026-08-20';
arv_race_map_render(array('rows'=>$mapRow));
t('rendering a map enqueues leaflet js',    in_array('js:leaflet', $GLOBALS['ENQUEUED'], true));
t('and leaflet css',                        in_array('css:leaflet', $GLOBALS['ENQUEUED'], true));
t('and our own map script',                 in_array('js:aravaipa-race-map', $GLOBALS['ENQUEUED'], true));

// Nothing to draw means nothing to load: a page with no usable map should
// not pull a third-party library off a CDN for an element that renders
// nothing.
$GLOBALS['ENQUEUED'] = array();
arv_race_map_render(array('rows'=>$noCoord));
t('a map with no pins loads nothing',       empty($GLOBALS['ENQUEUED']));
$GLOBALS['ENQUEUED'] = array();
arv_race_map_render(array('rows'=>''));
t('an empty map loads nothing either',      empty($GLOBALS['ENQUEUED']));
$GLOBALS['NOW']='2026-08-26';

echo "fixture integrity:\n";
// Every column added to the row format this session (end date, live URL,
// registration close, confirmed, guessed) silently shifted every fixture that
// was a field short, because the format is anchored from the end. Twice that
// produced a test which passed for the wrong reason. This scans every race
// row literal in this file and checks it actually parses into the shape it
// claims, so the next column addition fails loudly here instead of quietly
// mis-parsing somewhere downstream.
$src = file_get_contents(__FILE__);
preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $src, $lits);
$checked = 0; $broken = array();
foreach ($lits[1] as $lit) {
  foreach (explode("\n", str_replace('\n', "\n", $lit)) as $row) {
    if (!preg_match('/^[^|]+\|\s*\d{4}-\d{2}-\d{2}\s*\|/', $row)) continue;
    // Rows that exist precisely to be rejected (an impossible calendar date)
    // are not fixture drift and must not be flagged as such.
    if (preg_match('/\|\s*\d{4}-02-(?:30|31)\s*\|/', $row)) continue;
    $cells = array_map('trim', explode('|', $row));
    $p = arv_upcoming_races_parse_row($cells);
    $checked++;
    if (!$p) { $broken[] = substr($row,0,40).' (did not parse)'; continue; }
    // register must be a URL or empty; venue must never be one. A shifted row
    // fails one of these immediately.
    if ($p['register'] !== '' && strpos($p['register'],'http') !== 0) $broken[] = $cells[0].': register="'.substr($p['register'],0,30).'"';
    elseif (strpos($p['venue'],'http') === 0) $broken[] = $cells[0].': venue is a URL';
  }
}
t("every race row fixture parses into the right shape ($checked checked)", empty($broken));
if (!empty($broken)) foreach (array_slice($broken,0,6) as $b) echo "       $b\n";

echo "hero overlay clamp:\n";
t('overlay > 1 clamped', strpos(arv_race_hero_render(array('overlay'=>'9','image'=>'https://x/y.jpg','race_name'=>'R')),'--arv-overlay:1;')!==false);
t('negative overlay clamped', strpos(arv_race_hero_render(array('overlay'=>'-4','image'=>'https://x/y.jpg','race_name'=>'R')),'--arv-overlay:0;')!==false);
t('no cta when url empty', strpos(arv_race_hero_render(array('race_name'=>'R','cta_label'=>'Register','cta_url'=>'')),'__cta')===false);

echo "partner tier + toggle:\n";
t('bad tier falls back to supporting', strpos(arv_partner_grid_render(array('rows'=>"A | https://x/l.png | | bogus")),'item--supporting')!==false);
t('logo-less partner skipped', arv_partner_grid_render(array('rows'=>"A |  | https://x"))==='');
t('grayscale "false" string respected', strpos(arv_partner_grid_render(array('rows'=>"A | https://x/l.png",'grayscale'=>'false')),'--grayscale')===false);
t('grayscale default on', strpos(arv_partner_grid_render(array('rows'=>"A | https://x/l.png")),'--grayscale')!==false);

echo "escaping (untrusted editor input):\n";
$x='<script>alert(1)</script>';
t('hero name escaped', strpos(arv_race_hero_render(array('race_name'=>$x)),'<script>alert')===false);
t('distance name escaped', strpos(arv_distance_cards_render(array('rows'=>"$x | 1")),'<script>alert')===false);
t('timeline escaped', strpos(arv_event_timeline_render(array('rows'=>"Day | 1PM | $x")),'<script>alert')===false);
t('partner alt escaped', strpos(arv_partner_grid_render(array('rows'=>"$x | https://x/l.png")),'<script>alert')===false);
t('region name escaped', strpos(arv_region_map_render(array('rows'=>"$x | 50 | 50 | https://x/")),'<script>alert')===false);
t('region detail escaped', strpos(arv_region_map_render(array('rows'=>"A | 50 | 50 | https://x/ | $x")),'<script>alert')===false);

echo "\n$pass passed, $fail failed\n";
exit($fail>0?1:0);
