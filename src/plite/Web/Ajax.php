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



use vertwo\plite\FJ;
use vertwo\plite\Log;
use vertwo\plite\Util\PrecTime;
use function vertwo\plite\clog;



class Ajax
{
    const DEBUG_NUKE                  = false;
    const DEBUG_AJAX_RESPONSE         = true;
    const DEBUG_AJAX_RESPONSE_VERBOSE = false;
    const AJAX_RESPONSE_VERBOSE_LEN   = 2048;

    const MIME_TEXT_PLAIN       = "text/plain";
    const MIME_APPLICATION_JSON = "application/json";



    /**
     * ************************************************************
     *
     * Invalidate and destroy SESSION, including cookies.
     *
     * ************************************************************
     */
    public static function nukeSession ()
    {
        if ( self::DEBUG_NUKE ) clog("nukeSession - ANTE - COOKIES", $_COOKIE);

        $_SESSION = [];

        // If it's desired to kill the session, also delete the session cookie.
        // Note: This will destroy the session, and not just the session data!
        if ( ini_get("session.use_cookies") )
        {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                      $params["path"], $params["domain"],
                      $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        if ( self::DEBUG_NUKE ) clog("nukeSession - POST - COOKIES", $_COOKIE);
    }



    public static function isElasticBeanstalkWorkerEnv ()
    {
        $userAgent = array_key_exists("HTTP_USER_AGENT", $_SERVER)
            ? $_SERVER['HTTP_USER_AGENT']
            : false;
        $mesgID    = array_key_exists("HTTP_X_AWS_SQSD_MSGID", $_SERVER)
            ? $_SERVER['HTTP_X_AWS_SQSD_MSGID']
            : false;
        $qID       = array_key_exists("HTTP_X_AWS_SQSD_QUEUE", $_SERVER)
            ? $_SERVER['HTTP_X_AWS_SQSD_QUEUE']
            : false;

        if ( false === $userAgent || false === $mesgID || false === $qID ) return false;

        $isok = "aws-sqsd/2.4" === $userAgent;

        return $isok;
    }



    protected $hasFiles  = false;
    protected $post      = [];
    protected $get       = [];
    protected $jsonInput = [];

    protected $now     = null;
    protected $at      = null;
    protected $success = false;
    protected $mesg    = null;
    protected $data    = [];

    protected $apiName      = "?";
    protected $hasResponded = false;

    protected $isElasticBeanstalkWorkerEnv = false;



    /**
     * ################################################################
     * ################################################################
     *
     * Create a Ajax object!
     *
     * ################################################################
     * ################################################################
     */
    public function __construct ( $apiName = null, $jsonInput = null )
    {
        $this->now = $_SERVER['REQUEST_TIME'];
        $this->at  = new PrecTime();

        if ( DEBUG_AJAX_TIMESTAMP )
        {
            clog("Ajax.ctor() -                       at", $this->at);
            clog("Ajax.ctor() -        at.getWholeMicros", $this->at->getWholeMicros());
            clog("Ajax.ctor() -        at.getWholeMillis", $this->at->getWholeMillis());
            clog("Ajax.ctor() -   at.getFractionalMillis", $this->at->getFractionalMillis());
            clog("Ajax.ctor() -       at.getWholeSeconds", $this->at->getWholeSeconds());
            clog("Ajax.ctor() - now: server-request-time", $this->now);
            clog("Ajax.ctor() -  at.getFractionalSeconds", $this->at->getFractionalSeconds());
        }

        $this->hasFiles = isset($_FILES) && (0 < count($_FILES));

        if ( isset($apiName) )
            $this->apiName = $apiName;

        if ( isset($jsonInput) )
            foreach ( $jsonInput as $jk => $jv )
            {
                $this->jsonInput[$jk] = $jv;
            }


        if ( DEBUG_AJAX_POST ) clog("Ajax.ctor/_POST", $_POST);
        if ( DEBUG_AJAX_GET ) clog("Ajax.ctor/_GET", $_GET);

        if ( isset($_POST) )
            $this->post = FJ::deepCopy($_POST);

        if ( isset($_GET) )
            $this->get = FJ::deepCopy($_GET);

        $this->apiName = $_SERVER["SCRIPT_NAME"];

        $this->isElasticBeanstalkWorkerEnv = self::isElasticBeanstalkWorkerEnv();
    }



    public function dump ( $mesg = false )
    {
        clog("$mesg [Ajax/POST]", $_POST);
        clog("$mesg [Ajax/GET] ", $_GET);
    }



    public function isAWSWorkerEnv () { return $this->isElasticBeanstalkWorkerEnv; }



    /**
     * Clears status of response.
     */
    public function clear ()
    {
        $this->data    = [];
        $this->success = false;
        $this->mesg    = null;
    }



    /**
     * Signal that request failed.  Outputs error.
     *
     * @param $mesg - Message to output & deliver.
     *
     * @return Ajax - For chaining.
     */
    public function fail ( $mesg )
    {
        Log::error($mesg);
        $this->success = false;
        $this->mesg    = $mesg;
        return $this;
    }



    const HTTP_OK                        = 200;
    const HTTP_ERROR_GENERIC             = 500;
    const HTTP_ERROR_NOT_IMPLEMENTED     = 501;
    const HTTP_ERROR_SERVICE_UNAVAILABLE = 503;



    public function http ( $httpStatusCode, $mesg )
    {
        Log::warn("(HTTP $httpStatusCode): $mesg");
        http_response_code($httpStatusCode);

        flush();
        ob_flush();
        exit();
    }



    /**
     * Gets information for a specific file-to-upload.
     *
     * @param string $fileKey - Specific file-to-upload - should be an entry from files().
     *
     * @return bool|array - Assoc array of file info, if it exists; (false) otherwise.
     */
    public function fileInfo ( $fileKey ) { return ($this->hasFiles && isset($_FILES[$fileKey])) ? $_FILES[$fileKey] : false; }



    /**
     * Gets files-to-upload, if any.
     *
     * @return array|bool - Array of files-to-upload; (false) otherwise).
     */
    public function files () { return $this->hasFiles() ? array_keys($_FILES) : false; }



    /**
     * Determines if GET contains specified key.
     *
     * @param string $key - Specified key.
     *
     * @return bool - (true) if GET contains key; (false) otherwise.
     */
    private function hasGet ( $key ) { return array_key_exists($key, $this->get); }



    /**
     * Determines if POST contains files-to-upload.
     *
     * @return bool - (true) if POST contains files-to-upload; (false) otherwise;
     */
    public function hasFiles () { return $this->hasFiles; }



    /**
     * Determines if POST contains specified key.
     *
     * @param string $key - Specified key.
     *
     * @return bool - (true) if POST contains key; (false) otherwise.
     */
    private function hasPost ( $key ) { return array_key_exists($key, $this->post); }



    /**
     * Detects if script is called via HTTPS.
     *
     * @return bool - (true) if HTTPS; (false) otherwise.
     */
    public function isHTTPS () { return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']; }



    /**
     * Detects if script is called via 'localhost' server name.
     *
     * @return bool - (true) if 'localhost'; (false) otherwise.
     */
    public function isLocalhost () { return isset($_SERVER['SERVER_NAME']) && ("localhost" === $_SERVER['SERVER_NAME']); }



    public function getConnectionID ( $info = false )
    {
        $tuple = $_SERVER['REMOTE_ADDR'] . "_" . $_SERVER['REMOTE_PORT'];

        if ( $this->isHTTPS() )
        {
            $tlsver = $_SERVER['SSL_PROTOCOL'];
            $tuple  = $tlsver . "_" . $tuple;
        }
        else
        {
            $tuple = "http_" . $tuple;
        }

        $shouldUseInfo = (false !== $info) && isset($info) && (0 < strlen($info));
        $infoString    = ($shouldUseInfo ? $info : "NO-AD-INFO");

        $id = $this->at->getWholeMicros() . "_" . $infoString . "_" . $tuple;
        return $id;
    }



    /**
     * Converts the response data into an AJAX JSON response.
     *
     * @return string
     */
    public function json ()
    {
        $resp = [];

        $resp['now']     = $this->at->getWholeMillis();
        $resp['success'] = $this->success;
        if ( isset($this->mesg) )
            $resp['mesg'] = $this->mesg;

        if ( $this->success )
        {
            if ( isset($this->data) )
                $resp['data'] = $this->data;
            else
                $resp['data'] = [];
        }
        else
        {
            $resp['error'] = $this->mesg;
        }

        $json = FJ::jsEncode($resp);

        if ( DEBUG_AJAX_RESPONSE ) clog("Ajax.json/output", $json);

        return $json;
    }



    /**
     * Gets the current, high-precision, time.
     *
     * @return PrecTime|null
     */
    public function now () { return $this->at; }



    /**
     * Send the AJAX response (JSON-encoded), and EXIT script (unrolling callers).
     *
     * NOTE - Aborts callers!
     *
     * @param string $contentType - MIME Type
     * @param null   $content
     */
    public function respond ( $contentType = self::MIME_APPLICATION_JSON, $content = null )
    {
        $response = (null == $content) ? $this->json() : $content;
        $this->raw($contentType, $response);
    }



    /**
     * @param string $message
     * @param string $mimeType
     * @param null   $content
     */
    public function fastfail ( $message, $mimeType = "application/json", $content = null )
    {
        $this->fail($message);
        $this->respond($mimeType, $content);
    }



    /**
     * Send the AJAX response (JSON-encoded), closes the client connection, but keeps running.
     *
     * DANGER - DOES NOT STOP processing.
     *
     * @return int - Length of output string (not including headers).
     */
    public function respondAndContinue ()
    {
        if ( !$this->hasResponded )
        {
            $this->hasResponded = true;
            $response           = $this->json();
            $length             = strlen($response);

            header('Content-Type: application/json');
            header('Connection: close');
            header("Content-Length: $length");
            echo $response;

            flush();

            return $length;
        }
        else
        {
            return 0;
        }
    }



    /**
     * Set a return value in the 'data' hash.
     *
     * @param mixed $key   - Key
     * @param mixed $value - Value
     *
     * @return - Ajax object for builder pattern.
     */
    public function set ( $key, $value )
    {
        $this->data[strval($key)] = $value;
        return $this;
    }



    public function getAll () { return array_merge($_GET, $_POST); }
    public function getAllAsJSON () { return FJ::js(array_merge($_GET, $_POST)); }



    /**
     * Sets a value in the $_SESSION.
     *
     * @param string $key   - Key to use
     * @param string $value - Value to set
     */
    public function setSession ( $key, $value ) { $_SESSION[$key] = $value; }



    public function hasSession ( $key ) { return array_key_exists($key, $_SESSION); }



    public function getSession ( $key ) { return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : false; }



    public function clearSession ( $key ) { if ( array_key_exists($key, $_SESSION) ) unset($_SESSION[$key]); }



    /**
     * Sets the number of seconds this script is allowed to run.
     *
     * @param $seconds
     */
    public function setSessionTimeout ( $seconds ) { set_time_limit($seconds); }



    /**
     * Fail-fast test-and-get of an HTTP GET key.
     *
     * NOTE - Aborts callers if specified key does not exist!
     *
     * @param mixed $key
     *
     * @return string value if 'key' exists; EXIT script otherwise (unrolling callers).
     */
    public function testAbortGet ( $key )
    {
        $val = $this->testGet($key);
        if ( false === $val )
        {
            clog("AJAX/{$this->apiName} - GET key [ $key ] unspecified; exiting");
            $this->fail("GET key [ $key ] unspecified");
            $this->respond();
        }
        return $val;
    }



    /**
     * Fail-fast test-and-get of an HTTP POST or GET key.
     *
     * NOTE - Aborts callers if specified key does not exist!
     *
     * @param string $key
     *
     * @return string|array value of 'key' if it exists; EXIT script otherwise (literal script exit).
     */
    public function testAbortBoth ( $key )
    {
        $val = $this->testPost($key);
        if ( false === $val )
        {
            $val = $this->testGet($key);

            if ( false === $val )
            {
                clog("AJAX/{$this->apiName} - POST/GET key [ $key ] unspecified; exiting");
                $this->fail("POST/GET key [ $key ] unspecified");
                $this->respond();
            }
        }

        return $val;
    }



    /**
     * Fail-fast test-and-get of an HTTP POST key.
     *
     * NOTE - Aborts callers if specified key does not exist!
     *
     * @param string $key
     *
     * @return string value of 'key' if it exists; EXIT script otherwise (literal script exit).
     */
    public function testAbortPost ( $key )
    {
        $val = $this->testPost($key);
        if ( false === $val )
        {
            clog("AJAX/{$this->apiName} - POST key [ $key ] unspecified; exiting");
            $this->fail("POST key [ $key ] unspecified");
            $this->respond();
        }
        return $val;
    }



    /**
     * Fail-fast test-and-get of a PHP SESSION key.
     *
     * NOTE - Aborts callers if specified key does not exist!
     *
     * @param string $key
     *
     * @return string value of 'key' if it exists; EXIT script otherwise (literal script exit).
     */
    public function testAbortSession ( $key )
    {
        $val = $this->testSession($key);
        if ( false === $val )
        {
            clog("AJAX/{$this->apiName} - SESSION key [ $key ] unspecified; exiting");
            $this->fail("SESSION key [ $key ] unspecified");
            $this->respond();
        }
        return $val;
    }



    /**
     * Test-and-get of an HTTP POST or GET key.
     *
     * @param string $key
     *
     * @return string|bool - value of 'key' exists; otherwise, (false)
     */
    public function testBoth ( $key )
    {
        $val = $this->testPost($key);
        if ( false === $val )
        {
            $val = $this->testGet($key);
        }

        return $val;
    }



    /**
     * Test-and-get of an HTTP GET key.
     *
     * @param string $key
     *
     * @return string|array - String value if 'key' exists; otherwise, (false)
     */
    public function testGet ( $key ) { return $this->hasGet($key) ? $this->get[$key] : false; }



    /**
     * Test-and-get of an HTTP POST key.
     *
     * @param string $key
     *
     * @return String value if 'key' exists; otherwise, (false)
     */
    public function testPost ( $key ) { return $this->hasPost($key) ? $this->post[$key] : false; }



    /**
     * Gets a value in the $_SESSION.
     *
     * @param string $key - Key to use
     *
     * @return mixed
     */
    public function testSession ( $key ) { return isset($_SESSION[$key]) ? $_SESSION[$key] : false; }



    /**
     * Indicate AJAX call is successful (from the server's POV).
     *
     * @param string $mesg - Optional message
     *
     * @return Ajax - For chaining.
     */
    public function win ( $mesg = null )
    {
        $this->success = true;
        $this->mesg    = $mesg;
        return $this;
    }



    /**
     * Dumps file through HTTP.
     *
     * @param string $filename
     * @param string $content
     */
    public function textFile ( $filename, $content )
    {
        $content = trim($content) . "\n";
        $this->file($filename, $content);
    }



    /**
     * Dumps file through HTTP.
     *
     * @param string $filename
     * @param string $content
     */
    public function file ( $filename, $content )
    {
        $len = strlen($content);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $len);

        echo $content;

        flush();
        ob_flush();

        exit(); // NOTE - Exit PHP - Immediately terminate execution
    }



    public function raw ( $contentType, $content )
    {
        if ( !$this->hasResponded )
        {
            $this->hasResponded = true;

            $contenTypeString = 'Content-Type: ' . $contentType;

            header($contenTypeString);

            clog("Content-Type (string)", $contenTypeString);

            if ( self::DEBUG_AJAX_RESPONSE )
            {
                $displayMesg = self::DEBUG_AJAX_RESPONSE_VERBOSE
                    ? $content
                    : FJ::clipString($content, self::AJAX_RESPONSE_VERBOSE_LEN);

                clog("AJAX response", $displayMesg);
            }

            echo "$content" . "\n";
            flush();
            ob_flush();
        }

        exit(); // NOTE - Exit PHP - Immediately terminate execution
    }
}
