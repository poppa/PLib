<?php
/**
 * Classes for reading and creating web feeds such as RSS, RDF and ATOM.
 *
 * <code>
 *   // Handles RSS, RDF and Atom feeds
 *   $feed = Feed::Parse($some_feed_as_xml);
 *   $channel = $feed->GetChannel();
 *   $items   = $feed->GetItems();
 *
 *   echo '<h1>' . $channel->GetTitle() . '</h1>';
 *   echo '<p><strong>' . $channel->GetContent() . '</strong></p>';
 *   echo '<hr/>';
 *
 *   echo '<ul>';
 *
 *   foreach ($items as $item) {
 *     echo '<li><p><strong>' . $item->GetTitle() . '</strong><br/>' .
 *          '<span>' . $item->GetDate() . '</span></p>' .
 *          $item->GetContent() . '<hr/>';
 *   }
 * </code>
 *
 * The methods @see{AbstrctThing::GetTitle()},
 * @see{AbstractThing::GetContent()} and @see{AbstractThing::GetDate()} are
 * generic for all types of feeds and will return the corresponding elements.
 * To retreive an arbitrary element call the method
 * @see{AbstractThing::GetElement()}.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Web
 * @uses Date
 * @uses HTTPRequest
 * @uses XMLDocument
 * @example RSSReader.xmpl
 * @example RSSWriter.xmpl
 */

/**
 * We need the {@see Date} class
 */
require_once PLIB_INSTALL_DIR . '/Calendar/Date.php';
/**
 * We might need the {@see HTTPRequest} class
 */
require_once PLIB_INSTALL_DIR . '/Protocols/Net.php';
/**
 * We need the {@see XMLDocument} class when creating feeds
 */
require_once PLIB_INSTALL_DIR . '/XML/XMLBuilder.php';

/**
 * Type constant for {@see AbstractThing}
 */
define('FEED_TYPE_ABSTRACT_THING', 1);
/**
 * Type constant for {@see AbstractChannel}
 */
define('FEED_TYPE_ABSTRACT_CHANNEL', 2);
/**
 * Type constant for {@see AbstractItem}
 */
define('FEED_TYPE_ABSTRACT_ITEM', 3);
/**
 * Type constant for {@see AbstractFeed}
 */
define('FEED_TYPE_ABSTRACT_FEED', 4);
/**
 * Type constant for {@see RssFeed}
 */
define('FEED_TYPE_RSS_FEED', 5);
/**
 * Type constant for {@see RssChannel}
 */
define('FEED_TYPE_RSS_CHANNEL', 6);
/**
 * Type constant for {@see RssItem}
 */
define('FEED_TYPE_RSS_ITEM', 7);
/**
 * Type constant for {@see RdfFeed}
 */
define('FEED_TYPE_RDF_FEED', 8);
/**
 * Type constant for {@see RdfChannel}
 */
define('FEED_TYPE_RDF_CHANNEL', 9);
/**
 * Type constant for {@see RdfItem}
 */
define('FEED_TYPE_RDF_ITEM', 10);
/**
 * Type constant for {@see AtomFeed}
 */
define('FEED_TYPE_ATOM_FEED', 11);
/**
 * Type constant for {@see AtomChannel}
 */
define('FEED_TYPE_ATOM_CHANNEL', 12);
/**
 * Type constant for {@see AtomItem}
 */
define('FEED_TYPE_ATOM_ITEM', 13);

/**
 * Name constant for {@see AbstractThing}
 */
define('FEED_NAME_THING', 'Thing');
/**
 * Name constant for {@see AbstractChannel}
 */
define('FEED_NAME_CHANNEL', 'Channel');
/**
 * Name constant for {@see AbstractItem}
 */
define('FEED_NAME_ITEM', 'Item');
/**
 * Name constant for {@see AbstractFeed}
 */
define('FEED_NAME_FEED', 'Feed');
/**
 * Name constant for {@see RssFeed}
 */
define('FEED_NAME_RSS', 'Rss');
/**
 * Name constant for {@see RdfFeed}
 */
define('FEED_NAME_RDF', 'Rdf');
/**
 * Name constant for {@see AtomFeed}
 */
define('FEED_NAME_ATOM', 'Atom');

