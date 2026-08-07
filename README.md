# Thailand Platform

Plugin-first platform code and design prototypes for `thai-land.co.il`.

## Release 0.1.0 boundary

The first package is deliberately additive and stores no data. It registers a
minimal public healthcheck at `/wp-json/thailand-platform/v1/health` so a live
package version can be verified independently before any visible or persistent
platform feature is enabled.

## Local verification

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/run.php
node --check prototype/app.js
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
Git code, pass the complete QA contract, use an immutable hash-matched public
package, deploy through a temporary administrator-only route, verify the public
healthcheck and rendered result independently, and delete that route with a
confirmed `404`.
