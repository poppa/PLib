<?php
/**
 * A set of classes to work with date and time
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @license GPL License 2
 * @version 0.2
 * @package Calendar
 */

/**
 * Date version constant
 */
define('PLIB_DATE_VERSION', '0.1');

/**
 * Internal support for the ISO-8601 date standard was added in PHP 5.1.
 * (Some fetures of 8601 was added earlier...)
 */
define('DATE_8601_COMPAT', version_compare(PHP_VERSION, '5.0', '>'));

/**
 * At the moment pretty much just a meta class...
 * More work will be done!
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Calendar
 * @subpackage DateTime
 */
class Date
{
	/**
	 * One day in seconds
	 */
	const UNIX_DAY = 86400;
	/**
	 * One week in seconds
	 */
	const UNIX_WEEK = 604800;
  /**
   * Bitflag for ISO_8601 compatibility
   */
	const ISO_8601 = 1;
  /**
   * Bitflag for LITTLE ENDIAN date (Not in use)
   */
	const LITTLE_ENDIAN = 2;

	/**
	 * Meaning monday is the first day of the week
	 * @var string
	 */
	public static $DATE_TYPE = Date::ISO_8601;
  /**
   * Default string format
   * @var string
   */
	public static $STRING_FORMAT = '%H:%M, %a %d %B %Y';
  /**
   * Default ISO string format
   * @var string
   */
	public static $STRING_ISO = '%a %d %B %Y';
  /**
   * Date as a unix time stamp
   * @var int
   */
	protected $unixtime = 0;
  /**
   * Year
   * @var int
   */
	protected $year = 0;
  /**
   * Month
   * @var int
   */
	protected $month = 0;
  /**
   * Week
   * @var int
   */
	protected $week = 0;
  /**
   * Date
   * @var string
   */
	protected $date = null;
  /**
   * Time
   * @var string
   */
	protected $time = null;
  /**
   * Time zone
   * @var string
   */
	protected $timezone = null;
  /**
   * ISO formatted date (YYYY-MM-DD HH:MM:SS)
   * @var string
   */
	protected $ymd = null;
  /**
   * Short ISO formatted date (YYYY-MM-DD)
   * @var string
   */
	protected $ymdShort = null;
  /**
   * Days in month
   * @var int
   */
	protected $days = 0;
  /**
   * Hour
   * @var int
   */
	protected $hour = 0;
  /**
   * Minute
   * @var int
   */
	protected $minute = 0;
  /**
   * Second
   * @var int
   */
	protected $second = 0;
  /**
   * Day of week
   * @var int
   */
	protected $wday = 0;
  /**
   * Day of year
   * @var int
   */
	protected $yday = 0;
  /**
   * Daylight savings
   * @var bool
   */
	protected $dst = false;
  /**
   * Member descriptions
   * @var array
   */
  protected $gettables = array(
    'unixtime' => 'Unix timestamp',
    'year'     => 'Year',
    'month'    => 'Month number (1-12)',
    'week'     => 'Week number',
    'date'     => 'Date (1-31)',
    'time'     => 'Time (12:00:00)',
    'timezone' => 'Time zone',
    'ymd'      => 'ISO formatted date (YYYY-MM-DD HH:MM:SS)',
    'ymdShort' => 'ISO formatted date (YYYY-MM-DD)',
    'days'     => 'Days in month (28-31)',
    'hour'     => 'Hour',
    'minute'   => 'Minute',
    'second'   => 'Second',
    'wday'     => 'Day of week',
    'yday'     => 'Day of year',
    'dst'      => 'Daylight savings'
  );

	/**
	 * Constructor
	 *
	 * @param string|int $date
	 *   Either a string date or a unix timestamp
	 * @param bool $dst
	 *   If set to false dayligt saving time will be discarted, i.e one hour
	 *   will be cut off.
	 */
	public function __construct($date=null)
	{
		if (is_string($date))
			$this->unixtime = strtotime($date, time());
		elseif (is_int($date))
			$this->unixtime = $date;
		else
			$this->unixtime = time();

		$this->week = date('W');
		$this->dst  = (bool)date('I', $this->unixtime);
		$this->days = date('t', $this->unixtime);
		$this->iso  = strftime(Date::$STRING_ISO, $this->unixtime);
		$this->ymd  = strftime('%Y-%m-%d %T', $this->unixtime);

		list($this->ymdShort, $this->time) = explode(' ', $this->ymd);
		list($this->wday, $this->yday) = explode(' ', date('w z', $this->unixtime));
		list($this->year, $this->month, $this->date) = explode('-',$this->ymdShort);
		list($this->hour, $this->minute, $this->second) = explode(':', $this->time);

		if (self::$DATE_TYPE == Date::ISO_8601 && $this->wday == 0)
			$this->wday = 7;
	}

	/**
	 * Add n number of years to the current date
	 *
	 * @param int $num
	 * @return Date
	 * @since 0.2
	 */
	public function AddYears($num)
	{
		$t = mktime($this->hour, $this->minute, $this->second, $this->month,
		            $this->date, $this->year+$num);

		return new Date($t);
	}

	/**
	 * Add n number of months to the current date
	 *
	 * @param int $num
	 * @return Date
	 * @since 0.2
	 */
	public function AddMonths($num)
	{
		$t = strtotime("+$num month", $this->unixtime);
		return new Date($t);
	}

	/**
	 * Add n number of days to the current date
	 *
	 * @param int $num
	 * @return Date
	 * @since 0.2
	 */
	public function AddDays($num)
	{
		$t = strtotime("+$num day", $this->unixtime);
		return new Date($t);
	}

	/**
	 * Add n number of hours to the current date
	 *
	 * @param int $num
	 * @return Date
	 * @since 0.2
	 */
	public function AddHours($num)
	{
		$t = strtotime("+$num hour", $this->unixtime);
		return new Date($t);
	}

	/**
	 * Add n number of minutes to the current date
	 *
	 * @param int $num
	 * @return Date
	 * @since 0.2
	 */
	public function AddMinutes($num)
	{
		$t = strtotime("+$num minute", $this->unixtime);
		return new Date($t);
	}

	/**
	 * Add n number of seconds to the current date
	 *
	 * @param int $num
	 * @return Date
	 * @since 0.2
	 */
	public function AddSeconds($num)
	{
		$t = strtotime("+$num minute", $this->unixtime);
		return new Date($t);
	}

	/**
	 * Format the date. Like {@see strftime()}
	 *
	 * @param string $format
	 * @return string
	 */
	public function Format($format)
	{
		return strftime($format, $this->unixtime);
	}

	/**
	 * Returns approximatley one year from now as a unix timestamp
	 *
	 * @return int
	*/
	public static function UnixYear()
	{
		return time() + (3600*24*7*52);
	}

	/**
	 * Returns approximatley one week from now as a unix timestamp
	 *
	 * @return int
	 */
	public static function UnixMonth()
	{
		return time() + (3600*24*7*30);
	}

	/**
	 * Returns one week from now as a unix timestamp
	 *
	 * @return int
	 */
	public static function UnixWeek()
	{
		return time() + (3600*24*7);
	}

	/**
	 * Magic PHP getter (see
	 * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get($index)
	{
		if (isset($this->{$index}))
			return $this->{$index};

		return false;
	}

	/**
	 * Magic PHP method (see
	 * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
	 *
	 * @return string
	 */
	public function __toString()
	{
		return strftime(Date::$STRING_FORMAT, $this->unixtime);
	}
}
?>