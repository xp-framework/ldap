<?php namespace peer\ldap\unittest;
 
use peer\ldap\LDAPEntry;

/**
 * Test LDAP entries created by constructor
 */
class LDAPEntryConstructorTest extends LDAPEntryTest {

  /** @return peer.ldap.LDAPEntry */
  protected function newInstance($dn, $attributes) { return new LDAPEntry($dn, $attributes); }
}