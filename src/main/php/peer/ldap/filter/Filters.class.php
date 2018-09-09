<?php namespace peer\ldap\filter;

use lang\FormatException;

/**
 * Parses LDAP filters
 *
 * @test  xp://peer.ldap.unittest.filter.FiltersTest
 */
class Filters {

  /**
   * Returns all braced expressions belonging together
   *
   * @param  string $input
   * @return iterable
   */
  private function all($input) {
    $o= $b= 0;
    $l= strlen($input);

    while ($o < $l) {

      // (&(objectClass=person)(cn~=Test))(cn=Test)
      // ^                               ^
      $s= $o;
      do {
        $o+= strcspn($input, '()', $o);
        if ('(' === $input{$o}) $b++; else if (')' === $input{$o}) $b--;
      } while ($o++ < $l && $b > 0);

      yield $this->parse(substr($input, $s, $o));
    }
  }

  /**
   * Parses a string
   *
   * @param  string $input
   * @param  peer.ldap.filter.Filter
   */
  public function parse($input) {
    if ('(' === $input{0} && ')' === $input{strlen($input) - 1}) {
      return $this->parse(substr($input, 1, -1));
    } else if ('&' === $input{0}) {
      return new AndFilter(...$this->all(substr($input, 1)));
    } else if ('|' === $input{0}) {
      return new OrFilter(...$this->all(substr($input, 1)));
    } else if ('!' === $input{0}) {
      return new NotFilter($this->parse(substr($input, 1)));
    } else if (preg_match('/^([a-zA-Z0-9:;_.-]+[a-zA-Z0-9;_.-]+)([~><:]?=)(.+)$/', $input, $matches)) {
      switch ($matches[2]) {
        case '=':
          if ('*' === $matches[3]) return new PresenceFilter($matches[1]);

          $s= preg_split('/(?<!\\\)\*/', $matches[3]);
          if (1 === sizeof($s)) {
            return new EqualityFilter($matches[1], $matches[3]);
          } else {
            $initial= array_shift($s);
            $final= array_pop($s);
            return new SubstringFilter($matches[1], '' === $initial ? null : $initial, $s, '' === $final ? null : $final);
          }

        case '~=':
          return new ApproximateFilter($matches[1], $matches[3]);

        case '>=':
          return new GreaterThanEqualsFilter($matches[1], $matches[3]);

        case '<=':
          return new LessThanEqualsFilter($matches[1], $matches[3]);

        case ':=':
          list($attribute, $rule)= explode(':', $matches[1], 2);
          return new ExtensibleFilter($attribute, $rule, $matches[3]);

        default:
          throw new FormatException('Invalid filter `'.$input.'`: Unrecognized operator `'.$matches[2].'`');
      }
    }

    throw new FormatException('Invalid filter `'.$input.'`: Expected `(`, `&`, `|`, `!` or criteria');
  }
}