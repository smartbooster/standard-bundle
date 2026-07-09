# SMARTBOOSTER - Symfony Standard Bundle

Bundle grouping all dev vendor that we use for testing and coding with the SMARTBOOSTER standards.

## What's inside !

- All the development vendors that we use daily to check our code quality: 
  - static analysis with [PHPStan](docs/phpstan.md), 
  - security taint analysis with [Psalm](docs/psalm.md), 
  - checkstyle and coding standards with [PHP-CS-Fixer](https://github.com/php-cs-fixer/php-cs-fixer) 
  - native linters (php, twig, yaml, sf container)
  - tests bundle configuration for [PHPUnit](https://phpunit.de/index.html)
- Makefiles with [QA](docs/qa.md) and testing commands to easily run them.

## Installation

```bash
# This bundle is not added to the symfony/recipes-contrib repo, you need to manually add the recipe endpoint on your composer.json
composer config --json extra.symfony.endpoint '["https://api.github.com/repos/smartbooster/standard-bundle/contents/recipes.json", "flex://defaults"]'
# Then require the bundle into your dev requirements:
composer require --dev smartbooster/standard-bundle
```

When being prompted "Do you want to execute this recipe?" from the other bundle (Phpstan, Dama, ...) answer No and press enter.  
The standard-bundle has its own recipe which will cover the bundles mentioned above (check the dotted manifest to see which files).

> Don't forget to do a recipe reset to have the latest changes from the recipe copied files (see next section).

## Updating the standard-bundle

If you require an upper version or update the bundle version on your project, run the following command to execute the recipe with the latest changes

```bash
composer update smartbooster/standard-bundle
composer recipes:install smartbooster/standard-bundle --reset --force
```

Also check the CHANGELOG.md in case some vendor depedancie has been removed (the recipe reset force command doesn't handle removing past added recipe files).

## Working on the bundle

### Architecture Decision Records (ADR)

Every structuring decision made on the standard-bundle (adding, replacing or removing a tool/rule) must be tracked in an ADR, stored in the [`adr/`](adr) folder.

Name the file `{date}-{topic}.md` (e.g. [`20260709-php-codesniffer-removal.md`](adr/20260709-php-codesniffer-removal.md)) and structure its content with the following sections:

- **Context**: the current situation and why it needs to change.
- **Goal**: what we are trying to achieve.
- **Selected solution**: the solution we chose (and, if relevant, the alternatives we discarded).
- **Impact**: the concrete consequences on the bundle (files added/changed/removed).

This practice is MANDATORY to help us recall why we did such changes in the past and must be taken seriously.

### Updating the recipe dotted manifest

If the dotted manifest has to change (for any configurator), or for a new major version of the bundle, 
you must create a new smartbooster.standard-bundle.{major}.{minor}.json with a distinct "ref" value.  
Use the following PHP script to generate a new random "ref" value:

```php
echo bin2hex(random_bytes(20));
```

Then add it to the recipes.json list.

## Contributing

Pull requests are welcome.

Thanks to [everyone who has contributed](https://github.com/smartbooster/standard-bundle/contributors) already.

---

*This project is supported by [SmartBooster](https://www.smartbooster.io)*
