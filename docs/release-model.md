# Release model

Plugins are released without git tags. (This library itself is versioned with `vX.Y.Z` tags on Packagist.) A release is "the plugin's `Version` header is not yet published".

1. Developer bumps `Version` in `<slug>.php`, `Stable tag` in `readme.txt` and adds a `= x.y.z =` entry under
   `== Changelog ==`, then merges to `main`.
2. The pipeline runs `build` → `check` → `deploy-wporg` → `release-github` (or the equivalent jobs).
3. `deploy-wporg` lists `https://plugins.svn.wordpress.org/<slug>/tags/`. If `<version>` is there, it exits 0
   ("already released"). `release-github` does the same with `GET /repos/<owner>/<slug>/releases/tags/v<version>`.
4. Pushes to `main` that do not bump the version therefore deploy nothing.

Notes:

- Creating the GitHub Release makes GitHub create the `v<version>` tag on its side. That is the published release record;
  nothing in the pipeline pushes a tag.
- A plugin without a wordpress.org SVN repository (not yet approved) makes `deploy-wporg` exit 0 with status
  `not-approved` and print submission instructions. Set `MMWP_FAIL_IF_UNAPPROVED=true` to fail instead.
- Published wordpress.org versions are immutable here: `--force` only bypasses the passing-check gate.
- A version lower than the highest published tag prints a warning but is not blocked.
- A `plugin-check.json` is only accepted for the exact slug and version it was produced for.
