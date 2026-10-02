# CI and releasing

Every merge into `master` releases the version in `core/.simpl`.

1. Open a pull request into `master` from your branch. Every push to it runs CI.
2. To release, on that branch:
    - Bump the version in `core/.simpl`, `core/composer.json`, `core/package.json` and `SIMPL_VERSION` in `core/src/.env`.
    - Set `SIMPL_LAST_UPDATE` in `core/src/.env`, every `<lastmod>` in the `sitemap.xml` files and the `#### Version <version> (<date>)` changelog entry in the root `README.md` to today's date in UTC.
    - Check it locally with `node .github/scripts/release.mjs check`.
3. Merge when **CI passed** and **Release readiness** are green.

## Workflows

| File                    | Runs on                                            | Does                                                         |
|-------------------------|----------------------------------------------------|--------------------------------------------------------------|
| `ci.yml`                | pushes to a pull request into `master`, or by hand | installs and tests every add-on combination                  |
| `release-readiness.yml` | the same, plus every night at 00:05 UTC            | checks the version and release dates on the pull request     |
| `release.yml`           | pushes to `master`                                 | checks again, then builds and uploads the release to the CDN |

### CI

1. **Plan the installs** (`.github/scripts/install-matrix.mjs`) lists every add-on combination that can be installed, from the dependencies in each `add-ons/*/addon.json`. It takes the PHP version from `core/composer.json` and the MariaDB version from `add-ons/db/compose.yaml`, so CI tests the versions a project ships with.
2. **Repository checks** runs `.githooks/pre-commit --all`, the pre-commit hook's checks on every tracked file.
3. **All add-ons** installs every add-on on Linux, Windows and macOS.
4. After that, every other combination, core only included, installs on Linux in parallel.

Each install (`.github/actions/install-and-test/action.yml`) builds the release zips from the pull request and installs them with the latest published `simpl` CLI: `simpl new --local`, then `simpl add --local` for each add-on that no other add-on in the combination depends on, which installs the rest as their dependencies. It runs `simpl composer install`, `simpl test` and `simpl stan`. With `db` on Linux it then runs `simpl test:integration`, `simpl migrate:fresh` and `simpl seed:fresh` against a MariaDB service. `npm install` builds the Sass and TypeScript last. The Composer and npm package caches are kept between runs, per OS and per set of `composer.json` and `package.json`.

**CI passed** is green when every job above passed, and is the check to require.

GitHub allows 256 jobs in one matrix, and the plan step fails with an error when there are more combinations.

### Release readiness

Sets the `Release readiness` commit status on the pull request. It requires:

- A version that directly follows the latest release on the CDN (after 2.0.0: 2.0.1, 2.1.0 or 3.0.0).
- The same version in all four version stamps.
- The same release date everywhere, equal to today in UTC.
- A changelog entry for a stable version.

The nightly run re-checks every open pull request into `master`, so a pull request with yesterday's date turns red. Fix the dates on the branch and push.

A pre-release appends `a` or `b` to the version (e.g. `2.1.0a`). It follows the latest stable release the same way, an alpha comes before a beta of the same version, and its changelog entry is optional. `versions.json` marks it `is-pre-release`, and `is-latest` stays on the newest stable release.

### Release

Runs the same checks as Release readiness, including the date. On a failure nothing is uploaded; fix it in a new pull request. It then builds the zips from the merged commit, uploads the version folder next to the live one and swaps it in, uploads `versions.json` last, and downloads the result to verify it. Releases run one at a time, in merge order.

## Scripts

- `.github/scripts/release.mjs`: `check`, `build <dir>` and `versions <dir>`. `--root <dir>` runs it on another checkout.
- `.github/scripts/install-matrix.mjs`: prints the add-on combinations.

## Actions

The actions are pinned to a full commit SHA, with the version in a comment. To update one, look up the new release's commit with `git ls-remote https://github.com/<owner>/<action> refs/tags/<tag>` and replace the SHA and comment everywhere it's used.
