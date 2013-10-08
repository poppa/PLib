<?php
/**
 * HTML parser class
 *
 * @author  Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 */

namespace PLib\HTML;


class Parser
{
  protected $doc;

  protected $containers = array();
  protected $tags = array();
  protected $data_callback;
  protected $tag_callback;

  public function __construct ($html)
  {
    $this->doc = new \DOMDocument ();
    $this->doc->loadHTML ($html);
  }

  public function add_containers (array $containers)
  {
    $this->containers += $containers;
  }

  public function add_tags (array $tags)
  {
    $this->tags += $tags;
  }

  public function set_data_callback ($func)
  {
    $this->data_callback = $func;
  }

  public function set_tag_callback ($func)
  {
    $this->tag_callback = $func;
  }

  public function parse ()
  {
    $this->low_parse ($this->doc->childNodes);
  }

  protected function low_parse ($children)
  {
    foreach ($children as $child) {
      //echo $child->nodeName ."($child->nodeType)\n";

      $cb = null;
      $cont = true;

      switch ($child->nodeType)
      {
        case XML_ELEMENT_NODE:
          if (array_key_exists($child->nodeName, $this->containers))
            $cb = $this->containers[$child->nodeName];
          elseif (array_key_exists($child->nodeName, $this->tags)) 
            $cb = $this->tags[$child->nodeName];
          elseif ($this->tag_callback)
            $cb = $this->tag_callback;

          if ($cb) {
            $cont = $cb ($this, $child->nodeName, $this->attr2array ($child->attributes));
          }

          break;

        case XML_TEXT_NODE:
          if ($this->data_callback)
            $cb = $this->data_callback;
          break;

        case XML_DOCUMENT_TYPE_NODE:
          continue 2;
          break;

        case XML_HTML_DOCUMENT_NODE:
          continue 2;
          break;

        case XML_PI_NODE:
          continue 2;
          break;

        case XML_COMMENT_NODE:
          continue 2;
          break;

        case XML_DOCUMENT_NODE:
          continue 2;
          break;

        default:
          throw new \Exception ("Unknown node ($child->nodeName:$child->nodeType");
      }

      
      if ($cont !== false && sizeof ($child->childNodes))
        $this->low_parse ($child->childNodes);
    }
  }

  protected function attr2array ($attr)
  {
    $a = array();
    foreach ($attr as $k => $v)
      $a[$v->name] = $v->value;

    return $a;
  }
}
?>