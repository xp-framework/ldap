LDAP protocol support for the XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

## 6.1.1 / 2015-08-24

* Fixed `LDAPClient::read()` which was broken after refactoring
  (@thekid)

## 6.1.0 / 2015-08-23

* **Heads up**: Changed LDAPSearchResult's getFirstEntry(), getEntry() &
  getNextEntry() methods to return `NULL` instead of `FALSE` when EOF is
  reached. While technically this is a BC break, it does not break the
  iteration advertised in the README file. See xp-framework/ldap#4.
  (@thekid)
* Implemented xp-framework/ldap#3: LDAP entries iteration via `foreach`
  (@thekid)

## 6.0.2 / 2015-02-12

* Changed dependency to use XP ~6.0 (instead of dev-master) - @thekid

## 6.0.1 / 2015-01-16

* Merged PR #1: Add class constants for SCOPE (@kiesel)

## 6.0.0 / 2015-01-10

* Removed superfluous encoding and decoding, XP encoding is utf-8 as is
  the encoding for all LDAP calls.
  (@thekid)
* Heads up: Converted classes to PHP 5.3 namespaces - (@thekid)