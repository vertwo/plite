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



namespace vertwo\plite\Provider;



use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\SecretsManager\SecretsManagerClient;
use Aws\Ses\SesClient;
use Exception;
use vertwo\plite\FJ;
use vertwo\plite\integrations\TrelloIntegration;
use vertwo\plite\Log;
use vertwo\plite\Provider\AWS\CRUDProviderAWS;
use vertwo\plite\Provider\AWS\CSVProviderAWS;
use vertwo\plite\Provider\AWS\EmailProviderAWS;
use vertwo\plite\Provider\AWS\FileProviderAWS;
use vertwo\plite\Provider\Database\PG;
use vertwo\plite\Provider\Local\CRUDProviderLocal;
use vertwo\plite\Provider\Local\CSVProviderLocal;
use vertwo\plite\Provider\Local\EmailProviderLocal;
use vertwo\plite\Provider\Local\FileProviderLocal;
use function vertwo\plite\cclog;
use function vertwo\plite\clog;
use function vertwo\plite\redlog;



abstract class ProviderFactory
{
    const DEBUG_CONFIG_INFO     = false;
    const DEBUG_DB_CONN         = false;
    const DEBUG_DB_CONN_VERBOSE = false;
    const DEBUG_SECRETS_MANAGER = true;
    const DEBUG_AWS_CREDS       = false;



    const DEBUG_CREDS_DANGEROUS = false; // DANGER - In __PRODUCTION__, this must be set to (false)!!!!!



    const CREDENTIALS_ARRAY_KEY = "credentials";

    const KEY_AUTH_FILE   = "auth_file";
    const KEY_AUTH_BUCKET = "auth_bucket";
    const KEY_AUTH_KEY    = "auth_key";

    const KEY_FILE_LOCATION = "file_location";
    const KEY_FILE_BUCKET   = "file_bucket";

    const AWS_ACCESS_ARRAY_KEY  = "aws_access_key_id";
    const AWS_SECRET_ARRAY_KEY  = "aws_secret_access_key";
    const AWS_REGION_ARRAY_KEY  = "aws_region";
    const AWS_VERSION_ARRAY_KEY = "aws_version";

    const DEFAULT_FJ_AWS_REGION  = "eu-west-1";
    const DEFAULT_FJ_AWS_VERSION = "latest";

    const PROVIDER_LOCAL = "local";
    const PROVIDER_PROXY = "proxy";
    const PROVIDER_CLOUD = "cloud";

    const DB_HOST_ARRAY_KEY = "db_host_";
    const DB_PORT_ARRAY_KEY = "db_port_";
    const DB_NAME_ARRAY_KEY = "db_name_";
    const DB_USER_ARRAY_KEY = "db_user_";
    const DB_PASS_ARRAY_KEY = "db_password_";

    const DEFAULT_LOCAL_PATH_PREFIX        = "/srv/";
    const DEFAULT_LOCAL_PATH_PREFIX_BACKUP = "/Users/srv/";

    const CONFIG_TYPE_BASE      = "config";
    const FILE_CONFIG           = self::CONFIG_TYPE_BASE;
    const PATH_COMPONENT_CONFIG = "/" . self::FILE_CONFIG . "/";

    const CONFIG_TYPE_CREDS    = "auth";
    const FILE_CREDS           = self::CONFIG_TYPE_CREDS;
    const PATH_COMPONENT_CREDS = "/" . self::CONFIG_TYPE_CREDS . "/";

    const PROVIDER_TYPE_FILE   = "file";
    const PROVIDER_TYPE_CRUD   = "crud";
    const PROVIDER_TYPE_CSV    = "csv";
    const PROVIDER_TYPE_DB     = "db";
    const PROVIDER_TYPE_SECRET = "secrets";
    const PROVIDER_TYPE_EMAIL  = "email";



    private $localPathPrefix = self::DEFAULT_LOCAL_PATH_PREFIX;



    abstract public function getAppName ();
    abstract protected function setConfigDefaults ();



