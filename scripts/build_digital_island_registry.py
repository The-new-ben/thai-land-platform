#!/usr/bin/env python3
"""Build the deterministic Koh Phangan digital-island registry."""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import re
import sys
from urllib.parse import urlsplit
from collections import Counter
from dataclasses import dataclass
from datetime import date
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "data" / "digital-islands" / "koh-phangan.json"
SCHEMA = ROOT / "data" / "digital-islands" / "island-world.schema.json"
REGISTRY = ROOT / "resources" / "digital-islands" / "registry.php"
MANIFEST = ROOT / "resources" / "digital-islands" / "manifest.json"

LONG_DASH_RE = re.compile("[\u2013\u2014\u200b]")
DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
SOURCE_ID_RE = re.compile(r"^source:[a-z0-9]+(?:[.:_-][a-z0-9]+)*$")
ENTITY_ID_RE = re.compile(r"^[a-z_]+:th:[a-z0-9]+(?::[a-z0-9-]+)+$")
LAYER_ID_RE = re.compile(r"^layer:[a-z0-9]+(?:[.:_-][a-z0-9]+)*$")
TOOL_ID_RE = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
RENDERER_ID_RE = re.compile(r"^(?:immersive_3d|practical_2d)$")
SATELLITE_SOURCE_ID = "source:copernicus.sentinel2.s2b_47ppl_20260326_0_l2a"

ACCURACY_CAPS = {
    "official_point": 50,
    "first_party_pin": 100,
    "community_mapped_feature": 500,
    "area_centroid": 5000,
}

PUBLIC_FACT_REUSE = {"public_fact_with_attribution", "attribute_and_geometry"}
PUBLIC_GEOMETRY_USE = {"official_point", "first_party_pin"}

FORBIDDEN_KEYS = {
    "post_content",
    "draft_body",
    "source_body",
    "downloaded_body",
    "snapshot_body",
    "cookie",
    "password",
    "token",
}

EXPECTED_RENDERERS = {
    "immersive_3d": {
        "role": "primary_capable_device",
        "library": "MapLibre GL JS",
        "library_version": "5.18.0",
        "delivery": "self_hosted_pinned",
        "capabilities": [
            "camera_presets",
            "entity_focus",
            "globe",
            "hillshade",
            "terrain",
            "building_extrusion",
            "satellite_imagery",
        ],
        "fallback_triggers": ["webgl_unavailable", "data_saver", "user_choice"],
        "source_ids": [
            "source:maplibre.docs",
            "source:protomaps.basemap",
            "source:mapzen.terrain",
            "source:usgs.srtm",
            "source:usgs.gmted2010",
            "source:noaa.etopo1",
            "source:esa.worldcover.2021",
            SATELLITE_SOURCE_ID,
        ],
    },
    "practical_2d": {
        "role": "fallback_and_operational",
        "library": "MapLibre GL JS",
        "library_version": "5.18.0",
        "delivery": "self_hosted_pinned",
        "capabilities": [
            "camera_presets",
            "entity_focus",
            "filters",
            "keyboard_list",
            "vector_basemap",
        ],
        "fallback_triggers": [],
        "source_ids": [
            "source:maplibre.docs",
            "source:protomaps.basemap",
            "source:esa.worldcover.2021",
        ],
    },
}

FORBIDDEN_RENDERER_CLAIMS = {
    "measurement",
    "offline",
    "parcel",
    "buildability",
    "photorealism",
    "walking",
}

EXPECTED_SATELLITE_SOURCE = {
    "source_id": SATELLITE_SOURCE_ID,
    "publisher": "European Union Copernicus programme; COG access via AWS Open Data",
    "title": "Sentinel-2 L2A true-colour item S2B_47PPL_20260326_0_L2A",
    "url": "https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/47/P/PL/2026/3/S2B_47PPL_20260326_0_L2A/TCI.tif",
    "authority_tier": "licensed_registry",
    "source_type": "official_dataset",
    "checked_on": "2026-08-12",
    "effective_on": "2026-03-26",
    "access_state": "current",
    "permitted_reuse": "attribute_and_geometry",
    "geometry_use": "orientation_only",
    "next_review_on": "2027-03-26",
    "imagery": {
        "item_id": "S2B_47PPL_20260326_0_L2A",
        "observed_at": "2026-03-26T03:55:36.171000Z",
        "tile_cloud_cover_percent": 14.307985,
        "tile_cloud_metadata_scope": "source_tile_not_cropped_island",
        "registry_url": "https://registry.opendata.aws/sentinel-2-l2a-cogs/",
        "legal_notice_url": "https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice",
        "processed_bounds": {"west": 99.92, "south": 9.63, "east": 100.12, "north": 9.84},
        "processing": ["cropped_to_bounds", "reprojected_to_epsg_3857", "compressed_to_webp"],
        "processed_projection": "EPSG:3857",
        "processed_format": "webp",
        "usage_scope": "orientation_only",
        "limitations": [
            "not_current_evidence",
            "not_parcel_evidence",
            "not_title_evidence",
            "not_buildability_evidence",
        ],
        "attribution": "Contains modified Copernicus Sentinel data 2026",
    },
}


