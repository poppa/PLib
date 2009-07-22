<?php
/**
 * GTextDTR - Dynamic Text Replacement
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Graphics
 * @version 0.1
 * @todo When the new HTML parser is done, fix this one too.
 */

/**
 * We need the HTML parser
 */
require_once PLIB_INSTALL_DIR . '/Parser/HTML.php';

/**
 * The GTextDTR (GText Dynamic Text Replacement) class will parse the entire
 * page and look for H1-H4 tags and wrap the content in a span tag, that is
 * hidden by a CSS rule, and turn the content into a text image which will be
 * set as background image in respecive H(x) tags.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Graphics
 */
class GTextDTR extends HTMLParser
{
  /**
   * Where we keep the parsed buffer
   * @var string
   */
  private $buffer = null;
  /**
   * Set to true when an opening H1 or H2 tag is found
   * @var bool
   */
  private $capture = false;
  /**
   * The data of a H1 or H2 tag
   * @var string
   */
  private $hdata = null;
  /**
   * The attributes of the current header tag
   * @var array
   */
  private $hattr = array();
  /**
   * The GText object
   * @var GTextCacheable
   */
  private $gtext = null;
	/**
	 * Default font sizes
	 * @var array
	 */
  private $fontSizes = array(
  	'h1' => 32,
  	'h2' => 24,
  	'h3' => 18,
  	'h4' => 16
  );
  /**
   * If set only header tags with this class will be parsed
   * @var string
   */
  private $classRule = null;
  /**
   * Header tags with this class name won't be parsed
   * @var string
   */
  private $notClassRule = null;
  /**
   * Only parse header tags inside of this rule.
   * This could be something like
   * <code>
   *   <div class='main-content'>
   * </code>
   * @var string
   */
  private $insideOf = null;
  /**
   * Style attributes dedicated to GTextDTR
   * @var array
   */
  private $forbiddenStyles = array('background-image', 'height', 'width');
  /**
   * Keeps count of the current tag depth
   * @var int
   */
  private $depth = 0;
  /**
   * Should me begin parsing header tags.
   * This only has mening when $insideOf is set
   * @var bool
   */
  private $doParse = true;
  /**
   * When $insideOf is used we need to keep track of when to stop, that is when
   * we reach "outsideOf" again.
   * @var int
   */
  private $stopAtLevel = 0;

  /**
   * Constructor
   *
   * @param GText $gtext
   */
  public function __construct(GText $gtext)
  {
    parent::__construct();
    $this->gtext  = $gtext;
  }

  /**
   * Set font size for a given header level
   *
   * @param string $tag
   *   h1, h2, h3 or h4
   * @param int $size
   */
  public function SetFontSize($tag, $size)
  {
  	$level = strtolower($level);
  	if (preg_match('/h[1-4]/', $level))
  		$this->fontSizes[$level] = (int)$size;
  }

  /**
   * Set all fontsizes at once.
   *
   * @param array $sizes
   *  Associative array where the keys should be h1-h4 and the values
   *  should be of integer values.
   */
  public function SetFontSizes(array $sizes)
  {
  	$this->fontSizes = $sizes;
  }

  /**
   * If you don't want to replace all header tags you can set a class rule which
   * means that only header tags with this css class applied to it will be
   * parsed and replaced.
   *
   * @param string $classname
   */
  public function HasClass($classname)
  {
  	$this->classRule = $classname;
  }

  /**
   * The inverse of {@see GTextDTR::HasClass()}.
   *
   * @param string $classname
   */
  public function NotHasClass($classname)
  {
  	$this->notClassRule = $classname;
  }

  /**
   * Set this to only parse header tags inside of this element.
   * NOTE! This will only work properly on valid XHTML.
   *
   * <code>
   *   $gtextDTR->InsideOf("<div class='main-content'>");
   * </code>
   *
   * @param string $what
   */
  public function InsideOf($what)
  {
  	$this->insideOf = $what;
  }

  /**
   * Overrides the Parse method in {@see HTMLParser}. Collects the current
   * buffer and calls the parent method.
   *
   * @return string
   */
  public function Parse()
  {
  	$content = ob_get_clean();

  	if ($this->insideOf)
  		$this->doParse = false;

  	parent::Parse($content);
  	return $this->GetContents();
  }

