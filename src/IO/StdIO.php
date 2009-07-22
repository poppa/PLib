<?php
/**
 * Helper classes for "IO" operations.
 *
 * Example:
 * <code>
 * //! List the content of this directory sorted on file name
 * $dir = new Dir('.');
 * $dir->sort('name');
 * while($f = $dir->emit('*')) {
 *  	switch ($f->filetype)
 * 		{
 * 		case 'file':
 * 			echo $f->name . " (" . $f->nicesize . ")<br/>\n";
 * 			break;
 * 		case 'dir':
 * 			echo "Directory: " . $f->name . "<br/>\n";
 * 			break;
 * 		}
 * }
 * </code>
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 * @todo These classes really needs to be looked over.
 */

/**
 * Class for handling filesystem files.
 * This class will collect information about the requested file and giv easy
 * access to that information. Most methods can be called statically if the
 * file path is sent as argument.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @version 0.4
 * @package IO
 */
class File extends IO
{
	/**
	 * 1 Kb in bytes
	 * @const int
	 */
	const Kb = 1024;
	/**
	 * 1 Mb in bytes
	 * @const int
	 */
	const Mb = 1048576;
	/**
	 * 1 Gb in bytes
	 * @const int
	 */
	const Gb = 1073741824;

	private $file;
	public  $path;
	public  $size;
	public  $filetype;
	public  $mtime;
	public  $ctime;
	public  $name;
	public  $nicesize;
	public  $extension = 'unknown';

	/**
	 * Constructor
	 *
	 * @param string $file
	 * 	The path to the file
	 */
	public function __construct($file)
	{
		if (!file_exists($file))
			throw new Exception("The file \"$file\" doesn't exist!");

		$this->initFile($file);
	}

	/**
	 * Set up basic members
	 *
	 * @param string $file
	 */
	protected function initFile($file)
	{
		$this->file = realpath($file);

		if (file_exists($this->file)) {
			$this->path     = $this->file;
			$this->size     = filesize($this->file);
			$this->filetype = filetype($this->file);
			$this->mtime    = filemtime($this->file);
			$this->ctime    = filectime($this->file);
			$this->name     = basename($this->file);
			$this->nicesize = IO::NiceSize($this->size);
			preg_match('/(?:\.(tar))?\.([a-z0-9]+)$/i', $this->file, $m);

			if (isset($m[2])) {
				$this->extension = strtolower($m[2]);
				if (isset($m[1]) && !empty($m[1]))
					$this->extension = 'tar.' . $this->extension;
			}
		}
	}

	/**
	 * Get the path of the file, i.e. the directory in which the file recides.
	 *
	 * @return string
	 */
	public function Dir()
	{
		return dirname($this->file);
	}

	/**
	 * Rename the file
	 *
	 * @param string $newName
	 * @return string
	 * 	Returns the new full path to the file
	 */
	public function Rename($newName)
	{
		$nn = dirname($this->file) . '/' . $newName;
		rename($this->path, $nn);
		$this->initFile($nn);

		return $this->path;
	}

	/**
	 * Same as file_get_contents().
	 * This method can be called statically thus the file reference argument
	 *
	 * @link file_get_contents()
	 * @return string
	 */
	public function GetContents()
	{
		return file_get_contents($this->file);
	}

	/**
	 * Tries to figure out if a file is binary or not!
	 *
	 * @since 0.3
	 * @throws Exception
	 *   If the file isn't found an exception is thrown
	 * @param string $file
	 * @return bool
	 */
	public static function IsBinary($file)
	{
		if (file_exists($file)) {
			if (!is_file($file)) return 0;

			$fh  = fopen($file, "r");
			$blk = fread($fh, 512);
			fclose($fh);
			clearstatcache();

			return (
				0 or substr_count($blk, "^ -~", "^\r\n")/512 > 0.3
				  or substr_count($blk, "\x00") > 0
			);
		}
		else
			throw new Exception("The file \"$file\" doesn't exist!");
	}

  /**
   * Returns a new {@see File} object for `$path`. If `$path` doesn't exists
   * tries to create it.
   *
   * @param string $path
   * @param string $mode
   *  See {@link fopen fopen()}
   * @param int $chmod
   *  See {@link chmod chmod()}
   * @return File
   */
  public static function Create($path, $mode='a+', $chmod=0664)
  {
    if (file_exists($path))
      return new self($path);

    // It's an assignment
    if ($fh = fopen($path, $mode)) {
      fclose($fh);
      chmod($path, $chmod);
      return new self($path);
    }

    throw new Exception("Unable to open or create file \"$path\"!");
  }

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		if (isset($this->fh) && is_resource($this->fh))
			fclose($this->fh);
	}
}

