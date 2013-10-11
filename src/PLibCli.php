<?php
/**
 * Classes for command line interface usage
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @license GPL3
 */

namespace PLib;

require_once dirname (__FILE__) . '/PLib.php';

/**
 * Reads from stdin, i.e. the keyboard in most cases.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @example
 *  $stdin = new Stdin ();
 *  $continue = null;
 *
 *  echo "Do you want to continue? [Y/n]: ";
 *
 *  while ($line = $stdin->read ()) {
 *    if (preg_match ('/y(es)?/i', $line) || empty($line))
 *      $continue = true;
 *    else
 *      $continue = false;
 *
 *    break;
 *  }
 */
class Stdin
{
  /**
   * The file handler resource
   * @var resource
   */
  protected $fh;

  /**
   * Creates a new instance of Stdin
   */
  public function __construct ()
  {
    $this->fh = fopen ('php://stdin', 'r');
  }

  /**
   * Read from stdin.
   *
   * @param bool $trim
   *   Trims the string by default
   * @return string
   */
  public function read ($trim=true)
  {
    $data = fread ($this->fh, 4096);
    return $trim ? trim ($data) : $data;
  }

  /**
   * Destructor. Closes the file handler resource
   */
  public function __destruct ()
  {
    if (is_resource ($this->fh))
      fclose ($this->fh);
  }
}

/**
 * Convenience function for Stdin
 */
function stdin ()
{
  static $c;
  if (!$c) $c = new Stdin ();
  return $c;
}

/**
 * Class for parsing command line options
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 */
class Options
{
  /**
   * Container for all options
   * @var array
   */
  private $options = array();

  /**
   * The name of the program, i.e the program being called on the CLI
   * @var string
   */
  private $programName = null;

  /**
   * The context string is given as argument to the constructor.
   * @var string
   */
  private $context = null;

  /**
   * The default help @see{Option} object.
   * @var Option
   */
  private $help = null;

  /**
   * Enable autohelp or not
   * @var bool
   */
  private $autoHelp = true;

  /**
   * Constructs a new Options object
   *
   * @param string $context
   *  Additional argument(s) that can be given after the options.
   */
  function __construct ($context=null)
  {
    $this->context = $context;
    $this->help = new Option ('help', 'h', Option::OPTIONAL, Argument::NONE,
                              'Show help options');
  }

  /**
   * Append a new @see{Option} object to this object.
   *
   * @see Option
   *
   * @param string $longopt
   *  The longopt, eg `--longopt`.
   * @param string $opt
   *  The short opt, eg `-o`.
   * @param int $flag
   *  One of the constants in @see{Option} which defines if the option
   *  is required or not.
   * @param int $type
   *  One of the constants in @see{Argument} which defines if the option
   *  requires an argument or not, or if it may have an argument.
   * @param string $description
   *  The description of the option
   * @param mixed $out
   *  This variable will be assigned the value of the option from the
   *  command line.
   *
   * @return
   *  Returns the object being called.
   */
  function option ($longopt, $opt, $flag, $type, $description, &$out=null)
  {
    $a = new Option ($longopt, $opt, $flag, $type, $description, $out);
    if ($longopt == 'help')
      $this->help = $a;
    else {
      if ($opt == 'h') $this->help->__opt('?');
      array_push ($this->options, $a);
    }

    return $this;
  }

  /**
   * Add an @see{Option} to this object
   *
   * @param Option $arg
   *
   * @return
   *  Returns the object being called
   */
  function add_option (Option $arg)
  {
    array_push ($this->options, $arg);
    return $this;
  }

  /**
   * Prints the usage details of the options
   *
   * @param bool $return
   *  If true the usage details will be returned rather than printed
   */
  function usage ($return=false)
  {
    if ($this->programName == null)
      throw new \Exception ('parse() not called in ' . get_class($this));

    $out = "Usage:\n  " . $this->programName;

    if (count ($this->options))
      $out .= " [OPTION...]";

    if ($this->context)
      $out .= " " . $this->context;

    $out .= "\n\n";

    if ($this->help)
      $out .= "Help options:\n  " . (string)$this->help;

    if (count ($this->options)) {
      $out .= "\nApplication options:\n";
      foreach ($this->options as $arg) {
        $out .= '  ' . (string)$arg;
      }
    }

    if ($return) return $out;
    echo $out . "\n";
  }

