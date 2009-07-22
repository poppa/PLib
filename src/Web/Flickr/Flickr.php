<?php
/**
 * The core API class.
 *
 * Once this class in included you don't need to explicitly include the
 * method classes since they will be autoloaded if not loaded.
 *
 * <code>
 * PLib::Import('Web.Flickr.Flickr');
 *
 * $api = new Flickr(...);
 *
 * //! The Photoset.php file will be loaded automatically
 * $photoset = new FlickrPhotoset($api, 'photoset-id');
 * </code>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.2
 * @package Web
 * @subpackage Flickr
 * @uses HTTPRequest
 * @uses FlickrCache
 * @uses FlickrException
 * @uses FlickrRequest
 * @uses FlickrResponse
 * @uses FlickrMethod
 */

/**
 * The FlickrRequest class depends on the {@see HTTPRequest} class
 */
require_once PLIB_INSTALL_DIR . '/Protocols/Net.php';

/**
 * The location of this file
 */
define("PLIB_FLICKR_ROOT", dirname(__FILE__));

/**
 * Autoload function for the Flickr method class files
 *
 * @param string $class
*/
function __autoload($class)
{
	$class = PLIB_FLICKR_ROOT . PLIB_DS . substr($class, 6) . '.php';
	if (file_exists($class))
		require_once $class;
}

$_this = PLIB_FLICKR_ROOT . DIRECTORY_SEPARATOR;
$_inc  = $_this . '__includes' . DIRECTORY_SEPARATOR;

/**
 * Include the cache class
*/
require_once $_inc . 'Cache.php';
/**
 * Include the Request class
*/
require_once $_inc . 'Request.php';
/**
 * Include the Response class
*/
require_once $_inc . 'Response.php';

//! Clear temporary variables
unset($_this, $_inc);

/**
 * The core Flickr API class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Flickr
 * @depends HTTPRequest
 * @depends FlickrCache
 * @depends FlickrRequest
 * @depends FlickrResponse
 * @depends FlickrException
 * @depends FlickrMethod
 */
class Flickr
{
	/**
	 * The query param for the API key
	 */
	const API_KEY = 'api_key';
	/**
	 * The query param for the API secret
	 */
	const API_SECRET = 'api_secret';
	/**
	 * The query param for the API signature
	 */
	const API_SIG = 'api_sig';
	/**
	 * The query param for the auth token
	 */
	const AUTH_TOKEN = 'auth_token';
	/**
	 * The query param for the frob
	 */
	const FROB = 'frob';
	/**
	 * The query param for the permisson
	 */
	const PERMS = 'perms';
	/**
	 * The query param for the method
	 */
	const METHOD = 'method';
	/**
	 * The query param for the response format
	 */
	const FORMAT = 'format';
	/**
	 * The query param for the privacy filter
	 */
	const PRIVACY_FILTER = 'privacy_filter';
	/**
	 * Read permission
	 */
	const PERM_READ = 'read';
	/**
	 * Write permission
	 */
	const PERM_WRITE = 'write';
	/**
	 * Delete permission
	 */
	const PERM_DELETE = 'delete';
	/**
	 * Default response format
	 */
	const RESPONSE_FORMAT = 'php_serial';
	/**
	 * Default API endpoint url
	 */
	const ENDPOINT_URL = 'http://www.flickr.com/services/rest/';
	/**
	 * Default authentication endpoint url
	 */
	const AUTH_ENDPOINT_URL = 'http://www.flickr.com/services/auth/';
	/**
	 * The URL to Flickr's web site
	 * @since 0.2
	 */
	const FLICKR_ENPOINT = 'http://www.flickr.com';
	/**
	 * Debug flag.
	 * @since 0.2
	 * @var bool
	 */
	public static $DEBUG = false;
	/**
	 * The API key
	 * @var string
	 */
	private $key = null;
	/**
	 * The API secret
	 * @var string
	 */
	private $secret = null;
	/**
	 * The API token
	 * @var string
	 */
	private $token = null;
	/**
	 * The API enpoint url
	 * @var string
	 */
	private $endpointURL = Flickr::ENDPOINT_URL;
	/**
	 * The response format
	 * @var string
	 */
	private $responseFormat = Flickr::RESPONSE_FORMAT;
	/**
	 * The cache dir
	 * @var string
	 */
	private $cacheDir = null;
	/**
	 * The cache object
	 * @var FlickrCache
	 */
	private $cache = null;
	/**
	 * The user ID
	 * @var string
	 */
	private $userID = null;
	/**
	 * The username
	 * @var string
	 */
	private $username = null;
	/**
	 * The users full name
	 * @var string
	 */
	private $fullname = null;
	/**
	 * The current permisson
	 * @var string
	 */
	private $perms = null;
	/**
	 * Use cacheing?
	 * @var bool
	 */
	private $useCache = false;
	/**
	 * Error object
	 * @since 0.2
	 * @var FlickrError
	 */
	private $error = null;

