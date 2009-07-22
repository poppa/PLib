<?php
/**
 * Helper methods for "Advanced Data Types"
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.2
 * @license GPL License 2
 * @package ADT
*/

define('PLIB_ADT_VERSION', '0.2');

/**
 * ADT master class
 *
 * @author Pontus �stlund <spam@poppa.se>
 * @version 0.2
 * @package ADT
*/
class ADT
{
	const KEYS        = 0;
	const KEYS_VALUES = 1;
	const VALUES      = 2;

	/**
	 * Like {@link array_map array_map()} but works on assoc and multidim arrays
	 *
	 * <code>
	 * $array = array('KEY1' => 'A value', 'KEY2' => 'My name in Pontus');
	 * $lcAll = ADT::Mmap('strtolower', $array);
	 * # $lcAll > array('key1' => 'a value', 'key2' => 'my name is pontus');
	 *
	 * $lcKeys = ADT:Mmap('strtolower', $array, 0);
	 * # $lcKeys > array('key1' => 'A value', 'key2' => 'My name in Pontus');
	 * </code>
	 *
	 * @param string $cb
	 *   Callback function to pass the elements to
	 * @param array|object $array
	 * @param int $type
	 *   Defines whether to only pass the keys, pass both keys and values or only
	 *   values to the callback:
	 *
	 *   * 0 = Only pass keys
	 *   * 1 = Pass both keys and values
	 *   * 2 = Only pass value
	 *
	 * @return array
	*/
	public static function Mmap($cb, $array, $type=1)
	{
		$a = array();
		$k = null;
		$v = null;
		foreach ($array as $key => $val) {
			switch ($type) {
				case 0:
					$k = call_user_func($cb, $key);
					$v = $val;
					break;

				case 1:
					$k = call_user_func($cb, $key);
					$v = is_array($val) ? ADT::Mmap($cb, $val, $type) :
					                      call_user_func($cb, $val);
					break;

				case 2:
					$k = $key;
					$v = is_array($val) ? ADT::Mmap($cb, $val, $type) :
					                      call_user_func($cb, $val);
					break;
			}
			$a[$k] = $v;
		}
		return $a;
	}

	/**
	 * Uniqueifies an array with associative arrays by the key $key.
	 * This method works destructivley on the array $array.
	 *
	 * <code>
	 *   $a = array(
	 *     array('type' => 1, 'name' => 'Test 1'),
	 *     array('type' => 2, 'name' => 'Test 2'),
	 *     array('type' => 1, 'name' => 'Test 3')
	 *   );
	 *
	 *   ADT::Unique2($a, 'type');
	 *
	 *   print_r($a);
	 *
	 *   Array
	 *   (
   *       [0] => Array
   *       (
   *            [type] => 1
   *            [name] => Test 1
   *        )
   *
   *       [1] => Array
   *       (
   *            [type] => 2
   *            [name] => Test 2
   *       )
   *   )
	 * </code>
	 *
	 * @param array $array
	 * @param string|int $key
	*/
	public static function Unique2(array &$array, $key)
	{
		$k = array();
		$r = array();

		foreach ($array as $v) {
			if (is_object($v))
				$v = (array)$v;

			if (!isset($v[$key]))
				continue;

			if (in_array($v[$key], $k))
				continue;

			$k[] = $v[$key];
			$r[] = $v;
		}

		$array = $r;
	}

	/**
	 * Pops the index with value $value from array $array and returns the value.
	 *
	 * <code>
	 * $arr = array("One", "Two", "Three", "Four");
	 * $two = ADT::PopValue($arr, "Two");
	 * echo $two;
	 * # $two > "Two"
	 * print_r($arr);
	 * # $arr > array("One", "Three", "Four")
	 * </code>
	 *
	 * @param array $array
	 * @param mixed $value
	 * @return string
	*/
	public static function PopValue(array &$array, $value)
	{
		$ret = null;
		if (in_array($value, $array)) {
			foreach ($array as $i => $item) {
				if ($item == $value) {
					unset($array[$i]);
					return $value;
				}
			}
		}
		return $ret;
	}
}
?>