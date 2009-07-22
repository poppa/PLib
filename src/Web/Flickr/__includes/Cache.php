<?php
/**
 * Flickr cache class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
*/

/**
 * The FlickrCache writes responses from Flickr web services to disk. The cache
 * files is pure PHP files with an array where each web service response
 * has it's own key.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
*/
class FlickrCache
{
	/**
	 * The cache directory
	 * @var string
	*/
	private $dir;
	/**
	 * The cache file name
	 * @var string
	*/
	private $file;
	/**
	 * The full path to the cache file
	 * @var string
	*/
	private $path;
	/**
	 * The cache array in the cache file
	 * @var array
	*/
	private $__cache = array();
	/**
	 * The cache file header
	 * @var string
	*/
	private $HEADER = null;

	/**
	 * Constructor
	 *
	 * @param Flickr $api
	*/
	public function __construct(Flickr $api)
	{
		$this->HEADER = "<?php\n" .
		"//! This file is auto generated.\n" .
		"//! Do not edit it directly!\n";

		$this->dir = $api->CacheDir();

		if (!is_dir($this->dir)) {
			throw new FlickrException(
				"The Flickr cache directory \"{$this->dir}\" is not a directory!"
			);
		}

		$this->file = $api->Key() . '.flickr-cache.php';

		if ($api->UseCache() && !is_null($this->dir)) {
			if (!is_writable($this->dir)) {
				throw new FlickrException(
					"The Flickr cache directory \"{$this->dir}\" is not writable!"
				);
			}

			$this->path = $this->dir . DIRECTORY_SEPARATOR . $this->file;

			if (!file_exists($this->path)) {
				$fc = $this->HEADER . "\$this->__cache = array();\n?>";
				file_put_contents($this->path, $fc);
			}
			else
				require_once $this->path;
		}
	}

	/**
	 * Write the data to the cache file
	 *
	 * @param string $method
	 * @param string $data
	 * @return bool
	*/
	public function Write($method, $data)
	{
		if (!$this->path)
			return false;

		$this->__cache[$method] = $data;
		$d = var_export($this->__cache, true);
		$d = $this->HEADER . "\$this->__cache = {$d};\n?>";
		file_put_contents($this->path, $d);

		return true;
	}

	/**
	 * Return the cache array or part of it
	 *
	 * @param string $key
	 * @return array
	*/
	public function Get($key=null)
	{
		if (!$key)
			return $this->__cache;

		if (array_key_exists($key, $this->__cache))
			return $this->__cache[$key];

		return array();
	}
}
?>