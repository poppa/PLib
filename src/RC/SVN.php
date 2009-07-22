<?php
/**
 * A set of classes to work with SVN repositories.
 *
 * __NOTE!__ Many of the SVN classes uses hidden members. In order to find out what
 * members is available for each class you will have to inspect the SVN XML
 * files associated with each class. Since the XML files can look a bit
 * different some members are created and set dynamically.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.2
 * @package Revision Control
 * @uses Date
 * @uses ADT
 * @uses XMLDocument
 */

/**
 * SVN.php version
 */
define('PLIB_SVN_VERSION', '0.2');

/**
 * Load the date class
 */
require_once PLIB_INSTALL_DIR . '/Calendar/Date.php';
/**
 * Load the ADT class
 */
require_once PLIB_INSTALL_DIR . '/ADT/ADT.php';
/**
 * Load the XML builder class
 */
require_once PLIB_INSTALL_DIR . '/XML/XMLBuilder.php';

/**
 * SVN main abstract class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Revision Control
 * @subpackage Subversion
 * @depends Date
 * @depends ADT
 */
abstract class SVN
{
	/**
	 * Base path to the SVN XML files
	 * @var $string
	 */
	protected static $BASE_PATH;
	/**
	 * The current revision
	 * @var int
	 */
	protected $revision = 0;
	/**
	 * Array for storing revision entries
	 * @var array
	 */
	protected $revisions = array();

# INTERNAL WORKINGS

	/**
	 * These members shall be converted into a {@link Date} object
	 * @var array
	 */
	protected $dateKeys = array('date', 'text-updated');
	/**
	 * Counter used when looping through the revisions array through
	 * {@link SVN::Next()}
	 * @var int
	 */
	private $counter = 0;
	/**
	 * The SAX parser object for parsing the SVN XML files
	 * @var resource
	 */
	protected $parser;

	/**
	 * Hidden constructor
	 */
	protected function __construct() {}

	/**
	 * Get the next item in the revisions array
	 *
	 * @return SVNEntry|bool
	 */
	public function Next()
	{
		$ret = false;
		if (isset($this->revisions[$this->counter]))
			$ret = $this->revisions[$this->counter++];

		return $ret;
	}

	/**
	 * Get the specified revision
	 *
	 * @param int $number
	 * @return SVNEntry|bool
	 */
	public function GetRevision($number)
	{
		if (isset($this->revisions[$number-1]))
			return $this->revisions[$number-1];

		return false;
	}
	/**
	 * Returns the revisions array
	 *
	 * @return array
	 */
	public function GetRevisions()
	{
		return $this->revisions;
	}

