<?php namespace peer\ldap;

use peer\ConnectException;
use peer\URL;
use lang\XPClass;
use lang\lang\IllegalArgumentException;

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
 * @test  xp://net.xp_framework.unittest.peer.LDAPTest
 */
class LDAPConnection extends \lang\Object {
  private $url;
  private $handle= null;

  static function __static() {
    XPClass::forName('peer.ldap.LDAPException');  // Error codes
  }

  /**
   * Create a new connection from a given DSN of the form:
   * `ldap://user:pass@host:port/?options[protocol_version]=2`
   *
   * @param   string|peer.URL $dsn
   */
  public function __construct($dsn) {
    $this->url= $dsn instanceof URL ? $dsn : new URL($dsn);
  }

  /**
   * Connect to the LDAP server
   *
   * @return  resource LDAP resource handle
   * @throws  peer.ConnectException
   */
  public function connect() {
    if ($this->isConnected()) return true;

    if (false === ($this->handle= ldap_connect($this->url->getHost(), $this->url->getPort(389)))) {
      throw new ConnectException('Cannot connect to '.$this->url->getHost().':'.$this->url->getPort(389));
    }
    
    if (false === ldap_bind($this->handle, $this->url->getUser(null), $this->url->getPassword(null))) {
      switch ($error= ldap_errno($this->handle)) {
        case -1: case LDAP_SERVER_DOWN:
          throw new ConnectException('Cannot connect to '.$this->url->getHost().':'.$this->url->getPort(389));
        
        default:
          throw new LDAPException('Cannot bind for "'.$this->url->getUser(null).'"', $error);
      }
    }

    foreach (array_merge(['protocol_version' => 3], $this->url->getParam('options')) as $option => $value) {
      if (false === ldap_set_option($this->handle, constant('LDAP_OPT_'.strtoupper($option), $value))) {
        ldap_unbind($this->handle);
        $this->handle= null;
        throw new LDAPException('Cannot set value "'.$option.'"', ldap_errno($this->handle));
      }
    }

    return $this;
  }

  /**
   * Checks whether the connection is open
   *
   * @return  bool
   */
  public function isConnected() {
    return is_resource($this->handle);
  }
  
  /**
   * Closes the connection
   *
   * @see     php://ldap_close
   */
  public function close() {
    if ($this->handle) {
      ldap_unbind($this->handle);
      $this->handle= null;
    }
  }

  /**
   * Perform an LDAP search with scope LDAP_SCOPE_SUB
   *
   * @param   string base_dn
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
  public function search() {
    $args= func_get_args();
    array_unshift($args, $this->handle);
    if (false === ($res= call_user_func_array('ldap_search', $args))) {
      throw new LDAPException('Search failed', ldap_errno($this->handle));
    }
    
    return new LDAPSearchResult(new LDAPEntries($this->handle, $res));
  }
  
  /**
   * Perform an LDAP search specified by a given filter.
   *
   * @param   peer.ldap.LDAPQuery filter
   * @return  peer.ldap.LDAPSearchResult search result object
   */
  public function searchBy(LDAPQuery $filter) {
    static $methods= [
      LDAPQuery::SCOPE_BASE     => 'ldap_read',
      LDAPQuery::SCOPE_ONELEVEL => 'ldap_list',
      LDAPQuery::SCOPE_SUB      => 'ldap_search'
    ];
    
    if (!isset($methods[$filter->getScope()]))
      throw new \lang\IllegalArgumentException('Scope '.$args[0].' not supported');
    
    if (false === ($res= @call_user_func_array(
      $methods[$filter->getScope()], array(
      $this->handle,
      $filter->getBase(),
      $filter->getFilter(),
      $filter->getAttrs(),
      $filter->getAttrsOnly(),
      $filter->getSizeLimit(),
      $filter->getTimelimit(),
      $filter->getDeref()
    )))) {
      throw new LDAPException('Search failed', ldap_errno($this->handle));
    }

    // Sort results by given sort attributes
    if ($filter->getSort()) foreach ($filter->getSort() as $sort) {
      ldap_sort($this->handle, $res, $sort);
    }
    return new LDAPSearchResult(new LDAPEntries($this->handle, $res));
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
    $res= ldap_read($this->handle, $entry->getDN(), 'objectClass=*', [], false, 0);
    if (LDAP_SUCCESS != ldap_errno($this->handle)) {
      throw new LDAPException('Read "'.$entry->getDN().'" failed', ldap_errno($this->handle));
    }

    $entry= ldap_first_entry($this->handle, $res);
    return LDAPEntry::create(ldap_get_dn($this->handle, $entry), ldap_get_attributes($this->handle, $entry));
  }
  
  /**
   * Check if an entry exists
   *
   * @param   peer.ldap.LDAPEntry entry specifying the dn
   * @return  bool TRUE if the entry exists
   */
  public function exists(LDAPEntry $entry) {
    $res= ldap_read($this->handle, $entry->getDN(), 'objectClass=*', [], false, 0);
    
    // Check for certain error code (#32)
    if (LDAP_NO_SUCH_OBJECT === ldap_errno($this->handle)) {
      return false;
    }
    
    // Check for other errors
    if (LDAP_SUCCESS != ldap_errno($this->handle)) {
      throw new LDAPException('Read "'.$entry->getDN().'" failed', ldap_errno($this->handle));
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
      $this->handle, 
      $entry->getDN(), 
      $entry->getAttributes()
    ))) {
      throw new LDAPException('Add for "'.$entry->getDN().'" failed', ldap_errno($this->handle));
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
      $this->handle,
      $entry->getDN(),
      $entry->getAttributes()
    ))) {
      throw new LDAPException('Modify for "'.$entry->getDN().'" failed', ldap_errno($this->handle));
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
      $this->handle,
      $entry->getDN()
    ))) {
      throw new LDAPException('Delete for "'.$entry->getDN().'" failed', ldap_errno($this->handle));
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
      $this->handle,
      $entry->getDN(),
      [$name => $value]
    ))) {
      throw new LDAPException('Add attribute for "'.$entry->getDN().'" failed', ldap_errno($this->handle));
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
      $this->handle,
      $entry->getDN(),
      $name
    ))) {
      throw new LDAPException('Delete attribute for "'.$entry->getDN().'" failed', ldap_errno($this->handle));
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
      $this->handle,
      $entry->getDN(),
      [$name => $value]
    ))) {
      throw new LDAPException('Replace attribute for "'.$entry->getDN().'" failed', ldap_errno($this->handle));
    }
    
    return $res;
  }
}