<?php

class Paay_Validator_PAN extends Paay_Validator_AbstractValidator
{
    protected $error_message = 'should be 16 digits';

    public function validator($value)
    {
        $value = (int)trim($value);

        if (empty($value)) {
            return false;
        }

        if (!is_int($value)) {
            return false;
        }

        if ($this->checkDigitsAmount($value)) {
            return false;
        }

        return true;
    }

    private function checkDigitsAmount($no)
    {
        if(preg_match('/^3[47][0-9]{2}.*/', $no)){
            $this->error_message = 'should be 15 digits';
            return strlen($no) !== 15;
        }

        return strlen($no) !== 16;
    }
}