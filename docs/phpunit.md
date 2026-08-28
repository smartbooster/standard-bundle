# PHPUnit — Testing

We use [PHPUnit](https://phpunit.de/) through the `symfony/phpunit-bridge` (`vendor/bin/simple-phpunit`) to test our projects.

The standard bundle provides:

- a default [`phpunit.xml.dist`](../phpunit.xml.dist) copied into the project by the Flex recipe (see below),
- the `Smart\StandardBundle\AbstractWebTestCase` base class (client + entity manager + Alice fixtures loading + DAMA rollback),
- the `Smart\StandardBundle\Validator\Constraints\AbstractValidatorTest` base class to unit-test constraint validators,
- the `make/test.mk` commands (`make phpunit`, `make coverage`, ...).

## Process

### Smoke test your URLs

We follow the [Symfony best practice "Smoke test your urls"](https://symfony.com/doc/current/best_practices.html#smoke-test-your-urls): a single
functional test per domain (Admin, App, ...) that asserts every admin/front URL responds with a 200. 

It is the cheapest way to get a wide safety net on a project: it boots the
kernel, the container, the routing, the security and renders every page, so most wiring regressions (service misconfiguration, broken template,
missing route) are caught with very little test code to maintain.

Each project has a `tests/Admin/AdminAvailabilityTest.php` extending `AbstractWebTestCase` which:

1. loads the minimal fixtures it needs (an admin user, common parameters),
2. logs in the admin user,
3. requests every static URL and asserts the response is OK.

> In the example below, `logIn()` is a small project-level helper (defined in the project's abstract admin test case) that loads the given
> fixture files then logs the admin user in.

```php
public function testPageIsOk(): void
{
    $this->logIn([
        $this->getMinimalFixturesDir() . '/vendor.yaml',
        $this->getFixturesDir() . '/common/smart_parameter.yaml',
    ]);

    $urls = [
        // Sécurité / Accueil / Profile
        '/login',
        '/dashboard',
        '/profile',
        // Commandes
        '/facture/list',
        '/devis/list',
        // ...
    ];

    foreach ($urls as $url) {
        $this->client->request('GET', $url);

        $this->assertUrlResponseOkWithMessage($url);
    }
}

private function assertUrlResponseOkWithMessage(string $url): void
{
    $this->assertTrue($this->client->getResponse()->isOk(), 'Failed url : ' . $url);
}
```

We deliberately do **not** use a `#[DataProvider]` here, for performance: each dataset would re-run `setUp()`, the fixture loading and the login
for every single URL, while the URLs all run on the exact same data. Logging in once and looping over the array of URLs inside a single test
method loads the fixtures once for the whole list.

The trade-off of the loop is that PHPUnit stops at the first failing URL and its default failure message doesn't tell you which URL broke — hence
the `assertUrlResponseOkWithMessage()` helper, which always passes the failed URL in the assertion message.

For dynamic URLs (show/edit pages needing an entity id), load a dedicated fixture, fetch the entity from its repository and inject its id:

```php
public function testDynamicOrganizationPageIsOk(): void
{
    $this->logIn([
        $this->getMinimalFixturesDir() . '/vendor.yaml',
        $this->getFixturesDir() . '/Admin/AdminAvailabilityTest/organization.yaml',
    ]);

    $urls = [
        '/app/user-organization/%d/show',
        '/app/user-organization/%d/edit',
        // ...
    ];

    $entity = $this->entityManager->getRepository(Organization::class)->findOneBy(['name' => 'ORGA_TEST']);

    foreach ($urls as $url) {
        $this->client->request('GET', sprintf($url, $entity->getId()));

        $this->assertUrlResponseOkWithMessage($url);
    }
}
```

**Rule : Every new page added to the project must be added to this test.**

### Test Class management

When [organizing your test suite in PHPUnit](https://docs.phpunit.de/en/12.5/writing-tests-for-phpunit.html), the industry standard relies on a clean **1:1 mapping** between your production classes and your test classes. Rather than creating isolated test classes for each individual method (like `MethodATest`), you should write a single test class matching your target service (e.g., `ServiceATest` for `ServiceA`).

Inside this class, the best practice is to split your assertions so that **each test method targets exactly one specific scenario** rather than wrapping all validation logic into a single, giant method. This guarantees that if one assertion fails, PHPUnit doesn't halt early and obscure other potential bugs.

```text
project/
├── src/                                 
│   └── Manager/
│       └── CloudSimulationManager.php   # Contains create() and rollback() methods
│
└── tests/                               
    ├── Manager/
    │   └── CloudSimulationManagerTest.php   # Tests both create() and rollback()
```

### Test fixtures management

Fixtures are written as [nelmio/alice](https://github.com/nelmio/alice) YAML files and loaded with
[theofidry/alice-data-fixtures](https://github.com/theofidry/AliceDataFixtures), which is required by our core-bundle. 

Alice lets us describe entities declaratively (with references, ranges and faker data), which keeps fixtures readable and cheap to write compared to PHP fixture classes.

Fixtures live under `tests/fixtures/` and **mirror the test tree**: each test class owning specific data gets its own folder named after it, while
truly shared data goes to a `common/` folder.

```text
tests/
├── Admin/
│   └── AdminAvailabilityTest.php
├── Manager/
│   └── CloudSimulationManagerTest.php
└── fixtures/
    ├── common/                          # data shared by many tests (admin user, parameters, ...)
    │   └── user_administrator.yaml
    ├── Admin/
    │   └── AdminAvailabilityTest/       # data owned by that test class only
    │       └── organization.yaml
    └── Manager/
        └── CloudSimulationManager/
            └── create_simulation.yaml
```

Why this separation: a test must be able to evolve its data without silently breaking other tests. Sharing one big fixture set across the suite
couples every test together; scoping fixtures per test class keeps each test self-explanatory (you see exactly the data it runs on) and makes
failures local.

Load them in tests through the `AbstractWebTestCase` helpers:

```php
$this->loadFixtureFiles([
    $this->getFixturesDir() . '/common/user_administrator.yaml',
    $this->getFixturesDir() . '/Manager/CloudSimulationManager/create_simulation.yaml',
]);
```

### Database reset between tests

Tests must be independent: a test must not pass or fail depending on data left over by a previous one, otherwise failures become order-dependent
and impossible to reproduce in isolation. This requires resetting the database between each test — but actually dropping/re-creating or truncating
the schema for every test is far too slow.

We combine two bundles (both required by the standard bundle):

- [liip/test-fixtures-bundle](https://github.com/liip/LiipTestFixturesBundle) — provides the `DatabaseTool` used by `loadFixtureFiles()` to load
  the Alice fixtures,
- [dama/doctrine-test-bundle](https://github.com/dmaicher/doctrine-test-bundle) — wraps **each test in a database transaction that is rolled back
  at the end of the test**. The database is reset instantly whatever the amount of data written, which is what keeps a fixtures-heavy functional
  suite fast.

The rollback is enabled by the PHPUnit extension registered in `phpunit.xml.dist`:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension" />
</extensions>
```

**Debugging a failing database test.** 

Because of the rollback, when a test fails you cannot simply open the database afterwards to inspect the data : everything the test wrote has been rolled back. 
Follow the [dama/doctrine-test-bundle debugging documentation](https://github.com/dmaicher/doctrine-test-bundle#debugging): either inspect the data with a
debugger breakpoint *while the test (and its transaction) is still running*, or temporarily disable the rollback behavior (comment out the
extension in `phpunit.xml.dist`) to let the data persist after the failure — don't forget to restore it afterwards.

### Default `phpunit.xml.dist` configuration

The recipe copies a default [`phpunit.xml.dist`](../phpunit.xml.dist) into the project. The notable choices:

```xml
<phpunit backupGlobals="false"
         colors="true"
         bootstrap="tests/bootstrap.php"
         displayDetailsOnTestsThatTriggerDeprecations="false"
         displayDetailsOnTestsThatTriggerErrors="true"
         displayDetailsOnTestsThatTriggerNotices="true"
         displayDetailsOnTestsThatTriggerWarnings="true"
>
```

- `displayDetailsOnTestsThatTriggerDeprecations="false"` — vendor deprecations would flood the output on every run; deprecations are handled in
  dedicated upgrade sessions, not on every test run. Errors, notices and warnings on the other hand always come from our code and are displayed.
- `<server name="APP_ENV" value="test" force="true" />` — guarantee the kernel always boots in `test` whatever the shell environment.
- `<server name="SYMFONY_PHPUNIT_VERSION" value="12.2" />` — pin the PHPUnit version installed by the bridge so every developer and the CI run the
  exact same PHPUnit.
- `<env name="MAILER_DSN" value="null://null"/>` — tests must never send real emails.
- The `<source>` block includes `src` but excludes `src/DataFixtures` and `src/Kernel.php`: coverage should measure application code, not
  dev-fixtures or framework glue.
- The DAMA extension (see previous section) enables the per-test transaction rollback.

### Mocks: when to use them?

Our default is to **not mock**: tests extend `AbstractWebTestCase`, use the real container services and real database (with fixtures + rollback).
Testing the real wiring is precisely what gives our functional tests their value — a mocked repository or manager would only prove the test agrees
with itself.

We mock in exactly two situations:

**1. External services (HTTP APIs, payment providers, ...).** A test must never call the outside world: it would be slow, flaky and would hit
real accounts. We isolate each external API behind a dedicated class (e.g. `StripeAPI`), then replace **only that class** in the container:

```php
private MockObject&StripeAPI $stripeAPIMock;

public function setUp(): void
{
    parent::setUp();

    $this->stripeAPIMock = $this->createMock(StripeAPI::class);
    static::getContainer()->set(StripeAPI::class, $this->stripeAPIMock);

    // the real manager is fetched from the container and receives the mocked API
    $this->manager = $this->getContainer()->get(StripePaymentManager::class);
}

public function testGetCustomerForCustomerId(): void
{
    $this->stripeAPIMock
        ->expects($this->exactly(2))
        ->method('retrieveCustomer')
        ->willReturnCallback(/* return a Stripe Customer built from a JSON fixture, or throw */);
    // ...
}
```

Real API payloads are stored as JSON files next to the test fixtures and replayed through the mock, so the test runs against realistic data.

**2. Framework classes that cannot be instantiated in a unit test.** For example `AbstractValidatorTest` mocks the Symfony
`ExecutionContext`/`ConstraintViolationBuilder` so a `ConstraintValidator` can be unit-tested (asserting which violation message is built) without
booting the whole validator component.

Everything else (entities, repositories, managers — code we own) is exercised for real through fixtures. Over-mocking couples tests to the
implementation and lets integration bugs through.

### `#[DataProvider]` usage

Use the PHPUnit `#[DataProvider]` attribute to run the same test logic over several inputs, with **named datasets** so failures are readable:

```php
#[DataProvider('failProvider')]
public function testValidationFail(string $value): void { /* ... */ }

public static function failProvider(): array
{
    return [
        'manque 4 chiffres' => ["123A"],
        'lettre non majuscule' => ["1234a"],
    ];
}
```

**Watch the context in which you use them.** 

Each dataset runs the test method again, including `setUp()` and any `loadFixtureFiles()` call inside the method. 

A large provider (big array of cases) combined with a test that loads a lot of fixtures multiplies the fixture-loading cost by
the number of datasets and can noticeably slow the suite down.

In that situation either keep the loaded fixtures minimal for that test, or group several cheap assertions inside a single test method instead of 
one dataset each — this is exactly why our URL smoke tests (see above) use a plain loop over the URLs instead of a provider.

> Data providers shine for pure/unit logic (utils, validators, entity methods) where each run costs nothing.

### Splitting test suites

The recipe ships a single test suite covering `tests/`, and the matching unified `make/test.mk` commands (`make phpunit`, `make coverage`, ...).

**Keep it that way by default**: one suite, one command, no decision to make.

Splitting into several suites is to be reserved for large projects with a lot of tests, where the split allows for example running part of the
suite automatically in the CI while keeping the slowest part manual. Example from one of our large projects:

```xml
<testsuites>
    <testsuite name="all">
        <directory>tests</directory>
    </testsuite>
    <!-- Runs no test, but allows installing phpunit which is required by the qa -->
    <testsuite name="none">
    </testsuite>
    <testsuite name="core"> <!-- tests of the application foundations -->
        <directory>tests/Entity</directory>
        <directory>tests/Repository</directory>
        <directory>tests/Utils</directory>
        <directory>tests/Admin</directory>
    </testsuite>
    <testsuite name="domain"> <!-- tests of the business rules and application logic -->
        <directory>tests/Manager</directory>
        <directory>tests/Validator</directory>
        <!-- ... -->
    </testsuite>
    <testsuite name="interface"> <!-- tests of the interfaces with the outside -->
        <directory>tests/API</directory>
        <directory>tests/Controller</directory>
        <directory>tests/Command</directory>
        <!-- ... -->
    </testsuite>
</testsuites>
```

The project then overrides `make/test.mk` with a `SUITE` variable so every command can target a suite, instead of the unified standard-bundle
command:

```makefile
SUITE=all

phpunit: ## Launch all tests
	vendor/bin/simple-phpunit --testsuite $(SUITE)

coverage: ## Launch all tests with code coverage html and text for CI
	XDEBUG_MODE=coverage vendor/bin/simple-phpunit --coverage-text --coverage-html build/phpunit \
		--coverage-cobertura build/phpunit-cobertura.xml --log-junit build/phpunit-report.xml --testsuite $(SUITE)

.PHONY: phpunit-core coverage-core
phpunit-core coverage-core: SUITE=core
phpunit-core: phpunit
coverage-core: coverage
# same pattern for domain / interface / none
```

This multiplies the Makefile targets and the CI configuration to maintain — which is exactly why we don't do it on standard-size projects.

## PHPStan integration

Our PHPStan setup includes the [phpstan-phpunit](https://github.com/phpstan/phpstan-phpunit) extension (see [phpstan.md](phpstan.md)). It reinforces our test writing by:

- checking that the proper typed assert methods are used (`assertTrue`, `assertCount`, `assertInstanceOf`, ...),
- inferring types from assertions and from `createMock()` (mock intersection types like `MockObject&StripeAPI`),

which avoids false positives being reported by PHPStan on test files. 

## Usage (`make/test.mk`)

| Command              | Purpose                                            |
|----------------------|----------------------------------------------------|
| `make phpunit`       | run the whole test suite                           |
| `make coverage-text` | run the tests with text code coverage              |
| `make coverage-html` | run the tests with html code coverage              |
| `make coverage`      | run the tests with all coverage reports for the CI |
