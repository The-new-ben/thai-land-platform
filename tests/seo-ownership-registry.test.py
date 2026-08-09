"""Contract tests for the machine-readable SEO ownership registry."""

from __future__ import annotations

import copy
import csv
import hashlib
import json
import math
import re
import subprocess
import sys
import unicodedata
import unittest
from pathlib import Path
from typing import Any
from urllib.parse import unquote, urlsplit


ROOT = Path(__file__).resolve().parents[1]
REGISTRY_PATH = ROOT / "data" / "seo" / "ownership-registry.json"
SCHEMA_PATH = ROOT / "data" / "seo" / "ownership-registry.schema.json"
README_PATH = ROOT / "data" / "seo" / "README.md"
BUILDER_PATH = ROOT / "scripts" / "build_seo_registry.py"
MANAGED_LIVE_EVIDENCE_PATH = (
    ROOT / "data" / "seo" / "evidence" / "managed-live-routes.0.3.5.json"
)
CONTENT_PATH = ROOT / "data" / "content" / "real-estate.json"

SNAPSHOT_BASELINES = {
    "yoast-sitemaps-2026-08-08": {
        "path": "data/seo/inventory/current-public-url-metadata.2026-08-08.csv",
        "digest": "6e34e459d0772ecc227d848bc1dfe42260c2df6dcaefe65457bb6dcb8698816c",
        "rows": 40,
    },
    "indexable-category-surfaces-2026-08-08": {
        "path": "data/seo/inventory/indexable-category-surfaces.2026-08-08.csv",
        "digest": "7844b78efc75533803496799099176cd7b8a31f57c915d5746d3ade8ed37cc65",
        "rows": 3,
    },
}


def reject_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f"duplicate JSON key: {key}")
        result[key] = value
    return result


def reject_non_finite(value: str) -> None:
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
        if "oneOf" in current:
            branch_results = [
                self.validate(value, branch, path) for branch in current["oneOf"]
            ]
            matches = sum(1 for result in branch_results if not result)
            if matches != 1:
                errors.append(f"{path}: expected exactly one oneOf branch, got {matches}")
            return errors

        expected_type = current.get("type")
        if expected_type is not None:
            accepted_types = (
                expected_type if isinstance(expected_type, list) else [expected_type]
            )
            if not any(json_type_matches(value, item) for item in accepted_types):
                return [
                    f"{path}: expected type {accepted_types}, got {type(value).__name__}"
                ]

        if "const" in current and value != current["const"]:
            errors.append(f"{path}: expected constant {current['const']!r}")
        if "enum" in current and value not in current["enum"]:
            errors.append(f"{path}: value is outside enum")

        if isinstance(value, str):
            if len(value) < current.get("minLength", 0):
                errors.append(f"{path}: string shorter than minLength")
            pattern = current.get("pattern")
            if pattern is not None and re.search(pattern, value) is None:
                errors.append(f"{path}: string does not match {pattern}")

        if isinstance(value, (int, float)) and not isinstance(value, bool):
            minimum = current.get("minimum")
            if minimum is not None and value < minimum:
                errors.append(f"{path}: number is below minimum {minimum}")

        if isinstance(value, list):
            if len(value) < current.get("minItems", 0):
                errors.append(f"{path}: array shorter than minItems")
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
            properties = current.get("properties", {})
            for required in current.get("required", []):
                if required not in value:
                    errors.append(f"{path}: missing required property {required}")
            for key, item in value.items():
                if key in properties:
                    errors.extend(
                        self.validate(item, properties[key], f"{path}.{key}")
                    )
                elif current.get("additionalProperties") is False:
                    errors.append(f"{path}: unexpected property {key}")

        return errors