/**
 * Abstract class representing a "thing" in a feed. It could be a channel
 * or an item...
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
abstract class AbstractThing
{
  /**
   * Type of thing
   * @var int
   */
	protected $TYPE = FEED_TYPE_ABSTRACT_THING;
  /**
   * Class name
   * @var string
   */
	protected $NAME = FEED_NAME_THING;
	/**
	 * Required elements. Not used when parsing, just when buildning
	 * @var array
	 */
	protected $required = array();
	/**
	 * Node names that should be tried for handling callback function.
	 * @var array
	 */
	protected $subnodes = array();
	/**
	 * Data container. An associative array where "key" will be the node name
	 * and "value" the value of the node.
	 * @var array
	 */
	protected $data = array();
  /**
   * Node attributes, if any
   * @var array
   */
  protected $attributes = array();
	/**
	 * The DOMElement node
	 * @var DOMElement
	 */
	protected $xml;
  /**
   * The name of the root node
   * @var string
   */
  protected $nodeName = 'abstract-thing';

	/**
	 * Constructor
	 *
	 * @param DOMElement $node
	 */
	protected function __construct(DOMElement $node=null)
	{
		if ($node) {
			$this->xml = $node;
			$this->parse();
		}
	}

	/**
	 * Parse the DOMElement node.
	 */
	protected final function parse()
	{
		$check = !!sizeof($this->subnodes);
		$name  = null;
		$i     = 0;

		while ($n = $this->xml->childNodes->item($i++)) {
      // It's an assigment
			if (($name = $n->nodeName) && in_array($name, $this->subnodes)||!$check) {
        // It's an assigment
				if (($pos = $this->isNSNode($name)) !== false)
					$name = substr($name, 0, $pos);

				if (method_exists($this, "parse_$name"))
					$this->{"parse_$name"}($n);
				else
					$this->parseDefault($n);
			}
		}
	}

	/**
	 * Checks if $name is a namespace node, i.e. contains a ":".
	 *
	 * @param string $name
	 * @return int|bool
	 */
	protected final function isNSNode($name)
	{
		return strpos($name, ':');
	}

	/**
	 * Default handler for adding a node to the data array.
	 *
	 * @param DOMElement $node
	 */
	protected final function parseDefault(DOMElement $node)
	{
		$nn = $node->nodeName;
		if (isset($this->data[$nn])) {
			if (!is_array($this->data[$nn]))
				$this->data[$nn] = array($this->data[$nn]);

			$this->data[$nn][] = $node->nodeValue;
		}
		else
			$this->data[$nn] = $node->nodeValue;
	}

	/**
	 * Returns the content of node $n with tags and everything except the main
	 * node it self. It's like "element.innerHTML" in JavaScript.
	 *
	 * @param DOMElement $n
	 * @return string
	 */
	protected function innerHTML(DOMElement $n)
	{
		$str = '';
		foreach ($n->childNodes as $c)
			$str .= $c->ownerDocument->saveXML($c);

		return $str;
	}

	/**
	 * Returns the data array
	 *
	 * @return array
	 */
	public function GetData()
	{
		return $this->data;
	}

	/**
	 * Returns the element $which from the data array.
	 *
	 * @param string $which
	 * @return mixed
	 */
	public function GetElement($which)
	{
		return issetor($this->data[$which], false);
	}

	/**
	 * Should return the date part of the thing.
	 * @return Date
	 */
	abstract public function GetDate();

	/**
	 * Should return the title of the thing
	 * @return string
	 */
	abstract public function GetTitle();

	/**
	 * Should return the content part of the thing
   *
	 * @return string
	 */
	abstract public function GetContent();

  /**
   * Add an arbitrary key/value pair to the {@link AbstractThing::$data} array.
   *
   * @param string $name
   * @param mixed $value
   */
  public function SetData($name, $value)
  {
    $this->data[$name] = $value;
  }

  /**
   * Set root node attributes
   *
   * @param array $attributes
   */
  public function SetAttributes(array $attributes)
  {
    $this->attributes = $attributes;
  }

  /**
   * Set a specific root attribute
   *
   * @param string $key
   * @param string $value
   */
  public function SetAttribute($key, $value)
  {
    $this->attributes[$key] = $value;
  }

  /**
   * Add an abritrary element
   *
   * @param string $name
   * @param string|array $value
   * @param array $attr
   */
  public function SetElement($name, $value, $attr=array())
  {
    $this->data[$name] = $value;
  }

  /**
   * Cast to string
   *
   * @return string
   */
  public function __toString()
  {
    try {
      $xdoc = new XMLDocument();
      $root = $xdoc->AddNode($this->nodeName, null, $this->attributes);

      foreach ($this->data as $key => $val) {
        if (is_array($val)) {
          $n = $root->AddNode($key);
          foreach ($val as $k => $v)
            $n->AddNode($k, safe_xml($v));
        }
        else
          $root->AddNode($key, safe_xml($val));
      }

      return $xdoc->Render(0, 0);
    }
    catch (Exception $e) {
      return 0;
    }
  }
}

