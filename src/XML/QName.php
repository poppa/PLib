<?php
/**
 * QName class
 *
 * @author  Pontus Östlund <pontus@poppa.se>
 * @version 1.0
 * @license GPL License 2
 * @package XML
 */

/**
 * Class representing a QName.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package XML
 */
class QName
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
  protected $localName;
  /**
   * The namespace prefix
   * @var string
   */
  protected $prefix;

  /**
   * Creates a new `QName` object
   *
   * @param string $namespace
   * @param string $localName
   * @param string $prefix
   */
  public function __construct($namespace, $localName=null, $prefix=null)
  {
    $this->namespace = $namespace;
    $this->localName = $localName;
    $this->prefix    = $prefix;

    if ($namespace && strlen($namespace) && $namespace[0] == '{')
      sscanf($namespace, "{%[^}]}%s", $this->namespace, $this->localName);
    elseif ($localName && strpos($localName, ':') !== false &&
            strpos($localName, '://') === false)
    {
      sscanf($localName, " %[^:]:%s", $this->prefix, $this->localName);
    }
  }

  /**
   * Getter/setter for the namespace
   *
   * @param string $namespace
   * @return string
   */
  public function NamespaceURI($namespace=null)
  {
    if ($namespace != null)
      $this->namespace = $namespace;

    return $this->namespace;
  }

  /**
   * Getter/setter for the local name
   *
   * @param string $localName
   * @return string
   */
  public function LocalName($localName=null)
  {
    if ($localName != null)
      $this->localName = $localName;

    return $this->localName;
  }

  /**
   * Getter/setter for the namespace prefix
   * @param string $prefix
   * @return string
   */
  public function Prefix($prefix=null)
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
  public function Name()
  {
    $n = '';
    if ($this->prefix != null)
      $n = $this->prefix . ':';

    return $n . $this->localName;
  }

  /**
   * Returns the fully qualified name
   *
   * @return string
   */
  public function FQN()
  {
    return $this->namespace ?
           sprintf("{%s}%s", $this->namespace, $this->localName) :
           $this->localName;
  }

  /**
   * Cast to string. Same as {@see QName::FQN()}
   * 
   * @return string
   */
  public function __toString()
  {
    return $this->FQN();
  }
}
?>