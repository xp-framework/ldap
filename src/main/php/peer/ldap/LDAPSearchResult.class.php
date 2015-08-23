<?php namespace peer\ldap;

/**
 * Wraps ldap search results
 *
 * @see      php://ldap_get_entries
 * @test     xp://peer.ldap.unittest.LDAPSearchResultTest
 */
class LDAPSearchResult extends \lang\Object {
  private $entries;
  private $first= null;
  private $all= null;

  /**
   * Constructor
   *
   * @param  peer.ldap.LDAPEntries $entries
   */
  public function __construct($entries) {
    $this->entries= $entries;
  }

  /**
   * Returns number of found elements
   *
   * @return  int
   */
  public function numEntries() {
    return $this->entries->size();
  }

  /**
   * Gets first entry
   *
   * @return  peer.ldap.LDAPEntry or FALSE if nothing is found
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function getFirstEntry() {
    $this->first= $this->entries->first();
    return $this->first ?: false;
  }

  /**
   * Get a search entry by resource
   *
   * @param   int offset
   * @return  peer.ldap.LDAPEntry or FALSE if none exists by this offset
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function getEntry($offset) {
    if (null === $this->all) {
      $this->all= [];
      if ($entry= $this->entries->first()) do {
        $this->all[]= $entry;
      } while ($entry= $this->entries->next());
    }
    
    return isset($this->all[$offset]) ? $this->all[$offset] : false;
  }

  /**
   * Gets next entry - ideal for loops such as:
   * <code>
   *   while ($entry= $l->getNextEntry()) {
   *     // doit
   *   }
   * </code>
   *
   * @return  peer.ldap.LDAPEntry or FALSE for EOF
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function getNextEntry() {
  
    // Check if we were called without getFirstEntry() being called first
    // Tolerate this situation by simply returning whatever getFirstEntry()
    // returns.
    if (null === $this->first) {
      return $this->getFirstEntry();
    }

    return $this->entries->next() ?: false;    
  }

  /**
   * Close resultset and free result memory
   *
   * @return  bool success
   */
  public function close() {
    return $this->entries->close();
  }

  /**
   * Retrieve a string representation of this object
   *
   * @return  string
   */
  public function toString() {
    return sprintf('%s@(%d entries)', nameof($this), $this->numEntries());
  }
}
