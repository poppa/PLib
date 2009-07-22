<?php
/**
 * Graphics text
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.2
 * @package Graphics
 * @uses Graphics
 * @depends {@link http://php.net/gd The PHP Image module}
 */

/**
 * Generic graphics stuff
*/
require_once '_Graphics.php';

/**
 * Class for generating text images
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 */
class GText extends Graphics
{
	/**
	 * Default background color
	 * @var string
	 */
	protected $bgcolor  = 'fff';
	/**
	 * Default text color
	 * @var string
	 */
	protected $fgcolor  = '000';
	/**
	 * Default font size
	 *
	 * @var int
	 */
	protected $fontsize = 14;
	/**
	 * Use transparent background.
	 * 0 means no transparency, 127 means full transparency
	 * @var int
	 */
	protected $alpha = 0;
	/**
	 * Image format
	 * @var string
	 */
	protected $format = 'png';
	/**
	 * If greater than 0 the text will be wrapped at column $wordwrap
	 * @var int
	 */
	protected $wordwrap = 0;
	/**
	 * The directory in which to save the image
	 * @var string
	 */
	protected $imagedir = '.';
	/**
	 * The font
	 * @var File
	 */
	protected $font;
	/**
	 * The path to the font
	 * @var string
	 */
	protected $fontpath;
	/**
	 * Publically settable members
	 * @var array
	 */
	protected $settable = array(
		'bgcolor',
		'fgcolor',
		'fontsize',
		'alpha',
		'format',
		'wordwrap',
		'imagedir',
		'font'
	);
	/**
	 * The generated file name
	 * @var string
	 */
	protected $filename = null;
	/**
	 * Number of generated images
	 * @var int
	 */
	protected static $instances = 0;

	/**
	 * Constructor
	 *
	 * @param string $font
	 * @param string $imagedir
	 *   Where to save the generated images
	 */
	public function __construct($font, $imagedir='.')
	{
		$this->font     = new File($font);
		$this->fontpath = $font;
		$this->imagedir = rtrim($imagedir, '/');
	}

	/**
	 * Create the text image
	 *
	 * @param string $text
	 * @param bool $output
	 *  if set to true the file will not be saved but rather just written
	 *  to the output buffer.
	 * @return array
	 */
	public function Render($text, $output=false)
	{
		$text  = ($text);
		$alpha = $this->alpha;

		if ($alpha > 0 && in_array($this->format, Graphics::$ALPHA_SUPPORT)) {
			if ($this->format == 'gif')
				$alpha = 127;
		}

		if ($this->wordwrap)
			$text = wordwrap($text, $this->wordwrap);

		$fontsize = (float)$this->fontsize;
		$fontpath = $this->fontpath;
		$bc       = self::Hex2RGB($this->bgcolor);
		$fc       = self::Hex2RGB($this->fgcolor);
		$size     = imagettfbbox($fontsize, 0, $fontpath, $text);
		$size     = self::ConvertBoundingBox($size);
		$xoffset  = $size['xoffset'];
		$yoffset  = $size['yoffset'];

		$img = imagecreatetruecolor($size['width'], $size['height']);

		if ($alpha)
			imagesavealpha($img, true);

		if (!$img)
			throw new GraphicsException("Couldn't create image!");

		if ($alpha)
			$bc = imagecolorallocatealpha($img, $bc['r'], $bc['g'], $bc['b'], $alpha);
		else
			$bc = imagecolorallocate($img, $bc['r'], $bc['g'], $bc['b']);

		$fc = imagecolorallocate($img, $fc['r'], $fc['g'], $fc['b']);

		imagefill($img, 0, 0, $bc);
		imagettftext($img, $fontsize, 0, $xoffset, $yoffset, $fc, $fontpath, $text);

		$filename = null;
		$hash     = null;

		if (!$output) {
			$hash     = $this->ToMD5(utf8_decode($text));
			$filename = $this->getFilePath(utf8_decode($text));
		}
		else
			header('Content-Type: ' . $this->MimeType());

		switch ($this->format) {
			case 'png':
				imagepng($img, $filename, Graphics::$PNG_QUALITY);
				break;

			case 'gif':
				imagegif($img, $filename);
				break;

			case 'jpg':
			case 'jpeg':
				imagejpeg($img, $filename, Graphics::$JPEG_QUALITY);
				break;
		}

		imagedestroy($img);

		$this->filename = null;
		self::$instances++;

		return array(
			'path'   => $filename,
			'width'  => $size['width'],
			'height' => $size['height'],
			'hash'   => $hash ? $hash : $this->ToMD5(utf8_decode($text))
		);
	}

