# CI notes

## Passing results between jobs

`check` writes `plugin-check.json` / `plugin-check.txt` in the plugin root and `build` writes `<slug>.zip` and
`dist/<slug>/`. Publish them as artifacts so `deploy-wporg` (which refuses without a passing `plugin-check.json`
for the current version) and `release-github` (which uploads the zip) can run in a later job. Or use `all` in one job.

## Image requirements

Jobs run on the official `composer:2` image (Alpine, PHP >= 8.1) on the `docker` runner. Install what it lacks with apk:

- build: `apk add --no-cache rsync zip unzip curl bash`
- check: the same, plus PHP extensions `pdo_sqlite sqlite3 mysqli zip gd intl` (`install-php-extensions ...`, or
  `docker-php-ext-install` as a fallback) and `wp` (WP-CLI phar from `https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar`)
- deploy-wporg: also `apk add --no-cache subversion`

Use `MMWP_DB=mysql` with a MySQL service when `pdo_sqlite` is unavailable.

## Secrets

`WPORG_USERNAME`, `WPORG_PASSWORD`, `GITHUB_TOKEN` must be masked + protected variables. The tool registers them
with its output filter, so commands, stdout and stderr never print them.

## Dry runs

`mmwp-release all --dry-run` builds and checks for real, shows the `svn status` diff that would be committed and the GitHub
release notes, and publishes nothing.

## This repository's pipeline

`.gitlab-ci.yml` runs phpcs, phpstan (level 8), the unit tests and the integration tests (real `build` + `check`
against `tests/fixtures/`; skipped tests fail the job in CI) on merge requests and `main`.
