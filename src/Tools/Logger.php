<?php
/**
 * A log writer utility
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 * @version 0.2
 */

/**
 * We need the {@see File} class.
 */
require_once PLIB_INSTALL_DIR . '/IO/StdIO.php';
/**
 * We need the {@see Date} class.
 */
require_once PLIB_INSTALL_DIR . '/Calendar/Date.php';

/**
 * A simple log writer.
 *
 * <code>
 * // Write to the default system log
 * $logger = new Logger();
 * $logger->Log('Some message');
 * $logger->Notice('Some notice');   // Message prefixed with [notice]
 * $logger->Warning('Some warning'); // Message prefixed with [warning]
 * $logger->Error('Some error');     // Message prefixed with [error]
 * $logger->Debug('Some warning');   // Only output if PLib is in debug mode
 *
 * // Write to user defined log file
 * $logger = new Logger(Logger::LOG_FILE, '/path/to/file.log');
 *
 * // Send by mail
 * $logger = new Logger(Logger::LOG_EMAIL, 'me@domain.com');
 * </code>
 *
 * @author poppa
 * @package IO
 */
class Logger
{
  /**
   * Log message is sent to PHP's system logger
   * @var int
   */
  const LOG_SYSTEM = 0;
  /**
   * Send log messages by email
   * @var int
   */
  const LOG_EMAIL  = 1;
  /**
   * Log messages to a user defined file
   * @var int
   */
  const LOG_FILE   = 3;
  /**
   * Message is sent to the SAPI logging handler
   * @var int
   */
  const LOG_SAPI   = 4;
  /**
   * When the last message was written to the log
   * @var Date
   */
  protected $lastWrite = null;
  /**
   * Logging method
   * @var int
   */
  protected $type = null;
  /**
   * Where to send the messages. Depends on the value of {@see Logger::$type}.
   * @var string
   */
  protected $dest = null;
  /**
   * Extra headers to append if {@see Logger::$type} is
   * {@see Logger::TYPE_EMAIL}
   * @var string
   */
  protected $extraHeaders = null;
  /**
   * Should the message be formatted or not
   * @var bool
   */
  public $formatOutput = true;

  /**
   * Creates a new logger object.
   * For further description of the parameters see {@link error_log error_log()}
   *
   * @param int $type
   *  Type of logging mechanism to use
   * @param string|File $dest
   *  Where to send the message. This depends on $type.
   * @param string $extraHeaders
   *  Only useful when message type is {@see Logger::LOG_MAIL}.
   * @param string $truncate
   *  If true and `$type` is `Logger::LOG_FILE` the file will be truncated
   */
  public function __construct($type=Logger::LOG_SYSTEM, $dest=null,
                              $extraHeaders=null, $truncate=false)
  {
    $this->type = $type;

    if ($type == self::LOG_EMAIL) {
      $this->dest = $dest;
      $this->extraHeaders = $extraHeaders;
    }
    elseif ($type == self::LOG_FILE) {
      if (!$dest) {
        throw new Exception("No file to write messages to is provided to " .
                            "Logger::__construct()!");
      }

      if (!($dest instanceof File))
        $dest = File::Create($dest);

      if (!$dest->IsWritable())
        throw new Exception("\"$dest\" is not writable!");

			if ($truncate)
				$dest->Truncate();

      $this->dest = $dest;
    }
  }

  /**
   * Write `$message` to the log file
   *
   * @param string $message
   */
  public function Log($message)
  {
    $args = func_get_args();
    if (sizeof($args) > 0) {
      //$message = array_shift($args);
      $message = $this->getMessage($args);
    }

    error_log($message, $this->type, $this->dest,
              $this->extraHeaders);
  }

  /**
   * Write to log file only when {@see PLib} is in debug mode.
   *
   * @see PLib::Debug()
   * @param string $args
   *  Variable length argument list
   */
  public function Debug($args)
  {
    if (PLib::Debug()) {
      $args = func_get_args();
      $msg = call_user_func_array(array($this, 'getMessage'), $args);
      $this->Log($this->prefix() . " [debug] $msg");
    }
  }

  /**
   * Write to logfile. Prepends the message with `NOTICE`
   *
   * @param string $args
   *  Variable length argument list
   */
  public function Notice($args)
  {
    $args = func_get_args();
    $msg = call_user_func_array(array($this, 'getMessage'), $args);
    $this->Log("[notice] $msg");
  }

  /**
   * Write to logfile. Prepends the message with `WARNING`
   *
   * @param string $args
   *  Variable length argument list
   */
  public function Warning($args)
  {
    $args = func_get_args();
    $msg = call_user_func_array(array($this, 'getMessage'), $args);
    $this->Log("[warning] $msg");
  }

  /**
   * Write to logfile. Prepends the message with `ERROR`
   *
   * @param string $args
   *  Variable length argument list
   */
  public function Error($args)
  {
    $args = func_get_args();
    $msg = call_user_func_array(array($this, 'getMessage'), $args);
    $this->Log("[error] $msg");
  }

  /**
   * Formats the message to write to the logfile
   * @return string
   */
  protected function getMessage($args)
  {
  	$msg = null;
  	if (is_array($args) && sizeof($args)) {
		  $msg = array_shift($args);
		  if (sizeof($args))
		    $msg = $this->format(vsprintf($msg, $args));
		  else
		    $msg = $this->format($msg);
		 }
		 else $msg = $this->format($args);

    return $msg;
  }

  /**
   * Formats the string if formatting is on
   * 
   * @param string $str
   * @return string
   */
  protected function format($str)
  {
    if ($this->formatOutput === true) {
      $o = array();

      foreach (explode("\n", $str) as $line)
	$o[] = $this->prefix() . $line;
      $str = rtrim(join("\n", $o))."\n";
    }

    return $str;
  }

  /**
   * Get the message prefix, i.e the date.
   * 
   * @return string
   */
  protected function prefix()
  {
    $msg = '';
    $now = new Date();

    if ($this->formatOutput) {
      if ($this->lastWrite == null) {
        $msg = '[' . $now->Format('%Y-%m-%d %T') . "] ";
      }
      else {
        if (($now->unixtime - $this->lastWrite->unixtime) > 1)
          $msg = '[' . $now->Format('%Y-%m-%d %T') . "] ";
        else
          $msg = '                      ';
      }
      $this->lastWrite = $now;
    }

    return $msg;
  }
}
?>
