<?php namespace peer\ldap\filter;

class ApproximateFilter implements Filter {
  public $kind= 'approximate';

  public $attribute, $value;

  public function __construct($attribute, $value) {
    $this->attribute= $attribute;
    $this->value= $value;
  }
}