<?php

class Paay_Validator_ExpiryYear extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'should be in form YYYY';

    public function validator($value)
    {
        $value = (int)trim($value);

        if (empty($value)) {
            return false;
        }
        
        if (!is_int($value)) {
            return false;
        }

        if (strlen($value) !== 4) {
            return false;
        }

        return true;
    }
}