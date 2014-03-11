<?php

class Paay_Validator_NameOnCard extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'should not be empty';

    public function validator($value)
    {
        $value = trim($value);

        if (empty($value)) {
            return false;
        }

        return true;
    }
}