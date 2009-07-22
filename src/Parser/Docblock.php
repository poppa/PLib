<?php
/**
 * A simple docblock parser
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.1
 * @package Parser
 * @subpackage Docblock
 * @uses Syntaxer
 * @uses STXMarkdown
 */

/**
 * We need the {@see STXMarkdown} class
 */
require_once PLIB_INSTALL_DIR . '/Parser/Syntaxer/STXMarkdown.php';

require_once PLIB_INSTALL_DIR . '/String/String.php';

/**
 * A simple dockblock parser that pulls out the docblock description and
 * docblock tags.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Parser
 * @subpackage Docblock
 */
class DocblockParser
{
	/**
	 * Beginning sequence of a docblock
	 */
	const BLOCK_BEGIN = '/**';
	/**
	 * Ending sequence of a docblock
	 */
	const BLOCK_END = '*/';
	/**
	 * White space chars
	 */
	const WHITES = " \r\n\t";
	/**
	 * At signs not preceeded by any of these chars should not be treated as
	 * a docblock tag
	 */
	const PATTERN = "*{ \r\n\t";
	/**
	 * Beginning of an inline tag
	 */
	const INLINE_BEGIN = '{@';
	/**
	 * End of inline tag
	 */
	const INLINE_END = '}';
	/**
	 * To save loading and processor time we can preparse example, tutorial and
	 * source files. We store directory references here if they are set.
	 * @see DocblockParser::StaticFilesLocation()
	 * @var array
	 */
	protected static $STATIC_DIRS = array();
	/**
	 * Keywords to treat at docblock tags
	 * @var array
	 */
	private static $keywords = array(
		# Standard PHPDocumentor tags
		'abstract', 'access', 'author', 'copyright', 'deprecated', 'example',
		'exception', 'final', 'global', 'ignore', 'internal', 'link', 'license',
		'magic', 'name', 'package', 'param', 'return', 'see', 'since', 'static',
		'staticvar', 'subpackage', 'throws', 'todo', 'uses', 'var', 'version',

		# Special PLib
		'depends', 'usedby'
	);
	/**
	 * The actual comment
	 * @var string
	 */
	private $dockblock;
	/**
	 * All text preceeding the first docblock tag
	 * @var string
	 */
	private $docblockDescription;
	/**
	 * Container for found tags
	 * @var array
	 */
	private $map = array();

	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * Returns the keywords array
	 *
	 * @return array
	 */
	public static function Keywords()
	{
		return self::$keywords;
	}

	/**
	 * To save loading and processor time we can preparse example, tutorial and
	 * source files. We store directory references here if they are set.
	 *
	 * Avilable indexes are:
	 *
	 * * EXAMPLE_SOURCE  > Were the original example files are
	 * * EXAMPLE_RESULT  > Where to put the parsed example files
	 * * SOURCE_RESULT   > Where to put parsed source code files
	 * * TUTORIAL_SOURCE > Where the orginal tutorial files are
	 * * TUTORIAL_RESULT > Where to put the parsed tutorial files
	 *
	 * @throws Exception
	 * @param string $index
	 *   What directory to set the value for
	 * @param string $value
	 *   The path to the directory
	 * @return void|string|array
	 * * If no arguments is passed the {@link DocblockParser::$STATIC_DIRS} will
	 *   be returned.
	 * * If only the index is passed the value of that index will be returned if
	 *   it is set or false will be returned
	 * * If both index and value is passed the value will be assigned to the
	 *   index in the array.
	 */
	public static function StaticFilesLocation($index=null, $value=null)
	{
		if ($index && !$value) {
			if(isset(self::$STATIC_DIRS[$index]))
				return self::$STATIC_DIRS[$index];
			return false;
		}

		if (!$index || !$value)
			return self::$STATIC_DIRS;

		$a = array(
			'EXAMPLE_SOURCE', 'EXAMPLE_RESULT',
			'SOURCE_RESULT',
		  'TUTORIAL_SOURCE', 'TUTORIAL_RESULT'
		);

		if (in_array($index, $a)) {
			if (!file_exists($value) || !is_dir($value)) {
				throw new Exception(sprintf(
					"The path \"%s\" in \"%s::StaticFilesLocation()\" doesn't exist",
					$value, __CLASS__
				));
			}
			self::$STATIC_DIRS[$index] = $value;
		}
	}

