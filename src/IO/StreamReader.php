<?php
/**
 * Stream reader
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 * @version 0.3
 */

/**
 * This class implements the {@see IStream} interface.
 */
require_once PLIB_INSTALL_DIR . '/Core/IStream.php';

/**
 * The StreamReader class has a number of methods to ease reading of a file.
 * The file it self will never be read into memory wich makes this class
 * handy when dealing with large files.
 *
 * <code>
 * $reader = new StreamReader('/really/large/file.log');
 * while (false !== ($line = $reader->ReadLine()))
 *   echo $line;
 * </code>
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
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
	 *  When file doesn't exist or isn't readable
	 */
	public function __construct($file)
	{
		if ($file instanceof File)
			$file = $file->path;

		if (!file_exists($file))
			throw new Exception("The file \"$file\" doesn't exist!");

		if (!is_readable($file))
			throw new Exception("The file \"$file\" isn't readable!");

		$this->file = $file;

		$this->resource = fopen($file, 'rb');
		flock($this->resource, LOCK_SH);
	}

	/**
	 * Read `$bytes` number of bytes. If no argument is given `1` byte at a time
   * will be read.
	 *
	 * @param int $bytes
	 * @return string|bool
	 */
	public function Read($bytes=1)
	{
		if (feof($this->resource))
			return false;

		return fread($this->resource, $bytes);
	}

  /**
   * Rewinds the stream `$bytes` number of bytes
   * 
   * @param int $bytes
   * @return bool
   */
  public function Unread($bytes=1)
  {
    $pos = ftell($this->resource);

    if ($pos - $bytes < 0) {
      fseek($this->resource, 0, SEEK_SET);
      return false;
    }

    fseek($this->resource, $pos-$bytes, SEEK_SET);
    return true;
  }

	/**
	 * Reads upto the first occurance of `$char` or reads to the end if `$char`
	 * is not found
	 *
	 * @param string $char
	 * @return string
	 */
  public function ReadToChar($char)
  {
    $buf = '';
    $c = null;
    while ((($c = fread($this->resource, 1)) != $char) &&
           !feof($this->resource))
    {
      $buf .= $c;
    }

    return strlen($buf) ? $buf : null;
  }

	/**
	 * Reads upto the first occurance of any of the characters in `$chars` or
   * reads to the end if no match is found
	 *
	 * @param array $chars
   *  Array of characters
	 * @return string
	 */
  public function ReadToChars(array $chars)
  {
    $buf = '';
    $c = null;
    while ((($c = fread($this->resource, 1)) !== false) &&
           !feof($this->resource))
    {
      if (in_array($c, $chars)) {
        $this->Unread();
        break;
      }
      else
        $buf .= $c;
    }

    return strlen($buf) ? $buf : null;
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
	public function ReadBlock($begin, $offset, $whence=SEEK_SET)
	{
		if (feof($this->resource))
			return false;

		if ($begin < 0) {
			throw new Exception(
				"The start index in \"StreamReader::ReadBlock()\" can't be less than 0"
			);
		}

		if ($offset < 1) {
			throw new Exception(
				"The offset in \"StreamReader::ReadBlock()\" can't be less than 1"
			);
		}

		rewind($this->resource);
		fseek($this->resource, $begin, $whence);

		return fread($this->resource, $offset);
	}

	/**
	 * Reads one line at a time from the file resource
	 *
	 * @return string
	 */
	public function ReadLine()
	{
		if (feof($this->resource))
			return false;

		return fgets($this->resource, 4096);
	}

	/**
	 * Returns the file pointer's current position
	 *
	 * @return int
	 */
	public function BytesRead()
	{
		return ftell($this->resource);
	}

	/**
	 * Seek to offset
	 *
	 * @param int $offset
	 * @param int $whence
   *  `SEEK_SET`, `SEEK_CUR` or `SEEK_END`. See {@link fseek fseek()}.
	 * @return int (0 on success, -1 otherwise)
	 */
	public function Seek($offset, $whence=SEEK_SET)
	{
		return fseek($this->resource, $offset, $whence);
	}

	/**
	 * Returns the current offset
	 *
	 * @since 0.3
	 * @return int
	 */
	public function Position()
	{
		return ftell($this->resource);
	}

	/**
	 * Close the file pointer
	 */
	public function Close()
	{
		$this->__destruct();
	}

	/**
	 * Destructor.
	 * Closes the file resource.
	 */
	public function __destruct()
	{
		if (is_resource($this->resource)) {
			fclose($this->resource);
			$this->resource = null;
		}
	}
}
