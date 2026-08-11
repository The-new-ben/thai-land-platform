# Thailand Platform

Plugin-first platform code and design prototypes for `thai-land.co.il`.

## Release 0.5.0 boundary

Version 0.5.0 adds the first Digital Islands pilot: a searchable, interactive
Koh Phangan world with 49 reviewed public records for settlements, roads,
landmarks, government services, infrastructure and three map-only project
identities. Private research candidates, held offers, internal review notes and
unlicensed professional-service leads are not part of the public source,
runtime or plugin package.

The release expands the canonical geography spine with seven districts, 34
subdistricts and six physical islands. The Koh Phangan page has one SEO owner,
one canonical path and one breadcrumb path. Its planned Thailand Map parent is
shown as plain breadcrumb text until that parent is released; a fail-closed
homepage link gives the child a real crawl path only while the exact map page
is publicly ready.

Digital Islands has an independent Off, Canary and Live control plus an exact
WordPress page-ID binding. Live mode requires the reviewed artifact, a
published password-free page at the exact path and all local assets. CesiumJS
and MapLibre adapters are present for a future pinned terrain release, but
their external engines and terrain data are deliberately marked pending in
0.5.0. The current release keeps an accessible interactive orientation world
and full static list available without those dependencies.

The public healthcheck confirms that the compiled geography artifact can be
loaded. Upgrade and activation still create no content, options, tables,
taxonomies, or persistent rewrite rules. Homepage, Real Estate, and Guides all
remain fail closed outside their exact bindings and stay under separate
administrator controls.

## Local verification

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/run.php
node --check prototype/app.js
node tests/tawk-state.test.js
python scripts/build_homepage_assets.py
python scripts/build_geography_registry.py --check
python scripts/build_digital_island_registry.py --check
python scripts/build_content_registry.py --check
python scripts/build_bangkok_rental_assets.py --check
python scripts/build_bangkok_rental_registry.py --check
python scripts/build_guide_assets.py --check
python scripts/build_priority_guides_registry.py --check
python scripts/build_seo_registry.py --check
python scripts/build_seo_runtime.py --check
python tests/geography-builder.test.py
python tests/digital-island-data.test.py
python tests/real-estate-content.test.py
python tests/bangkok-rental-data.test.py
python tests/priority-guides-compiler.test.py
python tests/draft-content-inventory.test.py
python tests/seo-runtime-gates.test.py
python tests/seo-ownership-registry.test.py
php -n tests/geography-resolver.test.php
php tests/digital-islands-runtime.test.php
php tests/digital-islands-settings.test.php
php tests/real-estate-runtime.test.php
php tests/guides-runtime.test.php
node --check assets/guides/guides.js
node --check scripts/live_guides_acceptance.cjs
node --check scripts/live_homepage_acceptance.cjs
node --check scripts/live_real_estate_acceptance.cjs
node --check scripts/live_seo_migration_acceptance.cjs
node --check scripts/live_sitewide_acceptance.cjs
node --check scripts/live_digital_island_acceptance.cjs
node tests/live-sitewide-acceptance.test.cjs
node tests/digital-islands-adapters.test.js
node tests/digital-island-live-acceptance.test.cjs
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
