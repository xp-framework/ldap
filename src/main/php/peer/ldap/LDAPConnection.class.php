<?php namespace peer\ldap;

use lang\IllegalArgumentException;
use lang\Value;
use lang\XPClass;
use peer\ConnectException;
use peer\URL;
use peer\ldap\util\LdapLibrary;
use peer\ldap\util\LdapProtocol;
use util\Secret;

/**
 * LDAP connection
 * 
 * @see   php://ldap
 * @see   http://developer.netscape.com/docs/manuals/directory/41/ag/
 * @see   http://developer.netscape.com/docs/manuals/dirsdk/jsdk40/contents.htm
 * @see   http://perl-ldap.sourceforge.net/doc/Net/LDAP/
 * @see   http://ldap.akbkhome.com/
 * @see   rfc://2251
 * @see   rfc://2252
 * @see   rfc://2253
 * @see   rfc://2254
 * @see   rfc://2255
 * @see   rfc://2256
 * @ext   ldap
 * @test  xp://peer.ldap.unittest.LDAPConnectionTest
 */
class LDAPConnection implements Value {
  private $url;
  private $handle= null;

  static function __static() {
    XPClass::forName('peer.ldap.LDAPException');  // Error codes
  }

  /**
   * Create a new connection from a given DSN of the form:
   * `ldap://user:pass@host:port/?options[protocol_version]=2`
   *
   * @param  string|peer.URL $dsn
   * @throws lang.IllegalArgumentException when DSN is malformed
   */
  public function __construct($dsn) {
    static $ports= ['ldap' => 389, 'ldaps' => 636];

    // TODO: Driver!
    $impl= getenv('PROTO') ? LdapProtocol::class : LdapLibrary::class;

    $this->url= $dsn instanceof URL ? $dsn : new URL($dsn);
    $this->proto= new $impl(
      $this->url->getScheme(),
      $this->url->getHost(),
      $this->url->getPort($ports[$this->url->getScheme()]),
      $this->url->getParams()
    );
  }

  /** @return peer.URL */
  public function dsn() { return $this->url; }

  /**
   * Connect to the LDAP server. Optionally takes DN and password which
   * overwrite any credentials given in the connection DSN.
   *
   * @param  string $dn
   * @param  util.Secret $password
   * @return self $this
   * @throws peer.ConnectException
   */
  public function connect($dn= null, Secret $password= null) {
    if ($this->proto->connected()) return true;

    $this->proto->connect(
      $dn ?: $this->url->getUser(null),
      $password ?: new Secret($this->url->getPassword(null))
    );
    return $this;
  }

  /**
   * Checks whether the connection is open
   *
   * @return bool
   */
  public function isConnected() {
    return $this->proto->connected();
  }
  
  /**
   * Closes the connection
   *
   * @see     php://ldap_close
   */
  public function close() {
    $this->proto->close();
  }

  /**
   * Error handler
   *
   * @param  string $message
   * @return peer.ldap.LDAPException
   */
  private function error($message) {
    $error= ldap_errno($this->proto->handle);
    switch ($error) {
      case -1: case LDAP_SERVER_DOWN:
        ldap_unbind($this->proto->handle);
        $this->proto->handle= null;
        return new LDAPDisconnected($message, $error);

      case LDAP_NO_SUCH_OBJECT:
        return new LDAPNoSuchObject($message, $error);
    
      default:  
        return new LDAPException($message, $error);
    }
  }

  /**
   * Perform an LDAP search with scope LDAP_SCOPE_SUB
   *
   * @param   string base
   * @param   string filter
   * @param   array attributes default []
   * @param   int attrsonly default 0,
   * @param   int sizelimit default 0
   * @param   int timelimit default 0 Time limit, 0 means no limit
   * @param   int deref one of LDAP_DEREF_*
   * @return  peer.ldap.LDAPSearchResult search result object
   * @throws  peer.ldap.LDAPException
   * @see     php://ldap_search
   */
  public function search($base, $filter, $attributes= [], $attrsOnly= 0, $sizeLimit= 0, $timeLimit= 0, $deref= LDAP_DEREF_NEVER) {
    return new LDAPSearchResult($this->proto->search(
      LDAPQuery::SCOPE_SUB,
      $base,
      $filter,
      $attributes,
      $attrsOnly,
      $sizeLimit,
      $timeLimit,
      null,
      $deref
    ));
  }
  
  /**
   * Perform an LDAP search specified by a given filter.
   *
   * @param   peer.ldap.LDAPQuery filter
   * @return  peer.ldap.LDAPSearchResult search result object
   */
  public function searchBy(LDAPQuery $filter) {
    return new LDAPSearchResult($this->proto->search(
      $filter->getScope(),
      $filter->getBase(),
      $filter->getFilter(),
      $filter->getAttrs(),
      $filter->getAttrsOnly(),
      $filter->getSizeLimit(),
      $filter->getTimelimit(),
      $filter->getSort(),
      $filter->getDeref()
    ));
  }
  