    /**
     * ProviderFactory constructor.
     *
     * @param string $localPathPrefix
     */
    function __construct ( $localPathPrefix = self::DEFAULT_LOCAL_PATH_PREFIX )
    {
        if ( file_exists($localPathPrefix) && is_dir($localPathPrefix) )
        {
            $this->localPathPrefix = $localPathPrefix;
        }
        else
        {
            $localPathPrefix = self::DEFAULT_LOCAL_PATH_PREFIX_BACKUP;

            if ( file_exists($localPathPrefix) && is_dir($localPathPrefix) )
            {
                $this->localPathPrefix = $localPathPrefix;
            }
            else
            {
                clog("No LOCAL config; will likely call setConfigDefaults() in sublcass.");
            }
        }
    }



    private function loadConfigParams ()
    {
        return $this->hasLocalConfig()
            ? $this->loadLocalConfig()
            : $this->setConfigDefaults();
    }



    public function getProviderValue ( $provider, $configKeyShort )
    {
        $params = $this->loadConfigParams();

        $provKey = $provider . "_provider";

        if ( !array_key_exists($provKey, $params) ) return false;

        $prov = $params[$provKey];

        $configKey = $provider . "_" . $configKeyShort . "_" . $prov;

        if ( !array_key_exists($configKey, $params) ) return false;

        return $params[$configKey];
    }



    private function isUsingLocalProvider ( $providerType )
    {
        $key = $providerType . "_provider";
        return $this->matches($key, self::PROVIDER_LOCAL);
    }



    private function isUsingCloudProvider ( $providerType )
    {
        $key = $providerType . "_provider";
        return $this->matches($key, self::PROVIDER_CLOUD);
    }



    private function matches ( $key, $targetValue )
    {
        return $this->has($key) ? $targetValue === $this->get($key) : false;
    }



    public function has ( $key )
    {
        $params = $this->loadConfigParams();
        $val    = array_key_exists($key, $params);
        $params = false;
        return $val;
    }



    public function get ( $key )
    {
        $params = $this->loadConfigParams();
        $val    = array_key_exists($key, $params) ? $params[$key] : null;
        $params = false;
        return $val;
    }



    private function getConfigFilePath () { return $this->getPathPrefix(self::PATH_COMPONENT_CONFIG) . $this->getPathFilename(self::FILE_CONFIG); }
    private function getAuthFileName () { return $this->getPathFilename(self::FILE_CREDS); }
    private function getAuthFilePath () { return $this->getPathPrefix(self::PATH_COMPONENT_CREDS) . $this->getAuthFileName(); }
    private function getPathPrefix ( $component ) { return $this->localPathPrefix . $this->getAppName() . $component; }
    private function getPathFilename ( $file ) { return $this->getAppName() . "-" . $file . ".js"; }



    private function hasLocalConfig ()
    {
        return file_exists($this->getConfigFilePath());
    }



    /**
     * @return array - JSON object containing both the config and auth info.
     */
    private function loadLocalConfig ()
    {
        $conf   = self::loadConfigFile($this->getConfigFilePath());
        $auth   = self::loadConfigFile($this->getAuthFilePath());
        $params = array_merge($conf, $auth);

        return $params;
    }



    private static function loadConfigFile ( $file )
    {
        if ( !is_readable($file) )
        {
            redlog("Could not read config file: $file");
            return [];
        }

        $json = file_get_contents($file);

        if ( self::DEBUG_CONFIG_INFO ) clog("config(json)", $json);

        return FJ::jsDecode($json);
    }



    /**
     * If this is running on a local machine with a config file,
     * use the credentials in the config file; otherwise, NOTE: DO NOTHING.
     *
     * When "nothing" is done, then allow AWS Client libraries to try to
     * pickup the role credentials.  This will work on EC2, and with the
     * command line.
     *
     * @param array|bool $params
     *
     * @return array
     */
    private function getCredsAWS ( $params = false )
    {
        if ( false === $params ) $params = [];

        $config = false;

        //
        // NOTE - If we have local params, use them.  This allow you
        //        to simulate the cloud environment on your local box
        //        using a config file, preferably in the /.../app/auth
        //        file...
        //        ...If, however, we DO NOT have local params, then this
        //        is the CLOUD config.  Use that.
        //
        if ( $this->hasLocalConfig() )
        {
            $config = $this->loadLocalConfig();

            $access = $config[self::AWS_ACCESS_ARRAY_KEY];
            $secret = $config[self::AWS_SECRET_ARRAY_KEY];

            if ( self::DEBUG_AWS_CREDS ) clog(self::AWS_ACCESS_ARRAY_KEY, $access);

            try
            {
                $params[self::CREDENTIALS_ARRAY_KEY] = [
                    'key'    => $access,
                    'secret' => $secret,
                ];

                $access = false;
                $secret = false;
            }
            catch ( \Exception $e )
            {
                clog($e);
                clog("Could not initialize local AWS credentials . ");
            }
        }
        else
        {
            $config = $this->loadConfigParams();
        }

        $params['region']  = self::getAWSRegion($config);
        $params['version'] = self::getAWSVersion($config);

        if ( self::DEBUG_CREDS_DANGEROUS ) clog("getCredsAWS() FINAL -params", $params);

        return $params;
    }



