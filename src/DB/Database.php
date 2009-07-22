<?php
/**
 * This file contains interfaces, base classes and exception classes for
 * creating database drivers.
 *
 * Example of usage:
 * <code>
 * $db = DB::Create('mysql://user:pass@host/db')->Connect();
 * $res = $db->Query("SELECT * FROM table");
 * while ($row = $res->Fetch()) {
 *   echo "<h1>" . $row->title   . "</h1>\n";
 *   echo "<p>"  . $row->excerpt . "</p>\n";
 *
 *   if (!$res->Last())
 *     echo "<hr/>\n";
 * }
 * ...
 * </code>
 *
 * ---------------
 *
 * Some features:
 *
 * * __Escapeing of dynamic values in queries__
 *
 *   The {@link IDB::Query()} method works in the same way as
 *   {@link http://php.net/sprintf sprintf()} does and each argument will
 *   will be escaped before inserted into the query.
 *
 * * __DB::TablePrefix($prefix)__
 *
 *   When developing database driven web applications you really can't
 *   assume that every installation can use a dedicated database. To solve
 *   this it's common to prefix the table names.
 *
 *   If you in your querys prefix all table references with #TPF# that
 *   pattern will be substituted with what's ever passed to this method.
 *
 *   Example:
 *
 *     <code>
 *     $db = DB::Create('mysql://user:pass@host/db')->Connect();
 *     $db->TablePrefix('myapplication_');
 *     $res = $db->Query("SELECT * FROM #TPF#employees");
 *     // This will then read:
 *     // $res = $db->Query("SELECT * FROM myapplication_employees");
 *     ...
 *     </code>
 *
 * * __DB::PrepareSQL();__
 *
 *     <code>
 *     $db->PrepareSQL("
 *       INSERT INTO #TPF#employees(name, email, phone, department)
 *       VALUES('%s','%s','%s','%s')",
 *       $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['department']
 *     );
 *     $db->Query();
 *     </code>
 *
 *   Note! The arguments passed to this method will be escaped for safe
 *   insertion.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.8.2
 * @package Database
 */

define('PLIB_DB_VERSION', '0.8.2');

/**
 * Database connection interface
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Database
 * @since 0.8
 */
interface IDB
{
	/**
	 * A database must be connectable!
	 *
	 * @throws DBConnectionFail If the connection fails
	 * @throws DBNoSuchDB If the selected DB doesn't exist
	 * @return DB
	 *   The instance of self
	 */
	public function Connect();

	/**
	 * Create a persistent connection.
	 * If the database implemented doesn't support persisten connections make
	 * this an alias of {@see IDB::Connect()}
	 *
	 * @throws DBConnectionFail If the connection fails
	 * @throws DBNoSuchDB If the selected DB doesn't exist
	 * @return DB
	 *   The instance of self
	 */
	public function PConnect();

	/**
	 * Close the connection
	 */
	public function Close();

	/**
	 * Run a database query. This method can be called with an arbitrary
	 * numner of arguments. It behaves pretty much like {@link printf()}. For
	 * further description see {@link DB::buildQuery()}
	 *
	 * @throws DBQueryFail
	 * @param mixed $args Arbitrary number of arguments
	 * @return DBResult
	 */
	public function Query();

	/**
	 * Escape a string for safe input.
	 *
	 * @param string $what
	 * @return string
	 */
	public function Escape($what);
}

/**
 * Interface for a database query result
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Database
 * @since 0.8
 */
interface IDBResult
{
	/**
	 * Iterate over the result set.
	 *
	 * This method should return an object. Should behave like
	 * {@link http://php.net/mysql_fetch_object mysql_fetch_object}, and it
	 * should increment the $counter member for each call.
	 *
	 * @uses $counter
	 * @return object
	 */
	public function Fetch();

	/**
	 * Get numer of rows in the result
	 * For an example see {@link http://php.net/mysql_num_rows mysql_num_rows}
	 *
	 * @return int
	 */
	public function NumRows();

	/**
	 * Get numer of affected rows in the result.
	 * {@link http://php.net/mysql_affected_rows mysql_affected_rows} for an
	 * example.
	 *
	 * @return int
	 */
	public function AffectedRows();

