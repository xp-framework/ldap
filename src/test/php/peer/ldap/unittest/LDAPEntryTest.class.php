<?php namespace peer\ldap\unittest;
 
use peer\ldap\LDAPEntry;

/**
 * Test LDAP entry class
 *
 * @see      xp://peer.ldap.LDAPEntry
 */
class LDAPEntryTest extends \unittest\TestCase {
  const DN = 'uid=friebe,ou=People,dc=xp-framework,dc=net';

  private $attributes = [
    'cn'          => ['Friebe, Timm J.'],
    'sn'          => ['Friebe'],
    'givenName'   => ['Timm'],
    'uid'         => ['friebe'],
    'displayName' => ['Friebe, Timm'],
    'mail'        => ['friebe@example.com'],
    'o'           => ['XP-Framework'],
    'ou'          => ['People'],
    'objectClass' => ['top', 'person', 'inetOrgPerson', 'organizationalPerson']
  ];

  private $entry;

  /**
   * Setup method
   *
   * @return void
   */    
  public function setUp() {
    $this->entry= new LDAPEntry(self::DN, $this->attributes);
  }

  #[@test]
  public function getDN() {
    $this->assertEquals(self::DN, $this->entry->getDN());
  }

  #[@test]
  public function getAttributes() {
    $this->assertEquals(array_change_key_case($this->attributes, CASE_LOWER), $this->entry->getAttributes());
  }

  #[@test]
  public function cnAttribute() {
    $this->assertEquals(['Friebe, Timm J.'], $this->entry->getAttribute('cn'));
  }

  #[@test]
  public function firstCnAttribute() {
    $this->assertEquals('Friebe, Timm J.', $this->entry->getAttribute('cn', 0));
  }

  #[@test]
  public function nonExistantAttribute() {
    $this->assertEquals(null, $this->entry->getAttribute('@@NON-EXISTANT@@'));
  }

  #[@test]
  public function objectClassAttribute() {
    $this->assertEquals(
      $this->attributes['objectClass'],
      $this->entry->getAttribute('objectclass')
    );
  }

  #[@test]
  public function isInetOrgPerson() {
    $this->assertTrue($this->entry->isA('inetOrgPerson'));
  }

  #[@test]
  public function isNotAliasObject() {
    $this->assertFalse($this->entry->isA('alias'));
  }
  
  #[@test]
  public function addAttributeTest() {
    $this->entry->setAttribute('newAttribute', 'newValue');
    
    $this->assertEquals('newValue', $this->entry->getAttribute('newattribute', 0));
    $this->assertEquals('newValue', $this->entry->getAttribute('newAttribute', 0));
  }
}