/**
 * Class for handling filesystem directories. This class will collect
 * information about the requested directory and give easy access to that
 * information. The contents of the directory will be also be collected and can
 * be easily looped through
 *
 * Example
 * <code>
 * $dir = new Dir('/path/to/dir');
 * // Loop through the contents of the directory and show only PHP files.
 * // The $f here will be a File object {@link File}
 * while ($f = $dir->emit('*.php')) {
 *     echo $f->path ($f->niceSize()) . "<br/>";
 * }
 * </code>
 *
 * For a more extensive example see the docblock for {@link IO}
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 */
class Dir extends IO
{
	private $dir;
	private $globPattern;
	private $contents_index;

	public  $path;
	public  $name;
	public  $size;
	public  $filetype;
	public  $contents;

	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @param string $dir
	 */
	public function __construct($dir)
	{
		$this->dir = realpath($dir);

		if (!file_exists($dir))
			throw new Exception("The directory \"$dir\" doesn't exist!");

		if (!is_dir($dir))
			throw new Exception("\"$dir\" is not a directory!");

		$this->path     = &$this->dir;
		$this->name     = basename($this->dir);
		$this->filetype = filetype($this->dir);

		//! Contents of this directory
		$this->contents       = $this->getContents();
		$this->contents_index = 0;
		$this->size           = sizeof($this->contents);
	}

	/**
	 * Sorting function. The method used to sort is called "Schwartzian
	 * Transform" which might look quite scary at first glance, but it a
	 * really fast method. If you don't know what it is, search Google and
	 * you'll find lots of stuff about it.
	 *
	 * @param string $key
	 * 	Array key to sort on
	 * @param int $order
	 * 	Sort order, either asc (SORT_ASC) or desc (SORT_DESC)
	 * @return void
	 */
	public function Sort($key='name', $order=SORT_ASC)
	{
		$a = $this->contents;

		switch ($key) {
			case 'type': case '-type':
			case 'path': case '-path':
			case 'name': case '-name':
			case 'extension': case '-extension':
			case 'dirname': case '-dirname':
			case 'basename': case '-basename':
				if ($key[0] == '-') {
					$order = SORT_DESC;
					$key = substr($key, 1);
				}
				$l = $this->mk_sortarray($a, $key);
				$this->_usort_str($l);
				break;

			//!Sort on file size
			case 'modified': case '-modified':
				$on = $key[0] == '-' ? '-mtime' : 'mtime';
			case 'mtime': case '-mtime':
			case 'ctime': case '-ctime':
			case 'size' :
				if ($key[0] == '-') {
					$order = SORT_DESC;
					$key = substr($key, 1);
				}
				$l = $this->mk_sortarray($a, $key);
				$this->_usort_int($l);
				break;

			default: return;
		}

		$this->contents = array_map(create_function('$a', 'return $a[0];'), $l);

		if ($order == SORT_DESC) {
			$this->contents = array_reverse($this->contents);
			unset($a);
		}

		/*
		if (sizeof($args))
			while ($k = array_shift($args))
				$this->sort($k);
		*/
	}

	/**
	 * Emit the contents of the directory.
	 *
	 * @param string $filter
	 * 	Glob pattern
	 * @return File|Dir
	 */
	public function Emit($filter='*')
	{
		if ($this->contents_index < $this->size) {
			$file = $this->contents[$this->contents_index]['name'];
			$path = $this->contents[$this->contents_index]['path'];
			$this->contents_index++;
			if ($this->glob($file, $filter)) {
				switch (filetype($path)) {
					case 'file': return new File($path); break;
					case 'dir':  return new Dir($path);  break;
				}
			}
			return $this->Emit($filter);
		}
		return false;
	}

	/**
	 * Create a regexp from the glob pattern
	 *
	 * @todo Bug checking? This could break...
	 * @param string $string
	 * 	The string to match on
	 * @param string $pattern
	 * 	The glob pattern
	 */
	private function glob($string, $pattern)
	{
		if (!$this->globPattern) {
			$find    = array('\*', '\|');
			$replace = array('.*','|');
			$pattern = preg_quote($pattern);
			$pattern = str_replace($find, $replace, $pattern);
			$this->globPattern = $pattern;
		}
		return preg_match('/' . $this->globPattern . '/i', $string);
	}

	/**
	 * Get the contents of the current directory
	 *
	 * @return array
	 */
	private function getContents()
	{
		$ret = array();
		$fh = opendir($this->dir);
		if (is_resource($fh)) {
			while ($f = readdir($fh)) {
				if (preg_match('/^\./', $f))
					continue;

				$fp = $this->dir . '/' . $f;
				$finfo = pathinfo($fp);

				if (isset($finfo['basename']))
					$finfo['basename'] = null;

				if (!isset($finfo['extension']))
					$finfo['extension']  = null;

				array_push($ret, array(
					'name'      => $f,
					'type'      => filetype($fp),
					'size'      => filesize($fp),
					'path'      => $fp,
					'mtime'     => filemtime($fp),
					'ctime'     => filectime($fp),
					'dirname'   => $finfo['dirname'],
					'basename'  => $finfo['basename'],
					'extension' => $finfo['extension']
					)
				);
			}
			closedir($fh);
			unset ($fh);
		}
		return $ret;
	}