def normalize_route(value: str) -> str:
    """Normalize an absolute or site-relative URL into one route key."""
    value = unicodedata.normalize("NFC", value.strip())
    split = urlsplit(value)
    if split.scheme or split.netloc:
        if split.scheme != "https" or split.netloc not in {
            "thai-land.co.il",
            "www.thai-land.co.il",
        }:
            raise AssertionError(f"unexpected inventory origin: {value}")
        path = unquote(split.path)
        query = split.query
    else:
        path, _, query = value.partition("?")
        path = unquote(path)
    path = unicodedata.normalize("NFC", path or "/")
    if not path.startswith("/"):
        raise AssertionError(f"route is not site-relative: {value}")
    if path != "/" and not path.endswith("/") and "." not in path.rsplit("/", 1)[-1]:
        path += "/"
    return path + (f"?{query}" if query else "")


def sha256_lf(path: Path) -> str:
    payload = path.read_bytes().replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    return hashlib.sha256(payload).hexdigest()


def normalized_term(value: str) -> str:
    return " ".join(unicodedata.normalize("NFKC", value).casefold().split())


def collect_geo_ids(value: Any) -> set[str]:
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


def authoritative_geography_ids() -> set[str]:
    regions = load_json(ROOT / "data" / "geography" / "regions.json")
    region_model = regions["region_model"]
    result = {"geo:th:country"}
    for region in region_model["regions"]:
        result.add(
            f"geo:th:region:{region_model['id']}:{region['id']}"
        )
    with (ROOT / "data" / "geography" / "provinces.csv").open(
        "r", encoding="utf-8-sig", newline=""
    ) as handle:
        provinces = list(csv.DictReader(handle))
    if len(provinces) != 77:
        raise AssertionError(f"expected 77 provinces, got {len(provinces)}")
    result.update(f"geo:th:province:{row['code']}" for row in provinces)

    compiled = collect_geo_ids(
        load_json(ROOT / "assets" / "geography" / "core.json")
    )
    if compiled != result:
        raise AssertionError("compiled geography differs from reviewed source")
    return result


class SeoOwnershipRegistryTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.registry = load_json(REGISTRY_PATH)
        cls.schema = load_json(SCHEMA_PATH)
        cls.validator = SchemaValidator(cls.schema)
        cls.owners = cls.registry["intent_owners"]
        cls.routes = cls.registry["routes"]
        cls.snapshots = cls.registry["inventory_snapshots"]
        cls.by_owner = {owner["owner_id"]: owner for owner in cls.owners}
        cls.by_route = {route["url"]: route for route in cls.routes}
        cls.by_snapshot = {
            snapshot["snapshot_id"]: snapshot for snapshot in cls.snapshots
        }

    def inventory_rows(self) -> dict[str, list[dict[str, str]]]:
        result: dict[str, list[dict[str, str]]] = {}
        for snapshot_id, baseline in SNAPSHOT_BASELINES.items():
            path = ROOT / baseline["path"]
            with path.open("r", encoding="utf-8-sig", newline="") as handle:
                result[snapshot_id] = list(csv.DictReader(handle))
        return result

    def test_registry_validates_against_declared_schema(self) -> None:
        errors = self.validator.validate(self.registry)
        self.assertEqual([], errors, "\n".join(errors))

    def test_schema_rejects_missing_required_owner_field(self) -> None:
        broken = copy.deepcopy(self.registry)
        del broken["intent_owners"][0]["primary_keyword"]
        errors = self.validator.validate(broken)
        self.assertTrue(
            any("missing required property primary_keyword" in item for item in errors),
            errors,
        )

    def test_schema_rejects_invalid_assignment_branch(self) -> None:
        broken = copy.deepcopy(self.registry)
        broken["routes"][0]["assignment"] = {
            "kind": "canonical_owner",
            "owner_id": "home",
            "state": "evidence_pending",
            "release_blocked": True,
            "candidate_owner_id": "home",
            "required_evidence": ["ראיה"],
        }
        errors = self.validator.validate(broken)
        self.assertTrue(any("oneOf" in item for item in errors), errors)

    def test_schema_enforces_numeric_minimum(self) -> None:
        broken = copy.deepcopy(self.registry)
        broken["inventory_snapshots"][0]["row_count"] = 0
        errors = self.validator.validate(broken)
        self.assertTrue(any("below minimum" in item for item in errors), errors)

    def test_builder_output_is_current(self) -> None:
        completed = subprocess.run(
            [sys.executable, str(BUILDER_PATH), "--check"],
            cwd=ROOT,
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(0, completed.returncode, completed.stdout + completed.stderr)
        self.assertIn("PASS: SEO ownership registry is current", completed.stdout)

    def test_protected_inventory_snapshots_are_present_and_immutable(self) -> None:
        self.assertEqual(set(SNAPSHOT_BASELINES), set(self.by_snapshot))
        all_routes: set[str] = set()
        total = 0
        for snapshot_id, baseline in SNAPSHOT_BASELINES.items():
            snapshot = self.by_snapshot[snapshot_id]
            self.assertEqual(baseline["path"], snapshot["path"])
            self.assertEqual(baseline["digest"], snapshot["content_sha256"])
            self.assertEqual(baseline["rows"], snapshot["row_count"])
            self.assertEqual(baseline["rows"], snapshot["protected_url_count"])

            path = (ROOT / snapshot["path"]).resolve()
            self.assertTrue(path.is_relative_to(ROOT.resolve()), path)
            self.assertTrue(path.is_file(), path)
            self.assertEqual(baseline["digest"], sha256_lf(path))

            with path.open("r", encoding="utf-8-sig", newline="") as handle:
                rows = list(csv.DictReader(handle))
            self.assertEqual(baseline["rows"], len(rows))
            required_headers = {
                "Url",
                "DecodedPath",
                "Status",
                "Canonical",
                "SelfCanonical",
                "Title",
                "ContentSha256",
            }
            self.assertTrue(required_headers.issubset(rows[0]), rows[0].keys())

            urls = [row["Url"] for row in rows]
            decoded = [normalize_route(row["DecodedPath"]) for row in rows]
            self.assertEqual(len(urls), len(set(urls)), snapshot_id)
            self.assertEqual(len(decoded), len(set(decoded)), snapshot_id)
            self.assertTrue(all(row["Status"] == "200" for row in rows))
            self.assertTrue(
                all(normalize_route(row["Canonical"]) == normalize_route(row["Url"]) for row in rows)
            )
            self.assertTrue(
                all(row["SelfCanonical"].casefold() == "true" for row in rows)
            )
            self.assertTrue(all(route not in all_routes for route in decoded))
            all_routes.update(decoded)
            total += len(rows)

        self.assertEqual(43, total)
        self.assertEqual(43, len(all_routes))

    def test_managed_live_evidence_is_local_complete_and_hash_bound(self) -> None:
        evidence = load_json(MANAGED_LIVE_EVIDENCE_PATH)
        self.assertEqual(1, evidence["schema_version"])
        self.assertEqual("https://thai-land.co.il", evidence["origin"])
        self.assertEqual(
            {
                "url": "https://thai-land.co.il/wp-json/thailand-platform/v1/health",
                "observed_at": "2026-08-08T19:32:22.941Z",
                "http_status": 200,
                "response": {
                    "name": "thailand-platform",
                    "version": "0.3.5",
                    "status": "ok",
                },
            },
            evidence["health"],
        )

        release = evidence["release"]
        acceptance_claim = evidence["acceptance"]
        for claim, path_key, bytes_key, digest_key in (
            (release, "receipt_path", "receipt_bytes", "receipt_sha256"),
            (release, "artifact_path", "artifact_bytes", "artifact_sha256"),
            (acceptance_claim, "path", "bytes", "sha256"),
        ):
            path = ROOT / claim[path_key]
            self.assertTrue(path.is_file(), path)
            self.assertEqual(claim[bytes_key], path.stat().st_size, path)
            self.assertEqual(
                claim[digest_key], hashlib.sha256(path.read_bytes()).hexdigest(), path
            )

        acceptance = load_json(ROOT / acceptance_claim["path"])
        self.assertEqual("0.3.5", acceptance["release"])
        self.assertEqual(8, acceptance["route_count"])
        self.assertEqual(
            {
                "passed": True,
                "total": 374,
                "passed_count": 374,
                "failed_count": 0,
            },
            {
                key: acceptance["acceptance"][key]
                for key in ("passed", "total", "passed_count", "failed_count")
            },
        )
        self.assertEqual(
            {route["route_id"] for route in evidence["managed_routes"]},
            set(acceptance["routes"]),
        )

        hub_route = self.by_route["/נדלן-בתאילנד/"]
        self.assertEqual("live", hub_route["lifecycle"])
        self.assertEqual([], hub_route["observed_in"])
        self.assertEqual(
            ["data/seo/evidence/managed-live-routes.0.3.5.json"],
            hub_route["source_evidence"],
        )

    def test_protected_urls_have_exactly_one_route_claim(self) -> None:
        expected: dict[str, set[str]] = {}
        for snapshot_id, rows in self.inventory_rows().items():
            for row in rows:
                route = normalize_route(row["DecodedPath"])
                expected.setdefault(route, set()).add(snapshot_id)

        route_groups: dict[str, list[dict[str, Any]]] = {}
        for route in self.routes:
            route_groups.setdefault(normalize_route(route["url"]), []).append(route)

        self.assertEqual(set(expected), {
            key
            for key, group in route_groups.items()
            if any(item["observed_in"] for item in group)
        })
        for url, snapshot_ids in expected.items():
            self.assertEqual(1, len(route_groups[url]), url)
            claim = route_groups[url][0]
            self.assertEqual("live", claim["lifecycle"], url)
            self.assertEqual("index", claim["indexing_policy"], url)
            self.assertEqual(snapshot_ids, set(claim["observed_in"]), url)
            assignment = claim["assignment"]
            if assignment["kind"] == "canonical_owner":
                self.assertIn(assignment["owner_id"], self.by_owner, url)
            else:
                self.assertTrue(assignment["release_blocked"], url)
                self.assertGreaterEqual(len(assignment["required_evidence"]), 1, url)
                current_owner = assignment["current_owner_id"]
                self.assertIn(current_owner, self.by_owner, url)
                if claim["indexing_policy"] == "index":
                    self.assertEqual(
                        url,
                        normalize_route(self.by_owner[current_owner]["canonical_url"]),
                    )
                candidate = assignment["candidate_owner_id"]
                if candidate is not None:
                    self.assertIn(candidate, self.by_owner, url)

    def test_planned_routes_do_not_shadow_observed_urls(self) -> None:
        observed = {
            normalize_route(row["DecodedPath"])
            for rows in self.inventory_rows().values()
            for row in rows
        }
        planned = {
            normalize_route(route["url"])
            for route in self.routes
            if route["lifecycle"] == "planned"
        }
        self.assertTrue(observed.isdisjoint(planned), observed & planned)

    def test_route_and_owner_identifiers_are_unique(self) -> None:
        for records, fields in (
            (self.owners, ("owner_id", "canonical_url", "intent_id")),
            (self.routes, ("route_id", "url")),
            (self.snapshots, ("snapshot_id", "path")),
        ):
            for field in fields:
                values = [record[field] for record in records]
                self.assertEqual(
                    len(values),
                    len(set(values)),
                    f"duplicate {field}",
                )
        self.assertEqual(59, len(self.owners))
        self.assertEqual(60, len(self.routes))

    def test_canonical_assignments_resolve_and_match_routes(self) -> None:
        for route in self.routes:
            assignment = route["assignment"]
            if assignment["kind"] == "migration_gate":
                current = self.by_owner[assignment["current_owner_id"]]
                if route["indexing_policy"] == "redirect":
                    self.assertEqual(route["redirect_target"], current["canonical_url"])
                else:
                    self.assertEqual(route["url"], current["canonical_url"])
                continue
            owner = self.by_owner[assignment["owner_id"]]
            self.assertEqual(route["url"], owner["canonical_url"], route["url"])
            self.assertEqual(route["lifecycle"], owner["lifecycle"], route["url"])

        gate_routes = [
            route
            for route in self.routes
            if route["assignment"]["kind"] == "migration_gate"
        ]
        self.assertEqual(2, len(gate_routes))
        for route in gate_routes:
            self.assertTrue(route["assignment"]["release_blocked"])
            self.assertIn(route["assignment"]["current_owner_id"], self.by_owner)
            self.assertGreaterEqual(
                len(route["assignment"]["required_evidence"]), 1, route["url"]
            )

    def test_business_owner_uses_existing_url_and_short_route_stays_redirect(self) -> None:
        business = self.by_owner["business-in-thailand"]
        self.assertEqual(
            "/עסקים-בתאילנד-סקירה-כללית/",
            business["canonical_url"],
        )
        short = self.by_route["/עסקים-בתאילנד/"]
        self.assertEqual("redirect", short["indexing_policy"])
        self.assertEqual(
            business["canonical_url"],
            short["redirect_target"],
        )
        self.assertEqual("migration_gate", short["assignment"]["kind"])
        self.assertEqual(
            "business-in-thailand",
            short["assignment"]["candidate_owner_id"],
        )
        self.assertEqual(
            "business-in-thailand",
            short["assignment"]["current_owner_id"],
        )

    def test_intent_terms_have_one_owner(self) -> None:
        term_owner: dict[str, str] = {}
        for owner in self.owners:
            terms = [owner["primary_keyword"], *owner["intent_synonyms"]]
            local: set[str] = set()
            for raw in terms:
                term = normalized_term(raw)
                self.assertNotIn(term, local, owner["owner_id"])
                local.add(term)
                if term in term_owner:
                    self.assertEqual(
                        term_owner[term],
                        owner["owner_id"],
                        f"intent term claimed by two owners: {raw}",
                    )
                term_owner[term] = owner["owner_id"]

    def test_breadcrumbs_match_owner_hierarchy(self) -> None:
        for owner in self.owners:
            owner_id = owner["owner_id"]
            chain = owner["breadcrumb_chain"]
            hierarchy: list[str] = []
            current: str | None = owner_id
            while current is not None:
                hierarchy.append(current)
                current = self.by_owner[current]["parent_owner_id"]
            hierarchy.reverse()
            expected = (
                [
                    item
                    for item in hierarchy
                    if self.by_owner[item]["lifecycle"] == "live"
                ]
                if owner["lifecycle"] == "live"
                else hierarchy
            )
            self.assertEqual(expected, [item["owner_id"] for item in chain], owner_id)
            self.assertEqual("home", chain[0]["owner_id"], owner_id)
            self.assertEqual(owner_id, chain[-1]["owner_id"], owner_id)
            self.assertEqual(len(chain), len({item["owner_id"] for item in chain}))
            for item in chain:
                target = self.by_owner[item["owner_id"]]
                self.assertEqual(target["name"], item["name"], owner_id)
                self.assertEqual(target["canonical_url"], item["url"], owner_id)
                if owner["lifecycle"] == "live":
                    self.assertEqual("live", target["lifecycle"], owner_id)
            if owner_id == "home":
                self.assertIsNone(owner["parent_owner_id"])
                self.assertEqual(1, len(chain))
            else:
                parent = owner["parent_owner_id"]
                self.assertIn(parent, self.by_owner, owner_id)
                if owner["lifecycle"] == "planned" or self.by_owner[parent]["lifecycle"] == "live":
                    self.assertEqual(parent, chain[-2]["owner_id"], owner_id)
                else:
                    self.assertNotIn(parent, [item["owner_id"] for item in chain], owner_id)

    def test_hierarchy_has_no_parent_cycles(self) -> None:
        for owner in self.owners:
            seen: set[str] = set()
            current: str | None = owner["owner_id"]
            while current is not None:
                self.assertNotIn(current, seen, owner["owner_id"])
                seen.add(current)
                current = self.by_owner[current]["parent_owner_id"]

    def test_internal_link_graph_is_resolved_and_reciprocal(self) -> None:
        technical = {"api_endpoint", "sitemap"}
        for owner in self.owners:
            owner_id = owner["owner_id"]
            edges: set[tuple[str, str, str]] = set()
            for edge in owner["internal_link_requirements"]:
                target = edge["target_owner_id"]
                self.assertIn(target, self.by_owner, owner_id)
                self.assertNotEqual(owner_id, target, owner_id)
                self.assertEqual("live", owner["lifecycle"], owner_id)
                self.assertEqual("live", self.by_owner[target]["lifecycle"], owner_id)
                key = (target, edge["relationship"], edge["placement"])
                self.assertNotIn(key, edges, owner_id)
                edges.add(key)
                self.assertGreaterEqual(edge["minimum_occurrences"], 1)
                self.assertGreaterEqual(len(edge["anchor_terms"]), 1)

            planned_edges: set[tuple[str, str, str]] = set()
            for edge in owner["planned_internal_link_requirements"]:
                target = edge["target_owner_id"]
                self.assertIn(target, self.by_owner, owner_id)
                self.assertNotEqual(owner_id, target, owner_id)
                self.assertTrue(
                    owner["lifecycle"] == "planned"
                    or self.by_owner[target]["lifecycle"] == "planned",
                    owner_id,
                )
                key = (target, edge["relationship"], edge["placement"])
                self.assertNotIn(key, planned_edges, owner_id)
                self.assertNotIn(key, edges, owner_id)
                planned_edges.add(key)
                self.assertGreaterEqual(edge["minimum_occurrences"], 1)
                self.assertGreaterEqual(len(edge["anchor_terms"]), 1)

            parent = owner["parent_owner_id"]
            if (
                owner_id != "home"
                and owner["entity_type"] not in technical
                and parent is not None
            ):
                bucket_name = (
                    "internal_link_requirements"
                    if owner["lifecycle"] == "live"
                    and self.by_owner[parent]["lifecycle"] == "live"
                    else "planned_internal_link_requirements"
                )
                self.assertTrue(
                    any(
                        edge["target_owner_id"] == parent
                        and edge["relationship"] == "parent_hub"
                        and edge["placement"] == "contextual_body"
                        for edge in owner[bucket_name]
                    ),
                    owner_id,
                )
                self.assertTrue(
                    any(
                        edge["target_owner_id"] == owner_id
                        and edge["relationship"] == "child_spoke"
                        for edge in self.by_owner[parent][bucket_name]
                    ),
                    owner_id,
                )

            if (
                owner["lifecycle"] == "live"
                and owner_id != "home"
                and owner["entity_type"] not in technical
            ):
                live_parent = owner["breadcrumb_chain"][-2]["owner_id"]
                self.assertTrue(
                    any(
                        edge["target_owner_id"] == live_parent
                        and edge["relationship"] == "parent_hub"
                        for edge in owner["internal_link_requirements"]
                    ),
                    owner_id,
                )
                self.assertTrue(
                    any(
                        edge["target_owner_id"] == owner_id
                        and edge["relationship"] == "child_spoke"
                        for edge in self.by_owner[live_parent]["internal_link_requirements"]
                    ),
                    owner_id,
                )

    def test_every_live_index_owner_is_reachable_from_home(self) -> None:
        public_owner_ids: set[str] = set()
        for route in self.routes:
            if (
                route["lifecycle"] != "live"
                or route["indexing_policy"] != "index"
            ):
                continue
            assignment = route["assignment"]
            public_owner_ids.add(
                assignment["owner_id"]
                if assignment["kind"] == "canonical_owner"
                else assignment["current_owner_id"]
            )

        reachable = {"home"}
        pending = ["home"]
        while pending:
            source = pending.pop()
            for edge in self.by_owner[source]["internal_link_requirements"]:
                target = edge["target_owner_id"]
                if target not in reachable:
                    reachable.add(target)
                    pending.append(target)

        self.assertEqual(44, len(public_owner_ids))
        self.assertTrue(public_owner_ids.issubset(reachable), public_owner_ids - reachable)

    def test_real_estate_spokes_have_hub_and_two_contextual_continuations(self) -> None:
        spokes = {
            "buy-property-thailand",
            "thailand-property-prices",
            "thailand-property-financing",
            "foreign-condo-ownership-thailand",
            "thailand-property-due-diligence-mistakes",
            "property-management-thailand",
            "bangkok-apartment-rental-guide",
        }
        hub = self.by_owner["thailand-real-estate"]
        self.assertEqual("live", hub["lifecycle"])
        self.assertEqual(
            ["home", "thailand-real-estate"],
            [crumb["owner_id"] for crumb in hub["breadcrumb_chain"]],
        )
        for spoke_id in spokes:
            spoke = self.by_owner[spoke_id]
            contextual = [
                edge
                for edge in spoke["internal_link_requirements"]
                if edge["placement"] == "contextual_body"
            ]
            targets = {edge["target_owner_id"] for edge in contextual}
            self.assertIn("thailand-real-estate", targets, spoke_id)
            self.assertGreaterEqual(len(targets), 2, spoke_id)
            self.assertTrue(
                any(
                    edge["target_owner_id"] == "thailand-real-estate"
                    and edge["relationship"] == "parent_hub"
                    for edge in spoke["internal_link_requirements"]
                ),
                spoke_id,
            )
            self.assertTrue(
                any(
                    edge["target_owner_id"] == spoke_id
                    and edge["relationship"] == "child_spoke"
                    for edge in hub["internal_link_requirements"]
                ),
                spoke_id,
            )
            self.assertEqual(
                ["home", "thailand-real-estate", spoke_id],
                [crumb["owner_id"] for crumb in spoke["breadcrumb_chain"]],
                spoke_id,
            )
            self.assertFalse(
                any(
                    edge["target_owner_id"] == "thailand-real-estate"
                    for edge in spoke["planned_internal_link_requirements"]
                ),
                spoke_id,
            )

    def test_managed_content_routes_use_canonical_seo_owner_foreign_keys(self) -> None:
        content = load_json(CONTENT_PATH)
        content_by_id = {route["route_id"]: route for route in content["routes"]}
        expected_divergences = {
            "thailand-property-buying-mistakes": "thailand-property-due-diligence-mistakes",
            "bangkok-apartment-rental": "bangkok-apartment-rental-guide",
            "thailand-property-management": "property-management-thailand",
        }
        self.assertEqual(
            expected_divergences,
            {
                route_id: route["seo_owner_id"]
                for route_id, route in content_by_id.items()
                if route_id != route["seo_owner_id"]
            },
        )

        for route_id, route in content_by_id.items():
            owner = self.by_owner[route["seo_owner_id"]]
            self.assertEqual("live", owner["lifecycle"], route_id)
            self.assertEqual(route["path"], owner["canonical_url"], route_id)
            parent_route_id = route["parent_route_id"]
            expected_parent = (
                "home"
                if parent_route_id is None
                else content_by_id[parent_route_id]["seo_owner_id"]
            )
            self.assertEqual(expected_parent, owner["parent_owner_id"], route_id)
            expected_chain = [
                "home"
                if crumb["route_id"] is None
                else content_by_id[crumb["route_id"]]["seo_owner_id"]
                for crumb in route["breadcrumbs"]
            ]
            self.assertEqual(
                expected_chain,
                [crumb["owner_id"] for crumb in owner["breadcrumb_chain"]],
                route_id,
            )

    def test_baseline_html_gaps_cannot_be_marked_ready(self) -> None:
        rows = {
            normalize_route(row["DecodedPath"]): row
            for snapshot_rows in self.inventory_rows().values()
            for row in snapshot_rows
        }
        for owner in self.owners:
            if owner["lifecycle"] != "live":
                continue
            row = rows.get(normalize_route(owner["canonical_url"]))
            if row is None:
                continue
            lacks_baseline_implementation = (
                int(row["MetaDescriptionLength"] or 0) == 0
                or int(row["MainH1Count"] or 0) != 1
                or int(row["MainUniqueInternalDestinations"] or 0) == 0
            )
            if lacks_baseline_implementation:
                self.assertNotEqual("ready", owner["review_state"], owner["owner_id"])

    def test_cannibalization_exclusions_use_stable_owner_and_intent_ids(self) -> None:
        for owner in self.owners:
            seen: set[str] = set()
            for exclusion in owner["cannibalization_exclusions"]:
                target_id = exclusion["owner_id"]
                self.assertNotEqual(owner["owner_id"], target_id)
                self.assertNotIn(target_id, seen, owner["owner_id"])
                seen.add(target_id)
                self.assertIn(target_id, self.by_owner)
                self.assertEqual(
                    self.by_owner[target_id]["intent_id"],
                    exclusion["intent_id"],
                )

    def test_subject_entities_are_valid_geography_foreign_keys(self) -> None:
        allowed = authoritative_geography_ids()
        for owner in self.owners:
            for entity_id in owner["subject_entity_ids"]:
                self.assertIn(entity_id, allowed, owner["owner_id"])
        self.assertEqual(
            ["geo:th:province:10"],
            self.by_owner["bangkok"]["subject_entity_ids"],
        )
        self.assertEqual(
            ["geo:th:province:83", "geo:th:province:84"],
            self.by_owner["phuket-or-samui"]["subject_entity_ids"],
        )

    def test_source_evidence_paths_exist(self) -> None:
        for owner in self.owners:
            for evidence in owner["source_evidence"]:
                if evidence.startswith(("https://", "http://")):
                    continue
                self.assertTrue((ROOT / evidence).is_file(), evidence)
        for route in self.routes:
            for evidence in route["source_evidence"]:
                if evidence.startswith(("https://", "http://")):
                    continue
                self.assertTrue((ROOT / evidence).is_file(), evidence)

    def test_research_evidence_is_current_and_explicit(self) -> None:
        evidence = {
            item["evidence_id"]: item
            for item in self.registry["research_evidence"]
        }
        required = {
            "google-ai-search-2026-07",
            "google-title-links",
            "google-link-practices",
            "google-breadcrumbs",
            "google-sitemaps",
            "google-commerce-structure",
            "hebrew-thailand-serp-2026-08-08",
        }
        self.assertEqual(required, set(evidence))
        for item in evidence.values():
            self.assertEqual("2026-08-08", item["checked_on"])
            self.assertGreater(len(item["purpose"]), 10)

    def test_human_fields_are_hebrew_first(self) -> None:
        hebrew = re.compile(r"[\u0590-\u05ff]")
        for owner in self.owners:
            for field in ("name", "primary_intent", "unique_contribution"):
                self.assertRegex(owner[field], hebrew, f"{owner['owner_id']} {field}")

    def test_forbidden_dash_characters_are_absent_from_authored_contracts(self) -> None:
        forbidden_characters = (chr(0x2013), chr(0x2014))
        forbidden_encodings = (
            "\\" + "u" + "2013",
            "\\" + "u" + "2014",
            "&#" + "8211;",
            "&#" + "8212;",
        )
        paths = (
            REGISTRY_PATH,
            SCHEMA_PATH,
            README_PATH,
            BUILDER_PATH,
            Path(__file__),
        )
        for path in paths:
            text = path.read_text(encoding="utf-8")
            for character in forbidden_characters:
                self.assertNotIn(character, text, str(path))
            lowered = text.lower()
            for encoding in forbidden_encodings:
                self.assertNotIn(encoding, lowered, str(path))


if __name__ == "__main__":
    unittest.main(verbosity=2)
