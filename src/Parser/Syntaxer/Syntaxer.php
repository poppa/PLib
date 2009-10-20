<?php
/**
 * This is a generic syntax highlighting script. The script highlights
 * virtually any language as long as there is a corresponding file
 * mapping the syntax. The mapping of a syntax is done by using a .stx
 * file originating from the brilliant text editor
 * {@link http://www.editplus.com Edit+}
 *
 * So if you miss highlighting for a language, go to
 * {@link http://editplus.com/files.html Edit+ files} and see if there is a
 * syntax file for your requested language and if so just drop that file
 * in the directory where the other .stx files are located.
 *
 * NOTE! Some features in the Edit+ syntax files are discarted in this script
 * and some other features are added. Check out the bundled php.stx file as
 * reference.
 *
 * @author  Pontus Östlund <pontus@poppa.se>
 * @version 2.0.5
 * @license GPL License 2
 * @package Parser
 * @uses StreamReader
 */

/**
 * Constant that can be useful if this script is being used in some other
 * text processing script like Markdown or Textile or something like that.
 */
define('SYNTAXER', 1);
/**
 * Sytaxer version constant.
 */
define('SYNTAXER_VERSION', '2.0.5');

/**
 * The StreamReader is used when loading and creating the syntax maps
 */
require_once PLIB_INSTALL_DIR . '/IO/StreamReader.php';

/**
 * This is a generic syntax highlighting script.
 * The script highlights virtually any language as long as there is a
 * corresponding file mapping the syntax. The mapping of a syntax is done by
 * using a .stx file originating from the brilliant text editor
 * {@link http://www.editplus.com Edit+}
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Parser
 * @subpackage Syntaxer
 */
class Syntaxer
{
	/**
	 * Tabsize, how much to indent
	 * @var int
	 */
	public $tabsize = 2;
	/**
	 * Line end. What character is a newline
	 * @var string
	 */
	public $newline = "\n";
	/**
	 * Space chacrater
	 * @var string
	 */
	public $space = '&#160;';
	/**
	 * Entity for &
	 * @var string
	 */
	public $ampersand = '&#38;';
	/**
	 * How to encode HTML/XML entities
	 * @var string
	 */
	public $mode = 'xhtml';
	/**
	 * Override the html_embedded instruction in the syntax file
	 * @var bool
	 */
	public $is_embedded;
	/**
	 * Number of lines
	 * @var int
	 */
	private $lines = 0;
	/**
	 * The size of the code in bytes
	 * @var int
	 */
	private $size = 0;
	/**
	 * Buffer string. This is where we append the processed data
	 * @var string
	 */
	private $_buffer = '';
	/**
	 * What syntax (or language rather) to parse
	 * @var string
	 */
	private $_lang;
	/**
	 * Has any error occured?
	 * @var bool
	 */
	private $_error = false;
	/**
	 * The syntax map
	 * @var SyntaxMap
	 */
	private $_map;
	/**
	 * The data to parse
	 * @var string
	 */
	private $data;
	/**
	 * The space character (n) tab size times
	 * @var string
	 */
	private $tab;
	/**
	 * Array of colors
	 * @var array
	 */
	private $colors;
	/**
	 * Array of white space character
	 * @var array
	 */
	private $white;
	/**
	 * Array of delimiter characters
	 * @var array
	 */
	private $delimiters;
	/**
	 * Whitespace and delimter arrays combined.
	 * @var array
	 */
	private $stop_chars;
	/**
	 * Array of keywords
	 * @var array
	 */
	private $keywords;
	/**
	 * Array of line comment sequences
	 * @var array
	 */
	private $line_comments;
	/**
	 * Array of opening sequences for block comments
	 * @var array
	 */
	private $comment_on;
	/**
	 * Array of closing sequences for block comments
	 * @var array
	 */
	private $comment_off;
	/**
	 * Array of quote charcters
	 * @var array
	 */
	private $quotes;
	/**
	 * Array of variable prefixes
	 * @var array
	 */
	private $prefix;
	/**
	 * Escape character
	 * @var string
	 */
	private $escape;
	/**
	 * Sequence for script start of HTML embedded languages.
	 * For instance <? in PHP or <% in ASP
	 * @var string
	 */
	private $script_begin;
	/**
	 * Sequence for script end of HTML embedded languages
	 * For instance ?> in PHP or %> in ASP
	 * @var string
	 */
	private $script_end;
	/**
	 * Array of arbitrary opening styling instructions
	 * @var array
	 */
	private $style_open;
	/**
	 * Array of arbitrary closing styling instructions
	 * @var array
	 */
	private $style_close;
	/**
	 * The characters to replace with $html_ent
	 * @var array
	 */
	private $html_chars = array('<','>','\'','"');
	/**
	 * XML/HTML entites we should convert $html_chars into
	 * @var array
	 */
	private $html_ent;
	/**
	 * Shall we look for prefixed variables
	 * @var bool
	 */
	private $has_prefix = false;
	/**
	 * How to wrap the lines
	 * @var array
	 */
	private $line_wrap = array('<li>',"</li>\n");
	/**
	 * The index is the name of the .stx file.
	 * The values are arrays of extensions that goes with the .stx
	 * @static array
	 */
	static $alias = array(
		'ruby'   => array('rb', 'rbc'),
		'php'    => array('php3','php4','php5'),
		'asp3'   => array('asp'),
		'shell'  => array('bash','sh'),
		'as2'    => array('as'),
		'pike'   => array('pike','pmod'),
		'python' => array('py')
	);

