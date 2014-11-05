<?php
//TODO: add more requirements to output for when they can actually be used by topfit

include 'utilities.php';

define('LIST_SECTION_REGEX', '#var _ = {};([\s\S]*?)\\$\\.extend\\(true, ([^ ]*), _\\);#m');
define('ENCHANT_REGEX', '#_\\[(\\d+)\\]=(\\{[^\\}]*\\});#');
define('ENCHANT_SPELL_REGEX', '#\\(\\$WH\\.g_enhanceTooltip\\.bind\\(tt\\)\\)\\(ITEM_ID,[^\\[\\)]*\\[(\\d+)\\][^\\)]*\\)#');
define('ENCHANT_BASE_REGEX', '#_\\[SPELL_ID\\]=(\\{[^\\}]*\\});#');
define('ENCHANT_TOOLTIP_REGEX', '#_\\[SPELL_ID\\]\\.tooltip_enus = \'(.*)\';#');
define('ENCHANT_ID_REGEX', '#<td[^>]*>Enchant Item\\: [^\\(]*\\((\\d+)\\)#i');

define('TOOLTIP_MAX_LEVEL_REGEX', '#Cannot be applied to items higher than level (\\d+)#i');
define('TOOLTIP_SIMPLE_EFFECT_REGEX1', '#Permanently enchants? (?<target>.*?) [ts]o (?<stats>.*)\\.#i');
define('TOOLTIP_SIMPLE_EFFECT_REGEX2', '#Permanently attache?s? .*? onto (?<target>.*?) [ts]o (?<stats>.*)\\.#i');
define('TOOLTIP_SIMPLE_EFFECT_REGEX3', '#Permanently embroiders? .*? into (?<target>.*?), (?<stats>.*)\\.#i');
define('TOOLTIP_ALT_EFFECT_REGEX', '#Permanently adds? (?<stats>.*?) to (?<target>.*?)\\.#i');
define('TOOLTIP_TIMED_STAT_REGEX', '#(?:sometimes)? increas(?:e|ing) (.*?) by (\\d+) for (\\d+) sec#i');
define('TOOLTIP_SIMPLE_STAT_REGEX', '#increas(?:e|ing) (?<stat1>.*?) by (?<amount1>\\d+)(?: and (?<stat2>.*?) by (?<amount2>\\d+))?#i');
define('TOOLTIP_SIMPLER_STAT_REGEX', '#(?<amount1>\\d+) (?<stat1>.*?)(?: and (?<amount2>\\d+) (?<stat2>.*?))?$#i');

// map targets from tooltip to item locations using slot numbers from WoW
$slot_mapping = array(
  'a neck' => 2,
  'shoulder armor' => 3,
  'a shoulder slot item' => 3,
  'a chest' => 5,
  'chest armor' => 5,
  'pants' => 7,
  'a leg slot item' => 7,
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

// map stat names as used in tooltips to stat global strings
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
  'Dodge' => 'ITEM_MOD_DODGE_RATING_SHORT',
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
  // currently not used by TopFit
  'health' => array(),
  'mana' => array(),
  'movement speed' => array(),
  'mount speed' => array(),
  'Mining skill' => array(),
  'Fishing skill' => array(),
  'Herbalism skill' => array(),
  'Skinning skill' => array(),
  'Herbalism, Mining, and Skinning skills' => array(),
  'hit' => array(),
  'additional points of damage. ' => array(),
);

$enchants = array();
$unknown_enchants = array();

function filter_index_section($index, $section) {
  $output = '';
  $index_matches = array();
  preg_match_all(LIST_SECTION_REGEX, $index, $index_matches, PREG_SET_ORDER);
  foreach ($index_matches as $match) {
    if ($match[2] == $section) {
      $output .= $match[1];
    }
  }

  return $output;
}