	/**
	 * Are we on the last row in the result?
	 *
	 * @return boolean
	 */
	public function Last();
}

/**
 * Master class for the database abstraction. All DB driver plugins must
 * inherit this class.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 */
abstract class DB
{
	/**
	 * Database host
	 * @var string
	 */
	protected $host;
	/**
	 * Name of the database to use
	 * @var string
	 */
	protected $dbname;
	/**
	 * The resource identifier after the query has been executed
	 * @var resource
	 */
	protected $resource;
	/**
	 * Table prefix
	 * @var string
	 */
	protected $tblprefix = null;
	/**
	 * If {@link DB::PrepareSQL()} is used the sql query will be stored here
	 * @var string
	 */
	protected $sql = null;

	/**
	 * This constructor is hidden so that an instance of this class can not
	 * be instantiated directly, it needs to be instantiated from a derived
	 * class.
	 *
	 * NOTE! For drivers that don't take username and password, {@link Sqlite}
	 * for instance, these ctor parameters must be defined in the constructor
	 * anyway.
	 *
	 * @param string $host
	 * @param string $dbname
	 * @param string|null $user
	 * @param string|null $pass
	 */
	abstract protected function __construct($host, $dbname, $user=null,
	                                        $pass=null);

	/**
	 * Factory method for creating a new DB driver object
	 *
	 * @throws DBException
	 * @throws DBDriverNotFound
	 * @param string $driver
	 *  The driver to use. Example: `mysql://uname:pword@host/db`
	 * @return DB
	 */
	public static final function Create($driver, $persistent=false)
	{
		$parts  = self::ParseURL($driver);
		$scheme = isset($parts['scheme']) ? $parts['scheme']          : null;
		$host   = isset($parts['host'])   ? $parts['host']            : null;
		$user   = isset($parts['user'])   ? $parts['user']            : null;
		$pass   = isset($parts['pass'])   ? $parts['pass']            : null;
		$db     = isset($parts['path'])   ? trim($parts['path'], '/') : null;

		if (!$scheme)
			throw new DBException("Missing scheme in connection string");

		if (!$host)
			throw new DBException("Missing host in connection string");

		$driver = ucfirst(strtolower($scheme));
		$dri = dirname(__FILE__) . "/{$driver}_driver.php";

		if (file_exists($dri)) {
			require_once $dri;
      $_db = new $driver($host, $db, $user, $pass);
      return $persistent ? $_db->PConnect() : $_db->Connect();
		}
		else
			throw new DBDriverNotFound("The driver [$driver] doesn't exist");
	}

	/**
	 * Set a table prefix. If this is set we can then use a string to subsitute
	 * {@link DB::prefix()} in front of the table names. The string to
	 * substitute is #TPF#.
	 *
	 * @uses $tblprefix
	 * @param string $prefix
	 * @final Can't be overridden
	 */
	public final function TablePrefix($prefix)
	{
		$this->tblprefix = $prefix;
	}

	/**
	 * Build a query.
	 *
	 * Each derived class has a query method. An arbitrary number of arguments,
	 * but at least one, can be passed to that method where the first argument
	 * should be the SQL query it self which can contain a formatted string
	 * ({@link http://php.net/sprintf sprintf()}).
	 *
	 * The formats will then be substituted with the rest of the arguments. So
	 * we pass all arguments to child::Query() as an array to here where we do
	 * the substitution if any are to be done.
	 *
	 * @param array $args
	 * @return string
	 */
	protected final function buildQuery($args)
	{
		if (sizeof($args) > 1) {
			$query = $this->prefix(array_shift($args));
			$args  = array_map(array($this, 'Escape'), $args);
			$query = vsprintf($query, $args);
		}
		else
			$query = $this->prefix($args[0]);

		return $query;
	}

	/**
	 * Replace the #TPF# pattern with our table prefix.
	 *
	 * @param string $query
	 * @return string
	 */
	private final function prefix($query)
	{
		return str_replace("#TPF#", $this->tblprefix, $query);
	}

	/**
	 * Prepare an SQL query. The construction is the same as
	 * {@link http://php.net/sprintf sprintf()}.
	 *
	 * @link DB::buildQuery()
	 * @uses $sql
	 * @param mixed Abritrary numer of arguments
	 */
	public final function PrepareSQL()
	{
		$this->sql = $this->buildQuery(func_get_args());
	}

