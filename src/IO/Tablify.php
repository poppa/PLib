<?php
/**
 * Tablify converts an CSV file into an HTML table
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 * @uses StreamReader
 */

/**
 * We might need the {@see StreamReader} class
 */
require_once PLIB_INSTALL_DIR . '/IO/StreamReader.php';

/**
 * Tablify converts an CSV file into an HTML table.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 * @subpackage Tablify
 * @version 0.1
 * @depends StreamReader
 */
class Tablify
{
	/**
	 * Instance counter. Used when creating a unique object key
	 * @var int
	 */
	protected static $instanses = 0;
	/**
	 * Configuration array. The array keys are reached through the magic
	 * methods {@see Tablify::__get()} and {@see Tablify::__set()}.
	 * @var array
	 */
	protected $conf = array(
		'CellSeparator'     => "\t",
		'RowSeparator'      => "\n",
		'Interactive'       => false,
		'TableAttributes'   => array(),
		'HeaderAttributes'  => array(),
		'OddRowAttributes'  => array(),
		'EvenRowAttributes' => array(),
		'Linkify'           => false,
		'Squeeze'           => false,
		'HeaderRow'         => false
	);
	/**
	 * The sort order.
	 * @var int
	 */
	protected $sortOrder = SORT_ASC;
	/**
	 * What column index to sort on.
	 * 0 means don't sort
	 * @var int
	 */
	protected $sortCol   = 0;
	/**
	 * The array with the rows and columns of the parsed data
	 * @var array
	 */
	protected $contents  = array();
	/**
	 * The resulting table
	 * @var string
	 */
	protected $buffer;
	/**
	 * The highest number of columns a row has.
	 * @var int
	 */
	protected $flood = 0;
	/**
	 * Each object gets an unique key so we can determine what table to sort
	 * on interactive sort if multiple tables are created.
	 * @var string
	 */
	protected $uniqueKey;
	/**
	 * The same object can only be used once. This member keeps track of that!
	 * @var int
	 */
	protected $renders = 0;

	/**
	 * Constructor
	 *
	 * @param array $args
	 *   Associative array with settings. The settings are:
	 *
	 *   * CellSeparator: What character to split cells on. Default is \t
	 *   * RowSeparator: What character to split row on. Default is \n
	 *   * Interactive: Boolean for if to allow interactive sorting.
	 *     Default is false.
	 *   * TableAttributes: Assoc array to set tag attributes for the table.
	 *   * HeaderAttributes: Assoc array to set tag attributes for the header tr.
	 *   * OddRowAttributes: Assoc array to set tag attributes for every odd tr.
	 *   * EvenRowAttributes: Assoc array to set tag attributes for every even tr.
	 *   * Linkify: Boolean for if to make URLs clickable.
	 *   * Squeeze: If set to true emty "cells" in the CSV file will be discarted.
	 *     This allows for CSV files formatted like this:
	 *
	 *     <code>
	 *     Given name       Last name   Profession
	 *     Brad             Pit         Actor
	 *     Ingmar           Bergman     Director
	 *     Mary Elizabeth   Winstead    Actress</code>
	 *
	 *   * HeaderRow: If true the first line in the CSV file will not be treated
	 *     as data but as column headers.
	 */
	public function __construct($args=array())
	{
		foreach ($args as $k => $v)
			if (isset($this->conf[$k]))
				$this->conf[$k] = $v;

		self::$instanses++;
		$this->uniqueKey = md5($_SERVER['PHP_SELF'] . '-' . self::$instanses);
	}

	/**
	 * Set sorting rules.
	 *
	 * @param int $col
	 *  What column to do the initial sort on.
	 * @param int $order
	 *  Sort order. Use the builtin constants SORT_ASC and SORT_DESC
	 */
	public function Sort($col=0, $order=0)
	{
		if ($order == SORT_ASC || $order == SORT_DESC)
			$this->sortOrder = $order;

		if (is_int($col) && $col > 0)
			$this->sortCol = $col;
	}

	/**
	 * Parse CSV string data.
	 *
	 * @param string $data
	 */
	public function Parse($data)
	{
		$arr = explode($this->RowSeparator, $data);

		while ($line = array_shift($arr))
			$this->createArray($line);
	}

	/**
	 * Parse a CVS file
	 *
	 * @param string $file
	 * @throws Exception
	 */
	public function ParseFile($file)
	{
		if (!file_exists($file))
			throw new Exception("The file \"$file\" doesn't exist!");

		$reader = new StreamReader($file);
		while (false !== ($line = $reader->ReadLine()))
			$this->createArray($line);
	}

