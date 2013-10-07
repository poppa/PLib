<?php
/**
 * A set of classes to work with date and time
 *
 * @author Pontus Östlund <poppanator@gmail.com>
 * @license GPL License 3
 */

namespace PLib;

/**
 * Date class
 *
 * @author Pontus Östlund <poppanator@gmail.com>
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
  protected $ymd_short = null;

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
    'unixtime'  => 'Unix timestamp',
    'year'      => 'Year',
    'month'     => 'Month number (1-12)',
    'week'      => 'Week number',
    'date'      => 'Date (1-31)',
    'time'      => 'Time (12:00:00)',
    'timezone'  => 'Time zone',
    'ymd'       => 'ISO formatted date (YYYY-MM-DD HH:MM:SS)',
    'ymd_short' => 'ISO formatted date (YYYY-MM-DD)',
    'days'      => 'Days in month (28-31)',
    'hour'      => 'Hour',
    'minute'    => 'Minute',
    'second'    => 'Second',
    'wday'      => 'Day of week',
    'yday'      => 'Day of year',
    'dst'       => 'Daylight savings'
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
  public function __construct ($date=null)
  {
    $this->init ($date);
  }

  protected function init ($date)
  {
    if (is_string ($date))
      $this->unixtime = strtotime ($date, time());
    elseif (is_int ($date))
      $this->unixtime = $date;
    else
      $this->unixtime = time ();

    $this->week = date ('W');
    $this->dst  = (bool)date ('I', $this->unixtime);
    $this->days = date ('t', $this->unixtime);
    $this->iso  = strftime (Date::$STRING_ISO, $this->unixtime);
    $this->ymd  = strftime ('%Y-%m-%d %T', $this->unixtime);

    list ($this->ymd_short, $this->time) = explode (' ', $this->ymd);

    list ($this->wday, $this->yday) =
      explode (' ', date('w z', $this->unixtime));

    list ($this->year, $this->month, $this->date) =
      explode ('-',$this->ymd_short);

    list ($this->hour, $this->minute, $this->second) =
      explode (':', $this->time);

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
  public function add_years ($num)
  {
    $t = mktime ($this->hour, $this->minute, $this->second, $this->month,
                 $this->date, $this->year+$num);

    $this->init ($t);
    return $this;
  }

  /**
   * Add n number of months to the current date
   *
   * @param int $num
   * @return Date
   * @since 0.2
   */
  public function add_months ($num)
  {
    $this->init (strtotime ("+$num month", $this->unixtime));
    return $this;
  }

  /**
   * Add n number of days to the current date
   *
   * @param int $num
   * @return Date
   * @since 0.2
   */
  public function add_days ($num)
  {
    $this->init (strtotime ("+$num day", $this->unixtime));
    return $this;
  }

  /**
   * Add n number of hours to the current date
   *
   * @param int $num
   * @return Date
   * @since 0.2
   */
  public function add_hours ($num)
  {
    $this->init (strtotime ("+$num hour", $this->unixtime));
    return $this;
  }

  /**
   * Add n number of minutes to the current date
   *
   * @param int $num
   * @return Date
   * @since 0.2
   */
  public function add_minutes ($num)
  {
    $this->init (strtotime ("+$num minute", $this->unixtime));
    return $this;
  }

  /**
   * Add n number of seconds to the current date
   *
   * @param int $num
   * @return Date
   * @since 0.2
   */
  public function add_seconds ($num)
  {
    $this->init (strtotime ("+$num minute", $this->unixtime));
    return $this;
  }

  /**
   * Format the date. Like {@see strftime()}
   *
   * @param string $format
   * @return string
   */
  public function format ($format)
  {
    return strftime ($format, $this->unixtime);
  }

  /**
   * Returns approximatley one year from now as a unix timestamp
   *
   * @return int
  */
  public static function unix_year ()
  {
    return time () + (3600*24*7*52);
  }

  /**
   * Returns approximatley one week from now as a unix timestamp
   *
   * @return int
   */
  public static function unix_month ()
  {
    return time () + (3600*24*7*30);
  }

  /**
   * Returns one week from now as a unix timestamp
   *
   * @return int
   */
  public static function unix_week () {
    return time () + (3600*24*7);
  }

  /**
   * Magic PHP getter (see
   * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
   *
   * @param string $key
   * @return mixed
   */
  public function __get ($index)
  {
    if (isset ($this->{$index}))
      return $this->{$index};

    throw new \Exception ("Unknown property \"$index\"!");
  }

  /**
   * Magic PHP method (see
   * {@link http://php.net/manual/en/language.oop5.magic.php Magic methods}).
   *
   * @return string
   */
  public function __toString ()
  {
    return strftime (self::$STRING_FORMAT, $this->unixtime);
  }
}
?>