#!/usr/bin/env python3
"""Validate and compile the Bangkok rental-area data contract."""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import re
import sys
import unicodedata
from dataclasses import dataclass
from datetime import date
from pathlib import Path
from typing import Any, Iterable
from urllib.parse import urlsplit


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SOURCE = ROOT / "data" / "content" / "bangkok-rental-areas.json"
DEFAULT_SCHEMA = ROOT / "data" / "content" / "bangkok-rental-areas.schema.json"
DEFAULT_OUTPUT = ROOT / "resources" / "content" / "bangkok-rental-areas.php"
PUBLIC_LANGUAGE_PATH = ROOT / "tests" / "run.php"

EXPECTED_SCHEMA_ID = (
    "https://thai-land.co.il/schemas/content/bangkok-rental-areas-v1.schema.json"
)
EXPECTED_SCHEMA_SHA256 = "63af098f311c031489044f2a12aa315602ba923ed037782f46844282db0487cd"
EXPECTED_CONTRACT_ID = "bangkok-rental-areas-v1"
EXPECTED_COUNTS = {
    "source_catalog": 14,
    "current_facts": 7,
    "official_districts": 50,
    "stations": 19,
    "corridors": 5,
    "featured_areas": 10,
}
EXPECTED_BMA_CODES = {f"{code:04d}" for code in range(1001, 1051)}
ALLOWED_PERSONA_TAGS = {
    "central",
    "value",
    "nightlife",
    "quiet",
    "family",
    "business",
    "food",
    "rail",
    "green",
    "upscale",
    "local",
}
BANGKOK_LAT_RANGE = (13.40, 14.10)
BANGKOK_LNG_RANGE = (100.30, 100.95)
FORBIDDEN_CODEPOINTS = {
    "\u2013": "en dash",
    "\u2014": "em dash",
}
FORBIDDEN_PUBLIC_PHRASES = {
    "טיוטה",
    "תוכן זמני",
    "טרם אומת",
    "לא אומת",
    "לשימוש פנימי",
    "הפרויקט שלנו",
    "placeholder",
    "verified",
    "unverified",
}
LEGAL_FACT_MARKERS = {
    "tm30",
    "lease",
    "deposit",
    "stamp-duty",
    "controlled-contract",
    "utility-charges",
}


class RegistryError(ValueError):
    """Raised when the authored data cannot be compiled safely."""


@dataclass(frozen=True)
class CompileResult:
    """One validated registry and its deterministic PHP artifact."""

    registry: dict[str, Any]
    artifact: bytes


def reject_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    """Reject duplicate JSON object keys."""
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise RegistryError(f"duplicate JSON key: {key}")
        result[key] = value
    return result


def reject_non_finite(value: str) -> None:
    """Reject NaN and Infinity JSON extensions."""
    raise RegistryError(f"non-finite JSON number: {value}")


def load_json(path: Path) -> dict[str, Any]:
    """Load one strict UTF-8 JSON object."""
    try:
        with path.open("r", encoding="utf-8") as handle:
            value = json.load(
                handle,
                object_pairs_hook=reject_duplicate_keys,
                parse_constant=reject_non_finite,
            )
    except (OSError, UnicodeError, json.JSONDecodeError) as error:
        raise RegistryError(f"cannot load JSON {path}: {error}") from error
    if not isinstance(value, dict):
        raise RegistryError(f"JSON root must be an object: {path}")
    return value


def sha256_lf(path: Path) -> str:
    """Hash a file after deterministic line-ending normalization."""
    try:
        payload = path.read_bytes()
    except OSError as error:
        raise RegistryError(f"cannot read {path}: {error}") from error
    payload = payload.replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    return hashlib.sha256(payload).hexdigest()


def canonical_json(value: Any) -> bytes:
    """Serialize JSON deterministically."""
    try:
        rendered = json.dumps(
            value,
            ensure_ascii=False,
            sort_keys=True,
            separators=(",", ":"),
            allow_nan=False,
        )
    except (TypeError, ValueError) as error:
        raise RegistryError(
            f"value cannot be serialized deterministically: {error}"
        ) from error
    return rendered.encode("utf-8")


