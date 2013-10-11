<?php
/*
  Run on console: php image.php
*/

require_once '../src/PLib.php';

PLib\import ('image');

$img_content = file_get_contents ('assets/feet.jpg');

$img = new PLib\StringImage ($img_content);

// Scale it to max width 700 or max height 700
// Crop it around the center to a size of 500x500 pixels
$img->scale (700, 700)->crop_center (500, 500);

// Now lets manipulate the image outside of the object with some standalone
// PHP image functions.

// Grab the image resource handler from the object
$ih = $img->resource ();

/*
    Manipulate the image as you see fit
*/

$font  = PLib\combine_path (dirname (__FILE__), 'assets/Ubuntu-B.ttf');
$text  = 'Summertime';
$white = imagecolorallocate ($ih, 255, 200, 150);
$black = imagecolorallocate ($ih, 0, 0, 0);

imagettftext ($ih, 24.0, 0.0, 21, 51, $black, $font, $text);
imagettftext ($ih, 24.0, 0.0, 20, 50, $white, $font, $text);

// And while we're at it, make it sepia
$img->sepia ();

// And save the changes
$img->save ('string-feet');
?>