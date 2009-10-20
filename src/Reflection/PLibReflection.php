<?php
/**
 * PLibReflection is mainly used to generate the short documentation of
 * the PLib library but the classes can be used to Reflect any object, member,
 * method and so on.
 *
 * @todo
 * * Need to implement support for constants and class constants.
 * * Implement support for @inheritdoc in docblocks.
 * * If a module with more than one class has shared method names the anchor
 *   links to the methods will always lead to the first occuring method.
 *   Thus the method anchors needs to be made unique. The same goes for
 *   class members.
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 * @uses DocblockParser
 * @uses XMLDocument
 */

//! Import required classes

/**
 * Load the docblock parser
 */
require_once PLIB_INSTALL_DIR . '/Parser/Docblock.php';
/**
 * Load the XML builder
 */
require_once PLIB_INSTALL_DIR . '/XML/XMLBuilder.php';

/**
 * An abstract meta class that the other PLibReflection* classes inherit.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
abstract class PLibReflection
{
	/**
	 * The name of the instance (class name, method name, param name...)
	 * @var string
	 */
	protected $name;
	/**
	 * Docblock object
	 * @var DocblockParser
	 */
	protected $docblock;
	/**
	 * The internal reflection object of the instance
	 * @var Reflection
	 */
	protected $reflectionObject;
	/**
	 * Where we keep errors and/or warnings
	 * @var array
	 */
	protected $errwarn = array();
	/**
	 * Where we can inject arbitrary info
	 * @var array
	 */
	protected $arbitrary = array();
  /**
   * Parent instance
   * @var PLibReflection
   */
  protected $parent = null;
	/**
	 * Base path to calulate file paths from.
	 * @see PLibReflection::BasePath()
	 * @staticvar string
	 */
	protected static $basepath = null;

	/**
	 * Hidden constructor.
	 */
	private function __construct(){}

  /**
   * Escapes control chars
   *
   * @param string $s
   * @return string
   */
  public static function EscapeString($s)
  {
    $f = array("\n","\t","\r","\0","\b","\v","\c","\a",'"');
    $t = array("\\n","\\t","\\r","\\0","\\b","\\v","\\c","\\a",'\"');
    return str_replace($f, $t, $s);
  }

	/**
	 * Creates an {@link DocblockParser} instance
	 *
	 * @param string $block
	 * @return DocblockParser
	 */
	protected function _docBlock($block)
	{
		$parser = new DocblockParser();
		$parser->Parse($block);
		return $parser;
	}

	/**
	 * Magic PHP getter (see
	 * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get($index)
	{
		if (isset($this->{$index}))
			return $this->{$index};

		return false;
	}

	/**
	 * Add info to the $arbitrary array
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function Inject($key, $value)
	{
		$this->arbitrary[$key] = $value;
	}

	/**
	 * Returns the reflection object of the instance
	 *
	 * @return Reflection
	 */
	public function ReflectionObject()
	{
		return $this->reflectionObject;
	}

	/**
	 * Method to retreive a memeber from the docblock object
	 *
	 * @param string $index
	 * @return mixed|bool
	 */
	public function Docblock($index=null)
	{
		if (!$index)
			return $this->docblock;

		if (isset($this->docblock->{$index}))
			return $this->docblock->{$index};

		return false;
	}

	/**
	 * Set/get the base path. The base path will be stripped off of the file path
	 * whenever appropriate.
	 *
	 * @param string $path
	 * @return string|void
	 */
	public static function BasePath($path=null)
	{
		if (!$path)
			return self::$basepath;

		if (!file_exists($path) && !is_dir($path)) {
			throw new Exception("Path \"$path\" given to PLibReflection::BasePath() ".
			                    "doesn't exist!");
		}

		self::$basepath = $path;
	}

	/**
	 * Returns the name of this instance
	 *
	 * @return string
	 */
	public function Name()
	{
		return $this->name;
	}

	/**
	 * Returns the description for the current instance if set in the
	 * docblock object
	 *
	 * @return string
	 */
	public function Description()
	{
		return $this->docblock->description;
	}

	/**
	 * Returns the version of the current instance if set in the
	 * docblock object
	 *
	 * @return string
	 */
	public function Version()
	{
		return $this->docblock->version;
	}

	/**
	 * Returns the author|s ofr the current instance if set in the
	 * docblock object
	 *
	 * @return string|array
	 */
	public function Author()
	{
		return $this->docblock->author;
	}

  /**
   * Returns the parent object
   * 
   * @return PLibReflection
   */
  public function Parent()
  {
    return $this->parent;
  }

	/**
	 * Converts the {@link DocblockParser} object to an XML tree
	 *
	 * @param XMLNode $node
	 * @return void
	 */
	protected final function docblockToXml(XMLNode &$node)
	{
		if ($this->docblock) {
			$db = $node->AddNode('docblock');
			foreach ($this->docblock->DocblockData() as $key => $val) {
				// Complex arrays for params, return and so on
				if (is_array($val)) {
					$nodeName   = null;
					switch ($key) {
						default:
							$this->addShallowArray($val, $key, $db);
							break;

						case 'param':
							$this->addDeepArray($val, 'params', 'param', $db);
							break;

						case 'uses':
							$this->addDeepArray($val, 'uses', 'use', $db);
							break;

						case 'usedby':
							$this->addDeepArray($val, 'usedby', 'used', $db);
							break;

						case 'depends':
							$this->addDeepArray($val, 'depends', 'depend', $db);
							break;

						case 'link':
						case 'author':
            case 'example':
						case 'copyright':
							foreach ($val as $i => $v)
								$db->AddNodeAsCDATA($key, safe_xml($v));

							break;
					}
				}
				else
					$db->AddCDATANode($key, utf8_encode($val));
			}
		}
	}

  /**
   * Add an array of docbloc tags that has no parent node
   *
   * <code>
   *   <example>FeedReader.xmpl</example>
   *   <example>FeedWriter.xmpl</example>
   * </code>
   *
   * @param array $elements
   * @param string $nodeName
   * @param XMLNode $node
   */
  protected function addShallowArray($elements, $nodeName, XMLNode &$node)
  {
    $n = $node->AddNode($nodeName);
    foreach ($elements as $k => $v) {
      if (is_array($v)) {
        $tn = $n->AddNode('types');
        foreach ($v as $type)
          $tn->AddCDATANode('type', utf8_encode($type));
      }
      else
        $n->AddCDATANode($k, utf8_encode($v));
    }
  }

  /**
   * Add an array of docbloc tags that has a parent node
   *
   * <code>
   *   <params>
   *     <param>Some param</param>
   *     <param>Some other param</param>
   *   </param>
   * </code>
   *
   * @param array $elements
   * @param string $parentNodeName
   *  Given the example above this would be "params"
   * @param string $nodeName
   *  Given the example above this would be "param"
   * @param XMLNode $node
   */
  protected function addDeepArray($elements, $parentNodeName, $nodeName,
			                            XMLNode &$node)
  {
    $pn = $node->AddNode($parentNodeName);
    foreach ($elements as $element) {
      $n = $pn->AddNode($nodeName);
      foreach ($element as $k => $v) {
        if (is_array($v)) {
          $tn = $n->AddNode('types');
          foreach ($v as $type)
            $tn->AddCDATANode('type', utf8_encode($type));
        }
        else
          $n->AddCDATANode($k, utf8_encode($v));
      }
    }
  }

	/**
	 * Converts the {@link PLibReflection::$arbitrary} array to XML
	 *
	 * @param XMLNode $node
	 */
	protected final function arbitraryToXml(XMLNode &$node)
	{
		foreach ($this->arbitrary as $key => $val)
			$node->AddNode($key, safe_xml($val));
	}

	/**
	 * Convert the object into an XML tree
	 * @return string
	 */
	abstract public function Xml();

	/**
	 * A PHP "magic" method. Gives the opportunity to create an abritray string
	 * of the object. (see
	 * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
	 *
	 * @return string
	 */
	abstract public function __toString();
}

