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



use ErrorException;
use Exception;



abstract class CLI
{
    const DEBUG_ARGS  = false;
    const DEBUG_CLASS = false;



    static function looksLikeCLI () { return false == isset($_SERVER["SERVER_PORT"]); }
    static function isCLI () { return self::looksLikeCLI() && (php_sapi_name() === 'cli'); }
    static function isWeb () { return !self::isCLI(); }

    static function req ( $string ) { return $string . ":"; }
    static function opt ( $string ) { return $string . "::"; }



    private   $argc;
    private   $argv;
    protected $opts      = [];
    protected $remaining = [];
    protected $optind    = 0;
    private   $argIndex  = 0;



    /**
     * This provides a list of the short options, in classic getopts() format.
     *
     * @return string - Classic getopt() spec (with colons); empty string ok.
     */
    abstract protected function getShortOpts ();

    /**
     * This provides an array of the long options, in getopts() format.
     *
     * @return array - getopt() spec (with colons); empty array is ok.
     */
    abstract protected function getLongOpts ();

    /**
     * CLI entry point.
     *
     * @return int
     */
    abstract public function main ();



    protected function argc () { return $this->argc; }
    protected function argv () { return $this->argv; }



    /**
     * @return string
     * @throws Exception
     */
    function shift ()
    {
        if ( count($this->remaining) == $this->argIndex )
            throw new Exception("No argv[{$this->argIndex}]");

        return $this->remaining[$this->argIndex++];
    }
    function hasMoreArgs () { return $this->argIndex < count($this->remaining); }
    function reset () { $this->argIndex = 0; }
    function remaining () { return $this->remaining; }



    public function __construct ()
    {
        $this->argc = $_SERVER['argc'];
        $this->argv = $_SERVER['argv'];

        $shortOpts = $this->getShortOpts();
        $longOpts  = $this->getLongOpts();

        $this->optind = 0;
        $this->opts   = getopt($shortOpts, $longOpts, $this->optind);

        $this->remaining = array_slice($this->argv, $this->optind);

        if ( self::DEBUG_ARGS ) $this->dump();
    }



    public function failOnWarnings ()
    {
        set_error_handler(function ( $errNo, $errStr, $errFile, $errLine ) use ( &$previous )
        {
            $msg = "$errStr in $errFile on line $errLine";
            if ( $errNo == E_NOTICE || $errNo == E_WARNING )
            {
                throw new ErrorException($msg, $errNo);
            }
            else
            {
                clog("warn|notice", $msg);
            }
        });
    }



    public function dump ()
    {
        clog($this->argc, $this->argv);

        $shortOpts = $this->getShortOpts();
        $longOpts  = $this->getLongOpts();

        clog("short-opts", $shortOpts);
        clog(" long-opts", $longOpts);

        clog("options", $this->opts);

        clog("optind (remaining): $this->optind", $this->remaining);
    }



    protected function getopt ( $opt ) { return array_key_exists($opt, $this->opts) ? $this->opts[$opt] : false; }
    protected function hasopt ( $opt ) { return array_key_exists($opt, $this->opts); }
    protected function noopt ( $opt ) { return !$this->hasopt($opt); }
    protected function optCount ( $opt ) { return $this->noopt($opt) ? 0 : count($this->opts[$opt]); }



    public static function cleanInput ( $string )
    {
        $string = strtolower($string);
        $string = preg_replace("/[^a-z0-9\-]/", "", $string);
        return $string;
    }



    public static function run ()
    {
        $class = get_called_class();
        if ( self::DEBUG_CLASS ) clog("get_called_class", $class);

        /** @var CLI $cli */
        $cli = new $class();
        $cli->main();
    }



    public function readPassword ( $mesg )
    {
        $counter = 1;
        do
        {
            $mesg2 = (1 == $counter) ? $mesg : "$counter) $mesg";

            $p1    = self::readSinglePasswordFromCLI($mesg2);
            $p2    = self::readSinglePasswordFromCLI($mesg2 . " (again)");
            $match = (0 === strcmp($p1, $p2));
            if ( !$match )
            {
                clog("  Sorry, both those passwords didn't match.  Try again, please.");
            }
        } while ( !$match );

        return $p1;
    }



    private function readSinglePasswordFromCLI ( $mesg )
    {
        do
        {
            // Get password without echo'ing.
            echo "$mesg: ";
            system('stty -echo');
            $passwd = trim(fgets(STDIN));
            system('stty echo');
            // add a new line since the users CR didn't echo
            echo "\n";
        } while ( 0 == strlen($passwd) );

        return $passwd;
    }
}
