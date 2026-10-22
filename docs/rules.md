# Project rules

The rules every SMARTBOOSTER repository is checked against by Smart App.

Each rule is evaluated on the files repository and ends up :
- **compliant**,
- **non compliant**, 
- **info** (a figure to follow, nothing to fix : the debt of a baseline, a build tool to migrate),
- **not applicable** (the repository has no such file)
- **unknown** (a file is unreadable, or the production version is not declared).

Rules are grouped by category, the subject of their code (`phpstan.level` → `phpstan`). The PHP rules are not applicable without
`composer.json`, the JavaScript rules without `package.json`.

The rules that ask for a tool to be run by the CI (`psalm.taint`, `php.cs-fixer`, `symfony.lints`, `php.composer-check`, `phpunit.ci`,
`eslint.config`, `js.prettier`, `js.type-check`) read the commands of `.gitlab-ci.yml`, with the `make` targets expanded from the
`Makefile` and the files it includes, and the `package.json` scripts expanded for `npm run <script>` (`npm run-script`, `npm test`, `pnpm`
and `yarn` with or without `run`). Nothing is executed : only the text of the files is read, so a tool run through `ifeq`, a GitLab
`include:` of another repository, a `composer` script or an npm `pre`/`post` script is not seen. What is checked is that the CI runs the
tool, not that its last run is green. The reports that the CI publishes to GitLab (`phpunit.ci`, `phpunit.coverage`) are read on the same
jobs : a manual job does not count, except the `test` one.

The front tool configs (`eslint.config.*`, the Prettier config) are JavaScript modules most of the time : they are never executed either,
they are read by patterns with their comments excluded. A config built by code (a package name in a variable, a preset picked by a
condition) is not followed.

## PHP

### `php.composer-require`

**Check :** `require.php` in `composer.json` accepts the whole minor of `PHP_VERSION`, and only that one (`~8.4.0`, `8.4.*`).
A wider constraint (`>=8.1`, `^8.4`) is not compliant.

```jsonc
// composer.json, with PHP_VERSION=8.4
{
    "require": {
        "php": "~8.4.0"
    }
}
```

**Why :** Composer must only resolve dependencies for the PHP version that runs in production.

### `php.composer-platform`

**Check :** `config.platform.php` in `composer.json` is in the production minor (`8.4.0` for `PHP_VERSION=8.4`).

```jsonc
// composer.json, with PHP_VERSION=8.4
{
    "config": {
        "platform": {
            "php": "8.4.0"
        }
    }
}
```

**Why :** Composer resolves dependencies for the production version, not for the one of the machine that runs the install.

### `php.cs-fixer`

**Check :** `.php-cs-fixer.php` (or `.php-cs-fixer.dist.php`) declares `'@Symfony' => true`, and the CI checks the style (`php-cs-fixer check`,
or `php-cs-fixer fix --dry-run`) instead of fixing it silently.
Without any rule set to `false` the rule is compliant, otherwise it is **info** with the number of disabled rules.

```php
// .php-cs-fixer.dist.php
return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'yoda_style' => false, // disabled rule : counted, not a failure
    ]);
```

```makefile
# make/qualimetry.mk, called by the CI
checkstyle:
	vendor/bin/php-cs-fixer check -v
```

**Why :** One single code style avoids review debates and formatting diffs. Disabling a rule is how a legacy code migrates to the standard
rule by rule : it is a debt to follow, not a failure. The config file is never executed, its tokens are read (comments excluded), so an
option set to `false` inside the configuration of a rule is counted too.

