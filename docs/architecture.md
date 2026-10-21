# Architecture

- `bin/mmwp-release` → `Application` (symfony/console) → one `Command\*` per CLI command (all extend `AbstractCommand`,
  which owns the global flags, config loading, secret masking and exit-code mapping).
- `Services` wires the object graph. All external programs run through `Process\CommandRunner` (`Runner` is the
  symfony/process implementation: logs every command to stderr, masks secrets). HTTP goes through `Http\HttpClient`
  (`CurlHttpClient` shells out to curl via the runner). Both are interfaces so tests use fakes.
- Pure, unit-tested logic: `Plugin\*` (header/readme/changelog parsing, validation), `Build\ForbiddenFiles`,
  `Check\PluginCheckParser`, `Check\ReportSummary`, `Check\CheckGate`, `Deploy\ReleaseDecision`, `Deploy\SvnStatusParser`.
- Failures are `ReleaseException`s carrying an `ExitCode`.
- Fixtures live in `tests/fixtures/`: `mmwp-fixture-plugin` (valid), `mmwp-fixture-broken` (version mismatch, no text
  domain: fails `build`), `mmwp-fixture-pcp-error` (valid headers, unescaped output: fails `check` with Plugin Check ERROR).
