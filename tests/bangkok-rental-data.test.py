#!/usr/bin/env python3
"""Adversarial contract tests for the Bangkok rental-area compiler."""

from __future__ import annotations

import copy
import importlib.util
import json
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from typing import Any, Callable


ROOT = Path(__file__).resolve().parents[1]
SOURCE_PATH = ROOT / "data" / "content" / "bangkok-rental-areas.json"
SCHEMA_PATH = ROOT / "data" / "content" / "bangkok-rental-areas.schema.json"
BUILDER_PATH = ROOT / "scripts" / "build_bangkok_rental_registry.py"
OUTPUT_PATH = ROOT / "resources" / "content" / "bangkok-rental-areas.php"

SPEC = importlib.util.spec_from_file_location(
    "build_bangkok_rental_registry", BUILDER_PATH
)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("Cannot load Bangkok rental registry compiler")
COMPILER = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = COMPILER
SPEC.loader.exec_module(COMPILER)


def read_json(path: Path) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as handle:
        value = json.load(handle)
    if not isinstance(value, dict):
        raise AssertionError(f"JSON root is not an object: {path}")
    return value


def write_json(path: Path, value: dict[str, Any]) -> None:
    payload = json.dumps(value, ensure_ascii=False, indent=2, allow_nan=False) + "\n"
    path.write_text(payload, encoding="utf-8", newline="")


def record_by_id(
    source: dict[str, Any], collection: str, field: str, value: str
) -> dict[str, Any]:
    matches = [item for item in source[collection] if item[field] == value]
    if len(matches) != 1:
        raise AssertionError(f"Expected one {collection} record for {value}")
    return matches[0]


