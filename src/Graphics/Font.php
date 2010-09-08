<?php
/**
 * Font
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @uses StreamReader
 * @uses PLibIterator
 * @uses File
 * @uses Dir
*/

/**
 * Include the {@see StreamReader} class
 */
require_once PLIB_INSTALL_DIR . '/IO/StreamReader.php';
/**
 * We need the {@see PlibIterator} class
 */
require_once PLIB_INSTALL_DIR . '/Core/Iterator.php';
/**
 * We need the {@see Dir} and {@see File} classes
 */
require_once PLIB_INSTALL_DIR . '/IO/StdIO.php';

/**
 * Reads information (creator, name, version an so on) from a font file.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @subpackage Font
 */
class Font
{
	//! Static members

	/**
	 * Collection of all fonts with full paths
	 * @var array
	 */
	protected static $fonts = array();
	/**
	 * Collection of only the font names
	 * @var array
	 */
	protected static $fontnames = array();
	/**
	 * Valid font extentions
	 * @var array
	 */
	protected static $validExtensions = array('ttf', 'afm', 'otf');

	//! Font sample

	/**
	 * Default font size for samples
	 * @var int
	 */
	public static $samplesize    = 32;
	/**
	 * Default background color for samples
	 * @var string
	 */
	public static $samplebgcolor = '000';
	/**
	 * Default foreground color for samples
	 * @var string
	 */
	public static $samplefgcolor = 'FFF';

	//! Object members

	/**
	 * Type of font (true type, type1 etc);
	 * @var string
	 */
	protected $type;
	/**
	 * The file system path to the font
	 * @var string
	 */
	protected $path;
	/**
	 * The font family
	 * @var string
	 */
	protected $family;
	/**
	 * The font sub family e.g. Regular
	 * @var string
	 */
	protected $subfamily;
	/**
	 * The font's full name
	 * @var string
	 */
	protected $fullname;
	/**
	 * Postscript name
	 * @var string
	 */
	protected $psname;
	/**
	 * Trademark
	 * @var string
	 */
	protected $trademark;
	/**
	 * The font manufacturer
	 * @var string
	 */
	protected $manufacturer;
	/**
	 * The font designer
	 * @var string
	 */
	protected $designer;
	/**
	 * The file object for the font
	 * @var File
	 */
	protected $fontfile;
	/**
	 * Unique name
	 * @var string
	 */
	protected $uniquename;
	/**
	 * The font version
	 * @var float
	 */
	protected $version;

	//! GText stuff for the sample output

	/**
	 * The GText object
	 * @var GText
	 */
	protected static $gtext;

	/**
	 * Hidden constructor. This class can't be instantiated directly but must
	 * be instantiated from a child class.
	 *
	 * @param string $file
	 */
	protected function __construct($file)
	{
		$this->fontfile = new File($file);
		$this->path = $this->fontfile->path;
	}

	/**
	 * Creates a new instanse of what ever fonts are being supported
	 *
	 * @param string $fontfile
	 * @return FontTTF|FontAFM|FontOTF
	 * @throws FontException
	 */
	public static final function Create($fontfile)
	{
		$f   = new File($fontfile);
		$e   = strtoupper($f->extension);
		$cls = "Font{$e}";

		if (class_exists($cls))
			return new $cls($fontfile);

		throw new FontException("{$f->extension} fonts is not supported!");
	}

	/**
	 * Add a font directory.
	 * The directory will be traversed recursivley for fonts to add.
	 *
	 * @param string $path
	 * @return void
	 * @throws FontException
	 */
	public static final function AddDir($path)
	{
		if (!file_exists($path))
			throw new FontException("Directory '$path' doesn't exist!");

		if (!is_dir($path))
			throw new FontException("The path '$path' is not a directory!");

		$dkey = RecursiveDirectoryIterator::KEY_AS_PATHNAME;
		$fkey = RecursiveIteratorIterator::CHILD_FIRST;
		$rdi  = new RecursiveDirectoryIterator($path, $dkey);
		$iter = new RecursiveIteratorIterator($rdi, $fkey);

		foreach ($iter as $path => $file) {
			if (is_dir($path))
				continue;

			$f = new File($path);

			if (!in_array(strtolower($f->extension), self::$validExtensions))
				continue;

			if (in_array($f->name, self::$fontnames))
				continue;

			array_push(self::$fonts, $f);
			array_push(self::$fontnames, $f->name);
		}

		usort(self::$fonts, array('Font', 'sort'));
	}

