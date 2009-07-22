<?php
/**
 * XML validator
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @package XML
 */

/**
 * Validate an XML document
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package XML
 */
class XMLValidator
{
  public static $validator = null;
  /**
   * Parser stack
   * @var array
   */
	private $_openStack = array();
  /**
   * Data to validate
   * @var string
   */
	private $data;
  /**
   * SAX parser
   * @var resource
   */
	private $parser;
  /**
   * Error from SAX parser
   * @var resource
   */
	private $error;
  /**
   * Error description
   * @var string
   */
	private $errstr;
  /**
   * Error line
   * @var int
   */
	private $errline;
  /**
   * Error column
   * @var int
   */
	private $errcol;
  /**
   * Error tag
   * @var string
   */
	private $errtag;

  /**
   * Creates a new XMLValidator object
   *
   * @param string $data
   *  XML tree
   */
	public function __construct($data)
	{
		$this->data   = $data;
		$this->parser = xml_parser_create();

		xml_set_object($this->parser, &$this);
		xml_set_element_handler($this->parser, 'startTag', 'endTag');
		xml_parse($this->parser, $this->data);

    // It's an assignment
		if ($err = xml_get_error_code($this->parser)) {
			$this->error   = $err;
			$this->errstr  = xml_error_string($err);
			$this->errline = xml_get_current_line_number($this->parser);
			$this->errcol  = xml_get_current_column_number($this->parser);
			$this->errtag  = $this->_openStack[sizeof($this->_openStack)-1];
		}
		xml_parser_free($this->parser);
	}

	/**
	 * Validate the XML
	 *
	 * @param string $xml
	 * @return XMLValidator
	*/
	public static function Validate($xml)
	{
    $v = new self($xml);
    self::$validator = $v;
    return !$v->IsError();
	}

  /**
   * Returns a formatted error string
   * @return string
   */
	public function GetError()
	{
		return sprintf("XML Error: %s in line %d column %d. Tag &lt;%s&gt; " .
		               "isn't closed correctly!",
		               $this->errstr, $this->errline,
		               $this->errcol, strtolower($this->errtag));
	}

  /**
   * Returns the error description
   *
   * @return string
   */
  public function ErrStr()
  {
    return $this->errstr;
  }

  /**
   * Returns the line on which the error occured
   * 
   * @return int
   */
  public function ErrLine()
  {
    return $this->errline;
  }

  /**
   * Returns the column in which the error occured
   *
   * @return int
   */
  public function ErrCol()
  {
    return $this->errcol;
  }

  /**
   * Returns the name of the tag where the error occured
   * 
   * @return string
   */
  public function ErrTag()
  {
    return $this->errtag;
  }

  /**
   * Did an error occur or not?
   *
   * @return bool
   */
	public function IsError()
	{
		return $this->error ? true : false;
	}

  /**
   * Callback for start tags
   *
   * @param resource $parser
   * @param string $tag
   * @param array $attr
   */
	private function startTag($parser, $tag, $attr)
	{
		array_push($this->_openStack, $tag);
	}

  /**
   * Callback for end tags
   * @param resource $parser
   * @param string $tag
   */
	private function endTag($parser, $tag)
	{
		array_pop($this->_openStack);
	}
}
?>