def json_type_matches(value: Any, expected: str) -> bool:
    """Return whether a value matches a JSON Schema primitive type."""
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
        return (
            isinstance(value, (int, float))
            and not isinstance(value, bool)
            and math.isfinite(value)
        )
    raise RegistryError(f"unsupported schema type: {expected}")


class SchemaValidator:
    """Dependency-free validator for every schema keyword used by this contract."""

    def __init__(self, schema: dict[str, Any]) -> None:
        self.schema = schema

    def resolve_ref(self, reference: str) -> dict[str, Any]:
        if not reference.startswith("#/"):
            raise RegistryError(f"unsupported schema reference: {reference}")
        current: Any = self.schema
        for raw_part in reference[2:].split("/"):
            part = raw_part.replace("~1", "/").replace("~0", "~")
            if not isinstance(current, dict) or part not in current:
                raise RegistryError(f"unresolvable schema reference: {reference}")
            current = current[part]
        if not isinstance(current, dict):
            raise RegistryError(f"schema reference is not an object: {reference}")
        return current

    def validate(
        self,
        value: Any,
        current: dict[str, Any] | None = None,
        path: str = "$",
    ) -> list[str]:
        schema = self.schema if current is None else current
        if "$ref" in schema:
            return self.validate(value, self.resolve_ref(schema["$ref"]), path)

        if "oneOf" in schema:
            branches = [
                self.validate(value, branch, path) for branch in schema["oneOf"]
            ]
            matches = sum(1 for errors in branches if not errors)
            return (
                []
                if matches == 1
                else [f"{path}: expected exactly one oneOf branch, got {matches}"]
            )

        errors: list[str] = []
        expected_type = schema.get("type")
        if expected_type is not None:
            accepted = (
                expected_type if isinstance(expected_type, list) else [expected_type]
            )
            if not any(json_type_matches(value, item) for item in accepted):
                return [
                    f"{path}: expected type {accepted}, got {type(value).__name__}"
                ]

        if "const" in schema and value != schema["const"]:
            errors.append(f"{path}: expected constant {schema['const']!r}")
        if "enum" in schema and value not in schema["enum"]:
            errors.append(f"{path}: value is outside enum")

        if isinstance(value, str):
            if len(value) < schema.get("minLength", 0):
                errors.append(f"{path}: string shorter than minLength")
            maximum_length = schema.get("maxLength")
            if maximum_length is not None and len(value) > maximum_length:
                errors.append(f"{path}: string longer than maxLength")
            pattern = schema.get("pattern")
            if pattern is not None and re.search(pattern, value) is None:
                errors.append(f"{path}: string does not match {pattern}")
            if schema.get("format") == "date":
                try:
                    parsed = date.fromisoformat(value)
                    if parsed.isoformat() != value:
                        raise ValueError
                except ValueError:
                    errors.append(f"{path}: invalid ISO date")
            if schema.get("format") == "uri":
                parsed_uri = urlsplit(value)
                if not parsed_uri.scheme or not parsed_uri.netloc:
                    errors.append(f"{path}: invalid absolute URI")

        if isinstance(value, (int, float)) and not isinstance(value, bool):
            if not math.isfinite(value):
                errors.append(f"{path}: number is not finite")
            minimum = schema.get("minimum")
            if minimum is not None and value < minimum:
                errors.append(f"{path}: number is below minimum {minimum}")
            maximum = schema.get("maximum")
            if maximum is not None and value > maximum:
                errors.append(f"{path}: number is above maximum {maximum}")

        if isinstance(value, list):
            if len(value) < schema.get("minItems", 0):
                errors.append(f"{path}: array shorter than minItems")
            maximum_items = schema.get("maxItems")
            if maximum_items is not None and len(value) > maximum_items:
                errors.append(f"{path}: array longer than maxItems")
            if schema.get("uniqueItems"):
                serialized = [canonical_json(item) for item in value]
                if len(serialized) != len(set(serialized)):
                    errors.append(f"{path}: array items are not unique")
            item_schema = schema.get("items")
            if isinstance(item_schema, dict):
                for index, item in enumerate(value):
                    errors.extend(
                        self.validate(item, item_schema, f"{path}[{index}]")
                    )

        if isinstance(value, dict):
            properties = schema.get("properties", {})
            for required in schema.get("required", []):
                if required not in value:
                    errors.append(f"{path}: missing required property {required}")
            for key, item in value.items():
                if key in properties:
                    errors.extend(
                        self.validate(item, properties[key], f"{path}.{key}")
                    )
                elif schema.get("additionalProperties") is False:
                    errors.append(f"{path}: unexpected property {key}")

        return errors