class BuildError(RuntimeError):
    """Controlled contract failure."""


@dataclass(frozen=True)
class BuildResult:
    registry_php: bytes
    manifest_json: bytes


def require(condition: bool, message: str) -> None:
    if not condition:
        raise BuildError(message)


def duplicate_guard(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise BuildError(f"duplicate JSON key: {key}")
        result[key] = value
    return result


def load_json(path: Path) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"), object_pairs_hook=duplicate_guard)
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise BuildError(f"cannot read {path.relative_to(ROOT)}: {exc}") from exc
    require(isinstance(value, dict), f"{path.name} must contain one object")
    return value


def valid_date(value: Any) -> bool:
    if not isinstance(value, str) or DATE_RE.fullmatch(value) is None:
        return False
    try:
        return date.fromisoformat(value).isoformat() == value
    except ValueError:
        return False


def hash_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def canonical_json(value: Any) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")


def walk(value: Any, path: str = "$" ) -> None:
    if isinstance(value, str):
        require(LONG_DASH_RE.search(value) is None, f"forbidden dash or zero-width character at {path}")
        require("\x00" not in value, f"NUL character at {path}")
        return
    if isinstance(value, list):
        for index, item in enumerate(value):
            walk(item, f"{path}[{index}]")
        return
    if isinstance(value, dict):
        for key, item in value.items():
            require(key not in FORBIDDEN_KEYS, f"forbidden source key at {path}.{key}")
            walk(item, f"{path}.{key}")


def json_type_matches(value: Any, expected: str) -> bool:
    if expected == "object":
        return isinstance(value, dict)
    if expected == "array":
        return isinstance(value, list)
    if expected == "string":
        return isinstance(value, str)
    if expected == "null":
        return value is None
    if expected == "boolean":
        return isinstance(value, bool)
    if expected == "integer":
        return isinstance(value, int) and not isinstance(value, bool)
    if expected == "number":
        return isinstance(value, (int, float)) and not isinstance(value, bool) and math.isfinite(value)
    raise BuildError(f"unsupported schema type: {expected}")


class SchemaValidator:
    """Dependency-free validator for the JSON Schema subset used by the pilot."""

    def __init__(self, schema: dict[str, Any]) -> None:
        self.schema = schema

    def resolve_ref(self, reference: str) -> dict[str, Any]:
        require(reference.startswith("#/"), f"unsupported schema reference: {reference}")
        current: Any = self.schema
        for raw_part in reference[2:].split("/"):
            part = raw_part.replace("~1", "/").replace("~0", "~")
            require(isinstance(current, dict) and part in current, f"unresolvable schema reference: {reference}")
            current = current[part]
        require(isinstance(current, dict), f"schema reference is not an object: {reference}")
        return current

    def validate(self, value: Any, current: Any = None, path: str = "$") -> list[str]:
        schema = self.schema if current is None else current
        if schema is True:
            return []
        if schema is False:
            return [f"{path}: value is not permitted"]
        if not isinstance(schema, dict):
            return [f"{path}: schema branch is malformed"]
        if "$ref" in schema:
            return self.validate(value, self.resolve_ref(schema["$ref"]), path)
        if "oneOf" in schema:
            results = [self.validate(value, branch, path) for branch in schema["oneOf"]]
            matches = sum(1 for errors in results if not errors)
            return [] if matches == 1 else [f"{path}: expected one oneOf match, got {matches}"]

        errors: list[str] = []
        expected_type = schema.get("type")
        if expected_type is not None:
            accepted = expected_type if isinstance(expected_type, list) else [expected_type]
            if not any(json_type_matches(value, item) for item in accepted):
                return [f"{path}: expected type {accepted}, got {type(value).__name__}"]
        if "const" in schema and value != schema["const"]:
            errors.append(f"{path}: expected constant {schema['const']!r}")
        if "enum" in schema and value not in schema["enum"]:
            errors.append(f"{path}: value is outside enum")

        if isinstance(value, str):
            if len(value) < schema.get("minLength", 0):
                errors.append(f"{path}: string shorter than minLength")
            maximum = schema.get("maxLength")
            if maximum is not None and len(value) > maximum:
                errors.append(f"{path}: string longer than maxLength")
            pattern = schema.get("pattern")
            if pattern is not None and re.search(pattern, value) is None:
                errors.append(f"{path}: string does not match {pattern}")
            if schema.get("format") == "date" and not valid_date(value):
                errors.append(f"{path}: invalid ISO date")

        if isinstance(value, (int, float)) and not isinstance(value, bool):
            minimum = schema.get("minimum")
            maximum = schema.get("maximum")
            if minimum is not None and value < minimum:
                errors.append(f"{path}: number is below minimum {minimum}")
            if maximum is not None and value > maximum:
                errors.append(f"{path}: number is above maximum {maximum}")

        if isinstance(value, list):
            if len(value) < schema.get("minItems", 0):
                errors.append(f"{path}: array shorter than minItems")
            maximum = schema.get("maxItems")
            if maximum is not None and len(value) > maximum:
                errors.append(f"{path}: array longer than maxItems")
            if schema.get("uniqueItems"):
                serialized = [canonical_json(item) for item in value]
                if len(serialized) != len(set(serialized)):
                    errors.append(f"{path}: array items are not unique")
            prefix = schema.get("prefixItems", [])
            if isinstance(prefix, list):
                for index, item_schema in enumerate(prefix):
                    if index < len(value):
                        errors.extend(self.validate(value[index], item_schema, f"{path}[{index}]"))
            item_schema = schema.get("items")
            start = len(prefix) if isinstance(prefix, list) else 0
            if item_schema is False and len(value) > start:
                errors.append(f"{path}: unexpected array item after prefixItems")
            elif isinstance(item_schema, dict):
                for index, item in enumerate(value[start:], start=start):
                    errors.extend(self.validate(item, item_schema, f"{path}[{index}]"))

        if isinstance(value, dict):
            properties = schema.get("properties", {})
            for required in schema.get("required", []):
                if required not in value:
                    errors.append(f"{path}: missing required property {required}")
            additional = schema.get("additionalProperties")
            for key, item in value.items():
                if key in properties:
                    errors.extend(self.validate(item, properties[key], f"{path}.{key}"))
                elif additional is False:
                    errors.append(f"{path}: unexpected property {key}")
                elif isinstance(additional, dict):
                    errors.extend(self.validate(item, additional, f"{path}.{key}"))
        return errors


def schema_validate(document: dict[str, Any], schema: dict[str, Any]) -> None:
    errors = SchemaValidator(schema).validate(document)
    require(not errors, f"digital-island schema validation failed: {errors[0] if errors else ''}")


def unique_index(records: Any, key: str, label: str, pattern: re.Pattern[str]) -> dict[str, dict[str, Any]]:
    require(isinstance(records, list), f"{label} must be an array")
    result: dict[str, dict[str, Any]] = {}
    for record in records:
        require(isinstance(record, dict), f"{label} record must be an object")
        identity = record.get(key)
        require(isinstance(identity, str) and pattern.fullmatch(identity) is not None, f"invalid {label} ID")
        require(identity not in result, f"duplicate {label} ID: {identity}")
        result[identity] = record
    return result


def normalized_entity_identity(entity: dict[str, Any]) -> tuple[str, str]:
    name = entity.get("names", {}).get("en", "")
    normalized = re.sub(r"[^a-z0-9]+", "", name.lower()) if isinstance(name, str) else ""
    return str(entity.get("entity_type", "")), normalized


def validate_source(source: dict[str, Any]) -> None:
    url = source.get("url", "")
    try:
        parsed = urlsplit(url)
    except (TypeError, ValueError) as exc:
        raise BuildError(f"source URL is invalid: {source.get('source_id')}") from exc
    require(
        isinstance(url, str)
        and parsed.scheme == "https"
        and bool(parsed.hostname)
        and parsed.username is None
        and parsed.password is None
        and not any(character.isspace() or ord(character) < 32 for character in url),
        f"source URL is not a safe HTTPS URL: {source.get('source_id')}",
    )
    require(valid_date(source.get("checked_on")), f"invalid source checked_on: {source.get('source_id')}")
    require(valid_date(source.get("next_review_on")), f"invalid source next_review_on: {source.get('source_id')}")
    require(source["next_review_on"] >= source["checked_on"], f"source review precedes check: {source.get('source_id')}")
    effective = source.get("effective_on")
    require(effective is None or valid_date(effective), f"invalid source effective_on: {source.get('source_id')}")
    require(source.get("access_state") in {"current", "stale", "unreachable", "retracted"}, f"invalid source access state: {source.get('source_id')}")


def validate_source_ids(ids: Any, sources: dict[str, dict[str, Any]], label: str, allow_empty: bool = False) -> None:
    require(isinstance(ids, list), f"{label} source_ids must be an array")
    require(allow_empty or len(ids) > 0, f"{label} has no sources")
    require(len(ids) == len(set(ids)), f"{label} has duplicate sources")
    for source_id in ids:
        require(isinstance(source_id, str) and SOURCE_ID_RE.fullmatch(source_id) is not None, f"{label} has invalid source ID")
        require(source_id in sources, f"{label} references unknown source: {source_id}")


def validate_renderer_contract(records: Any, sources: dict[str, dict[str, Any]]) -> None:
    renderers = unique_index(records, "renderer_id", "renderer", RENDERER_ID_RE)
    require(set(renderers) == set(EXPECTED_RENDERERS), "renderer set is incomplete or unexpected")
    for renderer_id, expected in EXPECTED_RENDERERS.items():
        renderer = renderers[renderer_id]
        for field, value in expected.items():
            require(renderer.get(field) == value, f"renderer contract mismatch: {renderer_id}.{field}")
        validate_source_ids(renderer.get("source_ids"), sources, renderer_id)
        claims = " ".join(renderer.get("capabilities", [])).lower()
        require(
            not any(forbidden in claims for forbidden in FORBIDDEN_RENDERER_CLAIMS),
            f"renderer contains a prohibited capability claim: {renderer_id}",
        )


def validate_satellite_source(sources: dict[str, dict[str, Any]]) -> None:
    source = sources.get(SATELLITE_SOURCE_ID)
    require(source == EXPECTED_SATELLITE_SOURCE, "approved Sentinel-2 source contract differs from review")
    for source_id, candidate in sources.items():
        require(
            source_id == SATELLITE_SOURCE_ID or "imagery" not in candidate,
            f"unreviewed imagery metadata is public: {source_id}",
        )


def validate_layer(layer: dict[str, Any], sources: dict[str, dict[str, Any]]) -> None:
    layer_id = layer["layer_id"]
    require(valid_date(layer.get("next_review_on")), f"invalid layer review date: {layer_id}")
    validate_source_ids(layer.get("source_ids"), sources, layer_id, allow_empty=True)
    if layer.get("coverage_state") == "source_missing":
        require(layer["source_ids"] == [], f"source-missing layer has a claimed source: {layer_id}")
    if layer.get("coverage_state") == "public_ready":
        require(layer.get("public_state") == "map_only", f"public-ready layer has unsafe public state: {layer_id}")
        require(len(layer["source_ids"]) > 0, f"public-ready layer has no source: {layer_id}")


def validate_official_tool(
    tool: dict[str, Any],
    sources: dict[str, dict[str, Any]],
    required_dimensions: set[str],
) -> None:
    tool_id = tool["tool_id"]
    source_id = tool["source_id"]
    require(source_id in sources, f"official tool references unknown source: {tool_id}")
    source = sources[source_id]
    require(source["access_state"] == "current", f"official tool source is not current: {tool_id}")
    require(source["url"] == tool["url"], f"official tool URL differs from its source: {tool_id}")
    require(
        source["permitted_reuse"] in {"link_only", "public_fact_with_attribution"},
        f"official tool source is not safe to link: {tool_id}",
    )
    require(valid_date(tool.get("checked_on")), f"invalid official tool checked_on: {tool_id}")
    require(valid_date(tool.get("next_review_on")), f"invalid official tool next_review_on: {tool_id}")
    require(tool["checked_on"] <= tool["next_review_on"] <= source["next_review_on"], f"official tool review window is unsafe: {tool_id}")
    supported = tool.get("supports_dimensions")
    require(isinstance(supported, list) and set(supported).issubset(required_dimensions), f"official tool supports an unknown decision dimension: {tool_id}")


def validate_entity(
    entity: dict[str, Any],
    sources: dict[str, dict[str, Any]],
    layers: dict[str, dict[str, Any]],
    island: dict[str, Any],
) -> None:
    entity_id = entity["entity_id"]
    require(valid_date(entity.get("checked_on")), f"invalid entity checked_on: {entity_id}")
    require(valid_date(entity.get("next_review_on")), f"invalid entity next_review_on: {entity_id}")
    require(entity["next_review_on"] >= entity["checked_on"], f"entity review precedes check: {entity_id}")
    validate_source_ids(entity.get("source_ids"), sources, entity_id)
    require(isinstance(entity.get("layer_ids"), list) and entity["layer_ids"], f"entity has no layers: {entity_id}")
    require(len(entity["layer_ids"]) == len(set(entity["layer_ids"])), f"entity has duplicate layers: {entity_id}")
    for layer_id in entity["layer_ids"]:
        require(layer_id in layers, f"entity references unknown layer: {entity_id} -> {layer_id}")
    require(isinstance(entity.get("geo_ids"), list) and island["geo_id"] in entity["geo_ids"], f"entity is outside the pilot island: {entity_id}")
    require("geo:th:subdistrict:840503" not in entity["geo_ids"], f"Ko Tao leaked into Koh Phangan: {entity_id}")

    coordinates = entity.get("coordinates")
    geometry = entity.get("geometry")
    require(isinstance(geometry, dict), f"entity geometry is malformed: {entity_id}")
    validate_source_ids(geometry.get("source_ids"), sources, f"{entity_id} geometry", allow_empty=True)
    require(set(geometry["source_ids"]).issubset(set(entity["source_ids"])), f"entity geometry source is not bound to entity: {entity_id}")
    if coordinates is None:
        require(geometry.get("kind") != "point", f"entity has point geometry without coordinates: {entity_id}")
    else:
        require(isinstance(coordinates, dict), f"entity coordinates are malformed: {entity_id}")
        latitude = coordinates.get("latitude")
        longitude = coordinates.get("longitude")
        require(isinstance(latitude, (int, float)) and not isinstance(latitude, bool) and math.isfinite(latitude), f"invalid latitude: {entity_id}")
        require(isinstance(longitude, (int, float)) and not isinstance(longitude, bool) and math.isfinite(longitude), f"invalid longitude: {entity_id}")
        bounds = island["bounds"]
        require(bounds["south"] <= latitude <= bounds["north"], f"latitude outside island envelope: {entity_id}")
        require(bounds["west"] <= longitude <= bounds["east"], f"longitude outside island envelope: {entity_id}")
        accuracy_class = coordinates.get("accuracy_class")
        accuracy_m = coordinates.get("accuracy_m")
        require(accuracy_class in ACCURACY_CAPS, f"unknown accuracy class: {entity_id}")
        require(isinstance(accuracy_m, int) and 1 <= accuracy_m <= ACCURACY_CAPS[accuracy_class], f"accuracy radius exceeds class cap: {entity_id}")
        validate_source_ids(coordinates.get("source_ids"), sources, f"{entity_id} coordinates")
        require(set(coordinates["source_ids"]).issubset(set(entity["source_ids"])), f"coordinate source is not bound to entity: {entity_id}")

    facts = entity.get("facts")
    require(isinstance(facts, list), f"entity facts are malformed: {entity_id}")
    fact_ids: set[str] = set()
    for fact in facts:
        require(isinstance(fact, dict), f"entity fact is malformed: {entity_id}")
        fact_id = fact.get("fact_id")
        require(isinstance(fact_id, str) and fact_id not in fact_ids, f"duplicate or invalid fact: {entity_id}")
        fact_ids.add(fact_id)
        require(valid_date(fact.get("checked_on")) and valid_date(fact.get("next_review_on")), f"invalid fact dates: {entity_id}/{fact_id}")
        require(fact["next_review_on"] >= fact["checked_on"], f"fact review precedes check: {entity_id}/{fact_id}")
        validate_source_ids(fact.get("source_ids"), sources, f"{entity_id}/{fact_id}")
        require(set(fact["source_ids"]).issubset(set(entity["source_ids"])), f"fact source is not bound to entity: {entity_id}/{fact_id}")

    state = entity.get("public_state")
    policy = entity.get("indexing_policy")
    allowed = {
        "private_candidate": {"private"},
        "review_required": {"private", "noindex_follow"},
        "map_only": {"map_only"},
        "indexable": {"index"},
        "withdrawn": {"private", "noindex_follow"},
    }
    require(policy in allowed.get(state, set()), f"public state and indexing policy disagree: {entity_id}")
    if state in {"map_only", "indexable"}:
        current = [sources[source_id] for source_id in entity["source_ids"] if sources[source_id]["access_state"] == "current"]
        retracted = [sources[source_id] for source_id in entity["source_ids"] if sources[source_id]["access_state"] == "retracted"]
        require(current and not retracted, f"public-safe entity has no current evidence or has retracted evidence: {entity_id}")


def public_fact_source(source: dict[str, Any]) -> bool:
    return source.get("access_state") == "current" and source.get("permitted_reuse") in PUBLIC_FACT_REUSE


def public_geometry_source(source: dict[str, Any]) -> bool:
    return source.get("access_state") == "current" and (
        source.get("permitted_reuse") == "attribute_and_geometry"
        or (
            source.get("permitted_reuse") == "public_fact_with_attribution"
            and source.get("geometry_use") in PUBLIC_GEOMETRY_USE
        )
    )


def validate_live_entity(entity: dict[str, Any], sources: dict[str, dict[str, Any]]) -> None:
    entity_id = entity["entity_id"]
    require(
        any(public_fact_source(sources[source_id]) for source_id in entity["source_ids"]),
        f"Live entity has no current reuse-eligible public citation: {entity_id}",
    )
    coordinates = entity.get("coordinates")
    if coordinates is not None:
        require(
            any(public_geometry_source(sources[source_id]) for source_id in coordinates["source_ids"]),
            f"Live coordinate has no geometry-reusable citation: {entity_id}",
        )
    for fact in entity["facts"]:
        if fact["public"] is not True:
            continue
        require(
            any(public_fact_source(sources[source_id]) for source_id in fact["source_ids"]),
            f"Live fact has no current reuse-eligible citation: {entity_id}/{fact['fact_id']}",
        )


def public_citation(source: dict[str, Any]) -> dict[str, Any]:
    return {
        "source_id": source["source_id"],
        "publisher": source["publisher"],
        "title": source["title"],
        "url": source["url"],
        "checked_on": source["checked_on"],
    }


def citations(
    source_ids: list[str],
    sources: dict[str, dict[str, Any]],
    geometry: bool = False,
) -> list[dict[str, Any]]:
    predicate = public_geometry_source if geometry else public_fact_source
    return [
        public_citation(sources[source_id])
        for source_id in source_ids
        if predicate(sources[source_id])
    ]


def sanitize_entity(entity: dict[str, Any], sources: dict[str, dict[str, Any]]) -> dict[str, Any]:
    public_facts = [
        {
            "fact_id": fact["fact_id"],
            "label_he": fact["label_he"],
            "value_he": fact["value_he"],
            "checked_on": fact["checked_on"],
            "next_review_on": fact["next_review_on"],
            "evidence": citations(fact["source_ids"], sources),
        }
        for fact in entity["facts"]
        if fact["public"] is True
    ]
    coordinates = entity["coordinates"]
    if coordinates is not None:
        coordinates = {
            "latitude": coordinates["latitude"],
            "longitude": coordinates["longitude"],
            "accuracy_class": coordinates["accuracy_class"],
            "accuracy_m": coordinates["accuracy_m"],
            "basis_label": coordinates["basis_label"],
            "evidence": citations(coordinates["source_ids"], sources, geometry=True),
        }
    return {
        "entity_id": entity["entity_id"],
        "entity_type": entity["entity_type"],
        "names": entity["names"],
        "aliases": entity["aliases"],
        "geo_ids": entity["geo_ids"],
        "location_label_he": entity["location_label_he"],
        "coordinates": coordinates,
        "geometry": {
            "kind": entity["geometry"]["kind"],
            "state": entity["geometry"]["state"],
        },
        "layer_ids": entity["layer_ids"],
        "public_state": entity["public_state"],
        "indexing_policy": entity["indexing_policy"],
        "facts": public_facts,
        "evidence": citations(entity["source_ids"], sources),
        "checked_on": entity["checked_on"],
        "next_review_on": entity["next_review_on"],
    }


def php_quote(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def php_export(value: Any, level: int = 0) -> str:
    indent = "\t" * level
    child = "\t" * (level + 1)
    if value is None:
        return "null"
    if value is True:
        return "true"
    if value is False:
        return "false"
    if isinstance(value, int) and not isinstance(value, bool):
        return str(value)
    if isinstance(value, float):
        require(math.isfinite(value), "generated PHP contains a non-finite float")
        return repr(value)
    if isinstance(value, str):
        return php_quote(value)
    if isinstance(value, list):
        if not value:
            return "array()"
        return "array(\n" + "\n".join(f"{child}{php_export(item, level + 1)}," for item in value) + f"\n{indent})"
    if isinstance(value, dict):
        if not value:
            return "array()"
        return "array(\n" + "\n".join(
            f"{child}{php_quote(str(key))} => {php_export(item, level + 1)},"
            for key, item in value.items()
        ) + f"\n{indent})"
    raise BuildError(f"cannot export PHP type: {type(value).__name__}")


def build() -> BuildResult:
    source = load_json(SOURCE)
    schema = load_json(SCHEMA)
    walk(source)
    walk(schema)
    schema_validate(source, schema)
    require(source.get("contract_id") == "thailand-digital-island-world-v1", "contract ID mismatch")
    require(source.get("schema_version") == 1, "schema version mismatch")
    require(source.get("$schema") == "./island-world.schema.json", "schema locator mismatch")
    require(valid_date(source.get("checked_on")), "invalid dataset checked_on")
    require(source.get("publication_state") in {"private_review", "canary", "live"}, "invalid publication state")
    canonical = source.get("canonical")
    require(isinstance(canonical, dict), "canonical contract is missing")
    require(canonical.get("owner_id") == "koh-phangan-map", "Koh Phangan owner mismatch")
    require(canonical.get("canonical_path") == "/מפת-קופנגן/", "Koh Phangan canonical path mismatch")
    require(canonical.get("breadcrumb_owner_ids") == ["home", "thailand-map", "koh-phangan-map"], "breadcrumb chain mismatch")
    if source["publication_state"] != "live":
        require(canonical.get("indexing_policy") == "noindex_follow", "non-live island must remain noindex")

    island = source.get("island")
    require(isinstance(island, dict), "island contract is missing")
    bounds = island.get("bounds")
    center = island.get("center")
    require(isinstance(bounds, dict) and bounds["south"] < bounds["north"] and bounds["west"] < bounds["east"], "island bounds are invalid")
    require(isinstance(center, dict) and bounds["south"] <= center["latitude"] <= bounds["north"] and bounds["west"] <= center["longitude"] <= bounds["east"], "island center is outside bounds")

    sources = unique_index(source.get("source_catalog"), "source_id", "source", SOURCE_ID_RE)
    for record in sources.values():
        validate_source(record)
    require("source:cesiumjs.docs" not in sources, "retired Cesium renderer evidence remains in the MapLibre contract")
    validate_satellite_source(sources)
    validate_renderer_contract(source.get("renderer_contract"), sources)
    required_dimensions = set(source["land_decision_policy"]["required_dimensions"])
    official_tools = unique_index(source.get("official_tools"), "tool_id", "official tool", TOOL_ID_RE)
    require(
        set(official_tools) == {"lands-maps-parcel-lookup", "koh-phangan-land-office", "onep-environmental-rules"},
        "the official land-tool set is incomplete or unexpected",
    )
    for record in official_tools.values():
        validate_official_tool(record, sources, required_dimensions)
    layers = unique_index(source.get("layer_catalog"), "layer_id", "layer", LAYER_ID_RE)
    for record in layers.values():
        validate_layer(record, sources)
    entities = unique_index(source.get("entities"), "entity_id", "entity", ENTITY_ID_RE)
    normalized_identities: dict[tuple[str, str], str] = {}
    for record in entities.values():
        validate_entity(record, sources, layers, island)
        identity = normalized_entity_identity(record)
        require(identity[1] != "", f"entity has no normalized English identity: {record['entity_id']}")
        require(
            identity not in normalized_identities,
            f"duplicate normalized entity identity: {normalized_identities.get(identity)} and {record['entity_id']}",
        )
        normalized_identities[identity] = record["entity_id"]

    entity_counts = Counter(record["entity_type"] for record in entities.values())
    require(entity_counts["settlement"] == 14, "Koh Phangan must retain exactly 14 reviewed muban records")
    require(len(entities) == 49, "the public Koh Phangan source must contain exactly 49 reviewed entities")
    require(entity_counts["property_project"] == 3, "Koh Phangan project seed is incomplete")
    require(entity_counts.get("property_offer", 0) == 0, "private property offers leaked into the public source")
    require(entity_counts.get("landmark", 0) == 7, "Koh Phangan must retain exactly seven reviewed orientation landmarks")
    require(entity_counts.get("road", 0) == 4, "Koh Phangan must retain exactly four reviewed road-corridor records")
    require(entity_counts.get("education", 0) == 1, "Koh Phangan must retain the reviewed public-school record")
    require(entity_counts.get("telecom", 0) == 1, "Koh Phangan must retain the reviewed telecom service card")

    if source["publication_state"] == "live":
        require(canonical.get("indexing_policy") == "index", "Live island must use index policy")
        for record in entities.values():
            validate_live_entity(record, sources)

    canary_entities = [
        sanitize_entity(record, sources)
        for _, record in sorted(entities.items())
        if record["public_state"] in {"map_only", "indexable"}
    ]
    public_entities = canary_entities if source["publication_state"] == "live" else []
    if source["publication_state"] == "live":
        require(len(canary_entities) == 49 and public_entities == canary_entities, "Live projection must equal the exact 49-entity reviewed set")
    coverage = Counter(record["coverage_state"] for record in layers.values())
    registry = {
        "contract_id": source["contract_id"],
        "schema_version": source["schema_version"],
        "dataset_version": source["dataset_version"],
        "checked_on": source["checked_on"],
        "publication_state": source["publication_state"],
        "source_digest": hash_bytes(canonical_json(source)),
        "schema_sha256": hash_bytes(SCHEMA.read_bytes()),
        "canonical": canonical,
        "island": island,
        "renderer_contract": source["renderer_contract"],
        "camera_presets": source["camera_presets"],
        "land_decision_policy": source["land_decision_policy"],
        "official_tools": [official_tools[key] for key in sorted(official_tools)],
        "sources_by_id": {key: sources[key] for key in sorted(sources)},
        "layers_by_id": {key: layers[key] for key in sorted(layers)},
        "entities_by_id": {key: entities[key] for key in sorted(entities)},
        "canary_map_entities": canary_entities,
        "public_map_entities": public_entities,
        "coverage_summary": {key: coverage[key] for key in sorted(coverage)},
        "counts": {
            "sources": len(sources),
            "official_tools": len(official_tools),
            "layers": len(layers),
            "entities": len(entities),
            "canary_map_entities": len(canary_entities),
            "public_map_entities": len(public_entities),
            "entity_types": dict(sorted(entity_counts.items())),
        },
    }
    php = (
        "<?php\n"
        "/**\n"
        " * Generated Koh Phangan digital-island registry.\n"
        " *\n"
        " * Do not edit this file directly.\n"
        " */\n\n"
        "return " + php_export(registry) + ";\n"
    ).encode("utf-8")
    manifest = {
        "contract_id": source["contract_id"],
        "schema_version": 1,
        "dataset_version": source["dataset_version"],
        "checked_on": source["checked_on"],
        "publication_state": source["publication_state"],
        "source_digest": registry["source_digest"],
        "schema_sha256": registry["schema_sha256"],
        "counts": registry["counts"],
        "artifacts": {
            "resources/digital-islands/registry.php": {
                "bytes": len(php),
                "sha256": hash_bytes(php),
            }
        },
    }
    return BuildResult(php, json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2).encode("utf-8") + b"\n")


def write_or_check(result: BuildResult, check: bool) -> None:
    expected = {REGISTRY: result.registry_php, MANIFEST: result.manifest_json}
    if check:
        stale = [path.relative_to(ROOT).as_posix() for path, value in expected.items() if not path.exists() or path.read_bytes() != value]
        require(not stale, "stale generated digital-island artifacts: " + ", ".join(stale))
        return
    for path, value in expected.items():
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(value)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    try:
        result = build()
        write_or_check(result, args.check)
    except BuildError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    print("PASS: digital-island registry " + ("is current" if args.check else "built"))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
