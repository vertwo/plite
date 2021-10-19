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
use function vertwo\plite\clog;
use function vertwo\plite\redlog;



abstract class AjaxWorkerAPI extends AjaxAPI
{
    /**
     * Gets the valid endpoints.
     *
     * AjaxEndpoints makes no sense without this; what would it be calling?
     *
     * @return array - List of valid methods.
     */
    function getValidEndpoints () { return [ 'processSQSMessages', ]; }



    /**
     * A prefix to append to log messages, if any.
     *
     * @return mixed
     */
    function getLogPrefix () { return "SQS"; }



    /**
     * How to deal with the exception percolated to here.
     *
     * @param Exception $e
     */
    function handleAPIPercolatedException ( $e )
    {
        clog($e);
        redlog("Could not handle UPLOAD: " . $e->getMessage());
        $this->http(self::HTTP_ERROR_GENERIC, "Could not handle UPLOAD; aborting.");
        exit(99);
    }



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
    function authorizeCaller ( $actualMethod ) { return true; }



    /**
     * This is the main entry point for SQS messages.
     *
     * Since this is the abstract class for workers, this method must be implemented.
     */
    abstract function processSQSMessages ();
}
