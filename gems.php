<?php

include 'utilities.php';
//TODO: gem colors
//TODO: socket types

define('GEM_REGEX', '#_\\[(\\d+)\\]=(\\{[^\\}]*\\});#');
define('STATS_REGEX', '#\\$\\.extend\\(g_items\\[GEM_ID\\], (.*)\\);#');

// map wowhead stat names to topfit equivalents
// use NULL to ignore a certain stat
$stat_mapping = array(
  // deliberately unmapped
  'quality' => '',
  'classs' => '',
  'flags2' => '',
  'id' => '',
  'level' => '',
  'name' => '',
  'reqlevel' => '',
  'slot' => '',
  'source' => '',
  'sourcemore' => '',
  'subclass' => '',
  'buyprice' => '',
  'statsInfo' => '',
  'sellprice' => '',
  'avgbuyout' => '',
  'commondrop' => '',

  //TODO: add handling for these
  'classes' => '',
  'specs' => '',
  'reqclass' => '',
  'reqskill' => '',
  'reqskillrank' => '',
  'side' => '', // probably alliance / horde

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

// load www.wowhead.com/items=3 and go from there
$index = download_file('http://www.wowhead.com/items=3');
$gem_matches = array();
preg_match_all(GEM_REGEX, $index, $gem_matches, PREG_SET_ORDER);
debug('found ' .count($gem_matches) . ' gems...');

$gems = array();
$gem_count = 0;
foreach ($gem_matches as $match) {
  //if ($gem_count++ >= 100) break;

  $gem = array();
  $gem['item_id'] = $match[1];
  $gem['base_data'] = json_decode($match[2], TRUE);

  $gem_file = download_file('http://www.wowhead.com/item=' . $gem['item_id']);
  if (empty($gem_file))
    break;
  $stat_matches = array();
  preg_match_all(str_replace('GEM_ID', $gem['item_id'], STATS_REGEX), $gem_file, $stat_matches);

  $gem['topfit_stats'] = array();
  foreach ($stat_matches[1] as $stat_data) {
    $gem['stat_data'] = json_decode($stat_data, TRUE);

    if (!empty($gem['stat_data']['jsonequip'])) {
      foreach ($gem['stat_data']['jsonequip'] as $key => $value) {
        if (!isset($stat_mapping[$key])) {
          debug('Unknown stat: ' . $key . ' (' . print_r($value, TRUE) . ')');
          continue;
        }

        if (!empty($stat_mapping[$key])) {
          $gem['topfit_stats'][$stat_mapping[$key]] = $value;
        }
      }
    }
  }
  if (empty($gem['topfit_stats'])) {
    debug('No applicable stats found for ' . $gem['base_data']['name_enus']);
  }

  $gems[] = $gem;
}

/*echo '<pre>';
print_r($gems);
echo '</pre>';//*/

debug('Done!');
