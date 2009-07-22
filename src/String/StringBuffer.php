<?php
/**
 * StringBuffer
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package String
 */

/**
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.1
 * @package String
 */
class StringBuffer
{
	/**
	 * The file currently being used
	 * @var string
	 */
	protected $string;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->string = "";
	}

	public function Add($args)
	{
		foreach (func_get_args() as $arg)
			$this->string .= $arg;
	}

	public function Addf($format, $args)
	{
		$args = func_get_args();
		$format = array_shift($args);
		$this->string .= vsprintf($format, $args);
	}

	public function Length()
	{
		return strlen($this->string);
	}

	public function Get()
	{
		$str = $this->string;
		$this->string = "";
		return $str;
	}

	public function GetLineIterator()
	{
		return new StringLineIterator($this->string);
	}

	/**
   * Magic method that converts the object into a string
   */
	public function __toString()
	{
		return $this->Get();
	}
}


require_once PLIB_INSTALL_DIR . '/Core/Iterator.php';

class StringSpliterator extends PLibIterator
{
	public function __constuct($string, $split)
	{

	}

	public function HasNext()
	{

	}

	public function Next()
	{

	}
}

class StringLineIterator extends PLibIterator
{
	public function __construct($string)
	{
		$this->container = explode("\n", $string);
		$this->length = sizeof($this->container);
	}

	public function HasNext()
	{
		return array_key_exists($this->pointer, $this->container);
	}

	public function Next()
	{
		if (!array_key_exists($this->pointer, $this->container))
			throw new PLibIteratorOutOfRangeException();

		return $this->container[$this->pointer++];
	}
}