<?php
/**
 * Archive unpacker/packer
 * This is pretty much a platform agnostic class. It only works on *nix.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package IO
 * @uses File
 * @uses Dir
*/

/**
 * We need the {@see File} class
 */
require_once 'StdIO.php';

/**
 * Class Archiver
 *
 * This is the wrapper class for ArchivePack and ArchiveUnpack.
 * The class unpacks and packs different types of archive files.
 * What archives can be uses depends on what softwares are installed
 * on the server running the script since the class executes shell
 * commands. This class does only work on *nix at this state, but
 * it would'nt take much effort to make it work on Windows.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package IO
*/
class Archiver
{
	/**
	 * If no directory is specified where the archive should either be created or
	 * extracted this is where the work will be done.
	 * @var string
	 */
	public static $tmpdir = '/tmp';
	/**
	 * Array to keep errors in
	 * @var array
	 */
	protected $_err = array();
	/**
	 * Flag that tells if something went bananas
	 * @var bool
	 */
	protected $error = false;

	/**
	 * Hidden constructor
	 *
	 * @param string $tmpdir
	 * @throws Exception
	 */
	protected function __construct()
	{
		if (!is_dir(self::$tmpdir)) {
			throw new Exception("The temporary directory \"".self::$tmpdir."\" is " .
			                    "not not a directory!");
		}
		if (!is_writable(self::$tmpdir)) {
			throw new Exception("The temporary directory \"".self::$tmpdir."\" is " .
			                    "not writable!");
		}
	}

	/**
	 * Execute a shellcommand
	 *
	 * @param string $cmd
	 * @return bool
	 */
	protected function _exec($cmd)
	{
		$retArr = array();
		$retStr = '';
		$cmd    = escapeshellcmd($cmd);
		$res    = exec("$cmd 2>&1 >/dev/null; echo $?");

		if($res != 0)
			throw new Exception("Could not execute \"$cmd\": $res");

		return true;
	}

	/**
	 * Recursively clean a directory
	 *
   * @param string $dir
   * @return void
   */
	protected function _clean(Dir $dir)
	{

	}

	/**
	 * Get the command for $archiveType
	 *
   * @param string $arrayAllowed
   *   Array to look in
   * @param string $arrayCommand
   *  array to return from
   * @param string $archiveType
   *   Type to look for
   * @return mixed
	 */
	protected function _getCommand($arrayAllowed, $arrayCommand, $archiveType)
	{
		foreach ($arrayAllowed as $key => $val)
			if (in_array($archiveType, $val))
				return $arrayCommand[$key];

		return false;
	}

	/**
	 * Generates a random string
	 *
   * @return string
   */
	public static function GetTmpId()
	{
		list($usec, $sec) = explode(" ", microtime());
		$ID  = ($sec - 1007700000) . str_pad((int)($usec * 1000000), 6, "0", STR_PAD_LEFT);
		$ID .= str_pad(rand(0, 999), 3, "0", STR_PAD_LEFT);
		return $ID;
	}
}

/**
 * This class packs files and directories into a given archive type.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package IO
 */
class ArchiverPack extends Archiver
{
	/**
	 * The type of archive to pack
	 * @var string
	 */
	protected $_type;
	/**
	 * The name of the archive
	 * @var string
	 */
	var $_name;
	/**
	 * The destination where to put the archive
	 * @var string
	 */
	var $_dest;
	/**
	 * Mode on the archive when packed
	 * @var int
	 */
	var $_chmod = 0666;
	/**
	 * The files to pack
	 * @var array
	 */
	var $_files = array();
	/**
	 * Commands for each archive type
	 * @var array
	 */
	var $_packCMD = array(
		/** Pack zip-files */
		'zip' => 'zip -jr &_DEST_& &_SOURCE_&',
		/** Pack tgz:s */
		'tgz' => 'tar czf &_DEST_& &_SOURCE_&',
		/** Pack tbz2 */
		'tbz2' => 'tar cjf &_DEST_& &_SOURCE_&'
	);
	/**
	 * Allowed archive types
	 * @var array
	 */
	var $_packAllowed = array(
		'zip'  => array('zip'),
		'tgz'  => array('tar.gz', 'tgz'),
		'tbz2' => array('tar.bz2', 'tbz2')
	);

	/**
	 * Constructor
	 *
	 * @param string $type
	 *  Type of archive
	 * @param string $name
	 *  Name of the archive
	 * @param string $dest
	 *  Destination where to put the archive
	 * @param string $chmod
	 *  Mode to set the archive to
	 * @throws Exception
	 */
	public function __construct($type, $name=null, $dest=null, $chmod=0666)
	{
		parent::__construct();

		if ($dest)
			$this->_dest = rtrim($dest, '/') . '/';
		else
			$this->_dest = self::$tmpdir . '/';

		//! Add a dot at the beginning of the type if not already done.
		$this->_type  = '.' . ltrim($type, '.');
		$this->_name  = trim($name);

		if (!is_dir($this->_dest))
			throw new Exception("The directory \"$this->_dest\" does not exist");

		if (!is_writable($this->_dest))
			throw new Exception("The directory \"$this->_dest\" is not writable");

		$this->_chmod = $chmod;
	}

	/**
	 * Add a file to the archive.
	 *
	 * @param string $file
	 *   The file to add to the archive
	 * @return bool
	 * @throws Exception
	 */
	public function Add($file)
	{
		if (!file_exists($file))
			throw new Exception("The file \"$file\" does not exist");

		array_push($this->_files, $file);
		return true;
	}

