# Psalm — Taint Analysis (security)

We use [Psalm](https://psalm.dev/) **only for its [taint analysis](https://psalm.dev/docs/security_analysis/)**, not as a type checker — [PHPStan](phpstan.md) stays our type checker. 

Taint analysis tracks untrusted data from a *source* (HTTP input) to a *sink* (SQL query, HTML output, shell command…) and reports the flow. 

> It replaces the abandoned `pheromone/phpcs-security-audit` for injection/XSS detection, with **real data-flow analysis** instead of pattern matching 
> (far fewer false positives) (see the [migration ADR](../adr/20260709-php-security-audit-migration.md) for more info).

## Process

Psalm knows neither the framework inputs nor the ORM, so **both ends of every flow must be declared**. 
Three pieces make taint analysis understand our Symfony/Doctrine stack:

| Piece                                                                   | Role                                                                                                                                 |
|-------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|
| `vimeo/psalm`                                                           | the engine                                                                                                                           |
| [`psalm/plugin-symfony`](https://github.com/psalm/psalm-plugin-symfony) | **sources**: Symfony HTTP input (`Request`, `InputBag`, `ParameterBag`, `HeaderBag`) + XSS **sinks** (Twig, `Response`)              |
| `psalm-taint-stubs.php` (custom)                                        | **sinks**: Doctrine ORM/DBAL SQL methods (`createQuery`, `QueryBuilder::where/andWhere/having/groupBy`, `Connection::executeQuery`…) |

The custom stub is required and cannot be replaced by a plugin: no Psalm plugin marks the Doctrine **ORM** query path as an SQL sink 
(`weirdan/doctrine-psalm-plugin` only covers low-level DBAL, and its type stubs even collide with ours). 

Native Psalm already ships SQL sinks for raw `mysqli`/`PDO`, but our code goes through Doctrine.

Configuration lives in `psalm.xml`, tuned to run **taint only**:

- `runTaintAnalysis="true"` — taint is the default mode, no `--taint-analysis` flag needed.
- `errorLevel="8"` — Psalm's **loosest** level (for Psalm, `1` is the strictest — the opposite of PHPStan). Type issues are muted so Psalm doesn't compete with PHPStan; taint findings are reported regardless of the level.
- all `findUnused*` off + `cacheDirectory` — keep the run focused on taint and cached.

### Setup

`vimeo/psalm` and `psalm/plugin-symfony` are part of the bundle `require`, and the recipe (manifest `1.5+`) ships `psalm.xml` + `psalm-taint-stubs.php` via `copy-from-package`.
Projects pick them up with `composer recipes:install smartbooster/standard-bundle --reset --force`, then generate their baseline with `make psgb`.

### Baseline 

Taint has its **own** [baseline](https://psalm.dev/docs/running_psalm/using_baselines/) (`psalm-taint-baseline.xml`), separate from PHPStan's — freeze legacy findings so CI only fails on new ones.

### False positives

On a taint flow, `@psalm-suppress` does **not** work (taint issues are emitted in a deferred pass). 
Use [`@psalm-taint-escape <type>`](https://psalm.dev/docs/security_analysis/avoiding_false_positives/) on the value you consider sanitized instead.

## Usage (`make qualimetry`)

| Command                                       | Purpose                                          |
|-----------------------------------------------|--------------------------------------------------|
| `make psalm`                                  | run the taint analysis (all findings)            |
| `make psalm-generate-baseline` or `make psgb` | generate / update the taint baseline             |
| `make psalm-ci`                               | CI run applying the baseline (only new findings) |
