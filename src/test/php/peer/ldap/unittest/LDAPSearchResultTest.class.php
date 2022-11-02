<?php namespace peer\ldap\unittest;
 
use peer\ldap\{LDAPEntries, LDAPEntry, LDAPSearchResult};
use unittest\{Test, TestCase, Values};

class LDAPSearchResultTest extends TestCase {

  /**
   * Creates a new LDAPEntries
   *
   * @param  string[] $dns
   * @return peer.ldap.LDAPEntries
   */
  private function newEntries($dns= []) {
    return new class($dns) extends LDAPEntries {
      private $entries, $offset= 0;

      public function __construct($entries) { $this->entries= $entries; }
      public function entry($offset) {
        return isset($this->entries[$offset])
          ? new LDAPEntry($this->entries[$offset], [])
          : null
        ;
      }
      public function size() { return sizeof($this->entries); }
      public function first() { return $this->entry($this->offset= 0); }
      public function next() { return $this->entry(++$this->offset); }
      public function close() { $this->entries= null; }
    };
  }

  #[Test]
  public function can_create() {
    new LDAPSearchResult($this->newEntries());
  }

  #[Test, Values([[[]], [['cn=first,o=test']], [['cn=first,o=test'], ['cn=second,o=test']]])]
  public function numEntries($entries) {
    $this->assertEquals(sizeof($entries), (new LDAPSearchResult($this->newEntries($entries)))->numEntries());
  }

  #[Test]
  public function no_first_entry() {
    $this->assertNull((new LDAPSearchResult($this->newEntries()))->getFirstEntry());
  }

  #[Test, Values([[['cn=first,o=test']], [['cn=first,o=test'], ['cn=second,o=test']]])]
  public function first($entries) {
    $this->assertEquals(
      new LDAPEntry($entries[0], []),
      (new LDAPSearchResult($this->newEntries($entries)))->getFirstEntry()
    );
  }

  #[Test]
  public function no_next_entry() {
    $this->assertNull((new LDAPSearchResult($this->newEntries()))->getNextEntry());
  }

  #[Test, Values([[['cn=first,o=test']], [['cn=first,o=test', 'cn=second,o=test']]])]
  public function next($entries) {
    $this->assertEquals(
      new LDAPEntry($entries[0], []),
      (new LDAPSearchResult($this->newEntries($entries)))->getNextEntry()
    );
  }

  #[Test, Values([[['cn=first,o=test']], [['cn=first,o=test', 'cn=second,o=test']]])]
  public function iteration_via_next($entries) {
    $result= new LDAPSearchResult($this->newEntries($entries));
    $actual= [];
    while ($entry= $result->getNextEntry()) {
      $actual[]= $entry->getDN();
    }
    $this->assertEquals($actual, $entries);
  }

  #[Test, Values([[['cn=first,o=test']], [['cn=first,o=test', 'cn=second,o=test']]])]
  public function iteration_via_first_and_ext($entries) {
    $result= new LDAPSearchResult($this->newEntries($entries));
    $actual= [];
    if ($entry= $result->getFirstEntry()) do {
      $actual[]= $entry->getDN();
    } while ($entry= $result->getNextEntry());
    $this->assertEquals($actual, $entries);
  }

  #[Test]
  public function no_entry_zero() {
    $this->assertNull((new LDAPSearchResult($this->newEntries()))->getEntry(0));
  }

  #[Test, Values([[['cn=first,o=test']], [['cn=first,o=test', 'cn=second,o=test']]])]
  public function entry_zero($entries) {
    $this->assertEquals(
      new LDAPEntry($entries[0], []),
      (new LDAPSearchResult($this->newEntries($entries)))->getEntry(0)
    );
  }

  #[Test, Values([[['cn=first,o=test']], [['cn=first,o=test', 'cn=second,o=test']]])]
  public function iteration_via_foreach($entries) {
    $result= new LDAPSearchResult($this->newEntries($entries));
    $actual= [];
    foreach ($result as $entry) {
      $actual[]= $entry->getDN();
    }
    $this->assertEquals($entries, $actual);
  }

  #[Test]
  public function iteration_via_foreach_when_first_returns_null() {
    $result= new LDAPSearchResult($this->newEntries());
    $this->assertEquals([], iterator_to_array($result));
  }
}