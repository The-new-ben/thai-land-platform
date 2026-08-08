#!/usr/bin/env python3
"""Compile and verify deterministic Thailand geography registry artifacts."""

from __future__ import annotations

import argparse
import csv
import hashlib
import io
import json
import math
import re
import sys
import unicodedata
from dataclasses import dataclass
from datetime import date
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SOURCE = ROOT / "data" / "geography"
EXPECTED_PROVINCE_CODES = (
    "10", "11", "12", "13", "14", "15", "16", "17", "18", "19",
    "20", "21", "22", "23", "24", "25", "26", "27",
    "30", "31", "32", "33", "34", "35", "36", "37", "38", "39",
    "40", "41", "42", "43", "44", "45", "46", "47", "48", "49",
    "50", "51", "52", "53", "54", "55", "56", "57", "58",
    "60", "61", "62", "63", "64", "65", "66", "67",
    "70", "71", "72", "73", "74", "75", "76", "77",
    "80", "81", "82", "83", "84", "85", "86",
    "90", "91", "92", "93", "94", "95", "96",
)
EXPECTED_REGION_COUNTS = {
    "bangkok-vicinity": 6,
    "central": 6,
    "eastern": 8,
    "northeastern": 20,
    "northern": 17,
    "southern": 14,
    "western": 6,
}
REGISTRY_KEYS = {
    "$schema",
    "schema_version",
    "dataset_version",
    "country_id",
    "geography_types",
    "classification_schemes",
    "inputs",
    "sources",
}
INPUT_NAMES = {
    "aliases",
    "geometry",
    "normalization_vectors",
    "provinces",
    "regions",
    "relations",
}
ENTITY_KEYS = (
    "id",
    "kind",
    "type",
    "status",
    "slug",
    "names",
    "external_ids",
    "priority",
    "geometry",
)
RELATION_KEYS = (
    "type",
    "object_id",
    "scheme_id",
    "is_primary",
    "valid_from",
    "valid_to",
    "source_id",
)
NORMALIZATION_VERSION = "1.0.0"
OUTPUT_PATHS = (
    "assets/geography/core.json",
    "resources/geography/manifest.json",
    "resources/geography/registry.php",
)
FORBIDDEN_DASHES = ("\u2013", "\u2014")
PUNCTUATION_TRANSLATION = str.maketrans(
    {
        "\u2018": "'",
        "\u2019": "'",
        "\u05f3": "'",
        "\u201c": '"',
        "\u201d": '"',
        "\u05f4": '"',
    }
)
ASCII_LOWER_TRANSLATION = str.maketrans(
    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
    "abcdefghijklmnopqrstuvwxyz",
)


class RegistryError(ValueError):
    """Raised when source data or generated output violates the contract."""


@dataclass(frozen=True)
class BuildResult:
    artifacts: dict[str, bytes]
    registry: dict[str, Any]
    manifest: dict[str, Any]


def require(condition: bool, message: str) -> None:
    if not condition:
        raise RegistryError(message)


def exact_keys(value: Any, expected: set[str], label: str) -> None:
    require(isinstance(value, dict), f"{label} must be an object")
    require(set(value) == expected, f"{label} fields are missing or unexpected")


def sha256_bytes(payload: bytes) -> str:
    return hashlib.sha256(payload).hexdigest()


def read_utf8(path: Path) -> tuple[bytes, str]:
    require(path.is_file() and not path.is_symlink(), f"source is missing or unsafe: {path.name}")
    payload = path.read_bytes()
    try:
        text = payload.decode("utf-8", errors="strict")
    except UnicodeDecodeError as error:
        raise RegistryError(f"source is not strict UTF-8: {path.name}") from error
    require(not text.startswith("\ufeff"), f"source contains a UTF-8 BOM: {path.name}")
    require("\x00" not in text, f"source contains a null byte: {path.name}")
    return payload, text


def reject_nonfinite(value: str) -> None:
    raise RegistryError(f"JSON contains a non-finite number: {value}")


def finite_float(value: str) -> float:
    parsed = float(value)
    require(math.isfinite(parsed), f"JSON contains a non-finite number: {value}")
    return parsed


