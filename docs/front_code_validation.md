# Front code validation — ESLint & Prettier

The following standard applied to the front code (Vue, JS, CSS) of our Symfony projects and also for full front project
without Symfony (Vanilla Javascript, Typescript, Full Vue, Vue SPA, ...).

> **Documentation only.** This bundle ships no front configuration: nothing below is copied by the recipe. This page states the
> principle and the reference setup each project type applies.

## Tool responsibilities

| Tool      | Scope                                                                              |
|-----------|------------------------------------------------------------------------------------|
| Prettier  | Code style via formatting : indentation, quotes, line breaks, Tailwind class order |
| ESLint    | Substance, outside typing: JS and Vue rules, project conventions                   |
| `vue-tsc` | Typing, TypeScript projects only (see [TypeScript](#typescript))                   |

**One tool, one responsibility.** No overlap means no contradictory reports and no fix loop between the two tools. 
A failure names its owner: form goes to Prettier, code to ESLint, types to the type checker. 
And each tool is configured and upgraded on its own.

## Why Prettier for formatting

Our choice relies on the tool already used in the [Vue.js](https://github.com/vuejs/core/blob/main/.prettierrc) ecosystem, which
they also recommend in their documentation about [Formatting](https://vuejs.org/guide/scaling-up/tooling.html#formatting), and
which is the reference shipped by the [create-vue](https://github.com/vuejs/create-vue) scaffolding tool.

Our research also showed that other major frameworks and projects rely on it for how simple it is to set up, namely
- [React](https://github.com/react/react/blob/main/.prettierrc.js),
- [Tailwind CSS](https://github.com/tailwindlabs/tailwindcss) and
- [GitLab](https://docs.gitlab.com/development/fe_guide/tooling/#formatting-with-prettier).

The tool is easily extended through plugins, which adapt formatting to the technical context it applies to. We use the official
[Tailwind class sorter](https://github.com/tailwindlabs/prettier-plugin-tailwindcss), see the [Tailwind CSS](#tailwind-css)
section.

In short, this tool guarantees an opinionated setup with few options: little to debate, and the same output everywhere.

## Why ESLint for code conventions and custom rules

We followed the same reasoning for this tool, fully used in the [Vue.js](https://github.com/vuejs/core/blob/main/eslint.config.js)
and [Vite.js](https://github.com/vitejs/vite/blob/main/eslint.config.js) ecosystem, and here again it is the reference shipped by
[create-vue](https://github.com/vuejs/create-vue).

> Both choices are therefore driven by [create-vue](https://github.com/vuejs/create-vue) which, being the official Vue project
> scaffolding tool maintained by the Vue team, guarantees that we always stay aligned with our ecosystem's best practices.

We also use the [Plugin Vue to detect wrong use of Vue.js Directives and violation of Style Guide](https://github.com/vuejs/eslint-plugin-vue)

## Discarded tool

`@antfu/eslint-config`, which brings rules and formatting together in a single tool, was the other candidate.

We discarded it because it is a personal configuration (often mention on other font lib, but used by no official Vue repository) and it imposes
its own style opinions. That choice would also cut us off from the plugins dedicated to the other front libraries, such as the
Tailwind class sorter.

## ESLint / Prettier boundary

ESLint can do formatting too, but we adjust its configuration to disable that behaviour so that it stays entirely handled by
Prettier.

We use the [`eslint-config-prettier`](https://github.com/prettier/eslint-config-prettier) config, imported as `skipFormatting` and
placed **last** in the ESLint config: it turns off every ESLint rule duplicating or conflicting with Prettier (core rules and
`vue/` layout rules).

This package also provides a CLI to test and validate that no rule is left in conflict:

`npx eslint-config-prettier <file.js> <file.vue>`

> To be sure the command compares every rule, pass one file of each extension handled by the config (js, vue, ts, ...).

It prints `No rules that are unnecessary or conflict with Prettier were found.` when there is no error.

> Why `eslint-config-prettier/flat` instead of `@vue/eslint-config-prettier/skip-formatting`? Because the Vue package was a
> transition package which is not meant to be maintained. We therefore chose to use `eslint-config-prettier/flat` like create-vue,
> which made that change at the beginning of 2026, cf. [PR #897](https://github.com/vuejs/create-vue/pull/897).

## Installation and Reference setup

This section is the framework agnostic base. Each following section adds what a given technical context requires on top of it.

Command to run to add the dependencies to your `package.json`:

```sh
yarn add --dev eslint @eslint/js eslint-config-prettier prettier
```

List of the commands to add in the `package.json` for each tool, plus the global validation one:

```json
"scripts": {
  "lint": "eslint . --fix --cache",
  "lint:check": "eslint .",
  "lint:baseline": "rm -f eslint-suppressions.json && eslint . --suppress-all",
  "format": "prettier --write .",
  "format:check": "prettier --check .",
  "validation": "yarn lint:check; s=$?; yarn format:check && exit $s"
}
```

- The `:check` commands only report, they don't apply fix.
- `validation` runs both checks, shows both reports, fails if either fails. Your CI qualimetry job can directly call this command.
- `--cache` writes a `.eslintcache` file, to add to the `.gitignore`.

**Configuration `prettier.config.mjs`**

```mjs
export default {
  semi: false,
  singleQuote: false,
  printWidth: 100,
  singleAttributePerLine: true,
}
```

- `semi`, `printWidth`: Directly takken from [create-vue template](https://github.com/vuejs/create-vue/blob/main/template/formatting/prettier/_prettierrc.json).
- `singleQuote: false`: deviation. Prettier keeps the quote needing fewest escapes; French strings full of apostrophes would end up mixed.
- `singleAttributePerLine`: [Vue style guide](https://vuejs.org/style-guide/rules-strongly-recommended.html#multi-attribute-elements), priority B.
- Any missing option = Prettier default (`tabWidth: 2`, `trailingComma: "all"`, `htmlWhitespaceSensitivity: "css"`, ...).

**How to ignore files in Prettier**

Prettier already reads `.gitignore` and `.prettierignore` (the `--ignore-path` option defaults to both), so generated code stays
out on its own. A `.prettierignore` is only needed for what is versioned but must not be reformatted, typically third-party
assets:

```
# jsVectorMap map data, generated and minified: third-party data, not our code.
assets/scripts/dist
```

> Ignoring is not the way to keep a scope tight: a wide glob passed on the command line is expanded before those rules apply, and
> that traversal fails on directories like `var/` that the user cannot read. If that your case, point the commands at the front directories instead
> (see the [Symfony](#symfony) section).

**Configuration `eslint.config.mjs`**

```mjs
import js from "@eslint/js"
import { defineConfig } from "eslint/config"
import skipFormatting from "eslint-config-prettier/flat"

export default defineConfig([
  { name: "app/files-to-lint", files: ["**/*.{js,mjs}"] },
  js.configs.recommended,
  skipFormatting,
])
```

- The `files` entry declares which extensions ESLint picks up when a directory is passed on the command line: without it, only
  `.js` is linted. The sections below extend it (`.vue`, `.ts`).
- `skipFormatting` stays **last**: on a shared rule, the last config wins.

**How to ignore files in ESLint**

ESLint only ignores `node_modules/` and `.git/` by default. Two helpers cover the rest,
[documented here](https://eslint.org/docs/latest/use/configure/ignore):

```mjs
import path from "node:path"
import { fileURLToPath } from "node:url"
import { defineConfig, globalIgnores, includeIgnoreFile } from "eslint/config"

const gitignorePath = path.resolve(path.dirname(fileURLToPath(import.meta.url)), ".gitignore")

export default defineConfig([
  includeIgnoreFile(gitignorePath, "Imported .gitignore patterns"),
  globalIgnores(["phpstorm.config.js"], "app/files-to-ignore"),
  // ...
])
```

- `includeIgnoreFile` reuses the `.gitignore`, so generated code is declared in one place only.
- `globalIgnores` handles what is versioned but must not be linted.

### Vue

Extra packages to add:

`yarn add --dev eslint-plugin-vue vue-eslint-parser`

> `vue-eslint-parser`, the parser that splits a `.vue` file to expose its template and its script to ESLint, is a peer dependency
> of `eslint-plugin-vue`. It has to be declared if the project uses yarn instead of pnpm, as the former doesn't install peer deps
> by default.

Impact on the ESLint config, `.vue` added to the linted extensions and the preset spread after the JS rules:

```mjs
// eslint.config.mjs
import pluginVue from "eslint-plugin-vue"

  { name: "app/files-to-lint", files: ["**/*.{js,mjs,vue}"] },
  // ...
  ...pluginVue.configs["flat/strongly-recommended-error"],
```

**Why the `flat/strongly-recommended-error` level?**

Out of the [eslint-plugin-vue presets](https://eslint.vuejs.org/user-guide/), `essential` (the create-vue default) only catches
errors. `strongly-recommended` adds the consistency rules usually left aside, whose absence lets component writing style drift
from one dev to another:

- [`vue/prop-name-casing`](https://eslint.vuejs.org/rules/prop-name-casing.html): camelCase prop names;
- [`vue/v-on-event-hyphenation`](https://eslint.vuejs.org/rules/v-on-event-hyphenation.html): hyphenated event names in templates;
- [`vue/require-default-prop`](https://eslint.vuejs.org/rules/require-default-prop.html): default for every optional prop
  (`default: undefined` when none makes sense).

The `-error` variant is needed because the preset declares those rules as `warn`: a warning never enters the baseline and never
fails `lint:check`.

Its 12 layout rules don't conflict with Prettier: 11 are turned off by `skipFormatting`, and `vue/first-attribute-linebreak` requires what
Prettier already produces.

**Why not the `recommended` level?** It adds 8 more rules, which report 752 violations measured over two of our codebases. 84% of
them are [`vue/attributes-order`](https://eslint.vuejs.org/rules/attributes-order.html), an ordering convention inside the tag
that means rewriting every existing template for no functional gain, and which the Vue style guide itself only ranks
[priority C](https://vuejs.org/style-guide/rules-recommended.html) ("minimize arbitrary choices"). Most of the rest is
`vue/no-v-html`, whereas our `v-html` render back-end content that we control. The six remaining rules report next to nothing.

### Symfony

Extra package to add:

`yarn add --dev globals`

The scope of the `lint` and `format` commands must be narrowed to the only directory holding front code, `assets`, which gives in `package.json`:

```json
"scripts": {
  "lint": "eslint assets --fix --cache",
  "lint:check": "eslint assets",
  "lint:baseline": "rm -f eslint-suppressions.json && eslint assets --suppress-all",
  "format": "prettier --write assets",
  "format:check": "prettier --check assets",
  "validation": "yarn lint:check; s=$?; yarn format:check && exit $s"
}
```

A project never validates `vendor/`: a bundle validates its own front code from its own repository.

**Specific use of globals in the context of using FOSJsRoutingBundle**

Impact on the ESLint config: the front code runs in the browser, and Twig injects globals that must be declared, otherwise
`no-undef` reports them.

```mjs
// eslint.config.mjs
import globals from "globals"

  {
    name: "app/globals-browser",
    languageOptions: {
      globals: {
        ...globals.browser,
        // Adapter of Ziggy's `route` function to FOSJsRoutingBundle, set on `window` by the Twig layout.
        route: "readonly",
      },
    },
  },
```

The root config files (`vite.config.mjs`, `phpstorm.config.js`) stay out of that scope, so there is nothing to declare for Node.
Widening the scope to them means adding their globals too, as they don't run in the browser:

```mjs
// eslint.config.mjs
  {
    // Config files run under Node: `vite.config.mjs` reads `__dirname`.
    name: "app/globals-node",
    files: ["**/*.config.{js,mjs}"],
    languageOptions: { globals: { ...globals.node } },
  },
```

### Inertia

Inertia pages and their layouts are not reusable components: resolved by their path (the `import.meta.glob` of the `main.js`
entries), they receive their props from the Symfony controller. Two rules have no object there, while the components remain
subject to both:

```mjs
// eslint.config.mjs
  {
    name: "app/inertia-pages-and-layouts",
    files: ["**/scripts/pages/**/*.vue", "**/scripts/layout/**/*.vue"],
    rules: {
      "vue/multi-word-component-names": "off",
      "vue/require-default-prop": "off",
    },
  },
```

- `vue/multi-word-component-names`: they are never written as a tag, so the collision with a native HTML element that the rule
  prevents cannot happen.
- `vue/require-default-prop`: the props are the controller's contract, not a component API. `required: true` would trigger a Vue
  warning as soon as the back sends `null`, and a `default: undefined` on every prop would change nothing at runtime.

### Tailwind CSS

Extra package to add:

`yarn add --dev prettier-plugin-tailwindcss`

The official class sorter enforces one canonical order for the utility classes, which removes any debate on where a class goes.

Impact on the Prettier config:

```mjs
// prettier.config.mjs
  plugins: ["prettier-plugin-tailwindcss"],
  tailwindStylesheet: "./assets/styles/app.css",
```

- The sorter must always be the **last** plugin of the list, as it has to run after the other Prettier plugins.
- Tailwind 4 has no `tailwind.config.js` anymore, the theme lives in CSS: `tailwindStylesheet` gives the sorter the CSS entry
  file importing it. Without it, the sorter doesn't know our design system utilities (`bg-primary`, `text-danger`, ...) and puts
  them first, among the unknown classes.
- The local `@import` of that entry must be relative (`./base.css`), otherwise the sorter can't resolve it.
- One CSS entry per interface (admin, app, extranet, ...) → declare the main one as above and the others through `overrides`:

```mjs
// prettier.config.mjs
  overrides: [
    { files: "assets/app/**", options: { tailwindStylesheet: "./assets/app/styles/main.css" } },
  ],
```

- The `tailwindcss` package must be resolvable: already the case on a project building its CSS, to add to the devDependencies of a
  repository only carrying the tooling.

### TypeScript

Extra package to add:

`yarn add --dev @vue/eslint-config-typescript`

[This config](https://github.com/vuejs/eslint-config-typescript), still the one of the create-vue TypeScript template, wires
`typescript-eslint` on the `.vue` files.

```mjs
// eslint.config.mjs
// import ...
import { defineConfigWithVueTs, vueTsConfigs } from "@vue/eslint-config-typescript"

export default defineConfigWithVueTs(
  { name: "app/files-to-lint", files: ["**/*.{ts,mts,vue}"] },
  // ...
  vueTsConfigs.recommended,
  // ...
)
```

`defineConfigWithVueTs` replaces `defineConfig`: it is the wrapper propagating the TypeScript parser to the `<script>` blocks.

Type checking stays out of ESLint, and are handled by `vue-tsc --build` (the create-vue `type-check` script).

## Legacy project behavior on front code validation

Both tools report a lot on a codebase that never had them. Rather than a big bang, each tool has its own way to only fail on new
code.

### ESLint: baseline

- [Bulk suppressions](https://eslint.org/docs/latest/use/suppressions): `eslint-suppressions.json` counts existing violations per file
  and rule, only new ones fail. Same principle as the [PHPStan baseline](phpstan.md).
- `yarn lint:baseline` regenerates it from scratch (`--suppress-all` never removes entries). Review its diff before committing.
- Violation fixed → entry obsolete → `lint:check` exits with code 2 → regenerate.
- Only `error` rules are recorded.

### Prettier: pragma

There is no proper "baseline" in Prettier but there is a similar alternative called pragma. 

The `requirePragma: true` config option restricts it to files starting with a `@format` pragma
([options](https://prettier.io/docs/options#require-pragma): "gradually transitioning large, unformatted codebases").

- Opt a file in: `yarn prettier --write --no-require-pragma --insert-pragma <files>` (`--insert-pragma` alone does nothing,
  `requirePragma` wins).
- Marker: `<!-- @format -->` in `.vue`, `/** @format */` in `.js` and `.css`.
- Then `yarn lint:baseline`: formatting fixes `vue/first-attribute-linebreak`, its entries become obsolete.
- Remove the option once every file is formatted.

### Prettier: one-shot formatting commit

Formatting everything at once is the other option, and the only one that gets rid of the pragma right away. It suits a project
with few front files, low sensitivity, and few people and open branches on it. That last point being the one to really assess,
as every open branch will have to be rebased over the reformatted files.

1. Commit the config, merge open branches.
2. One commit with `yarn format`, nothing else.
3. Safety net: `prettier --debug-check` on the same scope reports any file whose meaning might change.
4. Once merged (squash or rebase changes the hash), add the commit hash to `.git-blame-ignore-revs`. Each dev runs
   `git config blame.ignoreRevsFile .git-blame-ignore-revs` (PhpStorm follows it). GitLab: *Blame preferences › Ignore specific revisions*.

Caveats:

- Whole files only: no maintained tool formats changed lines only.
- Template edge case: text moved to its own line becomes an edge space once compiled by Vue. Invisible in a block, visible under
  `whitespace-pre-wrap` or `<pre>`: check those areas.
- `blame.ignoreRevsFile` configured + file missing on a branch → `git blame` fails.

## New project behavior on front code validation

- No baseline, no pragma: both checks pass from the first commit, `validation` stays green on every merge request.
