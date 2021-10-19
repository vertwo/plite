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


// Basic operating parameters.
use DateTime;
use DateTimeZone;
use Exception;


define("RFC3339_USE_SLOW_INIT", false);

// RFC-3339 timezone offset string pattern
define("RFC3339_REGEX_TIMEZONE_OFFSET", "([-\+])(\d{2}):(\d{2})");

// This is full RFC-3339.
define("RFC3339_REGEX_STRICT", "(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})([\.]\d+)?(Z|" . RFC3339_REGEX_TIMEZONE_OFFSET . ")");
define("RFC3339_PATTERN_STRICT", "/^" . RFC3339_REGEX_STRICT . "$/");

// This is RFC-3339, but with an optional TimeZone spec.
define("RFC3339_REGEX_RELAXED", RFC3339_REGEX_STRICT . "?");
define("RFC3339_PATTERN_RELAXED", "/^" . RFC3339_REGEX_RELAXED . "$/");

// regex capture positions
define("RFC3339_REGEX_CAPTURE_DATE", 0);
define("RFC3339_REGEX_CAPTURE_TIME", 1);
define("RFC3339_REGEX_CAPTURE_FRACTION", 2);
define("RFC3339_REGEX_CAPTURE_TZ", 3);
define("RFC3339_REGEX_CAPTURE_TZ_SIGN", 4);
define("RFC3339_REGEX_CAPTURE_TZ_HOUR", 5);
define("RFC3339_REGEX_CAPTURE_TZ_MINUTE", 6);


/**
 * ************************************************************************
 * A strictly-conformant RFC-3339 implementation having sub-second (i.e.,
 * microsecond) precision based on PHP's microtime().
 *
 * NOTE - PHP's microtime() depends on gettimeofday(2), 4.2BSD.
 *
 * DANGER - If time_t is a 32-bit value, this code blows up in 2038. TODO - FIXME
 *
 * Unix-like systems (Mac OS X > 10.6.4, Linux, and derivatives of
 * 4.2BSD should all have this method available, though the accuracy and
 * precision is completely dependent on the underlying OS.
 *
 * NOTE - RFC-3339 is a mostly-conforming subset of ISO-8601: tools.ietf.org/html/rfc3339
 *
 * Basically, RFC-3339 date/time excludes durations, representations using
 * weeks, and  attempts to be explicit in areas where ISO-8601 is
 * ambiguous. It also eliminates partial formats, particularly in time
 * formats.  Week specifications are also eliminated.
 *
 * NOTE - The only optional component of RFC-3339 is a fractional time spec.
 *
 * NOTE - The only non-conforming element of RFC-3339 is the "Unknown Local Offset Convention" of "-00:00".
 *
 * While a similar convention is used in RFC-2822 (SMTP), this is
 * explicitly disallowed in ISO-8601.
 *
 * https://en.wikipedia.org/wiki/ISO_8601#Time_offsets_from_UTC
 *
 * This class makes use of microtime() in an attempt to achieve subsecond
 * precision.  However, as it relies on PHP microtime()--which relies on
 * gettimeofday(2) from 4.2BSD--the precision is limited by the
 * underlying implementation of gettimeofday(2), which is affected
 * by the OS.  In particular, NTP and similar time-services which
 * discipline the system clock, e.g., adjtime(2), are applying that clock
 * discipline to the value returned by microtime().
 *
 * DANGER - In addition, VM technologies like Xen and VMWare can seriously mangle system time.
 * DANGER - There is no implied accuracy or precision of the time represented.
 * DANGER - Neither the accuracy nor precision in this class is suitable for time-critial work, without underlying HW/OS guarantees.
 *
 * NOTE - In timer mode, this object performs lazy-evaluation of all state to keep timer latency down.
 *
 * This means that the ctor is only initializing only the bare minimum
 * necessary to represent the current time--for example, the string
 * returned by PHP's microtime().
 *
 * This makes it possible to use two RFC3339 objects as a timer, wherein
 * one is created at the start time, and one is created at the stop time,
 * and avoiding the latency of the state-initialization (hundreds of
 * microseconds).
 *
 * DANGER - This means that any method which evaluates the state of this object must first materialize the state.
 *
 * While this is an implementor's issue, it is worth noting because it can
 * make the code easier to understand.  This is likely to be done in a
 * method called materializeState().
 * ************************************************************************
 */
