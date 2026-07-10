# PHPStan — Static Analysis (types)

We use [PHPStan](https://phpstan.org/) as our main static analysis tool: it type-checks the code without running it. 
It is complemented — not replaced — by [Psalm taint analysis](psalm.md) for security taint analysis.

**Extensions**

- [phpstan-symfony](https://github.com/phpstan/phpstan-symfony) — understands the Symfony container, controllers and services.
- [phpstan-doctrine](https://github.com/phpstan/phpstan-doctrine) — understands Doctrine entities and repositories.
- [phpstan-phpunit](https://github.com/phpstan/phpstan-phpunit) — ensure proper usage of typed assert function (assertTrue, assertCount, ...), better mock intersection type, and also help reduce false positif for tests files
- [phpstan-friendly-formatter](https://github.com/yamadashy/phpstan-friendly-formatter) — nicer error output + Identifier Summary.
- [phpstan-disallowed-calls](https://github.com/spaze/phpstan-disallowed-calls) — bans dangerous calls (see below).

## Process

### For new project

**Level.** Every new project runs at the **highest level** (`10` today), set in the copied `phpstan.neon`.

### Handle PHPStan for legacy project and handle level update

When you first require the standard bundle it will copy the `phpstan.neon` default config and the `qualimetry.mk` whitch set the default level to 10.

**Approach A: Move up level by level**

You need to find the minimum starting level of the project where phpstan returns no errors, then generate the baseline from that level.

You can then fix errors one by one to progressively move up the levels.

While you may have fewer changes to make to become fully compliant with a given level (e.g. full compat level 8), this approach doesn't guarantee 
that your future development will start directly on the latest rules of the most advanced level 10.

See the PHPStan docs on [rule levels](https://phpstan.org/user-guide/rule-levels).

**Approach B: Stay on the max level and use the baseline**

We recommend this approach to ensure that all new development starts directly on phpstan's highest standards.

Generate the baseline with the command `make phpstan-generate-baseline` or its shortcut `make pgb`.

You can then work through the old errors in dedicated sessions, rule by rule.

See the PHPStan docs on [baseline](https://phpstan.org/user-guide/baseline).

> Still if this approach generate too much work (to directly apply level 10 on a legacy project) you can still lower back the level and
> regenerate the baseline from there.

### Handling false positives

For a specific case in a file, use the comment `// qa: False positive %additional explanation% @phpstan-ignore %code identifier%`

Otherwise, use the config format to specify a path, for example

```yaml
ignoreErrors:
    # method.nonObject ignoré dans tests/ : les fixtures initialisent des objets partiels
    # dont certaines methodes peuvent retourner null avant d'etre completement renseignes.
    -
        identifier: method.nonObject
        path: tests/
```

See the PHPStan docs on [ignoreErrors](https://phpstan.org/user-guide/ignoring-errors).

### About Static Security check, taint analysis and responsability

**Security via `disallowedFunctionCalls`.**
- PHPStan covers the *presence-based* security checks that taint analysis structurally cannot (a function is dangerous regardless of data-flow).
- We ban, among others: `eval`, `exec`, `system`, `passthru`, `shell_exec`, `popen`, `phpinfo`, `assert`, `md5`, `sha1`, `error_reporting`, `ini_set`.
- Injection/XSS (data-flow) is handled by [Psalm](psalm.md); dependency CVEs by `composer audit`.

## FAQ

### How the baseline indicate that a count ignoreError has been fixed in a file ?

When running phpstan and the amount of past ignoreError has been lower due to type correction (or phpstan update or even new phpstan extension) you
will see the following messages :

**When pattern isn't matched at all**

```text
❯ path/to/File.php
-----------------------------------------------------------

  ✘ Ignored error pattern X in path path/to/File.php was not matched in reported errors.
  🏷️  ignore.unmatched
  <unknown file line>
```

**For count reduction**

```text
❯ path/to/File.php
-----------------------------------------------------------------------------

  ✘ Ignored error pattern X in path path/to/File.php is expected to occur 3 times, but occurred only 2 times.
  🏷️  ignore.count
  > 28|         Code  ...
```

That means that the previously detected line (28 in this example) is not flagged anymore.

Instead of regenerate the full baseline, and take the risk to ignore remaining error, the best approach is to :
- open the baseline
- find the "path/to/File.php" with the mention "X" pattern
- For each message :
  - Pattern not matched : Completely remove the mention ignore pattern for that file
  - For count reduction : lower the count value to the new mention value when running phpstan (on the example above the new value must change from 3 to 2)

After that you can launch `make phpstan` again and the ignore.unmatched / ignore.count will not be shown anymore.

> Note that this behavior of detecting count lowering on the baseline is tied to the option reportUnmatchedIgnoredErrors set to true and this is the
> expected behavior. It ensure that we keep track of fixed error mention on the baseline witch can be reduced that way.

## Usage (`make qualimetry`)

| Command                                                 | Purpose                                         |
|---------------------------------------------------------|-------------------------------------------------|
| `make phpstan`                                          | run the static analysis                         |
| `make phpstan-generate-baseline` or shortcut `make pgb` | generate / update the PHPStan baseline          |
| `make qa`                                               | run PHPStan + the rest of the qualimetry suite  |
