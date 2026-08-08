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
    parser.add_argument("--max-age-minutes", type=int, default=15)
    args = parser.parse_args()

    receipt = parse_json(args.receipt.resolve())
    artifact = args.artifact.resolve()
    source_root = args.source_root.resolve()
    python_bin = args.python_bin.resolve()
    php_bin = args.php_bin.resolve()

    exact_keys(
        receipt,
        {
            "builder",
            "built_at",
            "bytes",
            "deterministic_zip",
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

    with zipfile.ZipFile(artifact, "r") as archive:
        require(archive.namelist() == expected_inventory, "ZIP inventory mismatch")
        require(archive.testzip() is None, "ZIP integrity check failed")

    qa = receipt["qa"]
    require(isinstance(qa, dict), "QA evidence must be an object")
    exact_keys(
        qa,
        {
            "contract_test_output",
            "contract_tests",
            "php_binary",
            "php_files_linted",
            "php_lint",
            "php_runtime",
        },
        "QA evidence",
    )
    require(qa["php_lint"] == "pass", "PHP lint did not pass")
    require(qa["contract_tests"] == "pass", "contract tests did not pass")
    require(qa["contract_test_output"] == "PASS: Thailand Platform release contract", "contract test output mismatch")
    require(type(qa["php_files_linted"]) is int and qa["php_files_linted"] > 0, "PHP lint count is invalid")
    require(isinstance(qa["php_runtime"], str) and qa["php_runtime"].startswith("PHP "), "PHP runtime is invalid")
    require(isinstance(qa["php_binary"], dict), "PHP binary evidence must be an object")
    exact_keys(qa["php_binary"], {"name", "sha256"}, "PHP binary evidence")
    require(qa["php_binary"]["name"] == php_bin.name, "PHP binary name mismatch")
    require(qa["php_binary"]["sha256"] == sha256(php_bin), "PHP binary hash mismatch")

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
