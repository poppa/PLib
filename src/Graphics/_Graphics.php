<?php
/**
 * Base class for the Graphics module.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @uses IO
 */

/**
 * Is gif support available?
 */
define('T_GIF', defined('IMG_GIF') ? 1 : 0);
/**
 * Is png support available?
 */
define('T_PNG', defined('IMG_PNG') ? 1 : 0);
/**
 * Is jpeg support available?
 */
define('T_JPG', defined('IMG_JPG') ? 1 : 0);
/**
 * PNG compression was added in PHP 5.1.2
 */
define('T_PNG_QUALITY', version_compare(phpversion(), '5.2.0', '>'));

/**
 * We need the {@see File} class
 */
require_once PLIB_INSTALL_DIR . '/IO/StdIO.php';

/**
 * Master class for graphics
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @uses File
 */
abstract class Graphics
{
	/**
	 * JPEG quality (0-100)
	 * @var int
	 */
	public static $JPEG_QUALITY = 80;
	/**
	 * Compression level. 0 = no compression
	 * @var int
	 */
	public static $PNG_QUALITY = 9;
	/**
	 * Image formats supporting alpha blending
	 * @var array
	 */
	public static $ALPHA_SUPPORT = array('png');

	/**
	 * Hidden constructor. This class can not be instantiated
	 */
	private function __construct() {}

	/**
	 * Validate whether $what is supported or not
	 *
	 * @param string $what
	 * @return bool
	 */
	public static function IsSupported($what)
	{
		switch (strtolower($what))
		{
			case 'png':  return T_PNG;
			case 'gif':  return T_GIF;
			case 'jpg':
			case 'jpeg': return T_JPG;
		}
		return false;
	}

	/**
	 * From PHP.net {@link http://php.net/manual/sv/function.imagettfbbox.php}
	 *
	 * @param array $bbox {@see imagettfbbox()}
	 * @return array
	 */
	public static function ConvertBoundingBox(array $bbox)
	{
    if ($bbox[0] >= -1)
      $xOffset = -abs($bbox[0] + 1);
    else
      $xOffset = abs($bbox[0] + 2);

    $width = abs($bbox[2] - $bbox[0]);

    if ($bbox[0] < -1)
    	$width = abs($bbox[2]) + abs($bbox[0]) - 1;

    $yOffset = abs($bbox[5] + 1);

    if ($bbox[5] >= -1)
    	$yOffset = -$yOffset; // Fixed characters below the baseline.

    $height = abs($bbox[7]) - abs($bbox[1]);

    if ($bbox[3] > 0)
    	$height = abs($bbox[7] - $bbox[1]) - 1;

    return array(
    	'width' => $width,
      'height' => $height,
      //! Using xCoord + xOffset with imagettftext puts the left most pixel of
      //! the text at xCoord.
      'xoffset' => $xOffset,
      //! Using yCoord + yOffset with imagettftext puts the top most pixel of
      //! the text at yCoord.
      'yoffset' => $yOffset,
      'belowBasepoint' => max(0, $bbox[1])
    );
	}

	/**
	 * Converts a hexadecimal colour into RGB values
	 *
	 * @param string $hex
	 * @return array
	 */
	public static function Hex2RGB($hex)
	{
		$hex = str_replace(array('0x','#'), array('',''), $hex);

		switch (strlen($hex))
		{
			case 2: return hexdec($hex);

			case 3:
				list($r,$g,$b) = str_split($hex);
				$r = $r.$r;
				$g = $g.$g;
				$b = $b.$b;
				break;

			case 6:
				list($r,$g,$b) = str_split($hex, 2);
				break;

			default:
				throw new Exception("The hexadecimal number must contain " .
				                    "2, 3 or 6 characters!");
		}

		return array(
			'r' => hexdec($r),
			'g' => hexdec($g),
			'b' => hexdec($b)
		);
	}
}

/**
 * Exception
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @subpackage Exception
 */
class GraphicsException extends Exception
{
	public $message = "Error in image";
}
?>