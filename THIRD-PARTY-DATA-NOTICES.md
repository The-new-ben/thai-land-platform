# Third-party software and data notices

The Thailand Platform plugin code remains proprietary. The following notices
apply only to the identified third-party software and map data. They do not
change the license of the plugin code or unrelated first-party and official
facts.

## Self-hosted map software

MapLibre GL JS 5.18.0 is redistributed under the BSD 3-Clause license. The
complete bundled notice is preserved at:
`assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.LICENSE.txt`

PMTiles JavaScript 4.5.0 is redistributed under the BSD 3-Clause license. The
complete bundled notice is preserved at:
`assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.LICENSE.txt`

The PMTiles JavaScript 4.5.0 browser bundle includes fflate 0.8.2,
redistributed under the MIT License. Copyright (c) 2023 Arjun Barrett.
License: https://github.com/101arrowz/fflate/blob/v0.8.2/LICENSE

## Self-hosted vector basemap

Protomaps © OpenStreetMap contributors.

OpenStreetMap data is available under the Open Data Commons Open Database
License 1.0 (ODbL):
https://opendatacommons.org/licenses/odbl/1-0/

Contributor and copyright information:
https://www.openstreetmap.org/copyright

Protomaps attribution guidance:
https://docs.protomaps.com/basemaps/attribution

The archive also includes Natural Earth data, which is in the public domain:
https://www.naturalearthdata.com/about/terms-of-use/

The local vector archive also contains ESA WorldCover 2021 land-cover data,
even when a public style does not display its land-cover layer. ESA WorldCover
data is licensed under CC BY 4.0 (Creative Commons Attribution 4.0
International):
https://creativecommons.org/licenses/by/4.0/

ESA WorldCover data access and attribution information:
https://esa-worldcover.org/en/data-access

## Self-hosted terrain tiles

Mapzen Terrain Tiles; SRTM and GMTED2010 data courtesy of the U.S. Geological
Survey; ETOPO1 courtesy of NOAA/NCEI. Not for navigation.

Mapzen and Tilezen terrain attribution:
https://github.com/tilezen/joerd/blob/master/docs/attribution.md

U.S. Geological Survey SRTM information:
https://www.usgs.gov/centers/eros/science/usgs-eros-archive-digital-elevation-shuttle-radar-topography-mission-srtm

U.S. Geological Survey GMTED2010 information:
https://www.usgs.gov/coastal-changes-and-impacts/gmted2010

NOAA/NCEI ETOPO information:
https://www.ncei.noaa.gov/products/etopo-global-relief-model

## Self-hosted satellite imagery

Contains modified Copernicus Sentinel data 2026

The reviewed local derivative comes from Sentinel-2 L2A true-colour item
`S2B_47PPL_20260326_0_L2A`, observed at
`2026-03-26T03:55:36.171000Z`. The source-tile cloud-cover metadata is
14.307985%; it is not a cloud-cover claim for the cropped island image. The
source COG is:
https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/47/P/PL/2026/3/S2B_47PPL_20260326_0_L2A/TCI.tif

The derivative was cropped to west 99.92, south 9.63, east 100.12, north 9.84,
reprojected to EPSG:3857, and compressed to WebP. It is orientation-only
historical imagery, not current parcel, title, or buildability evidence.

AWS Open Data Sentinel-2 COG registry and access citation:
https://registry.opendata.aws/sentinel-2-l2a-cogs/

Copernicus Sentinel Data Legal Notice:
https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice

## OpenStreetMap-derived orientation facts

© OpenStreetMap contributors. The Digital Islands registry also contains a
reviewed subset of OSM-derived orientation facts. These facts use the same ODbL
and contributor notices above. The plugin does not request OpenStreetMap
community tile servers.
