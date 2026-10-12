# Project rules

The rules every SMARTBOOSTER repository is checked against by Smart App.

Each rule is evaluated on the files repository and ends up :
- **compliant**,
- **non compliant**, 
- **not applicable** (the repository has no such file)
- **unknown** (a file is unreadable, or the production version is not declared).

Rules are grouped by category, the subject of their code (`phpstan.level` → `phpstan`). The PHP rules are not applicable without
`composer.json`, the JavaScript rules without `package.json`.

## PHP

### `php.composer-require`

**Check :** `require.php` in `composer.json` accepts the whole minor of `PHP_VERSION`, and only that one (`~8.4.0`, `8.4.*`).
A wider constraint (`>=8.1`, `^8.4`) is not compliant.

**Why :** Composer must only resolve dependencies for the PHP version that runs in production.

### `php.composer-platform`

**Check :** `config.platform.php` in `composer.json` is in the production minor (`8.4.0` for `PHP_VERSION=8.4`).

**Why :** Composer resolves dependencies for the production version, not for the one of the machine that runs the install.

## PHPStan

### `phpstan.level`

**Check :** The PHPStan config file declares `level: 10`. A level only given on the command line (Makefile, CI) does not count,
and `max` is not accepted.

**Why :** The highest level catches type errors before production. `max` moves with PHPStan versions: a level is tested before being
imposed, so we declare `10` explicitly.

See [PHPStan](phpstan.md) for the setup of a new project and the level migration workflow of a legacy one.

## JavaScript

### `js.package-manager`

**Check :** One single lock file next to `package.json`: `package-lock.json` (npm) or `pnpm-lock.yaml` (pnpm), never `yarn.lock`.
The `packageManager` field is mandatory and names the same manager as the lock (`npm@11.6.2`).

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

**Why :** JavaScript dependencies must be installed for the Node version that runs in production.

### `js.engine-strict`

**Check :** `engine-strict=true` in `.npmrc`.

**Why :** Without it `engines.node` is only a warning: npm installs anyway on a Node version the project refuses.
