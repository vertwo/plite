<?php



namespace vertwo\plite\Provider;



interface EmailProvider
{
    function init ();
    function sendEmail ( $params );
}
