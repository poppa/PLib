<?php
/**
 * Simple authentication module
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Security
 * @version 0.1
*/

/**
 * We use the SQLite or MySQL class.
*/
require_once PLIB_INSTALL_DIR . '/DB/Database.php';
/**
 * We might need the Cookie class
*/
require_once PLIB_INSTALL_DIR . '/Web/Cookie.php';

/**
 * SimpleAuth authentication class
 *
 * This class provides a simple authentication mechanism. It uses either a
 * SQLite or MySQL database table to store the users and it can use an already
 * existing {@see DB} object or create one. It can either do a "basic
 * authentication" or you can create a web form and tell SimpleAuth to store
 * the authentication in a cookie instead.
 *
 * __Here's how to use a basic authentication__
 *
 * <code>
 * $auth = new SimpleAuth('sqlite:///path/to/db.sqlite', 'myapp');
 *
 * if (!$auth->IsAuthenticated()) {
 *   $auth->BasicAuth();
 *   echo "<h1>401 Unauthorized</h1>";
 *   die;
 * }
 *
 * echo "Hi there " . $auth->Get('realname');
 * </code>
 *
 * __And this is how a cookie authentication could look like__
 *
 * <code>
 * $auth = new SimpleAuth('sqlite:///path/to/db.sqlite', 'myapp');
 * $auth->SetAuthMethod(SimpleAuth::AUTH_COOKIE);
 *
 * if (!$auth->IsAuthenticated()) {
 *   if (isset($_POST['login'])) {
 *     if ($auth->Authenticate($_POST['username'], $_POST['password'])
 *       Redirect($_SERVER['PHP_SELF']);
 *
 *     echo "<p>Bad username or password. Try again!</p>";
 *   }
 *
 *   require_once 'login-form.php';
 *   die;
 * }
 *
 * echo "Hi there " . $auth->Get('realname');
 * </code>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Security
*/
class SimpleAuth
{
	/**
	 * Flag for using basic authentication
	*/
	const AUTH_BASIC = 0;
	/**
	 * Flag for using cookie authentication
	*/
	const AUTH_COOKIE = 1;
	/**
	 * No level matching will be done
	*/
	const LEVEL_VOID = 1;
	/**
	 * User's level must be greater than the level specified
	*/
	const LEVEL_GT   = 2;
	/**
	 * User's level must be less than the level specified
	*/
	const LEVEL_LT   = 4;
	/**
	 * User's level must be equal to the level specified
	*/
	const LEVEL_EQ   = 8;
	/**
	 * Flag to tell we're using SQLite
	*/
	const DB_SQLITE = 0;
	/**
	 * Flag to tell we're using MySQL
	*/
	const DB_MYSQL = 1;
	/**
	 * Salt that's used when creating a password and logging on
	 * @var string
	*/
	protected static $salt = 'plIB1siMP6LEauTH312pASS42wor421DsA217LT';
	/**
	 * The DB object
	 * @var DB
	*/
	protected $db = null;
	/**
	 * Database mode. MySQL or SQLite
	 * @var int
	*/
	protected $dbmode = null;
	/**
	 * The table layout for SQLite
	 * @var string
	*/
	protected $sqliteTableLayout = "
	CREATE TABLE %s (
		uid INTEGER AUTOINCREMENT PRIMARY KEY,
		username VARCHAR(255),
		password BLOB,
		realname VARCHAR(255),
		appname VARCHAR(255),
		level INTEGER,
		email VARCHAR(255)
	);";
  /**
	 * The table layout for MySQL
	 * @var string
	*/
	protected $mysqlTableLayout = "
	CREATE TABLE IF NOT EXISTS %s (
		uid INT(11) AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(255),
		password BLOB,
		realname VARCHAR(255),
		appname VARCHAR(255),
		level TINYINT(3),
		email VARCHAR(255)
	)";
	/**
	 * The name of the DB table
	 * @var string
	*/
	protected static $tableName = 'simpleauth';
	/**
	 * The name of the authentication cookie
	 * @var string
	*/
	protected $authCookieName = 'simpleauth';
	/**
	 * Are we authenticated or not?
	 * @var bool
	*/
	protected $isAuthenticated = false;
	/**
	 * The user object when authenticated
	 * @var SimpleAuthUser
	*/
	protected $user = null;
	/**
	 * Authentication mode.
	 * Default is SimpleAuth::AUTH_BASIC
	 * @var int
	*/
	protected $authmode = self::AUTH_BASIC;
	/**
	 * Realm of the authentication if basic authentication is being used
	 * @var string
	*/
	protected $realm = 'PLib SimpleAuth';
	/**
	 * Name of the application the authentication is valid for.
	 * Useful if the same table should act as authentication resource for more
	 * than one site/application
	 *
	 * @var string
	*/
	protected $appname = null;
	/**
	 * How to compare a user�s level agains required level.
	 * @var int
	*/
	protected $reqLevelRule = SimpleAuth::LEVEL_VOID;
	/**
	 * Required level to authenticate.
	 * @var int
	*/
	protected $requiredLevel = 0;

	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @throws DBException
	 * @throws DBDriverNotFound
	 *
	 * @param string $dbcon
	 *  This can already be an existing database object derived from {@see DB}
	 *  or a connection string according to {@see DB::Create()}.
	 * @param string $appname
	 *  Is used to separate users of different sites/applications. The same
	 *  database and table can be used for multiple sites/application if the
	 *  $appname is set.
	 * @param int $reqLevel
	 *  A level to match a user's level against. How to match it is determined
	 *  by $reqLevelRule.
	 * @param int $reqLevelRule
	 *  Sets the rule for how to match user's levels againt $reqLevel.
	 *  Use the class constants `LEVEL_*` (bit flags) to set the rule.
	 *
	 *  * SimpleAuth::LEVEL_VOID<br/>
	 *    Means I don't give a crap about levels
	 *
	 *  * SimpleAuth::LEVEL_EQ<br/>
	 *    Means the user's level must match the given level.
	 *
	 *  * SimpleAuth::LEVEL_GT<br/>
	 *    The user's level must be greater than the given level.
	 *
	 *  * SimpleAuth::LEVEL_LT<br/>
	 *    The user's level must be less than the given level.
	 *  
	 *  Since the constants are bitflags you can combine `LEVEL_EQ` with
	 *  `LEVEL_GT` or `LEVEL_LT` if a user's level must be equal to OR greater
	 *  than, or equal to OR less than.
	*/
	public function __construct($dbcon, $appname, $reqLevel=0,
	                            $reqLevelRule=SimpleAuth::LEVEL_VOID)
	{
		if (is_object($dbcon) && is_a($dbcon, 'DB'))
			$this->db = $dbcon;
		elseif (is_string($dbcon))
			$this->db = DB::Create($dbcon);
		else
			throw new Exception("\$dbcon must be an object or a string!");

		if (is_a($this->db, 'Mysql'))
			$this->dbmode = self::DB_MYSQL;
		elseif (is_a($this->db, 'Sqlite'))
			$this->dbmode = self::DB_SQLITE;
		else
			throw new Exception('Unsupported database driver given to SimpleAuth!');

		$this->appname    = $appname;
		$this->authCookieName .= '_' . $appname;
		$this->requiredLevel = $reqLevel;
		$this->reqLevelRule = $reqLevelRule;
	}

