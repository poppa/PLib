<?php
/**
 * Various net related classes.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.3
 * @package Protocols
 * @example Net.xmpl
 * @uses StringReader
 */

/**
 * The {@see HTTPResponse} needs the {@link StringReader} class.
 */
require_once PLIB_INSTALL_DIR . '/String/String.php';

/**
 * Class for handling HTTP Queries
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.1
 * @since 0.3
 * @package Protocols
 * @subpackage HTTP
 */
class HTTPRequest
{
	/**
	 * Timeout in seconds
	 * @var int
	 */
	public $timeout = 30;
	/**
	 * Default transfer encoding
	 * @var string
	 */
	public $encoding = 'UTF-8';
	/**
	 * Request headers.
	 * @var array
	 */
	protected $requestHeaders = array(
		'User-Agent' => 'PLib HTTPClient (http://plib.poppa.se)',
		'Connection' => 'Close'
	);
	/**
	 * HTTP version to use for the request
	 * @var string
	 */
	protected $httpVersion = '1.1';
	/**
	 * Use persistent connection or not.
	 * @var bool
	 */
	protected $keepAlive = false;
	/**
	 * How many redirects to follow.
	 * @var int
	 */
	protected $maxRedirects = 5;
	/**
	 * A cookie jar, if we wan't to keep cookie information
	 * @var HTTPCookie
	 */
	protected $cookie = null;
	/**
	 * Number of seconds to use a cached version of the request.
	 * 0 means don't use cache at all
	 * @var int
	 */
	protected $cache = 0;
	/**
	 * Key for a request's cache (MD5 sum of the request header)
	 * @var string
	 */
	protected $cachekey = null;
	/**
	 * Keeps track of the number of redirects
	 * @var int
	 */
	protected $recursions = 0;

	/**
	 * Creates a HTTP-request object
	 *
	 * @param HTTPCookie $cookie
	 *  If you want to enable cookies pass an instance of {@see HTTPCookie}
	 *  as first argument.
	 * @param string $encoding
	 *  Transfer encoding
	 * @param string $version
	 *  HTTP version to use
	 */
	public function __construct(HTTPCookie $cookie=null, $encoding='UTF-8',
	                            $version='1.1')
	{
		$this->cookie   = $cookie;
		$this->encoding = $encoding;
		$this->version  = $version;
	}

	/**
	 * Getter/setter for persistent connection.
	 *
	 * @param bool $value
	 * @return bool
	 */
	public function KeepAlive($value=null)
	{
		if (is_bool($value)) {
			$this->keepAlive = $value;
			$this->requestHeaders['Connection'] = $value ? 'Keep-Alive' : 'Close';
		}

		return $this->keepAlive;
	}

	/**
	 * Set a request header
	 *
	 * @param string $name
	 *  The name of the header to set
	 * @param mixed $value
	 *  The value to set
	 */
	public function SetHeader($name, $value)
	{
		$this->headers[$name] = $value;
	}

	/**
	 * Returns the array of request headers
	 *
	 * @return array
	 */
	public function Headers()
	{
		return $this->headers;
	}

	/**
	 * Enable/disable cache.
	 *
	 * @param int $seconds
	 *  Number of seconds to use a cached request.
	 *  0 means don't use cache at all.
	 */
	public function Cache($seconds)
	{
		$this->cache = $seconds;
	}

	/**
	 * Getter/setter for how many redirects to follow
	 *
	 * @param int $max
	 * @return int
	 */
	public function MaxRedirects($max=null)
	{
		if (is_int($max)) $this->maxRedirects = $max;
		return $this->maxRedirects;
	}

