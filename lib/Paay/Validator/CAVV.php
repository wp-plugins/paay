<?php

class Paay_Validator_CAVV extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'should be valid CAVV';

    public function validator($value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            return false;
        }

        return true;
    }
}