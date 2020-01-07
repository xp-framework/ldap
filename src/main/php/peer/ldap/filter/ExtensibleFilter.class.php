<?php namespace peer\ldap\filter;

class ExtensibleFilter implements Filter {
  public $kind= 'extensible';

  public $type, $rule, $value, $attributes;

  public function __construct($type, $rule, $value, $attributes= false) {
    $this->type= $type;
    $this->rule= $rule;
    $this->value= $value;
  }
}