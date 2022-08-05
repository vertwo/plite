<?php
/**
 * Copyright (c) 2012-2022 Troy Wu
 * Copyright (c) 2021-2022 Version2 OÜ
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
use vertwo\plite\Provider\ProviderFactory;
use function vertwo\plite\clog;
use function vertwo\plite\cynlog;
use function vertwo\plite\grnlog;
use function vertwo\plite\redulog;
use function vertwo\plite\yellog;



/**
 * Routing class, extending RoutedAjax.  Basically, all requests show up here.
 *
 * Expects a .htaccess file like this:
 *
 * #----
 * RewriteEngine on
 * RewriteCond %{REQUEST_FILENAME} !-f
 * RewriteRule ^(.*)$ route.php?url=$1  [L,QSA]
 * #----
 *
 * Class RoutedAjax
 *
 * @package vertwo\plite\Web
 */
abstract class WebRouter extends Ajax
{
    const DEBUG = true;

    const CONFIG_KEY_ROUTING_ROOT = "routing_root";
    const DEFAULT_INPUT_MAXLEN    = 256;

    protected $whole;
    protected $page;
    protected $path;
    protected $query;

    private $routingRoot;



    /**
     * Subclass returns string to represent app (or other context).
     *
     * Completely arbitrary, meant to facility grep & CLI tools.
     *
     * @return string
     */
    public abstract function getCustomLoggingPrefix ();



    /**
     * Subclass implements to handle HTTP request.
     *
     * @return mixed
     */
    public abstract function handleRequest ();



    static function cleanInput ( $method, $size = self::DEFAULT_INPUT_MAXLEN )
    {
        $m = FJ::stripNon7BitCleanASCII(FJ::stripSpaces(trim($method)));
        $m = substr(trim($m), 0, $size);
        $m = strtolower($m);

        return $m;
    }



    /**
     * RoutedAjax constructor.
     *
     * @param ProviderFactory $pf
     */
    function __construct ( $pf )
    {
        parent::__construct();

        $this->routingRoot = $pf->get(self::CONFIG_KEY_ROUTING_ROOT);

        $isWorkerEnv = $this->isAWSWorkerEnv();
        $env         = $isWorkerEnv ? "SQS" : "Web";

        Log::setCustomPrefix("[$env] " . $this->getCustomLoggingPrefix());

        $this->whole = $_SERVER['REQUEST_URI'];

        $requri      = explode('?', $this->whole, 2);
        $uri         = $requri[0];
        $this->query = 2 == count($requri) ? $requri[1] : "";

        //
        // NOTE - Test if this is being routed by the PROPER .htaccess rewrite...
        //
        $url = $this->testGet("url");

        if ( false !== $url )
        {
            $rewrittenTokens = explode("/", $url, 2);
            $this->page      = $rewrittenTokens[0];
            $this->path      = 2 == count($rewrittenTokens) ? $rewrittenTokens[1] : "";
        }
        else
        {
            $requri = explode('?', $this->whole, 2);
            $uri    = $this->getRequestWithoutPrefix($this->routingRoot);

            clog("routing root", $this->routingRoot);
            clog("actual request", $uri);

            $pathTokens = explode("/", $uri, 3);

            clog("path tokens", $pathTokens);

            $this->page = 2 == count($pathTokens) ? $pathTokens[1] : "";
            $this->path = 3 == count($pathTokens) ? $pathTokens[2] : "";
        }

        $this->page = self::cleanInput($this->page);

        if ( self::DEBUG ) clog("whole", $this->whole);
        if ( self::DEBUG ) clog("page", $this->page);
        if ( self::DEBUG ) clog("path", $this->path);
    }



    /**
     * Called as the "first" thing to happen, before headers & 'main' processing.
     */
    function initSession ()
    {
        if ( PHP_SESSION_NONE === session_status() )
        {
            session_start();
            grnlog("----====[ Session STARTED ]====----");
        }
        else
        {
            yellog("----====[ Session resuming ]====----");
        }
    }



    /**
     * Called after the session init, but before 'main' processing.
     *
     * Override to disable default-handling; a noop is fine.
     */
    function initCacheHeaders ()
    {
        cynlog("----====[ Disabling Cache ]====----");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        header("Expires: 0"); // Date in the past
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, no-transform, max-age=0"); //HTTP 1.1
        header("Cache-Control: post-check=0, pre-check=0"); //HTTP 1.1
        header("Pragma: no-cache"); //HTTP 1.0
    }



    function getRequestWithoutPrefix ( $prefix )
    {
        if ( FJ::startsWith($prefix, $this->whole) )
        {
            return substr($this->whole, strlen($prefix));
        }
        else
        {
            return "";
        }
    }



    function abortIfNotRouted ( $abortPage )
    {
        if ( FJ::endsWith(".php", $this->page) || FJ::endsWith(".html", $this->page) )
        {
            redulog("NOT-ROUTED: [ " . $this->page . " ]; aborting.");
            header("Location: $abortPage");
            exit(1);
        }
    }



    /**
     *
     * MAIN entry point!
     *
     */
    final public function main ()
    {
        $this->initSession();
        $this->initCacheHeaders();
        $this->handleRequest();
        exit(0);
    }
}