	/**
	 * Creates a new Flickr object
	 *
	 * @throws FlickrException
	 *   If $cacheDir is set and it's not a directory or the directory
	 *   isn't writable.
	 * @param string $key
	 * @param string $secret
	 * @param string $token
	 * @param string $cacheDir
	 */
	public function __construct($key, $secret, $token=null, $cacheDir=null)
	{
		$this->key      = $key;
		$this->secret   = $secret;
		$this->token    = $token;
		$this->cacheDir = $cacheDir;

		if (!is_null($this->cacheDir)) {
			$this->useCache = true;
			$this->cache = new FlickrCache($this);
		}
	}

	/**
	 * Getter/setter for the API key
	 *
	 * @param string $key
	 * @return string
	 */
	public function Key($key=null)
	{
		if ($key)
			$this->key = $key;

		return $this->key;
	}

	/**
	 * Getter/setter for the API secret
	 *
	 * @param string $secret
	 * @return string
	 */
	public function Secret($secret=null)
	{
		if ($secret)
			$this->secret = $secret;

		return $this->secret;
	}

	/**
	 * Getter/setter for the API token
	 *
	 * @param string $token
	 * @return string
	 */
	public function Token($token=null)
	{
		if ($token)
			$this->token = $token;

		return $this->token;
	}

	/**
	 * Getter/setter for the endpoint URL
	 *
	 * @param string $endpoint
	 * @return string
	 */
	public function Endpoint($endpoint=null)
	{
		if ($endpoint)
			$this->endpointURL = $endpoint;

		return $this->endpointURL;
	}

	/**
	 * Getter/setter for the permission
	 *
	 * @param string $endpoint
	 * @return string
	 */
	public function Permisson($perm=null)
	{
		if ($perm && ($perm == Flickr::PERM_READ ||
		              $perm == Flickr::PERM_WRITE ||
		              $perm == Flickr::PERM_DELETE))
		{
			$this->perms = $perm;
		}

		return $this->perms;
	}

	/**
	 * Are we using caching or not
	 *
	 * @return bool
	 */
	public function UseCache()
	{
		return $this->useCache;
	}

	/**
	 * Getter/setter for the cachedir
	 *
	 * @throws FlickrException
	 *   If the cachedir isn't a directory or isn't writable
	 * @param string $cacheDir
	 * @return string
	 */
	public function CacheDir($cacheDir=null)
	{
		if (!is_null($cacheDir)) {
			$this->cacheDir = $cacheDir;
			$this->useCache = true;
			$this->cache = new FlickrCache($this);
		}

		return $this->cacheDir;
	}

	/**
	 * Getter/setter for the response format
	 *
	 * @param string $responseFormat
	 * @return string
	 */
	public function ResponseFormat($responseFormat=null)
	{
		if ($responseFormat)
			$this->responseFormat = $responseFormat;

		return $this->responseFormat;
	}

	/**
	 * Returns the cache object
	 *
	 * @return FlickrCache
	 */
	public function Cache()
	{
		return $this->cache;
	}

	/**
	 * Returns whether we're authenticated or not
	 *
	 * @return bool
	 */
	public function IsAuthenticated()
	{
		return $this->UserID();
	}

	/**
	 * Returns the user id or tries to fetch it if not set.
	 *
	 * @return string
	 */
	public function UserID()
	{
		if (!$this->userID) {
			try {
				$res = $this->execute('flickr.auth.checkToken');
				if (!$res)
					$this->userID = null;
				else {
					if (isset($res->auth)) {
						$this->token    = $res->auth->token;
						$this->userID   = $res->auth->user->nsid;
						$this->username = $res->auth->user->username;
						$this->fullname = $res->auth->user->fullname;
					}
				}
			}
			catch (FlickrException $e) {
				if (Flickr::$DEBUG)
					PLib::PrintException($e);

				$this->userID = null;
			}
		}
		return $this->userID;
	}

	/**
	 * Returns a Flickr token
	 *
	 * @param string $perms
	 * @param string $frob
	 * @return string
	 */
	public function GetToken($perms, $frob)
	{
		$this->Permisson($perms);
		$params = array(Flickr::FROB => $frob);

		try {
			$res = $this->execute('flickr.auth.getToken', $params);

			if ($res && isset($res->auth)) {
				$this->token    = $res->auth->token;
				$this->userID   = $res->auth->user->nsid;
				$this->username = $res->auth->user->username;
				$this->fullname = $res->auth->user->fullname;
			}
		}
		catch (FlickrException $e) {
			if (Flickr::$DEBUG)
				PLib::PrintException($e);

			$this->token = null;
		}

		return $this->token;
	}

	/**
	 * Returns the frob.
	 *
	 * @since 0.2
	 * @return string
	 */
	public function GetFrob()
	{
		try {
			$res = $this->execute('flickr.auth.getFrob', array());
			if ($res)
				return $res->frob;
		}
		catch (Exception $e) {
			if (self::$DEBUG)
				PLib::PrintException($e);
		}

		return null;
	}

	/**
	 * Creates an authentication URL.
	 *
	 * @param string $perms
	 * @return string
	 */
	public function GetAuthURL($perms)
	{
		$this->perms = $perms;
		$params = array(
			Flickr::API_KEY => $this->key,
			Flickr::API_SECRET  => $this->secret,
			Flickr::PERMS   => $perms
		);

		$params = FlickrRequest::SignParams($this->secret, $params);
		return Flickr::AUTH_ENDPOINT_URL . '?' . http_build_query($params);
	}

