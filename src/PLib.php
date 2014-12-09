<?php
/**
 * PLib (Poppa PHP Library) is a set of PHP classes and functions to make
 * everyday PHP programming a little bit easier.
 *
 * @copyright 2013 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @version   2.0
 * @package   PLib
 */

namespace PLib;

/**
 * It's PLib
 */
define ('PLIB', true);

/**
 * PLib version
 */
define ('PLIB_VERSION', '2.0');

/**
 * The path to PLib it self
 */
define ('PLIB_PATH', __DIR__);

if (!defined ('PLIB_TMP_DIR')) {
  /**
   * The path to the temp dir used by PLib. By default it's created as a subdir
   * to PLib it self.
   *
   * To overwrite this define {@link PLIB_TMP_DIR} prior to including PLib.php
   */
  define ('PLIB_TMP_DIR', PLIB_PATH . DIRECTORY_SEPARATOR . 'tmp');
}

if (!defined ('PLIB_SQLITE_FILE')) {
  /**
   * The path to the default SQLite file to use for internal PLib stuff.
   * To overwrite this define `PLIB_SQLITE_FILE` prior to including PLib.php
   */
  define ('PLIB_SQLITE_FILE', PLIB_TMP_DIR.DIRECTORY_SEPARATOR.'plib.sqlite');
}

if (!is_dir (PLIB_TMP_DIR)) {
  if (!mkdir (PLIB_TMP_DIR, 0777)) {
    $m = sprintf ("Unable to create \"PLIB_TMP_DIR (%s)\"!", PLIB_TMP_DIR);
    throw new \Exception ($m, 1);
  }
}

/**
 * Are we running CLI or not
 */
define ('PLIB_IS_CLI', PHP_SAPI == 'cli');

/**
 * Include PLib file $which
 *
 * @api
 *
 * @param string $which
 */
function import ($which)
{
  if (!has_suffix ($which, '.php'))
    $which .= '.php';

  $org = $which;
  $which = combine_path (PLIB_PATH, $which);

  if (!file_exists($which))
    throw new \Exception ("PLib file \"$org\" doesn't exist!\n");

  require_once $which;
}

/**
 * Combine paths with OS directory separator
 *
 * @api
 *
 * @param string $args This is a `vararg`, i.e it takes any numer of arguments.
 */
function combine_path ($args)
{
  return _low_combine_path (func_get_args ());
}

/**
 * Combine paths with forward slashes
 *
 * @api
 *
 * @param string $args This is a `vararg`, i.e it takes any numer of arguments.
 */
function combine_path_unix ($args)
{
  return _low_combine_path (func_get_args (), '/');
}

/**
 * Low level path combiner
 *
 * @internal
 *
 * @param array $paths
 * @param string $sep
 *  Directory separator
 */
function _low_combine_path (array $paths, $sep=DIRECTORY_SEPARATOR)
{
  if (!$paths || !sizeof ($paths))
    return "";

  $path = implode ($sep, $paths);
  $out = array();

  foreach (explode($sep, $path) as $i => $p) {
    if ($p == '' || $p== '.' ) continue;
    if ($p == '..' && $i > 0 && end ($out) != '..')
      array_pop($out);
    else
      $out[] = $p;
  }

  return ($path[0] === $sep ? $sep : '') . join ($sep, $out);
}

/**
 * Checks if `$str` ends with `$tail`
 *
 * @api
 *
 * @param string $str
 * @param string $tail
 * @return bool
 */
function has_suffix ($str, $tail)
{
  return is_string ($str) &&
         substr ($str, strlen ($str) - strlen ($tail)) === $tail;
}

/**
 * Checks if `$str` starts with `$head`
 *
 * @api
 *
 * @param string $str
 * @param string $head
 * @return bool
 */
function has_prefix ($str, $head)
{
  return is_string ($str) && substr ($str, 0, strlen ($head)) === $head;
}

/**
 * Returns a SQLite PDO instance for the default SQLite db.
 *
 * @api
 *
 * @throws \Exception
 *  If no SQLite driver is avilable
 * @throws \PDOException
 *  If the instantiation fails.
 *
 * @return \PDO
 */
function get_default_sqlite ()
{
  if (!class_exists ('SQLite3'))
    throw new \Exception ('No SQLite3 driver exists!', 1);

  try { return new \PDO ("sqlite:" . PLIB_SQLITE_FILE); }
  catch (\PDOException $e) { throw $e; }
}

/**
 * General string class
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class String
{
  /**
   * Implodes an array by joining with `$glue`
   *
   * <pre>
   *  $list = array('One', 'Two', 'Three', 'Four');
   *  echo String::implode_nicely($list);
   *  // One, Two, Three and Four
   *
   *  echo String::implode_nicely($list, 'or');
   *  // One, Two, Three or Four
   * </pre>
   *
   * @param array $a
   * @param string $glue
   * @return string
   */
  public static function implode_nicely (array $a, $glue='and')
  {
    if (empty ($a))
      return null;

    if (sizeof ($a) == 1)
      return $a[0];

    $last = array_pop ($a);
    $s = implode (', ', $a);
    return $s . ' ' . trim ($glue) . ' ' . $last;
  }
}

/**
 * Just an empty object, pretty much as a stdClass
 *
 * @api
 */
class Object {}

function debug ()
{
  $bt = debug_backtrace();
  $b = isset($bt[0]['file']) ? $bt[0] : $bt[2];
  $out = $b['file'] . ':' . $b['line'] . ': ';

  ob_start();

  foreach (func_get_args() as $arg) {
    if (is_scalar($arg))
      echo $arg;
    else {
      print_r($arg);
    }
  }

  return $out . ob_get_clean();
}

function wdebug()
{
  $res = call_user_func_array("\PLib\debug", func_get_args());
  echo $res . "\n";
}
?>