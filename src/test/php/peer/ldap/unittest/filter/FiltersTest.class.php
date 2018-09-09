<?php namespace peer\ldap\unittest\filter;

use peer\ldap\filter\AndFilter;
use peer\ldap\filter\ApproximateFilter;
use peer\ldap\filter\EqualityFilter;
use peer\ldap\filter\ExtensibleFilter;
use peer\ldap\filter\Filters;
use peer\ldap\filter\GreaterThanEqualsFilter;
use peer\ldap\filter\LessThanEqualsFilter;
use peer\ldap\filter\NotFilter;
use peer\ldap\filter\OrFilter;
use peer\ldap\filter\PresenceFilter;
use peer\ldap\filter\SubstringFilter;
use unittest\TestCase;

class FiltersTest extends TestCase {

  #[@test]
  public function can_create() {
    new Filters();
  }

  #[@test]
  public function presence() {
    $this->assertEquals(
      new PresenceFilter('objectClass'),
      (new Filters())->parse('objectClass=*')
    );
  }

  #[@test, @values([
  #  'person',
  #  '\*person',
  #  'person\*',
  #])]
  public function equality($value) {
    $this->assertEquals(
      new EqualityFilter('objectClass', $value),
      (new Filters())->parse('objectClass='.$value)
    );
  }

  #[@test]
  public function substring_initial() {
    $this->assertEquals(
      new SubstringFilter('objectClass', 'person', [], null),
      (new Filters())->parse('objectClass=person*')
    );
  }

  #[@test]
  public function substring_final() {
    $this->assertEquals(
      new SubstringFilter('objectClass', null, [], 'person'),
      (new Filters())->parse('objectClass=*person')
    );
  }

  #[@test]
  public function substring_initial_and_final() {
    $this->assertEquals(
      new SubstringFilter('objectClass', 'a', [], 'b'),
      (new Filters())->parse('objectClass=a*b')
    );
  }

  #[@test, @values([
  #  ['a*b*c*d', ['a', ['b', 'c'], 'd']],
  #  ['*b*c*d', [null, ['b', 'c'], 'd']],
  #  ['a*b*c*', ['a', ['b', 'c'], null]],
  #])]
  public function substring_any($input, $expected) {
    $this->assertEquals(
      new SubstringFilter('objectClass', ...$expected),
      (new Filters())->parse('objectClass='.$input)
    );
  }

  #[@test]
  public function approximate() {
    $this->assertEquals(
      new ApproximateFilter('cn', 'Test'),
      (new Filters())->parse('cn~=Test')
    );
  }

  #[@test]
  public function greater_than() {
    $this->assertEquals(
      new GreaterThanEqualsFilter('storageQuota', '100'),
      (new Filters())->parse('storageQuota>=100')
    );
  }

  #[@test]
  public function less_than() {
    $this->assertEquals(
      new LessThanEqualsFilter('storageQuota', '100'),
      (new Filters())->parse('storageQuota<=100')
    );
  }

  #[@test]
  public function extensible() {
    $this->assertEquals(
      new ExtensibleFilter('userAccountControl', '1.2.840.113556.1.4.804', '65568'),
      (new Filters())->parse('userAccountControl:1.2.840.113556.1.4.804:=65568')
    );
  }

  #[@test]
  public function braces() {
    $this->assertEquals(
      new EqualityFilter('cn', 'Test'),
      (new Filters())->parse('(cn=Test)')
    );
  }

  #[@test]
  public function not() {
    $this->assertEquals(
      new NotFilter(new EqualityFilter('cn', 'Test')),
      (new Filters())->parse('!(cn=Test)')
    );
  }

  #[@test]
  public function and() {
    $this->assertEquals(
      new AndFilter(
        new EqualityFilter('objectClass', 'person'),
        new ApproximateFilter('cn', 'Test')
      ),
      (new Filters())->parse('&(objectClass=person)(cn~=Test)')
    );
  }

  #[@test]
  public function and_and_or() {
    $this->assertEquals(
      new OrFilter(
        new AndFilter(
          new EqualityFilter('objectClass', 'person'),
          new ApproximateFilter('cn', 'Test')
        ),
        new EqualityFilter('cn', 'Test')
      ),
      (new Filters())->parse('|(&(objectClass=person)(cn~=Test))(cn=Test)')
    );
  }
}