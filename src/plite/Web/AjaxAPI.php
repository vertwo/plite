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



namespace vertwo\plite\Web;



use Exception;
use vertwo\plite\FJ;
use vertwo\plite\Log;
use function vertwo\plite\cclog;
use function vertwo\plite\clog;
use function vertwo\plite\grnlog;



abstract class AjaxAPI extends Ajax
{
    const DEBUG_METHOD = false;
    const DEBUG_API    = true;

    public static function alnumOnly ( $input )
    {
        // https://stackoverflow.com/questions/659025/how-to-remove-non-alphanumeric-characters
        return preg_replace("/[^[:alnum:]]/", "", $input);
    }

    public static function cleanAlnumLower ( $input ) { return strtolower(self::alnumOnly($input)); }
    public static function cleanAlnumDashesUnderscores ( $key ) { return preg_replace("/[^[:alnum:]\-_]/", "", $key); }



    /**
     * Gets the valid endpoints.
     *
     * AjaxEndpoints makes no sense without this; what would it be calling?
     *
     * @return array - List of valid methods.
     */
    abstract function getValidEndpoints ();
    /**
     * A prefix to append to log messages, if any.
     *
     * @return string
     */
    abstract function getLogPrefix ();
    /**
     * How to deal with the exception percolated to here.
     *
     * @param Exception $e
     */
    abstract function handleAPIPercolatedException ( $e );
    /**
     * Checks if caller is authorized; if not, this method should handle,
     * including calling header() for raw HTTP response, and exiting as
     * necessary via exit().
     *
     * If caller is not authorized, this method MUST return false, at which
     * point call will exit(99) immediately.
     *
     * WARN - DO NOT ASSUME that anything good happens after this call
     *  returns (false).
     *
     * @param $actualMethod - Method to invoke.
     *
     * @return boolean - Is caller authorized?
     */
    abstract function authorizeCaller ( $actualMethod );



    private static function buildMatchingEndpoints ( $validEndpoints )
    {
        $methodMap = [];

        foreach ( $validEndpoints as $validEndpoint )
        {
            $cleanedEndpoint = self::cleanAlnumLower($validEndpoint);

            $methodMap[$cleanedEndpoint] = $validEndpoint;
        }

        if ( self::DEBUG_METHOD ) clog("method-map", $methodMap);

        return $methodMap;
    }



    protected static function getMatchingEndpoint ( $endpoint, $validEndpoints )
    {
        $methodMap = self::buildMatchingEndpoints($validEndpoints);

        //
        // NOTE - This is *MUCH* safer than just asking if method 'is_callable()'...
        //

        foreach ( $methodMap as $validEndpoint => $actualMethod )
        {
            if ( 0 == strcmp($endpoint, $validEndpoint) )
            {
                if ( self::DEBUG_METHOD ) clog("Endpoints->call()", $endpoint);
                return $actualMethod;
            }
        }

        return false;
    }



    protected function getEndpointToCall ( $endpoint )
    {
        $rawEndpoints     = $this->getValidEndpoints();
        $validEndpoints   = self::buildMatchingEndpoints($rawEndpoints);
        $matchingEndpoint = self::getMatchingEndpoint($endpoint, $validEndpoints);

        if ( self::DEBUG_METHOD ) clog("known endpoints", $rawEndpoints);
        if ( self::DEBUG_METHOD ) clog("valid endpoints", $validEndpoints);
        if ( self::DEBUG_METHOD ) clog("matching endpoint", $matchingEndpoint);

        return $matchingEndpoint;
    }