/**
 * This class represents a file. Since the Reflection classes don't have any
 * methods for grabbing top level information we do it manually here.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
class PLibReflectionModule extends PLibReflection
{
	/**
	 * The path to the file
	 * @var string
	 */
	protected $file;
	/**
	 * Container for all classes in this file
	 * @var array
	 */
	protected $classes = array();
	/**
	 * Container for all functions in this file
	 * @var array
	 */
	protected $functions = array();
  /**
   * Container for included files
   * @var array
   */
  protected $includes = array();
  /**
   * Container for constants
   * @var array
   */
  protected $constants = array();

	/**
	 * Constructor
	 * @throws Exception
	 * @param string $file
	 */
	public function __construct($file)
	{
		if (!file_exists($file))
			throw new Exception("The file \"$file\" doesn't exist!");

		$this->file = $file;
		$this->parse();
	}

	/**
	 * Parse the file. Look for all classes and functions in the current file.
	 *
	 * A note on the function scanning:
	 * If class methods is defined with `public, protected, private, ...` the
	 * regexp will skip these functions. But if a class method is just is
	 * declared like `function method()` it will be caught by the functions
	 * regexp. Now, to clean up the pattern match result we loop over the
	 * {@link PLibReflectionModule::$classes} array and check if any of the
	 * class methods exist in the functions array. If they do they get popped
	 * out, {@link ADT::PopValue()}, of the functions array and we end up with
	 * a functions array with only `functions`
	 *
	 * @return void
	 */
	private function parse()
	{
		PLib::wdebug("BGREEN:Scanning:NONE: $this->file");
		$fc = file_get_contents($this->file);
		$re = '/\n[ \t]*
			(?:static|abstract)?[ \r\n\t]*
			(class|interface)[ \r\n\t]+
			([_a-z0-9]+)
			(?:
				[ \r\n\t]+
				(?:extends|implements)
				[ \r\n\t]+
				(?:[_a-z0-9]+)
			)?
			(?:
				[ \r\n\t]+
				(?:extends|implements)
				[ \r\n\t]+
				(?:[_a-z0-9]+)
			)?
			(?:[ \r\n\t]*\{)
		/imx';

		// The class names will, hopefully, be in index 2.
		preg_match_all($re, $fc, $m);

		$this->docblock = $this->_docBlock(DocblockParser::GetTopLevelBlock($fc));
		$this->name     = basename($this->file);

		if (sizeof($m[2])) {
			$len = sizeof($m[2]);
			PLib::wdebug(
				"  BLUE:Found \"%d\" class%s", $len, ($len > 1 ? 'es' : '')
			);
			foreach ($m[2] as $className) {
				try {
					$this->classes[] = new PLibReflectionClass(
						new ReflectionClass($className), $this
					);
				}
				catch (ReflectionException $e) {
					$this->errwarn[] = $e;
					PLib::wdebug(
						"  BPURPLE:WARNING:NONE: Couldn't create PLibReflectionClass!\n " .
						"          RED:" . $e->getMessage()
					);
				}
			}
		}
		else PLib::wdebug("  GRAY:Found no classes");

		// Pick out functions
		$re = '/\n[ \t]*
			(function)[ \r\n\t]+
			([_a-z0-9]+)
			(?:[ \r\n\t]*\()
		/imx';

		if (preg_match_all($re, $fc, $m)) {
			$len = sizeof($m[2]);
			PLib::wdebug(
				"  BLUE:Found \"%d\" function%s", $len, ($len > 1 ? 's' : '')
			);
			foreach ($this->classes as $cls)
				foreach ($cls->methods as $method)
					if (in_array($method->name, $m[2]))
						ADT::PopValue(&$m[2], $method->name);

			$len = sizeof($m[2]);
			PLib::wdebug(
				"  BLUE:Keeping \"%d\" function%s", $len, ($len>1||$len==0?'s':'')
			);

			if (sizeof($m[2])) {
				foreach ($m[2] as $funcname) {
					try {
						$this->functions[] = new PLibReflectionFunction(
							new ReflectionFunction($funcname)
						);
					}
					catch (ReflectionException $e) {
            $this->errwarn[] = $e;
						PLib::wdebug(
							"  BPURPLE:WARNING:NONE: Couldn't create PLibReflectionFunction!".
							"\n           RED:" . $e->getMessage()
						);
					}
				}
			}
		}
		else
			PLib::wdebug("  GRAY:Found no functions");
/*
    // Pick out includes
    // This is not bullet proof. It requires that there's nothing before the
    // require_once statement.
    $re = "/^require_once\(?\s*(.*)\s*\)?\s*;/im";

    if (preg_match_all($re, $fc, $m)) {
      $len = sizeof($m[1]);
      PLib::wdebug(
        "  BLUE:Found \"%d\" include%s", $len, ($len > 1 ? 's' : '')
      );

      if (sizeof($m[1])) {
        foreach ($m[1] as $inc) {
          eval("\$inceval=$inc;");
          $this->includes[] = array(
            'raw' => $inc,
            'module' => PLib::PathToNamespace($inceval)
          );
        }
      }
    }
    else
      PLib::wdebug("  GRAY:Found no includes");
*/
    // Pick out constants
  }

	/**
	 * Add a class to the classes array
	 *
	 * @param PLibReflectionClass $class
	 */
	public function AddClass(PLibReflectionClass $class)
	{
		$this->classes[] = $class;
	}

	/**
	 * Add a function to the functions array
	 *
	 * @param PLibReflectionFunction $class
	 */
	public function AddFunction(PLibReflectionFunction $func)
	{
		$this->functions[] = $func;
	}

  /**
   * Returns the internal file path, relative from PLib's root
   * 
   * @return string
   */
  public function GetFile()
  {
    return $this->file;
  }

	/**
	 * {@inheritdoc}
	 */
	public function Xml()
	{
		$xdoc = new XMLDocument();
		$xroot = $xdoc->AddNode('module');
		$xroot->AddNode('name', $this->name);
		$xroot->AddNode('path', $this->file);
		$this->arbitraryToXml($xroot);
		$this->docblockToXml($xroot);

		foreach ($this->classes as $class) {
			$x = $class->Xml();
			if ($x)
				$xroot->AddDomNode($x->firstChild);
		}

		foreach ($this->functions as $func) {
			$x = $func->Xml();
			if ($x)
				$xroot->AddDomNode($x->firstChild);
		}

		return $xdoc->DomDoc();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->name;
	}
}

/**
 * Abstract meta class for {@link PLibReflectionFunction} and
 * {@link PLibReflectionMethod}
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
abstract class AbstractPLibReflectionFunction extends PLibReflection
{
	/**
	 * Container for the params to the method
	 * @var array
	 */
	protected $params = array();

	/**
	 * Add a param to the params array
	 *
	 * @param PLibReflectionParam $param
	 */
	public function AddParam(PLibReflectionParam $param)
	{
		$this->params[] = $param;
	}

	/**
	 * Turns the params array into a readable string
	 *
	 * @return string
	 */
	public function ParamsToString()
	{
		$req = array();
		$opt = array();

		foreach ($this->params as $param) {
			if ($param->optional)
				$opt[] = (string)$param;
			else
				$req[] = (string)$param;
		}

		$str = implode(', ', $req);
		if (sizeof($opt)) {
			$optstr = '';
			foreach ($opt as $p) $optstr .= " [, $p";
			if (!strlen($str))
				$optstr = "[" . substr($optstr, 4);

			$str .= $optstr . str_repeat(']', sizeof($opt));
		}
		return $str;
	}

	/**
	 * Converts the params to an XML tree and appends that to node $n
	 *
	 * @param XMLNode $n
	 */
	protected function paramsToXml(XMLNode &$n)
	{
		$p = $n->AddNode('params');
		foreach ($this->params as $param) {
			$x = $param->Xml();
			if ($x)
				$p->AddDomNode($x->firstChild);
		}
	}
}