def iter_strings(value: Any) -> Iterable[str]:
    """Yield all nested strings, including object keys."""
    if isinstance(value, str):
        yield value
    elif isinstance(value, list):
        for item in value:
            yield from iter_strings(item)
    elif isinstance(value, dict):
        for key, item in value.items():
            yield key
            yield from iter_strings(item)


def iter_public_strings(source: dict[str, Any]) -> Iterable[str]:
    """Yield visitor-facing labels and descriptive copy."""
    yield from iter_strings(source["public_labels"])
    yield source["entity_policy"]["relationship"]
    yield source["pricing_method"]["public_label"]
    for item in source["source_catalog"]:
        yield item["name"]
        yield item["publisher"]
    for fact in source["current_facts"]:
        yield fact["public_label"]
        yield fact["public_value"]
    for collection in ("official_districts", "stations"):
        for item in source[collection]:
            yield from iter_strings(item["names"])
            if "aliases" in item:
                yield from iter_strings(item["aliases"])
    for corridor in source["corridors"]:
        yield from iter_strings(corridor["names"])
        yield corridor["summary"]
    for area in source["featured_areas"]:
        yield from iter_strings(area["names"])
        yield from iter_strings(area["aliases"])
        yield area["monthly_asking_bands"]["basis_label"]
        yield area["fit_summary"]
        yield area["tradeoff"]
        yield from iter_strings(area["micro_area_notes"])
        yield from iter_strings(area["daily_life_cues"])
        yield from iter_strings(area["public_copy"])


def normalize_alias(value: str) -> str:
    """Normalize an alias for deterministic collision checks and indexes."""
    normalized = unicodedata.normalize("NFKC", value).casefold()
    normalized = "".join(
        " " if unicodedata.category(character).startswith("P") else character
        for character in normalized
    )
    return " ".join(normalized.split())


def parse_boundary_phrases(path: Path = PUBLIC_LANGUAGE_PATH) -> tuple[str, ...]:
    """Read the shared 50-phrase public-language boundary."""
    try:
        text = path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as error:
        raise RegistryError(f"cannot read public-language boundary: {error}") from error
    start = text.find("$presentation_phrases = array(")
    end = text.find(");", start)
    if start < 0 or end < 0:
        raise RegistryError("public-language boundary is missing")
    phrases = tuple(re.findall(r"'([^']*)'", text[start:end]))
    if len(phrases) != 50 or len(set(phrases)) != 50:
        raise RegistryError("public-language boundary must contain exactly 50 phrases")
    return phrases


def validate_schema_contract(schema: dict[str, Any], schema_path: Path) -> str:
    """Require the exact reviewed schema ID, constants, and digest."""
    if schema.get("$id") != EXPECTED_SCHEMA_ID:
        raise RegistryError("schema ID drift")
    properties = schema.get("properties", {})
    if properties.get("schema_version", {}).get("const") != 1:
        raise RegistryError("schema version drift")
    if properties.get("contract_id", {}).get("const") != EXPECTED_CONTRACT_ID:
        raise RegistryError("schema contract ID drift")
    digest = sha256_lf(schema_path)
    if digest != EXPECTED_SCHEMA_SHA256:
        raise RegistryError(
            f"schema drift: expected {EXPECTED_SCHEMA_SHA256}, got {digest}"
        )
    return digest