	/**
	 * Set the authentication mode
	 *
	 * @param int $mode
	 *   Either SimpleAuth::AUTH_BASIC or SimpleAuth::AUTH_COOKIE
	*/
	public function SetAuthMethod($mode)
	{
		if ($mode == self::AUTH_BASIC || $mode == self::AUTH_COOKIE)
			$this->authmode = $mode;
	}

	/**
	 * Setter/getter for the table name
	 *
	 * @param string $tablename
	 * @return string
	*/
	public static function TableName($tablename=null)
	{
		if ($tablename && is_string($tablename))
			self::$tableName = $tablename;
		else
			return self::$tableName;
	}

	/**
	 * Set up the table
	 *
	 * @throws Exception
	*/
	public function SetupTable()
	{
		$table = null;
		if ($this->dbmode == self::DB_SQLITE) {
			$sql = "
			SELECT * FROM sqlite_master WHERE name = 'simpleauth'
			AND type = 'table'";

			$res = $this->db->Query($sql);

			if ($res->NumRows() > 0)
				return;

			$table = $this->sqliteTableLayout;
		}
		else
			$table = $this->mysqlTableLayout;

		try { $this->db->Query($table, self::$tableName); }
		catch (Exception $e) {
			throw new Exception("Couldn't setup database table for SimpleAuth:" .
			                    Plib::PrintException($e, true));
		}
	}

	/**
	 * Set the realm for a basic authentication.
	 *
	 * @param string $realm
	*/
	public function SetRealm($realm)
	{
		$this->realm = $realm;
	}

