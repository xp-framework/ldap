<?php namespace peer\ldap\util;

use peer\ldap\LDAPEntry;

class Entries {
  private $list, $offset= 0;
  
  public function __construct($list) {
    $this->list= $list;
  }

  /** @return int */
  public function size() { return sizeof($this->list); }

  /**
   * Gets first entry
   *
   * @return  peer.ldap.LDAPEntry or NULL if nothing was found
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function first() {
    if (empty($this->list)) return null; // Nothing found

    $this->offset= 0;
    return LDAPEntry::create($this->list[0]['dn'], $this->list[0]['attr']);
  }

  /**
   * Gets next entry
   *
   * @return  peer.ldap.LDAPEntry or NULL if nothing was found
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function next() {
    if (++$this->offset >= sizeof($this->list) - 1) return null;

    $entry= LDAPEntry::create($this->list[$this->offset]['dn'], $this->list[$this->offset]['attr']);
    return $entry;
  }

  /**
   * Close resultset and free result memory
   *
   * @return  bool success
   */
  public function close() {
    $this->offset= 0;
  }
}