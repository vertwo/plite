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
use function vertwo\plite\clog;



abstract class RoutedAjax extends Ajax
{
    private $whole;
    private $page;
    private $path;



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



    function page () { return $this->page; }
    function path () { return $this->path; }



    /**
     *
     * MAIN entry point!
     *
     */
    final public function main ()
    {
        $isWorkerEnv = $this->isAWSWorkerEnv();
        $env         = $isWorkerEnv ? "SQS" : "Web";

        Log::setCustomPrefix("[$env] " . $this->getCustomLoggingPrefix());

        $this->whole = $_SERVER['REQUEST_URI'];

        clog("whole", $this->whole);

        // Grabs the URI and breaks it apart in case we have querystring stuff
        $requri     = explode('?', $_SERVER['REQUEST_URI'], 2);
        $this->page = $requri[0];
        $this->path = 2 == count($requri) ? $requri[1] : "";

        $this->handleRequest();
    }
}
