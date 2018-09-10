<?php namespace peer\ldap\util;

use peer\SSLSocket;
use peer\Socket;
use peer\ldap\LDAPException;
use peer\ldap\LDAPSearchResult;
use peer\ldap\filter\Filters;

class LdapProtocol {
  const REQ_BIND = 0x60;
  const REQ_UNBIND = 0x42;
  const REQ_SEARCH = 0x63;
  const REQ_MODIFY = 0x66;
  const REQ_ADD = 0x68;
  const REQ_DELETE = 0x4a;
  const REQ_MODRDN = 0x6c;
  const REQ_COMPARE = 0x6e;
  const REQ_ABANDON = 0x50;
  const REQ_EXTENSION = 0x77;

  const RES_BIND = 0x61;
  const RES_SEARCH_ENTRY = 0x64;
  const RES_SEARCH_REF = 0x73;
  const RES_SEARCH = 0x65;
  const RES_MODIFY = 0x67;
  const RES_ADD = 0x69;
  const RES_DELETE = 0x6b;
  const RES_MODRDN = 0x6d;
  const RES_COMPARE = 0x6f;
  const RES_EXTENSION = 0x78;

  const SCOPE_BASE_OBJECT = 0;
  const SCOPE_ONE_LEVEL   = 1;
  const SCOPE_SUBTREE     = 2;

  const NEVER_DEREF_ALIASES = 0;
  const DEREF_IN_SEARCHING = 1;
  const DEREF_BASE_OBJECT = 2;
  const DEREF_ALWAYS = 3;

  const STATUS_OK = 0;

  protected static $continue= [
    self::RES_SEARCH_ENTRY => true,
    self::RES_EXTENSION => true,
  ];

  protected $messageId= 0;

  public function __construct($scheme, $host, $port, $params) {
    if ('ldaps' === $scheme) {
      $this->sock= new SSLSocket($host, $port);  
    } else {
      $this->sock= new Socket($host, $port);
    }
    $this->filters= new Filters();
  }

  /** @return string */
  public function connection() { return $this->sock->toString(); }

  /** @return int */
  public function id() { return (int)$this->sock->getHandle(); }

  /** @return bool */
  public function connected() { return $this->sock->isConnected(); }

  /**
   * Calculates and returns next message id, starting with 1.
   *
   * @return  int
   */
  protected function nextMessageId() {
    if (++$this->messageId >= 0x7fffffff) {
      $this->messageId= 1;
    }
    return $this->messageId;
  }

  /**
   * Handle response
   *
   * @param  int $status
   * @param  sting $error
   */
  protected function handleResponse($status, $error= null) {
    if (self::STATUS_OK !== $status) {
      throw new LDAPException($error, $status);
    }
  }

  /**
   * Send message, return result
   *
   * @param  var $message
   * @return var
   */
  public function send($message) {
    with ($this->stream->startSequence()); {
      $this->stream->writeInt($this->nextMessageId());
      $this->stream->startSequence($message['req']);
      call_user_func($message['write'], $this->stream);
      $this->stream->endSequence();
      $this->stream->endSequence();
    }
    $this->stream->flush();

    $result= [];
    do {
      with ($this->stream->readSequence()); {
        $messageId= $this->stream->readInt();
        $tag= $this->stream->readSequence($message['res']);
        try {
          $result[]= call_user_func($message['read'][$tag], $this->stream);
        } catch (\lang\XPException $e) {
          $this->stream->finishSequence();
          $this->stream->finishSequence();
          $this->stream->read($this->stream->remaining());
          throw $e;
        }
        $this->stream->finishSequence();
        $this->stream->finishSequence();
      }
    } while (isset(self::$continue[$tag]));
    return $result;
  }

  /**
   * Bind
   *
   * @param  string $user
   * @param  util.Secret $password
   * @return void
   * @throws peer.ldap.LDAPException
   */
  public function connect($user, $password) {
    $this->sock->connect();
    $this->stream= new BerStream($this->sock->in(), $this->sock->out());

    $this->send([
      'req'   => self::REQ_BIND,
      'write' => function($stream) use($user, $password) {
        $stream->writeInt($version= 3);
        $stream->writeString($user);
        $stream->writeString($password->reveal(), BerStream::CONTEXT);
      },
      'res'   => self::RES_BIND,
      'read'  => [self::RES_BIND => function($stream) {
        $status= $stream->readEnumeration();
        $matchedDN= $stream->readString();
        $error= $stream->readString();

        // TODO: Referalls

        $this->handleResponse($status, $error ?: 'Bind error');
      }]
    ]);
  }

  /**
   * Search
   *
   * @param  string $base
   * @return var
   */
  public function search($scope, $base, $filter, $attributes= [], $attrsOnly= 0, $sizeLimit= 0, $timeLimit= 0, $sort= [], $deref= LDAP_DEREF_NEVER) {
    $r= $this->send([
      'req'   => self::REQ_SEARCH,
      'write' => function($stream) use($base, $filter, $attributes) {
        $stream->writeString($base);
        $stream->writeEnumeration(self::SCOPE_ONE_LEVEL);
        $stream->writeEnumeration(self::NEVER_DEREF_ALIASES);
        $stream->writeInt(0);
        $stream->writeInt(0);
        $stream->writeBoolean(false);

        $stream->writeFilter($this->filters->parse($filter));

        $stream->startSequence();
        foreach ($attributes as $attribute) {
          $stream->writeString($attribute);
        }
        $stream->endSequence();
      },
      'res'   => [self::RES_SEARCH_ENTRY, self::RES_SEARCH, self::RES_EXTENSION],
      'read'  => [
        self::RES_SEARCH_ENTRY => function($stream) {
          $name= $stream->readString();
          $stream->readSequence();
          $attributes= [];
          do {
            $stream->readSequence();
            $attr= $stream->readString();

            $stream->readSequence(0x31);
            $attributes[$attr]= [];
            do {
              $attributes[$attr][]= $stream->readString();
            } while ($stream->remaining() > 0);
            $stream->finishSequence();

            $stream->finishSequence();
          } while ($stream->remaining() > 0);
          $stream->finishSequence();
          return ['dn' => $name, 'attr' => $attributes];
        },
        self::RES_SEARCH => function($stream) {
          $status= $stream->readEnumeration();
          $matchedDN= $stream->readString();
          $error= $stream->readString();

          $this->handleResponse($status, $error ?: 'Search failed');
        },
        self::RES_EXTENSION => function($stream) {
          $status= $stream->readEnumeration();
          $matchedDN= $stream->readString();
          $error= $stream->readString();

          $name= 0x8a === $stream->peek() ? $stream->readString(0x8a) : null;
          // $value= 0x8b === $stream->peek() ? $stream->readString(0x8b) : null;

          $this->handleResponse($status, ($error ?: 'Search failed').($name ? ': '.$name : ''));
        }
      ]
    ]);
    return new Entries($r);
  }

  /**
   * Closes the connection
   *
   * @return void
   */
  public function close() {
    $this->stream->close();
  }

  /**
   * Destructor. Ensures stream is closed.
   */
  public function __destruct() {
    $this->close();
  }
}