# Qualimetry Assurance

Here is the complete list of libraries we use to ensure the code quality of our projects.

## PHPStan

We use [PHPStan](https://github.com/phpstan/phpstan) as our main static code analysis.

**Extensions**

- [PHPStan-Symfony](https://github.com/phpstan/phpstan-symfony) extension to ensure that our controllers are correctly defined.
- [PHPStan-Doctrine](https://github.com/phpstan/phpstan-doctrine) extension to ensure that our Doctrine entities are correctly defined.
- [phpstan-friendly-formatter](https://github.com/yamadashy/phpstan-friendly-formatter) extension to improve the output of error messages and Identifier Summary.
- [phpstan-disallowed-calls](https://github.com/spaze/phpstan-disallowed-calls) to detect usage of disallowed calls (exec, system, ...).

**Ease migration form lower level to PHPStan max level**

All project must be set on the **highest level** (which is 10 at the moment).

If your current project is on an lower level, increase it one level at a time and add the currently unsupported rules to the phpstan.neon 
configuration as shown below:

```yaml
parameters:
    ignoreErrors:
        # ... Current ignore error by identifier ...
        # Level 8 rules to handle
        - identifier: method.nonObject  # You can add the number of time the error occurent as a comment at the end to better prioritize
        - identifier: argument.type
        - identifier: ...
        # Level 9 rules to handle
        - identifier: offsetAccess.nonOffsetAccessible
        - identifier: binaryOp.invalid
        - identifier: ...
        # Level 10 rules to handle
        - identifier: postInc.type
        - identifier: cast.double
        - identifier: ...
```

This will then allow you to **debug each error type with one separate commit to move through the levels step by step for clear history**.

## Security Sniff

We use the vendor [squizlabs/php_codesniffer](https://github.com/squizlabs/PHP_CodeSniffer) in combination with [pheromone/phpcs-security-audit](https://github.com/FloeDesignTechnologies/phpcs-security-audit) 
to address security error detections on our projects as well as non fixable errors for PSR12 ruleset. 

This includes :

- [SQL Injection detection mentioned in the OWASP checklist for Symfony](https://cheatsheetseries.owasp.org/cheatsheets/Symfony_Cheat_Sheet.html#sql-injection)
- Detecting methods that should not be used (Phpinfo, eval, ...)

The full security list can be found [here](https://github.com/FloeDesignTechnologies/phpcs-security-audit/tree/master/Security/Sniffs).

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
