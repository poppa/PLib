<?php
/**
 * A Bitly class
 *
 * This class communicates with {@link http://bit.ly} which is a service to
 * shorten, track and share links.
 *
 * Copyright © 2009, Pontus Östlund - {@link www.poppa.se}
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.1
 * @package Web
 * @uses HTTPRequest
 * @uses HTTPResponse
 * @uses Cache
 */

/**
 * Include the HTTP classes
 */
require_once PLIB_INSTALL_DIR . '/Protocols/Net.php';
/**
 * Include the Cache class
 */
require_once PLIB_INSTALL_DIR . '/Cache/Cache.php';

/**
 * A Bitly class
 *
 * This class communicates with {@link http://bit.ly} which is a service to
 * shorten, track and share links.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 */
class Bitly
{
  /**
   * States that the argument given is a `shortUrl`.
   * @var int
   */
  const ARG_URL = 1;
  /**
   * States that the argument given is a `hash`.
   * @var int
   */
  const ARG_HASH = 2;
  /**
   * Bitly version
   * @var string
   */
  const VERSION = '2.0.1';
  /**
   * Response format in XML
   * @var string
   */
  const FORMAT_XML = 'xml';
  /**
   * Response format in JSON
   * @var string
   */
  const FORMAT_JSON = 'json';
  /**
   * Base URL to the Bitly service
   * @var string
   */
  const BASE_URL = 'http://api.bit.ly';
  /**
   * Argument type to query variable name mapping
   * @var array
   */
  protected static $PARAM_KEY = array(self::ARG_URL => 'shortUrl',
                                      self::ARG_HASH => 'hash');
  /**
   * Login name
   * @var string
   */
  protected $handle = null;
  /**
   * Bitly API key
   * @var string
   */
  protected $apikey = null;
  /**
   * Bitly version to use
   * @var string
   */
  protected $version = self::VERSION;
  /**
   * Response format
   * @var string
   */
  protected $format = self::FORMAT_JSON;
  /**
   * Callback function. Only useful if {@see Bitly::$format} is `JSON`.
   * @var string
   */
  protected $callback = null;
  /**
   * How long should the request be cached?
   * @var int
   */
  protected $cache = Cache::PERSISTENT;

  /**
   * Creates a new Bitly object
   *
   * @param string $username
   * @param string $apikey
   * @param string $cache
   */
  public function __construct($username, $apikey, $cache=null)
  {
    $this->handle = $username;
    $this->apikey = $apikey;
    if ($cache != null)
      $this->cache = $cache;
  }

  /**
   * Getter/setter for how long to cache the requests
   *
   * @throws Exception
   *  If cache is set and less than 0
   * @param $cache
   * @return string
   */
  public function Cache($cache=null)
  {
    if ($cache != null) {
      if ($cache < 0)
				throw new Exception("Cache time must be greater than `0'!");

      $this->cache = $cache;
    }

    return $this->cache;
  }
  
  /**
   * Getter/setter for the Bitly version
   *
   * @param string $version
   * @return string
   */
  public function Version($version=null)
  {
    if ($version)
      $this->version = $version;
      
    return $version;
  }

  /**
   * Getter/setter for the Bitly response format.
   *
   * @throws Exception
   *  If format given isn't supported
   * @param string $format
   *  Should be `Bitly::FORMAT_JSON` or `Bitly::FORMAT_XML`.
   * @return string
   */
  public function Format($format=null)
  {
    if ($format) {
      if (!in_array($format, array(self::FORMAT_JSON, self::FORMAT_XML))) {
				throw new Exception("Unknown format \"$format\". Must be `Bitly::" .
			                      "FORMAT_JSON' or `Bitly::FORMAT_XML'!\n");
      }
      $this->format = $format;
    }
    
    return $this->format;
  }
  
  /**
   * Getter/setter for the callback function for JSON responses
   *
   * @param string $callback
   * @return string
   */
  public function Callback($callback=null)
  {
    if ($callback)
      $this->callback = $callback;

    return $this->callback;
  }

  /**
   * Shortens a URL
   *
   * @throws Exception
   *  If the returned HTTP status isn't `200`
   * @param string $url
   * @return string
   *  Returns the raw response as a string
   */
  public function Shorten($url)
  {
    return $this->call("shorten", array("longUrl" => $url));
  }
  
  /**
   * Same as {@see Bitly::Shorten()}, except this method returns the shorter
   * URL directly instead of the entire string response
   *
   * @throws Exception
   *  If the returned HTTP status isn't `200` or if the status code from Bitly
   *  isn't successful
   * @param string $url
   * @return string
   */
  public function GetShortUrl($url)
  {
    $r = $this->Shorten($url);
    if ($this->format == self::FORMAT_JSON) {
      $r = json_decode($r);
      if ($r->errorCode != 0)
				throw new Exception("Bitly error ($r->errorCode): $r->errorMessage!");

      return $r->results->{$url}->shortUrl;
    }

    throw new Exception("Bitly::GetShortUrl() isn't implemented for XML ".
                        "response");
  }

