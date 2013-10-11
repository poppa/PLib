<?php
/**
 * The image class makes it easy to manipulate images.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 * @todo
 *   When converting a PNG image with alpha channels to a JPEG image
 *   we need to fill the background or else it will look like crap!
 */

namespace PLib;

/**
 * Generic graphics stuff
 */
require_once PLIB_PATH . '/includes/graphics.php';

use PLib\File;

/**
 * The image class makes it easy to manipulate images.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class Image extends Graphics
{
  /**
   * The source file
   * @var File
   */
  protected $src;

  /**
   * Allowed file extensions
   * @var array
   */
  protected $handle = array('jpg', 'jpeg', 'png', 'gif');

  /**
   * Type of image
   * @var string
   */
  protected $type;

  /**
   * Image resource
   * @var resource
   */
  protected $resource;

  /**
   * Image width
   * @var int
   */
  protected $width;

  /**
   * Image height
   * @var int
   */
  protected $height;

  /**
   * Ctor
   *
   * @param string $src
   */
  public function __construct ($src)
  {
    $this->__init ($src);
  }

  protected function __init ($src)
  {
    if ($src instanceof File)
      $this->src = $src;
    else
      $this->src = new File ($src);

    if ($this->src->extension == 'unknown') {
      $t = $this->image_type ();
      $this->src->extension = $t;
    }

    switch ($this->src->extension)
    {
      case 'png':
        if (!HAS_PNG) throw new GraphicsException ('PNG support is missing!');
        $this->type = 'png';

        if (!$this->resource)
          $this->resource = @imagecreatefrompng ($this->src->path);

        break;

      case 'gif':
        if (!HAS_GIF) throw new GraphicsException ('GIF support is missing!');

        $this->type = 'gif';

        if (!$this->resource)
          $this->resource = @imagecreatefromgif ($this->src->path);

        break;

      case 'jpg':
      case 'jpeg':
        if (!HAS_JPG) throw new GraphicsException ('JPEG support is missing!');

        $this->type = 'jpeg';

        if (!$this->resource)
          $this->resource = @imagecreatefromjpeg ($this->src->path);

        break;

      default:
        throw new GraphicsException (
          "Images of type \"{$this->src->extension}\" is not supported!"
        );
    }

    $this->width  = imagesx ($this->resource);
    $this->height = imagesy ($this->resource);

    return $this; 
  }

  /**
   * Convert to given image format
   *
   * @throws GraphicsException
   * @param string $what
   * @param string $filename
   *  If no filename is given the current image will be over writted
   *  by the new one.
   * @return Image
   */
  public function convert_to ($what, $filename=null)
  {
    $what = strtolower ($what);

    if (!in_array ($what, $this->handle))
      throw new GraphicsException ("Images of type '$what' is not supported!");

    if (!$filename) {
      $pi = pathinfo ($this->src->path);
      $filename = $pi['dirname'] . '/' . $pi['filename'] . '.' . $what;
    }

    switch ($what)
    {
      case 'png':
        if ($this->type == 'png')
          return $this->move_to_location ($filename);

        if (!HAS_PNG)
          throw new GraphicsException ('PNG support is missing!');

        @imagepng ($this->resource, $filename, self::$PNG_QUALITY);
        break;

      case 'gif':
        if ($this->type == 'gif')
          return $this->move_to_location ($filename);

        if (!HAS_GIF)
          throw new GraphicsException ('GIF support is missing!');

        @imagegif ($this->resource, $filename);
        break;

      case 'jpg':
      case 'jpeg':
        if ($this->type == 'jpeg')
          return $this->move_to_location ($filename);

        if (!HAS_JPG)
          throw new GraphicsException ('JPEG support is missing!');

        if ($this->type == 'png' && $this->use_alpha_blending ())
          imagealphablending ($this->resource, true);

        @imagejpeg ($this->resource, $filename, self::$JPEG_QUALITY);
        break;
    }

    //return new self ($filename);

    return $this->__init ($filename);
  }

  /**
   * Alias for `Image::convert_to('jpeg')`
   *
   * @param string $filename
   * @return Image
   */
  public function convert_to_jpeg ($filename=null)
  {
    return $this->convert_to ('jpeg', $filename);
  }

  /**
   * Alias for `Image::convert_to('png')`
   *
   * @param string $filename
   * @return Image
   */
  public function convert_to_png ($filename=null)
  {
    return $this->convert_to ('png', $filename);
  }

  /**
   * Alias for `Image::convert_to('gif')`
   *
   * @param string $filename
   * @return Image
   */
  public function convert_to_gif ($filename=null)
  {
    return $this->convert_to ('gif', $filename);
  }

  /**
   * Scale to given dimensions
   *
   * @param int $width
   * @param int $height
   * @param bool $constrain
   * @return Image
   */
  public function scale ($width, $height, $constrain=true)
  {
    if ($width > 0 && $height > 0) {
      $x = $this->width;
      $y = $this->height;

      if ($constrain)
        list ($x, $y) = $this->get_contraints ($width, $height);
      else {
        $x = $width;
        $y = $height;
      }

      if ($y != $this->height || $x != $this->width) {
        // It's an assignment
        if (($tmp = @imagecreatetruecolor ($x, $y)) == false)
          throw new GraphicsException ("Couldn't create a raw copy for scaling");

        //! It's an assignment!
        if ($useAlpha = $this->use_alpha_blending ())
          @imagealphablending ($tmp, false);

        if (!@imagecopyresampled ($tmp, $this->resource, 0, 0, 0, 0, $x, $y,
                                  $this->width, $this->height))
        {
          throw new GraphicsException ("Couldn't resample image!");
        }

        switch ($this->type)
        {
          case 'png':
            if ($useAlpha)
              @imagesavealpha ($tmp, true);
            break;
        }

        @imagedestroy ($this->resource);
        $this->resource = $tmp;
      }

      //return new self ($this->src->path);
      return $this->__init ($this->src->path);
    }

    return $this;
  }

  /**
   * scales the image to the given percentage
   *
   * @param int $percentage
   * @return Image
   */
  public function scale_percentage ($percentage)
  {
    $percentage = $percentage/100;
    $width      = (int)($this->width*$percentage);
    $height     = (int)($this->height*$percentage);
    return $this->scale ($width, $height);
  }

  /**
   * Make a copy of the current image
   *
   * @param string $newName
   *   The filename of the copy
   * @return Image
   */
  public function copy ($new_name)
  {
    if (!@copy ($this->src->path, $new_name)) {
      throw new GraphicsException ("Couldn\'t copy \"{$this->src->path}\" " .
                                   "to \"$new_name\"!");
    }

    return new self ($new_name);
  }

  /**
   * Rename the image.
   * This won't move the file to a new location but just rename the file at
   * the location where it exists.
   *
   * @param string $newname
   * @return Image
   */
  public function rename ($newname)
  {
    $newname = combine_path ($this->src->dir (), basename ($newname));

    if (!@rename ($this->src->path, $newname)) {
      throw new GraphicsException ("Couldn't rename \"{$this->src->path}\" " .
                                   "to \"$newname\"!");
    }

    $this->src = new File ($newname);

    return $this;
  }

  /**
   * Rotates the image in $angle degrees.
   *
   * @since 0.2
   * @link imagerotate
   * @throws GraphicsException
   * @param float $angle
   * @param int $bgcolor
   * @param int $ignoreTransparent
   * @return Image
   */
  public function rotate ($angle, $bgcolor=0x000000, $ignoreTransparent=null)
  {
    if (function_exists ('imagerotate')) {
      imagerotate ($this->resource, $angle, $bgcolor, $ignoreTransparent);
      return $this->__init ($this->src->path);
    }

    throw new GraphicsException ("imagerotate() is not supported in this " .
                                 "compilation of PHP!");
  }

  /**
   * Returns the file system path of the image
   *
   * @return string
   */
  public function path ()
  {
    return $this->src->path;
  }

  /**
   * Returns the filename of the image
   *
   * @return string
   */
  public function filename ()
  {
    return $this->src->name;
  }

  /**
   * Returns the width of the image
   *
   * @return int
   */
  public function width ()
  {
    return $this->width;
  }

  /**
   * Returns the height of the image
   *
   * @return int
   */
  public function height ()
  {
    return $this->height;
  }

  /**
   * Returns the type of the image
   *
   * @return string
   */
  public function type ()
  {
    if (!$this->type)
      $this->image_type ();

    return $this->type;
  }

  /**
   * Detects the image type from the actual image data
   *
   * @since 0.2
   * @throws GraphicsException
   *  If the image type is not supported
   * @return string
   */
  public function image_type ()
  {
    if (!is_null ($this->type))
      return $this->type;

    if (extension_loaded ('exif')) {
      $t = exif_imagetype ($this->src->path);

      switch ($t) {
        case IMAGETYPE_GIF:
          $this->type = 'gif';
          break;

        case IMAGETYPE_JPEG2000:
        case IMAGETYPE_JPEG:
          $this->type = 'jpeg';
          break;

        case IMAGETYPE_PNG:
          $this->type = 'png';
          break;

        default:
          $this->type = null;
      }
    }
    else {
      $info = getimagesize ($this->src->path);
      list (,$this->type) = explode ('/', $info['mime']);
    }

    if (!parent::is_supported ($this->type)) {
      throw new GraphicsException ("Images of type \"{$this->type}\" is not" .
                                   "supported");
    }

    return $this->type;
  }

  /**
   * Returns the image resource handle
   *
   * @since 0.2
   * @todo Return by reference will cause a fatal error in PHP 6
   * @return resource
   */
  public function &resource ()
  {
    return $this->resource;
  }

  /**
   * Converts the image to grayscale
   *
   * @since 0.2
   * @return Image
   */
  public function grayscale ()
  {
    imagefilter ($this->resource, IMG_FILTER_GRAYSCALE);
    return $this->__init ($this->src->path);
  }

  /**
   * Gives the image a sepia tone
   *
   * @param array $rgb
   *  Define your own colors if you like. Default is r:112, g:66, b:20
   *
   * @return Image
   */
  public function sepia (array $rgb=array(112, 66, 20))
  {
    $this->grayscale ();
    imagefilter ($this->resource, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2]);
    return $this->__init ($this->src->path);
  }

  /**
   * Crop the image
   *
   * @since 0.2
   * @param int $x
   *  The x-position in the original image to start from
   * @param int $y
   *  The y-position in the original image to start from
   * @param int $width
   *  The desired width of the new image
   * @param int $height
   *  The desired height of the new image
   * @return image
   */
  public function crop ($x, $y, $width, $height)
  {
    $r = imagecreatetruecolor ($width, $height);
    imagecopyresampled ($r, $this->resource, 0, 0, $x, $y, $width, $height,
                        $width, $height);
    imagedestroy ($this->resource);
    $this->resource = $r;
    return $this->__init ($this->src->path);
  }

  /**
   * Crops around the center of the image
   *
   * @since 0.2
   * @param int $width
   * @param int $height
   * @return Image
   */
  public function crop_center ($width, $height)
  {
    $x = $this->width/2  - $width/2;
    $y = $this->height/2 - $height/2;

    return $this->crop ($x, $y, $width, $height);
  }

  /**
   * Save changes
   */
  public function save ()
  {
    $this->create_image_from_type ($this->resource, $this->src->path);
    return $this->__init ($this->src->path);
  }

  /**
   * Checks if the image given as argument is a PNG image with
   * alpha channels.
   *
   * @param string $path
   * @return bool
   */
  public static function is_alpha_png ($path)
  {
    if (!is_readable ($path))
      return false;

    $fh = @fopen ($path, 'rb');

    if (!is_resource ($fh))
      return false;

    $d = @fread ($fh, 52);
    @fclose ($fh);

    return substr (bin2hex ($d),50,2) == "04" ||
           substr (bin2hex ($d),50,2) == "06";
  }

  /**
   * Prints out information about the object
   *
   * @return string
   */
  public function __toString ()
  {
    return
      get_class ($this) . "(\n" .
      "  Path:   {$this->src->path},\n" .
      "  Width:  {$this->width},\n"     .
      "  Height: {$this->height},\n"    .
      "  Type:   {$this->type}\n"       .
      ")";
  }

  /**
   * Destructor
   */
  public function __destruct ()
  {
    if (is_resource ($this->resource))
      imagedestroy ($this->resource);
  }

  /**
   * Returns constraint proportions from the desired width and height
   *
   * @since 0.2
   * @param int $width
   * @param int $height
   * @return array
   */
  protected function get_contraints ($width, $height)
  {
    if ($width > 0 && $height > 0) {
      $x = $this->width;
      $y = $this->height;

      if ($x > $width) {
        $y = (int)floor ($y * ($width / $x));
        $x = $width;
      }
      if ($y > $height) {
        $x = (int)floor ($x * ($height / $y));
        $y = $height;
      }
    }

    return array($x, $y);
  }

  /**
   * Returns a new image handler with the same size as the current image
   */
  protected function new_image_handler ()
  {
    return imagecreatetruecolor ($this->width, $this->height);
  }

  /**
   * Creates a new image from the current image type
   *
   * @since 0.2
   * @param resource $resource
   */
  protected function create_image_from_type ($resource=null, $path=null)
  {
    if (!$resource)
      $resource = $this->resource;

    if (!$path)
      $path = $this->src->path;

    switch ($this->type) 
    {
      case 'jpeg':
        imagejpeg ($resource, $path, Graphics::$JPEG_QUALITY);
        break;

      case 'png':
        imagepng ($resource, $path, Graphics::$PNG_QUALITY);
        break;

      case 'gif':
        imagegif ($resource, $path);
        break;
    }

    if ($resource != $this->resource)
      $this->resource = $resource;
  }

  /**
   * Should we use alpha blending on the current image
   *
   * @return bool
   */
  protected function use_alpha_blending ()
  {
    if ($this->type == 'png' && self::is_alpha_png ($this->src->path))
      return true;

    return false;
  }

  /**
   * Moves the current file to a new location.
   *
   * @param string $where
   * @return Image
   */
  protected function move_to_location ($where)
  {
    if (!$where)
      return $this;

    if (!@copy ($this->src->path, $where)) {
      throw new GraphicsException ("Couldn't move file to new location " .
                                   "($where)");
    }

    $old = $this->src->path;
    $this->src = new File ($where);
    @unlink ($old);

    return $this;
  }
}