class RFC3339 implements Wired
{

    const NORMAL_MODE = 0;
    const TIMER_MODE  = 1;

    const DATE_FORMAT_WITHOUT_TIMEZONE = "Y-m-d\TH:i:s";
    const ZULU_OFFSET_STRING           = "Z";
    const UTC_OFFSET_STRING            = "+00:00";
    const UNKNOWN_OFFSET_STRING        = "-00:00";

    const ONE_THOUSAND               = 1000;
    const MILLISECONDS_IN_ONE_SECOND = self::ONE_THOUSAND;

    const ONE_MILLION                = 1000000;
    const MICROSECONDS_IN_ONE_SECOND = self::ONE_MILLION;


    private $isMaterializing = false;

    private $mt       = false; // String returned by microtime.
    private $fraction = false; // fraction-of-a-second: could be from timespec or microtime().

    private $usec = false; // Microseconds represented by $fraction.
    private $sec  = false; // As determined by microtime.

    private $timespec = false;

    private $parts; // Matched tokens from strict|relaxed regex patterns.

    /** @var DateTimeZone $TIMEZONE_UTC */
    private static $TIMEZONE_UTC = false; // Singleton.


    public static function UTC ()
    {
        return (false !== self::$TIMEZONE_UTC)
            ? self::$TIMEZONE_UTC
            : (self::$TIMEZONE_UTC = new DateTimeZone("UTC"));
    }


    public static function isValidTimezoneOffsetString ( $offsetString )
    {
        return preg_match("/^" . RFC3339_REGEX_TIMEZONE_OFFSET . "$/", $offsetString);
    }


    public static function isStrictMatch ( $timespec, &$matches = [] )
    {
        return preg_match(RFC3339_PATTERN_STRICT, $timespec, $matches);
    }


    public static function isRelaxedMatch ( $timespec, &$matches = [] )
    {
        return preg_match(RFC3339_PATTERN_RELAXED, $timespec, $matches);
    }


    /**
     * @param string|null $timespec - A time encoding.
     * @param int         $mode     - Either TIMER or NORMAL mode.
     *
     * @throws Exception - If timespec is bad or non-conforming.
     */
    public function __construct ( $timespec = null, $mode = self::NORMAL_MODE )
    {
        if ( null === $timespec )
        {
            $this->mt = microtime(false);

            if ( DEBUG_RFC3339 ) error_log("-----=====[ RFC-3339.ctor/timer ]=====-----");

            if ( RFC3339_USE_SLOW_INIT ) $this->materializeState();
        }
        else if ( self::TIMER_MODE === $mode )
        {
            $this->mt = $timespec;

            if ( DEBUG_RFC3339 ) error_log("-----=====[ RFC-3339.ctor/timer-with-explicit-start ]=====-----");

            if ( RFC3339_USE_SLOW_INIT ) $this->materializeState();
        }
        else
        {
            $this->timespec = $timespec;

            if ( DEBUG_RFC3339 ) error_log("-----=====[ RFC-3339.ctor/normal ]=====-----");

            /*
             * This has to be done to fully-initialize the rest of the object.
             * We only skip this in the other modes to allow the timer to have
             * as little app latency as possible.
             */
            $this->materializeState();
        }
    }


    /**
     * @throws Exception
     */
    private function materializeState ()
    {
        if ( $this->isAlreadyInitialized() || $this->isMaterializing )
            return;

        $this->isMaterializing = true;

        if ( $this->lacksTimespec() && $this->hasTimerVal() )
            $this->initTimespecFromTimer();

        $this->parseTimespec();

        $this->isMaterializing = false;
    }


    private function isAlreadyInitialized () { return $this->hasTimespec() && $this->hasTimerVal(); }


    private function hasTimespec () { return false !== $this->timespec; }


    private function hasTimerVal () { return false !== $this->mt; }


    private function lacksTimespec () { return !$this->hasTimespec(); }


    private function initTimespecFromTimer ()
    {
        $tokens = explode(" ", $this->mt);
        $usec   = substr($tokens[0], 1);
        $sec    = intval($tokens[1]);

        $now            = new DateTime("@$sec", self::UTC());
        $this->timespec = $now->format(self::DATE_FORMAT_WITHOUT_TIMEZONE) . $usec . self::ZULU_OFFSET_STRING;
    }


