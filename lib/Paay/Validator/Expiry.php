<?php

class Paay_Validator_Expiry extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'your card is expired';

    public function validator($value)
    {
        $nowDate = new DateTime();
        $setDate = new DateTime('01-'.$value);

        if($setDate->format('Y-m') < $nowDate->format('Y-m')){
            return false;
        }

        return true;
    }
}