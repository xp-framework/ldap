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
  protected $read= [0];

  protected $in, $out;

  /**
   * Constructor
   *
   * @param  io.streams.InputStream $in
   * @param  io.streams.OutputStream $out
   */
  public function __construct(InputStream $in, OutputStream $out) {

    // Debug
    /*
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
    */

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
    $w= $this->out->write($this->write[0]);
    $this->write= [''];
    return $w;
  }

  public function read($l) {
    $t= debug_backtrace();
    $chunk= $this->in->read($l);
    $this->read[0]-= strlen($chunk);
    // fprintf(STDOUT, "%s   READ %d bytes from %s, remain %d\n", str_repeat('   ', sizeof($this->read)), $l, $t[1]['function'], $this->read[0]);
    return $chunk;
  }

  public function readTag($expected) {
    $head= unpack('Ctag', $this->in->read(1));
    $test= (array)$expected;
    if (!in_array($head['tag'], $test)) {
      throw new \lang\IllegalStateException(sprintf(
        'Expected any of [%s], have 0x%02x',
        implode(', ', array_map(function($t) { return sprintf('0x%02x', $t); }, $test)),
        $head['tag']
      ));
    }

    return $head['tag'];
  }

  protected function decodeLength() {
    $head= unpack('Cl0', $this->in->read(1));
    if ($head['l0'] <= 0x7f) {
      $length= $head['l0'];
      $this->read[0]-= 2;
    } else if (0x81 === $head['l0']) {
      $l= unpack('C', $this->in->read(1));
      $length= $l[1];
      $this->read[0]-= 3;
    } else if (0x82 === $head['l0']) {
      $l= unpack('C2', $this->in->read(2));
      $length= $l[1] * 0x100 + $l[2];
      $this->read[0]-= 4;
    } else if (0x83 === $head['l0']) {
      $l= unpack('C3', $this->in->read(3));
      $length= $l[1] * 0x10000 + $l[2] * 0x100 + $l[3];
      $this->read[0]-= 5;
    } else {
      throw new \lang\IllegalStateException('Length too long: '.$head['l0']);
    }
    return $length;
  }

  /**
   * Reads an integer
   *
   * @return int
   */
  public function readInt() {
    $this->readTag(self::INTEGER);
    return unpack('N', str_pad($this->read($this->decodeLength()), 4, "\0", STR_PAD_LEFT))[1];
  }

  /**
   * Reads an enumeration
   *
   * @return int
   */
  public function readEnumeration() {
    $this->readTag(self::ENUMERATION);
    return unpack('N', str_pad($this->read($this->decodeLength()), 4, "\0", STR_PAD_LEFT))[1];
  }

  /**
   * Reads a string
   *
   * @return string
   */
  public function readString() {
    $this->readTag(self::OCTETSTRING);
    return $this->read($this->decodeLength());
  }

  /**
   * Reads a boolean
   *
   * @return bool
   */
  public function readBoolean() {
    $this->readTag(self::BOOLEAN);
    return $this->read($this->decodeLength()) ? true : false;
  }

  /**
   * Read sequence
   *
   * @param  var $tag expected either a tag or an array of tags
   * @return int The found tag
   */
  public function readSequence($tag= self::SEQ_CTOR) {
    $tag= $this->readTag($tag);
    $len= $this->decodeLength();
    $this->read[0]-= $len;
    array_unshift($this->read, $len);
    // fprintf(STDOUT, "%s`- BEGIN SEQ %d bytes\n", str_repeat('   ', sizeof($this->read)), $this->read[0]);
    return $tag;
  }

  public function remaining() {
    return $this->read[0];
  }

  public function available() {
    return $this->in->available();
  }

  /**
   * Finish reading a sequence
   */
  public function finishSequence() {
    // fprintf(STDOUT, "%s   END SEQ remain: %d bytes\n", str_repeat('   ', sizeof($this->read)), $this->read[0]);
    $shift= array_shift($this->read);
    $this->read[0]-= $shift;
  }

  /**
   * Closes I/O
   *
   * @return void
   */
  public function close() {
    $this->in->close();
    $this->out->close();
  }
}