	/**
	 * Return the SQL query if {@link DB::PrepareSQL()} has been used.
	 * This method is pretty much only for debug usage.
	 *
	 * @return string
	 * @deprecated This method is only kept for compatibility with older
	 * PHP5 versions. Use the functionality of the magic method __toString
	 * instead. That is; just echo the damn object!
	 */
	public function GetSQL()
	{
		return $this->sql;
	}

	/**
	 * Alias for DB::Escape()
	 *
	 * @param string $what
	 * @return string
	 */
	public function Quote($what)
	{
		return $this->Escape($what);
	}

	/**
	 * Parse the connection string.
	 *
	 * @param string $url
	 * @return array
	 */
	public static function ParseURL($url)
	{
		$parts = array();

		$scheme = null;
		$host   = null;
		$user   = null;
		$pass   = null;
		$db     = null;

		if (substr($url, 0, 6) == "sqlite") {
			preg_match('#^([a-z]+)://(.*)$#', $url, $m);
			$scheme = $m[1];
			$host   = $m[2];
		}
		else {
			$parts = parse_url($url);

			$scheme = isset($parts['scheme']) ? $parts['scheme']          : null;
			$host   = isset($parts['host'])   ? $parts['host']            : null;
			$user   = isset($parts['user'])   ? $parts['user']            : null;
			$pass   = isset($parts['pass'])   ? $parts['pass']            : null;
			$db     = isset($parts['path'])   ? trim($parts['path'], '/') : null;
		}

		$parts['scheme'] = $scheme;
		$parts['host']   = $host;
		$parts['user']   = $user;
		$parts['pass']   = $pass;
		$parts['db']     = $db;

		return $parts;
	}

	/**
	 * DTOR
	 * Unset the resource; the database connection
	 */
	public function __destruct()
	{
		if (is_resource($this->resource))
			unset($this->resource);
	}

	/**
	 * Converts the object to a string (See
	 * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}
	 * for a description.
	 * @see PLib::__toString()
	 * @return string
	 */
	public function __toString()
	{
		if (class_exists('PLib'))
			return PLib::__toString($this);
	}

	/**
	 * Magic function __sleep
	 *
	 * See {@link http://php.net/manual/en/language.oop5.magic.php PHP.NET} for a
	 * description.
	 * @magic
	 */
	public final function __sleep()
	{
		$this->Close();
	}

	/**
	 * Magic function __wakeup
	 *
	 * See {@link http://php.net/manual/en/language.oop5.magic.php PHP.NET} for a
	 * description.
	 * @magic
	 */
	public final function __wakeup()
	{
		$this->Connect();
	}
}

/**
 * Master class for database results
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 */
abstract class DBResult
{
	/**
	 * The query result
	 * @var resource
	 */
	protected $result;
	/**
	 * Keep track of how many rows we've looped through
	 * @var int
	 */
	public $counter = 0;

	/**
	 * Constructor
	 *
	 * @uses $result
	 * @param resource $result
	 */
	public function __construct($result)
	{
		$this->result = $result;
	}

	/**
	 * Are we on the last row in the result?
	 *
	 * @return boolean
	 */
	public function Last()
	{
		return $this->counter == $this->NumRows() ? true : false;
	}

	/**
	 * Return the current result index
	 *
	 * @return int
	 */
	public function Counter()
	{
		return $this->counter;
	}

	/**
	 * Destructor
	 * Free the resource
	 */
	public function __destruct()
	{
		unset($this->result);
	}
}

/**
 * General DB exception
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Exception
 * @since 0.10
 */
class DBException extends Exception
{
	public $message;
}

/**
 * Exception class for missing DB-drivers
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Exception
 * @since 0.8
 */
class DBDriverNotFound extends Exception
{
	public $message;
}

/**
 * Exception class for failed connections
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Exception
 */
class DBConnectionFail extends Exception
{
	public $message;
}

/**
 * Exception class when a selected DB doesn't exist
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Exception
 */
class DBNotFound extends Exception
{
	public $message;
}

/**
 * Exception class for failed queries
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Database
 * @subpackage Exception
 */
class DBQueryFail extends Exception
{
	public $message;
}
?>