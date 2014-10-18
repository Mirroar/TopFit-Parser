<?php

define('DOWNLOAD_LIMIT', 50);

/**
 * Print debug output
 */
function debug($text) {
  echo $text . '<br>';
}

/**
 * Returns string status information.
 * Can be changed to int or bool return types.
 */
function cURLdownload($url, $file, $redirects = 30) {
  $ch = curl_init();
  if ($ch) {
    $fp = fopen($file, "w");
    if ($fp) {
      if (!curl_setopt($ch, CURLOPT_URL, $url)) {
        fclose($fp); // to match fopen()
        curl_close($ch); // to match curl_init()
        return;
      }
      if ((!ini_get('open_basedir') && !ini_get('safe_mode')) || $redirects < 1) {
        curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $redirects > 0);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $redirects);
      } else {
        curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
      }
      curl_exec($ch)
      curl_close($ch);
      fclose($fp);
    }
  }
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
