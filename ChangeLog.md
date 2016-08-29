LDAP protocol support for the XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

## 7.4.1 / 2016-08-29

* Made compatible with xp-framework/network v8.0.0 - @thekid

## 7.4.0 / 2016-08-28

* Added forward compatibility with XP 8.0.0 - @thekid

## 7.3.0 / 2016-07-03

* Merged PR #8: Detect disconnects - @thekid
* Extended `connect()` to optionally accept DN and password with which
  to bind for instead of the credentials passed in the connection DSN.
  (@thekid)
* Rewrote search() and searchBy() internally without the need for
  `call_user_func_array()`.
  (@thekid)

## 7.2.0 / 2016-05-02

* Merged PR #6: Add accessor for underlying connection DSN - @thekid

## 7.1.1 / 2016-04-22

* Fixed LDAP options not being correctly set in `connect()` - @thekid

## 7.1.0 / 2016-04-19

* Implemented xp-framework/rfc#147: new LDAP API. See pull request #5
  (@thekid)

## 7.0.0 / 2016-02-22

* **Adopted semantic versioning. See xp-framework/rfc#300** - @thekid 
* Added version compatibility with XP 7 - @thekid
* **Heads up**: Changed minimum XP version to XP 6.5.0, and with it the
  minimum PHP version to PHP 5.5.
  (@thekid)

## 6.2.0 / 2016-01-23

* **Heads up**: Change minimum XP version required to *6.3.0*.
  (@thekid)
* Fix code to use `nameof()` instead of the deprecated `getClassName()`
  method from lang.Generic. See xp-framework/core#120
  (@thekid)

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
