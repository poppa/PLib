<?php
/**
 * HTML parser
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Parser
 * @version 0.1
 */

/**
 * HTML parser class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Parser
 * @version 0.1
 * @todo Doesn't handle nested stuff. Need to make the parser recursive.
 */
class HTMLParser
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

	const TAG_START      = '<';
	const TAG_END        = '>';
	const TAG_FIN        = '/';
	const TAG_XML        = '/>';
	const TAG_COMMENT    = '--';
	const TAG_PREPROC    = '?';
	const TAG_DOCTYPE    = '!';
	const ENT_START      = '&';
	const ENT_END        = ';';
	const ATTR_STR1      = '\'';
	const ATTR_STR2      = '"';
	const ATTR_EQ        = '=';
	const WHITES         = " \t\n\r";
	const STOP_PARSE     = -1;

	private $stack       = array();
	private $stacklen    = 0;
	private $data        = null;
	private $length      = 0;
	private $tag         = null;
	private $tagname     = null;
	private $tagargs     = null;
	private $ctx         = null;
	private $byte        = 0;
	private $current     = null;

	//! Callbacks

	private $contCB      = array();
	private $tagCB       = array();
	private $entCB       = null;
	private $dataCB      = null;

	public function __construct() {}

	/**
	 * Add action to take place when tag $tag is encountered.
	 * The callback function will be called with the following arguments:
	 * <code>
	 * callback(HTMLParser $p, array $attributes)
	 * </code>
	 *
	 * @param string $tag
	 * @param string $cb
	 */
	public function AddTag($tag, $cb)
	{
		$this->tagCB[$tag] = $cb;
	}

	/**
	 * Add one ore more tag callbacks at once. The argument should
	 * be an associative array with the key/value should be tag/callback.
	 *
	 * @see HTMLParser::AddTag()
	 * @param array $cbs
	 */
	public function AddTags(array $cbs)
	{
		foreach ($cbs as $tag => $cb)
			$this->tagCB[$tag] = $cb;
	}

	/**
	 * Add action to take place when container tag $tag is encountered.
	 * The callback function will be called with the following arguments:
	 * <code>
	 * callback(HTMLParser $p, array $attributes, string $content)
	 * </code>
	 *
	 * @param string $tag
	 * @param string $cb
	 */
	public function AddContainer($tag, $cb)
	{
		$this->contCB[$tag] = $cb;
	}

	/**
	 * Add one ore more container tag callbacks at once. The argument should
	 * be an associative array with the key/value should be tag/callback.
	 *
	 * @see HTMLParser::AddContainer()
	 * @param array $cbs
	 */
	public function AddContainers(array $cbs)
	{
		foreach ($cbs as $tag => $cb)
			$this->contCB[$tag] = $cb;
	}

	/**
	 * Add action to take place when tag data is encountered.
	 * The callback function will be called with the following arguments:
	 *
	 * <code>
	 * callback(HTMLParser $p, string $content)
	 * </code>
	 *
	 * @param string $cb
	 */
	public function SetDataCallback($cb)
	{
		$this->dataCB = $cb;
	}

	/**
	 * Set callback for entities.
	 * The callback function will be called with the following arguments:
	 *
	 * <code>
	 * callback(HTMLParser $p, string $entity);
	 * </code>
	 *
	 * @param string $cb
	 */
	public function SetEntityCallback($cb)
	{
		$this->entCB = $cb;
	}

	/**
	 * Render the stack of parsed data. Note that {@see HTMLParser::Parse()}
	 * must be called prior to this method. Since {@see HTMLParser::Parse()}
	 * returns the HTMLParser object it self the call can be chained like:
	 *
	 * <code>
	 * $result = $parser->Parse()->Render();
	 * </code>
	 *
	 * @param bool $return
	 *   If true the each {@see HTMLElement} and result from eventual callbacks
	 *   will be added to a buffer which will then be returned. If you only want
	 *   to parse the data and have no interest in the original HTML you should
	 *   set this to false since that will both speed things up and keep the
	 *   memory consuption down.
	 * @return string|void
	 */
	public function Render($return=true)
	{
		$buf       = '';
		$hasTagCB  = sizeof($this->tagCB);
		$hasContCB = sizeof($this->contCB);
		$hasEntCB  = $this->entCB  != null;
		$hasDataCB = $this->dataCB != null;

		$hasCB = $hasTagCB || $hasContCB || $hasEntCB || $hasDataCB;
		$cb    = false;

		if ($hasCB) {
			$count = -1;
			$el = null;

			while (++$count < $this->stacklen) {
				$el = $this->stack[$count];
				$this->current = $el;
				$t  = $el->type;
				$n  = $el->value;
				$uv = null; // Value from callback

				switch ($t)
				{
					//! ==================================================================
					//!
					//!			Container tag
					//!
					//! ==================================================================
					case self::TYPE_CONTAINER:
						if ($hasContCB && isset($this->contCB[$n])) {
							list($cnt, $et) = $this->GetContent($el, 1);
							$uv = call_user_func($this->contCB[$n], $this, $el->attributes, $cnt);

							if ($uv == self::STOP_PARSE)
								return $return ? $buf : null;

							if ($return) $buf .= $uv;
							if ($et) $count = $et->position;
						}
						else {
							if ($return) $buf .= (string)$el;
						}

						break;

					//! ==================================================================
					//!
					//!			XML style tag
					//!
					//! ==================================================================
					case self::TYPE_TAG:
						if ($hasTagCB && isset($this->tagCB[$n])) {
							$uv = call_user_func($this->tagCB[$n], $this, $el->attributes);

							if ($uv == self::STOP_PARSE)
								return $return ? $buf : null;

							if ($return)
								$buf .= $uv;
						}
						else {
							if ($return) $buf .= (string)$el;
						}
						break;

					//! ==================================================================
					//!
					//!			Data
					//!
					//! ==================================================================
					case self::TYPE_DATA:
						if ($hasDataCB) {
							$uv = call_user_func($this->dataCB, $this, $n);
							if ($uv == self::STOP_PARSE)
								return $return ? $buf : null;

							if ($return)
								$buf .= $uv;
						}
						else {
							if ($return) $buf .= $n;
						}

						break;

					//! ==================================================================
					//!
					//!			Entities
					//!
					//! ==================================================================
					case self::TYPE_ENTITY:
						if ($hasEntCB) {
							$uv = call_user_func($this->entCB, $this, $n);
							if ($uv == self::STOP_PARSE)
								return $return ? $buf : null;

							if ($return) $buf .= $uv;
						}
						else {
							if ($return) $buf .= $n;
						}

						break;

					//! ==================================================================
					//!
					//!			Default
					//!
					//! ==================================================================
					default:
						if ($return) $buf .= (string)$el;
						break;
				} // End switch
			}   // End while

			if ($return) return $buf;
		}

		if ($return) return $this->__toString();
	}

	/**
	 * Tries to find the end tag of $e.
	 *
	 * @throws Exception
	 *   If $e is not a container tag
	 * @param HTMLElement $e
	 * @return HTMLElement
	 */
	public function GetEndTag(HTMLElement $e)
	{
		if ($e->type != self::TYPE_CONTAINER)
			throw new Exception('Trying to get end tag of a non-container tag');

		$pos = $e->position+1;
		for (; $pos < $this->stacklen; $pos++) {
			if ($this->stack[$pos]->value == "/$e->value")
				return $this->stack[$pos];
		}

		return false;
	}

	/**
	 * Tries to collect the content of a container tag.
	 *
	 * @param HTMLElement $e
	 * @param bool $getEndTag
	 * @return string|array
	 *   if $getEndTag is true an array will be returned where the first index
	 *   is the content and the second the end tag it self (HTMLElement).
	 */
	public function GetContent(HTMLElement $e, $getEndTag=false)
	{
		$et = null;
		if ($e->type != self::TYPE_CONTAINER) {
			//throw new Exception('Trying to get content of a non-container tag ' .
			//                    '(' . $e->value . ')');
			dbg('Trying to get content of a non-container tag ("' . $e->value . '") '.
			    'at offset "' . $e->byte . '"');
		}

		$ret = '';
		$pos = $e->position+1;
		for (; $pos < $this->stacklen; $pos++) {
			if ($this->stack[$pos]->value == "/$e->value") {
				$et = $this->stack[$pos];
				break;
			}
			$ret .= (string)$this->stack[$pos];
		}

		return $getEndTag ? array($ret, $et) : $ret;
	}

	/**
	 * Returns the current HTMLElement during {@see HTMLParser::Render()}
	 *
	 * @return HTMLElement
	 */
	public function Current()
	{
		return $this->current;
	}

	/**
	 * Returns the stack of {@see HTMLElement}s.
	 *
	 * @return array
	 */
	public function Stack()
	{
		return $this->stack;
	}

	/**
	 * Returns the current tag name during {@see HTMLParser::Render()}
	 *
	 * @return string
	 */
	public function TagName()
	{
		if ($this->current && $this->current->type != self::TYPE_DATA)
			return $this->current->value;

		return null;
	}

	/**
	 * Parse the $str. To get the result of the parse call
	 * {@see HTMLParser::Render()}.
	 *
	 * @param string $str
	 * @return HTMLParser
	 *   Returns this instance
	 */
	public function Parse($str)
	{
		$this->data   = $str;
		$this->length = strlen($this->data);

		while ($this->scan()) {
			$this->tag = null;
			$this->ctx = null;
		}

		return $this;
	}

	/**
	 * Scan the data
	 *
	 * @return bool
	 */
	private function scan()
	{
		if ($this->byte >= $this->length)
			return false;

		$ts = $this->find('<', $this->byte);

		if ($ts === false)
			return false;

		// Data at beginning
		if ($this->byte != $ts) {
			$tstr = substr($this->data, $this->byte, $ts-$this->byte);

			if (!$this->parseEntities($tstr))
				$this->pStack(self::TYPE_DATA, $tstr, null, $this->byte);

			unset($tstr);
		}

		if ($this->isComment($this->data, $ts))
			$te = $this->find('-->', $ts)+3;
		elseif ($this->isCDATA($this->data, $ts))
			$te = $this->find(']]>', $ts)+3;
		else
			$te = $this->find('>', $ts)+1;

		if ($te === false || $te < $ts)
			return false;

		$this->byte = $te;
		$this->tag  = substr($this->data, $ts, $te-$ts);
		$name       = $this->parseTagName($this->tag);
		$attr       = array();

		if ($this->ctx == self::TYPE_CONTAINER ||
		    $this->ctx == self::TYPE_TAG       ||
		    $this->ctx == self::TYPE_PREPROC   ||
		    $this->ctx == self::TYPE_DOCTYPE)
		{
			$attr = $this->parseTagAttributes($this->tag);
		}
		else if ($this->ctx == self::TYPE_COMMENT)
			$name = substr($this->tag, 4, -3);
		else if ($this->ctx == self::TYPE_CDATA)
			$name = substr($this->tag, 9, -3);

//	dbg("$name : $this->ctx");

		$this->pStack($this->ctx, $name, $attr, $ts);

		return true;
	}

	/**
	 * Checks if the current position in $str is the beginning of a comment.
	 *
	 * @param string $str
	 * @param int $pos
	 *   The offset where to start searching from.
	 * @return bool
	 */
	private function isComment($str, $pos)
	{
		return substr($str, $pos+1, 3) == '!--';
	}

	/**
	 * Checks if the current position in $str is the beginning of a CDATA section.
	 *
	 * @param string $str
	 * @param int $pos
	 * @return bool
	 */
	private function isCDATA($str, $pos)
	{
		return substr($str, $pos+1, 8) == '![CDATA[';
	}

	/**
	 * Fint the position of $what in $this->data.
	 *
	 * @param string $what
	 * @param int $start
	 *   The offset to start searching from
	 * @return int
	 */
	private function find($what, $start)
	{
		return strpos($this->data, $what, $start);
	}

	/**
	 * Finds the tag name in a full tag. `<div class='wrapper'>` will return
	 * `div`.
	 *
	 * @param string $in
	 * @return string
	 */
	private function parseTagName($in)
	{
		$name = false;

		if ($in[0] == '<')
			$in = substr($in, 1);

		switch ($in[0])
		{
			case '/':
				$name = substr($in, 0, strpos($in, '>'));
				$this->ctx = self::TYPE_ENDTAG;
				break;

			case '!':
				if (substr($in,1, 2) == self::TAG_COMMENT) {
					$name = '!--';
					$this->ctx = self::TYPE_COMMENT;
				}
				elseif (substr($in, 2, 5) == 'CDATA') {
					$name = 'CDATA';
					$this->ctx = self::TYPE_CDATA;
				}
				else {
					$name = substr($in, 1, strcspn($in, self::WHITES)-1);
					$this->ctx = self::TYPE_DOCTYPE;
				}
				break;

			case '?':
				$name = substr($in, 1, strcspn($in, self::WHITES )-1);
				$this->ctx = self::TYPE_PREPROC;
				break;

			default:
				$stop = '/>'.self::WHITES;
				$name = substr($in, 0, strcspn($in, $stop));
				$this->ctx = (substr($in,-2) == '/>' ? self::TYPE_TAG :
				                                       self::TYPE_CONTAINER);
		}

		return $name;
	}

	/**
	 * Parse tag attributes.
	 *
	 * @param string $in
	 * @return array
	 *   The found attributes. It's an assoc array where the attribute name is
	 *   the key and the attribute value is the value.
	 */
	private function parseTagAttributes($in)
	{
		$attr  = array();
		$in    = rtrim(ltrim($in, '<'), '>');
		$len   = strlen($in);
		$begin = strcspn($in, self::WHITES, 0)+1;
		$next  = 0;

		if (!$begin || $begin-1 == $len || $begin == $len)
			return $attr;

		//! Match all...
		$mall = '\'"='.self::WHITES.'/';

		do {
			//! Skip consecutive whitespace
			$begin = $this->skipWhites($in, $begin);
			$add   = 0;
			$next  = strcspn($in, $mall, $begin);
			$name  = substr($in, $begin, $next);
			$bn    = $begin + $next;

			if ($bn >= $len)
				break;

			if ($in[$bn] == self::TAG_FIN)
				break;

			//! This matches strings in doctypes for instance:
			//! <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
			//! "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
			//!
			//! Here "-//W3C...//EN" and "http://www.w3.orf/...dtd" will become
			//! attributes
			if ($in[$bn] == '\'' || $in[$bn] == '"') {
				$np = strcspn($in, $in[$bn], $bn+1)+1;
				$name = substr($in, $bn, $np+1);
				$attr[$name] = false;
				$begin = $bn+$np+2;
				continue;
			}

			//! If not an = it's probably some old style html like:
			//! <td nobr>...</td>
			if ($in[$bn] != '=') {
				$attr[$name] = false;
				$begin = $bn + 1;
				continue;
			}

			$begin += $next + 1;
			$char = $in[$begin];

			if ($char != '\'' && $char != '"')
				$char = self::WHITES . '>/';
			else {
				$begin++;
				//! Compensate for the quote it self
				$add = 1;
			}

			$next = strcspn($in, $char, $begin);
			$attr[$name] = substr($in, $begin, $next);
			$begin += $next + $add + 1;
		} while ($begin < $len);

		return $attr;
	}

	/**
	 * Moves the offset in string $in to the first non-whitespace character.
	 *
	 * @param string $in
	 * @param int $begin
	 * @return int
	 */
	private function skipWhites($in, $begin)
	{
		$slen = strlen($in);
		while (strcspn($in, self::WHITES, $begin) == 0 && $begin < $slen)
			$begin++;

		return $begin;
	}

	/**
	 * Parse entities.
	 * Found entities and surrounding data will be put on the stack when found.
	 *
	 * @param string $in
	 * @return bool
	 */
	private function parseEntities($in)
	{
		$stk = array();
		$sb = $this->byte;
		$ret = false;
		while (($pos = strpos($in, '&')) !== false) {
			if (preg_match('/^&([^ <>;&]+);/', substr($in, $pos), $m)) {
				$ret = true;
				$ppos = $sb - $pos;
				$sb += $pos;
				$this->pStack(self::TYPE_DATA, substr($in, 0, $pos), null, $ppos);
				$this->pStack(self::TYPE_ENTITY, "&{$m[1]};", null, $sb);
				$in = substr($in, $pos+2+strlen($m[1]));
				$sb += strlen($m[1]+2);
				continue;
			}
			$in = substr($in, $pos+1);
			$this->pStack(self::TYPE_DATA, $in, null, $sb);
		}

		//! Append what's left of $in
		if ($ret && strlen($in))
			$this->pStack(self::TYPE_DATA, $in, null, $sb);

		return $ret;
	}

	/**
	 * Creates a new {@see HTMLElement} and puts it on the stack.
	 *
	 * @param int $type
	 * @param string $value
	 *   The name of a tag or the content if it's data or the entire entity if an
	 *   entity.
	 * @param array $attr
	 * @param int $byte
	 *   The offset where the element begin.
	 */
	private function pStack($type, $value=null, array $attr=null, $byte=0)
	{
		$this->stack[] = new HTMLElement($type, $value, $attr, $this->stacklen++,
		                                 $byte);
	}

	/**
	 * Renders the stack into a string
	 *
	 * @return string
	 */
	public function __toString()
	{
		$s = '';
		foreach ($this->stack as $part)
			$s .= (string)$part;

		return $s;
	}
}

