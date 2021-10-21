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



namespace vertwo\plite;



use Exception;
use vertwo\plite\Util\PrecTime;
use vertwo\plite\Util\Wired;



class Log
{
    const DEBUG_TIMING = false;
    const DEBUG_REMOTE = false;

    const CLOG_ERROR_LOG_CONSTANT      = 'error_log';
    const CLOG_FILENAME                = "php.clog";
    const CLOG_TIMING_THRESHOLD        = 50; // millis before we mark it red.
    const CLOG_DEBUG_TIMING            = false;
    const CLOG_DEBUG_ERROR_LOG_DEFAULT = false;
    const CLOG_FOPEN_MODE              = "a+";
    const CLOG_PASSWORD_PATTERN        = "/(passw[o]*[r]*d|scramble|secret)/i";

    // CLOG options.
    const CLOG_VERSION_LITE         = 1;
    const CLOG_VERSION_DEBUG        = 9;
    const CLOG_VERSION              = self::CLOG_VERSION_LITE;
    const CLOG_MESG_BODY_WIDTH      = 115;
    const CLOG_MESG_EXCEPTION_WIDTH = 50;
    const CLOG_TAB_WIDTH            = 1; // Expressed as a power of two.
    const CLOG_IGNORE_DEPTH         = 0;
    const CLOG_OBEY_DEPTH           = 1;
    const CLOG_SESSION              = true;
    const CLOG_ARRAY_KEY_FANCY      = true;
    const CLOG_DEPTH_INDENT         = 4;

    // Timer options.
    const TIMER_PREFIX_LEN     = (20 + 6);
    const TIMER_TEXT_LEN       = (self::CLOG_MESG_BODY_WIDTH - self::TIMER_PREFIX_LEN);
    const TIME_LIMIT_5_MINUTES = (5 * 60);

    // Logging constants
    const TEXT_COLOR_WHITE  = "\033[1;37m";
    const TEXT_COLOR_RED    = "\033[0;31m";
    const TEXT_COLOR_GREEN  = "\033[0;32m";
    const TEXT_COLOR_YELLOW = "\033[1;33m";
    const TEXT_COLOR_BLUE   = "\033[0;34m";
    const TEXT_COLOR_CYAN   = "\033[0;36m";
    const TEXT_COLOR_ORANGE = "\033[0;33m";

    const TEXT_COLOR_BG_RED    = "\033[41m";
    const TEXT_COLOR_BG_YELLOW = "\033[30;43m";

    const TEXT_COLOR_SUFFIX    = "\033[0m";
    const TEXT_COLOR_UL_CYAN   = "\033[4;36m";
    const TEXT_COLOR_UL_BLACK  = "\033[4;30m";
    const TEXT_COLOR_UL_WHITE  = "\033[4;37m";
    const TEXT_COLOR_UL_GREEN  = "\033[4;32m";
    const TEXT_COLOR_UL_YELLOW = "\033[4;33m";
    const TEXT_COLOR_UL_RED    = "\033[4;31m";


    const CLOG_ALT_FILE_DIRS = [
        "/Users/srv/www/logs",  // macOS root filesystem is read-only now...so moving to /Users
        "/srv/www/logs",        // Orig dev
        "/var/log/apache2",     // New apache
        "/var/log/apache",      // Old apache
        "/var/log/httpd",       // Alt apache
    ];


    /**
     * @var resource
     */
    private static $customPrefix = false;
    /**
     * @var bool|Resource
     */
    private static $logfp = false;


    public static function color ( $color, $str )
    {
        $str = (null === $str) ? "(null)" : strval($str);

        return self::shouldColor() ? ($color . $str . self::TEXT_COLOR_SUFFIX) : $str;
    }



    public static function red ( $str ) { return self::color(self::TEXT_COLOR_RED, $str); }
    public static function cyan ( $str ) { return self::color(self::TEXT_COLOR_CYAN, $str); }
    public static function yellow ( $str ) { return self::color(self::TEXT_COLOR_YELLOW, $str); }
    public static function white ( $str ) { return self::color(self::TEXT_COLOR_WHITE, $str); }
    public static function green ( $str ) { return self::color(self::TEXT_COLOR_GREEN, $str); }
    public static function blue ( $str ) { return self::color(self::TEXT_COLOR_BLUE, $str); }
    public static function orange ( $str ) { return self::color(self::TEXT_COLOR_ORANGE, $str); }

