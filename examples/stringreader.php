<?php
/*
  Run on console: php streamreader.php

  This script removes all unneccessary whitespace from a CSS file.
  This is the same script as streamreader.php except this scripts reads
  the CSS file into memory and manipulates it through the StringReader class
  instead of the StreamReader class.
*/

require_once '../src/PLib.php';

PLib\import ('io');
PLib\import ('string');

$file = new PLib\File ('assets/style.css');
$sr = new PLib\StringReader ($file->get_contents ());
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
        $sr->read ();
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