	/**
	 * Set the password salt to use when storing passwords in the database.
	 *
	 * @param string $salt
	*/
	public static function SetSalt($salt)
	{
		self::$salt = $salt;
	}

	/**
	 * Returns the password salt
	 *
	 * @return string
	*/
	public function GetSalt()
	{
		return self::$salt;
	}

	/**
	 * Check if we've got an authentication or not
	 *
	 * @return bool
	*/
	public function IsAuthenticated()
	{
		$authed = false;
		switch ($this->authmode)
		{
			case self::AUTH_COOKIE:
				$cookie = new Cookie($this->authCookieName);
				$cookie = $cookie->Get(true);

				if (!$cookie)
					return false;

				if ($cookie->appname == $this->appname) {
					$this->user = $cookie;
					$this->user->SetDB($this->db);
					return true;
				}

				return false;

			case self::AUTH_BASIC:
				$u = issetor($_SERVER['PHP_AUTH_USER'], false);
				$p = issetor($_SERVER['PHP_AUTH_PW'], false);

				if (!$u && !$p)
					return false;

				return $this->Authenticate($u, $p);
		}
		return $authed;
	}

	/**
	 * Invoke a basic authentication
	 *
	 * You can take care of the logic behind an aborted authentication:
	 *
	 * <code>
	 * $auth = new SimpleAuth('sqlite:///path/to/db.sqlite', 'myapp');
	 *
	 * if (!$auth->IsAuthenticated()) {
	 *   $auth->BasicAuth();
	 *   echo "<h1>401 Unauthorized!</h1>";
	 *   die;
	 * }
	 *
	 * echo "You'r in";
	 * </code>
	*/
	public function BasicAuth()
	{
		if (!$this->IsAuthenticated()) {
	    header('WWW-Authenticate: Basic realm="' . $this->realm . '"');
	    header('HTTP/1.0 401 Unauthorized');
		}
	}

	/**
	 * Authenticate a user
	 *
	 * @throws Exception
	 * @param string $username
	 * @param string $password
	 * @return bool
	*/
	public function Authenticate($username, $password)
	{
		$sql = "
		SELECT * FROM %s
		WHERE username = '%s'
		AND password = '%s' AND appname = '%s'";

		try {
			$pw = sha1($password . self::$salt);
			$res = $this->db->Query($sql, self::$tableName, $username, $pw,
			                        $this->appname);
			if ($res->NumRows() == 1) {

				$this->user = new SimpleAuthUser($res, $this->db);

				if ($this->reqLevelRule != SimpleAuth::LEVEL_VOID) {
					$auth = 1;
					if ($this->reqLevelRule == SimpleAuth::LEVEL_EQ) {
						if ($this->user->level != $this->requiredLevel)
							$auth = 0;
					}
					elseif ($this->reqLevelRule == (self::LEVEL_EQ|self::LEVEL_LT)) {
						if ($this->user->level > $this->requiredLevel)
							$auth = 0;
					}
					elseif ($this->reqLevelRule == (self::LEVEL_EQ|self::LEVEL_GT)) {
						if ($this->user->level < $this->requiredLevel)
							$auth = 0;
					}
					elseif ($this->reqLevelRule == self::LEVEL_GT) {
						if ($this->user->level <= $this->requiredLevel)
							$auth = 0;
					}
					elseif ($this->reqLevelRule == self::LEVEL_LT) {
						if ($this->user->level >= $this->requiredLeve)
							$auth = 0;
					}

					if (!$auth) {
						$this->user = null;
						return false;
					}
				}

				if ($this->authmode == self::AUTH_COOKIE) {
					$cookie = new Cookie($this->authCookieName);
					$cookie->Set($this->user, 0, true);
				}
				return true;
			}
			return false;
		}
		catch (Exception $e) {
			throw new Exception("Error in SimpleAuth: " .
			                    PLib::PrintException($e, true));
		}
	}

	/**
	 * Drop the authentication.
	 * Only works for cookie authentication.
	*/
	public function UnAuthenticate()
	{
		if ($this->authmode == self::AUTH_COOKIE) {
			$cookie = new Cookie($this->authCookieName);
			$cookie->Delete();
		}
	}