  /**
   * Parse the arguments given on the command line.
   *
   * NOTE: the arguments array will be popped of options and will just contain
   *       additional arguments given on the command line.
   *
   * @throws OptionsException
   *  If `$throw` is `true`.
   *
   * @param array $argv
   *  The arguments from the command line
   * @param bool $throw
   *  If true an exception will be thrown if the parsing fails in any way.
   *  Defaults to true
   */
  function parse (&$argv, $throw=true)
  {
    // Remove the program which is the first argument
    $this->programName = array_shift ($argv);
    $rest = array();

    if (($argc = count ($argv)) > 0) {
      for ($i = 0; $i < $argc; $i++) {
        $o = $argv[$i];
        $v = null;
        $continue = false;

        // It's an option flag
        if ($o[0] == '-') {
          if (strlen ($o) > 1) {
            // It's a long opt
            if ($o[1] == '-') {
              $o = substr ($o, 2);
            }
            else {
              $o = substr ($o, 1);
              switch (strlen ($o))
              {
                // Just a dash
                case 0:
                  if ($throw)
                    throw new OptionsException ('Malformed options flag!');
                  $continue = true;
                  break;

                // Value in next array index
                case 1:
                    /* skip */
                  break;

                // Option with value/other option appended directly
                default:
                  $tmp = $o[0];
                  $v = substr ($o, 1);
                  $tmpa = $this->find_option ($o[0]);

                  // It's a combined flag, i.e. -xyz, so lets see if yz also
                  // is options.
                  if ($tmpa && ($tmpa->type == Argument::NONE)) {
                    $tmpa->is_set = true;
                    $tmpa->set_value (true);
                    $nopts = preg_split ('//', $v, -1, PREG_SPLIT_NO_EMPTY);

                    foreach ($nopts as $nopt) {
                      if ($tmpa = $this->find_option ($nopt)) {
                        if ($tmpa->type !== Argument::NONE) {
                          if ($throw) {
                            throw new OptionsException (
                              "Option \"$nopt\" can not be combined with " .
                              "other options since it can take an argument");
                          }
                        }
                        $tmpa->is_set = true;
                        $tmpa->set_value (true);
                      }
                      else {
                        if ($throw)
                          throw new OptionsException ("Unknown option \"$nopt\"");
                      }
                    }

                    $continue = true;
                  }

                  $o = $tmp;
                  break;
              }
            }

            if ($continue)
              continue;

            if (!$v) {
              if (strpos ($o, '=') > -1)
                list ($o, $v) = explode ('=', $o);
            }
          }
          else {
            if ($throw)
              throw new OptionsException ('Malformed options flag!');
          }

          $this->parse_opt ($o, $v, $i, $argv, $throw);
        }
        // Push rest args to the new argv array
        else {
          array_push ($rest, $o);
        }
      }
    }

    foreach ($this->options as $opt) {
      if ($opt->flag == Option::REQUIRED && !$opt->is_set) {
        if ($throw)
          throw new OptionsException ("Missing required argument \"" .
                                      $opt->get_opt_names() . "\"");
      }
    }

    // Put the program name back into the argv array
    array_unshift ($rest, $this->programName);
    $argv = $rest;
  }

  /**
   * Parse an option
   *
   * @throws OptionsException
   *  If `$throw` is `true`.
   *
   * @param string $o
   *  The option name, without dashes
   * @param mixed $v
   *  The value of the option, if found prior to this method is called.
   * @param int $i
   *  The argv counter
   * @param bool $throw
   *  If true exceptions will be thrown if parsing fails.
   */
  private function parse_opt ($o, $v, &$i, &$argv, $throw)
  {
    if ($a = $this->find_option ($o)) {
      if ($v === null)  {
        if ($a->type == Argument::REQUIRED)  {
          if (!array_key_exists ($i+1, $argv)) {
            if ($throw) {
              throw new OptionsException ("Argument \"".$a->get_opt_names () .
                                          "\" is missing required value!");
            }

            return ;
          }

          $tmp = $argv[$i+1];
          if ($tmp[0] == '-') {
            if ($throw) {
              throw new OptionsException ("Argument \"".$a->get_opt_names () .
                                          "\" is missing required value!");
            }
          }

          $v = $tmp;
          $i++;
        }
        elseif ($a->type == Argument::OPTIONAL) {
          $next = $argv[$i+1];
          // If the next argument starts with a dash this argument has no
          // argument and is being used as a flag.
          if ($next && strlen ($next) && $next[0] == '-')
            $v = true;
          else {
            $i++;
            $v = $next;
          }
        }
        // It's just a flag so set it to true
        else $v = true;
      }

      $a->is_set = true;
      $a->set_value ($v);
    }
    else {
      if ($throw) throw new OptionsException ("Unknown option \"$o\"");
    }
  }

  /**
   * Find option with name `$name`.
   *
   * @param string $name
   *
   * @return
   *  An @see{Option} object if found, null otherwise.
   */
  function find_option ($name)
  {
    foreach ($this->options as $a)
      if ($a->opt == $name || $a->longopt == $name)
        return $a;

    if ($this->help->opt == $name || $this->help->longopt == $name) {
      if ($this->autoHelp) {
        $this->usage ();
        exit(0);
      }
    }

    return null;
  }
}