See [Qualimetry Assurance](qa.md#symfony-checkstyle).

### `php.composer-check`

**Check :** The CI runs `composer validate` and `composer audit`.

```makefile
# make/qualimetry.mk, called by the CI
composer-validate:
	composer validate composer.json
	composer audit --abandoned=report
```

**Why :** An invalid `composer.json` or a dependency with a known vulnerability must stop the CI before production.

## Infrastructure

### `infra.mysql-version`

**Check :** `MYSQL_VERSION` in `.env` is a full version, patch included (`8.4.8`, not `8.4`). Not applicable when `composer.json`
has no Doctrine (`doctrine/orm`, `doctrine/dbal` or `doctrine/doctrine-bundle`).

```dotenv
# .env
MYSQL_VERSION=8.4.8
```

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        server_version: "%env(resolve:MYSQL_VERSION)%"
```

**Why :** `MYSQL_VERSION` is the MySQL version of the production server, and the Docker box aligns on it. Doctrine needs it as
`doctrine.dbal.server_version` to pick the right SQL platform without connecting to the database, and it expects the exact server
version, patch included.

## PHPStan

### `phpstan.level`

**Check :** The PHPStan config file declares `level: 10`. A level only given on the command line (Makefile, CI) does not count,
and `max` is not accepted.

```neon
# phpstan.neon
parameters:
    level: 10
```

**Why :** The highest level catches type errors before production. `max` moves with PHPStan versions: a level is tested before being
imposed, so we declare `10` explicitly.

See [PHPStan](phpstan.md) for the setup of a new project and the level migration workflow of a legacy one.

### `phpstan.baseline`

**Check :** The PHPStan baseline (the file the config includes under a `baseline` name, `phpstan-baseline.neon` by default) is empty.
A project without baseline is compliant. Otherwise the rule is **info**, with the total number of ignored errors : the sum of the `count`
of each entry, not the number of entries.

```neon
# phpstan-baseline.neon : compliant
parameters:
    ignoreErrors: []
```

**Why :** Each error ignored by the baseline is a typing debt. The rule is not a failure, it measures the debt so that its reduction
can be followed from one scan to the next.

See [PHPStan](phpstan.md#handle-phpstan-for-legacy-project-and-handle-level-update).

### `phpstan.extensions`

**Check :** The `includes` of the PHPStan config contain the extensions of the standard : `phpstan-doctrine`, `phpstan-phpunit` and
`phpstan-symfony` (their `extension.neon` **and** their `rules.neon`), and `phpstan-disallowed-calls` (its `extension.neon`).
`phpstan-symfony` also needs `symfony.containerXmlPath`, without which it resolves no service.

```neon
# phpstan.neon
includes:
    - vendor/phpstan/phpstan-doctrine/extension.neon
    - vendor/phpstan/phpstan-doctrine/rules.neon
    - vendor/phpstan/phpstan-phpunit/extension.neon
    - vendor/phpstan/phpstan-phpunit/rules.neon
    - vendor/phpstan/phpstan-symfony/extension.neon
    - vendor/phpstan/phpstan-symfony/rules.neon
    - vendor/spaze/phpstan-disallowed-calls/extension.neon

parameters:
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
```

**Why :** The standard bundle installs the extensions as dependencies without enabling them : being in `composer.lock` proves nothing,
the config is what activates them. Without them PHPStan understands neither Doctrine, PHPUnit nor the Symfony container.
Only the `includes` of the config itself are read, not those of the files it includes.

### `phpstan.disallowed-calls`

**Check :** `parameters.disallowedFunctionCalls` of the PHPStan config bans the reference list of the standard : `exec`, `eval`, `system`,
`passthru`, `shell_exec`, `phpinfo`, `popen`, `md5`, `sha1`, `assert`, `error_reporting`, `ini_set`. A project can ban more, not less.

```neon
# phpstan.neon
parameters:
    disallowedFunctionCalls:
        -
            function: 'exec()'
            message: 'for security reasons. Use Symfony Process component instead.'
        -
            function: 'eval()'
            message: 'for security reasons.'
        # ... the 12 functions of the list
```

**Why :** These calls open security holes (command execution, broken hashes) or hide errors at runtime. Whether a function is dangerous does not depend
on the data flow, so it is PHPStan that bans it, taint analysis being left to [Psalm](psalm.md). `function` is read with or without `()`,
alone or as a list, whatever its case.

See [PHPStan](phpstan.md#about-static-security-check-taint-analysis-and-responsability).

## Psalm

### `psalm.taint`

**Check :** `psalm.xml` has `runTaintAnalysis="true"`, declares `psalm-taint-stubs.php` in its `<stubs>` (the file must be there), and the CI
runs Psalm. With an empty taint baseline (`psalm-taint-baseline.xml`, or the one named by `errorBaseline`), or none, the rule is compliant.
Otherwise it is **info**, with the number of ignored occurrences.

```xml
<!-- psalm.xml -->
<psalm runTaintAnalysis="true" errorLevel="8" ...>
    <stubs>
        <file name="psalm-taint-stubs.php" />
    </stubs>
</psalm>
```

```makefile
# make/qualimetry.mk, called by the CI
psalm-ci:
	vendor/bin/psalm --use-baseline=psalm-taint-baseline.xml
```

**Why :** Taint analysis follows the data of the user to the SQL, the HTML and the shell : an injection is seen before production.
It needs both ends of every flow to be declared, hence the stubs for Doctrine. The baseline freezes the findings of a legacy code so that the CI
only fails on new ones : it is a debt to follow, not a failure.

See [Psalm](psalm.md).

## Symfony

### `symfony.lints`

**Check :** The CI runs the four lints : PHP (`php -l`, or `parallel-lint`), `lint:twig`, `lint:yaml` and `lint:container`.

```makefile
# make/qualimetry.mk, called by the CI
lint-twig:
	$(CONSOLE) lint:twig templates
lint-yaml:
	$(CONSOLE) lint:yaml config translations
lint-container:
	$(CONSOLE) lint:container
```

**Why :** A syntax or configuration error is seen by the CI, not in production.

## PHPUnit

### `phpunit.ci`

**Check :** A PHPUnit config is at the root (`phpunit.xml`, `phpunit.dist.xml` or `phpunit.xml.dist`), `tests/` holds test classes
(`*Test.php`), the CI runs PHPUnit (`simple-phpunit`, `phpunit`, `paratest`) and publishes the JUnit report of the tests.

```yaml
# .gitlab-ci.yml
test:
  artifacts:
    reports:
      junit: build/phpunit-report.xml
  script:
    - make coverage # vendor/bin/simple-phpunit ... --log-junit build/phpunit-report.xml
```

**Why :** Tests that do not run on every push protect nothing. With the JUnit report, GitLab shows the failed tests in the merge request.
Only the files are read : whether the last pipeline is green is not checked.

### `phpunit.coverage`

**Check :** The CI publishes the code coverage to GitLab : the rate read in the log of the job running PHPUnit (`coverage`, a regular
expression) and the Cobertura report (`artifacts:reports:coverage_report`). The job running PHPUnit is found by its commands, with the
`make` targets and `npm run` scripts expanded (`make coverage` → `vendor/bin/simple-phpunit …`).

```yaml
# .gitlab-ci.yml
test:
  coverage: /^\s*(?:\x1b\[[0-9;]*m)?\s*Lines:\s*(\d+(?:\.\d+)?)%/
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: build/phpunit-cobertura.xml
```

**Why :** The rate followed from one pipeline to the next, and the covered lines shown in the merge request diff, show what the tests leave
out. The Cobertura report of any job counts : the rule does not check that it is the job running PHPUnit.

**Value :** The rate of the job running PHPUnit on the pipeline of the analysed commit, read through the GitLab API. It is not the rate
of the pipeline : GitLab averages it over every job that reads a rate, so PHPUnit at 50 % and Vitest at 90 % give 70 %, which measures
nothing. No value when the job was not run (a manual `test` job), when the commit has no pipeline, when several jobs run PHPUnit and read
a rate, or when the job is split with `parallel` (one rate per part). The value never changes the status. Smart App also keeps the last
rate read on the repository, with its commit and date : a scan without a rate does not erase it.

### `phpunit.smoke-test`

**Check :** `tests/` holds a URL smoke test, found by its name : a `*AvailabilityTest.php` file (`tests/Admin/AdminAvailabilityTest.php`).

**Why :** A single test calling every page catches most wiring regressions for very little code : misconfigured service, broken template,
missing route. The file is not read : the rule does not check that every URL is in it.

See [Smoke test your URLs](phpunit.md#smoke-test-your-urls).

## JavaScript

### `js.package-manager`

**Check :** One single lock file next to `package.json`: `package-lock.json` (npm) or `pnpm-lock.yaml` (pnpm), never `yarn.lock`.
The `packageManager` field is mandatory and names the same manager as the lock (`npm@11.6.2`).

```jsonc
// package.json, next to a package-lock.json and no yarn.lock
{
    "packageManager": "npm@11.6.2"
}
```

**Why :** One manager and its lock install the same dependency versions everywhere; two managers sharing a repository do not.
`packageManager` makes the choice explicit and lets Corepack pin the version.

> `npm` is the main package manager that we decide to use because it is the only one that comes natively with Node: installing
> Node gives the matching npm, with nothing else to install or to keep aligned on the Docker image, the CI and the production host.
>
> Yarn and pnpm are not shipped with Node. They go through [Corepack](https://github.com/nodejs/corepack), a shim bundled with
> Node up to 24 but opt-in (`corepack enable`) and no longer distributed from Node 25, or through a global install that we would
> have to pin everywhere. This is why `packageManager` is mandatory: it states which manager and which exact version the project
> expects, and it is what Corepack reads to pin it.
>
> **Why Yarn is excluded.** We only ever used Yarn Classic (1.x), a line that is frozen upstream: it no longer evolves, and a
> modern Yarn is not native either (Corepack, another config model). It also has rough edges we ran into: it does not install
> peer dependencies by default (see the `vue-eslint-parser` note in [front code validation](front_code_validation.md)).
>
> **Why pnpm is accepted.** We use it to try this manager on our internal projects. We do not use it on Clever Cloud instances,
> because it is not provided by default on the PHP scalers, unlike npm.

### `js.node-engine`

**Check :** `engines.node` in `package.json` accepts the major of `NODE_VERSION` and refuses the others (`24.x`, `^24.11`).

```jsonc
// package.json, with NODE_VERSION=24.11.1
{
    "engines": {
        "node": "^24.11"
    }
}
```

**Why :** JavaScript dependencies must be installed for the Node version that runs in production.

### `js.engine-strict`

**Check :** `engine-strict=true` in `.npmrc`.

```ini
# .npmrc
engine-strict=true
```

**Why :** Without it `engines.node` is only a warning: npm installs anyway on a Node version the project refuses.

### `js.prettier`

**Check :** Only on a repository with Tailwind (`tailwindcss` in the `dependencies` or `devDependencies` of `package.json`), not applicable
otherwise. `prettier` is in the `devDependencies`, the Prettier config loads the Tailwind class sorter (`prettier-plugin-tailwindcss`), and
the CI checks the formatting (`prettier --check`, `-c`, or `--list-different`), directly or through a `package.json` script.

The config is the one Prettier reads : the `prettier` key of `package.json` first, then `.prettierrc`, `.prettierrc.{json,yml,yaml,json5}`,
`.prettierrc.*` or `prettier.config.*` (`js`, `ts`, `mjs`, `mts`, `cjs`, `cts`), then `.prettierrc.toml`. A `prettier` key naming a shared
config (`"@acme/prettier-config"`) is **unknown** : its plugins are not read.

```mjs
// prettier.config.mjs
export default {
  plugins: ["prettier-plugin-tailwindcss"],
  tailwindStylesheet: "./assets/styles/app.css",
}
```

```json
"scripts": {
  "format:check": "prettier --check assets",
  "validation": "npm run lint:check; s=$?; npm run format:check && exit $s"
}
```

**Why :** One single formatting, Tailwind classes order included, avoids review debates and formatting diffs. In our standard it is
the class sorter that gives Prettier its meaning, hence a rule that only applies with Tailwind.

See [Front code validation](front_code_validation.md#tailwind-css).

### `js.type-check`

**Check :** Does not force TypeScript : the rule only applies to a project that contains some, i.e. when GitLab counts TypeScript among the
languages of the repository, or when a `tsconfig.json` is at its root (GitLab counts a `.vue` as « Vue » even when its script is in
TypeScript). Its types are then checked : the checker is installed in the `devDependencies`, run by a `package.json` script, and the CI runs it,
directly or through that script. The value is the name of the script.

The checker is `vue-tsc` in a Vue project (the only one that reads the `.vue` files) or when it is installed, `tsc` (package `typescript`)
otherwise.

```json
"scripts": {
  "type-check": "vue-tsc --noEmit",
  "validation": "npm run lint:check; s=$?; npm run format:check && npm run type-check && exit $s"
}
```

**Why :** ESLint, with the TypeScript parser, reads the code but does not check the types, and neither does the Vite build : without a
type checker, a type error goes to production.

See [Front code validation](front_code_validation.md#typescript).

### `js.vite`

**Check :** The front code is built by Vite alone : a `vite.config.*` at the root and `vite` in the `devDependencies`, without Webpack
(`webpack.config.*`, `webpack` or `@symfony/webpack-encore` in `package.json`). Otherwise the rule is **info**, with the build tool as value
(`webpack-encore`, `webpack`, or both during a migration), and without value when Vite is only half set up (a `vite.config.*` without
`vite`, or the reverse). Not applicable without any front build.

```jsonc
// package.json, next to a vite.config.mjs
{
    "devDependencies": {
        "vite": "^8.1.3"
    }
}
```

**Why :** One build tool, fast and maintained, for every project. A Webpack build is not a failure but a migration to plan.

## ESLint

### `eslint.config`

**Check :** `eslint` is in the `devDependencies`, configured with the flat format (`eslint.config.*` : `js`, `mjs`, `cjs`, `ts`, `mts`, `cts`,
the only one ESLint 9 reads; a legacy `.eslintrc*` is not compliant), and the CI runs it, directly or through a `package.json` script.
When the project has Prettier (`prettier` in the `devDependencies`), the config also imports `eslint-config-prettier` (or the former
`@vue/eslint-config-prettier`).

```mjs
// eslint.config.mjs
import js from "@eslint/js"
import { defineConfig } from "eslint/config"
import skipFormatting from "eslint-config-prettier/flat"

export default defineConfig([
  { name: "app/files-to-lint", files: ["**/*.{js,mjs}"] },
  js.configs.recommended,
  skipFormatting,
])
```

```yaml
# .gitlab-ci.yml
test:
  script:
    - npm run validation  # npm run lint:check → eslint assets
```

**Why :** The errors of the front code are seen by the CI, not in production. With Prettier, `eslint-config-prettier` turns off the ESLint
rules that duplicate or fight its formatting; without Prettier it has nothing to turn off, so it is not asked.

See [Front code validation](front_code_validation.md#eslint--prettier-boundary).

### `eslint.vue-preset`

**Check :** The ESLint config applies an `eslint-plugin-vue` preset at least as demanding as `flat/strongly-recommended`, the SmartBooster
target (`flat/essential` < `flat/strongly-recommended` < `flat/recommended`). The `-error` variant of a preset counts as its preset. The
value is the most demanding preset of the config, as written. Not applicable without Vue : neither `vue` in `package.json`, nor Vue among the
languages GitLab counts (a VuePress project receives Vue through `vuepress` and still writes its components).

```mjs
// eslint.config.mjs
import pluginVue from "eslint-plugin-vue"

  ...pluginVue.configs["flat/strongly-recommended-error"],
```

**Why :** Vue components are written the same way from one dev to another : `flat/essential` only catches errors, `strongly-recommended`
adds the consistency rules (prop casing, event hyphenation, default props).

See [Front code validation](front_code_validation.md#vue) for why this level and not `recommended`.

### `eslint.js-recommended`

**Check :** The ESLint config applies the recommended rules of ESLint : `js.configs.recommended`, `js` being the name given to the import of
`@eslint/js` (another package's `configs.recommended`, like `tseslint.configs.recommended`, does not count), or `eslint:recommended`.

```mjs
// eslint.config.mjs
import js from "@eslint/js"

  js.configs.recommended,
```

**Why :** Without them ESLint only reports what the plugins add, and lets an undeclared variable or unreachable code through.

### `eslint.suppressions`

**Check :** The ESLint bulk suppressions (`eslint-suppressions.json`, written by `eslint --suppress-all`) are empty. A project without the
file is compliant. Otherwise the rule is **info**, with the total number of suppressed errors : the sum of the `count` of each rule of each
file.

```jsonc
// eslint-suppressions.json : info, 3 suppressed errors
{
    "assets/scripts/main.js": {
        "no-undef": { "count": 3 }
    }
}
```

**Why :** Each suppressed error is a debt of the front code, like the [PHPStan baseline](#phpstanbaseline). The rule is not a failure,
it measures the debt so that its reduction can be followed from one scan to the next.

See [Front code validation](front_code_validation.md#eslint-baseline).
