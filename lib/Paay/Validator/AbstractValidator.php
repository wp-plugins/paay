<?php

abstract class Paay_Validator_AbstractValidator
{
    protected $error_message = 'Field is invalid';

    abstract public function validator($value);

    public function validate($field, $value)
    {
        if (false === $this->validator($value)) {
            $this->error($field);

            return false;
        }

        return true;
    }

    public function error($field)
    {
        wc_add_notice(__('<strong>'.$field.'</strong>'.' '.$this->error_message), 'error');
    }
}