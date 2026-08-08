#!/usr/bin/env python3
"""Adversarial tests for the real-estate content contract compiler."""

from __future__ import annotations

import copy
import importlib.util
import json
import re
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from typing import Any, Callable


ROOT = Path(__file__).resolve().parents[1]
SOURCE_PATH = ROOT / "data" / "content" / "real-estate.json"
SCHEMA_PATH = ROOT / "data" / "content" / "real-estate.schema.json"
BUILDER_PATH = ROOT / "scripts" / "build_content_registry.py"
OUTPUT_PATH = ROOT / "resources" / "content" / "real-estate.php"

SPEC = importlib.util.spec_from_file_location("build_content_registry", BUILDER_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("Cannot load content registry compiler")
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


def route_by_id(source: dict[str, Any], route_id: str) -> dict[str, Any]:
    matches = [route for route in source["routes"] if route["route_id"] == route_id]
    if len(matches) != 1:
        raise AssertionError(f"Expected one route named {route_id}")
    return matches[0]


class RealEstateContentTest(unittest.TestCase):
    maxDiff = None

    def copied_contract(
        self,
    ) -> tuple[tempfile.TemporaryDirectory[str], Path, Path, Path]:
        temporary = tempfile.TemporaryDirectory()
        root = Path(temporary.name)
        source = root / "real-estate.json"
        schema = root / "real-estate.schema.json"
        output = root / "real-estate.php"
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

    def test_exact_runtime_contract_and_body_preservation(self) -> None:
        result = COMPILER.compile_registry(SOURCE_PATH, SCHEMA_PATH)
        registry = result.registry
        self.assertEqual(1, registry["schema_version"])
        self.assertEqual("thailand-real-estate-v1", registry["contract_id"])
        self.assertEqual("thailand-real-estate", registry["hub_route_id"])
        self.assertEqual(8, len(registry["routes_by_id"]))
        self.assertEqual(
            {
                "841": "thailand-real-estate",
                "65": "thailand-property-financing",
                "69": "thailand-property-buying-mistakes",
                "118": "bangkok-apartment-rental",
                "336": "buy-property-thailand",
                "474": "foreign-condo-ownership-thailand",
                "609": "thailand-property-management",
                "810": "thailand-property-prices",
            },
            registry["route_id_by_post_id"],
        )
        self.assertEqual(
            {
                route_id: binding[1]
                for route_id, binding in COMPILER.EXPECTED_BINDINGS.items()
            },
            {
                route_id: route["path"]
                for route_id, route in registry["routes_by_id"].items()
            },
        )
        self.assertEqual(
            {
                "mode": "preserve_wordpress_body",
                "mutation_allowed": False,
                "source_field": "post_content",
                "wordpress_filter": "the_content",
                "public_punctuation_policy": "replace_long_dashes_with_hyphen",
                "prefix_components": ["breadcrumb", "route_intro"],
                "suffix_components": ["continuations", "freshness", "sources"],
            },
            registry["body_contract"],
        )
        for route in registry["routes_by_id"].values():
            self.assertEqual("preserve", route["wordpress"]["body_mode"])

    def test_public_copy_is_unique_keyword_led_and_render_ready(self) -> None:
        source = read_json(SOURCE_PATH)
        meta_descriptions: set[str] = set()
        ownership_terms: set[str] = set()
        for route in source["routes"]:
            primary = COMPILER.normalize_term(route["ownership"]["primary_keyword"])
            self.assertTrue(
                COMPILER.normalize_term(route["public"]["h1"]).startswith(primary)
            )
            self.assertTrue(
                COMPILER.normalize_term(route["public"]["seo_title"]).startswith(
                    primary
                )
            )
            meta = COMPILER.normalize_term(route["public"]["meta_description"])
            self.assertNotIn(meta, meta_descriptions)
            meta_descriptions.add(meta)
            for term in [
                route["ownership"]["primary_keyword"],
                *route["ownership"]["synonyms"],
            ]:
                normalized = COMPILER.normalize_term(term)
                self.assertNotIn(normalized, ownership_terms)
                ownership_terms.add(normalized)

        self.assertEqual(8, len(meta_descriptions))
        self.assertEqual("פרטים שמשתנים", source["public_labels"]["freshness_heading"])
        self.assertEqual("מקורות שימושיים", source["public_labels"]["sources_heading"])
        self.assertGreaterEqual(len(source["freshness_catalog"]), 3)
        self.assertGreaterEqual(len(source["source_catalog"]), 6)

    def test_public_copy_avoids_shared_presentation_language(self) -> None:
        run_source = (ROOT / "tests" / "run.php").read_text(encoding="utf-8")
        start = run_source.index("$presentation_phrases = array(")
        end = run_source.index(");", start)
        forbidden = re.findall(r"'([^']*)'", run_source[start:end])
        self.assertEqual(50, len(forbidden))

        public_sources = [
            SOURCE_PATH.read_text(encoding="utf-8"),
            *(path.read_text(encoding="utf-8") for path in (ROOT / "src" / "Content").glob("*.php")),
            *(path.read_text(encoding="utf-8") for path in (ROOT / "templates").rglob("*.php")),
            (ROOT / "assets" / "content" / "content.js").read_text(encoding="utf-8"),
        ]
        public_payload = "\n".join(public_sources)
        for phrase in forbidden:
            self.assertNotIn(phrase, public_payload, phrase)

    def test_hub_covers_every_spoke_with_sections_cards_and_decisions(self) -> None:
        source = read_json(SOURCE_PATH)
        hub_id = source["hub_route_id"]
        spoke_ids = {
            route["route_id"] for route in source["routes"] if route["kind"] == "spoke"
        }
        experience = source["hub_experience"]
        section_targets = {
            route_id
            for section in experience["sections"]
            for route_id in section["route_ids"]
        }
        card_targets = {card["route_id"] for card in experience["cards"]}
        decision_targets = {
            choice["target_route_id"]
            for decision in experience["decision_paths"]
            for choice in decision["choices"]
        }
        self.assertEqual(spoke_ids, section_targets)
        self.assertEqual(spoke_ids, card_targets)
        self.assertEqual(spoke_ids, decision_targets)

        hub = route_by_id(source, hub_id)
        self.assertEqual(
            spoke_ids,
            {link["target_route_id"] for link in hub["continuations"]},
        )
        for route in source["routes"]:
            if route["kind"] != "spoke":
                continue
            self.assertEqual(hub_id, route["parent_route_id"])
            self.assertEqual(hub_id, route["parent_link"]["target_route_id"])
            self.assertGreaterEqual(len(route["continuations"]), 2)

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
        self.assertEqual(0, checked.returncode, checked.stderr)
        self.assertIn("PASS: content registry is current", checked.stdout)

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
        self.assertEqual(0, linted.returncode, linted.stderr)
        runtime_code = (
            "define('ABSPATH', '/'); "
            f"$registry = require '{OUTPUT_PATH.as_posix()}'; "
            "if (!is_array($registry) || count($registry['routes_by_id']) !== 8) { exit(1); } "
            "echo $registry['contract_id'];"
        )
        executed = subprocess.run(
            [php, "-r", runtime_code],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=False,
        )
        self.assertEqual(0, executed.returncode, executed.stderr)
        self.assertEqual("thailand-real-estate-v1", executed.stdout)

    def test_id_and_path_mismatches_fail_closed(self) -> None:
        def wrong_id(source: dict[str, Any]) -> None:
            route_by_id(source, "thailand-property-financing")["wordpress"][
                "post_id"
            ] = 66

        def wrong_path(source: dict[str, Any]) -> None:
            route_by_id(source, "buy-property-thailand")["path"] = "/new-path/"

        with self.subTest("post ID"):
            self.assert_source_rejected(wrong_id, "ID/path mismatch")
        with self.subTest("route path"):
            self.assert_source_rejected(wrong_path, "ID/path mismatch")

    def test_invalid_link_target_fails_closed(self) -> None:
        def mutate(source: dict[str, Any]) -> None:
            route_by_id(source, "thailand-property-financing")["continuations"][0][
                "target_route_id"
            ] = "missing-route"

        self.assert_source_rejected(mutate, "invalid link target")

    def test_duplicate_primary_or_synonym_ownership_fails_closed(self) -> None:
        def mutate(source: dict[str, Any]) -> None:
            primary = route_by_id(source, "thailand-real-estate")["ownership"][
                "primary_keyword"
            ]
            route_by_id(source, "thailand-property-financing")["ownership"][
                "synonyms"
            ][0] = primary

        self.assert_source_rejected(mutate, "duplicate keyword/synonym ownership")

    def test_missing_parent_or_continuations_fail_closed(self) -> None:
        def missing_parent(source: dict[str, Any]) -> None:
            route_by_id(source, "bangkok-apartment-rental")["parent_route_id"] = None

        def missing_continuations(source: dict[str, Any]) -> None:
            route_by_id(source, "thailand-property-prices")["continuations"] = []

        with self.subTest("parent"):
            self.assert_source_rejected(missing_parent, "missing hub parent")
        with self.subTest("continuations"):
            self.assert_source_rejected(
                missing_continuations,
                "schema validation failed|missing continuations",
            )

    def test_duplicate_routes_fail_closed(self) -> None:
        def mutate(source: dict[str, Any]) -> None:
            source["routes"][-1] = copy.deepcopy(source["routes"][0])

        self.assert_source_rejected(mutate, "duplicate route ID")

    def test_nonreciprocal_contextual_link_fails_closed(self) -> None:
        def mutate(source: dict[str, Any]) -> None:
            financing = route_by_id(source, "thailand-property-financing")
            financing["continuations"][0][
                "target_route_id"
            ] = "thailand-property-buying-mistakes"

        self.assert_source_rejected(mutate, "contextual link is not reciprocal")

    def test_schema_drift_fails_closed(self) -> None:
        temporary, source_path, schema_path, _ = self.copied_contract()
        try:
            schema = read_json(schema_path)
            schema["description"] = "Unexpected schema change"
            write_json(schema_path, schema)
            with self.assertRaisesRegex(COMPILER.RegistryError, "schema drift"):
                COMPILER.compile_registry(source_path, schema_path)
        finally:
            temporary.cleanup()

    def test_check_mode_rejects_stale_output(self) -> None:
        temporary, source_path, schema_path, output_path = self.copied_contract()
        try:
            source = read_json(source_path)
            route_by_id(source, "thailand-property-prices")["public"][
                "summary"
            ] += " הנתונים מסודרים לפי החלטת המשתמש."
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

    def test_contract_files_exclude_forbidden_dash_characters(self) -> None:
        paths = [
            SOURCE_PATH,
            SCHEMA_PATH,
            BUILDER_PATH,
            OUTPUT_PATH,
            Path(__file__),
        ]
        forbidden = [chr(0x2013), chr(0x2014)]
        for path in paths:
            text = path.read_text(encoding="utf-8")
            for character in forbidden:
                self.assertNotIn(character, text, str(path))


if __name__ == "__main__":
    unittest.main(verbosity=2)
