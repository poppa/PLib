<?php
/**
 * HTML parser and related classes
 *
 * @copyright 2014 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib
 */

namespace PLib\HTML;

/**
 * A simple HTML parser class.
 *
 * This parser operates on callbacks. There are a number of different
 * ways to set callbacks and there are different types of callbacks to set
 * depending on the type of tag to capture.
 *
 * The two main callback types are for container tags like `<div></div>`,
 * `<p></p>`, `<h1></h1>` and so on, and tags without content like `<hr>`,
 * `<br>`, `<img>` and so on. Since this parser in many ways is a generic
 * `SGML` parser it isn't aware of which tag is a container and which is not.
 * So you'll have to decide that for your self.
 *
 * All callback functions have the same signature which is:
 *
 * <pre>
 *  void|false|string|array function(Parser $p, ATag $tag)
 * </pre>
 *
 * o If `void` is returned the parser will just continue.
 *
 * o If `false` is returned the parser will abort.
 *
 * o If `string` is returned the captured `$tag` will be replaced with what's
 *   returned and the parser will continue with the newly inserted string.
 *
 *   *NOTE*
 *
 *   Be aware that if you insert the same type of tag as was captured you
 *   may very well end up with an infinit loop. But there's ways around that.
 *
 * o If `array` is returned the first index of the array is what will replace
 *   the captured tag, and the parser will then skip to after the captured tag.
 *   If the captured tag is a container tag the parser will skip to after the
 *   captured tags closing tag.
 *
 *   A second, optional, value can be placed in the returned array which
 *   indicates what type of tag is returned. Say you capture a non-container
 *   tag and want to replace that with a container tag you need to tell the
 *   parser that so that it can skip to the end of what was inserted. The
 *   parser has the constants {@link Parser::CB_TYPE_CONTAINER} and
 *   {@link Parser::CB_TYPE_TAG} that indicates what's being returned.
 *
 * The most simple way to add callbacks is via {@link Parser::add_tag()},
 * {@link Parser::add_container} or if you want to add serveral different
 * callbacks at once {@link Parser::add_tags()} and
 * {@link Parser::add_containers()}.
 *
 * <pre>
 *  $parser = new Parser();
 *  $parser->add_tag('hr', function(Parser $p, ATag $t) {
 *    // Do something
 *  });
 *
 *  $parser->add_container('b', function(Parser $p, ATag $t) {
 *    // Do something
 *  });
 *
 *  $parser->add_containers(array(
 *    'div' => function(Parser $p, ATag $t) {
 *      // Do something with divs
 *    },
 *
 *    'h2' => function(Parser $p, ATag $t) {
 *      // Do something with h2s
 *    },
 *
 *    'strong' => function(Parser $p, ATag $t) {
 *      // Do something with strongs
 *    }
 *  ));
 * </pre>
 *
 * Another way is to inherit the parser and defining callback methods in the
 * class:
 *
 * <pre>
 *  class MyParser extends Parser
 *  {
 *    function cb_tag_br(Parser $p, ATag $t) {
 *      // Do something with br tags
 *    }
 *
 *    function cb_container_nav(Parser $p, ATag $t) {
 *      // Do something with nav tags
 *    }
 *  }
 * </pre>
 *
 * And finally there's a way to capture every single tag:
 *
 * <pre>
 *  $parser->set_tag_callback(function(Parser $p, ATag $t) {
 *    // Every single tag
 *  });
 * </pre>
 *
 * And you can capture every single text node as well, that is only the
 * text content of the document:
 *
 * <pre>
 *  $parser->set_data_callback(function(Parser $p, ATag $t) {
 *    // Every single text node
 *  });
 * </pre>
 *
 * Here's some examples of usage
 *
 * <pre>
 *  // This example captures the page titles that makes the results of a
 *  // Google search. This example doesn't manipulate the HTML but rather
 *  // just scrapes it for data.
 *
 *  require_once('path/to/PLib.php');
 *  PLib\import('htmlparser');
 *
 *  use PLib\HTML\Parser;
 *  use PLib\HTML\ATag;
 *
 *  // Container to store some stuff
 *  $text = array();
 *
 *  $html = `http query google for a search on PHP`
 *
 *  $p = new Parser();
 *  $p->set_container('h3', function(Parser $p, ATag $t) {
 *    // Get the inner contents of the H3 tag
 *    $cont = $p->get_content($t);
 *
 *    // Clone the parser and remove the callback from the clone
 *    $new_p = clone $p;
 *    $new_p->remove_callback('p');
 *    // You can also clear all callbacks with $new_p->clear_callbacks()
 *
 *    $new_p->add_container('a', function(Parser $p, ATag $t) {
 *      array_push($text, array(
 *        'text' => strip_tags($p->get_content($t)),
 *        'url'  => $t->get_attribute('href')
 *      ));
 *    });
 *
 *    $new_p->parse($cont);
 *  });
 *
 *  $p->parse($html);
 * </pre>
 *
 * @author  Pontus Östlund <poppanator@gmail.com>
 */
