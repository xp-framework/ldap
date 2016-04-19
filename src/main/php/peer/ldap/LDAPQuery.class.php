<?php namespace peer\ldap;

/**
 * Class encapsulating LDAP queries.
 *
 * @see     xp://peer.ldap.LDAPClient
 * @see     rfc://2254
 * @test    xp://net.xp_framework.unittest.peer.LDAPQueryTest
 */
class LDAPQuery extends \lang\Object {
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
    $sort=        false,
    $deref=       false;
    
  /**
   * Constructor.
   *
   * @param   string base
   * @param   var[] args
   */
  public function __construct() {
    $args= func_get_args();
    
    $this->base= array_shift($args);
    if (sizeof ($args)) $this->filter= $this->_prepare($args);
  }

  /**
   * Format the query as requested by the format identifiers. Values are escaped
   * approriately, so they're safe to use in the query.
   *
   * @param   var[] args
   * @return  string filter
   */
  protected function _prepare($args) {
    $query= $args[0];
    if (sizeof($args) <= 1) return $query;

    $i= 0;
    
    // This fixes strtok for cases where '%' is the first character
    $query= $tok= strtok(' '.$query, '%');
    while (++$i && $tok= strtok('%')) {
    
      // Support %1$s syntax
      if (is_numeric($tok{0})) {
        sscanf($tok, '%d$', $ofs);
        $mod= strlen($ofs) + 1;
      } else {
        $ofs= $i;
        $mod= 0;
      }
      
      if (is_array($args[$ofs])) {
        throw new \lang\IllegalArgumentException(
          'Non-scalar or -object given in for LDAP query.'
        );
      } 
      
      // Type-based conversion
      if ($args[$ofs] instanceof \util\Date) {
        $tok{$mod}= 's';
        $arg= $args[$ofs]->toString('YmdHi\\ZO');
      } else if ($args[$ofs] instanceof \lang\Generic) {
        $arg= $args[$ofs]->toString();
      } else {
        $arg= $args[$ofs];
      }
      
      // NULL actually doesn't exist in LDAP, but is being used here to
      // clarify things (ie. show that no argument has been passed)
      switch ($tok{$mod}) {
        case 'd': $r= is_null($arg) ? 'NULL' : sprintf('%.0f', $arg); break;
        case 'f': $r= is_null($arg) ? 'NULL' : floatval($arg); break;
        case 'c': $r= is_null($arg) ? 'NULL' : $arg; break;
        case 's': $r= is_null($arg) ? 'NULL' : strtr($arg, array('(' => '\\28', ')' => '\\29', '\\' => '\\5c', '*' => '\\2a', chr(0) => '\\00')); break;
        default: $r= '%'; $mod= -1; $i--; continue;
      }
      $query.= $r.substr($tok, 1 + $mod);
      
    }
    return substr($query, 1);
  }
  
  /**
   * Prepare a query statement.
   *
   * @param   var[] args
   * @return  string
   */
  public function prepare() {
    $args= func_get_args();
    return $this->_prepare($args);
  }
  
  /**
   * Set Filter
   *
   * @param   string filter
   * @return  self $this
   */
  public function setFilter() {
    $args= func_get_args();
    $this->filter= $this->_prepare($args);
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
   * @param   var[] args
   * @return  self $this
   */
  public function setBase() {
    $args= func_get_args();
    $this->base= $this->_prepare($args);
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
    return $this;
    $this->deref= $deref;
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
    $namelen= 0;
    
    $str= nameof($this)."@{\n";
    foreach (array_keys(get_object_vars($this)) as $index) { $namelen= max($namelen, strlen($index)); }
    foreach (get_object_vars($this) as $name => $value) {
      if ('_' == $name{0}) continue;
    
      // Nicely convert certain types
      if (is_bool($value)) $value= $value ? 'TRUE' : 'FALSE';
      if (is_array($value)) $value= implode(', ', $value);
      
      if ('scope' == $name) switch ($value) {
        case LDAPClient::SCOPE_BASE: $value= 'LDAP_SCOPE_BASE'; break;
        case LDAPClient::SCOPE_ONELEVEL: $value= 'LDAP_SCOPE_ONELEVEL'; break;
        case LDAPClient::SCOPE_SUB: $value= 'LDAP_SCOPE_SUB'; break;
      }
      
      $str.= sprintf("  [%-".($namelen+5)."s] %s\n",
        $name,
        $value
      );
    }
    
    return $str."}\n";
  }
}