/**
 * This class represents a {@link ReflectionFunction}
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
class PLibReflectionFunction extends AbstractPLibReflectionFunction
{
	/**
	 * Is the function internal (built-in) or user defined
	 * @var bool
	 */
	protected $internal;
	/**
	 * Does the function return a reference or not?
	 * @var bool
	 */
	protected $reference;

	/**
	 * Constrcutor
	 *
	 * @param ReflectionFunction $ref
	 */
	public function __construct(ReflectionFunction $ref)
	{
		$this->reflectionObject = $ref;
		$this->parse($ref);
	}

	/**
	 * Pull out info from the Reflection object and populate this object
	 *
	 * @param ReflectionFunction $func
	 */
	protected function parse(ReflectionFunction $func)
	{
		$this->name      = $func->getName();
		$this->docblock  = $this->_docBlock($func->getDocComment());
		$this->internal  = $func->isInternal();
		$this->reference = $func->returnsReference();

		foreach ($func->getParameters() as $param)
			$this->params[] = new PLibReflectionParam($param);
	}

	/**
	 * {@inheritdoc}
	 */
	public function Xml()
	{
		$xdoc = new XMLDocument();
		$xroot = $xdoc->AddNode('function');
		$xroot->AddNode('name',      $this->name);
		$xroot->AddNode('internal',  $this->internal);
		$xroot->AddNode('reference', $this->reference);
		$xroot->AddNode('string',    (string)$this);
		$this->docblockToXml($xroot);
		$this->paramsToXml($xroot);

		return $xdoc->DomDoc();
	}
	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function __toString()
	{
		return sprintf(
			'%s%s(%s)',
			$this->reference ? '&amp;' : '',
			$this->name,
			$this->ParamsToString()
		);
	}
}

