<?php namespace peer\ldap;

use lang\IllegalArgumentException;
use lang\Value;
use util\Date;
use util\Objects;

/**
 * Class encapsulating LDAP queries.
 *
 * @see     xp://peer.ldap.LDAPConnection#searchBy
 * @see     rfc://2254
 * @test    xp://net.xp_framework.unittest.peer.LDAPQueryTest
 */
class LDAPQuery implements Value {
  const RECEIVE_TYPES  = 1;
  const RECEIVE_VALUES = 0;

  const SCOPE_BASE     = 0x0000;
  const SCOPE_ONELEVEL = 0x0001;
  const SCOPE_SUB      = 0x0002;

  public
    $filter=      '',
    $scope=       0,
    $base=        '',
    $attrs=       [],
    $attrsOnly=   self::RECEIVE_VALUES,
    $sizelimit=   0,
    $timelimit=   0,
    $sort=        [],
    $deref=       false;
    
  /**
   * Constructor
   *
   * @param  string $base
   * @param  string $filter
   * @param  var... $args
   */
  public function __construct($base= null, $filter= null, ... $args) {
    $this->base= $base;
    if (null !== $filter) {
      $this->filter= $this->prepare($filter, ...$args);
    }
  }

  /**
   * Prepare a query statement.
   *
   * @param  string $format
   * @param  var... $args
   * @return string
   */
  public function prepare($format, ... $args) {
    static $quotes= ['(' => '\\28', ')' => '\\29', '\\' => '\\5c', '*' => '\\2a', "\x00" => '\\00'];

    if (empty($args)) return $format;

    // This fixes strtok for cases where '%' is the first character
    $i= 0;
    $format= $tok= strtok(' '.$format, '%');
    while ($tok= strtok('%')) {
    
      // Support %1$s syntax
      if (is_numeric($tok[0])) {
        sscanf($tok, '%d$', $ofs);
        $ofs--;
        $mod= strlen($ofs) + 1;
      } else {
        $ofs= $i;
        $mod= 0;
      }

      // Type-based conversion
      if ($args[$ofs] instanceof Date) {
        $tok[$mod]= 's';
        $arg= $args[$ofs]->toString('YmdHi\\ZO');
      } else if ($args[$ofs] instanceof Value) {
        $arg= $args[$ofs]->toString();
      } else if (is_array($args[$ofs]) || is_object($args[$ofs])) {
        throw new IllegalArgumentException('Non-scalar or -object given in for LDAP query.');
      } else {
        $arg= $args[$ofs];
      }
      
      // NULL actually doesn't exist in LDAP, but is being used here to
      // clarify things (ie. show that no argument has been passed)
      switch ($tok[$mod]) {
        case 'd': $r= null === $arg ? 'NULL' : sprintf('%.0f', $arg); break;
        case 'f': $r= null === $arg ? 'NULL' : floatval($arg); break;
        case 'c': $r= null === $arg ? 'NULL' : $arg; break;
        case 's': $r= null === $arg ? 'NULL' : strtr($arg, $quotes); break;
        default: $r= '%'; $mod= -1; $i--;
      }

      $format.= $r.substr($tok, 1 + $mod);
      $i++;
    }

    return substr($format, 1);
  }
  
  /**
   * Set Filter
   *
   * @param  string $format
   * @param  var... $args
   * @return self $this
   */
  public function setFilter($format, ... $args) {
    $this->filter= $this->prepare($format, ...$args);
    return $this;
  }

  /**
   * Get Filter
   *
   * @return  string
   */
  public function getFilter() {
    return $this->filter;
  }

  /**
   * Set Scope
   *
   * @param   int scope
   * @return  self $this
   */
  public function setScope($scope) {
    $this->scope= $scope;
    return $this;
  }

  /**
   * Get Scope
   *
   * @return  string
   */
  public function getScope() {
    return $this->scope;
  }

  /**
   * Set Base
   *
   * @param  string $format
   * @param  var... $args
   * @return self $this
   */
  public function setBase($format, ... $args) {
    $this->base= $this->prepare($format, ...$args);
    return $this;
  }