	/**
	 * Add a single font
	 *
	 * @param string $path
	 * @return void
	 * @throws FontException
	 */
	public static final function AddFont($path)
	{
		if (!file_exists($path))
			throw new FontException("The font '$path' doesn't exist!");

		if (!is_file($path))
			throw new FontException("The path '$path' is not a file!");

		self::$fonts[] = new File($path);
		usort(self::$fonts, array('Font', 'sort'));
	}

	/**
	 * Returns all fonts
	 *
	 * @return array
	 *  Each value is an instance of {@see File}
	 */
	public static final function GetFonts($type=null)
	{
		if ($type) {
			if (!in_array(strtolower($type), self::$validExtensions))
				throw new FontException("$type is not a valid font extension");

			return self::filterFontsByExtension($type);
		}

		usort(self::$fonts, array('Font', 'sort'));
		return self::$fonts;
	}

	/**
	 * Function for sorting the font array on file name.
	 *
	 * @param File $a
	 * @param File $b
	 * @return int
	 */
	protected static function sort($a, $b)
	{
		return strcasecmp($a->name, $b->name);
	}

	/**
	 * Create a font array with only $which fonts
	 *
	 * @param string $which
	 *  e.g. font file extension
	 * @return array
	 */
	protected static function filterFontsByExtension($which)
	{
		$which = strtolower($which);
		$out = array();

		foreach (self::$fonts as $font)
			if (strtolower($font->extension) == $which)
				$out[] = $font;

		usort($out, array('Font', 'sort'));
		return $out;
	}

	/**
	 * Returns the path to $fontname if $fontname exists in the internal array.
	 * NOTE! This should contain the file extension as well
	 *
	 * <code>
	 * $fontpath = Font::GetFontPath('verdana.ttf');
	 * </code>
	 *
	 * @param string $fontname
	 * @return string|bool
	 */
	public static final function GetFontPath($fontname)
	{
		foreach (self::$fonts as $file) {
			if (strtolower($file->name) == strtolower($fontname))
				return $file->path;
		}

		return false;
	}

	/**
	 * Returns an iterator to interate over all available fonts
	 *
	 * <code>
	 * Font::AddDir('/usr/share/fonts/truetype');
	 * $iter = Font::GetIterator();
	 * while ($iter->HasNext() {
	 *   $font = $iter->Next();
	 *   echo "File name: " . $font->name . ", " .
	 *        "File path: " . $font->path . "<br/>";
	 * }
	 * </code>
	 *
	 * @param string $type
	 *  If given only fonts of type $type will be collected.
	 * @return FontIterator
	 */
	public static final function GetIterator($type=null)
	{
		return new FontIterator($type);
	}

	//! ==========================================================================
	//!
	//!     Object methods
	//!
	//! ==========================================================================

	/**
	 * Returns a StreamReader object
	 *
	 * @return StreamReader
	 */
	protected function streamReader()
	{
		return new StreamReader($this->fontfile->path);
	}

	/**
	 * Returns the font's path
	 *
	 * @return string
	 */
	public function Path()
	{
		return $this->path;
	}

	/**
	 * Returns the font family
	 *
	 * @return string
	 */
	public function Family()
	{
		return $this->family;
	}

	/**
	 * Returns the sub family (e.g. Regular)
	 *
	 * @return string
	 */
	public function SubFamily()
	{
		return $this->subfamily;
	}

	/**
	 * Returns the font manufacturer
	 *
	 * @return string
	 */
	public function Manufacturer()
	{
		return $this->manufacturer;
	}

	/**
	 * Returns the font trademark
	 *
	 * @return string
	 */
	public function Trademark()
	{
		return $this->trademark;
	}

	/**
	 * Returns the name of the font
	 *
	 * @return string
	 */
	public function Name()
	{
		return $this->fullname ? $this->fullname : $this->fontfile->name;
	}

