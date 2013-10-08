<?php
/**
 * HTML parser class
 *
 * @author  Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 */

namespace PLib\HTML;

/**
 * A simple HTML parser class. This is still work in progress.
 *
 * This parser will operate on callbacks. There are a number of different
 * ways to set callbacks:
 *
 * NOTE: If the callback functions returns `false` no eventual child nodes
 *       will be processed.
 *
 * <pre>
 *  $p = new Parser ();
 *
 *  // This callback will be called for every DIV that's found.
 *  //
 *  // The first argument is the actual DOMNode. If it's manipulated the
 *  // resulting output will be manipulated accordingly.
 *  //
 *  // The second argument is the node attributes. And the third argument is
 *  // the node value, if any.
 *
 *  $p->add_callback ('div', function (DOMNode $node, $attr, $data) {
 *    $node->setAttribute ('class', 'my-div');
 *  });
 *
 *  // You can also set many callbacks at once. The array index will match
 *  // the tags DOMNode name. So you can match comment and text nodes here
 *  // by defining #comment or/and #text as indices in the array
 *
 *  $p->add_tags (array(
 *    'div' => function (DOMNode $node, $attr, $data) {
 *      // handle div tags
 *    },
 *
 *    'script' => function (DOMNode $node, $attr, $data) {
 *      // handle script
 *    }
 *  ));
 *
 *  // If you wish to capture every tag do this:
 *  $p->set_tag_callback (function (DOMNode $node, $tagname, $attr, $data) {
 *    // Handle
 *  });
 *
 *  // Or capture all text nodes
 *  $p->set_data_callback (function (DOMNode $node, $text) {
 *    // Handle data
 *  });
 *
 *  // And there's a special callback to add for doctypes
 *  $p->set_doctype_callback (function (DOMNode $node) {
 *    // Handle doctype
 *  });
 * </pre>
 *
 * @author  Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 */
class Parser
{
  /**
   * Dom document
   * @var DOMDocument
   */
  protected $doc;

  /**
   * Container for named tag callbacks
   * @var array
   */
  protected $tags = array();

  /**
   * Data callback
   * @var function
   */
  protected $data_callback;

  /**
   * Tag callback
   * @var function
   */
  protected $tag_callback;

  /**
   * Doctype callback
   * @var function
   */
  protected $doctype_callback;

  private $cbcache;

  /**
   * Constructor
   */
  public function __construct ()
  {
    $this->doc = new \DOMDocument ();
    $this->doc->recover = true;
    $this->cbcache = new Object;
  }

  /**
   * Add a callback for tag `$tag`.
   * NOTE: This will take precedence over @see{set_tag_callback()}.
   *
   * @param string $tag
   * @param function $callback
   */
  public function add_tag ($tag, $callback)
  {
    $this->tags[$tag] = $callback;
  }

  /**
   * Add tag callbacks
   * NOTE: This will take precedence over @see{set_tag_callback()}.
   *
   * @param array $tag
   *  Associative array where the indices are the tag names to add callbacks
   *  for, and the values are the actual callbacks
   */
  public function add_tags (array $tags)
  {
    $this->tags += $tags;
  }

  /**
   * Add a callback for all data nodes
   *
   * @param function $func
   */
  public function set_data_callback ($func)
  {
    $this->data_callback = $func;
  }

  /**
   * Add a callback for all tag nodes
   *
   * @param function $func
   */
  public function set_tag_callback ($func)
  {
    $this->tag_callback = $func;
  }

  /**
   * Add a callback for the doctype node
   *
   * @param function $func
   */
  public function set_doctype_callback ($func)
  {
    $this->doctype_callback = $func;
  }

  /**
   * Parse the HTML
   *
   * @param string $html
   * @return The object being called
   */
  public function parse ($html)
  {
    @$this->doc->loadHTML ($html);
    $this->low_parse ($this->doc->childNodes);
    return $this;
  }

  /**
   * Render the parsed HTML to string
   *
   * @param bool $format
   */
  public function render ($format=null)
  {
    // The formatting of HTML is beyond bad. This is a little hack that makes
    // it at least a little bit better.
    $this->doc->formatOutput = $format !== false;
    $html = $this->doc->saveXML ();
    $xml = new \DOMDocument();
    $xml->preserveWhiteSpace = $format !== false ? true : $format;
    $xml->formatOutput = $format;
    $xml->loadXML ($html);
    return $xml->saveHTML ();
  }

  /**
   * Internal low level function
   */
  protected function low_parse (\DOMNodeList $children)
  {
    $len = $children->length;

    for ($i = $len-1; $i >= 0; --$i) {
      if (!($child = $children->item ($i)))
        continue;

      $cb = null;
      $cont = true;

      switch ($child->nodeType)
      {
        case XML_PI_NODE:
        case XML_ENTITY_REF_NODE:
        case XML_ENTITY_NODE:
        case XML_HTML_DOCUMENT_NODE:
          break;

        case XML_TEXT_NODE:
          if ($cb = $this->getcb ($child))
            $cont = $cb ($child, $child->data);
          break;

        case XML_DOCUMENT_TYPE_NODE:
          if ($cb = $this->doctype_callback)
            $cont = $cb ($child);
          break;

        case XML_COMMENT_NODE:
          if ($cb = $this->getcb ($child))
            $cont = $cb ($child, $child->nodeValue);
          break;

        default:
          if ($cb = $this->getcb ($child)) {
            $cont = $cb ($child, $child->nodeName,
                         $this->attr2array ($child->attributes),
                         $child->nodeValue);
          }

          break;
      }

      if ($cont !== false && sizeof ($child->childNodes))
        $this->low_parse ($child->childNodes);
    }
  }

  private function getcb ($tag)
  {
    $cb = null;
    $type = $tag->nodeType;
    $tag = $tag->nodeName;

    if ($cb = $this->cbcache->{$tag . $type})
      return $cb;

    switch ($type)
    {
      case XML_COMMENT_NODE:
        if (array_key_exists($tag, $this->tags))
          $cb = $this->tags[$tag];
        break;

      case XML_TEXT_NODE:
        if (array_key_exists($tag, $this->tags))
          $cb = $this->tags[$tag];
        elseif ($cb = $this->data_callback)
          ;
        break;

      default:
        if (array_key_exists($tag, $this->tags))
          $cb = $this->tags[$tag];
        elseif ($cb = $this->tag_callback)
          ;
        break;
    }

    if ($cb) {
      $this->cbcache->{$tag . $type} = $cb;
      return $cb;
    }
  }

  /**
   * Internal function. Converts an attribute node list into an array.
   *
   * @param DOMNamedNodeMap $attr
   */
  protected function attr2array (\DOMNamedNodeMap $attr)
  {
    $a = array();
    foreach ($attr as $k => $v)
      $a[$v->name] = $v->value;

    return $a;
  }
}

class Object
{
  private $container = array();

  public function __get ($k) {
    if (isset ($this->{$k}))
      return $this->{$k};

    return false;
  }

  public function __set ($k, $v) {
    $this->{$k} = $v;
  }
}
?>