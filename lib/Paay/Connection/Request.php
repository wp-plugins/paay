<?php

class Paay_Connection_Request
{
    public $host;

    public $resource;

    public $method;

    public $headers;

    public $body;

    public function __construct()
    {
        $this->headers = array(
            'Accept: application/vnd.paay.api.v1+json',
        );
        $this->body = array();
    }

    public function getFullUrl()
    {
        return $this->host . '/' . $this->resource;
    }

    public function addHeader($header)
    {
        $this->headers[] = $header;

        return $this;
    }

    public function setOperation($action)
    {
        switch($action) {
            case 'addTransaction':
                $this->method = 'POST';
                break;
            case 'approveTransaction':
                $this->method = 'POST';
                break;
            case 'findUser':
                $this->method = 'GET';
                break;
            case 'checkTransaction':
                $this->method = 'GET';
                break;
            defaut:
                throw new Paay_Exception_ApiException("Invalid request type");
        }
    }
}