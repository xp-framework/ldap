<?php namespace peer\ldap\filter;

class OrFilter implements Filter {
  public $filters;

  public function __construct(... $filters) {
    $this->filters= $filters;
  }
}