/**
 * Abstract representation of a feed channel.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
abstract class AbstractChannel extends AbstractThing
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_ABSTRACT_CHANNEL;
  /**
   * Class name
   * @var string
   */
	protected $NAME = FEED_NAME_CHANNEL;
  /**
   * Name of the last build date node
   * @var string
   */
  protected $lastBuildDateNode = 'lastBuildDate';

	/**
	 * The items of the channel
	 * @var array
	 */
	protected $items = array();

	/**
	 * Returns the array of items
	 *
	 * @return array
	 */
	public function GetItems()
	{
		return $this->items;
	}

	/**
	 * Adds an item to the items array
	 *
	 * @param AbstractItem $item
	 */
	public function AddItem(AbstractItem $item)
	{
		$this->items[] = $item;
	}

  /**
   * Tries to find the last build date
   *
   * @return Date
   */
  public function GetLastBuildDate()
  {
    if (!sizeof($this->items))
      return null;

    $item = $this->items[0];

    foreach (array('pubDate', 'dc:date', 'updated', 'published') as $d) {
      $el = null;
      // It's an assignment
      if ($el = $item->GetElement($d))
        return $el;
    }
  }

  /**
   * Set the title element
   *
   * @param string $value
   */
  abstract public function SetTitle($value);

  /**
   * Set the description element
   *
   * @param string $value
   */
  abstract public function SetDescription($value);

  /**
   * Set the link element
   *
   * @param string $value
   */
  abstract public function SetLink($value);

  /**
   * Cast to string
   *
   * @return string
   */
  public function __toString()
  {
    try {
      $xdoc = new XMLDocument();
      $root = $xdoc->AddNode($this->nodeName, null, $this->attributes);

      foreach ($this->data as $key => $val) {
        if (is_array($val)) {
          $n = $root->AddNode($key);
          foreach ($val as $k => $v)
            $n->AddNode($k, safe_xml($v));
        }
        else
          $root->AddNode($key, safe_xml($val));
      }

      $root->AddNode($this->lastBuildDateNode,
                     safe_xml($this->GetLastBuildDate()));

      foreach ($this->items as $item) {
        $root->AddNodeTree(((string)$item));
      }

      return $xdoc->Render(0, 1);
    }
    catch (Exception $e) {
      return 0;
    }
  }
}

/**
 * Abstract representation of a feed item
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
abstract class AbstractItem extends AbstractThing
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_ABSTRACT_ITEM;
  /**
   * Class name
   * @var string
   */
	protected $NAME = FEED_NAME_ITEM;
  /**
   * Node name
   * @var string
   */
  protected $nodeName = 'abstract-item';

  /**
   * Set the title element
   *
   * @param string $value
   */
  abstract public function SetTitle($value);

  /**
   * Set the description element
   *
   * @param string $value
   */
  abstract public function SetDescription($value);

  /**
   * Set the link element
   *
   * @param string $value
   */
  abstract public function SetLink($value);

  /**
   * Set the date element
   *
   * @param string|Date $value
   */
  abstract public function SetDate($value);
}

/**
 * Abstract main class representing a feed like Rss, Atom, Rdf and what not.
 *
 * @author Pontus Östlund
 * @package Web
 * @subpackage Feed
 */
