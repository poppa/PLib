<?php
/**
 * Response class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
*/

/**
 * A Flickr Response object
 * Converts a reponse array into a more accessible object
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
*/
class FlickrResponse
{
	/**
	 * The original response array
	 * @var array
	*/
	private $respone;

	/**
	 * Constructor
	 *
	 * @param array $respone
	*/
	public function __construct(array $respone)
	{
		$this->respone = $respone;
		$this->responseToObject($this, $respone);
	}

	/**
	 * Populates this object with members from the reponse array
	 *
	 * @param object $part
	 * @param array $array
	*/
	private function responseToObject(&$part, array $array)
	{
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$part->{$key} = new stdClass();
				$this->responseToObject($part->{$key}, $value);
			}
			else {
				if ($key == '_content')
					$part = $value;
				else {
					if (!is_object($part))
						$part = new stdClass();

					$part->{$key} = $value;
				}
			}
		}
	}

	/**
	 * Returns the original reponse array
	 *
	 * @return array
	*/
	public function Response()
	{
		return $this->respone;
	}

	/**
	 * Magic getter
	 *
	 * @param string $which
	 * @return string
	*/
	public function __get($which)
	{
		if (isset($this->{$which}))
			return $this->{$which};
	}
}
?>