/**
 * Represents a reflection class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
class PLibReflectionClass extends PLibReflection
{
	/**
	 * Type of class, either class or interface
	 * @var string
	 */
	protected $type;
	/**
	 * Is the class abstract or not
	 * @var bool
	 */
	protected $abstract;
	/**
	 * Is the class final or not
	 * @var bool
	 */
	protected $final;
	/**
	 * Name of the class this class extends, if any
	 * @var string
	 */
	protected $extends;
	/**
	 * Is the class instantiable or not
	 * @var bool
	 */
	protected $instantiable;
	/**
	 * Container for interfaces this class implements, if any
	 * @var array
	 */
	protected $ifaces = array();
	/**
	 * Container for class constants
	 * @var array
	*/
	protected $constants = array();
	/**
	 * Container for class members
	 * @var array
	 */
	protected $properties = array();
	/**
	 * Container for class methods
	 * @var array
	 */
	protected $methods = array();

	/**
	 * Constructor.
	 *
	 * @param ReflectionClass $class
	 */
	public function __construct(ReflectionClass $class,
                              PLibReflection $parent=null)
	{
		$this->reflectionObject = $class;
    $this->parent = $parent;
		$this->parse($class);
	}

	/**
	 * Parses the reflection class and populates this object
	 *
	 * @param ReflectionClass $class
	 */
	protected function parse(ReflectionClass $class)
	{
		$this->name         = $class->getName();
		$this->type         = $class->isInterface() ? 'interface' : 'class';
		$this->abstract     = $class->isAbstract();
		$this->final        = $class->isFinal();
		$this->instantiable = $class->isInstantiable();
		$this->docblock     = $this->_docBlock($class->getDocComment());

    // It's an assignment
		if ($parent = $class->getParentClass())
			$this->extends = $parent->getName();

    // It's an assigment
		if ($ifaces = $class->getInterfaces()) {
			$ifs = array();
			foreach ($ifaces as $iface)
				$this->ifaces[] = $iface->getName();
		}

		$this->docblock = $this->_docBlock($class->getDocComment());

		foreach ($class->getMethods() as $method)
			$this->methods[] = new PLibReflectionMethod($method, $this->name);

		foreach($class->getProperties() as $prop)
			$this->properties[] = new PLibReflectionProperty($prop);

    DocblockParser::GetClassConstBLock($this->name, $this->parent->file);

     /*
    foreach ($class->getConstants() as $const => $val) {
      $c = new PLibReflectionConstant($const, $val, $this);
      $this->constants[] = $c;
    }
    */
	}

	/**
	 * Add a method to the methods array
	 *
	 * @param PLibReflectionMethod $method
	 */
	public function AddMethod(PLibReflectionMethod $method)
	{
		$this->methods[] = $method;
	}

	/**
	 * Add a memeber to the members array
	 *
	 * @param PLibReflectionProperty $prop
	 */
	public function AddPropery(PLibReflectionProperty $prop)
	{
		$this->properties[] = $member;
	}

	/**
	 * {@inheritdoc}
	 */
	public function Xml()
	{
		$xdoc = new XMLDocument();
		$xroot = $xdoc->AddNode('class');
		$xroot->AddNode('name',         $this->name);
		$xroot->AddNode('type',         $this->type);
		$xroot->AddNode('abstract',     $this->abstract);
		$xroot->AddNode('final',        $this->final);
		$xroot->AddNode('instantiable', $this->instantiable);
		$xroot->AddNode('extends',      $this->extends);
		$xroot->AddNode('string',       (string)$this);
		$xiface = $xroot->AddNode('implements');

		foreach ($this->ifaces as $iface)
			$xiface->AddNode('interface', $iface);

		$this->docblockToXml($xroot);

		$xmeth = $xroot->AddNode('methods');
		foreach ($this->methods as $method) {
			$x = $method->Xml();
			if ($x)
				$xmeth->AddDomNode($x->firstChild);
		}

		$xprop = $xroot->AddNode('properties');
		foreach ($this->properties as $prop) {
			$x = $prop->Xml();
			if ($x) $xprop->AddDomNode($x->firstChild);
		}

		return $xdoc->DomDoc();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function __toString()
	{
		$str = sprintf(
			"%s%s%s%s%s%s%s%s",
			$this->instantiable ? ''          : '[',
			$this->abstract     ? 'abstract ' : '',
			$this->final        ? 'final '    : '',
			$this->type . ' ',
			$this->name,
			$this->extends ? ' extends ' . $this->extends : '',
			$this->ifaces  ? ' implements ' . implode(', ', $this->ifaces) : '',
			$this->instantiable ? '' : ']'
		);

		return $str;
	}
}

