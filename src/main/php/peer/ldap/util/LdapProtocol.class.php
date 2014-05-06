<?php namespace peer\ldap\util;

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

  const REP_BIND = 0x61;
  const REP_SEARCH_ENTRY = 0x64;
  const REP_SEARCH_REF = 0x73;
  const REP_SEARCH = 0x65;
  const REP_MODIFY = 0x67;
  const REP_ADD = 0x69;
  const REP_DELETE = 0x6b;
  const REP_MODRDN = 0x6d;
  const REP_COMPARE = 0x6f;
  const REP_EXTENSION = 0x78;

  const SCOPE_BASE_OBJECT = 0;
  const SCOPE_ONE_LEVEL   = 1;
  const SCOPE_SUBTREE     = 2;

  const NEVER_DEREF_ALIASES = 0;
  const DEREF_IN_SEARCHING = 1;
  const DEREF_BASE_OBJECT = 2;
  const DEREF_ALWAYS = 3;

  protected $messageId= 0;

  public function __construct(\peer\Socket $sock) {
    $this->stream= new BerStream(
      $sock->getInputStream(),
      $sock->getOutputStream()
    );
  }

  protected function nextMessageId() {
    if (++$this->messageId >= 0x7fffffff) {
      $this->messageId= 1;
    }
    return $this->messageId;
  }

  public function send($message) {
    $this->stream->startSequence();
    $this->stream->writeInt($this->nextMessageId());
    call_user_func($message['write'], $this->stream);
    $this->stream->endSequence();

    $this->stream->flush();

    return call_user_func($message['read'], $this->stream);
  }

  public function search() {
    static $handlers= null;

    if (!$handlers) $handlers= [
      self::REP_SEARCH_ENTRY => function($stream) {
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
      self::REP_SEARCH => function($stream) {
        $stream->read($stream->remaining());    // XXX FIXME
        return '<EOR>';
      }
    ];

    return $this->send([
      'write' => function($stream) {
        $stream->startSequence(self::REQ_SEARCH);
        $stream->writeString('o=example');
        $stream->writeEnumeration(0);
        $stream->writeEnumeration(0);
        $stream->writeInt(0);
        $stream->writeInt(0);
        $stream->writeBoolean(false);

        $stream->startSequence(0x87);
        $stream->write('objectClass');
        $stream->endSequence();

        $stream->startSequence();
        $stream->endSequence();
        $stream->endSequence();
      },
      'read'  => function($stream) use($handlers) {
        $result= [];
        do {
          $stream->readSequence();
          $messageId= $stream->readInt();

          $tag= $stream->readSequence([self::REP_SEARCH_ENTRY, self::REP_SEARCH]);
          $result[]= call_user_func($handlers[$tag], $stream);
          $stream->finishSequence();

          $stream->finishSequence();
        } while (self::REP_SEARCH_ENTRY === $tag);

        return $result;
      }
    ]);
  }

  public function __destruct() {
    $this->stream->close();
  }
}