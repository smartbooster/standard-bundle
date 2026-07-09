# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`smartbooster/standard-bundle` is a Symfony Flex **recipe bundle**: it does not ship application/runtime logic for consumers, it ships dev-tooling
configuration (PHPStan, Psalm, PHP-CS-Fixer, PHPUnit test helpers, Makefiles) that gets copied into consumer Symfony projects via
`composer recipes:install`. The two abstract classes in `src/` are the only real runtime code; everything else in the repo is either a config
template distributed by the recipe, or documentation.

## Commands

`make/*.mk` (`qualimetry.mk`, `test.mk`, `dev.mk`) are themselves the recipe deliverable: they use `$(CONSOLE)`/`$(ENV)` variables that are only
defined by a consuming project's root Makefile. Don't invoke them with `make` in this repo — run the underlying vendor binaries directly instead.

When this bundle is checked out as a vendored dependency of a consumer project (e.g. under `vendor/smartbooster/standard-bundle/` inside
`client-project`), PHP only exists inside that project's `php` Docker container — there is no host PHP to rely on. Run every command below
through it: `docker compose exec php <command>` from the consumer project root (or `docker compose exec --user=dev php bash` for a shell first).
Do not run `php`/`vendor/bin/*` directly against the host PHP install; it will not match the container's PHP version/extensions and can produce
misleading results (e.g. missing `var/cache/dev/App_KernelDevDebugContainer.xml` errors from `phpstan-symfony` when there's no app to boot).

- Static analysis (types, level 10 + disallowed calls): `vendor/bin/phpstan analyse src tests --level=10 --memory-limit=1G -v -c phpstan.neon`
  - Regenerate baseline: same command with `--allow-empty-baseline --generate-baseline` (writes `phpstan-baseline.neon`)
  - Requires two things this repo doesn't have out of the box (see `.github/workflows/qualimetry.yml` for the exact steps): a stub
    `var/cache/dev/App_KernelDevDebugContainer.xml` (`phpstan.neon`'s `symfony.containerXmlPath` expects a booted kernel's container dump — an
    empty `<container xmlns="http://symfony.com/schema/dic/services"><services></services></container>` is enough), and PHPUnit installed
    (`vendor/bin/simple-phpunit --version` once) since `src/AbstractWebTestCase.php`/`AbstractValidatorTest.php` extend real PHPUnit/Symfony
    test classes and `phpstan.neon`'s `bootstrapFiles` points at `vendor/bin/.phpunit/phpunit/vendor/autoload.php`
- Security taint analysis (SQLi/XSS/...): `vendor/bin/psalm` (only needs the container XML stub above, not PHPUnit)
  - CI mode (fail only on new findings): `vendor/bin/psalm --use-baseline=psalm-taint-baseline.xml`
  - Regenerate baseline: `vendor/bin/psalm --set-baseline=psalm-taint-baseline.xml`
- Coding standard checkstyle: `vendor/bin/php-cs-fixer check -v` (`--diff` to preview, `fix` instead of `check` to auto-fix)
- Tests: `vendor/bin/simple-phpunit` — single test: `vendor/bin/simple-phpunit path/to/FileTest.php` or `--filter methodName`
- Composer sanity: `composer validate composer.json && composer audit --abandoned=report`

## Architecture

### Runtime code vs. recipe payload
- `src/` — `SmartStandardBundle` (bundle marker class) plus two abstract test-case base classes consumed by other projects:
  `AbstractWebTestCase` (wires `WebTestCase` + Doctrine `EntityManager` + Liip fixtures/DAMA transaction rollback, plus assertion helpers) and
  `AbstractValidatorTest` (mocks a Symfony `ExecutionContext`/`ConstraintViolationBuilder` to unit-test `ConstraintValidator`s).
- Everything else — `phpstan.neon`, `psalm.xml`, `psalm-taint-stubs.php`, `.php-cs-fixer.dist.php`, `make/*.mk`, `phpunit.xml.dist`,
  `config/packages/*` — is a config template copied verbatim into consumer projects by the Flex recipe, not config for this repo's own app
  (there is no app here).

### Recipe mechanics (how files reach consumer projects)
- `recipes.json` lists every published version; each version maps to a dotted manifest file
  `smartbooster.standard-bundle.{major}.{minor}[.patch].json` holding the `copy-from-package` file mapping and a random `ref` hash.
- Flex diffs recipes by `ref`. Any change to the set of copied files requires a **new** dotted manifest file with a **fresh** `ref`
  (`bin2hex(random_bytes(20))`), registered as a new entry in `recipes.json` — reusing an old `ref` means consumers never see the change.
- Consumers update with `composer update smartbooster/standard-bundle && composer recipes:install smartbooster/standard-bundle --reset --force`.
  This never *removes* files that a previous recipe version copied but the new one no longer does — such removals must be called out in
  `CHANGELOG.md`.

### QA tool layering (`docs/qa.md`, `docs/phpstan.md`, `docs/psalm.md`)
Type-correctness and security are deliberately split across two engines with two independent baselines — don't conflate them:
- **PHPStan** (`phpstan.neon` / `phpstan-baseline.neon`) — type checking at level 10, plus `disallowedFunctionCalls` banning dangerous functions by
  *presence* regardless of data-flow (`eval`, `exec`, `phpinfo`, `md5`/`sha1`, `assert`, `ini_set`, `error_reporting`, ...).
- **Psalm** (`psalm.xml` / `psalm-taint-baseline.xml`) — used *only* for taint (data-flow) analysis, not type checking
  (`errorLevel="8"`, Psalm's loosest, on purpose, so it doesn't compete with PHPStan). Sources/XSS sinks come from `psalm/plugin-symfony`
  (Symfony HTTP input, Twig/`Response`); SQL sinks on Doctrine ORM/DBAL come from the custom `psalm-taint-stubs.php` stub, since no existing
  Psalm plugin covers the Doctrine ORM query path. Suppress a taint false positive with `@psalm-taint-escape <type>`, never `@psalm-suppress`
  (taint issues are emitted in a deferred pass that suppress doesn't reach).
- **PHP-CS-Fixer** — the sole checkstyle/coding-standard tool; `squizlabs/php_codesniffer` was removed as a duplicate (see ADR below).

### ADRs (`adr/`)
Every structuring decision on this bundle (adding, replacing or removing a tool/rule) must be recorded as an ADR named `{date}-{topic}.md` with
sections **Context / Goal / Selected solution / Impact**. Check `adr/` before assuming *why* a tool exists or was dropped — e.g.
`20260709-php-security-audit-migration.md` (Psalm vs. `pheromone/phpcs-security-audit`) and `20260709-php-codesniffer-removal.md`
(PHP-CS-Fixer vs. `squizlabs/php_codesniffer`).

### CI (`.github/workflows/qualimetry.yml`)
Runs `composer-validate`, `lint-php`, `checkstyle`, `phpstan` and `psalm-ci` (via `make -f make/qualimetry.mk <target>`, since this repo has no
root Makefile of its own) on push to `main` and on pull requests. `lint-twig`/`lint-yaml`/`lint-container` are intentionally excluded — they
call `$(CONSOLE)`, which only exists in a real consuming app.

### Release workflow
Changes are tracked manually in `CHANGELOG.md` (`## vX.Y.Z - (date)` with `### Added/Changed/Removed/Fixed` sections). When a change affects
which files the recipe copies, it needs both a new dotted manifest (see above) and a CHANGELOG entry describing what was added/removed.