class Parser
{
  /**
   * Callback type is container
   */
  const CB_TYPE_CONTAINER = 1;

  /**
   * Callback type is a tag
   */
  const CB_TYPE_TAG = 2;

  // Internal
  const CB_TYPE_ANY_TAG = 3;
  // Internal
  const CB_TYPE_DATA = 4;

  /**
   * Position in the parser loop
   * @var int
   */
  protected $ppos = -1;

  /**
   * If {@link HTMLParser::get_content()} is called this will be the position
   * of the closing tag of the tag that was given to
   * {@link HTMLParser::get_content()}. Depending on what was returned from the
   * user callback the parser loop will advance to this position.
   * @var int
   */
  protected $ppos_advance = 0;

  /**
   * Container tag callbacks
   * @var array
   */
  protected $cb_container = array();

  /**
   * Tag tag callbacks
   * @var array
   */
  protected $cb_tag = array();

  /**
   * Array of all found tags and data
   * @var array
   */
  protected $stack = array();

  /**
   * Is this instance a clone?
   * @var bool
   */
  protected $is_clone = false;

  /**
   * Any tag callback
   */
  protected $tag_callback = null;

  /**
   * Any data callback
   */
  protected $data_callback = null;

  /**
   * Creates a new HTMLParser instance
   */
  function __construct()
  {
    foreach (get_class_methods($this) as $method) {
      if (substr($method, 0, 3) === 'cb_') {
        sscanf ($method, "cb_%[^_]_%s", $type, $name);
        if ($type === 'container')
          $this->cb_container[$name] = array($this, $method);
        else if ($type === 'tag')
          $this->cb_tag[$name] = array($this, $method);
      }
    }
  }

  /**
   * Set any tag callback. This will be called for every tag. To unset it
   * call the method without any arguments
   *
   * @param function $cb_func
   *  Function signature is the same as for every other callback signature.
   *  See {@link HTMLParser} for a description.
   * @return HTMLParser
   *  Returns the object being called
   */
  function set_tag_callback($cb_func=null)
  {
    $this->tag_callback = $cb_func;
    return $this;
  }

  /**
   * Set data callback. The function will be called for every text tag.
   * To unset it call the method without any arguments
   *
   * @see set_tag_callback()
   * @param function $cb_func
   *  Function signature is the same as for every other callback signature.
   *  See {@link HTMLParser} for a description.
   * @return HTMLParser
   *  Returns the object being called
   */
  function set_data_callback($cb_func=null)
  {
    $this->data_callback = $cb_func;
    return $this;
  }

  /**
   * Add a container callback
   *
   * @param string $name
   *  The name of the container tag to apply the callback on
   * @param function $callback
   *  The callback signature is:
   *  function ({@link HTMLParser} $p, {@link ATag} $t)
   * @return HTMLParser
   *  The object being called
   */
  function add_container($name, $callback)
  {
    $this->cb_container[strtolower($name)] = $callback;
    return $this;
  }

  /**
   * Add container callbacks.
   *
   * @see {@link HTMLParser::add_container()}
   * @see {@link HTMLParser::add_tags()}
   * @param array $c
   *  The key/value array where the key is the tag name and the value
   *  is the callback function.
   * @return HTMLParser
   *  The object being called
   */
  function add_containers(array $c)
  {
    foreach ($c as $tag => $cb) {
      $this->cb_container[strtolower($tag)] = $cb;
    }
    return $this;
  }

  /**
   * Remove a container callback.
   *
   * @param string $name
   *  The name of the container to remove the callback for.
   * @return HTMLParser
   *  The object being called
   */
  function remove_container($name)
  {
    unset($this->cb_container[strtolower($name)]);
    return $this;
  }