	/**
	 * Constructor
	 *
	 * @param string $lang
	 * @param string $path
	 * @param string $cachepath
	 */
	public function __construct($lang, $path=null, $cachepath=null)
	{
		$this->_lang        = $lang;
		$this->_map         = new SyntaxMap($path, $cachepath);
	}

	/**
	 * Return the processed and highlighted code
	 * @return string
	 */
	public function GetBuffer()
	{
		return $this->_buffer;
	}

	/**
	 * Return the number of lines we processed
	 * @return int
	 */
	public function GetLines()
	{
		return $this->lines;
	}

	/**
	 * Return the size of the code in bytes
	 * @return int
	 */
	public function GetSize()
	{
		return $this->size;
	}

	/**
	 * Get the laguage processed
	 * @return string
	 */
	public function GetLanguage()
	{
		$l = $this->_map->getMapValue('_PREFS', 'title');
		if (!$l) $l = $this->_lang;
		return $l;
	}

	/**
	 * Set the line wrappers
	 *
	 * @param string $prepend
	 * @param string $append
	 */
	public function SetLineWrapper($prepend, $append)
	{
		$this->line_wrap = array($prepend, $append);
	}

	/**
	 * Returns the path to the syntax files
	 *
	 * @since 2.0.5
	 * @return string
	 */
	public function GetStxPath()
	{
		return $this->_map->GetStxPath();
	}

	/**
	 * Return the path to the PHP-ified .stx file
	 *
	 * @since 2.0.5
	 * @return string
	 */
	public function GetStxCachePath()
	{
		return $this->_map->GetStxCachePath();
	}

	/**
	 * Add an alias to the alias array
	 *
	 * @since 2.0.3
	 * @param string $extension
	 * @param string|array $alias
	 * @return void
	 */
	public static function AddAlias($extension, $alias)
	{
		if (isset(self::$alias[$extension])) {
			$a =& self::$alias[$extension];
			if (is_array($a)) {
				if (!is_array($alias)) {
					if (!in_array($alias, $a))
						array_push($a, $alias);
				}
				else
					$a = array_merge($a, $alias);
			}
			else {
				if (is_array($alias))
					$a = array_push($alias, $a);
				else
					$a = array($a, $alias);
			}
		}
	}

	/**
	 * Set wether we should treat the language as HTML embedded or not?
	 * This value can be set in the .stx file but we want to override that
	 * setting is some circumstances - like highlighting code snippets in a
	 * forum or something like that.
	 *
	 * @param bool $bool
	 */
	public function HTMLEmbedded($bool)
	{
		$this->is_embedded = $bool;
	}

	/**
	 * Find what syntax file to use for an alias (extension).
	 *
	 * @param string $ext
	 * @return string
	 */
	public static function Alias($ext)
	{
		foreach (Syntaxer::$alias as $key => $val)
			if (in_array($ext, $val) || $key == $ext)
				return $key;

		return $ext;
	}

	/**
	 * Try to autodetect what syntax file to use from a file path
	 * First whe check agains the the extension, if no match there or if there
	 * simply is no extension we check for a shebang. If nothing is found we
	 * just pass back what was passed in.
	 *
	 * @param string $file
	 * @return string
	 */
	public static function AutoDetect($file)
	{
		$f = @pathinfo($file);
		$stx = null;
		if (isset($f['extension']))
			if ($stx = Syntaxer::Alias($f['extension'])) // It's an assigment
				return $stx;
			else
				return $f['extension'];

		//! No extension found, look for shebang
		$fh = @fopen($file, 'r');
		if (!is_resource($fh))
			return issetor($f['extension'], $file);

		$fl = @fread($fh, 128);
		fclose($fh);

		if (!$fl)
			return $file;

		//! Find the interpreter in a shebang in any of the following formats
		//! (using Ruby as an example):
		//!
		//!     #!ruby
		//!     #!/what/ever/path/to/ruby
		//!     #!/usr/bin/env ruby
		//!
		//! Ruby will be matched in any of these cases
		$re = '%^#!([a-z0-9]+)|(?:/[a-z0-9]+)*(?:/([a-z0-9]+))\s*([a-z0-9]+)?%i';
		if (!preg_match($re, $fl, $m))
			return $file;

		return Syntaxer::Alias(issetor($m[2], $m[1]));
	}