	/**
	 * Do an arbitrary HTTP action
	 *
	 * @throws HTTPMaxRedirectException
	 * @throws HTTPRequestException
	 * @throws HTTPResponseException
	 *
	 * @param string $method
	 *  What method to use: `GET`, `POST`, `PROPPATCH`...
	 * @param string $url
	 *  Where to send the request. A full URI: http://server.com/path/
	 * @param array $vars
	 *  Query variables to send. An associative array with key/value pairs
	 * @param array $headers
	 *  Additional request headers
	 * @param string $data
	 *  Data to send as raw data in a SOAP call for instance
	 *
	 * @return HTTPResponse
	 */
	public function DoMethod($method, $uri, $vars=array(), $headers=array(),
	                         $data=null)
	{
		$host   = null;
		$port   = null;
		$path   = null;
		$query  = null;
		$body   = null;
		$sock   = null;
		$method = strtoupper($method);

		if ($this->recursions >= $this->maxRedirects) {
			throw new HTTPMaxRedirectException(
				"Redirect limit ($this->maxRedirects) exceeded!"
			);
		}

		$request = array();

    // It's an assignment
		if (!($request = parse_url($uri)))
			throw new HTTPRequestException("Bad URL ($uri) as argument");

		if (!isset($request['host']))
			throw new HTTPRequestException("Missing host in URL ($uri) argument");

		$protocol = isset($request['scheme']) ? $request['scheme'] : false;
		$host     = $request['host'];
		$port     = isset($request['port']) ? (int)$request['port'] : 80;
		$query    = isset($request['query']) ? $request['query'] : null;

		if (!empty($vars))
			$query = http_build_query($vars);

		//! Default the request path to root
		$path = isset($request['path']) ? $request['path'] : '/';

		$addHeader = "";
		if (!empty($headers))
			$this->requestHeaders = $headers + $this->requestHeaders;

		foreach ($this->requestHeaders as $key => $val) {
			if (!is_string($key))
				throw new HTTPRequestException("Malformed header, missing key.");

			if (empty($val))
				throw new HTTPRequestException("Malformed header, missing value " .
				                               "for key \"$key\"");
			//! Due to a bug in PHP5.2?
			if ($key == 'Accept-Encoding')
				continue;

			$addHeader .= "$key: $val\r\n";
		}

		if ($this->cookie) {
			$c = $this->cookie->CreateHeader(($port==443), $host, $path);
			if ($c !== false)
				$addHeader .= $c;
		}

		switch ($method)
		{
			case 'GET':
				$path .= $query ? '?' . $query : '';
				break;

			case 'POST':
				$body = empty($data) ? $query : urlencode($data);
				if (!isset($this->headers['Content-Type']))
					$addHeader .= "Content-Type: application/x-www-form-urlencoded\r\n";
				$addHeader .= "Content-Length: " . strlen($body) . "\r\n\r\n" . $body;
				break;

			default:
				$body = $data;
				if (!isset($this->headers['Content-Type']))
					$addHeader .= "Content-Type: text/plain\r\n";
				$addHeader .= "Content-Length: " . strlen($body) . "\r\n\r\n" . $body;
				break;
		}

		$header = "$method $path HTTP/$this->version\r\n" .
		          "Host: $host\r\n$addHeader\r\n";

		$rawresponse = false;
		if ($this->cache > 0)
			$rawresponse = $this->getCache($header);

		if ($rawresponse === false) {
			$errno = 0;
			$errstr = null;
      // It's an assignment
			if (!($sock = @fsockopen($host, $port, $errno, $errstr, $this->timeout)))
			{
				throw new HTTPRequestException(
					"Couldn't fetch $host! $errstr (errno: $errno)"
				);
			}

			if (!is_resource($sock))
				throw new HTTPRequestException("Couldn't connect to $host");

			fputs($sock, $header);

			$rawresponse = "";
			while (!feof($sock))
				$rawresponse .= fgets($sock, 512);
		}
		else
			$rawresponse = file_get_contents($rawresponse);

		$resp = new HTTPResponse($rawresponse);

		$cookie = null;
    // It's an assignment
		if ($this->cookie && ($cookie = $resp->GetHeader('set-cookie')))
			$this->cookie->Set($host, $cookie);

		if ($resp->Status() != 200) {
			if ($resp->Status() == 302 || $resp->Status() == 301) {
				$location = $resp->GetHeader('location');
				if ($location != $path && !strpos($location, '://'))
					$url = $protocol . "://" . $host . $location;
				else
					$url = $location;

				$headers["Referer"] = $url;

				if ($cookie)
					$headers["Cookie"] = $cookie;

				$this->recursions++;
				$vars = array();
				return $this->DoMethod($method, $url, $vars, $headers, $data);
			}
		}

		if ($this->cache > 0)
			$this->writeCache($header, $rawresponse, $resp->Status());

		$this->recursions = 0;

		return $resp;
	}

