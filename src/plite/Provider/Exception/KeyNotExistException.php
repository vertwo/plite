<?php



namespace vertwo\plite\Provider\Exception;



use Exception;



class KeyNotExistException extends Exception
{
    private $id;


    function __construct ( $id )
    {
        parent::__construct("Provider: specified key ($id) does not exist.");
        $this->id = $id;
    }
}