	/**
	 * Parse the code
	 * @param string $data
	 */
	public function Parse($data)
	{
		//! Load the syntax map
		if ($this->_map)
			$this->_map->load($this->_lang);

		$map        = $this->_map;
		$this->data = str_replace(array("\r\n","\r"), array("\n",''), $data);

			//! Setup some defaults
		$this->white = array(" ", "\t", "\n");
		if (($tabsize = $map->getMapValue('_PREFS', 'indentation')) !== false) {
			$this->tabsize = $tabsize;
			unset($tabsize);
		}
		$this->tab           = str_repeat($this->space, $this->tabsize);
		$this->escape        = $map->getMapValue('_PREFS', 'escape');
		$this->colors        = $map->getMap('_COLOR');
		$this->delimiters    = $map->getMap('_DELIMS');
		$this->keywords      = $map->getMap('_KEYWORDS');
		$this->line_comments = $map->collect('linecomment');
		$this->comment_on    = $map->collect('commenton');
		$this->comment_off   = $map->collect('commentoff');
		$this->quotes        = $map->collect('quotation');
		$this->prefix        = $map->collect('prefix', true, false);
		$this->style_open    = $map->collect('style_open_', true);
		$this->style_close   = $map->collect('style_close_', true);
		$this->stop_chars    = array_merge($this->delimiters, $this->white);
		$html                = $map->getMapValue('_PREFS', 'html_embedded');
		$html                = $html == 'y' ? true : false;

		if (empty($this->escape))
			$this->escape = false;

		if (sizeof($this->prefix) > 0)
			$this->has_prefix = true;

		if ($this->mode == 'xhtml')
			$this->html_ent = array('&#60;', '&#62;','&#39;', '&#34;');
		else {
			$this->html_ent  = array('&lt;','&gt;','&apos;','&quot;');
			$this->space     = '&nbsp;';
			$this->ampersand = '&amp;';
		}

		//! Shall we initially highlight or not?
		if (!is_bool($this->is_embedded))
			$highlight = $html ? false : true;
		else {
			$highlight = !$this->is_embedded;
			$html      = $this->is_embedded;
		}

		if ($html) {
			$this->script_begin = $map->getMapValue('_PREFS', 'script_begin');
			$this->script_end = $map->getMapValue('_PREFS', 'script_end');
		}

		$offset   = -1;
		$data_len = $this->size = strlen($data);

		//! Reset these members if the object is being reused
		$this->lines   = 0;
		$this->_buffer = "";
		$this->_error  = false;

		//! This is the buffer where we store a line of code
		$line = null;

		//! ########################################################################
		//!
		//!     Begin loop through the data
		//!
		//! ########################################################################
		while (++$offset < $data_len) {
			$char = $data[$offset];

			if (preg_match('/\s/', $char)) {
				switch ($char)
				{
					case "\t":
						$char = $this->tab;
						break;

					case " ":
						$char = $this->space;
						break;
				}

				if ($char == "\n") {
					$this->appendLine($line);
					$line = null;
				}
				else
					$line .= $char;

				continue;
			}

			//! If we're dealing with an HTML embedded language and havn't yet
			//! found an opening script tag we look for it.
			if ($html && !$highlight) {
				$os = substr($data, $offset, strlen($this->script_begin));
				if ($os == $this->script_begin) {
					$highlight = true;
					$line .= $this->highlight($this->HTMLChars($os), 'script_begin');
					//! Lets append the opening tag and move on.
					//! We need to increment the offsets with the length of the
					//! opening tag. The same goes for the closing tag below
					$len     = strlen($os)-1;
					$offset += $len;
					unset($len);
				}
				else
					$line .= $this->HTMLChars($char);

				unset($os);
				continue;
			}
			//! We're inside the script tag, lets see if it's time to break out
			elseif ($html && $highlight) {
				$es = substr($data, $offset, strlen($this->script_end));
				if ($es == $this->script_end) {
					$highlight = false;
					$line .= $this->highlight($this->HTMLChars($es), 'script_end');
					$len = strlen($es)-1;
					$offset += $len;
					unset($es, $len);
					continue;
				}
				unset($es);
			}

			//! If we're touching a line comment we just grab what's left of the
			//! line and append that to the buffer.
			if ($this->isLineCmt($data, $offset)) {
				$nl = strpos($data, "\n", $offset);
				//! If no \n was found we're athe the end of file
				if (!$nl) $nl = $data_len;
				$end = substr($data, $offset, $nl-$offset);
				$offset += strlen($end);
				$end = $this->toWhite($end);
				$line .= $this->highlight($this->HTMLChars($end), 'linecomment');
				$this->appendLine($line);
				$line = "";
				continue;
			}

			//! Check for beginning of a block comment.
			//! If we find it we get the array index of the opening comment type
			//! in the array of available block comments. The closing instruction
			//! should be at the same index in in the block comment close array.
      //!
      //! It's an assigment
			if (($bcl = $this->isBlockComment($data, $offset)) !== false) {
				$i       = $offset;
				$cc      = $this->comment_off[$bcl];
				$cc_len  = strlen($cc);
				$comment = $char;

				while (++$i < $data_len) {
					if (substr($data, $i, $cc_len) == $cc) {
						$comment .= substr($data, $i, $cc_len);
						break;
					}
					$comment .= $data[$i];
				}
				$offset += strlen($comment)-1;
				$clines = preg_split('/\n/', $comment);
				$cnt    = sizeof($clines);

				for ($i = 0; $i < $cnt; $i++) {
					$line .= $this->toWhite($clines[$i]);

					if ($i < $cnt-1) {
						$l = $this->highlight($this->HTMLChars($line), 'blockcomment');
						$this->appendLine($l);
						$line = '';
					}
				}
				$line = $this->highlight($this->HTMLChars($line),'blockcomment');
				continue;
			}

			//! Check for quote chars - ordinary strings
			if ($this->isQuoteChar($char, $offset)) {
				$i = $offset;
				$str = $char;

				while (++$i < $data_len) {
					$c    = $data[$i];
					$str .= $c;
					$prev = $data[$i-1];

					//! This is tricky:
					//! We're matching a closing quote but it preceeds by an escape
					//! charcater. That means we should'nt close the quote.
					//! But what is the preceeding escape character also is preceedes by
					//! an escape character?
					//!
					//! We loop backwards untli we don't find any consecutive escape
					//! chars and if we found an even number of escape chars we close
					//! the quote.
					//!
					//! This often happens in regexps:
					//!
					//!     $find    = array("\\", "'");
					//!     $replace = array("\\\\", "\'");
					//!
					if ($this->escape && $c == $char && $prev == $this->escape) {
						$k = 0;
						$j = $i-1;
						while ($data[--$j] == $this->escape)
							$k++;

						if ($k % 2) break;
					}
					elseif ($c == $char && ($prev != $this->escape || !$this->escape))
						break;
				}

				$offset += strlen($str)-1;
				$sl = preg_split("/\n/", $str);
				$cnt = sizeof($sl);

				for ($i = 0; $i < $cnt; $i++) {
					$l = $this->toWhite($sl[$i]);
					$line .= $this->highlight($this->HTMLChars($l), 'quote');

					if ($i < $cnt-1) {
						$this->appendLine($line);
						$line = '';
					}
				}
				continue;
			}

			//! Check for variable prefixes like $, @, % and alike that is used in
			//! languages like PHP, Ruby, Perl and so on.
			if ($this->has_prefix) {
				if (in_array($char, $this->prefix)) {
					$word = $char;
					$key = $this->getArrayKey($this->prefix, $char);

					while (++$offset < $data_len) {
						$c = $data[$offset];
						if (!in_array($c, $this->stop_chars))
							$word .= $c;
						else
							break;
					}

					$line .= $this->highlight($this->HTMLChars($word), $key);

					if (!in_array($c, $this->white))
						$line .= $this->highlight($this->HTMLChars($c), 'delimiter');
					else
						$offset--;

					continue;
				}
			}

			//! A delimiter. Higlight it, add it and move on
			if (in_array($char, $this->delimiters)) {
				$line .= $this->highlight($this->HTMLChars($char), 'delimiter');
				continue;
			}
			//! When nothing has been caught earlier on we should look for a
			//! key word, function and alike.
			//! We search from the current offset to the next delimiter or white
			//! space, and there we have our word!
			else {
				$word = $char;
				while (++$offset < $data_len) {
					$c = $data[$offset];
					if (!in_array($c, $this->stop_chars))
						$word .= $c;
					else
						break;
				}

				if (is_numeric($word))
					$line .= $this->highlight($word, 'numeric');
				else {
					$key = $this->getColorKey($word);
					if ($key)
						$line .= $this->highlight($this->HTMLChars($word), $key);
					else
						$line .= $this->HTMLChars($word);
				}

				//! When we're at EOF we break out of the loop and were done!
				if ($offset == $data_len)
					break;

				$char = '';
				if (!in_array($c, $this->white))
					$line .= $this->highlight($this->HTMLChars($c), 'delimiter');
				else
					$offset--;
			}

			$line .= $this->HTMLChars($char);
		}

		if (!empty($line))
			$this->appendLine($line);

		unset($this->data, $data_len, $data, $char, $line, $offset,
		      $html, $highlight);
	}

