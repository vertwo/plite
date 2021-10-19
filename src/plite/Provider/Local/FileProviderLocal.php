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
use vertwo\plite\Provider\Base\FileProviderBase;
use function vertwo\plite\clog;



class FileProviderLocal extends FileProviderBase
{
    private $parent = false;
    private $dir    = false;



    function __construct ( $params )
    {
        $parent = $params["file_location"];
        $dir    = $params['file_bucket'];

        $this->parent = $parent;
        $this->dir    = $parent . DIRECTORY_SEPARATOR . $dir;
    }



    /**
     * @param $params array|bool
     *
     * @throws Exception
     */
    public function init ( $params = false )
    {
        //clog("FP.init() params", $params);

        if ( false !== $params )
        {
            if ( array_key_exists("dir", $params) )
            {
                $this->dir = $params["dir"];
            }

            if ( array_key_exists("bucket", $params) )
            {
                $bucket    = $params['bucket'];
                $this->dir = $this->parent . DIRECTORY_SEPARATOR . $bucket;
            }
        }

        if ( false === $this->dir )
        {
            Log::error("No directory specified; aborting.");
            throw new Exception("No directory specified for local FileProvider.");
        }

        if ( !file_exists($this->dir) )
        {
            Log::error("[ " . $this->dir . " ] does not exist; aborting.");
            throw new Exception("Specified path does not exist (local).");
        }

        if ( !is_dir($this->dir) )
        {
            Log::error("[ " . $this->dir . " ] is not a directory; aborting.");
            throw new Exception("Specified path is not a directory (local).");
        }

        if ( !is_readable($this->dir) )
        {
            Log::warn("[ " . $this->dir . " ] is NOT readable.");
        }

        if ( !is_writeable($this->dir) )
        {
            Log::warn("[ " . $this->dir . " ] is NOT writeable.");
        }

        //clog("FP.init()", "FileProvider (local - {$this->dir}) successfully init'ed.");
    }



    /**
     * @param bool|array $params
     *
     * @return array
     */
    public function ls ( $params = false )
    {
        $prefix = (false !== $params && array_key_exists('prefix', $params)) ? $params['prefix'] : "";

        //
        // NOTE - ...otherwise, look locally.
        //
        $pattern = $this->getLocalPathFromKey($prefix . "*");
        $list    = glob($pattern);

        if ( self::DEBUB_FILES_VERBOSE ) clog("pattern", $pattern);
        if ( self::DEBUB_FILES_VERBOSE ) clog("glob list", $list);

        $files = [];

        foreach ( $list as $entry )
        {
            $file = basename($entry);

            // if ( $this->doesNameConform($file) ) $files[] = $file;

            $files[] = $file;
        }

        if ( self::DEBUG_FILES ) clog("files", $files);

        return $files;
    }
    /**
     * @param bool|array $params
     *
     * @return array
     */
    public function lsDirs ( $params = false )
    {
        $prefix = (false !== $params || array_key_exists('prefix', $params)) ? $params['prefix'] : "";

        $dirs    = [];
        $entries = $this->ls($params);
        foreach ( $entries as $entry )
        {
            $wholePath = $this->getLocalPathFromKey($prefix . $entry);
            if ( is_dir($wholePath) ) $dirs[] = $entry;
        }
        return $dirs;
    }
    /**
     * @param bool|array $params
     *
     * @return array
     */
    public function lsFiles ( $params = false )
    {
        $prefix = (false !== $params || array_key_exists('prefix', $params)) ? $params['prefix'] : "";

        $files   = [];
        $entries = $this->ls($params);
        foreach ( $entries as $entry )
        {
            $wholePath = $this->getLocalPathFromKey($prefix . $entry);
            if ( is_file($wholePath) ) $files[] = $entry;
        }
        return $files;
    }



    /**
     * @param      $path
     * @param      $data
     * @param bool $meta
     *
     * @return bool|int
     *
     * @throws Exception
     */
    public function write ( $path, $data, $meta = false )
    {
        $data = trim($data) . "\n";

        $wholePath = $this->getLocalPathFromKey($path);

        $wholeDir = dirname($wholePath);
        if ( file_exists($wholeDir) )
        {
            if ( !is_dir($wholeDir) )
            {
                throw new Exception("Path component of ($wholeDir) already exists as non-dir.");
            }
        }
        else
        {
            $umask = umask(0);
            $isok  = mkdir($wholeDir, 0770, true);
            umask($umask);

            if ( false === $isok )
                throw new Exception("Could not create path component ($wholeDir)");
        }

        $isok = file_put_contents($wholePath, $data);

        return $isok;
    }



    /**
     * @param $path
     *
     * @return string
     *
     * @throws Exception - Happens if file does not exist.
     */
    public function read ( $path )
    {
        $wholePath = $this->getLocalPathFromKey($path);

        if ( self::DEBUG_FILES ) clog("FP.read()", $wholePath);

        if ( !file_exists($wholePath) ) throw new Exception("File does not exist (path [$wholePath] ok?).");

        $data = file_get_contents($wholePath);

        if ( false === $data ) throw new Exception("Could not read file (perms?).");

        return $data;
    }



    private function getLocalPathFromKey ( $filename )
    {
        return $this->dir . DIRECTORY_SEPARATOR . $filename;
    }
}
