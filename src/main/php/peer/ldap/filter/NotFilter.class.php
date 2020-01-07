<?php namespace peer\ldap\filter;

class NotFilter implements Filter {
  public $kind= 'not';

  public $filter;

  public function __construct($filter) {
    $this->filter= $filter;
  }
}