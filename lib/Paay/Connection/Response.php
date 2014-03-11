<?php

class Paay_Connection_Reponse
{
    public $code;

    public $body;

    public function __construct($code, $body)
    {
        $this->code = $code;
        $this->body = $body;
    }
}