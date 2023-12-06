# SMARTBOOSTER - Symfony Standard Bundle

Bundle grouping all dev vendor that we use for testing and coding with the SMARTBOOSTER standards.

## Installation

Add the following to your project's composer.json file:

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
The standard-bundle has its own onboarded recipe which will copy the missing files from the repository.

## Updating the recipe manifest

If the manifest.json has to change (for any configurator or copied file content), you must update his "ref" value.  
Use the following PHP script to generate a new random "ref" value:

```php
echo bin2hex(random_bytes(20));
```

## Contributing

Pull requests are welcome.

Thanks to [everyone who has contributed](https://github.com/smartbooster/symfony-docker/contributors) already.

---

*This project is supported by [SmartBooster](https://www.smartbooster.io)*
