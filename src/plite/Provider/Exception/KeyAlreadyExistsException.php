<?php



namespace vertwo\plite\Provider\Exception;



use Exception;



class KeyAlreadyExistsException extends Exception
{
    private $id;


    function __construct ( $id )
    {
        parent::__construct("Provider: specified key ($id) already exists; not adding.");
        $this->id = $id;
    }
}
