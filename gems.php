<?php
//TODO: see if info about level-adjusted gem stats can be parsed

include 'utilities.php';

define('GEM_REGEX', '#_\\[(\\d+)\\]=(\\{[^\\}]*\\});#');
define('STATS_REGEX', '#\\$\\.extend\\(g_items\\[GEM_ID\\], (.*)\\);#');

// map wowhead stat names to topfit equivalents
$stat_mapping = array(
  // deliberately unmapped
  'quality' => '',
  'classs' => '',
  'flags2' => '',
  'id' => '',
  'level' => '',
  'name' => '',
  'slot' => '',
  'source' => '',
  'sourcemore' => '',
  'buyprice' => '',
  'statsInfo' => '',
  'sellprice' => '',
  'avgbuyout' => '',
  'commondrop' => '',
  'classes' => '',
  'reqclass' => '',
  'specs' => '',

  // interesting information that is parsed and used outside of normal stats
  'reqskill' => '', // see $skill_mapping
  'reqskillrank' => '',
  'side' => '', // alliance (1) / horde (2)
  'reqlevel' => '',
  'subclass' => '',

  // actual stats
  'agi' => 'ITEM_MOD_AGILITY_SHORT',
  'str' => 'ITEM_MOD_STRENGTH_SHORT',
  'int' => 'ITEM_MOD_INTELLIGENCE_SHORT',
  'sta' => 'ITEM_MOD_STAMINA_SHORT',
  'spi' => 'ITEM_MOD_SPIRIT_SHORT',
  'critstrkrtng' => 'ITEM_MOD_CRIT_RATING_SHORT',
  'hastertng' => 'ITEM_MOD_HASTE_RATING_SHORT',
  'mastrtng' => 'ITEM_MOD_MASTERY_RATING_SHORT',
  'parryrtng' => 'ITEM_MOD_PARRY_RATING_SHORT',
  'dodgertng' => 'ITEM_MOD_DODGE_RATING_SHORT',
  'resirtng' => 'ITEM_MOD_RESILIENCE_RATING_SHORT',
  'pvppower' => 'ITEM_MOD_PVP_POWER_SHORT',
  'multistrike' => 'ITEM_MOD_CR_MULTISTIKE_SHORT',
  'versatility' => 'ITEM_MOD_VERSATILITY',
  //ITEM_MOD_CR_LIFESTEAL_SHORT
  //ITEM_MOD_EXTRA_ARMOR_SHORT
  'health' => '', //TODO: find global string
  'arcres' => 'RESISTANCE6_NAME',
  'firres' => 'RESISTANCE2_NAME',
  'frores' => 'RESISTANCE4_NAME',
  'holres' => 'RESISTANCE1_NAME',
  'natres' => 'RESISTANCE3_NAME',
  'shares' => 'RESISTANCE6_NAME',
  'dmg' => 'ITEM_MOD_DAMAGE_PER_SECOND_SHORT', // might not really be dps, but +damage, however that really calculates. but good enough for now
);

// mapping profession IDs to names
$skill_mapping = array(
  202 => 'engineering',
  755 => 'jewelcrafting',
);

// mapping auction item subclasses to socket colors
$socket_mapping = array(
  0 => array('red'),
  1 => array('blue'),
  2 => array('yellow'),
  3 => array('red', 'blue'),
  4 => array('yellow', 'blue'),
  5 => array('red', 'yellow'),
  6 => array('meta'),
  9 => array('sha-touched'),
  10 => array('cogwheel'),
);

// load www.wowhead.com/items=3 and go from there
$index = download_file('http://www.wowhead.com/items=3');
$gem_matches = array();
preg_match_all(GEM_REGEX, $index, $gem_matches, PREG_SET_ORDER);
debug('found ' .count($gem_matches) . ' gems...');

$gems = array();
$gem_count = 0;
foreach ($gem_matches as $match) {
  $gem = array();
  $gem['item_id'] = $match[1];
  $gem['base_data'] = json_decode($match[2], TRUE);

  $gem_url = 'http://www.wowhead.com/item=' . $gem['item_id'];
  $gem_file = download_file($gem_url);
  if (empty($gem_file))
    break;
  $stat_matches = array();
  preg_match_all(str_replace('GEM_ID', $gem['item_id'], STATS_REGEX), $gem_file, $stat_matches);

  foreach ($stat_matches[1] as $stat_data) {
    $gem['stat_data'] = json_decode($stat_data, TRUE);

    if (!empty($gem['stat_data']['jsonequip'])) {
      $data = $gem['stat_data']['jsonequip'];
      foreach ($data as $key => $value) {
        if (!isset($stat_mapping[$key])) {
          debug('Unknown stat: ' . $key . ' (' . print_r($value, TRUE) . ') in ' . 'http://www.wowhead.com/item=' . $gem['item_id']);
          continue;
        }

        if (!empty($stat_mapping[$key])) {
          $gem['topfit']['stats'][$stat_mapping[$key]] = $value;
        }
      }

      if (!empty($data['reqskill'])) {
        $gem['topfit']['requirements'][$skill_mapping[$data['reqskill']]] = $data['reqskillrank'];
      }
      if (!empty($data['side'])) {
        $gem['topfit']['requirements']['faction'] = ($data['side'] == 1 ? 'alliance' : 'horde');
      }
      if (!empty($data['reqlevel'])) {
        $gem['topfit']['requirements']['level'] = $data['reqlevel'] == 1;
      }
      if (isset($data['subclass']) && !empty($socket_mapping[$data['subclass']])) {
        $gem['topfit']['requirements']['socketcolor'] = $socket_mapping[$data['subclass']];
      }
    }
  }
  if (empty($gem['topfit']['stats'])) {
    // don't need to save gems that do not provide stats (they are the raw gems)
    debug('No applicable stats found for ' . $gem['base_data']['name_enus'] . ' - skipping');
    continue;
  }

  $gems[] = $gem;
}

// data is collected, time to generate a lua file
$output = 'local addonName, ns = ...

ns.gemIDs = {
';

foreach ($gems as $gem) {
  $output .= '  [' . $gem['item_id'] . '] = { -- ' . $gem['base_data']['name_enus'] . "\n";

  $output .= '  },' . "\n";
}

$output .= '}' . "\n";

print '<pre>' . $output . '</pre>';

debug('Done!');
