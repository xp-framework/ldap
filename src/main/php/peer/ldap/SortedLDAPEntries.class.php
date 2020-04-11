<?php namespace peer\ldap;

use util\Objects;

/**
 * Wraps sorted ldap search results
 *
 * @see   php://ldap_sort
 */
class SortedLDAPEntries {
  private $result, $offset, $size;

  /**
   * Constructor
   *
   * @param  var $hdl ldap connection
   * @param  var $res ldap result resource
   * @param  string[] $sort
   */
  public function __construct($hdl, $res, $sort) {
    $this->result= ldap_get_entries($hdl, $res);
    $this->size= $this->result['count'];
    unset($this->result['count']);

    foreach ($sort as $attr) {
      $attr= strtolower($attr);
      usort($this->result, function($a, $b) use($attr) {
        return Objects::compare($a[$attr], $b[$attr]);
      });
    }

    ldap_free_result($res);
  }

  /** @return int */
  public function size() { return $this->size; }

  /**
   * Gets first entry
   *
   * @return  peer.ldap.LDAPEntry or NULL if nothing was found
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function first() {
    $this->offset= 0;
    $entry= $this->result[$this->offset];
    $dn= $entry['dn'];
    unset($entry['dn']);
    return LDAPEntry::create($dn, $entry);
  }
  
  /**
   * Gets next entry
   *
   * @return  peer.ldap.LDAPEntry or NULL if nothing was found
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function next() {
    if (++$this->offset >= $this->size) return null;

    $entry= $this->result[$this->offset];
    $dn= $entry['dn'];
    unset($entry['dn']);
    return LDAPEntry::create($dn, $entry);
  }

  /**
   * Close resultset and free result memory
   *
   * @return  bool success
   */
  public function close() {
    return true;
  }
}