<?php
// Standalone harness: stubs the Cornerstone + WP functions the elements call,
// then renders each one with realistic Black Canyon data so the output can be
// eyeballed in a browser before any of this goes near the live site.
define('ABSPATH', true);
define('ARV_ELEMENTS_URL', './');
define( 'ARV_ELEMENTS_PATH', __DIR__ . '/' );

// WordPress core defines these; the harness does not load WordPress. Without
// them includes/helpers.php fatals on the constant it builds out of
// DAY_IN_SECONDS, which took the whole preview with it. arv-store-test.php
// has stubbed the same four since it was written.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

function cs_register_element($n, $c) { $GLOBALS['ARV_EL'][$n] = $c; }
function cs_value($d, $desig = 'markup', $p = false) { return $d; }
function cs_compose_values($v, ...$p) { return $v; }
function cs_compose_controls($c, ...$p) { return $c; }
function cs_partial_controls($n) { return array(); }
function __($s, $d = '') { return $s; }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

require_once __DIR__ . '/includes/helpers.php';
foreach (['race-hero','distance-cards','event-timeline','partner-grid','countdown','region-map'] as $e) {
  require_once __DIR__ . '/includes/elements/' . $e . '.php';
}

// Defaults straight out of each element definition, so the harness renders
// exactly what an editor sees when they drop the element on a page.
function arv_defaults($name, $over = array()) {
  $v = $GLOBALS['ARV_EL'][$name]['values'];
  return array_merge($v, array('mod_id' => 'e123-4', 'class' => ''), $over);
}

$logo = 'https://placehold.co/300x120/272727/ffffff?text=PARTNER';

$blocks = array(
  'Race Hero' => arv_race_hero_render(arv_defaults('aravaipa-race-hero', array(
    'cta_url' => 'https://ultrasignup.com/register.aspx?did=1',
    'status_note' => 'Lottery opens Sept 1',
    'status' => 'lottery',
  ))),
  'Distance Cards' => arv_distance_cards_render(arv_defaults('aravaipa-distance-cards', array(
    'rows' => "100K | 5,900 ft | 20 hrs | 7:00 AM | https://ultrasignup.com\n50K | 2,700 ft | 11 hrs | 8:00 AM | https://ultrasignup.com\n60K Sky | 4,100 ft | 14 hrs | 6:30 AM",
  ))),
  'Event Timeline' => arv_event_timeline_render(arv_defaults('aravaipa-event-timeline')),
  'Partner Grid' => arv_partner_grid_render(arv_defaults('aravaipa-partner-grid', array(
    'rows' => "HOKA | $logo | https://hoka.com | title\nKahtoola | $logo | https://kahtoola.com | presenting\nAravaipa | $logo | | supporting\nSquirrels Nut Butter | $logo | | supporting\nNaked Running | $logo | | supporting",
  ))),
  'Countdown' => arv_countdown_render(arv_defaults('aravaipa-countdown', array(
    'heading' => 'Until the gun',
  ))),
  'Region Map' => arv_region_map_render(arv_defaults('aravaipa-region-map')),
);

$out = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
$out .= '<link rel="stylesheet" href="assets/aravaipa-elements.css">';
$out .= '<style>body{margin:0;font-family:-apple-system,Helvetica,Arial,sans-serif;color:#272727;background:#f4f4f4}';
$out .= '.h{padding:.4rem 1rem;background:#272727;color:#8f8;font:700 11px monospace;letter-spacing:.1em}';
$out .= '.w{padding:2.5rem 1.5rem;background:#fff;margin-bottom:1.5rem}</style>';
foreach ($blocks as $label => $html) {
  $out .= '<div class="h">' . strtoupper($label) . '</div>';
  // The hero is full bleed by design, so it renders outside the padded wrap.
  $out .= ($label === 'Race Hero') ? $html : '<div class="w">' . $html . '</div>';
}
$out .= '<script src="assets/aravaipa-countdown.js"></script>';
file_put_contents(__DIR__ . '/preview.html', $out);
echo "rendered preview.html (" . strlen($out) . " bytes)\n";
