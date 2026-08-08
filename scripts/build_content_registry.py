#!/usr/bin/env python3
"""Validate and compile the real-estate content contract deterministically."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import unicodedata
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SOURCE = ROOT / "data" / "content" / "real-estate.json"
DEFAULT_SCHEMA = ROOT / "data" / "content" / "real-estate.schema.json"
DEFAULT_OUTPUT = ROOT / "resources" / "content" / "real-estate.php"

EXPECTED_SCHEMA_ID = (
    "https://thai-land.co.il/schemas/content/real-estate-v1.schema.json"
)
EXPECTED_SCHEMA_SHA256 = (
	"aab1c04b4c47a826f5656412e99ef21458c9395eeb737d54311ec3062cf85d1d"
)
EXPECTED_CONTRACT_ID = "thailand-real-estate-v1"
EXPECTED_HUB_ROUTE_ID = "thailand-real-estate"
EXPECTED_BINDINGS: dict[str, tuple[int | None, str]] = {
    "thailand-real-estate": (841, "/נדלן-בתאילנד/"),
    "thailand-property-financing": (
        65,
        "/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/",
    ),
    "thailand-property-buying-mistakes": (
        69,
        "/5-הטעויות-המובילות-שיש-להימנע-מהן-בעת/",
    ),
    "bangkok-apartment-rental": (118, "/מדריך-להשכרת-דירה-בבנגקוק/"),
    "buy-property-thailand": (336, "/קניית-נכס-בתאילנד/"),
    "foreign-condo-ownership-thailand": (
        474,
        "/זכויות-בית-משותף-נכס-בתאילנד/",
    ),
    "thailand-property-management": (609, "/property-management/"),
    "thailand-property-prices": (810, "/price/"),
}
FORBIDDEN_CODEPOINTS = {
    "\u200b": "zero width space",
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


class RegistryError(ValueError):
    """Raised when the authored contract cannot be compiled safely."""


@dataclass(frozen=True)
class CompileResult:
    """One validated runtime registry and its deterministic PHP artifact."""

    registry: dict[str, Any]
    artifact: bytes


def reject_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    """Reject duplicate JSON object keys instead of accepting the last value."""
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise RegistryError(f"duplicate JSON key: {key}")
        result[key] = value
    return result


def reject_non_finite(value: str) -> None:
    """Reject JSON extensions such as NaN and Infinity."""
    raise RegistryError(f"non-finite JSON number: {value}")


def load_json(path: Path) -> dict[str, Any]:
    """Load a strict UTF-8 JSON object."""
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
    """Hash bytes after deterministic line-ending normalization."""
    try:
        payload = path.read_bytes()
    except OSError as error:
        raise RegistryError(f"cannot read {path}: {error}") from error
    payload = payload.replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    return hashlib.sha256(payload).hexdigest()


def canonical_json(value: Any) -> bytes:
    """Serialize JSON with stable keys, Unicode, separators, and line endings."""
    try:
        rendered = json.dumps(
            value,
            ensure_ascii=False,
            sort_keys=True,
            separators=(",", ":"),
            allow_nan=False,
        )
    except (TypeError, ValueError) as error:
        raise RegistryError(f"value cannot be serialized deterministically: {error}") from error
    return rendered.encode("utf-8")


def json_type_matches(value: Any, expected: str) -> bool:
    """Return whether a Python value matches a JSON Schema primitive type."""
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
        return isinstance(value, (int, float)) and not isinstance(value, bool)
    raise RegistryError(f"unsupported schema type: {expected}")


class SchemaValidator:
    """Dependency-free validator for every JSON Schema keyword used here."""

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
            match_count = sum(1 for errors in branches if not errors)
            if match_count != 1:
                return [
                    f"{path}: expected exactly one oneOf branch, got {match_count}"
                ]
            return []

        errors: list[str] = []
        expected_type = schema.get("type")
        if expected_type is not None:
            accepted = expected_type if isinstance(expected_type, list) else [expected_type]
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
            maximum = schema.get("maxLength")
            if maximum is not None and len(value) > maximum:
                errors.append(f"{path}: string longer than maxLength")
            pattern = schema.get("pattern")
            if pattern is not None and re.search(pattern, value) is None:
                errors.append(f"{path}: string does not match {pattern}")

        if isinstance(value, list):
            if len(value) < schema.get("minItems", 0):
                errors.append(f"{path}: array shorter than minItems")
            maximum = schema.get("maxItems")
            if maximum is not None and len(value) > maximum:
                errors.append(f"{path}: array longer than maxItems")
            if schema.get("uniqueItems"):
                items = [canonical_json(item) for item in value]
                if len(items) != len(set(items)):
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
    """Yield every string nested below a JSON-compatible value."""
    if isinstance(value, str):
        yield value
    elif isinstance(value, list):
        for item in value:
            yield from iter_strings(item)
    elif isinstance(value, dict):
        for key, item in value.items():
            yield key
            yield from iter_strings(item)


def normalize_term(value: str) -> str:
    """Normalize a keyword for exact ownership collision checks."""
    normalized = unicodedata.normalize("NFKC", value).casefold()
    normalized = "".join(
        " " if unicodedata.category(character).startswith("P") else character
        for character in normalized
    )
    return " ".join(normalized.split())


def require_unique_ids(items: list[dict[str, Any]], field: str, label: str) -> None:
    """Reject duplicate identifiers in a catalog."""
    seen: set[str] = set()
    for item in items:
        value = item[field]
        if value in seen:
            raise RegistryError(f"duplicate {label}: {value}")
        seen.add(value)


def validate_schema_contract(schema: dict[str, Any], schema_path: Path) -> str:
    """Require the exact reviewed schema before validating source data."""
    if schema.get("$id") != EXPECTED_SCHEMA_ID:
        raise RegistryError("schema ID drift")
    if schema.get("properties", {}).get("schema_version", {}).get("const") != 1:
        raise RegistryError("schema version drift")
    if (
        schema.get("properties", {}).get("contract_id", {}).get("const")
        != EXPECTED_CONTRACT_ID
    ):
        raise RegistryError("schema contract ID drift")
    digest = sha256_lf(schema_path)
    if digest != EXPECTED_SCHEMA_SHA256:
        raise RegistryError(
            f"schema drift: expected {EXPECTED_SCHEMA_SHA256}, got {digest}"
        )
    return digest


def validate_forbidden_characters(source: dict[str, Any]) -> None:
    """Reject typography characters that are forbidden in public output."""
    for value in iter_strings(source):
        for character, label in FORBIDDEN_CODEPOINTS.items():
            if character in value:
                raise RegistryError(f"forbidden {label} in content contract")


def public_values(source: dict[str, Any]) -> Iterable[str]:
    """Yield copy that can be shown directly to visitors."""
    yield from iter_strings(source["public_labels"])
    yield from iter_strings(source["freshness_catalog"])
    yield from iter_strings(source["source_catalog"])
    for route in source["routes"]:
        yield from iter_strings(route["public"])
        yield from iter_strings(route["breadcrumbs"])
        if route["parent_link"] is not None:
            yield from iter_strings(route["parent_link"])
        yield from iter_strings(route["continuations"])
    yield from iter_strings(source["hub_experience"])


def validate_public_language(source: dict[str, Any]) -> None:
    """Keep build-state language out of copy intended for rendering."""
    for value in public_values(source):
        folded = unicodedata.normalize("NFKC", value).casefold()
        for phrase in FORBIDDEN_PUBLIC_PHRASES:
            if phrase.casefold() in folded:
                raise RegistryError(f"forbidden public phrase: {phrase}")


def validate_catalogs(source: dict[str, Any]) -> tuple[set[str], set[str]]:
    """Validate freshness and source catalogs and return their IDs."""
    freshness = source["freshness_catalog"]
    sources = source["source_catalog"]
    require_unique_ids(freshness, "freshness_id", "freshness ID")
    require_unique_ids(sources, "source_id", "source ID")

    urls: set[str] = set()
    for item in sources:
        url = item["url"]
        if url in urls:
            raise RegistryError(f"duplicate source URL: {url}")
        urls.add(url)

    return (
        {item["freshness_id"] for item in freshness},
        {item["source_id"] for item in sources},
    )


def validate_routes(
    source: dict[str, Any],
    freshness_ids: set[str],
    source_ids: set[str],
) -> dict[str, dict[str, Any]]:
    """Validate exact identities, ownership, hierarchy, and route-local copy."""
    routes = source["routes"]
    if len(routes) != len(EXPECTED_BINDINGS):
        raise RegistryError(
            f"expected {len(EXPECTED_BINDINGS)} routes, got {len(routes)}"
        )

    routes_by_id: dict[str, dict[str, Any]] = {}
    routes_by_path: dict[str, str] = {}
    post_ids: dict[int, str] = {}
    for route in routes:
        route_id = route["route_id"]
        path = unicodedata.normalize("NFC", route["path"])
        if route_id in routes_by_id:
            raise RegistryError(f"duplicate route ID: {route_id}")
        if path in routes_by_path:
            raise RegistryError(
                f"duplicate route path: {path} owned by {routes_by_path[path]} and {route_id}"
            )
        routes_by_id[route_id] = route
        routes_by_path[path] = route_id

        post_id = route["wordpress"]["post_id"]
        if post_id is not None:
            if post_id in post_ids:
                raise RegistryError(
                    f"duplicate WordPress post ID: {post_id} for {post_ids[post_id]} and {route_id}"
                )
            post_ids[post_id] = route_id

    if set(routes_by_id) != set(EXPECTED_BINDINGS):
        missing = sorted(set(EXPECTED_BINDINGS) - set(routes_by_id))
        extra = sorted(set(routes_by_id) - set(EXPECTED_BINDINGS))
        raise RegistryError(f"route set mismatch: missing={missing}, extra={extra}")

    for route_id, (expected_post_id, expected_path) in EXPECTED_BINDINGS.items():
        route = routes_by_id[route_id]
        actual = (route["wordpress"]["post_id"], route["path"])
        expected = (expected_post_id, expected_path)
        if actual != expected:
            raise RegistryError(
                f"ID/path mismatch for {route_id}: expected {expected!r}, got {actual!r}"
            )

    hub_route_id = source["hub_route_id"]
    if hub_route_id != EXPECTED_HUB_ROUTE_ID or hub_route_id not in routes_by_id:
        raise RegistryError("hub route mismatch")
    hub = routes_by_id[hub_route_id]
    if hub["kind"] != "hub":
        raise RegistryError("hub route must have kind hub")
    if hub["parent_route_id"] is not None or hub["parent_link"] is not None:
        raise RegistryError("hub route cannot have a parent")
    if hub["wordpress"] != {
        "post_id": 841,
        "post_type": "page",
        "identity_policy": "id_and_path_exact",
        "body_mode": "preserve",
    }:
        raise RegistryError("hub WordPress identity contract mismatch")

    term_owners: dict[str, str] = {}
    meta_owners: dict[str, str] = {}
    spoke_ids = set(routes_by_id) - {hub_route_id}
    for route_id, route in routes_by_id.items():
        wordpress = route["wordpress"]
        if wordpress["body_mode"] != "preserve":
            raise RegistryError(f"stored body is not preserved for {route_id}")

        if route_id != hub_route_id:
            if route["kind"] != "spoke":
                raise RegistryError(f"non-hub route must be a spoke: {route_id}")
            if route["parent_route_id"] != hub_route_id:
                raise RegistryError(f"missing hub parent for {route_id}")
            if wordpress["post_type"] != "post":
                raise RegistryError(f"spoke post type mismatch for {route_id}")
            if wordpress["identity_policy"] != "id_and_path_exact":
                raise RegistryError(f"spoke identity policy mismatch for {route_id}")
            parent_link = route["parent_link"]
            if (
                not isinstance(parent_link, dict)
                or parent_link["target_route_id"] != hub_route_id
            ):
                raise RegistryError(f"missing parent link for {route_id}")

        ownership = route["ownership"]
        route_terms = [ownership["primary_keyword"], *ownership["synonyms"]]
        local_terms: set[str] = set()
        for term in route_terms:
            normalized = normalize_term(term)
            if not normalized:
                raise RegistryError(f"empty normalized ownership term for {route_id}")
            if normalized in local_terms:
                raise RegistryError(f"duplicate ownership term inside {route_id}: {term}")
            local_terms.add(normalized)
            previous = term_owners.get(normalized)
            if previous is not None:
                raise RegistryError(
                    f"duplicate keyword/synonym ownership: {term!r} belongs to {previous} and {route_id}"
                )
            term_owners[normalized] = route_id

        primary = normalize_term(ownership["primary_keyword"])
        for field in ("h1", "seo_title"):
            title = normalize_term(route["public"][field])
            if not title.startswith(primary):
                raise RegistryError(
                    f"{field} is not keyword-led for {route_id}: {route['public'][field]!r}"
                )

        meta = normalize_term(route["public"]["meta_description"])
        previous_meta = meta_owners.get(meta)
        if previous_meta is not None:
            raise RegistryError(
                f"duplicate meta description for {previous_meta} and {route_id}"
            )
        meta_owners[meta] = route_id

        freshness_id = route["freshness_id"]
        if freshness_id not in freshness_ids:
            raise RegistryError(
                f"invalid freshness target {freshness_id} on {route_id}"
            )
        for source_id in route["source_ids"]:
            if source_id not in source_ids:
                raise RegistryError(
                    f"invalid source target {source_id} on {route_id}"
                )

        breadcrumbs = route["breadcrumbs"]
        expected_length = 2 if route_id == hub_route_id else 3
        if len(breadcrumbs) != expected_length:
            raise RegistryError(f"breadcrumb length mismatch for {route_id}")
        if breadcrumbs[0] != {"label": "ראשי", "path": "/", "route_id": None}:
            raise RegistryError(f"home breadcrumb mismatch for {route_id}")
        if breadcrumbs[-1]["route_id"] != route_id or breadcrumbs[-1]["path"] != route["path"]:
            raise RegistryError(f"current breadcrumb mismatch for {route_id}")
        if route_id != hub_route_id:
            if (
                breadcrumbs[1]["route_id"] != hub_route_id
                or breadcrumbs[1]["path"] != hub["path"]
            ):
                raise RegistryError(f"hub breadcrumb mismatch for {route_id}")
        for crumb in breadcrumbs:
            target = crumb["route_id"]
            if target is None:
                if crumb["path"] != "/":
                    raise RegistryError(f"invalid home breadcrumb on {route_id}")
                continue
            if target not in routes_by_id:
                raise RegistryError(
                    f"invalid breadcrumb target {target} on {route_id}"
                )
            if crumb["path"] != routes_by_id[target]["path"]:
                raise RegistryError(
                    f"breadcrumb path mismatch for target {target} on {route_id}"
                )

        continuation_targets: set[str] = set()
        for link in route["continuations"]:
            target = link["target_route_id"]
            if target == route_id:
                raise RegistryError(f"self continuation on {route_id}")
            if target not in routes_by_id:
                raise RegistryError(f"invalid link target {target} on {route_id}")
            if target in continuation_targets:
                raise RegistryError(f"duplicate continuation {target} on {route_id}")
            continuation_targets.add(target)
        if route_id != hub_route_id and len(continuation_targets) < 2:
            raise RegistryError(f"missing continuations for {route_id}")

    hub_targets = {
        link["target_route_id"] for link in hub["continuations"]
    }
    if hub_targets != spoke_ids:
        raise RegistryError(
            f"hub continuations must contain every spoke exactly once: {sorted(hub_targets)}"
        )

    continuation_map = {
        route_id: {
            link["target_route_id"] for link in route["continuations"]
        }
        for route_id, route in routes_by_id.items()
    }
    for source_id, targets in continuation_map.items():
        for target_id in targets:
            if source_id == hub_route_id:
                if routes_by_id[target_id]["parent_route_id"] != hub_route_id:
                    raise RegistryError(
                        f"hub continuation is not reciprocated by parent: {target_id}"
                    )
                continue
            if target_id == hub_route_id:
                raise RegistryError(
                    f"spoke {source_id} must use parent_link for the hub"
                )
            if source_id not in continuation_map[target_id]:
                raise RegistryError(
                    f"contextual link is not reciprocal: {source_id} -> {target_id}"
                )

    return routes_by_id


def validate_hub_experience(
    source: dict[str, Any], routes_by_id: dict[str, dict[str, Any]]
) -> None:
    """Validate every hub section, card, and decision destination."""
    experience = source["hub_experience"]
    hub_route_id = source["hub_route_id"]
    spoke_ids = set(routes_by_id) - {hub_route_id}

    require_unique_ids(experience["sections"], "section_id", "hub section ID")
    section_targets: list[str] = []
    for section in experience["sections"]:
        for target in section["route_ids"]:
            if target not in spoke_ids:
                raise RegistryError(
                    f"invalid hub section target {target} in {section['section_id']}"
                )
            section_targets.append(target)
    if len(section_targets) != len(set(section_targets)):
        raise RegistryError("a spoke appears in more than one hub section")
    if set(section_targets) != spoke_ids:
        raise RegistryError("hub sections must cover every spoke exactly once")

    card_targets = [card["route_id"] for card in experience["cards"]]
    if len(card_targets) != len(set(card_targets)):
        raise RegistryError("duplicate hub card route")
    if set(card_targets) != spoke_ids:
        raise RegistryError("hub cards must cover every spoke exactly once")

    require_unique_ids(
        experience["decision_paths"], "decision_id", "decision path ID"
    )
    decision_targets: set[str] = set()
    for decision in experience["decision_paths"]:
        local_targets: set[str] = set()
        for choice in decision["choices"]:
            target = choice["target_route_id"]
            if target not in spoke_ids:
                raise RegistryError(
                    f"invalid decision target {target} in {decision['decision_id']}"
                )
            if target in local_targets:
                raise RegistryError(
                    f"duplicate decision target {target} in {decision['decision_id']}"
                )
            local_targets.add(target)
            decision_targets.add(target)
    if decision_targets != spoke_ids:
        raise RegistryError("decision paths must offer every spoke")


def validate_source(
    source: dict[str, Any], schema: dict[str, Any], schema_path: Path
) -> dict[str, dict[str, Any]]:
    """Run schema and semantic validation and return exact routes by ID."""
    validate_schema_contract(schema, schema_path)
    errors = SchemaValidator(schema).validate(source)
    if errors:
        raise RegistryError("schema validation failed: " + "; ".join(errors))
    if source["contract_id"] != EXPECTED_CONTRACT_ID:
        raise RegistryError("content contract ID mismatch")
    if source["body_contract"] != {
        "mode": "preserve_wordpress_body",
        "mutation_allowed": False,
        "source_field": "post_content",
        "wordpress_filter": "the_content",
        "public_punctuation_policy": "replace_long_dashes_with_hyphen",
        "prefix_components": ["breadcrumb", "route_intro"],
        "suffix_components": ["continuations", "freshness", "sources"],
    }:
        raise RegistryError("stored body preservation contract drift")
    validate_forbidden_characters(source)
    validate_public_language(source)
    freshness_ids, source_ids = validate_catalogs(source)
    routes_by_id = validate_routes(source, freshness_ids, source_ids)
    validate_hub_experience(source, routes_by_id)
    return routes_by_id


def build_runtime_registry(
    source: dict[str, Any],
    routes_by_id: dict[str, dict[str, Any]],
    source_digest: str,
    schema_digest: str,
) -> dict[str, Any]:
    """Build indexes without changing authored editorial ordering."""
    ordered_routes = {
        route_id: routes_by_id[route_id] for route_id in sorted(routes_by_id)
    }
    route_id_by_path = {
        route["path"]: route_id
        for route_id, route in sorted(
            routes_by_id.items(), key=lambda item: item[1]["path"]
        )
    }
    route_id_by_post_id = {
        str(route["wordpress"]["post_id"]): route_id
        for route_id, route in sorted(
            routes_by_id.items(),
            key=lambda item: (
                item[1]["wordpress"]["post_id"] is None,
                item[1]["wordpress"]["post_id"] or 0,
            ),
        )
        if route["wordpress"]["post_id"] is not None
    }
    hub_route_id = source["hub_route_id"]
    children_by_parent = {
        hub_route_id: sorted(
            route_id
            for route_id, route in routes_by_id.items()
            if route["parent_route_id"] == hub_route_id
        )
    }
    freshness_by_id = {
        item["freshness_id"]: item
        for item in sorted(
            source["freshness_catalog"], key=lambda item: item["freshness_id"]
        )
    }
    sources_by_id = {
        item["source_id"]: item
        for item in sorted(
            source["source_catalog"], key=lambda item: item["source_id"]
        )
    }

    registry: dict[str, Any] = {
        "schema_version": source["schema_version"],
        "contract_id": source["contract_id"],
        "source_sha256": source_digest,
        "schema_sha256": schema_digest,
        "site": source["site"],
        "hub_route_id": hub_route_id,
        "body_contract": source["body_contract"],
        "rendering_owners": source["rendering_owners"],
        "public_labels": source["public_labels"],
        "freshness_by_id": freshness_by_id,
        "sources_by_id": sources_by_id,
        "routes_by_id": ordered_routes,
        "route_id_by_path": route_id_by_path,
        "route_id_by_post_id": route_id_by_post_id,
        "children_by_parent": children_by_parent,
        "hub_experience": source["hub_experience"],
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
 * Generated real-estate content registry.
 *
 * Run scripts/build_content_registry.py to rebuild this file.
 */

if ( ! defined( 'ABSPATH' ) ) {{
\texit;
}}

return json_decode(
\t<<<'THAILAND_PLATFORM_CONTENT_JSON'
{payload}
THAILAND_PLATFORM_CONTENT_JSON,
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
    """Compile one source file after all fail-closed checks pass."""
    source_path = Path(source_path)
    schema_path = Path(schema_path)
    source = load_json(source_path)
    schema = load_json(schema_path)
    routes_by_id = validate_source(source, schema, schema_path)
    registry = build_runtime_registry(
        source,
        routes_by_id,
        sha256_lf(source_path),
        sha256_lf(schema_path),
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
                raise RegistryError(
                    f"compiled registry is stale: {arguments.output}"
                )
            print(f"PASS: content registry is current: {arguments.output}")
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