    private static function getAWSRegion ( $config )
    {
        if ( false === $config ) return self::DEFAULT_FJ_AWS_REGION;

        return array_key_exists(self::AWS_REGION_ARRAY_KEY, $config)
            ? $config[self::AWS_REGION_ARRAY_KEY]
            : self::DEFAULT_FJ_AWS_REGION;
    }



    private static function getAWSVersion ( $config )
    {
        if ( false === $config ) return self::DEFAULT_FJ_AWS_VERSION;

        return array_key_exists(self::AWS_VERSION_ARRAY_KEY, $config)
            ? $config[self::AWS_VERSION_ARRAY_KEY]
            : self::DEFAULT_FJ_AWS_VERSION;
    }



    /**
     * @return S3Client|bool
     */
    private function getS3Client ()
    {
        $params = $this->getCredsAWS();
        try
        {
            $s3 = new S3Client($params);
        }
        catch ( Exception $e )
        {
            clog($e);
            clog("Cannot get AWS S3 Client; returning(false) . ");
            $s3 = false;
        }

        $params = self::clearParams($params);

        return $s3;
    }



    private function getSESClient ()
    {
        $params = $this->getCredsAWS();
        try
        {
            $ses = new SesClient($params);
        }
        catch ( Exception $e )
        {
            clog($e);
            clog("Cannot get AWS SES Client; returning(false) . ");
            $ses = false;
        }

        $params = self::clearParams($params);

        return $ses;

    }



    /**
     * @return CRUDProvider
     *
     * @throws Exception
     */
    public function getCRUDProvider ()
    {
        $params = $this->loadConfigParams();

        $providerType = self::PROVIDER_TYPE_CRUD;
        $isProvLocal  = $this->isUsingLocalProvider($providerType);

        clog("is $providerType local?", $isProvLocal);

        $provParams = $isProvLocal
            ? $this->getCRUDParamsLocal()
            : $this->getCRUDParamsAWS();

        $prov = $isProvLocal
            ? new CRUDProviderLocal($provParams)
            : new CRUDProviderAWS($provParams);

        $provParams = false;
        $params     = false;

        return $prov;
    }



    /**
     * @return array
     *
     * @throws Exception
     */
    private function getCRUDParamsLocal () { return []; }



    private function getCRUDParamsAWS ()
    {
        $s3 = $this->getS3Client();

        $params = [
            "s3" => $s3,
        ];

        return $params;
    }



    /**
     * @return CRUDProvider
     *
     * @throws Exception
     */
    public function getCSVProvider ()
    {
        $params = $this->loadConfigParams();

        $providerType = self::PROVIDER_TYPE_CRUD;
        $isProvLocal  = $this->isUsingLocalProvider($providerType);

        clog("is $providerType local?", $isProvLocal);

        $provParams = $isProvLocal
            ? $this->getCRUDParamsLocal()
            : $this->getCRUDParamsAWS();

        $prov = $isProvLocal
            ? new CSVProviderLocal($provParams)
            : new CSVProviderAWS($provParams);

        $provParams = false;
        $params     = false;

        return $prov;
    }



    /**
     * @param string $secretName
     *
     * @return EmailProvider
     * @throws Exception
     */
    public function getEmailProvider ( $secretName )
    {
        $params = $this->loadConfigParams();

        $providerType = self::PROVIDER_TYPE_EMAIL;
        $isProvLocal  = $this->isUsingLocalProvider($providerType);

        clog("is $providerType local?", $isProvLocal);

        $provParams = $isProvLocal
            ? $this->getEmailParamsLocal($secretName)
            : $this->getEmailParamsAWS($params);

        $prov = $isProvLocal
            ? new EmailProviderLocal($provParams)
            : new EmailProviderAWS($provParams);

        $provParams = false;
        $params     = false;

        return $prov;
    }



