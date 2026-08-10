# Immersive Thailand Map Platform Implementation Brief

Date: 2026-08-10

Status: Research and architecture decision. This document does not authorize a production change.

## Purpose

Thai-Land.co.il should not treat the map as a decorative page. It should become the geographic interface that connects the site's destinations, attractions, businesses, services, property projects, routes, articles and products.

The experience can feel like landing inside Thailand, but it must remain fast, useful and honest on ordinary phones. A cinematic 3D mode should enhance a complete HTML and map experience, not replace it.

The right outcome is not merely a map page. It is the spatial operating system of the site: every article, attraction, service, product, property, route and destination becomes part of one explorable Thailand, while each valuable subject retains a strong permanent page for search.

## Current technical constraint

Google cannot be the nationwide 3D foundation today. As checked on 2026-08-10, the current [Google Maps coverage page](https://developers.google.com/maps/coverage) lists Thailand for 2D Map Tiles, but not for Photorealistic 3D Tiles or native Maps JavaScript 3D. This is an inference from Google's current coverage table, which was updated on 2026-08-07.

Google Photorealistic 3D Tiles can remain a future optional enhancement if Thailand coverage becomes available. It should not block the product, determine the data model or be promised in public language now.

Nationwide photorealism also cannot be claimed unless imagery and captures are licensed for that purpose. The product can still deliver a convincing world through terrain, buildings, editorial layers, owned 3D models and owned 360 tours.

## Recommended stack

### Primary immersive engine

Use [CesiumJS](https://github.com/CesiumGS/cesium) for the premium globe and immersive mode. CesiumJS is Apache 2.0 software built for a high-precision WGS84 globe, terrain, imagery, entities and streamed 3D Tiles. The [CesiumJS fundamentals](https://cesium.com/learn/cesiumjs-fundamentals/) describe its browser-based 3D globe and 2D map capabilities.

Cesium should handle:

- National fly-in and province-to-place camera travel
- Terrain and ground-level camera movement
- Large 3D Tiles datasets
- Selected building, attraction and property models
- Entity selection, spatial storytelling and time-based scenes
- Smooth transitions between country, province, city, district and place

Use [Cesium World Terrain](https://cesium.com/learn/cesiumjs-learn/cesiumjs-terrain/) during early implementation when its terms and cost fit the release. Cesium can also consume independently hosted terrain. Terrain and imagery must remain separate sources with separate attribution.

Use [Cesium OSM Buildings](https://cesium.com/platform/cesium-ion/content/cesium-osm-buildings/) as an early building layer where useful. Its metadata distinguishes estimated heights. Missing heights may be estimated from levels, so the interface must never present every building as surveyed truth.

For owned or licensed city and property models, use the [Cesium 3D building tiling pipeline](https://cesium.com/platform/cesium-ion/3d-tiling-pipeline/3d-buildings/) or an equivalent standards-based pipeline that preserves per-feature metadata and creates multiple levels of detail.

### Fast default and fallback map

Use [MapLibre GL JS](https://maplibre.org/maplibre-gl-js/docs) for the first nationwide map release, the standard mobile experience and fallback mode. It supports vector tiles, globe views, [3D terrain](https://maplibre.org/maplibre-gl-js/docs/examples/3d-terrain/), building extrusions and custom 3D layers.

MapLibre should handle:

- Fast searchable national coverage
- Province, city, district and neighborhood exploration
- Entity clustering and zoom-gated detail
- Ordinary mobile and low-power devices
- A reliable fallback when WebGL, memory, bandwidth or battery is limited
- Hebrew and right-to-left interface presentation

Follow MapLibre's [large data guidance](https://maplibre.org/maplibre-gl-js/docs/guides/large-data/): remove unused fields, reduce precision, simplify geometry, compress, cluster, gate detail by zoom and serve large collections as vector tiles rather than one GeoJSON response.

### Analytical overlays

Use [deck.gl](https://deck.gl/docs) only for optional analytical layers such as route flows, travel-time patterns, demand clusters, investment comparisons and time-based visualizations. Its [MapLibre integration](https://deck.gl/docs/api-reference/mapbox/overview) has terrain limitations, so it should not own the main 3D scene. Follow the [deck.gl performance guide](https://deck.gl/docs/developer-guide/performance) and avoid repeated full-data updates. Use binary data for very large layers where justified. [GeoJsonLayer reference](https://deck.gl/docs/api-reference/layers/geojson-layer)

### Basemap, terrain and tile delivery

OpenStreetMap can supply a useful geographic foundation. Its data is licensed under ODbL and requires attribution. Review the [OpenStreetMap copyright page](https://www.openstreetmap.org/copyright) and [OSMF license FAQ](https://osmfoundation.org/wiki/Licence/Licence_and_Legal_FAQ) for each derived database decision.

Do not use the public OpenStreetMap tile server as production infrastructure. Its [tile usage policy](https://operations.osmfoundation.org/policies/tiles/) forbids bulk downloading and offline prefetching and provides no service guarantee.

Keep OSM-derived basemap and road data in its own licensed layer. Keep Thai-Land.co.il editorial, commercial, property and service records in an independent first-party database and layer. This separation protects attribution, update and licensing decisions.

Use versioned [PMTiles](https://docs.protomaps.com/) archives for stable country extracts where appropriate. PMTiles supports direct browser access through HTTP Range requests and can carry vector, raster and terrain content. Use the official [MapLibre integration](https://docs.protomaps.com/pmtiles/maplibre) and [cloud storage guidance](https://docs.protomaps.com/pmtiles/cloud-storage). The reference implementation is available in the [PMTiles repository](https://github.com/protomaps/PMTiles).

Store large archives behind object storage and a CDN, not WordPress PHP. [Cloudflare R2 pricing](https://developers.cloudflare.com/r2/pricing/) is one current option. Range delivery must be validated against the CDN behavior described in [Cloudflare's range request documentation](https://developers.cloudflare.com/cache/concepts/default-cache-behavior/).

[MapTiler Server](https://docs.maptiler.com/guides/self-hosting/map-server/maptiler-server-technical-specification/) is an alternative for serving vector and raster archives, quantized-mesh terrain, PostGIS data and compatible endpoints for MapLibre or Cesium. Its official guides cover [self-hosted data](https://docs.maptiler.com/guides/self-hosting/self-hosted-maps/maptiler-data-and-maptiler-server-working-together/) and [PostGIS vector tiles](https://docs.maptiler.com/guides/self-hosting/map-server/vector-tiles-from-postgresql/).

### Spatial database and routing

Use PostGIS or an equivalent spatial database as the source for entity geometry, administrative relationships, nearby search, project footprints, route anchors and geospatial validation.

Use [Valhalla](https://github.com/valhalla/valhalla) for self-hosted driving, walking, cycling, matrices, isochrones and map matching. The [Valhalla API](https://valhalla.github.io/valhalla/api/) supports road routing and can support transit when a valid OSM graph and licensed GTFS are available.

Do not infer ferry, rail, flight or public-transit schedules from proximity. Curate those connections from official operators or licensed schedule feeds and store the source and checked date.

Google Routes can be offered in a separate Google experience or deep link when useful. Do not draw Google route results on the Cesium or MapLibre map. Review the [Google Routes policies](https://developers.google.com/maps/documentation/routes/policies), [Google Maps service terms](https://cloud.google.com/maps-platform/terms/maps-service-terms/index-20240515), [travel modes](https://developers.google.com/maps/documentation/routes/reference/rest/v2/RouteTravelMode), [transit route limits](https://developers.google.com/maps/documentation/routes/transit-route) and [usage and billing](https://developers.google.com/maps/documentation/routes/usage-and-billing).

### Street-level and immersive media

Use a lazy-loaded Google Maps Embed street-view drawer at a selected coordinate where a panorama exists. The [Maps Embed guide](https://developers.google.com/maps/documentation/embed/embedding-map) defines the iframe behavior. Follow the [Street View policy](https://developers.google.com/maps/documentation/streetview/policies): retain attribution, do not cache imagery and do not extract geographic or model data from it.

Owned or licensed 360 tours should provide the seamless experience at flagship attractions, hotels and property projects. These can connect the national map to a site, building, lobby, floor or unit without depending on Street View coverage.

Use [WebXR](https://www.w3.org/TR/webxr/) only as an opt-in enhancement. It requires a secure context, device support, user activation and permission. [WebXR Hit Test](https://immersive-web.github.io/hit-test/) can support placing a licensed model on a detected surface. [WebXR Anchors](https://immersive-web.github.io/anchors/) remains a draft and must not become a critical dependency.

### WordPress boundary

WordPress owns:

- Editorial pages and canonical URLs
- Entity editing and publishing workflow
- Search intent, breadcrumb and indexability decisions
- Human-readable summaries, media and calls to action
- Public entity pages, list views and accessible fallbacks
- A server-rendered map shell that works before the map application loads

WordPress does not own:

- High-volume vector, raster or terrain tile delivery
- Large geometry responses for the whole country
- Continuous routing computation
- Raw 3D model streaming

Implement the future map as an isolated plugin module. Load its application code only on map-enabled pages. Public REST routes should return only the fields needed for the current viewport and filters. Every route needs the permission behavior required by the [WordPress custom endpoint guide](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/). Mutating routes must be capability-gated.

The [WordPress Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) can support the server-rendered shell and small interactive controls. Use the [WordPress Transients API](https://developer.wordpress.org/apis/transients/) only as an expendable cache because transient values can disappear before their maximum expiration.

The map module should support Off, Canary and Live states plus an emergency disable path. No map release should rewrite legacy post bodies or make the current site dependent on a map service.

## Visitor experience

### Landing

The initial view is a fast, elegant national overview with a clear question: Where do you want to land?

The visitor can search in Hebrew, Thai or English, choose a region or enter through a practical intent such as trip planning, property, business, services, community or shopping.

The cinematic experience begins only after the visitor chooses to enter it. The camera flies from Thailand to a region, province, destination, neighborhood and place. Reduced-motion users receive direct transitions instead.

### Three primary modes

#### Explore

Show attractions, beaches, nature, food, hotels, culture, neighborhoods, community places and editorial journeys.

#### Live

Show official weather, warnings, opening state, daylight, seasons and time-sensitive conditions. Clearly separate observed, forecast, scheduled and editorial information.

#### Plan and act

Let the visitor calculate a route, save a trip, compare properties, contact a service, book, buy and continue to the canonical page.

### Semantic zoom

- Country view shows regions, primary journeys and editorial entry points.
- Province view shows destination hubs and meaningful clusters.
- City view shows neighborhoods, transport connections and local collections.
- Street view shows individual entities, access points and selected models.
- Flagship place view can open an owned 360 tour, property model or licensed digital twin.

The map should never become a field of tens of thousands of pins. Density, entity priority and zoom determine what appears.

### Entity drawer and actions

Selecting a place opens an adjacent card or drawer with its name, image, useful facts, current status, price context, key action and canonical page. The visitor can then:

- Open the full page
- Route from the current or selected place
- Save it to an itinerary
- Compare it with another entity
- Contact or book
- Buy a related product or service
- Open Street View or an owned 360 tour where available

### Nationwide Israeli community layer

The Israeli community is a nationwide layer, not a separate small map. It can include community centers, Hebrew-speaking services, kosher food, medical assistance, relocation support, education, business services and emergency information throughout Thailand.

### Property lens

Where sources and licenses allow, show project footprints, phases, completion state, unit types, price bands, amenities and travel-time areas to airports, beaches, hospitals, schools and business centers.

Official assessed land values must be labeled as assessed values, not market prices. View, sunlight or line-of-sight simulations should appear only where the geometry and terrain are accurate enough to support them.

### Features beyond a conventional map

- Airport landing journeys from Bangkok, Phuket, Chiang Mai and Samui
- A time slider for daylight, season, weather, events and property phases
- A trip constellation that connects saved places and detects travel-time or opening-hour conflicts
- Open-now journey planning based on time, route and current operating information
- Animated travel corridors between related entities
- Shared map stories whose stops always lead to permanent pages
- Optional VR visits and AR placement of licensed property or landmark models
- A low-data mode with the same search, lists, saved places and actions

## SEO and indexability model

The map canvas has one canonical hub URL, such as `/map/` after final route approval. Pans, zoom levels, temporary filters and map camera coordinates do not become indexable pages.

Shareable map state can use a hash or controlled query parameters. These states should be noindex or canonicalized to the map hub unless an approved permanent page owns that intent.

Every durable subject has one HTML owner when it provides unique value:

- Region or province hub
- Destination, city or district hub
- Attraction page
- Business or service listing
- Hotel page
- Property project page
- Editorially unique route or itinerary

The map preview links to that owner. The entity page can link back to a selected map state, but its canonical remains the entity page.

Thin entities remain map records and list results without receiving indexable pages. A page becomes indexable only when it has unique information, a defined visitor purpose, sufficient evidence, useful internal links and an assigned primary search intent.

Sitemaps include only approved canonical entities. Breadcrumbs follow the same content tree used by navigation. The controlling rule is:

**One primary query, one owner, one canonical URL and one breadcrumb path.**

## Entity schema

Every place, service, project and product-facing location receives one stable entity ID used by the site, map, directory and shop.

### Identity

- Stable ID and entity type
- Hebrew, Thai and English names
- Aliases and transliterations
- Canonical slug
- Parent and child entity IDs
- Duplicate and merge history

### Spatial data

- Point, polygon or line geometry
- Centroid and display bounds
- Entrance and access nodes
- Altitude where known
- Region, province, district and subdistrict
- Geometry source and accuracy class

### Editorial and search ownership

- Primary user intent and query owner
- Canonical URL
- Summary and unique facts
- Breadcrumb path
- Related entities and editorial collections
- FAQ and media references
- Indexable, noindex or map-only state

### Visitor operations

- Current status and opening hours
- Seasonality and event windows
- Accessibility and family suitability
- Contact, booking and purchase actions
- Price or price range with currency
- Price source and checked date

### Connections

- Road, walking, cycling, ferry, rail and air edges
- Travel time, mode and route source
- Nearby and related entities
- Schedule source and checked date
- Access restrictions

### Business and service fields

- Service category and service area
- Public registration reference where licensed
- Languages served
- Delivery, appointment or response terms
- Public contact channels

### Property fields

- Developer and project identity
- Tenure and legal category
- Development phase and completion state
- Unit types and inventory state
- Price band and checked date
- Amenities and project boundary
- Nearby travel-time relationships

### Commerce fields

- Product or service IDs
- Fulfillment area
- Availability and inventory state
- Current price and checked time
- Booking, purchase or lead action

### Visual fields

- Icon class and map priority
- Thumbnail and hero image
- Owned 360 tour
- glTF model or 3D Tiles asset
- Bounds and level-of-detail rules
- Asset source and license

### Provenance and governance

- Source URL and publisher
- Dataset version
- Fetched and checked dates
- License
- Field-level confidence and accuracy
- Next review date
- Public, private, embargoed and moderation states

Field-level provenance matters because one entity can combine facts from several sources with different update dates and licenses.

## Authoritative Thailand data foundation

No single source covers all of Thailand. Build a source ledger and inspect the license, freshness, geographic precision and update method for every dataset before ingestion.

Priority official sources include:

- The [Tourism Authority of Thailand](https://www.tat.or.th/en) and its [tourist attraction dataset](https://datacatalog.tat.or.th/en/dataset/tourist-attraction), which includes public location and visitor fields. The [TAT tourism dataset catalog](https://datacatalog.tat.or.th/dataset/?groups=tourism-attraction) also exposes related collections.
- The Ministry of Tourism and Sports [common tourism data service](https://common.mots.go.th/) and its [TTD API guide](https://ckan.mots.go.th/dataset/46808be9-3fca-4f20-910a-d59f3a40c768/resource/b0b65769-9807-4393-8484-02025d774c0d/download/how-to-call-api-ttd-v2.pdf).
- [GISTDA Open Data](https://opendata.gistda.or.th/en/) for official geospatial, satellite and disaster datasets.
- [GISTDA disaster services](https://sds.gistda.or.th/) for official disaster layers.
- [GISTDA Sphere web services](https://sphere.gistda.or.th/docs/web-service/search/) for documented search, nearby, geocoding, routing, elevation and map interfaces. Each exact service still needs a license and price decision before use.
- [National Statistical Office GIS](https://gis.nso.go.th/) for official spatial and statistical services.
- [Thailand Government Open Data](https://data.go.th/pages/about-open-data) as the national catalog, while still checking the license on each individual dataset.
- The Treasury Department [land valuation dataset](https://www.data.go.th/dataset/gdpublish-land-valuation) for official assessed values by province. These are tax assessment data, not current market prices.
- The Department of Business Development [DataWarehouse](https://datawarehouse.dbd.go.th/) for legal-entity and business research. Do not scrape or bulk-ingest it until an access method and license are approved.
- The Thai Meteorological Department [data services](https://tmd.go.th/en/service/serviceData), [open data portal](https://data.tmd.go.th/dataset/index.php) and [RSS feeds](https://tmd.go.th/en/service/rss) for forecasts, warnings, radar and official reports.

Official weather and disaster warnings must retain their meaning, source and timestamp. They should not be replaced by inferred safety claims.

## Progressive enhancement, accessibility and performance budgets

The standard page must work before the map application runs. Render the page title, search, navigation, selected entity, important cards and a useful list as HTML. The map enhances this foundation.

Support three device tiers:

- Tier A: strong desktop devices receive Cesium terrain, buildings and detailed models.
- Tier B: typical mobile devices receive MapLibre terrain or simplified Cesium, restrained detail and clusters.
- Tier C: low-power, data-saver or unsupported devices receive a static preview plus complete list, search, route and action controls.

Initial engineering budgets:

- Keep the normal page transfer before map activation below 300 KB, excluding editorial images and fonts.
- Do not place the Cesium or MapLibre bundle on the critical path of ordinary content pages.
- Target a usable standard map within 4 seconds at the 75th percentile on the chosen mid-range mobile and network test profile.
- Target the first useful 3D scene within 6 seconds on the chosen mid-range mobile test device after the visitor chooses immersive mode.
- Keep the first useful 3D view below 8 MB transferred, then stream only viewport and level-of-detail needs.
- Target 60 frames per second on reference desktop hardware and a stable 30 frames per second on reference mobile hardware.
- Cap mobile pixel ratio and scene resolution when needed. Cesium exposes `resolutionScale`, and [Cesium request render mode](https://cesium.com/blog/2018/01/24/cesium-scene-rendering-performance/) reduces idle rendering when the scene is unchanged. [Cesium Viewer reference](https://cesium.com/learn/cesiumjs/ref-doc/Viewer.html?classFilter=flyTo)
- Pause animation and live refresh when the tab is hidden.
- Load Street View, 360 tours and high-detail models only on request.
- Cluster, tile and zoom-gate large entity collections.
- Record performance by device tier, route and feature flag before widening a release.

Accessibility acceptance should target [WCAG 2.2 AA](https://www.w3.org/TR/WCAG22/). At minimum:

- Every map result is also reachable through a visible list.
- All controls work by keyboard.
- Selected place and route changes are announced to screen readers.
- Color is not the only status signal.
- Focus remains predictable when drawers open or close.
- Reduced-motion mode replaces flights with direct transitions.
- A static or simplified view remains available when 3D cannot run.

## Delivery phases

### Phase 0: data and licensing foundation

- Approve the entity schema and geographic hierarchy.
- Create the source and license ledger.
- Establish multilingual naming and duplicate resolution.
- Define canonical ownership and map-only records.
- Build a representative test set across Bangkok, Phuket, Koh Samui, Chiang Mai and less-covered provinces.
- Benchmark tiles, geometry, routing and WordPress API boundaries.

### Phase 1: useful nationwide map

- Ship the MapLibre national map with server-rendered fallback.
- Add multilingual search, clusters and semantic zoom.
- Add attractions, services, properties and Israeli-community entities.
- Connect every approved entity to its canonical page.
- Add basic Valhalla road and walking routes.
- Add official warning layers with timestamps.

### Phase 2: immersive Thailand

- Add the Cesium mode with terrain and OSM buildings.
- Add national fly-in, province transitions and ground camera.
- Add selected owned or licensed 3D landmarks and property models.
- Add device-tier controls, reduced motion and low-data behavior.

### Phase 3: planning and commerce

- Add multimodal itinerary logic where schedules are licensed.
- Add live opening, event and weather layers.
- Add service, booking, purchase and lead actions.
- Add property footprints, travel-time comparisons and phase timelines.
- Add owned 360 tours at priority locations.

### Phase 4: flagship worlds

- Add high-detail digital twins for selected places.
- Add advanced time-based scenes and guided spatial stories.
- Add opt-in WebXR for supported devices.
- Add moderated community contributions with provenance and duplicate control.

Do not begin Phase 4 before the data model, canonical ownership, standard mobile map and accessible fallback are proven.

## Licensing and cost caveats

### Cesium

CesiumJS itself is Apache 2.0. Hosted content and Cesium ion have separate commercial terms and quotas. Current [Cesium ion pricing](https://cesium.com/platform/cesium-ion/pricing/) lists paid commercial plans beginning at $149 per month, with larger published plans at $499, $524 and $874 per month depending on account and capacity. A commercial organization must confirm the correct plan before launch.

### MapTiler

Current [MapTiler pricing](https://www.maptiler.com/cloud/pricing/) lists Flex at $30 per month with 25,000 map sessions, 10,000 3D sessions and 500,000 API requests. Published overage rates and terrain limits must be included in traffic modeling. Self-hosted MapTiler software and data have their own license requirements.

### Google Photorealistic 3D Tiles

If Thailand coverage becomes available, review the [Map Tiles API overview](https://developers.google.com/maps/documentation/tile/overview), [Map Tiles policies](https://developers.google.com/maps/documentation/tile/policies) and [usage and billing](https://developers.google.com/maps/documentation/tile/usage-and-billing). Google restricts caching, offline use, machine analysis and extraction. Logos and dynamic attributions must remain visible.

Current [Google Maps pricing](https://developers.google.com/maps/billing-and-pricing/pricing) lists 1,000 free Photorealistic 3D Tiles root events per month, then $6 per 1,000 in the first paid volume tier. Root tileset requests are the billed events, and session handling affects consumption.

### Street View Embed

Current [Maps Embed billing](https://developers.google.com/maps/documentation/embed/usage-and-billing) lists no charge and unlimited usage, with an API key required. Street View still carries policy, attribution, key-security and user-experience obligations.

### Storage and serving

Current [Cloudflare R2 pricing](https://developers.cloudflare.com/r2/pricing/) lists standard storage at $0.015 per GB-month, operation charges and free internet egress. PMTiles access can create many range reads, so real request counts must be measured.

### Working operating budget

Use $250 to $900 per month as an early planning range for a modest map provider plan, object storage, spatial database and routing server. This is a planning allowance, not a provider quote. Benchmark traffic, concurrent routing, tile reads, stored models and editorial volume before approving production capacity. High-detail 3D capture and model production are separate content costs.

## Release acceptance

Before any map capability goes live:

- Confirm the exact source, license, attribution and update date of each layer.
- Confirm Google content is not mixed with a non-Google map in a prohibited way.
- Confirm every indexable entity has one canonical owner and one breadcrumb path.
- Confirm temporary map states do not enter the sitemap.
- Confirm map-only records do not generate thin pages.
- Confirm Hebrew, Thai and English names resolve to one entity.
- Confirm the public page works without 3D and without client-side scripting.
- Confirm keyboard, screen reader, reduced-motion and low-data behavior.
- Test the defined desktop and mobile performance budgets.
- Confirm user location is not retained by default.
- Confirm warnings, schedules, opening hours, prices and property phases show their dates.
- Release through Off, Canary and Live states with an emergency disable path.
- Verify the live HTML owner, canonical, breadcrumb, structured data, internal links and map deep link after release.

## Decision

Build the useful nationwide MapLibre and entity foundation first. Add Cesium as the premium immersive layer on the same entity graph. Use deck.gl only for analytical views. Host large tiles outside WordPress. Use OSM-derived geography with proper attribution and a separate first-party entity database. Use Valhalla for the map's native route layer. Keep Google Street View and any future Google 3D content optional, separately attributed and compliant with Google's platform rules.

This path can create the feeling of entering a living Thailand without sacrificing mobile users, search ownership, licensing control or the site's ability to grow to millions of geographic relationships.
