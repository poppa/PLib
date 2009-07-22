<?php
/**
 * The image class makes it easy to manipulate images.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.3
 * @package Graphics
 * @uses Graphics
 * @uses File
 * @todo
 * 	 When converting a PNG image with alpha channels to a JPEG image
 *   we need to fill the background or else it will look like crap!
 */

/**
 * Generic graphics stuff
 */
require_once '_Graphics.php';

/**
 * The image class makes it easy to manipulate images.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Graphics
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
	public function __construct($src)
	{
		$this->src = new File($src);

		if ($this->src->extension == 'unknown') {
			$t = $this->ImageType();
			$this->src->extension = $t;
		}

		switch ($this->src->extension)
		{
			case 'png':
				if (!T_PNG) throw new GraphicsException('PNG support is missing!');
				$this->type = 'png';
				$this->resource = @imagecreatefrompng($this->src->path);
				break;

			case 'gif':
				if (!T_GIF) throw new GraphicsException('GIF support is missing!');
				$this->type = 'gif';
				$this->resource = @imagecreatefromgif($this->src->path);
				break;

			case 'jpg':
			case 'jpeg':
				if (!T_JPG) throw new GraphicsException('JPEG support is missing!');
				$this->type = 'jpeg';
				$this->resource = @imagecreatefromjpeg($this->src->path);
				break;

			default:
				throw new GraphicsException(
					"Images of type \"{$this->src->extension}\" is not supported!"
				);
		}

		$this->width  = imagesx($this->resource);
		$this->height = imagesy($this->resource);
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
	public function ConvertTo($what, $filename=null)
	{
		$what = strtolower($what);

		if (!in_array($what, $this->handle))
			throw new GraphicsException("Images of type '$what' is not supported!");

		if (!$filename) {
			$pi = pathinfo($this->src->path);
			$filename = $pi['dirname'] . '/' . $pi['filename'] . '.' . $what;
		}

		switch ($what)
		{
			case 'png':
				if ($this->type == 'png')
					return $this->moveToLocation($filename);

				if (!T_PNG)
					throw new GraphicsException('PNG support is missing!');

				@imagepng($this->resource, $filename, self::$PNG_QUALITY);
				break;

			case 'gif':
				if ($this->type == 'gif')
					return $this->moveToLocation($filename);

				if (!T_GIF)
					throw new GraphicsException('GIF support is missing!');

				@imagegif($this->resource, $filename);
				break;

			case 'jpg':
			case 'jpeg':
				if ($this->type == 'jpeg')
					return $this->moveToLocation($filename);

				if (!T_JPG)
					throw new GraphicsException('JPEG support is missing!');

				if ($this->type == 'png' && $this->useAlphaBlending())
					imagealphablending($this->resource, true);

				@imagejpeg($this->resource, $filename, self::$JPEG_QUALITY);
				break;
		}

		return new self($filename);
	}

	/**
	 * Alias for `Image::ConvertTo('jpeg')`
	 *
	 * @param string $filename
	 * @return Image
	 */
	public function ConvertToJPEG($filename=null)
	{
		return $this->ConvertTo('jpeg', $filename);
	}

	/**
	 * Alias for `Image::ConvertTo('png')`
	 *
	 * @param string $filename
	 * @return Image
	 */
	public function ConvertToPNG($filename=null)
	{
		return $this->ConvertTo('png', $filename);
	}

	/**
	 * Alias for `Image::ConvertTo('gif')`
	 *
	 * @param string $filename
	 * @return Image
	 */
	public function ConvertToGIF($filename=null)
	{
		return $this->ConvertTo('gif', $filename);
	}

	/**
	 * Scale to given dimensions
	 *
	 * @param int $width
	 * @param int $height
	 * @param bool $constrain
	 * @return Image
	 */
	public function Scale($width, $height, $constrain=true)
	{
		if ($width > 0 && $height > 0) {
			$x = $this->width;
			$y = $this->height;

			if ($constrain)
				list($x, $y) = $this->getContraints($width, $height);
			else {
				$x = $width;
				$y = $height;
			}

			if ($y != $this->height || $x != $this->width) {
        // It's an assignment
				if (($tmp = @imagecreatetruecolor($x, $y)) == false)
					throw new GraphicsException("Couldn't create a raw copy for scaling");

				//! It's an assignment!
				if ($useAlpha = $this->useAlphaBlending())
					@imagealphablending($tmp, false);

				if (!@imagecopyresampled($tmp, $this->resource, 0, 0, 0, 0, $x, $y,
				                         $this->width, $this->height))
				{
					throw new GraphicsException("Couldn't resample image!");
				}

				switch ($this->type)
				{
					case 'png':
						if ($useAlpha)
							@imagesavealpha($tmp, true);

						@imagepng($tmp, $this->src->path, self::$PNG_QUALITY);
						break;

					case 'gif':
						@imagegif($tmp, $this->src->path);
						break;

					case 'jpg':
					case 'jpeg':
						@imagejpeg($tmp, $this->src->path, self::$JPEG_QUALITY);
						break;

					default:
						throw new GraphicsException("Bad image type: {$this->type}!");
				}

				@imagedestroy($tmp);
			}
			return new self($this->src->path);
		}
		return $this;
	}

	/**
	 * Scales the image to the given percentage
	 *
	 * @param int $percentage
	 * @return Image
	 */
	public function ScalePercentage($percentage)
	{
		$percentage = $percentage/100;
		$width      = (int)($this->width*$percentage);
		$height     = (int)($this->height*$percentage);
		return $this->Scale($width, $height);
	}

	/**
	 * Make a copy of the current image
	 *
	 * @param string $newName
	 *   The filename of the copy
	 * @return Image
	 */
	public function Copy($newName)
	{
		if (!@copy($this->src->path, $newName))
			throw new GraphicsException("Couldn\'t copy \"{$this->src->path}\" " .
			                            "to \"$newName\"!");
		return new self($newName);
	}

	/**
	 * Rename the image.
	 * This won't move the file to a new location but just rename the file at
	 * the location where it exists.
	 *
	 * @param string $newname
	 * @return Image
	 */
	public function Rename($newname)
	{
		$newname = $this->src->Dir() . basename($newname);
		if (!@rename($this->src->path, $newname))
			throw new GraphicsException("Couldn't rename \"{$this->src->path}\" " .
			                            "to \"$newname\"!");

		$this->src->path = $newname;
		$this->src->name = basename($newname);
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
	public function Rotate($angle, $bgcolor=0x000000, $ignoreTransparent=null)
	{
		if (function_exists('imagerotate')) {
			imagerotate($this->resource, $angle, $bgcolor, $ignoreTransparent);
			return $this;
		}

		throw new GraphicsException("imagerotate() is not supported in this " .
		                            "compilation of PHP!");
	}

	/**
	 * Returns the File object of the image
	 *
	 * @since 0.3
	 * @return File
	 */
	public function File()
	{
		return $this->src;
	}

	/**
	 * Returns the file system path of the image
	 *
	 * @return string
	 */
	public function Path()
	{
		return $this->src->path;
	}

	/**
	 * Returns the filename of the image
	 *
	 * @return string
	 */
	public function FileName()
	{
		return $this->src->name;
	}

	/**
	 * Returns the filesize of the image in bytes
	 *
	 * @return int
	 */
	public function Size()
	{
		return $this->src->size;
	}

	/**
	 * Returns the filesize human readable like 43Kb
	 *
	 * @param int $decimals
	 * @return string
	 */
	public function NiceSize($decimals=1)
	{
		return $this->src->NiceSize($decimals);
	}

	/**
	 * Returns the width of the image
	 *
	 * @return int
	 */
	public function Width()
	{
		return $this->width;
	}

	/**
	 * Returns the height of the image
	 *
	 * @return int
	 */
	public function Height()
	{
		return $this->height;
	}

	/**
	 * Returns the type of the image
	 *
	 * @return string
	 */
	public function Type()
	{
		if (!$this->type)
			$this->ImageType();

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
	public function ImageType()
	{
		if (!is_null($this->type))
			return $this->type;

		if (extension_loaded('exif')) {
			$t = exif_imagetype($this->src->path);

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
			$info = getimagesize($this->src->path);
			list(,$this->type) = explode('/', $info['mime']);
		}

		if (!parent::IsSupported($this->type))
			throw new GraphicsException("Images of type \"{$this->type}\" is not" .
			                            "supported");

		return $this->type;
	}

	/**
	 * Returns the image resource handle
	 *
	 * @since 0.2
	 * @todo Return by reference will cause a fatal error in PHP 6
	 * @return resource
	 */
	public function &Resource()
	{
		return $this->resource;
	}

	/**
	 * Converts the image to grayscale
	 *
	 * @since 0.2
	 * @return Image
	 */
	public function Grayscale()
	{
		$r = clone $this;
		$r = $r->Resource();
		imagecopymergegray($r, $this->resource, 0, 0, 0, 0, $this->width,
		                   $this->height, 0);

		$this->createImageFromType($r);
		return new Image($this->src->path);
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
	public function Crop($x, $y, $width, $height)
	{
		$r = imagecreatetruecolor($width, $height);
		imagecopyresampled($r, $this->resource, 0, 0, $x, $y, $width, $height,
		                   $width, $height);

		$this->createImageFromType($r);
		imagedestroy($r);
		unset($r);
		return new Image($this->src->path);
	}

	/**
	 * Crops around the center of the image
	 *
	 * @since 0.2
	 * @param int $width
	 * @param int $height
	 * @return Image
	 */
	public function CropCenter($width, $height)
	{
		$x = $this->width/2  - $width/2;
		$y = $this->height/2 - $height/2;

		return $this->Crop($x, $y, $width, $height);
	}

	/**
	 * Saves a new image of $resource from the current image type.
	 *
	 * @param resource $resource
	 */
	public function SaveImageFromResource($resource)
	{
		$this->createImageFromType($resource);
	}

	/**
	 * Checks if the image given as argument is a PNG image with
	 * alpha channels.
	 *
	 * @param string $path
	 * @return bool
	 */
	public static function AlphaPNG($path)
	{
		if (!is_readable($path))
			return false;

		$fh = @fopen($path, 'rb');
		if (!is_resource($fh))
			return false;

		$d = @fread($fh, 52);
		@fclose($fh);

		return substr(bin2hex($d),50,2) == "04" || substr(bin2hex($d),50,2) == "06";
	}

	/**
	 * Prints out information about the object
	 *
	 * @return string
	 */
	public function __toString()
	{
		return
		get_class($this) . "(\n" .
		"  Path:   {$this->src->path},\n" .
		"  Width:  {$this->width},\n"     .
		"  Height: {$this->height},\n"    .
		"  Type:   {$this->type}\n"       .
		")";
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		if (is_resource($this->resource))
			imagedestroy($this->resource);
	}

	/**
	 * Returns constraint proportions from the desired width and height
	 *
	 * @since 0.2
	 * @param int $width
	 * @param int $height
	 * @return array
	 */
	protected function getContraints($width, $height)
	{
		if ($width > 0 && $height > 0) {
			$x = $this->width;
			$y = $this->height;

			if ($x > $width) {
				$y = (int)floor($y * ($width / $x));
				$x = $width;
			}
			if ($y > $height) {
				$x = (int)floor($x * ($height / $y));
				$y = $height;
			}
		}

		return array($x, $y);
	}

	/**
	 * Creates a new image from the current image type
	 *
	 * @since 0.2
	 * @param resource $resource
	 */
	protected function createImageFromType($resource)
	{
		switch ($this->type) {
			case 'jpeg':
				imagejpeg($resource, $this->src->path, Graphics::$JPEG_QUALITY);
				break;

			case 'png':
				imagepng($resource, $this->src->path, Graphics::$PNG_QUALITY);
				break;

			case 'gif':
				imagegif($resource, $this->src->path);
				break;
		}
	}

	/**
	 * Should we use alpha blending on the current image
	 *
	 * @return bool
	 */
	protected function useAlphaBlending()
	{
		if ($this->type == 'png' && self::AlphaPNG($this->src->path))
			return true;

		return false;
	}

	/**
	 * Moves the current file to a new location.
	 *
	 * @param string $where
	 * @return Image
	 */
	protected function moveToLocation($where)
	{
		if (!$where)
			return $this;

		if (!@copy($this->src->path, $where)) {
			throw new GraphicsException("Couldn't move file to new location " .
			                            "($where)");
		}

		$old = $this->src->path;
		$this->src = new File($where);
		@unlink($old);

		return $this;
	}
}

/**
 * Creates an {@see Image} object from a string
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Graphics
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
	public function __construct($data)
	{
		$this->data = $data;
		$this->resource = imagecreatefromstring($data);
		if (!is_resource($this->resource))
			throw new GraphicsException("Couldn't create an image from data!");

		$this->width  = imagesx($this->resource);
		$this->height = imagesy($this->resource);

		$this->tempnam = tempnam(PLIB_TMP_DIR, 'image-');
		file_put_contents($this->tempnam, $data);
		$this->src = new File($this->tempnam);

		unset($f);
	}

	/**
	 * Saves the file to dist.
	 *
	 * @since 0.2
	 * @param string $filename
	 *  The full path to the new image. You don't need to add the extension
	 *  since that will be added dynamically depending on what type of image
	 *  we're dealing with.
	 * @param int $mode
	 *  Set the permission of the image.
	 * @return bool
	 */
	public function SaveToDisk($filename, $mode=0666)
	{
		if (!$this->type)
			$this->ImageType();

		$name = $filename . '.' . $this->type;
		if (@copy($this->src->path, $name)) {
			@chmod($name, $mode);
			return true;
		}

		return false;
	}

	/**
	 * @since 0.2
	 * Destructor. Removes the temporary file and calls
	 * {@see Image::__desctruct()}
	 */
	public function __destruct()
	{
		$this->data = null;
		if ($this->tempnam && file_exists($this->tempnam))
			unlink($this->tempnam);

		parent::__destruct();
	}
}
?>