	/**
	 * Add a user to the database
	 *
	 * @throws Exception
	 * @param string $username
	 * @param string $password
	 * @param string $realname
	 * @param string $email
	 * @param string $level
	 *   Authentication level. This has no meaning if you don't give it one
	 * @return bool
	*/
	public function AddUser($username, $password, $realname, $email, $level=0,
	                        $appname=null)
	{
		$appname = $appname ? $appname : $this->appname;
		$sql = "SELECT uid FROM %s WHERE username='%s' AND appname='%s'";
		$res = $this->db->Query($sql, self::$tableName, $username, $appname);

		if ($res->NumRows() != 0) {
			throw new Exception("SimpleAuth: A user with the username \"$username\" ".
			                    "already exist!");
		}

		$sql = "
		INSERT INTO %s (
			uid, username, password, realname, email, appname, level
		)
		VALUES (NULL, '%s', '%s', '%s', '%s', '%s', %d)";

		try {
			$pw = sha1($password . self::$salt);
			$this->db->Query($sql, self::$tableName, $username, $pw, $realname,
			                 $email, $appname, (int)$level);
		}
		catch (Exception $e) {
			throw new Exception("SimpleAuth: Couldn't add the user \"$username\"!\n" .
			                    PLib::PrintException($e, true));
		}

		return $this->db->InsertID();
	}

	/**
	 * Delete a user
	 *
	 * @throws Exception
	 * @param string|int $userId
	 * @return bool
	*/
	public function DropUser($userId)
	{
		$userId = (int)$userId;
		$sql = "DELETE FROM %s WHERE uid = %d";

		try { $this->db->Query($sql, self::$tableName, $userId); }
		catch (Exception $e) {
			throw new Exception("SipleAuth: Couldn't remove user with id " .
			                    "\"$userId\"!\n" . PLib::PrintException($e, true));
		}
		return true;
	}

	/**
	 * Getter for user fields
	 *
	 * @param string $which
	 *   The field to get.
	*/
	public function Get($which)
	{
		return $this->user->{$which};
	}

	/**
	 * Set any of the user database fields
	 *
	 * @param string $which
	 * @param string $what
	 * @return bool
	*/
	public function Set($which, $what)
	{
		return $this->user->{$which} = $what;
	}

	/**
	 * Returns a @see{SimpleAuthUser} object if the user exists
	 *
	 * @throws DBQueryFail
	 * @param string|int $handle
	 *   Can either be the username or the user id.
	 * @param bool $tieToAppname
	 *   Only allow users of the current appname
	 * @return SimpleAuthUser|bool
	*/
	public function GetUser($handle, $tieToAppname=true)
	{
		$sql = "SELECT * FROM %s WHERE %s = '%s'";
		$field = is_numeric($handle) ? 'uid' : 'username';

		if ($tieToAppname)
			$sql .= sprintf(" AND appname = '%s'", $this->db->Quote($this->appname));

		try {
			$res = $this->db->Query($sql, self::$tableName, $field, $handle);
			if ($res->NumRows() == 1)
				return new SimpleAuthUser($res, $this->db);
		}
		catch (DBQueryFail $e) {
			throw $e;
		}
		return false;
	}

	/**
	 * Just prints out the contents of the database table
	*/
	public function Inspect($tieToAppname=true)
	{
		$sql = "SELECT * FROM %s";

		if ($tieToAppname)
			$sql .= " WHERE appname = '" . $this->db->Escape($this->appname) . "'";

		$res = $this->db->Query($sql, self::$tableName);

		if ($res->NumRows() > 0) {
			if (!class_exists('SQLTablify'))
				require_once PLIB_INSTALL_DIR . '/DB/SQLTablify.php';

			static $tablify;

			if (!$tablify) {
				$tablify = new SQLTablify(array(
					'HeaderRow'   => true,
					'Linkify'     => true,
					'Interactive' => true
				));
				$tablify->Sort(0, SORT_ASC);
			}
			$tablify->Parse($res, true);
			echo $tablify->Render();
		}
		else
			wbr("SimpleAuth has no users!");
	}

	/**
	 * Returns a result set of the entire table
	 *
	 * @throws Exception
	 * @param bool $tieToAppName
	 *   Only list users belongs to the current appname
	 * @return DBResult
	*/
	public function Iterator($tieToAppname=true)
	{
		$sql = "SELECT * FROM %s";

		if ($tieToAppname)
			$sql .= " WHERE appname = '" . $this->db->Escape($this->appname) . "'";

		return $this->db->Query($sql, self::$tableName);
	}

