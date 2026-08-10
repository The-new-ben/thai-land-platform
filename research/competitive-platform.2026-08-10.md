# Thai-Land.co.il competitive platform research

Checked: 2026-08-10

## Executive decision

No researched Hebrew competitor combines travel planning, complete Thailand geography, relocation, business, real estate, transport, services, Israeli community information, commerce, and an interactive map in one hierarchy.

Thai-Land.co.il should not imitate one site. It should combine the strongest product patterns from several site types while keeping one shared Thailand entity graph and one SEO owner for every important search task.

## Current Hebrew gap

Hebrew results are divided among broad travel publishers, travel sellers, individual guides, property lead sites, and community organizations. They answer pieces of the Thailand journey, but they do not connect the country from region and province down to a neighborhood, hotel, service provider, transport node, property project, product, or community location.

Examples reviewed:

- Lametayel Thailand: https://www.lametayel.co.il/destinations/thailand-295
- Lametayel Thailand planning guide: https://www.lametayel-thailand.com/planning-a-trip-to-thailand/
- Go Thailand: https://www.go-thailand.co.il/
- Uri Eliyahu Bangkok guide: https://www.uri-eliyahu.co.il/bangkok
- ThaiProperty: https://thaiproperty.co.il/
- Sawadeeka Real Estate: https://sawadeeka-nadlan.com/
- Chabad Thailand: https://chabadthailand.co.il/

## Architecture patterns to adopt

### Country planning and destinations

Japan Guide separates destinations, interests, and trip planning. This prevents broad planning pages from competing with place pages.

- Homepage: https://www.japan-guide.com/
- Destination hierarchy: https://www.japan-guide.com/e/e623a.html

Thailand's official tourism site adds the local entity pattern. A province connects highlights, attractions, food, accommodation, events, wellness, transport, and nearby provinces.

- Tourism Authority of Thailand: https://www.tourismthailand.org/home?lang=th
- Bangkok province page: https://www.tourismthailand.org/Destinations/Provinces/bangkok/219

Decision for Thai-Land:

1. Country
2. Region
3. Province
4. Destination
5. District or island area
6. Neighborhood, beach, or local zone
7. Place, hotel, service, project, product, or transport node

All 77 provinces may exist in the data layer. An indexable page should open only when it contains enough distinct information and serves a real search task.

### Destination template

Tripadvisor demonstrates the demand for attraction categories, neighborhoods, hotels, and maps. Agoda demonstrates area-first hotel discovery.

- Bangkok attractions: https://www.tripadvisor.com/Attractions-g293916-Activities-Bangkok.html
- Bangkok hotels: https://www.tripadvisor.com/Hotels-g293916-Bangkok-Hotels.html
- Sukhumvit hotel area: https://www.agoda.com/sukhumvit/maps/bangkok-th.html

Each major Thai-Land destination should contain fast orientation, an area chooser, map, season guidance, recommended trip length, arrival points, local transport, attractions by interest, hotels by area and traveler, daily costs with an observation date, family and accessibility details, Israeli services, relocation fit, property context, routes, and itineraries.

### Map

Atlas Obscura connects country pages, cities, subjects, places, stories, and itineraries to the map. Visit Thailand Today demonstrates an emerging map-first Thailand experience.

- Thailand atlas: https://www.atlasobscura.com/things-to-do/thailand
- Visit Thailand Today: https://www.visitthailandtoday.com/
- Interactive map: https://www.visitthailandtoday.com/map

The Thai-Land map should be a decision product, not a pin display. Layers should include geography, attractions, beaches, hotels, restaurants, Israeli locations, services, hospitals, schools, transport, property projects, products, delivery points, and events.

OpenStreetMap may supply the geographic base if attribution and ODbL obligations are honored: https://www.openstreetmap.org/copyright

Thailand's Ministry of Tourism and Sports also documents a tourism data platform: https://common.mots.go.th/

### Transport

Rome2Rio owns route intent by comparing modes, duration, price, and steps. 12Go adds bookable inventory.

- Bangkok to Phuket: https://www.rome2rio.com/s/Bangkok/Phuket
- 12Go: https://12go.asia/en

Thai-Land should separate the national transport hub, modes, nodes, operators, and exact origin-to-destination routes. Every time, price, frequency, and operating claim needs a checked date.

### Relocation

Expatica's strongest feature is a relocation journey and checklist connected to a service directory. Numbeo demonstrates demand for comparable city costs, but its figures need clearer household assumptions.

- Expatica: https://www.expatica.com/
- Expatica directory: https://www.expatica.com/directory/
- Numbeo Thailand: https://www.numbeo.com/cost-of-living/country_result.jsp?country=Thailand

Thai-Land should organize relocation around considering a move, choosing a city and visa, building a budget, temporary housing, permanent housing, health, schools, banking, communications, transport, tax, pets, the first week, 30 days, and 90 days.

### Business

The business hub must be a decision tree, not one long article. It should separate company structure, foreign ownership, Foreign Business Act restrictions, BOI, licenses, capital, banking, visas, permits, employment, accounting, VAT, corporate tax, import and export, privacy, sectors, locations, and professional services.