def validate_characters_and_language(source: dict[str, Any]) -> None:
    """Enforce typography and shared public-language boundaries."""
    for value in iter_strings(source):
        for character, label in FORBIDDEN_CODEPOINTS.items():
            if character in value:
                raise RegistryError(f"forbidden {label} in Bangkok data")

    boundary = parse_boundary_phrases()
    for value in iter_public_strings(source):
        folded = value.casefold()
        for phrase in boundary:
            if phrase.casefold() in folded:
                raise RegistryError(
                    f"forbidden presentation phrase in public Bangkok data: {phrase}"
                )
        for phrase in FORBIDDEN_PUBLIC_PHRASES:
            if phrase.casefold() in folded:
                raise RegistryError(
                    f"forbidden internal phrase in public Bangkok data: {phrase}"
                )


def unique_records(
    items: list[dict[str, Any]], field: str, label: str
) -> dict[str, dict[str, Any]]:
    """Index records while rejecting duplicate identifiers."""
    indexed: dict[str, dict[str, Any]] = {}
    for item in items:
        value = item[field]
        if value in indexed:
            raise RegistryError(f"duplicate {label}: {value}")
        indexed[value] = item
    return indexed


def validate_name_namespace(
    items: list[dict[str, Any]], id_field: str, label: str
) -> None:
    """Reject ambiguous canonical names and aliases within one entity domain."""
    seen_by_language: dict[str, dict[str, str]] = {
        "he": {},
        "en": {},
        "th": {},
    }
    for item in items:
        item_id = item[id_field]
        aliases = item.get("aliases", {"he": [], "en": [], "th": []})
        for language in ("he", "en", "th"):
            terms = [item["names"][language], *aliases.get(language, [])]
            for term in terms:
                normalized = normalize_alias(term)
                if not normalized:
                    raise RegistryError(f"empty normalized {label} alias: {item_id}")
                previous = seen_by_language[language].get(normalized)
                if previous is not None and previous != item_id:
                    raise RegistryError(
                        f"ambiguous {label} alias: {term} belongs to {previous} and {item_id}"
                    )
                seen_by_language[language][normalized] = item_id


def parse_contract_date(value: str, label: str) -> date:
    """Parse an exact YYYY-MM-DD value."""
    try:
        parsed = date.fromisoformat(value)
    except ValueError as error:
        raise RegistryError(f"invalid date for {label}: {value}") from error
    if parsed.isoformat() != value:
        raise RegistryError(f"non-canonical date for {label}: {value}")
    return parsed


def validate_source_references(
    owner_id: str, source_ids: list[str], sources_by_id: dict[str, dict[str, Any]]
) -> None:
    """Require every source foreign key to resolve."""
    for source_id in source_ids:
        if source_id not in sources_by_id:
            raise RegistryError(f"unknown source on {owner_id}: {source_id}")


def validate_coordinates(item_id: str, coordinates: dict[str, Any]) -> None:
    """Keep authored map points inside a plausible Bangkok envelope."""
    latitude = coordinates["lat"]
    longitude = coordinates["lng"]
    if not (BANGKOK_LAT_RANGE[0] <= latitude <= BANGKOK_LAT_RANGE[1]):
        raise RegistryError(f"latitude is outside Bangkok bounds: {item_id}")
    if not (BANGKOK_LNG_RANGE[0] <= longitude <= BANGKOK_LNG_RANGE[1]):
        raise RegistryError(f"longitude is outside Bangkok bounds: {item_id}")