class BangkokRentalDataTest(unittest.TestCase):
    maxDiff = None

    def copied_contract(
        self,
    ) -> tuple[tempfile.TemporaryDirectory[str], Path, Path, Path]:
        temporary = tempfile.TemporaryDirectory()
        root = Path(temporary.name)
        source = root / "bangkok-rental-areas.json"
        schema = root / "bangkok-rental-areas.schema.json"
        output = root / "bangkok-rental-areas.php"
        shutil.copyfile(SOURCE_PATH, source)
        shutil.copyfile(SCHEMA_PATH, schema)
        shutil.copyfile(OUTPUT_PATH, output)
        return temporary, source, schema, output

    def assert_source_rejected(
        self,
        mutation: Callable[[dict[str, Any]], None],
        message_pattern: str,
    ) -> None:
        temporary, source_path, schema_path, _ = self.copied_contract()
        try:
            source = read_json(source_path)
            mutation(source)
            write_json(source_path, source)
            with self.assertRaisesRegex(COMPILER.RegistryError, message_pattern):
                COMPILER.compile_registry(source_path, schema_path)
        finally:
            temporary.cleanup()

    def test_exact_contract_counts_indexes_and_parent_binding(self) -> None:
        result = COMPILER.compile_registry(SOURCE_PATH, SCHEMA_PATH)
        registry = result.registry
        self.assertEqual(1, registry["schema_version"])
        self.assertEqual("bangkok-rental-areas-v1", registry["contract_id"])
        self.assertEqual("2026-08-10", registry["checked_on"])
        self.assertEqual("bangkok-apartment-rental", registry["site"]["parent_route_id"])
        self.assertEqual(
            "/מדריך-להשכרת-דירה-בבנגקוק/",
            registry["site"]["parent_path"],
        )
        self.assertEqual(
            "איפה כדאי לגור בבנגקוק לפי תקציב וסגנון חיים",
            registry["public_labels"]["area_comparison_heading"],
        )
        self.assertEqual(14, len(registry["sources_by_id"]))
        self.assertEqual(7, len(registry["facts_by_id"]))
        self.assertEqual(50, len(registry["districts_by_id"]))
        self.assertEqual(19, len(registry["stations_by_id"]))
        self.assertEqual(5, len(registry["corridors_by_id"]))
        self.assertEqual(10, len(registry["areas_by_id"]))
        self.assertEqual(
            {f"{code:04d}" for code in range(1001, 1051)},
            set(registry["district_id_by_bma_code"]),
        )
        self.assertEqual(19, len(registry["station_id_by_code"]))
        self.assertEqual(10, len(registry["area_order"]))
        self.assertEqual(
            set(registry["area_order"]),
            {
                area_id
                for area_ids in registry["area_ids_by_corridor_id"].values()
                for area_id in area_ids
            },
        )
        self.assertEqual(COMPILER.sha256_lf(SOURCE_PATH), registry["source_sha256"])
        self.assertEqual(COMPILER.sha256_lf(SCHEMA_PATH), registry["schema_sha256"])

    def test_alias_and_operational_indexes_are_deterministic(self) -> None:
        registry = COMPILER.compile_registry(SOURCE_PATH, SCHEMA_PATH).registry
        self.assertEqual(
            "market:bangkok:on-nut",
            registry["area_id_by_alias"]["en"]["on nut"],
        )
        self.assertEqual(
            "market:bangkok:asok",
            registry["area_id_by_alias"]["en"]["asoke"],
        )
        self.assertEqual(
            "transit:bts:n5",
            registry["station_id_by_alias"]["en"]["ari"],
        )
        self.assertEqual(
            "geo:th:bma:1001",
            registry["district_id_by_bma_code"]["1001"],
        )
        for line_id, station_ids in registry["station_ids_by_line_id"].items():
            self.assertEqual(sorted(station_ids), station_ids, line_id)
            for station_id in station_ids:
                self.assertEqual(
                    line_id,
                    registry["stations_by_id"][station_id]["line_id"],
                )

    def test_artifact_is_deterministic_current_and_valid_php(self) -> None:
        first = COMPILER.compile_registry(SOURCE_PATH, SCHEMA_PATH)
        second = COMPILER.compile_registry(SOURCE_PATH, SCHEMA_PATH)
        self.assertEqual(first.registry, second.registry)
        self.assertEqual(first.artifact, second.artifact)
        self.assertEqual(first.artifact, OUTPUT_PATH.read_bytes())

        checked = subprocess.run(
            [sys.executable, str(BUILDER_PATH), "--check"],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=False,
        )
        self.assertEqual(0, checked.returncode, checked.stdout + checked.stderr)
        self.assertIn("PASS: Bangkok rental registry is current", checked.stdout)

        php = shutil.which("php")
        if php is None:
            self.skipTest("PHP CLI is unavailable")
        linted = subprocess.run(
            [php, "-l", str(OUTPUT_PATH)],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=False,
        )
        self.assertEqual(0, linted.returncode, linted.stdout + linted.stderr)
        runtime_code = (
            "define('ABSPATH', '/'); "
            f"$registry = require '{OUTPUT_PATH.as_posix()}'; "
            "if (!is_array($registry) || count($registry['areas_by_id']) !== 10) { exit(1); } "
            "echo $registry['contract_id'];"
        )
        executed = subprocess.run(
            [php, "-r", runtime_code],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=False,
        )
        self.assertEqual(0, executed.returncode, executed.stdout + executed.stderr)
        self.assertEqual("bangkok-rental-areas-v1", executed.stdout)

    def test_check_mode_rejects_stale_output(self) -> None:
        temporary, source_path, schema_path, output_path = self.copied_contract()
        try:
            source = read_json(source_path)
            source["public_labels"]["fit_heading"] += " נוסף"
            write_json(source_path, source)
            checked = subprocess.run(
                [
                    sys.executable,
                    str(BUILDER_PATH),
                    "--source",
                    str(source_path),
                    "--schema",
                    str(schema_path),
                    "--output",
                    str(output_path),
                    "--check",
                ],
                cwd=ROOT,
                text=True,
                capture_output=True,
                check=False,
            )
            self.assertNotEqual(0, checked.returncode)
            self.assertIn("compiled registry is stale", checked.stderr)
        finally:
            temporary.cleanup()

    def test_strict_json_rejects_duplicate_keys_and_non_finite_numbers(self) -> None:
        temporary, source_path, schema_path, _ = self.copied_contract()
        try:
            original = source_path.read_text(encoding="utf-8")
            source_path.write_text(
                original.replace("{", '{\n  "schema_version": 1,', 1),
                encoding="utf-8",
            )
            with self.assertRaisesRegex(COMPILER.RegistryError, "duplicate JSON key"):
                COMPILER.compile_registry(source_path, schema_path)

            source_path.write_text(
                original.replace('"lat": 13.7367', '"lat": NaN', 1),
                encoding="utf-8",
            )
            with self.assertRaisesRegex(COMPILER.RegistryError, "non-finite JSON"):
                COMPILER.compile_registry(source_path, schema_path)
        finally:
            temporary.cleanup()

    def test_schema_drift_and_shape_fail_closed(self) -> None:
        temporary, source_path, schema_path, _ = self.copied_contract()
        try:
            schema = read_json(schema_path)
            schema["description"] += " changed"
            write_json(schema_path, schema)
            with self.assertRaisesRegex(COMPILER.RegistryError, "schema drift"):
                COMPILER.compile_registry(source_path, schema_path)
        finally:
            temporary.cleanup()

        def extra_property(source: dict[str, Any]) -> None:
            source["unexpected"] = True

        def invalid_date(source: dict[str, Any]) -> None:
            source["checked_on"] = "2026-02-30"

        with self.subTest("additional property"):
            self.assert_source_rejected(extra_property, "schema validation failed")
        with self.subTest("date format"):
            self.assert_source_rejected(invalid_date, "schema validation failed")

    def test_exact_bma_code_set_and_global_ids_fail_closed(self) -> None:
        def invalid_code_set(source: dict[str, Any]) -> None:
            source["official_districts"][-1]["bma_code"] = "1000"

        def duplicate_global_id(source: dict[str, Any]) -> None:
            source["stations"][0]["station_id"] = source["official_districts"][0][
                "district_id"
            ]

        with self.subTest("BMA code set"):
            self.assert_source_rejected(invalid_code_set, "BMA district code set mismatch")
        with self.subTest("global ID"):
            self.assert_source_rejected(duplicate_global_id, "globally unique")

    def test_alias_and_public_identity_collisions_fail_closed(self) -> None:
        def ambiguous_alias(source: dict[str, Any]) -> None:
            source["featured_areas"][1]["aliases"]["en"].append(
                source["featured_areas"][0]["names"]["en"]
            )

        def duplicate_english_name(source: dict[str, Any]) -> None:
            source["featured_areas"][1]["names"]["en"] = source[
                "featured_areas"
            ][0]["names"]["en"]

        def duplicate_hebrew_summary(source: dict[str, Any]) -> None:
            source["featured_areas"][1]["public_copy"]["summary"] = source[
                "featured_areas"
            ][0]["public_copy"]["summary"]

        with self.subTest("alias"):
            self.assert_source_rejected(ambiguous_alias, "ambiguous market-area alias")
        with self.subTest("English name"):
            self.assert_source_rejected(
                duplicate_english_name,
                "ambiguous market-area alias|English names must be unique",
            )
        with self.subTest("Hebrew summary"):
            self.assert_source_rejected(
                duplicate_hebrew_summary, "Hebrew summaries must be unique"
            )

    def test_source_district_station_and_corridor_references_fail_closed(self) -> None:
        def unknown_source(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["monthly_asking_bands"]["source_ids"][
                0
            ] = "source:missing:evidence"

        def unknown_district(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["official_district_ids"][0] = (
                "geo:th:bma:9999"
            )

        def unknown_station(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["station_ids"][0] = "transit:bts:e99"

        def unknown_corridor(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["corridor_id"] = (
                "corridor:bangkok:missing"
            )

        for label, mutation, pattern in (
            ("source", unknown_source, "unknown source"),
            ("district", unknown_district, "unknown district reference"),
            ("station", unknown_station, "unknown station reference|corridor station"),
            ("corridor", unknown_corridor, "unknown corridor reference"),
        ):
            with self.subTest(label):
                self.assert_source_rejected(mutation, pattern)

    def test_corridor_area_and_station_membership_fail_closed(self) -> None:
        def missing_area(source: dict[str, Any]) -> None:
            source["corridors"][0]["area_ids"].pop()

        def duplicate_area_membership(source: dict[str, Any]) -> None:
            source["corridors"][1]["area_ids"].append(
                source["corridors"][0]["area_ids"][0]
            )

        def incomplete_station_membership(source: dict[str, Any]) -> None:
            source["corridors"][0]["station_ids"].pop()

        with self.subTest("missing area"):
            self.assert_source_rejected(
                missing_area, "corridor station membership mismatch|cover every featured area"
            )
        with self.subTest("duplicate area"):
            self.assert_source_rejected(
                duplicate_area_membership, "multiple corridors|station membership mismatch"
            )
        with self.subTest("station coverage"):
            self.assert_source_rejected(
                incomplete_station_membership, "corridor station membership mismatch"
            )

    def test_bangkok_coordinate_bounds_fail_closed(self) -> None:
        def invalid_area_latitude(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["coordinates"]["lat"] = 14.2

        def invalid_station_longitude(source: dict[str, Any]) -> None:
            source["stations"][0]["coordinates"]["lng"] = 100.1

        with self.subTest("area latitude"):
            self.assert_source_rejected(invalid_area_latitude, "outside Bangkok bounds")
        with self.subTest("station longitude"):
            self.assert_source_rejected(invalid_station_longitude, "outside Bangkok bounds")

    def test_rent_band_order_rounding_hierarchy_and_evidence_fail_closed(self) -> None:
        def reversed_band(source: dict[str, Any]) -> None:
            band = source["featured_areas"][0]["monthly_asking_bands"]["one_bedroom"]
            band["min_thb"] = band["max_thb"]

        def unrounded_band(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["monthly_asking_bands"]["one_bedroom"][
                "min_thb"
            ] += 500

        def inverted_bedroom_hierarchy(source: dict[str, Any]) -> None:
            asking = source["featured_areas"][0]["monthly_asking_bands"]
            asking["two_bedroom"]["min_thb"] = asking["one_bedroom"]["min_thb"]

        def pricing_date_drift(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["monthly_asking_bands"]["checked_on"] = (
                "2026-08-09"
            )

        def no_market_evidence(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["monthly_asking_bands"]["source_ids"] = [
                "source:bma:districts"
            ]

        for label, mutation, pattern in (
            ("order", reversed_band, "rent band order"),
            ("rounding", unrounded_band, "unrounded rent band"),
            ("bedrooms", inverted_bedroom_hierarchy, "two-bedroom band"),
            ("date", pricing_date_drift, "pricing date mismatch"),
            ("market evidence", no_market_evidence, "lacks market-listing evidence"),
        ):
            with self.subTest(label):
                self.assert_source_rejected(mutation, pattern)

    def test_date_order_and_official_legal_evidence_fail_closed(self) -> None:
        def source_after_contract(source: dict[str, Any]) -> None:
            source["source_catalog"][0]["checked_on"] = "2026-08-11"

        def effective_after_check(source: dict[str, Any]) -> None:
            fact = next(
                item for item in source["current_facts"] if item["effective_on"]
            )
            fact["effective_on"] = "2026-08-11"

        def legal_fact_market_only(source: dict[str, Any]) -> None:
            source["current_facts"][0]["source_ids"] = [
                "source:propertyscout:bangkok-condos"
            ]

        def legal_source_not_official_domain(source: dict[str, Any]) -> None:
            source_record = record_by_id(
                source,
                "source_catalog",
                "source_id",
                "source:immigration:tm30",
            )
            source_record["url"] = "https://example.com/tm30"

        for label, mutation, pattern in (
            ("source date", source_after_contract, "later than contract date"),
            ("effective date", effective_after_check, "later than its check date"),
            ("legal kind", legal_fact_market_only, "lacks official government evidence"),
            ("legal domain", legal_source_not_official_domain, "lacks official government evidence"),
        ):
            with self.subTest(label):
                self.assert_source_rejected(mutation, pattern)

    def test_persona_vocabulary_public_language_and_typography_fail_closed(self) -> None:
        def invalid_persona(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["persona_tags"][0] = "invented-tag"

        def presentation_phrase(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["public_copy"]["summary"] += (
                " כל מה שצריך לדעת במקום אחד"
            )

        def internal_phrase(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["public_copy"]["action_label"] = (
                "placeholder"
            )

        def forbidden_dash(source: dict[str, Any]) -> None:
            source["featured_areas"][0]["fit_summary"] += chr(0x2014)

        for label, mutation, pattern in (
            ("persona", invalid_persona, "schema validation failed|unknown persona"),
            ("presentation", presentation_phrase, "forbidden presentation phrase"),
            ("internal", internal_phrase, "forbidden internal phrase"),
            ("dash", forbidden_dash, "forbidden em dash"),
        ):
            with self.subTest(label):
                self.assert_source_rejected(mutation, pattern)

    def test_contract_files_exclude_forbidden_long_dashes(self) -> None:
        paths = (
            SOURCE_PATH,
            SCHEMA_PATH,
            BUILDER_PATH,
            OUTPUT_PATH,
            Path(__file__),
        )
        for path in paths:
            text = path.read_text(encoding="utf-8")
            self.assertNotIn(chr(0x2013), text, str(path))
            self.assertNotIn(chr(0x2014), text, str(path))


if __name__ == "__main__":
    unittest.main(verbosity=2)
