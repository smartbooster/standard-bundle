# Extraction of the DAST scans (OWASP ZAP) to `smartbooster/standard-security`

**Goal**: stop shipping the blackbox DAST scans from this bundle, and ship them instead from a package that any project can install,
whatever its stack.

**Context**: the DAST scans (`make/security.mk`, `dast_scan/build-plan.sh`, `dast_scan/scripts/*.js`, `docs/security.md`) were added in
v1.6.0 and hardened in v1.7.x. Unlike the rest of the bundle, nothing in them is PHP or Symfony specific: a set of shell/JS scripts and
config driven by `make` and run in Docker against a URL. Front-only projects (VitePress, VuePress, Astro sites ...) need the same scans but cannot install
a Symfony bundle, so the files ended up copied by hand into those projects, and already started to drift from the bundle version.

**Selected solution**: move the DAST scans to a dedicated internal package repository on Gitlab,
a stack-agnostic package installed by copying its `packages/` folder into the project's `.standard-security/` directory at a given git tag,
with a `standard-security.lock` tracking the installed version and a `make security-fetch` target to update it (same approach as the
`symfony-docker.lock` / `make docker-fetch` of smartbooster/symfony-docker).

Discarded alternatives:
- keeping the files in this bundle and copying them by hand into non-Symfony projects: two copies to maintain, which already diverged;
- one package per stack (composer + npm): the same scripts published twice, for tooling that has no language dependency at all;
- git submodule / subtree, or a templating tool (copier, cruft): extra tooling or workflow for files that are never customised per project
  (the project-specific part is data, `dast_scan/targets.json` and `dast_scan/alert-filters.json`, not a templated file).

## Impact

- `make/security.mk`, `dast_scan/build-plan.sh`, `dast_scan/scripts/` and `docs/security.md` removed from the bundle
- new recipe manifest `smartbooster.standard-bundle.1.8.json`, without the three DAST entries of `copy-from-package`
- the recipe reset does not remove files copied by a previous version: projects have to delete them by hand and install
  standard-security to keep the scans (see the v1.8.0 entry of the CHANGELOG)
- project-owned files (`dast_scan/targets.json`, `dast_scan/alert-filters.json`) keep their format and location
