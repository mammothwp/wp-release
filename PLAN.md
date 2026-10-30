# PLAN — mammothwp/wp-release

Composer package providing `bin/mmwp-release`: prepare, verify and ship a Mammoth WP plugin to
wordpress.org (SVN) and GitHub Releases. Identical locally and in CI.

## Principles
- Release state is derived, never tagged: plugin header `Version` vs. what is already published.
  The tool never creates or pushes git tags. (A GitHub Release created via API creates a tag on GitHub only.)
- Every command is idempotent and safe to run on every push to `main`.
- All external tools (composer, wp, zip, rsync, svn, curl) go through one `Process\Runner` wrapper that logs
  every command and masks secrets. HTTP (SVN listing, GitHub API) goes through an `Http\Client` interface so it can be mocked.
- Fail fast, distinct exit codes, no `else`, no function > 100 lines.

## Layout
```
bin/mmwp-release            thin entrypoint -> Application
src/Application.php         symfony/console app, registers commands
src/Command/*               Build, Check, DeployWporg, ReleaseGithub, Status, All (+ AbstractCommand: global flags)
src/Config/                 Config (resolved), ConfigLoader (env + flags + mmwp-release.json)
src/Plugin/                 HeaderParser, ReadmeParser, PluginInfo, ChangelogExtractor
src/Build/                  Builder, Validator, ForbiddenFiles, ZipInspector
src/Check/                  TempSite, PluginCheckRunner, ReportSummary
src/Deploy/                 SvnDeployer, ReleaseDecision (pure idempotency logic), SvnClient
src/Github/                 GithubClient
src/Http/                   HttpClient interface + CurlHttpClient
src/Process/                Runner (symfony/process, logging, masking)
src/Output/                 Reporter (table|json, debug logging)
src/Exception/              ReleaseException subclasses carrying exit codes
tests/Unit, tests/Integration, tests/fixtures/{mmwp-fixture-plugin,mmwp-fixture-broken}
```

## Global flags (every command)
`--dry-run`, `--debug-mode`, `--force`, `--path=<plugin root>` (default cwd), `--format=table|json`, `--slug=`.

## Config precedence
CLI flag > `MMWP_*` env > `mmwp-release.json` > auto-detect.
Slug: `--slug` / `MMWP_SLUG` / main plugin file with `Plugin Name:` header (scan root `*.php`).
`mmwp-release.json` keys: `slug`, `php_min`, `wp_version`, `strict`, `excludes` (extra forbidden patterns), `github_repo`.

## Commands
| Command | Behaviour |
|---|---|
| `build` | copy to temp, `composer install --no-dev -o` (only if composer.json), require `.distignore`, rsync -> `dist/<slug>/`, zip `<slug>.zip`, validate, print size + count |
| `check` | temp WP (SQLite drop-in or `--db=mysql`), install, plugin-check, unzip built zip, activate, `wp plugin check`, write `plugin-check.json/.txt`, exit by severity; `--strict`, `--self-test`, `--keep` |
| `deploy-wporg` | needs passing check; idempotent via SVN `tags/` listing; sparse checkout, rsync trunk/assets, svn add/rm, cp tag, commit |
| `release-github` | idempotent via `releases/tags/v<version>`; create release with readme changelog notes, upload zip |
| `status` | slug, versions, published state, what each deploy would do |
| `all` | build → check → deploy-wporg → release-github, stop at first failure |

`--dry-run`: build/check still run (they are local & non-destructive); deploy-wporg shows `svn status` and stops
before commit; release-github prints the planned request.
`--force`: for deploy/release, proceed even if the version already exists? **No** — `--force` only bypasses the
"passing check required" gate and the "not yet approved" soft-exit is untouched; it never overwrites a published tag.
`--debug-mode`: log each command + stdout/stderr, table output in plugin-check.

## Exit codes
| Code | Meaning |
|---|---|
| 0 | success / already released / not yet approved (default) |
| 1 | unexpected error |
| 2 | usage / configuration error (no slug, bad flags, missing `.distignore`) |
| 3 | missing external tool (wp, svn, rsync, zip …) |
| 10 | build failed (composer / rsync / zip) |
| 11 | validation failed (headers, readme, forbidden files, version mismatch) |
| 20 | plugin check ERRORs (or WARNINGs in strict) |
| 21 | plugin activation failed / temp site setup failed |
| 22 | self-test failed |
| 30 | deploy refused (no passing check result) |
| 31 | SVN failure (checkout/commit) |
| 32 | plugin not yet approved and `MMWP_FAIL_IF_UNAPPROVED=true` |
| 40 | GitHub API failure / missing token |

## Env vars
`MMWP_SLUG`, `MMWP_PCP_STRICT`, `MMWP_DB` (sqlite|mysql), `MMWP_DB_HOST/USER/PASS/NAME`, `MMWP_FAIL_IF_UNAPPROVED`,
`MMWP_GITHUB_OWNER` (default mammothwp), `MMWP_DEBUG`, plus `WP_VERSION`, `WPORG_USERNAME`, `WPORG_PASSWORD`, `GITHUB_TOKEN`
(third-party names kept as specified).

## Commit order
1. PLAN.md  2. package skeleton + tooling (composer, phpcs, phpstan, phpunit)  3. core: process, config, parsers  4. build + validator
5. check  6. deploy-wporg + github + status + all  7. fixtures + tests  8. CI + docs + CHANGELOG.

## Open questions
- Should `--force` ever allow re-release of an existing version? (Assumed no; WP.org tags are immutable by policy.)
- GitHub repo name = slug under `mammothwp` (configurable). Confirm.
- Packagist registration of `mammothwp/wp-release` and CI variables (`WPORG_USERNAME`, `WPORG_PASSWORD`, `GITHUB_TOKEN`) need the owner.
- CI image is `composer:2` (Alpine): tools via apk, extensions via install-php-extensions; deploy jobs must `apk add subversion`.
- Final MR "TESTS: PASSED" requires svn-less environments to still pass: SVN logic is unit-tested with mocks.

## Implementation notes (post-build)
- Plugin Check requires `Tested up to` == current WordPress minor, so the integration test rewrites it in a temp copy of the fixtures.
- `check` rebuilds when the zip is missing or older than the main file / readme.txt / composer.json.
- Dev machine without `pdo_sqlite`: integration tests skip with a message (CI runs them with `--fail-on-skipped`).
- `svn` is not installed on the authoring VPS: the SVN flow is verified with a recording fake runner, not a real commit.
- Fixture `mmwp-fixture-pcp-error` was added so a real Plugin Check ERROR is exercised (the specified broken variant fails earlier, at `build` validation).
