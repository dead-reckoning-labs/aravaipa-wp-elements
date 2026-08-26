<?php
define('ABSPATH', true); define('ARV_ELEMENTS_URL','./');
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
$GLOBALS['NOW']='2026-08-25';
function current_time($f){return $GLOBALS['NOW'];}
function apply_filters($tag,$v){return $v;}
define('DAY_IN_SECONDS',86400);
$d=__DIR__.'/'; require_once $d.'includes/helpers.php';
foreach(['race-hero','distance-cards','event-timeline','partner-grid','countdown','region-map','upcoming-races','season-calendar'] as $e) require_once $d."includes/elements/$e.php";

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
$r = arv_upcoming_races_render(array('rows'=>"Jangover | 2027-09-19 | September 19 | 75K | 50K | 25K | 15K | 7K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?did=1 | https://www.aravaiparunning.com/insomniac/jangover/ | https://x/i.png |  |  |  | "));
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
$row = "Black Canyon 100K | 2027-02-13 | February 13 | 100K | 60K | Black Canyon Trail | Mayer, AZ | https://ultrasignup.com/register.aspx?did=9 | https://www.aravaiparunning.com/blackcanyon/ | https://x/bc.png |  |  |  | ";
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
  arv_upcoming_races_render(array('rows'=>"S | 2027-09-01 | | 5K | Multiple Regional Parks | Arizona | https://u.com | https://a.com | |  |  | "))), true)['@graph'][0];
t('region-only location becomes addressRegion with no bogus locality', ($e['location']['address']['addressRegion'] ?? '')==='Arizona' && !isset($e['location']['address']['addressLocality']));

echo "past races drop off:\n";
$GLOBALS['NOW']='2026-09-15';
$list = "Past | 2026-09-01 | | 50K | V | P, AZ | https://u.com | https://a.com |  |  | \n"
      . "Today | 2026-09-15 | | 50K | V | P, AZ | https://u.com | https://a.com |  |  | \n"
      . "Future | 2026-09-20 | | 50K | V | P, AZ | https://u.com | https://a.com |  |  | ";
$r = arv_upcoming_races_render(array('rows'=>$list));
t('a race that already ran is gone', strpos($r,'Past')===false);
t('a race running today is still up', strpos($r,'Today')!==false);
t('a future race is still up', strpos($r,'Future')!==false);
t('and the dropped race is out of the schema too', substr_count($r,'"@type":"SportsEvent"')===2);

// Cocodona runs the better part of a week. Dropping it on day two, while
// runners are still on the course, would be worse than leaving it a day long.
$GLOBALS['NOW']='2026-09-13';
$multi = "Cocodona | 2026-09-12 | September 12-18 | 250 Mile | Start | Black Canyon City, AZ | https://u.com | https://a.com |  | 2026-09-18 |  | ";
t('a multi-day race stays up mid-race', strpos(arv_upcoming_races_render(array('rows'=>$multi)),'Cocodona')!==false);
$GLOBALS['NOW']='2026-09-18';
t('and through its final day', strpos(arv_upcoming_races_render(array('rows'=>$multi)),'Cocodona')!==false);
$GLOBALS['NOW']='2026-09-19';
t('holds its results through the weekend', strpos(arv_upcoming_races_render(array('rows'=>$multi)),'Cocodona')!==false);
$GLOBALS['NOW']='2026-09-21';
t('and clears on the Monday after', arv_upcoming_races_render(array('rows'=>$multi))==='');
$e = json_decode(preg_replace('#.*<script type="application/ld\+json">(.*?)</script>.*#s','$1',
  (function(){ $GLOBALS['NOW']='2026-09-13'; return arv_upcoming_races_render(array('rows'=>"Cocodona | 2026-09-12 | Sept 12-18 | 250 Mile | Start | Black Canyon City, AZ | https://u.com | https://a.com |  | 2026-09-18 |  | ")); })()), true)['@graph'][0];
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
$rh = "Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://www.aravaiparunning.com/rock-hawk/ |  |  |  |  | ";
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
$smr = "Snow Mountain Ranch | 2026-09-12 | September 12 | 50KM | 33KM | Snow Mountain Ranch | Granby, CO | https://ultrasignup.com/register.aspx?did=131162 | https://a.com/smr/ |  |  |  | 2026-09-07 | ";
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
$rh2 = "Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://a.com/rh/ |  |  | https://live.aravaiparunning.com/#/rock_hawk-2026 | 2026-08-24 | ";
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
$unconf = "Guess | 2027-04-25 | | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/g/ |  |  | https://live.aravaiparunning.com/#/g-2027 |  | 0";
t('unconfirmed race is dropped by default', arv_upcoming_races_render(array('rows'=>$unconf))==='');
t('unconfirmed race appears with the toggle off', strpos(arv_upcoming_races_render(array('rows'=>$unconf,'only_confirmed'=>'false')),'Guess')!==false);