	/**
	 * Shortcut for {@link HTTPRequest::DoMethod()} with a GET method.
	 *
	 * @throws HTTPMaxRedirectException
	 * @throws HTTPRequestException
	 * @throws HTTPResponseException
	 *
	 * @param string $url
	 *  Remote URL
	 * @param array $vars
	 *  Associative array with query variables
	 * @return HTTPResponse
	 */
	public function Get($url, $vars=array(), $headers=array())
	{
		if (strpos($url, '?') !== false) {
			list($url, $vars) = explode('?', $url);
			$vars = Net::QueryToArray($vars);
		}
		return $this->DoMethod('GET', $url, $vars, $headers);
	}

	/**
	 * Shortcut for {@link HTTPRequest::DoMethod()} with a POST method
	 *
	 * @throws HTTPMaxRedirectException
	 * @throws HTTPRequestException
	 * @throws HTTPResponseException
	 *
	 * @param string $url
   *  Remote URL
	 * @param array  $vars
   *  Assoc array with query variables
	 * @return string|bool
	 */
	public function Post($url, $vars=array(), $headers=array())
	{
		return $this->DoMethod('POST', $url, $vars, $headers);
	}

	/**
	 * Clear the current cache
	 */
	public function ClearCache()
	{
		$file = Net::TMPDir() . "/{$this->cachekey}.cache";
		if (file_exists($file))
			unlink($file);
	}

	/**
	 * Generates an MD5 string out of the request header that will be used as
	 * the name of the cache file
	 *
	 * @param string $in
	 *  This should be the entire request header
	 * @return string
	 */
	protected function createCacheKey($in)
	{
		return md5($in);
	}

	/**
	 * Write the cache to the tmp directory
	 *
	 * @param string $header
	 *  The raw header for the request
	 * @param string $response
	 *  the entire response
	 * @param int $code
	 *  The request status code
	 */
	protected function writeCache($header, $response, $code)
	{
		if (in_array($code, array(200, 201, 202))) {
			$key = $this->createCacheKey($header);
			file_put_contents(Net::TMPDir() . "/$key.cache", $response);
			$this->cachekey = $key;
		}
	}

	/**
	 * Look up the cache file and if it exists and hasn't expired return the
	 * path to the cache file.
	 *
	 * @param string $header
	 *  The raw request header
	 * @return string|bool
	 */
	protected function getCache($header)
	{
		$key = $this->createCacheKey($header);
		$file = Net::TMPDir() . "/$key.cache";

		if (file_exists($file)) {
			$mtime = filemtime($file);
			if (time() < ($mtime + $this->cache)) {
				$this->cache = 0;
				return $file;
			}
			else
				unlink($file);
		}

		return false;
	}
}

/**
 * Class that parses a raw HTTP response.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.1
 * @since 0.3
 * @package Protocols
 * @subpackage HTTP
 */
class HTTPResponse
{
	/**
	 * The raw response, unparsed
	 * @var string
	 */
	protected $rawData = null;
	/**
	 * The raw header part of the raw response
	 * @var string
	 */
	protected $rawHeader = null;
	/**
	 * The parsed headers
	 * @var array
	 */
	protected $headers = array();
	/**
	 * The parsed data part of the response
	 * @var string
	 */
	protected $data = null;
	/**
	 * The HTTP status code of the response
	 * @var int
	 */
	protected $status = 0;
	/**
	 * The HTTP version used for the reponse
	 * @var string
	 */
	protected $version = null;

	/**
	 * Creates a new HTTPResponse object
	 *
	 * @param string $data
	 *  A raw HTTP response.
	 */
	public function __construct($data)
	{
		$this->rawData = $data;
		$this->parse($data);
	}

	/**
	 * Returns the HTTP headers
	 *
	 * @return array
	 */
	public function Headers()
	{
		return $this->headers;
	}

	/**
	 * Returns the content of the response.
	 *
	 * @return string
	 */
	public function Data()
	{
		return $this->data;
	}

	/**
	 * Returns the status code of the response
	 *
	 * @return int
	 */
	public function Status()
	{
		return $this->status;
	}

	/**
	 * Returns the HTTP version used for the response
	 *
	 * @return string
	 */
	public function Version()
	{
		return $this->version;
	}

