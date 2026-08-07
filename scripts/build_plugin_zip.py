#!/usr/bin/env python3
"""Build, test, and verify a deterministic Thailand Platform plugin ZIP."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import zipfile
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath
from typing import Any


PLUGIN_SLUG = "thailand-platform"
FIXED_TIMESTAMP = (1980, 1, 1, 0, 0, 0)
FORBIDDEN_NAMES = {
    ".env",
    ".npmrc",
    ".pypirc",
    ".htaccess",
    "auth.json",
    "cookies.txt",
    "credentials.json",
    "id_rsa",
    "wp-config.php",
}
FORBIDDEN_SUFFIXES = {".key", ".log", ".map", ".p12", ".pem", ".pfx", ".sql", ".sqlite"}
SECRET_PATTERNS = {
    "private_key": re.compile(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    "authorization_header": re.compile(rb"(?i)authorization\s*:\s*(?:basic|bearer)\s+\S+"),
    "github_token": re.compile(rb"\bgh(?:p|o|u|s|r)_[A-Za-z0-9]{30,}\b"),
    "aws_access_key": re.compile(rb"\b(?:AKIA|ASIA)[A-Z0-9]{16}\b"),
    "stripe_secret": re.compile(rb"\bsk_(?:live|test)_[A-Za-z0-9]{20,}\b"),
    "database_password": re.compile(rb"(?i)define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"][^'\"]+['\"]"),
}


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def json_no_duplicates(path: Path) -> dict[str, Any]:
    def reject(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
        result: dict[str, Any] = {}
        for key, value in pairs:
            if key in result:
                raise ValueError(f"duplicate JSON key in {path.name}: {key}")
            result[key] = value
        return result

    def reject_constant(value: str) -> None:
        raise ValueError(f"non-finite JSON value in {path.name}: {value}")

    parsed = json.loads(
        path.read_text(encoding="utf-8"),
        object_pairs_hook=reject,
        parse_constant=reject_constant,
    )
    if not isinstance(parsed, dict):
        raise ValueError(f"{path.name} must contain a JSON object")
    return parsed


def read_inventory(root: Path) -> list[str]:
    inventory_path = root / "package-files.txt"
    entries = [
        line.strip()
        for line in inventory_path.read_text(encoding="utf-8").splitlines()
        if line.strip() and not line.lstrip().startswith("#")
    ]

    if entries != sorted(entries):
        raise ValueError("package-files.txt must be sorted")
    if len(entries) != len(set(entries)):
        raise ValueError("package-files.txt contains duplicates")
    if not entries:
        raise ValueError("package-files.txt is empty")

    for entry in entries:
        posix = PurePosixPath(entry)
        lower_name = posix.name.lower()
        if posix.is_absolute() or ".." in posix.parts or "\\" in entry:
            raise ValueError(f"unsafe package path: {entry}")
        if lower_name in FORBIDDEN_NAMES or lower_name.startswith(".env."):
            raise ValueError(f"forbidden package filename: {entry}")
        if posix.suffix.lower() in FORBIDDEN_SUFFIXES:
            raise ValueError(f"forbidden package suffix: {entry}")

        source = root / Path(*posix.parts)
        if not source.is_file() or source.is_symlink():
            raise ValueError(f"missing, non-file, or symlinked package entry: {entry}")

        payload = source.read_bytes()
        for label, pattern in SECRET_PATTERNS.items():
            if pattern.search(payload):
                raise ValueError(f"secret scanner match ({label}) in package entry: {entry}")

    return entries


def validate_vendor_tree(root: Path) -> dict[str, Any]:
    vendor_root = root / "lib" / "plugin-update-checker"
    manifest_path = vendor_root / "VENDOR-MANIFEST.sha256"
    manifest_entries: dict[str, str] = {}

    for line in manifest_path.read_text(encoding="utf-8").splitlines():
        match = re.fullmatch(r"([0-9a-f]{64})  ([^\\]+)", line)
        if not match:
            raise ValueError("vendor manifest contains an invalid line")
        digest, relative = match.groups()
        path = PurePosixPath(relative)
        if path.is_absolute() or ".." in path.parts or relative in manifest_entries:
            raise ValueError(f"vendor manifest contains an unsafe or duplicate path: {relative}")
        manifest_entries[relative] = digest

    actual_paths = sorted(
        path.relative_to(vendor_root).as_posix()
        for path in vendor_root.rglob("*")
        if path.is_file() and path.name not in {"VENDOR-MANIFEST.sha256", "VENDOR-RECEIPT.md"}
    )
    if list(manifest_entries) != actual_paths:
        raise ValueError("vendor manifest inventory disagrees with the vendored upstream tree")

    total_bytes = 0
    for relative, expected_hash in manifest_entries.items():
        source = vendor_root / Path(*PurePosixPath(relative).parts)
        payload = source.read_bytes()
        total_bytes += len(payload)
        if sha256_bytes(payload) != expected_hash:
            raise ValueError(f"vendor file hash mismatch: {relative}")

    manifest_payload = manifest_path.read_bytes()
    return {
        "files": len(manifest_entries),
        "bytes": total_bytes,
        "manifest": "lib/plugin-update-checker/VENDOR-MANIFEST.sha256",
        "manifest_sha256": sha256_bytes(manifest_payload),
        "upstream_commit": "a2db6871deec989a74e1f90fafc6d58ae526a879",
        "upstream_tag": "v5.6",
    }


def header_value(text: str, label: str) -> str:
    match = re.search(rf"^\s*(?:\*\s*)?{re.escape(label)}:\s*(.+?)\s*$", text, re.MULTILINE)
    if not match:
        raise ValueError(f"missing plugin header: {label}")
    return match.group(1).strip()


def read_release_contract(root: Path) -> dict[str, str]:
    main = (root / "thailand-platform.php").read_text(encoding="utf-8")
    readme = (root / "readme.txt").read_text(encoding="utf-8")
    manifest = json_no_duplicates(root / "release.json")

    manifest_keys = {
        "author",
        "download_url",
        "homepage",
        "last_updated",
        "name",
        "requires",
        "requires_php",
        "sections",
        "slug",
        "tested",
        "version",
    }
    if set(manifest) != manifest_keys:
        raise ValueError("release manifest fields are missing or unexpected")

    version = header_value(main, "Version")
    requires = header_value(main, "Requires at least")
    requires_php = header_value(main, "Requires PHP")
    constant = re.search(
        r"define\(\s*'THAILAND_PLATFORM_VERSION'\s*,\s*'([0-9]+(?:\.[0-9]+)+)'\s*\);",
        main,
    )
    if not constant or constant.group(1) != version:
        raise ValueError("plugin header and version constant disagree")

    readme_fields = {
        "version": header_value(readme, "Stable tag"),
        "requires": header_value(readme, "Requires at least"),
        "tested": header_value(readme, "Tested up to"),
        "requires_php": header_value(readme, "Requires PHP"),
    }
    expected = {
        "name": "Thailand Platform",
        "slug": PLUGIN_SLUG,
        "version": version,
        "author": "thai-land.co.il",
        "homepage": "https://thai-land.co.il/",
        "requires": requires,
        "tested": readme_fields["tested"],
        "requires_php": requires_php,
        "download_url": (
            "https://raw.githubusercontent.com/The-new-ben/thai-land-platform/main/"
            f"plugin-dist/{version}/{PLUGIN_SLUG}-{version}.zip"
        ),
    }

    if readme_fields["version"] != version:
        raise ValueError("readme stable tag disagrees with plugin version")
    if readme_fields["requires"] != requires:
        raise ValueError("readme WordPress minimum disagrees with plugin header")
    if readme_fields["requires_php"] != requires_php:
        raise ValueError("readme PHP minimum disagrees with plugin header")

    for key, value in expected.items():
        if manifest.get(key) != value:
            raise ValueError(f"release manifest field disagrees: {key}")
    if not isinstance(manifest.get("last_updated"), str) or not manifest["last_updated"].strip():
        raise ValueError("release manifest last_updated is missing")
    sections = manifest.get("sections")
    if (
        not isinstance(sections, dict)
        or set(sections) != {"changelog"}
        or not isinstance(sections.get("changelog"), str)
    ):
        raise ValueError("release manifest changelog is missing")

    return {
        "version": version,
        "requires": requires,
        "tested": readme_fields["tested"],
        "requires_php": requires_php,
    }


def resolve_php_binary(requested: str | None) -> Path:
    candidate = requested or shutil.which("php")
    if not candidate:
        raise ValueError("PHP executable was not found")
    resolved = Path(candidate).resolve()
    if not resolved.is_file():
        raise ValueError(f"PHP executable is not a file: {resolved}")
    return resolved


def run_checked(command: list[str], cwd: Path) -> str:
    completed = subprocess.run(command, cwd=cwd, capture_output=True, text=True, check=False)
    output = (completed.stdout + completed.stderr).strip()
    if completed.returncode != 0:
        raise ValueError(f"QA command failed ({completed.returncode}): {' '.join(command)}\n{output}")
    return output


def run_qa(root: Path, entries: list[str], php_bin: Path) -> dict[str, Any]:
    php_files = [entry for entry in entries if entry.lower().endswith(".php")]
    for entry in php_files:
        run_checked([str(php_bin), "-l", str(root / Path(*PurePosixPath(entry).parts))], root)

    test_output = run_checked([str(php_bin), str(root / "tests" / "run.php")], root)
    php_version = run_checked([str(php_bin), "--version"], root).splitlines()[0]

    return {
        "php_binary": {
            "name": php_bin.name,
            "sha256": sha256_bytes(php_bin.read_bytes()),
        },
        "php_runtime": php_version,
        "php_lint": "pass",
        "php_files_linted": len(php_files),
        "contract_tests": "pass",
        "contract_test_output": test_output,
    }


def build_zip(root: Path, output: Path, entries: list[str]) -> None:
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for entry in entries:
            source = root / Path(*PurePosixPath(entry).parts)
            member = f"{PLUGIN_SLUG}/{entry}"
            info = zipfile.ZipInfo(member, FIXED_TIMESTAMP)
            info.create_system = 3
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def verify_zip(output: Path, root: Path, entries: list[str], version: str) -> dict[str, Any]:
    expected = [f"{PLUGIN_SLUG}/{entry}" for entry in entries]
    with zipfile.ZipFile(output, "r") as archive:
        names = archive.namelist()
        if names != expected:
            raise ValueError("ZIP inventory or ordering mismatch")
        if archive.testzip() is not None:
            raise ValueError("ZIP integrity test failed")
        if any("\\" in name or not name.startswith(f"{PLUGIN_SLUG}/") for name in names):
            raise ValueError("ZIP contains an unexpected root or path separator")

        for entry in entries:
            member = f"{PLUGIN_SLUG}/{entry}"
            source = root / Path(*PurePosixPath(entry).parts)
            if archive.read(member) != source.read_bytes():
                raise ValueError(f"ZIP payload disagrees with source: {entry}")

        main = archive.read(f"{PLUGIN_SLUG}/thailand-platform.php").decode("utf-8")
        if f"Version: {version}" not in main:
            raise ValueError("ZIP is missing the expected plugin header version")
        if f"'THAILAND_PLATFORM_VERSION', '{version}'" not in main:
            raise ValueError("ZIP is missing the expected version constant")

    payload = output.read_bytes()
    return {
        "bytes": len(payload),
        "inventory": expected,
        "inventory_count": len(expected),
        "path": output.as_posix(),
        "sha256": sha256_bytes(payload),
        "slug": PLUGIN_SLUG,
        "version": version,
    }


def atomic_write_text(path: Path, contents: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("w", encoding="utf-8", dir=path.parent, delete=False) as handle:
        handle.write(contents)
        temporary = Path(handle.name)
    temporary.replace(path)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--out", type=Path)
    parser.add_argument("--php-bin")
    parser.add_argument("--receipt-artifact-path")
    parser.add_argument("--source-commit", default=os.environ.get("THAILAND_SOURCE_COMMIT"))
    args = parser.parse_args()

    if (
        not args.source_commit
        or not re.fullmatch(r"[0-9a-f]{40}", args.source_commit)
        or args.source_commit == "0" * 40
    ):
        raise ValueError("--source-commit must be a full lowercase 40-character Git commit")

    root = args.root.resolve()
    contract = read_release_contract(root)
    entries = read_inventory(root)
    vendor = validate_vendor_tree(root)
    php_bin = resolve_php_binary(args.php_bin)
    qa = run_qa(root, entries, php_bin)
    output = (args.out or root / "plugin-dist" / f"{PLUGIN_SLUG}-{contract['version']}.zip").resolve()
    output.parent.mkdir(parents=True, exist_ok=True)

    temporary_handle = tempfile.NamedTemporaryFile(dir=output.parent, suffix=".zip", delete=False)
    temporary_handle.close()
    temporary_output = Path(temporary_handle.name)
    try:
        build_zip(root, temporary_output, entries)
        receipt = verify_zip(temporary_output, root, entries, contract["version"])
        temporary_output.replace(output)
        receipt["path"] = args.receipt_artifact_path or output.name
    finally:
        if temporary_output.exists():
            temporary_output.unlink()

    receipt.update(
        {
            "built_at": datetime.now(timezone.utc).isoformat(),
            "deterministic_zip": True,
            "builder": {
                "python_executable": {
                    "name": Path(sys.executable).name,
                    "sha256": sha256_bytes(Path(sys.executable).read_bytes()),
                },
                "python_runtime": sys.version.splitlines()[0],
                "script_sha256": sha256_bytes(Path(__file__).read_bytes()),
            },
            "qa": qa,
            "release_contract": contract,
            "secret_scan": {
                "matches": 0,
                "patterns": sorted(SECRET_PATTERNS),
                "result": "pass",
            },
            "source_commit": args.source_commit,
            "vendor": vendor,
        }
    )
    receipt_path = output.with_suffix(".receipt.json")
    atomic_write_text(
        receipt_path,
        json.dumps(receipt, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
    )
    print(json.dumps(receipt, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
