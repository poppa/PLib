<?php
/**
 * Streaming interface
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 */

namespace PLIB;

/**
 * Streaming interface.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
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
  public function read ($byte=1);

  /**
   * Rewinds the stream `$bytes` number of bytes
   *
   * @param int $bytes
   * @return bool
   */
  public function unread ($bytes=1);

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
  public function read_block ($begin, $offset, $whence=SEEK_SET);

  /**
   * Reads one line at a time from the resource
   *
   * @return string
   */
  public function read_line ();

  /**
   * Reads upto the first occurance of `$char` or reads to the end if `$char`
   * is not found
   *
   * @param string $char
   * @return string
   */
  public function read_to_char ($char);

  /**
   * Reads upto the first occurance of any of the characters in `$chasr` or
   * reads to the end if no match is found
   *
   * @param array $chars
   *  Array of characters
   * @return string
   */
  public function read_to_chars (array $chars);

  /**
   * Returns the interal pointer's current position, i.e byte offset from the
   * beginning of the stream
   *
   * @return int
   */
  public function bytes_read ();

  /**
   * Seek to offset
   *
   * @param int $offset
   * @param int $whence
   *  `SEEK_SET`, `SEEK_CUR` or `SEEK_END`. See {@link fseek fseek()}.
   * @return int (0 on success, -1 otherwise)
   */
  public function seek ($offset, $whence=SEEK_SET);

  /**
   * Look `$bytes` ahead and reset to the previous position
   *
   * @param int $bytes
   *  Number of bytes to peek
   * @return string
   */
  public function peek ($bytes=1);

  /**
   * Returns the current offset
   *
   * @since 0.3
   * @return int
   */
  public function position ();
}
?>