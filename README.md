# Thailand Platform

Plugin-first platform code and design prototypes for `thai-land.co.il`.

## Release 0.3.6 boundary

The package keeps the live RTL homepage, country, seven-region, 77-province,
and national real-estate systems intact. It upgrades the protected Bangkok
rental guide with ten market areas, all 50 official Bangkok districts, 19 rail
stations, source-dated rent bands, tenant facts, budget filters, a decision map,
and route-specific responsive artwork. Existing WordPress bodies and public
URLs remain intact.
Content route IDs now retain a separate foreign key to canonical SEO owner IDs,
and the released real-estate hub is the live parent of all seven existing guides.
The responsive menu stays visible through pointer hover and keyboard focus,
the search action keeps its intended contrast, and the accessibility control
uses a reserved sticky-header dock on smaller screens so it cannot cover prose,
calls to action, or footer controls while the reader scrolls.
The responsive overflow contract explicitly wins over theme-level overflow
rules while preserving the drawer's scroll lock.
When the accessibility panel opens, it now begins below the sticky header while
its 44-pixel toggle stays in the reserved header dock. The full panel remains
inside short viewports with its own scroll area and no collision with the menu
or brand.

The public healthcheck confirms that the compiled geography artifact can be
loaded. Upgrade and activation still create no content, options, tables,
taxonomies, or persistent rewrite rules. The managed real-estate experience
remains fail closed outside its exact page and post bindings, and its release
mode stays under administrator control.

## Local verification

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/run.php
node --check prototype/app.js
node tests/tawk-state.test.js
python scripts/build_homepage_assets.py
python scripts/build_geography_registry.py --check
python scripts/build_content_registry.py --check
python scripts/build_bangkok_rental_assets.py --check
python scripts/build_bangkok_rental_registry.py --check
python scripts/build_seo_registry.py --check
python scripts/build_seo_runtime.py --check
python tests/geography-builder.test.py
python tests/real-estate-content.test.py
python tests/bangkok-rental-data.test.py
python tests/draft-content-inventory.test.py
python tests/seo-runtime-gates.test.py
python tests/seo-ownership-registry.test.py
php -n tests/geography-resolver.test.php
php tests/real-estate-runtime.test.php
node --check scripts/live_real_estate_acceptance.cjs
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
