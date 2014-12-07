<?php

include 'utilities.php';

//TODO: get current version number from http://www.askmrrobot.com/areas/wow/scripts-60/wod.version.js
$index = download_file('http://www.askmrrobot.com/wod/json/strategies/v117');

$data = json_decode($index);

$class_mapping = array(
    'DeathKnight' => array(
        'globalString' => 'DEATHKNIGHT',
        'specs' => array(
            'Blood' => 250,
            'Frost' => 251,
            'Unholy' => 252,
        ),
    ),
    'Druid' => array(
        'globalString' => 'DRUID',
        'specs' => array(
            'Balance' => 102,
            'Feral' => 103,
            'Guardian' => 104,
            'Restoration' => 105,
        ),
    ),
    'Hunter' => array(
        'globalString' => 'HUNTER',
        'specs' => array(
            'BeastMastery' => 253,
            'Marksmanship' => 254,
            'Survival' => 255,
        ),
    ),
    'Mage' => array(
        'globalString' => 'MAGE',
        'specs' => array(
            'Arcane' => 62,
            'Fire' => 63,
            'Frost' => 64,
        ),
    ),
    'Monk' => array(
        'globalString' => 'MONK',
        'specs' => array(
            'Brewmaster' => 268,
            'Mistweaver' => 270,
            'Windwalker' => 269,
        ),
    ),
    'Paladin' => array(
        'globalString' => 'PALADIN',
        'specs' => array(
            'Holy' => 65,
            'Protection' => 66,
            'Retribution' => 70,
        ),
    ),
    'Priest' => array(
        'globalString' => 'PRIEST',
        'specs' => array(
            'Discipline' => 256,
            'Holy' => 257,
            'Shadow' => 258,
        ),
    ),
    'Rogue' => array(
        'globalString' => 'ROGUE',
        'specs' => array(
            'Assassination' => 259,
            'Combat' => 260,
            'Subtlety' => 261,
        ),
    ),
    'Shaman' => array(
        'globalString' => 'SHAMAN',
        'specs' => array(
            'Elemental' => 262,
            'Enhancement' => 263,
            'Restoration' => 264,
        ),
    ),
    'Warlock' => array(
        'globalString' => 'WARLOCK',
        'specs' => array(
            'Affliction' => 265,
            'Demonology' => 266,
            'Destruction' => 267,
        ),
    ),
    'Warrior' => array(
        'globalString' => 'WARRIOR',
        'specs' => array(
            'Arms' => 71,
            'Fury' => 72,
            'Protection' => 73,
        ),
    ),
);

$stat_mapping = array(
    'Agility' => 'ITEM_MOD_AGILITY_SHORT',
    'Intellect' => 'ITEM_MOD_INTELLECT_SHORT',
    'Stamina' => 'ITEM_MOD_STAMINA_SHORT',
    'Strength' => 'ITEM_MOD_STRENGTH_SHORT',
    'Spirit' => 'ITEM_MOD_SPIRIT_SHORT',
    'BonusArmor' => 'ITEM_MOD_EXTRA_ARMOR_SHORT',
    'Armor' => 'RESISTANCE0_NAME',
    'AttackPower' => 'ITEM_MOD_MELEE_ATTACK_POWER_SHORT',
    'Avoidance' => 'ITEM_MOD_CR_AVOIDANCE_SHORT',
    'CriticalStrike' => 'ITEM_MOD_CRIT_RATING_SHORT',
    'Haste' => 'ITEM_MOD_HASTE_RATING_SHORT',
    'Leech' => 'ITEM_MOD_CR_LIFESTEAL_SHORT',
    'Mastery' => 'ITEM_MOD_MASTERY_RATING_SHORT',
    'MovementSpeed' => '',
    'Multistrike' => 'ITEM_MOD_CR_MULTISTRIKE_SHORT',
    'SpellPower' => 'ITEM_MOD_SPELL_POWER_SHORT',
    'Versatility' => 'ITEM_MOD_VERSATILITY',
    'MainHandDps' => 'ITEM_MOD_DAMAGE_PER_SECOND_SHORT',
    'OffHandDps' => 'ITEM_MOD_DAMAGE_PER_SECOND_SHORT', // not used if MainHandDps is also defined
);

