<?php
/**
 * Upload
 *
 * @copyright 2013 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib\Web
 */

namespace PLib\Web;

/**
 * Requires the iterator interface
 */
require_once PLIB_PATH . '/includes/iterator.php';

/**
 * A simple upload class.
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class Upload extends \PLib\Iterator
{
  /**
   * Total size of the upload
   * @var float
   */
  protected $totalSize = 0;

  /**
   * Config array
   * @var array
   */
  protected $cfg = array();

  /**
   * Warnings
   * @var array
   */
  protected $warnings = array();

  /**
   * Error/warning messages
   * @var array
   */
  protected $messages  = array(
    1 => 'File size (%d) for "%s" exceeds allowed file size (%d)',
    2 => 'Files with file extension "%s" are not allowed to be uploaded',
    3 => 'The file "%s" doesn\'t seem to an uploaded file!',
    4 => 'Couldn\'t set permisson to "%d" on "%s"!',
    5 => 'The file "%s" already exists and file over writing is not allowed!',
    6 => 'Couldn\'t remove already existing file "%s"',
    7 => 'Couldn\'t move uploaded file to "%s"',
    8 => 'Skipping file due to upload error (%d) on file "%s"',
    9 => 'Target location "%s" is not writable'
  );

  /**
   * The last message
   * @var string
   */
  protected $last_message;

  /**
   * Constructor
   *
   * @param string $location
   *   Where to put the upload
   * @param array $allowedFileTypes
   *   Allowed file extesions to upload. Empty mean no restrictions.
   * @param string $maxsize
   *   Max upload size. Suffixes are K, M, G
   * @param string $checkMimeType
   *   Validate against mimetype. If set to true $allowedFileTypes should
   *   contain allowed mimetypes rather than extensions
   * @param bool $owerwrite
   *   Overwrite existing files with the same name as the uploaded one
   * @param int $chmod
   */
  public function __construct ($location, $allowedFileTypes=array(),
                               $maxsize='2M', $checkMimeType=false,
                               $owerwrite=true, $chmod=0666)
  {
    $this->cfg['location']  = rtrim ($location, '/') . '/';
    $this->cfg['allowed']   = $allowedFileTypes;
    $this->cfg['maxsize']   = $this->make_max ($maxsize);
    $this->cfg['owerwrite'] = $owerwrite;
    $this->cfg['chmod']     = $chmod;

    if (!is_writable ($this->cfg['location'])) {
      $m = sprintf ($this->messages[9], $this->cfg['location']);
      throw new UploadException ($m);
    }

    if (!empty ($_FILES)) {
      foreach ($_FILES as $k => $v) {
        if ($v['error'] != 0) {
          $this->fail (8, sprintf ($this->messages[8], $v['error'], $v['name']));
          continue;
        }

        if ($this->is_valid ($v))
          $this->container[] = $v;
      }
    }
  }

  /**
   * Is there any more files in the iteration
   *
   * @return bool
   */
  public function has_next ()
  {
    return array_key_exists ($this->pointer, $this->container);
  }

  /**
   * Returns the next file
   *
   * @return string The path to the file
   */
  public function next ()
  {
    return $this->copy ();
  }

  /**
   * Set the messages in the internal message array.
   * Useful if you wish to translate the messages
   *
   * @param array $array
   */
  public function set_messages (array $array)
  {
    $this->messages = $array;
  }

  /**
   * Check if the there are any messages
   *
   * @return bool
   */
  public function has_messages ()
  {
    return sizeof ($this->warnings) > 0 ? true : false;
  }

  /**
   * Returns the last message.
   *
   * @return string
   */
  public function get_last_message ()
  {
    return $this->last_message;
  }

  /**
   * Returns all messages
   *
   * @param string $type
   *   If set the messages will be stringified else the array of messages
   *   will be returned.
   * @return mixed
   */
  public function get_messages ($type='string')
  {
    $r = null;

    switch ($type)
    {
      default:
      case 'string':
        $r = "";
        $i = 1;
        foreach ($this->warnings as $message) {
          $r .= "$i: " . $message['message'] . "<br/>\n";
          $i++;
        }
        break;

      case 'array':
        $r = $this->warnings;
        break;
    }

    return $r;
  }

  /**
   * Copy the current file to the desired location
   *
   * @throws UploadException
   * @return string
   *   The path to the file
   */
  protected function copy ()
  {
    if (!sizeof ($this->container))
      return null;

    $ok    = true;
    $tfile = $this->container[$this->pointer]['tmp_name'];
    $file  = $this->container[$this->pointer]['name'];
    $path  = $this->cfg['location'] . $file;

    if (file_exists ($path)) {
      if (!$this->cfg['owerwrite']) {
        @unlink ($tfile);
        $this->pointer++;
        throw new UploadException (sprintf($this->messages[5], $path));
      }

      if (!@unlink ($path)) {
        @unlink ($tfile);
        $this->pointer++;
        throw new UploadException (sprintf ($this->messages[6], $path));
      }
    }

    $this->pointer++;
    $this->do_copy ($tfile, $path, $this->cfg['chmod']);

    return $path;
  }

  /**
   * Do the actual copying
   *
   * @throws UploadException
   * @param string $tempfile
   *   The uploaded file
   * @param string $file
   *   The new location of the uploaded file
   * @param int $chmod
   * @return bool
   */
  protected function do_copy ($tempfile, $file, $chmod)
  {
    if (@move_uploaded_file ($tempfile, $file)) {
      if (@chmod ($file, $chmod))
        return true;

      $this->fail (4, sprintf ($this->messages[4], $chmod, $file));
    }
    else
      throw new UploadException (sprintf ($this->messages[7], $file));

    return true;
  }

  /**
   * Checks if the file is valid to upload
   *
   * @param array $file
   * @return bool
   */
  protected function is_valid (array $file)
  {
    if (!$this->check_size ($file))
      return false;

    if (!$this->check_extension ($file['name']))
      return false;

    if (!$this->is_uploaded_file ($file))
      return false;

    return true;
  }

  /**
   * Check if the file size is valid
   *
   * @param array $file
   * @return bool
   */
  protected function check_size (array $file)
  {
    if ($file['size'] > $this->cfg['maxsize']) {
      $m = sprintf ($this->messages[1], $file['size'], $file['name'],
                                        $this->cfg['maxsize']);
      return $this->fail (1, $m);
    }
    return true;
  }

  /**
   * Check if the file extension is valid
   *
   * @param string $file
   * @return bool
   */
  protected function check_extension ($file)
  {
    if (!is_array ($this->cfg['allowed']) || !sizeof ($this->cfg['allowed']))
      return true;

    $ext = $this->get_extension ($file);
    if (!in_array ($ext, $this->cfg['allowed']))
      return $this->fail (2, sprintf ($this->messages[2], $ext));

    return true;
  }

  /**
   * Converts nx (where n is a number and x a suffix) into bytes.
   * e.g 1M will be 1024000, 5K will be 5210 and so on.
   *
   * @param string $max
   * @return int
   */
  protected final function make_max ($max)
  {
    // Match a size postfix
    if (preg_match ("/^(\d+)(M|K|G){0,2}b?$/i", $max, $match)) {
      switch (strtoupper ($match[2])) {
        case 'G':
          $max = $match[1] * (1024 * 1000000);
          break;

        case 'M':
          $max = $match[1] * (1024 * 1000);
          break;

        case 'K':
        default:
          $max = $match[1] * 1024;
      }
    }
    else
      if (!is_int ($max))
        $max = (int)$max;

    return $max;
  }

  /**
   * Returns the file extension of a file
   *
   * @param string $file
   * @return mixed
   */
  protected final function get_extension ($file)
  {
    if (preg_match ('/.*\.([-_\w\d]+)$/', $file, $reg)) {
      // Lets se if we have a file that might be a tarball
      if (preg_match ('/(gz|bz|bz2)$/i', $reg[1], $tmp)) {
        $tempExt = $tmp[1];
        // See if we find a real tarball
        if (preg_match ("/\.tar\./", $file))
          $reg[1] = "tar.$tempExt";
      }
    }
    else
      return false;

    return $reg[1];
  }

  /**
   * Check if the file is an uploaded file or not
   *
   * @param array $file
   * @return bool
   */
  protected final function is_uploaded_file (array $file)
  {
    if (is_uploaded_file ($file['tmp_name']))
      return true;

    return $this->fail (3, sprintf ($this->messages[3], $file['name']));
  }

  /**
   * Sets a message to the warnings array
   *
   * @param int $messageIndex
   * @param string $message
   * @return bool
   *   Always returns false
   */
  protected final function fail ($messageIndex, $message=null)
  {
    if (!$message)
      $message = $this->messages[$messageIndex];

    $this->last_message = $message;
    array_push ($this->warnings,
                array("index" => $messageIndex, "message" => $message));
    return false;
  }

  /**
   * Setter
   * Set the value of key in the config array
   *
   * @param string $key
   * @param mixed $val
   */
  public function __set ($key, $val)
  {
    if (array_key_exists ($key, $this->cfg))
      $this->cfg[$key] = $val;
  }

  /**
   * Returns the valu of key $key in the config array
   *
   * @param string $key
   * @return mixed
   */
  public function __get ($key)
  {
    if (array_key_exists ($key, $this->cfg))
      return $this->cfg[$key];

    return false;
  }
}

/**
 * Upload exception
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class UploadException extends \Exception
{
}
?>