	/**
	 * Parse the block comment
	 *
	 * @param string $in
	 */
	public function Parse($in)
	{
		$this->dockblock = $in;
		if (strlen($this->dockblock))
			$this->scan();

		if (sizeof($this->map)) {
			$tagParser = new DocblockTagParser();
			foreach ($this->map as $tag => $value)
				$this->map[$tag] = $tagParser->Parse($tag, $value);
		}
	}

	/**
	 * Scan the block comment for description and tags.
	 */
	protected function scan()
	{
		$wordstr = implode('|', self::$keywords);
		$re = "#/\*\*(.*?)(?:[^{]@(?:$wordstr)|\*/)#sim";
		preg_match($re, $this->dockblock, $m);

		if ($m && $m[1]) {
			$this->docblockDescription = $this->fixStars($m[1]);
			$this->map['description'] = $this->fixStars($m[1]);
		}

		$pos = strlen($m[1]) + strlen(self::BLOCK_BEGIN);
		$end = strlen($this->dockblock);

		while(1) {
			if ($pos > $end) break;

			$chr = $this->dockblock[$pos-1];

			if ($chr == '@') {
        // It's an assigment
				if (($kw = $this->isKeyword($pos)) !== false) {
					$pos += strlen($kw);
					$fp = $pos;
					while (1) {
						$np = strpos($this->dockblock, '@', $fp);
						if ($this->isKeyword($np+1) || $fp >= $end)
							break;
						$fp++;
					}

					if (!$np) $np = $end;

					$ln = $this->fixStars(substr($this->dockblock, $pos, $np-$pos));

					if (array_key_exists($kw, $this->map)) {
						if (is_array($this->map[$kw]))
							$this->map[$kw][] = $ln;
						else {
							$v = $this->map[$kw];
							$this->map[$kw] = array($v, $ln);
						}
					}
					else
						$this->map[$kw] = $ln;

					$pos = $fp;
					continue;
				}
			}
			$pos++;
		}
	}

	/**
	 * Checks if the @ sign at position $pos is a tag or not.
	 *
	 * @param int $pos
	 * @return string|bool
	 *  If @ is a keyword the keyword is returned else false is returned
	 */
	private function isKeyword($pos)
	{
		if ($this->dockblock[$pos-2] == "{")
			return false;

		$e = strcspn($this->dockblock, self::WHITES, $pos);
		$w = substr($this->dockblock, $pos, $e);
		#cwrite("BRED:$w");
		return in_array($w, self::$keywords) ? $w : false;
	}

	/**
	 * Strips all leading asterics.
	 *
	 * @param string $in
	 * @return string
	 */
	private function fixStars($in)
	{
		$out = '';
		foreach (explode("\n", $in) as $line)
			$out .= preg_replace('#^/?\s*\*+/?\s?#', '', $line) . "\n";

		return strlen($out) ? trim($out) : trim($in);
	}

	/**
	 * Pulls out the top level docblock from a file
	 *
	 * @param string $in
	 *   Can either be a file reference or file contents.
	 * @return string|bool
	 */
	public static function GetTopLevelBlock($in)
	{
		if (!(strpos($in, "\n")) && file_exists($in))
			$in = file_get_contents($in);

		$begin = strpos($in, self::BLOCK_BEGIN);
		$end   = strpos($in, self::BLOCK_END);

		if ($begin === false || $end === false)
			return false;

		$block = substr($in, $begin, $end-$begin);
		return $block;
	}

  protected static $classConstants = array();

