<?php
/**
 * Description goes here...
 *
 * @author Pontus Östlund <spam@poppa.se>
*/

class HTMLParser2
{
  const TYPE_CONTAINER =   1;
  const TYPE_TAG       =   2;
  const TYPE_ATTRIBUTE =   4;
  const TYPE_DATA      =   8;
  const TYPE_ENTITY    =  16;
  const TYPE_COMMENT   =  32;
  const TYPE_PREPROC   =  64;
  const TYPE_DOCTYPE   = 128;
  const TYPE_ENDTAG    = 256;
  const TYPE_CDATA     = 512;

  private $contCB      = array();
  private $tagCB       = array();
  private $entCB       = null;
  private $dataCB      = null;
  private $allTagCB    = null;

  protected $doc = null;
  private $level = -1;

  public function __construct() {}

  public function Parse($html)
  {
    $this->doc = new DOMDocument();
    
    try {
      $this->doc->loadHTML($html);
    }
    catch (Exception $e) {
      throw $e;
    }

    foreach ($this->doc->childNodes as $cn)
      $this->walk($cn);
  }
  
  private function walk($node)
  {
    $this->level++;
    echo ("<pre>" . $this->indent() . $node->nodeName . "</pre>\n");

    if ($node->hasChildNodes()) {
      foreach ($node->childNodes as $child) {
	$this->walk($child);
      }
    }

    $this->level--;
  }
  
  private function indent()
  {
    return str_repeat('  ', $this->level);
  }
}
?>
