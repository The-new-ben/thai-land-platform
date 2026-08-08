#!/usr/bin/env python3
"""Tests for the generated SEO runtime migration-gate contract."""

from __future__ import annotations

import hashlib
import json
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
REGISTRY_PATH = ROOT / "data" / "seo" / "ownership-registry.json"
BUILDER_PATH = ROOT / "scripts" / "build_seo_runtime.py"
OUTPUT_PATH = ROOT / "resources" / "seo" / "migration-gates.php"
LIVE_SCRIPT_PATH = ROOT / "scripts" / "live_seo_migration_acceptance.cjs"


def load_registry(path: Path = REGISTRY_PATH) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def expected_contract() -> dict[str, Any]:
    registry = load_registry()
    owners = {owner["owner_id"]: owner for owner in registry["intent_owners"]}
    gates: dict[str, dict[str, Any]] = {}
    for route in registry["routes"]:
        assignment = route["assignment"]
        if assignment["kind"] != "migration_gate":
            continue
        expected_redirect_target = (
            route["redirect_target"]
            if route["indexing_policy"] == "redirect"
            else None
        )
        if route["indexing_policy"] == "redirect":
            assert expected_redirect_target == owners[
                assignment["current_owner_id"]
            ]["canonical_url"]
        gates[route["url"]] = {
            "route_id": route["route_id"],
            "current_owner_id": assignment["current_owner_id"],
            "candidate": assignment["candidate_owner_id"],
            "state": assignment["state"],
            "release_blocked": assignment["release_blocked"],
            "indexing_policy": route["indexing_policy"],
            "expected_redirect_target": expected_redirect_target,
        }
    return {
        "contract_version": "1.0.0",
        "source": {
            "registry_version": registry["registry_version"],
            "sha256": hashlib.sha256(REGISTRY_PATH.read_bytes()).hexdigest(),
        },
        "migration_gates": dict(sorted(gates.items())),
    }


def load_php_contract(path: Path = OUTPUT_PATH) -> dict[str, Any]:
    php = shutil.which("php")
    if php is None:
        raise AssertionError("PHP CLI is required to validate the runtime contract")
    program = (
        "$contract = require $argv[1]; "
        "$json = json_encode($contract, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); "
        "if ($json === false) { fwrite(STDERR, json_last_error_msg()); exit(2); } "
        "fwrite(STDOUT, $json);"
    )
    result = subprocess.run(
        [php, "-r", program, str(path)],
        cwd=ROOT,
        check=False,
        capture_output=True,
        text=True,
        encoding="utf-8",
    )
    if result.returncode != 0:
        raise AssertionError(result.stderr or result.stdout)
    return json.loads(result.stdout)


def run_builder(*arguments: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(BUILDER_PATH), *arguments],
        cwd=ROOT,
        check=False,
        capture_output=True,
        text=True,
        encoding="utf-8",
    )


