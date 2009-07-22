<?php
/**
 * Misc string classes
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package String
 * @version 0.2
 */

/**
 * General string class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package String
 */
class String
{
	/**
	 * Implodes an array by joining with `$glue`
	 *
	 * <code>
	 * $list = array('One', 'Two', 'Three', 'Four');
	 * echo String::ImplodeNicely($list);
	 * // One, Two, Three and Four
	 *
	 * echo String::ImplodeNicely($list, 'or');
	 * // One, Two, Three or Four
	 * </code>
	 *
	 * @param array $a
	 * @param string $glue
	 * @return string
	 */
	public static function ImplodeNicely(array $a, $glue='and')
	{
		if (empty($a))
			return null;

		if (sizeof($a) == 1)
			return $a[0];

		$last = array_pop($a);
		$s = implode(', ', $a);
		return $s . ' ' . trim($glue) . ' ' . $last;
	}

	/**
	 * Checks if `$str` ends with `$tail`
	 *
	 * @param string $str
	 * @param string $tail
	 * @return bool
	 */
	public static function EndsWith($str, $tail)
	{
		return substr($str, strlen($str)-strlen($tail)) == $tail;
	}

	/**
	 * Checks if `$str` starts with `$head`
	 *
	 * @param string $str
	 * @param string $head
	 * @return bool
	 */
	public static function StartsWith($str, $head)
	{
		return substr($str, 0, strlen($head)) == $head;
	}
}

/**
 * String reader that mimics the {@see StreamReader} class.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package String
 * @version 0.2
 * @since 0.2
 */
class StringReader
{
	/**
	 * The internal string
	 * @var string
	 */
	protected $string;
	/**
	 * The current position within the string
	 * @var int
	 */
	protected $cursor = 0;
	/**
	 * The length of the string
	 * @var int
	 */
	protected $length = 0;

	/**
	 * Constructor
	 *
	 * @param string $str
	 */
	public function __construct($str)
	{
		$this->string = $str;
		$this->length = strlen($str);
	}

	/**
	 * Read `$bytes` number of bytes from the current position
	 *
	 * @throws Exception
	 *  If trying to read beyond the string length
	 * @param int $bytes
	 * @return string
	 */
	public function Read($bytes=1)
	{
		if ($this->cursor + $bytes > $this->length)
			return false;

		return substr($this->string, $this->cursor+=$bytes, $bytes);
	}

  /**
   * Rewinds the stream `$bytes` number of bytes
   *
   * @param int $bytes
   * @return bool
   */
  public function Unread($bytes=1)
  {
    $this->cursor -= $bytes;
  }

	/**
	 * Read a block of bytes from the string
	 *
	 * @param int $start
	 * @param int $len
	 * @param int $whence
	 * @return string
	 */
	public function ReadBlock($start, $len, $whence=SEEK_SET)
	{
		$this->Seek($start, $whence);
		$ret = substr($this->string, $this->cursor, $len);
		return $ret;
	}

	/**
	 * Seek to position `$pos`
	 *
	 * @param int $pos
	 * @param int $whence
   *  `SEEK_SET`, `SEEK_CUR` or `SEEK_END`. See {@link fseek fseek()}.
	 */
	public function Seek($pos, $whence=SEEK_SET)
	{
		switch ($whence)
		{
			case SEEK_SET: $this->cursor = $pos; break;
			case SEEK_CUR: $this->cursor += $pos; break;
			case SEEK_END: $this->cursor = $this->length - $pos; break;
			default:
				throw new Exception("Unknown value to '\$whence'!");
		}
	}

	/**
	 * Read one line at a time
	 * @since 0.2
	 */
	public function ReadLine()
	{
		return $this->ReadToChar("\n");
	}

	/**
	 * Reads upto the first occurance of `$char` or reads to the end if `$char`
	 * is not found
	 *
	 * @param string $char
	 * @return string
	 */
	public function ReadToChar($char)
	{
		$s = '';
		$c = null;
		while ($this->cursor < $this->length &&
		      ($c = $this->string[$this->cursor++]) != $char)

		{
			$s .= $c;
		}

		return $s;
	}

	/**
	 * Reads to the end of the string from the current position
	 *
	 * @return string
	 */
	public function ReadToEnd()
	{
		$ret = substr($this->string, $this->cursor);
		$this->cursor = $this->length;
		return $ret;
	}

	/**
	 * Returns the current position within the string
	 *
	 * @return int
	 */
	public function Position()
	{
		return $this->cursor;
	}

	/**
	 * Returns the length of the string
	 *
	 * @return int
	 */
	public function Length()
	{
		return $this->length;
	}
	
	/**
	 * Has the pointer reached the end of the file?
	 * @since 0.2
	 */
	public function End()
	{
		return $this->cursor >= $this->length;
	}

	/**
	 * Clears the string.
	 */
	public function Dispose()
	{
		$this->string = null;
		unset($this->string);
	}
}
?>