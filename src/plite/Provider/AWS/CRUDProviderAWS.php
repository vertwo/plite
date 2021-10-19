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



namespace vertwo\plite\Provider\AWS;



use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Exception;
use vertwo\plite\Provider\Base\CRUDProviderBase;
use function vertwo\plite\clog;
use function vertwo\plite\redlog;



class CRUDProviderAWS extends CRUDProviderBase
{
    /**
     * @var S3Client
     */
    private $s3     = false;
    private $bucket = false;
    private $key    = false;



    /**
     * CRUDProviderAWS constructor.
     *
     * @param bool|array $params
     */
    function __construct ( $params = false )
    {
        $this->s3 = $params['s3'];
    }



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

        if ( false === $this->s3 )
        {
            redlog("CRUD.init(): Cannot establish AWS S3 connection.");
            throw new Exception("Cannot get AWS S3 reference; aborting.");
        }

        if ( array_key_exists("bucket", $params) ) $this->bucket = $params['bucket'];
        if ( array_key_exists("file", $params) ) $this->key = $params['file'];
        if ( array_key_exists("key", $params) ) $this->key = $params['key'];

        if ( false === $this->bucket )
            throw new Exception("No bucket specified; aborting");

        if ( false === $this->key )
            throw new Exception("No key specified; aborting");

        clog("CRUD.init(" . $this->bucket . ", " . $this->key . ")", "S3Client successfully init'ed.");
    }



    function loadData ()
    {
        $data = $this->getFromS3();

        return $data;
    }



    function saveData ( $data )
    {
        $params = [
            'Bucket' => $this->bucket,
            'Key'    => $this->key,
            'Body'   => $data,
            //'ACL'    => 'public-read',
        ];

        try
        {
            // Upload data.
            $result = $this->s3->putObject($params);

            $url = $result['ObjectURL'];
            clog("CRUD File URL (S3)", $url);
        }
        catch ( S3Exception $e )
        {
            clog($e);
        }
    }



    private function getFileContentsFromS3 ( $params )
    {
        $result = $this->s3->getObject($params);

        $body = $result['Body'];

        return $body;
    }



    private function getFromS3 ()
    {
        $params = [
            'Bucket' => $this->bucket,
            'Key'    => $this->key,
        ];

        return $this->getFileContentsFromS3($params);
    }
}
