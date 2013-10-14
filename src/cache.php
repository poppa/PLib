<?php
/**
 * Simple cache
 *
 * @copyright 2013 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib
 */

namespace PLib;

/**
 * Internal function for retreiving a "global" {@link Cache} object.
 *
 * @internal
 */
function __cache ()
{
  static $c;
  if (!$c) $c = new Cache ();
  return $c;
}

/**
 * Convenience function for {@link Cache::add()}.
 *
 * @api
 *
 * @param string $key
 *  The cache key
 * @param string $value
 *  The cache value
 * @param int $lifetime
 *  Number of seconds the cache should exist
 * @param string $hash
 * @param mixed $rm_callback
 *  If set this function will be called prior to the cache being removed
 *
 * @return bool
 *  Returns true if success, false otherwise
 */
function cache_add ($key, $value, $lifetime=0, $hash=null, $rm_callback=null)
{
  return __cache ()->add ($key, $value, $lifetime, $hash, $rm_callback);
}

/**
 * Convenience function for {@link Cache::get()}.
 *
 * @api
 *
 * @param string $key
 * @param bool $return_expired
 *  If false expired results will not be returned
 *
 * @return CacheResult
 */
function cache_get ($key, $return_expired=true)
{
  return __cache ()->get ($key, $return_expired);
}

/**
 * Convenience function for {@link Cache::remove()}
 *
 * @api
 *
 * @param string $key
 */
function cache_remove ($key)
{
  __cache ()->remove ($key);
}

/**
 * Convenience function for {@link Cache::clear()}
 *
 * @api
 */
function cache_clear ()
{
  __cache ()->clear ();
}

/**
 * Simple cache class. Has support for SQLite databases.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @todo Add support for MySQL
 */
class Cache
{
  /**
   * If returned from a remove callback function the cached entry will be kept.
   */
  const KEEP = true;

  /**
   * 10 years ahead in time. Use as third argument to {@see Cache::add()} to
   * add non-expireing cache (10 years must be seen as persistent)
   */
  const PERSISTENT = 315360000;

  /**
   * @var \PDO Database driver object
   */
  private $dbh = null;

  /**
   * @var string Database driver name
   */
  private $driver = null;

  /**
   * @var string Date format
   */
  private $date_fmt = 'Y-m-d H:i:s';

  /**
   * @var string The name of the cache table
   */
  protected static $tablename = 'plib_cache';

