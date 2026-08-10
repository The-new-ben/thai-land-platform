#!/usr/bin/env python3
"""Strictly verify a Thailand Platform release receipt and artifact."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import zipfile
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any


SLUG = "thailand-platform"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def parse_json(path: Path) -> dict[str, Any]:
    def pairs_hook(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
        result: dict[str, Any] = {}
        for key, value in pairs:
            if key in result:
                raise ValueError(f"duplicate JSON key: {key}")
            result[key] = value
        return result

    def reject_constant(value: str) -> None:
        raise ValueError(f"non-finite JSON value: {value}")

    value = json.loads(
        path.read_text(encoding="utf-8"),
        object_pairs_hook=pairs_hook,
        parse_constant=reject_constant,
    )
    if not isinstance(value, dict):
        raise ValueError("receipt must be a JSON object")
    return value


def exact_keys(value: dict[str, Any], expected: set[str], label: str) -> None:
    if set(value) != expected:
        raise ValueError(f"{label} fields are missing or unexpected")


def valid_hash(value: Any) -> bool:
    return isinstance(value, str) and bool(re.fullmatch(r"[0-9a-f]{64}", value))


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--receipt", type=Path, required=True)
    parser.add_argument("--artifact", type=Path, required=True)
    parser.add_argument("--source-root", type=Path, required=True)
    parser.add_argument("--source-commit", required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--expected-path", required=True)
    parser.add_argument("--python-bin", type=Path, required=True)
    parser.add_argument("--php-bin", type=Path, required=True)
    parser.add_argument("--node-bin", type=Path, required=True)
    parser.add_argument("--max-age-minutes", type=int, default=15)
    args = parser.parse_args()

    receipt = parse_json(args.receipt.resolve())
    artifact = args.artifact.resolve()
    source_root = args.source_root.resolve()
    python_bin = args.python_bin.resolve()
    php_bin = args.php_bin.resolve()
    node_bin = args.node_bin.resolve()

    exact_keys(
        receipt,
        {
            "builder",
            "built_at",
            "bytes",
            "deterministic_zip",
            "geography",
            "inventory",
            "inventory_count",
            "path",
            "qa",
            "release_contract",
            "secret_scan",
            "sha256",
            "slug",
            "source_commit",
            "vendor",
            "version",
        },
        "receipt",
    )
    require(receipt["slug"] == SLUG, "receipt slug mismatch")
    require(receipt["version"] == args.version, "receipt version mismatch")
    require(receipt["source_commit"] == args.source_commit, "source commit mismatch")
    require(receipt["path"] == args.expected_path, "receipt artifact path mismatch")
    require(receipt["deterministic_zip"] is True, "deterministic build flag is not true")
    require(type(receipt["bytes"]) is int and receipt["bytes"] == artifact.stat().st_size, "artifact size mismatch")
    require(valid_hash(receipt["sha256"]) and receipt["sha256"] == sha256(artifact), "artifact hash mismatch")

    built_at = datetime.fromisoformat(receipt["built_at"])
    require(built_at.tzinfo is not None, "receipt timestamp lacks a timezone")
    age = datetime.now(timezone.utc) - built_at.astimezone(timezone.utc)
    require(age >= timedelta(minutes=-2), "receipt timestamp is too far in the future")
    require(age <= timedelta(minutes=args.max_age_minutes), "receipt is stale")

    inventory_entries = [
        line.strip()
        for line in (source_root / "package-files.txt").read_text(encoding="utf-8").splitlines()
        if line.strip() and not line.lstrip().startswith("#")
    ]
    expected_inventory = [f"{SLUG}/{entry}" for entry in inventory_entries]
    require(receipt["inventory"] == expected_inventory, "receipt inventory mismatch")
    require(type(receipt["inventory_count"]) is int, "inventory count type mismatch")
    require(receipt["inventory_count"] == len(expected_inventory), "inventory count mismatch")

    geography = receipt["geography"]
    require(isinstance(geography, dict), "geography evidence must be an object")
    exact_keys(
        geography,
        {
            "artifacts",
            "country_id",
            "counts",
            "dataset_version",
            "manifest",
            "manifest_sha256",
            "parity",
            "schema_version",
            "source_inputs",
            "source_manifest_sha256",
        },
        "geography evidence",
    )
    require(geography["parity"] == "pass", "geography parity did not pass")
    require(geography["schema_version"] == "1.0.0", "geography schema version mismatch")
    require(
        isinstance(geography["dataset_version"], str)
        and re.fullmatch(r"[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]+", geography["dataset_version"]) is not None,
        "geography dataset version is invalid",
    )
    require(geography["country_id"] == "geo:th:country", "geography country identity mismatch")
    require(geography["manifest"] == "resources/geography/manifest.json", "geography manifest path mismatch")

    geography_manifest_path = source_root / geography["manifest"]
    geography_manifest = parse_json(geography_manifest_path)
    exact_keys(
        geography_manifest,
        {
            "artifacts",
            "country_id",
            "counts",
            "dataset_version",
            "entity_type_counts",
            "normalization",
            "schema_version",
            "source_inputs",
            "source_manifest_sha256",
        },
        "geography manifest",
    )
    require(valid_hash(geography["manifest_sha256"]), "geography manifest hash is invalid")
    require(geography["manifest_sha256"] == sha256(geography_manifest_path), "geography manifest hash mismatch")
    for field in (
        "artifacts",
        "country_id",
        "counts",
        "dataset_version",
        "schema_version",
        "source_inputs",
        "source_manifest_sha256",
    ):
        require(geography[field] == geography_manifest[field], f"geography manifest disagrees: {field}")

    counts = geography["counts"]
    require(isinstance(counts, dict), "geography counts must be an object")
    exact_keys(
        counts,
        {"alias_candidates", "alias_keys", "entities", "provinces", "regions", "relations"},
        "geography counts",
    )
    require(counts["entities"] == 85, "geography entity count mismatch")
    require(counts["provinces"] == 77, "geography province count mismatch")
    require(counts["regions"] == 7, "geography region count mismatch")
    require(counts["relations"] == 154, "geography relation count mismatch")
    require(
        type(counts["alias_candidates"]) is int
        and type(counts["alias_keys"]) is int
        and counts["alias_candidates"] >= counts["alias_keys"] > 0,
        "geography alias counts are invalid",
    )
    require(
        geography_manifest["entity_type_counts"] == {"country": 1, "province": 77, "statistical_region": 7},
        "geography entity type counts mismatch",
    )

    expected_sources = {
        "aliases.csv",
        "geometry.json",
        "normalization-vectors.json",
        "provinces.csv",
        "regions.json",
        "registry.json",
        "registry.schema.json",
        "relations.json",
    }
    source_inputs = geography["source_inputs"]
    require(isinstance(source_inputs, dict), "geography source evidence must be an object")
    exact_keys(source_inputs, expected_sources, "geography source evidence")
    for relative, evidence in source_inputs.items():
        require(isinstance(evidence, dict), f"geography source record is invalid: {relative}")
        exact_keys(evidence, {"bytes", "sha256"}, f"geography source record {relative}")
        source_path = source_root / "data" / "geography" / relative
        require(source_path.is_file() and not source_path.is_symlink(), f"geography source is missing or unsafe: {relative}")
        require(type(evidence["bytes"]) is int and evidence["bytes"] == source_path.stat().st_size, f"geography source size mismatch: {relative}")
        require(valid_hash(evidence["sha256"]) and evidence["sha256"] == sha256(source_path), f"geography source hash mismatch: {relative}")
    require(
        geography["source_manifest_sha256"] == source_inputs["registry.json"]["sha256"],
        "geography source manifest lineage mismatch",
    )

    expected_geography_artifacts = {
        "assets/geography/core.json",
        "resources/geography/registry.php",
    }
    geography_artifacts = geography["artifacts"]
    require(isinstance(geography_artifacts, dict), "geography artifact evidence must be an object")
    exact_keys(geography_artifacts, expected_geography_artifacts, "geography artifact evidence")
    for relative, evidence in geography_artifacts.items():
        require(isinstance(evidence, dict), f"geography artifact record is invalid: {relative}")
        exact_keys(evidence, {"bytes", "sha256"}, f"geography artifact record {relative}")
        artifact_path = source_root / relative
        require(artifact_path.is_file() and not artifact_path.is_symlink(), f"geography artifact is missing or unsafe: {relative}")
        require(type(evidence["bytes"]) is int and evidence["bytes"] == artifact_path.stat().st_size, f"geography artifact size mismatch: {relative}")
        require(valid_hash(evidence["sha256"]) and evidence["sha256"] == sha256(artifact_path), f"geography artifact hash mismatch: {relative}")

    with zipfile.ZipFile(artifact, "r") as archive:
        require(archive.namelist() == expected_inventory, "ZIP inventory mismatch")
        require(archive.testzip() is None, "ZIP integrity check failed")

    qa = receipt["qa"]
    require(isinstance(qa, dict), "QA evidence must be an object")
    exact_keys(
        qa,
        {
            "bangkok_asset_compiler",
            "bangkok_data_tests",
            "bangkok_registry_compiler",
            "content_migration_ledger_compiler",
            "content_migration_ledger_compiler_output",
            "content_migration_ledger_tests",
            "content_registry_compiler",
            "contract_test_output",
            "contract_tests",
            "draft_content_inventory_tests",
            "geography_builder_tests",
            "geography_compiler",
            "guide_asset_compiler",
            "guides_runtime_test_output",
            "guides_runtime_tests",
            "homepage_asset_compiler",
            "javascript_files_checked",
            "javascript_source_sha256",
            "javascript_syntax",
            "node_binary",
            "node_runtime",
            "php_binary",
            "php_files_linted",
            "php_lint",
            "php_runtime",
            "priority_guides_compiler_test_output",
            "priority_guides_compiler_tests",
            "priority_guides_registry_compiler",
            "queued_expired_content_tests",
            "real_estate_content_tests",
            "real_estate_runtime_test_output",
            "real_estate_runtime_tests",
            "seo_registry_compiler",
            "seo_registry_tests",
            "seo_runtime_compiler",
            "seo_runtime_gate_tests",
            "tawk_state_test_output",
            "tawk_state_tests",
        },
        "QA evidence",
    )
    require(qa["php_lint"] == "pass", "PHP lint did not pass")
    require(qa["contract_tests"] == "pass", "contract tests did not pass")
    require(qa["homepage_asset_compiler"] == "pass", "homepage asset compiler parity did not pass")
    require(qa["bangkok_asset_compiler"] == "pass", "Bangkok asset compiler parity did not pass")
    require(qa["bangkok_registry_compiler"] == "pass", "Bangkok registry compiler parity did not pass")
    require(qa["bangkok_data_tests"] == "pass", "Bangkok data tests did not pass")
    require(qa["content_registry_compiler"] == "pass", "content registry compiler parity did not pass")
    require(qa["real_estate_content_tests"] == "pass", "real-estate content tests did not pass")
    require(qa["real_estate_runtime_tests"] == "pass", "real-estate runtime tests did not pass")
    require(
        qa["real_estate_runtime_test_output"]
        == "PASS: Thailand Platform release contract\nPASS: managed real-estate runtime contract",
        "real-estate runtime test output mismatch",
    )
    require(qa["draft_content_inventory_tests"] == "pass", "draft-content inventory tests did not pass")
    require(qa["content_migration_ledger_compiler"] == "pass", "content migration ledger compiler parity did not pass")
    require(
        qa["content_migration_ledger_compiler_output"] == "PASS: content migration ledger is current",
        "content migration ledger compiler output mismatch",
    )
    require(qa["content_migration_ledger_tests"] == "pass", "content migration ledger tests did not pass")
    require(qa["queued_expired_content_tests"] == "pass", "queued expired-content tests did not pass")
    require(qa["geography_compiler"] == "pass", "geography compiler parity did not pass")
    require(qa["geography_builder_tests"] == "pass", "geography builder tests did not pass")
    require(qa["guide_asset_compiler"] == "pass", "guide asset compiler parity did not pass")
    require(qa["priority_guides_registry_compiler"] == "pass", "priority guides registry compiler parity did not pass")
    require(qa["priority_guides_compiler_tests"] == "pass", "priority guides compiler tests did not pass")
    require(
        qa["priority_guides_compiler_test_output"] == "PASS: priority guides compiler tests",
        "priority guides compiler test output mismatch",
    )
    require(qa["guides_runtime_tests"] == "pass", "priority guides runtime tests did not pass")
    require(
        qa["guides_runtime_test_output"] == "PASS: priority guides runtime tests",
        "priority guides runtime test output mismatch",
    )
    require(qa["seo_registry_compiler"] == "pass", "SEO ownership registry compiler parity did not pass")
    require(qa["seo_runtime_compiler"] == "pass", "SEO runtime compiler parity did not pass")
    require(qa["seo_registry_tests"] == "pass", "SEO ownership registry tests did not pass")
    require(qa["seo_runtime_gate_tests"] == "pass", "SEO runtime gate tests did not pass")
    require(qa["javascript_syntax"] == "pass", "JavaScript syntax checks did not pass")
    require(qa["tawk_state_tests"] == "pass", "Tawk behavior tests did not pass")
    require(qa["tawk_state_test_output"] == "PASS: Tawk chat behavior", "Tawk behavior test output mismatch")
    expected_javascript_files = sorted(
        [entry for entry in inventory_entries if entry.lower().endswith(".js")]
        + [
            "scripts/live_guides_acceptance.cjs",
            "scripts/live_homepage_acceptance.cjs",
            "scripts/live_real_estate_acceptance.cjs",
            "scripts/live_seo_migration_acceptance.cjs",
        ]
    )
    require(qa["javascript_files_checked"] == expected_javascript_files, "JavaScript syntax inventory mismatch")
    javascript_source_sha256 = qa["javascript_source_sha256"]
    require(isinstance(javascript_source_sha256, dict), "JavaScript source hashes must be an object")
    exact_keys(javascript_source_sha256, set(expected_javascript_files), "JavaScript source hashes")
    for relative in expected_javascript_files:
        source = source_root / relative
        require(source.is_file() and not source.is_symlink(), f"JavaScript QA source is missing or unsafe: {relative}")
        require(
            valid_hash(javascript_source_sha256[relative])
            and javascript_source_sha256[relative] == sha256(source),
            f"JavaScript QA source hash mismatch: {relative}",
        )
    require(qa["contract_test_output"] == "PASS: Thailand Platform release contract", "contract test output mismatch")
    require(type(qa["php_files_linted"]) is int and qa["php_files_linted"] > 0, "PHP lint count is invalid")
    require(isinstance(qa["php_runtime"], str) and qa["php_runtime"].startswith("PHP "), "PHP runtime is invalid")
    require(isinstance(qa["php_binary"], dict), "PHP binary evidence must be an object")
    exact_keys(qa["php_binary"], {"name", "sha256"}, "PHP binary evidence")
    require(qa["php_binary"]["name"] == php_bin.name, "PHP binary name mismatch")
    require(qa["php_binary"]["sha256"] == sha256(php_bin), "PHP binary hash mismatch")
    require(isinstance(qa["node_runtime"], str) and re.fullmatch(r"v[0-9]+\.[0-9]+\.[0-9]+(?:[-+].*)?", qa["node_runtime"]) is not None, "Node runtime is invalid")
    require(isinstance(qa["node_binary"], dict), "Node binary evidence must be an object")
    exact_keys(qa["node_binary"], {"name", "sha256"}, "Node binary evidence")
    require(qa["node_binary"]["name"] == node_bin.name, "Node binary name mismatch")
    require(qa["node_binary"]["sha256"] == sha256(node_bin), "Node binary hash mismatch")

    builder = receipt["builder"]
    require(isinstance(builder, dict), "builder evidence must be an object")
    exact_keys(builder, {"python_executable", "python_runtime", "script_sha256"}, "builder evidence")
    require(isinstance(builder["python_executable"], dict), "Python evidence must be an object")
    exact_keys(builder["python_executable"], {"name", "sha256"}, "Python evidence")
    require(builder["python_executable"]["name"] == python_bin.name, "Python binary name mismatch")
    require(builder["python_executable"]["sha256"] == sha256(python_bin), "Python binary hash mismatch")
    require(isinstance(builder["python_runtime"], str) and builder["python_runtime"], "Python runtime is invalid")
    require(builder["script_sha256"] == sha256(source_root / "scripts" / "build_plugin_zip.py"), "builder source hash mismatch")

    contract = receipt["release_contract"]
    require(isinstance(contract, dict), "release contract must be an object")
    exact_keys(contract, {"requires", "requires_php", "tested", "version"}, "release contract")
    require(contract["version"] == args.version, "release contract version mismatch")
    require(contract["requires"] == "6.9", "WordPress minimum mismatch")
    require(contract["tested"] == "7.0.3", "tested WordPress version mismatch")
    require(contract["requires_php"] == "7.4", "PHP minimum mismatch")

    secret_scan = receipt["secret_scan"]
    require(isinstance(secret_scan, dict), "secret scan must be an object")
    exact_keys(secret_scan, {"matches", "patterns", "result"}, "secret scan")
    require(secret_scan["result"] == "pass" and secret_scan["matches"] == 0, "secret scan did not pass")
    require(isinstance(secret_scan["patterns"], list) and len(secret_scan["patterns"]) >= 6, "secret scan coverage is too small")

    vendor = receipt["vendor"]
    require(isinstance(vendor, dict), "vendor evidence must be an object")
    exact_keys(vendor, {"bytes", "files", "manifest", "manifest_sha256", "upstream_commit", "upstream_tag"}, "vendor evidence")
    require(vendor["files"] == 116 and vendor["bytes"] == 381461, "vendor size or count mismatch")
    require(vendor["upstream_tag"] == "v5.6", "vendor tag mismatch")
    require(vendor["upstream_commit"] == "a2db6871deec989a74e1f90fafc6d58ae526a879", "vendor commit mismatch")
    require(vendor["manifest"] == "lib/plugin-update-checker/VENDOR-MANIFEST.sha256", "vendor manifest path mismatch")
    require(vendor["manifest_sha256"] == sha256(source_root / vendor["manifest"]), "vendor manifest hash mismatch")

    print("PASS: strict release receipt and artifact verification")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (OSError, ValueError, zipfile.BadZipFile) as error:
        raise SystemExit(f"REJECT: {error}")
