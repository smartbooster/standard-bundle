CHANGELOG for 1.x
===================
## v1.2.2 - (2026-01-05)
### Changed
- `qualimetry.mk` : composer audit --abandoned flag set to report instead of fail (the fail behavior must be handled by the scheduled audit job)

## v1.2.1 - (2026-01-05)
### Fixed
- Fix recipes .php-cs-fixer.dist.php missing .php extension

## v1.2.0 - (2026-01-05)
### Added
- `composer.json` add more qualimetry requirements :
  - pheromone/phpcs-security-audit and dealerdirect/phpcodesniffer-composer-installer for security sniff
  - friendsofphp/php-cs-fixer to enhance complience with the symfony coding standards
  - yamadashy/phpstan-friendly-formatter and spaze/phpstan-disallowed-calls to improve phpstan output and disallowed-calls 
- `qualimetry.mk` add symfony-checkstyle command based on php-cs-fixer + [qa.md](docs/qa.md) docs
- `.php-cs-fixer.dist` default PHPCSFixer config
- `phpcs.xml` add Security sniff from pheromone/phpcs-security-audit
- `phpstan.neon` add disallowed-calls rule + friendly-formatter and cache config

## Removed
- `sebastian/phpcpd` requirement as it is flag has abandoned and no longer maintained.

## v1.1.2 - (2025-07-30)
### Changed
- `phpunit.xml` update config on SYMFONY_PHPUNIT_VERSION 12.2 for PHP 8.4 support (@mathieu-ducrot)
- `phpstan.neon` update config for PHP 8.4 support (@mathieu-ducrot)

## v1.1.1 - (2025-07-29)
### Added
- `composer.json` add dama/doctrine-test-bundle:^8.0 to fix deprecations on PHP 8.4 support

## v1.1.0 - (2025-07-28)
### Added
- `composer.json` add liip/test-fixtures-bundle:^3.0 for PHP 8.4 support
- `composer.json` add phpstan/phpstan-doctrine & symfony :2.0 for PHP 8.4 support

## v1.0.5 - (2025-07-28)
### Added
- `phpcs.xml` rule to ignore line limit on comments
### Changed
- `test.mk` remove --colors options to control them from the phpunit.xml

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