	/**
	 * Returns the mimetype for the current image
	 *
	 * @return string
	 */
	public function MimeType()
	{
		return "image/$this->format";
	}

	/**
	 * Generates a unique filename
	 *
	 * @return string
	 */
	protected function generateFileName($text)
	{
		return 'gtext-' . $this->ToMD5($text);
	}

	/**
	 * Returns the full path of the image
	 *
	 * @return string
	 */
	protected function getFilePath($text)
	{
		$fn = $this->generateFileName($text);
		return $this->imagedir . '/' . $fn . '.' . $this->format;
	}

	/**
	 * Returns a md5 hash of the object. All members will be used as salt.
	 * Useful if cacheing the images in a dynamic environment where the hash
	 * can be used to invalidate the cache.
	 *
	 * @param string $text
	 * @return string
	 */
	public function ToMD5($text)
	{
		return md5(
			$this->fgcolor  .
			$this->fontpath .
			$this->fontsize .
			$this->format   .
			$this->bgcolor  .
			$this->alpha    .
			$this->wordwrap .
			$text
		);
	}

	/**
	 * Setter.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	public function __set($key, $value)
	{
		if (!in_array($key, $this->settable))
			return false;

		switch ($key) {
			case 'font':
				if (!$value instanceof File)
					$value = new File($value);

				$this->font = $value;
				$this->fontpath = $this->font->path;
				break;

			case 'alpha':
				if ($value < 0 || $value > 127)
					throw new GraphicsException('Alpha needs to be between 0 and 127!');

				$this->alpha = $value;
				break;

			case 'format':
				if (!self::IsSupported($value))
					throw new GraphicsException("Images of type $value is not supported");

				$this->format = strtolower($value);
				break;

			default:
				$this->{$key} = $value;
		}
		return true;
	}
}

/**
 * Implements cacheable text images
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @depends Cache
 */
class GTextCacheable extends GText
{
	/**
	 * Is the cache class loaded or not?
	 * @var int
	 */
	protected static $cacheloaded = 0;

	/**
	 * Constructor
	 *
	 * @see GText::__construct()
	 * @param string $font
	 * @param string $imgdir
	 */
	public function __construct($font, $imgdir='.')
	{
		parent::__construct($font, $imgdir);

		if (!self::$cacheloaded && !class_exists('Cache')) {
			PLib::Import('Cache.Cache');
			self::$cacheloaded = 1;
		}
	}

	/**
	 * Render the graphics text
	 *
	 * @param string $text
	 * @return array
	 */
	public function Render($text, $output=false)
	{
		$cachekey = $this->generateFileName($text);
		$md5 = $this->ToMD5($text);

    // It's an assignment
		if (false === ($data = Cache::Get($cachekey, $md5))) {
			$data = parent::Render($text);
			Cache::Add($cachekey, serialize($data), Date::UnixYear(), $md5);
		}
		else {
			if (!file_exists($this->getFilePath($text))) {
				$data = parent::Render($text);
				Cache::Add($cachekey, serialize($data), Date::UnixYear(), $md5);
			}
			else {
				$data = unserialize($data);
				self::$instances++;
			}
		}

		if ($output) {
			header('Content-Type: ' . $this->MimeType());
			echo file_get_contents($data['path']);
			die;
		}
		return $data;
	}
}

/**
 * Like {@see GText} and {@see GTextCacheable} except this class utilize
 * a special database table.
 *
 * __The table structure__
 * <code>
 * # MySQL
 * CREATE TABLE  `dbname`.`gtext` (
 *   `ckey` varchar(255) default NULL,
 *   `created` datetime default NULL,
 *   `hash` varchar(255) default NULL,
 *   `width` int(11) default NULL,
 *   `height` int(11) default NULL,
 *   `remove_callback` varchar(255) default NULL,
 *   `id` int(11) NOT NULL auto_increment,
 *   `type` varchar(100) default NULL,
 *   `data` longblob,
 *   PRIMARY KEY  (`id`)
 * ) ENGINE=MyISAM
 *
 * #SQLite
 * CREATE TABLE `gtext` (
 *   `ckey`            VARCHAR(255),
 *   `created`         DATE,
 *   `hash`            VARCHAR(255),
 *   `width`           INT(11),
 *   `height`          INT(11),
 *   `remove_callback` VARCHAR(255),
 *   `type`            VARCHAR(100),
 *   `data`            LONGBLOB
 * )
 * </code>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @example GTextDB.xmpl
 * @depends DB
 * @depends Mysql
 */
class GTextDB extends GText
{
	/**
	 * DB connection object
   * 
	 * @var Mysql|SQLite
	 */
	protected static $db;
	/**
	 * Name of the database table
	 * @var string
	 */
	protected static $table = 'gtext';