	/**
	 * Render the CVS data to a table.
	 *
   * @throws Exception
	 * @return string
	 */
	public function Render()
	{
		$this->renders++;

		if ($this->renders > 1) {
			throw new Exception(
				"This Tablify object can only be used once. To create another table " .
				"you need to create a new Tablify instance or call the \"Copy\" " .
				"method for this object."
			);
		}

		if ($this->Interactive) {
			if (headers_sent($filename, $linenum) && (!isset($_SESSION))) {
				throw new Exception(
					"Can not run in interactive mode since headers already have been " .
				  "sent in $filename ($linenum). Interactive mode requires a " .
				  "session and one could not be started due to the above."
				);
			}
			elseif (!isset($_SESSION))
				session_start();

			if (isset($_SESSION) && !isset($_SESSION['__tablify']))
				$_SESSION['__tablify'] = new TablifySession();

			// Interactive values overrides those set in {@link Tablify::Sort()}
      // It's an assigment
			if ($so = $this->getSessObject()) {
				$this->sortCol   = $so['sort'];
				$this->sortOrder = $so['order'];
			}
		}

		$this->buffer = '<table' . $this->attrToStr($this->TableAttributes) . ">\n";

		if ($this->HeaderRow)
			$this->createHeader(array_shift($this->contents));

		if ($this->sortCol > 0) {
			$i = 0;
			$ints = 0;
			$callback = '_strsort';
			$dlen = sizeof($this->contents);

			while ($i++ < ($dlen < 10 ? $dlen-1 : 10)) {
				if ($this->isNumber($this->contents[$i][$this->sortCol-1]))
					$ints++;
			}

			if ($ints > 4)
				$callback = '_intsort';
			uasort($this->contents, array($this, $callback));
		}

		if ($this->sortOrder == SORT_DESC)
			$this->contents = array_reverse($this->contents);

		$i   = 0;
		$erc = $this->attrToStr($this->EvenRowAttributes);
		$orc = $this->attrToStr($this->OddRowAttributes);

		foreach ($this->contents as $row) {
			$this->buffer .= "\t<tr" . (++$i % 2 ? $erc : $orc) . ">\n";

			foreach ($row as $col) {
				if ($this->Linkify)
					$col = $this->linkify($col);

				if (empty($col)) $col = '&nbsp;';

				$this->buffer .= "\t\t<td>$col</td>\n";
			}

			$diff = $this->flood - sizeof($row);

			while ($diff-- > 0)
				$this->buffer .= "\t\t<td>&nbsp;</td>\n";

			$this->buffer .= "\t</tr>\n";
		}

		$ret = $this->buffer .= "</table>\n";

		$this->contents = array();
		$this->buffer   = null;

		return $ret;
	}

	/**
	 * Make a copy of the object.
	 * All settings in {@link Tablify::$conf} will be copied to the new object
	 *
	 * @return Tablify
	 */
	public function Copy()
	{
		$ret = new self();
		foreach ($this->conf as $key => $val)
			$ret->{$key} = $val;

		return $ret;
	}

	/**
	 * Splits $line to a column array and append to the {@link Tablify::$content}
	 * array.
	 *
	 * @param string $line
	 */
	protected function createArray($line)
	{
		$line = preg_replace("/\r?\n?$/", '', $line);

		if ($this->Squeeze)
			$cols = preg_split("/{$this->CellSeparator}+/", $line);
		else
			$cols = explode($this->CellSeparator, $line);

    // It's an assigment
		if (($l = sizeof($cols)) > $this->flood)
			$this->flood = $l;

		$this->contents[] = array_map('trim', $cols);
	}

