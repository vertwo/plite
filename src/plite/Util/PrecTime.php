<?php
/**
 * Copyright (c) 2012-2021 Troy Wu
 * Copyright (c) 2021      Version2 OÜ
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */



namespace vertwo\plite\Util;



use DateTime;
use DateTimeZone;
use Exception;



/**
 * ************************************************************************
 * Reads any-old-timespec.  If it's parseable at all, we will
 * convert it into an RFC-3339 compliant timespec; specifically,
 * if there is no timezone information, we will use "-00:00" for
 * the timezone offset.
 *
 * If no timespec is given, then generates an RFC-3339-compliant
 * timespec based on the current time given by microtime(),
 * typically used by a caller that wants to use this as a high-
 * resolution timer.
 *
 * DANGER - This class does not provide any precision or accuracy guarantees above those in the RFC3339 class.
 * DANGER - If kernel/system time (from gettimeofday) is disciplined by something like NTP, then the timer won't be precise.
 *
 * DANGER - We use UNKNOWN_LOCAL timezone offset string to also indicate an unknown timezone.
 *
 * This is obviously not the intended use, because using
 * UNKNOWN_LOCAL implies you know the point in time (terrestrially)
 * but you just don't know what the offset is.  Our use violates
 * this assumption.
 *
 *
 * @see RFC3339
 * ************************************************************************
 */
class PrecTime extends RFC3339
{
    const WHOLE_TIMESTAMP_PATTERN = "/(\d+)\.(\d+)/";

    /** @var RFC3339 $stop */
    private $stop;


    /**
     * ****************************************************************
     * Comparison function intended to be used with sort.
     *
     * Sort is in ASCENDING (natural) order.
     *
     * @param PrecTime $a
     * @param PrecTime $b
     *
     * @return int
     * @static
     * ****************************************************************
     */
    public static function cmp ( $a, $b )
    {
        if ( false === $a && false === $b )
        {
            return 0;
        }
        else if ( false === $a )
        {
            // $a is unknown (should go last)
            // $b is known (should go before)
            return 100;
        }
        else if ( false === $b )
        {
            // $a is known (should go before)
            // $b is unknown (should go last)
            return -100;
        }

        if ( $a->isBefore($b) ) return -1;
        else if ( $a->isAfter($b) ) return 1;
        else return 0;
    }


    /**
     * ****************************************************************
     * Comparison function intended to be used with sort.
     *
     * Sort is in DESCENDING order.
     *
     * @param PrecTime $a
     * @param PrecTime $b
     *
     * @return int
     * @static
     * ****************************************************************
     */
    public static function rcmp ( $a, $b )
    {
        if ( false === $a && false === $b )
        {
            return 0;
        }
        else if ( false === $a )
        {
            // $a is unknown (should go last)
            // $b is known (should go before)
            return 100;
        }
        else if ( false === $b )
        {
            // $a is known (should go before)
            // $b is unknown (should go last)
            return -100;
        }

        if ( $a->isBefore($b) ) return 1;
        else if ( $a->isAfter($b) ) return -1;
        else return 0;
    }


    /**
     * Gets time difference in days (including fractional component).
     *
     * @param $t1
     * @param $t2
     *
     * @return float|int - Time difference in days (including fractional component).
     * @throws Exception
     */
    public static function getTimeDiff ( $t1, $t2 )
    {
        $dt1      = new DateTime("@$t1");
        $dt2      = new DateTime("@$t2");
        $interval = $dt1->diff($dt2);

        $d = $interval->format("%a");
        $h = $interval->format("%h");
        $i = $interval->format("%i");
        $s = $interval->format("%s");
        $f = $interval->format("%f");

        $d = (int)$d;
        $h = $h / (24); // hours
        $i = $i / (24 * 60); // min
        $s = $s / (24 * 60 * 60); // sec
        $f = $f / (24 * 60 * 60 * 1000000); // frac of sec

        return $d + $h + $i + $s + $f;
    }


    /**
     * ################################################################
     * ################################################################
     *
     * Create an RFC-3339 compliant time object with a timezone setting
     * enforced!
     *
     * @param string|null              $timespec - RFC-3339 timespec.
     *
     * @param string|DateTimeZone|null $tz       - TimeZone spec.
     *
     * @throws Exception - Bad timespec.
     *
     * NOTE - A bad timezone-spec, either from the timespec or the '$tz' argument itself, cannot throw an Exception here.
     *
     * When a bad timezone-spec is used, the offset generated is the
     * UNKNOWN_LOCAL timezone offset string, per RFC-3339.
     *
     * ################################################################
     * ################################################################
     */
    public function __construct ( $timespec = null, $tz = null )
    {
        $normalizedTimespec = self::parse($timespec, $tz);

        parent::__construct($normalizedTimespec);
    }


