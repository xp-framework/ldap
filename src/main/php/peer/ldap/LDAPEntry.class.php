<?php namespace peer\ldap;

use util\Objects;

/**
 * Wraps LDAP entry
 *
 * @purpose  Represent a single entry
 * @see      xp://peer.ldap.LDAPSearchResult
 * @see      xp://peer.ldap.LDAPClient
 * @test     xp://peer.ldap.unittest.LDAPEntryTest
 * @test     xp://peer.ldap.unittest.LDAPEntryCreateTest
 */
class LDAPEntry implements \lang\Value {
  public
    $dn=          '',
    $attributes=  array();
  
  protected
    $_ans=        array();
    
  /**
   * Constructor
   *
   * @param   string dn default NULL "distinct name"
   * @param   var[] attrs default array()
   */
  public function __construct($dn= null, $attrs= array()) {
    $this->dn= $dn;
    if (sizeof($attrs)) {
      $this->attributes= array_change_key_case($attrs, CASE_LOWER);
      $this->_ans= array_combine(array_keys($this->attributes), array_keys($attrs));
    }
  }

  /**
   * Create LDAPEntry from a given DN and associative
   * array
   *
   * @param   string dn
   * @param   [:string] data
   * @return  peer.ldap.LDAPEntry
   */
  public static function create($dn, array $data) {
    $e= new self($dn);

    foreach ($data as $key => $value) {
      if ('count' === $key || is_int($key)) continue;

      // Store case-preserved version of key name in _ans array        
      $lkey= strtolower($key);
      $e->_ans[$lkey]= $key;

      $e->attributes[$lkey]= $value;
      unset($e->attributes[$lkey]['count']);
    }

    return $e;
  }
      
  /**
   * Set this entry's DN (distinct name)
   *
   * @param   string dn
   */
  public function setDN($dn) {
    $this->dn= $dn;
  }
  
  /**
   * Retrieve this entry's DN (distinct name)
   *
   * @return  string DN
   */
  public function getDN() {
    return $this->dn;
  }

  /**
   * Set attribute
   *
   * @param   string key
   * @param   var value
   */
  public function setAttribute($key, $value) {
    $this->_ans[strtolower($key)]= $key;
    $this->attributes[strtolower($key)]= (array)$value;
  }
  
  /**
   * Retrieve an attribute - an offset may be supplied to define
   * the values offset within the attribute. If -1 (the default)
   * is supplied, an array of attribute values is returned.
   *
   * Note: If the value does not exist, NULL is returned
   *
   * @param   string key
   * @param   int idx default -1
   * @return  var attribute
   */
  public function getAttribute($key, $idx= -1) {
    $lkey= strtolower($key);
    return (($idx >= 0)
      ? (isset($this->attributes[$lkey][$idx]) ? $this->attributes[$lkey][$idx] : null)
      : (isset($this->attributes[$lkey]) ? $this->attributes[$lkey] : null)
    );
  }
  
  /**
   * Retrieve all attributes
   *
   * @return  array
   */
  public function getAttributes() {
    return $this->attributes;
  }
  
  /**
   * Retrieve whether this entry is of a given object class.
   *
   * Note: The given objectClass is treated case-sensitive!
   *
   * @param   string objectClass
   * @return  bool
   */
  public function isA($objectClass) {
    return in_array($objectClass, $this->attributes['objectclass']);
  }

  /**
   * Retrieve a string representation of this object
   *
   * @return  string
   */
  public function toString() {
    $s= sprintf("%s@DN(%s){\n", nameof($this), $this->getDN());
    foreach ($this->attributes as $name => $attr) {
      $s.= sprintf("  [%-20s] %s\n", $this->_ans[$name], implode(', ', $attr));
    }
    return $s.'}';
  }

  /**
   * Retrieve a hash code of this object
   *
   * @return  string
   */
  public function hashCode() {
    return 'DN:'.$this->dn;
  }

  /**
   * Returns whether a given comparison value is equal to this LDAP entry
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self
      ? Objects::compare([$this->dn, $this->attributes], [$value->dn, $value->attributes])
      : 1
    ;
  }
}