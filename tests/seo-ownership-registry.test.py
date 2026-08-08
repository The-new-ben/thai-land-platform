"""Contract tests for the machine-readable SEO ownership registry."""

from __future__ import annotations

import copy
import csv
import json
import math
import re
import unittest
from pathlib import Path
from typing import Any
from urllib.parse import urlsplit


ROOT = Path(__file__).resolve().parents[1]
REGISTRY_PATH = ROOT / "data" / "seo" / "ownership-registry.json"
SCHEMA_PATH = ROOT / "data" / "seo" / "ownership-registry.schema.json"
README_PATH = ROOT / "data" / "seo" / "README.md"


def reject_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    """Reject duplicate JSON keys instead of silently accepting the last value."""
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f"duplicate JSON key: {key}")
        result[key] = value
    return result


def reject_non_finite(value: str) -> None:
    """Reject NaN and infinity, which are not valid JSON data values."""
    raise ValueError(f"non-finite JSON number: {value}")


def load_json(path: Path) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as handle:
        value = json.load(
            handle,
            object_pairs_hook=reject_duplicate_keys,
            parse_constant=reject_non_finite,
        )
    if not isinstance(value, dict):
        raise ValueError(f"JSON root must be an object: {path}")
    return value


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
        return (
            isinstance(value, (int, float))
            and not isinstance(value, bool)
            and math.isfinite(value)
        )
    raise AssertionError(f"unsupported schema type in test validator: {expected}")


class SchemaValidator:
    """Dependency-free validator for every JSON Schema keyword used here."""

    def __init__(self, schema: dict[str, Any]) -> None:
        self.schema = schema

    def resolve_ref(self, reference: str) -> dict[str, Any]:
        if not reference.startswith("#/"):
            raise AssertionError(f"unsupported schema reference: {reference}")
        current: Any = self.schema
        for raw_part in reference[2:].split("/"):
            part = raw_part.replace("~1", "/").replace("~0", "~")
            current = current[part]
        if not isinstance(current, dict):
            raise AssertionError(f"schema reference is not an object: {reference}")
        return current

    def validate(
        self,
        value: Any,
        schema: dict[str, Any] | None = None,
        path: str = "$",
    ) -> list[str]:
        current = self.schema if schema is None else schema
        if "$ref" in current:
            return self.validate(value, self.resolve_ref(current["$ref"]), path)

        errors: list[str] = []
        expected_type = current.get("type")
        if expected_type is not None:
            accepted_types = (
                expected_type if isinstance(expected_type, list) else [expected_type]
            )
            if not any(json_type_matches(value, item) for item in accepted_types):
                errors.append(
                    f"{path}: expected type {accepted_types}, got {type(value).__name__}"
                )
                return errors

        if "const" in current and value != current["const"]:
            errors.append(f"{path}: value does not match const")
        if "enum" in current and value not in current["enum"]:
            errors.append(f"{path}: value is not in enum")

        if isinstance(value, str):
            if len(value) < current.get("minLength", 0):
                errors.append(f"{path}: string is shorter than minLength")
            pattern = current.get("pattern")
            if pattern is not None and re.search(pattern, value) is None:
                errors.append(f"{path}: string does not match pattern {pattern}")

        if isinstance(value, list):
            if len(value) < current.get("minItems", 0):
                errors.append(f"{path}: array is shorter than minItems")
            if "maxItems" in current and len(value) > current["maxItems"]:
                errors.append(f"{path}: array is longer than maxItems")
            if current.get("uniqueItems"):
                serialized = [
                    json.dumps(item, ensure_ascii=False, sort_keys=True)
                    for item in value
                ]
                if len(serialized) != len(set(serialized)):
                    errors.append(f"{path}: array items are not unique")
            item_schema = current.get("items")
            if isinstance(item_schema, dict):
                for index, item in enumerate(value):
                    errors.extend(
                        self.validate(item, item_schema, f"{path}[{index}]")
                    )

        if isinstance(value, dict):
            required = current.get("required", [])
            for key in required:
                if key not in value:
                    errors.append(f"{path}: missing required property {key}")
            properties = current.get("properties", {})
            for key, item in value.items():
                if key in properties:
                    errors.extend(
                        self.validate(item, properties[key], f"{path}.{key}")
                    )
                elif current.get("additionalProperties") is False:
                    errors.append(f"{path}: unexpected property {key}")

        return errors


