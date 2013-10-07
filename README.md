PLib (Poppa PHP Library)
================================================================================

This is a set of classes and functions to make everyday PHP programming a
little bit easier.

Usage
--------------------------------------------------------------------------------

It's simple to use. Just include `PLib.php` and then import the functionality
you want.

  // Include PLib.php
  require_once "path/to/PLib.php";
  
  // Will include image.php
  PLib\import ('image');
  
  // Instantiate a new Image object
  $img = new PLib\Image ('path/to/image.jpg');
  
  // Makes a copy of the image, crops it around the center and turns it into
  // grayscale
  $img->copy ('path/to/image-copy.jpg')->crop_center (300, 300)->grayscale ();
  
There might be some more examples in the [example](tree/master/examples) 
directory.