abstract class Feed
{
  /**
   * Default date format for date nodes
   * @var string
   */
  public static $DateFormat = '%a, %d %b %Y %T %z';
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_ABSTRACT_FEED;
  /**
   * Class name
   * @var string
   */
	protected $NAME = FEED_NAME_FEED;
	/**
	 * DOMelement of this object
	 * @var DOMElement
	 */
	protected $xml = null;
	/**
	 * The channel object
	 * @var AbstractChannel
	 */
	protected $channel = null;
  /**
   * The name of the root node
   * @var string
   */
  protected $nodeName = null;
  /**
   * Root node attributes
   * @var array
   */
  protected $attributes = array();
  /**
   * Namespaces
   * @var array
   */
  protected $ns = array();
  /**
   * Output encoding
   * @var string
   */
  protected $encoding = 'utf-8';

  /**
   * Factory method for creating an empty Feed.
   *
   * @param string $version
   * @param string $enc
   * @param array $ns
   */
  abstract static public function Create($version=null, $enc='utf-8',
                                         array $ns=array());

	/**
	 * Creates a feed object from an XML feed.
	 *
	 * @throws Exception
	 *  If the XML can't be parsed
	 * @param string $xml
	 * @return Feed
	 *   Can etither be a {@see RssFeed}, {@see AtomFeed} or {@see RdfFeed}
	 */
	public static final function Parse($xml)
	{
		if (!is_utf8($xml))
			$xml = utf8_encode($xml);

		$map = array('feed', 'rss', 'rdf');
		$doc = null;

		try {
			$doc = new DOMDocument();
			$doc->preserveWhiteSpace = false;
			$doc->loadXML($xml);
		}
		catch (Exception $e) {
			throw $e;
		}

		if (!$doc) throw new Exception('Couldn\'t load XML');

		$feed = null;
		$name = null;
		$i    = 0;

		while ($node = $doc->childNodes->item($i++)) {
      // It's a double assignment
			if (($name = $node->nodeName) && ($pos = strpos($name, ':')) !== false)
				$name = substr($name, 0, $pos);

			if (in_array($name, $map)) {
				$feed = $name;
				break;
			}
		}

		switch ($feed)
		{
			case 'rss':  return new RssFeed($node);
			case 'rdf':  return new RdfFeed($node);
			case 'feed': return new AtomFeed($node);
		}

		return 0;
	}

	/**
	 * Fetches the feed from $url and returns a *feed object.
	 *
	 * @throws HTTPRequestException
   * @throws HTTPResponseException
   * @throws HTTPMaxRedirectException
	 * @param string $url
   * @param int $cache
   *  Cache the request for $cache number of seconds
	 * @return Feed
	 */
	public static final function ParseURL($url, $cache=600)
	{
		static $cli;

		if (!$cli) {
			$cli = new HTTPRequest();
			//! Cache for ten minutes
			$cli->Cache($cache);
		}

		$resp = null;
		try { $resp = $cli->Get($url); }
		catch (Exception $e) {
			throw $e;
		}

    try {
      $rv = self::Parse((string)$resp);
      if (!$rv)
        throw new Exception("No object returned from Feed::Parse()");

      return $rv;
    }
    catch (Exception $e) {
      $cli->ClearCache();
      throw $e;
    }
	}

	/**
	 * Creates a new Feed object
	 *
	 * @param DOMElement $node
   * @param string $enc
   *  Output encoding
	 */
	protected function __construct(DOMNode $node=null)
	{
		if ($node)
			$this->xml = $node;
	}

	/**
	 * Returns the channel object
	 *
	 * @return AbstractChannel
	 */
	public function GetChannel()
	{
		if ($this->channel)
			return $this->channel;

		$class = $this->NAME . 'Channel';

		if (!class_exists($class))
			throw new Exception("$this->TYPE has no channel class");

		$this->channel = new $class($this->xml);
		return $this->channel;
	}

	/**
	 * Returns the items of the Feed object
	 *
	 * @return array
	 */
	public function GetItems()
	{
		if (!$this->channel)
			$this->GetChannel();

		return $this->channel->GetItems();
	}

  /**
   * Set the channel for the feed
   *
   * @param AbstractChannel $chnl
   * @return AbstractChannel
   */
  public function SetChannel(AbstractChannel $chnl)
  {
    $this->channel = $chnl;
    return $chnl;
  }

