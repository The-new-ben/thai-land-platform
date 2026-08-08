# Thailand geography registry

This directory is the reviewed authoring source for Thailand geography. It is
not a public API and must not be packaged as runtime data. The deterministic
compiler produces the bounded server and client artifacts used by WordPress.

`registry.json` owns the independent schema and dataset versions, canonical ID
rules, geography-only types, classification schemes, input inventory, and exact
source metadata. `registry.schema.json` documents that contract.

The administrative spine is country, province, district, subdistrict, and
village. The seven NSO regions are statistical classifications, not
administrative parents. Public navigation and SEO breadcrumbs are owned by the
separate SEO ownership registry.

`provinces.csv` contains all 77 province-equivalent first-level records,
including Bangkok. The compiler derives immutable IDs such as
`geo:th:province:10` and keeps `TH-10` as an external ISO identifier. The
`priority` field controls editorial sequencing only.

`relations.json` defines typed relation rules and explicit relation records.
This permits one administrative parent while preserving separate statistical,
editorial, physical, service-area, availability, proximity, and part-of
relations. Commercial entities are not geography types.

`aliases.csv` contains reviewed aliases with locale, context, lifecycle, and
source identity. Alias resolution is exact and may return an ambiguous result.
It must never choose between duplicate names without context.

`geometry.json` holds optional centers and bounds. Boundary polygons do not
belong in the core homepage payload. `normalization-vectors.json` fixes the
normalization contract shared by the compiler and PHP resolver.

Build or verify the generated artifacts with:

```powershell
python scripts/build_geography_registry.py
python scripts/build_geography_registry.py --check
```