/**
 * Represents a reflection class method
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
class PLibReflectionMethod extends AbstractPLibReflectionFunction
{
	/**
	 * The name of the class this method exists in.
	 * @var string
	 */
	protected $className;
	/**
	 * Is the method a constructor
	 * @var bool
	 */
	protected $constructor;
	/**
	 * Is the method a destructor
	 * @var bool
	 */
	protected $destructor;
	/**
	 * Visibility of the method: private, protected, public
	 * @var string
	 */
	protected $visibility;
	/**
	 * Is the method abstract
	 * @var bool
	 */
	protected $abstract;
	/**
	 * Is the method final or not
	 * @var bool
	 */
	protected $final;
	/**
	 * Is the method static or not
	 * @var bool
	 */
	protected $static;
	/**
	 * Does this method return a reference
	 * @var bool
	 */
	protected $reference;
	/**
	 * The class in which this method is declared
	 * @var bool
	 */
	protected $declaringClass;
	/**
	 * Is the method inherited or not
	 * @var bool
	 */
	protected $inherited;

	/**
	 * Constrcutor
	 *
	 * @param ReflectionMethod $method
	 * @param string $className
	 */
	public function __construct(ReflectionMethod $method, $className=null)
	{
		$this->className = $className;
		$this->reflectionObject = $method;
		$this->parse($method);
	}

	/**
	 * Pull out info from the Reflection object and populate this object
	 *
	 * @param ReflectionMethod $method
	 * @param string $className
	 */
	protected function parse(ReflectionMethod $method, $className=null)
	{
		$this->docblock    = $this->_docBlock($method->getDocComment());
		$this->name        = $method->getName();
		$this->abstract    = $method->isAbstract();
		$this->constructor = $method->isConstructor();
		$this->destructor  = $method->isDestructor();
		$this->reference   = $method->returnsReference();
		$this->visibility  = !$method->isPrivate() ?
		                        $method->isPublic() ? 'public' : 'protected' :
		                     'private' ;

		$this->final          = $method->isFinal();
		$this->static         = $method->isStatic();
		$this->declaringClass = $method->getDeclaringClass();
		$this->inherited = $this->declaringClass->getName() == $this->className ?
		                   false : true;

		foreach ($method->getParameters() as $param)
			$this->params[] = new PLibReflectionParam($param);
	}

	/**
	 * {@inheritdoc}
	 */
	public function Xml()
	{
		$xdoc = new XMLDocument();
		$xroot = $xdoc->AddNode('method');
		$xroot->AddNode('name',            $this->name);
		$xroot->AddNode('constructor',     $this->constructor);
		$xroot->AddNode('destructor',      $this->destructor);
		$xroot->AddNode('abstract',        $this->abstract);
		$xroot->AddNode('visibility',      $this->visibility);
		$xroot->AddNode('final',           $this->final);
		$xroot->AddNode('reference',       $this->reference);
		$xroot->AddNode('static',          $this->static);
		$xroot->AddNode('declaring-class', $this->declaringClass->getName());
		$xroot->AddNode('inherited',       $this->inherited);
		$xroot->AddNode('string',          (string)$this);

		$this->docblockToXml($xroot);
		$this->paramsToXml($xroot);

		return $xdoc->DomDoc();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function __toString()
	{
		$dec = null;
		if ($this->inherited)
			$dec = ' inherited from ' . $this->declaringClass->getName() . '';

		$str = sprintf(
			"%s%s%s%s%s(%s)%s",
			$this->abstract ? 'abstract ' : '',
			$this->visibility . ' ',
			$this->final  ? 'final '  : '',
			$this->static ? 'static ' : '',
			$this->name,
			$this->ParamsToString(),
			$dec
		);
		return $str;
	}
}

