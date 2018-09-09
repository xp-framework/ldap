<?php namespace peer\ldap\filter;

class EqualityFilter implements Filter {
  public $attribute, $value;

  public function __construct($attribute, $value) {
    $this->attribute= $attribute;
    $this->value= $value;
  }
}