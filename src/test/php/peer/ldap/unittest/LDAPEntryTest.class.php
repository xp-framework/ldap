<?php namespace peer\ldap\unittest;
 
use peer\ldap\LDAPEntry;

/**
 * Test LDAP entry class
 *
 * @see      xp://peer.ldap.LDAPEntry
 */
abstract class LDAPEntryTest extends \unittest\TestCase {
  const DN = 'uid=friebe,ou=People,dc=xp-framework,dc=net';

  private $attributes = [
    'cn'          => ['Friebe, Timm J.'],
    'sn'          => ['Friebe'],
    'givenName'   => ['Timm'],
    'uid'         => ['friebe'],
    'displayName' => ['Übercoder'],
    'mail'        => ['friebe@example.com'],
    'o'           => ['XP-Framework'],
    'ou'          => ['People'],
    'objectClass' => ['top', 'person', 'inetOrgPerson', 'organizationalPerson']
  ];

  protected $fixture;

  /** @return peer.ldap.LDAPEntry */
  protected abstract function newInstance($dn, $attributes);

  /**
   * Setup method
   *
   * @return void
   */    
  public function setUp() {
    $this->fixture= $this->newInstance(self::DN, $this->attributes);
  }

  #[@test]
  public function getDN() {
    $this->assertEquals(self::DN, $this->fixture->getDN());
  }

  #[@test]
  public function getAttributes() {
    $this->assertEquals(array_change_key_case($this->attributes, CASE_LOWER), $this->fixture->getAttributes());
  }

  #[@test]
  public function cnAttribute() {
    $this->assertEquals(['Friebe, Timm J.'], $this->fixture->getAttribute('cn'));
  }

  #[@test]
  public function firstCnAttribute() {
    $this->assertEquals('Friebe, Timm J.', $this->fixture->getAttribute('cn', 0));
  }

  #[@test]
  public function unicodeAttribute() {
    $this->assertEquals('Übercoder', $this->fixture->getAttribute('displayName', 0));
  }

  #[@test]
  public function nonExistantAttribute() {
    $this->assertEquals(null, $this->fixture->getAttribute('@@NON-EXISTANT@@'));
  }

  #[@test]
  public function objectClassAttribute() {
    $this->assertEquals(
      $this->attributes['objectClass'],
      $this->fixture->getAttribute('objectclass')
    );
  }

  #[@test]
  public function isInetOrgPerson() {
    $this->assertTrue($this->fixture->isA('inetOrgPerson'));
  }

  #[@test]
  public function isNotAliasObject() {
    $this->assertFalse($this->fixture->isA('alias'));
  }
  
  #[@test]
  public function addAttributeTest() {
    $this->fixture->setAttribute('newAttribute', 'newValue');
    
    $this->assertEquals('newValue', $this->fixture->getAttribute('newattribute', 0));
    $this->assertEquals('newValue', $this->fixture->getAttribute('newAttribute', 0));
  }
}