    /**
     * @param $secretName
     *
     * @return bool|mixed
     * @throws Exception
     */
    private function getEmailParamsLocal ( $secretName )
    {
        return $this->getSecret($secretName);
    }



    /**
     * @param $secretName - Ignored here.
     *
     * @return array
     */
    private function getEmailParamsAWS ( $params )
    {
        $fromEmail = $params['email_cloud_from_email'];
        $fromName  = $params['email_cloud_from_email'];

        $ses = $this->getSESClient();

        $params = [
            "ses"        => $ses,
            "from-email" => $fromEmail,
            "from-name"  => $fromName,
        ];

        return $params;
    }



//    /**
//     * @param string $secretName
//     *
//     * @return EmailIntegration
//     * @throws Exception
//     */
//    public function getEmailIntegration ( $secretName )
//    {
//        $emailParams = $this->getSecret($secretName);
//
//        $em = new EmailIntegration($emailParams);
//
//        $emailParams = false;
//
//        return $em;
//    }



    /**
     * @param string $secretName
     *
     * @return TrelloIntegration
     * @throws Exception
     */
    public function getTrelloIntegration ( $secretName )
    {
        //$trelloAPI = $this->getSecret("trello_api");
        $trelloAPI = $this->getSecret($secretName);
        $key       = $trelloAPI['public_key'];
        $token     = $trelloAPI['secret_token'];

        $provParams = [
            "key"   => $key,
            "token" => $token,
            "debug" => true,
        ];

        $tp = new TrelloIntegration($provParams);

        $provParams = false;

        return $tp;
    }



    /**
     * @return FileProvider
     */
    public function getFileProvider ()
    {
        $params = $this->loadConfigParams();

        $providerType = self::PROVIDER_TYPE_FILE;
        $isProvLocal  = $this->isUsingLocalProvider($providerType);

        clog("is $providerType local?", $isProvLocal);

        $provParams = $isProvLocal
            ? $this->getFileParamsLocal()
            : $this->getFileParamsAWS();

        $prov = $isProvLocal
            ? new FileProviderLocal($provParams)
            : new FileProviderAWS($provParams);

        $provParams = false;
        $params     = false;

        return $prov;
    }



    private function getFileParamsLocal ()
    {
        if ( !$this->has(self::KEY_FILE_LOCATION) )
        {
            Log::error("Cannot find auth file; aborting.");
            return [];
        }
        if ( !$this->has(self::KEY_FILE_BUCKET) )
        {
            Log::error("Cannot find auth bucket; aborting.");
            return [];
        }

        $authFilePath = $this->get(self::KEY_FILE_LOCATION);
        $authBucket   = $this->get(self::KEY_FILE_BUCKET);

        $params = [
            self::KEY_FILE_LOCATION => $authFilePath,
            self::KEY_FILE_BUCKET   => $authBucket,
        ];
        return $params;
    }



    private function getFileParamsAWS ()
    {
        $s3 = $this->getS3Client();

        $params = [
            "s3" => $s3,
        ];

        if ( $this->has(self::KEY_FILE_BUCKET) )
        {
            $params[self::KEY_FILE_BUCKET] = $this->get(self::KEY_FILE_BUCKET);
        }

        return $params;
    }



    /**
     * @return SecretsManagerClient|bool
     */
    private function getSecretsManagerClient ()
    {
        $params = $this->getCredsAWS();
        try
        {
            if ( self::DEBUG_CREDS_DANGEROUS ) clog("creds for SecMan", $params);

            $secman = new SecretsManagerClient($params);
        }
        catch ( \Exception $e )
        {
            clog($e);
            clog("Cannot get AWS SecMan Client; returning(false)");
            $secman = false;
        }

        $params = self::clearParams($params);

        return $secman;
    }



    private static function clearParams ( $params )
    {
        unset($params[self::CREDENTIALS_ARRAY_KEY]);
        return false;
    }