    public static function ulred ( $str ) { return self::color(self::TEXT_COLOR_UL_RED, $str); }
    public static function ulcyan ( $str ) { return self::color(self::TEXT_COLOR_UL_CYAN, $str); }
    public static function ulyellow ( $str ) { return self::color(self::TEXT_COLOR_UL_YELLOW, $str); }
    public static function ulwhite ( $str ) { return self::color(self::TEXT_COLOR_UL_WHITE, $str); }
    public static function ulgreen ( $str ) { return self::color(self::TEXT_COLOR_UL_GREEN, $str); }

    public static function bgred ( $str ) { return self::color(self::TEXT_COLOR_BG_RED, $str); }
    public static function bgyellow ( $str ) { return self::color(self::TEXT_COLOR_BG_YELLOW, $str); }



    public static function log ()
    {
        $prefix = (self::DEBUG_TIMING || self::DEBUG_REMOTE) ? self::makePrefix() : "";
        $argc   = func_num_args();

        if ( 2 == $argc )
        {
            $key        = func_get_arg(0);
            $val        = func_get_arg(1);
            $prompt     = self::cyan($key . ": ");
            $longPrefix = $prefix . $prompt;
        }
        else
        {
            $val        = func_get_arg(0);
            $key        = "";
            $longPrefix = $prefix;
        }

        if ( is_scalar($val) )
            self::logScalar($longPrefix, $val);
        else
            self::logObject($prefix, $key, $val);
    }



    private static function makePrefix ()
    {
        $time = $remote = "";

        if ( self::DEBUG_TIMING )
        {
            $time = microtime(true);
            $time = $time - floor($time);
            $time = sprintf("%0.3f", $time);
            $time .= ' ';
            $time = self::red($time);
        }

        if ( self::DEBUG_REMOTE && isWeb() )
        {
            //$remote = $_SERVER['REMOTE_ADDR'] . ":" . $_SERVER['REMOTE_PORT'] . " ";
            //$remote = $_SERVER['REMOTE_ADDR'] . ":" . $_SERVER['REMOTE_PORT'] . "/" . $_SERVER['REQUEST_METHOD'] . " ";
            $remote = $_SERVER['REMOTE_ADDR'] . "/" . $_SERVER['REQUEST_METHOD'] . " ";
            //$remote = self::yellow($remote);
        }

        $prefix = $time . $remote;

        return $prefix;
    }


    private static function logScalar ( $prefix, $scalar )
    {
        //$mesg = is_bool($scalar) ? ($scalar ? self::ulgreen("true") : self::ulred("FALSE")) : self::yellow(strval($scalar));
        $mesg = is_bool($scalar) ? ($scalar ? self::ulgreen("true") : self::ulred("FALSE")) : strval($scalar);
        self::_log($prefix . $mesg);
    }



    /**
     * ****************************************************************
     * Pretty-prints a dump of the current call-stack.
     * ****************************************************************
     */
    public static function dump ()
    {
        try
        {
            throw new Exception();
        }
        catch ( Exception $e )
        {
            self::logException($e);
        }
    }


    public static function warn ()
    {
        $argc = func_num_args();

        if ( 2 == $argc )
        {
            $desc = func_get_arg(0);
            $item = func_get_arg(1);
            clog($desc, self::ulyellow("WARNING - $item"));
        }
        else
        {
            $item = func_get_arg(0);
            clog(self::ulyellow("WARNING - $item"));
        }
    }


    public static function error ( $mesg, $shouldAbort = false, $errorCode = 1 )
    {
        clog(self::ulred("ERROR - $mesg"));

        try
        {
            throw new Exception($mesg);
        }
        catch ( Exception $e )
        {
            clog($e);
        }

        if ( false !== $shouldAbort ) exit($errorCode);

        return false;
    }