    /**
     * @param $timespec
     * @param $tz
     *
     * @return string|null
     * @throws Exception
     */
    private static function parse ( $timespec, $tz )
    {
        if ( null === $timespec ) return null;

        try
        {
            $core = new RFC3339($timespec);
            return $core->rfc();
        }
        catch ( Exception $e )
        {
            if ( DEBUG_ANALTIME ) error_log("AT.parse() - Not an RFC-3339 compliant timestamp [ " . $e->getMessage() . " ] ; moving on...");
        }

        try
        {
            $dt       = self::tryUTFix($timespec);
            $timespec = $dt->format("c"); // 'UT' should be interpreted as 'GMT'.
            return $timespec;
        }
        catch ( Exception $e )
        {
            if ( DEBUG_ANALTIME ) error_log("AT.parse() - Not a UT->GMT crazy-timespec; moving on...");
        }

        if ( DEBUG_ANALTIME ) error_log("AT.parse/timespec: $timespec");

        if ( RFC3339::isRelaxedMatch($timespec) )
        {
            if ( null !== $tz )
            {
                if ( DEBUG_ANALTIME && is_string($tz) ) error_log("AT.parse/tz-raw: $tz");
                if ( DEBUG_ANALTIME && $tz instanceof DateTimeZone ) error_log("AT.parse/DTZ: " . $tz->getName());

                $unrelaxedTimespec = self::getISOWithForcedTimezone($timespec, $tz);
            }
            else
            {
                $unrelaxedTimespec = $timespec . RFC3339::UNKNOWN_OFFSET_STRING;
            }

            if ( DEBUG_ANALTIME ) error_log("AT.parse/unrelaxedTimespec: $unrelaxedTimespec");

            return $unrelaxedTimespec;
        }
        else
        {
            //$dt = new DateTime($timespec);
            //$tz = new DateTimeZone($tz);
        }

        if ( self::isWholeMatch($timespec) )
        {
            preg_match(self::WHOLE_TIMESTAMP_PATTERN, $timespec, $matches);
            $secondsSinceEpoch = $matches[1];
            $usec              = $matches[2];
            $iso               = date(self::DATE_FORMAT_WITHOUT_TIMEZONE, $secondsSinceEpoch);

            // Add fractional & UTC (zulu) timezone.
            $iso  .= ".$usec" . self::ZULU_OFFSET_STRING;
            $core = new RFC3339($iso);
            return $core->rfc();
        }

        try
        {
            $dt = new DateTime($timespec);



            if ( null !== $tz )
            {
                if ( !$tz instanceof DateTimeZone && is_string($tz) )
                {
                    $tz = new DateTimeZone($tz);
                }
            }
            else
            {
                $dttz = $dt->getTimezone();

                if ( DEBUG_ANALTIME ) error_log("AT.parse/dttz: " . $dttz);
                if ( null == $dttz && DEBUG_ANALTIME ) error_log("AT.parse/dttz-is-null!");

                if ( null !== $dttz )
                {
                    $tz = $dttz;
                }
            }



            //$tz = (null !== $tz) ? $tz : $tz = $dt->getTimezone();

            if ( DEBUG_ANALTIME ) error_log("AT.parse/dt-ISO: " . $dt->format("c"));
            if ( DEBUG_ANALTIME ) error_log("AT.parse/dt-timezone: " . $tz->getName());

            $timespec = self::getISOWithForcedTimezone($dt, $tz);
            return $timespec;
        }
        catch ( Exception $e )
        {
            if ( DEBUG_ANALTIME ) error_log("AT.parse() - Not a PHP-recognizable timespec; giving up.");
            throw $e;
        }
    }


    public static function isWholeMatch ( $timespec ) { return preg_match(self::WHOLE_TIMESTAMP_PATTERN, $timespec); }


    /**
     * Attempts to understand timespecs with a "UT" timezone,
     * common in older/brokener SMTP/MIME messages (AOL, old
     * list servers, etc).
     *
     * @param string $timespec
     *
     * @return DateTime
     * @throws Exception - If timespec isn't a CRAZY_UT timespec, or if post-conversion timespec doesn't parse.
     */
    private static function tryUTFix ( $timespec )
    {
        if ( !self::doesTimespecEndInUT($timespec) )
            throw new Exception("This timespec is not a CRAZY_UT timespec.");

        $gmt = self::UT2GMT($timespec);
        return new DateTime($gmt);
    }