def normalize_public_route(raw_value: str) -> str | None:
    """Return an indexable route candidate or None for a fragment or asset."""
    value = raw_value.strip()
    if not value.startswith("/") or value.startswith("//"):
        return None
    if value.startswith("/#") or value.startswith("#"):
        return None
    value = value.split("#", 1)[0]
    if re.search(r"\.(?:css|js|png|jpe?g|webp|gif|svg|woff2?)(?:\?|$)", value, re.I):
        return None
    return value


def discover_live_routes() -> set[str]:
    """Rebuild the first-party route inventory from current source and tests."""
    routes: set[str] = set()

    for relative in ("resources/homepage.html", "prototype/index.html"):
        text = (ROOT / relative).read_text(encoding="utf-8")
        for match in re.finditer(r'\b(?:href|action)="([^"]+)"', text):
            route = normalize_public_route(match.group(1))
            if route is not None:
                routes.add(route)
        if re.search(r'<form\b[^>]*\baction="/"[^>]*>', text) and re.search(
            r'<input\b[^>]*\bname="s"', text
        ):
            routes.add("/?s={query}")

    for relative in ("assets/homepage/homepage.js", "prototype/app.js"):
        text = (ROOT / relative).read_text(encoding="utf-8")
        for match in re.finditer(r"\bhref:\s*['\"]([^'\"]+)['\"]", text):
            route = normalize_public_route(match.group(1))
            if route is not None:
                routes.add(route)

    release = load_json(ROOT / "release.json")
    homepage = urlsplit(release["homepage"])
    if homepage.netloc == "thai-land.co.il":
        routes.add(homepage.path or "/")

    readme = (ROOT / "README.md").read_text(encoding="utf-8")
    routes.update(re.findall(r"`(/wp-json/[^`]+)`", readme))

    for relative in ("src/Geography/Route.php", "src/Health/Route.php"):
        route_source = (ROOT / relative).read_text(encoding="utf-8")
        namespace_match = re.search(
            r"REST_NAMESPACE\s*=\s*'([^']+)'", route_source
        )
        route_match = re.search(r"REST_ROUTE\s*=\s*'([^']+)'", route_source)
        if namespace_match and route_match:
            routes.add(
                "/wp-json/"
                + namespace_match.group(1).strip("/")
                + "/"
                + route_match.group(1).strip("/")
            )

    test_source = (ROOT / "tests" / "run.php").read_text(encoding="utf-8")
    for match in re.finditer(
        r"\$route_key\s*=\s*'([^']+/health)'", test_source
    ):
        routes.add("/wp-json/" + match.group(1).strip("/"))

    return routes


def collect_geo_ids(value: Any) -> set[str]:
    """Collect canonical geography IDs from a compiled JSON value."""
    found: set[str] = set()
    if isinstance(value, dict):
        for item in value.values():
            found.update(collect_geo_ids(item))
    elif isinstance(value, list):
        for item in value:
            found.update(collect_geo_ids(item))
    elif isinstance(value, str) and value.startswith("geo:th:"):
        found.add(value)
    return found


def source_geography_ids() -> set[str]:
    """Derive every canonical public geography ID from reviewed source."""
    regions = load_json(ROOT / "data" / "geography" / "regions.json")
    if regions.get("country", {}).get("id") != "TH":
        raise AssertionError("geography country source must be TH")

    entity_ids = {"geo:th:country"}
    region_model = regions.get("region_model", {})
    region_model_id = region_model.get("id")
    region_rows = region_model.get("regions")
    if not isinstance(region_model_id, str) or not isinstance(region_rows, list):
        raise AssertionError("geography region source is incomplete")
    if len(region_rows) != 7:
        raise AssertionError(f"expected 7 statistical regions, got {len(region_rows)}")
    for region in region_rows:
        region_id = region.get("id") if isinstance(region, dict) else None
        if not isinstance(region_id, str) or not region_id:
            raise AssertionError("geography region source has an invalid ID")
        entity_ids.add(f"geo:th:region:{region_model_id}:{region_id}")

    with (ROOT / "data" / "geography" / "provinces.csv").open(
        "r", encoding="utf-8-sig", newline=""
    ) as handle:
        rows = list(csv.DictReader(handle))
    if len(rows) != 77:
        raise AssertionError(f"expected 77 province rows, got {len(rows)}")
    for row in rows:
        code = row["code"]
        if re.fullmatch(r"[0-9]{2}", code) is None:
            raise AssertionError(f"invalid province code: {code}")
        entity_ids.add(f"geo:th:province:{code}")
    return entity_ids


