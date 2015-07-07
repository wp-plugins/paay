<?php

class Paay_Validator_PAN extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'should be 15 or 16 digits';

    public function validator($value)
    {
        $value = (int)trim($value);

        if (empty($value)) {
            return false;
        }

        if (!is_int($value)) {
            return false;
        }

        if (strlen($value) < 15 || strlen($value) > 16) {
            return false;
        }

        return true;
    }
}