<?php namespace peer\ldap\filter;

class AndFilter implements Filter {
  public $kind= 'and';

  public $filters;

  public function __construct(... $filters) {
    $this->filters= $filters;
  }
}