	/**
	 * Sets a new error object
	 *
	 * @param int $code
	 * @param string $message
	 * @return void
	 */
	public function SetError($code, $message)
	{
		$this->error = new FlickrError($code, $message);
	}

	/**
	 * Returns the error object and resets it
	 *
	 * @since 0.2
	 * @return FlickrError
	 *   Returns NULL if no error has occured
	 */
	public function LastError()
	{
		$e = $this->error;
		$this->error = null;
		return $e;
	}

	/**
	 * Executes a Flickr method
	 *
	 * @param string $method
	 * @param array $params
	 * @return FlickrResponse
	 */
	private function execute($method, array $params=array())
	{
		if (!isset($params[self::API_KEY]))
			$params[self::API_KEY] = $this->key;

		if (!isset($params[self::METHOD]))
			$params[self::METHOD] = $method;

		if (!isset($params[self::FORMAT]))
			$params[self::FORMAT] = $this->responseFormat;

		if (!isset($params[self::PERMS]))
			$params[self::PERMS] = $this->Permisson();

		if ($this->token && !isset($params[self::AUTH_TOKEN]))
			$params[self::AUTH_TOKEN] = $this->token;

		$params  = FlickrRequest::SignParams($this->secret, $params);
		$request = new FlickrRequest($this, $method, $params);
		$res     = $request->Query($this->UseCache());

		return $res;
	}

	/**
	 * Converts the object to a string representation
	 *
	 * @return String
	 */
	public function __toString()
	{
		return print_r($this, true);
	}
}

/**
 * A base class for all Flickr methods
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
 */
abstract class FlickrMethod
{
	/**
	 * The Flickr API object
	 * @var Flickr
	 */
	protected $api;

	/**
	 * Hidden constructor.
	 * This class must be instantiated through an inheriting class.
	 *
	 * @param Flickr $api
	 */
	protected function __construct(Flickr $api)
	{
		if (!$api->IsAuthenticated())
			throw new FlickrException("User not autenticated!");

		$this->api = $api;
	}

	/**
	 * Executes a Flicker method
	 *
	 * @throws FlickrException
	 *   If HTTP query fails.
	 * @param string $method
	 * @param array $params
	 * @param bool $useCache
	 * @param string $cachePrefix
	 * @return FlickrResponse
	 */
	protected function execute($method, array $params=array(), $useCache=null,
	                           $cachePrefix=null)
	{
		if (is_null($useCache))
			$useCache = $this->api->UseCache();

		$params[Flickr::API_KEY] = $this->api->Key();
		$params[Flickr::METHOD]  = $method;
		$params[Flickr::FORMAT]  = $this->api->ResponseFormat();
		$params[Flickr::PERMS]   = $this->api->Permisson();

		if ($this->api->Token())
			$params[Flickr::AUTH_TOKEN] = $this->api->Token();

		$params  = FlickrRequest::SignParams($this->api->Secret(), $params);
		$request = new FlickrRequest($this->api, $method, $params, $cachePrefix);
		$res     = $request->Query($cachePrefix, $useCache);

		return $res;
	}

	/**
	 * Constructs the full URL to a Flicker photo
	 *
	 * @param stdClass $response
	 *   The part from {@see FlickrResponse} that contain photo info.
	 * @return string
	 */
	protected function getPhotoSrc($response)
	{
		$p = $response;
		$url = 'farm' . $p->farm . '.static.flickr.com/' . $p->server . '/' .
		       $p->id . '_' . $p->secret . '.jpg';

		return 'http://' . $url;
	}

	/**
	 * Download a Flickr image
	 *
	 * @param string $src
	 * @param string $saveTo
	 *   Directory to save to
	 * @return string
	 *   The path to the downloaded file
	 */
	protected function download($src, $saveTo)
	{
		$filename = rtrim($saveTo, '/') . '/' . basename($src);
		$cli = new HTTPRequest();
		$resp = $cli->Get($src);

		if ($resp) {
			file_put_contents($filename, (string)$data);
			return $filename;
		}
		return false;
	}
}

/**
 * Generic error class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Flickr
 * @version 0.1
*/
class FlickrError
{
	/**
	 * Response/error code
	 * @var int
	*/
	protected $code	= 0;
	/**
	 * Response/error message
	 * @var string
	*/
	protected $message = null;

	/**
	 * Creates a new FlickrError object
	 *
	 * @param int $code
	 * @param string $message
	*/
	public function __construct($code, $message)
	{
		$this->code = $code;
		$this->message = $message;
	}

	/**
	 * Getter for the error code
	 *
	 * @return int
	*/
	public function GetCode()
	{
		return $this->code;
	}

	/**
	 * Getter for the error message
	 *
	 * @return string
	*/
	public function GetMesssage()
	{
		return $this->message;
	}
}

/**
 * Generic Flickr exception class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
*/
class FlickrException extends Exception {}
?>