  /**
   * @var string SQLite table layout
   */
  protected static $s_table = 'CREATE TABLE %s (
                                ckey VARCHAR(255),
                                data TEXT,
                                created DATE,
                                expires DATE,
                                hash VARCHAR(255),
                                remove_callback VARCHAR(255)
                              )';

  /**
   * @var string MySQL table layout
   */
  protected static $m_table = 'CREATE TABLE IF NOT EXISTS %s (
                                id INT(11) PRIMARY KEY AUTO_INCREMENT,
                                ckey VARCHAR(255),
                                data TEXT,
                                created DATETIME,
                                expires DATETIME,
                                hash VARCHAR(255),
                                remove_callback VARCHAR(255)
                              )';

  /**
   * Creates a new Cache object
   *
   * @throws \Exception
   *  If the given driver isn't supported
   *
   * @param PDO $db
   *  If not given the default SQLite file in PLib will be used
   */
  public function __construct (\PDO $db=null)
  {
    $this->dbh = $db ? $db : get_default_sqlite ();
    $this->driver = $this->dbh->getAttribute (\PDO::ATTR_DRIVER_NAME);

    if ($this->driver === "sqlite") {
      $this->init_sqlite ();
      return;
    }

    throw new \Exception ("Support for \"$this->driver\" is not implemented!");
  }

  /**
   * Add to cache
   *
   * @api
   *
   * @param string $key
   *  The cache key
   * @param string $value
   *  The cache value
   * @param int $lifetime
   *  Number of seconds the cache should exist. If not given the cache will be
   *  kept for 24 hours.
   * @param string $hash
   * @param string|callback $rm_callback
   *  If set this function will be called prior to the cache being removed
   *
   * @return bool
   *  Returns true if success, false otherwise
   */
  public function add ($key, $value, $lifetime=0, $hash=null, $rm_callback=null)
  {
    if (!$hash)
      $hash = md5 ($value);

    if (!$lifetime)
      $lifetime = 3600 * 24;

    $date  = date ($this->date_fmt, time () + $lifetime);
    $now   = date ($this->date_fmt, time ());
    $table = self::$tablename;

    $this->remove ($key);

    $sql = "
      INSERT INTO $table (ckey, data, created, expires, hash, remove_callback)
      VALUES (:ckey,:data,:created,:expires,:hash,:rmcb)";

    $st = $this->dbh->prepare ($sql);

    return $st->execute (array(
      ':ckey'    => $key,
      ':data'    => $value,
      ':created' => $now,
      ':expires' => $date,
      ':hash'    => $hash,
      ':rmcb'    => serialize ($rm_callback)
    ));
  }

  /**
   * Get cache
   *
   * @api
   *
   * @param string key
   * @param bool $return_expired
   *  If false expired results will not be returned
   *
   * @return CacheResult
   */
  public function get ($key, $return_expired=true)
  {
    $sql = sprintf ("SELECT * FROM %s WHERE ckey = %s",
                    self::$tablename, $this->dbh->quote ($key));

    if ($p = $this->dbh->query ($sql)) {
      $p->setFetchMode (\PDO::FETCH_INTO, new CacheResult ());

      if ($x = $p->fetch (\PDO::FETCH_INTO)) {
        if ($x->is_expired ())
          $this->remove ($key, $x);

        if (!$x->is_expired () || $return_expired)
          return $x;
      }
    }

    return null;
  }

  /**
   * Remove cache
   *
   * @api
   *
   * @param string $key
   * @param CacheResult $c
   *  This is an internal argument and should never be given from outside
   */
  public function remove ($key, CacheResult $c=null)
  {
    if (!$c)
      $c = $this->get ($key);

    if (!$c) return;

    $keep = 0;

    if ($cb = $c->get_rm_callback ())
      $keep = @call_user_func ($cb, $key, $c);

    if (!$keep) {
      $sql = sprintf ("DELETE FROM %s WHERE ckey = %s",
                      self::$tablename, $this->dbh->quote ($key));
      $this->dbh->query ($sql);
    }
  }

  /**
   * Clear the entire cache table
   */
  public function clear ()
  {
    $this->dbh->query ("DELETE FROM " . self::$tablename);
  }

  /**
   * Initialize the SQLite table
   */
  protected function init_sqlite ()
  {
    $sql = sprintf ("SELECT * FROM sqlite_master WHERE name = '%s' " .
                    "AND type = 'table'", self::$tablename);

    if ($p = $this->dbh->query ($sql)) {
      if (!$p->fetch (\PDO::FETCH_ASSOC)) {
        $sql = sprintf (self::$s_table, self::$tablename);

        if (!($p = $this->dbh->query ($sql)))
          throw new \Exception ('Unable to create cache table!\n');
      }
    }
  }
}

/**
 * Cache result class. This class should never be instantiated from the
 * outside.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class CacheResult
{
  /**
   * @var string Cache key
   */
  public $ckey = null;

  /**
   * @var string Creation date
   */
  public $created = null;

  /**
   * @var string Expiration date
   */
  public $expires = null;
  /**
   * @var string The cache data
   */
  public $data = null;

  /**
   * @var string Hash of data (MD5 sum)
   */
  public $hash = null;

  /**
   * @var string On remove callback function
   */
  public $remove_callback = null;

  /**
   * Getter for the remove callback function
   *
   * @api
   *
   * @return string
   *  Returns the on remove callback function if it's set or null otherwise
   */
  public function get_rm_callback ()
  {
    return $this->remove_callback && strlen ($this->remove_callback) ?
          unserialize ($this->remove_callback) : null;
  }

  /**
   * Is the cache expired or not
   *
   * @api
   *
   * @return bool
   */
  public function is_expired ()
  {
    return strtotime ('now') > strtotime ($this->expires);
  }
}
?>