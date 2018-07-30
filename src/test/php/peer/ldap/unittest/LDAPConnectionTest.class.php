<?php namespace peer\ldap\unittest;

use peer\ldap\LDAPConnection;
use peer\URL;
use lang\IllegalArgumentException;

class LDAPConnectionTest extends \unittest\TestCase {

  /** @return var[][] */
  private function dsns() {
    return [
      ['ldap://example.com'],
      ['ldap://example.com:389'],
      ['ldaps://example.com'],
      ['ldaps://example.com:636'],
      ['ldap://bind-dn:password@example.com'],
      ['ldap://bind-dn:password@example.com:389'],
      ['ldap://example.com/?protocol_version=2'],
      ['ldap://example.com/?protocol_version=2&network_timeout=30']
    ];
  }

  #[@test, @values('dsns')]
  public function can_create_from_dsn_string($dsn) {
    new LDAPConnection($dsn);
  }

  #[@test, @values('dsns')]
  public function can_create_from_dsn_url($dsn) {
    new LDAPConnection(new URL($dsn));
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function unknown_option() {
    new LDAPConnection('ldap://example.com/?unkown=value');
  }

  #[@test]
  public function dsn() {
    $this->assertEquals(new URL('ldap://example.com'), (new LDAPConnection('ldap://example.com'))->dsn());
  }
}