  /**
   * Render th object to XML
   * @param AbstractChannel $chnl
   * @param bool $addHeader
   *  If true an text/xml header will be output
   */
  public function Render(AbstractChannel $chnl, $addHeader=false)
  {
    $xdoc = new XMLDocument('1.0', $this->encoding);
    $root = $xdoc->AddNode($this->nodeName, null, $this->attributes+$this->ns);
    $root->AddNodeTree((string)$chnl);

    return $xdoc->Render(null, 1);
  }

  /**
   * Set root node attributes
   *
   * @param array $attributes
   */
  public function SetAttributes(array $attributes)
  {
    $this->attributes = $attributes;
  }

  /**
   * Set a specific root attribute
   *
   * @param string $key
   * @param string $value
   */
  public function SetAttribute($key, $value)
  {
    $this->attributes[$key] = $value;
  }

  /**
   * Set the output encoding
   *
   * @param string $enc
   */
  public function SetEncoding($enc)
  {
    $this->encoding = $enc;
  }

  /**
   * Add namespace
   * 
   * @param string $name
   * @param string $space
   */
  public function AddNamespace($name, $space)
  {
    $this->ns[$name] = $space;
  }

  /**
   * Cast to string
   *
   * @return string
   */
  public function __toString()
  {
    return $this->Render($this->channel);
  }
}

/**
 * A class for reading and creating Rss feeds
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class RssFeed extends Feed
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_RSS_FEED;
  /**
   * Class name
   * @var string
   */
	protected $NAME = FEED_NAME_RSS;
  /**
   * Node name
   * @var string
   */
  protected $nodeName = 'rss';

  /**
   * Creates a new RssFeed object
   *
   * @param string $version
   *  RSS version to use
   * @param string $encoding
   *  Output encoding
   * @param array $ns
   *  Namespaces
   *
   * @return RssFeed
   */
  public static function Create($version='2.0', $encoding='utf-8',
                                array $ns=array())
  {
    $rss = new self();
    $rss->SetAttributes($ns);
    if ($version)
      $rss->SetAttribute('version', $version);
    else
      $rss->SetAttribute('version', '2.0');

    $rss->SetEncoding($encoding);

    return $rss;
  }
}

/**
 * A class for reading and creating an Rss channel
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class RssChannel extends AbstractChannel
{
  /**
   * Class name
   * @var name
   */
	protected $TYPE = FEED_TYPE_RSS_CHANNEL;
	/**
	 * Subnodes to render.
	 * @var array
	 */
	protected $subnodes = array(
		'language','copyright','managingEditor','webMaster','pubDate',
		'lastBuildDate','category','generator','docs','cloud','ttl','image',
		'rating','textInput','skipHours','skipDays','title','item','description'
	);
	/**
	 * Required elements when buildning
	 * @var array
	 */
	protected $required = array('title', 'link', 'description');
  /**
   * Node name
   * @var string
   */
  protected $nodeName = 'channel';

	/**
	 * Creates a new RssChannel object
	 *
	 * @param DOMElement $node
	 */
	public function __construct(DOMElement $node=null)
	{
		if ($node) {
			foreach ($node->childNodes as $child) {
				if ($child->nodeName == 'channel') {
					parent::__construct($child);
					return;
				}
			}
			throw new Exception('Failed to find channel node for ' . $this->TYPE);
		}
		parent::__construct();
	}

	/**
	 * Returns the date part of the channel. "lastBuildDate" takes precedence
	 * over "pubDate".
	 *
	 * @return Date
	 */
	public function GetDate()
	{
		$date = null;
		if (isset($this->data['lastBuildDate']))
			$date = $this->data['lastBuildDate'];

		else if (isset($this->data['pubDate']))
			$date = $this->data['pubdate'];

		return new Date($date);
	}

	/**
	 * Returns the title.
	 *
	 * @return string
	 */
	public function GetTitle()
	{
		return issetor($this->data['title'], null);
	}

	/**
	 * Returns the description element.
	 *
	 * @return string
	 */
	public function GetContent()
	{
		return issetor($this->data['description'], null);
	}

  public function SetTitle($value)
  {
    $this->data['title'] = $value;
  }

  public function SetDescription($value)
  {
    $this->data['description'] = $value;
  }

  public function SetLink($value)
  {
    $this->data['link'] = $value;
  }

	/**
	 * Callback for the image node
	 *
	 * @param DOMElement $node
	 */
	protected function parse_image(DOMElement $node)
	{
		$this->data['image'] = array();
		$i = 0;
		while ($cn = $node->childNodes->item($i++))
			$this->data['image'][$cn->nodeName] = $cn->nodeValue;
	}

	/**
	 * Callback for the item node
	 *
	 * @param DOMElement $node
	 */
	protected function parse_item(DOMElement $node)
	{
		$this->items[] = new RSSItem($node);
	}
}

