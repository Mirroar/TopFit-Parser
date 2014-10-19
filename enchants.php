<?php
//TODO: see if info about level-adjusted gem stats can be parsed
//TODO: add more requirements to output for when they can actually be used by topfit

include 'utilities.php';

define('ENCHANT_REGEX', '#_\\[(\\d+)\\]=(\\{[^\\}]*\\});#');
define('ENCHANT_SPELL_REGEX', '#\\(\\$WH\\.g_enhanceTooltip\\.bind\\(tt\\)\\)\\(ITEM_ID,[^\\[\\)]*\\[(\\d+)\\][^\\)]*\\)#');
define('ENCHANT_BASE_REGEX', '#_\\[SPELL_ID\\]=(\\{[^\\}]*\\});#');
define('ENCHANT_TOOLTIP_REGEX', '#_\\[SPELL_ID\\]\\.tooltip_enus = \'(.*)\';#');
define('ENCHANT_ID_REGEX', '#<td[^>]*>Enchant Item\\: [^\\(]*\\((\\d+)\\)</td>#');

define('TOOLTIP_SIMPLE_EFFECT_REGEX', '#Permanently enchants? (.*?) to (.*)\.#');

// map targets from tooltip to item locations
// TODO: use slot numbers from WoW
$slot_mapping = array(
  'a ring' => 0,
  'a neck' => 0,
  'a cloak' => 0,
  'a weapon' => 0,
  'gloves' => 0,
  'bracers' => 0,
  'a chest' => 0,
  'chest armor' => 0,
  'boots' => 0,
  'a pair of boots' => 0,
  'a shoulder slot item' => 0,
  'a melee weapon' => 0,
  'a two-handed weapon' => 0,
  'a two-handed melee weapon' => 0,
  'a staff' => 0,
  'a shield' => 0,
  'a shield or off-hand item' => 0,
  'a shield or held item' => 0,
);

// load www.wowhead.com/items=0.6 and go from there
$index = download_file('http://www.wowhead.com/items=0.6');
$enchant_matches = array();
preg_match_all(ENCHANT_REGEX, $index, $enchant_matches, PREG_SET_ORDER);
debug('found ' .count($enchant_matches) . ' potential enchants...');

$enchants = array();
foreach ($enchant_matches as $match) {
  $enchant = array();
  $enchant['item_id'] = $match[1];
  $enchant['base_data'] = json_decode($match[2], TRUE);

  $enchant_url = 'http://www.wowhead.com/item=' . $enchant['item_id'];
  $enchant_file = download_file($enchant_url);
  if (empty($enchant_file))
    break;

  // find the corresponding spell that applies the item's effect
  $spell_matches = array();
  preg_match_all(str_replace('ITEM_ID', $enchant['item_id'], ENCHANT_SPELL_REGEX), $enchant_file, $spell_matches);
  foreach ($spell_matches[1] as $spell_id) {
    $enchant['spell_id'] = $spell_id;

    $spell_url = 'http://www.wowhead.com/spell=' . $enchant['spell_id'];
    $spell_file = download_file($spell_url);

    if (empty($spell_file))
      break 2;

    $spell_info_matches = array();
    preg_match_all(str_replace('SPELL_ID', $enchant['spell_id'], ENCHANT_BASE_REGEX), $spell_file, $spell_info_matches);
    foreach ($spell_info_matches[1] as $spell_json) {
      $enchant['spell_data'] = json_decode($spell_json, TRUE);
    }

    $tooltip_matches = array();
    preg_match_all(str_replace('SPELL_ID', $enchant['spell_id'], ENCHANT_TOOLTIP_REGEX), $spell_file, $tooltip_matches);
    foreach ($tooltip_matches[1] as $tooltip) {
      $enchant['tooltip'] = str_replace('\\\'', '\'', $tooltip);
    }

    $enchant_id_matches = array();
    preg_match_all(ENCHANT_ID_REGEX, $spell_file, $enchant_id_matches);
    foreach ($enchant_id_matches[1] as $enchant_id) {
      $enchant['enchant_id'] = $enchant_id;
    }

    // now begins the fun part: parsing the tooltip to get information about the enchant's effects
    $effect_matches = array();
    preg_match_all(TOOLTIP_SIMPLE_EFFECT_REGEX, $enchant['tooltip'], $effect_matches, PREG_SET_ORDER);
    foreach ($effect_matches as $effect) {
      if (!isset($slot_mapping[$effect[1]])) {
        debug('Unknown Effect Target: ' . $effect[1]);
      }
      //debug('Effect: ' . $effect[2]);
    }

    break;
  }

  $enchants[] = $enchant;
}

echo '<pre>' . print_r($enchants, TRUE) . '</pre>';

debug('Done!');