	/**
	 * Tries to pack the archive. If no $folder is given it means we should pack
	 * an entire directory. If no name is given for the archive we take the name
	 * of the first file added or the directory name.
	 *
	 * @param string $folder
	 * @return mixed
	 * @throws Exception
	 */
	public function Pack($folder=null)
	{
		if (empty($this->_files) && empty($folder))
			throw new Exception("There's no files to pack!");

		if ($folder) {
			if (!is_dir($folder))
				throw new Exception("\"$folder\" is not a directory");

			$folder = realpath($folder);
			if (!is_writable($this->_dest))
				throw new Exception("\"$folder\" is not writable");

			$this->_files = array();
		}

		$firstFile = $folder ? $folder : $this->_files[0];

		//! If no name is specified we grab the name of either
		//! the directory or the first file added to the archive.
		if(!$this->_name) {
			$typeOf = !$folder ? is_file($firstFile) ? 'file' : 'dir' : 'dir';
			$tsource = rtrim($firstFile, '/');
			$packName = split('/', $tsource);
			$this->_name = $packName[count($packName)-1];

			if($typeOf == 'file')
				$this->_name = substr($this->_name, 0, strrpos($this->_name, "."));
		}

		$this->_dest .= $this->_name . $this->_type;

		// Grab the command
		$cmd = $this->_getCommand($this->_packAllowed, $this->_packCMD,
		                          substr($this->_type, 1));
		if ($cmd === false)
			throw new Exception("Couldn't find a command for type \"$this->_type\"");

		$content = '';
		$chdir   = false;
		if (!$folder) {
			foreach ($this->_files as $file)
				$content .= $file . " ";
		}
		else {
			$currDir   = realpath('.');
			if (!@chdir($folder))
				throw new Exception("Couldn't change directory to: $folder");

			if (!$this->_packDir('.'))
				return false;

			foreach ($this->_files as $file)
				$content .= $file . " ";

			$chdir = true;
		}

		$cmd = str_replace('&_DEST_&', $this->_dest, $cmd);
		$cmd = str_replace('&_SOURCE_&', $content, $cmd);

		$this->_exec($cmd);
		@chmod($this->_dest, $this->_chmod);

		if ($chdir === true)
			@chdir($currDir);

		return $this->_dest;
	}

	/**
	 * Recursively loops through a directory structure and adds each file and
	 * directory to the _files array. This method is used when packing an entire
	 * directory.
   *
	 * @param string $dir
	 * @throws Exception
	 */
	protected function _packDir($dir)
	{
    // It's an assigment
		if (!$dh = @opendir($dir))
			throw new Exception("Couldn't open directory for reading: $targetDir");

		while (false !== ($f = @readdir($dh))) {
			if (preg_match ('/^\.\.?$/', $f))
				continue;

			if (@is_file($dir . '/' . $f)) {
				if (!$this->Add($dir . '/' . $f))
					return false;
			}
			elseif (@is_dir($dir . '/' . $f))
				$this->_packDir($dir . '/' . $f);
		}
		@closedir($dh);
		return true;
	}
} // ArchiverPack

/**
 * This class unpacks archive files.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package IO
 */
class ArchiverUnpack extends Archiver
{
	/**
	 * Commands for unpacking an archive file
	 * @var array
	 */
	protected $_unPackCMD = array(
		/** unpack zip files */
		'unzip' => 'unzip &_FILE_& -d &_DEST_&',
		/** unpack tar.gz and tgz */
		'untgz' => 'tar zxfC &_FILE_& &_DEST_&',
		/** unpack tar.bz2 and tbz2 */
		'unbzip' => 'tar jxfC &_FILE_& &_DEST_&'
	);
	/**
	 * Allowed filetypes to unpack
	 * @var array
	 */
	protected $_unPackAllowed = array(
		/** zip files */
		'unzip'  => array('zip'),
		/** tar.gz and tgz */
		'untgz'  => array('tar.gz', 'tgz'),
		/** tar.bz2 and tbz2 */
		'unbzip' => array('tar.bz2', 'tbz2')
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Tries to unpack an archive
	 *
	 * @param string $file
	 *  The file to unpack
	 * @param string $location
	 *  The location to unpack the archive to
	 * @param string
	 *  Creates a sub directory in location and unpack into that.
	 * @return mixed
	 * @throws Exception
	 */
	public function Extract($file, $location=null, $subdir=null)
	{
		$file = new File($file);

		$unPackTo = $location ? $location : self::$tmpdir;
		$unPackTo = rtrim($unPackTo, '/') . '/';

		if ($subdir) {
			if (!@mkdir($unPackTo . $subdir))
				throw new Exception("Couldn't create the subdirectory \"$subdir\"");

			$unPackTo .= $subdir . '/';
		}

		$type = $file->extension;
		$cmd  = $this->_getCommand($this->_unPackAllowed, $this->_unPackCMD, $type);

		if (!$type || !$cmd)
			throw new Exception("Found no command to handle $type files!");

		$cmd  = str_replace('&_FILE_&', $file->path, $cmd);
		$cmd  = str_replace('&_DEST_&', $unPackTo, $cmd);

		$this->_exec($cmd);

		return $unPackTo;
	}
} // ArchiverUnpack
?>