    /**
     * @return PG
     *
     * @throws Exception
     */
    public function getDatabaseConnection ()
    {
        $params = $this->loadConfigParams();

        if ( self::DEBUG_DB_CONN ) clog("params", $params);

        $provKey = self::PROVIDER_TYPE_DB . "_provider";
        $source  = $params[$provKey];

        switch ( $source )
        {
            case self::PROVIDER_CLOUD:
                $dbParams = $this->getRDSParams();
                if ( self::DEBUG_DB_CONN_VERBOSE ) clog("db (CLOUD) params", $dbParams);
                break;

            default:
                $dbParams = $this->getLocalDBParams($params, $source);
                if ( self::DEBUG_DB_CONN_VERBOSE ) clog("db (local) params", $dbParams);
                break;
        }

        if ( self::DEBUG_DB_CONN ) clog("DB params", $dbParams);

        $connString = $this->getDatabaseConnectionString($dbParams);

        $dbParams   = false;
        $pg         = new PG($connString); // <--------- MEAT
        $connString = false;

        return $pg;
    }



//    /**
//     * @return PGNew
//     *
//     * @throws Exception
//     */
//    public function getDatabaseConnectionWithExceptions ()
//    {
//        $params = $this->loadConfigParams();
//
//        clog("params", $params);
//
//        $provKey = self::PROVIDER_TYPE_DB . "_provider";
//        $source  = $params[$provKey];
//
//        switch ( $source )
//        {
//            case self::PROVIDER_CLOUD:
//                $dbParams = $this->getRDSParams();
//                if ( self::DEBUG_DB_CONN_VERBOSE ) clog("db (CLOUD) params", $dbParams);
//                break;
//
//            default:
//                $dbParams = $this->getLocalDBParams($params, $source);
//                if ( self::DEBUG_DB_CONN_VERBOSE ) clog("db (local) params", $dbParams);
//                break;
//        }
//
//        clog("DB params", $dbParams);
//
//        $connString = $this->getDatabaseConnectionString($dbParams);
//
//        $dbParams   = false;
//        $pg         = new PGNew($connString); // <--------- MEAT
//        $connString = false;
//
//        return $pg;
//    }



//    /**
//     * @return PGCursorConn
//     *
//     * @throws Exception
//     */
//    public function getCursorDatabaseConnection ()
//    {
//        $params = $this->loadConfigParams();
//
//        if ( self::DEBUG_DB_CONN ) clog("params", $params);
//
//        $provKey = self::PROVIDER_TYPE_DB . "_provider";
//        $source  = $params[$provKey];
//
//        switch ( $source )
//        {
//            case self::PROVIDER_CLOUD:
//                $dbParams = $this->getRDSParams();
//                if ( self::DEBUG_DB_CONN_VERBOSE ) clog("db (CLOUD) params", $dbParams);
//                break;
//
//            default:
//                $dbParams = $this->getLocalDBParams($params, $source);
//                if ( self::DEBUG_DB_CONN_VERBOSE ) clog("db (local) params", $dbParams);
//                break;
//        }
//
//        if ( self::DEBUG_DB_CONN ) clog("DB params (buffered)", $dbParams);
//
//        $db = PGCursorConn::newInstance($dbParams); // <--------- MEAT
//
//        return $db;
//    }



    /**
     * @param $config - Local config parameters.
     * @param $source - e.g., 'local' or 'proxy'.
     *
     * @return array
     *
     * @throws Exception
     */
    private function getLocalDBParams ( $config, $source )
    {
        $hostKey = self::DB_HOST_ARRAY_KEY . $source;
        $portKey = self::DB_PORT_ARRAY_KEY . $source;
        $nameKey = self::DB_NAME_ARRAY_KEY . $source;

        $host = $config[$hostKey];
        $port = $config[$portKey];
        $name = $config[$nameKey];

        clog("host", $host);
        clog("port", $port);
        clog("name", $name);

        if ( self::PROVIDER_PROXY == $source )
        {
            $params = self::getRDSParams();
            $user   = $params['username'];
            $pass   = $params['password'];
        }
        else
        {
            $userKey = self::DB_USER_ARRAY_KEY . $source;
            $passKey = self::DB_PASS_ARRAY_KEY . $source;

            $user = $config[$userKey];
            $pass = $config[$passKey];
        }

        return [
            'host'     => $host,
            'port'     => $port,
            'username' => $user,
            'password' => $pass,
            'dbname'   => $name,
        ];
    }



    private function getDatabaseConnectionString ( $dbParams )
    {
        if ( false === $dbParams ) return false;

        $host = $dbParams['host'];
        $port = $dbParams['port'];
        $user = $dbParams['username'];
        $pass = $dbParams['password'];
        $db   = $dbParams['dbname'];

        $dbstr = "host = $host port = $port dbname = $db user = $user";

        if ( self::DEBUG_DB_CONN ) clog("getDBConnectionString - DB conn str(no passwd)", $dbstr);

        $dbstr .= " password = $pass";

        return $dbstr;
    }



