# Netgen Remote Media & Ibexa CMS integration

This bundle provides an integration of [Netgen Remote Media](https://github.com/netgen/NetgenRemoteMediaBundle/tree/3.0) for Ibexa CMS.

## Installation instructions

[INSTALL.md](INSTALL.md)

## Remote media in the RichText field

[RICHTEXT.md](RICHTEXT.md)

## Migration from Netgen Remote Media 2

[MIGRATION.md](MIGRATION.md)

## Changelog and upgrade instructions

* [CHANGELOG.md](CHANGELOG.md)
* [UPGRADE.md](UPGRADE.md)

## Running tests

Running tests requires that you have complete vendors installed, so run
`composer install` before running the tests.

You can run unit tests by calling `composer test` from the repo root:

```
$ composer test
```

JavaScript tests use the Node.js test runner (Node.js 22.12+) and need no dependencies:

```
$ node --test 'tests/js/**/*.test.mjs'
```
