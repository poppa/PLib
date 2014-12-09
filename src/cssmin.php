<?php
/**
 * CSS minifier
 *
 * @copyright 2014 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @link      https://github.com/poppa/PLib
 * @license   http://opensource.org/licenses/GPL-3.0 GPL License 3
 * @package   PLib
 */

namespace PLib\CSSMin;

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

}
?>