# Thailand Platform

Plugin-first platform code and design prototypes for `thai-land.co.il`.

## Release 0.2.3 boundary

The package preserves the minimal public healthcheck at
`/wp-json/thailand-platform/v1/health` and adds a theme-independent RTL homepage
behind the allowlisted `off | canary | live` presentation option. Upgrades
default to the legacy homepage until an administrator deliberately changes the
mode. Canary responses are private and noindex. Mode changes, deactivation,
and uninstall clear the installed page cache so the original theme returns
immediately after rollback.

The homepage also keeps the existing chat compact and leaves enough room for
its launcher beside the fixed phone actions.

## Local verification

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/run.php
node --check prototype/app.js
python scripts/build_homepage_assets.py
```

The deterministic builder includes only the exact sorted inventory in
`package-files.txt`, validates the plugin/readme/manifest release contract,
runs PHP lint and the dependency-free contract tests, applies a named secret
scan, reopens the ZIP, compares every packaged byte with source, and writes a
SHA-256 receipt bound to the reviewed Git commit.

From a clean, reviewed commit, use the trusted release wrapper:

```powershell
.\scripts\release.ps1
```

It compares every release input with the Git index, builds twice, and refuses
to promote the artifact unless both ZIP hashes are identical. A successful run
publishes the local immutable candidate under
`plugin-dist/<version>/thailand-platform-<version>.zip` together with its strict
receipt; those two files are then deliberately force-added for the release
commit.

## Production rule

Never edit this plugin directly on production. Releases must come from reviewed
Git code, pass the complete QA contract, use an immutable hash-matched package,
deploy in Off mode, pass an administrator-only canary, and verify the public
healthcheck and rendered result independently. Emergency recovery also includes
an independent Upress cache clear because cached responses do not execute PHP.