	/**
	 * Returns the font designer
	 *
	 * @return string
	 */
	public function Designer()
	{
		return $this->designer;
	}

	/**
	 * Returns the post script name
	 *
	 * @return string
	 */
	public function PostScriptName()
	{
		return $this->psname;
	}

	/**
	 * Returns the unique name
	 *
	 * @return string
	 */
	public function UniqueName()
	{
		return $this->uniquename;
	}

	/**
	 * Returns the version of the font
	 *
	 * @return string
	 */
	public function Version()
	{
		return $this->version;
	}

	/**
	 * Returns the font type
	 *
	 * @return string
	 */
	public function Type()
	{
		return $this->type;
	}

	/**
	 * Creates a text image
	 * Only applicable for TTF fonts
	 * @return array
	 */
	public function Sample()
	{
		return false;
	}

	/**
	 * Initialize the GText object
	 */
	protected final function initGText()
	{
		if (!class_exists('GText'))
			require_once 'GText.php';

		$g = new GTextDB($this->path);
		$g->fontsize  = self::$samplesize;
		$g->fgcolor   = self::$samplefgcolor;
		$g->bgcolor   = self::$samplebgcolor;
		self::$gtext  = &$g;
	}
}

/**
 * Class for reading TTF fonts
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @subpackage Font
 */
class FontTTF extends Font
{
	/**
	 * Type of font
	 * @var string
	 */
	protected $type = 'TrueType';

	/**
	 * Constructor
	 *
	 * @param string $fontfile
	 * @see Font::Create()
	 */
	public function __construct($fontfile)
	{
		parent::__construct($fontfile);
		$this->parseTTF();
	}

	/**
	 * This is pretty much a port of a Perl script from ImageMagick
	 * {@link http://www.imagemagick.org/Usage/scripts/imagick_type_gen}
	 *
	 * @return void
	 * @throws FontException
	 */
	private function parseTTF()
	{
		$rd = $this->streamReader();
		$header = $rd->Read(12);

		$fontversion   = null;
		$numtables     = null;
		$searchrange   = null;
		$entryselector = null;
		$rangeshift    = null;

		$a = unpack("Nfontversion/"   .
		            "nnumtables/"     .
		            "nsearchrange/"   .
		            "nentryselector/" .
		            "nrangeshift",
		            $header);
		extract($a);

		$a = unpack('A4', $header);
		$fontversioncode = bin2hex($a[1]);

		if ($fontversioncode != "00010000") {
			$rd->Close();
			throw new FontException(
				"The file '$this->path' given to FontTTF is not a True Type font"
			);
		}

		$tag      = null;
		$checksum = null;
		$offset   = null;
		$length   = null;
		$i        = 0;

		$isset = array();

		while (++$i <= $numtables) {
			$a = unpack("A4tag/"     .
			            "Nchecksum/" .
			            "Noffset/"   .
			            "Nlength",
			            $rd->Read(16));

			extract($a);

			if ($tag != 'name' || $offset < 1)
				continue;

			$format       = null;
			$count        = null;
			$stringOffset = null;

			$a = unpack("nformat/ncount/nstringOffset", $rd->ReadBlock($offset, 6));
			extract($a);

			$tablebase   = $offset + 6;
			$storagebase = $tablebase + $count * 12;

			$j = 0;
			while (++$j <= $count) {
				$entry = $rd->ReadBlock($tablebase + ($j-1)*12, 12);
				$p1 = $rd->Position();
				$namePlatformID = null;
				$nameEncodingID = null;
				$nameLanguageID = null;
				$nameID         = null;
				$nameLength     = null;
				$nameOffset     = null;

				$a = unpack("nnamePlatformID/" .
				            "nnameEncodingID/" .
				            "nnameLanguageID/" .
				            "nnameID/"         .
				            "nnameLength/"     .
				            "nnameOffset",
				            $entry);

				extract($a);

				if (in_array($nameID, $isset))
					continue;

	      //! ID meanings : figured out from getttinfo
	      //!
	      //! Platform: 0=Apple  1=macintosh  3=microsoft
	      //! Encoding: 0=unicode(8) 1=unicode(16)
	      //! Language: 0=english  1033=English-US  1041=Japanese 2052=Chinese

	      if (!($nameLanguageID == 0 || $nameLanguageID == 1033))
	      	continue;

	      if ($nameLength < 1)
	      	$name = "";
				else {
		      $name = $rd->ReadBlock($storagebase + $nameOffset, $nameLength);
		      //! UTF-16 encoded
		      //! I've found fonts where the encoding bit is wrong, thus the
		      //! extra method where we check the string it self
					if ($nameEncodingID == 1 || is_utf16($name))
						$name = utf16_decode($name);
				}

				$isset[] = $nameID;

				echo "$nameID: $name\n";
				
				switch ($nameID)
				{
					case 1: $this->family       = $name; break;
					case 2: $this->subfamily    = $name; break;
					case 3: $this->uniquename   = $name; break;
					case 4: $this->fullname     = $name; break;
					case 5: $this->version      = $name; break;
					case 6: $this->psname       = $name; break;
					case 7: $this->trademark    = $name; break;
					case 8: $this->manufacturer = $name; break;
					case 9: $this->designer     = $name; break;
				}
			}
		}
		$rd->Close();
	}

