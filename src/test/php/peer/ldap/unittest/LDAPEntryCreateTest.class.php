<?php namespace peer\ldap\unittest;
 
use peer\ldap\LDAPEntry;

/**
 * Test LDAP entries created by create()
 */
class LDAPEntryCreateTest extends LDAPEntryTest {

  /** @return peer.ldap.LDAPEntry */
  protected function newInstance($dn, $attributes) {
    return LDAPEntry::create($dn, $attributes);
  }
}