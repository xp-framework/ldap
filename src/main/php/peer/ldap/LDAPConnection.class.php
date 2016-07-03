<?php namespace peer\ldap;

use peer\ConnectException;
use peer\URL;
use lang\XPClass;
use lang\IllegalArgumentException;
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
class LDAPConnection extends \lang\Object {
  private static $options;

  private $url;
  private $handle= null;

  static function __static() {
    XPClass::forName('peer.ldap.LDAPException');  // Error codes
    self::$options= [
      'deref' => function($handle, $value) {
        return ldap_set_option($handle, LDAP_OPT_DEREF, constant('LDAP_DEREF_'.strtoupper($value)));
      },
      'sizelimit' => function($handle, $value) {
        return ldap_set_option($handle, LDAP_OPT_SIZELIMIT, (int)$value);
      },
      'timelimit' => function($handle, $value) {
        return ldap_set_option($handle, LDAP_OPT_TIMELIMIT, (int)$value);
      },
      'network_timeout' => function($handle, $value) {
        return ldap_set_option($handle, LDAP_OPT_NETWORK_TIMEOUT, (int)$value);
      },
      'protocol_version' => function($handle, $value) {
        return ldap_set_option($handle, LDAP_OPT_PROTOCOL_VERSION, (int)$value);
      },
    ];
  }

  /**
   * Create a new connection from a given DSN of the form:
   * `ldap://user:pass@host:port/?options[protocol_version]=2`
   *
   * @param  string|peer.URL $dsn
   * @throws lang.IllegalArgumentException when DSN is malformed
   */
  public function __construct($dsn) {
    $this->url= $dsn instanceof URL ? $dsn : new URL($dsn);
    foreach ($this->url->getParams() as $option => $value) {
      if (!isset(self::$options[$option])) {
        throw new IllegalArgumentException('Unknown option "'.$option.'"');
      }
    }
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
    static $ports= ['ldap' => 389, 'ldaps' => 636];

    if ($this->isConnected()) return true;

    $uri= sprintf(
      '%s://%s:%d',
      $this->url->getScheme(),
      $this->url->getHost(),
      $this->url->getPort($ports[$this->url->getScheme()])
    );
    if (false === ($this->handle= ldap_connect($uri))) {
      throw new ConnectException('Cannot connect to '.$uri);
    }

    foreach (array_merge(['protocol_version' => 3], $this->url->getParams()) as $option => $value) {
      $set= self::$options[$option];
      if (!$set($this->handle, $value)) {
        ldap_unbind($this->handle);
        $this->handle= null;
        throw new LDAPException('Cannot set option "'.$option.'"', ldap_errno($this->handle));
      }
    }

    if (null === $dn) {
      $result= ldap_bind($this->handle, $this->url->getUser(null), $this->url->getPassword(null));
    } else {
      $result= ldap_bind($this->handle, $dn, $password->reveal());
    }
    if (false === $result) {
      $error= ldap_errno($this->handle);
      ldap_unbind($this->handle);
      $this->handle= null;
      if (LDAP_SERVER_DOWN === $error || -1 === $error) {
        throw new ConnectException('Cannot connect to '.$uri);
      } else {
        throw new LDAPException('Cannot bind for "'.($user ?: $this->url->getUser(null)).'"', $error);
      }
    }

    return $this;
  }

  /**
   * Checks whether the connection is open
   *
   * @return bool
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
   * Error handler
   *
   * @param  string $message
   * @return peer.ldap.LDAPException
   */
  private function error($message) {
    $error= ldap_errno($this->handle);
    if (LDAP_SERVER_DOWN === $error || -1 === $error) {
      ldap_unbind($this->handle);
      $this->handle= null;
      return new LDAPDisconnected($message, $error);
    } else {
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
  public function search($base, $filter, $attributes, $attrsonly, $sizelimit, $timelimit, $deref) {
    if (false === ($res= ldap_search(
      $this->handle,
      $base,
      $filter,
      $attrs,
      $attrsOnly,
      $sizeLimit,
      $timelimit,
      $deref
    ))) {
      throw $this->error('Search failed');
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
    
    if (!isset($methods[$filter->getScope()])) {
      throw new IllegalArgumentException('Scope '.$filter->getScope().' not supported');
    }

    $f= $methods[$filter->getScope()];
    if (false === ($res= $f(
      $this->handle,
      $filter->getBase(),
      $filter->getFilter(),
      $filter->getAttrs(),
      $filter->getAttrsOnly(),
      $filter->getSizeLimit(),
      $filter->getTimelimit(),
      $filter->getDeref()
    ))) {
      throw $this->error('Search failed');
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
      throw $this->error('Read "'.$entry->getDN().'" failed');
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
      $this->handle, 
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
      $this->handle,
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
      $this->handle,
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
      $this->handle,
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
      $this->handle,
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
      $this->handle,
      $entry->getDN(),
      [$name => $value]
    ))) {
      throw $this->error('Replace attribute for "'.$entry->getDN().'" failed');
    }
    
    return $res;
  }
}