<?php
/**
 * Iterator
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Core
 */

/**
 * A generic iterator
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Core
 */
abstract class PLibIterator
{
	/**
	 * The array to iterate over
	 * @var array
	 */
	protected $container = array();
	/**
	 * The current index
	 * @var int
	 */
	protected $pointer = 0;
	/**
	 * The total length of the $container
	 * @var int
	 */
	protected $length  = 0;

	/**
	 * Checks if there's a next index in the array
	 *
	 * @return bool
	 */
	abstract public function HasNext();
	/**
	 * Returns the next item in the array
	 *
	 * @return mixed
	 */
	abstract public function Next();

	/**
	 * Returns the length of the array
	 *
	 * @return int
	 */
	public function Length()
	{
		if ($this->length > 0)
			return $this->length;

		if (is_array($this->container))
			return sizeof($this->container);
		if (is_string($this->container))
			return strlen($this->container);

		return 0;
	}

	/**
	 * Checks if the next item is the first item
	 *
	 * @return bool
	 */
	public function First()
	{
		return $this->pointer == 0;
	}

	/**
	 * Checks if the current item is the last item
	 *
	 * @return bool
	 */
	public function Last()
	{
		return $this->pointer == $this->Length();
	}

	/**
	 * Checks if the current item is the next last item
	 *
	 * @return bool
	 */
	public function NextLast()
	{
		return ($this->pointer + 1) == $this->Length();
	}

	/**
	 * Returns the current index we're at
	 *
	 * @return int
	 */
	public function Pointer()
	{
		return $this->pointer;
	}

	/**
	 * Reverses the iterator
	 */
	public function Reverse()
	{
		if (is_array($this->container))
			$this->container = array_reverse($this->container);
	}
}

/**
 * Generic exception when the iterator is out of range
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Core
 * @subpackage Exception
*/
class PLibIteratorOutOfRangeException extends Exception
{
	public $message = "The requested index is out of range";
}
?>