    private static function doesTimespecEndInUT ( $timespec ) { return preg_match("/.*\ UT$/", $timespec); }


    private static function UT2GMT ( $timespec ) { return preg_replace("/\ UT$/", " GMT", $timespec); }


    /**
     * @param DateTime|string          $dt
     *
     * @param null|DateTimeZone|string $tz
     *
     * @return string - RFC-3339 compliant timestamp; i.e., WITH TIMEZONE
     * (even if the timezone is unknown).
     */
    private static function getISOWithForcedTimezone ( $dt, $tz )
    {
        $shouldForceTimezone = (null !== $tz);
        $basic               = is_string($dt) ? $dt : $dt->format(RFC3339::DATE_FORMAT_WITHOUT_TIMEZONE);

        if ( DEBUG_ANALTIME ) error_log("AT.getISOWithForcedTimezone/shouldForceTimezone: " . $shouldForceTimezone);
        if ( DEBUG_ANALTIME ) error_log("AT.getISOWithForcedTimezone/basic: " . $basic);

        if ( $shouldForceTimezone )
        {
            // Ignore the timezone from the DateTime object, and use ($tz).
            if ( is_string($tz) && RFC3339::isValidTimezoneOffsetString($tz) )
            {
                // This is an offset-spec.
                $offsetString = $tz;
            }
            else
            {
                try
                {
                    // Is this a regular timezone string or a DateTimeZone object?
                    $actualDateTime = is_string($dt) ? new DateTime($dt) : $dt;
                    $offsetString   = self::getTimezoneOffsetString($tz, $actualDateTime);
                }
                catch ( Exception $e )
                {
                    // This can only happen if DateTimeZone.ctor() cannot recognize ($tz).
                    // Assume time is in UTC, but use "-00:00" per RFC-3339.
                    $offsetString = RFC3339::UNKNOWN_OFFSET_STRING;
                }
            }

            if ( DEBUG_ANALTIME ) error_log("AT.getISOWithForcedTimezone/offsetString: " . $offsetString);

            return $basic . $offsetString;
        }
        else
        {
            // Try to use the timezone from the DateTime object.
            if ( false === $dt->getTimezone() )
            {
                // If it doesn't exist, then assume time is in UTC, but use "-00:00" per RFC-3339.
                return $basic . RFC3339::UNKNOWN_OFFSET_STRING;
            }
            else
            {
                // Output an ISO-8601 timespec, with a known timezone.
                return $dt->format("c");
            }
        }
    }


    /**
     * @param string|DateTimeZone $tz - Some kind of Timezone-spec.
     * @param DateTime            $dt - Valid DateTime object.
     *
     * @return string - RFC-3339 compliant Timezone offset string.
     *
     * @throws Exception - Thrown if DateTimeZone.ctor() cannot recognize ($tz).
     */
    private static function getTimezoneOffsetString ( $tz, $dt )
    {
        if ( is_string($tz) ) $tz = new DateTimeZone($tz);

        if ( DEBUG_ANALTIME ) error_log("AT::getTimezoneOffsetString/DTZ: " . $tz->getName());

        $offsetInSeconds = $tz->getOffset($dt);
        return self::convertOffsetSecondsToOffsetString($offsetInSeconds);
    }


    public static function convertOffsetSecondsToOffsetString ( $offsetInSeconds )
    {
        $offsetString = sprintf(
            "%s%02d:%02d",
            self::getOffsetSign($offsetInSeconds),
            self::getOffsetHours($offsetInSeconds),
            self::getOffsetMinutes($offsetInSeconds)
        );
        return $offsetString;
    }


    private static function getOffsetSign ( $offsetInSeconds )
    {
        return (0 <= $offsetInSeconds) ? '+' : '-';
    }


    private static function getOffsetHours ( $offsetInSeconds )
    {
        $seconds = abs($offsetInSeconds);
        return floor($seconds / 3600);
    }


    private static function getOffsetMinutes ( $offsetInSeconds )
    {
        $hours                   = self::getOffsetHours($offsetInSeconds);
        $hoursExpressedInSeconds = $hours * 3600;

        $seconds                     = abs($offsetInSeconds);
        $remainderExpressedInSeconds = $seconds - $hoursExpressedInSeconds;
        $minutes                     = $remainderExpressedInSeconds / 60;

        return floor($minutes);
    }