	/**
	 * Set the base path for where to look for the XML files
	 *
	 * <code>
	 * SVN::SetBase('/home/poppa/svn/app/');
	 * $log  = new SVNLog('logfile.xml');
	 * $info = new SVNInfo('infofile.xml');
	 * </code>
	 *
	 * @param string $path
	 */
	public static final function SetBase($path)
	{
		self::$BASE_PATH = rtrim($path, '/');
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
	 * Magic PHP setter (see
	 * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	protected function __set($key, $value)
	{
		if (in_array($key, $this->dateKeys))
			$value = new Date($value);

		$this->{$key} = $value;
	}

	/**
	 * Converts the object to a string
	 *
	 * @see PLib::__toString()
	 * @return string
	 */
	public function __toString()
	{
		if (class_exists('PLib'))
			return PLib::__toString($this);

		return get_class($this);
	}

	/**
	 * Returns the full path to the XML file passed to one of the constructors
	 *
	 * @throws SVNException
	 * @param string $path
	 * @return string
	 */
	protected function getPath($path)
	{
		if (self::$BASE_PATH)
			$path = self::$BASE_PATH . '/' . $path;

		if (!file_exists($path))
			throw new SVNException("The path \"$path\" doesn't exist!");

		return $path;
	}

	/**
	 * Creates a parser resource to use for parsing the XML files.
	 *
	 * The object calling this method must have the methods "tagCallback",
	 * "tagEndCallback" and "dataCallback" defined or else an exception will
	 * be thrown.
	 *
	 * @uses SVN::$parser
	 * @throws SVNException
	 * @param SVN $object
	 */
	protected function createParser(SVN $object)
	{
		$reqmeths = array('tagCallback', 'tagEndCallback', 'dataCallback');
		$missing = array();
		foreach ($reqmeths as $meth)
			if (!method_exists($this, $meth))
				$missing[] = $meth;

		if (!empty($missing)) {
			$s = sizeof($missing) > 1 ? 's' : '';
			throw new SVNException(
				"The method$s \"" . join(', ', $missing) . "\" must be implemented " .
				"in \"" . get_class($this) . "\"."
			);
		}

		$saxparser = xml_parser_create('');
		xml_parser_set_option($saxparser, XML_OPTION_SKIP_WHITE, 1);
		xml_set_object($saxparser, $object);
		xml_set_element_handler($saxparser, 'tagCallback', 'tagEndCallback');
		xml_set_character_data_handler($saxparser, "dataCallback");
		$this->parser = $saxparser;
		unset($saxparser);
	}

	/**
	 * Run the {@link SVN::$parser} parser.
	 *
	 * @param string $xml
	 */
	protected function parseXML($xml)
	{
		xml_parse($this->parser, $xml, true);
		xml_parser_free($this->parser);
		unset($this->parser);
	}
}

/**
 * Abstract meta class representing a commit entry
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Revision Control
 * @subpackage Subversion
 */
abstract class SVNEntry extends SVN
{
	/**
	 * The author of the commit
	 * @var string
	 */
	protected $author;
	/**
	 * The date of the commit
	 * @var string
	 */
	protected $date;

	protected function __construct() {}

	/**
	 * @see SVN::__get()
	 * @param string $key
	 * @return mixed
	 */
	public function __get($index)
	{
		if ($index == 'message')
			$index = 'msg';

		return parent::__get($index);
	}
}

/**
 * Represents an entry in a SVN log file
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Revision Control
 * @subpackage Subversion
 */
class SVNLogEntry extends SVNEntry
{
	/**
	 * The actual log message
	 * @var string
	 */
	private $message;

	/**
	 * Constructor
	 *
	 * @param int $revision
	 */
	public function __construct($revision)
	{
		$this->revision = $revision;
	}
}

/**
 * SVNLog parses a repository log file generated with the `--xml` flag, i.e:
 * `svn log --xml > mylog.xml`
 *
 * __Programmatical note!__<br/>
 * This class uses a SAX parser since these log files tend to become rather
 * extensive. The SAX parser uses way, way less memory than a DOM parser
 * for instance.
 *
 * * {@link http://en.wikipedia.org/wiki/SAX Basic description of SAX}
 * * {@link http://php.net/manual/en/function.xml-parser-create.php PHP SAX}
 * * {@link http://en.wikipedia.org/wiki/Document_Object_Model Basics of DOM}
 * * {@link http://php.net/manual/en/ref.dom.php PHP DOM}
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Revision Control
 * @subpackage Subversion
  */
class SVNLog extends SVN
{
	/**
	 * The SVN log file to parse
	 * @var string
	 */
	private $logfile = null;

# INERNAL WORKINGS

	/**
	 * Flag for telling if we're inside a LOGENTRY or not
	 * @var bool
	 */
	private $inEntry = false;
	/**
	 * The current revision we're in
	 * @var int
	 */
	private $currRev = null;
	/**
	 * Current node name
	 * @var string
	 */
	private $currIndex = null;
	/**
	 * If set only find this revision
	 * @var int
	 */
	private $getRevision = 0;
	/**
	 * Counter to track the current array index
	 * @var int
	 */
	private $i = 0;

	/**
	 * Constructor
	 *
	 * @throws SVNException
	 * @param string $file
	 *  The full path to the log file
	 */
	public function __construct($file, $revision=0)
	{
		$file = $this->getPath($file);
		$this->getRevision = $revision;
		$this->logfile = $file;
		$this->Parse($this->getRevision);
	}

	/**
	 * Parse the log file...
	 *
	 * @param int $revision
	 *  Only get this revision. 0 means get all revisions
	 */
	public function Parse($revision=0)
	{
		$this->createParser($this);
		$this->parseXML(file_get_contents($this->logfile));
	}

	/**
	 * Callback for opening XML tags
	 *
	 * @param resource $parser
	 * @param string $name
	 * @param array $attr
	 */
	protected function tagCallback($parser, $name, $attr)
	{
		if ($name == 'LOGENTRY') {
			$this->inEntry = true;
			$this->revisions[] = new SVNLogEntry($attr['REVISION']);
			$this->currRev = $this->i;
			$this->i++;
			return;
		}

		if ($this->inEntry)
			$this->currIndex = strtolower($name);
	}

	/**
	 * Callback for tag data
	 *
	 * @param resource $parser
	 * @param string $data
	 */
	protected function dataCallback($parser, $data)
	{
		if ($this->inEntry && $this->currIndex) {
			//! The SAX parser only seems to read 1024 bytes of character data at a
			//! time. Thus, when dealing with longer text, the cdata callback function
			//! will be called with the same XML tag multiple times until all data is
			//! fed.
			//!
			//! So if the same node is being parsed the data should be concatenated
			//! to the previous result.
			if (isset($this->revisions[$this->currRev]->{$this->currIndex}))
				$this->revisions[$this->currRev]->{$this->currIndex} .= trim($data);
			else
				$this->revisions[$this->currRev]->{$this->currIndex} = trim($data);
		}
	}

	/**
	 * Callback for closing tags
	 *
	 * @param resource $parser
	 * @param string $name
	 */
	protected function tagEndCallback($parser, $name)
	{
		$this->currIndex = null;
		if ($name == 'LOGENTRY') {
			$this->inEntry = false;
			$this->currRev = null;
		}
	}
}

/**
 * Parses an XML file generated through "svn info --xml" and turns that into
 * an object reprsentation of the XML file.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Revision Control
 * @subpackage Subversion
 */
class SVNInfo extends SVN
{
	/**
	 * The file to parse
	 * @var string
	 */
	protected $logfile;
	/**
	 * Kind of file (dir, file)
	 * @var string
	 */
	protected $kind;
	/**
	 * The internal repository path to the file given info about
	 * @var string
	 */
	protected $path;
	/**
	 * The URL to the file given info about.
	 * I.e http://server.com/svn/app/trunk/lib/File.ext
	 * @var string
	 */
	protected $url;
	/**
	 * The repository node. Contains "root" and "uuid".
	 * @var SVNMetaClass
	 */
	protected $repository;
	/**
	 * Commit info about the file given info about. Contains "revision",
	 * "author" and "date"
	 * @var SVNMetaClass
	 */
	protected $commit;

# INTERNAL WORKINGS

	/**
	 * The current node level the parser's at.
	 * @var int
	 */
	private $level = 0;
	/**
	 * The current node name
	 * @var string
	 */
	private $currentIndex = null;
	/**
	 * The current node attributes
	 * @var array
	 */
	private $currentAttr = array();
	/**
	 * The current SVNMetaClass object
	 * @var SVNMetaClass
	 */
	private $currentObject = null;

	/**
	 * Constructor
	 *
	 * @param string $logfile
	 *   The log file to parse
	 * @param string $path
	 */
	public function __construct($logfile, $path=null)
	{
		$this->logfile = $this->getPath($logfile);
		$this->parse();
	}

	/**
	 * Parse the XML file
	 */
	protected function parse()
	{
		$this->createParser($this);
		$this->parseXML(file_get_contents($this->logfile));
	}

	/**
	 * Tag callback.
	 *
	 * @see SVN::createParser()
	 * @param resource $parser
	 * @param string $tag
	 * @param array $attr
	 */
	protected function tagCallback($parser, $tag, $attr)
	{
		if ($tag == 'ENTRY') {
			$this->kind = $attr['KIND'];
			$this->path = $attr['PATH'];
			$this->revision = $attr['REVISION'];
		}

		$this->currentIndex = strtolower($tag);
		$this->currentAttr  = ADT::Mmap('strtolower', $attr, 0);
		$this->level++;
	}

	/**
	 * Data callback
	 *
	 * @link SVN::createParser()
	 * @param resource $parser
	 * @param string $data
	 */
	protected function dataCallback($parser, $data)
	{
		$data = trim($data);
		if ($this->level == 3 && strlen($data)) {
			if (sizeof($this->currentAttr)) {
				$this->{$this->currentIndex} =
					new SVNMetaClass($this->currentIndex, $this->currentAttr);
			}
			else
				$this->{$this->currentIndex} = $data;
		}
		else if ($this->level == 3 && !strlen($data)) {
			if (!$this->currentObject) {
				$this->currentObject =
					new SVNMetaClass($this->currentIndex, $this->currentAttr);
			}
		}
		elseif ($this->level == 4)
			$this->currentObject->{$this->currentIndex} = $data;
	}

	/**
	 * Closing tag callback
	 *
	 * @param resource $parser
	 * @param string $tag
	 */
	protected function tagEndCallback($parser, $tag)
	{
		if ($this->level == 3) {
			$this->{strtolower($tag)} = $this->currentObject;
			$this->currentObject = null;
			$this->currentIndex = null;
		}

		$this->level--;
	}
}

/**
 * Generic SVN meta class
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Revision Control
 * @subpackage Subversion
 */
class SVNMetaClass extends SVN
{
	/**
	 * The name of the class
	 * @var unknown_type
	 */
	protected $name;

	/**
	 * Constructor
	 *
	 * @param string $name
	 * @param array $attr
	 */
	public function __construct($name=null, $attr=null)
	{
		$this->name = $name;
		$this->Populate($attr);
	}

	/**
	 * Converts an assoc array into members of this object
	 *
	 * @param array $array
	 */
	public function Populate($array)
	{
		foreach ($array as $k => $v)
			$this->{$k} = $v;
	}

	/**
	 * Magic PHP method (see
	 * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
	 *
	 * @return string
	 */
	public function __toString()
	{
		return __CLASS__ . "($this->name)";
	}
}

/**
 * Generic SVN exception
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Revision Control
 * @subpackage Exception
*/
class SVNException extends Exception
{
	public $message;
}
?>