	/**
	 * Creates the header row and makes them interactive of in interactive mode.
	 *
	 * @param array $row
	 */
	protected function createHeader($row)
	{
		$attr = $this->attrToStr($this->HeaderAttributes);
		$this->buffer .= "\t<tr$attr>\n";

		if ($this->Interactive) {
			$i      = 0;
			$active = $this->sortCol;
			$arrow  = null;
			$order  = null;

			foreach ($row as $col) {
				$i++;

				if ($i == $active) {
					if ($this->sortOrder)
						$order = $this->sortOrder == SORT_ASC ? SORT_DESC : SORT_ASC;
					else
						$order = SORT_DESC;

					$arrow = $order == SORT_ASC ? ' &dArr;' : ' &uArr;';
				}
				else {
					$arrow = null;
					$order = $this->sortOrder;
				}

				$key = $this->uniqueKey . '-' . $i . '-' . $order;
				$url = $_SERVER['PHP_SELF'] . '?__tablify=' . $key;
				$this->buffer .=
					"\t\t<th scope='col'><a href=\"$url\">$col</a>$arrow</th>\n";
			}
		}
		else {
			foreach ($row as $col)
				$this->buffer .= "\t\t<th scope='col'>$col</th>\n";
		}

		$this->buffer .= "\t</tr>\n";
	}

	/**
	 * Determines how to sort the tables if in interactive mode.
	 *
	 * @return array|bool
	 */
	protected function getSessObject()
	{
    // It's an assigment in here
		if (isset($_GET['__tablify']) && $sess = $_GET['__tablify']) {
			list ($key, $col, $order) = explode('-', $sess);
			if ($key == $this->uniqueKey) {
				$sessobj = array(
					'order' => $order,
					'sort'  => $col
				);

				$_SESSION['__tablify']->Add($this->uniqueKey, $sessobj);

				if ($key == $this->uniqueKey)
					return $sessobj;
			}
		}

		if (isset($_SESSION['__tablify'])) {
			foreach ($_SESSION['__tablify']->Get() as $key => $obj) {
				if ($key == $this->uniqueKey)
					return $obj;
			}
		}

		return false;
	}

	/**
	 * Sort content on string values
	 * This is a callback for {@link uasort()}
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	protected function _strsort($a, $b)
	{
		$col = $this->sortCol - 1;
		return strcmp($a[$col], $b[$col]);
	}

	/**
	 * Sort content on numeric values
	 * This is a callback for {@link uasort()}
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	protected function _intsort($a, $b)
	{
		$col = $this->sortCol - 1;
		return $a[$col] != $b[$col] ?
		       	$a[$col] > $b[$col] ? 1 : -1 : 0;
	}

	/**
	 * Check if $what is numberic
	 *
	 * @param mixed $what
	 * @return bool
	 */
	protected function isNumber($what)
	{
		return preg_match('/^[0-9.]+$/', $what);
	}

	/**
	 * Turns an associative array into a string of HTML tag attributes.
	 *
	 * @param array $attr
	 * @return string
	 */
	protected function attrToStr($attr)
	{
		$out = '';
		foreach ($attr as $k => $v)
			$out .= " $k" . '="' . (strlen($v) ? htmlentities($v) : $k) . '"';

		return $out;
	}

	/**
	 * Makes URLs clickable
	 *
	 * @param string $str
	 * @return string
	 */
	protected function linkify($str)
	{
		$pattern[0] = "#(^|[\n ])([\w]+?://.*?[^ \"\n\r\t<]*)\b#is";
		$replace[0] = "\\1<a href=\"\\2\">\\2</a>";
		$pattern[1] = "#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+" .
		              "(?:/[^ \"\t\n\r<]*)?)#is";
		$replace[1] = "\\1<a href=\"http://\\2\">\\2</a>";
		$pattern[2] = "#(^|[\n ])([a-z0-9&\-_.]+?)@" .
		              "([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i";
		$replace[2] = "\\1<a href=\"mailto:\\2@\\3\">\\2@\\3</a>";

		return preg_replace($pattern, $replace, $str);
	}

	/**
	 * Magic method. Sets values to the {@link Tablify::$conf} array
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function __set($key, $value)
	{
		if (isset($this->conf[$key]))
			$this->conf[$key] = $value;
	}

	/**
	 * Magic method. Returns the value of the {@link Tablify::$conf} array with
	 * index $what
	 *
	 * @param string $what
	 * @return mixed
	 */
	public function __get($what)
	{
		return isset($this->conf[$what]) ? $this->conf[$what] : false;
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		$this->buffer = null;
		$this->contents = null;
	}
}

/**
 * Keeps track of interative sorting settings for a {@link Tablify} object
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 * @subpackage Tablify
 * @version 0.1
 */
class TablifySession
{
	/**
	 * Container for session objects
	 * @var int
	 */
	protected $objects = array();

	public function __construct() {}

	public function Add($key, $arr)
	{
		$this->objects[$key] = $arr;
	}

	public function Get()
	{
		return $this->objects;
	}
}
?>