def validate_source_and_date_contracts(
    source: dict[str, Any], sources_by_id: dict[str, dict[str, Any]]
) -> None:
    """Validate evidence dates, official legal sources, and pricing sources."""
    checked_on = parse_contract_date(source["checked_on"], "contract")
    pricing = source["pricing_method"]
    if parse_contract_date(pricing["checked_on"], "pricing method") != checked_on:
        raise RegistryError("pricing method date differs from contract date")
    validate_source_references(
        pricing["pricing_method_id"], pricing["source_ids"], sources_by_id
    )
    if not any(
        sources_by_id[source_id]["kind"] == "market_listings"
        for source_id in pricing["source_ids"]
    ):
        raise RegistryError("pricing method lacks market-listing evidence")

    for evidence in sources_by_id.values():
        evidence_date = parse_contract_date(
            evidence["checked_on"], evidence["source_id"]
        )
        if evidence_date > checked_on:
            raise RegistryError(
                f"source date is later than contract date: {evidence['source_id']}"
            )

    legal_fact_count = 0
    for fact in source["current_facts"]:
        validate_source_references(fact["fact_id"], fact["source_ids"], sources_by_id)
        fact_date = parse_contract_date(fact["checked_on"], fact["fact_id"])
        if fact_date > checked_on:
            raise RegistryError(f"fact date is later than contract date: {fact['fact_id']}")
        effective_on = fact.get("effective_on")
        if effective_on is not None:
            effective_date = parse_contract_date(
                effective_on, f"{fact['fact_id']} effective date"
            )
            if effective_date > fact_date:
                raise RegistryError(
                    f"fact effective date is later than its check date: {fact['fact_id']}"
                )
        if any(marker in fact["fact_id"] for marker in LEGAL_FACT_MARKERS):
            legal_fact_count += 1
            official_legal_sources = [
                sources_by_id[source_id]
                for source_id in fact["source_ids"]
                if sources_by_id[source_id]["kind"] == "official_government"
                and (urlsplit(sources_by_id[source_id]["url"]).hostname or "")
                .casefold()
                .endswith(".go.th")
            ]
            if not official_legal_sources:
                raise RegistryError(
                    f"legal fact lacks official government evidence: {fact['fact_id']}"
                )
    if legal_fact_count == 0:
        raise RegistryError("no official-source legal facts were identified")


def validate_rent_band(
    area_id: str,
    bedroom_label: str,
    band: dict[str, Any],
    rounding_thb: int,
) -> None:
    """Validate one plausible, ordered, rounded asking-price range."""
    minimum = band["min_thb"]
    maximum = band["max_thb"]
    if minimum >= maximum:
        raise RegistryError(f"invalid rent band order: {area_id} {bedroom_label}")
    if minimum < 5000 or maximum > 500000:
        raise RegistryError(f"implausible rent band: {area_id} {bedroom_label}")
    if minimum % rounding_thb != 0 or maximum % rounding_thb != 0:
        raise RegistryError(f"unrounded rent band: {area_id} {bedroom_label}")


