<?php

require __DIR__ . '/../Exception/ConnectionException.php';
require __DIR__ . '/../Connection/Response.php';

class Paay_Adapter_Curl
{
    protected $url;

    protected $headers;

    protected $body;

    protected $curlOptions;

    protected $response;

    protected $status;

    public function __construct()
    {
        $this->curlOptions = $this->getDefaultCurlConfig();
    }

    public function sendRequest(Paay_Connection_Request $request)
    {
        $ch = $this->buildCurlHandler($request);

        $response = curl_exec($ch);

        if ($response === false)
        {
            throw new Paay_Exception_ConnectionException(curl_error($ch));
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new Paay_Connection_Reponse($code, $response);
    }

    /**
     * @return string Request method
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @param String $method Request method
     */
    public function setMethod($method) {
        $this->method = $method;

        return $this;
    }

    /**
     * @return string Request body
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @param String $newbody Request body
     */
    public function setBody($body) {
        $this->body = $body;

        return $this;
    }

    /**
     * @return array curl options as array
     */
    public function getCurlOptions() {
        return $this->curlOptions;
    }

    /**
     * @param Array $newcurlOptions Curl options as array
     */
    public function setCurlOptions($curlOptions) {
        $this->curlOptions = $curlOptions;

        return $this;
    }

    public function setCurlOption($key, $value)
    {
        $this->curlOptions[$key] = $value;
    }

    public function removeCurlOption($key)
    {
        unset($this->curlOptions[$key]);
    }

    public function setMethodPost()
    {
        $this->setCurlOption(CURLOPT_POST, 1);
    }

    public function setMethodGet()
    {
        if(array_key_exists(CURLOPT_POST, $this->curlOptions)) {
            $this->removeCurlOption(CURLOPT_POST);
        }
    }

    public function setUrl($url)
    {
        $this->setCurlOption(CURLOPT_URL, $url);
    }

    public function setHeaders(array $headers)
    {
        $this->setCurlOption(CURLOPT_HTTPHEADER, $headers);
    }

    public function setPostData($data)
    {
        $this->setCurlOption(CURLOPT_POSTFIELDS, json_encode($data));
    }

    protected function buildCurlHandler(Paay_Connection_Request $request)
    {
        $ch = curl_init();
        $this->setUrl($request->getFullUrl());
        if(strtoupper($request->method) == 'POST') {
            $this->setMethodPost();
        }

        $this->setHeaders($request->headers);
        if(count($request->body) > 0) {
            $this->setPostData($request->body);
        }

        if (count($this->curlOptions) == 0) {
            throw new InvalidArgumentException('You must provide options for curl request!');
        }

        foreach($this->curlOptions as $option => $value)
        {
            curl_setopt($ch, $option, $value);
        }

        return $ch;
    }

    protected function getDefaultCurlConfig()
    {
        return array(
             CURLOPT_SSL_VERIFYHOST => 0,
             CURLOPT_SSL_VERIFYPEER => 0,
             // CURLOPT_USERAGENT => 'asdf', // @TODO generate UserAgent
             CURLOPT_FOLLOWLOCATION => 1,
             CURLOPT_RETURNTRANSFER => 1,
        );
    }
}