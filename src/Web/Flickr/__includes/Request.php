<?php
/**
 * FlickrRequest
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
 */

if (!function_exists('mb_unserialize')) {
	/**
	 * Unserializes a multibyte string.
	 * Taken from user comments at @link{http://php.net/unserialize PHP.NET}
	 *
	 * @param string $serial_str
	 * @return mixed
	*/
	function mb_unserialize($serial_str)
	{
		$out = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'",
		                    $serial_str );
	  return unserialize($out);
	}
}

/**
 * A FlickrRequest class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
 */
class FlickrRequest
{
	/**
	 * The API object
	 * @var Flickr
	*/
	private $api;
	/**
	 * The Flickr method to call
	 * @var string
	*/
	private $method = null;
	/**
	 * Method parameters
	 * @var array
	*/
	private $params = array();
	/**
	 * The cache key
	 * @var string
	*/
	private $cacheKey = null;

	/**
	 * Constructor
	 *
	 * @param Flickr $api
	*/
	public function __construct(Flickr $api, $method, array $params,
	                            $cachePrefix=null)
	{
		$this->api      = $api;
		$this->method   = $method;
		$this->params   = $params;
		$this->cacheKey = $this->method;

		if ($cachePrefix)
			$this->cacheKey .= '_' . $cachePrefix;
	}

	/**
	 * Do the actual HTTP call to a flickr method
	 *
	 * @throws FlickrException
	 * @param bool $useCache
	 * @return FlickrResponse
	 *   or false if fail
	*/
	public function Query($cachePrefix=null, $useCache=null)
	{
		$cacheKey = null;

		if ($useCache === true) {
			$c = $this->api->Cache();
			if ($c) {
				$m = $c->Get($this->cacheKey);
				if (sizeof($m))
					return new FlickrResponse($m);
			}
		}

		$params = http_build_query($this->params);

    $cookie = new HTTPCookie('flickr', Net::TMPDir());
		$client = new HTTPRequest($cookie);
    $client->MaxRedirects(5);

		$url = $this->api->Endpoint() . '?' . $params;

		$extraHeaders = array(
			'User-Agent'      => $_SERVER['HTTP_USER_AGENT'],
			'Accept'          => $_SERVER['HTTP_ACCEPT'],
			'Accept-Language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
			'Accept-Charset'  => 'ISO-8859-1,UTF-8'
		);

		$response = null;

		try { $response = $client->Get($url, null, $extraHeaders); }
		catch (Exception $e) {
			throw new FlickrException($e->getMessage());
		}

		$respres = null;

		if ($this->api->ResponseFormat() == 'php_serial')
			$respres = @unserialize(utf8_encode((string)$response));

		//! This probably means we got an offset error above.
		if (!is_array($respres)) {
			$respres = mb_unserialize($response);

			if (!is_array($respres)) {
				$this->api->SetError(-1, "Couldn't unserialize response data!");
				return false;
			}
		}

		if ($respres['stat'] == 'fail') {
			$this->api->SetError($respres['code'], $respres['message']);
			return false;
		}

		if ($this->api->UseCache() && $useCache === true)
			$this->api->Cache()->Write($this->cacheKey, $respres);

		return new FlickrResponse($respres);
	}

	/**
	 * Sign the parameters
	 *
	 * @param string $secret
	 * @param array $params
	 * @return array
	*/
	public static function SignParams($secret, array $params)
	{
		$sig = '';
		$values = array();
		ksort($params);

		foreach ($params as $key => $value) {
			$sig .= $key . $value;
			$values[$key] = urlencode($value);
		}

		$values['api_sig'] = md5($secret . $sig);

		return $values;
	}
}
?>