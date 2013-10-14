<?php
/**
 * Helper classes for "IO" operations.
 *
 * <pre>
 *  //! List the content of this directory sorted on file name
 *  $dir = new PLib\Dir ('.');
 *  $dir->sort ('name');
 *  while($f = $dir->emit ('*')) {
 *    switch ($f->filetype)
 *    {
 *      case 'file':
 *        echo $f->name . " (" . $f->nicesize . ")<br/>\n";
 *        break;
 *      case 'dir':
 *        echo "Directory: " . $f->name . "<br/>\n";
 *        break;
 *    }
 *  }
 * </pre>
 *
 * @copyright 2013 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib
 */

namespace PLib;

/**
 * Class for handling filesystem files.
 *
 * This class will collect information about the requested file and giv easy
 * access to that information. Most methods can be called statically if the
 * file path is sent as argument.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class File extends IO
{
  /**
   * 1 Kb in bytes
   * @var int
   */
  const Kb = 1024;

  /**
   * 1 Mb in bytes
   * @var int
   */
  const Mb = 1048576;

  /**
   * 1 Gb in bytes
   * @var int
   */
  const Gb = 1073741824;

  /**
   * File path
   * @var string
   * @internal
   */
  private $file;

  /**
   * Path of the file
   * @var string
   */
  public  $path;

  /**
   * File size
   * @var int
   */
  public  $size;

  /**
   * File type
   * @var string
   */
  public  $filetype;

  /**
   * File modification time
   * @var int
   */
  public  $mtime;

  /**
   * File creation time
   * @var int
   */
  public  $ctime;

  /**
   * File name
   * @var string
   */
  public  $name;

  /**
   * Human readable file size. Like 4.6 kb
   * @var string
   */
  public  $nicesize;

  /**
   * File extension
   * @var string
   */
  public  $extension = 'unknown';

  /**
   * Constructor
   *
   * @param string $file
   *  The path to the file
   */
  public function __construct($file)
  {
    if (!file_exists ($file))
      throw new \Exception ("The file \"$file\" doesn't exist!");

    $this->init_file ($file);
  }

  /**
   * Set up basic members
   *
   * @param string $file
   */
  protected function init_file ($file)
  {
    $this->file = realpath ($file);

    if (file_exists ($this->file)) {
      $this->path     = $this->file;
      $this->size     = filesize ($this->file);
      $this->filetype = filetype ($this->file);
      $this->mtime    = filemtime ($this->file);
      $this->ctime    = filectime ($this->file);
      $this->name     = basename ($this->file);
      $this->nicesize = parent::_nice_size ($this->size);
      preg_match ('/(?:\.(tar))?\.([a-z0-9]+)$/i', $this->file, $m);

      if (isset ($m[2])) {
        $this->extension = strtolower ($m[2]);
        if (isset ($m[1]) && !empty ($m[1]))
          $this->extension = 'tar.' . $this->extension;
      }
    }
  }

  /**
   * Get the path of the file, i.e. the directory in which the file recides.
   *
   * @api
   * @return Dir
   */
  public function dir ()
  {
    return new Dir (dirname ($this->file));
  }

  /**
   * Rename the file
   *
   * @api
   * @param string $new_name
   * @return string
   *  Returns the new full path to the file
   */
  public function rename ($new_name)
  {
    $nn = PLib\combine_path (dirname ($this->file), $new_name);
    rename ($this->path, $nn);
    $this->init_file ($nn);

    return $this->path;
  }

  /**
   * Same as file_get_contents().
   * This method can be called statically thus the file reference argument
   *
   * @api
   * @link file_get_contents()
   * @return string
   */
  public function get_contents ()
  {
    return file_get_contents ($this->file);
  }

  /**
   * Truncates the file to `$len` length, ie empties it if `$len` is `0`
   *
   * @api
   * @param int $len
   *  Truncates the file to this length
   * @return bool
   *  `true` on success, `false` otherwise
   */
  public function truncate ($len=0)
  {
    $fh = fopen ($this->path, 'w');

    if (is_resource ($fh)) {
      ftruncate ($fh, $len);
      fclose ($fh);
      unset ($fh);
      return true;
    }

    unset ($fh);
    return false;
  }

  /**
   * Get the size in human readable format
   *
   * @api
   * @param int decimals
   * @return string
   */
  public function nice_size ($decimals=1)
  {
    return parent::_nice_size ($this->size, $decimals);
  }

  /**
   * Tries to figure out if a file is binary or not!
   *
   * @api
   * @throws \Exception
   *   If the file isn't found an exception is thrown
   * @param string $file
   * @return bool
   */
  public static function is_binary ($file)
  {
    if ($file instanceof File)
      $file = $file->path;

    if (file_exists ($file)) {
      if (!is_file ($file)) return 0;

      $fh  = fopen ($file, "r");
      $blk = fread ($fh, 512);
      fclose ($fh);
      clearstatcache ();

      return (
        0 or substr_count ($blk, "^ -~") / 512 > 0.3
          or substr_count ($blk, "\x00") > 0
      );
    }

    throw new \Exception ("The file \"$file\" doesn't exist!");
  }

  /**
   * Returns a new {@link File} object for `$path`. If `$path` doesn't exists
   * tries to create it.
   *
   * @api
   * @param string $path
   * @param string $mode
   *  See {@link fopen fopen()}
   * @param int $chmod
   *  See {@link chmod chmod()}
   * @return File
   */
  public static function create ($path, $mode='a+', $chmod=0664)
  {
    if (file_exists ($path))
      return new self ($path);

    // It's an assignment
    if ($fh = fopen ($path, $mode)) {
      fclose ($fh);
      chmod ($path, $chmod);
      return new self ($path);
    }

    throw new \Exception ("Unable to open or create file \"$path\"!");
  }

  /**
   * Destructor
   */
  public function __destruct ()
  {
    if (isset ($this->fh) && is_resource ($this->fh))
      fclose ($this->fh);
  }

  /**
   * To string conversion. Returns the path of the file.
   */
  public function __toString ()
  {
    return $this->file;
  }
}