def authoritative_geography_ids() -> set[str]:
    """Use compiled geography when present and prove it matches reviewed source."""
    source_ids = source_geography_ids()
    compiled_path = ROOT / "assets" / "geography" / "core.json"
    if not compiled_path.is_file():
        return source_ids
    compiled_ids = collect_geo_ids(load_json(compiled_path))
    if compiled_ids != source_ids:
        missing = sorted(source_ids - compiled_ids)
        unexpected = sorted(compiled_ids - source_ids)
        raise AssertionError(
            "compiled geography ID parity failed: "
            f"missing={missing}, unexpected={unexpected}"
        )
    return compiled_ids


class SeoOwnershipRegistryTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.registry = load_json(REGISTRY_PATH)
        cls.schema = load_json(SCHEMA_PATH)
        cls.owners = cls.registry["owners"]
        cls.by_url = {owner["url"]: owner for owner in cls.owners}

    def test_registry_validates_against_declared_schema(self) -> None:
        errors = SchemaValidator(self.schema).validate(self.registry)
        self.assertEqual([], errors, "\n".join(errors))

    def test_schema_rejects_missing_required_field(self) -> None:
        broken = copy.deepcopy(self.registry)
        del broken["owners"][0]["primary_intent"]
        errors = SchemaValidator(self.schema).validate(broken)
        self.assertTrue(
            any("missing required property primary_intent" in item for item in errors),
            errors,
        )

    def test_schema_rejects_unexpected_field(self) -> None:
        broken = copy.deepcopy(self.registry)
        broken["owners"][0]["unreviewed_field"] = True
        errors = SchemaValidator(self.schema).validate(broken)
        self.assertTrue(
            any("unexpected property unreviewed_field" in item for item in errors),
            errors,
        )

    def test_all_discovered_live_routes_have_exactly_one_record(self) -> None:
        discovered = discover_live_routes()
        registered_live = {
            owner["url"] for owner in self.owners if owner["lifecycle"] == "live"
        }
        self.assertEqual(discovered, registered_live)
        self.assertEqual(14, len(registered_live))

    def test_next_national_owners_are_reserved(self) -> None:
        required = {
            "/בריאות-בתאילנד/",
            "/ויזות-לתאילנד/",
            "/חוקים-ומסים-בתאילנד/",
            "/חנות-לישראלים-בתאילנד/",
            "/חיים-בתאילנד/",
            "/ישראלים-בתאילנד/",
            "/יעדים-בתאילנד/",
            "/מפת-תאילנד/",
            "/נדלן-בתאילנד/",
            "/עסקים-בתאילנד/",
            "/פרויקטים-נדלן-בתאילנד/",
            "/שירותים-בתאילנד/",
            "/תחבורה-בתאילנד/",
        }
        planned = {
            owner["url"]
            for owner in self.owners
            if owner["lifecycle"] == "planned"
        }
        self.assertEqual(required, planned)

    def test_owner_ids_urls_and_primary_intents_are_unique(self) -> None:
        for field in ("owner_id", "url", "primary_intent"):
            values = [owner[field] for owner in self.owners]
            self.assertEqual(
                len(values),
                len(set(values)),
                f"duplicate SEO ownership field: {field}",
            )

    def test_canonical_parent_and_breadcrumb_references_are_valid(self) -> None:
        registered = set(self.by_url)
        for owner in self.owners:
            url = owner["url"]
            self.assertIn(owner["canonical_owner"], registered, url)
            if owner["parent_hub"] is None:
                self.assertEqual("/", url)
                self.assertEqual(1, len(owner["breadcrumb_chain"]))
            else:
                self.assertIn(owner["parent_hub"], registered, url)
                self.assertGreaterEqual(len(owner["breadcrumb_chain"]), 2, url)
                self.assertEqual(
                    owner["parent_hub"], owner["breadcrumb_chain"][-2]["url"], url
                )

            chain_urls = [crumb["url"] for crumb in owner["breadcrumb_chain"]]
            self.assertEqual("/", chain_urls[0], url)
            self.assertEqual(url, chain_urls[-1], url)
            self.assertEqual(len(chain_urls), len(set(chain_urls)), url)
            for index, chain_url in enumerate(chain_urls):
                self.assertIn(chain_url, registered, url)
                if index:
                    self.assertEqual(
                        chain_urls[index - 1],
                        self.by_url[chain_url]["parent_hub"],
                        url,
                    )

    def test_hierarchy_has_no_parent_cycles(self) -> None:
        for owner in self.owners:
            seen: set[str] = set()
            current: str | None = owner["url"]
            while current is not None:
                self.assertNotIn(current, seen, owner["url"])
                seen.add(current)
                current = self.by_url[current]["parent_hub"]

    def test_cannibalization_exclusions_delegate_to_other_owners(self) -> None:
        registered = set(self.by_url)
        for owner in self.owners:
            intents: set[str] = set()
            for exclusion in owner["cannibalization_exclusions"]:
                self.assertNotIn(exclusion["intent"], intents, owner["url"])
                intents.add(exclusion["intent"])
                self.assertIn(exclusion["owner_url"], registered, owner["url"])
                self.assertNotEqual(
                    owner["url"], exclusion["owner_url"], owner["url"]
                )

    def test_route_kind_and_canonical_policy_are_explicit(self) -> None:
        patterns = [owner for owner in self.owners if owner["route_kind"] == "pattern"]
        self.assertEqual(["/?s={query}"], [owner["url"] for owner in patterns])
        for owner in self.owners:
            if owner["route_kind"] == "exact":
                self.assertNotIn("{", owner["url"])
                self.assertNotIn("?", owner["url"])
            if owner["url"] == "/?s={query}":
                self.assertEqual("/", owner["canonical_owner"])
                self.assertEqual("noindex", owner["indexing_policy"])
            else:
                self.assertEqual(owner["url"], owner["canonical_owner"])

    def test_subject_entities_are_valid_geography_foreign_keys(self) -> None:
        allowed = authoritative_geography_ids()
        for owner in self.owners:
            for entity_id in owner["subject_entity_ids"]:
                self.assertIn(entity_id, allowed, owner["url"])

        self.assertEqual(
            ["geo:th:province:10"],
            self.by_url["/בנגקוק-תאילנד/"]["subject_entity_ids"],
        )
        self.assertEqual(
            ["geo:th:province:10"],
            self.by_url[
                "/טיול-בבנגקוק-ליומיים-3-ימים-או-4-ימים-מדר/"
            ]["subject_entity_ids"],
        )
        self.assertEqual(
            ["geo:th:province:83", "geo:th:province:84"],
            self.by_url["/פוקט-או-קו-סמוי/"]["subject_entity_ids"],
        )
        self.assertEqual(
            ["geo:th:country"],
            self.by_url["/wp-json/thailand-platform/v1/geography"]["subject_entity_ids"],
        )
        for url in (
            "/?s={query}",
            "/אודות/",
            "/sitemap_index.xml",
            "/wp-json/thailand-platform/v1/health",
        ):
            self.assertEqual([], self.by_url[url]["subject_entity_ids"], url)

    def test_source_inventory_is_sorted_and_exists(self) -> None:
        source_files = self.registry["discovery"]["source_files"]
        self.assertEqual(sorted(source_files), source_files)
        for relative in source_files:
            self.assertTrue((ROOT / relative).is_file(), relative)
        for owner in self.owners:
            if owner["lifecycle"] != "live":
                continue
            for evidence in owner["source_evidence"]:
                relative = evidence.split(":", 1)[0]
                self.assertTrue((ROOT / relative).is_file(), evidence)

    def test_human_fields_are_hebrew_first(self) -> None:
        hebrew = re.compile(r"[\u0590-\u05ff]")
        for owner in self.owners:
            for field in ("name", "primary_intent", "audience"):
                self.assertRegex(owner[field], hebrew, f"{owner['url']} {field}")
            for exclusion in owner["cannibalization_exclusions"]:
                self.assertRegex(exclusion["intent"], hebrew, owner["url"])

    def test_forbidden_dash_characters_are_absent(self) -> None:
        forbidden_characters = (chr(0x2013), chr(0x2014))
        forbidden_encodings = (
            "\\" + "u" + "2013",
            "\\" + "u" + "2014",
            "&#" + "8211;",
            "&#" + "8212;",
        )
        for path in (REGISTRY_PATH, SCHEMA_PATH, README_PATH, Path(__file__)):
            text = path.read_text(encoding="utf-8")
            for character in forbidden_characters:
                self.assertNotIn(character, text, str(path))
            lowered = text.lower()
            for encoding in forbidden_encodings:
                self.assertNotIn(encoding, lowered, str(path))


if __name__ == "__main__":
    unittest.main(verbosity=2)