def validate_semantics(source: dict[str, Any]) -> dict[str, dict[str, dict[str, Any]]]:
    """Validate identity, references, coverage, coordinates, prices, and dates."""
    for field, expected in EXPECTED_COUNTS.items():
        if len(source[field]) != expected:
            raise RegistryError(
                f"expected {expected} {field}, got {len(source[field])}"
            )

    sources_by_id = unique_records(source["source_catalog"], "source_id", "source ID")
    facts_by_id = unique_records(source["current_facts"], "fact_id", "fact ID")
    districts_by_id = unique_records(
        source["official_districts"], "district_id", "district ID"
    )
    stations_by_id = unique_records(source["stations"], "station_id", "station ID")
    corridors_by_id = unique_records(
        source["corridors"], "corridor_id", "corridor ID"
    )
    areas_by_id = unique_records(source["featured_areas"], "area_id", "area ID")

    global_ids = [source["pricing_method"]["pricing_method_id"]]
    for collection in (
        sources_by_id,
        facts_by_id,
        districts_by_id,
        stations_by_id,
        corridors_by_id,
        areas_by_id,
    ):
        global_ids.extend(collection)
    if len(global_ids) != len(set(global_ids)):
        raise RegistryError("stable IDs must be globally unique")

    district_codes = {district["bma_code"] for district in districts_by_id.values()}
    if district_codes != EXPECTED_BMA_CODES:
        missing = sorted(EXPECTED_BMA_CODES - district_codes)
        extra = sorted(district_codes - EXPECTED_BMA_CODES)
        raise RegistryError(f"BMA district code set mismatch: missing={missing}, extra={extra}")
    if len(district_codes) != len(districts_by_id):
        raise RegistryError("duplicate BMA district code")

    validate_name_namespace(source["official_districts"], "district_id", "district")
    validate_name_namespace(source["stations"], "station_id", "station")
    validate_name_namespace(source["featured_areas"], "area_id", "market-area")

    station_codes = [station["code"] for station in source["stations"]]
    if len(station_codes) != len(set(station_codes)):
        raise RegistryError("duplicate station code")
    english_area_names = [
        normalize_alias(area["names"]["en"]) for area in source["featured_areas"]
    ]
    if len(english_area_names) != len(set(english_area_names)):
        raise RegistryError("featured-area English names must be unique")
    hebrew_summaries = [
        normalize_alias(area["public_copy"]["summary"])
        for area in source["featured_areas"]
    ]
    if len(hebrew_summaries) != len(set(hebrew_summaries)):
        raise RegistryError("featured-area Hebrew summaries must be unique")

    validate_source_and_date_contracts(source, sources_by_id)
    for district in districts_by_id.values():
        validate_source_references(
            district["district_id"], district["source_ids"], sources_by_id
        )
    for station in stations_by_id.values():
        validate_source_references(
            station["station_id"], station["source_ids"], sources_by_id
        )
        validate_coordinates(station["station_id"], station["coordinates"])

    all_area_ids = set(areas_by_id)
    all_station_ids = set(stations_by_id)
    corridor_area_owner: dict[str, str] = {}
    covered_station_ids: set[str] = set()
    for corridor in corridors_by_id.values():
        corridor_id = corridor["corridor_id"]
        corridor_area_ids = set(corridor["area_ids"])
        corridor_station_ids = set(corridor["station_ids"])
        unknown_areas = corridor_area_ids - all_area_ids
        unknown_stations = corridor_station_ids - all_station_ids
        if unknown_areas:
            raise RegistryError(
                f"unknown corridor areas on {corridor_id}: {sorted(unknown_areas)}"
            )
        if unknown_stations:
            raise RegistryError(
                f"unknown corridor stations on {corridor_id}: {sorted(unknown_stations)}"
            )
        for area_id in corridor_area_ids:
            previous = corridor_area_owner.get(area_id)
            if previous is not None:
                raise RegistryError(
                    f"area belongs to multiple corridors: {area_id} {previous} {corridor_id}"
                )
            corridor_area_owner[area_id] = corridor_id
        member_station_ids = {
            station_id
            for area_id in corridor_area_ids
            for station_id in areas_by_id[area_id]["station_ids"]
        }
        if member_station_ids != corridor_station_ids:
            raise RegistryError(f"corridor station membership mismatch: {corridor_id}")
        covered_station_ids.update(corridor_station_ids)

    if set(corridor_area_owner) != all_area_ids:
        raise RegistryError("corridors do not cover every featured area exactly once")
    if covered_station_ids != all_station_ids:
        raise RegistryError("corridors do not cover every station")

    pricing = source["pricing_method"]
    contract_date = source["checked_on"]
    for area in areas_by_id.values():
        area_id = area["area_id"]
        validate_coordinates(area_id, area["coordinates"])
        unknown_districts = set(area["official_district_ids"]) - set(districts_by_id)
        unknown_stations = set(area["station_ids"]) - all_station_ids
        if unknown_districts:
            raise RegistryError(
                f"unknown district reference on {area_id}: {sorted(unknown_districts)}"
            )
        if unknown_stations:
            raise RegistryError(
                f"unknown station reference on {area_id}: {sorted(unknown_stations)}"
            )
        corridor_id = area["corridor_id"]
        if corridor_id not in corridors_by_id:
            raise RegistryError(f"unknown corridor reference on {area_id}: {corridor_id}")
        if corridor_area_owner.get(area_id) != corridor_id:
            raise RegistryError(f"area and corridor membership disagree: {area_id}")

        tags = set(area["persona_tags"])
        if not tags <= ALLOWED_PERSONA_TAGS:
            raise RegistryError(
                f"unknown persona tags on {area_id}: {sorted(tags - ALLOWED_PERSONA_TAGS)}"
            )
        asking = area["monthly_asking_bands"]
        if asking["pricing_method_id"] != pricing["pricing_method_id"]:
            raise RegistryError(f"pricing method mismatch on {area_id}")
        for field in ("currency", "unit"):
            if asking[field] != pricing[field]:
                raise RegistryError(f"pricing {field} mismatch on {area_id}")
        if asking["checked_on"] != contract_date or asking["checked_on"] != pricing["checked_on"]:
            raise RegistryError(f"pricing date mismatch on {area_id}")
        validate_source_references(area_id, asking["source_ids"], sources_by_id)
        if not any(
            sources_by_id[source_id]["kind"] == "market_listings"
            for source_id in asking["source_ids"]
        ):
            raise RegistryError(f"rent band lacks market-listing evidence: {area_id}")
        one_bedroom = asking["one_bedroom"]
        two_bedroom = asking["two_bedroom"]
        validate_rent_band(
            area_id, "one_bedroom", one_bedroom, pricing["rounding_thb"]
        )
        validate_rent_band(
            area_id, "two_bedroom", two_bedroom, pricing["rounding_thb"]
        )
        if (
            two_bedroom["min_thb"] <= one_bedroom["min_thb"]
            or two_bedroom["max_thb"] <= one_bedroom["max_thb"]
        ):
            raise RegistryError(f"two-bedroom band is not above one-bedroom band: {area_id}")

    return {
        "sources_by_id": sources_by_id,
        "facts_by_id": facts_by_id,
        "districts_by_id": districts_by_id,
        "stations_by_id": stations_by_id,
        "corridors_by_id": corridors_by_id,
        "areas_by_id": areas_by_id,
    }


