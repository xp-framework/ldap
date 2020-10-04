<?php namespace peer\ldap\unittest;
 
use peer\ldap\LDAPEntry;
use unittest\Test;

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

  #[Test]
  public function getDN() {
    $this->assertEquals(self::DN, $this->fixture->getDN());
  }

  #[Test]
  public function getAttributes() {
    $this->assertEquals(array_change_key_case($this->attributes, CASE_LOWER), $this->fixture->getAttributes());
  }

  #[Test]
  public function cnAttribute() {
    $this->assertEquals(['Friebe, Timm J.'], $this->fixture->getAttribute('cn'));
  }

  #[Test]
  public function firstCnAttribute() {
    $this->assertEquals('Friebe, Timm J.', $this->fixture->getAttribute('cn', 0));
  }

  #[Test]
  public function unicodeAttribute() {
    $this->assertEquals('Übercoder', $this->fixture->getAttribute('displayName', 0));
  }

  #[Test]
  public function nonExistantAttribute() {
    $this->assertEquals(null, $this->fixture->getAttribute('@@NON-EXISTANT@@'));
  }

  #[Test]
  public function objectClassAttribute() {
    $this->assertEquals(
      $this->attributes['objectClass'],
      $this->fixture->getAttribute('objectclass')
    );
  }

  #[Test]
  public function isInetOrgPerson() {
    $this->assertTrue($this->fixture->isA('inetOrgPerson'));
  }

  #[Test]
  public function isNotAliasObject() {
    $this->assertFalse($this->fixture->isA('alias'));
  }
  
  #[Test]
  public function addAttributeTest() {
    $this->fixture->setAttribute('newAttribute', 'newValue');
    
    $this->assertEquals('newValue', $this->fixture->getAttribute('newattribute', 0));
    $this->assertEquals('newValue', $this->fixture->getAttribute('newAttribute', 0));
  }
}