# Qualimetry Assurance

Here is the complete list of libraries we use to ensure the code quality of our projects.

Per-tool documentation:

- [PHPStan](phpstan.md) — static analysis (types) + dangerous-call banning.
- [Psalm](psalm.md) — taint analysis (security: injection / XSS).

## PHPStan

We use [PHPStan](https://phpstan.org/) as our main static code analysis (type checking), at the highest level, with the Symfony and Doctrine extensions. 

It also bans dangerous function calls (`eval`, `exec`, `phpinfo`, …). 
See **[phpstan.md](phpstan.md)** for the details, extensions and the level-migration workflow.

## Security

Security detection is handled by **[Psalm taint analysis](psalm.md)** (it replaces the abandoned `pheromone/phpcs-security-audit`). 
It tracks untrusted input to dangerous sinks and covers, among others:

- [SQL injection listed in the OWASP Symfony cheat sheet](https://cheatsheetseries.owasp.org/cheatsheets/Symfony_Cheat_Sheet.html#sql-injection) (Doctrine ORM/DBAL),
- XSS (Twig templates and `Response`), plus command/LDAP/header/SSRF/file injection.

Two complements handle what taint analysis structurally cannot:

- **presence of dangerous functions** (weak crypto, `phpinfo`, `eval`…) → PHPStan `disallowedFunctionCalls`, see [phpstan.md](phpstan.md);
- **dependency CVEs** → `composer audit` (see below).

## Symfony Checkstyle

We use the vendor [PHP-CS-Fixer/PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) highlighted by Symfony on its [Coding Standards page](https://symfony.com/doc/current/contributing/code/standards.html#making-your-code-follow-the-coding-standards) to ensure that our checkstyle complies 
with the official standard put forward by the framework.

**Ease migration to full checkstyle compliance**

Just like PHPStan, we advice running the command `make symfony-checkstyle` and report each unhandled rule as `false` in `.php-cs-fixer.dist` as the following :

```php
// ...

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'phpdoc_tag_type' => false,
        'phpdoc_indent' => false,
        'single_quote' => false,
        // ...
    ])
    ->setFinder($finder)
;
```

Then one by one remove the rule set to false and commit for each rule to move through the checkstyle compliance step by step, one commit at a time for clear history.

## Lint

In addition, we also use the native PHP lint on our src files as well as the Symfony lints to validate our Twig and YAML files, as well as the
definition of services in the container.

## Composer validate

Finally, we use the `composer validate` command to check the formatting of the `composer.json` file, and the `audit` command to check, with each 
push to our CI, that there are no active CVEs on our dependencies.
