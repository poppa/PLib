<?php
/*
  Run on console: php image.php
*/

require_once '../src/PLib.php';

PLib\import ('image');

$img1 = new PLib\Image ('assets/feet.jpg');

// Copy the image
// Scale it to max width 400 or max height 400
// Crop it around the center to a size of 300x300 pixels
$img2 = $img1->copy ('feet2.jpg')->scale (400, 400)->crop_center (300, 300);

// Now lets manipulate the image outside of the object with some standalone
// PHP image functions.

// Grab the image resource handler from the object
$ih = $img2->resource ();

/*
    Manipulate the image as you see fit
*/

$font  = PLib\combine_path (dirname (__FILE__), 'assets/Ubuntu-B.ttf');
$text  = 'Summertime';
$white = imagecolorallocate ($ih, 255, 255, 255);
$black = imagecolorallocate ($ih, 0, 0, 0);

imagettftext ($ih, 18.0, 0.0, 11, 31, $black, $font, $text);
imagettftext ($ih, 18.0, 0.0, 10, 30, $white, $font, $text);

// Save the manipulations back to the object
$img2 = $img2->save_image_from_resource ($ih);

// And while we're at it, make it grayscale
$img2 = $img2->grayscale ();
?>