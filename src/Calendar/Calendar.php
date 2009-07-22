<?php
/**
 * Calendar
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Calendar
 * @uses PLibIterator
 * @uses Date
*/

/**
 * We need the {@see PlibIterator} class
 */
require_once PLIB_INSTALL_DIR . '/Core/Iterator.php';
/**
 * We need the {@see Date} class
 */
require_once PLIB_INSTALL_DIR . '/Calendar/Date.php';

/**
 * A simple calendar
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Calendar
*/
class Calendar extends PLibIterator
{
	/**
	 * Time zone
	 * @var string
	*/
	protected static $timezone = 'GMT';
	/**
	 * Storage for events
	 * @var array
	*/
	protected $events = array();
	/**
	 * The current day
	 * @var Date
	*/
	public $today;
	/**
	 * The currently selected month
	 * @var Date
	*/
	protected $now;
	/**
	 * The from date
	 * @var Date
	*/
	protected $from;
	/**
	 * The to date
	 * @var Date
	*/
	protected $to;
	/**
	 * The current timestamp in the iterator
	 * @var int
	*/
	protected $ts;
	/**
	 * The last timestamp
	 *
	 * @var int
	*/
	protected $tsEnd;
	/**
	 * Compensate daylight savings time
	 * @var bool
	*/
	protected $dstCompensate = false;

	/**
	 * Constructor
	 *
	 * @param Date $from
	*/
	public function __construct(Date $from, $firstdayofweek=1)
	{
		if ($firstdayofweek == 0)
			Date::$DATE_TYPE = Date::LITTLE_ENDIAN;

		$this->today = new Date(null, false);
		$this->from  = new Date("{$from->year}-{$from->month}-01T05:00", false);
		$this->now   = $this->from;
		$this->to    = $this->from->AddMonths(1)->AddDays(-1);

		if ($this->from->wday != 1) {
			$ndays = -$this->from->wday+1;
			$this->from = $this->from->AddDays($ndays);
		}

		if ($this->to->wday != 7) {
			$ndays = 7-$this->to->wday;
			$this->to = $this->to->AddDays($ndays);
		}

		$this->ts    = $this->from->unixtime;
		$this->tsEnd = $this->to->unixtime;
	}

	public function Format($format)
	{
		return strftime($format, $this->now->unixtime);
	}

	/**
	 * Set events
	 *
	 * @param array $events
	*/
	public function SetEvents(array $events)
	{
		foreach ($events as $event)
			$this->events[] = strtotime($event)+(3600*5);
	}

	/**
	 * See {@see PLibIterator::HasNext()}
	 *
	 * @return bool
	*/
	public function HasNext()
	{
		return $this->ts <= $this->tsEnd + ($this->dstCompensate ? 3600 : 0);
	}

	/**
	 * See {@see PLibIterator::Next()}
	 *
	 * @return CalendarDate
	*/
	public function Next()
	{
		$r = new CalendarDate($this->ts);
		if ($r->ymdShort == $this->today->ymdShort)
			$r->today = true;

		if (in_array($this->ts, $this->events))
			$r->hasEvent = true;

		if ($r->month != $this->now->month)
			$r->leaf = true;

		if ($r->dst)
			$this->dstCompensate = true;
		else
			$this->dstCompensate = false;

		$this->ts += Date::UNIX_DAY;

		return $r;
	}

	/**
	 * Are we about to hit the first iteration or just hit the first iteration
	 *
	 * @return bool
	*/
	public function First()
	{
		return $this->ts <= $this->from->unixtime;
	}

	/**
	 * Is it the last iteration
	 *
	 * @return bool
	*/
	public function Last()
	{
		return $this->ts + Date::UNIX_DAY >= $this->tsEnd;
	}

	/**
	 * Converts an instance of {@see DBResult} into the internal events array.
	 * The db result set sould contain a field named "compare_date" which
	 * should be the actual date to compare against.
	 *
	 * @param DBResult $obj
	*/
	public function DBResult2Events(DBResult $obj)
	{
		while ($row = $obj->Fetch()) {
			$this->events[] = is_int($row->compare_date) ? $row->compare_date :
			                              strtotime($row->compare_date, time());
		}
	}
}


/**
 * Pretty much identical to {@see Date} except that a few special members
 * is added.
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Calendar
 * @depends Date
*/
class CalendarDate extends Date
{
	public $today    = false;
	public $hasEvent = false;
	public $leaf     = false;

	public function __construct($date)
	{
		parent::__construct($date);
	}
}
?>