	/**
	 * Returns the raw, unparsed, response
	 *
	 * @return string
	 */
	public function RawData()
	{
		return $this->rawData;
	}

	/**
	 * Returns the raw, unparsed, HTTP header of the response
	 *
	 * @return string
	 */
	public function RawHeader()
	{
		return $this->rawHeader;
	}

	/**
	 * Returns a specific HTTP header.
	 *
	 * @param string $which
	 *  The header to fetch.
	 * @return string|int|bool
	 *  Returns false if the wanted header isn't set.
	 */
	public function GetHeader($which)
	{
		$which = strtolower($which);
		if (isset($this->headers[$which]))
			return $this->headers[$which];

		return false;
	}

	/**
	 * Cast the object to a string, returns the data part of the resopnse.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->data;
	}

	/**
	 * Parses the raw response
	 *
	 * @throws HTTPResponseException
	 * @param string $data
	 */
	protected function parse($data)
	{
		$pos = strpos($data, "\r\n\r\n");

		if (!$pos)
			throw new HTTPResponseException("Couldn't parse response header!");

		$header = substr($data, 0, $pos);
		$body   = substr($data, $pos+4);

		$this->parseHeader($header);

		$enc = $this->GetHeader('content-encoding');

		if ($this->GetHeader('transfer-encoding') == 'chunked') {
			$rd = new StringReader($body);
			$body = '';
			$bytes = 0;
			do {
        // It's an assignment
        if (($bytes = hexdec($rd->ReadLine())) == 0)
					continue;

        $body .= $this->decode($rd->Read((int)$bytes), $enc);
			} while (!$rd->End());

			$rd->Dispose();
		}

		$this->data = $body;
		unset($body, $header);
	}

	/**
	 * Parse the response header
	 *
	 * @param string $header
	 */
	protected function parseHeader($header)
	{
		$this->rawHeader = $header;
		$lines = explode("\r\n", $header);
		//! Get the first line with status and such...
		if (preg_match('#HTTP/(\d\.\d) ([0-9]+)#', array_shift($lines), $m)) {
			$this->version = $m[1];
			$this->status  = (int)$m[2];
		}

		foreach ($lines as $line) {
			preg_match('/^(.*?): *(.*?)$/', $line, $m);
			$this->headers[strtolower($m[1])] = $m[2];
		}
	}

	/**
	 * Decode a body part
	 *
	 * @param string $data
	 * @param string $how
	 */
	protected function decode($data, $how)
	{
		switch ($how) {
			case 'gzip': $data = gzuncompress($data); break;
		}
		return $data;
	}
}

/**
 * Class fo handling cookies in HTTP requests.
 *
 * @usedby HTTPRequest::__construct()
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Protocols
 * @subpackage HTTP
 */
class HTTPCookie
{
	/**
	 * The name of the cookie file
	 * @var string
	 */
	private $name;
	/**
	 * The path where to store the cookie files
	 * @var string
	 */
	private $path = PLIB_TMP_DIR;
	/**
	 * The full path to the cookie file
	 * @var string
	 */
	private $cookiejar = null;

	/**
	 * Container for found cookies
	 * @var array
	 */
	private $cookies = array();

	/**
	 * Constructor
	 *
	 * @throws Exception
	 *
	 * @param string $name
	 *  The name of the cookie file to use
	 * @param string $path
	 *  The path where to save the cookie file
	 */
	public function __construct($name, $path=null)
	{
		$this->name = $name;
		if ($path)
			$this->path = $path;

		if (!is_writable($this->path))
			throw new Exception("The path \"$this->path\" is not writable so " .
			                    "cookies can not be saved");

		$this->cookiejar = $this->path . DIRECTORY_SEPARATOR . $this->name;
	}

