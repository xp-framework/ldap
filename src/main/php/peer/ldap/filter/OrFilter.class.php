<?php namespace peer\ldap\filter;

class OrFilter implements Filter {
  public $kind= 'or';

  public $filters;

  public function __construct(... $filters) {
    $this->filters= $filters;
  }
}