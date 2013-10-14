<?php
/*
  Run on console: php file-dir.php
*/

require_once '../src/PLib.php';

PLib\import ('io');

$dir = new PLib\Dir (__DIR__);
$dir->sort ();

while ($file = $dir->emit ()) {
  printf ("%-50s %-5s %s\n", $file, $file->filetype, $file->nicesize);
}

echo "\n";

print_r ($dir->contents)
?>