    public static function abort ( $mesg, $errorCode = 1 ) { self::error($mesg, true, $errorCode); }


    private static function logObject ( $prefix, $desc, $item )
    {
        if ( null === $item )
        {
            $str = self::bgred("[NULL object]");
            $str = "== " . self::yellow($desc) . " " . $str . " ==";
            self::log($prefix . $str);
            return;
        }
        else if ( is_array($item) )
        {
            $descString = (0 == strlen($desc)) ? "<Array>" : "$desc <Array>";

            self::logArray($prefix, $descString, $item);
        }
        else if ( $item instanceof Exception )
        {
            self::logException($item);
        }
        else
        {
            if ( $item instanceof Wired )
            {
                try
                {
                    $ref        = new \ReflectionClass($item);
                    $type       = $ref->getName();
                    $descString = (0 == strlen($desc)) ? "<$type>" : "$desc <$type>";
                    $wiredHash  = call_user_func([ $item, "toHash" ]);

                    self::logArray($prefix, $descString, $wiredHash);
                    return;
                }
                catch ( \ReflectionException $e )
                {
                    clog($e);
                }
            }
            else
            {
                try
                {
                    $ref   = new \ReflectionClass($item);
                    $type  = $ref->getName();
                    $data  = var_export($item, true);
                    $color = self::TEXT_COLOR_RED;

                    $str = FJ::jsEncode($data);

                    $type = self::yellow("<$type>");
                    $str  = self::color($color, $str);

                    self::log($prefix . "$type: $str");
                }
                catch ( \ReflectionException $e )
                {
                    clog($e);
                }
            }
        }
    }


    /**
     * ****************************************************************
     *
     * Pretty-prints an array object.
     *
     * Handles recursively defined arrays.
     *
     * @param string $prefix
     * @param string $desc - Description to be printed above array
     *                     contents.
     * @param array  $item
     * @param int    $depth
     *
     * ****************************************************************
     */
    public static function logArray ( $prefix, $desc, $item, $depth = 0 )
    {
        $indent = self::createIndent($depth);

        //self::log("clogHandleArray/prefix: $prefix");
        //self::log("clogHandleArray/indent: [$indent]");
        //self::log("clogHandleArray/parent-pre: [$parentPre]");
        //print_r($item);

        $count = count($item);

        if ( 0 == $count )
        {
            $str = self::bgred("[EMPTY array]");
            $str = "== " . self::yellow($desc) . " " . $str . " ==";
            self::log($prefix . $str);
            return;
        }

        $arKeys = array_keys($item);
        if ( is_int($arKeys[0]) )
        {
            $padding   = ceil(log10($count));
            $preFormat = "  [%{$padding}d]: ";
            $blank     = "%-{$padding}s  ";
            $keyColor  = self::TEXT_COLOR_WHITE;
        }
        else if ( self::CLOG_ARRAY_KEY_FANCY && is_string($arKeys[0]) )
        {
            $padding = 0;
            foreach ( $arKeys as $k )
            {
                $len = strlen($k);
                if ( $len > $padding )
                {
                    $padding = $len;
                }
            }
            $preFormat = "  [%{$padding}s]: ";
            $blank     = "%-{$padding}s  ";
            $keyColor  = self::TEXT_COLOR_CYAN;
        }
        else
        {
            $preFormat = "  [%s]: ";
            $blank     = "%s";
            $keyColor  = self::TEXT_COLOR_CYAN;
        }

        $pre = sprintf($blank, $desc);
        $pre = self::ulyellow($pre);

        //self::log("clogHandleArray/pre: $pre");

        if ( 0 === $depth )
        {
            self::log($prefix . $pre);
        }
        else
        {
            //self::log($prefix . $parentPre . "<Array>");
        }

        //if ( 0 !== $depth )
        //$prefix = self::clogCreatePlaceholder(strlen($prefix), ' ');

        foreach ( $item as $key => $val )
        {
            $pre = sprintf($preFormat, $key);
            $pre = self::color($keyColor, $pre);

            if ( is_array($val) )
            {
                $post = self::ulcyan("Array");
                $str  = $pre . $post;
                self::log($prefix . $indent . $str);

                // Recursion.
                self::logArray($prefix, $desc, $val, 1 + $depth, $pre); // FIXME
            }
            else
            {
                if ( is_bool($val) )
                {
                    $color = $val ? self::TEXT_COLOR_UL_GREEN : self::TEXT_COLOR_UL_RED;
                    $v     = $val ? 'true' : 'FALSE';
                    $v     = self::color($color, $v);
                    $str   = $pre . $v;
                }
                else
                {
                    $val  = self::obfuscatePasswords($key, $val);
                    $post = self::green($val);
                    $str  = $pre . $post;
                }

                self::log($prefix . $indent . $str);
            }
        }
    }


