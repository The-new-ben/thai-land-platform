# Thailand Platform

Plugin-first platform code and design prototypes for `thai-land.co.il`.

## Release 0.2.7 boundary

The package keeps the live RTL homepage, country, seven-region, and 77-province
geography spine intact. It also makes the current homepage description and hero
artwork the explicit Open Graph and X sharing values, replacing legacy homepage
social metadata without changing the canonical SEO description owner. While the
mobile navigation drawer is open, the external accessibility controls are hidden
and inert so they cannot cover the drawer heading or receive background focus.

The public healthcheck now confirms that the compiled geography artifact can be
loaded. Upgrade and activation still create no content, options, tables,
taxonomies, or persistent rewrite rules.

## Local verification

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/run.php
node --check prototype/app.js
node tests/tawk-state.test.js
python scripts/build_homepage_assets.py
python scripts/build_geography_registry.py --check
python tests/geography-builder.test.py
python tests/seo-ownership-registry.test.py
php -n tests/geography-resolver.test.php
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