/**
 * Class for handling filesystem directories. This class will collect
 * information about the requested directory and give easy access to that
 * information. The contents of the directory will be also be collected and can
 * be easily looped through.
 *
 * <pre>
 *  $dir = new Dir ('/path/to/dir');
 *  // Loop through the contents of the directory and show only PHP files.
 *  // The $f here will be a File object {@link File}
 *  while ($f = $dir->emit ('*.php')) {
 *    echo $f->path ($f->nice_size ()) . "&lt;br/>";
 *  }
 * </pre>
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class Dir extends IO
{
  /**
   * Dir path
   * @var string
   * @internal
   */
  private $dir;

  /**
   * Glob pattern
   * @var string
   * @internal
   */
  private $glob_pattern;

  /**
   * Contents index
   * @var int
   * @internal
   */
  private $contents_index;

  /**
   * Directory path
   * @var string
   */
  public $path;

  /**
   * Directory name
   * @var string
   */
  public $name;

  /**
   * Number of files/directories in this directory
   * @var int
   */
  public $size;

  /**
   * File type
   * @var string
   */
  public $filetype;

  /**
   * Array of File/Dir objects in this directory
   * @var array
   */
  public $contents;

  /**
   * Constructor
   *
   * @throws \Exception
   * @param string $dir
   */
  public function __construct ($dir)
  {
    $this->dir = realpath ($dir);

    if (!file_exists ($dir))
      throw new \Exception ("The directory \"$dir\" doesn't exist!");

    if (!is_dir ($dir))
      throw new \Exception ("\"$dir\" is not a directory!");

    $this->path     = &$this->dir;
    $this->name     = basename ($this->dir);
    $this->filetype = filetype ($this->dir);

    //! Contents of this directory
    $this->contents       = $this->get_contents ();
    $this->contents_index = 0;
    $this->size           = sizeof ($this->contents);
    $this->nicesize       = $this->size;
  }

  /**
   * Sorting function. The method used to sort is called "Schwartzian
   * Transform" which might look quite scary at first glance, but it a
   * really fast method. If you don't know what it is, search Google and
   * you'll find lots of stuff about it.
   *
   * @api
   * @param string $key
   *  Array key to sort on
   * @param int $order
   *  Sort order, either asc (SORT_ASC) or desc (SORT_DESC)
   * @return void
   */
  public function sort ($key='name', $order=SORT_ASC)
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
          $key = substr ($key, 1);
        }

        $l = $this->mk_sortarray ($a, $key);
        $this->_usort_str ($l);
        break;

      case 'modified': case '-modified':
        $on = $key[0] == '-' ? '-mtime' : 'mtime';
      case 'mtime': case '-mtime':
      case 'ctime': case '-ctime':
      case 'size' :
        if ($key[0] == '-') {
          $order = SORT_DESC;
          $key = substr ($key, 1);
        }

        $l = $this->mk_sortarray ($a, $key);
        $this->_usort_int ($l);
        break;

      default: return;
    }

    $this->contents = array_map (create_function ('$a', 'return $a[0];'), $l);

    if ($order == SORT_DESC) {
      $this->contents = array_reverse ($this->contents);
      unset ($a);
    }
  }

  /**
   * Emit the contents of the directory.
   *
   * @api
   * @param string $filter
   *  Glob pattern
   * @return File|Dir|bool Returns false when no more files/dirs are
   *  available
   */
  public function emit ($filter='*')
  {
    if ($this->contents_index < $this->size) {
      $file = $this->contents[$this->contents_index]['name'];
      $path = $this->contents[$this->contents_index]['path'];
      $this->contents_index++;

      if ($this->glob ($file, $filter)) {
        switch (filetype ($path)) {
          case 'file': return new File ($path); break;
          case 'dir':  return new Dir ($path);  break;
        }
      }

      return $this->emit ($filter);
    }

    return false;
  }

  /**
   * Match `$pattern` against `$string`.
   *
   * @todo Bug checking? This could break...
   * @param string $subject
   *  The string to match on
   * @param string $pattern
   *  The glob pattern
   * @return mixed Returns `1` if the pattern matches given subject, `0` if it
   *  does not, or `FALSE` if an error occurred.
   */
  private function glob ($subject, $pattern)
  {
    if (!$this->glob_pattern) {
      $find    = array('\*', '\|');
      $replace = array('.*','|');
      $pattern = preg_quote ($pattern);
      $pattern = str_replace ($find, $replace, $pattern);
      $this->glob_pattern = $pattern;
    }

    return preg_match ('/' . $this->glob_pattern . '/i', $subject);
  }

  /**
   * Get the contents of the current directory
   *
   * @return array
   */
  private function get_contents ()
  {
    $ret = array();
    $fh = opendir ($this->dir);

    if (is_resource ($fh)) {
      while ($f = readdir ($fh)) {
        if (preg_match ('/^\./', $f))
          continue;

        $fp = $this->dir . DIRECTORY_SEPARATOR . $f;
        $finfo = pathinfo ($fp);

        if (!isset ($finfo['basename']))
          $finfo['basename'] = null;

        if (!isset ($finfo['extension']))
          $finfo['extension']  = null;

        array_push($ret, array(
          'name'      => $f,
          'type'      => filetype ($fp),
          'size'      => filesize ($fp),
          'path'      => $fp,
          'mtime'     => filemtime ($fp),
          'ctime'     => filectime ($fp),
          'dirname'   => $finfo['dirname'],
          'basename'  => $finfo['basename'],
          'extension' => $finfo['extension']
          )
        );
      }

      closedir ($fh);
      unset ($fh);
    }

    return $ret;
  }

  /**
   * Returns a recursive iterator
   *
   * @param string $path
   * @return \RecursiveIteratorIterator
   */
  public static function recursive_iterator ($path)
  {
    $dkey = \RecursiveDirectoryIterator::KEY_AS_PATHNAME;
    $fkey = \RecursiveIteratorIterator::CHILD_FIRST;
    $rdi  = new \RecursiveDirectoryIterator ($path, $dkey);

    return new \RecursiveIteratorIterator ($rdi, $fkey);
  }

  /**
   * Creates a directory hierarchy.
   *
   * @throws \Exception
   * @param string $path
   */
  public static function mkdirhier ($path)
  {
    if ($path[0] !== DIRECTORY_SEPARATOR) {
      $message = "The path to Dir::mkdirhier() needs to be absolute! ".
                 "Call like this: Dir::mkdirhier(realpath('../" .
                 "relative/path/'));";
      throw new \Exception ($message);
    }

    $path  = trim ($path, DIRECTORY_SEPARATOR);
    $parts = explode (DIRECTORY_SEPARATOR, $path);
    $sofar = "";

    foreach ($parts as $part) {
      $sofar .= DIRECTORY_SEPARATOR . $part;
      if (!is_dir ($sofar)) {
        if (!@mkdir ($sofar))
          throw new \Exception ("Couldn't create directory $sofar");
      }
    }
  }

  /**
   * Delete the directory
   *
   * @throws \Exception
   * @param string $path
   * @param bool $recurse
   *   If true the directory will be removed recursivley
   */
  public static function remove ($path, $recurse=false, $keepRoot=false)
  {
    static $root = null;
    if ($keepRoot && $root == null)
      $root = $path;

    if ($recurse) {
      $fh = opendir ($path);

      if (!is_resource ($fh))
        throw new \Exception ("Couldn't open \"$path\" for reading!");

      while (false !== ($file = readdir ($fh))) {
        if (in_array ($file, array('.', '..')))
          continue;

        $fp = $path . DIRECTORY_SEPARATOR . $file;

        if (is_dir ($fp))
            self::remove ($fp, $recurse, $keepRoot);
        else
          if (!unlink ($fp))
            throw new Exception ("Couldn't remove \"$fp\"!");
      }

      closedir ($fh);

      if ($path != $root)
        rmdir ($path);
    }
    else {
      if ($path != $root)
        if (!rmdir ($path))
          throw new \Exception ("Couldn't remove direcotry \"$path\"");
    }
  }

  /**
   * To string conversion. Returns the path of the dir.
   */
  public function __toString ()
  {
    return $this->dir;
  }
}

