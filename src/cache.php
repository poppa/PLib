<?php
/**
 * Simple cache
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 */

namespace PLib;

// Internal function
function __cache ()
{
  static $c;
  if (!$c) $c = new Cache ();
  return $c;
}

/**
 * Convenience function for Cache::add();
 *
 * @param string $key
 * @param string $value
 * @param int $lifetime
 *  Number of seconds the cache should exist
 * @param string $hash
 * @param mixed $rm_callback
 *  If set this function will be called prior to the cache being removed
 *
 * @return
 *  Returns true if success, false otherwise
 */
function cache_add ($key, $value, $lifetime=0, $hash=null, $rm_callback=null)
{
  return __cache ()->add ($key, $value, $lifetime, $hash, $rm_callback);
}

/**
 * Convenience function for Cache::get ()
 *
 * @param string key
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
 * Convenience function for Cache::remove ();
 *
 * @param string $key
 */
function cache_remove ($key)
{
  __cache ()->remove ($key);
}

/**
 * Convenience function for Cache::clear ()
 */
function cache_clear ()
{
  __cache ()->clear ();
}

/**
 * Simple cache class
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class Cache
{
  /**
   * If returned from a remove callback function the cached entry will be kept.
   */
  const KEEP = true;

  /**
   * 10 years ahead in time. Use as third argument to {@see Cache::add()} to
   * add non-expireing cache (10 years must be seen as persistent ;)
   * @var int
   */
  const PERSISTENT = 315360000;

  /**
   * Database driver object
   * @var PDO
   */
  private $dbh = null;

  /**
   * Database driver name
   * @var string
   */
  private $driver = null;

  /**
   * Date format
   */
  private $date_fmt = 'Y-m-d H:i:s';

  /**
   * The name of the cache table
   * @var string
   */
  protected static $tableName = 'plib_cache';

  /**
   * SQLite table layout
   * @var string
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
   * MySQL table layout
   * @var string
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
   * @param string $key
   * @param string $value
   * @param int $lifetime
   *  Number of seconds the cache should exist. If not given the cache will be
   *  kept for 24 hours.
   * @param string $hash
   * @param mixed $rm_callback
   *  If set this function will be called prior to the cache being removed
   *
   * @return
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
    $table = self::$tableName;

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
   * @param string key
   * @param bool $return_expired
   *  If false expired results will not be returned
   *
   * @return CacheResult
   */
  public function get ($key, $return_expired=true)
  {
    $sql = sprintf ("SELECT * FROM %s WHERE ckey = %s",
                    self::$tableName, $this->dbh->quote ($key));

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
   * @param string $key
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
                      self::$tableName, $this->dbh->quote ($key));
      $this->dbh->query ($sql);
    }
  }

  /**
   * Clear the entire cache table
   */
  public function clear ()
  {
    $this->dbh->query ("DELETE FROM " . self::$tableName);
  }

  /**
   * Initialize the SQLite table
   */
  protected function init_sqlite ()
  {
    $sql = sprintf ("SELECT * FROM sqlite_master WHERE name = '%s' " .
                    "AND type = 'table'", self::$tableName);

    if ($p = $this->dbh->query ($sql)) {
      if (!$p->fetch (\PDO::FETCH_ASSOC)) {
        $sql = sprintf (self::$s_table, self::$tableName);

        if (!($p = $this->dbh->query ($sql)))
          throw new \Exception ('Unable to create cache table!\n');
      }
    }
  }
}

/**
 * Cache result class
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class CacheResult
{
  /**
   * Cache key
   * @var string
   */
  public $ckey = null;

  /**
   * Creation date
   * @var string
   */
  public $created = null;

  /**
   * Expiration date
   * @var string
   */
  public $expires = null;
  /**
   * Data
   * @var string
   */
  public $data = null;

  /**
   * Hash of data (MD5 sum)
   * @var string
   */
  public $hash = null;

  /**
   * Remove callback function
   * @var string
   */
  public $remove_callback = null;

  /**
   * Getter for the remove callback function
   */
  public function get_rm_callback ()
  {
    return $this->remove_callback && strlen ($this->remove_callback) ?
          unserialize ($this->remove_callback) : null;
  }

  /**
   * Is the cache expired or not
   */
  public function is_expired ()
  {
    return strtotime ('now') > strtotime ($this->expires);
  }
}
?>