LDAP protocol support for the XP Framework
========================================================================

The peer.ldap package implements LDAP (Lighweight Directory Access 
Protocol) access.

Example (LDAP search)
---------------------

```
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
