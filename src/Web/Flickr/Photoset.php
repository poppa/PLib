<?php
/**
 * Flickr Photoset class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.2
 * @package Web
 * @subpackage Flickr
*/

/**
 * Load Flickr
 */
require_once 'Flickr.php';

/**
 * Flickr Photoset class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Flickr
*/
class FlickrPhotoset extends FlickrMethod
{
	/**
	 * The Photoset ID
	 * @var string
	*/
	private $id = null;

	/**
	 * Constructor
	 *
	 * @param Flickr $api
	 * @param string $photosetID
	*/
	public function __construct(Flickr $api, $photosetID=null)
	{
		parent::__construct($api);
		$this->id = $photosetID;
	}

	/**
	 * Returns a list of all available photosets
	 *
	 * @param bool $useCache
	 *   Override the default set in @see{Flickr}.
	 * @return FlickrResponse
	*/
	public function GetList($useCache=null)
	{
		$res = $this->execute('flickr.photosets.getList', array(), $useCache);
		return $res;
	}

	/**
	 * Returns a list of the photos
	 *
	 * @link http://www.flickr.com/services/api/flickr.photosets.getPhotos.html
	 * @throws FlickrException
	 * @param string $extras
	 * @param int $privacyFilter
	 * @param int $perPage
	 * @param int $page
	 * @param bool $cache
	 *   Overrides the default cacheability
	 * @return FlickrResponse
	*/
	public function GetPhotos($extras=null, $privacyFilter=null, $perPage=null,
	                          $page=null, $cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("Missing Photoset ID!");

		$params = array('photoset_id' => $this->id);

		if ($extras)
			$params['extras'] = $extras;

		if ($privacyFilter)
			$params['privacy_filter'] = $privacyFilter;

		if ($perPage)
			$params['per_page'] = $perPage;

		if ($page)
			$params['page'] = $page;

		$res = $this->execute('flickr.photosets.getPhotos', $params, $cache,
		                      $this->id);
		return $res;
	}

	/**
	 * Returns the info about the current photoset
	 *
	 * @link http://www.flickr.com/services/api/flickr.photosets.getInfo.html
	 * @throws FlickrException
	 * @param bool $cache
	 *   Overrides the default cacheability
	 * @return FlickrResponse
	*/
	public function GetInfo($cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("Missing Photoset ID!");

		$params = array('photoset_id' => $this->id);
		$res = $this->execute('flickr.photosets.getInfo', $params, $cache,
		                      $this->id);
		return $res;
	}

	/**
	 * Returns next and previous photos for a photo in a set.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photosets.getContext.html
	 * @param string $photoID
	 * @param bool $cache
	 *   Override default cacheability
	 * @return FlickrResponse
	*/
	public function GetContext($photoID, $cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("Missing Photoset ID!");

		$params = array('photoset_id' => $this->id, 'photo_id' => $photoID);
		$res = $this->execute('flickr.photosets.getContext', $params, $cache,
		                      $this->id);
		return $res;
	}

	/**
	 * Returns the full path to the plain photo
	 *
	 * @param array|object $photo
	 * @return string
	*/
	public function GetSrc($photo)
	{
		return $this->getPhotoSrc($photo);
	}

	/**
	 * Download the given photo
	 *
	 * @param string $src
	 * @param string $saveTo
	 * @return string
	*/
	public function Download($src, $saveTo)
	{
		return parent::download($src, $saveTo);
	}

	/**
	 * Returns the URL to the photoset on Flickr.com
	 *
	 * @since 0.2
	 * @param stdClass $obj
	 *   This should be an photoset object of a result set from a call to
	 *   @see{FlickrPhotoset::GetList()} for instance.
	 * @return string
	*/
	public function GetURL(stdClass $photoset)
	{
		$url = Flickr::FLICKR_ENPOINT.'/photos/'.$this->api->UserID().'/sets/';
		return $url . $photoset->id . '/';
	}
}
?>