    /**
     * ****************************************************************
     * Pretty-prints and Exception object.
     *
     * @param Exception $ex
     * ****************************************************************
     */
    private static function logException ( $ex )
    {
        /*
        if ( $ex instanceof FJEX && $ex->getCode() < 0 )
        {
            $str = sprintf("========######## [ %s ] ########========", $ex->getMessage());
            $str = self::ulyellow($str);
            self::log($str);
            return;
        }
        */

        $depth = 0;

        $prefixLen = strlen(dirname(__DIR__)) + 1;
        $file      = $ex->getFile();
        $file      = substr($file, $prefixLen);
        $mesg      = $ex->getMessage();

        $str = sprintf("%3d) %s - (%s:%d)", $depth, $mesg, $file, $ex->getLine());
        $str = self::bgred($str);
        self::log($str);

        $trace      = $ex->getTrace();
        $traceCount = count($trace);

        for ( $i = ($traceCount - 1); $i >= 0; --$i )
        {
            ++$depth;
            $exceptionLineGap = self::CLOG_MESG_EXCEPTION_WIDTH;

            $frame  = array_shift($trace);
            $file   = isset($frame['file']) ? $frame['file'] : "?";
            $line   = isset($frame['line']) ? $frame['line'] : "?";
            $caller = isset($frame['function']) ? $frame['function'] : "?";
            if ( isset($frame['class']) )
            {
                $class  = $frame['class'];
                $caller = "$class.$caller";
            }

            $file = basename($file);
            $mesg = "$file:$line";

            $str = sprintf("%3d) %s%-{$exceptionLineGap}s - (%s)", $depth, "", $caller, $mesg);
            $str = self::bgred($str);
            self::log($str);
        }
    }


    private static function createIndent ( $depth )
    {
        $indentCount = (self::CLOG_DEPTH_INDENT * $depth) + ((0 === $depth) ? 0 : 2);
        return self::createPlaceholder($indentCount);
    }


    private static function createPlaceholder ( $len, $char = ' ' )
    {
        $str = "";
        while ( $len-- )
        {
            $str .= $char;
        }
        return $str;
    }


    private static function obfuscatePasswords ( $key, $val )
    {
        // Deal with password-like fields.

        return !preg_match(self::CLOG_PASSWORD_PATTERN, $key)
            ? $val
            : self::createPlaceholder(strlen($val), "*");
    }


    /**
     * This opens a log file statically.
     *
     * DANGER - This has a side effect of setting the static file handler.
     */
    private static function initFileHandle ()
    {
        if ( isCLI() )
        {
            if ( self::CLOG_DEBUG_ERROR_LOG_DEFAULT ) error_log("IS-CLI; aborting!");

            self::$logfp = false;
            return;
        }

        if ( false !== self::$logfp ) return;

        $errorLogPath = ini_get(self::CLOG_ERROR_LOG_CONSTANT);

        if ( self::CLOG_DEBUG_ERROR_LOG_DEFAULT ) error_log("Log - error-log-path: $errorLogPath");

        $errorLogDir = dirname($errorLogPath);

        if ( !$errorLogPath || 0 == strlen($errorLogDir) )
        {
            $logdir = pathinfo(realpath("/proc/" . getmypid() . "/fd/2"), PATHINFO_DIRNAME);

            if ( self::CLOG_DEBUG_ERROR_LOG_DEFAULT ) error_log("Log - log-dir: $logdir");

            if ( !$logdir || 0 == strlen($logdir) )
            {
                if ( !self::CLOG_DEBUG_ERROR_LOG_DEFAULT ) self::initAlternateFileHandles();
                return;
            }
            else
            {
                $clogFilePath = $logdir . DIRECTORY_SEPARATOR . self::CLOG_FILENAME;
            }
        }
        else
        {
            $clogFilePath = $errorLogDir . DIRECTORY_SEPARATOR . self::CLOG_FILENAME;
        }

        if ( self::CLOG_DEBUG_ERROR_LOG_DEFAULT ) error_log("Trying to open clog file @ $clogFilePath...");

        self::$logfp = @fopen($clogFilePath, self::CLOG_FOPEN_MODE);
    }


