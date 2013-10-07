<?php
/*
  Run on console: php cache.php
*/

require_once '../src/PLib.php';

PLib\import ('cache');

// Arbitrary cache key
$key = 'my cache key';

// Cache life time. 20 seconds as an example.
$cache_lifetime = 20;

$data = null;

// See if we have any cached data
if ($data = PLib\cache_get ($key)) {
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
  PLib\cache_add ($key, $data, $cache_lifetime);
}

echo "$data\n";
?>