/**
 * A class for reading and crearting Rss items.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class RssItem extends AbstractItem
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_RSS_ITEM;
	/**
	 * Subnodes to parse
	 * @var array
	 */
	protected $subnodes = array(
		'title','link','description','author','category','comments','enclosure',
		'guid','pubDate','source',
		/*Extra elements*/
		'content','content:encoded','dc:date'
	);
  /**
   * Node name
   * @var string
   */
  protected $nodeName = 'item';

  /**
   * Creates a new RssItem object
   *
   * @param DOMElement $node
   */
  public function __construct(DOMElement $node=null)
  {
    parent::__construct($node);
  }

	/**
	 * Returns the date
	 *
	 * @return string
	 */
	public function GetDate()
	{
		$date = issetor($this->data['pubDate'], null);
		if (!$date)
			$date = issetor($this->data['dc:date'], null);

		return new Date($date);
	}

	/**
	 * Returns the title
	 *
	 * @return string
	 */
	public function GetTitle()
	{
		return issetor($this->data['title'], null);
	}

	/**
	 * Returns the content. Either the description, content or content:encoded
	 * element.
	 *
	 * @return string
	 */
	public function GetContent()
	{
		if (isset($this->data['description']))
			return $this->data['description'];

		if (isset($this->data['content']))
			return $this->data['content'];

		if (isset($this->data['content:encoded']))
			return $this->data['content:encoded'];

		return null;
	}

  /**
   * Set the title element
   *
   * @param string $value
   */
  public function SetTitle($value)
  {
    $this->data['title'] = $value;
  }

  /**
   * Set the description element
   *
   * @param string $value
   */
  public function SetDescription($value)
  {
    $this->data['description'] = $value;
  }

  /**
   * Set the link element
   *
   * @param string $value
   */
  public function SetLink($value)
  {
    $this->data['link'] = $value;
  }

  /**
   * Set the date element
   *
   * @param string|Date $value
   */
  public function SetDate($value)
  {
    if (is_string($value) || is_int($value))
      $value = new Date($value);

    $this->data['pubDate'] = $value->Format(Feed::$DateFormat);
  }

	/**
	 * Callback for category nodes
	 *
	 * @param DOMElement $node
	 */
	protected function parse_category(DOMElement $node)
	{
		if (!isset($this->data['category']))
			$this->data['category'] = array();

		$this->data['category'][] = $node->nodeValue;
	}

	/**
	 * Callback for the description node.
	 *
	 * @param DOMElement $n
	 */
	protected function parse_description(DOMElement  $n)
	{
		if ($n->firstChild && $n->firstChild->nodeType == XML_CDATA_SECTION_NODE)
			$this->data[$n->nodeName] = $n->nodeValue;
		else
			$this->data[$n->nodeName]  = parent::innerHTML($n);
	}

	/**
	 * Callback for the content node.
	 *
	 * @param DOMElement $n
	 */
	protected function parse_content(DOMElement $n)
	{
		$this->parse_description($n);
	}
}

/**
 * A class for reading and creating RDF feeds
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class RdfFeed extends RssFeed
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_RDF_FEED;
  /**
   * Class name
   * @var string
   */
	protected $NAME = FEED_NAME_RDF;

  /**
   * Creates a new RssFeed object
   *
   * @param string $version
   *  RSS version to use
   * @param string $encoding
   *  Output encoding
   * @param array $ns
   *  Namespaces
   *
   * @return RdfFeed
   */
  public static function Create($version='2.0', $encoding='utf-8',
                                array $ns=array())
  {
    $rss = new self();
    $rss->SetAttributes($ns);
    if ($version)
      $rss->SetAttribute('version', $version);
    else
      $rss->SetAttribute('version', '2.0');

    $rss->SetEncoding($encoding);

    return $rss;
  }
}

