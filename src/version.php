<?php
/**
 * Version class
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 */

namespace PLib;

/**
 * Class representing a version, like 1.2, 0.2.345 etc
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @example
 *  $v1 = new Version (2, 2, 135);
 *  $v2 = new Version ("1.13");
 *
 *  if ($v1->greater_than ($v2))
 *    echo "v1 is greater than v2";
 *  elseif ($v1->less_than ($v2))
 *    echo "v1 is less than v2";
 *  elseif ($v1->equals ($v2))
 *    echo "v1 equals v2";
 *  else
 *    echo "How could this happen";
 *
 *  // Or
 *
 *  $comp = $v1->compare ($v2);
 *  switch ($comp)
 *  {
 *    case  0: echo "v1 equals v2"; break;
 *    case  1: echo "v1 is greater than v2"; break;
 *    case -1: echo "v1 is less than v2"; break;
 *  }
 *
 *  echo (string)$v1; // 2.2.135
 *
 *  $v1->minor = 3;
 *
 *  echo (string)$v1; // 2.3.135
 */
class Version
{
  /**
   * Major version
   * @var int
   */
  public $major = 0;

  /**
   * Minor version
   * @var int
   */
  public $minor = null;

  /**
   * Build
   * @var int
   */
  public $build = null;

  /**
   * Creates a new instance
   *
   * @param string|int $major
   *  If a string the entire version as a string is expected. `$minor` and
   *  `$build` will have no effect if `$major` is a string
   * @param int $minor
   * @param int $build
   */
  public function __construct ($major, $minor=null, $build=null)
  {
    if (is_string ($major))
      $this->parse_string ($major);
    else {
      $this->major = $major;
      $this->minor = $minor;
      $this->build = $build;
    }
  }

  /**
   * Parse a version string (i.e. `1.2`, `1.4.45` etc)
   *
   * @param string $s
   */
  protected function parse_string ($s)
  {
    if (preg_match ('/(\d+)\.?(\d+)?\.?(\d+)?/', $s, $m)) {
      $this->major = (int)$m[1];
      if (array_key_exists (2, $m))
        $this->minor = (int)$m[2];
      if (array_key_exists (3, $m))
        $this->build = (int)$m[3];
    }
  }

  /**
   * Compare this object to version `$v`
   *
   * @param Version $v
   * @return int
   *  If this version is greater than `$v` `1` will be returned
   *  If this version is less that `$v` `-1` will be returned
   *  If this version is equal to `$v` `0`  will be returned
   */
  public function compare (Version $v)
  {
    if ($this->major > $v->major)
      return 1;
    if ($this->major < $v->major)
      return -1;

    $t = $this->minor == null ? 0 : $this->minor;
    $s = $v->minor == null ? 0 : $v->minor;

    if ($t > $s) return 1;
    if ($t < $s) return -1;

    $t = $this->build == null ? 0 : $this->build;
    $s = $v->build == null ? 0 : $v->build;

    if ($t > $s) return 1;
    if ($t < $s) return -1;

    return 0;
  }

  /**
   * Checks if this version is equal to version `$v`
   *
   * @param Version $v
   * @return bool
   */
  public function equals (Version $v)
  {
    return $this->compare ($v) == 0;
  }

  /**
   * Checks if this version is less than version `$v`
   *
   * @param Version $v
   * @return bool
   */
  public function less_than (Version $v)
  {
    return $this->compare ($v) == -1;
  }

  /**
   * Checks if this version is greater than version `$v`
   *
   * @param Version $v
   * @return bool
   */
  public function greater_than (Version $v)
  {
    return $this->compare ($v) == 1;
  }

  /**
   * Turns this object into a string (i.e. `2.3.42`)
   *
   * @return string
   */
  public function __toString ()
  {
    $a = array((string)$this->major);

    if ($this->minor !== null) array_push ($a, $this->minor);
    if ($this->build !== null) array_push ($a, $this->build);
    return join ('.', $a);
  }
}
?>