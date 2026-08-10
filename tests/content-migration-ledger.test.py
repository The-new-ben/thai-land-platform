"""Dependency-free contract tests for the content migration execution ledger."""

from __future__ import annotations

import copy
import json
import re
import subprocess
import sys
import unittest
from collections import Counter
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"
if str(SCRIPTS) not in sys.path:
    sys.path.insert(0, str(SCRIPTS))

from build_content_migration_ledger import (  # noqa: E402
    CATEGORY_INVENTORY_PATH,
    CANDIDATE_OWNERS,
    DRAFT_DISPOSITION_PATH,
    DRAFT_METADATA_PATH,
    LEDGER_PATH,
    MANAGED_ROUTES_PATH,
    OWNERSHIP_REGISTRY_PATH,
    PUBLIC_INVENTORY_PATH,
    RELEASED_DRAFT_IDS,
    SCHEMA_PATH,
    SOURCE_REVIEW_PATH,
    SYSTEM_ENRICHMENT_HUBS,
    build_ledger,
    load_csv,
    load_json,
    normalize_route,
    sha256_lf,
    validate_ledger,
)


BUILDER_PATH = SCRIPTS / "build_content_migration_ledger.py"
README_PATH = ROOT / "data" / "content" / "migration" / "README.md"


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
    raise AssertionError(f"unsupported schema type: {expected}")


class SchemaValidator:
    """Validate every JSON Schema keyword used by the migration schema."""

    def __init__(self, schema: dict[str, Any]) -> None:
        self.schema = schema

    def resolve_ref(self, reference: str) -> dict[str, Any]:
        if not reference.startswith("#/"):
            raise AssertionError(f"unsupported reference: {reference}")
        current: Any = self.schema
        for raw_part in reference[2:].split("/"):
            part = raw_part.replace("~1", "/").replace("~0", "~")
            current = current[part]
        if not isinstance(current, dict):
            raise AssertionError(f"reference is not an object: {reference}")
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
            accepted = expected_type if isinstance(expected_type, list) else [expected_type]
            if not any(json_type_matches(value, item) for item in accepted):
                return [f"{path}: expected {accepted}, got {type(value).__name__}"]

        if "const" in current and value != current["const"]:
            errors.append(f"{path}: constant differs")
        if "enum" in current and value not in current["enum"]:
            errors.append(f"{path}: value is outside enum")

        if isinstance(value, str):
            if len(value) < current.get("minLength", 0):
                errors.append(f"{path}: string shorter than minLength")
            pattern = current.get("pattern")
            if pattern is not None and re.search(pattern, value) is None:
                errors.append(f"{path}: string does not match {pattern}")

        if isinstance(value, int) and not isinstance(value, bool):
            minimum = current.get("minimum")
            if minimum is not None and value < minimum:
                errors.append(f"{path}: number below minimum")

        if isinstance(value, list):
            if len(value) < current.get("minItems", 0):
                errors.append(f"{path}: array shorter than minItems")
            if current.get("uniqueItems"):
                serialized = [
                    json.dumps(item, ensure_ascii=False, sort_keys=True) for item in value
                ]
                if len(serialized) != len(set(serialized)):
                    errors.append(f"{path}: array items are not unique")
            item_schema = current.get("items")
            if isinstance(item_schema, dict):
                for index, item in enumerate(value):
                    errors.extend(self.validate(item, item_schema, f"{path}[{index}]"))

        if isinstance(value, dict):
            properties = current.get("properties", {})
            for required in current.get("required", []):
                if required not in value:
                    errors.append(f"{path}: missing required property {required}")
            for key, item in value.items():
                if key in properties:
                    errors.extend(self.validate(item, properties[key], f"{path}.{key}"))
                elif current.get("additionalProperties") is False:
                    errors.append(f"{path}: unexpected property {key}")
        return errors


def expected_hierarchy(owner_id: str, owners: dict[str, dict[str, Any]]) -> list[str]:
    chain: list[str] = []
    current: str | None = owner_id
    while current is not None:
        if current in chain:
            raise AssertionError(f"owner hierarchy cycle: {owner_id}")
        chain.append(current)
        current = owners[current]["parent_owner_id"]
    chain.reverse()
    return chain


class ContentMigrationLedgerTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.ledger = load_json(LEDGER_PATH)
        cls.schema = load_json(SCHEMA_PATH)
        cls.validator = SchemaValidator(cls.schema)
        cls.registry = load_json(OWNERSHIP_REGISTRY_PATH)
        cls.managed = load_json(MANAGED_ROUTES_PATH)
        cls.owners = {
            item["owner_id"]: item for item in cls.registry["intent_owners"]
        }
        cls.routes = {
            normalize_route(item["url"]): item for item in cls.registry["routes"]
        }
        cls.source_review = load_json(SOURCE_REVIEW_PATH)
        cls.source_reviews = {
            item["post_id"]: item for item in cls.source_review["records"]
        }
        cls.legacy = cls.ledger["legacy_public_surfaces"]
        cls.drafts = cls.ledger["draft_records"]
        cls.hubs = cls.ledger["planned_hubs"]

    def test_ledger_validates_against_schema(self) -> None:
        errors = self.validator.validate(self.ledger)
        self.assertEqual([], errors, "\n".join(errors))

    def test_schema_rejects_missing_completion_evidence(self) -> None:
        broken = copy.deepcopy(self.ledger)
        del broken["legacy_public_surfaces"][0]["completion_evidence"]
        errors = self.validator.validate(broken)
        self.assertTrue(
            any("missing required property completion_evidence" in item for item in errors),
            errors,
        )

    def test_builder_output_is_current(self) -> None:
        completed = subprocess.run(
            [sys.executable, str(BUILDER_PATH)],
            cwd=ROOT,
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(0, completed.returncode, completed.stdout + completed.stderr)
        self.assertIn("PASS: content migration ledger is current", completed.stdout)

    def test_scope_is_exact_and_all_record_ids_are_unique(self) -> None:
        self.assertEqual(43, self.ledger["scope"]["frozen_public_surface_count"])
        self.assertEqual(
            8, self.ledger["scope"]["platform_managed_original_surface_count"]
        )
        self.assertEqual(35, len(self.legacy))
        self.assertEqual(63, len(self.drafts))
        self.assertEqual(11, len(self.hubs))
        record_ids = [
            item["record_id"] for item in [*self.legacy, *self.drafts, *self.hubs]
        ]
        self.assertEqual(len(record_ids), len(set(record_ids)))

    def test_legacy_public_surfaces_match_frozen_inventories(self) -> None:
        observed_rows: dict[str, dict[str, str]] = {}
        for path in (PUBLIC_INVENTORY_PATH, CATEGORY_INVENTORY_PATH):
            for row in load_csv(path):
                url = normalize_route(row["DecodedPath"])
                self.assertNotIn(url, observed_rows)
                observed_rows[url] = row
        exclusions = {
            item["url"]
            for item in self.ledger["scope"]["platform_managed_original_surfaces"]
        }
        actual = {item["source_url"] for item in self.legacy}
        self.assertEqual(set(observed_rows) - exclusions, actual)
        self.assertEqual(43, len(observed_rows))
        self.assertEqual(8, len(exclusions))
        self.assertIn("/", exclusions)

        for record in self.legacy:
            row = observed_rows[record["source_url"]]
            self.assertEqual(row["ContentSha256"], record["source_metrics"]["content_sha256"])
            review_key = record["evidence"]["source_review_record_key"]
            if review_key is None:
                self.assertEqual("live_html_retrieval_required", record["source_material_status"])
                self.assertEqual("not_in_repository", record["evidence"]["source_body_snapshot_status"])
            else:
                self.assertIn(review_key, self.source_reviews)
                self.assertEqual("live_body_reviewed_no_body_stored", record["source_material_status"])
                self.assertEqual("reviewed_no_body_stored", record["evidence"]["source_body_snapshot_status"])

    def test_legacy_owner_and_hierarchy_assignments_match_registry(self) -> None:
        for record in self.legacy:
            route = self.routes[record["source_url"]]
            assignment = route["assignment"]
            if assignment["kind"] == "canonical_owner":
                owner_id = assignment["owner_id"]
                self.assertIsNone(record["candidate_owner_id"])
            else:
                owner_id = assignment["current_owner_id"]
                self.assertEqual(assignment["candidate_owner_id"], record["candidate_owner_id"])
                self.assertTrue(record["release_target"]["blocked"])
            owner = self.owners[owner_id]
            self.assertEqual(owner_id, record["canonical_owner_id"])
            self.assertEqual(owner["canonical_url"], record["canonical_target_url"])
            self.assertEqual(owner["parent_owner_id"], record["hierarchy_parent_owner_id"])
            self.assertEqual(
                expected_hierarchy(owner_id, self.owners),
                record["hierarchy_path_owner_ids"],
            )

        self.assertEqual(
            {
                "conditional_merge": 8,
                "extract_then_review": 4,
                "keep_rewrite": 21,
                "preserve_historical": 2,
            },
            dict(Counter(item["disposition"] for item in self.legacy)),
        )
        self.assertEqual(
            {"blocked_evidence": 14, "rewrite_pending": 21},
            dict(Counter(item["migration_status"] for item in self.legacy)),
        )

    def test_remaining_drafts_match_both_source_inventories(self) -> None:
        metadata = {
            int(row["PostId"]): row for row in load_csv(DRAFT_METADATA_PATH)
        }
        dispositions = {
            int(row["PostId"]): row for row in load_csv(DRAFT_DISPOSITION_PATH)
        }
        self.assertEqual(set(metadata), set(dispositions))
        released = {
            post_id
            for post_id, row in dispositions.items()
            if row["Disposition"] == "publish_release"
        }
        self.assertEqual(RELEASED_DRAFT_IDS, released)
        actual = {item["source_post_id"] for item in self.drafts}
        self.assertEqual(set(metadata) - released, actual)

        reusable = 0
        for record in self.drafts:
            post_id = record["source_post_id"]
            source = metadata[post_id]
            disposition = dispositions[post_id]
            self.assertEqual(source["PostType"], record["source_post_type"])
            self.assertEqual(disposition["Disposition"], record["disposition"])
            review = self.source_reviews.get(post_id)
            expected_target = (
                review["target_owner_id"] if review is not None else disposition["TargetOwnerId"]
            )
            self.assertEqual(expected_target, record["release_target"]["target_owner_id"])
            self.assertEqual(disposition["TargetOwnerId"], record["evidence"]["source_target_owner_id"])
            if review is None:
                self.assertEqual("draft_body_retrieval_required", record["source_material_status"])
                self.assertEqual("not_in_repository", record["evidence"]["source_body_snapshot_status"])
            else:
                self.assertEqual("draft_body_reviewed_no_body_stored", record["source_material_status"])
                self.assertEqual("reviewed_no_body_stored", record["evidence"]["source_body_snapshot_status"])
            if record["source_material_class"] != "no_reusable_material":
                reusable += 1
        self.assertEqual(58, reusable)
        self.assertEqual(
            {
                "discard": 5,
                "discard_after_extract": 4,
                "extract_entity": 7,
                "extract_merge": 13,
                "keep_rewrite": 12,
                "legal_rewrite": 5,
                "rebuild_tool": 1,
                "service_rewrite": 5,
                "split_rewrite": 10,
                "trust_rewrite": 1,
            },
            dict(Counter(item["disposition"] for item in self.drafts)),
        )

    def test_draft_targets_are_registered_or_explicit_candidates(self) -> None:
        candidate_ids: set[str] = set()
        for record in self.drafts:
            target_id = record["release_target"]["target_owner_id"]
            if target_id in self.owners:
                self.assertEqual(target_id, record["canonical_owner_id"])
                self.assertIsNone(record["candidate_owner_id"])
                self.assertEqual(
                    expected_hierarchy(target_id, self.owners),
                    record["hierarchy_path_owner_ids"],
                )
            else:
                candidate_ids.add(target_id)
                self.assertIn(target_id, CANDIDATE_OWNERS)
                self.assertIsNone(record["canonical_owner_id"])
                self.assertEqual(target_id, record["candidate_owner_id"])
                parent = CANDIDATE_OWNERS[target_id]["parent_owner_id"]
                self.assertEqual(parent, record["hierarchy_parent_owner_id"])
        self.assertEqual(set(CANDIDATE_OWNERS), candidate_ids)
        cannabis_business = next(
            item for item in self.drafts if item["source_post_id"] == 498
        )
        self.assertEqual(
            "candidate-cannabis-business-thailand",
            cannabis_business["release_target"]["target_owner_id"],
        )
        self.assertEqual("thailand-cannabis-law", cannabis_business["evidence"]["source_target_owner_id"])
        self.assertEqual("separate_owner_full_legal_research", cannabis_business["evidence"]["source_review_decision"])

    def test_planned_hubs_match_registry_and_expose_supporting_sources(self) -> None:
        expected = {
            item["owner_id"]
            for item in self.registry["intent_owners"]
            if item["lifecycle"] == "planned"
        }
        actual = {item["owner_id"] for item in self.hubs}
        self.assertEqual(expected, actual)
        self.assertEqual(11, len(actual))
        for hub in self.hubs:
            owner = self.owners[hub["owner_id"]]
            self.assertEqual(owner["canonical_url"], hub["canonical_url"])
            self.assertEqual(owner["parent_owner_id"], hub["hierarchy_parent_owner_id"])
            self.assertEqual(
                expected_hierarchy(owner["owner_id"], self.owners),
                hub["hierarchy_path_owner_ids"],
            )
            expected_enrichment = (
                "wave-05-platform-systems"
                if hub["owner_id"] in SYSTEM_ENRICHMENT_HUBS
                else None
            )
            self.assertEqual(
                expected_enrichment,
                hub["release_target"]["system_enrichment_wave_id"],
            )
        identity_hubs = {
            item["owner_id"]: item for item in self.hubs
            if item["migration_status"] == "identity_created_content_pending"
        }
        self.assertEqual({"thailand-visas", "thailand-law-and-tax"}, set(identity_hubs))
        self.assertEqual(846, identity_hubs["thailand-visas"]["evidence"]["draft_identity_post_id"])
        self.assertEqual(848, identity_hubs["thailand-law-and-tax"]["evidence"]["draft_identity_post_id"])
        for hub in identity_hubs.values():
            self.assertEqual("production_draft_identity", hub["release_target"]["target_state"])
            self.assertEqual("draft", hub["evidence"]["draft_identity_status"])
        map_hub = next(item for item in self.hubs if item["owner_id"] == "thailand-map")
        self.assertEqual(
            "structured_geography_available_content_required",
            map_hub["source_material_status"],
        )
        self.assertEqual(3, len(map_hub["evidence"]["structured_source_paths"]))

    def test_no_route_or_source_is_falsely_complete(self) -> None:
        records = [*self.legacy, *self.drafts, *self.hubs]
        self.assertTrue(all(item["migration_status"] != "complete" for item in records))
        for record in records:
            values = record["completion_evidence"].values()
            self.assertTrue(all(value in (None, False) for value in values), record["record_id"])

        broken = copy.deepcopy(self.ledger)
        broken["legacy_public_surfaces"][0]["migration_status"] = "complete"
        errors = validate_ledger(broken)
        self.assertTrue(any("complete without sufficient evidence" in item for item in errors))

    def test_evidence_paths_exist_and_source_digests_are_current(self) -> None:
        for source in self.ledger["sources"]:
            path = ROOT / source["path"]
            self.assertTrue(path.is_file(), source["path"])
            self.assertEqual(source["sha256_lf"], sha256_lf(path))
        for item in self.ledger["scope"]["platform_managed_original_surfaces"]:
            self.assertTrue((ROOT / item["evidence_path"]).is_file(), item)
        for hub in self.hubs:
            for path in hub["evidence"]["structured_source_paths"]:
                self.assertTrue((ROOT / path).is_file(), path)

    def test_release_waves_are_ordered_and_every_target_resolves(self) -> None:
        waves = self.ledger["release_waves"]
        self.assertEqual(list(range(1, 7)), [item["sequence"] for item in waves])
        wave_ids = {item["wave_id"] for item in waves}
        for index, wave in enumerate(waves):
            previous = {item["wave_id"] for item in waves[:index]}
            self.assertTrue(set(wave["depends_on"]).issubset(previous), wave["wave_id"])
        for record in [*self.legacy, *self.drafts, *self.hubs]:
            target = record["release_target"]
            self.assertIn(target["wave_id"], wave_ids)
            if target["system_enrichment_wave_id"] is not None:
                self.assertIn(target["system_enrichment_wave_id"], wave_ids)

    def test_committed_ledger_is_exact_builder_output(self) -> None:
        self.assertEqual(build_ledger(), self.ledger)

    def test_forbidden_dash_characters_are_absent(self) -> None:
        forbidden_characters = (chr(0x2013), chr(0x2014))
        forbidden_encodings = (
            "\\" + "u" + "2013",
            "\\" + "u" + "2014",
            "&#" + "8211;",
            "&#" + "8212;",
        )
        for path in (LEDGER_PATH, SCHEMA_PATH, BUILDER_PATH, README_PATH, Path(__file__)):
            text = path.read_text(encoding="utf-8")
            for character in forbidden_characters:
                self.assertNotIn(character, text, str(path))
            lowered = text.lower()
            for encoded in forbidden_encodings:
                self.assertNotIn(encoded, lowered, str(path))


if __name__ == "__main__":
    unittest.main(verbosity=2)