	/**
	 * Returns an array from {@see GText::Render()}
	 *
	 * @param string $text
	 *   The sample text to write to the image
	 * @return array
	 */
	public function Sample($text)
	{
		if (!parent::$gtext)
			$this->initGText();

		parent::$gtext->font = $this->path;
		return parent::$gtext->Render($text);
	}
}

/**
 * Class for reading Open Type Fonts
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @subpackage Font
 */
class FontOTF extends Font
{
	/**
	 * Type of font
	 * @var string
	 */
	protected $type = 'OpenType';

	/**
	 * Constructor
	 *
	 * @param string $fontfile
	 */
	public function __construct($fontfile)
	{
		parent::__construct($fontfile);
		$this->parseOTF();
	}

	/**
	 * Parse the font
	 */
	protected function parseOTF()
	{
		$rd = $this->streamReader();
		$header = $rd->Read(12);

		$sfntversion   = null;
		$numtables     = null;
		$searchrange   = null;
		$entryselector = null;
		$rangeshift    = null;

		$a = unpack('A4sfntversion/'  .
		            'nnumtables/'     .
		            'nsearchrange/'   .
		            'nentryselector/' .
		            'nrangeshift', $header);

		extract($a);

		switch ($sfntversion) {
			case 'OTTO':
				// wbr('OpenType fonts containing CFF data');
				break;

			case '00010000':
				// wbr('Version 1');
				break;

			case '00020000':
				// wbr('Version 2');
				break;

			default:
				$fn = $this->fontfile->name;
				throw new FontException("\"$fn\" is not an OpenType font!");
		}

		$i = 0;
		$tablebase = 12;

		while (++$i <= $numtables) {
			$table = $rd->ReadBlock($tablebase + ($i-1)*16, 16);

			$a = unpack('a4tag/Nchecksum/Noffset/Nlength', $table);

			//! Naming table. See
			//! http://partners.adobe.com/public/developer/opentype/index_name.html
			//!
			//! The code below is identical to the one in FontTTF
			if ($a['tag'] == 'name') {
				$d = $rd->ReadBlock($a['offset'], 6);
				$tablebase = $a['offset'] + 6;

				$a = unpack('nformat/ncount/nstringoffset', $d);
				$count = $a['count'];
				$storagebase = $tablebase + ($count*12);

				$isset = array();
				$j = 0;
				while (++$j <= $count) {
					$entry = $rd->ReadBlock($tablebase + ($j-1)*12, 12);
					$namePlatformID = null;
					$nameEncodingID = null;
					$nameLanguageID = null;
					$nameID         = null;
					$nameLength     = null;
					$nameOffset     = null;

					$a = unpack("nnamePlatformID/" .
					            "nnameEncodingID/" .
					            "nnameLanguageID/" .
					            "nnameID/"         .
					            "nnameLength/"     .
					            "nnameOffset",
					            $entry);
					extract($a);

					if (in_array($nameID, $isset))
						continue;

		      //! ID meanings : figured out from getttinfo
		      //!
		      //! Platform: 0=Apple  1=macintosh  3=microsoft
		      //! Encoding: 0=unicode(8) 1=unicode(16)
		      //! Language: 0=english  1033=English-US  1041=Japanese 2052=Chinese

		      if (!($nameLanguageID == 0 || $nameLanguageID == 1033))
		      	continue;

		      if ($nameLength < 1)
		      	$name = "";
					else {
			      $name = $rd->ReadBlock($storagebase + $nameOffset, $nameLength);
			      //! UTF-16 encoded
			      //! I've found fonts where the encoding bit is wrong, thus the
			      //! extra method where we check the string it self
						if ($nameEncodingID == 1 || is_utf16($name))
							$name = utf16_decode($name);
					}

					$isset[] = $nameID;

					switch ($nameID)
					{
						case 1: $this->family       = $name; break;
						case 2: $this->subfamily    = $name; break;
						case 3: $this->uniquename   = $name; break;
						case 4: $this->fullname     = $name; break;
						case 5: $this->version      = (float)$name; break;
						case 6: $this->psname       = $name; break;
						case 7: $this->trademark    = $name; break;
						case 8: $this->manufacturer = $name; break;
						case 9: $this->designer     = $name; break;
					}
				}
			}
		}
		$rd->Close();
	}