def alias_index(items: list[dict[str, Any]], id_field: str) -> dict[str, dict[str, str]]:
    """Build deterministic multilingual canonical-name and alias indexes."""
    result: dict[str, dict[str, str]] = {"he": {}, "en": {}, "th": {}}
    for item in items:
        aliases = item.get("aliases", {"he": [], "en": [], "th": []})
        for language in ("he", "en", "th"):
            for term in [item["names"][language], *aliases.get(language, [])]:
                result[language][normalize_alias(term)] = item[id_field]
    return {
        language: dict(sorted(index.items()))
        for language, index in result.items()
    }


def build_runtime_registry(
    source: dict[str, Any],
    indexed: dict[str, dict[str, dict[str, Any]]],
    source_digest: str,
    schema_digest: str,
) -> dict[str, Any]:
    """Build deterministic lookup indexes without losing editorial order."""
    registry: dict[str, Any] = {
        "schema_version": source["schema_version"],
        "contract_id": source["contract_id"],
        "source_sha256": source_digest,
        "schema_sha256": schema_digest,
        "site": source["site"],
        "checked_on": source["checked_on"],
        "entity_policy": source["entity_policy"],
        "public_labels": source["public_labels"],
        "pricing_method": source["pricing_method"],
        "sources_by_id": dict(sorted(indexed["sources_by_id"].items())),
        "facts_by_id": dict(sorted(indexed["facts_by_id"].items())),
        "districts_by_id": dict(sorted(indexed["districts_by_id"].items())),
        "stations_by_id": dict(sorted(indexed["stations_by_id"].items())),
        "corridors_by_id": dict(sorted(indexed["corridors_by_id"].items())),
        "areas_by_id": dict(sorted(indexed["areas_by_id"].items())),
        "source_order": [item["source_id"] for item in source["source_catalog"]],
        "fact_order": [item["fact_id"] for item in source["current_facts"]],
        "district_order": [
            item["district_id"] for item in source["official_districts"]
        ],
        "station_order": [item["station_id"] for item in source["stations"]],
        "corridor_order": [item["corridor_id"] for item in source["corridors"]],
        "area_order": [item["area_id"] for item in source["featured_areas"]],
        "district_id_by_bma_code": dict(
            sorted(
                (item["bma_code"], item["district_id"])
                for item in source["official_districts"]
            )
        ),
        "station_id_by_code": dict(
            sorted((item["code"], item["station_id"]) for item in source["stations"])
        ),
        "station_ids_by_line_id": {
            line_id: sorted(
                station["station_id"]
                for station in source["stations"]
                if station["line_id"] == line_id
            )
            for line_id in sorted({item["line_id"] for item in source["stations"]})
        },
        "area_ids_by_corridor_id": {
            corridor["corridor_id"]: list(corridor["area_ids"])
            for corridor in sorted(
                source["corridors"], key=lambda item: item["corridor_id"]
            )
        },
        "district_id_by_alias": alias_index(
            source["official_districts"], "district_id"
        ),
        "station_id_by_alias": alias_index(source["stations"], "station_id"),
        "area_id_by_alias": alias_index(source["featured_areas"], "area_id"),
    }
    registry["registry_sha256"] = hashlib.sha256(canonical_json(registry)).hexdigest()
    return registry


