<?php
/**
 * Flicker Photo class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
*/

/**
 * Include the main FlickrMethod class
*/
require_once 'Flickr.php';

/**
 * Flicker Photo class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @subpackage Flickr
*/
class FlickrPhoto extends FlickrMethod
{
	/**
	 * The photo id
	 * @var string
	*/
	private $id = null;
	/**
	 * The src of the photo on Flickr
	 * @var string
	*/
	private $src = null;
	/**
	 * Default parameters for every method call
	 * @var array
	*/
	private $defParams = array();

	/**
	 * Constructor
	 *
	 * @param Flickr $api
	 * @param string $photoID
	*/
	public function __construct(Flickr $api, $photoID=null)
	{
		parent::__construct($api);
		$this->id = $photoID;
		if (!is_null($this->id))
			$this->defParams['photo_id'] = $this->id;
	}

	/**
	 * Returns the photo info
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getInfo.html
	 * @param string $secret
	 *   If a correct secret is passed permission checking is skipped.
	 * @param bool $cache
	 *   Override the default cacheability
	 * @return stdClass|FlickrResponse
	*/
	public function GetInfo($secret=null, $cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("A photo ID is required!");

		$params = $this->defParams;

		if (!is_null($secret))
			$params['secret'] = $secret;

		$res = $this->execute('flickr.photos.getInfo', $params, $cache, $this->id);
		$this->src = $res->src = $this->getPhotoSrc($res->photo);

		return isset($res->photo) ? $res->photo : $res;
	}

	/**
	 * Returns the Exif info of the image if it has any
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getExif.html
	 * @since 0.2
	 * @param string $secret
	 *   If a correct secret is passed permission checking is skipped.
	 * @param bool $cache
	 *   Override the default cacheability
	 * @return stdClass|FlickrResponse
	*/
	public function GetExif($secret=null, $cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("A photo ID is required!");

		$params = $this->defParams;

		if (!is_null($secret))
			$params['secret'] = $secret;

		$res = $this->execute('flickr.photos.getExif', $params, $cache, $this->id);
		return isset($res->exif) ? $res->exif : $res;
	}

	/**
	 * Returns available sizes of the image
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getSizes.html
	 * @since 0.2
	 * @param bool $cache
	 * @return stdClass|FlickrResponse
	*/
	public function GetSizes($cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("A photo ID is required!");

		$params = $this->defParams;
		$res = $this->execute('flickr.photos.getSizes', $params, $cache, $this->id);

		return isset($res->sizes) ? $res->sizes : $res;
	}

