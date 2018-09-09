<?php namespace peer\ldap\filter;

class AndFilter implements Filter {
  public $filters;

  public function __construct(... $filters) {
    $this->filters= $filters;
  }
}