  /**
   * Read an entry
   *
   * @param   peer.ldap.LDAPEntry entry specifying the dn
   * @return  peer.ldap.LDAPEntry entry
   * @throws  lang.IllegalArgumentException
   * @throws  peer.ldap.LDAPException
   */
  public function read(LDAPEntry $entry) {
    $res= ldap_read($this->proto->handle, $entry->getDN(), 'objectClass=*', [], false, 0);
    if (LDAP_SUCCESS != ldap_errno($this->proto->handle)) {
      throw $this->error('Read "'.$entry->getDN().'" failed');
    }

    $entry= ldap_first_entry($this->proto->handle, $res);
    return LDAPEntry::create(ldap_get_dn($this->proto->handle, $entry), ldap_get_attributes($this->proto->handle, $entry));
  }
  
  /**
   * Check if an entry exists
   *
   * @param   peer.ldap.LDAPEntry entry specifying the dn
   * @return  bool TRUE if the entry exists
   */
  public function exists(LDAPEntry $entry) {
    $res= ldap_read($this->proto->handle, $entry->getDN(), 'objectClass=*', [], false, 0);
    
    // Check for certain error code (#32)
    if (LDAP_NO_SUCH_OBJECT === ldap_errno($this->proto->handle)) {
      return false;
    }
    
    // Check for other errors
    if (LDAP_SUCCESS != ldap_errno($this->proto->handle)) {
      throw $this->error('Read "'.$entry->getDN().'" failed');
    }
    
    // No errors occurred, requested object exists
    ldap_free_result($res);
    return true;
  }
  
  /**
   * Add an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @return  bool success
   * @throws  lang.IllegalArgumentException when entry parameter is not an LDAPEntry object
   * @throws  peer.ldap.LDAPException when an error occurs during adding the entry
   */
  public function add(LDAPEntry $entry) {
    
    // This actually returns NULL on failure, not FALSE, as documented
    if (null == ($res= ldap_add(
      $this->proto->handle, 
      $entry->getDN(), 
      $entry->getAttributes()
    ))) {
      throw $this->error('Add for "'.$entry->getDN().'" failed');
    }
    
    return $res;
  }

  /**
   * Modify an entry. 
   *
   * Note: Will do a complete update of all fields and can be quite slow
   * TBD(?): Be more intelligent about what to update?
   *
   * @param   peer.ldap.LDAPEntry entry
   * @return  bool success
   * @throws  lang.IllegalArgumentException when entry parameter is not an LDAPEntry object
   * @throws  peer.ldap.LDAPException when an error occurs during adding the entry
   */
  public function modify(LDAPEntry $entry) {
    if (false == ($res= ldap_modify(
      $this->proto->handle,
      $entry->getDN(),
      $entry->getAttributes()
    ))) {
      throw $this->error('Modify for "'.$entry->getDN().'" failed');
    }
    
    return $res;
  }

  /**
   * Delete an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @return  bool success
   * @throws  lang.IllegalArgumentException when entry parameter is not an LDAPEntry object
   * @throws  peer.ldap.LDAPException when an error occurs during adding the entry
   */
  public function delete(LDAPEntry $entry) {
    if (false == ($res= ldap_delete(
      $this->proto->handle,
      $entry->getDN()
    ))) {
      throw $this->error('Delete for "'.$entry->getDN().'" failed');
    }
    
    return $res;
  }

  /**
   * Add an attribute to an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @param   string name
   * @param   var value
   * @return  bool
   */
  public function addAttribute(LDAPEntry $entry, $name, $value) {
    if (false == ($res= ldap_mod_add(
      $this->proto->handle,
      $entry->getDN(),
      [$name => $value]
    ))) {
      throw $this->error('Add attribute for "'.$entry->getDN().'" failed');
    }
    
    return $res;
  }

  /**
   * Delete an attribute from an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @param   string name
   * @return  bool
   */
  public function deleteAttribute(LDAPEntry $entry, $name) {
    if (false == ($res= ldap_mod_del(
      $this->proto->handle,
      $entry->getDN(),
      $name
    ))) {
      throw $this->error('Delete attribute for "'.$entry->getDN().'" failed');
    }
    
    return $res;
  }

  /**
   * Add an attribute to an entry
   *
   * @param   peer.ldap.LDAPEntry entry
   * @param   string name
   * @param   var value
   * @return  bool
   */
  public function replaceAttribute(LDAPEntry $entry, $name, $value) {
    if (false == ($res= ldap_mod_replace(
      $this->proto->handle,
      $entry->getDN(),
      [$name => $value]
    ))) {
      throw $this->error('Replace attribute for "'.$entry->getDN().'" failed');
    }
    
    return $res;
  }

  /** @return string */
  public function toString() { return nameof($this).'('.$this->proto->connection().')'; }

  /** @return string */
  public function hashCode() { return 'C'.$this->proto->id(); }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value === $this ? 0 : 1;
  }
}