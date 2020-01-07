<?php namespace peer\ldap\filter;

class PresenceFilter implements Filter {
  public $kind= 'presence';

  public $attribute;

  public function __construct($attribute) {
    $this->attribute= $attribute;
  }
}