  /**
   * Add a tag callback.
   *
   * @param string $name
   *  The name of the tag to apply the callback on
   * @param function $callback
   *  The callback signature is:
   *  function({@link HTMLParser} $p, {@link ATag} $t)
   * @return HTMLParser
   *  The object being called
   */
  function add_tag($name, $callback)
  {
    $this->cb_tag[strtolower($name)] = $callback;
    return $this;
  }

  /**
   * Add tag callbacks.
   *
   * @see {@link HTMLParser::add_tag()}
   * @see {@link HTMLParser::add_container()}
   * @param array $c
   *  The key/value array where the key is the tag name and the value
   *  is the callback function.
   * @return HTMLParser
   *  The object being called
   */
  function add_tags(array $c)
  {
    foreach ($c as $tag => $cb) {
      $this->cb_tag[strtolower($tag)] = $cb;
    }
    return $this;
  }

  /**
   * Remove a tag callback.
   *
   * @param string $name
   *  The name of the tag to remove the callback for.
   * @return HTMLParser
   *  The object being called
   */
  function remove_tag($name)
  {
    unset($this->cb_tag[strtolower($name)]);
    return $this;
  }

  /**
   * Remove all tag callbacks.
   *
   * @return HTMLParser
   *  The object being called
   */
  function clear_tags()
  {
    $this->cb_tag = array();
    return $this;
  }

  /**
   * Remove all container callbacks.
   *
   * @return HTMLParser
   *  The object being called
   */
  function clear_containers()
  {
    $this->cb_container = array();
    return $this;
  }

  /**
   * Remove all callbacks.
   *
   * @return HTMLParser
   *  The object being called
   */
  function clear_callbacks()
  {
    $this->clear_containers();
    $this->clear_tags();
    $this->tag_callback = null;
    $this->data_callback = null;

    return $this;
  }

  /**
   * Find the position of $tag
   *
   * @param ATag $tag
   * @return int
   *  Returns the position of the tag in the stack or -1 if the
   *  tag was not found.
   */
  protected function find_tag(ATag $t)
  {
    for ($i = 0; $i < sizeof($this->stack); $i++) {
      if ($this->stack[$i] === $t) {
        return $i;
      }
    }

    return -1;
  }

  /**
   * Find the corresponding endtag for $t
   *
   * @param ATag $t
   * @param int $pos
   *  Offset to start from, i.e the position of $t.
   *  If it's -1 the position of $t will first be resolved.
   */
  protected function find_end_tag(ATag $t, $pos=-1)
  {
    if ($pos === -1)
      $pos = $this->find_tag($t);

    if ($pos === -1) {
      throw new Exception("Ooops!", 1);
    }

    $pos += 1;
    $depth = 0;
    $n  = $t->name();
    $cn = '/' . $n;

    while (isset($this->stack[$pos])) {
      $t = $this->stack[$pos];
      $tn = $t->name();

      if ($tn === $n) {
        $depth++;
      }
      else if ($tn === $cn) {
        if ($depth === 0) {
          break;
        }

        $depth -= 1;
      }

      $pos++;
    }

    return $pos;
  }

  /**
   * Get the contents of a tag.
   *
   * @param Tag $tag
   * @return string
   */
  function get_content(ATag $tag)
  {
    if (!$tag->is_a(Tag::TAG)) {
      return null;
    }

    $tpos = $this->find_tag($tag);

    if ($tpos === -1) {
      throw new Exception("Tag not found! ", 1);
    }

    $n     = $tag->name();
    $cn    = '/' . $n;
    $pos   = $tpos + 1; // skip the tag it self
    $depth = 0;
    $stack = array();

    while (isset($this->stack[$pos])) {
      $t = $this->stack[$pos];
      $tn = $t->name();

      if ($tn === $n)
        $depth++;

      if ($tn === $cn) {
        if ($depth === 0) {
          break;
        }

        $depth -= 1;
      }

      array_push($stack, $t);
      $pos++;
    }

    $this->ppos_advance = $pos;

    if (sizeof($stack)) {
      $buf = '';

      foreach ($stack as $t) {
        $buf .= (string) $t;
      }

      return $buf;
    }

    return '';
  }

