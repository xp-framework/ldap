<?php namespace peer\ldap;

use IteratorAggregate, Traversable;
use lang\{Value, Closeable};
use util\Objects;

/**
 * Wraps ldap search results
 *
 * @see      php://ldap_get_entries
 * @test     xp://peer.ldap.unittest.LDAPSearchResultTest
 */
class LDAPSearchResult implements Value, Closeable, IteratorAggregate {
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
   * @return  peer.ldap.LDAPEntry or NULL if there is no first entry
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function getFirstEntry() {
    $this->first= $this->entries->first();
    return $this->first;
  }

  /**
   * Get a search entry by resource
   *
   * @param   int offset
   * @return  peer.ldap.LDAPEntry or NULL if none exists by this offset
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function getEntry($offset) {
    if (null === $this->all) {
      $this->all= [];
      if ($entry= $this->entries->first()) do {
        $this->all[]= $entry;
      } while ($entry= $this->entries->next());
    }
    
    return $this->all[$offset] ?? null;
  }

  /**
   * Gets next entry - ideal for loops such as:
   * <code>
   *   while ($entry= $l->getNextEntry()) {
   *     // doit
   *   }
   * </code>
   *
   * @return  peer.ldap.LDAPEntry or NULL for EOF
   * @throws  peer.ldap.LDAPException in case of a read error
   */
  public function getNextEntry() {
  
    // Check if we were called without getFirstEntry() being called first
    // Tolerate this situation by simply returning whatever getFirstEntry()
    // returns.
    if (null === $this->first) {
      return $this->getFirstEntry();
    }

    return $this->entries->next();    
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
   * Iterate over all the entries
   */
  public function getIterator(): Traversable {
    $entry= $this->entries->first();
    do {
      yield $entry;
    } while ($entry= $this->entries->next());
  }

  /**
   * Retrieve a string representation of this object
   *
   * @return  string
   */
  public function toString() {
    return sprintf('%s@(%d entries)', nameof($this), $this->numEntries());
  }

  /**
   * Retrieve a hash code of this object
   *
   * @return  string
   */
  public function hashCode() {
    return 'R:'.Objects::hashOf($this->entries);
  }

  /**
   * Returns whether a given comparison value is equal to this LDAP entry
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self
      ? Objects::compare($this->entries, $value->entries)
      : 1
    ;
  }
}