    public function call ( $method )
    {
        //
        // Clean method name.
        //
        $method = self::cleanAlnumLower($method);

        //
        //
        // NOTE - ...Do the method VERIFICATION soon after.
        //
        //
        $actualMethod = $this->getEndpointToCall($method);

        if ( false === $actualMethod )
        {
            cclog(Log::TEXT_COLOR_BG_RED, "Calling unknown/unpublished method [ $method ]; aborting.");
            exit(98);
        }

        //
        //
        // NOTE - Let subclass tell us how to authenticate the caller.
        //
        //
        $isAllowed     = $this->authorizeCaller($actualMethod);
        $notAuthorized = !$isAllowed;

        if ( $notAuthorized )
        {
            exit(99);
        }

        //
        //
        // NOTE - NOTE - Actually call the endpoint.
        //
        //
        return $this->callEndpointMethod($actualMethod); // NOTE - Actually call the endpoint.
    }



    /**
     * @return Ajax
     */
    private function callEndpointMethod ( $actualMethod )
    {
        $prefix = $this->getLogPrefix();

        Log::setCustomPrefix($prefix . "->" . $actualMethod . "()");

        clog("\n");

        //clog("-----=====[ API - $actualMethod ] =====-----");

        $isSensitive     = self::isSensitive($actualMethod);
        $hasPostKeys     = (0 != count(array_keys($_POST)));
        $hasGetKeys      = (0 != count(array_keys($_GET)));
        $isSensitiveMesg = $isSensitive ? " / SENSITIVE" : "";

        $mesg = "-----=====[ $prefix - {$actualMethod}{$isSensitiveMesg} ] =====-----";

        clog($mesg);

        if ( $isSensitive ) clog($mesg);
        else if ( $hasPostKeys ) clog("POST params", $_POST);
        else if ( $hasGetKeys ) clog("GET params", $_GET);
        else clog($mesg);

        if ( !$this->isAWSWorkerEnv() )
            clog("PHP Session", $_SESSION);

        switch ( $actualMethod )
        {
            case 'auth':
            case 'logout':
                break;

            default:
                session_write_close();
                break;
        }

        try
        {
            $this->{$actualMethod}(); // <------------- MEAT!!!
        }
        catch ( Exception $e )
        {
            return $this->handleAPIPercolatedException($e);
        }

        return $this;
    }



    private static function cleanMethod ( $method )
    {
        $method = FJ::stripNon7BitCleanASCII(trim($method));
        $method = substr(trim($method), 0, 256);
        $method = strtolower($method);

        return $method;
    }



    private static function hasAuthKeyword ( $str )
    {
        $str = strtolower($str);
        return false
               || stristr($str, "pass")
               || stristr($str, "user")
               || stristr($str, "log")
               || stristr($str, "auth")
               || stristr($str, "key")
               || stristr($str, "secret");
    }



    private static function isSensitive ( $method )
    {
        if ( self::hasAuthKeyword($method) ) return true;

        $postKeys = array_keys($_POST);

        foreach ( $postKeys as $gk => $val )
        {
            $hasAuthData = self::hasAuthKeyword($gk);
            if ( $hasAuthData ) return true;
        }

        $getKeys = array_keys($_GET);

        foreach ( $getKeys as $gk => $val )
        {
            $hasAuthData = self::hasAuthKeyword($gk);
            if ( $hasAuthData ) return true;
        }

        return false;
    }



    /**
     *
     * MAIN entry point!
     *
     */
    public function main ()
    {
        session_start();

        $isWorkerEnv = $this->isAWSWorkerEnv();
        $env         = $isWorkerEnv ? "SQS" : "API";

        Log::setCustomPrefix($env);

        $method = $this->testBoth("method");

        if ( false === $method )
        {
            if ( $isWorkerEnv )
            {
                $method = "processSQSMessages";
                grnlog("No problem; this is an SQS worker; defaulting to [" . $method . "]");
            }
            else
            {
                clog($env, "Dropping request because no method.");
                self::nukeSession();
                $this->http(self::HTTP_ERROR_NOT_IMPLEMENTED, "No API method specified; aborting.");
                exit(1);
            }
        }

        $method = self::cleanMethod($method);

        $this->call($method)->respond(); // NOTE - DONE here, after Ajax::respond().

        exit(0);
    }
}
