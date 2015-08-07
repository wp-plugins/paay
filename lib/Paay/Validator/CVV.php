<?php

class Paay_Validator_CVV extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'Should be valid CVV';

    public function validator($value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            return false;
        }

        return true;
    }
}