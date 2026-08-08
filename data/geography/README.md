# Thailand geography registry

This registry separates administrative geography from editorial discovery.
That distinction prevents a tourism region, island cluster, or market area from
being mistaken for an official administrative parent.

The administrative spine is:

1. country
2. province
3. district
4. subdistrict
5. village

The seven NSO regions are stored as statistical facets. A province belongs
directly to Thailand in the administrative tree and may also carry one or more
editorial facets later, such as Gulf islands, Andaman coast, Bangkok commuter
belt, or Eastern Economic Corridor.

`provinces.csv` contains the complete 77 province first-level registry,
including Bangkok, keyed by the two digit Ministry of Interior code used by
the National Statistical Office. The `priority` field controls editorial
sequencing only. It does not change administrative status.

Hebrew labels are reader-facing transliterations. Thai names and stable Latin
slugs remain the matching keys for imports, aliases, search, redirects, and
future external data joins.

Source references and their coverage are declared in `regions.json`. Every
future district, subdistrict, island, city, neighborhood, attraction, project,
business, service, and product must point to a stable parent entity before it
can enter a public index.