	/**
	 * Convertes tabs to spaces and spaces to HTML entities
	 *
	 * @param string $in
	 * @return string
	 */
	private function toWhite($in)
	{
		$in = str_replace("\t", $this->tab, $in);
		return str_replace(' ', $this->space, $in);
	}

	/**
	 * Highlight a word or a delimiter or what ever.
	 * We also check if the current $what should be styled in any other
	 * way. And if so add the styling
	 *
	 * @param string $what
	 * @param string $key
	 *     The $key to look for in the color array.
	 * @return string
	 */
	private function highlight($what, $key)
	{
		$style = $this->getStyle($key);

		//! Empty lines will collapse the li tag
		if (empty($what) && !is_numeric($what))
			$what = $this->space;

		if (!empty($style))
			$what = join($what, $style);

		$color = issetor($this->colors[$key], false);
		if (!$color)
			return $what;

		return "<span style='color: $color;'>$what</span>";
	}

	/**
	 * Collect the style to use., if anym for a specific keyword
	 *
	 * @param string $key
	 * @return array
	 */
	private function getStyle($key)
	{
		$so = issetor($this->style_open[$key], false);
		$sc = issetor($this->style_close[$key], false);

		if ($so && $sc)
			return array($so, $sc);

		return array();
	}

	/**
	 * Find what color key to use for a specific keyword.
	 * The keywords array is a multidimentional associative array where the
	 * key of the first level is the color key to use. The value of this key
	 * is in turn an assoc array where the key is the first character of
	 * the values. The reason for this is that we don't need to look through
	 * the entire keywords array to find the color key for a keyword we only
	 * need to look in the sub array that has a key the same as the first
	 * character in the word.
	 *
	 * @param string $word
	 * @return string|bool
	 */
	private function getColorKey($word)
	{
		$ch1 = $word[0];
		if (!preg_match('/[a-z]/i', $ch1))
			$ch1 = 'nochar';

		foreach ($this->keywords as $key => $val) {
			if (array_key_exists($ch1, $val)) {
				if (in_array($word, $val[$ch1]))
					if (isset($this->colors[$key]))
						return $key;
					else
						return 'reserved_words';
			}
		}

		return false;
	}

