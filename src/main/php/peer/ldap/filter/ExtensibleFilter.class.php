<?php namespace peer\ldap\filter;

class ExtensibleFilter implements Filter {
  public $attribute, $rule, $value;

  public function __construct($attribute, $rule, $value) {
    $this->attribute= $attribute;
    $this->rule= $rule;
    $this->value= $value;
  }
}