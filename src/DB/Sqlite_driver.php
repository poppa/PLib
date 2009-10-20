<?php
/**
 * Sqlite database driver.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.1
 * @license GPL version 2
 * @package Database
 * @subpackage Sqlite
 * @depends DB
 * @depends IDB
 * @depends DBResult
 * @depends IDBResult
*/

/**
 * Load the DB class
 */
require_once 'Database.php';

/**
 * Sqlite child class to the DB class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Sqlite
 */
class Sqlite extends DB implements IDB
{
	/**
	 * File access mode for the DB
	 * @var int
	 */
	private $mode;
	/**
	 * Error string
	 * @var string
	 */
	private $error;

	/**
	 * Constructor
   *
   * NOTE! Use {@see DB::Create()} instead of instantiating this class
   * directly.
	 *
	 * @param string $host
	 * @param string $dbname
	 */
	public function __construct($host, $dbname, $user=null, $pass=null)
	{
		$this->host   = $host;
		$this->dbname = $dbname;
		$this->Connect();
	}

	/**
	 * Create a Sqlite database connection
	 *
	 * @link http://php.net/sqlite_open sqlite_open()
	 * @throws DBConnectionFail
	 *   If the connection fails
	 * @throws DBNotFound
	 *   If the selected DB doesn't exist
	 * @param int $mode
	 * @return Sqlite
	 */
	public function Connect($mode=0777)
	{
		if (is_resource($this->resource))
			$this->Close();

		$this->init(@sqlite_open($this->host, $this->mode, $this->error));
		return $this;
	}

	/**
	 * SQLite persisten connection
	 *
	 * @see Sqlite::Connect()
	 * @link http://php.net/sqlite_popen
	 * @return Sqlite
	 */
	public function PConnect($mode=0777)
	{
		$this->init(@sqlite_popen($this->host, $this->mode, $this->error));
		return $this;
	}

	/**
	 * Verifies the connection and selects the db choosen.
	 *
	 * @param resource $dbr
	 * @return bool
	 */
	private function init($dbr)
	{
		if (!is_resource($dbr))
			throw new DBConnectionFail($this->error);

		$this->resource = $dbr;
		unset($dbr);
		return true;
	}

	/**
	 * Run a database query. This method can be called with an arbitrary
	 * numner of arguments. It behaves pretty much like
	 * {@link http://php.net/printf printf()}. For  further description see
	 * {@link DB::buildQuery()}
	 *
	 * @link http://php.net/sqlite_query
	 * @throws DBQueryFail
	 * @param mixed Arbitrary number of arguments
	 * @return DBResult
	 */
	public function Query()
	{
		$args = func_get_args();

		if (sizeof($args) == 0)
			$q = $this->sql;
		else
			$q = parent::buildQuery($args);

		$res = @sqlite_query($q, $this->resource);

		if (!$res) {
			throw new DBQueryFail(
				sqlite_error_string(sqlite_last_error($this->resource))
			);
		}

		return new Sqlite_DBResult($res);
	}

	/**
	 * Checks if table $table exists.
	 *
	 * @param string $table
	 * @return bool
	 */
	public function TableExists($table)
	{
		$sql = "SELECT * FROM sqlite_master WHERE name = '%s' " .
		  	   "AND type = 'table'";
		try { return ($this->Query($sql, $table)->NumRows() > 0); }
		catch (Exception $e) {
			throw new DBQueryFail($e->getMessage());
		}
	}

	/**
	 * Escape a string input
	 *
	 * @link http://php.net/sqlite_escape_string
	 * @param string $what
	 * @return string
	 */
	public function Escape($what)
	{
		return @sqlite_escape_string($what);
	}

	/**
	 * Return the last inserted id.
	 *
	 * @link http://php.net/sqlite_last_insert_rowid
	 * @return int
	 */
	public function InsertID()
	{
		return @sqlite_last_insert_rowid($this->resource);
	}

	/**
	 * Close the connection
	 * @link http://php.net/sqlite_close
	 */
	public function Close()
	{
		@sqlite_close($this->resource);
		parent::__destruct();
	}

	/**
	 * Destruct the DB object
	 */
	public function __destruct()
	{
		$this->Close();
	}
}

/**
 * Sqlite_Result child class to {@link DBResult}
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Sqlite
 */
class Sqlite_DBResult extends DBResult implements IDBResult
{
	/**
	 * Constructor
	 *
	 * @param resource $res
	*/
	public function __construct($res)
	{
		parent::__construct($res);
	}

	/**
	 * Iterate over the result set.
	 * Wrapper for {@link sqlite_fetch_object()}
	 *
	 * @uses DBResult::$counter
	 * @link http://php.net/sqlite_fetch_object
	 * @return object
	 */
	public function Fetch()
	{
		$this->counter++;
		if (!function_exists('sqlite_fetch_object')) {
			$res = @sqlite_fetch_array($this->result, SQLITE_ASSOC);
			$res = (object)$res;
		}
		else
			$res = @sqlite_fetch_object($this->result);

		return $res;
	}

	/**
	 * Fetch result as an associative array
	 *
	 * @link http://php.net/sqlite_fetch_array sqlite_fetch_array()
	 * @return array
	 */
	public function FetchAssoc()
	{
		$this->counter++;
		return @sqlite_fetch_array($this->result, SQLITE_ASSOC);
	}

	/**
	 * Get numer of rows in the result
	 *
	 * @link http://php.net/sqlite_num_rows
	 * @return int
	 */
	public function NumRows()
	{
		return @sqlite_num_rows($this->result);
	}

	/**
	 * Get numer of affected rows in the result
	 *
	 * @link http://php.net/sqlite_changes
	 * @return int
	 */
	public function AffectedRows()
	{
		return @sqlite_changes($this->result);
	}

	/**
	 * Get a single row result
	 *
	 * @link http://php.net/sqlite_fetch_single
	 * @param int $row
	 * @param string $col
	 * @return mixed
	 */
	public function Result($row=0, $col=null)
	{
		return @sqlite_fetch_single($this->result);
	}
}
?>
