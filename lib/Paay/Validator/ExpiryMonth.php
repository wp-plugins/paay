<?php

class Paay_Validator_ExpiryMonth extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'should be in form MM';

    public function validator($value)
    {
        $value = (int)trim($value);

        if (empty($value)) {
            return false;
        }
        
        if (!is_int($value)) {
            return false;
        }

        $value = str_pad($value, 2, '0', STR_PAD_LEFT);

        return true;
    }
}