  /**
   * Expands a shortened URL to it's original value
   *
   * @throws Exception
   *  If the returned HTTP status isn't `200`
   * @param string $urlOrHash
   *  Either the shortened URL or its hash.
   * @return string
   */
  public function Expand($urlOrHash)
  {
    $type = self::ARG_HASH;
    if (strpos($urlOrHash, "://") > 3)
      $type = self::ARG_URL;

    return $this->call("expand", array(self::$PARAM_KEY[$type] => $urlOrHash));
  }

  /**
   * Returns info about the page of the shortened URL, page title etc...
   *
   * @throws Exception
   *  If the returned HTTP status isn't `200`
   * @param string $urlOrHash
   *  Either the shortened URL or its hash.
   * @param string|array $keys
   *  One or more keys to limit the attributes returned about each bitly 
   *  document, eg: htmlTitle,thumbnail
   */
  public function Info($urlOrHash, $keys=null)
  {
    $type = self::ARG_HASH;
    if (strpos($urlOrHash, "://") > 3)
      $type = self::ARG_URL;

    $p = array(self::$PARAM_KEY[$type] => $urlOrHash);
    if ($keys)
      $p += array("keys" => (is_array($keys) ? join(',', $keys) : $keys));

    return $this->call("info", $p);
  }

  /**
   * Returns traffic and referrer data of the shortened URL
   *
   * @throws Exception
   *  If the returned HTTP status isn't `200`
   * @param string $urlOrHash
   *  Either the shortened URL or its hash.
   * @return string
   */
  public function Stats($urlOrHash)
  {
    $type = self::ARG_HASH;
    if (strpos($urlOrHash, "://") > 3)
      $type = self::ARG_URL;

    return $this->call("stats", array(self::$PARAM_KEY[$type] => $urlOrHash),
                       'GET', false);
  }

  /**
   * Does the actual HTTP call to Bitly
   *
   * @throws Exception
   *  If the returned HTTP status isn't `200`
   * @param string $service
   *  The Bitly API service to call
   * @param array $params
   *  Associative array of parameters to the Bitly service
   * @param string $method
   *  The HTTP method to use
   * @param bool $cache
   *  If false the request will not be cached
   * @return string;
   */
  protected function call($service, array $params, $method='GET', $cache=true)
  {
    $response = null;
    $p = $this->getDefaultParams();

    if ($params) $p += $params;

    $method   = $this->normalizeHttpMethod($method);
    $service  = $this->getNormalizedUrl($service);
    $authz    = $this->getAuthzHeader();
    $res      = null;
    $cachekey = null;
    $docache  = false;

    if ($cache && $this->cache && PLIB_HAS_SQLITE) {
      $cachekey = md5($method.$service.$authz['Authorization'].
		                  $this->paramsToString($p));
      if ($res = Cache::Get($cachekey))
				return $res;

      $docache = true;
    }

    $q = new HTTPRequest();
    // Cache the request for 30 sec even if we don't cache the result
    $q->Cache(30);

    /* HTTPResponse */ 
    $response = $q->DoMethod($method, $service, $p, $authz);

    if (($st = $response->Status()) != 200)
      throw new Exception("Bad status ($st) in HTTP response!\n");

    $res = $response->Data();

    if ($docache)
      Cache::Add($cachekey, $res, time()+$this->cache);

    return $res;
  }
  
  /**
   * Turns the params array into a string representation for usage 
   * in the cache key
   *
   * @param array $params
   * @return string
   */
  protected function paramsToString(array $params)
  {
    $s = '';
    foreach ($params as $k => $v)
      $s .= "$k$v";
    return $s;
  }

  /**
   * Normalizes the HTTP method to use for the service call and verifies it's
   * a valid method
   *
   * @throws Exception
   *  If a bad method is given
   * @param string $method
   * @return string
   */
  protected function normalizeHttpMethod($method)
  {
    $method = strtoupper($method);
    if (!in_array($method, array('POST', 'GET')))
      throw new Exception("Bad HTTP method. Must be `GET' or `POST'\n");

    return $method;
  }

  /**
   * Normalizes the Bilty service to call. Makes sure there's a heading slash
   * and that there's no trailing slash
   *
   * @return string
   */
  protected function getNormalizedUrl($service)
  {
    if ($service[0] != '/') $service = "/$service";
    return self::BASE_URL . rtrim($service, '/');
  }

  /**
   * Returns an authorization header
   *
   * @return array
   */
  protected function getAuthzHeader()
  {
    return array("Authorization" => 
                 "Basic ".base64_encode($this->handle . ":" . $this->apikey));
  }

  /**
   * Returns the default parameters needed for every call
   *
   * @return array
   */
  protected function getDefaultParams()
  {
    $a = array(
      'apiKey'  => $this->apikey,
      'format'  => $this->format,
      'version' => $this->version
    );
    
    if ($this->callback && $this->format == self::FORMAT_JSON)
      $a['callback'] = $this->callback;

    return $a;
  }
}
?>
