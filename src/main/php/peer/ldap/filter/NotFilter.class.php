<?php namespace peer\ldap\filter;

class NotFilter implements Filter {
  public $filter;

  public function __construct($filter) {
    $this->filter= $filter;
  }
}