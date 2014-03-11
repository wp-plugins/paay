<?php

require __DIR__ . '/Adapter/Curl.php';
require __DIR__ . '/Connection/Request.php';

class Paay_Connection
{
    protected $adapter;

    protected $host;

    protected $key;

    protected $secret;

    public function __construct($host, $key, $secret, $adapter = null)
    {
        $this->host = $host;
        $this->key = $key;
        $this->secret = $secret;
        $this->adapter = $adapter ? $adapter : new Paay_Adapter_Curl();
    }

    public function sendRequest(Paay_Connection_Request $request)
    {
        $signature = $this->generateSignature($request->resource, $request->body);

        $request->addHeader('Paay-Auth-Key: ' . $this->key)
                ->addHeader('Paay-Signature: ' . $signature);
        $request->host = $this->host;

        return $this->adapter->sendRequest($request);
    }

    /**
     * @return string Paay host
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @param String $host Paay host
     */
    public function setHost($host) {
        $this->host = $host;

        return $this;
    }

    /**
     * @return string Paay API key
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * @param String $key Paay API key
     */
    public function setKey($key) {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string Paay API secret
     */
    public function getSecret() {
        return $this->secret;
    }

    /**
     * @param String $secret Paay API secret
     */
    public function setSecret($secret) {
        $this->secret = $secret;

        return $this;
    }

    protected function generateSignature($url, array $data = array())
    {
        $arrayToStringVars = array('data', 'query');

        $dataStr = '';
        foreach ($arrayToStringVars as $var) {
            if (isset(${$var}) && is_array(${$var})) {
                $dataStr .= !empty(${$var}) ? json_encode(${$var}) : '';
            }
        }
        $url = explode('?', $url);
        $url = $url[0];

        return md5(trim($url) . trim($dataStr) . trim($this->secret));
    }
}