	/**
	 * Get the array key for an array that has a requested value in it.
	 *
	 * @param array $array
	 * @param mixed $value
	 * @return string|bool
	 */
	private function getArrayKey($array, $value)
	{
		foreach ($array as $k => $v)
			if ($v == $value)
				return $k;

		return false;
	}

	/**
	 * Turn a string into safe html
	 *
	 * @param string $what
	 * @return string
	 */
	private function HTMLChars($what)
	{
		$what = preg_replace('/(&)(?!#160;)/', $this->ampersand, $what);
		return str_replace($this->html_chars, $this->html_ent, $what);
	}

	/**
	 * Check if $char is a quote character or not
	 *
	 * @param string $char
	 * @param int $offset
	 *     The offset in the data string for the $char
	 * @return bool
	 */
	private function isQuoteChar($char, $offset)
	{
		foreach ($this->quotes as $quote)
			if ($char == $quote && $this->data[$offset-1] != $this->escape)
				return true;

		return false;
	}

	/**
	 * Check if we've hit a line comment or not
	 *
	 * @param string $data
	 *     This is the entire data string
	 * @param int $offset
	 *     See {@link Syntaxer::isQuoteChar()}
	 * @return bool
	 */
	private function isLineCmt($data, $offset)
	{
		foreach ($this->line_comments as $lc)
			if (substr($data, $offset, strlen($lc)) == $lc)
				return true;

		return false;
	}

	/**
	 * Check if we've hit a block comment or not
	 *
	 * @param string $data
	 *  See {@link Syntaxer::isLineCmt()}
	 * @param int $offset
	 *  See {@link Syntaxer::isQuoteChar()}
	 * @return int|bool
	 *  If we have indeed hit a block comment we return the array index
	 *  of where we found the opening comment sequence. The closing comment
	 *  sequence will be found at the same index in the
	 *  {@link Syntaxer::comment_off} array. This is due to the fact that
	 *  the opening and closing sequence of a block comment doesn't have
	 *  to have the same length.
	 */
	private function isBlockComment($data, $offset)
	{
		$i = 0;
		foreach ($this->comment_on as $oc) {
			if (substr($data, $offset, strlen($oc)) == $oc)
				return $i;

			$i++;
		}
		return false;
	}

