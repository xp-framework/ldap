<?php namespace peer\ldap\unittest;
 
use peer\ldap\LDAPEntry;

/**
 * Test LDAP entries created by fromData()
 */
class LDAPEntryFromDataTest extends LDAPEntryTest {

  /** @return peer.ldap.LDAPEntry */
  protected function newInstance($dn, $attributes) {
    return LDAPEntry::fromData(array_merge(
      ['dn' => $dn, 'count' => sizeof($attributes) + 1],
      $attributes
    ));
  }
}