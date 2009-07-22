<?php
/**
 * PHP Command Line Interface helper functions
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.2
 * @since PLib 0.1.8
 * @package PLib
 * @subpackage CLI
*/

if (PHP_SAPI == 'cli') {

/**
 * Clears the cli color
*/
define('PLIB_CLI_NO_COLOR', "\033[0m");
/**
 * Red foreground text
*/
define('PLIB_CLI_RED',      "\033[31m");
/**
 * Light gray foreground text
*/
define('PLIB_CLI_GRAY',     "\033[37m");
/**
 * Blue foreground text
*/
define('PLIB_CLI_BLUE',     "\033[34m");
/**
 * Purple foreground text
*/
define('PLIB_CLI_PURPLE',   "\033[35m");
/**
 * Green foreground text
*/
define('PLIB_CLI_GREEN',    "\033[32m");
/**
 * Brownish foreground text
*/
define('PLIB_CLI_BROWN',    "\033[33m");
/**
 * Black foreground text
*/
define('PLIB_CLI_BLACK',    "\033[1m");
/**
 * Color keyword pattern
*/
define('PLIB_CLI_CLR_RE', "/(B)?(PURPLE|RED|GREEN|BLUE|GRAY|BROWN|BLACK|".
                          "NONE):/i");

if (!function_exists('cwrite')) {
	/**
	 * Searches the output for colorizing instructions and colorize the output
	 * to the cli if pattern matching occurs.
	 *
	 * Example: `This is a RED:red word`<br/>
	 * Result: This is a <span style='color:red'>red word</span>
	 *
	 * If bold text is desired prefix the COLOR with a B.
	 *
	 * Example: `This is a BRED:bold red wordNONE: I promise`<br/>
	 * Result: This is a <span style='color:red'><b>bold red word</b></span>
	 * I promise
	 *
	 * To stop colorized output just write NONE: (see the example above).
	 *
	 * __AVAILABLE COLORS:__
	 *
	 * * `RED    | BRED`
	 * * `GRAY   | BGRAY`
	 * * `BLUE   | BBLUE`
	 * * `PURPLE | BPURPLE`
	 * * `GREEN  | BGREEN`
	 * * `BLACK  | BBLACK`
	 *
	 * @param mixed $args
	 *   Works like {@link sprintf()}
	*/
	function cwrite($args=null)
	{
		$args = func_get_args();
		$msg = null;

		if (sizeof($args)) {
			$msg = array_shift($args);
			if (sizeof($args))
				$msg = vsprintf($msg, $args);
		}

		$msg = preg_replace_callback(PLIB_CLI_CLR_RE, '_cli_colorize', $msg);
		wnl($msg);
		echo PLIB_CLI_NO_COLOR;
	}
}

/**
 * Exactly like {@see cwrite()} except `cprint` doesn't print a newline.
 *
 * @since plibcli 0.2
 * @param mixed $args
 *   See {@link sprintf()}
*/
function cprint($args=null)
{
		$args = func_get_args();
		$msg = null;

		if (sizeof($args)) {
			$msg = array_shift($args);
			if (sizeof($args))
				$msg = vsprintf($msg, $args);
		}

		$msg = preg_replace_callback(PLIB_CLI_CLR_RE, '_cli_colorize', $msg);
		echo $msg;
		echo PLIB_CLI_NO_COLOR;
}

/**
 * Callback for the colorizing pattern matching
 *
 * @param array $m
 * @return string
 */
function _cli_colorize($m)
{
	$ret = PLIB_CLI_NO_COLOR;
	switch ($m[2]) {
		case 'NONE':
			$ret = PLIB_CLI_NO_COLOR;
			break;

		default:
      // It's an assignment
			if (!$ret = @constant("PLIB_CLI_{$m[2]}"))
				$ret = PLIB_CLI_NO_COLOR;
	}

	if (!empty($m[1]) && $m[2] != 'GRAY')
		$ret = str_replace('[', "[1;", $ret);

	return $ret;
}

/**
 * A simple command line options parser
 *
 * <code>
 * #----------------------------------------------------------
 * # Say this has been invoked on the command line...
 * #----------------------------------------------------------
 * #
 * # user@machine:~/bin/mycli -f xml --charset=iso-8859-1 -cq
 * #
 * #----------------------------------------------------------
 *
 * // mycli
 *
 * $opts = new Getoptlong('f|--format', 'c|--charset', 'h|--help', 'c', 'q');
 *
 * if ($opts->Get('h'))
 *   help() and exit(0);
 *
 * if (($format = $opts->Get('f')) === false)
 * 	 help() and exit(0);
 *
 * $charset = 'utf-8';
 * if ($opts->Get('c')))
 *   $charset = $opts['c'];
 *
 * // Alternative ways of usage
 *
 * foreach ($opts->Options() as $key => $val) {
 *   switch($key) {
 *     case 'h':
 *       usage();
 *       exit(0);
 *       break;
 *     case 'q':
 *       ...
 *   }
 * }
 *
 * // -------
 *
 * while (false !== ($opt = $opts->Next())) {
 *   switch ($opt['key']) {
 *     case 'h':
 *       ...
 *   }
 * }
 * </code>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package PLib
 * @subpackage CLI
*/
class Getoptlong
{
	/**
	 * Where we keep the result after parsing the options
	 * @var array
	*/
	protected $options = array();

	/**
	 * Any number of arguments can be passed to the constructor. If both short
	 * and long options can be used for an option separate them with a pipe:
	 *
	 * `$opts = new Getoptlong('v|--verbose', 'h|--help');`
	 *
	 * @param string $args
	*/
	public function __construct($args)
	{
		$argv = $_SERVER['argv'];
		array_shift($argv);
		$args = func_get_args();
		$opts = array();
		$keys = array();
		$vals = array();
		$ret  = array();

		foreach ($args as $arg) {
			$a = array();
			$o = explode('|', $arg);
			foreach ($o as $opt) {
				if (strpos($opt, '--') !== false)
					$a['long'] = substr($opt, 2);
				else {
					if (strlen($opt) > 1) {
						array_merge($args, str_split($opt));
					}
					else
						$a['short'] = $opt;
				}
			}
			$opts[] = $a;
		}

		$re = '^--?([-_a-z0-9]+)=?(?:(?:["\']?)(.*)(?:["\']?|\s))?';
		$mod = 0;

		foreach ($argv as $arg) {
			if (preg_match("/$re/i", $arg, $m)) {
				if (strpos($arg, '--') === false && strlen($m[1]) > 1) {
					$o = str_split($m[1]);
					for ($i=0; $i < sizeof($o); $i++)
						$keys[$i+1+($mod++)] = $o[$i];
				}
				else
					$keys[$mod] = $m[1];

				if (!empty($m[2]))
					$vals[$mod++] = $m[2];
			}
			else
				$vals[$mod-1] = $arg;

			$mod++;
		}

		foreach ($opts as $opt) {
			foreach ($keys as $i => $key) {
				if (in_array($key, $opt)) {
					$ret[$opt['short']] = empty($vals[$i]) ? true : $vals[$i];
					break;
				}
			}
		}

		$this->options = $ret;
	}

	/**
	 * Get the index $index from the options array. Returns FALSE if the index
	 * doesn't exist
	 *
	 * @param string $index
	 * @return string|bool
	*/
	public function Get($index)
	{
		if (isset($this->options[$index]))
			return $this->options[$index];

		return false;
	}

	/**
	 * Returns the options array
	 *
	 * @return array
	*/
	public function Options()
	{
		reset($this->options);
		return $this->options;
	}

	/**
	 * Return the current key and value pair from an array and advance the array
	 * cursor in the options array
	 *
	 * @see each()
	 * @return array
	*/
	public function Next()
	{
		return each($this->options);
	}

	/**
	 * Reset the options array if {@link Getoptlong::Next()} has been used
	*/
	public function Reset()
	{
		reset($this->options);
	}
}

/**
 * Reads from stdin, i.e. the keyboard in most cases.
 *
 * <code>
 * $stdin = new Stdin();
 * $continue = null;
 *
 * echo "Do you want to continue? [Y/n]: ";
 *
 * while ($line = $stdin->Read()) {
 *   if (preg_match('/y(es)?/i', $line) || empty($line))
 *     $continue = true;
 *   else
 *     $continue = false;
 *
 *   break;
 * }
 * </code>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @since plibcli 0.2
 * @package PLib
 * @subpackage CLI
*/
class Stdin
{
	/**
	 * The file handler resource
	 * @var resource
	*/
	protected $fh;

	/**
	 * Creates a new instance of Stdin
	*/
	public function __construct()
	{
		$this->fh = fopen('php://stdin', 'r');
	}

	/**
	 * Read from stdin.
	 *
	 * @param bool $trim
	 *   Trims the string by default
	 * @return string
	*/
	public function Read($trim=true)
	{
		$data = fread($this->fh, 4096);
		return $trim ? trim($data) : $data;
	}

	/**
	 * Destructor. Closes the file handler resource
	*/
	public function __destruct()
	{
		if (is_resource($this->fh))
			fclose($this->fh);
	}
}

} // if PHP_SAPI
?>