	/**
	 * Append a line of code to the buffer
	 *
	 * @param string $what
	 * @return void;
	 */
	private function appendLine($what)
	{
		$this->lines++;
    if (!strlen($what)) $what = $this->space;
		$this->_buffer .= join($what, $this->line_wrap);
	}
}

/**
 * This class sets up the syntax maps.
 * We parse the Edit+ .stx files and turn them into PHP files
 * so that we don't have to do this process on every request.
 * If the .stx file has a newer timestamp than the corresponding
 * PHP file the PHP file will be regenerated
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Parser
 * @subpackage Syntaxer
 * @usedby Syntaxer
 */
class SyntaxMap
{
	/**
	 * Default location of the .stx files
	 * @var string
	 */
	private $_path = 'stx/';
	/**
	 * Default location of the .php syntax files
	 * @var string
	 */
	private $_cachepath = 'stx/phps/';
	/**
	 * What syntax are we about to load
	 * @var string
	 */
	private $_lang;
	/**
	 * Where we keep the values when we generate or regenerate
	 * a php syntax map
	 * @var array
	 */
	private $_KEYWORDS = array();
	/**
	 * Where we keep the preferences
	 * @var array
	 */
	private $_PREFS = array();
	/**
	 * Where we keep the colors
	 * @var array
	 */
	private $_COLOR = array();
	/**
	 * Where we keep the delimiters
	 * @var array
	 */
	private $_DELIMS = array();

	/**
	 * Constructor
	 *
	 * @param string $path
	 *  Path to the .stx files
	 * @param string $cachepath
	 *  Path to the generated .php files
	 * @throws SyntaxerIOError
	 * @return void
	 */
	public function __construct($path=null, $cachepath=null)
	{
		$this_dir = dirname(__FILE__);

		if ($path) {
			if (is_dir($path))
				$this->_path = rtrim($path,'/') . '/';
			else {
				throw new SyntaxerIOError(
					"The given path [$path] to the .stx files does not exist"
				);
			}
		}
		else
			$this->_path = $this_dir . DIRECTORY_SEPARATOR . $this->_path;

		if ($cachepath) {
			if (is_dir($cachepath)) {
				if (is_writable($cachepath))
					$this->_cachepath = rtrim($cachepath, '/') . '/';
				else {
					if (!@chmod($cachepath, 0777)) {
						throw new SyntaxerIOError(
							"The given cachepath [$cachepath] is not writable"
						);
					}
					$this->_cachepath = rtrim($cachepath, '/') . '/';
				}
			}
			else {
				throw new SyntaxerIOError(
					"The given cachepath [$cachepath] does not exist"
				);
			}
		}
		else
			$this->_cachepath = $this_dir . DIRECTORY_SEPARATOR . $this->_cachepath;

		//! Set some default colors
		//! Those can we over ridden in the stx-file.
		$this->_COLOR['default']            = '#000000';
		$this->_COLOR['reserved_words']     = '#000099';
		$this->_COLOR['built_in_functions'] = '#ff0000';
		$this->_COLOR['linecomment']        = '#0099CC';
		$this->_COLOR['blockcomment']       = '#0099CC';
		$this->_COLOR['delimiter']          = '#0000FF';
		$this->_COLOR['quote']              = '#008800';
		$this->_COLOR['numeric']            = 'purple';
	}

	/**
	 * Returns the path to the syntax files
	 *
	 * @since 2.0.5
	 * @return string
	 */
	public function GetStxPath()
	{
		return $this->_path;
	}

	/**
	 * Return the path to the PHP-ified .stx file
	 *
	 * @since 2.0.5
	 * @return string
	 */
	public function GetStxCachePath()
	{
		return $this->_cachepath;
	}

