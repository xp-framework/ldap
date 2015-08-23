<?php namespace peer\ldap;

/**
 * Wraps ldap search results
 *
 * @see      php://ldap_get_entries
 * @test     xp://peer.ldap.unittest.LDAPSearchResultTest
 */
class LDAPEntries extends \lang\Object {
  private $size, $conn, $result;
  private $iteration= null;
  private $entries= null;

  /**
   * Constructor
   *
   * @param   resource hdl ldap connection
   * @param   resource res ldap result resource
   */
  public function __construct($hdl, $res) {
    $this->conn= $hdl;
    $this->result= $res;
    $this->size= ldap_count_entries($this->conn, $this->result);
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
    $entry= ldap_first_entry($this->conn, $this->result);
    if (!$entry) {
      if ($e= ldap_errno($this->conn)) {
        throw new LDAPException('Could not fetch first result entry.', $e);
      }
      return null;   // Nothing found
    } else {
      $this->iteration= [$entry, 1];
      return new LDAPEntry(ldap_get_dn($this->conn, $entry), ldap_get_attributes($this->conn, $entry));
    }
  }
  
  /**
   * Gets next entry
   *
   * @return  peer.ldap.LDAPEntry or NULL if nothing was found
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function next() {
  
    // If we have reached the number of results reported by ldap_count_entries()
    // - see constructor, return FALSE without trying to read further. Trying
    // to read "past the end" results in LDAP error #84 (decoding error) in some 
    // client/server constellations, which is then incorrectly reported as an error.
    if ($this->iteration[1] >= $this->size) return null;
    
    // Fetch the next entry. Return FALSE if it was the last one (where really,
    // we shouldn't be getting here)
    $entry= ldap_next_entry($this->conn, $this->iteration[0]);
    if (!$entry) {
      if ($e= ldap_errno($this->conn)) {
        throw new LDAPException('Could not fetch next result entry.', $e);
      }
      return null;   // EOF
    }
    
    // Keep track how many etnries we have fetched so we stop once we
    // have reached this number - see above for explanation.
    $this->iteration= [$entry, ++$this->iteration[1]];
    return new LDAPEntry(ldap_get_dn($this->conn, $entry), ldap_get_attributes($this->conn, $entry));
  }

  /**
   * Close resultset and free result memory
   *
   * @return  bool success
   */
  public function close() {
    if ($this->result) {
      ldap_free_result($this->result);
      $this->result= null;
    }
    return true;
  }
}