    /**
     * @throws Exception
     */
    private function parseTimespec ()
    {
        /*
         * If we're here, we need to initialize the object
         * from the timespec (possibly created from timer-vals).
         *
         * The next time through, we'll be already-initialized
         * because we'll already have a timespec -AND- the timer-vals
         * will be set (at least the seconds-portion, if not the
         * microseconds-portion).
         */

        if ( DEBUG_RFC3339 ) error_log("RFC-3339.parseTimespec: " . $this->timespec);

        // NOTE - PHP's preg_match has a side-effect of actually parsing and storing the captures ($this->parts).
        // NOTE - This next line should end the recursion from materializeState() -> here -> ... -> materializeState().

        if ( !self::isStrictMatch($this->timespec, $this->parts) )
        {
            if ( self::isRelaxedMatch($this->timespec) )
            {
                throw new Exception("RFC-3339 - Non-conforming: No timezone.");
            }
            else
            {
                throw new Exception("RFC-3339 - Non-conforming: Bad timespec [ $this->timespec ].");
            }
        }

        array_shift($this->parts); // Get rid of the [0], which is the whole string.

        if ( DEBUG_RFC3339 ) error_log("RFC-3339.parseTimespec/regex-captures: " . $this->parts);

        //$hasFractionPart = isset($this->parts[RFC3339_REGEX_CAPTURE_FRACTION]);
        $hasFraction    = 0 < strlen($this->parts[RFC3339_REGEX_CAPTURE_FRACTION]);
        $this->fraction = $hasFraction ? $this->parts[RFC3339_REGEX_CAPTURE_FRACTION] : false;

        if ( DEBUG_RFC3339 ) error_log("RFC-3339.parseTimespec/usec: " . $this->fraction);

        // Initialize timer-val from microtime() output.
        $this->setTimerVal();
    }


    /**
     * Initializes the high-precision timer component from microtime() output.
     *
     * Ensures that
     *
     *   '$this->fraction'
     *   '$this->usec'
     *   '$this->sec'
     *
     * are set properly.
     *
     * @throws Exception - If fraction is greater-than-1.
     */
    private function setTimerVal ()
    {
        if ( DEBUG_RFC3339 ) error_log("RFC-3339.setTimerVal/fraction: " . $this->fraction);

        if ( false === $this->fraction )
        {
            $usec           = 0;
            $this->usec     = 0;
            $this->fraction = false;
        }
        else
        {
            $this->fraction = floatval($this->fraction);

            $usec       = $this->fraction * self::MICROSECONDS_IN_ONE_SECOND;
            $this->usec = intval(round($usec));
        }

        $date   = $this->date();
        $time   = $this->time();
        $offset = $this->offsetString();

        $ts = $date . "T" . $time . $offset;

        $dt        = new DateTime($ts);
        $secString = $dt->format("U");
        $sec       = intval($secString);
        $this->sec = $sec;

        $usecString = sprintf("%0.8f", $usec);
        $this->mt   = "$usecString $secString";

        if ( DEBUG_RFC3339 ) error_log("RFC-3339.setTimerVal/microtime: " . $this->mt);
    }


    public function date () { return $this->get(RFC3339_REGEX_CAPTURE_DATE); }


    public function time () { return $this->get(RFC3339_REGEX_CAPTURE_TIME); }


    public function offsetString () { return $this->get(RFC3339_REGEX_CAPTURE_TZ); }


    private function get ( $regexCaptureIndex )
    {
        $this->materializeState();

        return $this->parts[$regexCaptureIndex];
    }


    public function sec ()
    {
        $this->materializeState();

        return $this->sec;
    }


    public function usec () { return $this->hasFractional() ? $this->usec : 0; }


    public function isZulu () { return self::ZULU_OFFSET_STRING === $this->offsetString(); }


    public function isUTC () { return $this->isZulu() || $this->isOffsetUTC(); }


    private function isOffsetUTC () { return self::UTC_OFFSET_STRING == $this->offsetString(); }


    public function isOffsetUnknownLocal () { return self::UNKNOWN_OFFSET_STRING == $this->offsetString(); }


    /** @return string - A strictly-conforming RFC-3339 timespec string. */
    public function rfc () { return $this->generateISO(true); }


    /** @return string - A strictly-conforming RFC-3339 timespec string without the fractional component. */
    public function iso () { return $this->generateISO(false); }


