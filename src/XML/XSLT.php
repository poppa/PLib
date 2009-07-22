<?php
/**
 * XSLT functions and classes
 *
 * @author  Pontus Östlund <pontus@poppa.se>
 * @license GPL License 2
 * @version 1.0
 * @package XML
 * @subpackage XSL
 */

/**
 * XSLTransform
 * A wrapper for creating an XSLT processor.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package XML
 * @subpackage XSL
 * @version 1.0
 * @example XSLTransform.xmpl
 */
class XSLTransform
{
	/**
	 * Constructor
	 *
	 * @throws XSLTransformException
	 * @param string $xsl
	 *   The path to the XSL file
	 * @param bool $setParams
	 *   If true some default XSLT params will be set. The following PHP
	 *   variables will be set and given a "namespace":
	 *
	 *     * $_SERVER will be popultated as $server.key
	 *     * $_REQUEST will be popultated as $form.key
	 *
	 *   So if $setParams is true you can for instance grab a querystring
	 *   variable named "name" in the XSL template with:
	 *
	 *     `<xsl:value-of select="$form.name" />`
	 *
	 *   or the current script file name with:
	 *
	 *     `<xsl:value-of select="$server.php_self" />`
	 *
	 *   The array keys in the PHP arrays will be lower cased when set in the
	 *   XSL template.
	 */
	public function __construct($xsl, $setParams=false)
	{
		if (!file_exists($xsl))
			throw new XSLTransformException("The file [$xsl] does not exists");

		$this->xslt = new XSLTProcessor();

		set_error_handler(array('XSLTransformException', 'ErrorHandler'));
		$xdoc = new DOMDocument();
		$xdoc->resolveExternals = true;
		$xdoc->substituteEntities = true;
		$xdoc->load($xsl);
		$this->xslt->importStyleSheet($xdoc);

		if ($setParams) {
			foreach ($_REQUEST as $key => $val)
				$this->SetParam(strtolower("form.$key"), $val);

			foreach ($_SERVER as $key => $val)
				if (!is_array($val) && !is_object($val))
					$this->SetParam(strtolower("server.$key"), $val);
		}

		restore_error_handler();
	}

	/**
	 * Set an XSL param in the stylesheet
	 *
	 * @param string $name
	 *   The XSL param name
	 * @param string|int $value
	 *   The XSL param value
	 * @param string $ns
	 *   The XSL namespace
	 * @return void
	 */
	public function SetParam($name, $value, $ns='')
	{
		$this->xslt->setParameter($ns, $name, $value);
	}

	/**
	 * Wrapper for {@link XSLTransform::SetParam()} so that we can pass an array
	 * with key/value pairs to set all params at once.
	 *
	 * @param array $params
	 */
	public function SetParams($params)
	{
		foreach ($params as $key => $val)
			$this->setParam($key, $val);
	}

	/**
	 * Get the actual XSLTProcessor object
	 *
	 * @return XSLTProcessor
	 */
	public function GetObject()
	{
		return $this->xslt;
	}

	/**
	 * Register PHP functions. {@see XSLTProcessor::registerPHPFunctions()}
	 *
	 * @param mixed $args
	 * 	Either a function name as a string or an array of function names
	 */
	public function RegisterPHPFunctions($args=null)
	{
		$this->xslt->registerPHPFunctions($args);
	}

	/**
	 * Do the transformation!
	 *
	 * @throws XSLTransformException
	 * @param  string|DOMDocument|XMLDocument|XMLNode $xml
	 *  Either a path to an XML document, a string reprsentation of an XML tree
	 *  or a {@link DOMDocument::__construct() DOMDocument} object or an
   *  instance of PLib's {@link XMLDocument} or {@link XMLNode}.
	 * @return mixed string|boolean
	 */
	public function Transform($xml)
	{
		set_error_handler(array('XSLTransformException', 'ErrorHandler'));

		if (is_string($xml)) {
			$xmld = $xml;
			$xml = new DOMDocument();
			$xml->substituteEntities = true;
			$xml->preserveWhiteSpace = false;

			if (file_exists($xmld))
				$xml->load($xmld);
			else
				$xml->loadXML($xmld);
		}
    elseif ($xml instanceof XMLNode)
      $xml = $xml->DomDoc();

		$res = $this->xslt->transformToXml($xml);

		restore_error_handler();

		return $res;
	}
}

/**
 * XSL transform exception class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package XML
 * @subpackage Exception
 */
class XSLTransformException extends Exception
{
	public $message;

	/**
	 * Handle error callbacks when used through
	 * {@link set_error_handler()}.
	 *
	 * <code>
	 * set_error_handler(array('XSLTransformException', 'ErrorHandler'), E_ALL);
	 * doSomeStuff();
	 * restore_error_handler();
	 * </code>
	 *
	 * @param int $code
	 * @param string $string
	 * @param string $file
	 * @param int $line
	 * @param mixed $context
	 */
	public static function ErrorHandler($code, $string, $file, $line, $context)
	{
		$e = new self($string, $code);
		$e->line = $line;
		$e->file = $file;
		throw $e;
	}
}
?>