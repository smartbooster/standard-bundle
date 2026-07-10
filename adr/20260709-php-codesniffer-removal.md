# Removal of `squizlabs/php_codesniffer` in favor of PHP-CS-Fixer

**Goal**: remove the `squizlabs/php_codesniffer` dependency (and its `phpcs.xml` ruleset), which duplicated `friendsofphp/php-cs-fixer`, already in 
place since v1.2.0 for the Symfony coding standards.

**Context**: `phpcs.xml` combined two responsibilities:
- the security sniffs from `pheromone/phpcs-security-audit`, replaced by Psalm taint analysis (see [security migration ADR](20260709-php-security-audit-migration.md));
- the `PSR12` ruleset for checkstyle / coding standard, which duplicated `php-cs-fixer`, already used in parallel for the Symfony coding standards.

Once the security part was removed, all that was left was this checkstyle duplication between the two tools.

**Selected solution**: keep only `php-cs-fixer` as the single checkstyle / coding standard tool and remove `squizlabs/php_codesniffer`.

> Note: although `squizlabs/php_codesniffer` has been picked up again and is actively maintained by [PHPCSStandards/PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/) (after the original squizlabs repository was abandoned), 
> we still keep `php-cs-fixer` as it covers a better implementation of the [PER Coding Style 3.0](https://www.php-fig.org/per/coding-style/) (the extension that succeeds the former PSR-12), 
> as well as Symfony-specific best-practice rules (the `@Symfony` ruleset) that PHP_CodeSniffer doesn't provide natively.

## Impact

- `composer.json`: removed the `squizlabs/php_codesniffer` dependency
- `phpcs.xml` removed, no file is copied by the recipe for PHP_CodeSniffer anymore
- `qualimetry.mk`: the `checkstyle`/`cs` targets now point directly to `php-cs-fixer` (formerly `symfony-checkstyle`/`sfcs`); the `code-beautifier`/`cbf` (`phpcbf`) target is removed