  protected static $sqlite_table =
  "CREATE TABLE %s (
    ckey            VARCHAR(255),
    created         DATE,
    hash            VARCHAR(255),
    width           INT(11),
    height          INT(11),
    remove_callback VARCHAR(255),
    type            VARCHAR(100),
    data            LONGBLOB
  )";

	/**
	 * Constructor
	 *
	 * @param string $font
	 * 	The full path to the font to use
	 * @throws GraphicsException
	 */
	public function __construct($font)
	{
		if (!self::$db)
      self::Initialize();
			//throw new GraphicsException("GTextDB is not initialized");

		parent::__construct($font, '/tmp');
	}

	/**
	 * Initialize the database object to use.
	 *
	 * @param DB $db
   *  A database driver extending {@see DB}.
	 */
	public static function Initialize(DB $db=null)
	{
    if (self::$db) return;
    if (!$db) $db = PLib::GetDB();

    if ($db instanceof SQLite) {
      if (!$db->TableExists(self::$table))
        $db->Query(self::$sqlite_table, self::$table);
    }

		self::$db = $db;
	}

	/**
	 * Create the image
	 *
	 * @param string $text
	 * @return array
	 * @throws Exception
	 */
	public function Render($text)
	{
		$cachekey = $this->generateFileName($text);
		$md5 = $this->ToMD5($text);
		$sql = "SELECT rowid AS id, ckey, width, height, hash, data, type " .
		       "FROM " . self::$table . " WHERE ckey='%s'";

		$res = self::$db->Query($sql, $cachekey);

		if ($res->NumRows() > 0) {
			$res = $res->Fetch();
			if ($res->hash == $md5)
				return array('url'    => $res->ckey,
				             'width'  => $res->width,
				             'height' => $res->height,
                     'type'   => $res->type,
                     'data'   => $res->data);
		}

		ob_start();
		$props = parent::Render($text, true);
		$image = ob_get_clean();

		if (isset($res->id)) {
			$sql = "
			UPDATE " . self::$table . " SET
				data='%s', hash='%s', created=NOW(), width=%d, height=%d,
				type='%s', ckey='%s'
			WHERE id=%d";
			try {
				self::$db->Query($sql, $image, $md5, $props['width'], $props['height'],
				                 'image/' . $this->format, $cachekey, $res->id);
			}
			catch (Exception $e) {
				throw $e;
			}
		}
		else {
			$sql = "
			INSERT INTO " . self::$table . " (
				ckey, data, created, hash, width, height, type
			) VALUES (
				'%s','%s',DATETIME('NOW'),'%s',%d,%d,'%s'
			)";

			try {
				self::$db->Query($sql, $cachekey, $image, $md5, $props['width'],
				                 $props['height'], 'image/' . $this->format);
			}
			catch (Exception $e) {
				throw $e;
			}
		}

		return array('url'    => $cachekey,
		             'width'  => $props['width'],
		             'height' => $props['height'],
                 'type'   => "image/$this->type",
                 'data'   => $image);
	}

	/**
	 * Get a specific image from the database
	 *
	 * @throws GraphicsException
	 *  If the DB object isn't initialized
	 * @param string $which
	 *  This is the cache key for the image.
	 *  {@see GTextDB} for an example of usage
	 * @return Mysql_DBResult|bool
	 */
	public static function Get($which)
	{
		if (!self::$db)
			throw new GraphicsException("GTextDB is not initialized");

		$sql = "SELECT data, type AS mimetype, width, height FROM " . self::$table .
           "WHERE ckey='%s' LIMIT 1";
		try {
			$res = self::$db->Query($sql, $which);
			if ($res->NumRows() == 0)
				return false;

			return $res->Fetch();
		}
		catch (Exception $e) {
			PLib::PrintException($e);
			return false;
		}
		return false;
	}

  /**
   * Clear the dabtabase
   */
  public function Clear()
  {
    self::$db->Query("DELETE FROM " . self::$table);
  }
}
?>