$presets = array();
foreach ($data->Data as $specKey => $specPresets) {
    $baseRow = array();

    // find associated class
    foreach ($class_mapping as $classKey => $classInfo) {
        if (strpos($specKey, $classKey) === 0) {
            $baseRow['classKey'] = $classKey;
            $baseRow['specKey'] = substr($specKey, strlen($classKey));

            // find associated specID
            foreach ($classInfo['specs'] as $spec => $specID) {
                if (strpos($baseRow['specKey'], $spec) === 0) {
                    $baseRow['specName'] = $spec;
                    $baseRow['specID'] = $specID;
                    break;
                }
            }

            if (empty($baseRow['specID'])) {
                debug('No applicable spec ID found for ' . $specKey);
            }

            // replace 'Dw' and '2h' with more readable texts
            if (strpos($baseRow['specKey'], 'Dw') == strlen($baseRow['specKey']) - 2) {
                $baseRow['specKey'] = substr($baseRow['specKey'], 0, strlen($baseRow['specKey']) - 2) . ' (Dual-Wield)';
            } elseif (strpos($baseRow['specKey'], '2h') == strlen($baseRow['specKey']) - 2) {
                //$suffix = ' (2-handed)';
                $suffix = '';
                if ($baseRow['specID'] == 72) {
                    $suffix = ' (Titan\'s Grip)';
                }
                $baseRow['specKey'] = substr($baseRow['specKey'], 0, strlen($baseRow['specKey']) - 2) . $suffix;
            }

            if ($baseRow['specKey'] == 'ProtectionGlad') {
                $baseRow['specKey'] = 'Protection: Gladiator';
                $baseRow['specName'] = 'Gladiator';
            }

            break;
        }
    }

    if (empty($baseRow['classKey'])) {
        debug('Could not associate class for specKey '.$specKey);
    } else {
        foreach ($specPresets as $preset) {
            $row = $baseRow;

            $row['name'] = $preset->Name;
            /*if (!empty($preset->Abbreviation)) {
                $row['name'] = $preset->Abbreviation;
            }//*/
            if (count($specPresets) <= 1) {
                $row['name'] = $row['specKey'];
            } else {
                $row['name'] = $row['specKey'] . ': ' . $row['name'];
            }

            $row['name'] = str_replace('PvE: ', '', $row['name']);
            $row['name'] = str_replace(' Build', '', $row['name']);

            $row['description'] = $preset->Description;

            // parse weights
            $highestWeight = 0;
            foreach ($preset->Weights as $key => $value) {
                if (!isset($stat_mapping[$key])) {
                    debug('Unknown stat found: ' . $key);
                } elseif(!empty($stat_mapping[$key])) {
                    // do not use OffHandDps if MainHandDps is also defined
                    if ($key == 'OffHandDps' && !empty($preset->Weights->MainHandDps)) {
                        continue;
                    }
                    $row['weights'][$stat_mapping[$key]] = $value;
                    if ($key != 'MainHandDps' && $value > $highestWeight) {
                        $highestWeight = $value;
                    }
                }
            }

            if ($highestWeight > 0) {
                // normalize weights
                foreach ($row['weights'] as $key => $value) {
                    $row['weights'][$key] = round($value / $highestWeight * 10, 2);
                }
            }

            foreach ($preset->Caps as $key => $value) {
                //TODO: import as actual caps
                // for caps < 15%, assume they are reached, otherwise assume they are not
                if ($value <= 15) {
                    $row['weights'][$stat_mapping[$key]] = $preset->WeightsCapped->{$key};
                }
            }

            $presets[$class_mapping[$row['classKey']]['globalString']][] = $row;
        }
    }
}


$output = 'local addonName, ns = ...

function ns:GetPresets(class)
  class = class or select(2, UnitClass("player"))
  return ns.presets[class]
end

ns.presets = {
';

$defaults = array();
foreach ($presets as $className => $classPresets) {
    $output .= '  ' . $className . ' = {' . "\n";

    foreach ($classPresets as $preset) {
        $output .= '    {' . "\n";
        $output .= '      name = "' . str_replace('"', '\\"', $preset['specName']) . '",' . "\n";
        $output .= '      wizardName = "' . str_replace('"', '\\"', $preset['name']) . '",' . "\n";
        //$output .= '      description = "' . str_replace('"', '\\"', $preset['description']) . '",' . "\n";
        $output .= '      specialization = ' . $preset['specID'] . ',' . "\n";
        if (empty($defaults[$preset['classKey']][$preset['specID']])) {
            $defaults[$preset['classKey']][$preset['specID']] = TRUE;
            $output .= '      default = true,' . "\n";
        }
        $output .= '      weights = {' . "\n";

        foreach ($preset['weights'] as $key => $value) {
            $output .= '        ' . $key . ' = ' . $value . ',' . "\n";
        }

        $output .= '      },' . "\n";

        $output .= '    },' . "\n";
    }

    $output .= '  },' . "\n";
}

$output .= '}' . "\n";

$output .= "
-- add some universal stats to every spec at very low scores for leveling if character is not max level
if UnitLevel('player') < MAX_PLAYER_LEVEL_TABLE[#MAX_PLAYER_LEVEL_TABLE] then
  for class, presets in pairs(ns.presets) do
    for _, preset in pairs(presets) do
      preset.weights.RESISTANCE0_NAME = preset.weights.RESISTANCE0_NAME or 0.001
      preset.weights.ITEM_MOD_STAMINA_SHORT = preset.weights.ITEM_MOD_STAMINA_SHORT or 0.01
      preset.weights.ITEM_MOD_CRIT_RATING_SHORT = preset.weights.ITEM_MOD_CRIT_RATING_SHORT or 0.01
      preset.weights.ITEM_MOD_MASTERY_RATING_SHORT = preset.weights.ITEM_MOD_MASTERY_RATING_SHORT or 0.01
      preset.weights.ITEM_MOD_HASTE_RATING_SHORT = preset.weights.ITEM_MOD_HASTE_RATING_SHORT or 0.01
    end
  end
end
";

file_put_contents(dirname(__FILE__) . '/presets.lua', $output);

debug('Done!');

/*echo '<pre>';
print_r($data);
echo '</pre>';//*/
