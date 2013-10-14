<?php
/**
 * Qname class
 *
 * @copyright 2013 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib
 */

namespace PLib\XML;

/**
 * Class representing a Qname.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class Qname
{
  /**
   * The namespace
   * @var string
   */
  protected $namespace;
  /**
   * The local name.
   * @var string
   */
  protected $localname;
  /**
   * The namespace prefix
   * @var string
   */
  protected $prefix;

  /**
   * Creates a new `Qname` object
   *
   * @param string $namespace
   * @param string $localname
   * @param string $prefix
   */
  public function __construct ($namespace, $localname=null, $prefix=null)
  {
    $this->namespace = $namespace;
    $this->localname = $localname;
    $this->prefix    = $prefix;

    if ($namespace && strlen ($namespace) && $namespace[0] == '{')
      sscanf ($namespace, "{%[^}]}%s", $this->namespace, $this->localname);
    elseif ($localname && strpos ($localname, ':') !== false &&
            strpos ($localname, '://') === false)
    {
      sscanf ($localname, " %[^:]:%s", $this->prefix, $this->localname);
    }
  }

  /**
   * Getter/setter for the namespace
   *
   * @param string $namespace
   * @return string
   */
  public function namespace_uri ($namespace=null)
  {
    if ($namespace != null)
      $this->namespace = $namespace;

    return $this->namespace;
  }

  /**
   * Getter/setter for the local name
   *
   * @param string $localname
   * @return string
   */
  public function local_name ($localname=null)
  {
    if ($localname != null)
      $this->localname = $localname;

    return $this->localname;
  }

  /**
   * Getter/setter for the namespace prefix
   *
   * @param string $prefix
   * @return string
   */
  public function prefix ($prefix=null)
  {
    if ($prefix != null)
      $this->prefix = $prefix;

    return $this->prefix;
  }

  /**
   * Returns the full attribute name
   *
   * @return string
   */
  public function name ()
  {
    $n = '';
    if ($this->prefix != null)
      $n = $this->prefix . ':';

    return $n . $this->localname;
  }

  /**
   * Returns the fully qualified name
   *
   * @return string
   */
  public function fqn ()
  {
    return $this->namespace ?
           sprintf ("{%s}%s", $this->namespace, $this->localname) :
           $this->localname;
  }

  /**
   * Cast to string. Same as {@see Qname::fqn()}
   *
   * @return string
   */
  public function __toString ()
  {
    return $this->fqn ();
  }
}
?>