class PLibReflectionConstant extends PLibReflection
{
  protected $name = null;
  protected $value = null;
  protected $type = null;

  public function __construct($name, $value,
                              PLibReflectionClass $parent)
  {
    $this->name = $name;
    $this->value = $value;
    $this->parent = $parent;

    $this->resolvType();

    trace($this->__toString() . ' (' . $this->type . ")\n");
  }

  protected function resolvType()
  {
    if (preg_match('/^[-+]?\d+$/', $this->value))
      $this->type = 'int';
    elseif (preg_match('/^[-+]?\d+\.\d+$/', $this->value))
      $this->type = 'float';
    elseif (preg_match('/^array/i', $this->value))
      $this->type = 'array';
    elseif (preg_match('/^(?!\d)/i', $this->value))
      $this->type = 'string';
    else
      $this->type = 'unknown';
  }

  public function Value()
  {
    return $this->value;
  }

  public function Xml()
  {
    return null;
  }

  public function __toString()
  {
    $rc = "const $this->name = ";
    if ($this->type == 'string')
      $rc .= '"' . PLibReflection::EscapeString($this->value) . '"';
    else
      $rc .= $this->value;

    return $rc;
  }
}

/**
 * Represents a reflection class member
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
class PLibReflectionProperty extends PLibReflection
{
  /**
   * Default property value
   * @var string
   */
	protected $defaultValue;
  /**
   * Has a default value or not
   * @var bool
   */
	protected $isDefault;
  /**
   * Is the property static or not?
   * @var bool
   */
	protected $isStatic;
  /**
   * Is the property private or not?
   * @var bool
   */
	protected $isPrivate;
  /**
   * Is the property protected or not?
   * @var bool
   */
	protected $isProtected;
  /**
   * Is the property public or not?
   * @var bool
   */
	protected $isPublic;
  /**
   * Datatype of the property
   * @var string
   */
	protected $datatype;

  /**
   * Constructor
   *
   * @param ReflectionProperty $prop
   */
	public function __construct(ReflectionProperty $prop)
	{
		$this->reflectionObject = $prop;
		$this->parse($prop);
	}

  /**
   * Parse the property
   * 
   * @param ReflectionProperty $p
   */
	protected function parse(ReflectionProperty $p)
	{
		$this->name = $p->getName();
		$this->docblock = $this->_docBlock($p->getDocComment());

		try { $this->defaultValue = $p->getValue(new stdClass()); }
		catch (Exception $e) {}

		if (is_array($this->defaultValue)) {
			$this->datatype = 'array';
			$this->defaultValue = var_export($this->defaultValue, 1);
		}
		elseif (is_string($this->defaultValue)) {
			$this->datatype = 'string';
		}
		elseif (is_int($this->defaultValue)) {
			$this->datatype = 'int';
		}
		elseif (is_float($this->defaultValue)) {
			$this->datatype = 'float';
		}
		elseif (is_object($this->datatype)) {
			$this->datatype = 'object';
			$this->defaultValue = null;
		}
		elseif (is_null($this->defaultValue)) {
			$this->datatype = 'null';
		}
		elseif (is_bool($this->defaultValue)) {
			$this->datatype = 'bool';
		}
		else {
			$this->defaultValue = null;
			$this->datatype = 'unknown';
		}

		$this->isDefault    = $p->isDefault();
		$this->isPrivate    = $p->isPrivate();
		$this->isProtected  = $p->isProtected();
		$this->isPublic     = $p->isPublic();
		$this->isStatic     = $p->isStatic();
	}

	/**
	 * Returns the "visibility of the property, i.e. private, protected or
	 * public
	 *
	 * @return string
	 *  private|protected|public
	 */
	public function GetVisibility()
	{
		if ($this->isPrivate)
			return 'private';
		if ($this->isProtected)
			return 'protected';
		if ($this->isPublic)
			return 'public';
	}

	/**
	 * {@inheritdoc}
	 */
	public function Xml()
	{
		$xdoc = new XMLDocument();
		$xroot = $xdoc->AddNode('property');
		$xroot->AddNode('name',       $this->name);
		$xroot->AddCDATANode('value',      ($this->defaultValue));
		$xroot->AddNode('default',    $this->isDefault);
		$xroot->AddNode('visibility', $this->GetVisibility());
		$xroot->AddNode('static',     $this->isStatic);
		$xroot->AddNode('type',       $this->datatype);
		$xroot->AddNode('string',     (string)$this);

		$this->docblockToXml($xroot);

		return $xdoc->DomDoc();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function __toString()
	{
		return sprintf(
			'%s %s%s$%s',
			$this->GetVisibility(),
			$this->isStatic ? 'static ' : '',
			$this->defaultValue ? "$this->datatype " : null,
			$this->name
		);
	}
}

