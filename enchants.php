<?php
//TODO: see if info about level-adjusted gem stats can be parsed
//TODO: add more requirements to output for when they can actually be used by topfit

include 'utilities.php';

define('ENCHANT_REGEX', '#_\\[(\\d+)\\]=(\\{[^\\}]*\\});#');
define('ENCHANT_SPELL_REGEX', '#\\(\\$WH\\.g_enhanceTooltip\\.bind\\(tt\\)\\)\\(ITEM_ID,[^\\[\\)]*\\[(\\d+)\\][^\\)]*\\)#');
define('ENCHANT_BASE_REGEX', '#_\\[SPELL_ID\\]=(\\{[^\\}]*\\});#');
define('ENCHANT_TOOLTIP_REGEX', '#_\\[SPELL_ID\\]\\.tooltip_enus = \'(.*)\';#');
define('ENCHANT_ID_REGEX', '#<td[^>]*>Enchant Item\\: [^\\(]*\\((\\d+)\\)</td>#i');

define('TOOLTIP_MAX_LEVEL_REGEX', '#Cannot be applied to items higher than level (\\d+)#i');
define('TOOLTIP_SIMPLE_EFFECT_REGEX', '#Permanently enchants? (.*?) [ts]o (.*)\\.#i');
define('TOOLTIP_TIMED_STAT_REGEX', '#(?:sometimes)? increase (.*?) by (\\d+) for (\\d+) sec#i');
define('TOOLTIP_SIMPLE_STAT_REGEX', '#increase (.*?) by (\\d+)#i');

// map targets from tooltip to item locations
// TODO: use slot numbers from WoW
$slot_mapping = array(
  'a neck' => 2,
  'a shoulder slot item' => 3,
  'a chest' => 5,
  'chest armor' => 5,
  'boots' => 8,
  'a pair of boots' => 8,
  'bracers' => 9,
  'gloves' => 10,
  'a ring' => 11,
  'a cloak' => 15,
  'a weapon' => 16,
  'your weapon' => 16,
  'a melee weapon' => 16,
  'a two-handed weapon' => 16,
  'a two-handed melee weapon' => 16,
  'a staff' => 16,
  'a shield' => 17,
  'a shield or off-hand item' => 17,
  'a shield or held item' => 17,
);