	/**
	 * Returns the number of users in the table
	 *
	 * @param bool $tieToAppName
	 *   If "false" all users will be counted. Default is to count only the users
	 *   beloning to the current appname
	 * @return int
	*/
	public function NumberOfUsers($tieToAppName=true)
	{
		$ret = 0;
		try {
			$sql = "SELECT COUNT(uid) FROM %s";
			if ($tieToAppName) {
				$sql .= sprintf(" WHERE appname = '%s'",
				                $this->db->Quote($this->appname));
			}

			$res = $this->db->Query($sql, SimpleAuth::$tableName);
			$ret = $res->Result();
		}
		catch (Exception $e) {
			throw $e;
		}

		return $ret;
	}

	/**
	 * Returns the full path to the DB file
	 *
	 * @return string
	*/
	public function GetDbPath()
	{
		return $this->dbfullpath;
	}
}

/**
 * A class representing a user in {@see SimpleAuth}.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Security
*/
class SimpleAuthUser
{
	/**
	 * Keys in this array is not settable
	 * @var array
	*/
	protected $unsettable = array('uid');
	/**
	 * Keys in this array must be unique
	 * @var array
	*/
	protected $unique = array('username');
	/**
	 * Keys in this array must not be empty.
	 * @var array
	*/
	protected $nonempty = array('password' => 4);
	/**
	 * The DB object
	 * @var DB
	*/
	protected $db = null;
	/**
	 * Container for the object members connected to the user
	 * @var array
	*/
	protected $members = array();

	/**
	 * Creates a new {@see SimpleAuth} user object.
	 * This class should not be instatiated directly. Call
	 * {@see SimpleAuth::GetUser()} instead.
	 *
	 * @param DBResult $res
	 * @param DB $db
	*/
	public function __construct(DBResult $res, DB $db)
	{
		foreach ($res->Fetch() as $key => $val)
			$this->members[$key] = $val;

		$this->db = $db;
	}

	/**
	 * Set the DB object
	 *
	 * @param DB $db
	*/
	public function SetDB(DB $db)
	{
		$this->db = $db;
	}

	/**
	 * Getter for user fields
	 *
	 * @param string $which
	 *   The field to get.
	*/
	public function Get($which)
	{
		return issetor($this->members[$which], false);
	}

	/**
	 * Set any of the user database fields
	 *
	 * @param string $which
	 * @param string $what
	 * @return bool
	*/
	public function Set($which, $what)
	{
		return $this->__set($which, $what);
	}

	/**
	 * Getter.
	 *
	 * @param string $which
	 * @return mixed
	*/
	public function __get($key)
	{
		return issetor($this->members[$key], false);
	}

	/**
	 * Setter
	 *
	 * @throws Exception
	 * @param string $which
	 * @param mixed $what
	*/
	public function __set($key, $value)
	{
		if (!isset($this->members[$key]))
			return false;

		if (in_array($key, $this->unsettable))
			return false;

		if (in_array($key, array_keys($this->nonempty))) {
			$minlen = $this->nonempty[$key];
			if (strlen($value) < $minlen)
				throw new Exception("$key in SimpleAuthUser must be at least $minlen ".
				                    "characters long!");
		}

		return $this->updateField($key, $value);
	}

	/**
	 * Update field $which with the value of $what
	 *
	 * @throws Exception
	 * @param string $which
	 * @param string $what
	 * @return bool
	*/
	protected function updateField($which, $what)
	{
		$table = SimpleAuth::TableName();

		if (in_array($which, $this->unique)) {
			$sql = "
			SELECT %s FROM %s
			WHERE %s = '%s' AND appname = '%s'";
			try {
				$res = $this->db->Query($sql, $which, $table, $which, $what,
				                        $this->appname);
				if ($res->NumRows() != 0) {
					$oldname = $res->Fetch();
					if ($oldname != $this->{$which}) {
						throw new Exception("SimpleAuth: The $which \"$$what\" " .
						                    "already exist!");
					}
				}
			}
			catch (Exception $e) {
				throw new Exception("SimpleAuth: " . PLib::PrintException($e, true));
			}
		}

		if ($which == 'password')
			$what = sha1($what . SimpleAuth::GetSalt());

		$sql = "UPDATE %s SET %s = '%s' WHERE uid = %d";
		try { $this->db->Query($sql, $table, $which, $what, $this->uid); }
		catch (Exception $e) {
			throw new Exception("SimpleAuth: Couldn't update \"$which\" for " .
			                    "\"{$this->username}\"!\n" .
			                    PLib::PrintException($e, true));
		}

		$this->members[$which] = $what;

		return true;
	}

	/**
	 * For the serialization of this object to work properly
	 *
	 * @return array
	*/
	public function __sleep()
	{
		return array('unsettable', 'unique', 'nonempty', 'members');
	}
}
?>