<?php
/**
 * Stream reader
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @version 0.3
 */

namespace PLib;

/**
 * This class implements the {@see IStream} interface.
 */
require_once PLIB_PATH . '/includes/istream.php';

/**
 * Uses PLib\File
 */
require_once PLIB_PATH . '/io.php';

/**
 * The StreamReader class has a number of methods to ease reading of a file.
 * The file it self will never be read into memory wich makes this class
 * handy when dealing with large files.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @example
 *  $reader = new PLib\StreamReader ('/really/large/file.log');
 *  while (false !== ($line = $reader->read_line ()))
 *    echo $line;
 */
class StreamReader implements IStream
{
  /**
   * The file currently being used
   * @var string
   */
  protected $file;

  /**
   * The file resource pointer
   * @var resource
   */
  protected $resource;

  /**
   * Constructor.
   *
   * @param string $file
   * @throws Exception
   *  If $file doesn't exist or isn't readable
   */
  public function __construct ($file)
  {
    if ($file instanceof PLib\File)
      $file = $file->path;

    if (!file_exists ($file))
      throw new \Exception ("The file \"$file\" doesn't exist!");

    if (!is_readable ($file))
      throw new \Exception ("The file \"$file\" isn't readable!");

    $this->file = $file;

    $this->resource = fopen ($file, 'rb');
    flock ($this->resource, LOCK_SH);
  }

  /**
   * Read `$bytes` number of bytes. If no argument is given `1` byte at a time
   * will be read.
   *
   * @param int $bytes
   * @return string|bool
   */
  public function read ($bytes=1)
  {
    if (feof ($this->resource))
      return false;

    return fread ($this->resource, $bytes);
  }

  /**
   * Rewinds the stream `$bytes` number of bytes
   *
   * @param int $bytes
   * @return bool
   */
  public function unread ($bytes=1)
  {
    $pos = ftell ($this->resource);

    if ($pos - $bytes < 0) {
      fseek ($this->resource, 0, SEEK_SET);
      return false;
    }

    fseek ($this->resource, $pos-$bytes, SEEK_SET);
    return true;
  }

  /**
   * Reads upto the first occurance of `$char` or reads to the end if `$char`
   * is not found
   *
   * @param string $char
   * @return string
   */
  public function read_to_char ($char)
  {
    $buf = '';
    $c = null;

    while ((($c = fread ($this->resource, 1)) != $char) &&
           !feof ($this->resource))
    {
      $buf .= $c;
    }

    return strlen ($buf) ? $buf : null;
  }

  /**
   * Reads upto the first occurance of any of the characters in `$chars` or
   * reads to the end if no match is found
   *
   * @param array $chars
   *  Array of characters
   * @return string
   */
  public function read_to_chars (array $chars)
  {
    $buf = '';
    $c = null;
    while ((($c = fread ($this->resource, 1)) !== false) &&
           !feof ($this->resource))
    {
      if (in_array ($c, $chars)) {
        $this->unread ();
        break;
      }
      else
        $buf .= $c;
    }

    return strlen ($buf) ? $buf : null;
  }

  /**
   * Reads `$offset` number of bytes starting at `$begin`.
   *
   * @param int $begin
   * @param int $offset
   * @param int $whence
   *  `SEEK_SET`, `SEEK_CUR` or `SEEK_END`. See {@link fseek fseek()}.
   * @throws Exception
   *  If `$begin` is less than `0`
   * @return string|bool
   */
  public function read_block ($begin, $offset, $whence=SEEK_SET)
  {
    if (feof ($this->resource))
      return false;

    if ($begin < 0) {
      throw new \Exception (
        "The start index in \"StreamReader::read_block()\" can't be less than 0"
      );
    }

    if ($offset < 1) {
      throw new \Exception (
        "The offset in \"StreamReader::read_block()\" can't be less than 1"
      );
    }

    rewind ($this->resource);
    fseek ($this->resource, $begin, $whence);

    return fread ($this->resource, $offset);
  }

  /**
   * Reads one line at a time from the file resource
   *
   * @return string
   */
  public function read_line ()
  {
    if (feof ($this->resource))
      return false;

    return fgets ($this->resource, 4096);
  }

  /**
   * Returns the file pointer's current position
   *
   * @return int
   */
  public function bytes_read ()
  {
    return ftell ($this->resource);
  }

  /**
   * Seek to offset
   *
   * @param int $offset
   * @param int $whence
   *  `SEEK_SET`, `SEEK_CUR` or `SEEK_END`. See {@link fseek fseek()}.
   * @return int (0 on success, -1 otherwise)
   */
  public function seek ($offset, $whence=SEEK_SET)
  {
    return fseek ($this->resource, $offset, $whence);
  }

  /**
   * Look `$bytes` ahead and reset to the previous position
   *
   * @param int $bytes
   *  Number of bytes to peek
   * @return string
   */
  public function peek ($bytes=1)
  {
    $tmp = fread ($this->resource, $bytes);
    $this->unread (strlen ($tmp));
    return $tmp;
  }

  /**
   * Look behind `$bytes`.
   *
   * @param int $bytes
   * @return string
   */
  public function look_behind ($bytes=1)
  {
    $pos = $this->position ();
    fseek ($this->resource, $pos-1-$bytes, SEEK_SET);
    $tmp = fread ($this->resource, $bytes);
    fseek ($this->resource, $pos, SEEK_SET);

    return $tmp;
  }

  /**
   * Returns the current offset
   *
   * @since 0.3
   * @return int
   */
  public function position ()
  {
    return ftell ($this->resource);
  }

  /**
   * Close the file pointer
   */
  public function close ()
  {
    $this->__destruct ();
  }

  /**
   * Destructor.
   * Closes the file resource.
   */
  public function __destruct ()
  {
    if (is_resource ($this->resource)) {
      fclose ($this->resource);
      $this->resource = null;
    }
  }
}
?>