<?php



namespace vertwo\plite\Provider\Exception;



use Exception;
use Throwable;



class NoAuthDataException extends Exception
{
    function __construct ()
    {
        $message = "No AuthData implementation provided.";
        parent::__construct($message);
    }
}
