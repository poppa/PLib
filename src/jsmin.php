<?php
/**
 * Javascript minifier
 *
 * @copyright 2014 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib
 */

namespace PLib\JSMin;

use \PLib;

require_once PLIB_PATH . '/stringreader.php';
require_once PLIB_PATH . '/io.php';

function minify($data)
{
  static $o;
  if (!$o) $o = new Minifer();
  return $o->minify($data);
}

class Minifer
{
  const EOF = PLib\IStream::EOF;

  /**
   * @var \PLib\StringReader
   */
  private $input;
  /**
   * @var string
   */
  private $output;
  /**
   * @var int
   */
  private $a;
  /**
   * @var int
   */
  private $b;
  /**
   * @var int
   */
  private $x = self::EOF;
  /**
   * @var int
   */
  private $y = self::EOF;
  /**
   * @var int
   */
  private $lookahead = self::EOF;

  /**
   * Minify $data
   *
   * @param string|\PLib\File $data
   */
  public function minify($data)
  {
    if ($data instanceof Plib\File) {
      $data = $data->get_contents();
    }

    $this->input = new PLib\StringReader($data);
    $this->output = '';

    $this->jsmin();

    $this->a = null;
    $this->b = null;
    $this->x = self::EOF;
    $this->y = self::EOF;
    $this->input = null;

    $tmp = $this->output;
    $this->output = null;

    return substr($tmp, 1);
  }

  private function jsmin()
  {
    if ($this->peek() == 0xEF) {
      $this->get();
      $this->get();
      $this->get();
    }

    $this->a = "\n";

    $this->action(3);

    while ($this->a !== self::EOF) {
      switch ($this->a) {
        case ' ':
          $this->action($this->is_alnum($this->b) ? 1 : 2);
          break;

        case "\n":
            switch ($this->b) {
              case '{':
              case '[':
              case '(':
              case '+':
              case '-':
              case '!':
              case '~':
                $this->action(1);
                break;

              case ' ':
                $this->action(3);
                break;

              default:
                $this->action($this->is_alnum($this->b) ? 1 : 2);
            }
            break;

        default:
          switch ($this->b) {
            case ' ':
              $this->action($this->is_alnum($this->a) ? 1 : 3);
              break;

            case "\n":
              switch ($this->a) {
                case '}':
                case ']':
                case ')':
                case '+':
                case '-':
                case '"':
                case '\'':
                case '`':
                  $this->action(1);
                  break;

                default:
                  $this->action($this->is_alnum($this->a) ? 1 : 3);
              }
              break;

            default:
              $this->action(1);
              break;
          }
      }
    }
  }

  private function peek()
  {
    return $this->lookahead = $this->get();
  }

  private function get()
  {
    $c = $this->lookahead;
    $this->lookahead = self::EOF;

    if ($c === self::EOF)
      $c = $this->input->read();

    //PLib\debug('$c = ', $c);

    if ($c > ' ' || $c == "\n" || $c === self::EOF)
      return $c;

    if ($c == "\r")
      return "\n";

    return ' ';
  }

  private function next()
  {
    $c = $this->get();
    $keep;

    if ($c == '/') {
      switch ($this->peek()) {
        case '/':
          for (;;) {
            $c = $this->get();

            if ($c <= "\n") {
              break;
            }
          }
        break;

        case '*':
          $this->get();
          $keep = $this->peek() == '!';

          // A /*! yadda */ comment block
          if ($keep) {
            // Add the previous char
            $this->add($this->a);

            // Add a newline before the comment if needed
            if ($this->a != "\n")
              $this->add("\n");

            $this->add("/*");
          }

          while ($c != ' ') {
            $cc = $this->get();

            if ($keep) {
              $this->add($cc);
            }

            switch ($cc) {
              case '*':
                if ($this->peek() == '/') {
                  $this->get();

                  if ($keep) {
                    $this->add('/');
                    $this->a = "\n";
                    $this->action(3);
                  }

                  $c = ' ';
                }
              break;

              case self::EOF:
                throw new \Exception("Unterminated comment.");
            }
          }
        break;
      }
    }

    $this->y = $this->x;
    $this->x = $c;

    return $c;
  }

  private function action($d)
  {
    switch ($d) {
      case 1:
        $this->add($this->a);

        if (($this->y == "\n" || $this->y == ' ') &&
            ($this->a == '+'  || $this->a == '-'  || $this->a == '*' ||
             $this->a == '/') &&
            ($this->b == '+'  || $this->b == '-'  || $this->b == '*' ||
             $this->b == '/'))
        {
          $this->add($this->y);
        }
        /* fall through */
      case 2:
        $this->a = $this->b;

        if ($this->a == '\'' || $this->a == '"' || $this->a == '`') {
          for (;;) {
            $this->add($this->a);
            $this->a = $this->get();

            if ($this->a === $this->b) {
              break;
            }

            if ($this->a == '\\') {
              $this->add($this->a);
              $this->a = $this->get();
            }

            if ($this->a === self::EOF) {
              throw new \Exception("Unterminated string literal.");
            }
          }
        }
        /* fall through */
      case 3:
        $this->b = $this->next();

        if ($this->b == '/' && (
            $this->a == '(' || $this->a == ',' || $this->a == '=' ||
            $this->a == ':' || $this->a == '[' || $this->a == '!' ||
            $this->a == '&' || $this->a == '|' || $this->a == '?' ||
            $this->a == '+' || $this->a == '-' || $this->a == '~' ||
            $this->a == '*' || $this->a == '/' || $this->a == '{' ||
            $this->a == "\n"))
        {
          $this->add($this->a);

          if ($this->a == '/' || $this->a == '*') {
            $this->add(' ');
          }

          $this->add($this->b);

          for (;;) {
            $this->a = $this->get();

            if ($this->a == '[') {
              for (;;) {
                $this->add($this->a);
                $this->a = $this->get();

                if ($this->a == ']') {
                  break;
                }

                if ($this->a == '\\') {
                  $this->add($this->a);
                  $this->a = $this->get();
                }

                if ($this->a === self::EOF) {
                  $msg = "Unterminated set in Regular Expression literal.";
                  throw new \Exception($msg);
                }
              }
            }
            else if ($this->a == '/') {
              switch ($this->peek()) {
                case '/':
                case '*':
                  $msg = "Unterminated set in Regular Expression literal.";
                  throw new \Exception($msg);
              }

              break;
            }
            else if ($this->a =='\\') {
              $this->add($this->a);
              $this->a = $this->get();
            }

            if ($this->a === self::EOF) {
              throw new \Exception("Unterminated Regular Expression literal.");
            }

            $this->add($this->a);
          }

          $this->b = $this->next();
        }
    }
  }

  private function add($c)
  {
    $this->output .= $c;
  }

  private function is_alnum($c)
  {
    return (($c >= 'a' && $c <= 'z') || ($c >= '0' && $c <= '9') ||
            ($c >= 'A' && $c <= 'Z') || $c == '_'  || $c == '$'  ||
             $c == "\\" || $c > 126);
  }
}
?>