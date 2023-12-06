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

## Contributing

Pull requests are welcome.

Thanks to [everyone who has contributed](https://github.com/smartbooster/symfony-docker/contributors) already.

---

*This project is supported by [SmartBooster](https://www.smartbooster.io)*
