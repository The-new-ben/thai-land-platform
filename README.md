# Thailand Platform

Plugin-first platform code and design prototypes for `thai-land.co.il`.

## Release 0.5.2 privacy hotfix

Version 0.5.2 removes the internal Sentinel source identifier from every public
Digital Islands representation. Recursive runtime and production acceptance
gates reject both `source_id` and `source_ids`. The public map remains
`index, follow` in Live mode; administrator-only Canary remains private and
anonymous requests fail closed as 404.

## Release 0.5.1 boundary

Version 0.5.1 gives the Koh Phangan Digital Islands pilot a self-hosted map
renderer. MapLibre GL JS 5.18.0 and PMTiles 4.5.0 load only on the exact map
route. The 2D view uses a local Protomaps vector archive. The rotatable 3D view
adds a local Sentinel-2 orientation image observed on 26 March 2026, local
Terrarium elevation, terrain and hillshade. Data-saver requests keep the
list-only view, reduced motion starts in 2D, and the accessible static list
remains available when the graphical renderer cannot start.

The renderer release boundary contains exactly 65 immutable assets: five
pinned vendor files including complete MapLibre, PMTiles and bundled fflate
license notices, one vector archive, one Sentinel WebP derivative and 58
terrain PNG tiles at zoom levels 8 through 13. `renderer-manifest.json` records
the exact identity, bounds, attribution, byte count and SHA-256 hash of every
file. Runtime readiness, package construction and release-receipt verification
all fail closed when the manifest, filesystem or ZIP differs.

The pilot still contains exactly 49 reviewed public records for settlements,
roads, landmarks, government services, infrastructure and three map-only
project identities. Twenty-seven records currently have reviewed coordinates;
the remaining reviewed records stay discoverable in the list instead of being
given invented pins. Private research candidates, held offers, internal review
notes and unlicensed professional-service leads are not part of the public
source, runtime or plugin package.

The release expands the canonical geography spine with seven districts, 34
subdistricts and six physical islands. The Koh Phangan page has one SEO owner,
one canonical path and one breadcrumb path. Its planned Thailand Map parent is
shown as plain breadcrumb text until that parent is released; a fail-closed
homepage link gives the child a real crawl path only while the exact map page
is publicly ready.

Digital Islands has an independent Off, Canary and Live control plus an exact
WordPress page-ID binding. Live mode requires the reviewed artifact, a
published password-free page at the exact path and every manifested local
renderer asset. The map is an orientation interface, not parcel, title,
buildability, boundary, measurement or navigation evidence. It does not claim
photorealistic buildings or walking-level coverage.

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
node --check scripts/local_digital_island_browser_acceptance.cjs
node --check tests/fixtures/digital-islands-browser-probe.js
node --check tests/fixtures/digital-islands-browser-server.cjs
node --check tests/fixtures/digital-islands-live-browser-probe.js
node tests/live-sitewide-acceptance.test.cjs
node tests/digital-islands-adapters.test.js
node tests/digital-island-live-acceptance.test.cjs
node scripts/live_digital_island_acceptance.cjs --source-only
node scripts/local_digital_island_browser_acceptance.cjs
```

The local browser acceptance command uses the pinned
`@playwright/cli@0.1.18` runner to execute seven real-browser scenarios against
the vendored MapLibre/PMTiles stack. It verifies 3D and 2D rendering, mobile,
reduced motion, data saver, no-WebGL and source-failure behavior; it writes its
report, screenshots and console receipts outside the repository under the
sibling `work/output/` directory. A release build repeats this gate and records
a bounded evidence summary in its strict receipt, while the QA harness and
fixtures remain outside the plugin ZIP.

After a candidate is deployed, the separate live acceptance requires the
reviewed WordPress page ID and must be run against that exact origin:

```powershell
$env:THP_BASE_URL = 'https://thai-land.co.il/'
$env:THP_DIGITAL_ISLAND_PAGE_ID = '<reviewed WordPress page ID>'
node scripts/live_digital_island_acceptance.cjs
```

That live gate compares the served plugin and all 65 renderer assets with the
reviewed local bytes, checks safe MIME, cache and `nosniff` headers, requires a
PMTiles Range response with HTTP 206 and an exact `Content-Range`, and drives a
real browser until `activeRenderer=3d` with local terrain, Sentinel imagery and
entity interaction. Its source-only mode and dependency-free contract test are
release checks, but neither is recorded as proof that production passed.

The deterministic builder includes only the exact sorted inventory in
`package-files.txt`, validates the plugin/readme/manifest release contract,
verifies all 65 renderer receipts and source notices, runs PHP lint and the
dependency-free contract tests, repeats the pinned real-browser renderer gate,
applies a named secret scan, reopens the ZIP, compares every packaged byte with
source, and writes a SHA-256 receipt bound to the reviewed Git commit.

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
