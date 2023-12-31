# SMARTBOOSTER - Symfony Standard Bundle

Bundle grouping all dev vendor that we use for testing and coding with the SMARTBOOSTER standards.

## What's inside !

- All the development vendors that we used daily to check our code quality. 
- Makefiles with QA and testing commands to easily run them.

## Installation

```bash
# As this bundle is still in development and not added to the symfony/recipes-contrib repo, you need to manually add the recipe endpoint on your composer.json
composer config --json extra.symfony.endpoint '["https://api.github.com/repos/smartbooster/standard-bundle/contents/recipes.json", "flex://defaults"]'
# Then require the bundle into your dev requirements:
composer require --dev smartbooster/standard-bundle
```

When being prompted "Do you want to execute this recipe?" from the other bundle (Phpstan, Codesnif, Dama, ...) answer No and press enter.  
The standard-bundle has its own recipe which will cover the bundles mentioned above (check the dotted manifest to see which files).

> Don't forget to do a recipe reset to have the latest changes from the recipe copied files (see next section).

## Reinstall the recipe to the latest changes

If you require an upper version or update the bundle version on your project, run the following command to execute the recipe with the latest changes

```bash
composer recipes:install smartbooster/standard-bundle --reset --force
```

## Working on the bundle

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
