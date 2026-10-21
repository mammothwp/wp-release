# Changelog

## Unreleased

- Initial implementation of `mammothwp/wp-release`: `build`, `check`, `deploy-wporg`, `release-github`, `status` and `all`
  commands, idempotent release model derived from the plugin version (no git tags), fixtures, unit and integration tests,
  GitLab CI and documentation.
- Add Mammoth WP package graphics (`docs/assets/banner-*.svg`, `diagram-*.svg`, light and dark, split-panel layout) and the README
  header, badges and "How it works" diagram, following the sibling packages; add `.gitattributes` to keep dev files out of Composer dists.
- Fix `build` shipping CI leftovers (`.cache/composer`, `wp-cli.phar`, tools dir) that made Plugin Check fail with `phar_files`,
  `application_detected` and `hidden_files`: in a git work tree only tracked files are staged (then `.distignore`, then fresh
  no-dev `vendor/`); without git, dot-files/dirs, `*.phar`, `.cache/` and `MMWP_TOOLS_DIR` are excluded. `build` now fails with
  exit 11 and lists the paths when the zip contains hidden files or `*.phar`. Adds `StageFiles`, unit tests and an integration
  test with simulated CI junk.
