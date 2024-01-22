CHANGELOG for 1.x
===================
## v1.0.4 - (2024-01-22)

### Added

- `composer.json` : Allow using dama/doctrine-test-bundle version ^6.7 to reduce the need of updating doctrine/dbal

## v1.0.3 - (2024-01-22)

### Added

- `AbstractWebTestCase::loadFixtureFiles` : Add shortcut function to load fixture files

## v1.0.2 - (2024-01-08)

### Added

- `AbstractWebTestCase::assertArrayContainsValues` : Test if an array contains exactly all values of an array of values, no matter there order

## v1.0.1 - (2024-01-04)

### Fix

- Fix passing format 1G to phpstan memory-limit option