    private static function initAlternateFileHandles ()
    {
        self::$logfp = false;

        foreach ( self::CLOG_ALT_FILE_DIRS as $dir )
        {
            $path = $dir . DIRECTORY_SEPARATOR . self::CLOG_FILENAME;

            if ( self::CLOG_DEBUG_ERROR_LOG_DEFAULT ) error_log("Log - Trying to open [ $path ] ...");

            $fp = @fopen($path, self::CLOG_FOPEN_MODE);
            if ( false !== $fp )
            {
                if ( self::CLOG_DEBUG_ERROR_LOG_DEFAULT ) error_log("Log - WIN - opened path [ $path ].");

                self::$logfp = $fp;
                return;
            }
        }
    }


    private static function isFileOpen () { return false !== self::$logfp; }


    private static function shouldColor () { return isCLI() || self::isFileOpen(); }


    private static function _log ( $mesg )
    {
        //error_log("FJ::log()");
        self::initFileHandle();

        if ( isWeb() && array_key_exists('REQUEST_TIME_FLOAT', $_SERVER) )
        {
            //error_log("Why doesn't this appear??");

            $now = new PrecTime();
            $rfc = $now->rfc();

            $mesgCustom = self::shouldColor() ? $rfc : "";

            $sessID = session_id();
            $sessID = substr($sessID, 0, 8);

            $mesgCustom .= " " . $sessID;

            $nowMillis         = $now->getWholeMillis();
            $reqTimeSec        = $_SERVER['REQUEST_TIME_FLOAT'];
            $reqTimeMillis     = $reqTimeSec * 1000;
            $diffMillis        = $nowMillis - $reqTimeMillis;
            $diffMillisRounded = round($diffMillis);

            $color = (self::CLOG_TIMING_THRESHOLD < $diffMillisRounded)
                ? self::TEXT_COLOR_BG_RED
                : self::TEXT_COLOR_YELLOW;

//            if ( self::CLOG_DEBUG_TIMING )
//            {
//                //error_log("rfc: $rfc");
//                //error_log("now: $nowMillis");
//                //error_log("req: $reqTimeMillis");
//                //error_log("dif: $diffMillis");
//                //error_log("dif-rounded: $diffMillisRounded");
//            }

            $diffString = sprintf("(+%'_3dms)", $diffMillisRounded);

            $diffInfo   = self::color($color, $diffString);
            $mesgCustom .= " $diffInfo";

            $self = (false !== self::$customPrefix)
                ? self::$customPrefix
                : $_SERVER['PHP_SELF'];

            $self = self::green($self);

            $mesgCustom .= " $self $mesg";

            if ( self::shouldColor() ) $mesgCustom .= "\n";
        }
        else
        {
            $mesgCustom = $mesg;
        }

        self::_outputLog($mesgCustom);
    }


    public static function setCustomPrefix ( $prefix ) { self::$customPrefix = $prefix; }


    public static function getCustomPrefix () { return self::$customPrefix; }


    public static function resetCustomPrefix () { self::$customPrefix = false; }


    private static function _outputLog ( $mesg )
    {
        if ( self::isFileOpen() )
        {
            // Actually write to file.
            $bytesWritten = @fwrite(self::$logfp, $mesg);
        }
        else
        {
            error_log($mesg);
        }
    }
}