	/**
	 * Returns a recursive iterator
	 *
	 * @since 0.3
	 * @param string $path
	 * @return RecursiveIteratorIterator
	 */
	public static function RecursiveIterator($path)
	{
		$dkey = RecursiveDirectoryIterator::KEY_AS_PATHNAME;
		$fkey = RecursiveIteratorIterator::CHILD_FIRST;
		$rdi  = new RecursiveDirectoryIterator($path, $dkey);
		return new RecursiveIteratorIterator($rdi, $fkey);
	}

	/**
	 * Creates a directory hierarchy
	 *
	 * @since 0.4
   * @throws Exception
	 * @param string $path
	 */
	public static function MkdirHier($path)
	{
		if ($path[0] != PLIB_DS) {
			throw new Exception("The path to Dir::MkdirHier() needs to be absolute! ".
			                    "Call like this: Dir::MkdirHier(realpath('../" .
			                    "relative/path/'));");
		}

		$path  = trim($path, PLIB_DS);
		$parts = explode(PLIB_DS, $path);
		$sofar = "";

		foreach ($parts as $part) {
			$sofar .= PLIB_DS . $part;
			if (!is_dir($sofar)) {
				if (!@mkdir($sofar))
					throw new Exception("Couldn't create directory $sofar");
			}
		}
	}

	/**
	 * Delete the directory
	 *
   * @throws Exception
	 * @param string $path
	 * @param bool $recurse
	 *   If true the directory will be removed recursivley
	 */
	public static function Remove($path, $recurse=false)
	{
		if ($recurse) {
			$fh = opendir($path);
			if (!is_resource($fh))
				throw new Exception("Couldn't open \"$path\" for reading!");

			while (false !== ($file = readdir($fh))) {
				if (in_array($file, array('.', '..')))
					continue;

				$fp = $path . DIRECTORY_SEPARATOR . $file;
				if (is_dir($fp))
					self::Remove($fp, $recurse);
				else
					if (!unlink($fp))
						throw new Exception("Couldn't remove \"$fp\"!");
			}
			closedir($fh);
			rmdir($path);
		}
		else {
			if (!rmdir($path))
				throw new Exception("Couldn't remove direcotry \"$path\"");
		}
	}
}

/**
 * Abstract IO class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 */
abstract class IO
{
	static $NEWLINE = "\n";
	protected $filetype;
  protected $path;

  public function IsWritable()
  {
    return is_writable($this->path);
  }

  public function IsReadable()
  {
    return is_readable($this->path);
  }

	/**
	 * Concatenates all arguments with the directory separator.
	 *
	 * @param string $args
	 *  Any number or arguments ...
	 * @return string
	 *  NOTE! No ending slash
	 */
	public static final function CombinePath($args)
	{
		$args = func_get_args();
		return join(DIRECTORY_SEPARATOR, $args);
	}

	protected function _usort_int(&$array)
	{
		usort($array,
			create_function(
				'$a,$b',
				'if ($a[1] == $b[1]) return 0;
				 return $a[1] < $b[1] ? -1 : 1;'
			)
		);
	}

	protected function _usort_str(&$array)
	{
		usort($array,
			create_function(
				'$a,$b', 'return strcmp($a[1], $b[1]);'
			)
		);
	}

	protected function mk_sortarray($array, $index)
	{
		return array_map(
			create_function(
				'$a', 'return array($a, strtolower($a["'.$index.'"]));'
			)
			, $array
		);
	}

	/**
	 * Return the file size in nice format such as 5Kb or 2Mb etc
	 *
	 * @param int $size
	 * @param int $decimals
	 * @return string
	 */
	public static function NiceSize($size, $decimals=1)
	{
		if ($size < File::Kb)
			$size = $size . "b";
		elseif ($size >= File::Kb && $size < File::Mb)
			$size = round($size / File::Kb, $decimals) . " kB";
		elseif ($size >= File::Mb && $size < File::Gb)
			$size = round($size / File::Mb, $decimals) . " MB";
		else
			$size = round($size / File::Gb, $decimals) . " GB";

		return $size;
	}

	/**
	 * Magic method. Returns the path.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->path;
	}
}

/**
 * Fake IO class
 *
 * @author Pontus �stlund <pontus@poppa.se>
 * @package IO
*/
class IOObject{}
?>
