<?php

class Paay_Validator_Zip extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'should be in form 12345';

    public function validator($value)
    {
        $value = (int)trim($value);

        if (empty($value)) {
            return false;
        }
        
        if (!is_int($value)) {
            return false;
        }

        if (strlen($value) !== 5) {
            return false;
        }

        return true;
    }
}