  /**
   * Get Base
   *
   * @return  string
   */
  public function getBase() {
    return $this->base;
  }

  /**
   * Checks whether query has a base specified.
   *
   * @return  bool 
   */
  public function hasBase() {
    return (bool)strlen($this->base);
  }

  /**
   * Set Attrs
   *
   * @param   var[] attrs
   * @return  self $this
   */
  public function setAttrs($attrs) {
    $this->attrs= $attrs;
    return $this;
  }

  /**
   * Get Attrs
   *
   * @return  var[]
   */
  public function getAttrs() {
    return $this->attrs;
  }

  /**
   * Set whether to return only attribute types.
   *
   * @param  bool mode
   * @return  self $this
   */
  public function setAttrsOnly($mode) {
    $this->attrsOnly= $mode;
    return $this;
  }

  /**
   * Check whether to return only requested attributes.
   *
   * @return  bool attrsonly
   */
  public function getAttrsOnly() {
    return $this->attrsOnly;
  }

  /**
   * Set Sizelimit
   *
   * @param   int sizelimit
   * @return  self $this
   */
  public function setSizelimit($sizelimit) {
    $this->sizelimit= $sizelimit;
    return $this;
  }

  /**
   * Get Sizelimit
   *
   * @return  int
   */
  public function getSizelimit() {
    return $this->sizelimit;
  }

  /**
   * Set Timelimit
   *
   * @param   int timelimit
   * @return  self $this
   */
  public function setTimelimit($timelimit) {
    $this->timelimit= $timelimit;
    return $this;
  }

  /**
   * Get Timelimit
   *
   * @return  int
   */
  public function getTimelimit() {
    return $this->timelimit;
  }

  /**
   * Set sort fields; the field(s) to sort on must be
   * used in the filter, as well, for the sort to take
   * place at all.
   *
   * @see     php://ldap_sort
   * @param   string[] sort array of fields to sort with
   * @return  self $this
   */
  public function setSort($sort) {
    $this->sort= $sort;
    return $this;
  }

  /**
   * Get sort
   *
   * @return  array sort
   */
  public function getSort() {
    return (array)$this->sort;
  }        

  /**
   * Set Deref
   *
   * @param   bool deref
   * @return  self $this
   */
  public function setDeref($deref) {
    $this->deref= $deref;
    return $this;
  }

  /**
   * Get Deref
   *
   * @return  bool
   */
  public function getDeref() {
    return $this->deref;
  }

  /**
   * Return a nice string representation of this object.
   *
   * @return  string
   */
  public function toString() {
    static $scopes= [
      self::SCOPE_BASE     => 'LDAP_SCOPE_BASE',
      self::SCOPE_ONELEVEL => 'LDAP_SCOPE_ONELEVEL',
      self::SCOPE_SUB      => 'LDAP_SCOPE_SUB'
    ];

    return sprintf(
      "%s@{\n".
      "  [filter        ] %s\n".
      "  [scope         ] %s\n".
      "  [base          ] %s\n".
      "  [attrs         ] %s\n".
      "  [attrsOnly     ] %s\n".
      "  [sizelimit     ] %s\n".
      "  [timelimit     ] %s\n".
      "  [sort          ] %s\n".
      "  [deref         ] %s\n".
      "}",
      nameof($this),
      $this->filter,
      isset($scopes[$this->scope]) ? $scopes[$this->scope] : '(unknown '.$this->scope.')',
      $this->base,
      Objects::stringOf($this->attrs),
      $this->attrsOnly  ? 'true' : 'false',
      $this->sizelimit,
      $this->timelimit,
      implode(', ', $this->sort),
      $this->deref ? 'true' : 'false'
    );
  }

  /**
   * Retrieve a hash code of this object
   *
   * @return  string
   */
  public function hashCode() {
    return Objects::hashOf((array)$this);
  }

  /**
   * Returns whether a given comparison value is equal to this LDAP entry
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self
      ? Objects::compare((array)$this, (array)$value)
      : 1
    ;
  }
}
