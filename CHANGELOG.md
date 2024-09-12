CHANGELOG for 1.x
===================
## v1.0.5 - (2024-02-06)
### Added
- Allow `doctrine/orm` ^v3.0

### Changed
- Moved `phpmetrics/phpmetrics` as suggested vendor package becasue it's not on the qualimetry calls command and also because it requires use to do a
downgrade of `nikic/php-parser` from 5.0 to 4.18 on default symfony-docker install which is needed by the `symfony/maker-bundle`

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