  /**
   * Parse html.
   *
   * @param string $str
   * @return HTMLParser
   *  The object being called
   */
  function parse($str)
  {
    $this->stack = $this->split($str);
    $pos = 0;
    $this->ppos = 0;

    //print_r($this->stack);

    while (isset($this->stack[$pos])) {
      $cbtype = null;
      $tag = $this->stack[$pos];
      $n = strtolower($tag->name());
      $ret = null;

      //echo "* $n\n";

      if (isset($this->cb_container[$n])) {
        $cbtype = self::CB_TYPE_CONTAINER;
        $ret = call_user_func_array($this->cb_container[$n],
                                    array($this, $tag));
      }
      elseif (isset($this->cb_tag[$n])) {
        $cbtype = self::CB_TYPE_TAG;
        $ret = call_user_func_array($this->cb_tag[$n],
                                    array($this, $tag));
      }
      elseif ($this->tag_callback && !$tag->is_a(Tag::TEXT)) {
        $cbtype = self::CB_TYPE_ANY_TAG;
        $ret = call_user_func_array($this->tag_callback,
                                    array($this, $tag));
      }
      elseif ($this->data_callback && $tag->is_a(Tag::TEXT)) {
        $cbtype = self::CB_TYPE_DATA;
        $ret = call_user_func_array($this->data_callback,
                                    array($this, $tag));
      }

      if ($ret === false) {
        return $this;
      }
      elseif (is_array($ret) || is_string($ret)) {
        if (sizeof($ret)) {
          // First we tokenize what's returned, and then we replace the current
          // tag and it's children with what was returned.
          $new_tags = $this->split(is_array($ret) ? $ret[0] : $ret);

          $epos = $pos;

          if ($cbtype === self::CB_TYPE_CONTAINER) {
            $epos = $this->find_end_tag($tag, $pos);
          }
          else {
            if (sizeof($ret) > 1 && $ret[1] === self::CB_TYPE_CONTAINER) {
              $epos = $pos + sizeof($new_tags) - 1;
            }
          }

          array_splice($this->stack, $pos, $epos-$pos+1, $new_tags);

          // If an array was returned we skip parsing what was inserted
          // and skip to the end of what was inserted
          if (is_array($ret)) {
            $this->ppos_advance = $pos + sizeof($new_tags);
          }
          else {
            $pos -= 1;
            $this->ppos_advance = 0;
          }
        }

        if ($this->ppos_advance > 0) {
          $pos = $this->ppos_advance - 1;
        }
      }

      $pos += 1;
      $this->ppos = $pos;
      $this->ppos_advance = 0;
    }

    return $this;
  }

  /**
   * Render the object back to HTML
   *
   * @return string
   *  The HTML
   */
  function render()
  {
    if ($this->ppos < 0)
      return null;

    $buf = '';

    for ($i = 0; $i < $this->ppos; $i++) {
      $t = $this->stack[$i];
      if (!$t->is_deleted())
        $buf .= (string) $t;
    }

    return $buf;
  }

  /**
   * Is this parser a clone of the original one?
   *
   * @return bool
   */
  function is_clone()
  {
    return $this->is_clone;
  }

  /**
   * To string converter. Same as calling {@link HTMLParser::render()}.
   *
   * @return string
   */
  function __toString()
  {
    return $this->render();
  }

  /**
   * Create an array of {@link ATag} objects.
   *
   * @param string $str
   * @return array(Tag)
   */
  protected function split($str)
  {
    $str  .= "\0\0";
    $pos   = 0;
    $len   = strlen($str)-2;
    $stack = array();

    while ($pos < $len) {
      $data  = null;
      $start = $pos;
      $c     = $str[$pos];

      switch ($c) {
        case '<':
          $n = $str[$pos+1];

          switch ($n) {
            case '?': // preprocessor instruction
              $pos = strpos($str, '?>', $pos+1) + 1;
              $data = substr($str, $start, $pos-$start+1);
              array_push($stack, new PPITag($data));
              break;

            case '!': // comment or doctype
              if ($str[$pos+2] == '-') {
                $pos  = strpos($str, '-->', $pos+1) + 2;
                $data = substr($str, $start, $pos-$start+1);
                array_push($stack, new CommentTag($data));
              }
              else {
                $pos  = strpos($str, '>', $pos+1);
                $data = substr($str, $start, $pos-$start+1);
                array_push($stack, new DoctypeTag($data));
              }
              break;

            case '/': // end tag
              $pos  = strpos($str, '>', $pos+1);
              $data = substr($str, $start, $pos-$start+1);
              array_push($stack, new EndTag($data));
              break;

            default:
              $pos  = strpos($str, '>', $pos+1);
              $data = substr($str, $start, $pos-$start+1);
              $type = ($str[$pos-1] == '/') ? ATag::XML : ATag::TAG;
              array_push($stack, ATag::create($data, $type));
              break;
          }

          break;

        default:
          $pos = strpos($str, '<', $pos);
          if ($pos !== false) {
            $data = substr($str, $start, $pos-$start);
            array_push($stack, new TextTag($data));
            $pos -= 1;
          }
          else {
            $data = substr($str, $start);
            array_push($stack, new TextTag($data));
            $pos = $len;
          }
          break;
      }

      $pos++;
    }

    return $stack;
  }