/**
 * Represents a reflection class method argument
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Reflection
 * @subpackage PLibReflection
 */
class PLibReflectionParam extends PLibReflection
{
	/**
	 * The default value of the param
	 * @var string
	 */
	protected $defaultValue;
	/**
	 * Is the param passed as reference
	 * @var bool
	 */
	protected $reference;
	/**
	 * Is the param an array?
	 * @var bool
	 */
	protected $array;
	/**
	 * Is the param optional
	 * @var bool
	 */
	protected $optional;
	/**
	 * Does the param need to be an instance of some kind
	 * @var string
	 */
	protected $class;
	/**
	 * Can the param be null
	 * @var bool
	 */
	protected $nullable;

	/**
	 * Constructor
	 *
	 * @param ReflectionParameter $param
	 */
	public function __construct(ReflectionParameter $param)
	{
		$this->reflectionObject = $param;
		$this->parse($param);
	}

	/**
	 * Parse the reflection object and populate this object
	 *
	 * @param ReflectionParameter $param
	 */
	protected function parse(ReflectionParameter $param)
	{
		$this->name = $param->getName();
		$this->optional = $param->isOptional();
		$this->defaultValue =
			$param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;

		$class = $param->getClass();
		$this->{'class'} = $class ? $class->getName() : null;
		$this->nullable = $param->allowsNull();
	}

	/**
	 * {@inheritdoc}
	 */
	public function Xml()
	{
		$xdoc = new XMLDocument();
		$xroot = $xdoc->AddNode('param');
		$xroot->AddNode('name',      $this->name);
		$xroot->AddNode('value',     $this->defaultValue);
		$xroot->AddNode('reference', $this->reference);
		$xroot->AddNode('optional',  $this->optional);
		$xroot->AddNode('class',     $this->{'class'});
		$xroot->AddNode('array',     $this->array);
		$xroot->AddNode('nullable',  $this->nullable);
		$xroot->AddNode('string',    (string)$this);

		return $xdoc->DomDoc();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function __toString()
	{
		$str = sprintf(
			"%s%s%s%s%s",
			$this->array ? 'Array ' : '',
			$this->{'class'} ? $this->{'class'} . ' ' : '',
			$this->reference ? '&' : '',
			"\$$this->name",
			$this->defaultValue ? '=' . $this->defaultValue : ''
		);
		return $str;
	}
}
?>