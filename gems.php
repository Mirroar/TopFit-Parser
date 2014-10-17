<?php

//TODO: gem colors
//TODO: socket types

define('GEM_REGEX', '#_\\[(\\d+)\\]=(\\{[^\\}]*\\});#');
define('STATS_REGEX', '#\\$\\.extend\\(g_items\\[GEM_ID\\], (.*)\\);#');

define('DOWNLOAD_LIMIT', 50);

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
  'multistrike' => '', //TODO: find global string
  'versatility' => '', //TODO: find global string
  'health' => '', //TODO: find global string
  'arcres' => '', //TODO: find global string
  'firres' => '', //TODO: find global string
  'frores' => '', //TODO: find global string
  'holres' => '', //TODO: find global string
  'natres' => '', //TODO: find global string
  'shares' => '', //TODO: find global string
  'dmg' => '', //TODO: find global string
);

function debug($text) {
  echo $text . '<br>';
  ob_flush();
}

function cURLcheckBasicFunctions() {
  if(!function_exists("curl_init") && !function_exists("curl_setopt") && !function_exists("curl_exec") && !function_exists("curl_close")) {
    return false;
  }
  return true;
}

/**
 * Returns string status information.
 * Can be changed to int or bool return types.
 */
function cURLdownload($url, $file, $redirects = 30) {
  if(!cURLcheckBasicFunctions())
    return "UNAVAILABLE: cURL Basic Functions";
  $ch = curl_init();
  if($ch) {
    $fp = fopen($file, "w");
    if($fp) {
      if(!curl_setopt($ch, CURLOPT_URL, $url)) {
        fclose($fp); // to match fopen()
        curl_close($ch); // to match curl_init()
        return "FAIL: curl_setopt(CURLOPT_URL)";
      }
      if ((!ini_get('open_basedir') && !ini_get('safe_mode')) || $redirects < 1) {
        curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_REFERER, 'http://domain.com/');
        //if( !curl_setopt($ch, CURLOPT_HEADER, $curlopt_header)) return "FAIL: curl_setopt(CURLOPT_HEADER)";
        if( !curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $redirects > 0)) return "FAIL: curl_setopt(CURLOPT_FOLLOWLOCATION)";
        if( !curl_setopt($ch, CURLOPT_FILE, $fp) ) return "FAIL: curl_setopt(CURLOPT_FILE)";
        if( !curl_setopt($ch, CURLOPT_MAXREDIRS, $redirects) ) return "FAIL: curl_setopt(CURLOPT_MAXREDIRS)";

        return curl_exec($ch);
      } else {
        curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_REFERER, 'http://domain.com/');
        if( !curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false)) return "FAIL: curl_setopt(CURLOPT_FOLLOWLOCATION)";
        if( !curl_setopt($ch, CURLOPT_FILE, $fp) ) return "FAIL: curl_setopt(CURLOPT_FILE)";
        if( !curl_setopt($ch, CURLOPT_HEADER, true)) return "FAIL: curl_setopt(CURLOPT_HEADER)";
        if( !curl_setopt($ch, CURLOPT_RETURNTRANSFER, true)) return "FAIL: curl_setopt(CURLOPT_RETURNTRANSFER)";
        if( !curl_setopt($ch, CURLOPT_FORBID_REUSE, false)) return "FAIL: curl_setopt(CURLOPT_FORBID_REUSE)";
        curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
      }
      // if( !curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true) ) return "FAIL: curl_setopt(CURLOPT_FOLLOWLOCATION)";
      // if( !curl_setopt($ch, CURLOPT_FILE, $fp) ) return "FAIL: curl_setopt(CURLOPT_FILE)";
      // if( !curl_setopt($ch, CURLOPT_HEADER, 0) ) return "FAIL: curl_setopt(CURLOPT_HEADER)";
      if(!curl_exec($ch))
        return "FAIL: curl_exec()";
      curl_close($ch);
      fclose($fp);
      return "SUCCESS: $file [$url]";
    }
    return "FAIL: fopen()";
  }
  return "FAIL: curl_init()";
}

/**
 * Helper function for downloading and caching a page from wowhead
 */
function download_file($url) {
  static $download_count = 0;
  $download_folder = dirname(__FILE__) . '/cache/';
  $filename = $download_folder . preg_replace('#[^a-z0-9\\.]#i', '-', $url);

  if (!file_exists($filename)) {
    if (DOWNLOAD_LIMIT && $download_count++ >= DOWNLOAD_LIMIT) {
      return '';
    }
    cURLdownload($url, $filename);
    debug("downloaded " . $url);
  }

  return file_get_contents($filename);
}

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

  foreach ($stat_matches[1] as $stat_data) {
    $gem['stat_data'] = json_decode($stat_data, TRUE);
    $gem['topfit_stats'] = array();

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

  $gems[] = $gem;
}

/*echo '<pre>';
print_r($gems);
echo '</pre>';//*/

debug('Done!');
