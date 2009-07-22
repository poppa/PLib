<?php
/**
 * Simple cache
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.1
 * @package Cache
 * @uses DB
 * @uses Date
 */

/**
 * The cache is saved in either a {@see Mysql} or {@see Sqlite} database
 */
require_once PLIB_INSTALL_DIR . '/DB/Database.php';
/**
 * The cache needs the {@see Date} class.
 */
require_once PLIB_INSTALL_DIR . '/Calendar/Date.php';

/**
 * A class for handling simple cacheing.
 * This is a static class. It uses SQLite to store the cache or MySQL if set
 * expicitly.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Cache
*/
class Cache
{
	/**
	 * If returned from a remove callback function the cached entry will be kept.
	 */
	const KEEP_CACHE = 1;
	/**
	 * The path where to save the SQLite file
	 * @var string
	 */
	public static $SQLITE_PATH = PLIB_TMP_DIR;
	/**
	 * The name of the SQLite file
	 * @var string
	 */
	public static $SQLITE_FILE = "cache.db";
	/**
	 * What backend to use. Valid values are sqlite or mysql
	 * @var string
	 */
	protected static $backend = 'sqlite';
	/**
	 * Has the db connection been initialized or not
	 * @var int
	 */
	protected static $isInit = 0;
	/**
	 * The db connection object
	 * @var DB
	 */
	protected static $db = null;
	/**
	 * The name of the db table
	 * @var string
	 */
	protected static $table = 'cache';
	/**
	 * SQLite table layout
	 * @var string
	 */
	protected static $ctable = '
		CREATE TABLE %s (
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
	protected static $mtable = '
		CREATE TABLE IF NOT EXISTS %s (
			id INT(11) PRIMARY KEY AUTO_INCREMENT,
			ckey VARCHAR(255),
			data TEXT,
			created DATETIME,
			expires DATETIME,
			hash VARCHAR(255),
			remove_callback VARCHAR(255)
		)';

	/**
	 * Initialize a MySQL backend
	 *
	 * @param Mysql $db
	 * @param bool $init
	 *  If the DB user don't have CREATE priviliges the table must be created
	 *  manually and if so is done we don't want to run {@see Cache::initDB()}
	 *  which will cause an exception.
	 */
	public static function UseMySQL(Mysql $db, $init=true)
	{
		self::$backend = 'mysql';
		self::$db = $db;

		if ($init)
			self::initDB();
		else
			self::$isInit = 1;
	}

	/**
	 * Initializes the db connection.
	 */
	protected static function initDB()
	{
		self::$isInit = 1;
		$table = self::$table;

		if (self::$backend == 'sqlite') {
      $db = null;
      if (self::$SQLITE_PATH && self::$SQLITE_FILE) {
        $f = self::$SQLITE_PATH . "/" . self::$SQLITE_FILE;
        $db = DB::Create("sqlite://$f", true);
      }
      else $db = PLib::GetDB();

			self::$db = $db;
			$res = $db->Query("SELECT * FROM sqlite_master WHERE name = '$table' " .
		  	                "AND type = 'table'");
			$createTable = 1;

			if ($res->NumRows() > 0)
				$createTable = 0;

			if ($createTable) {
				try { $db->Query(self::$ctable, $table); }
				catch (Exception $e) {
					throw $e;
				}
			}
		}
		elseif (self::$backend == 'mysql') {
			try { self::$db->Query(self::$mtable, $table); }
			catch (Exception $e) {
				throw $e;
			}
		}

		self::$isInit = 1;
	}

	/**
	 * Add to the cache
	 *
	 * @param string $key
	 *  The lookup key also used to fetch from the cache
	 * @param string $value
	 *  The actual value to cache
	 * @param int $time
	 *  A unix timestamp defining for how long the cache should last
	 * @param string $hash
	 *  A user defined hash to validate against. This hash can then be sent
	 *  to {@see Cache::Get()} which then also will check the hash and not just
	 *  the timestamp do validate the cache
	 * @param string $rmCallback
	 *  If set this function will be called prior to the deletion of the
	 *  cache. The function will be called with the `cachekey` as first
	 *  argument and the entire db result row as second argument. (This will be
	 *  an instance of {@see SQLite_DBResult} or {@see Mysql_DBResult} depending
	 *  on what backend is being used.
	 *
	 *  If you wish to keep the cache, i.e. abort the deletion, return
	 *  {@see Cache::KEEP_CACHE} from the callback function.
	 */
	public static function Add($key, $value, $time, $hash=null, $rmCallback=null)
	{
		if (self::$isInit === 0)
			self::initDB();

		if (!$hash)
			$hash = md5($value);

		$table = self::$table;

		$date = new Date($time);
		$date = $date->ymd;
		$now  = new Date();
		$now  = $now->ymd;

		self::Remove($key);

		$sql = "
		INSERT INTO $table (ckey, data, created, expires, hash, remove_callback)
		VALUES ('%s','%s','%s','%s','%s','%s')";

		self::$db->Query($sql, $key, $value, $now, $date, $hash,
		                 serialize($rmCallback));
	}

	/**
	 * Returns the cached item or false if the cache has expired or doesn't
	 * exist.
	 *
	 * @param string $key
	 *  A key associated with this cache
	 * @param string $hash
	 *  See {@see Cache::Add()}
	 * @param bool $returnObject
	 *  If set to true the SQL result set will be returned rather than
	 *  the actual cache data.
	 * @return string|DBResult
	 */
	public static function Get($key, $hash=null, $returnObject=false)
	{
		if (self::$isInit === 0)
			self::initDB();

		$table = self::$table;

		$sql = "SELECT * FROM $table WHERE ckey = '%s'";
		$res = self::$db->Query($sql, $key);

		if ($res->NumRows() == 0)
			return false;

		$res = $res->Fetch();

		if ($hash && $hash != $res->hash) {
			self::Remove($key, $res);
			return false;
		}

		$old = new Date($res->expires);
		$new = new Date();

		if ($old->unixtime < $new->unixtime) {
			self::Remove($key, $res);
			return false;
		}

		return $returnObject ? $res : $res->data;
	}

	/**
	 * Remove the item with key `$key` from the cache
	 *
	 * @param string $key
	 * @param DBResult $dbrow
	 *  This param is only used internally
	 */
	public static function Remove($key, $dbrow=null)
	{
		if (self::$isInit === 0)
			self::initDB();

		$table = self::$table;

		if (!$dbrow) {
			$r = self::$db->Query("SELECT * FROM $table WHERE ckey = '%s'", $key);
			if ($r->NumRows() == 0)
				return;

			$dbrow = $r->Fetch();
		}

		if ($dbrow->remove_callback) {
			$cb = unserialize($dbrow->remove_callback);
			if (is_array($cb) || is_string($cb) && strlen($cb))
				@call_user_func($cb, $key, $dbrow);
		}

		$sql = "DELETE FROM $table WHERE ckey = '%s'";
		self::$db->Query($sql, $key);
	}

	/**
	 * Clears the entire cache table
	 */
	public static function Clear()
	{
		if (self::$isInit === 0)
			self::initDB();

		self::$db->Query("DELETE FROM " . self::$table);
	}

	/**
	 * Returns the entire cache
	 *
	 * @return DBResult
	 */
	public static function GetAll()
	{
		if (self::$isInit === 0)
			self::initDB();

		return self::$db->Query("SELECT * FROM " . self::$table);
	}
}
?>