/**
 * Abstract IO class
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
abstract class IO
{
  /**
   * New line char
   * @var string
   * @internal
   */
  protected static $NEWLINE = "\n";

  /**
   * File type (dir|file)
   * @var string
   */
  protected $filetype;

  /**
   * Path to file/dir
   * @var string
   */
  protected $path;

  /**
   * Check if path of object is writable
   *
   * @return bool
   */
  public function is_writable ()
  {
    return is_writable ($this->path);
  }

  /**
   * Check if path of object is readable
   *
   * @return bool
   */
  public function is_readable ()
  {
    return is_readable ($this->path);
  }

  /**
   * Sort array on int
   *
   * @internal
   * @param array $array
   */
  protected function _usort_int (&$array)
  {
    usort ($array,
      create_function (
        '$a,$b',
        'if ($a[1] == $b[1]) return 0;
         return $a[1] < $b[1] ? -1 : 1;'
      )
    );
  }

  /**
   * Sort array on string
   *
   * @internal
   * @param array $array
   */
  protected function _usort_str (&$array)
  {
    usort ($array,
      create_function (
        '$a,$b', 'return strcmp($a[1], $b[1]);'
      )
    );
  }

  /**
   * Set up sortable array
   *
   * @internal
   * @param array $array
   * @param string $index
   */
  protected function mk_sortarray ($array, $index)
  {
    return array_map (
      create_function (
        '$a', 'return array($a, strtolower($a["'.$index.'"]));'
      )
      , $array
    );
  }

  /**
   * Return the file size in nice format such as 5Kb or 2Mb etc
   *
   * @api
   * @param int $size
   * @param int $decimals
   * @return string
   */
  public static function _nice_size ($size, $decimals=1)
  {
    if ($size < File::Kb)
      $size = $size . " b";
    elseif ($size >= File::Kb && $size < File::Mb)
      $size = round ($size / File::Kb, $decimals) . " kB";
    elseif ($size >= File::Mb && $size < File::Gb)
      $size = round ($size / File::Mb, $decimals) . " MB";
    else
      $size = round ($size / File::Gb, $decimals) . " GB";

    return $size;
  }

  /**
   * Magic method. Returns the path.
   *
   * @return string
   */
  public function __toString ()
  {
    return $this->path;
  }
}
?>