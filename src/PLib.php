<?php
/**
 * PLib (Poppa PHP Library) is a set of PHP classes and functions to make
 * everyday PHP programming a little bit easier.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 * @version 2.0
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
define ('PLIB_PATH', dirname(__FILE__));

if (!defined ('PLIB_TMP_DIR')) {
  /**
   * The path to the temp dir used by PLib. By default it's created a subdir
   * to PLib it self.
   *
   * To overwrite this define PLIB_TMP_DIR prior to including PLib.php
   */
  define ('PLIB_TMP_DIR', PLIB_PATH . DIRECTORY_SEPARATOR . 'tmp');
}

if (!defined ('PLIB_SQLITE_FILE')) {
  /**
   * The path to the default SQLite file to use for internal PLib stuff.
   * To overwrite this define PLIB_SQLITE_FILE prior to including PLib.php
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
 * Include PLib file $which
 *
 * @param string $which
 */
function import ($which)
{
  if (!has_suffix ($which, '.php'))
    $which .= '.php';

  $org = $which;
  $which = combine_path (PLIB_PATH, $which);

  if (!$which)
    throw new \Exception ("PLib file \"$org\" doesn't exist!\n");

  require_once $which;
}

/**
 * Combine paths with OS directory separator
 *
 * @param string ... $args
 */
function combine_path ($args)
{
  return _low_combine_path (func_get_args ());
}

/**
 * Combine paths with forward slashes
 *
 * @param string ... $args
 */
function combine_path_unix ($args)
{
  return _low_combine_path (func_get_args (), '/');
}

/**
 * Low level path combiner
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
      $out[]= $p;
  }

  return ($path[0] == '/' ? '/' : '') . join ('/', $out);
}

/**
 * Checks if `$str` ends with `$tail`
 *
 * @param string $str
 * @param string $tail
 * @return bool
 */
function has_suffix ($str, $tail)
{
  return is_string ($str) &&
         substr ($str, strlen ($str) - strlen ($tail)) == $tail;
}

/**
 * Checks if `$str` starts with `$head`
 *
 * @param string $str
 * @param string $head
 * @return bool
 */
function has_prefix ($str, $head)
{
  return is_string ($str) && substr ($str, 0, strlen ($head)) == $head;
}

/**
 * Returns a SQLite PDO instace for the default SQLite db.
 *
 * @throws
 *  An exception if no SQLite driver is avilable or if the
 *  instantiation fails.
 *
 * @return PDO
 */
function get_default_sqlite ()
{
  if (!class_exists ('SQLite3'))
    throw new \Exception ('No SQLite3 driver exists!', 1);

  try { return new \PDO ("sqlite:" . PLIB_SQLITE_FILE); }
  catch (\PDOException $e) { throw $e; }
}
?>