	/**
	 * Load and parse the .stx file.
	 * If the timestamp of .stx is newer than .php we regenerate
	 * the .php file.
	 *
	 * @param string $lang The laguage to load
	 * @throws SyntaxerIOError
	 * @return bool
	 */
	public function load($lang)
	{
		$this->_lang = $lang;
		$stxfile     = $this->_path . "$lang.stx";
		$stxphp      = $this->_cachepath . "$lang.php";
		if (!file_exists($stxfile)) {
			$stxfile = $this->_path . 'none.stx';
			$stxphp  = $this->_cachepath . 'none.php';
		}

		if (!file_exists($stxfile)) {
			throw new SyntaxerIOError(
				"Couldn't load any syntax file! The requested syntax file " .
				"'$lang.stx' was not found and neither was the fallback syntax file " .
				"'none.stx'. Check that the path '$this->_path' really exists'"
			);
		}

		//! Check if a generated php file exists and load it
		//! if so and the .stx file is not modified since the php
		//! file was generated.
		if (file_exists($stxphp) && (filemtime($stxphp) > filemtime($stxfile))) {
			require $stxphp;
			return true;
		}

		if (!is_writable($this->_cachepath)) {
			if (!@chmod($this->_cachepath, 0777)) {
				$m = "The cache directory '$this->_cachepath' is not writable and " .
				     "couldn't be made writable automatically";
				throw new SyntaxerIOError($m);
			}
		}

		//! The .stx file has sections like:
		//!
		//!		#KEYWORD=Properties
		//!		[...]
		//!		#KEYWORD=Reserved words
		//!		[...]
		//!
		//! When we're inside such a section all entities to that
		//! section will be placed in the same array. This variable
		//! tells whether or not we're inside a keyword section
		$in_keyword = false;

		//! The following entites exists in the .stx file
		//!
		//!		#COLOR:     What color should we use on an entity
		//!		#KEYWORD:   Is the entity a function a reserved word and so on.
		//!		#DELIMITER: Delimiters used in this laguage
		//!
		//! These variables will be written to the .php file
		$color_str     = '';
		$keyword_str   = '';
		$delimiter_str = '';
		$pref_str      = '';

		//! General escape chars
		$escape_chars  = array('\\');

		//! The stream reader that reads the .stx file line by line.
		$sreader = new StreamReader($stxfile);

		//! The current line
		$buffer = null;

		//! Loop through the .stx file line by line
		while (($buffer = $sreader->ReadLine()) !== false) {
			//! We skip comments and empty lines
			if (preg_match('/(?:^;)|(?:^\s*$)/', $buffer))
				continue;

			//! Match a keyword of any kind in the .stx
			if (preg_match('/^#/', $buffer)) {

				//! Match: #[WORD]=[value]
				if (preg_match('/^#([_A-Z0-9]+)=(.*)/', $buffer, $match)) {
					$key = strtolower(trim($match[1]));
					$val = trim($match[2]);

					//! Match anything that starts with #COLOR
					//! In the .stx it looks something like:
					//!
					//!	#COLOR_PREDEFINED_CLASS_FUNCTION=ccffaa
					//!	#COLOR_PREFIX=990000
					//!
					//! What's after #COLOR_ will be the color key
					if (substr($key, 0, 5) == 'color') {
						$color_key = substr($key, 6);
						if (!(substr($val, 0, 1) == '#'))
							$val = "#$val";

						$this->_COLOR[$color_key] = $val;
						$color_str .=
						"\$this->_COLOR['$color_key'] = '$val';\n";
						unset($color_key, $match);
						continue;
					}

					//! Match #KEYWORD
					if ($key == 'keyword') {
						$in_keyword = true;
						$val = strtolower(preg_replace('/[-\s]+/', '_', $val));

						//! To speed up lookup later on each keyword
						//! will have a separate key in the keywords array
						$this->_KEYWORDS[$val] = array();
						unset($match);
						continue;
					}

					//! Okey, so we didn't match COLOR or KEYWORD
					//! so we're dealing with delimiters or other stuff
					//! and sice we did match #[WORD] we're no longer inside
					//! a keyword section.
					$in_keyword = false;

					if (empty($val)) {
						unset($match);
						continue;
					}

					//! Setup the delimiters
					if ($key == 'delimiter') {
						$delims = preg_split('//', $val, -1, PREG_SPLIT_NO_EMPTY);

						$find    = array("\\", "'");
						$replace = array("\\\\", "\'");
						foreach ($delims as $delim) {
							$delim = str_replace($find, $replace, $delim);
							$delimiter_str .= "'$delim',";
						}
						$delimiter_str = rtrim($delimiter_str, ',');
						$this->_DELIMS  = $delims;
						unset($find, $replace, $delim, $match, $delims);
						continue;
					}

					//! we're left with general preferences
					$escape_char = in_array($val, $escape_chars) ? '\\' : '';

					$this->_PREFS[$key] = $val;

					$val = str_replace("'", "\'", $val);
					$pref_str .=
						"\$this->_PREFS['$key'] = '{$escape_char}{$val}';\n";
					unset($escape_char, $match);
					continue;
				} //! Match: #[WORD]=[value]
				continue;
			}
			//! No match of #
			else {
				//! If we're inside a #KEYWORD we're dealing with
				//! built-in functions and such
				if ($in_keyword) {
					$tval = trim($buffer);

					//! Check for case sensitivity
					if (!(isset($cs)))
						$cs = $this->getMapValue('_PREFS','case');

					if ($cs == 'n')
						$tval = strtolower($tval);

					//! The ^ character is an escape character
					//! in the stx-file. Thus we remove it if it's there
					if (preg_match('/^\^/', $tval))
						$tval = substr($tval, 1);

					//! To speed the up the lookup we further split up the
					//! KEYWORDS array so that all keywords are grouped
					//! by their first character.
					if (preg_match("/^[a-z]/i", $tval[0])) {
						if (!(isset($this->_KEYWORDS[$val][$tval[0]])))
							$this->_KEYWORDS[$val][$tval[0]] = array();

						$tmparr =& $this->_KEYWORDS[$val][$tval[0]];
					}
					else {
						if (!(isset($this->_KEYWORDS[$val]['nochar'])))
							$this->_KEYWORDS[$val]['nochar'] = array();

						$tmparr =& $this->_KEYWORDS[$val]['nochar'];
					}
					array_push($tmparr, $tval);
					unset($tval);
				}
			}
		} //! while

		//! Set up value to write to file
		$puts  = "<?php\n// " . strtoupper($lang) . " SYNTAX FILE\n";
		$puts .=
		"// THIS FILE IS AUTO-GENERATED\n" .
		"// DO NOT EDIT THIS IF YOU WISH TO KEEP IT\n";
		$puts .=
		"// IF YOU WISH TO MAKE CHANGES EDIT THE .STX FILE INSTEAD.\n";
		$puts .= "\n/** Color definitions */\n";
		$puts .= $color_str;
		$puts .= "\n/** Preferenses */\n";
		$puts .= $pref_str;
		$puts .= "\n/** Delimiters */\n";
		$puts .= "\$this->_DELIMS = array($delimiter_str);\n";
		$puts .= "\n/** Built in functions and stuff */\n";

		$keywords = var_export($this->_KEYWORDS, true);
		$keywords = str_replace(array("\n","\t"," "), array('','',''), $keywords);

		$puts .= "\$this->_KEYWORDS = " . $keywords . ";\n";
		$puts .= "?>";

		//! Lets write the colleted values to the php file
		$php_fh = @fopen($stxphp, 'w');
		if (!(is_resource($php_fh)))
			throw new SyntaxerIOError("Couldn't create cache file [$stxphp]");

		if (!@fwrite($php_fh, $puts))
			throw new SyntaxerIOError("Couldn't write cache file [$stxphp]");

		@fclose($php_fh);

		unset($keywords, $puts, $php_fh, $pref_str, $color_str, $delimiter_str,
		      $keyword_str);

		return true;
	}

