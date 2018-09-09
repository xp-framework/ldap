<?php namespace peer\ldap\filter;

class SubstringFilter implements Filter {
  public $attribute, $initial, $any, $final;

  public function __construct($attribute, $initial, $any, $final) {
    $this->attribute= $attribute;
    $this->initial= $initial;
    $this->any= $any;
    $this->final= $final;
  }
}