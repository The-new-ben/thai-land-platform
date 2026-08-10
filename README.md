# Thailand Platform

Plugin-first platform code and design prototypes for `thai-land.co.il`.

## Release 0.4.1 boundary

Version 0.4.1 is the parent-hub lifecycle and public activation release that
follows successful administrator-only Canary verification of version 0.4.0.
It moves the visa and law-and-tax hubs from reviewed draft ownership into the
released guide hierarchy while keeping the live RTL homepage, country,
seven-region, 77-province, national real-estate, and five child-guide systems
intact.

The released contract gives every managed guide one SEO owner, one canonical
path, and one breadcrumb path. Parent availability, child ancestry, and
homepage link expectations now agree with the public lifecycle. The homepage
adds both hub links to desktop navigation, mobile navigation, and the footer
only when both exact WordPress pages satisfy their public identity, path, and
asset gates. If either hub fails a gate, neither is exposed through those
homepage surfaces.

No stored WordPress body or canonical URL changes in this release. The
historical April 2022 route remains archival and noindex, follow. Guides stay
independent from Homepage and Real Estate under separate Off, Canary, and Live
controls.

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
python scripts/build_content_registry.py --check
python scripts/build_bangkok_rental_assets.py --check
python scripts/build_bangkok_rental_registry.py --check
python scripts/build_guide_assets.py --check
python scripts/build_priority_guides_registry.py --check
python scripts/build_seo_registry.py --check
python scripts/build_seo_runtime.py --check
python tests/geography-builder.test.py
python tests/real-estate-content.test.py
python tests/bangkok-rental-data.test.py
python tests/priority-guides-compiler.test.py
python tests/draft-content-inventory.test.py
python tests/seo-runtime-gates.test.py
python tests/seo-ownership-registry.test.py
php -n tests/geography-resolver.test.php
php tests/real-estate-runtime.test.php
php tests/guides-runtime.test.php
node --check assets/guides/guides.js
node --check scripts/live_guides_acceptance.cjs
node --check scripts/live_homepage_acceptance.cjs
node --check scripts/live_real_estate_acceptance.cjs
node --check scripts/live_seo_migration_acceptance.cjs
node --check scripts/live_sitewide_acceptance.cjs
node tests/live-sitewide-acceptance.test.cjs
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
