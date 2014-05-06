<?php namespace peer\ldap\util;

use io\streams\BufferedInputStream;
use io\streams\InputStream;
use io\streams\OutputStream;

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

  protected $write= [''];

  /**
   * Constructor
   *
   * @param  io.streams.InputStream $in
   * @param  io.streams.OutputStream $out
   */
  public function __construct(InputStream $in, OutputStream $out) {

    // Debug
    $in= newinstance('io.streams.InputStream', [$in], [
      'backing'     => null,
      '__construct' => function($backing) { $this->backing= $backing; },
      'read'        => function($length= 8192) {
        $chunk= $this->backing->read($length);
        if (null !== $chunk) {
          \util\cmd\Console::writeLine(BerStream::dump($chunk, '<<<'));
        }
        return $chunk;
      },
      'available'   => function() { return $this->backing->available(); },
      'close'       => function() { $this->backing->close(); }
    ]);
    $out= newinstance('io.streams.OutputStream', [$out], [
      'backing'     => null,
      '__construct' => function($backing) { $this->backing= $backing; },
      'write'       => function($chunk) {
        \util\cmd\Console::writeLine(BerStream::dump($chunk, '>>>'));
        return $this->backing->write($chunk);
      },
      'flush'       => function() { return $this->backing->flush(); },
      'close'       => function() { $this->backing->close(); }
    ]);

    $this->in= $in instanceof BufferedInputStream ? $in : new BufferedInputStream($in);
    $this->out= $out;
  }

  private static function chars($bytes, $start, $offset) {
    $s= '';
    for ($j= $start; $j < min($offset, strlen($bytes)); $j++) {
      $c= $bytes{$j};
      $s.= ($c < "\x20" || $c > "\x7F" ? '.' : $c);
    }
    return $s;
  }

  public static function dump($bytes, $message= null) {
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
      $s.= '|'.str_pad(self::chars($bytes, $o - 16, $o), 16, ' ', STR_PAD_RIGHT)."|\n";
    }
    return $s;
  }

  /**
   * Starts writing a sequence
   *
   * @param  int $tag
   */
  public function startSequence($tag= self::SEQ_CTOR) {
    array_unshift($this->write, '');
    $this->writeByte($tag);
  }

  /**
   * Encode length
   *
   * @param  int $l
   * @return string encoded bytes
   * @throws lang.IllegalStateException if length is > 0xffffff
   */
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

  /**
   * Write raw bytes to current sequence
   *
   * @param  string $raw
   */
  public function write($raw) {
    $this->write[0].= $raw;
  }

  /**
   * Write single byte to current sequence
   *
   * @param  int $b
   */
  public function writeByte($b) {
    $this->write[0].= pack('C', $b);
  }

  /**
   * Write length to current sequence
   *
   * @param  int $l
   */
  public function writeLength($l) {
    $this->write[0].= $this->encodeLength($l);
  }

  /**
   * Write integer to current sequence
   *
   * @param  int $i
   * @param  int $tag
   */
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
    $this->write[0].= pack('CC', $tag, $len).substr(pack('N', $i), -$len);
  }

  /**
   * Write NULL value to current sequence
   *
   * @param  string $raw
   */
  public function writeNull() {
    $this->write[0].= pack('C', self::NULL)."\x00";
  }

  /**
   * Write boolean to current sequence
   *
   * @param  bool $b
   * @param  int $tag
   */
  public function writeBoolean($b, $tag= self::BOOLEAN) {
    $this->write[0].= pack('C', $tag)."\x01".($b ? "\xff" : "\x00");
  }

  /**
   * Write string to current sequence
   *
   * @param  string $s
   * @param  int $tag
   */
  public function writeString($s, $tag= self::OCTETSTRING) {
    $length= $this->encodeLength(strlen($s));
    $this->write[0].= pack('C', $tag).$length.$s;
  }

  /**
   * Write enumeration to current sequence
   *
   * @param  int $e
   * @param  int $tag
   */
  public function writeEnumeration($e, $tag= self::ENUMERATION) {
    $this->writeInt($e, $tag);
  }

  /**
   * Ends current sequences
   */
  public function endSequence() {
    $length= $this->encodeLength(strlen($this->write[0]) - 1);
    $seq= array_shift($this->write);
    $this->write[0].= $seq{0}.$length.substr($seq, 1);
  }

  /**
   * Flushes all sequences to output
   *
   * @return int number of bytes written
   */
  public function flush() {
    return $this->out->write($this->write[0]);
  }

  /**
   * Read sequence
   *
   * @param  string $tag expected tag
   * @return var
   */
  public function readSequence($tag= self::SEQ_CTOR) {
    $head= unpack('Ctag/Cl0/a3rest', $this->in->read(5));
    if ($head['tag'] !== $tag) {
      throw new \lang\IllegalStateException(sprintf('Expected %0x, have %0x', $tag, $head['tag']));
    }

    if ($head['l0'] <= 0x7f) {
      $length= $head['l0'];
      $this->in->pushBack(substr($head['rest']));
    } else if (0x81 === $head['l0']) {
      $l= unpack('C', $head['rest']);
      $length= $l[1];
      $this->in->pushBack(substr($head['rest'], 1));
    } else if (0x82 === $head['l0']) {
      $l= unpack('C2', $head['rest']);
      $length= $l[1] * 0x100 + $l[2];
      $this->in->pushBack(substr($head['rest'], 2));
    } else if (0x83 === $head['l0']) {
      $l= unpack('C3', $head['rest']);
      $length= $l[1] * 0x10000 + $l[2] * 0x100 + $l[3];
    } else {
      throw new \lang\IllegalStateException('Length too long: '.$head['l0']);
    }

    return ['tag' => $tag, 'length' => $length];
  }

  /**
   * Read response
   *
   * @return var
   */
  public function read() {
    $seq= $this->readSequence();
    return $seq;
  }
}