/**
 * A class for reading and creating an Rdf channel
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class RdfChannel extends AbstractChannel
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_RDF_CHANNEL;

	/**
	 * Subnodes to render.
	 * @var array
	 */
	protected $subnodes = array(
		'language','copyright','managingEditor','webMaster','pubDate',
		'lastBuildDate','category','generator','docs','cloud','ttl','image',
		'rating','textInput','skipHours','skipDays','title','item','description'
	);
	/**
	 * Required elements when buildning
	 * @var array
	 */
	protected $required = array('title', 'link', 'description');

	/**
	 * Creates a new Rdf channel object
	 *
	 * @param DOMElement $node
	 */
	public function __construct(DOMElement $node=null)
	{
		$i = 0;
    if ($node) {
      while ($n = $node->childNodes->item($i++)) {
        if ($n->nodeName == 'channel') {
          parent::__construct($n);
          break;
        }
      }
    }
		parent::__construct($node);
	}

	/**
	 * Returns the date part of the channel. "lastBuildDate" takes precedence
	 * over "pubDate".
	 *
	 * @return Date
	 */
	public function GetDate()
	{
		$date = null;

		if (isset($this->data['lastBuildDate']))
			$date = $this->data['lastBuildDate'];
		else if (isset($this->data['pubDate']))
			$date = $this->data['pubDate'];

		return new Date($date);
	}

	/**
	 * Returns the title.
	 *
	 * @return string
	 */
	public function GetTitle()
	{
		return issetor($this->data['title'], null);
	}

	/**
	 * Returns the description element.
	 *
	 * @return string
	 */
	public function GetContent()
	{
		return issetor($this->data['description'], null);
	}

  /**
   * Set the title
   *
   * @param string $value
   */
  public function SetTitle($value)
  {
    $this->data['title'] = $value;
  }

  /**
   * Set the desciption
   *
   * @param string $value
   */
  public function SetDescription($value)
  {
    $this->data['description'] = $value;
  }

  /**
   * Set the link
   *
   * @param string $value
   */
  public function SetLink($value)
  {
    $this->data['link'] = $value;
  }

	/**
	 * Callback for the item node
	 *
	 * @param DOMElement $node
	 */
	protected function parse_item(DOMElement $node)
	{
		$this->items[] = new RSSItem($node);
	}
}

/**
 * A class for reading and creating an Rdf item
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class RdfItem extends RssItem
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_RDF_ITEM;
}

/**
 * A class for reading and creating Atom feeds
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class AtomFeed extends Feed
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_ATOM_FEED;
  /**
   * Class name
   * @var string
   */
	protected $NAME = FEED_NAME_ATOM;

  /**
   * XML node name of this instance
   * @var string
   */
  protected $nodeName = 'feed';

  /**
   * Creates a new RssFeed object
   *
   * @param string $version
   *  RSS version to use
   * @param string $encoding
   *  Output encoding
   * @param array $ns
   *  Namespaces
   *
   * @return AtomFeed
   */
  public static function Create($version='2.0', $encoding='utf-8',
                                array $ns=array())
  {
    $rss = new self();
    $rss->SetAttributes($ns);
    $rss->SetEncoding($encoding);

    return $rss;
  }
}