class SeoRuntimeMigrationGateTests(unittest.TestCase):
    maxDiff = None

    def test_checked_in_runtime_contract_is_current(self) -> None:
        result = run_builder("--check")
        self.assertEqual(0, result.returncode, result.stderr or result.stdout)

    def test_generated_php_is_valid_and_matches_registry(self) -> None:
        php = shutil.which("php")
        self.assertIsNotNone(php, "PHP CLI is required")
        lint = subprocess.run(
            [php, "-l", str(OUTPUT_PATH)],
            cwd=ROOT,
            check=False,
            capture_output=True,
            text=True,
            encoding="utf-8",
        )
        self.assertEqual(0, lint.returncode, lint.stderr or lint.stdout)
        self.assertEqual(expected_contract(), load_php_contract())

    def test_contract_contains_only_the_two_declared_migration_gates(self) -> None:
        contract = load_php_contract()
        gates = contract["migration_gates"]
        self.assertEqual(2, len(gates))
        self.assertEqual(
            {
                "route-cheap-flight-tips-legacy",
                "route-business-short-redirect",
            },
            {gate["route_id"] for gate in gates.values()},
        )
        self.assertTrue(all(gate["release_blocked"] is True for gate in gates.values()))

    def test_index_gate_is_self_canonical_and_redirect_gate_targets_owner(self) -> None:
        registry = load_registry()
        owners = {owner["owner_id"]: owner for owner in registry["intent_owners"]}
        routes = {
            route["route_id"]: route
            for route in registry["routes"]
            if route["assignment"]["kind"] == "migration_gate"
        }
        gates = {
            gate["route_id"]: (path, gate)
            for path, gate in load_php_contract()["migration_gates"].items()
        }

        cheap_path, cheap = gates["route-cheap-flight-tips-legacy"]
        cheap_source = routes[cheap["route_id"]]
        self.assertEqual("index", cheap["indexing_policy"])
        self.assertIsNone(cheap["expected_redirect_target"])
        self.assertEqual(
            owners[cheap["current_owner_id"]]["canonical_url"], cheap_path
        )
        self.assertEqual("thailand-flights", cheap["candidate"])
        self.assertEqual("evidence_pending", cheap["state"])
        self.assertEqual(cheap_source["url"], cheap_path)

        business_path, business = gates["route-business-short-redirect"]
        business_source = routes[business["route_id"]]
        self.assertEqual("redirect", business["indexing_policy"])
        self.assertNotEqual(business_path, business["expected_redirect_target"])
        self.assertEqual(
            owners[business["current_owner_id"]]["canonical_url"],
            business["expected_redirect_target"],
        )
        self.assertEqual("business-in-thailand", business["candidate"])
        self.assertEqual("target_review_pending", business["state"])
        self.assertEqual(
            business_source["redirect_target"], business["expected_redirect_target"]
        )

    def test_builder_is_deterministic_and_path_independent(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            temporary = Path(temporary_directory)
            registry_copy = temporary / "registry.json"
            output = temporary / "migration-gates.php"
            registry_copy.write_bytes(REGISTRY_PATH.read_bytes())

            first = run_builder(
                "--registry", str(registry_copy), "--output", str(output)
            )
            self.assertEqual(0, first.returncode, first.stderr or first.stdout)
            first_payload = output.read_bytes()

            second = run_builder(
                "--registry", str(registry_copy), "--output", str(output)
            )
            self.assertEqual(0, second.returncode, second.stderr or second.stdout)
            self.assertEqual(first_payload, output.read_bytes())
            self.assertEqual(OUTPUT_PATH.read_bytes(), output.read_bytes())
            self.assertNotIn(str(temporary).encode("utf-8"), output.read_bytes())

    def test_check_rejects_a_stale_generated_file(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output = Path(temporary_directory) / "migration-gates.php"
            built = run_builder("--output", str(output))
            self.assertEqual(0, built.returncode, built.stderr or built.stdout)
            output.write_bytes(output.read_bytes() + b"\n/* stale */\n")

            checked = run_builder("--check", "--output", str(output))
            self.assertNotEqual(0, checked.returncode)
            self.assertIn("stale", checked.stderr.lower())

    def test_check_rejects_registry_drift(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            temporary = Path(temporary_directory)
            registry_copy = temporary / "registry.json"
            output = temporary / "migration-gates.php"
            registry_copy.write_bytes(REGISTRY_PATH.read_bytes())
            built = run_builder(
                "--registry", str(registry_copy), "--output", str(output)
            )
            self.assertEqual(0, built.returncode, built.stderr or built.stdout)

            changed = load_registry(registry_copy)
            changed["registry_version"] = "2.0.0-local-drift"
            registry_copy.write_text(
                json.dumps(changed, ensure_ascii=False, indent=2) + "\n",
                encoding="utf-8",
            )
            checked = run_builder(
                "--check", "--registry", str(registry_copy), "--output", str(output)
            )
            self.assertNotEqual(0, checked.returncode)
            self.assertIn("stale", checked.stderr.lower())

    def test_contract_files_have_no_long_dash_characters(self) -> None:
        forbidden = (chr(0x2013), chr(0x2014))
        for path in (BUILDER_PATH, OUTPUT_PATH, Path(__file__), LIVE_SCRIPT_PATH):
            text = path.read_text(encoding="utf-8")
            for character in forbidden:
                self.assertNotIn(character, text, str(path))


if __name__ == "__main__":
    unittest.main(verbosity=2)
