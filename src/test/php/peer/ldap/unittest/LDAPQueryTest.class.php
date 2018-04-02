<?php namespace peer\ldap\unittest;
 
use lang\IllegalArgumentException;
use peer\ldap\LDAPQuery;
use unittest\TestCase;
use util\Date;

/**
 * Test LDAPQuery class
 *
 * @see   xp://peer.ldap.LDAPQuery
 */
class LDAPQueryTest extends TestCase {
  private $fixture;

  /** @return void */
  public function setUp() {
    $this->fixture= new LDAPQuery();
  }

  #[@test]
  public function can_create() {
    new LDAPQuery();
  }

  #[@test]
  public function can_create_with_base() {
    $this->assertEquals('dc=example,dc=com', (new LDAPQuery('dc=example,dc=com'))->getBase());
  }

  #[@test]
  public function can_create_with_base_filter_and_format_args() {
    $this->assertEquals('(uid=test)', (new LDAPQuery('dc=example,dc=com', '(uid=%s)', 'test'))->getFilter());
  }

  #[@test]
  public function replaces_s_token_with_string() {
    $this->assertEquals(
      '(&(objectClass=*)(uid=kiesel))',
      $this->fixture->prepare('(&(objectClass=*)(uid=%s))', 'kiesel')
    );
  }

  #[@test]
  public function percent_sign_first() {
    $this->assertEquals('foo bar', $this->fixture->prepare('%s', 'foo bar'));
  }

  #[@test]
  public function percent_sign_escaping() {
    $this->assertEquals('foo%bar', $this->fixture->prepare('foo%%bar', 'arg'));
  }

  #[@test]
  public function numbered_tokens_are_supported() {
    $this->assertEquals('foo bar', $this->fixture->prepare('%2$s %1$s', 'bar', 'foo'));
  }

  #[@test, @values([
  #  ['foo(bar', 'foo\\28bar'],
  #  ['foo)bar', 'foo\\29bar'],
  #  ['foo\\bar', 'foo\\5cbar'],
  #  ['foo*bar', 'foo\\2abar'],
  #  ["foo\000bar", 'foo\\00bar']
  #])]
  public function special_characters_are_escaped($input, $expected) {
    $this->assertEquals($expected, $this->fixture->prepare('%s', $input));
  }

  #[@test]
  public function copy_through_token() {
    $this->assertEquals('foo(*'.chr(0).'\\)bar', $this->fixture->prepare('%c', 'foo(*'.chr(0).'\\)bar'));
  }
  
  #[@test, @values(['%d', '%s'])]
  public function dates_as_argument($token) {
    $this->assertEquals(
      '198005280630Z+0200',
      $this->fixture->prepare($token, new Date('1980-05-28 06:30:00 Europe/Berlin'))
    );
  }

  #[@test, @values(['%d', '%s'])]
  public function null_as_argument($token) {
    $this->assertEquals('NULL', $this->fixture->prepare($token, null));
  }

  #[@test, @expect(IllegalArgumentException::class), @values([
  #  [[]],
  #  [[1, 2, 3]],
  #  [['color' => 'green']],
  #])]
  public function invalid_array_argument($arg) {
    $this->fixture->prepare('%d', $arg);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function invalid_object_argument() {
    $this->fixture->prepare('%d', new \StdClass());
  }
}