	/**
	 * Saves a cookie to the cookie file.
	 *
	 * @param string $domain
	 * @param string $cookie
	 *  The raw unparsed cookie
	 * @return bool
	 */
	public function Set($domain, $cookie)
	{
		$cparts = array_map("trim", explode(";", $cookie));

		$c = array(
		  // Note! If the domain is set in the raw cookie that will overwrite
		  // this value.
			'domain'  => $domain,
			'path'    => '/',
			'secure'  => 'FALSE',
			'expires' => null
		);

		$v = array();
		foreach ($cparts as $cpart) {
			if (!preg_match('/(.*?)=(.*)/', $cpart, $m))
				continue;

			list(, $name, $val) = $m;

			if (array_key_exists($name, $c))
				$c[$name] = $val;
			else {
				$v['name'] = $name;
				$v['value'] = $val;
			}
		}

		$cf = $this->cookiejar;

		if ($c['expires'])
			$c['expires'] = strtotime($c['expires']);

		if (!file_exists($cf))
			file_put_contents($cf, "");

		$lines = file($cf);

		$fh = fopen($cf.".t", 'w');
		if (!is_resource($fh))
			return false;

		$value = sprintf(
			"%s\t%s\t%s\t%s\t%s\t%s\n",
		  $c['domain'], $c['path'], $c['expires'], $c['secure'],
		  $v['name'], $v['value']
		);

		$wrote = false;

		foreach ($lines as $line) {
			list($d, $p,,,$n,) = sscanf($line, "%s\t%s\t%s\t%s\t%s\t%s");
			if ($d == $c['domain'] && $p == $c['path'] && $n == $v['name']) {
				fwrite($fh, $value);
				$wrote = true;
			}
			else
				fwrite($fh, $line);
		}

		if (!$wrote)
			fwrite($fh, $value);

		fclose($fh);
		rename($cf.".t", $cf);
		return true;
	}

	/**
	 * Lookup cookies in the cookie file
	 *
	 * @param string $_domain
	 *  The domain the cookie applies to
	 * @param string $_path
	 *  The path the cookie applies to
	 * @param string|void $_name
	 *  A name of a value key
	 * @return array|bool
	 */
	public function Get($_domain, $_path, $_name='')
	{
		$file = $this->cookiejar;

		if (!file_exists($file))
			return false;

		if ($_path[strlen($_path)-1] != '/')
			$_path = dirname($_path);

		$fh = @fopen($file, 'r');
		if (!is_resource($fh))
			return false;

		$now = time();
		$ret = array();

		while ($ci = fscanf($fh, "%s\t%s\t%s\t%s\t%s\t%s\n")) {
			list($domain, $path, $expires, $secure, $name, $value) = $ci;

			if ($domain[0] == ".")
				$_domain = substr($_domain, strpos($_domain, '.'));

			if ($domain == $_domain && $this->pathInPath($path, $_path)) {
				$strlen   = strlen(join("\t", $ci));
				$position = ftell($fh) - $strlen - 1;
				if ($expires > $now) {
					array_push($ret, array(
						'domain'   => $domain,
						'path'     => $path,
						'expires'  => $expires,
						'secure'   => $secure,
						'name'     => $name,
						'value'    => $value,
						'position' => $position,
						'length'   => $strlen
					));
				}
				break;
			}
		}

		@fclose($fh);
		$this->cookies = $ret;

		return $ret;
	}

	/**
	 * Creates a cookie request header.
	 *
	 * @see HTTPCookie::Get()
	 * @param bool $secure
	 *  If the cookie is marked as secure it should only be passed on if the
	 *  connection is secure. So if a secure connection is used for the request
	 *  set this to true
	 * @param string $domain
	 * @param string $path
	 * @param string $name
	 * @return string|bool
	 */
	public function CreateHeader($secure=false, $domain=null, $path=null,
	                             $name=null)
	{
		if (empty($this->cookies))
			$this->Get($domain, $path, $name);

		if (empty($this->cookies))
			return false;

		$cvar = "";
		$c = $this->cookies;

		foreach ($c as $cc)
			if ($cc['secure'] == 'FALSE' || ($cc['secure'] == 'TRUE' && $secure))
				$cvar .= $cc['name'] . '=' . $cc['value'] . '; ';

		$cvar = rtrim($cvar, '; ');
		if (strlen($cvar))
			return "Cookie: $cvar\r\n";

		return false;
	}

	/**
	 * See if the cookie path is valid for the request path
	 *
	 * @param string $cookie
	 *  The path in the cookie
	 * @param string $request
	 *  The request path
	 * @return bool
	 */
	private function pathInPath($cookie, $request)
	{
		if ($cookie == '/')
			return true;

		$cookie = trim($cookie, '/');
		$request = trim($request, '/');

		if (strlen($request) < strlen($cookie))
			return false;

		if (substr($request, 0, strlen($cookie)) == $cookie)
			return true;

		return false;
	}

