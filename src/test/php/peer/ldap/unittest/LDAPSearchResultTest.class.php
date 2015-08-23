<?php namespace peer\ldap\unittest;
 
use peer\ldap\LDAPSearchResult;
use peer\ldap\LDAPEntry;

class LDAPSearchResultTest extends \unittest\TestCase {

  /**
   * Creates a new LDAPEntries
   *
   * @param  string[] $dns
   * @return peer.ldap.LDAPEntries
   */
  private function newEntries($dns= []) {
    return newinstance('peer.ldap.LDAPEntries', [$dns], [
      'entries' => [],
      'offset' => 0,
      '__construct' => function($entries) { $this->entries= $entries; },
      'entry' => function($offset) {
        return isset($this->entries[$offset])
          ? new LDAPEntry($this->entries[$offset], [])
          : null
        ;
      },
      'size' => function() { return sizeof($this->entries); },
      'first' => function() { return $this->entry($this->offset= 0); },
      'next' => function() { return $this->entry(++$this->offset); },
      'close' => function() { $this->entries= null; }
    ]);
  }

  #[@test]
  public function can_create() {
    new LDAPSearchResult($this->newEntries());
  }

  #[@test, @values([
  #  [[]],
  #  [['cn=first,o=test']],
  #  [['cn=first,o=test'], ['cn=second,o=test']]
  #])]
  public function numEntries($entries) {
    $this->assertEquals(sizeof($entries), (new LDAPSearchResult($this->newEntries($entries)))->numEntries());
  }

  #[@test]
  public function no_first_entry() {
    $this->assertFalse((new LDAPSearchResult($this->newEntries()))->getFirstEntry());
  }

  #[@test, @values([
  #  [['cn=first,o=test']],
  #  [['cn=first,o=test'], ['cn=second,o=test']]
  #])]
  public function first($entries) {
    $this->assertEquals(
      new LDAPEntry($entries[0], []),
      (new LDAPSearchResult($this->newEntries($entries)))->getFirstEntry()
    );
  }

  #[@test]
  public function no_next_entry() {
    $this->assertFalse((new LDAPSearchResult($this->newEntries()))->getNextEntry());
  }

  #[@test, @values([
  #  [['cn=first,o=test']],
  #  [['cn=first,o=test', 'cn=second,o=test']]
  #])]
  public function next($entries) {
    $this->assertEquals(
      new LDAPEntry($entries[0], []),
      (new LDAPSearchResult($this->newEntries($entries)))->getNextEntry()
    );
  }

  #[@test, @values([
  #  [['cn=first,o=test']],
  #  [['cn=first,o=test', 'cn=second,o=test']]
  #])]
  public function iteration_via_next($entries) {
    $result= new LDAPSearchResult($this->newEntries($entries));
    $actual= [];
    while ($entry= $result->getNextEntry()) {
      $actual[]= $entry->getDN();
    }
    $this->assertEquals($actual, $entries);
  }

  #[@test, @values([
  #  [['cn=first,o=test']],
  #  [['cn=first,o=test', 'cn=second,o=test']]
  #])]
  public function iteration_via_first_and_ext($entries) {
    $result= new LDAPSearchResult($this->newEntries($entries));
    $actual= [];
    if ($entry= $result->getFirstEntry()) do {
      $actual[]= $entry->getDN();
    } while ($entry= $result->getNextEntry());
    $this->assertEquals($actual, $entries);
  }

  #[@test]
  public function no_entry_zero() {
    $this->assertFalse((new LDAPSearchResult($this->newEntries()))->getEntry(0));
  }

  #[@test, @values([
  #  [['cn=first,o=test']],
  #  [['cn=first,o=test', 'cn=second,o=test']]
  #])]
  public function entry_zero($entries) {
    $this->assertEquals(
      new LDAPEntry($entries[0], []),
      (new LDAPSearchResult($this->newEntries($entries)))->getEntry(0)
    );
  }

  #[@test, @values([
  #  [['cn=first,o=test']],
  #  [['cn=first,o=test', 'cn=second,o=test']]
  #])]
  public function iteration_via_foreach($entries) {
    $result= new LDAPSearchResult($this->newEntries($entries));
    $actual= [];
    foreach ($result as $entry) {
      $actual[]= $entry->getDN();
    }
    $this->assertEquals($actual, $entries);
  }
}