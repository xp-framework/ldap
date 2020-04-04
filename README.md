LDAP support for the XP Framework
========================================================================

[![Build Status on TravisCI](https://secure.travis-ci.org/xp-framework/ldap.svg)](http://travis-ci.org/xp-framework/ldap)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Required PHP 5.6+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-5_6plus.png)](http://php.net/)
[![Supports PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.png)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-framework/ldap/version.png)](https://packagist.org/packages/xp-framework/ldap)

The peer.ldap package implements LDAP (Lighweight Directory Access Protocol) access.

Example (LDAP search)
---------------------

```php
use peer\ldap\LDAPConnection;
use util\cmd\Console;

$l= new LDAPConnection('ldap://ldap.example.com');
$l->connect();

$search= $l->search(
  'ou=People,dc=OpenLDAP,dc=Org', 
  '(objectClass=*)'
);
  
Console::writeLinef('===> %d entries found', $search->numEntries());
foreach ($search as $result) {
  Console::writeLine('---> ', $result->toString());
}

$l->close();
```

Example (Modifying an entry)
----------------------------

```php
use peer\ldap\LDAPConnection;
use peer\ldap\LDAPEntry;

$l= new LDAPConnection('ldap://uid=admin,o=roles,dc=planet-xp,dc=net:password@ldap.example.com');
$l->connect();

with ($entry= $l->read(new LDAPEntry('uid=1549,o=people,dc=planet-xp,dc=net'))); {
  $entry->setAttribute('firstname', 'Timm');

  $l->modify($entry);
}

$l->close();
```

Example (Adding an entry)
-------------------------

```php
use peer\ldap\LDAPConnection;
use peer\ldap\LDAPEntry;

$l= new LDAPConnection('ldap://uid=admin,o=roles,dc=planet-xp,dc=net:password@ldap.example.com');
$l->connect();

with ($entry= new LDAPEntry('uid=1549,o=people,dc=planet-xp,dc=net')); {
  $entry->setAttribute('uid', 1549);
  $entry->setAttribute('firstname', 'Timm');
  $entry->setAttribute('lastname', 'Friebe');
  $entry->setAttribute('objectClass', 'xpPerson');

  $l->add($entry);
}

$l->close();
```

Dynamically creating LDAP queries
---------------------------------
If the LDAP queries need to be constructed dynamically the LDAPQuery
class provides a printf-style syntax to do so:

```php
use peer\ldap\LDAPQuery;

$res= $ldap->searchBy(new LDAPQuery(
  'o=people,dc=planet-xp,dc=net',
  '(&(objectClass=%c)(|(username=%s)(uid=%d)))',
  'xpPerson',
  'friebe'
  1549
));
```

When using the "%s" token, the value passed is escaped according to 
rules in LDAP query syntax. The %c token copies as-is, and %d handles
the argument as numeric value.