  public static function GetClassConstBLock($className, $file)
  {
    if (array_key_exists($file, self::$classConstants)) {
      trace("::: $file already indexed\n");
      return;
    }

    self::$classConstants[$file] = array();
/*
    trace("##########################################\n" .
          "#  Get class constants for $className in $file\n".
          "##########################################\n");
 */
    $rd = new StringReader(utf8_decode(file_get_contents($file)));
    $char = null;
    $inCmt = 0;
    $inCls = 0;
    $docblock = null;

    while (($char = $rd->Read()) !== false)
    {
      // Line comment, skip to newline
      if ($char == '#') {
        $rd->ReadToChar("\n");
        continue;
      }

      $checkKeyword = false;
      $next = $rd->Read();

      if ($char == '/' && ($next == '*' || $next == '/')) {
        // Line comment, skip to newline
        if ($next == '/') {
          $rd->ReadToChar("\n");
          continue;
        }

        $next = $rd->Read();
        $isDocBlock = $next == '*';
        $docblock = "/*$next";
        while (($char = $rd->Read()) !== false) {
          $next = $rd->Read();
          if ($char == '*' && $next == '/') {
            $docblock .= "$char$next";
            break;
          }
          else {
            $rd->Unread();
            $docblock .= "$char";
          }
        }

        if ($isDocBlock) {
          //trace("$docblock\n-----------------------------\n");
          $checkKeyword = true;
        }
      }
      else {
        $rd->Unread();
      }

      if ($checkKeyword) {
        $line = null;
        while (($line = $rd->Read()) == "\n")
          $line = '';

        $line = $rd->ReadToChar("\n");
        trace("### Line: $line\n");
        if (preg_match('/class\s+(.[^\s]*)[\s{]/i', $line, $m)) {
          trace(">>> %s\n", $m[1]);
          $cname = $m[1];
          $cdata = '';

        }
      }
    }
    exit(0);
  }

	/**
	 * Returns the map array with description and found tags
	 *
	 * @return array
	 */
	public function DocblockData()
	{
		return $this->map;
	}

	/**
	 * Magic getter.
	 *
	 * @see PLib::__toString()
	 * @param string $key
	 * @return mixed
	 */
	public function __get($key)
	{
		if (isset($this->map[$key]))
			return $this->map[$key];

		return false;
	}
}

/**
 * Parses the docblock tags, i.e stuff beginning with @ followed by anything
 * in {@link DocblockParser::$keywords}
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Parser
 * @subpackage Docblock
 */
class DocblockTagParser
{
	/**
	 * The tag to parse
	 * @var string
	 */
	protected $tag;
	/**
	 * The value of the tag
	 * @var string
	 */
	protected $value;
	/**
	 * {@link DocblockParser::$keywords}
	 * @var array
	 */
	protected $keywords = array();
	/**
	 * The kewords array joined by a | to a string. Used in the tag matching
	 * regexp.
	 * @var string
	 */
	protected static $strKeywords;
	/**
	 * Regexp for finding inline tags.
	 * @var string
	 */
	protected static $inlineRegexp;
	/**
	 * Base link to search page on <http://php.net> where to search for built-in
	 * PHP classes. Unlike built-in functions classes can't be resolved on
	 * php.net just by appending the name to the URL.
	 * @var string
	 */
	protected $phpNetObjectLink = 'http://php.net/manual-lookup.php?pattern=';
	/**
	 * Base link to <http://php.net>. When built-in functions are found the
	 * function name is appended to this.
	 * @var string
	 */
	protected $phpNetFuncLink = 'http://php.net/';
	/**
	 * The {@link Syntaxer} object to use for code highlighting.
	 * @var Syntaxer
	 */
	protected $stx;
	/**
	 * The {@link STXMarkdown} object to use for highlighting tutorial files.
	 * @var STXMarkdown
	 */
	protected $stxmd;
	/**
	 * Don't bother reflect these datatypes
	 * @var array
	 */
	protected $skipType = array(
		'string', 'int', 'bool', 'boolean', 'float', 'mixed', 'object',
		'array', 'resource', 'null', 'void'
	);
	/**
	 * Stores parsed example files so we don't need to parse the same file
	 * more than once.
	 * @var array
	 */
	protected static $parsedExamples = array();
	/**
	 * Stores reflected functions, classes and so on.
	 * @var array
	 */
	protected static $parsedRefs = array(
		"classes"   => array(),
		"functions" => array(),
		"constants" => array()
	);
	/**
	 * Syntaxer object to use other than the default
	 * @var Syntaxer
	 */
	public static $syntaxer = null;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		if (!self::$syntaxer) {
			$this->stx = new Syntaxer('php');
			$this->stx->SetLineWrapper('', "<br/>\n");
			$this->stx->HTMLEmbedded(false);
		}
		else
			$this->stx = self::$syntaxer;

