<?php
/**
 * Streaming interface
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Core
 */

/**
 * Stream interface.
 * 
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Core
 */
interface IStream
{
	/**
	 * Read `$bytes` number of bytes. If no argument is given `1` byte at a time
   * will be read.
	 *
	 * @param int $bytes
	 * @return string|bool
	 */
  public function Read($byte=1);
  /**
   * Rewinds the stream `$bytes` number of bytes
   *
   * @param int $bytes
   * @return bool
   */
  public function Unread($bytes=1);
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
  public function ReadBlock($begin, $offset, $whence=SEEK_SET);
	/**
	 * Reads one line at a time from the resource
	 *
	 * @return string
	 */
  public function ReadLine();
	/**
	 * Reads upto the first occurance of `$char` or reads to the end if `$char`
	 * is not found
	 *
	 * @param string $char
	 * @return string
	 */
  public function ReadToChar($char);
	/**
	 * Reads upto the first occurance of any of the characters in `$chasr` or
   * reads to the end if no match is found
	 *
	 * @param array $chars
   *  Array of characters
	 * @return string
	 */
  public function ReadToChars(array $chars);
	/**
	 * Returns the interal pointer's current position, i.e byte offset from the
   * beginning of the stream
	 *
	 * @return int
	 */
  public function BytesRead();
	/**
	 * Seek to offset
	 *
	 * @param int $offset
	 * @param int $whence
   *  `SEEK_SET`, `SEEK_CUR` or `SEEK_END`. See {@link fseek fseek()}.
	 * @return int (0 on success, -1 otherwise)
	 */
  public function Seek($offset, $whence=SEEK_SET);
  /**
   * Look `$bytes` ahead and reset to the previous position
   *
   * @param int $bytes
   *  Number of bytes to peek
   * @return string
   */
  public function Peek($bytes=1);
	/**
	 * Returns the current offset
	 *
	 * @since 0.3
	 * @return int
	 */
  public function Position();
}
?>