    /**
     * @return DateTime
     * @throws Exception
     */
    public function getDateTime () { return new DateTime($this->iso()); }


    /**
     * ****************************************************************
     * NOTE - Probably requires 64-bit arithmetic.
     *
     * @return string - The number of seconds,
     * including the fractional component, from the start of the epoch
     * represented by this timestamp.
     * ****************************************************************
     * @throws Exception
     */
    public function getFractionalSeconds ()
    {
        $dt         = new DateTime($this->iso());
        $sinceEpoch = $dt->format("U");
        $whole      = sprintf("%s.%06d", $sinceEpoch, $this->usec());
        return $whole;
    }


    /**
     * NOTE - Probably requires 64-bit arithmetic.
     *
     * @return string - The number of milliseconds,
     * including the fractional component, from the start of the epoch
     * represented by this timestamp.
     * @throws Exception
     */
    public function getFractionalMillis ()
    {
        $dt         = new DateTime($this->iso());
        $sinceEpoch = $dt->format("U");
        $whole      = sprintf("%s%s", $sinceEpoch, $this->getJustFractionalMillis());
        return $whole;
    }


    private function getJustFractionalMillis ()
    {
        $fm    = round($this->usec() / 1000, 3);
        $whole = sprintf("%07.3f", $fm);
        return $whole;
    }


    /**
     * @return float - The number of whole seconds, rounded-to-nearest,
     * from the start of the epoch represented by this timestamp.
     * @throws Exception
     */
    public function getWholeSeconds ()
    {
        return round($this->getWholeMicros() / 1000000);
    }


    /**
     * Gets a timestamp in whole milliseconds, rounded-to-nearest.
     *
     * Particularly useful for communicating with Javascript or Java front-ends.
     *
     * NOTE - This requires 64-bit arithmetic.
     *
     * @return string - A string which is the number of whole milliseconds
     * (represented by this timestamp) from the epoch.
     * @throws Exception
     */
    public function getWholeMillis ()
    {
        return round($this->getWholeMicros() / 1000);
    }


    /**
     * Gets this timestamp in whole microseconds.
     *
     * This is the highest precision we can get from PHP.
     *
     * NOTE - This value is returned as a string, which can be used exactly.
     * NOTE - However, if duck-typed as a number, will probably require 64-bit arithmetic.
     *
     * @return string
     * @throws Exception
     */
    public function getWholeMicros ()
    {
        $dt         = new DateTime($this->iso());
        $sinceEpoch = $dt->format("U");
        $whole      = sprintf("%s%06d", $sinceEpoch, $this->usec());
        return $whole;
    }


    /**
     * @param $tz
     *
     * @return PrecTime
     * @throws Exception
     */
    public function shiftTimezone ( $tz )
    {
        $dt = $this->getDateTime();
        $dt->setTimezone($tz);

        if ( DEBUG_RFC3339 ) error_log("RFC-3339.shiftTimezone/dt-ISO: " . $dt->format("c"));
        if ( DEBUG_RFC3339 ) error_log("RFC-3339.shiftTimezone/dt-timestamp: " . $dt->getTimestamp());
        if ( DEBUG_RFC3339 ) error_log("RFC-3339.shiftTimezone/dt-fraction: " . $this->usec());

        if ( DEBUG_RFC3339 ) error_log("RFC3339.shiftTimezone/BTWN :" . $this);

        if ( $this->hasFractional() )
        {
            $basic    = $dt->format(self::DATE_FORMAT_WITHOUT_TIMEZONE);
            $fraction = $this->usec();
            $tz       = $dt->format("P");

            $fractionString = ".$fraction";

            $timespec = $basic . $fractionString . $tz;
        }
        else
        {
            $timespec = $dt->format("c");
        }

        if ( DEBUG_RFC3339 ) error_log("RFC-3339.shiftTimezone/shifted-timespec: " . $timespec);

        $shifted = new PrecTime($timespec);
        return $shifted;
    }


    /**
     * ****************************************************************
     * Returns the difference between the creation of this object and
     * the time this method was called with microsecond precision,
     * expressed in milliseconds.
     *
     * @return float - Since the overall precision is microseconds,
     * there may be 3 digits after the decimal point.
     * ****************************************************************
     */
    public function stop ()
    {
        $this->stop = new RFC3339();
        return $this->stop->mdiff($this);
    }
}
