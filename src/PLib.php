<?php
/**
 * PLib (Poppa PHP Library) is a set of PHP classes to make everyday PHP
 * programming a little bit easier.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.2
 * @package PLib
 * @example PLib.xmpl
 */

/**
 * The PLIB constant
 */
define('PLIB', true);
/**
 * The PLIB version
 */
define('PLIB_VERSION', '0.2.0');
/**
 * Justa a silly description
 */
define('PLIB_DESCRIPTION', 'It suddenly occured to me that I had written a
number of handy PHP classes and functions that I was constantly re-using for
new projects. It also occured to me that I always had to alter them in one way
or another and that I always tried to come up with smarter ways to organize
the structure of my classes for every little project.

So I thought I\'d better try to create a re-usable structure and fix the
classes to become more generic. And that\'s when PLib was born.

One might think that this is a some what unneccessary job to do since there
already is [PEAR](http://pear.php.net/) and that is of course true, but
I also do programming for the pure fun of it and to learn new stuff and that\'s
the only reason why I am working on PLib. And some PLib classes doesn\'t have
an equivalent in PEAR so it\'s not all double work ;)

[Download the latest version of PLib](http://plib.poppa.se/download/)');

/**
 * Where PLib is installed
 */
define('PLIB_INSTALL_DIR', dirname(__FILE__));
/**
 * Shortcut for DIRECTORY_SEPARATOR
 */
define('PLIB_DS', DIRECTORY_SEPARATOR);

if (!defined('PLIB_TMP_DIR')) {
	/**
	 * Where we put temporary files and stuff
	 */
	define('PLIB_TMP_DIR', PLIB_INSTALL_DIR . PLIB_DS . 'tmp');
}

if (!is_dir(PLIB_TMP_DIR) && !is_writable(PLIB_TMP_DIR)) {
	throw new PLibException(
		'The PLIB_TMP_DIR "' . PLIB_TMP_DIR . '" is not writabe. PLIB_TMP_DIR ' .
		'is defined in "' . __FILE__ . '" at line "57". You can also override ' .
		'this by defining the constant PLIB_TMP_DIR prior to including PLib.php.'
	);
}

/**
 * Have we got SQLite support or not
 */
define('PLIB_HAS_SQLITE', function_exists('sqlite_open'));

if (!defined('PLIB_DEBUG')) {
	/**
	 * Debug mode or not
	 */
	define('PLIB_DEBUG', false);
}

if (defined('PLIB_DEBUG') && PLIB_DEBUG) {
	PLib::Debug(true);
	error_reporting(E_ALL);
}

/**
 * General newline to use. When in CLI just a newline
 */
define('PLIB_NL', PHP_SAPI == 'cli' ? "\n" : "<br/>\n");

/**
 * Default SQLite db to use for various stuff (Cache, GTextDB, etc)
 * Use {@see PLib::GetDB()} to get an instatiated {@see Sqlite} object.
 */
define('PLIB_SQLITE_DB', PLIB_TMP_DIR . PLIB_DS . 'plib.sqlite');

if (!defined('PLIB_DBG_FILE')) {
  /**
   * Default debug file.
   */
  define('PLIB_DBG_FILE', PLIB_TMP_DIR.PLIB_DS.'debug.log');
}

/**
 * Write arguments and append a `<br/>` at the end
 *
 * @param mixed $args
 * 	Works like {@link sprintf()}
 * @return void
 */
function wbr($args=null)
{
	$args = func_get_args();
	$msg = array_shift($args);
	if (sizeof($args))
		$msg = vsprintf($msg, $args);

	echo $msg . PLIB_NL;
}

/**
 * Write arguments and append a \n at the end
 *
 * @param mixed $args
 *  Works like {@link sprintf()}
 * @return void
 */
function wnl($args=null)
{
	$args = func_get_args();
	$msg = array_shift($args);
	if (sizeof($args))
		$msg = vsprintf($msg, $args);

	echo $msg . "\n";
}

/**
 * Like {@link print_r() print_r} but wraps the ouput in a `<pre/>` tag.
 *
 * @param mixed $what
 * @param bool $ent
 *  Whether or not to turn the output into HTML entitites
 * @return void
 */
function rprint($what, $ent=true)
{
	if (PHP_SAPI != 'cli')
		echo "<pre>";

	if ($ent && (is_array($what) || is_object($what)))
		print_r(array_map('htmlentities', (array)$what));
	elseif ($ent)
		echo htmlentities($what);
	else
		print_r($what);
		
	if (PHP_SAPI != 'cli')
		echo "</pre>";
}

//! This function might be part of PHP6...
if (!function_exists('issetor')) {
	/**
	 * A shortcut for the following:
	 * <code>$myvar = isset($_GET['var']) ? $_GET['var'] : 'default';</code>
	 *
	 * With issetor we do like this:
	 * <code>$myvar = issetor($_GET['var'], 'default');</code>
	 *
	 * @param mixed &$what
   *  Assoc array key
	 * @param mixed $else
   *  Default value
	 * @return mixed
	 */
	function issetor(&$what, $else)
	{
		return isset($what) ? $what : $else;
	}
}

/**
 * Returns `$else` if `$what` is empty
 *
 * @param mixed $what
 * @param mixed $else
 * @return mixed
 */
function isemptyor($what, $else)
{
	return empty($what) ? $else : $what;
}

/**
 * Decode UTF-16 encoded strings.
 *
 * Can handle both BOM'ed data and un-BOM'ed data.
 * Assumes Big-Endian byte order if no BOM is available.
 *
 * @param string $str
 *  UTF-16 encoded data to decode.
 * @return string
 *  UTF-8 / ISO encoded data.
 * @version 0.1 / 2005-01-19
 * @author Rasmus Andersson
 * @link http://rasmusandersson.se
 * @link http://php.net/utf8-decode
 */
function utf16_decode($str)
{
	$len = strlen($str);

  if($len < 2)
  	return $str;

  $bom_be = true;
  $c0 = ord($str[0]);
  $c1 = ord($str[1]);

  if ($c0 == 0xfe && $c1 == 0xff)
  	$str = substr($str,2);
  elseif ($c0 == 0xff && $c1 == 0xfe) {
  	$str = substr($str,2);
  	$bom_be = false;
  }

  $newstr = '';

  for($i = 0; $i < $len ; $i += 2) {
    if($bom_be) {
    	$val  = ord($str[$i]) << 4;
    	if ($i+1 < $len)
    		$val += ord($str[$i+1]);
    }
    else {
    	$val  = ord($str[$i+1]) << 4;
    	$val += ord($str[$i]);
    }
    $newstr .= ($val == 0x228) ? "\n" : chr($val);
  }
  return $newstr;
}

/**
 * Check if string `$str` is UTF-16 encoded or not.
 * This might not be bullet proof!
 *
 * @param string $str
 * @return bool
 */
function is_utf16($str)
{
	if (strlen($str) < 2)
		return false;

	$c0 = ord($str[0]);
	$c1 = ord($str[1]);

	if (($c0 == 0xfe && $c1 == 0xff) || ($c0 == 0xff && $c1 == 0xfe))
		$str = substr($str, 2);

	return hexdec(bin2hex(substr($str, 0, 1))) == 0;
}

/**
 * Checks if `$str` is UTF-8 encoded or not
 *
 * @param String $str
 *  The  string to encode
 * @return bool
 */
function is_utf8($str)
{
	return mb_detect_encoding($str, 'auto') == 'UTF-8';
}

/**
 * Check if `$total` contains `$flag`
 *
 * <code>
 * define('PLAIN', 1);
 * define('HTML',  2);
 *
 * function someFunc($bitparams)
 * {
 *   if (check_bit_flag($bitparams, PLAIN))
 *     echo "Plain text<br/>";
 *
 *   if (check_bit_flag($bitparams, HTML))
 *     echo "HTML text";
 * }
 *
 * someFunc(PLAIN);
 * someFunc(PLAIN|HTML);
 * </code>
 *
 * @since 0.1.11
 * @param int $total
 * @param int $flag
 * @return bool
 */
function check_bit_flag($total, $flag)
{
	return ($total & $flag) == $flag;
}

/**
 * Get a random string.
 * This function is taken from CaptchaSecurityImages.php
 *
 * @author Simon Jarvis
 * @copyright 2006 Simon Jarvis
 * @param int $chars
 *  The length of the string
 * @return string
 */
function random_string($chars=6)
{
	static $possible = '23456789bcdfghjkmnpqrstvwxyz';
	$len = strlen($possible);
	$code = '';
	$i = 0;
	while ($i < $chars) {
		$code .= substr($possible, mt_rand(0, $len-1), 1);
		$i++;
	}
	return $code;
}

/**
 * Redirect the request to `$where`
 *
 * @param string $where
 */
function redirect($where)
{
	header("Location: $where");
	die;
}

/**
 * Returns a pager object.
 * Say you have a list of news that you fetch from a database and you want
 * to display 10 items a time. You then need a "next" and "previous" link and
 * we have all programmed this logic a million times.
 *
 * This function strives to make this logic a fair bit easier. Just pass in the
 * name of the "offset" get/post variable, the number of items you want to
 * display per page and the total number of items you have to display and you
 * will get the logic back in an object.
 *
 * <code>
 * $total = $db->Query("SELECT COUNT(id) FROM some_table");
 * $pager = pager('offset', 20, $total);
 *
 * printf("<a href='./?offset=%s'>Prev</a> | ", $pager->prev);
 *
 * for ($i = 0; $i < $total; $i++) {
 *   if ($i == $pager->current)
 *    printf("<a href='./?offset=%d'><strong>%d</strong></a> | ", i, (i+1));
 *   else
 *    printf("<a href='./?offset=%d'>%d</a> | ", i, (i+1));
 * }
 *
 * printf("<a href='./?offset=%s'>Next</a>", $pager->next);
 * </code>
 *
 * @param string $pagervar
 *  The name of the get/post variable that acts as the offset
 * @param int $display
 *  The number of items you want listed per page
 * @param int $total
 *  The total number of items displayable.
 * @return stdClass
 * * display - Number of items displayed
 * * offset  - Steps from page 0
 * * pages   - Total number of pages
 * * current - Current page
 * * next    - Next page
 * * prev    - Previous page
 * * from    - Index from which the current view starts (display*offset)
 * * to      - Index at which the current view ends (from+offset)
 * * total   - Total number of items.
 */
function pager($pagervar, $display, $total)
{
	$offset   = issetor($_REQUEST[$pagervar], 0);
	$pages    = ceil($total / $display);
	$currpage = $offset+1;
	$next     = $offset+1 >= $pages ? 0 : $offset + 1;
	$prev     = $offset-1 < 0 ? 0 : $offset - 1;
	$from     = $offset * $display;
	$to       = $from + $display > $total ? $total : $from + $display;
	return (object)array(
		'display' => $display,
		'offset'  => $offset,
		'pages'   => $pages,
		'current' => $currpage,
		'next'    => $next,
		'prev'    => $prev,
		'from'    => $from+1,
		'to'      => isemptyor($to, 0),
		'total'   => isemptyor($total, 0)
	);
}

/**
 * Unserializes a multibyte string.
 * Taken from user comments at {@link http://php.net/unserialize PHP.NET}
 *
 * @param string $serial_str
 * @return mixed
 */
function mb_unserialize($serial_str)
{
	$out = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'",
	                    $serial_str );
  return unserialize($out);
}

/**
 * Quotes HTML/XML attributes
 *
 * @since 0.2
 * @param string $in
 * @return string
 */
function html_attribute_quote($in)
{
  $from = array('\'','"','<','>');
  $to   = array('&#39;','&#34;','&#60;','&#62;');

  return str_replace($from, $to, $in);
}

/**
 * Writes to PLib's debug log. Only writes to the file when PLib is in
 * debug mode.
 * Takes a variable length argument list. See {@link sprintf sprintf()}.
 */
function debug()
{
  if (PLib::Debug()) {
    if (!class_exists('Logger')) PLib::Import('Tools.Logger');

    static $logger;
    if (!$logger)
      $logger = new Logger(Logger::LOG_FILE, PLIB_DBG_FILE);

    $args = func_get_args();
    call_user_func_array(array($logger, 'Debug'), $args);
  }
}

/**
 * Writes to PLib's debug log.
 * Takes a variable length argument list. See {@link sprintf sprintf()}.
 */
function trace()
{
  if (!class_exists('Logger')) PLib::Import('Tools.Logger');
  static $logger;
  if (!$logger) {
    $logger = new Logger(Logger::LOG_FILE, PLIB_DBG_FILE);
    $logger->formatOutput = true;
  }

  $args = func_get_args();
  call_user_func_array(array($logger, 'Log'), $args);
}

/**
 * PLib is the core class and is a hidden class, i.e. it can't be instantiated,
 * and only contain static methods. This class pretty much only handle loading
 * of requested PLib modules, creation of the documentation site and other
 * meta like functionality.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package PLib
 * @example PLib.xmpl
 */
class PLib
{
	/**
	 * The PLib web site
	 */
	const PLIB_URL = 'http://plib.poppa.se';
	/**
	 * The location of the latest PLib release
	 */
	const DOWNLOAD_URL = 'http://plib.poppa.se/download/';
	/**
	 * Returns the current version of PLib
	 */
	const VERSION_URL = 'http://plib.poppa.se/version.php';
	/**
	 * Files matching this regexp pattern will not be autoloaded
	 * @staticvar string
	 */
	public static $BLOCK_FILES = '^\.|^stx$|docs|PLib\.php|^__';
	/**
	 * Useful in debugging methods to dtermine if output should be verbose
	 * or not.
	 * @staticvar bool
	 */
	public static $VERBOSE = false;
	/**
	 * Whether or not to allow source view in the documentation site.
	 * @staticvar bool
	 */
	public static $SHOW_DOC_SOURCE = false;
	/**
	 * If this is set the PLibDoc will look for highlighted example and source
	 * file in this directory.
	 * @var string
	 */
	public static $ALTERNATIVE_HIGHLIGHT_PATH = null;
	/**
	 * Debug mode flag
	 * @staticvar bool
	 */
	protected static $debug = false;
  /**
   * Methods callable from the XSL templates.
   * @var array
   */
  private static $xsltFuncs = array(
    'PLib::XSL_HighlightSourceFile',
    'PLib::XSL_HighlightExampleFile'
  );
  /**
   * Default SQLite database object.
   * @see PLib::GetDB()
   * @var SQlite
   */
  private static $db = null;

	/**
	 * Contructor
	 *
	 * This ctor is hidden so we can't create an instance of this class
	 */
	private function __construct() {}

	/**
	 * Import PLib classes
	 *
	 * <code><?php
	 * require_once 'PLib.php';
	 * PLib::Import('Protocols.HTTPClient');
	 * $cli = new HTTPRequest();
	 * $res = $cli->Get('http://google.com/search', array('q' => 'php'));
	 * ?></code>
	 *
	 * @param string $which
	 *  You can use an asterisk (\*) to load all files from the root or any
	 *  subdirectory.
	 *
	 *  * So to load all Protocol classes this would be valid:
	 *    `PLib::Import('Protocols.*');`
	 *  * Or to load only the HTML parser:
	 *    `PLib::Import('Parser.HTMLParser');`
	 *  * Or to load every class file in PLib:
	 *    `PLib::Import('*');`
	 *
	 * @return void
	 */
	public static final function Import($which)
	{
		$paths = explode('.', $which);
		$plen = sizeof($paths)-1;

		if ($paths[$plen] == '*') {
			array_pop($paths);
			$path = PLIB_INSTALL_DIR . PLIB_DS . join(PLIB_DS, $paths);
			self::Load(rtrim($path, PLIB_DS));
		}
		else {
			$file = PLIB_INSTALL_DIR . PLIB_DS . join(PLIB_DS, $paths) . '.php';
			if (file_exists($file))
				require_once $file;
		}
	}

	/**
	 * Returns the relative path to the PLib install dir
	 *
	 * @param string $path
	 * @return string
	 */
	public static final function GetInternalPath($path)
	{
		return substr($path, strlen(PLIB_INSTALL_DIR)+1);
	}

	/**
	 * Converts a file path to a PLib namespace (Dir.Subdir.File)
	 *
	 * @param string $path
	 * @return string
	 */
	public static final function PathToNamespace($path)
	{
		$p  = substr($path, strlen(PLIB_INSTALL_DIR)+1);
		$epos = strrpos($p, '.');
		$epos = $epos === false ? strlen($p) : $epos;
		return str_replace(PLIB_DS, '.', substr($p, 0, $epos));
	}

	/**
	 * Write out debugging messages. If used in CLI {@link cwrite()} will be
	 * called.
	 *
	 * @param mixed $args
	 */
	public static function wdebug($args=null)
	{
		$args = func_get_args();
		$msg = null;
		if (sizeof($args)) {
			$msg = array_shift($args);
			if (sizeof($args))
				$msg = vsprintf($msg, $args);
		}

		if (self::$debug || self::$VERBOSE) {
			if (PHP_SAPI == 'cli') {
				if (function_exists('cwrite'))
					cwrite($msg);
				else
					wnl($msg);
			}
			else
				wbr($msg);
		}
	}

	/**
	 * Used to recursivley load classes in our path
	 * Don't call this method directly. Use {@link PLib::Import()} instead.
	 *
	 * @param string $dir
	 * @return void
	 */
	protected static final function Load($dir)
	{
		if (is_dir($dir)) {
			$dh = opendir($dir);
			if (is_resource($dh)) {
				while (($file = readdir($dh)) !== false) {
					$path = $dir . PLIB_DS . $file;

					if (preg_match('/' . self::$BLOCK_FILES . '/i', $file))
						continue;

					if (is_file($path)) {
						if(preg_match('/\.php$/', $file))
							require_once $path;
					}
					else
						self::Load($path);
				}
			}
			closedir($dh);
		}
	}

  /**
   * Returns the default SQLite database that's being used for various stuff
   * like {@see Cache}, {@see GTextDB}, etc
   *
   * @return SQLite
   */
  public static function GetDB()
  {
    if (!PLIB_HAS_SQLITE)
      throw new Exception("SQLite not available on this system!");

    if (!class_exists('Sqlite'))
      require_once PLIB_INSTALL_DIR . '/DB/Sqlite_driver.php';

    if (!self::$db)
      self::$db = DB::Create('sqlite://' . PLIB_SQLITE_DB)->PConnect();

    return self::$db;
  }

	/**
	 * Makes links clickable
	 *
	 * @param string $str
	 * @return string
	 */
	public static final function Linkify($str)
	{
		$pattern[0] = "#(^|[\n ])([\w]+?://.*?[^ \"\n\r\t<]*)\b#is";
		$replace[0] = "\\1<a href=\"\\2\">\\2</a>";
		$pattern[1] = "#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+".
                  "(?:/[^ \"\t\n\r<]*)?)#is";
		$replace[1] = "\\1<a href=\"http://\\2\">\\2</a>";
		$pattern[2] = "#(^|[\n ])([a-z0-9&\-_.]+?)@([\w\-]+\." .
                  "([\w\-\.]+\.)*[\w]+)#i";
		$replace[2] = "\\1<a href=\"mailto:\\2@\\3\">\\2@\\3</a>";

		return preg_replace($pattern, $replace, $str);
	}

	/**
	 * Setter and getter for debug mode
	 *
	 * @param bool $bool
	 * @return bool|void
	 */
	public static final function Debug($bool=null)
	{
		if (is_bool($bool))
			self::$debug = $bool;
		else
			return self::$debug;
	}

	/**
	 * Print an Exception object in a nice manner.
	 *
	 * @param Exception $e
   * @param bool $return
   *  If true the result will be returned instead of printed.
	 */
	public static final function PrintException(Exception $e, $return=false)
	{
		$out = '';
		$out = $e->getMessage() . PLIB_NL;
		if (self::$debug) {
			$out .= 'in "' . $e->getFile() . '" on line "' . $e->getLine() . '".' .
		          PLIB_NL . $e->getTraceAsString();
		}

		if ($return) return $out;
		echo $out;
	}

	/**
	 * Get information about all PLib modules and classes or a specific
	 * PLib class
	 *
	 * @param string|object $class
	 *  The item to get information about
	 * @param bool $return
	 *  Return or output the result
	 * @param bool $desc
	 *  Display the PLib description or not
	 * @return string|bool
	 */
	public static final function Info($class=null, $return=false, $desc=true)
	{
		if (!class_exists('XSLTransform'))
			self::Import('XML.XSLT');

		if (!class_exists('IO'))
			self::Import('IO.StdIO');

		$result = null;
		$func = null;

		if (isset($_GET['__plibclass']))
			$class = $_GET['__plibclass'];
		elseif (isset($_GET['__plibfunction']))
			$func = $_GET['__plibfunction'];

		if (is_object($class))
			$class = get_class($class);

		try {
			$bdir = IO::CombinePath(PLIB_INSTALL_DIR, '__plib');
			$xml  = IO::CombinePath($bdir, 'plib.xml');
			$xslt = new XSLTransform(IO::CombinePath($bdir, 'plib-info.xsl'), true);
			$xslt->xslt->registerPhpFunctions();
			$xslt->SetParam('plib.debug', self::$debug);
			$xslt->SetParam('plib.description', $desc);

			if ($class)
				$xslt->SetParam('plib.class', $class);
			elseif ($func)
				$xslt->SetParam('plib.function', $func);

			$result = $xslt->Transform($xml);
		}
		catch (Exception $e) {
			wbr($e->getMessage());
		}

		if ($result && sizeof($result) && !$return)
			echo $result;
		elseif ($result && sizeof($result))
			return $result;
		else
			return false;
	}

	/**
	 * Highlight an example file called from a module info page.
	 * NOTE! This method is called from within the PLib XSL template
	 * (/modtree.xsl) and returns an {@link DOMDocument} object and
	 * should not be called from anywhere but an XSL template.
	 *
	 * @param string $file
	 *  The example file to highlight.
	 * @return DOMDocument
	 */
	public static final function XSL_HighlightExampleFile($file)
	{
		if (!class_exists('IO'))
			PLib::Import('IO.StdIO');

		$path = null;
		if (self::$ALTERNATIVE_HIGHLIGHT_PATH != null)
			$path = self::$ALTERNATIVE_HIGHLIGHT_PATH;
		else
			$path = IO::CombinePath(PLIB_INSTALL_DIR, '__plib', 'parsed');

		$pp = $path . '/examples/' . $file;

		if (file_exists($pp)) {
			if (!class_exists('XMLDocument'))
				self::Import('XML.XMLBuilder');

			$xdoc = new XMLDocument();
			$xroot = $xdoc->AddNode('div');
			$xroot->AddNodeTree(
				'<code>' . utf8_encode(file_get_contents($pp)) . '</code>'
			);
			return $xdoc->DomDoc();
		}

		$result = '';
		$exfile = IO::CombinePath(PLIB_INSTALL_DIR, '__plib', 'examples', $file);

		if (!file_exists($exfile))
			return strval(false);
		else
			$result = self::higlightFile($exfile);

		$xdoc = new XMLDocument();
		$xroot = $xdoc->AddNode('code');
		$xroot->AddNodeTree('<pre>' . $result . '</pre>');
		return $xdoc->DomDoc();
	}

	/**
	 * Highlight a PLib source file from within an XMLTemplate. This will only
	 * work when in debug mode.
	 *
	 * See docblock for {@link PLib::XSL_HighlightExampleFile()} for further
	 * explaination.
	 *
	 * @throws PLibException
	 * @param string $file
	 * @return DOMDocument
	 */
	public static final function XSL_HighlightSourceFile($file)
	{
		if (self::$debug || self::$SHOW_DOC_SOURCE) {
			if (!class_exists('IO'))
				PLib::Import('IO.StdIO');

			$path = null;
			if (self::$ALTERNATIVE_HIGHLIGHT_PATH != null)
				$path = self::$ALTERNATIVE_HIGHLIGHT_PATH;
			else
				$path = IO::CombinePath(PLIB_INSTALL_DIR, '__plib', 'parsed');

			$pp = $path . '/source/' . str_replace('.', '_', $file) . '.php.src';

			if (file_exists($pp)) {
				if (!class_exists('XMLDocument'))
					self::Import('XML.XMLBuilder');

				$xdoc = new XMLDocument();

        try {
          $xroot = $xdoc->AddNode('div');
          $xroot->AddNodeTree(
            '<code>' . file_get_contents($pp) . '</code>'
          );
          return $xdoc->DomDoc();
        }
        catch (Exception $e) {
          PLib::PrintException($e);
          die;
        }
			}

			$result = '';
			$source = PLIB_INSTALL_DIR . PLIB_DS .
			          str_replace('.', PLIB_DS, $file) . '.php';

			if (!file_exists($source))
				return strval(false);
			else
				$result = self::higlightFile($source);

			if (!class_exists('XMLDocument'))
				self::Import('XML.XMLBuilder');

			$xdoc  = new XMLDocument();
			$xroot = $xdoc->AddNode('code');
			$xroot->AddNodeTree('<pre>' . $result . '</pre>');
			return $xdoc->DomDoc();
		}

		throw new PLibException('PLib::XSL_HighlightSourceFile() not used in ' .
		                        'debug mode.');
	}

	/**
	 * Generates the PLib documentation site
	 *
	 * @param bool $showSource
	 *  If true a link for viewing the source of each file will appear.
	 * @param bool $parsePrivate
	 *  If true private methods and members will also be displayed
	 */
	public static final function PLibDoc($showSource=null, $parsePrivate=null)
	{
		if (!class_exists('XSLTransform'))
			PLib::Import('XML.XSLT');

    if (headers_sent())
      ob_clean();

		try {
			$parsePrivate = is_bool($parsePrivate) ? $parsePrivate : PLib::$debug;
			$showSource = is_bool($showSource) ? $showSource : self::$SHOW_DOC_SOURCE;

			$xsl = new XSLTransform(PLIB_INSTALL_DIR.'/__plib/plib-html.xsl', true);
			$xsl->xslt->registerPhpFunctions(self::$xsltFuncs);
			$xsl->SetParam('plib.debug', $parsePrivate);
			$xsl->SetParam('plib.source-view', $showSource);
			$xsl->SetParam('plib.install-dir', PLIB_INSTALL_DIR);
			$res = $xsl->Transform(PLIB_INSTALL_DIR . '/__plib/plib.xml');
			echo $res;
		}
		catch (Exception $e) {
			PLib::PrintException($e);
		}
	}

	/**
	 * Returns the body or menu part of the documentation.
	 *
	 * @since 0.1.20
	 * @throws Exception
	 * @param string $which
	 *  The part to get: "menu" or "body"
	 * @param bool $showSource
	 * @param bool $parsePrivate
	 * @param string $alternativeDocFile
	 *  Use this .xml file instead of plib.xml in the __plib dir.
	 * @return string
	 */
	public static function PLibDocPart($which, $showSource=null,
	                                   $parsePrivate=null,
	                                   $alternativeDocFile=null)
	{
		if (!class_exists('XSLTransform'))
			PLib::Import('XML.XSLT');

    //if (headers_sent())
    //  ob_end_clean();

    $res = null;

		try {
			$parsePrivate = is_bool($parsePrivate) ? $parsePrivate : PLib::$debug;
			$showSource = is_bool($showSource) ? $showSource : self::$SHOW_DOC_SOURCE;

			$xslfile = null;

			switch ($which)
			{
				case 'menu':
				case 'navigation':
					$xslfile = 'plib-navigation.xsl';
					break;

				case 'body':
				case 'content':
					$xslfile = 'plib-body.xsl';
					break;
			}

			$xsl = new XSLTransform(PLIB_INSTALL_DIR."/__plib/$xslfile", true);
			$xsl->xslt->registerPHPFunctions(self::$xsltFuncs);
			$xsl->SetParam('plib.debug', $parsePrivate);
			$xsl->SetParam('plib.source-view', $showSource);
			$xsl->SetParam('plib.install-dir', PLIB_INSTALL_DIR);
			$xml = issetor($alternativeDocFile, PLIB_INSTALL_DIR.'/__plib/plib.xml');
			$res = $xsl->Transform($xml);
		}
		catch (Exception $e) {
			PLib::PrintException($e);
		}
		return $res;
	}

	/**
	 * Check for a new version of PLib
	 * @since 0.1.14
	 */
	public static final function CheckVersion()
	{
		if (!class_exists('HTTPRequest'))
			PLib::Import('Protocols.Net');

		try {
			$c = new HTTPRequest();
			$v = $c->Get('http://plib.poppa.se/version.php');

			if (version_compare(PLIB_VERSION, (string)$v, '<')) {
				wbr("<strong>There's a new version of PLib!</strong>");
				wbr("The latest version is <strong><em>$v</em></strong> and you are " .
				    "using version <strong><em>" . PLIB_VERSION . "</em></strong>");
				wbr("Go to <a href='" . self::DOWNLOAD_URL . "'>the download page</a> ".
				    "to grab the latest version!");
			}
      elseif (version_compare(PLIB_VERSION, (string)$v, '>')) {
        wbr("What, you have a newer version (%s) than what is available (%s)!",
            PLIB_VERSION, (string)$v);
      }
			else
				wbr("You are using the latest version ($v) of PLib");
		}
		catch (Exception $e) {
			die("An error occured when trying to check for a new version of PLib: " .
			    $e->getMessage());
		}
	}

	/**
	 * Syntax highlight a source file.
	 *
	 * @param string $file
	 * @return string
	 * @depends Syntaxer
	 */
	protected static final function higlightFile($file)
	{
		if (!class_exists('Syntaxer'))
			self::Import('Parser.Syntaxer.Syntaxer');

		$excont = file_get_contents($file);

		$st = new Syntaxer('php');
		$st->SetLineWrapper("", "\n");
		$st->Parse(utf8_encode($excont));
		return $st->GetBuffer();
	}

	/**
	 * A PHP "magic" method.
	 * Gives the opportunity to create an abritray string from the object
	 *
	 * @return string
	 */
	public function __toString($obj=null)
	{
		if (!$obj)
			$obj = $this;

		return get_class($obj);
	}
}

/**
 * Just creates an empty object
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package PLib
 */
class PLibFakeClass extends PLib
{
	public function __construct() {}
}

/**
 * General PLib exception class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package PLib
 * @subpackage Exceptions
 */
class PLibException extends Exception
{
	public $message;
}

/**
 * Times execution of code.
 *
 * <code>
 * $timer = new ExecutionTimer();
 * // some code here
 * echo "Execution time: $timer<br/>";
 * </code>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package PLib
 * @subpackage Tools
 */
class ExecutionTimer
{
	/**
	 * Start time of execution. {@link microtime()};
	 * @var float
	*/
	private $time;
	/**
	 * Number of floating points to round to.
	 * -1 means don't round
	 * @var int
	*/
	private $round = -1;

	/**
	 * Constructor
	*/
	public function __construct($round=-1)
	{
		$this->time = microtime(true);
		$this->round = $round;
	}

	/**
	 * Returns the execution timer
	 *
	 * @see microtime
	 * @return float
	*/
	public function Get()
	{
		$t = (microtime(true)-$this->time);

		if ($this->round > -1)
			$t = round($t, $this->round);

		return $t;
	}

	/**
	 * Converts the execution time to a string.
	 *
	 * @return string
	*/
	public function __toString()
	{
		return (string)$this->Get();
	}
}
?>