/**
 * Pretty much an enum. Only contains constants to be used for stateing
 * if an option needs an argument or not, or if it may have an argument.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 */
class Argument
{
  /**
   * Option doesn't take an argument
   * @const int
   */
  const NONE = 1;
  /**
   * Option needs an argument
   * @const int
   */
  const REQUIRED = 2;
  /**
   * Option may have an argument
   * @const int
   */
  const OPTIONAL = 3;
}

/**
 * Class representing an option on the command line
 *
 * @author Pontus Östlund <pontus@poppa.se>
 */
class Option
{
  /**
   * Constant stating option is optional
   * @const int
   */
  const OPTIONAL = 1;

  /**
   * Constant stating option is required
   * @const int
   */
  const REQUIRED = 2;

  /**
   * The name of the longopt
   * @var string
   */
  private $longopt;

  /**
   * The name of the short opt
   * @var string
   */
  private $opt;

  /**
   * Is th option required or not
   * @see{Option::OPTIONAL} and @see{Option::REQUIRED}
   * @var int
   */
  private $flag;

  /**
   * Does the option take, or may it take, an argument.
   * @see{Argument::REQUIRED},@see{Argument::OPTIONAL} and @see{Argument::NONE}
   * @var int
   */
  private $type;

  /**
   * HM
   */
  private $haveValue;

  /**
   * The description of the option
   * @var string
   */
  private $description;

  /**
   * The variable which will get the value set on command line
   * This is a reference to a variable set outside of this scope
   * @var mixed
   */
  private $refarg;

  /**
   * Hm
   */
  private $is_set = false;

  /**
   * Gettable object members
   * @var array
   */
  private $gettable = array(
    'longopt',
    'opt',
    'flag',
    'type',
    'description',
    'is_set'
  );

  /**
   * Settable object members
   * @var array
   */
  private $settable = array(
    'is_set'
  );

  /**
   * Creates a new Option object
   *
   * @param string $longopt
   * @param string $opt
   * @param int $flag
   *  Is the option required or not.
   *  @see{Option::REQUIRED} and @see{Option::OPTIONAL}
   * @param int $type
   *  Can the option take an argument.
   *  @see{Argument::OPTIONAL}, @see{Argument::REQUIRED} and
   *  @see{Argument::NONE}
   * @param string $desc
   *  The description of the option
   * @param mixed $refarg
   *  A reference to the variable that should be given the value set for
   *  this option on the command line
   */
  function __construct ($longopt, $opt, $flag, $type, $desc, &$refarg=null)
  {
    $this->longopt = $longopt;
    $this->opt = $opt;
    $this->flag = $flag;
    $this->type = $type;
    $this->description = $desc;
    $this->refarg = &$refarg;
  }

  /**
   * Setter for the value of the option
   *
   * @param mixed $value
   */
  function set_value ($value)
  {
    $this->refarg = $value;
  }

  /**
   * Returns a string representation of the longopt and opt of this option
   */
  function get_opt_names ()
  {
    $r = array();
    if ($this->opt) array_push ($r, "-$this->opt");
    if ($this->longopt) array_push ($r, "--$this->longopt");
    return implode ($r, ', ');
  }

  /* Internal method for setting the opt
   */
  function __opt ($n)
  {
    $this->opt = $n;
  }

  /**
   * Getter for object properties
   *
   * @throws OptionsException
   *  If the requested property doesn't exist
   *
   * @param string $which
   */
  function __get ($which)
  {
    if (in_array ($which, $this->gettable))
      return $this->{$which};

    throw new OptionsException ("Unknown object property \"$which\"");
  }

  /**
   * Setter for object properties
   *
   * @throws OptionsException
   *  If the requested property doesn't exist
   *
   * @param string $which
   * @param mixed $value
   */
  function __set ($which, $value)
  {
    if (in_array ($which, $this->settable)) {
      $this->{$which} = $value;
      return true;
    }

    throw new OptionsException ("Unknown object property \"$which\"");
  }

  /**
   * String representation of the object.
   * Only for debugging purposes
   */
  function __toString ()
  {
    $o = '';
    $a = array();

    if ($this->opt && $this->longopt)
      $o .= "-$this->opt, --$this->longopt";
    elseif ($this->opt)
      $o .= "-$this->opt";
    elseif ($this->longopt)
      $o .= "    --$this->longopt";

    return sprintf ("%-20s %s\n", $o, $this->description);
  }
}

/**
 * Options exception
 *
 * @author Pontus Östlund <pontus@poppa.se>
 */
class OptionsException extends \Exception {}

if (function_exists ('plib\main')) {
  exit (call_user_func_array ('plib\main', array(count ($argv), $argv)));
}
elseif (function_exists ('\main')) {
  exit (call_user_func_array ('main', array(count ($argv), $argv)));
}
?>