- Thailand BOI 2026 quick guide: https://www.boi.go.th/upload/content/Quick_Guide_to_Starting_a_Business_in_Thailand.pdf
- AustCham incorporation FAQ: https://www.austchamthailand.com/resources/news/faq-and-all-you-need-know-about-incorporating-company-thailand

Every legal claim needs an effective date and should link to a separate service page when the reader wants paid help.

### Services

Yelp's service pattern separates category, location, provider, exact service, project details, and quote request.

- Yelp for Services: https://business.yelp.com/services/
- Quote requests: https://www.yelp-support.com/article/How-do-I-message-a-business-or-request-a-quote?bui=1f19CHcG2wPvQWzEK2Jh_A&l=en_US

Thai-Land provider records need name, category, specialties, languages, office or service area, contact methods, hours, relevant credentials, scope, price basis or estimated range, response time, examples, and confirmation date.

### Real estate and projects

DDProperty separates location, project, and listing filters. FazWaz exposes price per square meter, transit distance, completion year, fees, estimated rent, estimated yield, update date, and project trends. ThaiProps demonstrates natural-language discovery over structured property fields.

- DDProperty: https://www.ddproperty.com/en?hl=en-US
- FazWaz Bangkok: https://www.fazwaz.com/property-for-sale/thailand/bangkok
- ThaiProps: https://www.thaiprops.com/
- Thailand Treasury assessment services: https://www.treasury.go.th/th/services/land-assessment
- Bank of Thailand residential price index: https://app.bot.or.th/BTWS_STAT/statistics/BOTWEBSTAT.aspx?language=ENG&reportID=920

Thai-Land must separate developers, projects, and units. A project record needs exact identity, location, developer, phase, completion status, unit mix, sizes, observed price, price per square meter, fees, ownership route, restrictions, facilities, nearby infrastructure, availability, checked date, and comparable projects.

### Israeli community

Chabad Thailand demonstrates the real geographic community network. Secret Tel Aviv demonstrates the community product loop of guides, events, jobs, businesses, newsletters, groups, and gatherings. InterNations demonstrates city groups and local events.

- Chabad Thailand: https://chabadthailand.co.il/
- Secret Tel Aviv: https://www.secrettelaviv.com/about-us
- InterNations Bangkok: https://www.internations.org/bangkok-expats?os=0

Thai-Land should connect every community location to local housing, services, jobs, events, kosher food, Chabad, transport, and destination information.

### Shop

Lazada demonstrates local category navigation, variants, seller choice, reviews, and payments. iHerb demonstrates destination-specific delivery limits, estimates, tracking, and duties.

- Lazada Thailand: https://www.lazada.co.th/
- iHerb shipping to Thailand: https://www.iherb.com/shipping/th

The first Thai-Land shop should be controlled, not an open marketplace. It needs destination-aware delivery, fulfillment location, delivery fee and estimate, stock state, variants, Thai baht prices, optional shekel estimates, tracking, support, returns, and separate handling for physical products and services.

## Shared entity model

Core entities:

- Region, province, district, destination, neighborhood
- Place, attraction, hotel, restaurant
- Organization, business, service provider
- Property developer, project, unit
- Transport node, route, operator
- Product, offer, event, guide
- Price observation

Every entity needs a permanent ID, canonical URL, Hebrew, Thai, and English names, aliases, geographic parent, coordinates when relevant, relationships, images, sources, checked date, public status, SEO owner, and related entities.

Prices are observations, not permanent identity fields. Each observation needs currency, minimum, maximum, basis, date, and source.

## SEO rules confirmed by current guidance

- Titles should be concise, descriptive, and distinct: https://developers.google.com/search/docs/appearance/title-link
- Breadcrumbs must reflect the real hierarchy: https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
- Internal links must be normal crawlable links: https://developers.google.com/search/docs/crawling-indexing/links-crawlable
- Ecommerce categories must link to subcategories and products: https://developers.google.com/search/docs/specialty/ecommerce/help-google-understand-your-ecommerce-site-structure
- Product markup must reflect real offers and availability: https://developers.google.com/search/docs/appearance/structured-data/product
- LocalBusiness data must use accurate locations, hours, and services: https://developers.google.com/search/docs/appearance/structured-data/local-business
- Most arbitrary filter states should not be indexable: https://developers.google.com/crawling/docs/faceted-navigation
- URL changes require one-to-one redirects to the closest equivalent: https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes
- Sitemap lastmod should reflect genuine changes: https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap

## Product direction

Thai-Land should feel like Japan Guide for hierarchy, Tourism Thailand for official geographic breadth, Atlas Obscura for map discovery, Rome2Rio for routes, Agoda for accommodation discovery, Expatica for relocation, Yelp for services, FazWaz for property comparison, Secret Tel Aviv for community, and Lazada for local commerce.

Its defensible advantage is that all of those systems share one Thailand entity graph and are written around real Israeli decisions in Hebrew.
