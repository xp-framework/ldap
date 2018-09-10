<?php namespace peer\ldap\util;

use lang\IllegalArgumentException;
use peer\ConnectException;
use peer\ldap\LDAPDisconnected;
use peer\ldap\LDAPEntries;
use peer\ldap\LDAPException;
use peer\ldap\LDAPNoSuchObject;
use peer\ldap\LDAPQuery;
use peer\ldap\LDAPSearchResult;
use peer\ldap\SortedLDAPEntries;

class LdapLibrary {
  private static $options;
  private $url;
  public $handle= null;

  static function __static() {
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

  public function __construct($scheme, $host, $port, $params) {
    $this->uri= sprintf('%s://%s:%d', $scheme, $host, $port);
    foreach ($params as $option => $value) {
      if (!isset(self::$options[$option])) {
        throw new IllegalArgumentException('Unknown option "'.$option.'"');
      }
    }
    $this->params= array_merge(['protocol_version' => 3], $params);
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

  /** @return string */
  public function connection() { return $this->handle.' -> '.$this->uri; }

  /** @return int */
  public function id() { return (int)$this->handle; }

  /** @return bool */
  public function connected() { return null !== $this->handle; }

  /**
   * Connect and bind
   *
   * @param  string $user
   * @param  util.Secret $password
   * @return void
   * @throws peer.ConnectException
   * @throws peer.ldap.LDAPException
   */
  public function connect($user, $password) {
    if (false === ($this->handle= ldap_connect($this->uri))) {
      throw new ConnectException('Cannot connect to '.$this->uri);
    }

    foreach ($this->params as $option => $value) {
      $set= self::$options[$option];
      if (!$set($this->handle, $value)) {
        ldap_unbind($this->handle);
        $this->handle= null;
        throw new ConnectException('Cannot set option "'.$option.'"', ldap_errno($this->handle));
      }
    }

    $result= ldap_bind($this->handle, $user, $password ? $password->reveal() : null);
    if (false === $result) {
      $error= ldap_errno($this->handle);
      ldap_unbind($this->handle);
      $this->handle= null;
      if (LDAP_SERVER_DOWN === $error || -1 === $error) {
        throw new ConnectException('Cannot connect to '.$uri);
      } else {
        throw new LDAPException('Cannot bind for "'.($dn ?: $this->url->getUser(null)).'"', $error);
      }
    } 
  }

  public function search($scope, $base, $filter, $attributes= [], $attrsOnly= 0, $sizeLimit= 0, $timeLimit= 0, $sort= [], $deref= LDAP_DEREF_NEVER) {
    static $methods= [
      LDAPQuery::SCOPE_BASE     => 'ldap_read',
      LDAPQuery::SCOPE_ONELEVEL => 'ldap_list',
      LDAPQuery::SCOPE_SUB      => 'ldap_search'
    ];

    if (!isset($methods[$scope])) {
      throw new IllegalArgumentException('Scope '.$filter->getScope().' not supported');
    }

    if (false === ($res= $methods[$scope]($this->handle, $base, $filter, $attributes, $attrsOnly, $sizeLimit, $timeLimit, $deref))) {
      throw $this->error('Search failed');
    }

    if ($sort) {
      $entries= new SortedLDAPEntries($this->handle, $res, $sort);
    } else {
      $entries= new LDAPEntries($this->handle, $res);
    }

    return $entries;
  }

  /**
   * Closes the connection
   *
   * @return void
   */
  public function close() {
    if ($this->handle) {
      ldap_unbind($this->handle);
      $this->handle= null;
    }
  }

  /** Ensures stream is closed. */
  public function __destruct() { $this->close(); }
}