	/**
	 * Converts the object to a string
	 *
	 * @see PLib::__toString()
	 * @return string
	 */
	public function __toString()
	{
		if (class_exists('PLib'))
			return PLib::__toString($this);

		return "HTTPCookie($this->cookiejar)";
	}
}

/**
 * Helper class for network stuff
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.2.1
 * @package Protocols
 */
class Net
{
	/**
	 * HTTP status code descriptions
	 * From {@link http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
	 * Wikipedia}
	 * @var array
	 */
	static $HTTP_STATUS_CODES = array(
	//! ==========================================================================
	//! 1xx INFORMATIONAL
	//! Request received, continuing process.
	//! ==========================================================================
	100 => 'Continue',
	101 => 'Switching Protocols',
	102 => 'Processing', // (WebDav)

	//! ==========================================================================
	//! SUCCESS
	//! The action was successfully received, understood, and accepted.
	//! ==========================================================================
	200 => 'OK',
	201 => 'Created',
	202 => 'Accepted',
	203 => 'Non-Authoritative Information',
	204 => 'No content',
	205 => 'Reset Content',
	206 => 'Partial Content',
	//! The message body that follows is an XML message and can contain a number
	//! of separate response codes, depending on how many sub-requests were made.
	207 => 'Multistatus', // (WebDav)

	//! ==========================================================================
	//! 3xx REDIRECTION
	//! The client must take additional action to complete the request.
	//! ==========================================================================
	300 => 'Multiple Choise',
	301 => 'Moved Permanently',
	//! This is the most popular redirect code, but also an example of industrial
	//! practice contradicting the standard. HTTP/1.0 specification (RFC 1945)
	//! required the client to perform a temporary redirect (the original
	//! describing phrase was "Moved Temporarily"), but popular browsers
	//! implemented it as a 303 See Other. Therefore, HTTP/1.1 added status codes
	//! 303 and 307 to disambiguate between the two behaviors. However, majority
	//! of Web applications and frameworks still use the 302 status code as if it
	//! were the 303.
	302 => 'Found',
	//! The response to the request can be found under another URI using a GET
	//! method.
	303 => 'See Other',
	//! Indicates the request URL has not been modified since last requested.
	//! Typically, the HTTP client provides a header like the If-Modified-Since
	//! header to provide a time with which to compare against. Utilizing this
	//! saves bandwidth and reprocessing on both the server and client.
	304 => 'Not Modified',
	305 => 'Use Proxy',
	//! No longer used.
	306 => 'Switch Proxy',
	//! In this occasion, the request should be repeated with another URI, but
	//! future requests can still be directed to the original URI. In contrast to
	//! 303, the original POST request must be repeated with another POST request.
	307 => 'Temporary Redirect',

	//! ==========================================================================
	//! 4xx CLIENT ERROR
	//! ==========================================================================
	//! The request contains bad syntax or cannot be fulfilled.
	400 => 'Bad Request',
	//! Similar to 403 Forbidden, but specifically for use when authentication is
	//! possible but has failed or not yet been provided. See basic authentication
	//! scheme and digest access authentication.
	//! http://en.wikipedia.org/wiki/Basic_authentication_scheme
	//! http://en.wikipedia.org/wiki/Digest_access_authentication
	401 => 'Unauthorized',
	//! The original intention was that this code might be used as part of some
	//! form of digital cash or micropayment scheme, but that has not happened,
	//! and this code has never been used.
	402 => 'Payment Required',
	//! The request was a legal request, but the server is refusing to respond to
	//! it. Unlike a 401 Unauthorised response, authenticating will make no
	//! difference.
	403 => 'Forbidden',
	//! See http://en.wikipedia.org/wiki/HTTP_404
	404 => 'Not Found',
	405 => 'Method Not Allowed',
	406 => 'Not Acceptable',
	407 => 'Proxy Authentication Required',
	408 => 'Request Timeout',
	409 => 'Conflict',
	//! Indicates that the resource requested is no longer available and will not
	//! be available again. This should be used when a resource has been
	//! intentionally removed; however, in practice, a 404 Not Found is often
	//! issued instead.
	410 => 'Gone',
	411 => 'Length Required',
	412 => 'Precondition Failed',
	413 => 'Request Entity Too Large',
	414 => 'Request-URI Too Long',
	415 => 'Unsupported Media Type',
	416 => 'Requested Range Not Satisfiable',
	417 => 'Expectation Failed',
	//! The request was well-formed but was unable to be followed due to semantic
	//! errors.
	422 => 'Unprocessable Entity', // (WebDAV)
	//! The resource that is being accessed is locked
	423 => 'Locked', // (WebDAV)(RFC 2518)
	//! The request failed due to failure of a previous request (e.g. a PROPPATCH)
	424 => 'Failed Dependency', // (WebDAV) (RFC 2518)
	//! Defined in drafts of WebDav Advanced Collections but not yet implemented.
	//! http://tools.ietf.org/html/draft-ietf-webdav-collection-protocol-04
	//! #section-5.3.2
	425 => 'Unordered Collection',
	//! The client should switch to TLS/1.0.
	//! http://en.wikipedia.org/wiki/Transport_Layer_Security
	426 => 'Upgrade Required',
	//! A Microsoft extension: The request should be retried after doing the
	//! appropriate action.
	449 => 'Retry With',

	//! ==========================================================================
	//! 5xx SERVER ERROR
	//! The server failed to fulfill an apparently valid request.
	//! ==========================================================================
	500 => 'Internal Server Error',
	501 => 'Not Implemented',
	502 => 'Bad Gateway',
	503 => 'Service Unavailable',
	504 => 'Gateway Timeout',
	505 => 'HTTP Version Not Supported',
	506 => 'Variant Also Negotiates', // (RFC 2295)
	507 => 'Insufficient Storage', // (WebDAV)
	//! This status code, while used by many servers, is not an official HTTP
	//! status code.
	509 => 'Bandwidth Limit Exceeded',
	510 => 'Not Extended' // (RFC 2774)
	);