function start_parsing($url, $spells_only = FALSE) {
  global $slot_mapping, $stat_mapping, $enchants, $unknown_enchants;
  // load www.wowhead.com/items=0.6 and go from there
  $index = download_file($url);

  if ($spells_only) {
    $index = filter_index_section($index, 'g_spells');
  } else {
    $index = filter_index_section($index, 'g_items');
  }

  $enchant_matches = array();
  preg_match_all(ENCHANT_REGEX, $index, $enchant_matches, PREG_SET_ORDER);
  debug('found ' .count($enchant_matches) . ' potential enchants...');

  foreach ($enchant_matches as $match) {
    $enchant = array();
    if ($spells_only) {
      $enchant['spell_id'] = $match[1];
    } else {
      $enchant['item_id'] = $match[1];

      $enchant_url = 'http://www.wowhead.com/item=' . $enchant['item_id'];
      $enchant_file = download_file($enchant_url);
      if (empty($enchant_file))
        break;

      // find the corresponding spell that applies the item's effect
      $spell_matches = array();
      preg_match_all(str_replace('ITEM_ID', $enchant['item_id'], ENCHANT_SPELL_REGEX), $enchant_file, $spell_matches);
      foreach ($spell_matches[1] as $spell_id) {
        $enchant['spell_id'] = $spell_id;
        break;
      }
    }
    $enchant['base_data'] = json_decode($match[2], TRUE);

    if (empty($enchant['base_data']['name_enus'])) continue;
    if (empty($enchant['spell_id'])) continue;

    $spell_url = 'http://www.wowhead.com/spell=' . $enchant['spell_id'];
    $spell_file = download_file($spell_url);

    if (empty($spell_file))
      break;

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

    $parse_successful = FALSE;
    if (!empty($enchant['enchant_id'])) {
      // now begins the fun part: parsing the tooltip to get information about the enchant's effects

      $effect_matches = array();
      $tt = $enchant['tooltip'];

      // clean tooltip from unnecessary info
      $tt = strip_tags($tt);

      preg_match_all(TOOLTIP_MAX_LEVEL_REGEX, $tt, $effect_matches, PREG_SET_ORDER);
      foreach ($effect_matches as $effect) {
        $enchant['topfit']['requirements']['max_ilevel'] = $effect[1];
        $tt = str_replace($effect[0], '', $tt);
      }

      foreach(array(TOOLTIP_SIMPLE_EFFECT_REGEX1, TOOLTIP_SIMPLE_EFFECT_REGEX2, TOOLTIP_SIMPLE_EFFECT_REGEX3, TOOLTIP_ALT_EFFECT_REGEX) as $regex) {
        preg_match_all($regex, $tt, $effect_matches, PREG_SET_ORDER);
        foreach ($effect_matches as $effect) {
          if (isset($slot_mapping[$effect['target']])) {
            $enchant['slot'] = $slot_mapping[$effect['target']];
          } else {
            debug('Unknown Effect Target: ' . $effect['target']);
          }
          //$parse_successful = TRUE;
          //debug('Effect: ' . $effect['stats']);
          $stat_matches = array();
          preg_match_all(TOOLTIP_TIMED_STAT_REGEX, $effect['stats'], $stat_matches, PREG_SET_ORDER);
          foreach ($stat_matches as $stat) {
            if (isset($stat_mapping[$stat[1]])) {
              $stats = $stat_mapping[$stat[1]];
              if (!is_array($stats)) {
                $stats = array($stats);
              }
              foreach ($stats as $single_stat) {
                $enchant['topfit']['stats'][$single_stat] = $stat[2] * $stat[3] / 90; // assumes 90 second internal cooldown for most enchants
                //TODO: Actually, especially noting down http://us.battle.net/wow/en/forum/topic/13087818929?page=23#442 this varies a lot per enchant and should probably be tagged for reviewing
                //debug('Timed enchant: ' . $stat[0] . ' (' . $enchant['base_data']['name_enus'] . ')');
              }
              $parse_successful = TRUE;

              $effect['stats'] = str_replace($stat[0], '', $effect['stats']);
            } else {
              debug('Unknown stat (timed): ' . $stat[1]);
            }
          }

          foreach (array(TOOLTIP_SIMPLE_STAT_REGEX, TOOLTIP_SIMPLER_STAT_REGEX) as $stat_regex) {
            $stat_matches = array();
            preg_match_all($stat_regex, $effect['stats'], $stat_matches, PREG_SET_ORDER);
            foreach ($stat_matches as $stat) {
              if (isset($stat_mapping[$stat['stat1']])) {
                $stats = $stat_mapping[$stat['stat1']];
                if (!is_array($stats)) {
                  $stats = array($stats);
                }
                foreach ($stats as $single_stat) {
                  $enchant['topfit']['stats'][$single_stat] = $stat['amount1'];
                }
                $parse_successful = TRUE;

                if (!empty($stat['stat2']) && isset($stat_mapping[$stat['stat2']])) {
                  $stats = $stat_mapping[$stat['stat2']];
                  if (!is_array($stats)) {
                    $stats = array($stats);
                  }
                  foreach ($stats as $single_stat) {
                    $enchant['topfit']['stats'][$single_stat] = $stat['amount2'];
                  }
                }

                $effect['stats'] = str_replace($stat[0], '', $effect['stats']);
              } else {
                debug('Unknown stat: ' . $stat['stat1']);
              }
            }
          }
        }
      }
    }
    if (!$parse_successful) {
      debug('Could not parse enchant "' . $enchant['base_data']['name_enus'] . '"');
      $enchant['parse_failed'] = TRUE;
    }

    if (!empty($enchant['slot'])) {
      $enchants[$enchant['slot']][] = $enchant;
    } else {
      debug('Skipping "' . $enchant['base_data']['name_enus'] . '" because of missing slot info.');
      $unknown_enchants[] = $enchant;
    }
  }
}

start_parsing('http://www.wowhead.com/items=0.6');
start_parsing('http://www.wowhead.com/skill=773', TRUE);

//echo '<pre>' . print_r($enchants, TRUE) . '</pre>';

// data is collected, time to generate a lua file
$enchants['"UNKNOWN"'] = $unknown_enchants;

$output = 'local addonName, ns = ...

ns.enchantIDs = {
';

foreach ($enchants as $slot => $enchant_group) {
  $output .= '  [' . $slot . '] = {' . "\n";
  foreach ($enchant_group as $enchant) {
    if (empty($enchant['enchant_id'])) continue;
    // start enchant info
    $output .= '    [' . $enchant['enchant_id'] . '] = { -- ' . $enchant['base_data']['name_enus'] . "\n";

    if (!empty($enchant['item_id'])) {
      $output .= '      itemID = ' . $enchant['item_id'] . ',' .  "\n";
    }
    $output .= '      spellID = ' . $enchant['spell_id'] . ',' .  "\n";

    if (!empty($enchant['parse_failed'])) {
      $output .= '      couldNotParse = true,' .  "\n";
    }

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
