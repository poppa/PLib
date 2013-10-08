<?php
require_once '../src/PLibCli.php';

PLib\import ('image');

use PLib\Option;
use PLib\Options;
use PLib\Argument;
use PLib\OptionsException;

function main ($argc, array $argv)
{
  $width   = null;
  $height  = null;
  $recurse = false;
  $exec    = false;
  $value   = false;
  $path    = null;

  $opts = new Options ('<path>');

  $opts->
    option ('width',  'w',  Option::REQUIRED, Argument::REQUIRED,
           'Defines the width of the shit', $width)->
    option ('height', 'h',  Option::REQUIRED, Argument::REQUIRED,
           'Defines the height of the shit', $height)->
    option ('recurse', 'r', Option::OPTIONAL, Argument::NONE,
           'Do recursive scanning', $recurse)->
    option ('execute', 'x', Option::OPTIONAL, Argument::OPTIONAL,
           'Do exec this shit', $exec)->
    option ('value', 'v',   Option::OPTIONAL, Argument::REQUIRED,
           'Do some value shit', $value);

  try { $opts->parse ($argv); }
  catch (OptionsException $e) {
    echo "\nArgumentument exception: " . $e->getMessage () . "\n\n";
    $opts->usage ();
    return 1;
  }
  catch (Exception $e) {
    echo "Unknown exception: " . $e->getMessage () . "\n";
    return 1;
  }

  if (count ($argv) > 1)
    $path = $argv[1];
  else {
    echo "\nMissing required argument <path>\n\n";
    $opts->usage ();
    return 1;
  }

  echo "\n" .
    "Width: $width, Height: $height, Recurse: $recurse, Exec: $exec, ".
    "Value: $value, Path: $path\n";

  do {
    echo "Continue [Y/n]: ";

    $r = PLib\stdin ()->read ();

    if (PLib\has_prefix (strtolower($r), 'y') || empty ($r))
      break;
    else if (PLib\has_prefix (strtolower ($r), 'n')) {
      echo "Ok, aborting!\n";
      return 0;
    }

  } while (1);

  echo "\nBye bye OK!\n";

  return 0;
}
?>