    private function isoBasic () { return $this->date() . "T" . $this->time(); }


    private function generateISO ( $shouldUseFractional )
    {
        $basic      = $this->isoBasic();
        $fractional = $shouldUseFractional ? $this->getFractionAsPaddedString() : "";
        $tzString   = $this->offsetString();

        $iso = $basic . $fractional . $tzString;
        return $iso;
    }


    /**
     * Does this timestamp have a fractional component?
     *
     * @return bool
     */
    protected function hasFractional ()
    {
        $this->materializeState();

        return false !== $this->fraction;
    }


    /** @return float */
    private function getFractionAsFloat () { return $this->hasFractional() ? $this->fraction : floatval(0); }


    private function getFractionAsPaddedString () { return $this->hasFractional() ? sprintf(".%06d", $this->usec()) : ""; }


    /**
     * @param RFC3339 $anotherTime
     *
     * @return float - Difference, in seconds, between the two times.  Since
     * precision is micro, 6 places after decimal can be returned.
     */
    public function diff ( $anotherTime )
    {
        $wholeDiff      = $this->getWholeSecondsDiff($anotherTime);
        $fractionalDiff = $this->getFractionalDiff($anotherTime);

        if ( DEBUG_RFC3339 ) error_log("RFC-3339.diff/whole: ", $wholeDiff);
        if ( DEBUG_RFC3339 ) error_log("RFC-3339.diff/frac: ", $fractionalDiff);

        $absTotalDiffInSeconds = abs($wholeDiff + $fractionalDiff);
        return $absTotalDiffInSeconds;
    }


    /**
     * @param RFC3339 $anotherTime
     *
     * @return int - Difference, in whole seconds, between the two times.
     */
    private function getWholeSecondsDiff ( $anotherTime )
    {
        return $this->sec() - $anotherTime->sec();
    }


    /**
     * @param RFC3339 $anotherTime
     *
     * @return float - Difference, in partial seconds, between the two
     * fraction-of-a-second components.
     */
    private function getFractionalDiff ( $anotherTime )
    {

        $myMicro    = $this->getFractionAsFloat();
        $otherMicro = $anotherTime->getFractionAsFloat();

        return $myMicro - $otherMicro;
    }


    /**
     * @param RFC3339 $anotherTime
     *
     * @return float - Difference, in millis.  Since precision is micro,
     * 3 places after decimal can be returned.
     */
    public function mdiff ( $anotherTime )
    {
        $secondsDiff = $this->diff($anotherTime);
        $millisDiff  = self::MILLISECONDS_IN_ONE_SECOND * $secondsDiff;
        return round($millisDiff, 3);
    }


    /**
     * @param RFC3339 $anotherTime
     *
     * @return int - Difference, in whole microseconds.  Precision is
     * micro, so integers are returned.
     */
    public function udiff ( $anotherTime )
    {
        $secondsDiff = $this->diff($anotherTime);
        $microsDiff  = self::MICROSECONDS_IN_ONE_SECOND * $secondsDiff;
        return intval(round($microsDiff));
    }


    /**
     * @param $anotherTime
     *
     * @return bool - Is $this time after $anotherTime?
     */
    public function isAfter ( $anotherTime ) { return 0 < $this->diff($anotherTime); }


    /**
     * @param $anotherTime
     *
     * @return bool - Is $this time before $anotherTime?
     */
    public function isBefore ( $anotherTime ) { return 0 > $this->diff($anotherTime); }


    /*
     * Wired methods.
     */


    function toArray () { return $this->toHash(); }



    /**
     * @return array
     * @throws Exception
     */
    public function toHash ()
    {
        $this->materializeState();

        return [
            "mt"       => $this->mt,
            "fraction" => $this->fraction,
            "usec"     => $this->usec,
            "sec"      => $this->sec,
            "timespec" => $this->timespec,
            "parts"    => $this->parts,
        ];
    }


    function toStore () { return $this->toHash(); }


    function __toString () { return $this->rfc(); }


    static function newInstance ( $flat )
    {
        $mt = $flat['mt'];

        $core           = new RFC3339($mt, self::TIMER_MODE);
        $core->fraction = $flat['fraction'];
        $core->usec     = $flat['usec'];
        $core->sec      = $flat['sec'];
        $core->timespec = $flat['timespec'];
        $core->parts    = FJ::copyArray($flat['parts']);

        return $core;
    }
}
