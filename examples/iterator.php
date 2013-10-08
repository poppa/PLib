<?php
namespace Test;

require_once '../src/PLib.php';
use \PLib;

PLib\import ('includes/iterator');

class StringIterator extends PLib\Iterator
{
  public function __construct ($s)
  {
    parent::__construct ($s);
  }

  public function has_next ()
  {
    return $this->pointer+1 <= $this->length;
  }

  public function next ()
  {
    if ($this->pointer > $this->length)
      return false;

    return $this->container[$this->pointer++];
  }
}

$sr = new StringIterator ('This is my string');

while ($sr->has_next ()) {
  echo $sr->next ();

  if ($sr->last ())
    echo "\n";
  else
    echo " ";
}

class ArrayIterator extends StringIterator
{
  public function __construct ($a)
  {
    if (!is_array ($a))
      $a = func_get_args();

    parent::__construct ($a);
  }
}

$ar = new ArrayIterator ('one', 'two', 'three', 'four');
$ar->reverse ();

while ($ar->has_next ()) {
  echo $ar->next ();

  if ($ar->last ())
    echo "\n";
  else
    echo " ";
}
?>