def parse_json(path: Path) -> tuple[bytes, Any]:
    payload, text = read_utf8(path)

    def pairs_hook(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
        result: dict[str, Any] = {}
        for key, value in pairs:
            if key in result:
                raise RegistryError(f"duplicate JSON key in {path.name}: {key}")
            result[key] = value
        return result

    try:
        value = json.loads(
            text,
            object_pairs_hook=pairs_hook,
            parse_constant=reject_nonfinite,
            parse_float=finite_float,
        )
    except json.JSONDecodeError as error:
        raise RegistryError(f"invalid JSON in {path.name}: {error.msg}") from error
    validate_text_tree(value, path.name)
    return payload, value


def validate_text(value: Any, label: str, *, trimmed: bool = False, nfkc_safe: bool = False) -> str:
    require(type(value) is str, f"{label} must be a string")
    require(value == unicodedata.normalize("NFC", value), f"{label} is not Unicode NFC")
    require(not any(dash in value for dash in FORBIDDEN_DASHES), f"{label} contains a forbidden dash")
    if trimmed:
        require(value and value == value.strip(), f"{label} is empty or has outer whitespace")
    if nfkc_safe:
        require(value == unicodedata.normalize("NFKC", value), f"{label} contains compatibility-only forms")
    return value


def validate_text_tree(value: Any, label: str) -> None:
    if isinstance(value, str):
        validate_text(value, label)
    elif isinstance(value, list):
        for index, nested in enumerate(value):
            validate_text_tree(nested, f"{label}[{index}]")
    elif isinstance(value, dict):
        for key, nested in value.items():
            validate_text(key, f"{label} key")
            validate_text_tree(nested, f"{label}.{key}")


def validate_date(value: Any, label: str, *, nullable: bool = True) -> str | None:
    if value is None and nullable:
        return None
    text = validate_text(value, label, trimmed=True)
    require(bool(re.fullmatch(r"[0-9]{4}-[0-9]{2}-[0-9]{2}", text)), f"{label} is not an ISO date")
    try:
        date.fromisoformat(text)
    except ValueError as error:
        raise RegistryError(f"{label} is not a real calendar date") from error
    return text


def validate_identifier(value: Any, label: str) -> str:
    text = validate_text(value, label, trimmed=True)
    require(bool(re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", text)), f"{label} is invalid")
    return text


def validate_geo_id(value: Any, label: str) -> str:
    text = validate_text(value, label, trimmed=True)
    require(bool(re.fullmatch(r"geo:th:[a-z0-9]+(?::[a-z0-9-]+)*", text)), f"{label} is invalid")
    return text


def normalize_alias(value: str) -> str:
    normalized = unicodedata.normalize("NFKC", value)
    normalized = normalized.translate(PUNCTUATION_TRANSLATION)
    normalized = normalized.translate(ASCII_LOWER_TRANSLATION)
    return " ".join(normalized.split())


def normalize_alias_without_intl(value: str) -> str:
    normalized = value.translate(PUNCTUATION_TRANSLATION)
    normalized = normalized.translate(ASCII_LOWER_TRANSLATION)
    return " ".join(normalized.split())


def read_csv(path: Path, expected_header: list[str]) -> tuple[bytes, list[dict[str, str]]]:
    payload, text = read_utf8(path)
    try:
        reader = csv.reader(io.StringIO(text, newline=""), strict=True)
        rows = list(reader)
    except csv.Error as error:
        raise RegistryError(f"invalid CSV in {path.name}: {error}") from error
    require(bool(rows), f"CSV is empty: {path.name}")
    require(rows[0] == expected_header, f"CSV header is invalid: {path.name}")
    records: list[dict[str, str]] = []
    for row_number, row in enumerate(rows[1:], start=2):
        require(row and any(field != "" for field in row), f"blank CSV row in {path.name}:{row_number}")
        require(len(row) == len(expected_header), f"CSV field count is invalid in {path.name}:{row_number}")
        record = dict(zip(expected_header, row))
        for field, value in record.items():
            validate_text(value, f"{path.name}:{row_number}:{field}")
        records.append(record)
    return payload, records


def validate_registry_contract(source_dir: Path) -> tuple[dict[str, Any], dict[str, dict[str, Any]], dict[str, bytes]]:
    registry_payload, registry = parse_json(source_dir / "registry.json")
    exact_keys(registry, REGISTRY_KEYS, "registry")
    require(registry["$schema"] == "./registry.schema.json", "registry schema reference mismatch")
    require(bool(re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+", str(registry["schema_version"]))), "schema version is invalid")
    require(bool(re.fullmatch(r"[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[1-9][0-9]*", str(registry["dataset_version"]))), "dataset version is invalid")
    require(registry["country_id"] == "geo:th:country", "country ID mismatch")

    geography_types = registry["geography_types"]
    require(isinstance(geography_types, list) and geography_types == sorted(set(geography_types)), "geography types must be unique and sorted")
    for geography_type in geography_types:
        require(bool(re.fullmatch(r"[a-z][a-z0-9]*(?:_[a-z0-9]+)*", str(geography_type))), "geography type is invalid")
    require({"country", "statistical_region", "province"}.issubset(geography_types), "required geography types are missing")
    require(not {"attraction", "business", "product", "real_estate_project", "service"}.intersection(geography_types), "commercial entity entered geography types")

    sources = registry["sources"]
    require(isinstance(sources, list) and bool(sources), "source metadata is empty")
    source_index: dict[str, dict[str, Any]] = {}
    source_ids_in_order: list[str] = []
    source_keys = {"id", "authority", "title", "url", "published_on", "effective_on", "retrieved_on", "license", "covers"}
    for index, source in enumerate(sources):
        exact_keys(source, source_keys, f"source[{index}]")
        source_id = validate_identifier(source["id"], f"source[{index}].id")
        require(source_id not in source_index, f"duplicate source ID: {source_id}")
        for field in ("authority", "title", "license"):
            validate_text(source[field], f"source[{index}].{field}", trimmed=True)
        url = validate_text(source["url"], f"source[{index}].url", trimmed=True)
        require(url.startswith("https://"), f"source URL is not HTTPS: {source_id}")
        validate_date(source["published_on"], f"source[{index}].published_on")
        validate_date(source["effective_on"], f"source[{index}].effective_on")
        validate_date(source["retrieved_on"], f"source[{index}].retrieved_on", nullable=False)
        covers = source["covers"]
        require(isinstance(covers, list) and bool(covers) and len(covers) == len(set(covers)), f"source coverage is invalid: {source_id}")
        for item in covers:
            validate_text(item, f"source[{index}].covers", trimmed=True)
        source_index[source_id] = source
        source_ids_in_order.append(source_id)
    require(source_ids_in_order == sorted(source_ids_in_order), "source metadata must be sorted by ID")

    schemes = registry["classification_schemes"]
    require(isinstance(schemes, list) and bool(schemes), "classification schemes are missing")
    scheme_index: dict[str, dict[str, Any]] = {}
    scheme_ids_in_order: list[str] = []
    scheme_keys = {"id", "kind", "is_administrative_parent", "levels", "source_ids"}
    for index, scheme in enumerate(schemes):
        exact_keys(scheme, scheme_keys, f"classification scheme[{index}]")
        scheme_id = validate_identifier(scheme["id"], f"classification scheme[{index}].id")
        require(scheme_id not in scheme_index, f"duplicate classification scheme: {scheme_id}")
        require(scheme["kind"] in {"administrative", "editorial", "statistical"}, f"classification scheme kind is invalid: {scheme_id}")
        require(type(scheme["is_administrative_parent"]) is bool, f"classification scheme parent flag is not boolean: {scheme_id}")
        levels = scheme["levels"]
        require(isinstance(levels, list) and bool(levels) and len(levels) == len(set(levels)), f"classification levels are invalid: {scheme_id}")
        require(all(level in geography_types for level in levels), f"classification scheme references an unknown geography type: {scheme_id}")
        scheme_sources = scheme["source_ids"]
        require(isinstance(scheme_sources, list) and bool(scheme_sources) and len(scheme_sources) == len(set(scheme_sources)), f"classification source IDs are invalid: {scheme_id}")
        require(all(source_id in source_index for source_id in scheme_sources), f"classification scheme references an unknown source: {scheme_id}")
        scheme_index[scheme_id] = scheme
        scheme_ids_in_order.append(scheme_id)
    require(scheme_ids_in_order == sorted(scheme_ids_in_order), "classification schemes must be sorted by ID")
    require(scheme_index["thai-administrative"]["is_administrative_parent"] is True, "administrative scheme parent flag mismatch")
    require(scheme_index["nso-seven-region-2025"]["is_administrative_parent"] is False, "statistical scheme became an administrative parent")

    inputs = registry["inputs"]
    exact_keys(inputs, INPUT_NAMES, "registry inputs")
    source_payloads: dict[str, bytes] = {
        "registry.json": registry_payload,
    }
    for name in sorted(inputs):
        descriptor = inputs[name]
        exact_keys(descriptor, {"path", "sha256", "source_ids"}, f"input descriptor {name}")
        relative = validate_text(descriptor["path"], f"input descriptor {name}.path", trimmed=True)
        require(bool(re.fullmatch(r"[a-z0-9][a-z0-9._-]*", relative)), f"input path is unsafe: {relative}")
        digest = validate_text(descriptor["sha256"], f"input descriptor {name}.sha256", trimmed=True)
        require(bool(re.fullmatch(r"[0-9a-f]{64}", digest)), f"input digest is invalid: {name}")
        input_sources = descriptor["source_ids"]
        require(isinstance(input_sources, list) and len(input_sources) == len(set(input_sources)), f"input source IDs are invalid: {name}")
        require(all(source_id in source_index for source_id in input_sources), f"input references an unknown source: {name}")
        payload, _ = read_utf8(source_dir / relative)
        require(sha256_bytes(payload) == digest, f"input digest mismatch: {relative}")
        source_payloads[relative] = payload

    schema_payload, schema_document = parse_json(source_dir / "registry.schema.json")
    require(isinstance(schema_document, dict), "registry schema must be an object")
    require(schema_document.get("$schema") == "https://json-schema.org/draft/2020-12/schema", "registry schema draft mismatch")
    require(schema_document.get("$id") == "https://thai-land.co.il/data/geography/registry.schema.json", "registry schema ID mismatch")
    source_payloads["registry.schema.json"] = schema_payload
    return registry, {"sources": source_index, "schemes": scheme_index}, source_payloads


def entity_record(
    entity_id: str,
    entity_type: str,
    slug: str,
    names: dict[str, str],
    external_ids: dict[str, str],
    priority: bool,
) -> dict[str, Any]:
    return {
        "id": entity_id,
        "kind": "geography",
        "type": entity_type,
        "status": "active",
        "slug": slug,
        "names": names,
        "external_ids": external_ids,
        "priority": priority,
        "geometry": None,
    }


def load_entities(
    source_dir: Path,
    contract: dict[str, Any],
    indexes: dict[str, dict[str, Any]],
) -> tuple[dict[str, dict[str, Any]], dict[str, dict[str, str]], dict[str, str], dict[str, Any]]:
    regions_path = source_dir / contract["inputs"]["regions"]["path"]
    _, metadata = parse_json(regions_path)
    exact_keys(metadata, {"schema_version", "country", "administrative_hierarchy", "editorial_entity_types", "region_model", "sources"}, "regions metadata")
    require(metadata["schema_version"] == contract["schema_version"], "regions schema version mismatch")
    require(metadata["administrative_hierarchy"] == ["country", "province", "district", "subdistrict", "village"], "administrative hierarchy mismatch")
    require(set(metadata["editorial_entity_types"]) == set(contract["geography_types"]), "legacy geography types disagree with registry contract")

    country = metadata["country"]
    exact_keys(country, {"id", "name_he", "name_en", "name_th"}, "country")
    require(country["id"] == "TH", "country external ID mismatch")
    country_names = {
        "he": validate_text(country["name_he"], "country Hebrew name", trimmed=True),
        "en": validate_text(country["name_en"], "country English name", trimmed=True),
        "th": validate_text(country["name_th"], "country Thai name", trimmed=True),
    }
    entities: dict[str, dict[str, Any]] = {
        contract["country_id"]: entity_record(
            contract["country_id"],
            "country",
            "thailand",
            country_names,
            {"iso_3166_1_alpha_2": "TH"},
            True,
        )
    }
    source_fields: dict[str, dict[str, str]] = {contract["country_id"]: {}}

    region_model = metadata["region_model"]
    exact_keys(region_model, {"id", "kind", "is_administrative_parent", "as_of", "regions"}, "region model")
    require(region_model["id"] == "nso-seven-region-2025", "region model ID mismatch")
    require(region_model["kind"] == "statistical_facet", "region model kind mismatch")
    require(type(region_model["is_administrative_parent"]) is bool and region_model["is_administrative_parent"] is False, "region model parent flag mismatch")
    validate_date(region_model["as_of"], "region model as_of", nullable=False)
    regions = region_model["regions"]
    require(isinstance(regions, list) and len(regions) == 7, "region model must contain seven regions")
    region_ids: list[str] = []
    region_lookup: dict[str, str] = {}
    for index, region in enumerate(regions):
        exact_keys(region, {"id", "name_he", "name_en", "name_th"}, f"region[{index}]")
        short_id = validate_identifier(region["id"], f"region[{index}].id")
        require(short_id not in region_lookup, f"duplicate region ID: {short_id}")
        entity_id = f"geo:th:region:{region_model['id']}:{short_id}"
        names = {
            "he": validate_text(region["name_he"], f"region {short_id} Hebrew name", trimmed=True),
            "en": validate_text(region["name_en"], f"region {short_id} English name", trimmed=True),
            "th": validate_text(region["name_th"], f"region {short_id} Thai name", trimmed=True),
        }
        entities[entity_id] = entity_record(
            entity_id,
            "statistical_region",
            short_id,
            names,
            {"nso_region_id": short_id},
            False,
        )
        source_fields[entity_id] = {"region_id": short_id}
        region_lookup[short_id] = entity_id
        region_ids.append(short_id)
    metadata_sources = metadata["sources"]
    require(isinstance(metadata_sources, list), "legacy source register is invalid")
    legacy_ids: list[str] = []
    for index, source in enumerate(metadata_sources):
        exact_keys(source, {"id", "authority", "url", "covers"}, f"legacy source[{index}]")
        source_id = validate_identifier(source["id"], f"legacy source[{index}].id")
        require(source_id in indexes["sources"], f"legacy source is not declared: {source_id}")
        canonical = indexes["sources"][source_id]
        require(source["authority"] == canonical["authority"], f"legacy source authority mismatch: {source_id}")
        require(source["url"] == canonical["url"], f"legacy source URL mismatch: {source_id}")
        require(source["covers"] == canonical["covers"], f"legacy source coverage mismatch: {source_id}")
        legacy_ids.append(source_id)
    require(legacy_ids == sorted(legacy_ids), "legacy sources must be sorted by ID")

    province_path = source_dir / contract["inputs"]["provinces"]["path"]
    _, province_rows = read_csv(
        province_path,
        ["code", "slug", "name_en", "name_th", "name_he", "region_id", "priority"],
    )
    require(len(province_rows) == 77, "province registry must contain exactly 77 rows")
    require(tuple(row["code"] for row in province_rows) == EXPECTED_PROVINCE_CODES, "province code set or ordering is invalid")
    seen_slugs: set[str] = set()
    seen_names: dict[str, set[str]] = {"en": set(), "th": set(), "he": set()}
    region_counts = {region_id: 0 for region_id in region_lookup}
    province_region: dict[str, str] = {}
    for row_number, row in enumerate(province_rows, start=2):
        code = row["code"]
        slug = validate_identifier(row["slug"], f"provinces.csv:{row_number}:slug")
        require(slug not in seen_slugs, f"duplicate province slug: {slug}")
        require(row["region_id"] in region_lookup, f"province references an unknown region: {code}")
        require(row["priority"] in {"0", "1"}, f"province priority is invalid: {code}")
        names = {
            "he": validate_text(row["name_he"], f"province {code} Hebrew name", trimmed=True),
            "en": validate_text(row["name_en"], f"province {code} English name", trimmed=True),
            "th": validate_text(row["name_th"], f"province {code} Thai name", trimmed=True),
        }
        for locale, name in names.items():
            require(name not in seen_names[locale], f"duplicate province name for locale {locale}: {name}")
            seen_names[locale].add(name)
        entity_id = f"geo:th:province:{code}"
        entities[entity_id] = entity_record(
            entity_id,
            "province",
            slug,
            names,
            {"iso_3166_2": f"TH-{code}", "moi_province_code": code},
            row["priority"] == "1",
        )
        source_fields[entity_id] = {
            "code": code,
            "region_id": row["region_id"],
            "slug": slug,
        }
        province_region[entity_id] = row["region_id"]
        seen_slugs.add(slug)
        region_counts[row["region_id"]] += 1
    require(region_counts == EXPECTED_REGION_COUNTS, "province statistical membership totals are invalid")
    return entities, source_fields, province_region, {
        "metadata": metadata,
        "region_lookup": region_lookup,
        "region_model": region_model,
    }


def load_geometry(
    source_dir: Path,
    contract: dict[str, Any],
    entities: dict[str, dict[str, Any]],
    source_index: dict[str, Any],
) -> None:
    path = source_dir / contract["inputs"]["geometry"]["path"]
    _, document = parse_json(path)
    exact_keys(document, {"schema_version", "dataset_version", "records"}, "geometry document")
    require(document["schema_version"] == contract["schema_version"], "geometry schema version mismatch")
    require(document["dataset_version"] == contract["dataset_version"], "geometry dataset version mismatch")
    require(isinstance(document["records"], list), "geometry records must be an array")
    seen: set[str] = set()
    for index, record in enumerate(document["records"]):
        exact_keys(record, {"entity_id", "center", "bounds", "source_id"}, f"geometry[{index}]")
        entity_id = validate_geo_id(record["entity_id"], f"geometry[{index}].entity_id")
        require(entity_id in entities and entity_id not in seen, f"geometry entity is missing or duplicated: {entity_id}")
        require(record["source_id"] in source_index, f"geometry source is unknown: {entity_id}")
        exact_keys(record["center"], {"lat", "lng"}, f"geometry[{index}].center")
        exact_keys(record["bounds"], {"south", "west", "north", "east"}, f"geometry[{index}].bounds")
        numbers: dict[str, float] = {}
        for field, raw in {
            "lat": record["center"]["lat"],
            "lng": record["center"]["lng"],
            "south": record["bounds"]["south"],
            "west": record["bounds"]["west"],
            "north": record["bounds"]["north"],
            "east": record["bounds"]["east"],
        }.items():
            require(type(raw) in {int, float} and math.isfinite(float(raw)), f"geometry number is invalid: {entity_id}:{field}")
            numbers[field] = float(raw)
        require(-90 <= numbers["lat"] <= 90 and -180 <= numbers["lng"] <= 180, f"geometry center is out of range: {entity_id}")
        require(-90 <= numbers["south"] < numbers["north"] <= 90, f"geometry latitude bounds are invalid: {entity_id}")
        require(-180 <= numbers["west"] < numbers["east"] <= 180, f"geometry longitude bounds are invalid: {entity_id}")
        require(numbers["south"] <= numbers["lat"] <= numbers["north"], f"geometry center is outside latitude bounds: {entity_id}")
        require(numbers["west"] <= numbers["lng"] <= numbers["east"], f"geometry center is outside longitude bounds: {entity_id}")
        entities[entity_id]["geometry"] = {
            "center": {"lat": numbers["lat"], "lng": numbers["lng"]},
            "bounds": {
                "south": numbers["south"],
                "west": numbers["west"],
                "north": numbers["north"],
                "east": numbers["east"],
            },
        }
        seen.add(entity_id)


def validate_rule_template(template: str, label: str) -> set[str]:
    placeholders = set(re.findall(r"\{([a-z_]+)\}", template))
    stripped = re.sub(r"\{[a-z_]+\}", "", template)
    require("{" not in stripped and "}" not in stripped, f"{label} contains an invalid placeholder")
    require(placeholders.issubset({"code", "region_id", "slug"}), f"{label} contains an unsupported placeholder")
    return placeholders


def validate_relation_semantics(
    subject_id: str,
    relation: dict[str, Any],
    entities: dict[str, dict[str, Any]],
    schemes: dict[str, dict[str, Any]],
) -> None:
    object_id = relation["object_id"]
    require(subject_id in entities, f"relation subject does not exist: {subject_id}")
    require(object_id in entities, f"relation object does not exist: {object_id}")
    require(subject_id != object_id, f"relation cannot target itself: {subject_id}")
    require(relation["scheme_id"] in schemes, f"relation scheme does not exist: {relation['scheme_id']}")
    if relation["type"] == "admin_parent":
        require(schemes[relation["scheme_id"]]["is_administrative_parent"] is True, f"admin parent uses a non-administrative scheme: {subject_id}")
        require(relation["is_primary"] is True, f"admin parent is not primary: {subject_id}")
    elif relation["type"] == "classified_in":
        require(schemes[relation["scheme_id"]]["is_administrative_parent"] is False, f"classification uses an administrative scheme: {subject_id}")
        require(entities[object_id]["type"] in {"statistical_region", "editorial_region"}, f"classification target has the wrong type: {object_id}")
        require(relation["is_primary"] is False, f"classification relation became primary: {subject_id}")


def assert_acyclic(relations: dict[str, list[dict[str, Any]]], relation_types: set[str]) -> None:
    graph: dict[str, list[str]] = {}
    for subject_id, subject_relations in relations.items():
        graph[subject_id] = [relation["object_id"] for relation in subject_relations if relation["type"] in relation_types]
    visiting: set[str] = set()
    visited: set[str] = set()

    def visit(entity_id: str) -> None:
        if entity_id in visiting:
            raise RegistryError(f"geography relation graph contains a cycle at {entity_id}")
        if entity_id in visited:
            return
        visiting.add(entity_id)
        for target_id in graph.get(entity_id, []):
            visit(target_id)
        visiting.remove(entity_id)
        visited.add(entity_id)

    for entity_id in sorted(graph):
        visit(entity_id)


def load_relations(
    source_dir: Path,
    contract: dict[str, Any],
    entities: dict[str, dict[str, Any]],
    source_fields: dict[str, dict[str, str]],
    province_region: dict[str, str],
    region_lookup: dict[str, str],
    source_index: dict[str, Any],
    schemes: dict[str, dict[str, Any]],
) -> dict[str, list[dict[str, Any]]]:
    path = source_dir / contract["inputs"]["relations"]["path"]
    _, document = parse_json(path)
    exact_keys(document, {"schema_version", "dataset_version", "relation_types", "rules", "records"}, "relations document")
    require(document["schema_version"] == contract["schema_version"], "relations schema version mismatch")
    require(document["dataset_version"] == contract["dataset_version"], "relations dataset version mismatch")
    relation_types = document["relation_types"]
    require(isinstance(relation_types, list) and relation_types == sorted(set(relation_types)), "relation types must be unique and sorted")
    allowed_types = {"admin_parent", "available_in", "classified_in", "located_in", "near", "part_of", "serves"}
    require(set(relation_types) == allowed_types, "relation type contract mismatch")
    rules = document["rules"]
    records = document["records"]
    require(isinstance(rules, list) and isinstance(records, list), "relation rules and records must be arrays")
    rule_keys = {"id", "subject_type", "type", "object_id_template", "scheme_id", "is_primary", "valid_from", "valid_to", "source_id"}
    rule_ids: list[str] = []
    expanded: list[tuple[str, dict[str, Any]]] = []
    for index, rule in enumerate(rules):
        exact_keys(rule, rule_keys, f"relation rule[{index}]")
        rule_id = validate_identifier(rule["id"], f"relation rule[{index}].id")
        rule_ids.append(rule_id)
        require(rule["subject_type"] in contract["geography_types"], f"relation rule subject type is invalid: {rule_id}")
        require(rule["type"] in allowed_types, f"relation rule type is invalid: {rule_id}")
        template = validate_text(rule["object_id_template"], f"relation rule {rule_id} object template", trimmed=True)
        placeholders = validate_rule_template(template, f"relation rule {rule_id} object template")
        require(rule["scheme_id"] in schemes, f"relation rule scheme is unknown: {rule_id}")
        require(type(rule["is_primary"]) is bool, f"relation rule primary flag is not boolean: {rule_id}")
        validate_date(rule["valid_from"], f"relation rule {rule_id}.valid_from")
        validate_date(rule["valid_to"], f"relation rule {rule_id}.valid_to")
        require(rule["source_id"] in source_index, f"relation rule source is unknown: {rule_id}")
        for subject_id, entity in sorted(entities.items()):
            if entity["type"] != rule["subject_type"]:
                continue
            fields = source_fields.get(subject_id, {})
            require(placeholders.issubset(fields), f"relation rule cannot resolve source fields: {rule_id}:{subject_id}")
            object_id = template.format(**fields)
            relation = {
                "type": rule["type"],
                "object_id": object_id,
                "scheme_id": rule["scheme_id"],
                "is_primary": rule["is_primary"],
                "valid_from": rule["valid_from"],
                "valid_to": rule["valid_to"],
                "source_id": rule["source_id"],
            }
            expanded.append((subject_id, relation))
    require(rule_ids == sorted(set(rule_ids)), "relation rules must be unique and sorted by ID")

    record_keys = {"subject_id", "type", "object_id", "scheme_id", "is_primary", "valid_from", "valid_to", "source_id"}
    record_sort_keys: list[tuple[str, str, str, str]] = []
    for index, record in enumerate(records):
        exact_keys(record, record_keys, f"relation record[{index}]")
        subject_id = validate_geo_id(record["subject_id"], f"relation record[{index}].subject_id")
        object_id = validate_geo_id(record["object_id"], f"relation record[{index}].object_id")
        require(record["type"] in allowed_types, f"relation record type is invalid: {subject_id}")
        require(record["scheme_id"] in schemes, f"relation record scheme is unknown: {subject_id}")
        require(type(record["is_primary"]) is bool, f"relation record primary flag is not boolean: {subject_id}")
        validate_date(record["valid_from"], f"relation record[{index}].valid_from")
        validate_date(record["valid_to"], f"relation record[{index}].valid_to")
        require(record["source_id"] in source_index, f"relation record source is unknown: {subject_id}")
        relation = {key: record[key] for key in RELATION_KEYS}
        expanded.append((subject_id, relation))
        record_sort_keys.append((subject_id, record["type"], object_id, record["scheme_id"]))
    require(record_sort_keys == sorted(record_sort_keys), "explicit relation records must be sorted")

    relations: dict[str, list[dict[str, Any]]] = {}
    seen: set[tuple[Any, ...]] = set()
    for subject_id, relation in sorted(expanded, key=lambda item: (item[0], item[1]["type"], item[1]["scheme_id"], item[1]["object_id"])):
        validate_relation_semantics(subject_id, relation, entities, schemes)
        identity = (subject_id,) + tuple(relation[key] for key in RELATION_KEYS)
        require(identity not in seen, f"duplicate relation: {subject_id}:{relation['type']}:{relation['object_id']}")
        seen.add(identity)
        relations.setdefault(subject_id, []).append(relation)
    assert_acyclic(relations, {"admin_parent", "part_of"})

    for province_id, expected_region in sorted(province_region.items()):
        province_relations = relations.get(province_id, [])
        admin = [relation for relation in province_relations if relation["type"] == "admin_parent"]
        membership = [relation for relation in province_relations if relation["type"] == "classified_in" and relation["scheme_id"] == "nso-seven-region-2025"]
        require(len(admin) == 1 and admin[0]["object_id"] == contract["country_id"], f"province administrative parent mismatch: {province_id}")
        require(len(membership) == 1 and membership[0]["object_id"] == region_lookup[expected_region], f"province statistical membership mismatch: {province_id}")
    return relations


def load_normalization_vectors(source_dir: Path, contract: dict[str, Any]) -> dict[str, Any]:
    path = source_dir / contract["inputs"]["normalization_vectors"]["path"]
    _, document = parse_json(path)
    exact_keys(document, {"schema_version", "normalization_version", "vectors"}, "normalization vectors")
    require(document["schema_version"] == contract["schema_version"], "normalization schema version mismatch")
    require(document["normalization_version"] == NORMALIZATION_VERSION, "normalization version mismatch")
    require(isinstance(document["vectors"], list) and bool(document["vectors"]), "normalization vectors are empty")
    for index, vector in enumerate(document["vectors"]):
        exact_keys(vector, {"input", "normalized"}, f"normalization vector[{index}]")
        validate_text(vector["input"], f"normalization vector[{index}].input")
        validate_text(vector["normalized"], f"normalization vector[{index}].normalized")
        require(normalize_alias(vector["input"]) == vector["normalized"], f"normalization vector mismatch at index {index}")
    return {
        "version": NORMALIZATION_VERSION,
        "unicode": "NFKC",
        "ascii_case": "lower",
        "punctuation": "U+2018 U+2019 U+05F3 to apostrophe; U+201C U+201D U+05F4 to straight quote",
        "whitespace": "trim and collapse Unicode whitespace to one ASCII space",
        "no_intl_fallback": "also index reviewed NFC canonical names without NFKC when the key differs",
        "vectors": document["vectors"],
    }


def load_aliases(
    source_dir: Path,
    contract: dict[str, Any],
    entities: dict[str, dict[str, Any]],
    relations: dict[str, list[dict[str, Any]]],
    source_index: dict[str, Any],
) -> dict[str, dict[str, list[dict[str, Any]]]]:
    path = source_dir / contract["inputs"]["aliases"]["path"]
    _, records = read_csv(
        path,
        ["entity_id", "locale", "alias", "context_id", "status", "ambiguity_group", "source_id"],
    )
    sort_keys: list[tuple[str, str, str, str, str]] = []
    candidates: dict[tuple[str, str], list[dict[str, Any]]] = {}

    def add_candidate(
        entity_id: str,
        locale: str,
        alias: str,
        context_id: str | None,
        status: str,
        ambiguity_group: str | None,
        include_no_intl_fallback: bool = False,
    ) -> None:
        normalized_values = {normalize_alias(alias)}
        if include_no_intl_fallback:
            normalized_values.add(normalize_alias_without_intl(alias))
        for normalized in normalized_values:
            require(bool(normalized), f"alias normalizes to an empty value: {entity_id}")
            candidates.setdefault((locale, normalized), []).append(
                {
                    "entity_id": entity_id,
                    "context_id": context_id,
                    "status": status,
                    "alias": alias,
                    "ambiguity_group": ambiguity_group,
                }
            )

    for row_number, record in enumerate(records, start=2):
        entity_id = validate_geo_id(record["entity_id"], f"aliases.csv:{row_number}:entity_id")
        require(entity_id in entities, f"alias entity does not exist: {entity_id}")
        locale = validate_text(record["locale"], f"aliases.csv:{row_number}:locale", trimmed=True)
        require(bool(re.fullmatch(r"[a-z]{2}(?:-[A-Z]{2})?", locale)), f"alias locale is invalid: {entity_id}")
        alias = validate_text(record["alias"], f"aliases.csv:{row_number}:alias", trimmed=True, nfkc_safe=True)
        context_id = record["context_id"] or None
        if context_id is not None:
            validate_geo_id(context_id, f"aliases.csv:{row_number}:context_id")
            require(context_id in entities, f"alias context does not exist: {context_id}")
        status = record["status"]
        require(status in {"active", "retired"}, f"alias status is invalid: {entity_id}")
        ambiguity_group = record["ambiguity_group"] or None
        if ambiguity_group is not None:
            validate_identifier(ambiguity_group, f"aliases.csv:{row_number}:ambiguity_group")
        require(record["source_id"] in source_index, f"alias source is unknown: {entity_id}")
        normalized = normalize_alias(alias)
        sort_keys.append((entity_id, locale, normalized, status, alias))
        add_candidate(entity_id, locale, alias, context_id, status, ambiguity_group)
    require(sort_keys == sorted(sort_keys), "aliases must be sorted by entity, locale, normalized value, status, and value")

    primary_parent: dict[str, str] = {}
    for subject_id, subject_relations in relations.items():
        parents = [relation["object_id"] for relation in subject_relations if relation["type"] == "admin_parent" and relation["is_primary"]]
        if len(parents) == 1:
            primary_parent[subject_id] = parents[0]
    for entity_id, entity in sorted(entities.items()):
        if entity["type"] == "country":
            context_id = None
        elif entity["type"] == "statistical_region":
            context_id = contract["country_id"]
        else:
            context_id = primary_parent.get(entity_id)
        for locale, alias in sorted(entity["names"].items()):
            add_candidate(entity_id, locale, alias, context_id, "active", None, True)

    by_alias: dict[str, dict[str, list[dict[str, Any]]]] = {}
    for (locale, normalized), raw_candidates in sorted(candidates.items()):
        unique: dict[tuple[str, str | None, str], dict[str, Any]] = {}
        groups_by_entity: dict[str, set[str]] = {}
        for candidate in raw_candidates:
            identity = (candidate["entity_id"], candidate["context_id"], candidate["status"])
            unique.setdefault(identity, candidate)
            if candidate["status"] == "active" and candidate["ambiguity_group"]:
                groups_by_entity.setdefault(candidate["entity_id"], set()).add(candidate["ambiguity_group"])
        active_entity_ids = sorted({candidate["entity_id"] for candidate in unique.values() if candidate["status"] == "active"})
        if len(active_entity_ids) > 1:
            declared_groups = [groups_by_entity.get(entity_id, set()) for entity_id in active_entity_ids]
            require(all(len(groups) == 1 for groups in declared_groups), f"ambiguous alias lacks an explicit ambiguity group: {locale}:{normalized}")
            group_names = {next(iter(groups)) for groups in declared_groups}
            require(len(group_names) == 1, f"ambiguous alias groups disagree: {locale}:{normalized}")
        output_candidates = []
        for candidate in sorted(unique.values(), key=lambda item: (item["entity_id"], item["status"], item["context_id"] or "", item["alias"])):
            output_candidates.append({key: candidate[key] for key in ("entity_id", "context_id", "status", "alias")})
        by_alias.setdefault(locale, {})[normalized] = output_candidates
    return by_alias


def build_indexes(
    entities: dict[str, dict[str, Any]],
    aliases: dict[str, Any],
    relations: dict[str, list[dict[str, Any]]],
) -> dict[str, Any]:
    by_external_id: dict[str, dict[str, str]] = {}
    by_slug: dict[str, dict[str, str]] = {}
    for entity_id, entity in sorted(entities.items()):
        entity_type = entity["type"]
        slug = entity["slug"]
        require(slug not in by_slug.setdefault(entity_type, {}), f"duplicate slug for type {entity_type}: {slug}")
        by_slug[entity_type][slug] = entity_id
        for namespace, value in sorted(entity["external_ids"].items()):
            namespace_index = by_external_id.setdefault(namespace, {})
            require(value not in namespace_index, f"duplicate external ID: {namespace}:{value}")
            namespace_index[value] = entity_id

    children_by_parent: dict[str, dict[str, list[str]]] = {}
    members_by_scheme: dict[str, dict[str, list[str]]] = {}
    for subject_id, subject_relations in sorted(relations.items()):
        for relation in subject_relations:
            relation_type = relation["type"]
            object_id = relation["object_id"]
            children_by_parent.setdefault(relation_type, {}).setdefault(object_id, []).append(subject_id)
            if relation_type == "classified_in":
                members_by_scheme.setdefault(relation["scheme_id"], {}).setdefault(object_id, []).append(subject_id)
    for parent_map in children_by_parent.values():
        for child_ids in parent_map.values():
            child_ids.sort()
    for scheme_map in members_by_scheme.values():
        for member_ids in scheme_map.values():
            member_ids.sort()
    return {
        "by_external_id": by_external_id,
        "by_slug": by_slug,
        "by_alias": aliases,
        "relations_by_subject": relations,
        "children_by_parent": children_by_parent,
        "members_by_scheme": members_by_scheme,
    }


def public_entity(entity: dict[str, Any]) -> dict[str, Any]:
    return {key: entity[key] for key in ENTITY_KEYS}


def build_public_payload(
    contract: dict[str, Any],
    entities: dict[str, dict[str, Any]],
    indexes: dict[str, Any],
    region_data: dict[str, Any],
) -> dict[str, Any]:
    region_ids = sorted(entity_id for entity_id, entity in entities.items() if entity["type"] == "statistical_region")
    province_ids = sorted(entity_id for entity_id, entity in entities.items() if entity["type"] == "province")
    schemes = []
    for scheme in contract["classification_schemes"]:
        scheme_region_ids = region_ids if scheme["id"] == region_data["region_model"]["id"] else []
        schemes.append(
            {
                "id": scheme["id"],
                "kind": scheme["kind"],
                "is_administrative_parent": scheme["is_administrative_parent"],
                "as_of": region_data["region_model"]["as_of"] if scheme_region_ids else None,
                "region_ids": scheme_region_ids,
            }
        )
    provinces = []
    for entity_id in province_ids:
        entity = public_entity(entities[entity_id])
        subject_relations = indexes["relations_by_subject"][entity_id]
        admin_parent = [relation["object_id"] for relation in subject_relations if relation["type"] == "admin_parent" and relation["is_primary"]]
        memberships = [
            {"scheme_id": relation["scheme_id"], "entity_id": relation["object_id"]}
            for relation in subject_relations
            if relation["type"] == "classified_in"
        ]
        require(len(admin_parent) == 1, f"public province lacks one administrative parent: {entity_id}")
        entity["admin_parent_id"] = admin_parent[0]
        entity["memberships"] = memberships
        provinces.append(entity)
    return {
        "schema_version": contract["schema_version"],
        "dataset_version": contract["dataset_version"],
        "country": public_entity(entities[contract["country_id"]]),
        "classification_schemes": schemes,
        "regions": [public_entity(entities[entity_id]) for entity_id in region_ids],
        "provinces": provinces,
    }


def json_bytes(value: Any) -> bytes:
    return (json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True, allow_nan=False) + "\n").encode("utf-8")


def php_quote(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def php_export(value: Any, level: int = 0) -> str:
    indent = "\t" * level
    child_indent = "\t" * (level + 1)
    if value is None:
        return "null"
    if value is True:
        return "true"
    if value is False:
        return "false"
    if type(value) is int:
        return str(value)
    if type(value) is float:
        require(math.isfinite(value), "generated PHP contains a non-finite float")
        return format(value, ".15g")
    if isinstance(value, str):
        return php_quote(value)
    if isinstance(value, list):
        if not value:
            return "array()"
        items = [f"{child_indent}{php_export(item, level + 1)}," for item in value]
        return "array(\n" + "\n".join(items) + f"\n{indent})"
    if isinstance(value, dict):
        if not value:
            return "array()"
        items = [
            f"{child_indent}{php_quote(str(key))} => {php_export(nested, level + 1)},"
            for key, nested in value.items()
        ]
        return "array(\n" + "\n".join(items) + f"\n{indent})"
    raise RegistryError(f"cannot export PHP value of type {type(value).__name__}")


def registry_php_bytes(registry: dict[str, Any]) -> bytes:
    header = (
        "<?php\n"
        "/**\n"
        " * Generated Thailand geography registry.\n"
        " *\n"
        " * Do not edit this file directly.\n"
        " */\n\n"
        "return "
    )
    return (header + php_export(registry) + ";\n").encode("utf-8")


def compile_registry(source_dir: Path) -> BuildResult:
    source_dir = source_dir.resolve()
    contract, contract_indexes, source_payloads = validate_registry_contract(source_dir)
    entities, source_fields, province_region, region_data = load_entities(source_dir, contract, contract_indexes)
    load_geometry(source_dir, contract, entities, contract_indexes["sources"])
    relations = load_relations(
        source_dir,
        contract,
        entities,
        source_fields,
        province_region,
        region_data["region_lookup"],
        contract_indexes["sources"],
        contract_indexes["schemes"],
    )
    normalization = load_normalization_vectors(source_dir, contract)
    aliases = load_aliases(source_dir, contract, entities, relations, contract_indexes["sources"])
    runtime_indexes = build_indexes(entities, aliases, relations)
    entities_by_id = {entity_id: entities[entity_id] for entity_id in sorted(entities)}
    public_payload = build_public_payload(contract, entities_by_id, runtime_indexes, region_data)
    core_payload = json_bytes(public_payload)
    public_digest = sha256_bytes(core_payload)
    runtime_registry = {
        "schema_version": contract["schema_version"],
        "dataset_version": contract["dataset_version"],
        "country_id": contract["country_id"],
        "public_digest": public_digest,
        "entities_by_id": entities_by_id,
        "indexes": runtime_indexes,
        "public_payload": public_payload,
    }
    php_payload = registry_php_bytes(runtime_registry)
    source_inputs = {
        path: {"sha256": sha256_bytes(payload), "bytes": len(payload)}
        for path, payload in sorted(source_payloads.items())
    }
    entity_type_counts: dict[str, int] = {}
    for entity in entities_by_id.values():
        entity_type_counts[entity["type"]] = entity_type_counts.get(entity["type"], 0) + 1
    alias_key_count = sum(len(values) for values in aliases.values())
    alias_candidate_count = sum(len(candidates) for values in aliases.values() for candidates in values.values())
    relation_count = sum(len(values) for values in relations.values())
    artifacts_without_manifest = {
        "assets/geography/core.json": core_payload,
        "resources/geography/registry.php": php_payload,
    }
    manifest = {
        "schema_version": contract["schema_version"],
        "dataset_version": contract["dataset_version"],
        "country_id": contract["country_id"],
        "counts": {
            "alias_candidates": alias_candidate_count,
            "alias_keys": alias_key_count,
            "entities": len(entities_by_id),
            "provinces": entity_type_counts.get("province", 0),
            "regions": entity_type_counts.get("statistical_region", 0),
            "relations": relation_count,
        },
        "entity_type_counts": dict(sorted(entity_type_counts.items())),
        "normalization": normalization,
        "source_manifest_sha256": sha256_bytes(source_payloads["registry.json"]),
        "source_inputs": source_inputs,
        "artifacts": {
            path: {"sha256": sha256_bytes(payload), "bytes": len(payload)}
            for path, payload in sorted(artifacts_without_manifest.items())
        },
    }
    manifest_payload = json_bytes(manifest)
    artifacts = {
        "assets/geography/core.json": core_payload,
        "resources/geography/manifest.json": manifest_payload,
        "resources/geography/registry.php": php_payload,
    }
    return BuildResult(artifacts=artifacts, registry=runtime_registry, manifest=manifest)


def write_or_check(result: BuildResult, output_root: Path, check: bool) -> None:
    output_root = output_root.resolve()
    stale: list[str] = []
    for relative in OUTPUT_PATHS:
        payload = result.artifacts[relative]
        target = output_root / Path(*relative.split("/"))
        if check:
            if not target.is_file() or target.read_bytes() != payload:
                stale.append(relative)
        else:
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(payload)
    if stale:
        raise RegistryError("generated geography artifacts are stale: " + ", ".join(stale))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-dir", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--output-root", type=Path, default=ROOT)
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    result = compile_registry(args.source_dir)
    write_or_check(result, args.output_root, args.check)
    action = "match" if args.check else "built"
    print(
        f"Geography artifacts {action}: "
        f"dataset {result.registry['dataset_version']}, "
        f"{result.manifest['counts']['provinces']} provinces, "
        f"{result.manifest['counts']['regions']} regions"
    )
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (OSError, RegistryError) as error:
        raise SystemExit(f"REJECT: {error}")
