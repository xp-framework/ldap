<?php namespace peer\ldap\filter;

class LessThanEqualsFilter implements Filter {
  public $kind= 'lessthanequals';

  public $attribute, $value;

  public function __construct($attribute, $value) {
    $this->attribute= $attribute;
    $this->value= $value;
  }
}