$stat_mapping = array(
  'Agility' => 'ITEM_MOD_AGILITY_SHORT',
  'your Agility' => 'ITEM_MOD_AGILITY_SHORT',
  'Strength' => 'ITEM_MOD_STRENGTH_SHORT',
  'Intellect' => 'ITEM_MOD_INTELLECT_SHORT',
  'your Intellect' => 'ITEM_MOD_INTELLECT_SHORT',
  'Stamina' => 'ITEM_MOD_STAMINA_SHORT',
  'Spirit' => 'ITEM_MOD_SPIRIT_SHORT',
  'Spirit and Stamina' => array(
    'ITEM_MOD_STAMINA_SHORT',
    'ITEM_MOD_SPIRIT_SHORT',
  ),
  'Stamina and Spirit' => array(
    'ITEM_MOD_STAMINA_SHORT',
    'ITEM_MOD_SPIRIT_SHORT',
  ),
  'all stats' => array(
    'ITEM_MOD_AGILITY_SHORT',
    'ITEM_MOD_STRENGTH_SHORT',
    'ITEM_MOD_INTELLECT_SHORT',
    'ITEM_MOD_STAMINA_SHORT',
    'ITEM_MOD_SPIRIT_SHORT',
  ),
  'crit' => 'ITEM_MOD_CRIT_RATING_SHORT',
  'critical strike' => 'ITEM_MOD_CRIT_RATING_SHORT',
  'your critical strike' => 'ITEM_MOD_CRIT_RATING_SHORT',
  'haste' => 'ITEM_MOD_HASTE_RATING_SHORT',
  'Haste' => 'ITEM_MOD_HASTE_RATING_SHORT',
  'mastery' => 'ITEM_MOD_MASTERY_RATING_SHORT',
  'multistrike' => 'ITEM_MOD_CR_MULTISTIKE_SHORT',
  'versatility' => 'ITEM_MOD_VERSATILITY',
  'parry' => 'ITEM_MOD_PARRY_RATING_SHORT',
  'dodge' => 'ITEM_MOD_DODGE_RATING_SHORT',
  'your dodge' => 'ITEM_MOD_DODGE_RATING_SHORT',
  'Agility and dodge' => array(
    'ITEM_MOD_AGILITY_SHORT',
    'ITEM_MOD_DODGE_RATING_SHORT',
  ),
  'attack power' => 'ITEM_MOD_MELEE_ATTACK_POWER_SHORT',
  'your attack power' => 'ITEM_MOD_MELEE_ATTACK_POWER_SHORT',
  'spell power' => 'ITEM_MOD_SPELL_POWER_SHORT',
  'PvP Power' => 'ITEM_MOD_PVP_POWER_SHORT',
  'PvP power' => 'ITEM_MOD_PVP_POWER_SHORT',
  'your PvP Power' => 'ITEM_MOD_PVP_POWER_SHORT',
  'PvP Resilience' => 'ITEM_MOD_RESILIENCE_RATING_SHORT',
  'armor' => 'RESISTANCE0_NAME',
  'Bonus Armor' => 'RESISTANCE0_NAME',
  'health' => array(), // usually irrelevant and not used by TopFit
  'mana' => array(), // usually irrelevant and not used by TopFit
  'movement speed' => array(), // currently not used by TopFit
  'hit' => array(), // hit rating: gone!
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

    if (!empty($enchant['enchant_id'])) {
      // now begins the fun part: parsing the tooltip to get information about the enchant's effects
      $parse_successful = FALSE;

      $effect_matches = array();
      $tt = $enchant['tooltip'];

      // clean tooltip from unnecessary info
      $tt = strip_tags($tt);

      preg_match_all(TOOLTIP_MAX_LEVEL_REGEX, $tt, $effect_matches, PREG_SET_ORDER);
      foreach ($effect_matches as $effect) {
        $enchant['topfit']['requirements']['max_ilevel'] = $effect[1];
        $tt = str_replace($effect[0], '', $tt);
      }

      preg_match_all(TOOLTIP_SIMPLE_EFFECT_REGEX, $tt, $effect_matches, PREG_SET_ORDER);
      foreach ($effect_matches as $effect) {
        if (isset($slot_mapping[$effect[1]])) {
          $enchant['slot'] = $slot_mapping[$effect[1]];
        } else {
          debug('Unknown Effect Target: ' . $effect[1]);
        }
        //$parse_successful = TRUE;
        //debug('Effect: ' . $effect[2]);
        $stat_matches = array();
        preg_match_all(TOOLTIP_TIMED_STAT_REGEX, $effect[2], $stat_matches, PREG_SET_ORDER);
        foreach ($stat_matches as $stat) {
          if (isset($stat_mapping[$stat[1]])) {
            $stats = $stat_mapping[$stat[1]];
            if (!is_array($stats)) {
              $stats = array($stats);
            }
            foreach ($stats as $single_stat) {
              $enchant['topfit']['stats'][$single_stat] = $stat[2] / $stat[3];
            }
            $parse_successful = TRUE;

            $effect[2] = str_replace($stat[0], '', $effect[2]);
          } else {
            debug('Unknown stat (timed): ' . $stat[1]);
          }
        }

        $stat_matches = array();
        preg_match_all(TOOLTIP_SIMPLE_STAT_REGEX, $effect[2], $stat_matches, PREG_SET_ORDER);
        foreach ($stat_matches as $stat) {
          if (isset($stat_mapping[$stat[1]])) {
            $stats = $stat_mapping[$stat[1]];
            if (!is_array($stats)) {
              $stats = array($stats);
            }
            foreach ($stats as $single_stat) {
              $enchant['topfit']['stats'][$single_stat] = $stat[2];
            }
            $parse_successful = TRUE;

            $effect[2] = str_replace($stat[0], '', $effect[2]);
          } else {
            debug('Unknown stat: ' . $stat[1]);
          }
        }
      }

      if (!$parse_successful) {
        debug('Could not parse enchant tooltip: ' . $tt);
      }
    }

    break;
  }

  if (!empty($enchant['slot'])) {
    $enchants[$enchant['slot']][] = $enchant;
  } else {
    debug('Skipping "' . $enchant['base_data']['name_enus'] . '" because of missing slot info');
  }
}

//echo '<pre>' . print_r($enchants, TRUE) . '</pre>';

// data is collected, time to generate a lua file
$output = 'local addonName, ns = ...

ns.enchantIDs = {
';

foreach ($enchants as $slot => $enchant_group) {
  $output .= '  [' . $slot . '] = {' . "\n";
  foreach ($enchant_group as $enchant) {
    // start enchant info
    $output .= '    [' . $enchant['enchant_id'] . '] = { -- ' . $enchant['base_data']['name_enus'] . "\n";

    $output .= '      itemID = ' . $enchant['item_id'] . ',' .  "\n";
    $output .= '      spellID = ' . $enchant['spell_id'] . ',' .  "\n";

    // enchant stats
    $output .= '      stats = {';
    if (!empty($enchant['topfit']['stats'])) {
      foreach ($enchant['topfit']['stats'] as $stat => $value) {
        $output .= '["' . $stat . '"] = ' . $value . ',';
      }
    }
    $output .= '},' . "\n";

    // enchant requirements
    if (!empty($enchant['topfit']['requirements'])) {
      $output .= '      requirements = {';
      foreach ($enchant['topfit']['requirements'] as $stat => $value) {
        $output .= '["' . $stat . '"] = ' . $value . ',';
      }
      $output .= '},' . "\n";
    }

    // end enchant info
    $output .= '    },' . "\n";
  }
  $output .= '  },' . "\n";
}

$output .= '}' . "\n";

file_put_contents(dirname(__FILE__) . '/enchants.lua', $output);

debug('Done!');