		$this->stxmd = new STXMarkdown($this->stx->GetStxPath(),
		                               $this->stx->GetStxCachePath());
		$this->keywords = DocblockParser::Keywords();

		if (!self::$strKeywords)
			self::$strKeywords = implode('|', $this->keywords);

		if (!self::$inlineRegexp) {
			self::$inlineRegexp = '\\' . DocblockParser::INLINE_BEGIN .
			                      '('  . self::$strKeywords . ')\s+(.*?)' .
			                      '\\' . DocblockParser::INLINE_END;
		}
	}

	/**
	 * Parse a docblock tag.
	 *
	 * @uses DocblockTagParser::$tag
	 * @uses DocblockTagParser::$value
	 * @param string $tag
	 * @param string $value
	 * @return mixed
	 */
	public function Parse($tag, $value)
	{
		$this->tag   = $tag;
		$this->value = $value;

		$ret = $value;

		switch ($this->tag) {
			// Parse param
			case 'param':
				$ret = array();
				if (is_array($this->value))
					foreach ($this->value as $param)
						$ret[] = $this->parseParam($param);
				else
					$ret[] = $this->parseParam($this->value);
				break;

			case 'usedby':
			case 'uses':
			case 'depends':
				$ret = array();
				if (is_array($this->value))
					foreach ($this->value as $dep)
						$ret[] = $this->parseTag($dep);
				else
					$ret[] = $this->parseTag($this->value);

				break;

			case 'author':
			case 'copyright':
				$ret = array();
				if (is_array($this->value))
					foreach ($this->value as $dep)
						$ret[] = $this->parseDescription($dep, false);
				else
					$ret[] = $this->parseDescription($this->value, false);

				break;

			//! Default/deprecated
			default:
			case 'deprecated':
				$ret = trim($this->parseDescription($this->value, false));
				break;

			// Parse description an such
			case 'todo':
			case 'description':
				$ret = trim($this->parseDescription($this->value));
				break;

			case 'example':
        // It's an assigment
				if ($d = DocblockParser::StaticFilesLocation('EXAMPLE_SOURCE')) {
          if (is_array($value)) {
            $ret = array();
            foreach ($value as $xmpl)
              $ret[] = $this->parseExampleFile("$d/$xmpl");
          }
          else
            $ret = $this->parseExampleFile("$d/$value");
				}
				break;

			case 'link':
				if (!is_array($value)) {
					if (is_array($value)) {
						$ret = array();
						foreach ($value as $v)
							$ret[] = $this->parseDescription("{@$tag $v}", false);
					}
					else
						$ret = $this->parseDescription("{@$tag $value}", false);
				}
				break;

			// Parse return, see ...
			case 'see':
			case 'throws':
			case 'return':
				$ret = $this->parseTag($value);
		}

		return $ret;
	}

  /**
   * Parse an example file
   * @param string $file
   * @return string
   */
  protected function parseExampleFile($file)
  {
    $value = basename($file);
    if (file_exists($file)) {
      $rd = DocblockParser::StaticFilesLocation('EXAMPLE_RESULT');
      if (!$rd) {
        if (PLib::$VERBOSE) {
          cwrite(
            "%s The path for example sources is set but the path where " .
            "to put the resulting files is not! Example files can't be " .
            "pre-parsed!", "  PURPLE:WARNING:NONE:"
          );
        }
        $value = null;
        break;
      }

      if ($value && !in_array($value, self::$parsedExamples)) {
        PLib::wdebug(
          "  GREEN:Parsing example file:NONE: $value"
        );
        array_push(self::$parsedExamples, $value);
        $this->stx->Parse(file_get_contents($file));
        file_put_contents("$rd/$value", $this->stx->GetBuffer());
      }
    }
    else {
      PLib::wdebug("  PURPLE:WARNING:NONE: Example file $value doesn't ".
                   "exist!");
    }

    return $value;
  }

	/**
	 * Returns the {@see DocblockParser::$parsedRefs} array.
	 * Only useful for debugging.
	 *
	 * @return array
	 */
	public static function GetRefs()
	{
		return self::$parsedRefs;
	}

	/**
	 * Parse a generic docblock tag that can have:
	 * Datatype[|Datatype] Description
	 *
	 * @param string $data
	 * @return array
	 */
	protected function parseTag($data)
	{
		$data  = trim($data);
		#$dlen  = strlen($data);
		$rtype = array();
		$desc  = null;

		// Capture 1 is the return type/s and 2 the description if any
		if (preg_match('/([_.a-z0-9&:|\(\)]+)?\s*(.*?)$/si', $data, $m)) {
			if (!empty($m[1])) {
				foreach (explode('|', $m[1]) as $t) {
					$t = trim($t);
					if (!preg_match('/^[_a-z0-9:\(\)]+$/i', $t)) {
						if (PLib::$VERBOSE) {
							cwrite(
								"  BROWN:NOTE:NONE:    Skipping malformed docblock return type: ".
								"GRAY:$t"
							);
						}
						cwrite($t);
						$rtype[] = $data;
						continue;
					}
					if (!in_array(strtolower($t), $this->skipType)) {
						if (strpos($t, ':') === false)
							$rtype[] = $this->reflectClass($t, null, 'class');
						else
							$rtype[] = $this->parseReference($data, false);
					}
					else
						$rtype[] = $t;
				}
			}
			if (!empty($m[2]))
				$desc = trim($this->parseDescription($m[2]));
		}
		else
			$rtype = array('void');

		return array(
			'type' => $rtype,
			'description' => $desc
		);
	}

	/**
	 * Parse a docblock param tag.
	 *
	 * @param string $param
	 * @return array
	 * Has the following indexes
	 *
	 * * type      => array with input type|s
	 * * variable  => name of the variable
	 * * descition => need explaination? ;)`
	 *
	 * So: `(at)param string|int $arg The input thing`<br/>
	 * would reult in:
	 *
	 * <pre>array(
	 *   'type' => array(0 => 'string', 1 => 'int'),
	 *   'variable => '$arg',
	 *   'description' => 'The input thing'
	 * )</pre>
	 */
	protected function parseParam($param)
	{
		$param    = trim($param);
		$plen     = strlen($param);
		$varpos   = strcspn($param, '&$');
		if ($varpos == $plen) $varpos = strcspn($param, DocblockParser::WHITES);
		$type     = substr($param, 0, $varpos);
		$rest     = substr($param, $varpos);
		$whitepos = strcspn($rest, DocblockParser::WHITES);
		$var      = substr($rest, 0, $whitepos);
		$desc     = $this->parseDescription(trim(substr($rest, $whitepos)));
		#cwrite("[RED:[{$type}NONE:] [BLUE:{$var}NONE:] [GREEN:{$desc}NONE:]");
		$atype = array();

		foreach (explode('|', $type) as $t) {
			$t = trim($t);
			if (!preg_match('/^[_a-z0-9]+$/i', $t)) {
				if (PLib::$VERBOSE) {
					cwrite(
						"  BROWN:NOTE:NONE:    Skipping malformed docblock param: GRAY:$t"
					);
				}
				$atype[] = $param;
				continue;
			}
			if (!in_array($t, $this->skipType)) {
				if (!array_key_exists($t, self::$parsedRefs['classes'])) {
					$r = $this->reflectClass($t, null, 'class');
					$atype[] = $r;
					self::$parsedRefs['classes'][$t] = $r;
				}
				else
					$atype[] = self::$parsedRefs['classes'][$t];
			}
			else
				$atype[] = $t;
		}

		return array(
			'type'        => $atype,
			'variable'    => trim($var),
			'description' => trim($desc)
		);
	}

	/**
	 * Parse description. Can either be the full description part of a docblock
	 * or description of a param or return type or what ever.
	 *
	 * @param string $desc
	 * @param bool $topara
	 *   Set to false if the data shouldn't be run through {@link Markdown()}
	 * @return string
	 */
	protected function parseDescription($desc, $topara=true)
	{
		if (!strlen($desc))
			return null;

		$desc = preg_replace_callback(
			"/" . self::$inlineRegexp . "/sim",
			array($this, 'inlineCallback'),
			$desc
		);

		if ($topara)
			return $this->toPara($desc);

		return $desc;
	}

	/**
	 * Applies {@link Markdown()} formatting and {@link Syntaxer
	 * syntax highlighting} to $in
	 *
	 * @param string $in
	 * @return string
	 */
	protected function toPara($in)
	{
		$in = preg_replace_callback(
			'#<code>(.*?)</code>#is',
			array($this, 'fixCodeBlock'),
			$in
		);
		return Markdown($in);
	}

	/**
	 * Callback function to regexp pattern matching in {@link
	 * DocblockTagParser::toPara()}
	 *
	 * @param array $m
	 * @return string
	 */
	protected function fixCodeBlock($m)
	{
		$ret = null;
		if (strlen($m[1])) {
			$ret = trim($m[1], "\n\r");
			$this->stx->Parse($ret);
			$ret = "<div class='code'><code>" .
			       $this->stx->GetBuffer() .
			       "</code></div>";
		}

		return $ret ? $ret : $m[0];
	}

	/**
	 * Callback function for regexp matching inline tags. {@link
	 * DocblockTagParser::parseDescription()}
	 *
	 * @param array $m
	 * @return string
	 */
	protected function inlineCallback($m)
	{
		if (!isset($m[1]) || !isset($m[2]))
			return;

		$link = null;
		switch ($m[1]) {
			case 'see':
			case 'link':
				if (strpos($m[2], '://') !== false)
					$link = $this->parseURL($m[2]);
				else
					$link = $this->parseReference($m[2]);
				break;
		}

		return $link ? $link : $m[0];
	}

	/**
	 * Parse a reference. A reference is for instance a class or function in
	 * contrast to regular URL. @see print() is a reference for instance.
	 *
	 * @param string $ref
	 * @param bool $scan
	 *   When not called from an inline tag don't bother with the first regexp.
	 *   For instance when parsing see, depends and tags like that
	 * @return string
	 */
	protected function parseReference($ref, $scan=true)
	{
		$link = null;
		$func = $ref;
		$text = null;

		if (preg_match('#(.[^\s]*)\s*(.*)?#ims', $ref, $m) || !$scan)
			list(, $func, $text) = $m;

		// Here we try to find out what it is being referenced. If we find a
		// double colon it's most certainly a class. We then need to find out
		// what the right side of :: is:
		//
		//   o A member if prefixed with a $
		//   o A method if we find a (
		//   o A class constant if none of the above is met.
		//
		// If we don't find a double colon it can either be a class or a function
		// but that we don't care about here. We just pass it on to
		// DocblockTagParser::reflectClass and let that method take care of it...
		if (strpos($func, '::')) {
			list($class, $method) = explode('::', $func);
			if ($method[0] == '$')
				$link = $this->reflectClass($class, $method, 'member');
			elseif (strpos($method, '('))
				$link = $this->reflectClass($class, $method, 'method');
			else
				PLib::wdebug("  BBLUE:Class constant: $ref");
		}
		else {
			$link = $this->reflectClass($func, null, 'class', $text);
		}
		return $link;
	}

	/**
	 * Creates a clickable link from an URL.
	 *
	 * @param string $url
	 * @return string
	 */
	protected function parseURL($url)
	{
		preg_match('#([a-z]+://.[^\s]*)\s*(.*)?#im', $url, $m);
		list(, $url, $link) = $m;

		if (empty($link)) {
			preg_match('#://(.*)#', $url, $m);
			$link = $m[1];
		}
		return "<a href='$url'>$link</a>";
	}

	/**
	 * Tries to introspect a function or a constant.
	 *
	 * If {@link DocblockTagParser::reflectClass()} cathes an exception, i.e
	 * what was passed to it wasn't a class, it will pass the argument here
	 * instead so we can ceck if it's a function, constant or what ever. If we
	 * fail here we just ignore it, no need to throw an exception since it might
	 * just be a typo or what ever in the docblock.
	 *
	 * @todo We only handle functions here right now. Need to also handle
	 * constants.
	 * @param string $what
	 * @param string $linktext
	 * @return string
	 */
	protected function reflectOther($what, $linktext=null)
	{
		if (array_key_exists($what, self::$parsedRefs['functions']))
			return self::$parsedRefs['functions']['what'];

		$link = $what;

		try {
			$pos = strpos($what, '(');
			$nwhat = $pos === false ? $what : substr($what, 0, $pos);
			$ref = new ReflectionFunction($nwhat);

			$fn = array();
			$fn['internal'] = $ref->isInternal();
			$fn['name']     = $ref->getName();
			$fn['line']     = $ref->getStartLine();

			if ($fn['internal'])
				$url = $this->phpNetFuncLink . $fn['name'];
			else
				$url = '?__plibfunction=' . $fn['name'] . '#function-' . $fn['name'];

			$link = "<a href='$url'>" . ($linktext ? $linktext : $what) . '</a>';
			self::$parsedRefs['functions'][$what] = $link;
		}
		catch (ReflectionException $ex) {
			if (PLib::$VERBOSE)
				PLib::wdebug("  RED:ERROR:NONE:   " . $ex->getMessage() . " ($what, $linktext)");
		}

		return $link;
	}

	/**
	 * Tries to introspect a class and it's members, methods and constants. If we
	 * fail we relay to {@link DocblockTagParser::reflectOther()}.
	 *
	 * @param string $class
	 *   Name of the class
	 * @param string $instance
	 *   Name of member, method or constant
	 * @param string $type
	 *   Context (class|member|method|constant)
	 * @param string $linktext
	 *   The description part, if any, of a tag.
	 * @return string
	 */
	protected function reflectClass($class, $instance, $type, $linktext=null)
	{
		if (strpos($class, '(') !== false)
			return $this->reflectOther($class, $linktext);

		$link = $lnk = $class . "::" . $instance;

		if (array_key_exists($link, self::$parsedRefs['classes']))
			return self::$parsedRefs['classes'][$link];

		try {
			$cls = array();
			$ref = new ReflectionClass($class);
			$cls['internal'] = $ref->isInternal();
			$cls['name']     = $ref->getName();
			$cls['file']     = $ref->getFileName();
			$cls['line']     = $ref->getStartLine();

			$mem = array(
				'name'     => null,
				'internal' => null,
				'file'     => null,
				'line'     => null
			);

			switch ($type) {
				case 'method':
					$ninstance = substr($instance, 0, strpos($instance, '('));
					if ($ref->hasMethod($ninstance)) {
						$meth            = $ref->getMethod($ninstance);
						$mem['name']     = $meth->getName();
						$mem['internal'] = $meth->isInternal();
						$mem['file']     = $meth->getFileName();
						$mem['line']     = $meth->getStartLine();
					}
					break;

				case 'member':
					$inst = ltrim($instance, '$');
					if ($ref->hasProperty($inst)) {
						$prop = $ref->getProperty($inst);
						$mem['name'] = $prop->getName();
						$mem['line'] = null;
						$mem['file'] = null;
						$mem['internal'] = null;
					}
				default:
					break;
			}

			if ($cls['internal']) {
				$url = $this->phpNetObjectLink;
				if ($type == 'class') {
					$url .= $cls['name'];
					$link = $cls['name'];
				}
				else {
					$url  .= urlencode($cls['name'] . " " . $mem['name']);
					$link = $cls['name'] . '::' . $instance;
				}
				$url .= "&amp;lang=en";
			}
			else {
				if ($type == 'class') {
					$url  = '?__plibclass=' . $cls['name'] . "#class-" . $cls['name'];
					$link = $cls['name'];
				}
				else {
					$_type = $type;
					if ($_type == 'member')
						$_type = 'property';

					$url  = '?__plibclass=' . $cls['name'] . "#$_type-" . $mem['name'];
					$link = $cls['name'] . "::$instance";
				}
			}

			$link = "<a href='$url'>$link</a>";
			self::$parsedRefs['classes'][$lnk] = $link;
		}
		catch (ReflectionException $ex) {
			if (PLib::$VERBOSE)
				PLib::wdebug("  BROWN:NOTICE:NONE:  " . $ex->getMessage() . ". " .
				             "Might be a function...");

			try {
				// Not a class, see if it's a function...
				$link = $this->reflectOther($class, $linktext);
			}
			catch (Exception $e) {
				if (PLib::$VERBOSE)
					PLib::wdebug("  BRED:Error again:NONE:   " . $e->getMessage());
			}
		}
		return $link;
	}
}
?>