    /**
     * @param $secretName
     *
     * @return bool|mixed
     * @throws Exception
     */
    public function getSecret ( $secretName )
    {
        $params = $this->loadConfigParams();

        if ( self::DEBUG_SECRETS_MANAGER ) clog("params", $params);

        $provKey = self::PROVIDER_TYPE_SECRET . "_provider";
        $source  = $params[$provKey];

        switch ( $source )
        {
            case self::PROVIDER_CLOUD:
                return $this->getSecretFromCloud($secretName);

            default:
                return $this->getSecretLocally($secretName, $params);
        }
    }



    /**
     * @param $secretName
     * @param $params
     *
     * @throws Exception
     */
    private function getSecretLocally ( $secretName, $params )
    {
        clog("Looking for local secret", $secretName);

        if ( !array_key_exists($secretName, $params) )
        {
            if ( self::DEBUG_SECRETS_MANAGER ) clog($secretName, $params);

            throw new Exception("Cannot find secret [ $secretName ] in local params; check auth.js");
        }

        return $params[$secretName];
    }



    private function getSecretFromCloud ( $secretName )
    {
        if ( self::DEBUG_SECRETS_MANAGER ) clog("getSec...FromCloud() - ANTE AWS SecMan Client", $secretName);

        $client = $this->getSecretsManagerClient();

        if ( self::DEBUG_SECRETS_MANAGER ) clog("getSec...FromCloud() - POST AWS SecMan Client");

        if ( false === $client || !$client )
        {
            redlog("Cannot create SecManClient object; aborting");
            return false;
        }

        try
        {
            clog("getSec...FromCloud()", "Getting secret [$secretName]...");

            $result = $client->getSecretValue(
                [
                    'SecretId' => $secretName,
                ]
            );
        }
        catch ( AwsException $e )
        {
            $error = $e->getAwsErrorCode();
            $this->handleSecManError($error);

            cclog(Log::TEXT_COLOR_BG_RED, "FAIL to get secrets.");
            return false;
        }
        catch ( Exception $e )
        {
            clog($e);
            clog("General error", $e);
            return false;
        }

        // Decrypts secret using the associated KMS CMK.
        // Depending on whether the secret is a string or binary, one of these fields will be populated.
        if ( isset($result['SecretString']) )
        {
            $secret = $result['SecretString'];
        }
        else
        {
            $secret = base64_decode($result['SecretBinary']);
        }

        // Your code goes here;
        if ( self::DEBUG_CREDS_DANGEROUS ) clog("secrets", $secret);

        return FJ::jsDecode($secret);
    }



    protected function handleSecManError ( $error )
    {
        if ( $error == 'DecryptionFailureException' )
        {
            // Secrets Manager can't decrypt the protected secret text using the provided AWS KMS key.
            // Handle the exception here, and/or rethrow as needed.
            clog("AWS SecMan error (handle in subclass)", $error);
        }
        if ( $error == 'InternalServiceErrorException' )
        {
            // An error occurred on the server side.
            // Handle the exception here, and/or rethrow as needed.
            clog("AWS SecMan error (handle in subclass)", $error);
        }
        if ( $error == 'InvalidParameterException' )
        {
            // You provided an invalid value for a parameter.
            // Handle the exception here, and/or rethrow as needed.
            clog("AWS SecMan error (handle in subclass)", $error);
        }
        if ( $error == 'InvalidRequestException' )
        {
            // You provided a parameter value that is not valid for the current state of the resource.
            // Handle the exception here, and/or rethrow as needed.
            clog("AWS SecMan error (handle in subclass)", $error);
        }
        if ( $error == 'ResourceNotFoundException' )
        {
            // We can't find the resource that you asked for.
            // Handle the exception here, and/or rethrow as needed.
            clog("AWS SecMan error (handle in subclass)", $error);
        }

        clog("AWS SecMan Error", $error);
        Log::error($error);
    }



    /**
     * @return bool|array
     *
     * @throws Exception
     */
    private function getRDSParams ()
    {
        $secretName = 'dashboard';
        return $this->getSecret($secretName);
    }
}