  /**
   * Destructor
   */
  function __destruct()
  {
    $this->stack = null;
  }

  /**
   * Clone the object. All callbacks of the cloned object will be kept
   */
  function __clone()
  {
    $this->is_clone = true;
    $this->stack = array();
    $this->ppos = -1;
    $this->ppos_advance = 0;
  }
}

/**
 * Class representing a HTML tag
 */
class ATag
{
  /**
   * Tag types
   */
  const NONE    =  0;
  const XML     =  1;
  const TAG     =  2;
  const CLOSE   =  4;
  const DOCTYPE =  8;
  const TEXT    = 16;
  const COMMENT = 32;
  const PPI     = 64;

  /**
   * Type of tag
   * @var int
   */
  protected $type = self::NONE;

  /**
   * The original data
   * @var string
   */
  protected $data = null;

  /**
   * The name of the tag
   * @var string
   */
  protected $name;

  /**
   * Is this tag deleted?
   * @var bool
   */
  protected $is_deleted = false;

  protected $parser = null;

  /**
   * Creates a new tag object
   *
   * @param string $data
   * @param Parser $p
   */
  function __construct($data, Parser $p=null)
  {
    $this->parser = $p;
    $this->data = $data;
    $this->resolve_name();
  }

  /**
   * Factory method for creating a tag object
   *
   * @param string $data
   * @param int $type
   *  Any of the tag type constants in {@link ATag}.
   * @return ATag
   */
  public static function create($data, $type)
  {
    switch ($type) {
      case self::TAG:
        return new Tag($data);

      case self::XML:
        return new XMLTag($data);

      case self::DOCTYPE:
        return new DoctypeTag($data);

      case self::CLOSE:
        return new CloseTag($data);

      case self::COMMENT:
        return new CommentTag($data);

      case self::TEXT:
        return new TextTag($data);

      case self::PPI:
        return new PPITag($data);
    }

    throw new Exception("Unknown tag type $type", 1);
  }

  /**
   * Checks if this object is of type $what
   *
   * @param int $what
   *  Any of the tag type constants in {@link ATag}
   * @return bool
   */
  public function is_a($what)
  {
    return ($what & $this->type) === $this->type;
  }

  /**
   * Returns the tag type
   *
   * @return int
   */
  public function type()
  {
    return $this->type;
  }

  /**
   * Returns the tag name
   *
   * @return string
   */
  public function name()
  {
    return $this->name;
  }

  /**
   * Delete the tag, i.e it will be skipped when rendered
   */
  public function delete()
  {
    $this->is_deleted = true;
  }

  /**
   * Undelete the tag, i.e it will be kept when rendered if previously deleted
   */
  public function undelete()
  {
    $this->is_deleted = false;
  }

  /**
   * Is the tag deleted or not?
   *
   * @return bool
   */
  public function is_deleted()
  {
    return $this->is_deleted;
  }

  /**
   * Resolve the tag name
   */
  protected function resolve_name()
  {
    if ($this->type === self::TEXT) {
      $this->name = '#text';
      return;
    }

    if ($this->type === self::COMMENT) {
      $this->name = "!--";
    }
    elseif ($this->is_a(self::DOCTYPE|self::PPI)) {
      preg_match("#<([!?][-a-z]+)#i", $this->data, $m);
      $this->name = $m[1];
    }
    else {
      if (preg_match("#<([/]?[-_a-z0-9]+)#i", $this->data, $m))
        $this->name = $m[1];
      else
        throw new Exception("Unable to resolve tag name!", 1);
    }
  }

  /**
   * Construct the tag
   *
   * @return string
   */
  protected function make_tag()
  {
    return $this->data;
  }

  /**
   * To string converter.
   */
  function __toString()
  {
    return $this->make_tag();
  }
}

/**
 * Class representing an end tag
 */
class EndTag extends ATag
{
  /**
   * {@inheritdoc}
   */
  protected $type = self::CLOSE;

