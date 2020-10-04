<?php namespace peer\ldap\unittest;
 
use lang\IllegalArgumentException;
use peer\ldap\LDAPQuery;
use unittest\{Expect, Test, TestCase, Values};
use util\Date;

/**
 * Test LDAPQuery class
 *
 * @see   xp://peer.ldap.LDAPQuery
 */
class LDAPQueryTest extends TestCase {

  #[Test]
  public function can_create() {
    new LDAPQuery();
  }

  #[Test]
  public function can_create_with_base() {
    $this->assertEquals('dc=example,dc=com', (new LDAPQuery('dc=example,dc=com'))->getBase());
  }

  #[Test]
  public function can_create_with_base_filter_and_format_args() {
    $this->assertEquals('(uid=test)', (new LDAPQuery('dc=example,dc=com', '(uid=%s)', 'test'))->getFilter());
  }

  #[Test]
  public function replaces_s_token_with_string() {
    $this->assertEquals(
      '(&(objectClass=*)(uid=kiesel))',
      (new LDAPQuery())->prepare('(&(objectClass=*)(uid=%s))', 'kiesel')
    );
  }

  #[Test]
  public function percent_sign_first() {
    $this->assertEquals('foo bar', (new LDAPQuery())->prepare('%s', 'foo bar'));
  }

  #[Test]
  public function percent_sign_escaping() {
    $this->assertEquals('foo%bar', (new LDAPQuery())->prepare('foo%%bar', 'arg'));
  }

  #[Test]
  public function numbered_tokens_are_supported() {
    $this->assertEquals('foo bar', (new LDAPQuery())->prepare('%2$s %1$s', 'bar', 'foo'));
  }

  #[Test, Values([['foo(bar', 'foo\\28bar'], ['foo)bar', 'foo\\29bar'], ['foo\\bar', 'foo\\5cbar'], ['foo*bar', 'foo\\2abar'], ["foo\000bar", 'foo\\00bar']])]
  public function special_characters_are_escaped($input, $expected) {
    $this->assertEquals($expected, (new LDAPQuery())->prepare('%s', $input));
  }

  #[Test]
  public function copy_through_token() {
    $this->assertEquals('foo(*'.chr(0).'\\)bar', (new LDAPQuery())->prepare('%c', 'foo(*'.chr(0).'\\)bar'));
  }
  
  #[Test, Values(['%d', '%s'])]
  public function dates_as_argument($token) {
    $this->assertEquals(
      '198005280630Z+0200',
      (new LDAPQuery())->prepare($token, new Date('1980-05-28 06:30:00 Europe/Berlin'))
    );
  }

  #[Test, Values(['%d', '%s'])]
  public function null_as_argument($token) {
    $this->assertEquals('NULL', (new LDAPQuery())->prepare($token, null));
  }

  #[Test, Expect(IllegalArgumentException::class), Values([[[]], [[1, 2, 3]], [['color' => 'green']],])]
  public function invalid_array_argument($arg) {
    (new LDAPQuery())->prepare('%d', $arg);
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function invalid_object_argument() {
    (new LDAPQuery())->prepare('%d', new \StdClass());
  }

  #[Test]
  public function deref_setter() {
    $this->assertEquals(
      LDAP_DEREF_ALWAYS,
      (new LDAPQuery())->setDeref(LDAP_DEREF_ALWAYS)->getDeref()
    );
  }
}