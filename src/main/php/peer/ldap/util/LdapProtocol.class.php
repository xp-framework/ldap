<?php namespace peer\ldap\util;

use peer\ldap\LDAPException;

class LdapProtocol extends \lang\Object {
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
    self::RES_SEARCH_ENTRY => true
  ];

  protected $messageId= 0;

  /**
   * Creates a new protocol instance communicating on the given socket
   *
   * @param  peer.Socket $sock
   */
  public function __construct(\peer\Socket $sock) {
    $this->stream= new BerStream(
      $sock->getInputStream(),
      $sock->getOutputStream()
    );
  }

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
   * @param  string $password
   * @return void
   * @throws peer.ldap.LDAPException
   */
  public function bind($user, $password) {
    $this->send([
      'req'   => self::REQ_BIND,
      'write' => function($stream) use($user, $password) {
        $stream->writeInt($version= 3);
        $stream->writeString($user);
        $stream->writeString($password, BerStream::CONTEXT);
      },
      'res'   => self::RES_BIND,
      'read'  => [self::RES_BIND => function($stream) {
        $status= $stream->readEnumeration();
        $matchedDN= $stream->readString();
        $error= $stream->readString();

        // TODO: Referalls
        $stream->read($stream->remaining());
        if (self::STATUS_OK !== $status) {
          throw new LDAPException($error ?: 'Bind error', $status);
        }
      }]
    ]);
  }

  /**
   * Search
   *
   * @param  string $base
   * @return var
   */
  public function search($base) {
    return $this->send([
      'req'   => self::REQ_SEARCH,
      'write' => function($stream) use($base) {
        $stream->writeString($base);
        $stream->writeEnumeration(self::SCOPE_ONE_LEVEL);
        $stream->writeEnumeration(self::NEVER_DEREF_ALIASES);
        $stream->writeInt(0);
        $stream->writeInt(0);
        $stream->writeBoolean(false);

        $stream->startSequence(0x87);
        $stream->write('objectClass');
        $stream->endSequence();

        $stream->startSequence();
        $stream->endSequence();
      },
      'res'   => [self::RES_SEARCH_ENTRY, self::RES_SEARCH],
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
            } while ($stream->remaining());
            $stream->finishSequence();

            $stream->finishSequence();
          } while ($stream->remaining());
          $stream->finishSequence();
          return ['name' => $name, 'attr' => $attributes];
        },
        self::RES_SEARCH => function($stream) {
          $status= $stream->readEnumeration();
          $matchedDN= $stream->readString();
          $error= $stream->readString();

          if (self::STATUS_OK !== $status) {
            throw new LDAPException($error ?: 'Search failed', $status);
          }
        }
      ]
    ]);
  }

  /**
   * Closes the connection
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