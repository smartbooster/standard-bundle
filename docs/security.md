# Security — DAST blackbox scans (OWASP ZAP)

[PHPStan](phpstan.md) and [Psalm](psalm.md) read the **code** (SAST). This documentation covers the other half: scanning the **running
application** (DAST) with [OWASP ZAP](https://www.zaproxy.org/), through the targets shipped by `make/security.mk`.

Scans are **blackbox**: no authentication, only the public routes of the project (the ones behind a `PUBLIC_ACCESS` `access_control` in
`config/packages/security.yaml`). ZAP runs in Docker, against a URL, and knows nothing about the code.

> The Makefile contains **no URL**: everything is declared in `dast_scan/targets.json` specific to the project, and the ZAP
> [Automation Framework](https://www.zaproxy.org/docs/automate/automation-framework/) plan is generated on the fly by `dast_scan/build-plan.sh`.

## What the recipe ships

| File                           | Role                                                                                                                     |
|--------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| `make/security.mk`             | the `dast-*` make targets, the Docker run and the `DAST_*` variables                                                     |
| `dast_scan/build-plan.sh`      | generates the ZAP plan (contexts, jobs, reports) from `targets.json`, on standard output                                 |
| `dast_scan/scripts/*.js`       | custom passive scan rules, injected inline into every generated plan (see [Custom passive rules](#custom-passive-rules)) |
| `dast_scan/targets.json`       | **not shipped — each project writes its own** (see [Setup](#setup-mandatory-after-a-recipe-reset))                       |
| `dast_scan/alert-filters.json` | **not shipped — optional, each project writes its own** (see [The baseline](#the-baseline-dast_scanalert-filtersjson))   |

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
4. Optionally write `dast_scan/alert-filters.json`, the project's arbitrated baseline — see
   [The baseline](#the-baseline-dast_scanalert-filtersjson). Absent, the plan simply carries no `alertFilter` job.

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
      "reportRisks": ["info", "low", "medium", "high"],
      "failOnRisk": "Low"
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
| `failOnRisk`        | `Low`   | lowest risk making the build fail, see [The gate](#the-gate-failonrisk) — `High`, `Medium`, `Low` or `Informational` |

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

## The gate (`failOnRisk`)

ZAP **always exits `0`**, whatever it finds. What turns an alert into a red pipeline is the `exitStatus` job, appended to every generated
plan with `errorLevel` set to the environment's `failOnRisk` (default `Low`) and `warnLevel` to `Informational`. It is declared
`alwaysRun: true`, so the status is still set when an earlier job interrupts the plan.

Two consequences worth knowing before raising the bar:

- `failOnRisk` filters on the **risk**, not on the report: an alert dropped from the reports by `reportRisks` still fails the build if its
  risk reaches `failOnRisk`. Keep `reportRisks` at least as wide as `failOnRisk`, otherwise the CI fails on an alert that appears nowhere.
- the job skips every alert requalified as `False Positive` (ZAP's `ExitStatusJob` ignores `CONFIDENCE_FALSE_POSITIVE`) — which is exactly
  what makes the baseline below work.

## The baseline (`dast_scan/alert-filters.json`)

A scan that fails on `Low` only stays useful if the alerts already arbitrated stop failing it. `dast_scan/alert-filters.json` is that
baseline: `build-plan.sh` turns it into an `alertFilter` job, emitted **before** any job generating traffic (a filter only applies to the
alerts raised after it is registered). The file is optional and, like `targets.json`, **never shipped by the recipe**: it holds project
arbitrations, and a shipped example would overwrite the project's own file on every reset.

```json
{
  "filters": [
    {
      "ruleId": "10096",
      "url": "https://admin\\.[^/]+(/.*)?",
      "urlRegex": true,
      "newRisk": "False Positive",
      "note": "Timestamp Disclosure on admin: the integers are the Sonata asset cache-busters (?v=1767630660), not a server clock."
    },
    {
      "ruleId": "40040-2",
      "url": "https://api\\.[^/]+(?:/(?!doc).*)?",
      "urlRegex": true,
      "newRisk": "Info",
      "note": "QID 150631 - outside /doc, reflecting the Origin is accepted: the api is stateless (Bearer token, no session cookie)."
    }
  ]
}
```

| Key                              | Role                                                                                                                                  |
|----------------------------------|---------------------------------------------------------------------------------------------------------------------------------------|
| `ruleId`                         | rule id, or `alertRef` to target a single alert of a multi-alert rule (`10055-4` is one variant of the CSP rule, `10055` all of them) |
| `newRisk`                        | `False Positive` (skipped by the gate, still visible in the report) or `Info` / `Low` / `Medium` / `High` to requalify                |
| `url` + `urlRegex`               | restrict the filter to an URL, literal or regex                                                                                       |
| `parameter` + `parameterRegex`   | restrict it to a parameter (cookie name, field name…)                                                                                 |
| `attack`/`evidence` (+ `…Regex`) | restrict it to the payload sent or to the evidence found                                                                              |
| `methods`, `context`             | restrict it to HTTP methods or to a ZAP context                                                                                       |
| `note`                           | **ours, not ZAP's**: stripped from the job and rendered as a YAML comment above the filter it documents                               |

Two traps:

- ZAP matches with `String.matches()`, so a regex must describe the **whole** URL. `"https://api\\.[^/]+/(?!doc)"` filters nothing;
  `"https://api\\.[^/]+/(?!doc).*"` does (JSON, hence the doubled backslash).
- a filter that matches nothing is **silent** — no warning in the report, no line in the log. After adding one, re-run the scan and check
  the alert really moved.

Prefer scoping a filter by what makes it a false positive (the parameter, the evidence, the rule) rather than by the URL that happened to
raise it: a filter pinned on URLs silently stops covering the next route, while a filter pinned on the rule keeps the arbitration explicit.
Always fill `note` — it ends up in the generated plan, which is the artefact the next audit reads.

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

## Add-ons (`DAST_ADDONS`)

The official ZAP image ships the **release** add-ons only. `DAST_ADDONS` (default `ascanrulesBeta`) lists the extra ones installed into the
container before each run — `ascanrulesBeta` is what brings the CORS active scan rule (alert `40040`, the equivalent of Qualys' QID 150631),
along with `40025` *Proxy Disclosure* and `40038` *Bypassing 403*. Add-on ids are **case sensitive**; set `DAST_ADDONS=` to install none.

The install runs as a **separate `zap.sh` invocation** inside the same container, before the one doing `-autorun`. Installing in the same run
does not work: resolving `ascanrulesBeta` pulls a newer `commonlib`, and swapping it while ZAP is up breaks the custom scripts that build
their metadata from it (`Could not initialize class …ScanRuleMetadata`).

## Output

Every run writes three things, all named after the make target:

- the plan actually executed, in `dast_scan/generated/<target>.yaml` — regenerated on each run, useful to check the scope before attacking
  and archivable by the CI;
- the reports, in `dast_scan/report/<target>-<timestamp>.html` and `.json` (`traditional-html-plus` and `traditional-json` templates),
  restricted to the declared sites and to `reportRisks`;
- ZAP's own log, copied out of the container into `dast_scan/report/<target>.log`. In `-cmd` mode the log never reaches stdout, so this is
  the only way to read the warnings counted by the report's `insight.log.warn`. The copy is unconditional (the exit code is saved
  beforehand), so a **failing** scan — the case where the log matters most — still yields it.

The `-plus` HTML template also lists the rules that ran **without** finding anything, the statistics, the parameters and the
request/response of each alert instance: the report says what was analysed, not only what alerted.

## Custom passive rules

`dast_scan/scripts/*.js` holds project-independent passive scan rules, copied **inline** into the plan (not mounted into the container): the
plan stays self-contained, and the one archived by the CI holds the rules that actually ran. They are registered before any traffic-generating
job and enabled in the three modes.

Four are shipped. The last three exist because Qualys reports vulnerabilities ZAP has no native equivalent for: the point is to be warned by
our own CI before an external audit is, hence the `QUALYS_QID` alert tag carried by each of them.

| Script                   | Id        | What it catches                                                                                                     |
|--------------------------|-----------|-----------------------------------------------------------------------------------------------------------------------|
| `phpinfo.js`             | `9000004` | a `phpinfo()` output in **any** response body, where the native *Hidden File Finder* rule only probes four known paths (`phpinfo.php`, `info.php`, `i.php`, `test.php`) |
| `form-autocomplete.js`   | `9000001` | a `type="password"` input whose autocompletion is disabled neither on the field nor on its form (QID 150112). ZAP retired its own rule `10012` in 2018 because browsers ignore `autocomplete="off"` on passwords; Qualys still reports it |
| `blank-target-links.js`  | `9000002` | a cross-domain link with `target="_blank"` and no `rel="noopener"` (QID 150222). The native rule `10108` only alerts when `rel` explicitly contains `opener`, so a link with no `rel` at all never triggers it, at any threshold |
| `client-side-cookies.js` | `9000003` | a cookie written by the served JavaScript (QID 150122 / 150123). Such a cookie never appears in a `Set-Cookie` header, so the native rules `10010`/`10011` — and any scanner reading responses only — are structurally blind to it |

The last three deliberately report from the **served code** rather than from observed traffic: they fire whether or not the crawl happened to
reach the page, which is what makes them reliable in CI. Each carries in its header the false positives that were tried and dropped
(name-based heuristics on Symfony scoped field names, `document.cookie = someVariable` in every cookie library…) — read it before widening a
pattern.

To add one, drop a `.js` file in `dast_scan/scripts/` — `build-plan.sh` picks up every file of the directory automatically. It must export a
`getMetadata()` returning a `ScanRuleMetadata.fromYaml(...)` and a `scan(ps, msg, src)` raising the alert. Take the next id in the
`9000001+` range reserved here for home-made rules, well clear of the ids ZAP's own add-ons use.

## Troubleshooting

| Symptom                                                  | Cause / fix                                                                                                                                   |
|----------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `build-plan.sh: targets file not found`                  | `dast_scan/targets.json` was not written after the recipe reset (see [Setup](#setup-mandatory-after-a-recipe-reset))                          |
| `build-plan.sh: unknown environment (x). Known: …`       | the make target asks for an environment absent from `.environments`                                                                           |
| `build-plan.sh: no route to replay`                      | `paths` is empty, or `excludePaths` excludes all of them in `active` mode                                                                     |
| `make: *** [dast-…] Error 137`                           | the JVM got `SIGKILL`ed: increase the value of the `DAST_JVM_MEM` variable                                                                    |
| `AccessDeniedException` on the reports                   | the report directory must be writable by uid 1000 (the target already `chmod 777` it, check a stale root-owned dir)                           |
| ZAP finds nothing but the pages exist                    | check the `baseUrl` from **inside** the container (`--network host`), and that the routes are really public                                   |
| `make: *** [dast-…] Error 1` with no error in the output | nominal: the `exitStatus` job found an alert at or above `failOnRisk`. Read the report, then fix it or arbitrate it in `alert-filters.json`   |
| a filter of `alert-filters.json` changes nothing         | the regex must match the **whole** URL (`String.matches()`); a filter matching nothing is silent. Check the emitted job with `make dast-plan` |
| `Could not initialize class …ScanRuleMetadata`           | an add-on was installed in the same `zap.sh` run as the scan, swapping `commonlib` under the scripts — keep the two invocations separate      |
| an alert is absent from the report but fails the build   | `reportRisks` is narrower than `failOnRisk` (see [The gate](#the-gate-failonrisk))                                                            |
