<?php
//TODO: see if info about level-adjusted gem stats can be parsed
//TODO: add more requirements to output for when they can actually be used by topfit

include 'utilities.php';

define('ENCHANT_REGEX', '#_\\[(\\d+)\\]=(\\{[^\\}]*\\});#');

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

  $enchants[] = $enchant;
}

echo '<pre>' . print_r($enchants, TRUE) . '</pre>';

debug('Done!');
