#!/usr/bin/env python3
"""Build, test, and verify a deterministic Thailand Platform plugin ZIP."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import zipfile
from datetime import datetime, timedelta, timezone
from pathlib import Path, PurePosixPath
from typing import Any


PLUGIN_SLUG = "thailand-platform"
FIXED_TIMESTAMP = (1980, 1, 1, 0, 0, 0)
RENDERER_MANIFEST_PATH = "resources/digital-islands/renderer-manifest.json"
RENDERER_LOADER_PATH = "src/DigitalIslands/RendererAssets.php"
RENDERER_BOUNDS = {"east": 100.12, "north": 9.84, "south": 9.63, "west": 99.92}
RENDERER_BROWSER_SCRIPT = "scripts/local_digital_island_browser_acceptance.cjs"
RENDERER_BROWSER_PASS = "PASS: Digital Islands real-browser acceptance (7 scenarios)."
RENDERER_BROWSER_SCENARIOS = [
    "desktop-3d",
    "desktop-2d",
    "mobile-2d",
    "reduced-motion",
    "data-saver",
    "no-webgl",
    "asset-failure",
]
RENDERER_TERRAIN_RANGES = {
    "8": {"count": 2, "max_x": 199, "max_y": 121, "min_x": 199, "min_y": 120},
    "9": {"count": 2, "max_x": 398, "max_y": 242, "min_x": 398, "min_y": 241},
    "10": {"count": 2, "max_x": 796, "max_y": 484, "min_x": 796, "min_y": 483},
    "11": {"count": 4, "max_x": 1593, "max_y": 968, "min_x": 1592, "min_y": 967},
    "12": {"count": 12, "max_x": 3187, "max_y": 1937, "min_x": 3184, "min_y": 1935},
    "13": {"count": 36, "max_x": 6374, "max_y": 3875, "min_x": 6369, "min_y": 3870},
}
FORBIDDEN_NAMES = {
    ".env",
    ".npmrc",
    ".pypirc",
    ".htaccess",
    "auth.json",
    "cookies.txt",
    "credentials.json",
    "id_rsa",
    "wp-config.php",
}
FORBIDDEN_SUFFIXES = {".key", ".log", ".map", ".p12", ".pem", ".pfx", ".sql", ".sqlite"}
SECRET_PATTERNS = {
    "private_key": re.compile(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    "authorization_header": re.compile(rb"(?i)authorization\s*:\s*(?:basic|bearer)\s+\S+"),
    "github_token": re.compile(rb"\bgh(?:p|o|u|s|r)_[A-Za-z0-9]{30,}\b"),
    "aws_access_key": re.compile(rb"\b(?:AKIA|ASIA)[A-Z0-9]{16}\b"),
    "stripe_secret": re.compile(rb"\bsk_(?:live|test)_[A-Za-z0-9]{20,}\b"),
    "database_password": re.compile(rb"(?i)define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"][^'\"]+['\"]"),
}


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def json_no_duplicates(path: Path) -> dict[str, Any]:
    def reject(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
        result: dict[str, Any] = {}
        for key, value in pairs:
            if key in result:
                raise ValueError(f"duplicate JSON key in {path.name}: {key}")
            result[key] = value
        return result

    def reject_constant(value: str) -> None:
        raise ValueError(f"non-finite JSON value in {path.name}: {value}")

    parsed = json.loads(
        path.read_text(encoding="utf-8"),
        object_pairs_hook=reject,
        parse_constant=reject_constant,
    )
    if not isinstance(parsed, dict):
        raise ValueError(f"{path.name} must contain a JSON object")
    return parsed


def geography_evidence(root: Path) -> dict[str, Any]:
    """Validate and freeze the compiled geography lineage in the release receipt."""
    manifest_path = root / "resources" / "geography" / "manifest.json"
    manifest = json_no_duplicates(manifest_path)
    expected_manifest_keys = {
        "artifacts",
        "country_id",
        "counts",
        "dataset_version",
        "entity_type_counts",
        "normalization",
        "schema_version",
        "source_inputs",
        "source_manifest_sha256",
    }
    if set(manifest) != expected_manifest_keys:
        raise ValueError("geography manifest fields are missing or unexpected")
    if manifest.get("schema_version") != "1.0.0":
        raise ValueError("geography schema version mismatch")
    if not re.fullmatch(r"[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]+", str(manifest.get("dataset_version", ""))):
        raise ValueError("geography dataset version is invalid")
    if manifest.get("country_id") != "geo:th:country":
        raise ValueError("geography country identity mismatch")

    counts = manifest.get("counts")
    if not isinstance(counts, dict) or set(counts) != {
        "alias_candidates",
        "alias_keys",
        "entities",
        "places",
        "provinces",
        "regions",
        "relations",
    }:
        raise ValueError("geography counts are missing or unexpected")
    if (
        counts["entities"] != 132
        or counts["places"] != 47
        or counts["provinces"] != 77
        or counts["regions"] != 7
    ):
        raise ValueError("geography entity counts do not match the national spine")
    if counts["relations"] != 220 or counts["alias_candidates"] < counts["alias_keys"]:
        raise ValueError("geography relation or alias counts are invalid")

    entity_type_counts = manifest.get("entity_type_counts")
    if entity_type_counts != {
        "country": 1,
        "district": 7,
        "island": 6,
        "province": 77,
        "statistical_region": 7,
        "subdistrict": 34,
    }:
        raise ValueError("geography entity type counts are invalid")

    expected_sources = {
        "aliases.csv",
        "geometry.json",
        "normalization-vectors.json",
        "places.csv",
        "provinces.csv",
        "regions.json",
        "registry.json",
        "registry.schema.json",
        "relations.json",
    }
    source_inputs = manifest.get("source_inputs")
    if not isinstance(source_inputs, dict) or set(source_inputs) != expected_sources:
        raise ValueError("geography source inventory is missing or unexpected")

    expected_artifacts = {
        "assets/geography/core.json",
        "resources/geography/registry.php",
    }
    artifacts = manifest.get("artifacts")
    if not isinstance(artifacts, dict) or set(artifacts) != expected_artifacts:
        raise ValueError("geography artifact inventory is missing or unexpected")

    for label, records, base in (
        ("source", source_inputs, root / "data" / "geography"),
        ("artifact", artifacts, root),
    ):
        for relative, record in records.items():
            if not isinstance(record, dict) or set(record) != {"bytes", "sha256"}:
                raise ValueError(f"geography {label} evidence is invalid: {relative}")
            path = base / relative if label == "source" else base / Path(*PurePosixPath(relative).parts)
            if not path.is_file() or path.is_symlink():
                raise ValueError(f"geography {label} is missing or unsafe: {relative}")
            payload = path.read_bytes()
            if record["bytes"] != len(payload) or record["sha256"] != sha256_bytes(payload):
                raise ValueError(f"geography {label} evidence mismatch: {relative}")

    registry_hash = source_inputs["registry.json"]["sha256"]
    if manifest.get("source_manifest_sha256") != registry_hash:
        raise ValueError("geography source manifest lineage mismatch")

    return {
        "artifacts": artifacts,
        "country_id": manifest["country_id"],
        "counts": counts,
        "dataset_version": manifest["dataset_version"],
        "manifest": "resources/geography/manifest.json",
        "manifest_sha256": sha256_bytes(manifest_path.read_bytes()),
        "parity": "pass",
        "schema_version": manifest["schema_version"],
        "source_inputs": source_inputs,
        "source_manifest_sha256": manifest["source_manifest_sha256"],
    }


def expected_renderer_terrain_tiles() -> list[str]:
    tiles: list[str] = []
    for zoom in ("8", "9", "10", "11", "12", "13"):
        tile_range = RENDERER_TERRAIN_RANGES[zoom]
        for x in range(tile_range["min_x"], tile_range["max_x"] + 1):
            for y in range(tile_range["min_y"], tile_range["max_y"] + 1):
                tiles.append(f"assets/digital-islands/terrain/20260811/{zoom}/{x}/{y}.png")
    return tiles


def renderer_evidence(root: Path, entries: list[str], release_version: str) -> dict[str, Any]:
    """Validate every self-hosted renderer byte and freeze it in the receipt."""
    manifest_path = root / RENDERER_MANIFEST_PATH
    loader_path = root / RENDERER_LOADER_PATH
    manifest_payload = manifest_path.read_bytes()
    if (
        len(manifest_payload) != 15395
        or sha256_bytes(manifest_payload) != "bf24b0b134e8c6abd3e38d1f7c2b712f7057d636950accdf61f1fe9eed864bb3"
    ):
        raise ValueError("renderer manifest pinned receipt mismatch")
    manifest = json_no_duplicates(manifest_path)
    expected_manifest_keys = {
        "attribution",
        "basemap",
        "contract_id",
        "dependencies",
        "inventory",
        "island_id",
        "release_version",
        "satellite",
        "schema_version",
        "terrain",
    }
    if set(manifest) != expected_manifest_keys:
        raise ValueError("renderer manifest fields are missing or unexpected")
    if (
        manifest.get("contract_id") != "thailand-digital-islands-renderer-v1"
        or manifest.get("schema_version") != 1
        or manifest.get("island_id") != "geo:th:island:ko-pha-ngan"
        or manifest.get("release_version") != release_version
        or release_version != "0.5.2"
    ):
        raise ValueError("renderer manifest identity or release version mismatch")

    expected_attribution = {
        "basemap": "Protomaps © OpenStreetMap contributors",
        "terrain": (
            "Mapzen Terrain Tiles; SRTM and GMTED2010 data courtesy of the U.S. Geological Survey; "
            "ETOPO1 courtesy of NOAA/NCEI. Not for navigation."
        ),
    }
    if manifest.get("attribution") != expected_attribution:
        raise ValueError("renderer attribution contract mismatch")

    expected_dependencies = {
        "maplibre": {
            "license_path": "assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.LICENSE.txt",
            "script_path": "assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.js",
            "style_path": "assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.css",
            "version": "5.18.0",
        },
        "pmtiles": {
            "license_path": "assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.LICENSE.txt",
            "script_path": "assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.js",
            "version": "4.5.0",
        },
    }
    if manifest.get("dependencies") != expected_dependencies:
        raise ValueError("renderer dependency contract mismatch")

    expected_basemap = {
        "bounds": RENDERER_BOUNDS,
        "format": "pmtiles",
        "path": "assets/digital-islands/data/koh-phangan-basemap-20260811.pmtiles",
    }
    if manifest.get("basemap") != expected_basemap:
        raise ValueError("renderer basemap contract mismatch")

    expected_satellite = {
        "attribution": "Contains modified Copernicus Sentinel data 2026",
        "bounds": RENDERER_BOUNDS,
        "format": "webp",
        "height": 2372,
        "observed_at": "2026-03-26T03:55:36.171000Z",
        "path": "assets/digital-islands/imagery/koh-phangan-sentinel2-20260326.webp",
        "projection": "EPSG:3857",
        "source_item_id": "S2B_47PPL_20260326_0_L2A",
        "width": 2227,
    }
    if manifest.get("satellite") != expected_satellite:
        raise ValueError("renderer satellite contract mismatch")

    expected_tiles = expected_renderer_terrain_tiles()
    terrain = manifest.get("terrain")
    expected_terrain_keys = {
        "base_path",
        "bounds",
        "format",
        "inventory_sha256",
        "max_zoom",
        "min_zoom",
        "tile_count",
        "tile_ranges",
        "tiles",
        "total_bytes",
        "url_template",
    }
    if not isinstance(terrain, dict) or set(terrain) != expected_terrain_keys:
        raise ValueError("renderer terrain fields are missing or unexpected")
    if (
        terrain.get("base_path") != "assets/digital-islands/terrain/20260811"
        or terrain.get("bounds") != RENDERER_BOUNDS
        or terrain.get("format") != "terrarium_png"
        or terrain.get("inventory_sha256") != "cde017fa9a5443e60d0dfba32984e9fcbdec357644b558b0fa128eb935444918"
        or terrain.get("max_zoom") != 13
        or terrain.get("min_zoom") != 8
        or terrain.get("tile_count") != 58
        or terrain.get("tile_ranges") != RENDERER_TERRAIN_RANGES
        or terrain.get("tiles") != expected_tiles
        or terrain.get("total_bytes") != 1092999
        or terrain.get("url_template") != "assets/digital-islands/terrain/20260811/{z}/{x}/{y}.png"
    ):
        raise ValueError("renderer terrain contract mismatch")

    inventory = manifest.get("inventory")
    if not isinstance(inventory, dict):
        raise ValueError("renderer inventory must be an object")
    expected_inventory_paths = sorted(
        {
            expected_basemap["path"],
            expected_satellite["path"],
            expected_dependencies["maplibre"]["license_path"],
            expected_dependencies["maplibre"]["script_path"],
            expected_dependencies["maplibre"]["style_path"],
            expected_dependencies["pmtiles"]["license_path"],
            expected_dependencies["pmtiles"]["script_path"],
            *expected_tiles,
        }
    )
    if sorted(inventory) != expected_inventory_paths or len(inventory) != 65:
        raise ValueError("renderer inventory boundary mismatch")

    important_receipts = {
        "assets/digital-islands/data/koh-phangan-basemap-20260811.pmtiles": (
            1205287,
            "9a8614610ea58d282989346763cd5900ad02d54d8bc7104eda799bea79799ded",
        ),
        "assets/digital-islands/imagery/koh-phangan-sentinel2-20260326.webp": (
            621958,
            "9ee99de2269a040c35be113bad44d444fc76c4dc136b36d4afe5cb57b5e3de2a",
        ),
        "assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.LICENSE.txt": (
            5984,
            "ee5fc05a0677eaf69601d2c7db0d9ecd6cc27c3abc1d0733bc9ed34707cf8ef2",
        ),
        "assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.css": (
            69541,
            "e4711ce4f6225070a859c7a40dc4d2e4e1ab76a5c71a12b4a65227ed2bf362fd",
        ),
        "assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.js": (
            1022148,
            "bc7101606a893f9018ac4a0d27f7de07d00fb3852231951fcf3dd900796ddfd7",
        ),
        "assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.LICENSE.txt": (
            2879,
            "4ca0c13e0b394eebfefc94cc1ba825b99b120283d98dd5ee2f6bc733bb8a5f77",
        ),
        "assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.js": (
            20229,
            "caf981bc46f6327ee7e65d5dc964d89d38a69f60edca2bd4c5c890c21b554c6c",
        ),
    }
    terrain_receipts: dict[str, dict[str, Any]] = {}
    terrain_total_bytes = 0
    for relative, record in inventory.items():
        posix = PurePosixPath(relative)
        if posix.is_absolute() or ".." in posix.parts or "\\" in relative:
            raise ValueError(f"unsafe renderer inventory path: {relative}")
        if not isinstance(record, dict) or set(record) != {"bytes", "sha256"}:
            raise ValueError(f"renderer receipt is malformed: {relative}")
        if (
            type(record["bytes"]) is not int
            or record["bytes"] <= 0
            or not isinstance(record["sha256"], str)
            or re.fullmatch(r"[0-9a-f]{64}", record["sha256"]) is None
        ):
            raise ValueError(f"renderer receipt value is invalid: {relative}")
        source = root / Path(*posix.parts)
        if not source.is_file() or source.is_symlink():
            raise ValueError(f"renderer asset is missing or unsafe: {relative}")
        payload = source.read_bytes()
        if record["bytes"] != len(payload) or record["sha256"] != sha256_bytes(payload):
            raise ValueError(f"renderer asset receipt mismatch: {relative}")
        if relative not in entries:
            raise ValueError(f"renderer asset is absent from package-files.txt: {relative}")
        if relative in expected_tiles:
            if not payload.startswith(b"\x89PNG\r\n\x1a\n"):
                raise ValueError(f"renderer terrain tile is not PNG: {relative}")
            terrain_receipts[relative] = record
            terrain_total_bytes += record["bytes"]

    for relative, (expected_bytes, expected_hash) in important_receipts.items():
        if inventory.get(relative) != {"bytes": expected_bytes, "sha256": expected_hash}:
            raise ValueError(f"renderer pinned receipt changed: {relative}")

    satellite_payload = (root / expected_satellite["path"]).read_bytes()
    if satellite_payload[:4] != b"RIFF" or satellite_payload[8:12] != b"WEBP":
        raise ValueError("renderer satellite asset is not a WebP container")
    terrain_canonical = "".join(
        f"{relative}\0{terrain_receipts[relative]['bytes']}\0{terrain_receipts[relative]['sha256']}\n"
        for relative in sorted(terrain_receipts)
    ).encode("utf-8")
    if (
        len(terrain_receipts) != 58
        or terrain_total_bytes != terrain["total_bytes"]
        or sha256_bytes(terrain_canonical) != terrain["inventory_sha256"]
    ):
        raise ValueError("renderer terrain receipt digest mismatch")

    bounded_directories = (
        "assets/digital-islands/data",
        "assets/digital-islands/imagery",
        "assets/digital-islands/terrain/20260811",
        "assets/digital-islands/vendor/maplibre-gl/5.18.0",
        "assets/digital-islands/vendor/pmtiles/4.5.0",
    )
    actual_inventory: list[str] = []
    for relative_directory in bounded_directories:
        directory = root / relative_directory
        if not directory.is_dir() or directory.is_symlink():
            raise ValueError(f"renderer inventory directory is missing or unsafe: {relative_directory}")
        for candidate in directory.rglob("*"):
            if candidate.is_symlink():
                raise ValueError(f"renderer inventory contains a symbolic link: {candidate}")
            if candidate.is_file():
                actual_inventory.append(candidate.relative_to(root).as_posix())
            elif not candidate.is_dir():
                raise ValueError(f"renderer inventory contains an invalid entry: {candidate}")
    if sorted(actual_inventory) != expected_inventory_paths:
        raise ValueError("renderer filesystem inventory disagrees with its manifest")

    for required_path in (RENDERER_MANIFEST_PATH, RENDERER_LOADER_PATH):
        if required_path not in entries:
            raise ValueError(f"renderer contract file is absent from package-files.txt: {required_path}")
        required_file = root / required_path
        if not required_file.is_file() or required_file.is_symlink():
            raise ValueError(f"renderer contract file is missing or unsafe: {required_path}")

    loader_text = loader_path.read_text(encoding="utf-8")
    for marker in (
        "const CONTRACT_ID  = 'thailand-digital-islands-renderer-v1';",
        "const MANIFEST_SHA256 = 'bf24b0b134e8c6abd3e38d1f7c2b712f7057d636950accdf61f1fe9eed864bb3';",
        "const RELEASE_VERSION = '0.5.2';",
        "const MAPLIBRE_VERSION = '5.18.0';",
        "const PMTILES_VERSION = '4.5.0';",
        "const TERRAIN_TILE_COUNT = 58;",
    ):
        if marker not in loader_text:
            raise ValueError(f"renderer loader contract marker is missing: {marker}")

    loader_payload = loader_path.read_bytes()
    return {
        "attribution": manifest["attribution"],
        "basemap": manifest["basemap"],
        "contract_id": manifest["contract_id"],
        "dependencies": manifest["dependencies"],
        "inventory": inventory,
        "inventory_count": len(inventory),
        "island_id": manifest["island_id"],
        "loader": {
            "bytes": len(loader_payload),
            "path": RENDERER_LOADER_PATH,
            "sha256": sha256_bytes(loader_payload),
        },
        "manifest": {
            "bytes": len(manifest_payload),
            "path": RENDERER_MANIFEST_PATH,
            "sha256": sha256_bytes(manifest_payload),
        },
        "parity": "pass",
        "release_version": manifest["release_version"],
        "satellite": manifest["satellite"],
        "schema_version": manifest["schema_version"],
        "terrain": manifest["terrain"],
    }


def digital_islands_evidence(root: Path, entries: list[str], release_version: str) -> dict[str, Any]:
    """Validate and freeze the public-only Koh Phangan artifact lineage."""
    source_path = root / "data" / "digital-islands" / "koh-phangan.json"
    schema_path = root / "data" / "digital-islands" / "island-world.schema.json"
    manifest_path = root / "resources" / "digital-islands" / "manifest.json"
    registry_path = root / "resources" / "digital-islands" / "registry.php"
    notice_path = root / "THIRD-PARTY-DATA-NOTICES.md"
    template_path = root / "templates" / "digital-islands" / "koh-phangan.php"
    public_view_path = root / "src" / "DigitalIslands" / "PublicView.php"

    source = json_no_duplicates(source_path)
    manifest = json_no_duplicates(manifest_path)
    expected_manifest_keys = {
        "artifacts",
        "checked_on",
        "contract_id",
        "counts",
        "dataset_version",
        "publication_state",
        "schema_sha256",
        "schema_version",
        "source_digest",
    }
    if set(manifest) != expected_manifest_keys:
        raise ValueError("Digital Islands manifest fields are missing or unexpected")
    if manifest.get("contract_id") != "thailand-digital-island-world-v1":
        raise ValueError("Digital Islands contract ID mismatch")
    if manifest.get("schema_version") != 1:
        raise ValueError("Digital Islands schema version mismatch")
    if manifest.get("publication_state") != "live":
        raise ValueError("Digital Islands artifact is not approved for Live publication")
    if source.get("publication_state") != "live":
        raise ValueError("Digital Islands source is not approved for Live publication")
    if source.get("contract_id") != manifest["contract_id"]:
        raise ValueError("Digital Islands source and manifest contract IDs disagree")
    if source.get("dataset_version") != manifest.get("dataset_version"):
        raise ValueError("Digital Islands source and manifest dataset versions disagree")
    if source.get("checked_on") != manifest.get("checked_on"):
        raise ValueError("Digital Islands source and manifest review dates disagree")

    counts = manifest.get("counts")
    expected_count_keys = {
        "canary_map_entities",
        "entities",
        "entity_types",
        "layers",
        "official_tools",
        "public_map_entities",
        "sources",
    }
    if not isinstance(counts, dict) or set(counts) != expected_count_keys:
        raise ValueError("Digital Islands counts are missing or unexpected")
    if (
        counts["entities"] != 49
        or counts["canary_map_entities"] != 49
        or counts["public_map_entities"] != 49
        or counts["layers"] != 24
        or counts["official_tools"] != 3
        or counts["sources"] != 38
    ):
        raise ValueError("Digital Islands reviewed public counts mismatch")
    entity_types = counts.get("entity_types")
    if (
        not isinstance(entity_types, dict)
        or not entity_types
        or not all(type(value) is int and value > 0 for value in entity_types.values())
        or sum(entity_types.values()) != 49
    ):
        raise ValueError("Digital Islands entity type counts mismatch")

    entities = source.get("entities")
    if not isinstance(entities, list) or len(entities) != 49:
        raise ValueError("Digital Islands source entity count mismatch")
    forbidden_entity_types = {"legal_overlay", "professional_service", "property_offer"}
    forbidden_record_keys = {"conflicts", "holds"}
    for entity in entities:
        if not isinstance(entity, dict):
            raise ValueError("Digital Islands source contains a malformed entity")
        if forbidden_record_keys.intersection(entity):
            raise ValueError("Digital Islands source contains internal review fields")
        if entity.get("entity_type") in forbidden_entity_types:
            raise ValueError("Digital Islands source contains a private entity type")
        if entity.get("public_state") != "map_only" or entity.get("indexing_policy") != "map_only":
            raise ValueError("Digital Islands source contains a non-public entity")

    schema_payload = schema_path.read_bytes()
    if manifest.get("schema_sha256") != sha256_bytes(schema_payload):
        raise ValueError("Digital Islands schema hash mismatch")
    canonical_source = (
        json.dumps(source, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n"
    ).encode("utf-8")
    if manifest.get("source_digest") != sha256_bytes(canonical_source):
        raise ValueError("Digital Islands source digest mismatch")

    artifacts = manifest.get("artifacts")
    expected_artifacts = {"resources/digital-islands/registry.php"}
    if not isinstance(artifacts, dict) or set(artifacts) != expected_artifacts:
        raise ValueError("Digital Islands artifact inventory mismatch")
    artifact_record = artifacts["resources/digital-islands/registry.php"]
    if not isinstance(artifact_record, dict) or set(artifact_record) != {"bytes", "sha256"}:
        raise ValueError("Digital Islands registry evidence is malformed")
    registry_payload = registry_path.read_bytes()
    if (
        artifact_record["bytes"] != len(registry_payload)
        or artifact_record["sha256"] != sha256_bytes(registry_payload)
    ):
        raise ValueError("Digital Islands registry evidence mismatch")

    notice_payload = notice_path.read_bytes()
    notice_text = notice_payload.decode("utf-8")
    notice_normalized = re.sub(r"\s+", " ", notice_text)
    for required in (
        "© OpenStreetMap contributors",
        "Open Data Commons Open Database License 1.0 (ODbL)",
        "https://opendatacommons.org/licenses/odbl/1-0/",
        "https://www.openstreetmap.org/copyright",
        "MapLibre GL JS 5.18.0",
        "PMTiles JavaScript 4.5.0",
        "fflate 0.8.2",
        "MIT License",
        "Copyright (c) 2023 Arjun Barrett",
        "https://github.com/101arrowz/fflate/blob/v0.8.2/LICENSE",
        "https://docs.protomaps.com/basemaps/attribution",
        "Natural Earth data, which is in the public domain",
        "https://www.naturalearthdata.com/about/terms-of-use/",
        "ESA WorldCover 2021",
        "https://creativecommons.org/licenses/by/4.0/",
        "https://esa-worldcover.org/en/data-access",
        "Mapzen Terrain Tiles",
        "https://github.com/tilezen/joerd/blob/master/docs/attribution.md",
        "https://www.usgs.gov/centers/eros/science/usgs-eros-archive-digital-elevation-shuttle-radar-topography-mission-srtm",
        "https://www.usgs.gov/coastal-changes-and-impacts/gmted2010",
        "https://www.ncei.noaa.gov/products/etopo-global-relief-model",
        "Contains modified Copernicus Sentinel data 2026",
        "S2B_47PPL_20260326_0_L2A",
        "https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/47/P/PL/2026/3/S2B_47PPL_20260326_0_L2A/TCI.tif",
        "https://registry.opendata.aws/sentinel-2-l2a-cogs/",
        "https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice",
        "orientation-only historical imagery, not current parcel, title, or buildability evidence",
    ):
        if required not in notice_normalized:
            raise ValueError(f"third-party software or data notice is incomplete: {required}")
    template_text = template_path.read_text(encoding="utf-8")
    public_view_text = public_view_path.read_text(encoding="utf-8")
    if "https://www.openstreetmap.org/copyright" not in template_text:
        raise ValueError("Digital Islands visible OpenStreetMap attribution is missing")
    if (
        "'attribution'" not in public_view_text
        or "https://www.openstreetmap.org/copyright" not in public_view_text
    ):
        raise ValueError("Digital Islands REST attribution contract is missing")
    if (
        "Contains modified Copernicus Sentinel data 2026. Image observed 26.03.2026."
        not in public_view_text
        or 'datetime="2026-03-26T03:55:36.171000Z"' not in template_text
        or "26.03.2026" not in template_text
    ):
        raise ValueError("Digital Islands visible Sentinel observation date is missing")

    return {
        "artifacts": artifacts,
        "checked_on": manifest["checked_on"],
        "contract_id": manifest["contract_id"],
        "counts": counts,
        "dataset_version": manifest["dataset_version"],
        "manifest": "resources/digital-islands/manifest.json",
        "manifest_sha256": sha256_bytes(manifest_path.read_bytes()),
        "license_notice": {
            "bytes": len(notice_payload),
            "path": "THIRD-PARTY-DATA-NOTICES.md",
            "sha256": sha256_bytes(notice_payload),
        },
        "parity": "pass",
        "publication_state": manifest["publication_state"],
        "renderer": renderer_evidence(root, entries, release_version),
        "schema": {
            "bytes": len(schema_payload),
            "path": "data/digital-islands/island-world.schema.json",
            "sha256": manifest["schema_sha256"],
        },
        "schema_version": manifest["schema_version"],
        "source": {
            "bytes": len(source_path.read_bytes()),
            "path": "data/digital-islands/koh-phangan.json",
            "sha256": sha256_bytes(source_path.read_bytes()),
        },
        "source_digest": manifest["source_digest"],
    }


def read_inventory(root: Path) -> list[str]:
    inventory_path = root / "package-files.txt"
    entries = [
        line.strip()
        for line in inventory_path.read_text(encoding="utf-8").splitlines()
        if line.strip() and not line.lstrip().startswith("#")
    ]

    if entries != sorted(entries):
        raise ValueError("package-files.txt must be sorted")
    if len(entries) != len(set(entries)):
        raise ValueError("package-files.txt contains duplicates")
    if not entries:
        raise ValueError("package-files.txt is empty")

    for entry in entries:
        posix = PurePosixPath(entry)
        lower_name = posix.name.lower()
        if posix.is_absolute() or ".." in posix.parts or "\\" in entry:
            raise ValueError(f"unsafe package path: {entry}")
        if lower_name in FORBIDDEN_NAMES or lower_name.startswith(".env."):
            raise ValueError(f"forbidden package filename: {entry}")
        if posix.suffix.lower() in FORBIDDEN_SUFFIXES:
            raise ValueError(f"forbidden package suffix: {entry}")

        source = root / Path(*posix.parts)
        if not source.is_file() or source.is_symlink():
            raise ValueError(f"missing, non-file, or symlinked package entry: {entry}")

        payload = source.read_bytes()
        for label, pattern in SECRET_PATTERNS.items():
            if pattern.search(payload):
                raise ValueError(f"secret scanner match ({label}) in package entry: {entry}")

    return entries


def validate_vendor_tree(root: Path) -> dict[str, Any]:
    vendor_root = root / "lib" / "plugin-update-checker"
    manifest_path = vendor_root / "VENDOR-MANIFEST.sha256"
    manifest_entries: dict[str, str] = {}

    for line in manifest_path.read_text(encoding="utf-8").splitlines():
        match = re.fullmatch(r"([0-9a-f]{64})  ([^\\]+)", line)
        if not match:
            raise ValueError("vendor manifest contains an invalid line")
        digest, relative = match.groups()
        path = PurePosixPath(relative)
        if path.is_absolute() or ".." in path.parts or relative in manifest_entries:
            raise ValueError(f"vendor manifest contains an unsafe or duplicate path: {relative}")
        manifest_entries[relative] = digest

    actual_paths = sorted(
        path.relative_to(vendor_root).as_posix()
        for path in vendor_root.rglob("*")
        if path.is_file() and path.name not in {"VENDOR-MANIFEST.sha256", "VENDOR-RECEIPT.md"}
    )
    if list(manifest_entries) != actual_paths:
        raise ValueError("vendor manifest inventory disagrees with the vendored upstream tree")

    total_bytes = 0
    for relative, expected_hash in manifest_entries.items():
        source = vendor_root / Path(*PurePosixPath(relative).parts)
        payload = source.read_bytes()
        total_bytes += len(payload)
        if sha256_bytes(payload) != expected_hash:
            raise ValueError(f"vendor file hash mismatch: {relative}")

    manifest_payload = manifest_path.read_bytes()
    return {
        "files": len(manifest_entries),
        "bytes": total_bytes,
        "manifest": "lib/plugin-update-checker/VENDOR-MANIFEST.sha256",
        "manifest_sha256": sha256_bytes(manifest_payload),
        "upstream_commit": "a2db6871deec989a74e1f90fafc6d58ae526a879",
        "upstream_tag": "v5.6",
    }


def header_value(text: str, label: str) -> str:
    match = re.search(rf"^\s*(?:\*\s*)?{re.escape(label)}:\s*(.+?)\s*$", text, re.MULTILINE)
    if not match:
        raise ValueError(f"missing plugin header: {label}")
    return match.group(1).strip()


def read_release_contract(root: Path) -> dict[str, str]:
    main = (root / "thailand-platform.php").read_text(encoding="utf-8")
    readme = (root / "readme.txt").read_text(encoding="utf-8")
    manifest = json_no_duplicates(root / "release.json")

    manifest_keys = {
        "author",
        "download_url",
        "homepage",
        "last_updated",
        "name",
        "requires",
        "requires_php",
        "sections",
        "slug",
        "tested",
        "version",
    }
    if set(manifest) != manifest_keys:
        raise ValueError("release manifest fields are missing or unexpected")

    version = header_value(main, "Version")
    requires = header_value(main, "Requires at least")
    requires_php = header_value(main, "Requires PHP")
    constant = re.search(
        r"define\(\s*'THAILAND_PLATFORM_VERSION'\s*,\s*'([0-9]+(?:\.[0-9]+)+)'\s*\);",
        main,
    )
    if not constant or constant.group(1) != version:
        raise ValueError("plugin header and version constant disagree")

    readme_fields = {
        "version": header_value(readme, "Stable tag"),
        "requires": header_value(readme, "Requires at least"),
        "tested": header_value(readme, "Tested up to"),
        "requires_php": header_value(readme, "Requires PHP"),
    }
    expected = {
        "name": "Thailand Platform",
        "slug": PLUGIN_SLUG,
        "version": version,
        "author": "thai-land.co.il",
        "homepage": "https://thai-land.co.il/",
        "requires": requires,
        "tested": readme_fields["tested"],
        "requires_php": requires_php,
        "download_url": (
            "https://raw.githubusercontent.com/The-new-ben/thai-land-platform/main/"
            f"plugin-dist/{version}/{PLUGIN_SLUG}-{version}.zip"
        ),
    }

    if readme_fields["version"] != version:
        raise ValueError("readme stable tag disagrees with plugin version")
    if readme_fields["requires"] != requires:
        raise ValueError("readme WordPress minimum disagrees with plugin header")
    if readme_fields["requires_php"] != requires_php:
        raise ValueError("readme PHP minimum disagrees with plugin header")

    for key, value in expected.items():
        if manifest.get(key) != value:
            raise ValueError(f"release manifest field disagrees: {key}")
    if not isinstance(manifest.get("last_updated"), str) or not manifest["last_updated"].strip():
        raise ValueError("release manifest last_updated is missing")
    sections = manifest.get("sections")
    if (
        not isinstance(sections, dict)
        or set(sections) != {"changelog"}
        or not isinstance(sections.get("changelog"), str)
    ):
        raise ValueError("release manifest changelog is missing")

    return {
        "version": version,
        "requires": requires,
        "tested": readme_fields["tested"],
        "requires_php": requires_php,
    }


def resolve_php_binary(requested: str | None) -> Path:
    candidate = requested or shutil.which("php")
    if not candidate:
        raise ValueError("PHP executable was not found")
    resolved = Path(candidate).resolve()
    if not resolved.is_file():
        raise ValueError(f"PHP executable is not a file: {resolved}")
    return resolved


def resolve_node_binary(requested: str | None) -> Path:
    candidate = requested or shutil.which("node")
    if not candidate:
        raise ValueError("Node executable was not found")
    resolved = Path(candidate).resolve()
    if not resolved.is_file():
        raise ValueError(f"Node executable is not a file: {resolved}")
    return resolved


def run_checked(
    command: list[str],
    cwd: Path,
    environment: dict[str, str] | None = None,
    timeout_seconds: float | None = None,
) -> str:
    try:
        completed = subprocess.run(
            command,
            cwd=cwd,
            capture_output=True,
            text=True,
            check=False,
            env=environment,
            timeout=timeout_seconds,
        )
    except subprocess.TimeoutExpired as error:
        stdout = error.stdout.decode("utf-8", "replace") if isinstance(error.stdout, bytes) else (error.stdout or "")
        stderr = error.stderr.decode("utf-8", "replace") if isinstance(error.stderr, bytes) else (error.stderr or "")
        output = (stdout + stderr).strip()
        raise ValueError(
            f"QA command timed out after {timeout_seconds:g} seconds: {' '.join(command)}\n{output}"
        ) from error
    output = (completed.stdout + completed.stderr).strip()
    if completed.returncode != 0:
        raise ValueError(f"QA command failed ({completed.returncode}): {' '.join(command)}\n{output}")
    return output


def renderer_browser_evidence(root: Path, node_bin: Path) -> tuple[str, dict[str, Any]]:
    """Run the real local browser contract and retain a bounded receipt summary."""
    with tempfile.TemporaryDirectory(prefix="thp-di-browser-acceptance-") as temporary:
        output_root = Path(temporary).resolve()
        output = run_checked(
            [
                str(node_bin),
                str(root / RENDERER_BROWSER_SCRIPT),
                "--output",
                str(output_root),
            ],
            root,
            timeout_seconds=600,
        )
        output_lines = [line.strip() for line in output.splitlines() if line.strip()]
        if output_lines.count(RENDERER_BROWSER_PASS) != 1:
            raise ValueError(f"Unexpected real-browser acceptance output: {output}")
        report_lines = [line for line in output_lines if line.startswith("Report: ")]
        if len(report_lines) != 1:
            raise ValueError("Real-browser acceptance did not emit one report path")
        reported_path = Path(report_lines[0][len("Report: ") :]).resolve()
        report_path = output_root / "acceptance-report.json"
        if reported_path != report_path or not report_path.is_file() or report_path.is_symlink():
            raise ValueError("Real-browser acceptance report escaped its bounded output")

        report = json_no_duplicates(report_path)
        expected_report_keys = {
            "assertions",
            "contract_id",
            "finished_at",
            "fixture",
            "playwright_cli",
            "release",
            "results",
            "reviewed_assets",
            "started_at",
        }
        if set(report) != expected_report_keys:
            raise ValueError("Real-browser acceptance report fields are missing or unexpected")
        if (
            report.get("contract_id") != "thp-digital-islands-maplibre-browser-v1"
            or report.get("release") != "0.5.2"
            or report.get("playwright_cli")
            != {"package": "@playwright/cli@0.1.18", "version": "0.1.18"}
        ):
            raise ValueError("Real-browser acceptance identity or Playwright pin mismatch")
        expected_assertion_keys = {
            "all_scenarios_passed",
            "asset_failure_fail_closed",
            "data_saver_list_only",
            "no_third_party_requests",
            "real_maplibre_execution",
        }
        assertions = report.get("assertions")
        if (
            not isinstance(assertions, dict)
            or set(assertions) != expected_assertion_keys
            or not all(value is True for value in assertions.values())
        ):
            raise ValueError("Real-browser aggregate assertions did not all pass")
        expected_fixture = {
            "contract_id": "thp-digital-islands-maplibre-browser-v1",
            "coordinate_entity_count": 27,
            "entity_count": 49,
            "island_id": "geo:th:island:ko-pha-ngan",
            "playwright_cli_package": "@playwright/cli@0.1.18",
            "status": "ready",
        }
        if report.get("fixture") != expected_fixture:
            raise ValueError("Real-browser fixture identity mismatch")

        try:
            started_at = datetime.fromisoformat(str(report["started_at"]).replace("Z", "+00:00"))
            finished_at = datetime.fromisoformat(str(report["finished_at"]).replace("Z", "+00:00"))
        except ValueError as error:
            raise ValueError("Real-browser acceptance timestamps are invalid") from error
        if (
            started_at.tzinfo is None
            or finished_at.tzinfo is None
            or finished_at < started_at
            or finished_at - started_at > timedelta(minutes=15)
        ):
            raise ValueError("Real-browser acceptance duration is invalid")

        results = report.get("results")
        if not isinstance(results, list) or [result.get("scenario") for result in results if isinstance(result, dict)] != RENDERER_BROWSER_SCENARIOS:
            raise ValueError("Real-browser acceptance scenario inventory mismatch")
        expected_reviewed_assets = {
            "client": "assets/digital-islands/digital-islands.js",
            "maplibre": "assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.js",
            "pmtiles": "assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.js",
            "satellite": "assets/digital-islands/imagery/koh-phangan-sentinel2-20260326.webp",
            "vector": "assets/digital-islands/data/koh-phangan-basemap-20260811.pmtiles",
        }
        reviewed_assets = report.get("reviewed_assets")
        if not isinstance(reviewed_assets, dict) or set(reviewed_assets) != set(expected_reviewed_assets):
            raise ValueError("Real-browser reviewed asset inventory mismatch")
        reviewed_asset_receipts: dict[str, dict[str, Any]] = {}
        for label, relative in expected_reviewed_assets.items():
            record = reviewed_assets[label]
            if not isinstance(record, dict) or set(record) != {"bytes", "path", "sha256"}:
                raise ValueError(f"Real-browser reviewed asset receipt is malformed: {label}")
            source = (root / relative).resolve()
            if Path(str(record["path"])).resolve() != source or not source.is_file() or source.is_symlink():
                raise ValueError(f"Real-browser reviewed asset path mismatch: {label}")
            payload = source.read_bytes()
            if (
                type(record["bytes"]) is not int
                or record["bytes"] != len(payload)
                or record["sha256"] != sha256_bytes(payload)
            ):
                raise ValueError(f"Real-browser reviewed asset receipt mismatch: {label}")
            reviewed_asset_receipts[label] = {
                "bytes": record["bytes"],
                "path": relative,
                "sha256": record["sha256"],
            }

        expected_evidence_keys = {
            "active_renderer",
            "canvas",
            "coordinate_entity_count",
            "csp_violations",
            "data_saver",
            "drawer",
            "entity_count",
            "hash",
            "map_count",
            "map_errors",
            "map_idle_event",
            "map_load_event",
            "map_loaded",
            "maplibre_asset_url",
            "maplibre_attribution_text",
            "maplibre_constructor",
            "maplibre_runtime_version",
            "marker_count",
            "orientation_canvas",
            "poster_hidden",
            "projection",
            "promise_rejections",
            "reduced_motion",
            "scenario",
            "selected_card_count",
            "status",
            "style_layers",
            "style_loaded",
            "style_sources",
            "terrain",
            "visible_attribution_text",
            "webgl2",
            "window_errors",
        }
        expected_network_keys = {
            "broken_asset_requests",
            "camera_transition_abortions",
            "pmtiles_range_requests",
            "pmtiles_requests",
            "request_budget",
            "request_count",
            "rest_requests",
            "satellite_requests",
            "terrain_requests",
            "third_party_requests",
            "unexpected_failed_requests",
        }
        scenario_contracts = {
            "desktop-3d": {
                "active_renderer": "3d",
                "data_saver": False,
                "drawer_visible": True,
                "map_count": 3,
                "map_rendered": True,
                "marker_count": 27,
                "orientation_canvas": False,
                "pmtiles": True,
                "poster_hidden": True,
                "projection": "globe",
                "reduced_motion": False,
                "satellite": True,
                "selected_card_count": 1,
                "terrain": True,
                "webgl2": True,
            },
            "desktop-2d": {
                "active_renderer": "2d",
                "data_saver": False,
                "drawer_visible": True,
                "map_count": 1,
                "map_rendered": True,
                "marker_count": 27,
                "orientation_canvas": False,
                "pmtiles": True,
                "poster_hidden": True,
                "projection": "mercator",
                "reduced_motion": False,
                "satellite": False,
                "selected_card_count": 1,
                "terrain": False,
                "webgl2": True,
            },
            "mobile-2d": {
                "active_renderer": "2d",
                "data_saver": False,
                "drawer_visible": True,
                "map_count": 1,
                "map_rendered": True,
                "marker_count": 27,
                "orientation_canvas": False,
                "pmtiles": True,
                "poster_hidden": True,
                "projection": "mercator",
                "reduced_motion": False,
                "satellite": False,
                "selected_card_count": 1,
                "terrain": False,
                "webgl2": True,
            },
            "reduced-motion": {
                "active_renderer": "2d",
                "data_saver": False,
                "drawer_visible": False,
                "map_count": 1,
                "map_rendered": True,
                "marker_count": 27,
                "orientation_canvas": False,
                "pmtiles": True,
                "poster_hidden": True,
                "projection": "mercator",
                "reduced_motion": True,
                "satellite": False,
                "selected_card_count": 0,
                "terrain": False,
                "webgl2": True,
            },
            "data-saver": {
                "active_renderer": "list",
                "data_saver": True,
                "drawer_visible": False,
                "map_count": 0,
                "map_rendered": False,
                "marker_count": 0,
                "orientation_canvas": False,
                "pmtiles": False,
                "poster_hidden": False,
                "projection": "",
                "reduced_motion": False,
                "satellite": False,
                "selected_card_count": 0,
                "terrain": False,
                "webgl2": False,
            },
            "no-webgl": {
                "active_renderer": "preview",
                "data_saver": False,
                "drawer_visible": False,
                "map_count": 0,
                "map_rendered": False,
                "marker_count": 0,
                "orientation_canvas": True,
                "pmtiles": False,
                "poster_hidden": True,
                "projection": "",
                "reduced_motion": False,
                "satellite": False,
                "selected_card_count": 0,
                "terrain": False,
                "webgl2": False,
            },
            "asset-failure": {
                "active_renderer": "preview",
                "data_saver": False,
                "drawer_visible": False,
                "map_count": 2,
                "map_rendered": False,
                "marker_count": 0,
                "orientation_canvas": True,
                "pmtiles": False,
                "poster_hidden": True,
                "projection": "",
                "reduced_motion": False,
                "satellite": False,
                "selected_card_count": 0,
                "terrain": False,
                "webgl2": False,
            },
        }
        artifact_receipts: dict[str, dict[str, dict[str, Any]]] = {}
        scenario_evidence: dict[str, dict[str, Any]] = {}
        for result in results:
            if not isinstance(result, dict) or set(result) != {
                "artifacts",
                "assertions",
                "browser",
                "console_errors",
                "evidence",
                "network",
                "passed",
                "scenario",
            }:
                raise ValueError("Real-browser scenario fields are missing or unexpected")
            scenario = result["scenario"]
            if result.get("passed") is not True:
                raise ValueError(f"Real-browser scenario did not pass: {scenario}")
            console_errors = result.get("console_errors")
            if (
                not isinstance(console_errors, list)
                or (scenario != "asset-failure" and console_errors)
                or (
                    scenario == "asset-failure"
                    and (
                        not console_errors
                        or not all(isinstance(message, str) and "503" in message for message in console_errors)
                    )
                )
            ):
                raise ValueError(f"Real-browser console error boundary mismatch: {scenario}")
            scenario_assertions = result.get("assertions")
            if (
                not isinstance(scenario_assertions, dict)
                or set(scenario_assertions) != {
                    "asset_failure_fail_closed",
                    "data_saver_list_only",
                    "entity_interaction",
                    "no_third_party_requests",
                    "real_maplibre_execution",
                }
                or not all(value is True for value in scenario_assertions.values())
            ):
                raise ValueError(f"Real-browser scenario assertions failed: {scenario}")
            evidence = result.get("evidence")
            network = result.get("network")
            browser = result.get("browser")
            contract = scenario_contracts[scenario]
            if (
                not isinstance(evidence, dict)
                or set(evidence) != expected_evidence_keys
                or evidence.get("entity_count") != 49
                or evidence.get("coordinate_entity_count") != 27
                or evidence.get("csp_violations") != []
                or evidence.get("window_errors") != []
                or evidence.get("promise_rejections") != []
                or evidence.get("scenario") != scenario
                or evidence.get("active_renderer") != contract["active_renderer"]
                or evidence.get("data_saver") is not contract["data_saver"]
                or type(evidence.get("map_count")) is not int
                or (
                    evidence["map_count"] < contract["map_count"]
                    if scenario == "desktop-3d"
                    else evidence["map_count"] != contract["map_count"]
                )
                or evidence.get("marker_count") != contract["marker_count"]
                or evidence.get("orientation_canvas") is not contract["orientation_canvas"]
                or evidence.get("poster_hidden") is not contract["poster_hidden"]
                or evidence.get("projection") != contract["projection"]
                or evidence.get("reduced_motion") is not contract["reduced_motion"]
                or evidence.get("selected_card_count") != contract["selected_card_count"]
                or evidence.get("webgl2") is not contract["webgl2"]
                or evidence.get("maplibre_constructor") is not True
                or not isinstance(evidence.get("status"), str)
                or not evidence["status"]
                or not isinstance(network, dict)
                or set(network) != expected_network_keys
                or network.get("third_party_requests") != []
            ):
                raise ValueError(f"Real-browser scenario evidence is incomplete: {scenario}")
            browser_viewport = browser.get("viewport") if isinstance(browser, dict) else None
            if (
                not isinstance(browser, dict)
                or set(browser) != {"user_agent", "viewport"}
                or not isinstance(browser.get("user_agent"), str)
                or not browser["user_agent"]
                or not isinstance(browser_viewport, dict)
                or set(browser_viewport) != {"height", "width"}
                or type(browser_viewport.get("height")) is not int
                or type(browser_viewport.get("width")) is not int
                or browser_viewport["height"] <= 0
                or browser_viewport["width"] <= 0
            ):
                raise ValueError(f"Real-browser identity is incomplete: {scenario}")

            map_state_fields = ("map_idle_event", "map_load_event", "map_loaded", "style_loaded")
            if any(type(evidence.get(field)) is not bool for field in map_state_fields):
                raise ValueError(f"Real-browser load events mismatch: {scenario}")
            if contract["map_rendered"]:
                if evidence["map_load_event"] is not True:
                    raise ValueError(f"Real-browser MapLibre load event is missing: {scenario}")
            elif any(evidence[field] for field in map_state_fields):
                raise ValueError(f"Real-browser fallback unexpectedly reported a map load: {scenario}")
            expected_style_sources = (
                ["basemap", "satellite", "terrain"]
                if scenario == "desktop-3d"
                else (["basemap"] if contract["map_rendered"] else [])
            )
            if evidence.get("style_sources") != expected_style_sources:
                raise ValueError(f"Real-browser style sources mismatch: {scenario}")
            if scenario == "desktop-3d" and not {
                "satellite-orientation-20260326",
                "terrain-hillshade",
                "buildings-extruded-reviewed-height",
            }.issubset(set(evidence.get("style_layers", []))):
                raise ValueError("Real-browser 3D renderer layers are incomplete")
            expected_terrain = {"exaggeration": 1.28, "source": "terrain"} if contract["terrain"] else None
            if evidence.get("terrain") != expected_terrain:
                raise ValueError(f"Real-browser terrain evidence mismatch: {scenario}")
            if contract["map_rendered"]:
                canvas = evidence.get("canvas")
                if (
                    not isinstance(canvas, dict)
                    or set(canvas) != {"height", "width"}
                    or type(canvas.get("height")) is not int
                    or type(canvas.get("width")) is not int
                    or canvas["height"] <= 0
                    or canvas["width"] <= 0
                    or "Protomaps © OpenStreetMap contributors" not in evidence.get("maplibre_attribution_text", "")
                ):
                    raise ValueError(f"Real-browser canvas or MapLibre attribution mismatch: {scenario}")
            elif evidence.get("canvas") is not None or evidence.get("style_layers") != []:
                raise ValueError(f"Real-browser fallback unexpectedly retained a map: {scenario}")

            drawer = evidence.get("drawer")
            visible_attribution = evidence.get("visible_attribution_text")
            if (
                not isinstance(drawer, dict)
                or set(drawer) != {"text", "visible"}
                or not isinstance(drawer.get("text"), str)
                or drawer.get("visible") is not contract["drawer_visible"]
                or not isinstance(visible_attribution, str)
                or "Contains modified Copernicus Sentinel data 2026. Image observed 26.03.2026."
                not in visible_attribution
            ):
                raise ValueError(f"Real-browser disclosure evidence mismatch: {scenario}")
            if contract["drawer_visible"] and "300 מטר" not in drawer["text"]:
                raise ValueError(f"Real-browser coordinate-accuracy disclosure is missing: {scenario}")

            numeric_network_fields = (
                "broken_asset_requests",
                "pmtiles_range_requests",
                "pmtiles_requests",
                "request_budget",
                "request_count",
                "rest_requests",
                "satellite_requests",
                "terrain_requests",
            )
            if any(type(network.get(field)) is not int or network[field] < 0 for field in numeric_network_fields):
                raise ValueError(f"Real-browser network counts are invalid: {scenario}")
            if (
                network["request_budget"] <= 0
                or network["request_count"] <= 0
                or network["request_count"] > network["request_budget"]
                or network["rest_requests"] != 3
                or network["pmtiles_range_requests"] != network["pmtiles_requests"]
                or (contract["pmtiles"] and network["pmtiles_requests"] <= 0)
                or (not contract["pmtiles"] and network["pmtiles_requests"] != 0)
                or (contract["terrain"] and network["terrain_requests"] <= 0)
                or (not contract["terrain"] and network["terrain_requests"] != 0)
                or (contract["satellite"] and network["satellite_requests"] <= 0)
                or (not contract["satellite"] and network["satellite_requests"] != 0)
            ):
                raise ValueError(f"Real-browser local request contract mismatch: {scenario}")
            camera_abortions = network.get("camera_transition_abortions")
            unexpected_failures = network.get("unexpected_failed_requests")
            if not isinstance(camera_abortions, list) or not isinstance(unexpected_failures, list):
                raise ValueError(f"Real-browser failure receipts are invalid: {scenario}")
            for failure in camera_abortions:
                failure_url = failure.get("url", "") if isinstance(failure, dict) else ""
                failure_range = failure.get("range", "") if isinstance(failure, dict) else ""
                same_origin_renderer_asset = (
                    re.match(
                        r"^http://127\.0\.0\.1:[0-9]+/wp-content/plugins/thailand-platform/assets/digital-islands/",
                        failure_url,
                    )
                    is not None
                )
                expected_terrain_abortion = (
                    "/terrain/20260811/" in failure_url
                    and re.search(r"\.png(?:[?#]|$)", failure_url) is not None
                )
                expected_pmtiles_abortion = (
                    "koh-phangan-basemap-20260811.pmtiles" in failure_url
                    and re.match(r"^bytes=[0-9]+-", failure_range, re.IGNORECASE) is not None
                )
                if (
                    not isinstance(failure, dict)
                    or set(failure) != {"error", "range", "url"}
                    or failure.get("error") != "net::ERR_ABORTED"
                    or not same_origin_renderer_asset
                    or not (expected_terrain_abortion or expected_pmtiles_abortion)
                ):
                    raise ValueError(f"Unexpected camera-transition request failure: {scenario}")
            if scenario == "asset-failure":
                if network["broken_asset_requests"] <= 0 or not unexpected_failures:
                    raise ValueError("Real-browser asset-failure probe did not observe its blocked asset")
                for failure in unexpected_failures:
                    if (
                        not isinstance(failure, dict)
                        or set(failure) != {"error", "range", "url"}
                        or "__acceptance_missing__" not in failure.get("url", "")
                    ):
                        raise ValueError("Real-browser asset-failure receipt is unexpected")
            elif network["broken_asset_requests"] != 0 or unexpected_failures:
                raise ValueError(f"Real-browser observed an unexpected asset failure: {scenario}")

            map_errors = evidence.get("map_errors")
            if (
                not isinstance(map_errors, list)
                or (scenario != "asset-failure" and map_errors)
                or (
                    scenario == "asset-failure"
                    and (not map_errors or not all(isinstance(message, str) and "503" in message for message in map_errors))
                )
            ):
                raise ValueError(f"Real-browser MapLibre error boundary mismatch: {scenario}")
            scenario_evidence[scenario] = {
                "accuracy_radius_m": 300 if contract["drawer_visible"] else None,
                "active_renderer": evidence["active_renderer"],
                "broken_asset_requests": network["broken_asset_requests"],
                "camera_transition_abortions": len(camera_abortions),
                "map_count": evidence["map_count"],
                "marker_count": evidence["marker_count"],
                "pmtiles_range_requests": network["pmtiles_range_requests"],
                "pmtiles_requests": network["pmtiles_requests"],
                "projection": evidence["projection"],
                "request_budget": network["request_budget"],
                "request_count": network["request_count"],
                "satellite_observation_date_visible": True,
                "satellite_requests": network["satellite_requests"],
                "terrain_requests": network["terrain_requests"],
                "unexpected_failed_requests": len(unexpected_failures),
                "webgl2": evidence["webgl2"],
            }

            artifacts = result.get("artifacts")
            if not isinstance(artifacts, dict) or set(artifacts) != {"console", "screenshot"}:
                raise ValueError(f"Real-browser artifacts are incomplete: {scenario}")
            artifact_receipts[scenario] = {}
            for label, record in artifacts.items():
                if not isinstance(record, dict) or set(record) != {"bytes", "path", "sha256"}:
                    raise ValueError(f"Real-browser artifact receipt is malformed: {scenario}/{label}")
                artifact_path = Path(str(record["path"])).resolve()
                try:
                    artifact_path.relative_to(output_root)
                except ValueError as error:
                    raise ValueError(f"Real-browser artifact escaped its output: {scenario}/{label}") from error
                if not artifact_path.is_file() or artifact_path.is_symlink():
                    raise ValueError(f"Real-browser artifact is missing or unsafe: {scenario}/{label}")
                payload = artifact_path.read_bytes()
                if (
                    type(record["bytes"]) is not int
                    or record["bytes"] != len(payload)
                    or not isinstance(record["sha256"], str)
                    or record["sha256"] != sha256_bytes(payload)
                ):
                    raise ValueError(f"Real-browser artifact receipt mismatch: {scenario}/{label}")
                if label == "screenshot" and (
                    record["bytes"] <= 0 or not payload.startswith(b"\x89PNG\r\n\x1a\n")
                ):
                    raise ValueError(f"Real-browser screenshot is not a PNG: {scenario}")
                artifact_receipts[scenario][label] = {
                    "bytes": record["bytes"],
                    "sha256": record["sha256"],
                }

        return RENDERER_BROWSER_PASS, {
            "artifacts": artifact_receipts,
            "assertions": assertions,
            "contract_id": report["contract_id"],
            "fixture": report["fixture"],
            "playwright_cli": report["playwright_cli"],
            "release": report["release"],
            "result": "pass",
            "reviewed_assets": reviewed_asset_receipts,
            "scenario_evidence": scenario_evidence,
            "scenarios": RENDERER_BROWSER_SCENARIOS,
        }


def run_qa(root: Path, entries: list[str], php_bin: Path, node_bin: Path) -> dict[str, Any]:
    php_files = [entry for entry in entries if entry.lower().endswith(".php")]
    for entry in php_files:
        run_checked([str(php_bin), "-l", str(root / Path(*PurePosixPath(entry).parts))], root)

    contract_environment = os.environ.copy()
    contract_environment["THAILAND_PLATFORM_NODE_BINARY"] = str(node_bin)

    javascript_files = sorted(
        [entry for entry in entries if entry.lower().endswith(".js")]
        + [
            "scripts/live_digital_island_acceptance.cjs",
            "scripts/live_guides_acceptance.cjs",
            "scripts/live_homepage_acceptance.cjs",
            "scripts/local_digital_island_browser_acceptance.cjs",
            "scripts/live_real_estate_acceptance.cjs",
            "scripts/live_seo_migration_acceptance.cjs",
            "scripts/live_sitewide_acceptance.cjs",
            "tests/digital-island-live-acceptance.test.cjs",
            "tests/fixtures/digital-islands-browser-probe.js",
            "tests/fixtures/digital-islands-browser-server.cjs",
            "tests/fixtures/digital-islands-live-browser-probe.js",
        ]
    )
    for entry in javascript_files:
        source = root / Path(*PurePosixPath(entry).parts)
        if not source.is_file() or source.is_symlink():
            raise ValueError(f"JavaScript QA input is missing or unsafe: {entry}")
        run_checked([str(node_bin), "--check", str(source)], root)

    javascript_source_sha256 = {
        entry: sha256_bytes((root / Path(*PurePosixPath(entry).parts)).read_bytes())
        for entry in javascript_files
    }

    tawk_output = run_checked([str(node_bin), str(root / "tests" / "tawk-state.test.js")], root)
    if tawk_output != "PASS: Tawk chat behavior":
        raise ValueError(f"Unexpected Tawk behavior test output: {tawk_output}")

    sitewide_acceptance_output = run_checked(
        [str(node_bin), str(root / "tests" / "live-sitewide-acceptance.test.cjs")],
        root,
    )
    if sitewide_acceptance_output != "PASS: sitewide acceptance contract":
        raise ValueError(f"Unexpected sitewide acceptance contract test output: {sitewide_acceptance_output}")

    digital_island_acceptance_output = run_checked(
        [str(node_bin), str(root / "tests" / "digital-island-live-acceptance.test.cjs")],
        root,
    )
    if not re.fullmatch(
        r"PASS: Digital Islands live acceptance contract and privacy parsers \([1-9][0-9]* assertions\)\.",
        digital_island_acceptance_output,
    ):
        raise ValueError(
            f"Unexpected Digital Islands acceptance contract output: {digital_island_acceptance_output}"
        )

    digital_island_live_source_output = run_checked(
        [str(node_bin), str(root / "scripts" / "live_digital_island_acceptance.cjs"), "--source-only"],
        root,
    )
    expected_digital_island_live_source_output = (
        "PASS: Digital Islands source gates; 49 Canary entities, 49 public entities, state live."
    )
    if digital_island_live_source_output != expected_digital_island_live_source_output:
        raise ValueError(
            f"Unexpected Digital Islands live source-gate output: {digital_island_live_source_output}"
        )

    digital_island_adapter_output = run_checked(
        [str(node_bin), str(root / "tests" / "digital-islands-adapters.test.js")],
        root,
    )
    if digital_island_adapter_output != "PASS: Digital Islands browser adapters":
        raise ValueError(f"Unexpected Digital Islands adapter output: {digital_island_adapter_output}")

    digital_island_browser_output, digital_island_browser = renderer_browser_evidence(root, node_bin)

    run_checked([sys.executable, str(root / "scripts" / "build_homepage_assets.py"), "--check"], root)
    run_checked([sys.executable, str(root / "scripts" / "build_bangkok_rental_assets.py"), "--check"], root)
    run_checked([sys.executable, str(root / "scripts" / "build_bangkok_rental_registry.py"), "--check"], root)
    run_checked([sys.executable, str(root / "tests" / "bangkok-rental-data.test.py")], root)
    run_checked([sys.executable, str(root / "scripts" / "build_content_registry.py"), "--check"], root)
    run_checked([sys.executable, str(root / "tests" / "real-estate-content.test.py")], root)
    real_estate_runtime_output = run_checked(
        [str(php_bin), str(root / "tests" / "real-estate-runtime.test.php")],
        root,
        contract_environment,
    )
    expected_real_estate_runtime_output = (
        "PASS: Thailand Platform release contract\n"
        "PASS: managed real-estate runtime contract"
    )
    if real_estate_runtime_output != expected_real_estate_runtime_output:
        raise ValueError(f"Unexpected real-estate runtime test output: {real_estate_runtime_output}")
    run_checked([sys.executable, str(root / "tests" / "draft-content-inventory.test.py")], root)
    migration_ledger_output = run_checked(
        [sys.executable, str(root / "scripts" / "build_content_migration_ledger.py")],
        root,
    )
    if migration_ledger_output != "PASS: content migration ledger is current":
        raise ValueError(f"Unexpected content migration ledger output: {migration_ledger_output}")
    run_checked([sys.executable, str(root / "tests" / "content-migration-ledger.test.py")], root)
    run_checked([sys.executable, str(root / "tests" / "queued-expired-content.test.py")], root)
    run_checked([sys.executable, str(root / "scripts" / "build_guide_assets.py"), "--check"], root)
    run_checked([sys.executable, str(root / "scripts" / "build_priority_guides_registry.py"), "--check"], root)
    guides_compiler_output = run_checked(
        [sys.executable, str(root / "tests" / "priority-guides-compiler.test.py")],
        root,
    )
    if guides_compiler_output != "PASS: priority guides compiler tests":
        raise ValueError(f"Unexpected priority guides compiler test output: {guides_compiler_output}")
    guides_runtime_output = run_checked(
        [str(php_bin), str(root / "tests" / "guides-runtime.test.php")],
        root,
    )
    if guides_runtime_output != "PASS: priority guides runtime tests":
        raise ValueError(f"Unexpected priority guides runtime test output: {guides_runtime_output}")
    run_checked([sys.executable, str(root / "scripts" / "build_geography_registry.py"), "--check"], root)
    run_checked([sys.executable, str(root / "tests" / "geography-builder.test.py")], root)
    run_checked([sys.executable, str(root / "scripts" / "build_digital_island_registry.py"), "--check"], root)
    run_checked([sys.executable, str(root / "tests" / "digital-island-data.test.py")], root)
    digital_island_runtime_output = run_checked(
        [str(php_bin), str(root / "tests" / "digital-islands-runtime.test.php")],
        root,
    )
    if digital_island_runtime_output != "PASS: Digital Islands runtime (Canary and Live)":
        raise ValueError(f"Unexpected Digital Islands runtime output: {digital_island_runtime_output}")
    digital_island_settings_output = run_checked(
        [str(php_bin), str(root / "tests" / "digital-islands-settings.test.php")],
        root,
    )
    if not re.fullmatch(
        r"PASS: digital islands administrator settings security gates \([1-9][0-9]* assertions\)\.",
        digital_island_settings_output,
    ):
        raise ValueError(f"Unexpected Digital Islands settings output: {digital_island_settings_output}")
    run_checked([sys.executable, str(root / "scripts" / "build_seo_registry.py"), "--check"], root)
    run_checked([sys.executable, str(root / "scripts" / "build_seo_runtime.py"), "--check"], root)
    run_checked([sys.executable, str(root / "tests" / "seo-ownership-registry.test.py")], root)
    run_checked([sys.executable, str(root / "tests" / "seo-runtime-gates.test.py")], root)
    test_output = run_checked(
        [str(php_bin), str(root / "tests" / "run.php")],
        root,
        contract_environment,
    )
    php_version = run_checked([str(php_bin), "--version"], root).splitlines()[0]
    node_version = run_checked([str(node_bin), "--version"], root).splitlines()[0]

    return {
        "php_binary": {
            "name": php_bin.name,
            "sha256": sha256_bytes(php_bin.read_bytes()),
        },
        "php_runtime": php_version,
        "php_lint": "pass",
        "php_files_linted": len(php_files),
        "node_binary": {
            "name": node_bin.name,
            "sha256": sha256_bytes(node_bin.read_bytes()),
        },
        "node_runtime": node_version,
        "javascript_syntax": "pass",
        "javascript_files_checked": javascript_files,
        "javascript_source_sha256": javascript_source_sha256,
        "tawk_state_tests": "pass",
        "tawk_state_test_output": tawk_output,
        "sitewide_acceptance_contract_tests": "pass",
        "sitewide_acceptance_contract_test_output": sitewide_acceptance_output,
        "digital_island_acceptance_contract_tests": "pass",
        "digital_island_acceptance_contract_test_output": digital_island_acceptance_output,
        "digital_island_live_source_tests": "pass",
        "digital_island_live_source_test_output": digital_island_live_source_output,
        "digital_island_adapter_tests": "pass",
        "digital_island_adapter_test_output": digital_island_adapter_output,
        "digital_island_browser_acceptance": digital_island_browser,
        "digital_island_browser_acceptance_tests": "pass",
        "digital_island_browser_acceptance_test_output": digital_island_browser_output,
        "digital_island_compiler": "pass",
        "digital_island_data_tests": "pass",
        "digital_island_runtime_tests": "pass",
        "digital_island_runtime_test_output": digital_island_runtime_output,
        "digital_island_settings_tests": "pass",
        "digital_island_settings_test_output": digital_island_settings_output,
        "contract_tests": "pass",
        "contract_test_output": test_output,
        "homepage_asset_compiler": "pass",
        "bangkok_asset_compiler": "pass",
        "bangkok_registry_compiler": "pass",
        "bangkok_data_tests": "pass",
        "content_registry_compiler": "pass",
        "real_estate_content_tests": "pass",
        "real_estate_runtime_tests": "pass",
        "real_estate_runtime_test_output": real_estate_runtime_output,
        "draft_content_inventory_tests": "pass",
        "content_migration_ledger_compiler": "pass",
        "content_migration_ledger_compiler_output": migration_ledger_output,
        "content_migration_ledger_tests": "pass",
        "queued_expired_content_tests": "pass",
        "guide_asset_compiler": "pass",
        "priority_guides_registry_compiler": "pass",
        "priority_guides_compiler_tests": "pass",
        "priority_guides_compiler_test_output": guides_compiler_output,
        "guides_runtime_tests": "pass",
        "guides_runtime_test_output": guides_runtime_output,
        "geography_compiler": "pass",
        "geography_builder_tests": "pass",
        "seo_registry_compiler": "pass",
        "seo_runtime_compiler": "pass",
        "seo_registry_tests": "pass",
        "seo_runtime_gate_tests": "pass",
    }


def build_zip(root: Path, output: Path, entries: list[str]) -> None:
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for entry in entries:
            source = root / Path(*PurePosixPath(entry).parts)
            member = f"{PLUGIN_SLUG}/{entry}"
            info = zipfile.ZipInfo(member, FIXED_TIMESTAMP)
            info.create_system = 3
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def verify_zip(output: Path, root: Path, entries: list[str], version: str) -> dict[str, Any]:
    expected = [f"{PLUGIN_SLUG}/{entry}" for entry in entries]
    with zipfile.ZipFile(output, "r") as archive:
        names = archive.namelist()
        if names != expected:
            raise ValueError("ZIP inventory or ordering mismatch")
        if archive.testzip() is not None:
            raise ValueError("ZIP integrity test failed")
        if any("\\" in name or not name.startswith(f"{PLUGIN_SLUG}/") for name in names):
            raise ValueError("ZIP contains an unexpected root or path separator")

        for entry in entries:
            member = f"{PLUGIN_SLUG}/{entry}"
            source = root / Path(*PurePosixPath(entry).parts)
            if archive.read(member) != source.read_bytes():
                raise ValueError(f"ZIP payload disagrees with source: {entry}")

        main = archive.read(f"{PLUGIN_SLUG}/thailand-platform.php").decode("utf-8")
        if f"Version: {version}" not in main:
            raise ValueError("ZIP is missing the expected plugin header version")
        if f"'THAILAND_PLATFORM_VERSION', '{version}'" not in main:
            raise ValueError("ZIP is missing the expected version constant")

    payload = output.read_bytes()
    return {
        "bytes": len(payload),
        "inventory": expected,
        "inventory_count": len(expected),
        "path": output.as_posix(),
        "sha256": sha256_bytes(payload),
        "slug": PLUGIN_SLUG,
        "version": version,
    }


def atomic_write_text(path: Path, contents: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("w", encoding="utf-8", dir=path.parent, delete=False) as handle:
        handle.write(contents)
        temporary = Path(handle.name)
    temporary.replace(path)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--out", type=Path)
    parser.add_argument("--php-bin")
    parser.add_argument("--node-bin")
    parser.add_argument("--receipt-artifact-path")
    parser.add_argument("--source-commit", default=os.environ.get("THAILAND_SOURCE_COMMIT"))
    args = parser.parse_args()

    if (
        not args.source_commit
        or not re.fullmatch(r"[0-9a-f]{40}", args.source_commit)
        or args.source_commit == "0" * 40
    ):
        raise ValueError("--source-commit must be a full lowercase 40-character Git commit")

    root = args.root.resolve()
    contract = read_release_contract(root)
    entries = read_inventory(root)
    vendor = validate_vendor_tree(root)
    php_bin = resolve_php_binary(args.php_bin)
    node_bin = resolve_node_binary(args.node_bin)
    qa = run_qa(root, entries, php_bin, node_bin)
    geography = geography_evidence(root)
    digital_islands = digital_islands_evidence(root, entries, contract["version"])
    output = (args.out or root / "plugin-dist" / f"{PLUGIN_SLUG}-{contract['version']}.zip").resolve()
    output.parent.mkdir(parents=True, exist_ok=True)

    temporary_handle = tempfile.NamedTemporaryFile(dir=output.parent, suffix=".zip", delete=False)
    temporary_handle.close()
    temporary_output = Path(temporary_handle.name)
    try:
        build_zip(root, temporary_output, entries)
        receipt = verify_zip(temporary_output, root, entries, contract["version"])
        temporary_output.replace(output)
        receipt["path"] = args.receipt_artifact_path or output.name
    finally:
        if temporary_output.exists():
            temporary_output.unlink()

    receipt.update(
        {
            "built_at": datetime.now(timezone.utc).isoformat(),
            "deterministic_zip": True,
            "builder": {
                "python_executable": {
                    "name": Path(sys.executable).resolve().name,
                    "sha256": sha256_bytes(Path(sys.executable).resolve().read_bytes()),
                },
                "python_runtime": sys.version.splitlines()[0],
                "script_sha256": sha256_bytes(Path(__file__).read_bytes()),
            },
            "geography": geography,
            "digital_islands": digital_islands,
            "qa": qa,
            "release_contract": contract,
            "secret_scan": {
                "matches": 0,
                "patterns": sorted(SECRET_PATTERNS),
                "result": "pass",
            },
            "source_commit": args.source_commit,
            "vendor": vendor,
        }
    )
    receipt_path = output.with_suffix(".receipt.json")
    atomic_write_text(
        receipt_path,
        json.dumps(receipt, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
    )
    print(json.dumps(receipt, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
