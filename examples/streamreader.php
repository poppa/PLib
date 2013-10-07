<?php
/*
  Run on console: php streamreader.php

  This script removes all unneccessary whitespace from a CSS file
*/

require_once '../src/PLib.php';

PLib\import ('streamreader');

$sr = new PLib\StreamReader ('assets/style.css');
$buf = '';
$char = null;
$delims = str_split (";,:#.{}\n\r\t ");

while (($char = $sr->read ()) !== false) {
  switch ($char) {
    case "\t":
    case "\r":
    case "\n":
    case " ":
      $n = $sr->peek ();
      $p = $sr->look_behind ();

      // If the prevoius or next charachter is a delimiter we can
      // dispose the whitespace char.
      if (in_array($n, $delims) || in_array($p, $delims))
        continue 2;

      break;

    case "/":
      // It's a comment, skip it
      if ($sr->peek () === '*') {
        while (($char = $sr->read ()) !== false) {
          if ($char === '*' && $sr->peek () === '/') {
            $sr->read (); // consume the slash (/) we peeked
            break;
          }
        }

        continue 2; // continue outer loop
      }
      break;

    case '\'':
    case '"':
      // Read upto the next occurance of $char
      $buf .= $char . $sr->read_to_char ($char) . $char;
      continue 2;
  }

  $buf .= $char;
}

echo "\n\n$buf";
?>