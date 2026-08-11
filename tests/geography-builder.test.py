#!/usr/bin/env python3
"""Adversarial tests for the deterministic geography registry compiler."""

from __future__ import annotations

import csv
import hashlib
import importlib.util
import io
import json
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from typing import Any, Callable


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "data" / "geography"
COMPILER_PATH = ROOT / "scripts" / "build_geography_registry.py"
SPEC = importlib.util.spec_from_file_location("build_geography_registry", COMPILER_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("Cannot load geography registry compiler")
COMPILER = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = COMPILER
SPEC.loader.exec_module(COMPILER)


def write_json(path: Path, value: Any) -> None:
    payload = json.dumps(value, ensure_ascii=False, indent=2, allow_nan=False) + "\n"
    path.write_text(payload, encoding="utf-8", newline="")


def read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8"))


def refresh_input_digest(source_dir: Path, relative_path: str) -> None:
    registry_path = source_dir / "registry.json"
    registry = read_json(registry_path)
    matches = [
        descriptor
        for descriptor in registry["inputs"].values()
        if descriptor["path"] == relative_path
    ]
    if len(matches) != 1:
        raise AssertionError(f"Expected one input descriptor for {relative_path}")
    matches[0]["sha256"] = hashlib.sha256((source_dir / relative_path).read_bytes()).hexdigest()
    write_json(registry_path, registry)


def set_input_sources(source_dir: Path, input_name: str, source_ids: list[str]) -> None:
    registry_path = source_dir / "registry.json"
    registry = read_json(registry_path)
    registry["inputs"][input_name]["source_ids"] = sorted(source_ids)
    write_json(registry_path, registry)


def read_csv_rows(path: Path) -> list[list[str]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.reader(handle, strict=True))


def write_csv_rows(path: Path, rows: list[list[str]]) -> None:
    buffer = io.StringIO(newline="")
    writer = csv.writer(buffer, lineterminator="\n")
    writer.writerows(rows)
    path.write_text(buffer.getvalue(), encoding="utf-8", newline="")


def sort_alias_rows(rows: list[list[str]]) -> list[list[str]]:
    header = rows[0]
    records = sorted(
        rows[1:],
        key=lambda row: (
            row[0],
            row[1],
            COMPILER.normalize_alias(row[2]),
            row[4],
            row[2],
            row[3],
            row[5],
            row[6],
        ),
    )
    return [header, *records]


class GeographyBuilderTest(unittest.TestCase):
    maxDiff = None

    def copied_source(self) -> tuple[tempfile.TemporaryDirectory[str], Path]:
        temporary = tempfile.TemporaryDirectory()
        source_dir = Path(temporary.name) / "geography"
        shutil.copytree(SOURCE, source_dir)
        return temporary, source_dir

    def assert_rejected(
        self,
        mutation: Callable[[Path], None],
        message_pattern: str,
    ) -> None:
        temporary, source_dir = self.copied_source()
        try:
            mutation(source_dir)
            with self.assertRaisesRegex(COMPILER.RegistryError, message_pattern):
                COMPILER.compile_registry(source_dir)
        finally:
            temporary.cleanup()

    def test_runtime_contract_and_real_alias_safety_cases(self) -> None:
        result = COMPILER.compile_registry(SOURCE)
        registry = result.registry
        self.assertEqual(
            {
                "schema_version",
                "dataset_version",
                "country_id",
                "public_digest",
                "entities_by_id",
                "indexes",
                "public_payload",
            },
            set(registry),
        )
        self.assertEqual(132, len(registry["entities_by_id"]))
        self.assertEqual(
            {
                "by_external_id",
                "by_slug",
                "by_alias",
                "relations_by_subject",
                "children_by_parent",
                "members_by_scheme",
            },
            set(registry["indexes"]),
        )
        expected_entity_fields = {
            "id",
            "kind",
            "type",
            "status",
            "slug",
            "names",
            "external_ids",
            "priority",
            "geometry",
        }
        for entity in registry["entities_by_id"].values():
            self.assertEqual(expected_entity_fields, set(entity))
            self.assertEqual("geography", entity["kind"])

        aliases = registry["indexes"]["by_alias"]
        self.assertEqual(
            ["geo:th:province:30", "geo:th:province:80"],
            [candidate["entity_id"] for candidate in aliases["en"]["nakhon"]],
        )
        retired = aliases["en"]["sra kaew"]
        self.assertEqual(1, len(retired))
        self.assertEqual("geo:th:province:27", retired[0]["entity_id"])
        self.assertEqual("retired", retired[0]["status"])
        for locale_aliases in aliases.values():
            for candidates in locale_aliases.values():
                for candidate in candidates:
                    self.assertEqual(
                        {"entity_id", "context_id", "status", "alias"},
                        set(candidate),
                    )

        relation_fields = {
            "type",
            "object_id",
            "scheme_id",
            "is_primary",
            "valid_from",
            "valid_to",
            "source_id",
        }
        relations = registry["indexes"]["relations_by_subject"]
        self.assertEqual(220, sum(len(items) for items in relations.values()))
        for subject_relations in relations.values():
            for relation in subject_relations:
                self.assertEqual(relation_fields, set(relation))

        public_payload = registry["public_payload"]
        self.assertEqual(77, len(public_payload["provinces"]))
        self.assertEqual(7, len(public_payload["regions"]))
        self.assertEqual(47, len(public_payload["places"]))
        self.assertEqual(
            {"district": 7, "island": 6, "subdistrict": 34},
            {
                place_type: sum(1 for place in public_payload["places"] if place["type"] == place_type)
                for place_type in ("district", "island", "subdistrict")
            },
        )
        self.assertTrue(all(place["geometry"] is None for place in public_payload["places"]))
        self.assertTrue(all(place["center"] is None for place in public_payload["places"]))
        self.assertTrue(all(place["bounds"] is None for place in public_payload["places"]))
        for place in public_payload["places"]:
            self.assertEqual(
                expected_entity_fields | {"admin_parent_id", "located_in_ids", "center", "bounds"},
                set(place),
            )
        self.assertEqual(
            registry["public_digest"],
            hashlib.sha256(result.artifacts["assets/geography/core.json"]).hexdigest(),
        )
        self.assertLessEqual(len(result.artifacts["assets/geography/core.json"]), 150_000)

    def test_normalization_vectors_and_no_intl_thai_fallback(self) -> None:
        result = COMPILER.compile_registry(SOURCE)
        vectors = read_json(SOURCE / "normalization-vectors.json")["vectors"]
        for vector in vectors:
            self.assertEqual(vector["normalized"], COMPILER.normalize_alias(vector["input"]))

        thai_aliases = result.registry["indexes"]["by_alias"]["th"]
        fallback_count = 0
        for entity in result.registry["entities_by_id"].values():
            thai_name = entity["names"]["th"]
            primary_key = COMPILER.normalize_alias(thai_name)
            fallback_key = COMPILER.normalize_alias_without_intl(thai_name)
            self.assertIn(primary_key, thai_aliases)
            if fallback_key != primary_key:
                fallback_count += 1
                self.assertIn(fallback_key, thai_aliases)
                self.assertIn(
                    entity["id"],
                    [candidate["entity_id"] for candidate in thai_aliases[fallback_key]],
                )
        self.assertGreater(fallback_count, 0)

    def test_artifacts_are_deterministic_current_and_within_budget(self) -> None:
        first = COMPILER.compile_registry(SOURCE)
        second = COMPILER.compile_registry(SOURCE)
        self.assertEqual(first.artifacts, second.artifacts)
        COMPILER.write_or_check(first, ROOT, True)
        self.assertLessEqual(len(first.artifacts["assets/geography/core.json"]), 150_000)

        with tempfile.TemporaryDirectory() as temporary:
            output_root = Path(temporary)
            COMPILER.write_or_check(first, output_root, False)
            COMPILER.write_or_check(first, output_root, True)
            core_path = output_root / "assets" / "geography" / "core.json"
            core_path.write_bytes(core_path.read_bytes() + b" ")
            with self.assertRaisesRegex(COMPILER.RegistryError, "artifacts are stale"):
                COMPILER.write_or_check(first, output_root, True)

    def test_duplicate_json_key_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "geometry.json"
            text = path.read_text(encoding="utf-8")
            text = text.replace(
                '"schema_version": "1.0.0",',
                '"schema_version": "1.0.0",\n  "schema_version": "1.0.0",',
                1,
            )
            path.write_text(text, encoding="utf-8", newline="")
            refresh_input_digest(source_dir, "geometry.json")

        self.assert_rejected(mutate, "duplicate JSON key")

    def test_non_finite_number_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "geometry.json"
            document = read_json(path)
            document["records"] = [
                {
                    "entity_id": "geo:th:province:10",
                    "center": {"lat": 1.0, "lng": 100.5},
                    "bounds": {"south": 0.0, "west": 100.0, "north": 2.0, "east": 101.0},
                    "source_id": "nso-geographic-standard",
                }
            ]
            write_json(path, document)
            text = path.read_text(encoding="utf-8").replace('"lat": 1.0', '"lat": 1e999', 1)
            path.write_text(text, encoding="utf-8", newline="")
            refresh_input_digest(source_dir, "geometry.json")

        self.assert_rejected(mutate, "non-finite number")

    def test_unexpected_registry_field_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "registry.json"
            registry = read_json(path)
            registry["unexpected"] = "value"
            write_json(path, registry)

        self.assert_rejected(mutate, "fields are missing or unexpected")

    def test_non_boolean_scheme_flag_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "registry.json"
            registry = read_json(path)
            registry["classification_schemes"][0]["is_administrative_parent"] = 0
            write_json(path, registry)

        self.assert_rejected(mutate, "flag is not boolean")

    def test_bad_province_code_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "provinces.csv"
            rows = read_csv_rows(path)
            rows[1][0] = "09"
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "provinces.csv")

        self.assert_rejected(mutate, "province code set or ordering is invalid")

    def test_duplicate_province_slug_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "provinces.csv"
            rows = read_csv_rows(path)
            rows[2][1] = rows[1][1]
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "provinces.csv")

        self.assert_rejected(mutate, "duplicate province slug")

    def test_province_source_order_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "provinces.csv"
            rows = read_csv_rows(path)
            rows[1], rows[2] = rows[2], rows[1]
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "provinces.csv")

        self.assert_rejected(mutate, "province code set or ordering is invalid")

    def test_source_metadata_order_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "registry.json"
            registry = read_json(path)
            registry["sources"][0], registry["sources"][1] = (
                registry["sources"][1],
                registry["sources"][0],
            )
            write_json(path, registry)

        self.assert_rejected(mutate, "source metadata must be sorted")

    def test_missing_relation_target_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "relations.json"
            document = read_json(path)
            document["rules"][0]["object_id_template"] = "geo:th:country:missing"
            write_json(path, document)
            refresh_input_digest(source_dir, "relations.json")

        self.assert_rejected(mutate, "relation object does not exist")

    def test_relation_graph_cycle_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "relations.json"
            document = read_json(path)
            document["records"] = [
                {
                    "subject_id": "geo:th:country",
                    "type": "part_of",
                    "object_id": "geo:th:province:10",
                    "scheme_id": "thai-administrative",
                    "is_primary": False,
                    "valid_from": None,
                    "valid_to": None,
                    "source_id": "nso-geographic-standard",
                }
            ]
            write_json(path, document)
            set_input_sources(
                source_dir,
                "relations",
                ["nso-geographic-standard", "nso-yearbook-2025"],
            )
            refresh_input_digest(source_dir, "relations.json")

        self.assert_rejected(mutate, "graph contains a cycle")

    def test_reviewed_place_scope_and_external_namespaces(self) -> None:
        result = COMPILER.compile_registry(SOURCE)
        registry = result.registry
        islands = {
            entity_id: entity
            for entity_id, entity in registry["entities_by_id"].items()
            if entity["type"] == "island"
        }
        self.assertEqual(
            {
                "geo:th:island:ko-chang-trat",
                "geo:th:island:ko-lanta-yai",
                "geo:th:island:ko-pha-ngan",
                "geo:th:island:ko-samui",
                "geo:th:island:ko-tao",
                "geo:th:island:phuket",
            },
            set(islands),
        )
        for island in islands.values():
            self.assertEqual({}, island["external_ids"])

        external = registry["indexes"]["by_external_id"]
        self.assertEqual("geo:th:district:8404", external["moi_district_code"]["8404"])
        self.assertEqual("geo:th:subdistrict:840406", external["moi_subdistrict_code"]["840406"])
        self.assertNotIn("thai_land_island_id", external)

        phuket_alias = registry["indexes"]["by_alias"]["en"]["phuket island"]
        self.assertEqual(
            [
                ("geo:th:island:phuket", "active"),
                ("geo:th:province:83", "retired"),
            ],
            [(candidate["entity_id"], candidate["status"]) for candidate in phuket_alias],
        )

        public_places = {
            place["id"]: place
            for place in registry["public_payload"]["places"]
        }
        self.assertEqual(
            ["geo:th:province:84", "geo:th:district:8404"],
            public_places["geo:th:island:ko-samui"]["located_in_ids"],
        )
        self.assertEqual(
            "geo:th:district:8404",
            public_places["geo:th:subdistrict:840406"]["admin_parent_id"],
        )

    def test_missing_reviewed_place_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "places.csv"
            rows = read_csv_rows(path)
            rows.pop()
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "places.csv")

        self.assert_rejected(mutate, "reviewed place registry row count is invalid")

    def test_island_cannot_borrow_an_administrative_code(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "places.csv"
            rows = read_csv_rows(path)
            island = next(row for row in rows[1:] if row[0] == "geo:th:island:ko-samui")
            island[6] = "moi_district_code"
            island[7] = "8404"
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "places.csv")

        self.assert_rejected(mutate, "island must not borrow an administrative code")

    def test_unsorted_reviewed_places_are_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "places.csv"
            rows = read_csv_rows(path)
            rows[1], rows[2] = rows[2], rows[1]
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "places.csv")

        self.assert_rejected(mutate, "reviewed place IDs or ordering are invalid")

    def test_invalid_relation_scheme_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "relations.json"
            document = read_json(path)
            document["rules"][1]["scheme_id"] = "thai-administrative"
            write_json(path, document)
            refresh_input_digest(source_dir, "relations.json")

        self.assert_rejected(mutate, "classification uses an administrative scheme")

    def test_alias_ambiguity_without_shared_group_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "aliases.csv"
            rows = read_csv_rows(path)
            matching = [row for row in rows[1:] if row[2] == "Nakhon"]
            self.assertEqual(2, len(matching))
            matching[0][5] = ""
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "aliases.csv")

        self.assert_rejected(mutate, "ambiguous alias lacks an explicit ambiguity group")

    def test_invalid_coordinates_are_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "geometry.json"
            document = read_json(path)
            document["records"] = [
                {
                    "entity_id": "geo:th:province:10",
                    "center": {"lat": 91.0, "lng": 100.5},
                    "bounds": {"south": 10.0, "west": 100.0, "north": 20.0, "east": 101.0},
                    "source_id": "nso-geographic-standard",
                }
            ]
            write_json(path, document)
            set_input_sources(source_dir, "geometry", ["nso-geographic-standard"])
            refresh_input_digest(source_dir, "geometry.json")

        self.assert_rejected(mutate, "geometry center is out of range")

    def test_center_outside_bounds_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "geometry.json"
            document = read_json(path)
            document["records"] = [
                {
                    "entity_id": "geo:th:province:10",
                    "center": {"lat": 15.0, "lng": 102.0},
                    "bounds": {"south": 10.0, "west": 100.0, "north": 20.0, "east": 101.0},
                    "source_id": "nso-geographic-standard",
                }
            ]
            write_json(path, document)
            set_input_sources(source_dir, "geometry", ["nso-geographic-standard"])
            refresh_input_digest(source_dir, "geometry.json")

        self.assert_rejected(mutate, "center is outside longitude bounds")

    def test_region_swap_that_preserves_counts_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "provinces.csv"
            rows = read_csv_rows(path)
            by_code = {row[0]: row for row in rows[1:]}
            by_code["10"][5], by_code["83"][5] = by_code["83"][5], by_code["10"][5]
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "provinces.csv")

        self.assert_rejected(mutate, "province statistical region truth mismatch")

    def test_alias_source_must_be_declared_by_its_input(self) -> None:
        def mutate(source_dir: Path) -> None:
            set_input_sources(source_dir, "aliases", ["thai-land-editorial-names"])

        self.assert_rejected(mutate, "alias source is not declared for its input")

    def test_php_and_core_payloads_preserve_float_parity(self) -> None:
        temporary, source_dir = self.copied_source()
        try:
            geometry_path = source_dir / "geometry.json"
            geometry = read_json(geometry_path)
            geometry["records"] = [
                {
                    "entity_id": "geo:th:province:10",
                    "center": {"lat": 13.756331234567891, "lng": 100.50176234567891},
                    "bounds": {
                        "south": 13.0,
                        "west": 100.0,
                        "north": 14.0,
                        "east": 101.0,
                    },
                    "source_id": "nso-geographic-standard",
                }
            ]
            write_json(geometry_path, geometry)
            set_input_sources(source_dir, "geometry", ["nso-geographic-standard"])
            refresh_input_digest(source_dir, "geometry.json")
            result = COMPILER.compile_registry(source_dir)

            output_root = Path(temporary.name) / "output"
            COMPILER.write_or_check(result, output_root, False)
            php = shutil.which("php")
            self.assertIsNotNone(php, "PHP is required for generated payload parity")
            php_script = (
                "$r = require $argv[1]; "
                "echo json_encode($r['public_payload'], "
                "JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);"
            )
            process = subprocess.run(
                [str(php), "-r", php_script, str(output_root / "resources" / "geography" / "registry.php")],
                check=False,
                capture_output=True,
                text=True,
                encoding="utf-8",
            )
            self.assertEqual(0, process.returncode, process.stderr)
            self.assertEqual(
                read_json(output_root / "assets" / "geography" / "core.json"),
                json.loads(process.stdout),
            )
        finally:
            temporary.cleanup()

    def test_schema_drift_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "registry.schema.json"
            schema = read_json(path)
            schema["additionalProperties"] = True
            write_json(path, schema)

        self.assert_rejected(mutate, "schema bytes do not match the reviewed contract")

    def test_arbitrary_geography_type_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            registry_path = source_dir / "registry.json"
            registry = read_json(registry_path)
            registry["geography_types"].append("hotel")
            registry["geography_types"].sort()
            write_json(registry_path, registry)

            regions_path = source_dir / "regions.json"
            regions = read_json(regions_path)
            regions["editorial_entity_types"].append("hotel")
            write_json(regions_path, regions)
            refresh_input_digest(source_dir, "regions.json")

        self.assert_rejected(mutate, "geography types do not match the reviewed allowlist")

    def test_malformed_geography_type_is_a_controlled_rejection(self) -> None:
        def mutate(source_dir: Path) -> None:
            registry_path = source_dir / "registry.json"
            registry = read_json(registry_path)
            registry["geography_types"].append(["hotel"])
            write_json(registry_path, registry)

        self.assert_rejected(mutate, "must be a string")

    def test_unrelated_alias_context_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "aliases.csv"
            rows = read_csv_rows(path)
            rows[1][3] = "geo:th:province:83"
            write_csv_rows(path, rows)
            refresh_input_digest(source_dir, "aliases.csv")

        self.assert_rejected(mutate, "alias context is not the canonical administrative context")

    def test_unsorted_geometry_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "geometry.json"
            document = read_json(path)
            document["records"] = [
                {
                    "entity_id": entity_id,
                    "center": {"lat": 13.5, "lng": 100.5},
                    "bounds": {"south": 13.0, "west": 100.0, "north": 14.0, "east": 101.0},
                    "source_id": "nso-geographic-standard",
                }
                for entity_id in ("geo:th:province:11", "geo:th:province:10")
            ]
            write_json(path, document)
            set_input_sources(source_dir, "geometry", ["nso-geographic-standard"])
            refresh_input_digest(source_dir, "geometry.json")

        self.assert_rejected(mutate, "geometry records must be sorted by entity ID")

    def test_region_order_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "regions.json"
            document = read_json(path)
            regions = document["region_model"]["regions"]
            regions[0], regions[1] = regions[1], regions[0]
            write_json(path, document)
            refresh_input_digest(source_dir, "regions.json")

        self.assert_rejected(mutate, "region IDs or ordering do not match the reviewed contract")

    def test_reversed_relation_interval_is_rejected(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "relations.json"
            document = read_json(path)
            document["rules"][0]["valid_from"] = "2026-12-31"
            document["rules"][0]["valid_to"] = "2026-01-01"
            write_json(path, document)
            refresh_input_digest(source_dir, "relations.json")

        self.assert_rejected(mutate, "date interval is reversed")

    def test_dataset_version_requires_a_real_calendar_date(self) -> None:
        def mutate(source_dir: Path) -> None:
            path = source_dir / "registry.json"
            registry = read_json(path)
            registry["dataset_version"] = "2026.99.99.1"
            write_json(path, registry)

        self.assert_rejected(mutate, "does not contain a real calendar date")

    def test_reviewed_thai_alias_indexes_nfkc_and_no_intl_forms(self) -> None:
        temporary, source_dir = self.copied_source()
        try:
            baseline = COMPILER.compile_registry(source_dir)
            selected = None
            for entity in baseline.registry["entities_by_id"].values():
                thai_name = entity["names"]["th"]
                if COMPILER.normalize_alias(thai_name) != COMPILER.normalize_alias_without_intl(thai_name):
                    canonical_candidates = baseline.registry["indexes"]["by_alias"]["th"][
                        COMPILER.normalize_alias(thai_name)
                    ]
                    canonical = next(
                        candidate
                        for candidate in canonical_candidates
                        if candidate["entity_id"] == entity["id"] and candidate["status"] == "active"
                    )
                    selected = (entity["id"], thai_name, canonical["context_id"])
                    break
            self.assertIsNotNone(selected)
            entity_id, thai_alias, context_id = selected

            path = source_dir / "aliases.csv"
            rows = read_csv_rows(path)
            rows.append(
                [
                    entity_id,
                    "th",
                    thai_alias,
                    context_id or "",
                    "active",
                    "",
                    "thai-land-editorial-names",
                ]
            )
            write_csv_rows(path, sort_alias_rows(rows))
            refresh_input_digest(source_dir, "aliases.csv")
            result = COMPILER.compile_registry(source_dir)
            aliases = result.registry["indexes"]["by_alias"]["th"]
            self.assertIn(COMPILER.normalize_alias(thai_alias), aliases)
            self.assertIn(COMPILER.normalize_alias_without_intl(thai_alias), aliases)
        finally:
            temporary.cleanup()


if __name__ == "__main__":
    unittest.main(verbosity=2)
