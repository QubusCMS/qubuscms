<?php
namespace TriTan\Common;

final class Date implements \TriTan\Interfaces\DateInterface
{
    protected $time;

    protected $timezone;

    protected $locale;

    protected $date;

    /**
     * Returns new Datetime object.
     *
     * @since 1.0.0
     * @param string|int $time
     * @param string|DateTimeZone $timezone
     * @param string $locale
     */
    public function __construct($time = null, $timezone = null, string $locale = null)
    {
        $this->time = $time;
        $this->timezone = $timezone;
        $this->locale = null === $locale ? 'en' : $locale;
        $this->date = new \Jenssegers\Date\Date($this->time, $this->timezone);
    }

    public function getDate()
    {
        return $this->date;
    }

    /**
     * Sets the timezone.
     *
     * @since 1.0.0
     * @param string|DateTimeZone $timezone
     */
    public function setTimezone($timezone)
    {
        return $this->date->setTimezone($timezone);
    }

    /**
     * Returns minute in seconds.
     *
     * @since 1.0.0
     * @return int
     */
    public static function minuteInSeconds()
    {
        return 60;
    }

    /**
     * Returns hour in seconds.
     *
     * @since 1.0.0
     * @return int
     */
    public static function hourInSeconds()
    {
        return 60 * static::minuteInSeconds();
    }

    /**
     * Returns day in seconds.
     *
     * @since 1.0.0
     * @return int
     */
    public static function dayInSeconds()
    {
        return 24 * static::hourInSeconds();
    }

    /**
     * Returns week in seconds.
     *
     * @since 1.0.0
     * @return int
     */
    public static function weekInSeconds()
    {
        return 7 * static::dayInSeconds();
    }

    /**
     * Returns month in seconds.
     *
     * @since 1.0.0
     * @return int
     */
    public static function monthInSeconds()
    {
        return date('t') * static::dayInSeconds();
    }

    /**
     * Returns year in seconds.
     *
     * @since 1.0.0
     * @return int
     */
    public static function yearInSeconds()
    {
        return (date('z', mktime(0, 0, 0, 12, 31, date('Y'))) + 1) * static::dayInSeconds();
    }

    /**
     * Formats date.
     *
     * This function uses the set timezone from TriTan options.
     *
     * Example Usage:
     *
     *      $datetime = 'May 15, 2018 2:15 PM';
     *      $this->format('Y-m-d H:i:s', $datetime);
     *
     * @since 1.0.0
     * @param string $format    Format of the date. Default is `Y-m-d H:i:s`.
     * @return string
     */
    public function format(string $format = 'Y-m-d H:i:s')
    {
        return $this->date->format($format);
    }

    /**
     * Format a GMT/UTC date/time
     *
     * @since 1.0.0
     * @param string $date      Date to be formatted. Default is `now`.
     * @param string $format    Format of the date. Default is `Y-m-d H:i:s`.
     * @return string Formatted date string.
     */
    public function gmtdate(string $date = 'now', string $format = 'Y-m-d H:i:s')
    {
        if ($date === 'now') {
            $string = (string) $this->date->now(new \DateTimeZone('GMT'))->format($format);
        } else {
            $string = str_replace(['AM', 'PM'], '', $date);
            $string = (string) $this->date->parse(strtotime($string), new \DateTimeZone('GMT'))->format($format);
        }
        return $string;
    }

    /**
     * Returns the date in localized format.
     *
     * @since 1.0.0
     * @return object Returns current localized datetime.
     */
    public function locale()
    {
        $timestamp = $this->date;
        $timestamp->setLocale($this->locale);
        return $timestamp;
    }

    /**
     * Converts given date string into a different format.
     *
     * $format should be either a PHP date format string, e.g. 'U' for a Unix
     * timestamp, or 'G' for a Unix timestamp assuming that $date is GMT.
     *
     * If $translate is true, then the given date and format string will
     * be passed to $this->locale() for translation.
     *
     * @since 1.0.0
     * @param string $format  Format of the date to return.
     * @param string $date    Date string to convert.
     * @param bool $translate Whether the return date should be translated. Default true.
     * @return string|int|bool Formatted date string or Unix timestamp. False if $date is empty.
     */
    public function db2Date(string $format, string $date, bool $translate = true)
    {
        if (empty($date)) {
            return false;
        }
        if ('G' == $format) {
            return strtotime($date . ' +0000');
        }
        if ('U' == $format) {
            return strtotime($date);
        }
        if ($translate) {
            return $this->locale()->parse($date)->format($format);
        } else {
            return $this->date->parse($date)->format($format);
        }
    }

    /**
     * Returns the current time based on specified type.
     *
     * The 'db' type will return the time in the format for database date field(s).
     * The 'timestamp' type will return the current timestamp.
     * Other strings will be interpreted as PHP date formats (e.g. 'Y-m-d H:i:s').
     *
     * If $gmt is set to either '1' or 'true', then both types will use GMT time.
     * If $gmt is false, the output is adjusted with the GMT offset based on General Settings.
     *
     * @since 1.0.0
     * @param string $type Type of time to return. Accepts 'db', 'timestamp', or PHP date
     *                     format string (e.g. 'Y-m-d').
     * @param bool $gmt    Optional. Whether to use GMT timezone. Default false.
     * @return int|string Integer if $type is 'timestamp', string otherwise.
     */
    public function current(string $type, bool $gmt = false)
    {
        if ('timestamp' === $type || 'U' === $type) {
            return $gmt ? time() : time() + (int) ($this->locale()->offsetHours * (int) static::hourInSeconds());
        }
        if ('db' === $type) {
            $type = 'Y-m-d H:i:s';
        }
        $timezone = $gmt ? new \DateTimeZone('GMT') : $this->timezone;
        $datetime = $this->date->now($timezone);

        return $datetime->format($type);
    }

    /**
     * Converts timestamp to localized human readable date.
     *
     * @since 1.0.0
     * @param string $format PHP date format string (e.g. 'Y-m-d').
     * @param int $timestamp Timestamp to convert.
     * @return string Localized human readable date.
     */
    public function timestampToDate(string $format, int $timestamp)
    {
        return (string) $this->locale()->createFromTimestamp($timestamp)->format($format);
    }

    /**
     * Prints elapsed time based on datetime.
     *
     * @since 1.0.0
     * @returns Elapsed time.
     */
    public function timeAgo($original)
    {
        // array of time period chunks
        $chunks = [
            [60 * 60 * 24 * (date('z', mktime(0, 0, 0, 12, 31, date('Y'))) + 1), 'year'],
            [60 * 60 * 24 * date('t'), 'month'],
            [60 * 60 * 24 * 7, 'week'],
            [60 * 60 * 24, 'day'],
            [60 * 60, 'hour'],
            [60, 'min'],
            [1, 'sec'],
        ];

        $today = time(); /* Current unix time  */
        $since = $today - $original;

        // $j saves performing the count function each time around the loop
        for ($i = 0, $j = count($chunks); $i < $j; $i++) {
            $seconds = $chunks[$i][0];
            $name = $chunks[$i][1];

            // finding the biggest chunk (if the chunk fits, break)
            if (($count = floor($since / $seconds)) != 0) {
                break;
            }
        }

        $print = ($count == 1) ? '1 ' . $name : "$count {$name}s";

        if ($i + 1 < $j) {
            // now getting the second item
            $seconds2 = $chunks[$i + 1][0];
            $name2 = $chunks[$i + 1][1];

            // add second item if its greater than 0
            if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
                $print .= ($count2 == 1) ? ', 1 ' . $name2 : " $count2 {$name2}s";
            }
        }
        return $print;
    }
}