def render_php(registry: dict[str, Any]) -> bytes:
    """Render a guarded PHP file containing one deterministic JSON payload."""
    payload = json.dumps(
        registry,
        ensure_ascii=False,
        sort_keys=True,
        indent=2,
        allow_nan=False,
    )
    document = f"""<?php
/**
 * Generated Bangkok rental-area registry.
 *
 * Run scripts/build_bangkok_rental_registry.py to rebuild this file.
 */

if ( ! defined( 'ABSPATH' ) ) {{
\texit;
}}

return json_decode(
\t<<<'THAILAND_PLATFORM_BANGKOK_RENTAL_JSON'
{payload}
THAILAND_PLATFORM_BANGKOK_RENTAL_JSON,
\ttrue,
\t512,
\tJSON_THROW_ON_ERROR
);
"""
    return document.encode("utf-8")


def compile_registry(
    source_path: Path = DEFAULT_SOURCE,
    schema_path: Path = DEFAULT_SCHEMA,
) -> CompileResult:
    """Compile one authored file after every fail-closed check passes."""
    source_path = Path(source_path)
    schema_path = Path(schema_path)
    source = load_json(source_path)
    schema = load_json(schema_path)
    schema_digest = validate_schema_contract(schema, schema_path)
    errors = SchemaValidator(schema).validate(source)
    if errors:
        raise RegistryError("schema validation failed: " + "; ".join(errors))
    if source["contract_id"] != EXPECTED_CONTRACT_ID:
        raise RegistryError("Bangkok content contract ID mismatch")
    validate_characters_and_language(source)
    indexed = validate_semantics(source)
    registry = build_runtime_registry(
        source,
        indexed,
        sha256_lf(source_path),
        schema_digest,
    )
    artifact = render_php(registry)
    return CompileResult(registry=registry, artifact=artifact)


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--schema", type=Path, default=DEFAULT_SCHEMA)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument(
        "--check",
        action="store_true",
        help="fail when the compiled output is missing or stale",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    arguments = parse_args(argv)
    try:
        result = compile_registry(arguments.source, arguments.schema)
        if arguments.check:
            try:
                current = arguments.output.read_bytes()
            except OSError as error:
                raise RegistryError(
                    f"compiled registry is missing: {arguments.output}: {error}"
                ) from error
            if current != result.artifact:
                raise RegistryError(f"compiled registry is stale: {arguments.output}")
            print(f"PASS: Bangkok rental registry is current: {arguments.output}")
            return 0

        arguments.output.parent.mkdir(parents=True, exist_ok=True)
        arguments.output.write_bytes(result.artifact)
        print(f"WROTE: {arguments.output}")
        print(f"SHA256: {hashlib.sha256(result.artifact).hexdigest()}")
        return 0
    except RegistryError as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
