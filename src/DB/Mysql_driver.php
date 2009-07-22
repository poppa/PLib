<?php
/**
 * Mysql database driver.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.8
 * @license GPL version 2
 * @package Database
 * @subpackage Mysql
 * @depends DB
 * @depends IDB
 * @depends DBResult
 * @depends IDBResult
*/

/**
 * Load the DB interface
 */
require_once 'Database.php';

/**
 * Mysql child class to the DB class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Mysql
 */
class Mysql extends DB implements IDB
{
	/**
	 * Database username
	 * @var string
	 */
	protected $username;
	/**
	 * Databse password
	 * @var string
	 */
	protected $password;

	/**
	 * Constructor
   *
   * NOTE! Use {@see DB::Create()} instead of instantiating this class
   * directly.
	 *
	 * @param string $host
	 * @param string $dbname
	 * @param string $user
	 * @param string $pass
	 */
	public function __construct($host, $dbname, $user=null, $pass=null)
	{
		$this->host     = $host;
		$this->dbname   = $dbname;
		$this->username = $user;
		$this->password = $pass;
	}

	/**
	 * Create a Mysql database connection
	 *
	 * @link http://php.net/mysql_connect
	 * @return Mysql
	 */
	public function Connect()
	{
		if (is_resource($this->resource))
			$this->Close();

		$this->init(@mysql_connect($this->host, $this->username, $this->password));
		return $this;
	}

	/**
	 * Mysql persisten connection
	 *
	 * @link http://php.net/mysql_pconnect mysql_pconnect()
	 * @return Mysql
	 */
	public function PConnect()
	{
		if (is_resource($this->resource))
			$this->Close();

		$this->init(@mysql_pconnect($this->host, $this->username, $this->password));
		return $this;
	}

	/**
	 * Verifies the connection and selects the db choosen.
	 *
	 * @link http://php.net/mysql_select_db
	 * @uses DB::$resource
	 * @throws DBConnectionFail
	 *   If the connection fails
	 * @throws DBNotFound
	 *   If the selected DB doesn't exist
	 * @param resource $dbr See {@link DB::$resource}
	 * @return bool
	 */
	private function init($dbr)
	{
		if (!is_resource($dbr))
			throw new DBConnectionFail(mysql_error());

		if (!mysql_select_db($this->dbname))
			throw new DBNotFound(mysql_error());

		$this->resource = $dbr;
		unset($dbr);
		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @link mysql_query()
	 * @throws DBQueryFail
	 * @param mixed $args Arbitrary number of arguments
	 * @return Mysql_DBResult
	 */
	public function Query()
	{
		$args = func_get_args();

		if (sizeof($args) == 0)
			$q = $this->sql;
		else
			$q = parent::buildQuery($args);

		$res = @mysql_query($q, $this->resource);

		if (!$res)
			throw new DBQueryFail(mysql_error());

		return new Mysql_DBResult($res);
	}

	/**
	 * Escape a string input
	 *
	 * @link mysql_escape_string()
	 * @param string $what
	 * @return string
	 */
	public function Escape($what)
	{
		return mysql_escape_string($what);
	}

	/**
	 * Return the last inserted id.
	 *
	 * @link mysql_insert_id()
	 * @return int
	 */
	public function InsertID()
	{
		return @mysql_insert_id($this->resource);
	}

	/**
	 * Close the connection
	 * @link mysql_close()
	 */
	public function Close()
	{
		@mysql_close($this->resource);
		parent::__destruct();
	}

	/**
	 * Destruct the DB object
	 *
	 * @link mysql_close()
	 */
	public function __destruct()
	{
		$this->Close();
	}
}

/**
 * Mysql child class to {@link DBResult}
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Mysql
 */
class Mysql_DBResult extends DBResult implements IDBResult
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
	 * Wrapper for {@link mysql_fetch_object()}
	 *
	 * @uses DBResult::$counter
	 * @link http://php.net/mysql_fetch_object
	 * @return object
	 */
	public function Fetch()
	{
		$this->counter++;
		return @mysql_fetch_object($this->result);
	}

	/**
	 * Fetch result as an associative array
	 *
	 * @link mysql_fetch_assoc()
	 * @uses DBResult::$counter
	 * @return array
	 */
	public function FetchAssoc()
	{
		$this->counter++;
		return @mysql_fetch_assoc($this->result);
	}

	/**
	 * Get numer of rows in the result
	 *
	 * @link mysql_num_rows()
	 * @return int
	 */
	public function NumRows()
	{
		return @mysql_num_rows($this->result);
	}

	/**
	 * Get numer of affected rows in the result
	 *
	 * @link mysql_affected_rows()
	 * @return int
	 */
	public function AffectedRows()
	{
		return @mysql_affected_rows($this->result);
	}

	/**
	 * Get the result
	 *
	 * @link mysql_result()
	 * @param int $row
	 * @param string $col
	 * @return mixed
	 */
	public function Result($row=0, $col=null)
	{
		return @mysql_result($this->result, $row, $col);
	}

	/**
	 * Seek offset in result set.
	 *
	 * @link mysql_data_seek()
	 * @param int $offset
	 * @return resource
	 */
	public function Seek($offset)
	{
		return @mysql_data_seek($this->result, $offset);
	}
}
?>