  /**
   * {@inheritdoc} of type end tag
   */
  function __construct($data)
  {
    parent::__construct($data);
  }
}

/**
 * Class representing a tag
 */
class Tag extends ATag
{
  /**
   * {@inheritdoc}
   */
  protected $type = self::TAG;

  /**
   * Tag attributes
   * @var array
   */
  protected $attributes = array();

  /**
   * {@inheritdoc} of type tag
   */
  function __construct($data)
  {
    parent::__construct($data);
    $this->parse_attr();
  }

  /**
   * Checks if this tag has any attributes
   *
   * @return bool
   */
  public function has_attributes()
  {
    return sizeof($this->attributes) > 0;
  }

  /**
   * Get all attributes
   *
   * @return array
   */
  public function attributes()
  {
    return $this->attributes;
  }

  /**
   * Attribute setter
   *
   * @param string $name
   * @param string $val
   */
  public function set_attribute($name, $val)
  {
    $this->attributes[$name] = htmlentities($val);
  }

  /**
   * Remove attribute
   *
   * @param string $name
   */
  public function remove_attribute($name)
  {
    unset($this->attributes[$name]);
  }

  /**
   * Get attribute
   *
   * @param string $name
   * @return string
   */
  public function get_attribute($name)
  {
    return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
  }

  /**
   * Parse the attributes and populate the {@link Tag::attribute} array.
   */
  protected function parse_attr()
  {
    $s = trim(strpbrk($this->data, " \t"));

    if ($s !== '>' && strlen($s)) {
      $this->attributes = array();
      $r = '#([-_a-z0-9]+)(=([\'"]?)(.*?)\\3)?[\s/>]#i';

      if (preg_match_all($r, $s, $m)) {
        for ($i = 0; $i < sizeof($m[0]); $i++) {
          $name = $m[1][$i];
          $val  = $m[4][$i];

          if (empty($m[3][$i]))
            $val = null;

          $this->attributes[$name] = $val;
        }
      }
    }
  }

  /**
   * Makes a string of the attributes
   *
   * @return string
   */
  protected function attr_to_string()
  {
    $tmp = array();

    foreach ($this->attributes as $key => $value) {
      $s = $key;
      if ($value) $s .= "=\"" . $value ."\"";
      array_push($tmp, $s);
    }

    return sizeof($tmp) ? ' ' . implode(' ', $tmp) : '';
  }

  /**
   * Creates a string representation of the tag
   *
   * @return string
   */
  protected function make_tag()
  {
    $t = "<$this->name" . $this->attr_to_string();

    if ($this->is_a(self::XML)) {
      $t .= " /";
    }

    return $t . '>';
  }

}

/**
 * Class representing a self closing tag
 */
class XMLTag extends Tag
{
  /**
   * {@inheritdoc}
   */
  protected $type = self::XML;
}

/**
 * Class representing a doctype tag
 */
class DoctypeTag extends Tag
{
  /**
   * {@inheritdoc}
   */
  protected $type = self::DOCTYPE;
}

/**
 * Class representing html text
 */
class TextTag extends ATag
{
  /**
   * {@inheritdoc}
   */
  protected $type = self::TEXT;
  protected $content = null;

  function get_content()
  {
    return $this->content === null ? $this->data : $this->content;
  }

  function set_content($data)
  {
    $this->content = $data;
  }

  function __toString()
  {
    return $this->content === null ? $this->data : $this->content;
  }
}

/**
 * Class representing a comment
 */
class CommentTag extends TextTag
{
  /**
   * {@inheritdoc}
   */
  protected $type = self::COMMENT;
  protected $tag_start = '<!--';
  protected $tag_end   = '-->';
  protected $content   = null;

  function __construct($data)
  {
    parent::__construct($data);
    $this->resolve_content();
  }

  protected function resolve_content()
  {
    $content = substr($this->data, strlen($this->tag_start));
    $content = substr($content, 0, strlen($content) - strlen($this->tag_end));
    $this->content = $content;
  }

  function __toString()
  {
    return $this->tag_start . $this->content . $this->tag_end;
  }
}

class PPITag extends CommentTag
{
  /**
   * {@inheritdoc}
   */
  protected $type = self::PPI;

  function __construct($data)
  {
    parent::__construct($data);
  }

  protected function resolve_content()
  {
    $this->tag_start = '<' . $this->name;
    $this->tag_end = '?>';
    parent::resolve_content();
  }
}
?>