	/**
	 * Returns a list of the latest public photos uploaded to flickr.
	 *
	 * **NOTE!** This means ALL public photos, not just yours!<br/>
	 * **NOTE!** The result of this method call won't be cached!
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getRecent.html
	 * @since 0.2
	 * @param int $perPage
	 * @param int $page
	 * @param string $extras
	 * @return stdClass|FlickrResponse
	*/
	public function GetRecent($perPage=null, $page=null, $extras=null)
	{
		$params = $this->defParams;

		if (!is_null($extras))
			$params['extras'] = $extras;

		if (!is_null($perPage))
			$params['per_page'] = $perPage;

		if (!is_null($page))
			$params['page'] = $page;

		$res = $this->execute('flickr.photos.getRecent', $params, false);

		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Return a list of your photos that have been recently created or which have
	 * been recently modified.
	 * Recently modified may mean that the photo's metadata (title, description,
	 * tags) may have been changed or a comment has been added (or just modified
	 * somehow).
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.recentlyUpdated.html
	 * @since 0.2
	 * @param int $minDate
	 *   A Unix timestamp indicating the date from which modifications should be
	 *   compared. Defauls to two weeks
	 * @param int $perPage
	 * @param int $page
	 * @param string $extras
	 * @return stdClass|FlickrResponse
	*/
	public function RecentlyUpdated($minDate=null, $perPage=null, $page=null,
	                                $extras=null)
	{
		if (!$minDate)
			$minDate = time() - (3600*24*14);

		$params = array('min_date' => $minDate);

		if (!is_null($perPage))
			$params['per_page'] = $perPage;

		if (!is_null($page))
			$params['page'] = $page;

		if (!is_null($extras))
			$params['extras'] = $extras;

		$res = $this->execute('flickr.photos.recentlyUpdated', $params, false);

		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Returns the permissions of the current photo.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getPerms.html
	 * @since 0.2
	 * @throws FlickrException
	 * @param bool $cache
	 *   Override default cacheability
	 * @return stdClass|FlickrResponse
	*/
	public function GetPerms($cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("A photo ID is required!");

		$res = $this->execute('flickr.photos.getPerms', $this->defParams, $cache,
		                      $this->id);

		return isset($res->perms) ? $res->perms : $res;
	}

	/**
	 * Returns next and previous photos for a photo in a photostream.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getContext.html
	 * @throws FlickrException
	 * @since 0.2
	 * @param bool $cache
	 *   Override default cacheability
	 * @return FlickrResponse
	*/
	public function GetContext($cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("A photo ID is required!");

		$res = $this->execute('flickr.photos.getContext', $this->defParams, $cache,
		                      $this->id);
		return $res;
	}

	/**
	 * Gets a list of photo counts for the given date ranges for the calling user.
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getCounts.html
	 * @since 0.2
	 * @param string $dates
	 * @param string $takenDates
	 * @return stdClass|FlickrResponse
	*/
	public function GetCounts($dates=null, $takenDates=null)
	{
		$params = array();

		if (!is_null($dates))
			$params['dates'] = $dates;

		if (!is_null($takenDates))
			$params['taken_dates'] = $takenDates;

		$res = $this->execute('flickr.photos.getCounts', $params, false);
		return isset($res->photocounts) ? $res->photocounts : $res;
	}

	/**
	 * Returns all visible sets and pools the photo belongs to.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getAllContexts.html
	 * @since 0.2
	 * @throws FlickrException
	 * @param bool $cache
	 *   Override the default cacheability
	 * @return FlickrResponse
	*/
	public function GetAllContexts($cache=null)
	{
		if (is_null($this->id))
			throw new FlickrException("A photo ID is required!");

		$res = $this->execute('flickr.photos.getAllContexts', $this->defParams,
		                      $cache, $this->id);
		return $res;
	}

	/**
	 * Fetch a list of recent photos from the calling users' contacts.
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link http://flickr.com/services/api/flickr.photos.getContactsPhotos.html
	 * @since 0.2
	 * @param int $count
	 * @param bool $justFriends
	 * @param bool $singlePhoto
	 * @param bool $includeSelf
	 * @param string $extras
	 * @return stdClass|FlickrResponse
	*/
	public function GetContactsPhotos($count=null, $justFriends=null,
	                                  $singlePhoto=null, $includeSelf=null,
	                                  $extras=null)
	{
		$params = array();

		if (!is_null($count))
			$params['count'] = $count;

		if (!is_null($justFriends))
			$params['just_friends'] = $justFriends;

		if (!is_null($singlePhoto))
			$params['single_photo'] = $singlePhoto;

		if (!is_null($includeSelf))
			$params['include_self'] = $includeSelf;

		if (!is_null($extras))
			$params['extras'] = $extras;

		$res = $this->execute('flickr.photos.getContactsPhotos', $params, false);

		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Fetch a list of recent photos from the calling users' contacts.
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link
	 * http://flickr.com/services/api/flickr.photos.getContactsPublicPhotos.html
	 * @since 0.2
	 * @param string $userId
	 *   The NSID of the user to fetch photos for. Defaults to the user id of
	 *   the current Flickr API instance.
	 * @param int $count
	 * @param bool $justFriends
	 * @param bool $singlePhoto
	 * @param bool $includeSelf
	 * @param string $extras
	 * @return stdClass|FlickrResponse
	*/
	public function GetContactsPublicPhotos($userId=null, $count=null,
	                                        $justFriends=null, $singlePhoto=null,
	                                        $includeSelf=null, $extras=null)
	{
		$params = array();

		if (is_null($userId))
			$userId = $this->api->UserID();

		$params['user_id'] = $userId;

		if (!is_null($count))
			$params['count'] = $count;

		if (!is_null($justFriends))
			$params['just_friends'] = $justFriends;

		if (!is_null($singlePhoto))
			$params['single_photo'] = $singlePhoto;

		if (!is_null($includeSelf))
			$params['include_self'] = $includeSelf;

		if (!is_null($extras))
			$params['extras'] = $extras;

		$res = $this->execute('flickr.photos.getContactsPhotos', $params, false);

		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Returns a list of your photos with no tags.
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getUntagged.html
	 * @since 0.2
	 * @param array $args
	 *   An associative array of arguments. See the API page for a description.
	 * @return stdClass|FlickrResponse
	*/
	public function GetUntagged(array $args=array())
	{
		$allowed = array(
			'min_upload_date',
			'max_upload_date',
			'min_date_taken',
			'max_date_taken',
			'privacy_filter',
			'extras',
			'per_page',
			'page'
		);

		$params = array();

		foreach (array_keys($args) as $key)
			if (in_array($key, $allowed))
				$params[$key] = $args[$key];

		$res = $this->execute('flickr.photos.getUntagged', $params, false);

		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Returns a list of your photos that are not part of any sets.
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getNotInSet.html
	 * @since 0.2
	 * @param array $args
	 *   An associative array of arguments. See the API page for a description.
	 * @return stdClass|FlickrResponse
	*/
	public function GetNotInSet(array $args=array())
	{
		$allowed = array(
			'min_upload_date',
			'max_upload_date',
			'min_date_taken',
			'max_date_taken',
			'privacy_filter',
			'extras',
			'per_page',
			'page'
		);

		$params = array();

		foreach (array_keys($args) as $key)
			if (in_array($key, $allowed))
				$params[$key] = $args[$key];

		$res = $this->execute('flickr.photos.getNotInSet', $params, false);

		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Returns a list of your geo-tagged photos.
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getWithGeoData.html
	 * @since 0.2
	 * @param array $args
	 *   An associative array of arguments. See the API page for a description.
	 * @return stdClass|FlickrResponse
	*/
	public function GetWithGeoData(array $args=array())
	{
		$allowed = array(
			'min_upload_date',
			'max_upload_date',
			'min_date_taken',
			'max_date_taken',
			'privacy_filter',
			'sort',
			'extras',
			'per_page',
			'page'
		);

		$params = array();

		foreach (array_keys($args) as $key)
			if (in_array($key, $allowed))
				$params[$key] = $args[$key];

		$res = $this->execute('flickr.photos.getWithGeoData', $params, false);
		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Returns a list of your photos which haven't been geo-tagged.
	 *
	 * **NOTE!** The result of this method call will not be cached.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getWithGeoData.html
	 * @since 0.2
	 * @param array $args
	 *   An associative array of arguments. See the API page for a description.
	 * @return stdClass|FlickrResponse
	*/
	public function GetWithoutGeoData(array $args=array())
	{
		$allowed = array(
			'min_upload_date',
			'max_upload_date',
			'min_date_taken',
			'max_date_taken',
			'privacy_filter',
			'sort',
			'extras',
			'per_page',
			'page'
		);

		$params = array();

		foreach (array_keys($args) as $key)
			if (in_array($key, $allowed))
				$params[$key] = $args[$key];

		$res = $this->execute('flickr.photos.getWithoutGeoData', $params, false);
		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Return a list of photos matching some criteria. Only photos visible to the
	 * calling user will be returned. To return private or semi-private photos,
	 * the caller must be authenticated with 'read' permissions, and have
	 * permission to view the photos. Unauthenticated calls will only return
	 * public photos.
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.search.html
	 * @since 0.2
	 * @throws FlickrException
	 * @param array $args
	 *   Since there's a load of arguments you can pass to the search method
	 *   you must pass the arguments as an associative array. See the Flickr
	 *   API for details.
	 * @return stdClass|FlickrResponse
	*/
	public function Search(array $args)
	{
		//! These are the allowed search arguments
		$allowed = array(
			'user_id',
			'tags',
			'tag_mode',
			'text',
			'min_uload_date',
			'max_upload_date',
			'min_date_taken',
			'max_date_taken',
			'license',
			'sort',
			'privacy_filter',
			'bbox',
			'accuracy',
			'safe_search',
			'content_type',
			'machine_tags',
			'machine_tag_mode',
			'group_id',
			'woe_id',
			'place_id',
			'extras',
			'per_page',
			'page'
		);

		$params = array();

		foreach (array_keys($args) as $key)
			if (in_array($key, $allowed))
				$params[$key] = $args[$key];

		if (!sizeof($params))
			throw new FlickrException("Parameterless searches is not allowed!");

		$res = $this->execute('flickr.photos.search', $params, false);
		return isset($res->photos) ? $res->photos : $res;
	}

	/**
	 * Returns the full path to the plain photo
	 *
	 * @throws FlickrException
	 * @return string
	*/
	public function GetSrc()
	{
		if (!$this->src)
			$this->GetInfo();

		return $this->src;
	}

	/**
	 * Download the image
	 *
	 * @throws FlickrException
	 * @param string $saveTo
	 * @return string
	*/
	public function Download($saveTo)
	{
		if (!$this->src)
			$this->GetInfo();

		return parent::download($this->src, $saveTo);
	}
}
?>