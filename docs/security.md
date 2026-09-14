# Security — DAST blackbox scans (OWASP ZAP)

[PHPStan](phpstan.md) and [Psalm](psalm.md) read the **code** (SAST). This documentation covers the other half: scanning the **running
application** (DAST) with [OWASP ZAP](https://www.zaproxy.org/), through the targets shipped by `make/security.mk`.

Scans are **blackbox**: no authentication, only the public routes of the project (the ones behind a `PUBLIC_ACCESS` `access_control` in
`config/packages/security.yaml`). ZAP runs in Docker, against a URL, and knows nothing about the code.

> The Makefile contains **no URL**: everything is declared in `dast_scan/targets.json` specific to the project, and the ZAP
> [Automation Framework](https://www.zaproxy.org/docs/automate/automation-framework/) plan is generated on the fly by `dast_scan/build-plan.sh`.

## What the recipe ships

| File                       | Role                                                                                               |
|----------------------------|----------------------------------------------------------------------------------------------------|
| `make/security.mk`         | the `dast-*` make targets, the Docker run and the `DAST_*` variables                               |
| `dast_scan/build-plan.sh`  | generates the ZAP plan (contexts, jobs, reports) from `targets.json`, on standard output           |
| `dast_scan/scripts/*.js`   | custom passive scan rules, injected inline into every generated plan (phpinfo response detection)  |
| `dast_scan/targets.json`   | **not shipped — each project writes its own** (see [Setup](#setup-mandatory-after-a-recipe-reset)) |

## Setup (mandatory after a recipe reset)

```bash
composer update smartbooster/standard-bundle
composer recipes:install smartbooster/standard-bundle --reset --force
```

The reset copies `make/security.mk`, `dast_scan/build-plan.sh` and `dast_scan/scripts/`, but **never `dast_scan/targets.json`**: base URLs,
subdomains and public routes are project-specific, and a shipped example would silently overwrite the project's own file on every reset.

**Writing `dast_scan/targets.json` is therefore the first step after the recipe install**, otherwise every target fails immediately:

```
build-plan.sh: targets file not found (dast_scan/targets.json)
```

Then complete the setup:

1. Ignore the generated artefacts in the project `.gitignore` (the plans and reports are rebuilt on each run):
   ```gitignore
   /dast_scan/generated
   /dast_scan/report
   ```
2. Check the prerequisites on the host running `make`: **Docker** (the ZAP image is pulled on first run) and **`jq`** (used by
   `build-plan.sh` to read `targets.json`).
3. Make sure the application answers on the declared `baseUrl` (the container runs with `--network host`, so `http://{sub}.localhost` works
   against a local stack).

## `dast_scan/targets.json`

```json
{
  "environments": {
    "local": {
      "baseUrl": "http://{sub}.localhost",
      "spiderMaxMins": 5,
      "spiderAjaxMaxMins": 2,
      "activeScanMaxMins": 10
    },
    "ci": {
      "baseUrl": "https://{sub}.my-project.integration.example.com",
      "spiderMaxMins": 5,
      "spiderAjaxMaxMins": 2,
      "activeScanMaxMins": 30,
      "reportRisks": ["medium", "high"]
    }
  },
  "sites": [
    {
      "sub": "admin",
      "ajaxSpider": true,
      "paths": ["/login", "/forgot_password", "/reset_password"]
    },
    {
      "sub": "app",
      "ajaxSpider": true,
      "paths": ["/login", "/register"]
    },
    {
      "sub": "api",
      "ajaxSpider": false,
      "paths": ["/docs"]
    },
  ],
  "excludePaths": [
    ".*/logout.*",
    ".*/_wdt/.*",
    ".*/_profiler.*",
    ".*/delete$",
    ".*/batch.*",
    ".*/forgot_password.*",
  ]
}
```

### `environments`

One entry per environment, addressed by the make target (`local`, `ci`, …). An unknown name lists the available ones and stops.

| Key                 | Default | Role                                                                                     |
|---------------------|---------|------------------------------------------------------------------------------------------|
| `baseUrl`           | —       | root URL of each site; `{sub}` is replaced by the `sub` of every entry of `sites`          |
| `spiderMaxMins`     | `5`     | cap, in minutes, of the classic spider (`crawl` and `active` modes)                        |
| `spiderAjaxMaxMins` | `2`     | cap, in minutes, of the AJAX spider, **per site** having `ajaxSpider: true`                |
| `activeScanMaxMins` | `10`    | cap, in minutes, of the active scanner (`active` mode)                                     |
| `reportRisks`       | all     | risk levels kept in the reports, e.g. `["medium", "high"]` to drop `info`/`low` noise      |

### `sites`

The scanned subdomains. They define the ZAP **context**: everything below these URLs is in scope, everything else (CDN, fonts, external
icons loaded by the pages) is neither crawled, attacked nor reported (can have some trace on insight due to spider crawl).

| Key          | Role                                                                                                              |
|--------------|--------------------------------------------------------------------------------------------------------------------|
| `sub`        | subdomain injected into `baseUrl` in place of `{sub}`                                                                |
| `paths`      | routes replayed with `GET` at the start of every scan, so passive analysis always sees them even without crawling     |
| `ajaxSpider` | `true` to additionally explore the site with the AJAX spider (headless Firefox) — reserve it for JS-heavy interfaces |

Routes with parameters are declared with a dummy value (`/register?token=test`): the point is to reach the
controller and have ZAP analyse the response, not to hit real data.

### `excludePaths`

Regexes taken out of scope, **in `active` mode only** — those routes are then neither attacked, nor replayed, nor passively analysed. They
must match the **whole URL** (`build-plan.sh` anchors them with `^…$` when filtering the replayed routes).

Exclude anything an attack payload must not reach: destructive routes (`.*/delete$`, `.*/batch.*`), routes sending emails
(`.*/forgot_password.*`, `.*/inscription.*`), payment callbacks (`.*/payment-ipn.*`), the profiler (`.*/_wdt/.*`, `.*/_profiler.*` when using it on dev mode) and the
logout, which would only kill the session.

## Commands

Three levels of increasing invasiveness, each one including the previous:

| Command                       | Mode      | What it adds                                             | Where it can run                      |
|-------------------------------|-----------|-----------------------------------------------------------|---------------------------------------|
| `make dast-blackbox-passive`  | `passive` | replays `paths` with `GET`, passive analysis only         | anywhere, prod included (if needed)   |
| `make dast-blackbox-crawl`    | `crawl`   | + spider and AJAX spider, no form submitted, no attack    | anywhere, prod included (if needed)   |
| `make dast-blackbox-active`   | `active`  | + form submission and real attacks (SQLi, XSS, traversal) | **disposable env only**               |
| `make dast-blackbox-ci`       | `active`  | same, on the `ci` environment of `targets.json`           | integration only                      |
| `make dast-plan`              | —         | prints the generated plan, runs **no** scan               | anywhere                              |

`dast-blackbox-passive`, `-crawl` and `-active` all target the `local` environment. `dast-plan` accepts the two of them as variables:

```bash
make dast-plan DAST_MODE=active DAST_ENV=ci
```

> `active` submits forms and sends attack payloads: it can create accounts, trigger emails and write junk data.

## Output

Every run writes two things, both named after the make target:

- the plan actually executed, in `dast_scan/generated/<target>.yaml` — regenerated on each run, useful to check the scope before attacking
  and archivable by the CI;
- the reports, in `dast_scan/report/<target>-<timestamp>.html` and `.json` (`traditional-html-plus` and `traditional-json` templates),
  restricted to the declared sites and to `reportRisks`.

The `-plus` HTML template also lists the rules that ran **without** finding anything, the statistics, the parameters and the
request/response of each alert instance: the report says what was analysed, not only what alerted.

## Custom passive rules

`dast_scan/scripts/*.js` holds project-independent passive scan rules, copied **inline** into the plan (not mounted into the container): the
plan stays self-contained, and the one archived by the CI holds the rules that actually ran. They are registered before any traffic-generating
job and enabled in the three modes.

`phpinfo.js` is the shipped example: it detects a `phpinfo()` output in **any** response body, where the native *Hidden File Finder* rule
only probes four known paths (`phpinfo.php`, `info.php`, `i.php`, `test.php`).

To add one, drop a `.js` file in `dast_scan/scripts/` — `build-plan.sh` picks up every file of the directory automatically. It must export a
`getMetadata()` returning a `ScanRuleMetadata.fromYaml(...)` (with an `id` that collides with no existing rule — `phpinfo.js` uses `100001`)
and a `scan(ps, msg, src)` raising the alert.

## Troubleshooting

| Symptom                                                    | Cause / fix                                                                                                          |
|------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `build-plan.sh: targets file not found`                     | `dast_scan/targets.json` was not written after the recipe reset (see [Setup](#setup-mandatory-after-a-recipe-reset)) |
| `build-plan.sh: unknown environment (x). Known: …`          | the make target asks for an environment absent from `.environments`                                                  |
| `build-plan.sh: no route to replay`                         | `paths` is empty, or `excludePaths` excludes all of them in `active` mode                                            |
| `make: *** [dast-…] Error 137`                              | the JVM got `SIGKILL`ed: increase the value of the `DAST_JVM_MEM` variable                                           |
| `AccessDeniedException` on the reports                      | the report directory must be writable by uid 1000 (the target already `chmod 777` it, check a stale root-owned dir)  |
| ZAP finds nothing but the pages exist                       | check the `baseUrl` from **inside** the container (`--network host`), and that the routes are really public          |
