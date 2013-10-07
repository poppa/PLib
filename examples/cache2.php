<?php
/*
  Run on console: php cache2.php

  This is the exact same script as cache.php except in this script a
  Cache object is used instead of the convenience functions in PLib/cache.php
*/

require_once '../src/PLib.php';

PLib\import ('cache');

$cache = new PLib\Cache ();

// Arbitrary cache key
$key = 'my cache key';

// Cache life time. 20 seconds as an example.
$cache_lifetime = 20;

$data = null;

// See if we have any cached data
if ($data = $cache->get ($key)) {
  // If it's expired set $data to null to regenerate it
  if ($data->is_expired ())
    $data = null;
  else {
    echo "+ Got data from cache!\n\n";
    $data = $data->data;
  }
}

if (!$data) {
  $data = 'This is my data';
  $cache->add ($key, $data, $cache_lifetime);
}

echo "$data\n";
?>