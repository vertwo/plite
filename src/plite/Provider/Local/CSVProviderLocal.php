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



namespace vertwo\plite\Provider\Local;



use Exception;
use vertwo\plite\Log;
use vertwo\plite\Provider\Base\CSVProviderBase;
use function vertwo\plite\clog;



class CSVProviderLocal extends CSVProviderBase
{
    private $parent = false;
    private $dir    = false;
    private $path   = false;



    function __construct ( $params = false ) { }



    /**
     * Do environment-specific initialization (test for readability, etc).
     *
     * @param bool|array $params
     *
     * @throws Exception
     */
    function init ( $params = false )
    {
        clog("CRUD.init() params", $params);

        parent::init($params);

        if ( false === $params )
            throw new Exception(self::ERROR_ENOFILESPEC);

        if ( !array_key_exists("file", $params) )
            throw new Exception(self::ERROR_ENOFILE);

        $file = $params['file'];

        if ( array_key_exists("dir", $params) )
            $this->dir = $params["dir"];

        $this->path = $this->dir . DIRECTORY_SEPARATOR . $file;

        clog("parent", $this->parent);
        clog("   dir", $this->dir);
        clog("  path", $this->path);

        if ( false === $this->dir )
        {
            Log::error("No directory specified; aborting.");
            throw new Exception("No directory specified for local CRUDProvider.");
        }

        if ( !file_exists($this->dir) )
        {
            Log::error("[ " . $this->dir . " ] does not exist; aborting.");
            throw new Exception("Specified dir does not exist (local).");
        }

        if ( !is_dir($this->dir) )
        {
            Log::error("[ " . $this->dir . " ] is not a directory; aborting.");
            throw new Exception("Specified dir is not a directory (local).");
        }

        if ( !is_readable($this->dir) )
        {
            Log::warn("[ " . $this->dir . " ] is NOT readable.");
        }

        if ( !is_writeable($this->dir) )
        {
            Log::warn("[ " . $this->dir . " ] is NOT writeable.");
        }

        if ( !file_exists($this->path) )
        {
            Log::error("[ " . $this->path . " ] does not exist; aborting.");
            throw new Exception("Specified path does not exist (local).");
        }

        if ( !is_file($this->path) )
        {
            Log::error("[ " . $this->path . " ] is not a file; aborting.");
            throw new Exception("Specified path is not a file (local).");
        }

        if ( !is_readable($this->path) )
        {
            Log::warn("[ " . $this->path . " ] is NOT readable.");
        }

        if ( !is_writable($this->path) )
        {
            Log::warn("[ " . $this->path . " ] is NOT writeable.");
        }

        clog("CRUD.init()", "CRUDProvider (local - {$this->path}) successfully init'ed.");

        parent::init($params);
    }



    function loadData ()
    {
        $data = file_get_contents($this->path);

        return $data;
    }



    function saveData ( $data )
    {
        $data = $this->mapToCSV($data);
        file_put_contents($this->path, $data);
    }
}
