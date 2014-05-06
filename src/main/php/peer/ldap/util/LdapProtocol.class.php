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

  public function send($request) {
    $this->stream->startSequence();
    $this->stream->writeInt($this->nextMessageId());

    $this->stream->startSequence($request['op']);
    call_user_func($request['write'], $this->stream);
    $this->stream->endSequence();

    $this->stream->endSequence();
    $this->stream->flush();

    return $this->stream->read();
  }
}