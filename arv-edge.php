<?php
define('ABSPATH', true); define('ARV_ELEMENTS_URL','./');
function cs_register_element($n,$c){$GLOBALS['ARV_EL'][$n]=$c;}
function cs_value($d,$x='markup',$p=false){return $d;}
function cs_compose_values($v,...$p){return $v;}
function cs_compose_controls($c,...$p){return $c;}
function cs_partial_controls($n){return array();}
function __($s,$d=''){return $s;}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$d=__DIR__.'/'; require_once $d.'includes/helpers.php';
foreach(['race-hero','distance-cards','event-timeline','partner-grid','countdown'] as $e) require_once $d."includes/elements/$e.php";

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

echo "\n$pass passed, $fail failed\n";
exit($fail>0?1:0);
