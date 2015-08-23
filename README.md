LDAP support for the XP Framework
========================================================================

[![Build Status on TravisCI](https://secure.travis-ci.org/xp-framework/ldap.svg)](http://travis-ci.org/xp-framework/ldap)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Required PHP 5.4+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-5_4plus.png)](http://php.net/)
[![Supports PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.png)](http://php.net/)
[![Supports HHVM 3.4+](https://raw.githubusercontent.com/xp-framework/web/master/static/hhvm-3_4plus.png)](http://hhvm.com/)
[![Latest Stable Version](https://poser.pugx.org/xp-framework/ldap/version.png)](https://packagist.org/packages/xp-framework/ldap)

The peer.ldap package implements LDAP (Lighweight Directory Access Protocol) access.

Example (LDAP search)
---------------------

```php
use peer\ldap\LDAPClient;
use util\cmd\Console;

$l= new LDAPClient('ldap.openldap.org');
$l->setOption(LDAP_OPT_PROTOCOL_VERSION, 3);
$l->connect();
$l->bind();

$res= $l->search(
  'ou=People,dc=OpenLDAP,dc=Org', 
  '(objectClass=*)'
);
  
Console::writeLinef('===> %d entries found', $res->numEntries());
while ($entry= $res->getNextEntry()) {
  Console::writeLine('---> ', $entry->toString());
}

$l->close();
```

Example (Modifying an entry)
----------------------------

```php
use peer\ldap\LDAPClient;
use peer\ldap\LDAPEntry;

$l= new LDAPClient('ldap.example.com');
$l->setOption(LDAP_OPT_PROTOCOL_VERSION, 3);
$l->connect();
$l->bind('uid=admin,o=roles,dc=planet-xp,dc=net', 'password');

with ($entry= $l->read(new LDAPEntry('uid=1549,o=people,dc=planet-xp,dc=net'))); {
  $entry->setAttribute('firstname', 'Timm');

  $l->modify($entry);
}

$l->close();
```

Example (Adding an entry)
-------------------------

```php
use peer\ldap\LDAPClient;
use peer\ldap\LDAPEntry;

$l= new LDAPClient('ldap.example.com');
$l->setOption(LDAP_OPT_PROTOCOL_VERSION, 3);
$l->connect();
$l->bind('uid=admin,o=roles,dc=planet-xp,dc=net', 'password');

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
  '(&(objectClass=%c)(|(username=%s)(uid=%d)))',
  'xpPerson',
  'friebe'
  1549
));
```

When using the "%s" token, the value passed is escaped according to 
rules in LDAP query syntax. The %c token copies as-is, and %d handles
the argument as numeric value.
