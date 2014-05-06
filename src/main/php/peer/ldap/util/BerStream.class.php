<?php namespace peer\ldap\util;

class BerStream extends \lang\Object {
  const EOC = 0;
  const BOOLEAN = 1;
  const INTEGER = 2;
  const BITSTRING = 3;
  const OCTETSTRING = 4;
  const NULL = 5;
  const OID = 6;
  const OBJECTDESCRIPTOR = 7;
  const EXTERNAL = 8;
  const REAL = 9;
  const ENUMERATION = 10;
  const PDV = 11;
  const UTF8STRING = 12;
  const RELATIVEOID = 13;
  const SEQUENCE = 16;
  const SET = 17;
  const NUMERICSTRING = 18;
  const PRINTABLESTRING = 19;
  const T61STRING = 20;
  const VIDEOTEXSTRING = 21;
  const IA5STRING = 22;
  const UTCTIME = 23;
  const GENERALIZEDTIME = 24;
  const GRAPHICSTRING = 25;
  const VISIBLESTRING = 26;
  const GENERALSTRING = 28;
  const UNIVERSALSTRING = 29;
  const CHARACTERSTRING = 30;
  const BMPSTRING = 31;
  const CONSTRUCTOR = 32;
  const CONTEXT = 128;

  const SEQ_CTOR = 48;   // (SEQUENCE | CONSTRUCTOR)

  protected $seq= [''];

  public function __construct(\io\streams\InputStream $in, \io\streams\OutputStream $out) {
    $this->in= $in;
    $this->out= $out;
  }

  public function startSequence($tag= self::SEQ_CTOR) {
    array_unshift($this->seq, '');
    $this->writeByte($tag);
  }

  protected function encodeLength($l) {
    if ($l <= 0x7f) {
      return pack('C', $l);
    } else if ($l <= 0xff) {
      return "\x81".pack('C', $l);
    } else if ($l <= 0xffff) {
      return "\x82".pack('CC', $l >> 8, $l);
    } else if ($l <= 0xffffff) {
      return "\x83".pack('CCC', $l << 16, $l >> 8, $l);
    } else {
      throw new \lang\IllegalStateException('Length too long: '.$l);
    }
  }

  public function write($raw) {
    $this->seq[0].= $raw;
  }

  public function writeByte($b) {
    $this->seq[0].= pack('C', $b);
  }

  public function writeLength($l) {
    $this->seq[0].= $this->encodeLength($l);
  }

  public function writeInt($i, $tag= self::INTEGER) {
    if ($i < -0xffffff || $i >= 0xffffff) {
      $len= 4;
    } else if ($i < -0xffff || $i >= 0xffff) {
      $len= 3;
    } else if ($i < -0xff || $i >= 0xff) {
      $len= 2;
    } else {
      $len= 1;
    }
    $this->seq[0].= pack('CC', $tag, $len).substr(pack('N', $i), -$len);
  }

  public function writeNull() {
    $this->seq[0].= pack('C', self::NULL)."\x00";
  }

  public function writeBoolean($b, $tag= self::BOOLEAN) {
    $this->seq[0].= pack('C', $tag)."\x01".($b ? "\xff" : "\x00");
  }

  public function writeString($s, $tag= self::OCTETSTRING) {
    $length= $this->encodeLength(strlen($s));
    $this->seq[0].= pack('C', $tag).$length.$s;
  }

  public function writeEnumeration($e, $tag= self::ENUMERATION) {
    $this->writeInt($e, $tag);
  }

  public function endSequence() {
    $length= $this->encodeLength(strlen($this->seq[0]) - 1);
    $seq= array_shift($this->seq);
    $this->seq[0].= $seq{0}.$length.substr($seq, 1);
  }

  private function chars($bytes, $start, $offset) {
    $s= '';
    for ($j= $start; $j < min($offset, strlen($bytes)); $j++) {
      $c= $bytes{$j};
      $s.= ($c < "\x20" || $c > "\x7F" ? '.' : $c);
    }
    return $s;
  }

  private function dump($bytes, $message= null) {
    $n= strlen($bytes);
    $next= ' ';
    if (null === $message) {
      $s= '';
      $p= 74;
    } else {
      $s= $message.' ';
      $p= 74 - strlen($message) - 1;
    }
    $s.= str_pad('== ('.$n." bytes) ==\n", $p, '=', STR_PAD_LEFT);
    $o= 0;
    while ($o < $n) {
      $s.= sprintf('%04x: ', $o);
      for ($i= 0; $i < 16; $i++) {  
        if ($i + $o >= $n) {
          $s.= 7 === $i ? '    ' :  '   ';
        } else {
          $s.= sprintf('%02x %s', ord($bytes{$i + $o}), 7 === $i ? ' ' : '');
        }
      }
      $o+= $i;
      $s.= '|'.str_pad($this->chars($bytes, $o - 16, $o), 16, ' ', STR_PAD_RIGHT)."|\n";
    }
    return $s;
  }

  public function flush() {
    \util\cmd\Console::writeLine($this->dump($this->seq[0], '>>>'));
    $this->out->write($this->seq[0]);
  }

  public function readSequence($tag= self::SEQ_CTOR) {
    $head= unpack('Ctag/Cl0/a3rest', $this->in->read(5));
    if ($head['tag'] !== $tag) {
      throw new \lang\IllegalStateException(sprintf('Expected %0x, have %0x', $tag, $head['tag']));
    }

    if ($head['l0'] <= 0x7f) {
      $length= $head['l0'];
      $s= $head['rest'];
    } else if (0x81 === $head['l0']) {
      $l= unpack('C', $head['rest']);
      $length= $l[1];
      $s= substr($head['rest'], -2);
    } else if (0x82 === $head['l0']) {
      $l= unpack('C2', $head['rest']);
      $length= $l[1] * 0x100 + $l[2];
      $s= substr($head['rest'], -1);
    } else if (0x83 === $head['l0']) {
      $l= unpack('C3', $head['rest']);
      $length= $l[1] * 0x10000 + $l[2] * 0x100 + $l[3];
      $s= '';
    } else {
      throw new \lang\IllegalStateException('Length too long: '.$head['l0']);
    }

    while (strlen($s) < $length) {
      $s.= $this->in->read($length - strlen($s));
    }
    return $s;
  }

  public function read() {
    $seq= $this->readSequence();
    \util\cmd\Console::writeLine($this->dump($seq, '<<<'));
    return new \lang\types\Bytes($seq);
  }
}