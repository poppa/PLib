<?php
/**
 * Creates a HTML table from a database recordset
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.10
 * @package Database
 * @depends Tablify
*/

/**
 * We need the {@see Tablify} class
 */
require_once PLIB_INSTALL_DIR . '/IO/Tablify.php';

/**
 * Creates a HTML table from a database recordset.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.10
 * @package Database
*/
class SQLTablify extends Tablify
{
	/**
	 * Column headers
	 * @var array
	 */
	private $headers = array();

	/**
	 * Constructor.
	 *
	 * @see Tablify::__construct()
	 * @param array $args
	 */
	public function __construct($args)
	{
		parent::__construct($args);
	}

	/**
	 * Set the column headers
	 *
	 * @param string $args
	 *  Arbitrary number of arguments
	 */
	public function Headers()
	{
		$this->headers = func_get_args();
	}

	/**
	 * Parse the database record set
	 *
	 * @param resource|object $resource
	 * @param bool $ucfirst
	 *  Turn the first character in the header fields to upper case if headers
	 *  isn't set explicitly
	 */
	public function Parse($resource, $ucfirst=true)
	{
		if (is_object($resource)) {
			if ($resource instanceof DBResult) {
				while ($row = $resource->FetchAssoc()) {
					if (!$this->headers) {
						foreach (array_keys($row) as $head) {
							if ($ucfirst) $head = ucfirst($head);
							$this->headers[] = $head;
						}
					}
					$this->append($row);
				}
			}
			array_unshift($this->contents, $this->headers);
			return;
		}

		if (!is_resource($resource)) {
			throw new Exception(
				"The resource given to \"SQLTablify::Parse()\" is not a valid " .
				"\"resource\"!"
			);
		}
	}

	/**
	 * Hide the {@link Tablify::ParseFile()} method
	*/
	public function ParseFile() {}

	/**
	 * Append the db row to the {@link Tablify::$content} array
	 *
	 * @param array $row
	 */
	private function append($row)
	{
		$ar = array();
		foreach ($row as $k => $v)
			$ar[] = $v;

		$this->contents[] = $ar;
		unset($ar);
	}
}
?>