$conf = "Real | 2026-09-01 | | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/r/ |  |  |  | 1";
t('confirmed race renders under the default', strpos(arv_upcoming_races_render(array('rows'=>$conf)),'Real')!==false);

// A row from before this column existed, or written by hand without it, has
// no eleventh tail value. It should behave exactly as it always did rather
// than silently disappear from every page that filters on confirmed.
$old = "Legacy | 2026-09-01 | | 50K | V | P, AZ | https://ultrasignup.com/register.aspx?did=9 | https://a.com/l/ |  |  | ";
t('a row with no confirmed column is treated as confirmed', strpos(arv_upcoming_races_render(array('rows'=>$old)),'Legacy')!==false);

echo "season calendar:\n";
t('empty rows return nothing', arv_season_calendar_render(array('rows'=>''))==='');

$cal = "Jan Race | 2027-01-15 | January 15 | 50K | V1 | Reno, NV | https://u.com/1 | https://a.com/1 |  |  |  | 1 | 1\nDec Race | 2026-12-05 | December 5 | 50K | V2 | Denver, CO | https://u.com/2 | https://a.com/2 |  |  |  | 1 | 0";
$r = arv_season_calendar_render(array('rows'=>$cal));
t('a race with a guessed year still renders', strpos($r,'Jan Race')!==false);
t('unconfirmed race is shown, unlike Upcoming Races', strpos($r,'Dec Race')!==false);
t('unconfirmed race carries the "Details soon" flag', strpos($r,'Details soon')!==false);
t('confirmed race does not', substr_count($r,'Details soon')===1);
// A plain Jan-through-Dec calendar, not a window rolling from today: that
// job already belongs to Upcoming Races. January sorts first even though
// "Jan Race" is tagged the later calendar year, which is the point: month/day
// is read off the row directly and the guessed year plays no part in order.
t('January groups before December, read off month/day not the guessed year', strpos($r,'Jan Race') < strpos($r,'Dec Race'));
t('month headings are real month names', strpos($r,'>December<')!==false && strpos($r,'>January<')!==false);

// This element never offers registration, on purpose: that is the entire
// reason it exists rather than reusing Upcoming Races for the whole season.
t('no register/live/results button anywhere', strpos($r,'arv-races__cta')===false && strpos($r,'>Register<')===false);
t('the row itself links to the race page', strpos($r,'href="https://a.com/2"')!==false);

// A race with two entries in the same month groups under one heading.
$two = "A | 2027-03-05 | | 50K | V | X, AZ | https://u.com/a | https://a.com/a |  |  |  |  | 1\nB | 2027-03-20 | | 50K | V | X, AZ | https://u.com/b | https://a.com/b |  |  |  |  | 1";
t('two races the same month share one heading', substr_count(arv_season_calendar_render(array('rows'=>$two)),'arv-calendar__month-name')===1);

// A race name with no page falls back to the register link rather than a
// dead href, and escaping still applies to untrusted input.
$x='<script>alert(1)</script>';
t('name is escaped', strpos(arv_season_calendar_render(array('rows'=>"$x | 2027-03-05 | | 50K | V | X, AZ | https://u.com/a | |  |  |  |  | 1")),'<script>alert')===false);

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