	/**
	 * Returns an array from {@see GText::Render()}
	 *
	 * @param string $text
	 *  The sample text to write to the image
	 * @return array
	 */
	public function Sample($text)
	{
		if (!parent::$gtext)
			$this->initGText();

		parent::$gtext->font = $this->path;
		return parent::$gtext->Render($text);
	}
}

/**
 * Class for reading type1 fonts
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @subpackage Font
 */
class FontAFM extends Font
{
	/**
	 * Type of font
	 * @var string
	 */
	protected $type = 'Adobe Type1';

	/**
	 * Constructor
	 *
	 * @param string $fontfile
	 */
	public function __construct($fontfile)
	{
		parent::__construct($fontfile);
		$this->parseAFM();
	}

	/**
	 * Parses the AFM file for information
	 *
	 * @return void
	 */
	protected function parseAFM()
	{
		$rd = $this->streamReader();

		while ($line = $rd->ReadLine()) {
			if (preg_match('/^StartCharMetrics/', $line))
				break;

			if (preg_match('/^FullName (.*)/', $line, $m)) {
				$this->fullname = str_replace(' L ', ' ', $m[1]);
				continue;
			}

			if (preg_match('/^FamilyName (.*)/', $line, $m)) {
				$this->family = str_replace(' L' , '', $m[1]);
				continue;
			}

			if (preg_match('/^Weight (.*)/', $line, $m)) {
				$this->subfamily = $m[1];
				continue;
			}

			if (preg_match('/^Version (.*)/', $line, $m)) {
				$this->version = (float)$m[1];
				continue;
			}

			if (preg_match('/^Notice (.*)/', $line, $m)) {
				if (preg_match('/\((.*)\)/', $m[1], $n))
					$m[1] = $n[1];

				$this->trademark = str_replace('++', '', $m[1]);
				continue;
			}
		}
	}
}

/**
 * Loops over all available fonts
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @subpackage Font
 * @see PLibIterator
 */
class FontIterator extends PLibIterator
{
	/**
	 * Constructor
	 *
	 * @param string $type
	 *   Type of fonts to collect
	 */
	public function __construct($type=null)
	{
		$this->container = Font::GetFonts($type);
	}

	/**
	 * Verifies if there's a next index in the interator
	 *
	 * @return bool
	 */
	public function HasNext()
	{
		if (!is_array($this->container) || empty($this->container))
			return false;

		return array_key_exists($this->pointer, $this->container);
	}

	/**
	 * Moves the iterator forward and returns the next value
	 *
	 * @return Font
	 */
	public function Next()
	{
		if (!array_key_exists($this->pointer, $this->container))
			return false;

		$n = $this->container[$this->pointer];

		if (!$n instanceof Font)
			$this->container[$this->pointer] = Font::Create($n);

		unset($n);

		return $this->container[$this->pointer++];
	}
}

/**
 * Generic font exception
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Graphics
 * @subpackage Exception
 */
class FontException extends Exception
{
	public $message;
}
?>