/**
 * A class for reading and creating an Atom channel (or main node)
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class AtomChannel extends AbstractChannel
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_ATOM_CHANNEL;
	/**
	 * Subnodes to render.
	 * @var array
	 */
	protected $subnodes = array(
		'title','link','updated','published','author','id','generator','icon',
		'logo','rights','source','subtitle','summary','entry'
	);

	/**
	 * Creates a new AtomFeed object
	 *
	 * @param DOMElement $node
	 */
	public function __construct(DOMElement $node=null)
	{
		parent::__construct($node);
	}

	/**
	 * Parse the entry element.
	 *
	 * @param DOMElement $node
	 */
	protected function parse_entry(DOMElement $node)
	{
		$this->items[] = new AtomEntry($node);
	}

	/**
	 * Returns the date, either the updated or published node. The "updated" node
	 * takes precedense over "published".
	 *
	 * @return string
	 */
	public function GetDate()
	{
		$date = issetor($this->data['updated'], null);
		if (!$date)
			$date = issetor($this->data['published'], null);

		return $date ? new Date($date) : null;
	}

	/**
	 * Returns the title
	 *
	 * @return string
	 */
	public function GetTitle()
	{
		return issetor($this->data['title'], null);
	}

	/**
	 * Returns the content, the summary node
	 * element.
	 *
	 * @return string
	 */
	public function GetContent()
	{
		return issetor($this->data['summary'], null);
	}

  /**
   * Set the title
   *
   * @param string $value
   */
  public function SetTitle($value)
  {
    $this->data['title'] = $value;
  }

  /**
   * Set the description
   *
   * @param string $value
   */
  public function SetDescription($value)
  {
    $this->data['summary'] = $value;
  }

  /**
   * Set the link
   *
   * @param string $value
   */
  public function SetLink($value)
  {
    $this->data['link'] = $value;
  }
}

/**
 * A class for reading and creating an Atom entry
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Web
 * @subpackage Feed
 */
class AtomEntry extends AbstractItem
{
  /**
   * Class type
   * @var int
   */
	protected $TYPE = FEED_TYPE_ATOM_ITEM;

  /**
   * XML node name of this instance
   * @var string
   */
  protected $nodeName = 'entry';

  /**
   * Creates a new AtomEntry object
   *
   * @param DOMElement $node
   */
  public function __construct(DOMElement $node=null)
  {
    parent::__construct($node);
  }

	/**
	 * Returns the date, either the updated or published node. The "updated" node
	 * takes precedense over "published".
	 *
	 * @return string
	 */
	public function GetDate()
	{
		$date = issetor($this->data['updated'], null);
		if (!$date)
			$date = issetor($this->data['published'], null);

		return $date ? new Date($date) : null;
	}

	/**
	 * Returns the title
	 *
	 * @return string
	 */
	public function GetTitle()
	{
		return issetor($this->data['title'], null);
	}

	/**
	 * Returns the content, the summary node
	 * element.
	 *
	 * @return string
	 */
	public function GetContent()
	{
		$c = issetor($this->data['content'], null);
		if (!$c)
			$c = issetor($this->data['summary'], null);

		return $c;
	}

  /**
   * Set the title element
   *
   * @param string $value
   */
  public function SetTitle($value)
  {
    $this->data['title'] = $value;
  }

  /**
   * Set the description element
   *
   * @param string $value
   */
  public function SetDescription($value)
  {
    $this->data['summary'] = $value;
  }

  /**
   * Set the link element
   *
   * @param string $value
   */
  public function SetLink($value)
  {
    $this->data['link'] = $value;
  }

  /**
   * Set the date element
   *
   * @param string|Date $value
   */
  public function SetDate($value)
  {
    if (is_string($value) || is_int($value))
      $value = new Date($value);

    $this->data['published'] = $value->Format(Feed::$DateFormat);
  }

	/**
	 * Callback for the content node.
	 *
	 * @param DOMElement $n
	 */
	protected function parse_content(DOMElement $n)
	{
		if ($n->firstChild->nodeType == XML_CDATA_SECTION_NODE)
			$this->data[$n->nodeName] = $n->nodeValue;
		else
			$this->data[$n->nodeName] = parent::innerHTML($n);
	}

	/**
	 * Callback for an eventual summary node
	 *
	 * @param DOMElement $n
	 */
	protected function parse_summary(DOMElement $n)
	{
		$this->parse_content($n);
	}

  /**
   * Parse a category node
   * @param DOMElement $n
   */
	protected function parse_category(DOMElement $n)
	{
		if (!isset($this->data['category']))
			$this->data['category'] = array();

		$this->data['category'][] = $n->getAttribute('label');
	}

  protected function parse_link(DOMElement $n)
  {
    if (!isset($this->data['link']))
      $this->data['link'] = array();

    $tmp = array();

    foreach ($n->attributes as $an)
      $tmp[$an->nodeName] = $an->nodeValue;

    $this->data['link'][] = $tmp;
  }
}
?>