  /**
   * Creates the text images as well as the new H1 or H2 tag with the text
   * wrapped in a span tag and CSS attributes written to the header tags.
   *
   * @param string $text
   * @param int $level
   * @return string
   */
  private function getGTextURL($tag, array $attr)
  {
  	$text = $this->hdata;
    $this->gtext->fontsize = $this->fontSizes[$tag];
    $img = $this->gtext->Render($text);
    $src = basename($img['path']);
    $style = '';
    if ($attr)
			list($attr, $style) = $this->parseAttr($attr);
		else
			$attr = '';

    return
    "<$tag style='{$style}background-image:url($src);" .
    "width:{$img['width']}px;" .
    "height:{$img['height']}px;'$attr><span style='display:none;'>" .
    "$text</span></$tag>";
  }

  /**
   * Turn the attribute array into a string and filter "style" attributes
   * if existing.
   *
   * @param array $attr
   * @return string
   */
  private function parseAttr(array $attr)
  {
		$str = '';
		$style = '';
		foreach ($attr as $key => $val) {
			if ($key == 'style') {
				$rules = explode(';', $val);
				$tmp = '';
				foreach ($rules as $rule) {
					list($name, $style) = explode(':', $rule);
					$name = trim($name);
					if (in_array($name, $this->forbiddenStyles))
						continue;
					$style = trim($style);
					$tmp .= "$name:$style;";
				}
				$style = $tmp;
			}
			else
				$str .= " $key='" . addslashes($val) . "'";
		}

		return array($str, $style);
  }

  /**
   * Tag callback. Will be called for every tag that's found in the buffer
   *
   * @param HTMLParser $p
   * @param string $tag
   * @param array $attr
   */
  protected function tagCallback(HTMLParser $p, $tag, $attr)
  {
  	if ($this->Context() == 'tag')
  		$this->depth++;

  	if ($this->doParse) {
	    switch ($tag) {
	      case 'h1':
	      case 'h2':
	      case 'h3':
	      case 'h4':
	      	if (!($this->classRule || $this->notClassRule) ||
	      	   ($this->classRule && $this->_hasClass($attr, $this->classRule)) ||
	      	   ($this->notClassRule && !$this->_hasClass($attr,
	      	                                             $this->notClassRule)))
	      	{
		        $this->capture = true;
	  	      $this->hattr = $attr;
	      	}
	      	else
	      		$this->buffer .= $this->Tag();
	        break;

	      case '/h1':
	      case '/h2':
	      case '/h3':
	      case '/h4':
	      	if ($this->capture) {
		        $this->buffer .= $this->getGTextURL(substr($tag, 1), $this->hattr);
		        $this->hattr   = array();
		        $this->hdata   = null;
		        $this->capture = false;
	      	}
	      	else
	      		$this->buffer .= $this->Tag();
	        break;

	      default:
	      	if ($this->stopAtLevel > 0 && $this->depth == $this->stopAtLevel) {
	      		$this->doParse = false;
	      		$this->stopAtLevel = 0;
	      	}
	        $this->buffer .= $this->Tag();
	    }
  	}
  	else {
  		if ($this->Tag() == $this->insideOf) {
  			$this->stopAtLevel = $this->depth;
  			$this->doParse = true;
  		}
  		$this->buffer .= $this->Tag();
  	}

		if ($this->Context() == 'endtag')
  		$this->depth--;
  }

  /**
   * Check if the tag has a class matching the class rule
   *
   * @param array $attr
   * @return bool
   */
  private function _hasClass(array $attr, $class)
  {
		if (isset($attr['class']))
			if (preg_match("/\b{$class}\b/i", $attr['class']))
				return true;

		return false;
  }

  /**
   * Data callback. Called when ever tag content is found
   *
   * @param HTMLParser $p
   * @param string $data
   */
  protected function dataCallback(HTMLParser $p, $data)
  {
    if ($this->capture)
      $this->hdata = $data;
    else
      $this->buffer .= $data;
  }

  /**
   * Returns the parsed buffer
   *
   * @return string
   */
  public function GetContents()
  {
    return $this->buffer;
  }

  /**
   * Destructor. Cleans our internal buffer
   */
  public function __destruct()
  {
    $this->buffer = null;
    $this->gtext  = null;
  }
}
?>