/**
 * Creates an {@see Image} object from a string
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class StringImage extends Image
{
  /**
   * The image data
   * @var string
   */
  protected $data = null;
  /**
   * The filename of the temporary file
   * @var string
   */
  protected $tempnam = null;

  /**
   * Contructor.
   * Tries to create an image resource from $data.
   *
   * @param string $data
   * @throws GraphicsException
   */
  public function __construct ($data)
  {
    $this->data = $data;
    $this->resource = imagecreatefromstring ($data);

    if (!is_resource ($this->resource))
      throw new GraphicsException ("Couldn't create an image from data!");

    $this->width  = imagesx ($this->resource);
    $this->height = imagesy ($this->resource);

    $this->tempnam = tempnam (PLIB_TMP_DIR, 'image-');
    file_put_contents ($this->tempnam, $data);
    $this->src = new File ($this->tempnam);

    unset ($f);
  }

  /**
   * Saves the file to dist.
   *
   * @param string $filename
   *  The full path to the new image. You don't need to add the extension
   *  since that will be added dynamically depending on what type of image
   *  we're dealing with.
   * @param int $mode
   *  Set the permission of the image.
   * @return bool
   */
  public function save ($filename, $mode=0666)
  {
    if (!$filename)
      return parent::save ();

    if (!$this->type)
      $this->image_type ();

    $name = $filename . '.' . $this->type;

    if (@copy ($this->src->path, $name)) {
      @chmod ($name, $mode);

      $this->__init ($name);
      parent::save ();
      return $this;
    }

    return false;
  }

  /**
   * @since 0.2
   * Destructor. Removes the temporary file and calls
   * {@see Image::__destruct()}
   */
  public function __destruct ()
  {
    $this->data = null;
    if ($this->tempnam && file_exists ($this->tempnam))
      unlink ($this->tempnam);

    parent::__destruct ();
  }
}
?>