# SMARTBOOSTER - Symfony Standard Bundle

Bundle grouping all dev vendor that we use for testing and coding with the SMARTBOOSTER standards.

## What's inside !

- All the development vendors that we used daily to check our code quality. 
- Makefiles with QA and testing commands to easily run them.

## Installation

> As this bundle is still in development and not added to the symfony/recipes-contrib repo, you need to manually add the recipe endpoint on your composer.json

To do so, add the following to your project's composer.json file:

```json
{
    "extra": {
        "symfony": {
            "endpoint": [
                "https://api.github.com/repos/smartbooster/standard-bundle/contents/recipes.json", 
                "flex://defaults"
            ]
        }
    }
}
```

Then require the bundle into your dev requirements:

```bash
composer require --dev smartbooster/standard-bundle
```

When being prompted "Do you want to execute this recipe?" from the other bundle (Phpstan, Codesnif, Dama, ...) answer No and press enter.  
The standard-bundle has its own onboarded recipe which will copy the missing files from the repository (check the dotted manifest to see which files).

## Reinstall the recipe to the latest changes

If you require an upper version or update the bundle version on your project, run the following command to execute the recipe with the latest changes

```bash
composer recipes:install smartbooster/standard-bundle --force
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

Thanks to [everyone who has contributed](https://github.com/smartbooster/symfony-docker/contributors) already.

---

*This project is supported by [SmartBooster](https://www.smartbooster.io)*
