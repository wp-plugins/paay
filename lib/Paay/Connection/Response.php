<?php

class Paay_Connection_Reponse
{
    public $code;

    public $body;

    public function __construct($code, $body)
    {
        $this->checkSuccess($body);
        $this->code = $code;
        $this->body = $body;
    }

    private function checkSuccess($json)
    {
        $response = json_decode($json, true);

        if(!$message = $this->findKey($response)){
            return;
        }

        if(trim($message) !== 'Success'){
            throw new \Exception('Could not create a transaction. Please try again later.');
        }
    }

    private function findKey($array)
    {
        foreach ($array as $key => $item){
            if ($key === 'message'){
                return $item;
            } else {
                if(is_array($item)) {
                    $this->findKey($item);
                }
            }
        }
        return false;
    }
}