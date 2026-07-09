# Migration `pheromone/phpcs-security-audit` → Psalm taint + PHPStan

**Goal**: replace the abandoned `pheromone/phpcs-security-audit` extension (32 sniffs, last updated 2019) and increase [taint analysis](https://www.jetbrains.com/pages/static-code-analysis-guide/what-is-taint-analysis/) 
with up-to-date standard solution.

**Selected solution** : Using [Psalm](https://psalm.dev/docs/security_analysis/) as it is the most mentioned taint analysis php tool in the ecosystem wich is still well maintain.

Available building blocks:
- **Psalm taint** = data-flow analysis (source → sink). Native + `psalm/plugin-symfony` (HTTP sources + Twig/Response XSS sinks) + `psalm-taint-stubs.php` (Doctrine SQL sinks).
- **PHPStan `disallowedFunctionCalls`** = function banning (presence check). Active in `phpstan.neon` for: `exec, eval, system, passthru, shell_exec, phpinfo, popen, md5, sha1, assert, error_reporting, ini_set`.
- **`composer audit`** = dependency CVE / advisories (already part of the `composer-validate` target).

For that replacement we made a Sniff-by-sniff analysis: what takes over, and by what mechanism.

## Security coverage handled replacement and new taint analysis

### Table 1 — Coverage of the 32 previous sniffs from pheromone/phpcs-security-audit

Status legend: ✅ covered · 🟡 covered by PHPStan · 🔵 covered by composer audit · ⚪ not relevant (no Drupal API) · ⏳ obsolete · ❌ not handled

| Sniff                                  | Detects                                          | Handled by                    | Mechanism                                                                                   | Status |
|----------------------------------------|--------------------------------------------------|-------------------------------|---------------------------------------------------------------------------------------------|--------|
| BadFunctions/SQLFunctions              | SQL built with input                             | **Psalm**                     | `sql` taint (native mysqli/pg + Doctrine stub)                                              | ✅      |
| BadFunctions/Mysqli                    | `mysqli_*` with input                            | **Psalm**                     | native `sql` taint                                                                          | ✅      |
| BadFunctions/SystemExecFunctions       | exec/system/passthru… with input                 | **Psalm** + **PHPStan**       | `shell` taint + `disallowedFunctionCalls` (already banned)                                  | 🟡✅    |
| BadFunctions/Backticks                 | backtick operator `` `…` ``                      | **Psalm**                     | `shell` taint (the `` ` `` operator can't be banned by presence)                            | ✅      |
| BadFunctions/EasyRFI                   | include/require with input (RFI/LFI)             | **Psalm**                     | `include` taint                                                                             | ✅      |
| BadFunctions/EasyXSS                   | echo/print of input (XSS)                        | **Psalm**                     | `html` taint (echo) + Symfony plugin (Twig/Response)                                        | ✅      |
| BadFunctions/CallbackFunctions         | call_user_func… with input                       | **Psalm**                     | `callable` taint                                                                            | ✅      |
| BadFunctions/FunctionHandlingFunctions | create_function/call_user_func                   | **Psalm**                     | `callable` / `eval` taint                                                                   | ✅      |
| BadFunctions/FilesystemFunctions       | fopen/unlink… with input                         | **Psalm**                     | `file` taint (open_basedir/symlink config warnings not covered)                             | ✅      |
| BadFunctions/FringeFunctions           | extract/parse_str… with input                    | **Psalm**                     | `extract` taint (partial)                                                                   | ✅      |
| BadFunctions/NoEvals                   | any `eval()`                                     | **PHPStan**                   | `disallowedFunctionCalls` (already banned)                                                  | 🟡     |
| BadFunctions/Phpinfos                  | `phpinfo()`                                      | **PHPStan**                   | `disallowedFunctionCalls` (already banned)                                                  | 🟡     |
| BadFunctions/CryptoFunctions           | weak crypto (mcrypt_*, crypt, OpenSSL padding)   | **PHPStan**                   | `disallowedFunctionCalls`: `md5`, `sha1` banned (`mcrypt_*` skipped: removed in PHP 8)      | 🟡     |
| BadFunctions/ErrorHandling             | `error_reporting()` / runtime display_errors     | **PHPStan**                   | `disallowedFunctionCalls`: `error_reporting`, `ini_set` banned                              | 🟡     |
| BadFunctions/Asserts                   | `assert()` (eval-like)                           | **PHPStan**                   | `disallowedFunctionCalls`: `assert()` banned                                                | 🟡     |
| BadFunctions/PregReplace               | `preg_replace` `/e` modifier + input             | —                             | `/e` modifier removed since PHP 7                                                           | ⏳      |
| CVE/20132110                           | CVE-2013-2110 (PHP < 5.3.26)                     | **composer audit**            | dependency version scan                                                                     | 🔵     |
| CVE/20134113                           | CVE-2013-4113 (PHP < 5.3.27)                     | **composer audit**            | dependency version scan                                                                     | 🔵     |
| Drupal7/SQLi                           | `db_query()` SQLi (Drupal)                       | **Psalm** (by intent)         | `sql` taint via Doctrine stub (`db_query` API absent)                                       | ✅⚪     |
| Drupal7/DynQueries                     | dynamic queries (where/having…)                  | **Psalm** (by intent)         | `sql` taint via Doctrine stub — replaces the intended usage                                 | ✅      |
| Drupal7/DbQueryAC                      | access control on queries (Drupal)               | ⚪                             | Drupal-specific access logic                                                                | ⚪      |
| Drupal7/AESModule                      | `db_query()` AES module (Drupal)                 | ⚪                             | Drupal API absent                                                                           | ⚪      |
| Drupal7/HttpRequest                    | SSRF via `drupal_http_request`                   | **Psalm** (by intent)         | `ssrf` taint (curl) for the Symfony equivalent                                              | ✅⚪     |
| Drupal7/XSSHTMLConstruct               | HTML built with input                            | **Psalm**                     | `html` taint + Twig (Symfony plugin)                                                        | ✅      |
| Drupal7/XSSFormValue                   | XSS via `#value` (Drupal Form API)               | ⚪                             | Drupal API absent                                                                           | ⚪      |
| Drupal7/XSSPTheme                      | XSS via `#theme`/html_tag (Drupal)               | ⚪                             | Drupal API absent                                                                           | ⚪      |
| Drupal7/Cachei                         | cache injection (Drupal)                         | ⚪                             | Drupal cache API absent                                                                     | ⚪      |
| Drupal7/UserInputWatch                 | input detection (Drupal)                         | ⚪                             | Drupal-specific heuristic                                                                   | ⚪      |
| Drupal7/AdvisoriesContrib              | vulnerable Drupal contrib modules                | **composer audit**            | dependency advisories (no Drupal here)                                                      | 🔵⚪    |
| Drupal7/AdvisoriesCore                 | outdated Drupal core                             | **composer audit**            | dependency advisories (no Drupal here)                                                      | 🔵⚪    |
| Misc/BadCorsHeader                     | `Access-Control-Allow-Origin: *`                 | **nelmio_security** (project) | CORS config handled by nelmio/security-bundle at project level, outside the standard-bundle | ✅⚪    |
| Misc/IncludeMismatch                   | meta: extensions not scanned by PHPCS            | —                             | PHPCS meta-rule, moot outside PHPCS                                                         | ❌      |

**Table 1 summary**
- **✅ Covered by Psalm taint** (injection/XSS, the core Symfony risk): ~15 sniffs.
- **🟡 Covered by PHPStan `disallowedFunctionCalls`**: presence-based bans — eval, phpinfo, exec/system, weak crypto (`md5`/`sha1`), runtime error handling (`error_reporting`/`ini_set`), `assert()`.
- **🔵 composer audit**: the 2 CVEs + Drupal advisories (already in place).
- **⚪ Not relevant**: sniffs entirely tied to the Drupal API (absent from this project).
- **✅ Project (nelmio_security)**: `BadCorsHeader` — CORS config handled by nelmio/security-bundle at each project's level, outside the standard-bundle's scope.
- **❌ Not handled**: `IncludeMismatch` (PHPCS meta-rule, moot here).
- **⏳ Obsolete**: `PregReplace` (`/e` removed in PHP 7).

### Table 2 — What Psalm taint analysis adds ON TOP (things we didn't have)

| Addition                                   | Detail                                                                                                                                                    | Provided by                    |
|--------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------|
| **Real data-flow analysis**                | Source → sink tracking across calls (controller input → sink in a service several calls away). phpcs was single-file / token-based.                       | Psalm (engine)                 |
| **Near-zero false positives**              | Only flags a proven flow, instead of alerting on every variable near a query (no more mass `phpcs:ignore`).                                               | Psalm (engine)                 |
| **SQLi on Doctrine ORM/DQL**               | `createQuery`, `QueryBuilder::where/andWhere/having/groupBy` marked as SQL sinks — phpcs only did pattern matching.                                       | `psalm-taint-stubs.php` stub   |
| **XSS in Twig templates**                  | Taint analysis of Twig templates (unescaped variables). **No** equivalent in phpcs-security-audit.                                                        | Symfony plugin                 |
| **XSS via Symfony Response**               | `Response`/HTML content marked as `html` sink.                                                                                                            | Symfony plugin                 |
| **Symfony HTTP sources modeled**           | `Request`, `InputBag`, `ParameterBag`, `HeaderBag` (query/request/attributes/headers) as sources, versioned per Symfony release.                          | Symfony plugin                 |
| **LDAP injection**                         | `ldap_search` as `ldap` sink.                                                                                                                             | Psalm native                   |
| **Header injection**                       | `header()` as `header` sink.                                                                                                                              | Psalm native                   |
| **Object injection / unserialize**         | `unserialize` as `unserialize` sink.                                                                                                                      | Psalm native                   |
| **SSRF**                                   | `curl_init/curl_setopt/getimagesize` as `ssrf` sink.                                                                                                      | Psalm native                   |
| **Path/file injection**                    | `file` family (fopen, file_get_contents, include…).                                                                                                       | Psalm native                   |
| **Cookie injection**                       | `setcookie` as `cookie` sink.                                                                                                                             | Psalm native                   |
| **Tooled false-positive handling**         | Dedicated taint baseline (`--set-baseline`/`--use-baseline`) + inline suppression via `@psalm-taint-escape sql` (not `@psalm-suppress`).                  | Psalm                          |

---

## Impact

- The dep `pheromone/phpcs-security-audit` is removed from the composer.json
- `psalm`, his extension and config are now added to the standard-bundle
