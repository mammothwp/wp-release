# Changelog

## Unreleased

- Initial implementation of `mammothwp/wp-release`: `build`, `check`, `deploy-wporg`, `release-github`, `status` and `all`
  commands, idempotent release model derived from the plugin version (no git tags), fixtures, unit and integration tests,
  GitLab CI and documentation.
- Add Mammoth WP package graphics (`docs/assets/banner-*.svg`, `diagram-*.svg`, light and dark, split-panel layout) and the README
  header, badges and "How it works" diagram, following the sibling packages; add `.gitattributes` to keep dev files out of Composer dists.
