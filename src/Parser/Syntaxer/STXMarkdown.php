<?php
/**
 * STXMarkdown extends the Markdown class to enable codeblocks to be
 * highlighted by the syntax highlighter class {@link Syntaxer}
 *
 * NOTE! This is not a standalone class but depends on two other classes:
 *
 * * PHP Markdown & Extra by Michel Fortin
 *   <http://www.michelf.com/projects/php-markdown/>
 *
 * * Syntaxer by Pontus Östlund
 *   <http://www.poppa.se/projects/syntaxer/>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 1.1
 * @package Parser
 * @uses MarkdownExtra_Parser
 * @uses Syntaxer
 */

/**
 * Version constant
 */
define('STX_MARKDOWN_VERSION', '1.1');

/**
 * We need markdown
 */
require_once PLIB_INSTALL_DIR . '/Parser/Markdown/markdown.php';
/**
 * We need the {@see Syntaxer} class
 */
require_once PLIB_INSTALL_DIR . '/Parser/Syntaxer/Syntaxer.php';

/**
 * The STXMarkdown class
 *
 * * Either open up markdown.php and change the value of the constant
 *   MARKDOWN_PARSER_CLASS to STXMarkdown
 *
 * * Or use this function instead:
 *     <code>
 *     function MyMarkdown($text)
 *     {
 *       static $parser;
 *       if (!isset($parser))
 *         $parser = new STXMarkdown();
 *       return $parser->transform($text);
 *     }
 *     </code>
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Parser
 * @subpackage Syntaxer
 */
class STXMarkdown extends MarkdownExtra_Parser
{
	/**
	 * Cache map for Syntaxer objects.
	 * @var array
	 */
	private $cache_map = array();
	/**
	 * The path where to look for .stx files
	 * @var string
	 */
	private $stxpath = null;
	/**
	 * The path where to save the generated syntax maps
	 * @var string
	 */
	private $stxcachepath = null;

	/**
	 * Set the tabsize in the Syntaxer parser
	 * @var int
	 */
	public static $tabsize = 4;

	/**
	 * Constructor
	 * Here we just initialize the parent class
	 */
	public function __construct($stxpath=null, $stxcachepath=null)
	{
		parent::__construct();
		$this->stxpath      = $stxpath;
		$this->stxcachepath = $stxcachepath;
		$this->tab_width    = self::$tabsize;
	}

	/**
	 * This method overrides the Markdown class that takes care of codeblocks.
	 *
	 * @see MarkdownExtra_Parser::_doCodeBlocks_callback()
	 * @param array $matches
	 * @return string
	 */
	function _doCodeBlocks_callback($matches)
	{
		$codeblock = $matches[1];
		$codeblock = $this->outdent($codeblock);
		$codeblock = $this->Hilite($codeblock);
		$codeblock = preg_replace(array('/\A\n+/', '/\n+\z/'), '', $codeblock);
		return "\n\n" . $this->hashBlock($codeblock) . "\n\n";
	}

	/**
	 * Do the actual highlighting.
	 *
	 * Some added fetures is that we at the top of the codeblock can specify
	 * what laguage we're highlighting. We can also specify wether the code
	 * should be treated as HTML ebedded or not. The default behaviour of the
	 * syntax highlighter is to first determine if the language at hand is
	 * an HTML embedded language or not. If it is no highlighting will be done
	 * until the processing instruction is found. For PHP the case would be
	 * `<?` or `<?php`.
	 *
	 * If the highlighter is used in a blog or something similar mostly snippets
	 * of code will be highlighted and thus you might not want to add the
	 * processing instructions but rather treat the code an non-html embedded.
	 *
	 * To accomplish this the text to be parsed could be formatted like this:
	 *
	 * <code>
	 * This is just some silly text that preceeds some silly example code:
	 *
	 * #lang=php embedded=n
	 * $some_var = 'This is a string';
	 * echo call_some_func($some_var);
	 *
	 * And here's some silly text after the silly codeblock.
	 * </code>
	 *
	 * @param string $in
	 * @return string
	 */
	public function Hilite($in)
	{
		$lang = 'none';
		$embedded = false;
		$in = rtrim($in, "\n");

		//! Check for what language to use and if treat is as
		//! HTML embedded or not
		$re = '/^\s*#lang=(.[^\s]+)(?:\s+embedded=(.*))?/im';
		if (preg_match($re, $in, $match)) {
			$lang = strtolower(trim($match[1]));
			if (isset($match[2]))
				$embedded = $match[2] == 'y' ? true : false;

			//! remove the instruction
			$in = preg_replace('/^\s*#lang=.*\r?\n/im', '', $in);
		}

		try {
			//! If an object for the language doesn't exist in the cache
			//! create a new object and cache it.
			if (!isset($this->cache_map[$lang])) {
				$stx = new Syntaxer($lang, $this->stxpath, $this->stxcachepath);
				$stx->tabsize = $this->tab_width - 2;
				$this->cache_map[$lang] = $stx;
			}
			else
				$stx = $this->cache_map[$lang];

			$stx->HTMLEmbedded($embedded);
			$stx->Parse($in);

			$lines = $stx->GetLines();
			$lang  = $stx->GetLanguage();
			$noun  = $lines == 1 ? 'line' : 'lines';
			$out1 = "<div class='highlight'>" .
			        "<div class='header'>" .
			        "<span class='em'>$lines</span> $noun of " .
			        "<span class='em'>$lang</span>" .
			        "</div><div class='body'>";
			$out2 = "</div></div>";
			$out = $out1."<ol>\n".$stx->GetBuffer()."</ol>".$out2;
			unset($stx);
			return $out;
		}
		catch (SyntaxerIOError $e) {
			throw new Exception($e->getMessage());
		}
	}
}

/**
 * Like {@see Markdown()} except this function creates an instance of
 * {@see STXMarkdown} that syntax highlights source code.
 *
 * @since 1.1
 * @param string $text
 */
function STXMarkdown($text)
{
	static $parser;
	if (!isset($parser))
		$parser = new STXMarkdown();

	return $parser->transform($text);
}
?>