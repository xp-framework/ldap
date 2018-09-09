<?php namespace peer\ldap\filter;

class PresenceFilter implements Filter {
  public $attribute;

  public function __construct($attribute) {
    $this->attribute= $attribute;
  }
}