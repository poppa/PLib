<?php
/**
 * Iterator
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @version 0.1
 */

namespace PLib;

/**
 * A generic iterator
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @version 0.1
 */
abstract class Iterator
{
  /**
   * The array|string to iterate over
   * @var array|string
   */
  protected $container = null;

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
   * Hidden constructor. Can only be called from an inheriting class
   *
   * @param mixed $c
   */
  protected function __construct ($c)
  {
    $this->container = $c;
    $this->length = $this->length ();
  }

  /**
   * Checks if there's a next index in the array
   *
   * @return bool
   */
  abstract public function has_next ();

  /**
   * Returns the next item in the array
   *
   * @return mixed
   */
  abstract public function next ();

  /**
   * Returns the length of the array
   *
   * @return int
   */
  public function length ()
  {
    if ($this->length > 0)
      return $this->length;

    if (is_array ($this->container))
      return sizeof ($this->container);

    if (is_string ($this->container))
      return strlen ($this->container);

    return 0;
  }

  /**
   * Checks if the next item is the first item
   *
   * @return bool
   */
  public function first ()
  {
    return $this->pointer == 0;
  }

  /**
   * Checks if the current item is the last item
   *
   * @return bool
   */
  public function last ()
  {
    return $this->pointer == $this->length ();
  }

  /**
   * Checks if the current item is the next last item
   *
   * @return bool
   */
  public function next_last ()
  {
    return ($this->pointer + 1) == $this->length ();
  }

  /**
   * Returns the current index we're at
   *
   * @return int
   */
  public function pointer ()
  {
    return $this->pointer;
  }

  /**
   * Reverses the iterator
   */
  public function reverse ()
  {
    if (is_array ($this->container))
      $this->container = array_reverse ($this->container);
    else if (is_string ($this->container))
      $this->container = strrev ($this->container);
  }
}

/**
 * Generic exception when the iterator is out of range
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @version 0.1
*/
class PLibIteratorOutOfRangeException extends \Exception
{
  public $message = "The requested index is out of range";
}
?>