function dbg($a)
{
	$args = func_get_args();
	$f = vsprintf(array_shift($args), $args);
	echo "<pre>[" . htmlentities($f) . "]</pre>\n";
}

class HTMLElement
{
	public $byte;
	public $type;
	public $position;
	public $value;
	public $attributes;

	public function __construct($type, $value, array $attr=null, $position=0,
	                            $byte=0)
	{
		$this->type       = $type;
		$this->position   = $position;
		$this->byte       = $byte;
		$this->value      = $value;
		$this->attributes = $attr;
	}

	public function __toString()
	{
		$begin = '<';
		$end   = '>';

		switch ($this->type)
		{
			case HTMLParser::TYPE_DATA:
			case HTMLParser::TYPE_ENTITY:
				$begin = $end = '';
				break;

			case HTMLParser::TYPE_TAG:
				$end = '/>';
				break;

			case HTMLParser::TYPE_COMMENT:
				$begin = '<!--';
				$end   = '-->';
				break;

			case HTMLParser::TYPE_DOCTYPE:
				$begin = '<!';
				break;

			case HTMLParser::TYPE_PREPROC:
				$begin = '<?';
				$end   = '?>';
				break;

			case HTMLParser::TYPE_CDATA:
				$begin = '<![CDATA[';
				$end   = ']]>';
				break;
		}

		$begin .= $this->value;

		if (sizeof($this->attributes)) {
			$a = array();
			foreach ($this->attributes as $key => $val) {
				if ($val !== false)
					$key .= "='" . str_replace('\'', '&quot;', $val) . '\'';

				$a[] = $key;
			}
			$begin .= ' ' . join(' ', $a);
		}

		return $begin . $end;
	}
}
?>
