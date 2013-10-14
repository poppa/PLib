<?php
/**
 * A simple class for handling cookies
 *
 * @copyright 2013 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib\Web
 */

namespace PLib\Web;

/**
 * A simple class for handling cookies
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class Cookie
{
  /**
   * The name of the cookie
   * @var string
   */
  protected $name = null;

  /**
   * The path of the cookie
   * @var string
   */
  protected $path = null;

  /**
   * The domain of the cookie
   * @var string
   */
  protected $domain = null;

  /**
   * Secure. If true only use over a secure HTTP connection (HTTPS)
   * @var bool
   */
  protected $secure = false;

  /**
   * Only allow the cookie over HTTP, means it won't be accessible to
   * JavaScript for instance.
   * @link setcookie()
   * @var bool
   */
  protected $httponly = false;

  /**
   * Value buffer if @see{Cookie::Append()} is used.
   * @var string
   */
  protected $value = null;

  /**
   * Constructor
   *
   * @param string $name
   * @param string $path
   * @param string $domain
   * @param bool $secure
   * @param bool $httponly
   */
  public function __construct ($name, $path="/", $domain=null, $secure=false,
                               $httponly=false)
  {
    $this->name     = $name;
    $this->path     = $path;
    $this->domain   = $domain;
    $this->secure   = $secure;
    $this->httponly = $httponly;
  }

  /**
   * Append to the value buffer
   *
   * @param string $value
   */
  public function append ($value)
  {
    $this->value .= $value;
  }

  /**
   * Set the cookie
   *
   * @param mixed $value
   * @param int|string $expires
   *  If $expires is a string the time will be calculated by @link{strtotime()}
   *  so for a description of how the strings can look like read there.
   * @param bool $serialize
   *  If true the value will be @see{serialize()}'d before written to the
   *  cookie
   * @return bool
   */
  public function set ($value, $expires=0, $serialize=false)
  {
    if ($this->value && !is_null ($value))
      $this->value .= $value;
    else
      $this->value = $value;

    if (!is_numeric ($expires))
      $expires = strtotime ($expires, time ());

    if ($serialize)
      $this->value = urlencode (serialize ($this->value));

    return setcookie ($this->name, $this->value, $expires, $this->path,
                      $this->domain, $this->secure, $this->httponly);
  }

  /**
   * Retreive the cookie
   *
   * @param bool $unserialize
   *  If the value was serialized before written to the cookie this needs to
   *  be set to true when retreiving the cookie.
   * @return string
   */
  public function get ($unserialize=false)
  {
    if (isset ($_COOKIE[$this->name])) {
      $c = $_COOKIE[$this->name];
      if ($unserialize)
        $c = unserialize (urldecode ($c));

      return $c;
    }

    return null;
  }

  /**
   * Simply deletes the cookie
   *
   * @return bool
   */
  public function remove ()
  {
    if (setcookie ($this->name, "", 0, $this->path, $this->domain,
                   $this->secure, $this->httponly))
    {
      return true;
    }

    return false;
  }

  /**
   * Alias for {@link Cookie::Remove()}.
   *
   * @return bool
   */
  public function delete ()
  {
    $this->remove ();
  }
}
?>