	/**
	 * Look-up a key from one of the arrays
	 *
	 * @param string $arr What array too look in
	 * @param string $val What key to look for
	 * @return string|bool
	 */
	public function getMapValue($arr, $val)
	{
		if (isset($this->{$arr}[$val]))
			return $this->{$arr}[$val];

		return false;
	}

	/**
	 * Return one of the arrays
	 *
	 * @param string $what
	 * @return array
	 */
	public function getMap($what)
	{
		if (isset($this->{$what}))
			return $this->{$what};

		return false;
	}

	/**
	 * Collect information about a group in the _PREFS array.
	 * A group could look like:
	 *
	 * <code>
	 * $_PREFS['linecomment'];
	 * $_PREFS['linecomment2'];
	 * $_PREFS['linecomment3'];
	 * </code>
	 *
	 * So here we collect everything starting with linecomment
	 * and put that into an array and return that array.
	 *
	 * @param string $begin
	 *   What the key in _PREFS should match
	 * @param boolean $assoc
	 *   Should we return a flat array or an associative.
	 * @param boolean $cut_begin
	 *   Should we keep what we match or throw it away
	 * @return array
	 */
	public function collect($begin, $assoc=false, $cut_begin=true)
	{
		$tmp = array();
		$len = strlen($begin);

		foreach ($this->_PREFS as $key => $val) {
			if(substr($key, 0, $len) == $begin) {
				if ($assoc) {
					$rest = $cut_begin ? substr($key, $len) : $key;
					$tmp[$rest] = $val;
				}
				else
					array_push($tmp, $val);
			}
		}
		return $tmp;
	}
}

/**
 * Exception class
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Parser
 * @subpackage Exceptions
 */
class SyntaxerIOError extends Exception
{
	public $message = "IO error from Syntaxer";
}
?>