	/**
	 * Where to put temporary stuff related to net operations.
	 * @var string
	 */
	private static $TMP_DIR = PLIB_TMP_DIR;

	/**
	 * Hidden constructor. This class can not be instantiated
	 */
	protected function __construct() {}

	/**
	 * Getter/setter for the tmp dir
	 *
	 * @param string dir
	 * @return string
	 */
	public static function TMPDir($dir=null)
	{
		if ($dir)
			self::$TMP_DIR = rtrim($dir, DIRECTORY_SEPARATOR);

		return self::$TMP_DIR;
	}

	/**
	 * Exit with status code $code
	 *
	 * @throws Exception
	 *  If headers are already sent
	 * @since 0.2.2
	 * @param int $code
	 */
	public static function ServerExit($code)
	{
		$file = null;
		$line = null;
		if (!headers_sent($file, $line)) {
			$desc = Net::StatusDescription($code);
			header("HTTP/1.1 $code $desc");
			echo "<h1>$code <small>$desc</small></h1>";
			die;
		}
		else
			throw new Exception("Headers already sent in $file on line $line!");
	}

	/**
	 * Returns the description of a HTTP status code
	 *
	 * @param int $code
	 * @return string|bool
	 */
	public static function StatusDescription($code)
	{
		return isset(self::$HTTP_STATUS_CODES[$code]) ?
		       self::$HTTP_STATUS_CODES[$code] : false;
	}

	/**
	 * Converts a query string into an associativ array
	 *
	 * @since 0.2.1
	 * @param string $querystring
	 * @return array
	 */
	public static function QueryToArray($querystring)
	{
		$out = array();
		$parts = explode('&', $querystring);
		foreach ($parts as $part) {
			list($k, $v) = explode('=', $part);
			$out[$k] = $v;
		}
		return $out;
	}
}

/**
 * Exception class for the HTTPClient class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Protocols
 * @subpackage Exception
 */
class HTTPMaxRedirectException extends Exception
{
	public $message;
}

/**
 * Exception class for the HTTPClient class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Protocols
 * @subpackage Exception
 */
class HTTPRequestException extends Exception
{
	public $message;
}

/**
 * Exception class for the HTTPClient class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Protocols
 * @subpackage Exception
 */
class HTTPResponseException extends Exception
{
	public $message;
}
?>